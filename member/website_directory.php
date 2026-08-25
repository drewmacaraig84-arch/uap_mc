<?php
require_once __DIR__ . '/../includes/auth.php';
require_member();

$userId = current_user_id();
$app = get_directory_application($pdo, $userId);
$isUnlocked = has_unlocked_website_directory($pdo, $userId);

$error = '';
$success = '';

// Handle Application Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_directory') {
    require_csrf();
    if (!$app) {
        $stmt = $pdo->prepare("INSERT INTO directory_applications (user_id, status) VALUES (?, 'pending_fee')
                               ON DUPLICATE KEY UPDATE status = 'pending_fee'");
        $stmt->execute([$userId]);
        $success = 'Application submitted successfully! The Chapter Admin will review and assign your advertising fee shortly.';
        $app = get_directory_application($pdo, $userId);
    }
}

// Handle Profile Save (Only if unlocked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile') {
    require_csrf();
    
    if (!$isUnlocked) {
        $error = 'You must complete and verify your directory advertising payment to unlock this feature.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $idNumber = trim($_POST['id_number'] ?? '');
        $role = trim($_POST['role_title'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $achievements = trim($_POST['achievements'] ?? '');
        $awards = trim($_POST['awards'] ?? '');

        // 1. Process Existing Photos
        $gallery = [];
        $existingPhotos = $_POST['existing_photos'] ?? [];
        $deletePhotos = $_POST['delete_photos'] ?? [];

        if (is_array($existingPhotos)) {
            foreach ($existingPhotos as $idx => $item) {
                if (!empty($item['path']) && !in_array((string)$idx, $deletePhotos, true)) {
                    $gallery[] = [
                        'path' => $item['path'],
                        'description' => trim($item['description'] ?? '')
                    ];
                }
            }
        }

        // 2. Process Newly Uploaded Photos
        if (!empty($_FILES['new_photos']['name']) && is_array($_FILES['new_photos']['name'])) {
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $uploadDir = __DIR__ . '/../uploads/members/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newDescriptions = $_POST['new_descriptions'] ?? [];

            foreach ($_FILES['new_photos']['name'] as $i => $filename) {
                if (isset($_FILES['new_photos']['error'][$i]) && $_FILES['new_photos']['error'][$i] === UPLOAD_ERR_OK && !empty($filename)) {
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExts) && $_FILES['new_photos']['size'][$i] <= 10 * 1024 * 1024) {
                        $uniqueName = 'member_' . $userId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                        $targetPath = $uploadDir . $uniqueName;
                        if (move_uploaded_file($_FILES['new_photos']['tmp_name'][$i], $targetPath)) {
                            $gallery[] = [
                                'path' => 'uploads/members/' . $uniqueName,
                                'description' => trim($newDescriptions[$i] ?? '')
                            ];
                        }
                    }
                }
            }
        }

        $galleryJson = json_encode($gallery);
        $firstPhoto = !empty($gallery[0]['path']) ? $gallery[0]['path'] : null;
        $firstDesc = !empty($gallery[0]['description']) ? $gallery[0]['description'] : null;

        $stmt = $pdo->prepare("INSERT INTO website_members 
            (user_id, name, id_number, role_title, specialty, location, achievements, awards, photo_path, photo_description, gallery_json, is_published)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                id_number = VALUES(id_number),
                role_title = VALUES(role_title),
                specialty = VALUES(specialty),
                location = VALUES(location),
                achievements = VALUES(achievements),
                awards = VALUES(awards),
                photo_path = VALUES(photo_path),
                photo_description = VALUES(photo_description),
                gallery_json = VALUES(gallery_json),
                is_published = 1");
        $stmt->execute([
            $userId,
            $name,
            $idNumber,
            $role,
            $specialty,
            $location,
            $achievements,
            $awards,
            $firstPhoto,
            $firstDesc,
            $galleryJson
        ]);

        $success = 'Your website directory profile & project gallery have been published!';
    }
}

