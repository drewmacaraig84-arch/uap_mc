<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $user_id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['approve', 'reject'])) {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'member'");
        $stmt->execute([$new_status, $user_id]);

        if (function_exists('set_flash')) {
            set_flash('success', 'Member registration ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully.');
        }
    }
    header('Location: approvals.php');
    exit;
}

$pending = $pdo->query("SELECT * FROM users WHERE role = 'member' AND status = 'pending' ORDER BY created_at ASC")->fetchAll();

$page_title = 'Pending Member Approvals';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">MEMBERSHIP VETTING</p>
    <h1>Member Registration Approvals</h1>
    <p class="page-subtitle">Review, verify, and approve new chapter member registration applications.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('approvals', '', 14); ?> <span><?php echo count($pending); ?> Pending</span>
  </div>
</div>

<div class="card">
  <?php if (empty($pending)): ?>
    <div style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
      <div style="width: 54px; height: 54px; border-radius: 14px; background: rgba(16,185,129,0.1); color: #10b981; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
        <?php echo icon('check', '', 28); ?>
      </div>
      <strong style="display: block; font-size: 16px; color: var(--text-primary);">All registrations are reviewed!</strong>
      <p class="muted" style="margin-top: 4px; font-size: 13px;">There are no new pending member registration applications awaiting review.</p>
    </div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Applicant Name</th>
            <th>PRC ID Number</th>
            <th>Application Date</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $p): ?>
            <tr>
              <td>
                <strong style="font-size: 14px;"><?php echo htmlspecialchars($p['name']); ?></strong>
              </td>
              <td><code><?php echo htmlspecialchars($p['id_number']); ?></code></td>
              <td><span class="muted" style="font-size: 12px;"><?php echo htmlspecialchars(date('F d, Y - h:i A', strtotime($p['created_at']))); ?></span></td>
              <td style="white-space: nowrap; text-align: right;">
                <form method="post" class="inline" style="display:inline-block;margin-right:4px;"
                      data-confirm="Approve registration for <?php echo htmlspecialchars($p['name']); ?>?"
                      data-confirm-title="Approve Member Registration"
                      data-confirm-btn="Approve"
                      data-confirm-class="btn-success">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                  <input type="hidden" name="action" value="approve">
                  <button class="btn btn-sm btn-success" type="submit" style="display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('check', '', 12); ?> <span>Approve</span>
                  </button>
                </form>
                <form method="post" class="inline" style="display:inline-block;"
                      data-confirm="Reject registration for <?php echo htmlspecialchars($p['name']); ?>?"
                      data-confirm-title="Reject Member Registration"
                      data-confirm-btn="Reject"
                      data-confirm-class="btn-danger">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                  <input type="hidden" name="action" value="reject">
                  <button class="btn btn-sm btn-danger" type="submit" style="display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('x', '', 12); ?> <span>Reject</span>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
