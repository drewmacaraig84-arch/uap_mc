<?php
require_once __DIR__ . '/../includes/auth.php';
require_member();

if (!is_good_member($pdo, current_user_id())) {
    header('Location: dashboard.php');
    exit;
}

$record = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ? LIMIT 1");
$record->execute([current_user_id()]);
$profile = $record->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_profile') {
    require_csrf();
    if (!is_good_member($pdo, current_user_id())) {
        header('Location: dashboard.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $role = trim($_POST['role_title'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $achievements = trim($_POST['achievements'] ?? '');
    $awards = trim($_POST['awards'] ?? '');
    $photoDescription = trim($_POST['photo_description'] ?? '');

    $photoPath = $profile['photo_path'] ?? null;

    // Handle Photo Upload
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowedExts)) {
            $error = 'Invalid image format. Please upload JPG, PNG, or WEBP.';
        } elseif ($_FILES['photo']['size'] > 10 * 1024 * 1024) {
            $error = 'Image file size must not exceed 10MB.';
        } else {
            $uploadDir = __DIR__ . '/../uploads/members/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = 'member_' . current_user_id() . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                $photoPath = 'uploads/members/' . $filename;
            } else {
                $error = 'Failed to upload image. Please try again.';
            }
        }
    }

    if (!$error) {
        $stmt = $pdo->prepare("INSERT INTO website_members 
            (user_id, name, id_number, role_title, specialty, location, achievements, awards, photo_path, photo_description, is_published)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
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
                is_published = 1");
        $stmt->execute([
            current_user_id(),
            $name,
            $idNumber,
            $role,
            $specialty,
            $location,
            $achievements,
            $awards,
            $photoPath,
            $photoDescription
        ]);

        $success = 'Profile updated successfully.';

        $record = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ? LIMIT 1");
        $record->execute([current_user_id()]);
        $profile = $record->fetch();
    }
}

$page_title = 'Website Directory Profile';
include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width: 900px; margin: 0 auto;">
  <h1>Chapter Members Directory For Website</h1>
  <p class="muted">This profile appears on the public website directory while you are in good standing.</p>

  <?php if ($error): ?>
    <div class="alert alert-error" style="margin-top: 14px;"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success" style="margin-top: 14px;"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" style="margin-top: 20px;">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save_profile">

    <div class="grid-2">
      <div class="field">
        <label>Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required>
      </div>
      <div class="field">
        <label>PRC ID No.</label>
        <input type="text" name="id_number" value="<?php echo htmlspecialchars($profile['id_number'] ?? ''); ?>" required>
      </div>
    </div>

    <div class="grid-2">
      <div class="field">
        <label>Role / Title</label>
        <input type="text" name="role_title" value="<?php echo htmlspecialchars($profile['role_title'] ?? ''); ?>" required>
      </div>
      <div class="field">
        <label>Architectural Specialty</label>
        <input type="text" name="specialty" value="<?php echo htmlspecialchars($profile['specialty'] ?? ''); ?>" required>
      </div>
    </div>

    <div class="grid-2">
      <div class="field">
        <label>Location</label>
        <input type="text" name="location" value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>" required>
      </div>
      <div class="field">
        <label>QR Image Placeholder</label>
        <div style="padding: 12px; border: 1px dashed var(--border-color, rgba(255,255,255,0.2)); border-radius: 8px; background: var(--field-bg, rgba(0,0,0,0.1)); min-height: 48px; display:flex; align-items:center; justify-content:center; color:var(--text-secondary);">
          QR image will be added here
        </div>
      </div>
    </div>

    <!-- FEATURED PHOTO & DESCRIPTION SECTION -->
    <div style="background: var(--bg-secondary, rgba(0,0,0,0.15)); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 10px; padding: 18px; margin: 18px 0;">
      <h3 style="margin-top:0; font-size:16px; color:var(--accent-primary, #f5b800);">📸 Featured Project / Work Photo</h3>
      <p class="muted" style="font-size:13px; margin-bottom: 14px;">Upload a showcase photo of your architectural project or practice to be featured on your public directory profile.</p>
      
      <?php if (!empty($profile['photo_path'])): ?>
        <div style="margin-bottom: 14px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
          <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($profile['photo_path']); ?>" alt="Current Featured Photo" style="max-width:180px; max-height:120px; object-fit:cover; border-radius:8px; border:1px solid var(--border-color);">
          <div>
            <span style="font-size:13px; font-weight:600; color:var(--text-primary); display:block;">Current Featured Photo</span>
            <span class="muted" style="font-size:12px;">Choose a new file below to replace it.</span>
          </div>
        </div>
      <?php endif; ?>

      <div class="field">
        <label>Upload Photo (JPG, PNG, WEBP)</label>
        <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
      </div>

      <div class="field" style="margin-bottom:0;">
        <label>Photo Description / Caption</label>
        <textarea name="photo_description" rows="3" placeholder="Describe this project (e.g., project name, location, architectural concept, role in the project)..."><?php echo htmlspecialchars($profile['photo_description'] ?? ''); ?></textarea>
      </div>
    </div>

    <div class="field">
      <label>Achievements</label>
      <textarea name="achievements" rows="4" placeholder="Describe your achievements..."><?php echo htmlspecialchars($profile['achievements'] ?? ''); ?></textarea>
    </div>

    <div class="field">
      <label>Awards</label>
      <textarea name="awards" rows="4" placeholder="List your awards or recognitions..."><?php echo htmlspecialchars($profile['awards'] ?? ''); ?></textarea>
    </div>

    <button class="btn btn-success" type="submit" style="padding: 10px 24px; font-weight:700;">Save Website Profile</button>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
