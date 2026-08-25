<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$goodMembers = $pdo->query("SELECT u.id, u.name, u.id_number, u.status,
    COUNT(md.id) AS total_dues,
    SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
    SUM(COALESCE(md.custom_amount, d.amount)) AS total_amount,
    SUM(md.total_paid) AS total_paid_sum,
    SUM(COALESCE(md.custom_amount, d.amount)) - SUM(md.total_paid) AS remaining_balance
    FROM users u
    LEFT JOIN member_dues md ON md.user_id = u.id
    LEFT JOIN dues d ON md.due_id = d.id
    WHERE u.role = 'member'
    GROUP BY u.id, u.name, u.id_number, u.status
    HAVING (
        COUNT(md.id) = 0
        OR (
            SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) = COUNT(md.id)
            AND (SUM(COALESCE(md.custom_amount, d.amount)) - SUM(md.total_paid)) <= 0
            AND NOT EXISTS (
                SELECT 1
                FROM member_dues md2
                LEFT JOIN dues d2 ON md2.due_id = d2.id
                WHERE md2.user_id = u.id
                  AND COALESCE(md2.custom_due_date, d2.due_date) < CURDATE()
                  AND md2.total_paid < COALESCE(md2.custom_amount, d2.amount)
            )
        )
    )
    ORDER BY u.name ASC")->fetchAll();

$page_title = 'Good Members';
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h1>Good Members</h1>
  <p class="muted">Members who have consistently paid their dues on time and are not behind schedule.</p>

  <?php if (empty($goodMembers)): ?>
    <div class="alert alert-info">No good members yet.</div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>PRC ID No.</th>
            <th>Total Dues</th>
            <th>Total Paid</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($goodMembers as $member): ?>
            <tr>
              <td>
                <?php echo htmlspecialchars($member['name']); ?>
                <span class="badge badge-paid">Good Member</span>
              </td>
              <td><?php echo htmlspecialchars($member['id_number']); ?></td>
              <td><?php echo (int)$member['total_dues']; ?></td>
              <td>₱<?php echo number_format((float)($member['total_paid_sum'] ?? 0), 2); ?></td>
              <td><span class="badge badge-paid">On Time</span></td>
              <td>
                <a href="../member/certificate.php?member_id=<?php echo (int)$member['id']; ?>" target="_blank" class="btn btn-sm">📜 Certificate</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
