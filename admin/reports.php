<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$payments = $pdo->query("
    SELECT p.id, u.name as member_name, u.id_number, d.title as due_title, p.amount_paid,
           p.method, p.reference_number, p.status, p.submitted_at, p.verified_at, r.receipt_number
    FROM payments p
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN users u ON md.user_id = u.id
    JOIN dues d ON md.due_id = d.id
    LEFT JOIN receipts r ON r.payment_id = p.id
    ORDER BY p.submitted_at DESC
")->fetchAll();

$total_verified = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'verified') $total_verified += $p['amount_paid'];
}

$page_title = 'Reports';
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h1>Reports</h1>
  <p>Total verified collections: <strong>₱<?php echo number_format($total_verified, 2); ?></strong></p>
  <a class="btn" href="export_csv.php">Export to Excel (CSV)</a>
</div>
<div class="card">
  <h2>All Payment Records</h2>
  <table>
    <tr><th>Member</th><th>Due</th><th>Amount</th><th>Method</th><th>Status</th><th>Receipt #</th><th>Submitted</th></tr>
    <?php foreach ($payments as $p): ?>
    <tr>
      <td><?php echo htmlspecialchars($p['member_name']); ?></td>
      <td><?php echo htmlspecialchars($p['due_title']); ?></td>
      <td>₱<?php echo number_format($p['amount_paid'], 2); ?></td>
      <td><?php echo strtoupper($p['method']); ?></td>
      <td><span class="badge badge-<?php echo $p['status'] === 'verified' ? 'paid' : $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
      <td><?php echo htmlspecialchars($p['receipt_number'] ?? '—'); ?></td>
      <td><?php echo htmlspecialchars($p['submitted_at']); ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