// Fetch member's current profile record
$record = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ? LIMIT 1");
$record->execute([$userId]);
$profile = $record->fetch();

// Fetch current user details as initial fallback
if (!$profile) {
    $uStmt = $pdo->prepare("SELECT name, id_number FROM users WHERE id = ?");
    $uStmt->execute([$userId]);
    $uRow = $uStmt->fetch();
    $profile = [
        'name' => $uRow['name'] ?? '',
        'id_number' => $uRow['id_number'] ?? '',
        'role_title' => 'Architect',
        'specialty' => '',
        'location' => 'Mindoro',
        'achievements' => '',
        'awards' => '',
        'gallery_json' => ''
    ];
}

// Decode gallery photos
$gallery = [];
if (!empty($profile['gallery_json'])) {
    $decoded = json_decode($profile['gallery_json'], true);
    if (is_array($decoded)) $gallery = $decoded;
} elseif (!empty($profile['photo_path'])) {
    $gallery[] = [
        'path' => $profile['photo_path'],
        'description' => $profile['photo_description'] ?? ''
    ];
}

$page_title = 'Website Directory Profile';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 960px; margin: 0 auto;">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom: 16px;">
    <div>
      <h1 style="margin-bottom:4px;">Website Directory Feature</h1>
      <p class="muted">Showcase your architectural practice, credentials, and projects on the official chapter website.</p>
    </div>
    <?php if ($isUnlocked): ?>
      <span class="badge badge-paid" style="font-size:13px; padding: 6px 14px;">✨ Feature Unlocked</span>
    <?php endif; ?>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom: 18px;"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom: 18px;"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <?php if (!$app): ?>
    <!-- ================= STATE 1: NOT APPLIED YET ================= -->
    <div style="background: var(--bg-secondary, rgba(0,0,0,0.15)); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 14px; padding: 32px 24px; text-align: center; margin-top: 10px;">
      <div style="font-size: 42px; margin-bottom: 12px;">🏛️</div>
      <h2 style="font-size: 20px; margin-bottom: 8px; color:var(--text-primary);">Apply to be Featured on the Website Directory</h2>
      <p class="muted" style="max-width: 560px; margin: 0 auto 24px; font-size: 14px; line-height: 1.6;">
        Promote your architectural profile, showcase multiple portfolio photos, and reach clients looking for professional architects across Mindoro.
      </p>
      
      <button type="button" onclick="openApplyModal()" class="btn btn-success" style="padding: 12px 32px; font-size: 15px; font-weight: 700;">
        Apply to Website Directory &rarr;
      </button>
    </div>

    <!-- Application Modal -->
    <div id="applyDirectoryModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:99999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(6px);">
      <div style="background:var(--card-bg, #18243a); border:1px solid var(--border-color, rgba(255,255,255,0.15)); border-radius:14px; max-width:480px; width:100%; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.6); color:var(--text-primary);">
        <div style="padding:24px;">
          <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
            <div style="width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:22px; background:rgba(245,158,11,0.15); color:#f59e0b; flex-shrink:0;">📢</div>
            <h3 style="margin:0; font-size:18px; font-weight:700;">Directory Advertisement Application</h3>
          </div>
          <p style="font-size:14px; line-height:1.6; color:var(--text-secondary); margin-bottom:20px;">
            Would you like to apply to be featured and advertised on the official UAP Mindoro Website Directory?
            <br><br>
            <strong>How it works:</strong>
            <br>1. Submit your application.
            <br>2. The Chapter Admin will review and set an advertising fee for you.
            <br>3. Once you pay and the payment is verified, the feature will be unlocked immediately!
          </p>
          <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" onclick="closeApplyModal()" class="btn btn-sm" style="background:transparent; border:1px solid var(--border-color); color:var(--text-primary); padding:8px 16px;">Cancel</button>
            <form method="post" style="display:inline;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="apply_directory">
              <button type="submit" class="btn btn-sm btn-success" style="padding:8px 20px; font-weight:700;">Confirm & Apply</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <script>
      function openApplyModal() { document.getElementById('applyDirectoryModal').style.display = 'flex'; }
      function closeApplyModal() { document.getElementById('applyDirectoryModal').style.display = 'none'; }
    </script>

  <?php elseif ($app['status'] === 'pending_fee'): ?>
    <!-- ================= STATE 2: PENDING ADMIN FEE ================= -->
    <div style="background: var(--bg-secondary, rgba(0,0,0,0.15)); border: 1px solid rgba(245,158,11,0.3); border-radius: 14px; padding: 28px 24px; text-align: center; margin-top: 10px;">
      <div style="font-size: 38px; margin-bottom: 10px;">⏳</div>
      <h2 style="font-size: 19px; margin-bottom: 8px; color:var(--text-primary);">Application Under Review</h2>
      <p class="muted" style="max-width: 540px; margin: 0 auto 16px; font-size: 14px; line-height: 1.6;">
        Your application to be advertised on the Website Directory was submitted on <strong><?php echo date('M d, Y H:i', strtotime($app['created_at'])); ?></strong>.
        The Chapter Admin will review your request and assign your directory advertising fee shortly.
      </p>
      <span class="badge badge-pending" style="font-size: 12px; padding: 5px 12px;">Status: Awaiting Admin Fee Assignment</span>
    </div>

  <?php elseif (!$isUnlocked): ?>
    <!-- ================= STATE 3: FEE SET / PAYMENT PENDING ================= -->
    <?php 
      $feeAmount = (float)($app['fee_amount'] ?: $app['due_amount'] ?: 0);
      $dueId = $app['member_due_id_val'] ?? $app['member_due_id'] ?? 0;
      $paymentStatus = $app['payment_status'] ?? 'unpaid';
    ?>
    <div style="background: var(--bg-secondary, rgba(0,0,0,0.15)); border: 1px solid rgba(59,130,246,0.3); border-radius: 14px; padding: 28px 24px; text-align: center; margin-top: 10px;">
      <?php if ($paymentStatus === 'pending'): ?>
        <div style="font-size: 38px; margin-bottom: 10px;">🔍</div>
        <h2 style="font-size: 19px; margin-bottom: 8px; color:var(--text-primary);">Payment Proof Submitted & Under Verification</h2>
        <p class="muted" style="max-width: 540px; margin: 0 auto 16px; font-size: 14px; line-height: 1.6;">
          Your payment of <strong>₱<?php echo number_format($feeAmount, 2); ?></strong> for the Website Directory Advertising Fee is currently pending verification by the admin. Once approved, your profile editor will automatically unlock!
        </p>
        <span class="badge badge-pending" style="font-size: 12px; padding: 5px 12px;">Verification in Progress</span>
      <?php else: ?>
        <div style="font-size: 38px; margin-bottom: 10px;">💳</div>
        <h2 style="font-size: 19px; margin-bottom: 8px; color:var(--text-primary);">Advertising Fee Assigned</h2>
        <p class="muted" style="max-width: 540px; margin: 0 auto 16px; font-size: 14px; line-height: 1.6;">
          The Chapter Admin has approved your application and set the directory advertising fee to:
        </p>
        <div style="font-size: 28px; font-weight: 900; color:var(--accent-primary, #f5b800); margin-bottom: 18px;">
          ₱<?php echo number_format($feeAmount, 2); ?>
        </div>
        <?php if ($dueId > 0): ?>
          <a href="pay.php?member_due_id=<?php echo (int)$dueId; ?>" class="btn btn-success" style="padding: 12px 32px; font-size: 15px; font-weight: 700; text-decoration:none; display:inline-block;">
            Pay ₱<?php echo number_format($feeAmount, 2); ?> to Unlock Feature &rarr;
          </a>
        <?php else: ?>
          <a href="dashboard.php" class="btn" style="padding: 10px 24px;">View in My Dues</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <!-- ================= STATE 4: UNLOCKED! FULL PROFILE & GALLERY EDITOR ================= -->
    <div style="margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
      <span class="muted" style="font-size:13px;">Manage your directory details and project portfolio gallery below.</span>
      <a href="<?php echo BASE_URL; ?>/public/member_profile.php?prc=<?php echo urlencode($profile['id_number']); ?>" target="_blank" class="btn btn-sm" style="background:transparent; border:1px solid var(--accent-primary, #f5b800); color:var(--accent-primary, #f5b800); font-weight:700;">
        👁️ View Public Profile
      </a>
    </div>

    <form method="post" enctype="multipart/form-data" style="margin-top: 10px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_profile">

      <div class="grid-2">
        <div class="field">
          <label>Full Name</label>
          <input type="text" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required>
        </div>
        <div class="field">
          <label>PRC License Number</label>
          <input type="text" name="id_number" value="<?php echo htmlspecialchars($profile['id_number'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="grid-2">
        <div class="field">
          <label>Role / Title</label>
          <input type="text" name="role_title" value="<?php echo htmlspecialchars($profile['role_title'] ?? ''); ?>" placeholder="e.g. Principal Architect, Senior Architect" required>
        </div>
        <div class="field">
          <label>Architectural Specialty</label>
          <input type="text" name="specialty" value="<?php echo htmlspecialchars($profile['specialty'] ?? ''); ?>" placeholder="e.g. Sustainable & Residential Design, Commercial" required>
        </div>
      </div>

      <div class="grid-2">
        <div class="field">
          <label>Primary Location / Base</label>
          <input type="text" name="location" value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>" placeholder="e.g. Calapan City, Oriental Mindoro" required>
        </div>
        <div class="field">
          <label>QR Code Status</label>
          <div style="padding: 10px 14px; border: 1px dashed var(--border-color, rgba(255,255,255,0.2)); border-radius: 8px; background: var(--field-bg, rgba(0,0,0,0.1)); min-height: 44px; display:flex; align-items:center; justify-content:center; color:var(--text-secondary); font-size:13px;">
            Chapter Digital Verification Badge Active
          </div>
        </div>
      </div>

      <!-- MULTIPLE PROJECT PHOTOS & DESCRIPTIONS GALLERY -->
      <div style="background: var(--bg-secondary, rgba(0,0,0,0.15)); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 12px; padding: 22px; margin: 22px 0;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom: 12px;">
          <h3 style="margin:0; font-size:17px; color:var(--accent-primary, #f5b800);">📸 Project Portfolio Photos & Captions</h3>
          <span class="muted" style="font-size:12px;">Upload multiple architectural project photos</span>
        </div>

        <!-- Existing Photos List -->
        <?php if (!empty($gallery)): ?>
          <label style="font-size:13px; font-weight:700; color:var(--text-primary); margin-bottom:10px; display:block;">Current Uploaded Photos (<?php echo count($gallery); ?>):</label>
          <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 22px;">
            <?php foreach ($gallery as $idx => $photo): ?>
              <div style="background:var(--field-bg, rgba(0,0,0,0.25)); border:1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius:10px; padding:12px; display:flex; flex-direction:column; gap:10px;">
                <input type="hidden" name="existing_photos[<?php echo $idx; ?>][path]" value="<?php echo htmlspecialchars($photo['path']); ?>">
                <div style="position:relative; width:100%; height:160px; overflow:hidden; border-radius:6px; background:#000;">
                  <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($photo['path']); ?>" alt="Project Photo" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <div class="field" style="margin-bottom:0;">
                  <label style="font-size:11px; text-transform:uppercase; color:var(--text-secondary);">Photo Description / Caption</label>
                  <textarea name="existing_photos[<?php echo $idx; ?>][description]" rows="2" placeholder="Project name, concept, or description..." style="font-size:12px; width:100%;"><?php echo htmlspecialchars($photo['description'] ?? ''); ?></textarea>
                </div>
                <div style="display:flex; align-items:center; justify-content:flex-end;">
                  <label style="font-size:12px; color:#ef4444; display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600;">
                    <input type="checkbox" name="delete_photos[]" value="<?php echo $idx; ?>"> Delete this photo
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Upload New Photos Container -->
        <label style="font-size:13px; font-weight:700; color:var(--text-primary); margin-bottom:8px; display:block;">Add New Project Photos:</label>
        <div id="newPhotoSlotsContainer" style="display:flex; flex-direction:column; gap:14px;">
          <div class="photo-upload-slot" style="background:var(--field-bg, rgba(0,0,0,0.25)); border:1px dashed var(--border-color, rgba(255,255,255,0.15)); border-radius:10px; padding:16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
              <span style="font-size:13px; font-weight:700; color:var(--accent-primary, #f5b800);">New Photo Slot #1</span>
            </div>
            <div class="field" style="margin-bottom:10px;">
              <label style="font-size:12px;">Select Photo (JPG, PNG, WEBP)</label>
              <input type="file" name="new_photos[]" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="field" style="margin-bottom:0;">
              <label style="font-size:12px;">Photo Description / Caption</label>
              <textarea name="new_descriptions[]" rows="2" placeholder="Describe this project (e.g. project name, location, architectural concept)..."></textarea>
            </div>
          </div>
        </div>

        <div style="margin-top: 14px;">
          <button type="button" onclick="addNewPhotoSlot()" class="btn btn-sm" style="background:transparent; border:1px dashed var(--accent-primary, #f5b800); color:var(--accent-primary, #f5b800); font-weight:700; padding:8px 16px;">
            + Add Another Photo Slot
          </button>
        </div>
      </div>

      <div class="field">
        <label>Career Achievements & Practice</label>
        <textarea name="achievements" rows="4" placeholder="Describe your architectural experience and notable projects..."><?php echo htmlspecialchars($profile['achievements'] ?? ''); ?></textarea>
      </div>

      <div class="field">
        <label>Honors, Distinctions & Awards</label>
        <textarea name="awards" rows="4" placeholder="List your professional awards or chapter distinctions..."><?php echo htmlspecialchars($profile['awards'] ?? ''); ?></textarea>
      </div>

      <button class="btn btn-success" type="submit" style="padding: 12px 28px; font-weight:700; font-size:15px;">Save Website Profile</button>
    </form>

    <script>
    let slotCounter = 1;
    function addNewPhotoSlot() {
      slotCounter++;
      const container = document.getElementById('newPhotoSlotsContainer');
      const div = document.createElement('div');
      div.className = 'photo-upload-slot';
      div.style.cssText = 'background:var(--field-bg, rgba(0,0,0,0.25)); border:1px dashed var(--border-color, rgba(255,255,255,0.15)); border-radius:10px; padding:16px; margin-top:10px;';
      div.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <span style="font-size:13px; font-weight:700; color:var(--accent-primary, #f5b800);">New Photo Slot #${slotCounter}</span>
          <button type="button" onclick="this.closest('.photo-upload-slot').remove()" style="background:transparent; border:none; color:#ef4444; cursor:pointer; font-size:12px; font-weight:700;">✕ Remove Slot</button>
        </div>
        <div class="field" style="margin-bottom:10px;">
          <label style="font-size:12px;">Select Photo (JPG, PNG, WEBP)</label>
          <input type="file" name="new_photos[]" accept=".jpg,.jpeg,.png,.webp">
        </div>
        <div class="field" style="margin-bottom:0;">
          <label style="font-size:12px;">Photo Description / Caption</label>
          <textarea name="new_descriptions[]" rows="2" placeholder="Describe this project (e.g. project name, location, architectural concept)..."></textarea>
        </div>
      `;
      container.appendChild(div);
    }
    </script>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
