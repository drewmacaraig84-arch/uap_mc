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

// 1. Handle Profile Details Save (Only if unlocked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile_details') {
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

        // Process up to 3 Website / Social Media Links
        $links = [];
        $rawLinks = $_POST['links'] ?? [];
        if (is_array($rawLinks)) {
            foreach ($rawLinks as $lnk) {
                $u = trim($lnk['url'] ?? '');
                $t = trim($lnk['type'] ?? 'auto');
                if (!in_array($t, ['auto', 'facebook', 'instagram', 'linkedin', 'youtube', 'telegram', 'website'], true)) {
                    $t = 'auto';
                }
                if ($u !== '') {
                    if (!preg_match('#^https?://#i', $u)) {
                        $u = 'https://' . ltrim($u, '/');
                    }
                    $links[] = [
                        'url' => $u,
                        'type' => $t
                    ];
                }
            }
        }
        // Fallback to legacy single link inputs if rawLinks was empty
        if (empty($links) && !empty($_POST['link_url'])) {
            $u = trim($_POST['link_url']);
            $t = trim($_POST['link_type'] ?? 'auto');
            if ($u !== '') {
                if (!preg_match('#^https?://#i', $u)) $u = 'https://' . ltrim($u, '/');
                $links[] = ['url' => $u, 'type' => $t];
            }
        }
        $links = array_slice($links, 0, 3);
        $linksJson = !empty($links) ? json_encode($links) : null;
        $primaryLinkUrl = !empty($links[0]['url']) ? $links[0]['url'] : null;
        $primaryLinkType = !empty($links[0]['type']) ? $links[0]['type'] : 'auto';

        $achievements = trim($_POST['achievements'] ?? '');
        $awards = trim($_POST['awards'] ?? '');

        // Check if user has an existing profile photo in users table
        $uStmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $userPhoto = $uStmt->fetchColumn();
        $firstPhoto = !empty($userPhoto) ? $userPhoto : null;

        ensure_user_profile_photo_column($pdo);
        $stmt = $pdo->prepare("INSERT INTO website_members 
            (user_id, name, id_number, role_title, specialty, location, company_name, link_url, link_type, links_json, achievements, awards, photo_path, is_published)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                id_number = VALUES(id_number),
                role_title = VALUES(role_title),
                specialty = VALUES(specialty),
                location = VALUES(location),
                company_name = VALUES(company_name),
                link_url = VALUES(link_url),
                link_type = VALUES(link_type),
                links_json = VALUES(links_json),
                achievements = VALUES(achievements),
                awards = VALUES(awards),
                photo_path = VALUES(photo_path),
                is_published = 1");
        $stmt->execute([
            $userId,
            $name,
            $idNumber,
            $role,
            $specialty,
            $location,
            $companyName !== '' ? $companyName : null,
            $primaryLinkUrl,
            $primaryLinkType,
            $linksJson,
            $achievements,
            $awards,
            $firstPhoto
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

        $success = 'Directory profile details saved successfully!';
    }
}

// 2. Handle Individual Project Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_project') {
    require_csrf();

    if (!$isUnlocked) {
        $error = 'You must complete and verify your directory advertising payment to unlock this feature.';
    } else {
        // Fetch current projects
        $currStmt = $pdo->prepare("SELECT projects_json FROM website_members WHERE user_id = ?");
        $currStmt->execute([$userId]);
        $currJson = $currStmt->fetchColumn();
        $projectsList = [];
        if (!empty($currJson)) {
            $dec = json_decode($currJson, true);
            if (is_array($dec)) $projectsList = $dec;
        }

        $pId = trim($_POST['project_id'] ?? '');
        if ($pId === '' || str_starts_with($pId, 'new_')) {
            $pId = 'proj_' . time() . '_' . bin2hex(random_bytes(3));
        }
        $pTitle = trim($_POST['title'] ?? '');
        $pCat = trim($_POST['category'] ?? 'RESIDENTIAL');
        $pLoc = trim($_POST['location'] ?? '');
        $pDesc = trim($_POST['description'] ?? '');
        $pTeam = trim($_POST['project_team'] ?? '');
        $existingCover = trim($_POST['existing_cover'] ?? '');
        $existingPhotos = $_POST['existing_photos'] ?? [];
        $deletePhotos = $_POST['delete_photos'] ?? [];

        $uploadDir = __DIR__ . '/../uploads/members/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

        $coverPhoto = '';
        $additionalPhotos = [];

        if (!empty($existingCover) && !in_array('cover', $deletePhotos, true)) {
            $coverPhoto = $existingCover;
        }
        if (is_array($existingPhotos)) {
            foreach ($existingPhotos as $phIdx => $phPath) {
                if (!empty($phPath) && !in_array((string)$phIdx, $deletePhotos, true)) {
                    if ($phPath !== $coverPhoto && !in_array($phPath, $additionalPhotos, true)) {
                        $additionalPhotos[] = $phPath;
                    }
                }
            }
        }

        // Handle Newly Uploaded Cover Photo
        if (isset($_FILES['cover']['name']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $cName = $_FILES['cover']['name'];
            $cExt = strtolower(pathinfo($cName, PATHINFO_EXTENSION));
            if (in_array($cExt, $allowedExts) && $_FILES['cover']['size'] <= 12 * 1024 * 1024) {
                $uniqueName = 'proj_cover_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $cExt;
                $targetPath = $uploadDir . $uniqueName;
                if (move_uploaded_file($_FILES['cover']['tmp_name'], $targetPath)) {
                    $coverPhoto = 'uploads/members/' . $uniqueName;
                }
            }
        }

        // Handle Newly Uploaded Additional Photos
        if (isset($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
            foreach ($_FILES['photos']['name'] as $fIdx => $filename) {
                if (count($additionalPhotos) + (!empty($coverPhoto) ? 1 : 0) >= 5) break;
                if (isset($_FILES['photos']['error'][$fIdx]) && $_FILES['photos']['error'][$fIdx] === UPLOAD_ERR_OK && !empty($filename)) {
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExts) && $_FILES['photos']['size'][$fIdx] <= 12 * 1024 * 1024) {
                        $uniqueName = 'proj_photo_' . $userId . '_' . time() . '_' . $fIdx . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                        $targetPath = $uploadDir . $uniqueName;
                        if (move_uploaded_file($_FILES['photos']['tmp_name'][$fIdx], $targetPath)) {
                            $additionalPhotos[] = 'uploads/members/' . $uniqueName;
                        }
                    }
                }
            }
        }

        // Combine: Cover photo is ALWAYS #1 in the photos array
        $allPhotos = [];
        if (!empty($coverPhoto)) {
            $allPhotos[] = $coverPhoto;
        }
        foreach ($additionalPhotos as $aph) {
            if (!in_array($aph, $allPhotos, true)) {
                $allPhotos[] = $aph;
            }
        }
        if (empty($coverPhoto) && !empty($allPhotos[0])) {
            $coverPhoto = $allPhotos[0];
        }
        $allPhotos = array_slice($allPhotos, 0, 5);

        $projectObj = [
            'id' => $pId,
            'title' => $pTitle !== '' ? $pTitle : 'Completed Architectural Work',
            'category' => $pCat !== '' ? strtoupper($pCat) : 'RESIDENTIAL',
            'location' => $pLoc !== '' ? $pLoc : '',
            'description' => $pDesc,
            'project_team' => $pTeam,
            'cover_photo' => $coverPhoto,
            'photos' => $allPhotos
        ];

        // Find index of existing project or append new
        $found = false;
        foreach ($projectsList as $idx => $existingProj) {
            if (($existingProj['id'] ?? '') === $pId) {
                $projectsList[$idx] = $projectObj;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $projectsList[] = $projectObj;
        }

        $projectsJson = json_encode(array_values($projectsList));

        // Build flat gallery for legacy consumers
        $gallery = [];
        foreach ($projectsList as $pr) {
            foreach ($pr['photos'] as $ph) {
                $gallery[] = [
                    'path' => $ph,
                    'description' => $pr['title']
                ];
            }
        }
        $galleryJson = json_encode($gallery);

        ensure_user_profile_photo_column($pdo);
        $uStmt = $pdo->prepare("UPDATE website_members SET projects_json = ?, gallery_json = ?, is_published = 1 WHERE user_id = ?");
        $uStmt->execute([$projectsJson, $galleryJson, $userId]);

        $success = 'Project "' . htmlspecialchars($projectObj['title']) . '" saved successfully!';
    }
}

// 3. Handle Individual Project Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_project') {
    require_csrf();
    $pId = trim($_POST['project_id'] ?? '');
    if ($pId !== '') {
        $currStmt = $pdo->prepare("SELECT projects_json FROM website_members WHERE user_id = ?");
        $currStmt->execute([$userId]);
        $currJson = $currStmt->fetchColumn();
        $projectsList = [];
        if (!empty($currJson)) {
            $dec = json_decode($currJson, true);
            if (is_array($dec)) $projectsList = $dec;
        }
        $projectsList = array_values(array_filter($projectsList, function($p) use ($pId) {
            return ($p['id'] ?? '') !== $pId;
        }));
        $projectsJson = json_encode($projectsList);

        $gallery = [];
        foreach ($projectsList as $pr) {
            foreach ($pr['photos'] as $ph) {
                $gallery[] = [
                    'path' => $ph,
                    'description' => $pr['title']
                ];
            }
        }
        $galleryJson = json_encode($gallery);

        $pdo->prepare("UPDATE website_members SET projects_json = ?, gallery_json = ? WHERE user_id = ?")->execute([$projectsJson, $galleryJson, $userId]);
        $success = 'Project deleted successfully!';
    }
}

