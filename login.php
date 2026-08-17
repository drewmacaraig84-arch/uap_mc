<?php
require_once __DIR__ . '/includes/config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_number = trim($_POST['id_number']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
    $stmt->execute([$id_number]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['role'] === 'member' && $user['status'] === 'pending') {
            $error = 'Your account is still awaiting admin approval. Please check back later.';
        } elseif ($user['role'] === 'member' && $user['status'] === 'rejected') {
            $error = 'Your registration was not approved. Please contact the admin.';
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            header('Location: ' . BASE_URL . ($user['role'] === 'admin' ? '/admin/dashboard.php' : '/member/dashboard.php'));
            exit;
        }
    } else {
        $error = 'Invalid PRC ID No. or password.';
    }
}

$page_title = 'Login';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-illustration">
      <h2>Welcome back</h2>
      <p>Access your dues, payments, and account history from one secure portal.</p>
    </div>
    <div class="auth-form">
      <div style="text-align:center;margin-bottom:16px;">
        <img src="<?php echo BASE_URL; ?>/uploads/uap_logo.jpg" alt="UAP MC Logo" style="height:84px;width:84px;object-fit:contain;border-radius:12px;">
      </div>
      <h1>Login</h1>
      <p class="page-subtitle">Sign in to continue managing your payment records.</p>
      <?php if (isset($_GET['registered'])): ?><div class="alert alert-success">Account created! Please wait for admin approval before logging in.</div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <form method="post">
        <div class="field"><label>PRC ID No.</label><input name="id_number" required autofocus></div>
        <div class="field"><label>Password</label><input type="password" name="password" required></div>
        <button class="btn" type="submit" style="width:100%;">Login</button>
      </form>
      <p class="muted" style="margin-top:14px;">No account yet? <a href="<?php echo BASE_URL; ?>/register.php">Register here</a></p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
