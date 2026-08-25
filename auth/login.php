<?php
require_once __DIR__ . '/../includes/config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $rateKey = 'login_attempts_' . md5($ip);
    $attempts = (int)($_SESSION[$rateKey] ?? 0);
    $lastAttemptTime = (int)($_SESSION[$rateKey . '_time'] ?? 0);

    // Lockout for 60 seconds if more than 5 attempts within 1 minute
    if ($attempts >= 5 && (time() - $lastAttemptTime) < 60) {
        $remainingLock = 60 - (time() - $lastAttemptTime);
        $error = "Too many failed login attempts. Please wait {$remainingLock} seconds before trying again.";
    } else {
        if ((time() - $lastAttemptTime) >= 60) {
            $_SESSION[$rateKey] = 0;
            $attempts = 0;
        }

        $id_number = trim($_POST['id_number'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            unset($_SESSION[$rateKey], $_SESSION[$rateKey . '_time']);

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
            $_SESSION[$rateKey] = $attempts + 1;
            $_SESSION[$rateKey . '_time'] = time();
            $error = 'Invalid PRC ID No. or password.';
        }
    }
}

$page_title = 'Login';
include __DIR__ . '/../includes/header.php';
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
      <?php if (isset($_GET['registered'])): ?><div class="alert alert-success"><strong>✓ Account Created!</strong><br>Please wait for admin approval before logging in.</div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error"><strong>⚠ Login Failed</strong><br><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
      <form method="post">
        <?php echo csrf_field(); ?>
        <div class="field"><label>PRC ID No.</label><input name="id_number" required autofocus></div>
        <div class="field"><label>Password</label><input type="password" name="password" required></div>
        <button class="btn" type="submit" style="width:100%;">Login</button>
      </form>
      <p class="muted" style="margin-top:14px;">No account yet? <a href="<?php echo BASE_URL; ?>/auth/register.php">Register here</a></p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
