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
                $success = 'Account details updated successfully.';
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
            $success = 'Password reset successfully for ' . htmlspecialchars($target['name']) . '.';
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

$page_title = 'Edit Member Accounts • UAP Mindoro Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">USER ADMINISTRATION</p>
    <h1>Member Account Manager</h1>
    <p class="page-subtitle">Edit member credentials, change registration status, or reset credentials.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('account_manager', '', 14); ?> <span><?php echo count($members); ?> Accounts</span>
  </div>
</div>

<div class="card" style="margin-bottom: 24px;">
  <form method="get" style="display:flex; gap:10px; flex-wrap: wrap;">
    <div style="flex:1; min-width: 260px; position: relative;">
      <input name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by member name or PRC ID No..." style="padding-left: 36px;">
      <div style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);">
        <?php echo icon('search', '', 16); ?>
      </div>
    </div>
    <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
      <?php echo icon('search', '', 14); ?> <span>Search</span>
    </button>
    <?php if ($search): ?>
      <a href="account_manager.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 4px;">
        <?php echo icon('x', '', 14); ?> <span>Clear</span>
      </a>
    <?php endif; ?>
  </form>

  <?php if ($error): ?>
    <div class="alert alert-error" style="margin-top:16px;">
      <div style="display:flex;align-items:center;gap:8px;">
        <?php echo icon('alert', '', 18); ?>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success" style="margin-top:16px;">
      <div style="display:flex;align-items:center;gap:8px;">
        <?php echo icon('check', '', 18); ?>
        <span><?php echo htmlspecialchars($success); ?></span>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (empty($members)): ?>
  <div class="card" style="text-align: center; padding: 40px 16px; color: var(--text-secondary);">
    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(0,0,0,0.05); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
      <?php echo icon('members', '', 24); ?>
    </div>
    <strong style="display: block; font-size: 15px; color: var(--text-primary);">No member accounts found</strong>
    <p class="muted" style="margin-top: 4px; font-size: 13px;">Try searching for another name or ID number.</p>
  </div>
<?php endif; ?>

<div style="display: flex; flex-direction: column; gap: 18px;">
  <?php foreach ($members as $m): ?>
    <div class="card" style="margin: 0;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59,130,246,0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center;">
            <?php echo icon('user', '', 18); ?>
          </div>
          <div>
            <h2 style="font-size: 16px; margin: 0; display: inline-flex; align-items: center; gap: 8px;">
              <span><?php echo htmlspecialchars($m['name']); ?></span>
              <span class="muted" style="font-size: 13px; font-weight: 500;">(<?php echo htmlspecialchars($m['id_number']); ?>)</span>
            </h2>
          </div>
        </div>
        <span class="badge-pill badge-<?php echo $m['status'] === 'approved' ? 'paid' : ($m['status'] === 'pending' ? 'pending' : 'unpaid'); ?>">
          <?php echo ucfirst($m['status']); ?>
        </span>
      </div>

      <div class="grid-2" style="gap: 20px;">
        <!-- Edit info + status -->
        <form method="post" style="background: var(--bg-secondary); padding: 16px; border-radius: 12px; border: 1px solid var(--border-color);">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
          <input type="hidden" name="action" value="update_info">
          
          <div class="field" style="margin-bottom: 10px;">
            <label>Full Name</label>
            <input name="name" value="<?php echo htmlspecialchars($m['name']); ?>" required>
          </div>
          <div class="field" style="margin-bottom: 10px;">
            <label>PRC ID No.</label>
            <input name="id_number" value="<?php echo htmlspecialchars($m['id_number']); ?>" required>
          </div>
          <div class="field" style="margin-bottom: 14px;">
            <label>Account Status</label>
            <select name="status">
              <option value="pending" <?php echo $m['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="approved" <?php echo $m['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
              <option value="rejected" <?php echo $m['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
          </div>
          <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 4px;">
            <?php echo icon('check', '', 12); ?> <span>Save Details</span>
          </button>
        </form>

        <!-- Reset password -->
        <form method="post" style="background: var(--bg-secondary); padding: 16px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-direction: column;"
              data-confirm="Reset password for <?php echo htmlspecialchars($m['name']); ?>?"
              data-confirm-title="Reset Member Password"
              data-confirm-btn="Reset Password"
              data-confirm-class="btn-danger">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
          <input type="hidden" name="action" value="reset_password">
          
          <div class="field" style="margin-bottom: 14px; flex-grow: 1;">
            <label>Set Direct Password</label>
            <input type="text" name="new_password" placeholder="Enter new password..." required>
            <p class="muted" style="margin-top:6px; font-size: 11.5px;">Sets the password directly. Provide this to the member so they can log in.</p>
          </div>
          <button class="btn btn-sm btn-danger" type="submit" style="display: inline-flex; align-items: center; justify-content: center; gap: 4px; margin-top: auto;">
            <?php echo icon('key', '', 12); ?> <span>Reset Password</span>
          </button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
