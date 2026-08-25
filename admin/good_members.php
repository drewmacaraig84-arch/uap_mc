<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$allMembers = $pdo->query("SELECT u.id, u.name, u.id_number, u.status,
    COUNT(md.id) AS total_dues,
    SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
    SUM(COALESCE(md.custom_amount, d.amount)) AS total_amount,
    SUM(md.total_paid) AS total_paid_sum,
    SUM(COALESCE(md.custom_amount, d.amount)) - SUM(md.total_paid) AS remaining_balance
    FROM users u
    LEFT JOIN member_dues md ON md.user_id = u.id
    LEFT JOIN dues d ON md.due_id = d.id
    WHERE u.role = 'member' AND u.status = 'approved'
    GROUP BY u.id, u.name, u.id_number, u.status
    ORDER BY u.name ASC")->fetchAll();

$goodMembers = [];
foreach ($allMembers as $m) {
    if (is_good_member($pdo, $m['id'])) {
        $goodMembers[] = $m;
    }
}

$page_title = 'Good Standing Members • UAP Mindoro Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">COMPLIANCE &amp; CERTIFICATION</p>
    <h1>Good Standing Roster</h1>
    <p class="page-subtitle">Chapter members with up-to-date dues compliance within the active cycle and 7-day grace window.</p>
  </div>
  <div class="hero-badge" style="background: rgba(16,185,129,0.12); color: #10b981; border-color: rgba(16,185,129,0.3);">
    <?php echo icon('good_members', '', 14); ?> <span><?php echo count($goodMembers); ?> Certified Members</span>
  </div>
</div>

<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div style="font-size: 13px; color: var(--text-secondary);">
      Showing members certified for chapter elections, national credentials, and good standing certificates.
    </div>
    <a href="export_csv.php" class="btn btn-sm btn-success" style="display: inline-flex; align-items: center; gap: 6px;">
      <?php echo icon('download', '', 14); ?> <span>Export Roster</span>
    </a>
  </div>

  <?php if (empty($goodMembers)): ?>
    <div style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
      <div style="width: 54px; height: 54px; border-radius: 14px; background: rgba(245,158,11,0.1); color: var(--accent-primary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
        <?php echo icon('info', '', 28); ?>
      </div>
      <strong style="display: block; font-size: 16px; color: var(--text-primary);">No certified good members yet</strong>
      <p class="muted" style="margin-top: 4px; font-size: 13px;">Members will appear here once their dues obligations are settled on time.</p>
    </div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member Architect</th>
            <th>PRC ID Number</th>
            <th>Dues Assigned</th>
            <th>Total Paid</th>
            <th>Standing Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($goodMembers as $member): ?>
            <tr>
              <td>
                <div style="display: flex; align-items: center; gap: 8px;">
                  <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($member['name']); ?></strong>
                </div>
              </td>
              <td><code><?php echo htmlspecialchars($member['id_number']); ?></code></td>
              <td><?php echo (int)$member['total_dues']; ?> items</td>
              <td><strong style="color: #10b981;">₱<?php echo number_format((float)($member['total_paid_sum'] ?? 0), 2); ?></strong></td>
              <td>
                <span class="badge-pill badge-paid" style="display: inline-flex; align-items: center; gap: 4px;">
                  <?php echo icon('check', '', 12); ?> Good Standing
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
