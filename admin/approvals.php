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
            set_flash('success', 'Member registration ' . ($action === 'approve' ? 'approved' : 'rejected') . '.');
        }
    }
    header('Location: approvals.php');
    exit;
}

$pending = $pdo->query("SELECT * FROM users WHERE role = 'member' AND status = 'pending' ORDER BY created_at ASC")->fetchAll();

$page_title = 'Pending Approvals';
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h1>Pending Member Registrations</h1>
  <?php if (empty($pending)): ?>
    <p class="muted">No pending registrations right now.</p>
  <?php else: ?>
  <table>
    <tr><th>Name</th><th>PRC ID No.</th><th>Registered</th><th>Action</th></tr>
    <?php foreach ($pending as $p): ?>
    <tr>
      <td><?php echo htmlspecialchars($p['name']); ?></td>
      <td><?php echo htmlspecialchars($p['id_number']); ?></td>
      <td><?php echo htmlspecialchars($p['created_at']); ?></td>
      <td>
        <form method="post" class="inline" style="display:inline-block;margin-right:4px;">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
          <input type="hidden" name="action" value="approve">
          <button class="btn btn-sm btn-success" type="submit">Approve</button>
        </form>
        <form method="post" class="inline" style="display:inline-block;">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
          <input type="hidden" name="action" value="reject">
          <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Reject this registration?');">Reject</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
