<?php
require_once __DIR__ . '/../includes/auth.php';
require_member();

$stmt = $pdo->prepare("
    SELECT md.id as member_due_id, md.status, md.payment_type, md.installment_months,
           md.total_paid,
           COALESCE(md.custom_title, d.title) as title,
           COALESCE(md.custom_description, d.description) as description,
           COALESCE(md.custom_amount, d.amount) as amount,
           COALESCE(md.custom_due_date, d.due_date) as due_date,
           COALESCE(md.custom_term, d.term) as term
    FROM member_dues md
    JOIN dues d ON md.due_id = d.id
    WHERE md.user_id = ?
    ORDER BY COALESCE(md.custom_due_date, d.due_date) ASC
");
$stmt->execute([current_user_id()]);
$dues = $stmt->fetchAll();

$page_title = 'My Dues';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-hero">
  <div>
    <p class="eyebrow">Member portal</p>
    <h1>My Dues</h1>
    <p class="page-subtitle">Track your obligations, monitor balances, and submit payments in a cleaner, more organized experience.</p>
  </div>
  <div class="hero-badge">
    <?php
      $isGoodMember = function_exists('is_good_member') ? is_good_member($pdo, current_user_id()) : true;
      echo $isGoodMember ? 'Good Member • In Good Standing' : 'Pending Dues Settlement';
    ?>

  </div>
</div>
<?php if ($isGoodMember): ?>
  <div class="alert alert-success" style="margin-top: 12px;">
    <strong>✅ Member in Good Standing</strong> — Your dues are fully settled for the active period.
  </div>
<?php endif; ?>

<?php
  $total_amount = 0;
  $total_paid = 0;
  $remaining_total = 0;
  foreach ($dues as $d) {
      $total_amount += round((float)$d['amount'], 2);
      $total_paid += round((float)$d['total_paid'], 2);
      $remaining_total += max(0, round((float)$d['amount'] - (float)$d['total_paid'], 2));
  }
?>
<div class="stat-grid">
  <div class="stat-card">
    <span class="muted">Total dues</span>
    <strong>₱<?php echo number_format($total_amount, 2); ?></strong>
  </div>
  <div class="stat-card">
    <span class="muted">Paid so far</span>
    <strong>₱<?php echo number_format($total_paid, 2); ?></strong>
  </div>
  <div class="stat-card">
    <span class="muted">Outstanding balance</span>
    <strong>₱<?php echo number_format($remaining_total, 2); ?></strong>
  </div>
</div>
<div class="card">
  <?php if (empty($dues)): ?>
    <p class="muted">No dues have been assigned to you yet.</p>
  <?php else: ?>
  <div class="table-shell">
  <table>
    <tr><th>Title</th><th>Total Amount</th><th>Paid</th><th>Remaining</th><th>Due Date</th><th>Status</th><th></th></tr>
    <?php foreach ($dues as $d):
      $remaining = round((float)$d['amount'] - (float)$d['total_paid'], 2);
    ?>
    <tr>
      <td>
        <?php echo htmlspecialchars($d['title']); ?>
        <?php if ($d['description']): ?><br><span class="muted"><?php echo htmlspecialchars($d['description']); ?></span><?php endif; ?>
        <?php if ($d['payment_type'] === 'partial' && $d['installment_months']): ?>
          <br><span class="muted"><?php echo $d['installment_months']; ?>-month installment</span>
        <?php endif; ?>
      </td>
      <td>₱<?php echo number_format($d['amount'], 2); ?></td>
      <td style="color:#1e7e34;font-weight:600;">₱<?php echo number_format($d['total_paid'], 2); ?></td>
      <td style="color:<?php echo $remaining > 0 ? '#b3261e' : '#1e7e34'; ?>;font-weight:600;">
        ₱<?php echo number_format($remaining, 2); ?>
      </td>
      <td><?php echo $d['due_date'] ? htmlspecialchars($d['due_date']) : '—'; ?></td>
      <td>
        <?php
          $badge = $d['status'];
          $label = match($d['status']) {
            'unpaid' => 'Unpaid',
            'pending' => 'Pending Verification',
            'partial' => 'Partially Paid',
            'paid' => 'Fully Paid',
            'rejected' => 'Rejected',
            default => ucfirst($d['status'])
          };
        ?>
        <span class="badge badge-<?php echo $badge === 'partial' ? 'pending' : $badge; ?>"><?php echo $label; ?></span>
      </td>
      <td>
        <?php if (in_array($d['status'], ['unpaid','rejected','partial'])): ?>
          <a class="btn btn-sm" href="pay.php?member_due_id=<?php echo $d['member_due_id']; ?>">
            <?php echo $d['status'] === 'partial' ? 'Pay Next Installment' : 'Pay Now'; ?>
          </a>
        <?php elseif ($d['status'] === 'pending'): ?>
          <span class="muted">Awaiting verification</span>
        <?php else: ?>
          <span class="muted">✓ Paid</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
