<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['method'];
    $allowed_methods = ['gcash', 'maya', 'bank'];

    if (!in_array($method, $allowed_methods)) {
        $error = 'Invalid payment method.';
    } elseif (!isset($_FILES['qr_image']) || $_FILES['qr_image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please choose a QR code image to upload.';
    } else {
        $ext = strtolower(pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $error = 'Only JPG or PNG images are allowed.';
        } else {
            $filename = 'qr_' . $method . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/../uploads/' . $filename;
            move_uploaded_file($_FILES['qr_image']['tmp_name'], $dest);
            $path = 'uploads/' . $filename;

            $stmt = $pdo->prepare("
                INSERT INTO qr_codes (method, image_path) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE image_path = VALUES(image_path), updated_at = NOW()
            ");
            $stmt->execute([$method, $path]);

            header('Location: qr_codes.php?saved=1');
            exit;
        }
    }
}

$qrcodes = $pdo->query("SELECT * FROM qr_codes")->fetchAll();
$existing = [];
foreach ($qrcodes as $q) { $existing[$q['method']] = $q; }

$labels = ['gcash' => 'GCash', 'maya' => 'Maya', 'bank' => 'Online Banking'];

$page_title = 'Payment QR Codes';
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h1>Payment QR Codes</h1>
  <p class="muted">Upload the QR code image for each payment method. Members will see the matching QR code when they select that method to pay.</p>
  <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">QR code updated.</div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
</div>

<div class="grid-2">
  <?php foreach ($labels as $method => $label): ?>
  <div class="card">
    <h2><?php echo $label; ?></h2>
    <?php if (isset($existing[$method])): ?>
      <img src="../<?php echo htmlspecialchars($existing[$method]['image_path']); ?>" alt="<?php echo $label; ?> QR" style="max-width:180px;display:block;margin-bottom:10px;border:1px solid #e5e7eb;border-radius:6px;">
      <p class="muted">Last updated: <?php echo htmlspecialchars($existing[$method]['updated_at']); ?></p>
    <?php else: ?>
      <p class="muted">No QR code uploaded yet.</p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
      <input type="hidden" name="method" value="<?php echo $method; ?>">
      <div class="field">
        <input type="file" name="qr_image" accept=".jpg,.jpeg,.png" required>
      </div>
      <button class="btn btn-sm" type="submit"><?php echo isset($existing[$method]) ? 'Replace QR Code' : 'Upload QR Code'; ?></button>
    </form>
  </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