// Fetch Profile
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
        'links_json' => '',
        'achievements' => '',
        'awards' => '',
        'photo_path' => $userProfilePhoto,
        'gallery_json' => '',
        'projects_json' => ''
    ];
}

// Decode social links (up to 3 links)
$socialLinks = [];
if (!empty($profile['links_json'])) {
    $decodedLinks = json_decode($profile['links_json'], true);
    if (is_array($decodedLinks)) $socialLinks = $decodedLinks;
}
if (empty($socialLinks) && !empty($profile['link_url'])) {
    $socialLinks[] = [
        'url' => $profile['link_url'],
        'type' => $profile['link_type'] ?? 'auto'
    ];
}
while (count($socialLinks) < 3) {
    $socialLinks[] = ['url' => '', 'type' => 'auto'];
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

  <?php if (!$isUnlocked): ?>
    <!-- Application & Unlock Flow -->
    <?php if (!$app): ?>
      <div style="background: rgba(245,158,11,0.05); border: 1px dashed rgba(245,158,11,0.3); border-radius: 12px; padding: 24px; text-align: center;">
        <div style="font-size: 32px; color: var(--accent-primary, #f5b800); margin-bottom: 8px;">★</div>
        <h3 style="margin-bottom: 8px;">Unlock Your Official Website Directory Listing</h3>
        <p class="muted" style="max-width: 540px; margin: 0 auto 20px; font-size: 14px;">
          Promote your architectural practice, services, portfolio, and contact links directly on the public UAP Mindoro Website.
        </p>
        <form method="POST" style="display: inline-block;">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="apply_directory">
          <button class="btn btn-primary" type="submit" style="padding: 10px 24px; font-weight:700;">Apply for Directory Feature</button>
        </form>
      </div>

    <?php elseif ($app['status'] === 'pending_fee'): ?>
      <div class="alert alert-info" style="display: flex; gap: 14px; align-items: center;">
        <div>
          <strong>Application Received &amp; Under Review</strong>
          <p style="margin: 4px 0 0; font-size: 13.5px;">Your application has been received. The Chapter Administrator will assign your advertising fee shortly. Please check back soon.</p>
        </div>
      </div>

    <?php elseif ($app['status'] === 'fee_set'): ?>
      <div style="background: rgba(59,130,246,0.08); border: 1px solid rgba(59,130,246,0.3); border-radius: 12px; padding: 22px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
          <div>
            <span class="badge badge-unpaid" style="font-size: 12px;">Payment Required</span>
            <h3 style="margin: 6px 0 0;">Website Directory Advertising Fee: ₱<?php echo number_format($app['fee_amount'], 2); ?></h3>
          </div>
          <?php if (!empty($app['due_id'])): ?>
            <a href="pay.php?id=<?php echo (int)$app['due_id']; ?>" class="btn btn-success" style="font-weight: 700; padding: 10px 20px;">
              Pay Now &rarr;
            </a>
          <?php else: ?>
            <a href="pay.php" class="btn btn-success" style="font-weight: 700; padding: 10px 20px;">
              Go to Payments &rarr;
            </a>
          <?php endif; ?>
        </div>
        <p class="muted" style="margin:0; font-size:13px;">
          Once your payment receipt is verified by the Chapter Treasurer, your website directory profile manager will be instantly activated.
        </p>
      </div>

    <?php elseif ($app['status'] === 'rejected'): ?>
      <div class="alert alert-error">
        <strong>Application Declined:</strong> <?php echo htmlspecialchars($app['remarks'] ?? 'Please contact the chapter admin for more details.'); ?>
      </div>
    <?php endif; ?>

  <?php else: ?>

    <!-- 1. DIRECTORY PROFILE DETAILS FORM -->
    <form method="POST">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save_profile_details">

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

      <!-- 1. FULL NAME -->
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required>
      </div>

      <!-- 2. COMPANY & ADDRESS -->
      <div class="grid-2">
        <div class="field">
          <label>Company / Architectural Firm Name</label>
          <input type="text" name="company_name" value="<?php echo htmlspecialchars($profile['company_name'] ?? ''); ?>" placeholder="e.g. Ting & Associates Architects, AESTRUKTURA Design Studio">
        </div>
        <div class="field">
          <label>Company / Office Address</label>
          <input type="text" name="location" value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>" placeholder="e.g. Calapan City, Oriental Mindoro (or full office address)" required>
        </div>
      </div>

      <!-- 3. ROLE / TITLE & SPECIALIZATION -->
      <div class="grid-2">
        <div class="field">
          <label>Role / Title</label>
          <input type="text" name="role_title" value="<?php echo htmlspecialchars($profile['role_title'] ?? ''); ?>" placeholder="e.g. Principal Architect, Project Architect, General Manager" required>
        </div>
        <div class="field">
          <label>Architectural Specialty / Specialization</label>
          <input type="text" name="specialty" value="<?php echo htmlspecialchars($profile['specialty'] ?? ''); ?>" placeholder="e.g. Sustainable & Residential Design, Commercial" required>
        </div>
      </div>

      <!-- 4. UP TO 3 WEBSITE / SOCIAL MEDIA LINKS -->
      <div style="background: var(--bg-secondary, rgba(0,0,0,0.12)); border: 1px solid var(--border-color, rgba(255,255,255,0.08)); border-radius: 12px; padding: 18px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
          <div>
            <strong style="font-size: 14px; color: var(--text-primary); display: block;">Website &amp; Social Media Showcase Links</strong>
            <span class="muted" style="font-size: 12px;">Add up to 3 links (Official Portfolio Website, Facebook, Instagram, LinkedIn, YouTube, Telegram)</span>
          </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 12px;">
          <?php for ($lIdx = 0; $lIdx < 3; $lIdx++): 
            $sLink = $socialLinks[$lIdx] ?? ['url' => '', 'type' => 'auto'];
            $curUrl = $sLink['url'] ?? '';
            $curType = $sLink['type'] ?? 'auto';
          ?>
            <div class="grid-2" style="gap: 12px; margin-bottom: 0; align-items: end; background: rgba(0,0,0,0.15); padding: 10px 14px; border-radius: 8px; border: 1px solid var(--border-color, rgba(255,255,255,0.05));">
              <div class="field" style="margin-bottom: 0;">
                <label style="font-size: 12px; font-weight: 700; color: var(--text-secondary);">
                  Link #<?php echo $lIdx + 1; ?> <?php echo $lIdx === 0 ? '<span style="color:var(--accent-primary, #f5b800); font-weight:600;">(Primary Showcase)</span>' : '<span style="color:var(--text-muted); font-weight:normal;">(Optional)</span>'; ?>
                </label>
                <input type="text" name="links[<?php echo $lIdx; ?>][url]" value="<?php echo htmlspecialchars($curUrl); ?>" placeholder="<?php echo $lIdx === 0 ? 'https://myfirm.com or https://facebook.com/...' : 'https://instagram.com/... or https://linkedin.com/...'; ?>">
              </div>
              <div class="field" style="margin-bottom: 0;">
                <label style="font-size: 12px; font-weight: 700; color: var(--text-secondary);">Platform / Icon Style</label>
                <select name="links[<?php echo $lIdx; ?>][type]">
                  <option value="auto" <?php echo $curType === 'auto' ? 'selected' : ''; ?>>Auto-Detect from URL</option>
                  <option value="website" <?php echo $curType === 'website' ? 'selected' : ''; ?>>Official Website / Portfolio</option>
                  <option value="facebook" <?php echo $curType === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                  <option value="instagram" <?php echo $curType === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                  <option value="linkedin" <?php echo $curType === 'linkedin' ? 'selected' : ''; ?>>LinkedIn</option>
                  <option value="youtube" <?php echo $curType === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
                  <option value="telegram" <?php echo $curType === 'telegram' ? 'selected' : ''; ?>>Telegram</option>
                </select>
              </div>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- 5. CAREER ACHIEVEMENTS & PRACTICE (UNDER SOCIAL LINKS) -->
      <div class="field" style="margin-top: 20px;">
        <label style="font-weight: 700; font-size: 13.5px; color: var(--text-primary);">Career Achievements &amp; Practice</label>
        <textarea name="achievements" rows="4" placeholder="Describe your architectural experience, milestones, design philosophy, or notable achievements..." style="font-size: 13.5px; line-height: 1.65;"><?php echo htmlspecialchars($profile['achievements'] ?? ''); ?></textarea>
      </div>

      <!-- 6. HONORS, DISTINCTIONS & AWARDS (UNDER ACHIEVEMENTS) -->
      <div class="field" style="margin-bottom: 20px;">
        <label style="font-weight: 700; font-size: 13.5px; color: var(--text-primary);">Honors, Distinctions &amp; Awards</label>
        <textarea name="awards" rows="4" placeholder="List your professional design awards, chapter recognitions, or academic distinctions..." style="font-size: 13.5px; line-height: 1.65;"><?php echo htmlspecialchars($profile['awards'] ?? ''); ?></textarea>
      </div>

      <!-- SAVE PROFILE DETAILS BUTTON (Placed under Honors, Distinctions & Awards) -->
      <div style="margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.1));">
        <button class="btn btn-success" type="submit" style="padding: 12px 28px; font-weight:700; font-size:14.5px; display:inline-flex; align-items:center; gap:8px; cursor: pointer;">
          <?php echo icon('check', '', 16); ?> <span>Save Profile Details</span>
        </button>
      </div>
    </form>

    <!-- 2. COMPLETED WORKS & PROJECTS PORTFOLIO (WITH PER-PROJECT SAVE) -->
    <div style="background: var(--bg-secondary, rgba(0,0,0,0.15)); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 14px; padding: 22px; margin: 24px 0; width: 100%; box-sizing: border-box;">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom: 16px;">
        <div>
          <h3 style="margin:0; font-size:18px; color:var(--accent-primary, #f5b800); display:inline-flex; align-items:center; gap:8px;">
            <?php echo icon('camera', '', 20); ?> <span>Completed Works &amp; Projects Portfolio</span>
          </h3>
          <p class="muted" style="font-size:12.5px; margin:4px 0 0;">Add completed architectural works with cover photo, team credits, and up to 5 photos per project. Save each project individually.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <button type="button" onclick="toggleAllProjects(true)" class="btn btn-sm btn-secondary" style="font-size:11.5px; padding: 5px 12px; border-radius: 6px; cursor: pointer;">
            Expand All
          </button>
          <button type="button" onclick="toggleAllProjects(false)" class="btn btn-sm btn-secondary" style="font-size:11.5px; padding: 5px 12px; border-radius: 6px; cursor: pointer;">
            Collapse All
          </button>
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

      <div id="projectsContainer" style="display: flex; flex-direction: column; gap: 16px; width: 100%;">
        <?php foreach ($renderProjects as $pIdx => $proj): ?>
          <?php 
            $coverPath = $proj['cover_photo'] ?? ($proj['photos'][0] ?? '');
            $otherPhotos = array_values(array_filter($proj['photos'] ?? [], function($ph) use ($coverPath) {
                return $ph !== $coverPath;
            }));
            $totalPhotosCount = (!empty($coverPath) ? 1 : 0) + count($otherPhotos);
            $pId = $proj['id'] ?? ('proj_' . $pIdx);
          ?>
          <div class="project-card-item" id="proj_card_<?php echo $pIdx; ?>" style="background: var(--field-bg, rgba(0,0,0,0.25)); border: 1px solid var(--border-color, rgba(255,255,255,0.12)); border-radius: 12px; overflow: hidden; width: 100%; box-sizing: border-box; transition: border-color 0.2s;">
            <form method="POST" enctype="multipart/form-data">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="save_project">
              <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($pId); ?>">
              <input type="hidden" name="existing_cover" value="<?php echo htmlspecialchars($coverPath); ?>">

              <!-- Accordion Header Bar (Clickable) -->
              <div class="project-card-header" onclick="toggleProjectAccordion(this)" style="display:flex; justify-content:space-between; align-items:center; padding: 12px 16px; background: rgba(255,255,255,0.03); cursor: pointer; user-select: none; border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.08));">
                <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                  <span class="accordion-chevron" style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: rgba(245,158,11,0.15); color: var(--accent-primary, #f5b800); font-size: 10px; font-weight: 900; transition: transform 0.25s ease;">▼</span>
                  
                  <?php if ($coverPath): ?>
                    <div style="width: 36px; height: 36px; border-radius: 6px; overflow: hidden; background: #000; flex-shrink: 0; border: 1px solid rgba(255,255,255,0.1);">
                      <img src="<?php echo htmlspecialchars(media_url($coverPath)); ?>" alt="Thumb" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                  <?php endif; ?>

                  <div style="min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                      <span style="font-weight: 800; font-size: 13.5px; color: var(--accent-primary, #f5b800);">Completed Work #<?php echo $pIdx + 1; ?>:</span>
                      <strong class="proj-title-preview" style="font-size: 13.5px; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px;"><?php echo htmlspecialchars(!empty($proj['title']) ? $proj['title'] : 'New Architectural Work'); ?></strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px; font-size: 11px; color: var(--text-secondary);">
                      <span class="proj-cat-preview"><?php echo htmlspecialchars(!empty($proj['category']) ? $proj['category'] : 'RESIDENTIAL'); ?></span>
                      <span>•</span>
                      <span><?php echo $totalPhotosCount; ?> / 5 Photos</span>
                    </div>
                  </div>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;" onclick="event.stopPropagation()">
                  <button type="submit" form="del_form_<?php echo $pIdx; ?>" class="btn btn-sm btn-secondary" style="color: #ef4444; border-color: rgba(239,68,68,0.3); font-size: 11px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;" onclick="return confirm('Are you sure you want to delete this project?');">
                    <?php echo icon('trash', '', 12); ?> <span>Delete Project</span>
                  </button>
                </div>
              </div>

              <!-- Collapsible Body Content -->
              <div class="project-card-body" style="padding: 18px; display: block;">
                <div class="grid-3" style="gap: 12px; margin-bottom: 12px;">
                  <div class="field" style="margin-bottom:0;">
                    <label style="font-size:12px;">Project Title / Name *</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($proj['title'] ?? ''); ?>" placeholder="e.g. Casa San Gregorio, DLSU-D Building" required oninput="updateProjTitlePreview(this)">
                  </div>
                  <div class="field" style="margin-bottom:0;">
                    <label style="font-size:12px;">Project Category</label>
                    <input type="text" name="category" value="<?php echo htmlspecialchars($proj['category'] ?? 'RESIDENTIAL'); ?>" placeholder="e.g. RESIDENTIAL, COMMERCIAL, INSTITUTIONAL" oninput="updateProjCatPreview(this)">
                  </div>
                  <div class="field" style="margin-bottom:0;">
                    <label style="font-size:12px;">Location</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($proj['location'] ?? ''); ?>" placeholder="e.g. Makati, Manila">
                  </div>
                </div>

                <div class="grid-2" style="gap: 16px; margin-bottom: 16px;">
                  <div class="field" style="margin-bottom:0;">
                    <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Architectural Concept &amp; Narrative</label>
                    <textarea name="description" rows="7" placeholder="Describe the design philosophy, space planning, materials, volumetric form, and concept..." style="font-size:13.5px; line-height:1.65; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"><?php echo htmlspecialchars($proj['description'] ?? ''); ?></textarea>
                  </div>
                  <div class="field" style="margin-bottom:0;">
                    <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Team / Collaborators</label>
                    <textarea name="project_team" rows="7" placeholder="e.g. Ar. Anthony Nazareno&#10;Ar. Vladimir Banks&#10;IDr. Marielle Saguibo&#10;Engr. Juan Dela Cruz" style="font-size:13.5px; line-height:1.6; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"><?php echo htmlspecialchars($proj['project_team'] ?? ''); ?></textarea>
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
                          <input type="checkbox" name="delete_photos[]" value="cover"> Remove Cover
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

                    <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp" style="font-size: 12px;">
                  </div>

                  <!-- Existing Additional Photos -->
                  <?php if (!empty($otherPhotos)): ?>
                    <label style="font-size: 12px; color: var(--text-secondary); display: block; margin-bottom: 6px;">Additional Gallery Photos:</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; margin-bottom: 12px;">
                      <?php foreach ($otherPhotos as $oIdx => $oPath): ?>
                        <div style="background: rgba(0,0,0,0.3); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 6px; padding: 6px; display: flex; flex-direction: column; gap: 4px;">
                          <input type="hidden" name="existing_photos[<?php echo $oIdx; ?>]" value="<?php echo htmlspecialchars($oPath); ?>">
                          <div style="width: 100%; height: 75px; border-radius: 4px; overflow: hidden; background: #000;">
                            <img src="<?php echo htmlspecialchars(media_url($oPath)); ?>" alt="Photo" style="width: 100%; height: 100%; object-fit: cover;">
                          </div>
                          <label style="font-size: 11px; color: #ef4444; display: flex; align-items: center; gap: 4px; cursor: pointer;">
                            <input type="checkbox" name="delete_photos[]" value="<?php echo $oIdx; ?>"> Delete
                          </label>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <!-- Add More Photos File Input -->
                  <?php if ($totalPhotosCount < 5): ?>
                    <div class="field" style="margin-bottom:0;">
                      <label style="font-size: 12px;">Upload Additional Photos (Up to <?php echo 5 - $totalPhotosCount; ?> more files):</label>
                      <input type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp" multiple style="font-size: 12px;">
                    </div>
                  <?php endif; ?>
                </div>

                <!-- SAVE THIS PROJECT BUTTON (Per Project) -->
                <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border-color, rgba(255,255,255,0.08)); display: flex; justify-content: flex-end; gap: 10px;">
                  <button type="submit" class="btn btn-primary btn-sm" style="padding: 9px 22px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                    <?php echo icon('check', '', 14); ?> <span>Save This Project</span>
                  </button>
                </div>
              </div>
            </form>

            <!-- Hidden Delete Form -->
            <form id="del_form_<?php echo $pIdx; ?>" method="POST" style="display:none;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="delete_project">
              <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($pId); ?>">
            </form>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top: 18px;">
        <button type="button" id="btnAddProject" class="btn btn-sm" style="background: rgba(245,158,11,0.08); border: 1px dashed var(--accent-primary, #f5b800); color: var(--accent-primary, #f5b800); font-weight: 700; padding: 10px 18px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
          <?php echo icon('plus', '', 14); ?> <span>Add Another Completed Work Project</span>
        </button>
      </div>
    </div>

    <script>
    (function() {
      var projectCount = <?php echo count($renderProjects); ?>;

      window.toggleProjectAccordion = function(header) {
        var card = header.closest('.project-card-item');
        if (!card) return;
        var body = card.querySelector('.project-card-body');
        var chevron = card.querySelector('.accordion-chevron');
        if (!body) return;

        if (body.style.display === 'none') {
          body.style.display = 'block';
          if (chevron) chevron.style.transform = 'rotate(0deg)';
        } else {
          body.style.display = 'none';
          if (chevron) chevron.style.transform = 'rotate(-90deg)';
        }
      };

      window.toggleAllProjects = function(expand) {
        var bodies = document.querySelectorAll('.project-card-item .project-card-body');
        var chevrons = document.querySelectorAll('.project-card-item .accordion-chevron');
        bodies.forEach(function(b) {
          b.style.display = expand ? 'block' : 'none';
        });
        chevrons.forEach(function(c) {
          c.style.transform = expand ? 'rotate(0deg)' : 'rotate(-90deg)';
        });
      };

      window.updateProjTitlePreview = function(input) {
        var card = input.closest('.project-card-item');
        if (!card) return;
        var preview = card.querySelector('.proj-title-preview');
        if (preview) {
          preview.textContent = input.value.trim() || 'New Architectural Work';
        }
      };

      window.updateProjCatPreview = function(input) {
        var card = input.closest('.project-card-item');
        if (!card) return;
        var preview = card.querySelector('.proj-cat-preview');
        if (preview) {
          preview.textContent = input.value.trim().toUpperCase() || 'RESIDENTIAL';
        }
      };

      window.removeUnsavedProjectCard = function(btn) {
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
          div.id = 'proj_card_' + pIdx;
          div.style.cssText = 'background: var(--field-bg, rgba(0,0,0,0.25)); border: 1px dashed var(--border-color-gold, rgba(245,158,11,0.35)); border-radius: 12px; margin-bottom: 16px; overflow: hidden; width: 100%; box-sizing: border-box;';
          
          div.innerHTML = [
            '<form method="POST" enctype="multipart/form-data">',
            '  <input type="hidden" name="_csrf_token" value="<?php echo generate_csrf(); ?>">',
            '  <input type="hidden" name="action" value="save_project">',
            '  <input type="hidden" name="project_id" value="proj_' + timestamp + '">',
            '  <div class="project-card-header" onclick="toggleProjectAccordion(this)" style="display:flex; justify-content:space-between; align-items:center; padding: 12px 16px; background: rgba(255,255,255,0.03); cursor: pointer; user-select: none; border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.08));">',
            '    <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">',
            '      <span class="accordion-chevron" style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: rgba(245,158,11,0.15); color: var(--accent-primary, #f5b800); font-size: 10px; font-weight: 900; transition: transform 0.25s ease;">▼</span>',
            '      <div style="min-width: 0;">',
            '        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">',
            '          <span style="font-weight: 800; font-size: 13.5px; color: var(--accent-primary, #f5b800);">New Completed Work #' + projectCount + ':</span>',
            '          <strong class="proj-title-preview" style="font-size: 13.5px; color: var(--text-primary);">(Unsaved New Project)</strong>',
            '        </div>',
            '        <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px; font-size: 11px; color: var(--text-secondary);">',
            '          <span class="proj-cat-preview">RESIDENTIAL</span>',
            '          <span>•</span>',
            '          <span>0 / 5 Photos</span>',
            '        </div>',
            '      </div>',
            '    </div>',
            '    <div style="display: flex; align-items: center; gap: 8px;" onclick="event.stopPropagation()">',
            '      <button type="button" onclick="removeUnsavedProjectCard(this)" class="btn btn-sm btn-secondary" style="color: #ef4444; border-color: rgba(239,68,68,0.3); font-size: 11px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;">',
            '        <span>Discard</span>',
            '      </button>',
            '    </div>',
            '  </div>',
            '  <div class="project-card-body" style="padding: 18px; display: block;">',
            '    <div class="grid-3" style="gap: 12px; margin-bottom: 12px;">',
            '      <div class="field" style="margin-bottom:0;">',
            '        <label style="font-size:12px;">Project Title / Name *</label>',
            '        <input type="text" name="title" placeholder="e.g. Casa San Gregorio, DLSU-D Building" required oninput="updateProjTitlePreview(this)">',
            '      </div>',
            '      <div class="field" style="margin-bottom:0;">',
            '        <label style="font-size:12px;">Project Category</label>',
            '        <input type="text" name="category" placeholder="e.g. RESIDENTIAL, COMMERCIAL, INSTITUTIONAL" value="RESIDENTIAL" oninput="updateProjCatPreview(this)">',
            '      </div>',
            '      <div class="field" style="margin-bottom:0;">',
            '        <label style="font-size:12px;">Location</label>',
            '        <input type="text" name="location" placeholder="e.g. Makati, Manila">',
            '      </div>',
            '    </div>',
            '    <div class="grid-2" style="gap: 16px; margin-bottom: 16px;">',
            '      <div class="field" style="margin-bottom:0;">',
            '        <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Architectural Concept &amp; Narrative</label>',
            '        <textarea name="description" rows="7" placeholder="Describe the design philosophy, space planning, materials, volumetric form, and concept..." style="font-size:13.5px; line-height:1.65; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"></textarea>',
            '      </div>',
            '      <div class="field" style="margin-bottom:0;">',
            '        <label style="font-size:13px; font-weight:700; color:var(--text-primary);">Project Team / Collaborators</label>',
            '        <textarea name="project_team" rows="7" placeholder="e.g. Ar. Anthony Nazareno&#10;Ar. Vladimir Banks&#10;IDr. Marielle Saguibo&#10;Engr. Juan Dela Cruz" style="font-size:13.5px; line-height:1.6; min-height:160px; width:100%; box-sizing:border-box; resize:vertical;"></textarea>',
            '      </div>',
            '    </div>',
            '    <div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 14px; border: 1px solid var(--border-color, rgba(255,255,255,0.06));">',
            '      <div style="margin-bottom: 12px; padding: 10px; background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.25); border-radius: 8px;">',
            '        <span style="font-size: 12px; font-weight: 700; color: var(--accent-primary, #f5b800); display: block; margin-bottom: 6px;">★ Cover Photo (Front Thumbnail on Directory Grid) *</span>',
            '        <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp" required style="font-size: 12px;">',
            '      </div>',
            '      <div class="field" style="margin-bottom:0;">',
            '        <label style="font-size: 12px;">Upload Additional Photos (Up to 4 more files, max 5 total photos):</label>',
            '        <input type="file" name="photos[]" accept=".jpg,.jpeg,.png,.webp" multiple style="font-size: 12px;">',
            '      </div>',
            '    </div>',
            '    <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border-color, rgba(255,255,255,0.08)); display: flex; justify-content: flex-end; gap: 10px;">',
            '      <button type="submit" class="btn btn-primary btn-sm" style="padding: 9px 22px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">',
            '        <span>Save This Project</span>',
            '      </button>',
            '    </div>',
            '  </div>',
            '</form>'
          ].join('');

          container.appendChild(div);
        });
      }
    })();
    </script>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
