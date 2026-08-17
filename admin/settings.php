<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please choose a logo image to upload.';
    } else {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $error = 'Only JPG or PNG images are allowed.';
        } else {
            $filename = 'logo_' . time() . '.' . $ext;
            $dest = __DIR__ . '/../uploads/' . $filename;
            move_uploaded_file($_FILES['logo']['tmp_name'], $dest);
            $path = 'uploads/' . $filename;

            $stmt = $pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value) VALUES ('logo', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$path]);

            header('Location: settings.php?saved=1');
            exit;
        }
    }
}

$stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'logo'");
$stmt->execute();
$logo = $stmt->fetch();

$page_title = 'Site Settings';
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h1>Site Settings</h1>
  <h2>Logo</h2>
  <p class="muted">Upload the UAP Mindoro Chapter logo. It will appear in the top navigation bar on every page.</p>
  <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Logo updated.</div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <?php if ($logo): ?>
    <img src="../<?php echo htmlspecialchars($logo['setting_value']); ?>" alt="Current Logo" style="max-width:160px;display:block;margin-bottom:14px;border:1px solid #e5e7eb;border-radius:8px;padding:6px;">
  <?php else: ?>
    <p class="muted">No logo uploaded yet.</p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <div class="field">
      <input type="file" name="logo" accept=".jpg,.jpeg,.png" required>
    </div>
    <button class="btn" type="submit"><?php echo $logo ? 'Replace Logo' : 'Upload Logo'; ?></button>
  </form>
  <h2>Admin Password</h2>
  <p class="muted">Change your admin account password here.</p>
  <a class="btn" href="change_password.php">Change Password</a>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
