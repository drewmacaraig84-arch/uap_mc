<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$stmt = $pdo->query("
    SELECT p.*, u.name as member_name, u.id_number,
           d.title as due_title, d.amount as due_amount,
           md.payment_type, md.installment_months, md.total_paid,
           p.installment_number
    FROM payments p
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN users u ON md.user_id = u.id
    JOIN dues d ON md.due_id = d.id
    WHERE p.status = 'pending'
    ORDER BY p.submitted_at ASC
");
$pending = $stmt->fetchAll();

$page_title = 'Pending Payments';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-hero">
  <div>
    <p class="eyebrow">Administrative overview</p>
    <h1>Pending Payments</h1>
    <p class="page-subtitle">Review incoming payments, verify submissions, and keep dues processing moving smoothly.</p>
  </div>
  <div class="hero-badge">Approval workflow</div>
</div>
<div class="stat-grid">
  <div class="stat-card">
    <span class="muted">Pending submissions</span>
    <strong><?php echo count($pending); ?></strong>
  </div>
  <div class="stat-card">
    <span class="muted">Review status</span>
    <strong>Needs attention</strong>
  </div>
</div>
<div class="card">
  <?php if (isset($_GET['done'])): ?><div class="alert alert-success">Payment updated successfully.</div><?php endif; ?>
  <?php if (empty($pending)): ?>
    <p class="muted">No pending payments right now. 🎉</p>
  <?php else: ?>
  <div class="table-shell">
  <table>
    <tr><th>Member</th><th>Due</th><th>Type</th><th>Amount Submitted</th><th>Progress</th><th>Method</th><th>Reference</th><th>Proof</th><th>Submitted</th><th>Action</th></tr>
    <?php foreach ($pending as $p):
      $remaining = round((float)$p['due_amount'] - (float)$p['total_paid'], 2);
    ?>
    <tr>
      <td>
        <?php echo htmlspecialchars($p['member_name']); ?><br>
        <span class="muted"><?php echo htmlspecialchars($p['id_number']); ?></span>
      </td>
      <td><?php echo htmlspecialchars($p['due_title']); ?></td>
      <td>
<?php
switch ($p['installment_number']) {

    case 0:
        echo '<span class="badge badge-paid">Full Payment</span>';
        break;

    case 1:
        echo '<span class="badge badge-pending">First Half</span>';
        break;

    case 2:
        echo '<span class="badge badge-warning">Second Half</span>';
        break;

    default:
        echo '<span class="badge">Unknown</span>';
}
?>
</td>
      <td>₱<?php echo number_format($p['amount_paid'], 2); ?></td>
      <td>
        <span class="muted">Paid so far: ₱<?php echo number_format($p['total_paid'], 2); ?></span><br>
        <span class="muted">Remaining: ₱<?php echo number_format($remaining, 2); ?></span>
      </td>
      <td><?php echo strtoupper($p['method']); ?></td>
      <td><?php echo htmlspecialchars($p['reference_number']); ?></td>
      <td>
        <?php if ($p['proof_image']): ?>
          <a href="../<?php echo htmlspecialchars($p['proof_image']); ?>" target="_blank">View</a>
        <?php endif; ?>
      </td>
      <td><?php echo htmlspecialchars($p['submitted_at']); ?></td>
      <td>
        <form method="post" action="verify.php" class="inline">
          <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
          <input type="hidden" name="action" value="approve">
          <button class="btn btn-sm btn-success" type="submit" onclick="return confirm('Approve this payment?');">Approve</button>
        </form>
        <form method="post" action="verify.php" class="inline">
          <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
          <input type="hidden" name="action" value="reject">
          <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Reject this payment?');">Reject</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
