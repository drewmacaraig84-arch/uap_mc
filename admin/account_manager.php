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
        $goodStandingOverride = $_POST['good_standing_override'] ?? 'auto';
        if (!in_array($goodStandingOverride, ['auto', 'revoked', 'granted'], true)) {
            $goodStandingOverride = 'auto';
        }
        $goodStandingReason = trim($_POST['good_standing_reason'] ?? '');
        $removePhoto = !empty($_POST['remove_photo']);

        if (!$name || !$id_number) {
            $error = 'Name and PRC ID No. are required.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE id_number = ? AND id != ?");
            $check->execute([$id_number, $user_id]);
            if ($check->fetch()) {
                $error = 'That PRC ID No. is already used by another account.';
            } else {
                $photoPath = $target['profile_photo'] ?? null;

                // Handle Photo Removal
                if ($removePhoto && !empty($photoPath)) {
                    $fileOnDisk = __DIR__ . '/../' . ltrim($photoPath, '/');
                    if (file_exists($fileOnDisk) && !is_dir($fileOnDisk)) {
                        @unlink($fileOnDisk);
                    }
                    $photoPath = null;
                    $pdo->prepare("UPDATE website_members SET photo_path = NULL WHERE user_id = ?")->execute([$user_id]);
                }

                // Handle Photo Upload
                if (!empty($_FILES['member_photo']['name']) && $_FILES['member_photo']['error'] === UPLOAD_ERR_OK) {
                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                    $fileTmp = $_FILES['member_photo']['tmp_name'];
                    $fileSize = $_FILES['member_photo']['size'];
                    $ext = strtolower(pathinfo($_FILES['member_photo']['name'], PATHINFO_EXTENSION));

                    if (!in_array($ext, $allowedExts)) {
                        $error = 'Invalid photo format. Please upload JPG, PNG, or WebP.';
                    } elseif ($fileSize > 5 * 1024 * 1024) {
                        $error = 'Photo file size exceeds 5MB limit.';
                    } else {
                        $uploadDir = __DIR__ . '/../uploads/avatars/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        $uniqueFilename = 'avatar_member_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                        $targetPath = $uploadDir . $uniqueFilename;

                        if (move_uploaded_file($fileTmp, $targetPath)) {
                            $photoPath = 'uploads/avatars/' . $uniqueFilename;
                            $pdo->prepare("UPDATE website_members SET photo_path = ? WHERE user_id = ?")->execute([$photoPath, $user_id]);
                        }
                    }
                }

                if (empty($error)) {
                    ensure_user_profile_photo_column($pdo);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, id_number = ?, status = ?, profile_photo = ?, good_standing_override = ?, good_standing_reason = ?, good_standing_updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $id_number, $status, $photoPath, $goodStandingOverride, $goodStandingReason !== '' ? $goodStandingReason : null, $user_id]);

                    // Sync name and company/link with website_members
                    $companyName = trim($_POST['company_name'] ?? '');
                    $linkUrl = trim($_POST['link_url'] ?? '');
                    $linkType = trim($_POST['link_type'] ?? 'auto');
                    if ($linkUrl !== '' && !preg_match('#^https?://#i', $linkUrl)) {
                        $linkUrl = 'https://' . ltrim($linkUrl, '/');
                    }

                    $wmCheck = $pdo->prepare("SELECT id FROM website_members WHERE user_id = ?");
                    $wmCheck->execute([$user_id]);
                    if ($wmCheck->fetch()) {
                        $pdo->prepare("UPDATE website_members SET name = ?, company_name = ?, link_url = ?, link_type = ? WHERE user_id = ?")
                            ->execute([$name, $companyName !== '' ? $companyName : null, $linkUrl !== '' ? $linkUrl : null, $linkType, $user_id]);
                    }

                    $success = 'Account details, profile photo, and directory settings updated successfully.';
                }
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
    $stmt = $pdo->prepare("SELECT u.*, wm.photo_path as wm_photo_path, wm.role_title, wm.specialty, wm.company_name, wm.link_url, wm.link_type 
                          FROM users u 
                          LEFT JOIN website_members wm ON wm.user_id = u.id 
                          WHERE u.role = 'member' AND (u.name LIKE ? OR u.id_number LIKE ? OR wm.company_name LIKE ?) 
                          ORDER BY u.name ASC");
    $like = "%$search%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query("SELECT u.*, wm.photo_path as wm_photo_path, wm.role_title, wm.specialty, wm.company_name, wm.link_url, wm.link_type 
                        FROM users u 
                        LEFT JOIN website_members wm ON wm.user_id = u.id 
                        WHERE u.role = 'member' 
                        ORDER BY u.name ASC");
}
$members = $stmt->fetchAll();

