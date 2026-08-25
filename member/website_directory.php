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

    $stmt = $pdo->prepare("INSERT INTO website_members (user_id, name, id_number, role_title, specialty, location, achievements, awards, is_published)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            id_number = VALUES(id_number),
            role_title = VALUES(role_title),
            specialty = VALUES(specialty),
            location = VALUES(location),
            achievements = VALUES(achievements),
            awards = VALUES(awards),
            is_published = 1");
    $stmt->execute([current_user_id(), $name, $idNumber, $role, $specialty, $location, $achievements, $awards]);

    if (function_exists('set_flash')) {
        set_flash('success', 'Profile updated successfully.');
    }

    $record = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ? LIMIT 1");
    $record->execute([current_user_id()]);
    $profile = $record->fetch();
}

$page_title = 'Website Directory Profile';
include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width: 900px; margin: 0 auto;">
  <h1>Chapter Members Directory For Website</h1>
  <p class="muted">This profile appears on the public website when you are recognized as a good member.</p>

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
        <div style="padding: 12px; border: 1px dashed rgba(255,255,255,0.2); border-radius: 8px; background: rgba(0,0,0,0.1); min-height: 70px; display:flex; align-items:center; justify-content:center; color:#bfd0de;">
          QR image will be added here
        </div>
      </div>
    </div>

    <div class="field">
      <label>Achievements</label>
      <textarea name="achievements" rows="5" placeholder="Describe your achievements..."><?php echo htmlspecialchars($profile['achievements'] ?? ''); ?></textarea>
    </div>

    <div class="field">
      <label>Awards</label>
      <textarea name="awards" rows="5" placeholder="List your awards or recognitions..."><?php echo htmlspecialchars($profile['awards'] ?? ''); ?></textarea>
    </div>

    <button class="btn" type="submit">Save Website Profile</button>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
