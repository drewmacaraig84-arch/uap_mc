<?php
require_once __DIR__ . '/../includes/config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$name || !$id_number || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        if ($stmt->fetch()) {
            $error = 'An account with that PRC ID Number already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, id_number, password, role, status) VALUES (?, ?, ?, 'member', 'pending')");
            $stmt->execute([$name, $id_number, $hash]);

            header('Location: ' . BASE_URL . '/auth/login.php?registered=1');
            exit;
        }
    }
}

$page_title = 'Member Registration • UAP Mindoro Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
  <!-- THEME TOGGLE BUTTON -->
  <div style="position: absolute; top: 20px; right: 20px; z-index: 50;">
    <button type="button" class="btn btn-sm btn-secondary" onclick="toggleTheme()" style="padding: 8px 14px; display: inline-flex; align-items: center; gap: 6px;">
      <?php echo icon('sun', '', 14); ?> <span id="themeToggleText">Switch Theme</span>
    </button>
  </div>

  <div class="auth-card-modern">
    <!-- LEFT BRANDING & HERO PANEL -->
    <div class="auth-hero-panel">
      <div>
        <div class="auth-logo-badge">
          <img src="<?php echo BASE_URL; ?>/public/logo.jpg" alt="UAP Mindoro Chapter Logo" onerror="if(this.src.indexOf('uploads/logo.jpg')===-1)this.src='<?php echo BASE_URL; ?>/uploads/logo.jpg';">
        </div>
        <p class="eyebrow" style="color: #fbbf24; margin-bottom: 6px;">NEW MEMBER REGISTRATION</p>
        <h2 class="auth-hero-title">Join UAP Mindoro Chapter</h2>
        <p class="auth-hero-sub">Register your official member profile to access online dues payments, track official receipts, and appear in the website directory.</p>

        <div class="auth-feature-list">
          <div class="auth-feature-item">
            <div class="auth-feature-icon">
              <?php echo icon('user', '', 16); ?>
            </div>
            <span>Official Architect Profile Verification</span>
          </div>
          <div class="auth-feature-item">
            <div class="auth-feature-icon">
              <?php echo icon('dues', '', 16); ?>
            </div>
            <span>Digital Payment Receipts &amp; Installment Tracking</span>
          </div>
          <div class="auth-feature-item">
            <div class="auth-feature-icon">
              <?php echo icon('good_members', '', 16); ?>
            </div>
            <span>Certificate of Good Standing Eligibility</span>
          </div>
        </div>
      </div>

      <div style="margin-top: 36px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.08); font-size: 12px; color: #64748b;">
        &copy; <?php echo date('Y'); ?> UAP Mindoro Chapter &bull; Portal &amp; Management
      </div>
    </div>

    <!-- RIGHT REGISTER FORM PANEL -->
    <div class="auth-form-panel">
      <div class="auth-header">
        <h1>Create Account</h1>
        <p>Register as a member of the UAP Mindoro Chapter.</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-error" style="display: flex; align-items: center; gap: 8px;">
          <?php echo icon('alert', '', 18); ?>
          <div>
            <strong>Registration Error</strong><br>
            <span style="font-size: 12.5px;"><?php echo htmlspecialchars($error); ?></span>
          </div>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="on">
        <?php echo csrf_field(); ?>
        
        <div class="field">
          <label>Full Legal Name</label>
          <div class="input-icon-wrap">
            <span class="input-leading-icon"><?php echo icon('user', '', 18); ?></span>
            <input type="text" name="name" required autofocus placeholder="e.g. Arch. Juan Dela Cruz, uap" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
          </div>
        </div>

        <div class="field">
          <label>PRC ID Number</label>
          <div class="input-icon-wrap">
            <span class="input-leading-icon"><?php echo icon('shield_check', '', 18); ?></span>
            <input type="text" name="id_number" required placeholder="e.g. 0123456" value="<?php echo htmlspecialchars($_POST['id_number'] ?? ''); ?>">
          </div>
        </div>

        <div class="field" style="margin-bottom: 22px;">
          <label>Create Password</label>
          <div class="input-icon-wrap">
            <span class="input-leading-icon"><?php echo icon('key', '', 18); ?></span>
            <input type="password" id="regPassword" name="password" required placeholder="Min. 6 characters">
            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('regPassword', this)" aria-label="Toggle password visibility">
              <?php echo icon('eye', '', 16); ?>
            </button>
          </div>
        </div>

        <button class="btn auth-btn-primary" type="submit" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
          <?php echo icon('plus', '', 16); ?> <span>Submit Application</span>
        </button>
      </form>

      <div class="auth-footer-link">
        Already have a member account? <a href="<?php echo BASE_URL; ?>/auth/login.php">Sign in here</a>
      </div>
    </div>
  </div>
</div>

<script>
function togglePasswordVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  if (input.type === 'password') {
    input.type = 'text';
    btn.style.color = 'var(--accent-primary)';
  } else {
    input.type = 'password';
    btn.style.color = 'var(--muted-text)';
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
