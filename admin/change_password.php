<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // Get current admin password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([current_user_id()]);
    $admin = $stmt->fetch();

    if (!password_verify($current, $admin['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, current_user_id()]);
        $success = 'Password changed successfully.';
    }
}

$page_title = 'Change Password';
include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:420px;margin:0 auto;">
  <h1>Change Admin Password</h1>
  <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <form method="post">
    <div class="field">
      <label>Current Password</label>
      <input type="password" name="current_password" required>
    </div>
    <div class="field">
      <label>New Password</label>
      <input type="password" name="new_password" required>
    </div>
    <div class="field">
      <label>Confirm New Password</label>
      <input type="password" name="confirm_password" required>
    </div>
    <button class="btn" type="submit" style="width:100%;">Change Password</button>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
