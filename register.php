<?php
require_once __DIR__ . '/includes/config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $id_number = trim($_POST['id_number']);
    $password = $_POST['password'];

    if (!$name || !$id_number || !$password) {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        if ($stmt->fetch()) {
            $error = 'An account with that PRC ID No. already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, id_number, password, role, status) VALUES (?, ?, ?, 'member', 'pending')");
            $stmt->execute([$name, $id_number, $hash]);

            header('Location: ' . BASE_URL . '/login.php?registered=1');
            exit;
        }
    }
}

$page_title = 'Register';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-illustration">
      <h2>Create your account</h2>
      <p>Register as a member and begin tracking your dues with a clear and professional workflow.</p>
    </div>
    <div class="auth-form">
      <div style="text-align:center;margin-bottom:16px;">
        <img src="<?php echo BASE_URL; ?>/uploads/uap_logo.jpg" alt="UAP MC Logo" style="height:84px;width:84px;object-fit:contain;border-radius:12px;">
      </div>
      <h1>Create Member Account</h1>
      <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <form method="post">
        <div class="field"><label>Full Name</label><input name="name" required></div>
        <div class="field"><label>PRC ID No.</label><input name="id_number" required placeholder="e.g. 0123456"></div>
        <div class="field"><label>Password</label><input type="password" name="password" required></div>
        <button class="btn" type="submit" style="width:100%;">Register</button>
      </form>
      <p class="muted" style="margin-top:14px;">Your account will need to be approved by an admin before you can log in.</p>
      <p class="muted" style="margin-top:6px;">Already have an account? <a href="<?php echo BASE_URL; ?>/login.php">Login here</a></p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
