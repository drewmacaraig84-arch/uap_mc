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
        if ($idNumber === '') {
            $uPr = $pdo->prepare("SELECT id_number FROM users WHERE id = ?");
            $uPr->execute([$userId]);
            $idNumber = $uPr->fetchColumn() ?: '';
        }
        $role = trim($_POST['role_title'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $linkUrl = trim($_POST['link_url'] ?? '');
        $linkType = trim($_POST['link_type'] ?? 'auto');
        if (!in_array($linkType, ['auto', 'facebook', 'instagram', 'linkedin', 'youtube', 'telegram', 'website'], true)) {
            $linkType = 'auto';
        }
        if ($linkUrl !== '' && !preg_match('#^https?://#i', $linkUrl)) {
            $linkUrl = 'https://' . ltrim($linkUrl, '/');
        }
        $achievements = trim($_POST['achievements'] ?? '');
        $awards = trim($_POST['awards'] ?? '');

        // 1. Process Completed Works Projects Portfolio
        $projects = [];
        $rawProjects = $_POST['projects'] ?? [];
        $uploadDir = __DIR__ . '/../uploads/members/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

        if (is_array($rawProjects)) {
            foreach ($rawProjects as $pKey => $pData) {
                $pTitle = trim($pData['title'] ?? '');
                $pId = !empty($pData['id']) ? trim($pData['id']) : ('proj_' . time() . '_' . bin2hex(random_bytes(3)));
                $pCat = trim($pData['category'] ?? 'RESIDENTIAL');
                $pLoc = trim($pData['location'] ?? '');
                $pDesc = trim($pData['description'] ?? '');
                $pTeam = trim($pData['project_team'] ?? '');
                $existingCover = trim($pData['existing_cover'] ?? '');
                $existingPhotos = $pData['existing_photos'] ?? [];
                $deletePhotos = $pData['delete_photos'] ?? [];

                // Filter existing photos
                $photosList = [];
                if (!empty($existingCover) && !in_array('cover', $deletePhotos, true)) {
                    $photosList[] = $existingCover;
                }
                if (is_array($existingPhotos)) {
                    foreach ($existingPhotos as $phIdx => $phPath) {
                        if (!empty($phPath) && !in_array((string)$phIdx, $deletePhotos, true) && !in_array($phPath, $photosList, true)) {
                            $photosList[] = $phPath;
                        }
                    }
                }

                // Handle Newly Uploaded Cover Photo
                if (isset($_FILES['projects']['name'][$pKey]['cover']) && $_FILES['projects']['error'][$pKey]['cover'] === UPLOAD_ERR_OK) {
                    $cName = $_FILES['projects']['name'][$pKey]['cover'];
                    $cExt = strtolower(pathinfo($cName, PATHINFO_EXTENSION));
                    if (in_array($cExt, $allowedExts) && $_FILES['projects']['size'][$pKey]['cover'] <= 12 * 1024 * 1024) {
                        $uniqueName = 'proj_cover_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $cExt;
                        $targetPath = $uploadDir . $uniqueName;
                        if (move_uploaded_file($_FILES['projects']['tmp_name'][$pKey]['cover'], $targetPath)) {
                            // Prepend or replace cover
                            if (!empty($photosList)) {
                                $photosList[0] = 'uploads/members/' . $uniqueName;
                            } else {
                                $photosList[] = 'uploads/members/' . $uniqueName;
                            }
                        }
                    }
                }

                // Handle Newly Uploaded Additional Photos (up to limit of 5 total photos)
                if (isset($_FILES['projects']['name'][$pKey]['photos']) && is_array($_FILES['projects']['name'][$pKey]['photos'])) {
                    foreach ($_FILES['projects']['name'][$pKey]['photos'] as $fIdx => $filename) {
                        if (count($photosList) >= 5) break; // Limit to 5 total photos including cover
                        if (isset($_FILES['projects']['error'][$pKey]['photos'][$fIdx]) && $_FILES['projects']['error'][$pKey]['photos'][$fIdx] === UPLOAD_ERR_OK && !empty($filename)) {
                            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            if (in_array($ext, $allowedExts) && $_FILES['projects']['size'][$pKey]['photos'][$fIdx] <= 12 * 1024 * 1024) {
                                $uniqueName = 'proj_photo_' . $userId . '_' . time() . '_' . $fIdx . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                                $targetPath = $uploadDir . $uniqueName;
                                if (move_uploaded_file($_FILES['projects']['tmp_name'][$pKey]['photos'][$fIdx], $targetPath)) {
                                    if (count($photosList) < 5) {
                                        $photosList[] = 'uploads/members/' . $uniqueName;
                                    }
                                }
                            }
                        }
                    }
                }

                // Enforce max 5 photos total per project
                $photosList = array_values(array_unique($photosList));
                $photosList = array_slice($photosList, 0, 5);

                if (!empty($pTitle) || !empty($photosList)) {
                    $coverPhoto = $photosList[0] ?? '';
                    $projects[] = [
                        'id' => $pId,
                        'title' => $pTitle !== '' ? $pTitle : 'Completed Architectural Work',
                        'category' => $pCat !== '' ? strtoupper($pCat) : 'RESIDENTIAL',
                        'location' => $pLoc !== '' ? $pLoc : $location,
                        'description' => $pDesc,
                        'project_team' => $pTeam,
                        'cover_photo' => $coverPhoto,
                        'photos' => $photosList
                    ];
                }
            }
        }

        $projectsJson = json_encode($projects);

        // Build flat gallery for legacy consumers
        $gallery = [];
        foreach ($projects as $proj) {
            foreach ($proj['photos'] as $ph) {
                $gallery[] = [
                    'path' => $ph,
                    'description' => $proj['title']
                ];
            }
        }
        $galleryJson = json_encode($gallery);

        // Check if user has an existing profile photo in users table
        $uStmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $userPhoto = $uStmt->fetchColumn();
        // STRICT: Never use project cover photos for the member's profile avatar
        $firstPhoto = !empty($userPhoto) ? $userPhoto : null;
        $firstDesc = null;

        ensure_user_profile_photo_column($pdo);
        $stmt = $pdo->prepare("INSERT INTO website_members 
            (user_id, name, id_number, role_title, specialty, location, company_name, link_url, link_type, achievements, awards, photo_path, photo_description, gallery_json, projects_json, is_published)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                id_number = VALUES(id_number),
                role_title = VALUES(role_title),
                specialty = VALUES(specialty),
                location = VALUES(location),
                company_name = VALUES(company_name),
                link_url = VALUES(link_url),
                link_type = VALUES(link_type),
                achievements = VALUES(achievements),
                awards = VALUES(awards),
                photo_path = VALUES(photo_path),
                photo_description = VALUES(photo_description),
                gallery_json = VALUES(gallery_json),
                projects_json = VALUES(projects_json),
                is_published = 1");
        $stmt->execute([
            $userId,
            $name,
            $idNumber,
            $role,
            $specialty,
            $location,
            $companyName !== '' ? $companyName : null,
            $linkUrl !== '' ? $linkUrl : null,
            $linkType,
            $achievements,
            $awards,
            $firstPhoto,
            $firstDesc,
            $galleryJson,
            $projectsJson
        ]);

        // Automatically generate public QR code for this website directory member
        if (file_exists(__DIR__ . '/../includes/qr_helper.php')) {
            require_once __DIR__ . '/../includes/qr_helper.php';
            $fStmt = $pdo->prepare("SELECT id FROM website_members WHERE user_id = ?");
            $fStmt->execute([$userId]);
            $wmId = (int)$fStmt->fetchColumn();
            if ($wmId > 0 && function_exists('generate_member_directory_qr')) {
                generate_member_directory_qr($pdo, $wmId, true);
            }
        }

        $success = 'Your website directory profile & completed works portfolio have been published!';
    }
}

// Fetch member's current profile record
$record = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ? LIMIT 1");
$record->execute([$userId]);
$profile = $record->fetch();

// Fetch current user details
$uStmt = $pdo->prepare("SELECT name, id_number, profile_photo FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$uRow = $uStmt->fetch();
$userProfilePhoto = $uRow['profile_photo'] ?? null;

// Fetch current user details as initial fallback
if (!$profile) {
    $profile = [
        'name' => $uRow['name'] ?? '',
        'id_number' => $uRow['id_number'] ?? '',
        'role_title' => 'Architect',
        'specialty' => '',
        'location' => 'Mindoro',
        'company_name' => '',
        'link_url' => '',
        'link_type' => 'auto',
        'achievements' => '',
        'awards' => '',
        'photo_path' => $userProfilePhoto,
        'gallery_json' => '',
        'projects_json' => ''
    ];
}

// Decode Completed Works projects
$projects = [];
if (!empty($profile['projects_json'])) {
    $decodedProjects = json_decode($profile['projects_json'], true);
    if (is_array($decodedProjects)) $projects = $decodedProjects;
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
      <span class="badge badge-paid" style="font-size:13px; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('sparkles', '', 14); ?> <span>Feature Unlocked</span>
      </span>
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
      <div style="width: 56px; height: 56px; border-radius: 14px; background: rgba(245,158,11,0.12); color: var(--accent-primary, #f5b800); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px;">
        <?php echo icon('building', '', 32); ?>
      </div>
      <h2 style="font-size: 20px; margin-bottom: 8px; color:var(--text-primary);">Apply to be Featured on the Website Directory</h2>
      <p class="muted" style="max-width: 560px; margin: 0 auto 24px; font-size: 14px; line-height: 1.6;">
        Promote your architectural profile, showcase multiple completed project photos, and reach clients looking for professional architects across Mindoro.
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
            <div style="width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:rgba(245,158,11,0.15); color:#f5b800; flex-shrink:0;">
              <?php echo icon('sparkles', '', 20); ?>
            </div>
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
      <div style="width: 50px; height: 50px; border-radius: 14px; background: rgba(245,158,11,0.12); color: #f59e0b; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
        <?php echo icon('clock', '', 26); ?>
      </div>
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
        <div style="width: 50px; height: 50px; border-radius: 14px; background: rgba(59,130,246,0.12); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
          <?php echo icon('search', '', 26); ?>
        </div>
        <h2 style="font-size: 19px; margin-bottom: 8px; color:var(--text-primary);">Payment Proof Submitted & Under Verification</h2>
        <p class="muted" style="max-width: 540px; margin: 0 auto 16px; font-size: 14px; line-height: 1.6;">
          Your payment of <strong>₱<?php echo number_format($feeAmount, 2); ?></strong> for the Website Directory Advertising Fee is currently pending verification by the admin. Once approved, your profile editor will automatically unlock!
        </p>
        <span class="badge badge-pending" style="font-size: 12px; padding: 5px 12px;">Verification in Progress</span>
      <?php else: ?>
        <div style="width: 50px; height: 50px; border-radius: 14px; background: rgba(245,158,11,0.12); color: #f59e0b; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
          <?php echo icon('wallet', '', 26); ?>
        </div>
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
    <!-- ================= STATE 4: UNLOCKED! FULL PROFILE & COMPLETED WORKS EDITOR ================= -->
    <div style="margin-bottom: 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
      <span class="muted" style="font-size:13px;">Manage your directory details and completed works portfolio below.</span>
      <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <a href="download_qr.php" class="btn btn-sm btn-primary" style="font-weight:700; display:inline-flex; align-items:center; gap:6px;">
          <?php echo icon('download', '', 14); ?> <span>Download Public QR Code</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/profile/<?php echo (int)($profile['id'] ?? 0); ?>" target="_blank" class="btn btn-sm" style="background:transparent; border:1px solid var(--accent-primary, #f5b800); color:var(--accent-primary, #f5b800); font-weight:700; display:inline-flex; align-items:center; gap:6px;">
          <?php echo icon('eye', '', 14); ?> <span>View Public Profile</span>
        </a>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data" style="margin-top: 10px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_profile">

      <!-- PROFILE PHOTO LINKED TO MY PROFILE -->
      <div style="background: var(--bg-secondary, rgba(0,0,0,0.15)); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 12px; padding: 18px; margin-bottom: 22px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 16px;">
          <div style="width: 64px; height: 64px; border-radius: 50%; overflow: hidden; background: var(--card-bg, #1a2234); border: 2px solid var(--border-color-gold, #f5b800); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <?php if ($userProfilePhoto): ?>
              <img src="<?php echo htmlspecialchars(media_url($userProfilePhoto)); ?>" alt="Profile Avatar" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
              <span style="font-size: 20px; font-weight: 700; color: var(--accent-primary, #f5b800);">
                <?php echo strtoupper(substr($profile['name'] ?? 'AR', 0, 2)); ?>
              </span>
            <?php endif; ?>
          </div>
          <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
              <strong style="font-size: 15px; color: var(--text-primary);">Website Directory Profile Avatar</strong>
              <?php if ($userProfilePhoto): ?>
                <span class="badge-pill" style="background: rgba(16,185,129,0.15); color: #10b981; font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                  <?php echo icon('check', '', 12); ?> <span>Linked to My Profile</span>
                </span>
              <?php endif; ?>
            </div>
            <p class="muted" style="font-size: 12.5px; margin: 0; line-height: 1.4;">
              <?php if ($userProfilePhoto): ?>
                Your profile picture from <strong>My Profile</strong> is automatically displayed on the chapter website directory.
              <?php else: ?>
                No profile picture set yet in My Profile. You can upload an official portrait in My Profile anytime.
              <?php endif; ?>
            </p>
          </div>
        </div>
        <a href="profile.php" class="btn btn-sm btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; padding: 8px 14px;">
          <?php echo icon('camera', '', 13); ?> <span>Update in My Profile &rarr;</span>
        </a>
      </div>

      <div class="field">
        <label>Full Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required>
      </div>

      <div class="grid-2">
        <div class="field">
          <label>Company / Architectural Firm Name</label>
          <input type="text" name="company_name" value="<?php echo htmlspecialchars($profile['company_name'] ?? ''); ?>" placeholder="e.g. Ting & Associates Architects, AESTRUKTURA Design Studio">
        </div>
        <div class="field">
          <label>Architectural Specialty</label>
          <input type="text" name="specialty" value="<?php echo htmlspecialchars($profile['specialty'] ?? ''); ?>" placeholder="e.g. Sustainable & Residential Design, Commercial" required>
        </div>
      </div>

      <div class="grid-2">
        <div class="field">
          <label>Role / Title</label>
          <input type="text" name="role_title" value="<?php echo htmlspecialchars($profile['role_title'] ?? ''); ?>" placeholder="e.g. Principal Architect, Project Architect, General Manager" required>
        </div>
        <div class="field">
          <label>Address</label>
          <input type="text" name="location" value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>" placeholder="e.g. Calapan City, Oriental Mindoro (or full office address)" required>
        </div>
      </div>

      <div class="grid-2">
        <div class="field">
          <label>Website / Social Media Link</label>
          <input type="text" name="link_url" value="<?php echo htmlspecialchars($profile['link_url'] ?? ''); ?>" placeholder="https://facebook.com/..., https://instagram.com/..., https://myfirm.com">
        </div>
        <div class="field">
          <label>Link Platform / Icon Style</label>
          <?php $curType = $profile['link_type'] ?? 'auto'; ?>
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
      </div>

      <!-- COMPLETED WORKS & PROJECT PORTFOLIO MANAGER -->
      <div style="background: var(--bg-secondary, rgba(0,0,0,0.15)); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 14px; padding: 22px; margin: 24px 0; width: 100%; box-sizing: border-box;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom: 16px;">
          <div>
            <h3 style="margin:0; font-size:18px; color:var(--accent-primary, #f5b800); display:inline-flex; align-items:center; gap:8px;">
              <?php echo icon('camera', '', 20); ?> <span>Completed Works &amp; Projects Portfolio</span>
            </h3>
            <p class="muted" style="font-size:12.5px; margin:4px 0 0;">Add completed architectural works with cover photo, team credits, and up to 5 photos per project.</p>
          </div>
        </div>

        <?php 
          $renderProjects = !empty($projects) ? $projects : [];
        ?>

        <div id="noProjectsNotice" style="<?php echo empty($renderProjects) ? 'display:block;' : 'display:none;'; ?> padding: 28px 20px; text-align: center; background: var(--field-bg, rgba(0,0,0,0.18)); border: 1px dashed var(--border-color, rgba(255,255,255,0.15)); border-radius: 12px; margin-bottom: 16px;">
          <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(245,158,11,0.12); color: var(--accent-primary, #f5b800); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
            <?php echo icon('camera', '', 20); ?>
          </div>
          <strong style="display:block; font-size:14px; color:var(--text-primary); margin-bottom:4px;">No completed works added yet</strong>
          <p class="muted" style="font-size:12.5px; margin:0 auto; max-width:480px;">Click the <strong>"+ Add Completed Work / Project"</strong> button below to manually showcase your architectural projects with custom cover photos, narratives, and collaborators.</p>
        </div>

        <div id="projectsContainer" style="display: flex; flex-direction: column; gap: 20px; width: 100%;">
          <?php foreach ($renderProjects as $pIdx => $proj): ?>
            <?php 
              $coverPath = $proj['cover_photo'] ?? ($proj['photos'][0] ?? '');
              $otherPhotos = array_values(array_filter($proj['photos'] ?? [], function($ph) use ($coverPath) {
                  return $ph !== $coverPath;
              }));
              $totalPhotosCount = (!empty($coverPath) ? 1 : 0) + count($otherPhotos);
            ?>
            <div class="project-card-item" style="background: var(--field-bg, rgba(0,0,0,0.25)); border: 1px solid var(--border-color, rgba(255,255,255,0.12)); border-radius: 12px; padding: 18px; width: 100%; box-sizing: border-box;">
              <input type="hidden" name="projects[<?php echo $pIdx; ?>][id]" value="<?php echo htmlspecialchars($proj['id'] ?? ('proj_' . $pIdx)); ?>">
              <input type="hidden" name="projects[<?php echo $pIdx; ?>][existing_cover]" value="<?php echo htmlspecialchars($coverPath); ?>">

              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.08));">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <span style="font-weight: 800; font-size: 14px; color: var(--accent-primary, #f5b800);">Completed Work #<?php echo $pIdx + 1; ?>:</span>
                  <strong style="font-size: 14px; color: var(--text-primary);"><?php echo htmlspecialchars(!empty($proj['title']) ? $proj['title'] : 'New Architectural Work'); ?></strong>
                </div>
                <button type="button" onclick="removeProjectCard(this)" class="btn btn-sm btn-secondary" style="color: #ef4444; border-color: rgba(239,68,68,0.3); font-size: 11px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">
                  <?php echo icon('trash', '', 12); ?> <span>Delete Project</span>
                </button>
              </div>

              <div class="grid-3" style="gap: 12px; margin-bottom: 12px;">
                <div class="field" style="margin-bottom:0;">
                  <label style="font-size:12px;">Project Title / Name *</label>
                  <input type="text" name="projects[<?php echo $pIdx; ?>][title]" value="<?php echo htmlspecialchars($proj['title'] ?? ''); ?>" placeholder="e.g. Casa San Gregorio, DLSU-D Building" required>
                </div>
                <div class="field" style="margin-bottom:0;">
                  <label style="font-size:12px;">Project Category</label>
                  <input type="text" name="projects[<?php echo $pIdx; ?>][category]" value="<?php echo htmlspecialchars($proj['category'] ?? 'RESIDENTIAL'); ?>" placeholder="e.g. RESIDENTIAL, COMMERCIAL, INSTITUTIONAL">
                </div>
                <div class="field" style="margin-bottom:0;">
                  <label style="font-size:12px;">Location</label>
                  <input type="text" name="projects[<?php echo $pIdx; ?>][location]" value="<?php echo htmlspecialchars($proj['location'] ?? ''); ?>" placeholder="e.g. Makati, Manila">
                </div>
              </div>

              <div class="grid-2" style="gap: 16px; margin-bottom: 16px;">
                <div class="field" style="margin-bottom:0;">
                  <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Architectural Concept &amp; Narrative</label>
                  <textarea name="projects[<?php echo $pIdx; ?>][description]" rows="7" placeholder="Describe the design philosophy, space planning, materials, volumetric form, and concept..." style="font-size:13.5px; line-height:1.65; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"><?php echo htmlspecialchars($proj['description'] ?? ''); ?></textarea>
                </div>
                <div class="field" style="margin-bottom:0;">
                  <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Team / Collaborators</label>
                  <textarea name="projects[<?php echo $pIdx; ?>][project_team]" rows="7" placeholder="e.g. Ar. Anthony Nazareno&#10;Ar. Vladimir Banks&#10;IDr. Marielle Saguibo&#10;Engr. Juan Dela Cruz" style="font-size:13.5px; line-height:1.6; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"><?php echo htmlspecialchars($proj['project_team'] ?? ''); ?></textarea>
                </div>
              </div>

              <!-- Photos Section (Cover + Additional, Limit to 5 total) -->
              <div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 14px; border: 1px solid var(--border-color, rgba(255,255,255,0.06));">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                  <strong style="font-size: 13px; color: var(--text-primary);">Project Photos (Max 5 photos total including Cover)</strong>
                  <span style="font-size: 11.5px; color: var(--accent-primary, #f5b800); font-weight: 700;"><?php echo $totalPhotosCount; ?> / 5 Photos</span>
                </div>

                <!-- Cover Photo Block -->
                <div style="margin-bottom: 12px; padding: 10px; background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.25); border-radius: 8px;">
                  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                    <span style="font-size: 12px; font-weight: 700; color: var(--accent-primary, #f5b800);">★ Cover Photo (Front Thumbnail on Directory Grid)</span>
                    <?php if ($coverPath): ?>
                      <label style="font-size: 11.5px; color: #ef4444; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                        <input type="checkbox" name="projects[<?php echo $pIdx; ?>][delete_photos][]" value="cover"> Remove Cover
                      </label>
                    <?php endif; ?>
                  </div>

                  <?php if ($coverPath): ?>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                      <div style="width: 90px; height: 60px; border-radius: 6px; overflow: hidden; background: #000; flex-shrink: 0;">
                        <img src="<?php echo htmlspecialchars(media_url($coverPath)); ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                      </div>
                      <span class="muted" style="font-size: 11.5px;">Current cover photo</span>
                    </div>
                  <?php endif; ?>

                  <input type="file" name="projects[<?php echo $pIdx; ?>][cover]" accept=".jpg,.jpeg,.png,.webp" style="font-size: 12px;">
                </div>

                <!-- Existing Additional Photos -->
                <?php if (!empty($otherPhotos)): ?>
                  <label style="font-size: 12px; color: var(--text-secondary); display: block; margin-bottom: 6px;">Additional Gallery Photos:</label>
                  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; margin-bottom: 12px;">
                    <?php foreach ($otherPhotos as $oIdx => $oPath): ?>
                      <div style="background: rgba(0,0,0,0.3); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 6px; padding: 6px; display: flex; flex-direction: column; gap: 4px;">
                        <input type="hidden" name="projects[<?php echo $pIdx; ?>][existing_photos][<?php echo $oIdx; ?>]" value="<?php echo htmlspecialchars($oPath); ?>">
                        <div style="width: 100%; height: 75px; border-radius: 4px; overflow: hidden; background: #000;">
                          <img src="<?php echo htmlspecialchars(media_url($oPath)); ?>" alt="Photo" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <label style="font-size: 11px; color: #ef4444; display: flex; align-items: center; gap: 4px; cursor: pointer;">
                          <input type="checkbox" name="projects[<?php echo $pIdx; ?>][delete_photos][]" value="<?php echo $oIdx; ?>"> Delete
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <!-- Add More Photos File Input -->
                <?php if ($totalPhotosCount < 5): ?>
                  <div class="field" style="margin-bottom:0;">
                    <label style="font-size: 12px;">Upload Additional Photos (Up to <?php echo 5 - $totalPhotosCount; ?> more files):</label>
                    <input type="file" name="projects[<?php echo $pIdx; ?>][photos][]" accept=".jpg,.jpeg,.png,.webp" multiple style="font-size: 12px;">
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="margin-top: 18px;">
          <button type="button" id="btnAddProject" class="btn btn-sm" style="background: rgba(245,158,11,0.08); border: 1px dashed var(--accent-primary, #f5b800); color: var(--accent-primary, #f5b800); font-weight: 700; padding: 10px 18px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
            <?php echo icon('plus', '', 14); ?> <span>Add Another Completed Work Project</span>
          </button>
        </div>
      </div>

      <div class="field" style="margin-top: 20px;">
        <label>Career Achievements &amp; Practice</label>
        <textarea name="achievements" rows="3" placeholder="Describe your architectural experience, publications, or notable milestones..."><?php echo htmlspecialchars($profile['achievements'] ?? ''); ?></textarea>
      </div>

      <div class="field">
        <label>Honors, Distinctions &amp; Awards</label>
        <textarea name="awards" rows="3" placeholder="List your professional design awards or chapter recognitions..."><?php echo htmlspecialchars($profile['awards'] ?? ''); ?></textarea>
      </div>

      <div style="margin-top: 24px;">
        <button class="btn btn-success" type="submit" style="padding: 12px 28px; font-weight:700; font-size:15px;">Save Completed Works &amp; Profile</button>
      </div>
    </form>

    <script>
    (function() {
      var projectCount = <?php echo count($renderProjects); ?>;

      window.removeProjectCard = function(btn) {
        var card = btn.closest('.project-card-item');
        if (card) {
          card.remove();
        }
        var container = document.getElementById('projectsContainer');
        var notice = document.getElementById('noProjectsNotice');
        if (container && notice) {
          if (container.querySelectorAll('.project-card-item').length === 0) {
            notice.style.display = 'block';
          }
        }
      };

      var btnAdd = document.getElementById('btnAddProject');
      if (btnAdd) {
        btnAdd.addEventListener('click', function(e) {
          e.preventDefault();
          var notice = document.getElementById('noProjectsNotice');
          if (notice) notice.style.display = 'none';

          projectCount++;
          var timestamp = Date.now();
          var pIdx = 'new_' + timestamp;
          var container = document.getElementById('projectsContainer');
          if (!container) return;

          var div = document.createElement('div');
          div.className = 'project-card-item';
          div.style.cssText = 'background: var(--field-bg, rgba(0,0,0,0.25)); border: 1px dashed var(--border-color-gold, rgba(245,158,11,0.35)); border-radius: 12px; padding: 18px; width: 100%; box-sizing: border-box;';
          
          div.innerHTML = [
            '<input type="hidden" name="projects[' + pIdx + '][id]" value="proj_' + timestamp + '">',
            '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.08));">',
            '  <div style="display: flex; align-items: center; gap: 8px;">',
            '    <span style="font-weight: 800; font-size: 14px; color: var(--accent-primary, #f5b800);">New Completed Work #' + projectCount + ':</span>',
            '    <span class="muted" style="font-size: 13px;">(Unsaved)</span>',
            '  </div>',
            '  <button type="button" onclick="removeProjectCard(this)" class="btn btn-sm btn-secondary" style="color: #ef4444; border-color: rgba(239,68,68,0.3); font-size: 11px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">',
            '    <span>Delete Project</span>',
            '  </button>',
            '</div>',
            '<div class="grid-3" style="gap: 12px; margin-bottom: 12px;">',
            '  <div class="field" style="margin-bottom:0;">',
            '    <label style="font-size:12px;">Project Title / Name *</label>',
            '    <input type="text" name="projects[' + pIdx + '][title]" placeholder="e.g. Casa San Gregorio, DLSU-D Building" required>',
            '  </div>',
            '  <div class="field" style="margin-bottom:0;">',
            '    <label style="font-size:12px;">Project Category</label>',
            '    <input type="text" name="projects[' + pIdx + '][category]" placeholder="e.g. RESIDENTIAL, COMMERCIAL, INSTITUTIONAL" value="RESIDENTIAL">',
            '  </div>',
            '  <div class="field" style="margin-bottom:0;">',
            '    <label style="font-size:12px;">Location</label>',
            '    <input type="text" name="projects[' + pIdx + '][location]" placeholder="e.g. Makati, Manila">',
            '  </div>',
            '</div>',
            '<div class="grid-2" style="gap: 16px; margin-bottom: 16px;">',
            '  <div class="field" style="margin-bottom:0;">',
            '    <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Architectural Concept &amp; Narrative</label>',
            '    <textarea name="projects[' + pIdx + '][description]" rows="7" placeholder="Describe the design philosophy, space planning, materials, volumetric form, and concept..." style="font-size:13.5px; line-height:1.65; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"></textarea>',
            '  </div>',
            '  <div class="field" style="margin-bottom:0;">',
            '    <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Team / Collaborators</label>',
            '    <textarea name="projects[' + pIdx + '][project_team]" rows="7" placeholder="e.g. Ar. Anthony Nazareno&#10;Ar. Vladimir Banks&#10;IDr. Marielle Saguibo&#10;Engr. Juan Dela Cruz" style="font-size:13.5px; line-height:1.6; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"></textarea>',
            '  </div>',
            '</div>',
            '<div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 14px; border: 1px solid var(--border-color, rgba(255,255,255,0.06));">',
            '  <div style="margin-bottom: 12px; padding: 10px; background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.25); border-radius: 8px;">',
            '    <span style="font-size: 12px; font-weight: 700; color: var(--accent-primary, #f5b800); display: block; margin-bottom: 6px;">★ Cover Photo (Front Thumbnail on Directory Grid) *</span>',
            '    <input type="file" name="projects[' + pIdx + '][cover]" accept=".jpg,.jpeg,.png,.webp" required style="font-size: 12px;">',
            '  </div>',
            '  <div class="field" style="margin-bottom:0;">',
            '    <label style="font-size: 12px;">Upload Additional Photos (Up to 4 more files, max 5 total photos):</label>',
            '    <input type="file" name="projects[' + pIdx + '][photos][]" accept=".jpg,.jpeg,.png,.webp" multiple style="font-size: 12px;">',
            '  </div>',
            '</div>'
          ].join('');

          container.appendChild(div);
        });
      }
    })();
    </script>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
