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
                    $uniqueName = 'member_' . current_user_id() . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
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
        current_user_id(),
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

    $success = 'Profile & project photos updated successfully.';

    $record = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ? LIMIT 1");
    $record->execute([current_user_id()]);
    $profile = $record->fetch();
}

// Load Gallery array
$gallery = [];
if (!empty($profile['gallery_json'])) {
    $decoded = json_decode($profile['gallery_json'], true);
    if (is_array($decoded)) {
        $gallery = $decoded;
    }
} elseif (!empty($profile['photo_path'])) {
    // Fallback from legacy single photo
    $gallery[] = [
        'path' => $profile['photo_path'],
        'description' => $profile['photo_description'] ?? ''
    ];
}

$page_title = 'Website Directory Profile';
include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width: 960px; margin: 0 auto;">
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

    <!-- MULTIPLE PROJECT PHOTOS & DESCRIPTIONS GALLERY -->
    <div style="background: var(--bg-secondary, rgba(0,0,0,0.15)); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 12px; padding: 22px; margin: 22px 0;">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom: 12px;">
        <h3 style="margin:0; font-size:17px; color:var(--accent-primary, #f5b800);">📸 Project Portfolio Photos & Captions</h3>
        <span class="muted" style="font-size:12px;">Showcase multiple architectural works & project descriptions</span>
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
      <label>Achievements</label>
      <textarea name="achievements" rows="4" placeholder="Describe your achievements..."><?php echo htmlspecialchars($profile['achievements'] ?? ''); ?></textarea>
    </div>

    <div class="field">
      <label>Awards</label>
      <textarea name="awards" rows="4" placeholder="List your awards or recognitions..."><?php echo htmlspecialchars($profile['awards'] ?? ''); ?></textarea>
    </div>

    <button class="btn btn-success" type="submit" style="padding: 12px 28px; font-weight:700; font-size:15px;">Save Website Profile</button>
  </form>
</div>

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

<?php include __DIR__ . '/../includes/footer.php'; ?>
