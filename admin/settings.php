<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? null;

    // ============ LOGO UPLOAD ============
    if ($action === 'upload_logo' && isset($_FILES['logo'])) {
        if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please choose a logo image to upload.';
        } else {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png'];

            if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
                $error = 'Only valid JPG or PNG images are allowed for logo.';
            } else {
                $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $dest = __DIR__ . '/../uploads/' . $filename;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                    $path = 'uploads/' . $filename;

                    $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('logo', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                    $stmt->execute([$path]);
                    if (function_exists('cache_delete')) {
                        cache_delete('site_setting:logo');
                    }
                    $success = 'Logo updated successfully.';
                } else {
                    $error = 'Failed to upload logo image.';
                }
            }
        }
    }

    // ============ ABOUT US UPDATE ============
    if ($action === 'update_about' && !empty($_POST['about_us'] ?? '')) {
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('about_us', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$_POST['about_us']]);
        if (function_exists('cache_delete')) {
            cache_delete('site_setting:about_us');
        }
        $success = 'About Us section updated successfully.';
    }

    // ============ SPONSOR MANAGEMENT ============
    if ($action === 'add_sponsor' && isset($_FILES['sponsor_logo'])) {
        if ($_FILES['sponsor_logo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please choose a sponsor logo to upload.';
        } else {
            $ext = strtolower(pathinfo($_FILES['sponsor_logo']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['sponsor_logo']['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png'];

            if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
                $error = 'Only valid JPG or PNG images are allowed for sponsors.';
            } else {
                $filename = 'sponsor_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $dest = __DIR__ . '/../uploads/' . $filename;
                if (move_uploaded_file($_FILES['sponsor_logo']['tmp_name'], $dest)) {
                    $logo_path = 'uploads/' . $filename;

                    $stmt = $pdo->prepare("INSERT INTO sponsors (name, logo_path, description, url, display_order) VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(display_order), 0) + 1 FROM sponsors s2))");
                    $stmt->execute([$_POST['sponsor_name'] ?? '', $logo_path, $_POST['sponsor_desc'] ?? '', $_POST['sponsor_url'] ?? '']);
                    $success = 'Sponsor added successfully.';
                } else {
                    $error = 'Failed to upload sponsor logo.';
                }
            }
        }
    }

    if ($action === 'delete_sponsor' && !empty($_POST['sponsor_id'] ?? '')) {
        $stmt = $pdo->prepare("DELETE FROM sponsors WHERE id = ?");
        $stmt->execute([$_POST['sponsor_id']]);
        $success = 'Sponsor deleted successfully.';
    }

    // ============ NEWS & ANNOUNCEMENTS MANAGEMENT ============
    if ($action === 'add_news' && !empty($_POST['news_title'] ?? '')) {
        $stmt = $pdo->prepare("INSERT INTO news_announcements (title, summary, date_posted, display_order) VALUES (?, ?, ?, (SELECT COALESCE(MAX(display_order), 0) + 1 FROM news_announcements n2))");
        $stmt->execute([$_POST['news_title'], $_POST['news_summary'] ?? '', $_POST['news_date'] ?? null]);
        $success = 'News announcement added successfully.';
    }

    if ($action === 'delete_news' && !empty($_POST['news_id'] ?? '')) {
        $stmt = $pdo->prepare("DELETE FROM news_announcements WHERE id = ?");
        $stmt->execute([$_POST['news_id']]);
        $success = 'News announcement deleted successfully.';
    }
}

// Fetch current data
$logo = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'logo'")->fetch();
$about_us = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'about_us'")->fetch();
$sponsors = $pdo->query("SELECT * FROM sponsors WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();
$news = $pdo->query("SELECT * FROM news_announcements WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();

$page_title = 'Site Settings';
include __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:1000px;">
  <h1>Site Settings</h1>
  
  <?php if ($success): ?><div class="alert alert-success"><strong>✓ Success</strong><br><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><strong>⚠ Error</strong><br><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <!-- LOGO SECTION -->
  <section style="margin-bottom:40px;padding-bottom:30px;border-bottom:1px solid rgba(255,255,255,0.1);">
    <h2 style="font-size:18px;margin-bottom:8px;">🎨 Logo</h2>
    <p class="muted">Upload the UAP Mindoro Chapter logo. It will appear in the top navigation bar on every page.</p>
    
    <?php if ($logo): ?>
      <div style="margin:16px 0;">
        <img src="../<?php echo htmlspecialchars($logo['setting_value']); ?>" alt="Current Logo" style="max-width:140px;height:auto;border:1px solid rgba(255,255,255,0.2);border-radius:6px;padding:6px;">
      </div>
    <?php else: ?>
      <p class="muted">No logo uploaded yet.</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:flex-start;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="upload_logo">
      <input type="file" name="logo" accept=".jpg,.jpeg,.png" required style="flex:1;">
      <button class="btn" type="submit" style="flex-shrink:0;"><?php echo $logo ? 'Replace' : 'Upload'; ?></button>
    </form>
  </section>

  <!-- ABOUT US SECTION -->
  <section style="margin-bottom:40px;padding-bottom:30px;border-bottom:1px solid rgba(255,255,255,0.1);">
    <h2 style="font-size:18px;margin-bottom:8px;">ℹ️ About Us</h2>
    <p class="muted">Edit the About Us section text displayed on the public homepage.</p>
    
    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="update_about">
      <div class="field" style="margin-top:12px;">
        <textarea name="about_us" rows="6" required style="width:100%;padding:10px;border:1px solid rgba(255,255,255,0.2);border-radius:6px;background:rgba(0,0,0,0.2);color:#fff;font-family:monospace;font-size:13px;line-height:1.5;"><?php echo htmlspecialchars($about_us['setting_value'] ?? ''); ?></textarea>
      </div>
      <button class="btn" type="submit" style="margin-top:10px;">Save About Us</button>
    </form>
  </section>

  <!-- SPONSORS SECTION -->
  <section style="margin-bottom:40px;padding-bottom:30px;border-bottom:1px solid rgba(255,255,255,0.1);">
    <h2 style="font-size:18px;margin-bottom:8px;">🤝 Sponsors</h2>
    <p class="muted">Add sponsor logos that will appear on the public website. Arrange them in your preferred order.</p>
    
    <form method="post" enctype="multipart/form-data" style="background:rgba(0,0,0,0.2);padding:16px;border-radius:6px;margin-bottom:20px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_sponsor">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div class="field" style="margin:0;">
          <label>Sponsor Name</label>
          <input type="text" name="sponsor_name" placeholder="e.g. ABC Corporation" required>
        </div>
        <div class="field" style="margin:0;">
          <label>Sponsor Logo</label>
          <input type="file" name="sponsor_logo" accept=".jpg,.jpeg,.png" required>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div class="field" style="margin:0;">
          <label>Description (Optional)</label>
          <input type="text" name="sponsor_desc" placeholder="Brief description">
        </div>
        <div class="field" style="margin:0;">
          <label>Website URL (Optional)</label>
          <input type="url" name="sponsor_url" placeholder="https://...">
        </div>
      </div>
      <button class="btn" type="submit" style="width:100%;">Add Sponsor</button>
    </form>

    <?php if (count($sponsors) > 0): ?>
      <div style="margin-top:20px;">
        <h3 style="font-size:14px;margin-bottom:12px;color:#f5b800;">Current Sponsors</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;">
          <?php foreach ($sponsors as $sponsor): ?>
            <div style="background:rgba(0,0,0,0.2);padding:12px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);">
              <img src="../<?php echo htmlspecialchars($sponsor['logo_path']); ?>" alt="<?php echo htmlspecialchars($sponsor['name']); ?>" style="width:100%;height:100px;object-fit:contain;margin-bottom:10px;background:rgba(0,0,0,0.3);border-radius:4px;padding:6px;">
              <p style="font-weight:600;font-size:13px;margin-bottom:4px;"><?php echo htmlspecialchars($sponsor['name']); ?></p>
              <form method="post" style="margin-top:8px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_sponsor">
                <input type="hidden" name="sponsor_id" value="<?php echo $sponsor['id']; ?>">
                <button type="submit" class="btn" style="width:100%;background:#d32f2f;font-size:12px;padding:6px;">Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <p class="muted" style="text-align:center;padding:20px;">No sponsors added yet.</p>
    <?php endif; ?>
  </section>

  <!-- NEWS & ANNOUNCEMENTS SECTION -->
  <section>
    <h2 style="font-size:18px;margin-bottom:8px;">📰 Latest News & Announcements</h2>
    <p class="muted">Add news and announcements that will display on the public website in a 3-column grid layout.</p>
    
    <form method="post" style="background:rgba(0,0,0,0.2);padding:16px;border-radius:6px;margin-bottom:20px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_news">
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:12px;">
        <div class="field" style="margin:0;">
          <label>News Title</label>
          <input type="text" name="news_title" placeholder="e.g. Mindoro Architecture Week 2026" required>
        </div>
        <div class="field" style="margin:0;">
          <label>Date</label>
          <input type="date" name="news_date" required>
        </div>
      </div>
      <div class="field" style="margin:0 0 12px 0;">
        <label>Summary</label>
        <textarea name="news_summary" rows="3" placeholder="Brief summary of the news/announcement" required style="width:100%;padding:10px;border:1px solid rgba(255,255,255,0.2);border-radius:6px;background:rgba(0,0,0,0.2);color:#fff;font-family:monospace;font-size:13px;"></textarea>
      </div>
      <button class="btn" type="submit" style="width:100%;">Add News</button>
    </form>

    <?php if (count($news) > 0): ?>
      <div style="margin-top:20px;">
        <h3 style="font-size:14px;margin-bottom:12px;color:#f5b800;">Current News & Announcements</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">
          <?php foreach ($news as $item): ?>
            <div style="background:rgba(0,0,0,0.2);padding:14px;border-radius:6px;border:1px solid rgba(255,255,255,0.1);display:flex;flex-direction:column;">
              <p style="font-size:12px;color:#f5b800;font-weight:600;margin:0 0 6px 0;"><?php echo date('M d, Y', strtotime($item['date_posted'])); ?></p>
              <h4 style="font-size:14px;margin:0 0 8px 0;color:#fff;"><?php echo htmlspecialchars($item['title']); ?></h4>
              <p style="font-size:12px;color:#bfd0de;margin:0 0 12px 0;flex-grow:1;"><?php echo htmlspecialchars($item['summary']); ?></p>
              <form method="post">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_news">
                <input type="hidden" name="news_id" value="<?php echo $item['id']; ?>">
                <button type="submit" class="btn" style="width:100%;background:#d32f2f;font-size:12px;padding:6px;">Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>

      </div>
    <?php else: ?>
      <p class="muted" style="text-align:center;padding:20px;">No news/announcements added yet.</p>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
