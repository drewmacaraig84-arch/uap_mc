<?php
require_once __DIR__ . '/../includes/auth.php';
require_member();

$stmt = $pdo->prepare("
    SELECT p.*, d.title, d.amount as due_amount, md.total_paid, md.installment_months,
           md.payment_type, r.receipt_number
    FROM payments p
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN dues d ON md.due_id = d.id
    LEFT JOIN receipts r ON r.payment_id = p.id
    WHERE md.user_id = ?
    ORDER BY p.submitted_at DESC
");
$stmt->execute([current_user_id()]);
$payments = $stmt->fetchAll();

$page_title = 'Payment History';
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h1>Payment History</h1>
  <?php if (empty($payments)): ?>
    <p class="muted">No payments submitted yet.</p>
  <?php else: ?>
  <table>
    <tr><th>Due</th><th>Type</th><th>Amount Paid</th><th>Method</th><th>Reference</th><th>Status</th><th>Submitted</th><th></th></tr>
    <?php foreach ($payments as $p): ?>
    <tr>
      <td><?php echo htmlspecialchars($p['title']); ?></td>
      <td>
        <?php if ($p['payment_type'] === 'partial'): ?>
          Installment <?php echo $p['installment_number']; ?>
          <?php if ($p['installment_months']): ?>(<?php echo $p['installment_months']; ?>-mo plan)<?php endif; ?>
        <?php else: ?>
          Full Payment
        <?php endif; ?>
      </td>
      <td>₱<?php echo number_format($p['amount_paid'], 2); ?></td>
      <td><?php echo strtoupper($p['method']); ?></td>
      <td><?php echo htmlspecialchars($p['reference_number']); ?></td>
      <td>
        <span class="badge badge-<?php echo $p['status'] === 'verified' ? 'paid' : $p['status']; ?>">
          <?php echo ucfirst($p['status']); ?>
        </span>
      </td>
      <td><?php echo htmlspecialchars($p['submitted_at']); ?></td>
      <td>
        <?php if ($p['receipt_number']): ?>
          <a class="btn btn-sm" href="../receipt.php?payment_id=<?php echo $p['id']; ?>" target="_blank">Receipt</a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