$page_title = 'Edit Member Accounts • UAP Mindoro Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">USER ADMINISTRATION</p>
    <h1>Member Account Manager</h1>
    <p class="page-subtitle">Edit member credentials, update profile pictures, change registration status, or reset passwords.</p>
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
    <?php
      $photo = ($m['profile_photo'] ?? null) ?: ($m['wm_photo_path'] ?? null);
      $photoUrl = $photo ? (str_starts_with($photo, 'http') ? $photo : BASE_URL . '/' . ltrim($photo, '/')) : null;
      $initials = strtoupper(substr($m['name'], 0, 1) . substr(strrchr($m['name'], ' ') ?: $m['name'], 1, 1));
    ?>
    <div class="card" style="margin: 0;">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 12px;">
          <div class="table-avatar-wrap">
            <?php if ($photoUrl): ?>
              <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="<?php echo htmlspecialchars($m['name']); ?>" class="table-avatar-img">
            <?php else: ?>
              <?php echo htmlspecialchars($initials); ?>
            <?php endif; ?>
          </div>
          <div>
            <h2 style="font-size: 16px; margin: 0; display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;">
              <span><?php echo htmlspecialchars($m['name']); ?></span>
              <span class="muted" style="font-size: 13px; font-weight: 500;">(PRC: <?php echo htmlspecialchars($m['id_number']); ?>)</span>
            </h2>
            <?php if (!empty($m['specialty'])): ?>
              <div style="font-size: 12px; color: var(--accent-primary); margin-top: 2px;">
                <?php echo htmlspecialchars($m['specialty']); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
          <?php
            $standing = get_member_standing_details($pdo, $m['id']);
            if ($standing['is_revoked']) {
                echo '<span class="badge-pill badge-unpaid" style="font-size: 11px;" title="' . htmlspecialchars($standing['reason'] ?? '') . '">' . icon('alert', '', 10) . ' Standing Revoked</span>';
            } elseif ($standing['is_good']) {
                echo '<span class="badge-pill badge-paid" style="font-size: 11px;">' . icon('good_members', '', 10) . ' Good Standing</span>';
            }
          ?>
          <span class="badge-pill badge-<?php echo $m['status'] === 'approved' ? 'paid' : ($m['status'] === 'pending' ? 'pending' : 'unpaid'); ?>">
            <?php echo ucfirst($m['status']); ?>
          </span>
        </div>
      </div>

      <div class="grid-2" style="gap: 20px;">
        <!-- Edit info + status + photo + good standing -->
        <form method="post" enctype="multipart/form-data" style="background: var(--bg-secondary); padding: 16px; border-radius: 12px; border: 1px solid var(--border-color);">
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
          <div class="grid-2" style="gap: 10px; margin-bottom: 10px;">
            <div class="field">
              <label>Account Status</label>
              <select name="status">
                <option value="pending" <?php echo $m['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $m['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $m['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
              </select>
            </div>
            <div class="field">
              <label>Good Standing Mode</label>
              <select name="good_standing_override">
                <option value="auto" <?php echo ($m['good_standing_override'] ?? 'auto') === 'auto' ? 'selected' : ''; ?>>Automatic (Dues-Based)</option>
                <option value="revoked" <?php echo ($m['good_standing_override'] ?? '') === 'revoked' ? 'selected' : ''; ?>>Revoked / On Hold</option>
                <option value="granted" <?php echo ($m['good_standing_override'] ?? '') === 'granted' ? 'selected' : ''; ?>>Granted / Exemption</option>
              </select>
            </div>
          </div>
          <div class="field" style="margin-bottom: 10px;">
            <label>Standing Reason / Admin Note</label>
            <input name="good_standing_reason" value="<?php echo htmlspecialchars($m['good_standing_reason'] ?? ''); ?>" placeholder="Optional reason (e.g. Disciplinary hold, Ethics review)...">
          </div>
          <div class="grid-2" style="gap: 10px; margin-bottom: 10px;">
            <div class="field">
              <label>Company / Firm Name</label>
              <input name="company_name" value="<?php echo htmlspecialchars($m['company_name'] ?? ''); ?>" placeholder="e.g. Ting & Associates Architects">
            </div>
            <div class="field">
              <label>Website / Social Link</label>
              <input name="link_url" value="<?php echo htmlspecialchars($m['link_url'] ?? ''); ?>" placeholder="https://facebook.com/..., https://yourfirm.com">
            </div>
          </div>
          <div class="field" style="margin-bottom: 10px;">
            <label>Link Platform / Icon Type</label>
            <?php $curType = $m['link_type'] ?? 'auto'; ?>
            <select name="link_type">
              <option value="auto" <?php echo $curType === 'auto' ? 'selected' : ''; ?>>Auto-Detect Icon from URL</option>
              <option value="facebook" <?php echo $curType === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
              <option value="instagram" <?php echo $curType === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
              <option value="linkedin" <?php echo $curType === 'linkedin' ? 'selected' : ''; ?>>LinkedIn</option>
              <option value="youtube" <?php echo $curType === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
              <option value="telegram" <?php echo $curType === 'telegram' ? 'selected' : ''; ?>>Telegram</option>
              <option value="website" <?php echo $curType === 'website' ? 'selected' : ''; ?>>Official Website / Portfolio</option>
            </select>
          </div>
          <div class="field" style="margin-bottom: 14px;">
            <label>Update Profile Picture</label>
            <input type="file" name="member_photo" accept="image/png, image/jpeg, image/webp" style="padding: 6px;">
            <?php if ($photoUrl): ?>
              <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: #ef4444; margin-top: 6px; cursor: pointer;">
                <input type="checkbox" name="remove_photo" value="1">
                <span>Remove current photo</span>
              </label>
            <?php endif; ?>
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
