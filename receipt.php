<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$payment_id = (int)($_GET['payment_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, r.receipt_number, r.issued_at, u.name as member_name, u.id_number, d.title as due_title
    FROM payments p
    JOIN receipts r ON r.payment_id = p.id
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN users u ON md.user_id = u.id
    JOIN dues d ON md.due_id = d.id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$r = $stmt->fetch();

if (!$r) { die('Receipt not found.'); }

// Members can only view their own receipt; admins can view any
if ($_SESSION['role'] === 'member') {
    $check = $pdo->prepare("SELECT u.id FROM payments p JOIN member_dues md ON p.member_due_id = md.id JOIN users u ON md.user_id = u.id WHERE p.id = ?");
    $check->execute([$payment_id]);
    if ($check->fetchColumn() != current_user_id()) {
        die('Not authorized.');
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Receipt <?php echo htmlspecialchars($r['receipt_number']); ?></title>
<style>
  body { font-family: Arial, sans-serif; padding:40px; max-width:600px; margin:auto; }
  .header { text-align:center; border-bottom:2px solid #1d3557; padding-bottom:16px; margin-bottom:24px; }
  .row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eee; }
  .label { color:#666; }
  .total { font-size:20px; font-weight:700; text-align:right; margin-top:20px; }
  .footer { margin-top:40px; text-align:center; color:#999; font-size:12px; }
  @media print { .no-print { display:none; } }
</style>
</head>
<body>
  <div class="header">
    <h2>OFFICIAL RECEIPT</h2>
    <p><strong><?php echo htmlspecialchars($r['receipt_number']); ?></strong></p>
  </div>
  <div class="row"><span class="label">Member</span><span><?php echo htmlspecialchars($r['member_name']); ?></span></div>
  <div class="row"><span class="label">PRC ID No.</span><span><?php echo htmlspecialchars($r['id_number']); ?></span></div>
  <div class="row"><span class="label">For</span><span><?php echo htmlspecialchars($r['due_title']); ?></span></div>
  <div class="row"><span class="label">Payment Method</span><span><?php echo strtoupper($r['method']); ?></span></div>
  <div class="row"><span class="label">Reference Number</span><span><?php echo htmlspecialchars($r['reference_number']); ?></span></div>
  <div class="row"><span class="label">Date Issued</span><span><?php echo htmlspecialchars($r['issued_at']); ?></span></div>
  <div class="total">Amount Paid: ₱<?php echo number_format($r['amount_paid'], 2); ?></div>
  <div class="footer">This is a system-generated receipt.</div>
  <p class="no-print" style="text-align:center;margin-top:20px;"><button onclick="window.print()">Print</button></p>
</body>
</html>
