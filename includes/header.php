<?php
$page_title = $page_title ?? 'UAP Mindoro Chapter Portal';
require_once __DIR__ . '/icons.php';
?>
<!DOCTYPE html>
<html lang="en" id="htmlElement">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title); ?></title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/theme.css">
<script>
// Apply theme immediately before page renders to prevent flash
(function() {
  const storedTheme = localStorage.getItem('theme');
  const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const theme = storedTheme || (systemPrefersDark ? 'dark' : 'light');
  document.getElementById('htmlElement').setAttribute('data-theme', theme);
  document.documentElement.style.colorScheme = theme;
})();
</script>
</head>
<body data-auth="<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>">

<?php if (isset($_SESSION['user_id'])): ?>
  <?php
    $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $nav_active = function($pages) use ($current_script) {
        $pages = (array)$pages;
        return in_array($current_script, $pages, true) ? ' active' : '';
    };

    $pending_approvals_count = 0;
    $pending_payments_count = 0;
    $pending_directory_count = 0;
    $pending_approvals = [];
    $pending_payments = [];
    $pending_directory_apps = [];

    if ($_SESSION['role'] === 'admin') {
        try {
            $pending_approvals = $pdo->query("SELECT id, name, id_number, created_at FROM users WHERE role = 'member' AND status = 'pending' ORDER BY created_at DESC LIMIT 5")->fetchAll();
            $pending_approvals_count = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'pending'")->fetchColumn();

            $pending_payments = $pdo->query("SELECT p.id, p.submitted_at, u.name, u.id_number, d.title FROM payments p JOIN member_dues md ON p.member_due_id = md.id JOIN users u ON md.user_id = u.id JOIN dues d ON md.due_id = d.id WHERE p.status = 'pending' ORDER BY p.submitted_at DESC LIMIT 5")->fetchAll();
            $pending_payments_count = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();

            $pending_directory_apps = $pdo->query("SELECT da.id, u.name, u.id_number, da.created_at FROM directory_applications da JOIN users u ON da.user_id = u.id WHERE da.status = 'pending_fee' ORDER BY da.created_at DESC LIMIT 5")->fetchAll();
            $pending_directory_count = (int)$pdo->query("SELECT COUNT(*) FROM directory_applications WHERE status = 'pending_fee'")->fetchColumn();
        } catch (Throwable $e) {}
    }
    $total_notifications = $pending_approvals_count + $pending_payments_count + $pending_directory_count;

    $logo_path = function_exists('get_site_logo') ? get_site_logo($pdo) : 'public/logo.jpg';
    $logo_src = BASE_URL . '/' . htmlspecialchars(ltrim($logo_path ?: 'public/logo.jpg', '/'));
    $user_name = $_SESSION['name'] ?? 'User';
    $user_role = $_SESSION['role'] ?? 'member';
    $user_initials = strtoupper(substr($user_name, 0, 1) . substr(strrchr($user_name, ' ') ?: $user_name, 1, 1));
    if (strlen($user_initials) < 2) {
        $user_initials = strtoupper(substr($user_name, 0, 2));
    }
  ?>

  <!-- SIDEBAR NAVIGATION -->
  <nav id="sidebarNav">
    <div class="nav-brand">
      <div class="nav-brand-img-wrap">
        <img src="<?php echo $logo_src; ?>" alt="UAP Logo" onerror="if(this.src.indexOf('public/logo.jpg')===-1)this.src='<?php echo BASE_URL; ?>/public/logo.jpg';">
      </div>
      <div class="nav-brand-text">
        <span class="nav-brand-title">UAP Mindoro</span>
        <span class="nav-brand-sub"><?php echo $user_role === 'admin' ? 'Admin Portal' : 'Member Portal'; ?></span>
      </div>
    </div>

    <div class="nav-links" id="sidebarLinks">
      <?php if ($user_role === 'admin'): ?>
        <div class="nav-category">Overview</div>
        <div class="nav-section">
          <a class="nav-item<?php echo $nav_active('dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/admin/dashboard.php">
            <span class="nav-icon"><?php echo icon('dashboard'); ?></span>
            <span>Dashboard</span>
          </a>
        </div>

        <div class="nav-category">Finances &amp; Dues</div>
        <div class="nav-section">
          <a class="nav-item<?php echo $nav_active('approvals.php'); ?>" href="<?php echo BASE_URL; ?>/admin/approvals.php">
            <span class="nav-icon"><?php echo icon('approvals'); ?></span>
            <span>Member Approvals</span>
            <?php if ($pending_approvals_count > 0): ?>
              <span class="nav-badge"><?php echo $pending_approvals_count; ?></span>
            <?php endif; ?>
          </a>
          <a class="nav-item<?php echo $nav_active(['payments.php', 'verify.php']); ?>" href="<?php echo BASE_URL; ?>/admin/payments.php">
            <span class="nav-icon"><?php echo icon('payments'); ?></span>
            <span>Payment Verification</span>
            <?php if ($pending_payments_count > 0): ?>
              <span class="nav-badge"><?php echo $pending_payments_count; ?></span>
            <?php endif; ?>
          </a>
          <a class="nav-item<?php echo $nav_active('dues.php'); ?>" href="<?php echo BASE_URL; ?>/admin/dues.php">
            <span class="nav-icon"><?php echo icon('dues'); ?></span>
            <span>Dues Packages</span>
          </a>
          <a class="nav-item<?php echo $nav_active('qr_codes.php'); ?>" href="<?php echo BASE_URL; ?>/admin/qr_codes.php">
            <span class="nav-icon"><?php echo icon('qr_codes'); ?></span>
            <span>Payment QR Codes</span>
          </a>
        </div>

        <div class="nav-category">Membership</div>
        <div class="nav-section">
          <a class="nav-item<?php echo $nav_active('members.php'); ?>" href="<?php echo BASE_URL; ?>/admin/members.php">
            <span class="nav-icon"><?php echo icon('members'); ?></span>
            <span>Chapter Members</span>
          </a>
          <a class="nav-item<?php echo $nav_active('good_members.php'); ?>" href="<?php echo BASE_URL; ?>/admin/good_members.php">
            <span class="nav-icon"><?php echo icon('good_members'); ?></span>
            <span>Good Standing</span>
          </a>
          <a class="nav-item<?php echo $nav_active('website_directory.php'); ?>" href="<?php echo BASE_URL; ?>/admin/website_directory.php">
            <span class="nav-icon"><?php echo icon('website_directory'); ?></span>
            <span>Website Directory</span>
            <?php if ($pending_directory_count > 0): ?>
              <span class="nav-badge"><?php echo $pending_directory_count; ?></span>
            <?php endif; ?>
          </a>
        </div>

        <div class="nav-category">System &amp; Settings</div>
        <div class="nav-section">
          <a class="nav-item<?php echo $nav_active('reports.php'); ?>" href="<?php echo BASE_URL; ?>/admin/reports.php">
            <span class="nav-icon"><?php echo icon('reports'); ?></span>
            <span>Financial Reports</span>
          </a>
          <a class="nav-item<?php echo $nav_active('account_manager.php'); ?>" href="<?php echo BASE_URL; ?>/admin/account_manager.php">
            <span class="nav-icon"><?php echo icon('account_manager'); ?></span>
            <span>Edit Accounts</span>
          </a>
          <a class="nav-item<?php echo $nav_active(['settings.php', 'change_password.php']); ?>" href="<?php echo BASE_URL; ?>/admin/settings.php">
            <span class="nav-icon"><?php echo icon('settings'); ?></span>
            <span>Settings &amp; Website</span>
          </a>
        </div>

      <?php else: ?>
        <div class="nav-category">Member Portal</div>
        <div class="nav-section">
          <a class="nav-item<?php echo $nav_active(['dashboard.php', 'pay.php']); ?>" href="<?php echo BASE_URL; ?>/member/dashboard.php">
            <span class="nav-icon"><?php echo icon('payments'); ?></span>
            <span>My Dues &amp; Pay</span>
          </a>
          <a class="nav-item<?php echo $nav_active('website_directory.php'); ?>" href="<?php echo BASE_URL; ?>/member/website_directory.php">
            <span class="nav-icon"><?php echo icon('website_directory'); ?></span>
            <span>Directory Profile</span>
          </a>
          <a class="nav-item<?php echo $nav_active('history.php'); ?>" href="<?php echo BASE_URL; ?>/member/history.php">
            <span class="nav-icon"><?php echo icon('dues'); ?></span>
            <span>Payment History</span>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </nav>

  <!-- TOPBAR HEADER -->
  <header class="topbar">
    <div style="display: flex; align-items: center; gap: 12px;">
      <button class="menu-toggle" type="button" aria-label="Toggle navigation" onclick="toggleMobileMenu()">
        <?php echo icon('dashboard', '', 18); ?>
      </button>
      <div class="topbar-search">
        <?php echo icon('search', '', 16); ?>
        <input id="globalSearchInput" type="text" placeholder="Search dues, members, records..." aria-label="Search">
      </div>
    </div>

    <div class="topbar-actions">
      <?php if ($user_role === 'admin'): ?>
        <!-- Notification Bell -->
        <div class="notification-bell" id="notificationBell" tabindex="0">
          <?php echo icon('bell', '', 18); ?>
          <?php if ($total_notifications > 0): ?>
            <span class="notification-badge" id="notificationBadge"><?php echo $total_notifications; ?></span>
          <?php endif; ?>

          <div class="notification-dropdown">
            <div class="notification-header">
              <span>Pending Action Items</span>
              <span style="font-size: 11px; color: var(--accent-primary); font-weight: 700;"><?php echo $total_notifications; ?> Pending</span>
            </div>
            <div class="notification-list">
              <?php if ($total_notifications === 0): ?>
                <div style="text-align: center; padding: 24px 16px; color: var(--text-secondary); font-size: 13px;">
                  <?php echo icon('check', '', 24); ?>
                  <p style="margin: 6px 0 0 0;">All caught up! No pending approvals, payments, or applications.</p>
                </div>
              <?php else: ?>
                <?php foreach ($pending_approvals as $approval): ?>
                  <div class="notification-item" onclick="window.location.href='<?php echo BASE_URL; ?>/admin/approvals.php'">
                    <span class="notification-type approval">Member Sign-Up</span>
                    <div class="notification-member"><?php echo htmlspecialchars($approval['name']); ?></div>
                    <div class="notification-meta">PRC: <?php echo htmlspecialchars($approval['id_number']); ?> &bull; <?php echo date('M d, Y', strtotime($approval['created_at'])); ?></div>
                  </div>
                <?php endforeach; ?>
                <?php foreach ($pending_payments as $payment): ?>
                  <div class="notification-item" onclick="window.location.href='<?php echo BASE_URL; ?>/admin/payments.php'">
                    <span class="notification-type payment">Payment Proof</span>
                    <div class="notification-member"><?php echo htmlspecialchars($payment['name']); ?></div>
                    <div class="notification-meta"><?php echo htmlspecialchars($payment['title']); ?> &bull; <?php echo date('M d, h:i A', strtotime($payment['submitted_at'])); ?></div>
                  </div>
                <?php endforeach; ?>
                <?php foreach ($pending_directory_apps as $dirApp): ?>
                  <div class="notification-item" onclick="window.location.href='<?php echo BASE_URL; ?>/admin/website_directory.php'">
                    <span class="notification-type directory">Directory Application</span>
                    <div class="notification-member"><?php echo htmlspecialchars($dirApp['name']); ?></div>
                    <div class="notification-meta">PRC: <?php echo htmlspecialchars($dirApp['id_number']); ?> &bull; <?php echo date('M d, Y', strtotime($dirApp['created_at'])); ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- User Profile Dropdown -->
      <div class="user-chip" id="userMenuTrigger">
        <div class="user-avatar"><?php echo htmlspecialchars($user_initials); ?></div>
        <div class="user-info">
          <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
          <span class="user-role"><?php echo htmlspecialchars($user_role); ?></span>
        </div>
        <?php echo icon('arrow_right', '', 12); ?>

        <div class="user-menu">
          <?php if ($user_role === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>/admin/settings.php">
              <?php echo icon('settings', '', 16); ?>
              <span>Portal Settings</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/change_password.php">
              <?php echo icon('key', '', 16); ?>
              <span>Change Password</span>
            </a>
            <div class="user-menu-divider"></div>
          <?php endif; ?>

          <button type="button" id="themeMenuToggle">
            <?php echo icon('sun', '', 16); ?>
            <span id="themeToggleText">Switch Theme</span>
          </button>

          <div class="user-menu-divider"></div>
          <form method="post" action="<?php echo BASE_URL; ?>/auth/logout.php" style="margin:0;padding:0;">
            <?php echo csrf_field(); ?>
            <button type="submit" style="color: #ef4444;">
              <?php echo icon('logout', '', 16); ?>
              <span>Log out</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </header>
<?php endif; ?>

<div class="container">
<?php if (function_exists('display_flash')) { display_flash(); } ?>
