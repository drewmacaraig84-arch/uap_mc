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

$page_title = 'My Dues • UAP Mindoro Chapter';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-hero">
  <div>
    <p class="eyebrow">MEMBER OBLIGATIONS</p>
    <h1>My Chapter Dues &amp; Obligations</h1>
    <p class="page-subtitle">Track your assigned chapter dues, monitor payment progress, and submit payment proofs online.</p>
  </div>
  <div class="hero-badge">
    <?php
      $isGoodMember = function_exists('is_good_member') ? is_good_member($pdo, current_user_id()) : true;
      echo $isGoodMember ? (icon('good_members', '', 14) . ' <span>Good Standing</span>') : (icon('clock', '', 14) . ' <span>Pending Settlement</span>');
    ?>
  </div>
</div>

<?php if ($isGoodMember): ?>
  <div class="alert alert-success" style="margin-top: 12px; display: flex; align-items: center; gap: 8px;">
    <?php echo icon('check', '', 18); ?>
    <span><strong>Member in Good Standing</strong> &mdash; Your dues are fully settled for the active chapter period.</span>
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
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
  <div class="stat-card" style="border-top: 3px solid #3b82f6;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Total Assigned Dues</span>
      <div class="stat-card-icon" style="background: rgba(59,130,246,0.12); color: #3b82f6;">
        <?php echo icon('dues', '', 18); ?>
      </div>
    </div>
    <strong>₱<?php echo number_format($total_amount, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">All assigned obligations</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid #10b981;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Settled Amount</span>
      <div class="stat-card-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
        <?php echo icon('check', '', 18); ?>
      </div>
    </div>
    <strong style="color: #10b981;">₱<?php echo number_format($total_paid, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Verified collections</span>
  </div>

  <div class="stat-card" style="border-top: 3px solid <?php echo $remaining_total > 0 ? '#ef4444' : '#10b981'; ?>;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Outstanding Balance</span>
      <div class="stat-card-icon" style="background: <?php echo $remaining_total > 0 ? 'rgba(239,68,68,0.12)' : 'rgba(16,185,129,0.12)'; ?>; color: <?php echo $remaining_total > 0 ? '#ef4444' : '#10b981'; ?>;">
        <?php echo icon('wallet', '', 18); ?>
      </div>
    </div>
    <strong style="color: <?php echo $remaining_total > 0 ? '#ef4444' : '#10b981'; ?>;">₱<?php echo number_format($remaining_total, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Remaining payment required</span>
  </div>
</div>

<div class="card">
  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
    <h2 style="font-size: 17px; margin: 0;">My Assigned Dues List</h2>
    <span class="muted" style="font-size: 12px;"><?php echo count($dues); ?> items</span>
  </div>

  <?php if (empty($dues)): ?>
    <p class="muted" style="text-align: center; padding: 32px;">No dues have been assigned to your account yet.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Due Package</th>
            <th>Total Amount</th>
            <th>Paid</th>
            <th>Remaining</th>
            <th>Due Date</th>
            <th>Status</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dues as $d):
            $remaining = round((float)$d['amount'] - (float)$d['total_paid'], 2);
          ?>
            <tr>
              <td>
                <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($d['title']); ?></strong>
                <?php if ($d['description']): ?><br><span class="muted" style="font-size: 11.5px;"><?php echo htmlspecialchars($d['description']); ?></span><?php endif; ?>
                <?php if ($d['payment_type'] === 'partial' && $d['installment_months']): ?>
                  <br><span class="badge-pill badge-partial" style="font-size: 10px; margin-top: 2px;"><?php echo $d['installment_months']; ?>-month installment</span>
                <?php endif; ?>
              </td>
              <td>₱<?php echo number_format($d['amount'], 2); ?></td>
              <td><strong style="color:#10b981;">₱<?php echo number_format($d['total_paid'], 2); ?></strong></td>
              <td>
                <strong style="color:<?php echo $remaining > 0 ? '#ef4444' : '#10b981'; ?>;">
                  ₱<?php echo number_format(max(0, $remaining), 2); ?>
                </strong>
              </td>
              <td><?php echo $d['due_date'] ? date('M d, Y', strtotime($d['due_date'])) : '<span class="muted">—</span>'; ?></td>
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
                <span class="badge-pill badge-<?php echo $badge === 'partial' ? 'pending' : ($badge === 'rejected' ? 'unpaid' : $badge); ?>">
                  <?php echo $label; ?>
                </span>
              </td>
              <td style="text-align: right;">
                <?php if (in_array($d['status'], ['unpaid','rejected','partial'])): ?>
                  <a class="btn btn-sm" href="pay.php?member_due_id=<?php echo $d['member_due_id']; ?>" style="display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('payments', '', 12); ?> <span><?php echo $d['status'] === 'partial' ? 'Pay Next' : 'Pay Now'; ?></span>
                  </a>
                <?php elseif ($d['status'] === 'pending'): ?>
                  <span class="muted" style="font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('clock', '', 12); ?> Awaiting review
                  </span>
                <?php else: ?>
                  <span class="badge-pill badge-paid" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('check', '', 11); ?> Settled
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
