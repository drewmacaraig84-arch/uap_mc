<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $user_id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'member'");
    $stmt->execute([$user_id]);
    $target = $stmt->fetch();

    if (!$target) {
        $error = 'Member not found.';
    } elseif ($action === 'update_info') {
        $name = trim($_POST['name'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $status = $_POST['status'] ?? 'pending';

        if (!$name || !$id_number) {
            $error = 'Name and PRC ID No. are required.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $check->execute([$id_number, $user_id]);
            if ($check->fetch()) {
                $error = 'That PRC ID No. is already used by another account.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, id_number = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $id_number, $status, $user_id]);
                $success = 'Account updated successfully.';
            }
        }
    } elseif ($action === 'reset_password') {
        $new_password = $_POST['new_password'] ?? '';
        if (strlen($new_password) < 4) {
            $error = 'New password must be at least 4 characters.';
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            $success = 'Password reset for ' . htmlspecialchars($target['name']) . '. Give them the new password.';
        }
    }
}

// Search
$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'member' AND (name LIKE ? OR id_number LIKE ?) ORDER BY name ASC");
    $like = "%$search%";
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'member' ORDER BY name ASC");
}
$members = $stmt->fetchAll();

$page_title = 'Account Manager';
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h1>Member Account Manager</h1>
  <p class="muted">Edit member details, change their account status, or reset their passwords.</p>
  <form method="get" style="display:flex;gap:8px;margin-top:12px;">
    <input name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or PRC ID No..." style="flex:1;">
    <button class="btn" type="submit">Search</button>
    <?php if ($search): ?><a href="account_manager.php" class="btn" style="background:#6b7280;">Clear</a><?php endif; ?>
  </form>
  <?php if ($error): ?><div class="alert alert-error" style="margin-top:12px;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success" style="margin-top:12px;"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
</div>

<?php if (empty($members)): ?>
  <div class="card"><p class="muted">No members found.</p></div>
<?php endif; ?>

<?php foreach ($members as $m): ?>
<div class="card">
  <h2>
    <?php echo htmlspecialchars($m['name']); ?>
    <span class="muted" style="font-weight:400;">(<?php echo htmlspecialchars($m['id_number']); ?>)</span>
    <span class="badge badge-<?php echo $m['status'] === 'approved' ? 'paid' : ($m['status'] === 'pending' ? 'pending' : 'rejected'); ?>">
      <?php echo ucfirst($m['status']); ?>
    </span>
  </h2>
  <div class="grid-2">
    <!-- Edit info + status -->
    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
      <input type="hidden" name="action" value="update_info">
      <div class="field">
        <label>Full Name</label>
        <input name="name" value="<?php echo htmlspecialchars($m['name']); ?>" required>
      </div>
      <div class="field">
        <label>PRC ID No.</label>
        <input name="id_number" value="<?php echo htmlspecialchars($m['id_number']); ?>" required>
      </div>
      <div class="field">
        <label>Account Status</label>
        <select name="status">
          <option value="pending" <?php echo $m['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="approved" <?php echo $m['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
          <option value="rejected" <?php echo $m['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
      </div>
      <button class="btn btn-sm btn-success" type="submit">Save Changes</button>
    </form>

    <!-- Reset password -->
    <form method="post"
          data-confirm="Reset password for <?php echo htmlspecialchars($m['name']); ?>?"
          data-confirm-title="Reset Password"
          data-confirm-btn="Reset Password"
          data-confirm-class="btn-danger"
          data-confirm-icon="🔐">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
      <input type="hidden" name="action" value="reset_password">
      <div class="field">
        <label>Reset Password</label>
        <input type="text" name="new_password" placeholder="New password for this member" required>
        <p class="muted" style="margin-top:4px;">Sets their password directly — give it to them so they can log in.</p>
      </div>
      <button class="btn btn-sm btn-danger" type="submit">Reset Password</button>
    </form>
  </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
