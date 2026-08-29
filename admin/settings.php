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
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

            if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
                $error = 'Only valid JPG, PNG, or WebP images are allowed for logo.';
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
                    $success = 'Organization logo updated successfully.';
                } else {
                    $error = 'Failed to upload logo image.';
                }
            }
        }
    }

    // ============ CHAPTER INFO UPDATE ============
    if ($action === 'update_info') {
        $org_name = trim($_POST['org_name'] ?? '');
        if ($org_name !== '') {
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('org_name', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$org_name]);
            $success = 'Organization information updated successfully.';
        }
    }

    // ============ ABOUT US & CONTACT UPDATE ============
    if ($action === 'update_about') {
        $keys = [
            'about_us'               => trim($_POST['about_us'] ?? ''),
            'contact_address'        => trim($_POST['contact_address'] ?? ''),
            'contact_email'          => trim($_POST['contact_email'] ?? ''),
            'contact_phone'          => trim($_POST['contact_phone'] ?? ''),
            'office_hours_weekdays'  => trim($_POST['office_hours_weekdays'] ?? ''),
            'office_hours_saturday'  => trim($_POST['office_hours_saturday'] ?? ''),
            'office_hours_sunday'    => trim($_POST['office_hours_sunday'] ?? ''),
        ];

        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($keys as $k => $v) {
            $stmt->execute([$k, $v]);
            if (function_exists('cache_delete')) {
                cache_delete('site_setting:' . $k);
            }
        }
        $success = 'Website About Us and Secretariat Contact information updated successfully.';
    }

    // ============ SPONSOR MANAGEMENT ============
    if ($action === 'add_sponsor') {
        $sponsor_name = trim($_POST['sponsor_name'] ?? '');
        $is_platinum = !empty($_POST['is_platinum']) ? 1 : 0;
        if (empty($sponsor_name)) {
            $error = 'Sponsor name is required.';
        } else {
            $logo_path = trim($_POST['sponsor_logo_url'] ?? ''); // external URL fallback

            // If a file was uploaded, prefer that
            if (!empty($_FILES['sponsor_logo']['name']) && $_FILES['sponsor_logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['sponsor_logo']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['sponsor_logo']['tmp_name']);
                finfo_close($finfo);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

                if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
                    $error = 'Only valid JPG, PNG, or WebP images are allowed for sponsors.';
                } else {
                    $filename = 'sponsor_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $dest = __DIR__ . '/../uploads/' . $filename;
                    if (move_uploaded_file($_FILES['sponsor_logo']['tmp_name'], $dest)) {
                        $logo_path = 'uploads/' . $filename;
                    } else {
                        $error = 'Failed to upload sponsor logo.';
                    }
                }
            }

            // Process Promotional Products (for Platinum partners)
            $products = [];
            if ($is_platinum) {
                $uploadDir = __DIR__ . '/../uploads/sponsors/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0775, true);
                }
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
                $rawProducts = $_POST['products'] ?? [];
                if (is_array($rawProducts)) {
                    foreach ($rawProducts as $k => $p) {
                        if (!empty($p['delete'])) continue;
                        $pName = trim($p['name'] ?? '');
                        $pDesc = trim($p['description'] ?? '');
                        $pLink = trim($p['link_url'] ?? '');
                        $pId = !empty($p['id']) ? trim($p['id']) : ('prod_' . time() . '_' . bin2hex(random_bytes(3)));
                        $imgPath = '';

                        if (isset($_FILES['products']['name'][$k]['image']) && $_FILES['products']['error'][$k]['image'] === UPLOAD_ERR_OK) {
                            $fName = $_FILES['products']['name'][$k]['image'];
                            $fExt = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
                            if (in_array($fExt, $allowedExts) && $_FILES['products']['size'][$k]['image'] <= 12 * 1024 * 1024) {
                                $pFileName = 'prod_' . time() . '_' . $k . '_' . bin2hex(random_bytes(3)) . '.' . $fExt;
                                $pDest = $uploadDir . $pFileName;
                                if (move_uploaded_file($_FILES['products']['tmp_name'][$k]['image'], $pDest)) {
                                    $imgPath = 'uploads/sponsors/' . $pFileName;
                                }
                            }
                        } elseif (!empty($p['image_url'])) {
                            $imgPath = trim($p['image_url']);
                        }

                        if (!empty($pName) || !empty($imgPath) || !empty($pDesc)) {
                            $products[] = [
                                'id' => $pId,
                                'name' => $pName !== '' ? $pName : 'Featured Promotional Product',
                                'description' => $pDesc,
                                'link_url' => $pLink,
                                'image_path' => $imgPath
                            ];
                        }
                    }
                }
            }
            $products_json = !empty($products) ? json_encode($products) : null;

            if (empty($error)) {
                $stmt = $pdo->prepare("INSERT INTO sponsors (name, logo_path, description, url, is_platinum, products_json, display_order) VALUES (?, ?, ?, ?, ?, ?, (SELECT COALESCE(MAX(display_order), 0) + 1 FROM sponsors s2))");
                $stmt->execute([$sponsor_name, $logo_path ?: null, $_POST['sponsor_desc'] ?? '', $_POST['sponsor_url'] ?? '', $is_platinum, $products_json]);
                $success = 'Sponsor partner added successfully.';
            }
        }
    }

    if ($action === 'edit_sponsor' && !empty($_POST['sponsor_id'] ?? '') && !empty($_POST['sponsor_name'] ?? '')) {
        $sponsor_id = (int)$_POST['sponsor_id'];
        $sponsor_name = trim($_POST['sponsor_name']);
        $sponsor_desc = trim($_POST['sponsor_desc'] ?? '');
        $sponsor_url = trim($_POST['sponsor_url'] ?? '');
        $is_platinum = !empty($_POST['is_platinum']) ? 1 : 0;

        $logo_path = null;
        if (!empty($_FILES['sponsor_logo']['name']) && $_FILES['sponsor_logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['sponsor_logo']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['sponsor_logo']['tmp_name']);
            finfo_close($finfo);

            if (in_array($ext, $allowedExtensions) && in_array($mime, $allowedMimes)) {
                $filename = 'sponsor_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $dest = __DIR__ . '/../uploads/' . $filename;
                if (move_uploaded_file($_FILES['sponsor_logo']['tmp_name'], $dest)) {
                    $logo_path = 'uploads/' . $filename;
                }
            }
        }

        // Process Promotional Products
        $products = [];
        if ($is_platinum) {
            $uploadDir = __DIR__ . '/../uploads/sponsors/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $rawProducts = $_POST['products'] ?? [];
            if (is_array($rawProducts)) {
                foreach ($rawProducts as $k => $p) {
                    if (!empty($p['delete'])) continue;
                    $pName = trim($p['name'] ?? '');
                    $pDesc = trim($p['description'] ?? '');
                    $pLink = trim($p['link_url'] ?? '');
                    $pId = !empty($p['id']) ? trim($p['id']) : ('prod_' . time() . '_' . bin2hex(random_bytes(3)));
                    $existingImg = trim($p['existing_image'] ?? '');
                    $imgPath = $existingImg;

                    if (isset($_FILES['products']['name'][$k]['image']) && $_FILES['products']['error'][$k]['image'] === UPLOAD_ERR_OK) {
                        $fName = $_FILES['products']['name'][$k]['image'];
                        $fExt = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
                        if (in_array($fExt, $allowedExts) && $_FILES['products']['size'][$k]['image'] <= 12 * 1024 * 1024) {
                            $pFileName = 'prod_' . time() . '_' . $k . '_' . bin2hex(random_bytes(3)) . '.' . $fExt;
                            $pDest = $uploadDir . $pFileName;
                            if (move_uploaded_file($_FILES['products']['tmp_name'][$k]['image'], $pDest)) {
                                $imgPath = 'uploads/sponsors/' . $pFileName;
                            }
                        }
                    } elseif (!empty($p['image_url'])) {
                        $imgPath = trim($p['image_url']);
                    }

                    if (!empty($pName) || !empty($imgPath) || !empty($pDesc)) {
                        $products[] = [
                            'id' => $pId,
                            'name' => $pName !== '' ? $pName : 'Featured Promotional Product',
                            'description' => $pDesc,
                            'link_url' => $pLink,
                            'image_path' => $imgPath
                        ];
                    }
                }
            }
        }
        $products_json = !empty($products) ? json_encode($products) : null;

        if ($logo_path) {
            $stmt = $pdo->prepare("UPDATE sponsors SET name = ?, description = ?, url = ?, is_platinum = ?, products_json = ?, logo_path = ? WHERE id = ?");
            $stmt->execute([$sponsor_name, $sponsor_desc, $sponsor_url, $is_platinum, $products_json, $logo_path, $sponsor_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE sponsors SET name = ?, description = ?, url = ?, is_platinum = ?, products_json = ? WHERE id = ?");
            $stmt->execute([$sponsor_name, $sponsor_desc, $sponsor_url, $is_platinum, $products_json, $sponsor_id]);
        }
        $success = 'Sponsor partner updated successfully.';
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

    if ($action === 'edit_news' && !empty($_POST['news_id'] ?? '') && !empty($_POST['news_title'] ?? '')) {
        $news_id = (int)$_POST['news_id'];
        $title = trim($_POST['news_title']);
        $summary = trim($_POST['news_summary'] ?? '');
        $date = !empty($_POST['news_date']) ? $_POST['news_date'] : date('Y-m-d');
        $stmt = $pdo->prepare("UPDATE news_announcements SET title = ?, summary = ?, date_posted = ? WHERE id = ?");
        $stmt->execute([$title, $summary, $date, $news_id]);
        $success = 'News announcement updated successfully.';
    }

    if ($action === 'delete_news' && !empty($_POST['news_id'] ?? '')) {
        $stmt = $pdo->prepare("DELETE FROM news_announcements WHERE id = ?");
        $stmt->execute([$_POST['news_id']]);
        $success = 'News announcement deleted successfully.';
    }
}

// Fetch current data
$settings_rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$logo_val = $settings_rows['logo'] ?? null;
$about_us_val = $settings_rows['about_us'] ?? '';
$org_name_val = $settings_rows['org_name'] ?? 'United Architects of the Philippines - Mindoro Chapter';
$contact_address_val = $settings_rows['contact_address'] ?? 'Calapan City, Oriental Mindoro, Philippines 5200';
$contact_email_val = $settings_rows['contact_email'] ?? 'uapmindoro@gmail.com';
$contact_phone_val = $settings_rows['contact_phone'] ?? '+63 (0) XXXX XXXX';
$office_hours_weekdays_val = $settings_rows['office_hours_weekdays'] ?? '9:00 AM – 5:00 PM';
$office_hours_saturday_val = $settings_rows['office_hours_saturday'] ?? '9:00 AM – 12:00 PM';
$office_hours_sunday_val = $settings_rows['office_hours_sunday'] ?? 'Closed';

$sponsors = $pdo->query("SELECT * FROM sponsors WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();
$news = $pdo->query("SELECT * FROM news_announcements WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();

$page_title = 'System & Website Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">CONFIGURATION &amp; CONTENT MANAGEMENT</p>
    <h1>Settings &amp; Preferences</h1>
    <p class="page-subtitle">Configure organization branding, chapter details, website content, sponsors, and news announcements.</p>
  </div>
  <div style="display: flex; gap: 10px; align-items: center;">
    <a href="<?php echo BASE_URL; ?>/admin/change_password.php" class="btn btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
      <?php echo icon('key', '', 14); ?> <span>Change Password</span>
    </a>
  </div>
</div>

<?php if ($success): ?>
  <div class="alert alert-success">
    <div style="display: flex; align-items: center; gap: 8px;">
      <?php echo icon('check', '', 18); ?>
      <span><strong>Success:</strong> <?php echo htmlspecialchars($success); ?></span>
    </div>
  </div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="alert alert-error">
    <div style="display: flex; align-items: center; gap: 8px;">
      <?php echo icon('alert', '', 18); ?>
      <span><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></span>
    </div>
  </div>
<?php endif; ?>

<!-- NAVIGATION TABS -->
<div style="display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
  <button type="button" class="tab-btn active" onclick="switchSettingsTab('brandingTab', this)">
    <?php echo icon('image', '', 16); ?> <span>Branding &amp; Organization</span>
  </button>
  <button type="button" class="tab-btn" onclick="switchSettingsTab('websiteTab', this)">
    <?php echo icon('website_directory', '', 16); ?> <span>Website About Us</span>
  </button>
  <button type="button" class="tab-btn" onclick="switchSettingsTab('sponsorsTab', this)">
    <?php echo icon('handshake', '', 16); ?> <span>Sponsors &amp; Partners (<?php echo count($sponsors); ?>)</span>
  </button>
  <button type="button" class="tab-btn" onclick="switchSettingsTab('newsTab', this)">
    <?php echo icon('newspaper', '', 16); ?> <span>News &amp; Updates (<?php echo count($news); ?>)</span>
  </button>
</div>

<style>
.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  background: transparent;
  border: 1px solid transparent;
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}
.tab-btn:hover {
  background: var(--bg-secondary);
  color: var(--text-primary);
}
.tab-btn.active {
  background: var(--button-primary, #f2b835);
  color: var(--button-primary-text, #1f2937);
  border-color: transparent;
  box-shadow: 0 4px 12px var(--button-shadow);
}
.settings-panel {
  display: none;
}
.settings-panel.active {
  display: block;
}
</style>

<script>
function switchSettingsTab(tabId, btn) {
  document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  const target = document.getElementById(tabId);
  if (target) target.classList.add('active');
  if (btn) btn.classList.add('active');
}
</script>

<!-- TAB 1: BRANDING & ORGANIZATION -->
<div id="brandingTab" class="settings-panel active">
  <div class="grid-2" style="gap: 24px;">
    <!-- LOGO SECTION -->
    <div class="card" style="margin: 0;">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(242,184,53,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
          <?php echo icon('image', '', 18); ?>
        </div>
        <h2 style="font-size: 16px; margin: 0;">Chapter Logo &amp; Seal</h2>
      </div>
      <p class="muted" style="margin-bottom: 16px; font-size: 13px;">Upload the official UAP Mindoro Chapter seal. Used on navigation headers, login portals, and receipts.</p>
      
      <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 20px; padding: 14px; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border-color);">
        <div style="width: 80px; height: 80px; border-radius: 4px; background: #fff; padding: 1px; border: 1px solid rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.1); flex-shrink: 0;">
          <?php
            $logo_src = $logo_val ? (BASE_URL . '/' . htmlspecialchars($logo_val)) : (BASE_URL . '/public/logo.jpg');
          ?>
          <img src="<?php echo $logo_src; ?>" alt="Current Logo" onerror="if(this.src.indexOf('public/logo.jpg')===-1)this.src='<?php echo BASE_URL; ?>/public/logo.jpg';" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 3px;">
        </div>
        <div>
          <strong style="display: block; font-size: 14px; margin-bottom: 4px;">Official Seal</strong>
          <span class="muted" style="font-size: 12px; display: block; margin-bottom: 8px;">Recommended: Transparent PNG or crisp JPG (min 200x200px)</span>
          <span class="badge-pill" style="background: rgba(16,185,129,0.15); color: #10b981; font-size: 11px; padding: 2px 8px;">Active</span>
        </div>
      </div>

      <form method="post" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="upload_logo">
        <div class="field" style="margin-bottom: 12px;">
          <label>Select New Logo File</label>
          <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" required style="width: 100%;">
        </div>
        <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('upload', '', 14); ?> <span><?php echo $logo_val ? 'Replace Chapter Logo' : 'Upload Logo'; ?></span>
        </button>
      </form>
    </div>

    <!-- ORGANIZATION INFO -->
    <div class="card" style="margin: 0;">
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59,130,246,0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center;">
          <?php echo icon('building', '', 18); ?>
        </div>
        <h2 style="font-size: 16px; margin: 0;">Chapter Title &amp; Information</h2>
      </div>
      <p class="muted" style="margin-bottom: 16px; font-size: 13px;">Official chapter legal/organizational title printed across official receipts and system headers.</p>

      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update_info">
        <div class="field" style="margin-bottom: 16px;">
          <label>Organization Name</label>
          <input type="text" name="org_name" value="<?php echo htmlspecialchars($org_name_val); ?>" required placeholder="e.g. United Architects of the Philippines - Mindoro Chapter">
        </div>
        <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
          <?php echo icon('check', '', 14); ?> <span>Save Organization Title</span>
        </button>
      </form>

      <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--border-color);">
        <strong style="display: block; font-size: 13px; margin-bottom: 8px;">Administration Quick Links</strong>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
          <a href="<?php echo BASE_URL; ?>/admin/qr_codes.php" class="btn btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 12px;">
            <?php echo icon('qr_codes', '', 14); ?> <span>QR Codes</span>
          </a>
          <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="btn btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 12px;">
            <?php echo icon('reports', '', 14); ?> <span>Reports</span>
          </a>
          <a href="<?php echo BASE_URL; ?>/admin/account_manager.php" class="btn btn-sm" style="background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 12px;">
            <?php echo icon('account_manager', '', 14); ?> <span>Admin Accounts</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- TAB 2: WEBSITE ABOUT US & CONTACT INFO -->
<div id="websiteTab" class="settings-panel">
  <div class="card" style="max-width: 900px; margin: 0;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(16,185,129,0.12); color: #10b981; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('info', '', 18); ?>
      </div>
      <div>
        <h2 style="font-size: 16px; margin: 0;">Website About Us &amp; Secretariat Information</h2>
        <p class="muted" style="margin: 0; font-size: 13px;">Manage the mission statement, secretariat contact details, and office hours for the public website.</p>
      </div>
    </div>

    <form method="post" style="margin-top: 20px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="update_about">

      <!-- SECTION 1: ABOUT US & MISSION -->
      <div style="padding: 16px; background: var(--bg-secondary); border-radius: 10px; border: 1px solid var(--border-color); margin-bottom: 20px;">
        <h3 style="font-size: 14px; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; color: var(--accent-primary);">
          <?php echo icon('document', '', 16); ?> <span>Chapter Overview &amp; Mission Statement</span>
        </h3>
        <div class="field" style="margin-bottom: 0;">
          <label style="font-size: 12px;">About Us Paragraph (Displayed on Home &amp; About Pages)</label>
          <textarea name="about_us" rows="5" required style="width: 100%; padding: 12px; font-family: inherit; font-size: 13px; line-height: 1.6;"><?php echo htmlspecialchars($about_us_val); ?></textarea>
        </div>
      </div>

      <!-- SECTION 2: SECRETARIAT CONTACT -->
      <div style="padding: 16px; background: var(--bg-secondary); border-radius: 10px; border: 1px solid var(--border-color); margin-bottom: 20px;">
        <h3 style="font-size: 14px; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; color: var(--accent-primary);">
          <?php echo icon('mail', '', 16); ?> <span>Secretariat Contact Information</span>
        </h3>
        <div class="field" style="margin-bottom: 14px;">
          <label style="font-size: 12px;">Secretariat Physical Address / Location</label>
          <input type="text" name="contact_address" value="<?php echo htmlspecialchars($contact_address_val); ?>" placeholder="e.g. Calapan City, Oriental Mindoro, Philippines 5200" required>
        </div>
        <div class="grid-2" style="gap: 14px;">
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Official Secretariat Email Address</label>
            <input type="email" name="contact_email" value="<?php echo htmlspecialchars($contact_email_val); ?>" placeholder="e.g. uapmindoro@gmail.com" required>
          </div>
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Contact Number / Hotline</label>
            <input type="text" name="contact_phone" value="<?php echo htmlspecialchars($contact_phone_val); ?>" placeholder="e.g. +63 (0) XXXX XXXX" required>
          </div>
        </div>
      </div>

      <!-- SECTION 3: OFFICE HOURS -->
      <div style="padding: 16px; background: var(--bg-secondary); border-radius: 10px; border: 1px solid var(--border-color); margin-bottom: 20px;">
        <h3 style="font-size: 14px; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; color: var(--accent-primary);">
          <?php echo icon('calendar', '', 16); ?> <span>Office Hours &amp; Secretariat Availability</span>
        </h3>
        <div class="grid-3" style="gap: 14px;">
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Monday – Friday</label>
            <input type="text" name="office_hours_weekdays" value="<?php echo htmlspecialchars($office_hours_weekdays_val); ?>" placeholder="e.g. 9:00 AM – 5:00 PM" required>
          </div>
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Saturday</label>
            <input type="text" name="office_hours_saturday" value="<?php echo htmlspecialchars($office_hours_saturday_val); ?>" placeholder="e.g. 9:00 AM – 12:00 PM" required>
          </div>
          <div class="field" style="margin: 0;">
            <label style="font-size: 12px;">Sunday &amp; Holidays</label>
            <input type="text" name="office_hours_sunday" value="<?php echo htmlspecialchars($office_hours_sunday_val); ?>" placeholder="e.g. Closed" required>
          </div>
        </div>
      </div>

      <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('check', '', 14); ?> <span>Save About Us &amp; Contact Settings</span>
      </button>
    </form>
  </div>
</div>

<!-- TAB 3: SPONSORS & PARTNERS -->
<div id="sponsorsTab" class="settings-panel">
  <div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(139,92,246,0.12); color: #8b5cf6; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('handshake', '', 18); ?>
      </div>
      <h2 style="font-size: 16px; margin: 0;">Add Chapter Sponsor / Partner</h2>
    </div>
    <p class="muted" style="margin-bottom: 16px; font-size: 13px;">Add sponsor logos, links, and promotional products for showcase on the chapter website and directory.</p>

    <form method="post" enctype="multipart/form-data" style="background: var(--bg-secondary); padding: 18px; border-radius: 12px; border: 1px solid var(--border-color);">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_sponsor">
      <div class="grid-2" style="gap: 14px; margin-bottom: 14px;">
        <div class="field" style="margin: 0;">
          <label>Sponsor / Partner Name <span style="color:var(--c-gold)">*</span></label>
          <input type="text" name="sponsor_name" placeholder="e.g. Boysen Paints Philippines, Davies Paint" required>
        </div>
        <div class="field" style="margin: 0;">
          <label>Logo Image <span class="muted" style="font-weight:400;">(upload or paste URL below)</span></label>
          <input type="file" name="sponsor_logo" accept=".jpg,.jpeg,.png,.webp">
        </div>
      </div>
      <div class="field" style="margin-bottom: 14px;">
        <label>Logo URL <span class="muted" style="font-weight:400;">(used if no file uploaded, e.g. https://example.com/logo.png)</span></label>
        <input type="url" name="sponsor_logo_url" placeholder="https://example.com/logo.png">
      </div>
      <div class="grid-2" style="gap: 14px; margin-bottom: 16px;">
        <div class="field" style="margin: 0;">
          <label>Description / Partnership Bio (Optional)</label>
          <input type="text" name="sponsor_desc" placeholder="e.g. Official Platinum Chapter Sponsor">
        </div>
        <div class="field" style="margin: 0;">
          <label>Website URL (Optional)</label>
          <input type="url" name="sponsor_url" placeholder="https://example.com">
        </div>
      </div>

      <!-- PLATINUM TIER TOGGLE -->
      <div style="background: rgba(245,158,11,0.06); border: 1px solid rgba(245,158,11,0.25); border-radius: 10px; padding: 14px; margin-bottom: 16px;">
        <label style="display: flex; align-items: center; gap: 10px; font-weight: 700; color: var(--text-primary); cursor: pointer; margin-bottom: 0;">
          <input type="checkbox" name="is_platinum" id="add_is_platinum" value="1" onchange="toggleAddPlatinumProducts(this.checked)" style="width: 18px; height: 18px; accent-color: var(--accent-primary, #f5b800);">
          <span style="display: inline-flex; align-items: center; gap: 6px;">
            <span style="color: var(--accent-primary, #f5b800); font-size: 15px;">★</span>
            <span>Designate as <strong>Platinum Sponsor / Partner</strong> (Unlocks Promotional Products Showcase &amp; Platinum Badge)</span>
          </span>
        </label>
        
        <!-- PROMOTIONAL PRODUCTS SLOTS -->
        <div id="addSponsorProductsSection" style="display: none; margin-top: 14px; padding-top: 14px; border-top: 1px dashed rgba(245,158,11,0.2);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <div>
              <strong style="font-size: 13px; color: var(--accent-primary, #f5b800);">Promotional Products &amp; Material Catalogs</strong>
              <p class="muted" style="font-size: 11.5px; margin: 2px 0 0;">Add featured products with images, specifications, and links for this Platinum sponsor.</p>
            </div>
            <button type="button" onclick="addProductSlot('addSponsorProductsList')" class="btn btn-sm" style="background: rgba(245,158,11,0.15); color: var(--accent-primary, #f5b800); border: 1px solid rgba(245,158,11,0.3); font-size: 11px; padding: 4px 10px;">
              + Add Product
            </button>
          </div>
          <div id="addSponsorProductsList" style="display: flex; flex-direction: column; gap: 12px;"></div>
        </div>
      </div>

      <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('plus', '', 14); ?> <span>Add Sponsor Partner</span>
      </button>
    </form>
  </div>

  <div class="card">
    <?php 
      $platinumCount = 0;
      $standardCount = 0;
      foreach ($sponsors as $s) {
        if (!empty($s['is_platinum'])) $platinumCount++;
        else $standardCount++;
      }
    ?>
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
      <h3 style="font-size: 15px; margin: 0;">Current Sponsors &amp; Partners (<?php echo count($sponsors); ?>)</h3>
      
      <!-- SPONSOR FILTER TABS -->
      <div style="display: flex; gap: 6px; background: var(--field-bg, rgba(0,0,0,0.2)); padding: 4px; border-radius: 8px; border: 1px solid var(--border-color, rgba(255,255,255,0.08));">
        <button type="button" class="sponsor-filter-btn active" onclick="filterSponsors('all', this)" style="background: var(--accent-primary, #f5b800); color: #000; font-weight: 700; border: none; padding: 4px 12px; border-radius: 6px; font-size: 11.5px; cursor: pointer;">
          All (<?php echo count($sponsors); ?>)
        </button>
        <button type="button" class="sponsor-filter-btn" onclick="filterSponsors('platinum', this)" style="background: transparent; color: var(--text-secondary); font-weight: 600; border: none; padding: 4px 12px; border-radius: 6px; font-size: 11.5px; cursor: pointer;">
          ★ Platinum (<?php echo $platinumCount; ?>)
        </button>
        <button type="button" class="sponsor-filter-btn" onclick="filterSponsors('standard', this)" style="background: transparent; color: var(--text-secondary); font-weight: 600; border: none; padding: 4px 12px; border-radius: 6px; font-size: 11.5px; cursor: pointer;">
          Standard (<?php echo $standardCount; ?>)
        </button>
      </div>
    </div>

    <?php if (count($sponsors) > 0): ?>
      <div id="adminSponsorsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px;">
        <?php foreach ($sponsors as $sponsor): ?>
          <?php 
            $isPlat = !empty($sponsor['is_platinum']);
            $sponsorProducts = [];
            if (!empty($sponsor['products_json'])) {
              $decodedP = json_decode($sponsor['products_json'], true);
              if (is_array($decodedP)) $sponsorProducts = $decodedP;
            }
          ?>
          <div class="admin-sponsor-card" data-tier="<?php echo $isPlat ? 'platinum' : 'standard'; ?>" style="background: var(--bg-secondary); padding: 16px; border-radius: 12px; border: 1px solid <?php echo $isPlat ? 'rgba(245,158,11,0.35)' : 'var(--border-color)'; ?>; display: flex; flex-direction: column; text-align: center; position: relative;">
            
            <?php if ($isPlat): ?>
              <div style="position: absolute; top: 10px; right: 10px;">
                <span style="background: linear-gradient(135deg, rgba(245,158,11,0.2), rgba(217,119,6,0.3)); border: 1px solid rgba(245,158,11,0.6); color: #f5b800; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 999px; display: inline-flex; align-items: center; gap: 3px; letter-spacing: 0.5px;">
                  ★ PLATINUM
                </span>
              </div>
            <?php endif; ?>

            <div style="height: 90px; display: flex; align-items: center; justify-content: center; background: #fff; border-radius: 8px; padding: 8px; margin-bottom: 12px; border: 1px solid rgba(0,0,0,0.06);">
              <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($sponsor['logo_path']); ?>" alt="<?php echo htmlspecialchars($sponsor['name']); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
            </div>
            <strong style="font-size: 14px; margin-bottom: 4px; display: block; color: var(--text-primary);"><?php echo htmlspecialchars($sponsor['name']); ?></strong>
            
            <?php if (!empty($sponsor['description'])): ?>
              <span class="muted" style="font-size: 12px; margin-bottom: 8px; display: block;"><?php echo htmlspecialchars($sponsor['description']); ?></span>
            <?php endif; ?>

            <?php if ($isPlat && count($sponsorProducts) > 0): ?>
              <span style="font-size: 11px; color: var(--accent-primary, #f5b800); font-weight: 700; margin-bottom: 8px; display: block;">
                <?php echo count($sponsorProducts); ?> Promotional Product<?php echo count($sponsorProducts) > 1 ? 's' : ''; ?>
              </span>
            <?php endif; ?>

            <?php if (!empty($sponsor['url'])): ?>
              <a href="<?php echo htmlspecialchars($sponsor['url']); ?>" target="_blank" style="font-size: 12px; margin-bottom: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                <?php echo icon('external_link', '', 12); ?> <span>Visit Site</span>
              </a>
            <?php endif; ?>

            <div style="margin-top: auto; display: flex; gap: 8px; align-items: center; padding-top: 8px;">
              <button type="button" class="btn btn-sm btn-secondary" 
                      onclick="openEditSponsorModal(<?php echo htmlspecialchars(json_encode([
                        'id' => $sponsor['id'],
                        'name' => $sponsor['name'],
                        'description' => $sponsor['description'] ?? '',
                        'url' => $sponsor['url'] ?? '',
                        'logo_path' => $sponsor['logo_path'] ?? '',
                        'is_platinum' => (int)($sponsor['is_platinum'] ?? 0),
                        'products' => $sponsorProducts
                      ]), ENT_QUOTES, 'UTF-8'); ?>)"
                      style="flex: 1; font-size: 12px; padding: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                <?php echo icon('edit', '', 12); ?> <span>Edit</span>
              </button>
              <form method="post" style="flex: 1; margin: 0;"
                    data-confirm="Delete sponsor '<?php echo htmlspecialchars($sponsor['name']); ?>'?"
                    data-confirm-title="Delete Sponsor"
                    data-confirm-btn="Delete"
                    data-confirm-class="btn-danger"
                    data-confirm-icon="trash">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_sponsor">
                <input type="hidden" name="sponsor_id" value="<?php echo $sponsor['id']; ?>">
                <button type="submit" class="btn btn-sm btn-danger" style="width: 100%; font-size: 12px; padding: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                  <?php echo icon('trash', '', 12); ?> <span>Delete</span>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 32px 10px; color: var(--text-secondary);">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245,158,11,0.1); color: var(--accent-primary, #f59e0b); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
          <?php echo icon('handshake', '', 26); ?>
        </div>
        <strong style="display: block;">No sponsors added yet</strong>
        <p class="muted" style="font-size: 12px; margin-top: 4px;">Add your first sponsor partner above.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- TAB 4: NEWS & ANNOUNCEMENTS -->
<div id="newsTab" class="settings-panel">
  <div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(245,158,11,0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('newspaper', '', 18); ?>
      </div>
      <h2 style="font-size: 16px; margin: 0;">Publish News or Announcement</h2>
    </div>
    <p class="muted" style="margin-bottom: 16px; font-size: 13px;">Create news articles and updates that appear on the website and member portals.</p>

    <form method="post" style="background: var(--bg-secondary); padding: 18px; border-radius: 12px; border: 1px solid var(--border-color);">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_news">
      <div class="grid-2" style="gap: 14px; margin-bottom: 14px;">
        <div class="field" style="margin: 0;">
          <label>News Headline / Title</label>
          <input type="text" name="news_title" placeholder="e.g. UAP Mindoro Chapter General Assembly 2026" required>
        </div>
        <div class="field" style="margin: 0;">
          <label>Date of Event / Publication</label>
          <input type="date" name="news_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
      </div>
      <div class="field" style="margin-bottom: 16px;">
        <label>Summary / Content</label>
        <textarea name="news_summary" rows="3" placeholder="Brief summary of the announcement, venue details, and registration instructions." required style="width: 100%; padding: 12px; font-family: inherit; font-size: 14px; line-height: 1.5;"></textarea>
      </div>
      <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('plus', '', 14); ?> <span>Publish News</span>
      </button>
    </form>
  </div>

  <div class="card">
    <h3 style="font-size: 15px; margin: 0 0 16px 0;">Published News &amp; Announcements (<?php echo count($news); ?>)</h3>
    <?php if (count($news) > 0): ?>
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($news as $item): ?>
          <div style="background: var(--bg-secondary); padding: 16px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
              <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                <strong style="font-size: 15px; color: var(--text-primary);"><?php echo htmlspecialchars($item['title']); ?></strong>
                <span class="badge" style="font-size: 11px;"><?php echo date('M d, Y', strtotime($item['date_posted'])); ?></span>
              </div>
              <p class="muted" style="margin: 0; font-size: 13px; line-height: 1.5;"><?php echo htmlspecialchars($item['summary']); ?></p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
              <button type="button" class="btn btn-sm btn-secondary" 
                      onclick="openEditNewsModal(<?php echo htmlspecialchars(json_encode([
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'summary' => $item['summary'],
                        'date_posted' => $item['date_posted']
                      ]), ENT_QUOTES, 'UTF-8'); ?>)"
                      style="font-size: 12px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo icon('edit', '', 12); ?> <span>Edit</span>
              </button>
              <form method="post" style="margin: 0;"
                    data-confirm="Delete news announcement '<?php echo htmlspecialchars($item['title']); ?>'?"
                    data-confirm-title="Delete News"
                    data-confirm-btn="Delete"
                    data-confirm-class="btn-danger"
                    data-confirm-icon="trash">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_news">
                <input type="hidden" name="news_id" value="<?php echo $item['id']; ?>">
                <button type="submit" class="btn btn-sm btn-danger" style="font-size: 12px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 4px;">
                  <?php echo icon('trash', '', 12); ?> <span>Delete</span>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 32px 10px; color: var(--text-secondary);">
        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245,158,11,0.1); color: var(--accent-primary, #f59e0b); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
          <?php echo icon('newspaper', '', 26); ?>
        </div>
        <strong style="display: block;">No news published yet</strong>
        <p class="muted" style="font-size: 12px; margin-top: 4px;">Publish your first chapter announcement above.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- EDIT NEWS MODAL -->
<div id="editNewsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:99999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(6px);">
  <div style="background:var(--card-bg, #18243a); border:1px solid var(--border-color, rgba(255,255,255,0.15)); border-radius:14px; max-width:540px; width:100%; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.6); color:var(--text-primary);">
    <div style="padding:22px;">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:rgba(245,158,11,0.15); color:#f59e0b;">
            <?php echo icon('edit', '', 18); ?>
          </div>
          <h3 style="margin:0; font-size:17px; font-weight:700;">Edit News Announcement</h3>
        </div>
        <button type="button" onclick="closeEditNewsModal()" style="background:transparent; border:none; color:var(--text-secondary); cursor:pointer; font-size:20px; padding:4px;">&times;</button>
      </div>

      <form method="post" id="editNewsForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="edit_news">
        <input type="hidden" name="news_id" id="edit_news_id" value="">

        <div class="field" style="margin-bottom:12px;">
          <label>News Headline / Title</label>
          <input type="text" name="news_title" id="edit_news_title" required style="width:100%;">
        </div>

        <div class="field" style="margin-bottom:12px;">
          <label>Date of Event / Publication</label>
          <input type="date" name="news_date" id="edit_news_date" required style="width:100%;">
        </div>

        <div class="field" style="margin-bottom:18px;">
          <label>Summary / Content</label>
          <textarea name="news_summary" id="edit_news_summary" rows="4" required style="width:100%; padding:10px; font-family:inherit; font-size:13.5px; line-height:1.5;"></textarea>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
          <button type="button" onclick="closeEditNewsModal()" class="btn btn-sm btn-secondary" style="padding:8px 16px;">Cancel</button>
          <button type="submit" class="btn btn-sm btn-success" style="padding:8px 20px; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
            <?php echo icon('check', '', 14); ?> <span>Save Changes</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT SPONSOR MODAL -->
<div id="editSponsorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:99999; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(6px);">
  <div style="background:var(--card-bg, #18243a); border:1px solid var(--border-color, rgba(255,255,255,0.15)); border-radius:14px; max-width:640px; width:100%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px -12px rgba(0,0,0,0.6); color:var(--text-primary);">
    <div style="padding:22px;">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <div style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:rgba(245,158,11,0.15); color:#f59e0b;">
            <?php echo icon('edit', '', 18); ?>
          </div>
          <h3 style="margin:0; font-size:17px; font-weight:700;">Edit Sponsor Partner</h3>
        </div>
        <button type="button" onclick="closeEditSponsorModal()" style="background:transparent; border:none; color:var(--text-secondary); cursor:pointer; font-size:20px; padding:4px;">&times;</button>
      </div>

      <form method="post" enctype="multipart/form-data" id="editSponsorForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="edit_sponsor">
        <input type="hidden" name="sponsor_id" id="edit_sponsor_id" value="">

        <div class="grid-2" style="gap: 12px; margin-bottom:12px;">
          <div class="field" style="margin-bottom:0;">
            <label>Sponsor / Partner Name</label>
            <input type="text" name="sponsor_name" id="edit_sponsor_name" required style="width:100%;">
          </div>
          <div class="field" style="margin-bottom:0;">
            <label>Website URL (optional)</label>
            <input type="url" name="sponsor_url" id="edit_sponsor_url" placeholder="https://example.com" style="width:100%;">
          </div>
        </div>

        <div class="field" style="margin-bottom:12px;">
          <label>Description / Partnership Role (optional)</label>
          <textarea name="sponsor_desc" id="edit_sponsor_desc" rows="2" style="width:100%; padding:8px; font-family:inherit; font-size:13px;"></textarea>
        </div>

        <div class="field" style="margin-bottom:14px;">
          <label>Replace Logo (leave empty to keep current)</label>
          <input type="file" name="sponsor_logo" accept="image/png, image/jpeg, image/webp" style="padding:6px; width:100%;">
        </div>

        <!-- PLATINUM TIER TOGGLE -->
        <div style="background: rgba(245,158,11,0.06); border: 1px solid rgba(245,158,11,0.25); border-radius: 10px; padding: 14px; margin-bottom: 16px;">
          <label style="display: flex; align-items: center; gap: 10px; font-weight: 700; color: var(--text-primary); cursor: pointer; margin-bottom: 0;">
            <input type="checkbox" name="is_platinum" id="edit_is_platinum" value="1" onchange="toggleEditPlatinumProducts(this.checked)" style="width: 18px; height: 18px; accent-color: var(--accent-primary, #f5b800);">
            <span style="display: inline-flex; align-items: center; gap: 6px;">
              <span style="color: var(--accent-primary, #f5b800); font-size: 15px;">★</span>
              <span>Designate as <strong>Platinum Sponsor / Partner</strong></span>
            </span>
          </label>
          
          <div id="editSponsorProductsSection" style="display: none; margin-top: 14px; padding-top: 14px; border-top: 1px dashed rgba(245,158,11,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
              <div>
                <strong style="font-size: 13px; color: var(--accent-primary, #f5b800);">Promotional Products &amp; Material Catalogs</strong>
                <p class="muted" style="font-size: 11.5px; margin: 2px 0 0;">Manage promotional product showcases for this Platinum partner.</p>
              </div>
              <button type="button" onclick="addProductSlot('editSponsorProductsList')" class="btn btn-sm" style="background: rgba(245,158,11,0.15); color: var(--accent-primary, #f5b800); border: 1px solid rgba(245,158,11,0.3); font-size: 11px; padding: 4px 10px;">
                + Add Product
              </button>
            </div>
            <div id="editSponsorProductsList" style="display: flex; flex-direction: column; gap: 12px;"></div>
          </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
          <button type="button" onclick="closeEditSponsorModal()" class="btn btn-sm btn-secondary" style="padding:8px 16px;">Cancel</button>
          <button type="submit" class="btn btn-sm btn-success" style="padding:8px 20px; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
            <?php echo icon('check', '', 14); ?> <span>Save Changes</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Sponsor Filter Tabs Logic
  function filterSponsors(tier, btn) {
    var buttons = document.querySelectorAll('.sponsor-filter-btn');
    buttons.forEach(function(b) {
      b.style.background = 'transparent';
      b.style.color = 'var(--text-secondary)';
      b.classList.remove('active');
    });
    btn.style.background = 'var(--accent-primary, #f5b800)';
    btn.style.color = '#000';
    btn.classList.add('active');

    var cards = document.querySelectorAll('.admin-sponsor-card');
    cards.forEach(function(card) {
      if (tier === 'all') {
        card.style.display = 'flex';
      } else {
        var cardTier = card.getAttribute('data-tier');
        card.style.display = (cardTier === tier) ? 'flex' : 'none';
      }
    });
  }

  function toggleAddPlatinumProducts(checked) {
    var sec = document.getElementById('addSponsorProductsSection');
    if (sec) {
      sec.style.display = checked ? 'block' : 'none';
      if (checked) {
        var list = document.getElementById('addSponsorProductsList');
        if (list && list.children.length === 0) {
          addProductSlot('addSponsorProductsList');
        }
      }
    }
  }

  function toggleEditPlatinumProducts(checked) {
    var sec = document.getElementById('editSponsorProductsSection');
    if (sec) {
      sec.style.display = checked ? 'block' : 'none';
      if (checked) {
        var list = document.getElementById('editSponsorProductsList');
        if (list && list.children.length === 0) {
          addProductSlot('editSponsorProductsList');
        }
      }
    }
  }

  var productSlotCount = 100;
  function addProductSlot(containerId, prodData) {
    var container = document.getElementById(containerId);
    if (!container) return;
    productSlotCount++;
    var idx = 'slot_' + Date.now() + '_' + productSlotCount;
    var data = prodData || { id: 'prod_' + Date.now(), name: '', description: '', link_url: '', image_path: '' };

    var div = document.createElement('div');
    div.className = 'sponsor-product-slot';
    div.style.cssText = 'background: rgba(0,0,0,0.25); border: 1px solid var(--border-color, rgba(255,255,255,0.1)); border-radius: 8px; padding: 12px;';

    var imgPreview = '';
    if (data.image_path) {
      var fullUrl = data.image_path.indexOf('http') === 0 ? data.image_path : ('<?php echo BASE_URL; ?>/' + data.image_path.replace(/^\//, ''));
      imgPreview = '<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;"><img src="' + fullUrl + '" style="width:44px; height:44px; object-fit:cover; border-radius:4px;"><span class="muted" style="font-size:11px;">Current Image</span></div>';
    }

    div.innerHTML = [
      '<input type="hidden" name="products[' + idx + '][id]" value="' + (data.id || '') + '">',
      '<input type="hidden" name="products[' + idx + '][existing_image]" value="' + (data.image_path || '') + '">',
      '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">',
      '  <span style="font-size:12px; font-weight:700; color:var(--accent-primary, #f5b800);">Promotional Product</span>',
      '  <button type="button" onclick="this.closest(\'.sponsor-product-slot\').remove()" style="background:transparent; border:none; color:#ef4444; font-size:11px; cursor:pointer; padding:2px 6px;">&times; Remove</button>',
      '</div>',
      '<div class="grid-2" style="gap:8px; margin-bottom:8px;">',
      '  <div class="field" style="margin:0;"><label style="font-size:11.5px;">Product Name *</label><input type="text" name="products[' + idx + '][name]" value="' + (data.name ? data.name.replace(/"/g, '&quot;') : '') + '" placeholder="e.g. Davies Sun & Rain, Megacryl" required style="font-size:12px; padding:6px 10px;"></div>',
      '  <div class="field" style="margin:0;"><label style="font-size:11.5px;">Product Link URL (Optional)</label><input type="url" name="products[' + idx + '][link_url]" value="' + (data.link_url ? data.link_url.replace(/"/g, '&quot;') : '') + '" placeholder="https://..." style="font-size:12px; padding:6px 10px;"></div>',
      '</div>',
      '<div class="field" style="margin-bottom:8px;"><label style="font-size:11.5px;">Product Description & Specs</label><textarea name="products[' + idx + '][description]" rows="2" placeholder="Product features, architectural application, color palette..." style="font-size:12px; padding:6px 10px;">' + (data.description || '') + '</textarea></div>',
      '<div class="field" style="margin:0;"><label style="font-size:11.5px;">Product Image</label>' + imgPreview + '<input type="file" name="products[' + idx + '][image]" accept=".jpg,.jpeg,.png,.webp" style="font-size:11px; padding:4px;"></div>'
    ].join('');

    container.appendChild(div);
  }

  function openEditNewsModal(data) {
    document.getElementById('edit_news_id').value = data.id;
    document.getElementById('edit_news_title').value = data.title || '';
    document.getElementById('edit_news_date').value = data.date_posted ? data.date_posted.substring(0, 10) : '';
    document.getElementById('edit_news_summary').value = data.summary || '';
    document.getElementById('editNewsModal').style.display = 'flex';
  }

  function closeEditNewsModal() {
    document.getElementById('editNewsModal').style.display = 'none';
  }

  function openEditSponsorModal(data) {
    document.getElementById('edit_sponsor_id').value = data.id;
    document.getElementById('edit_sponsor_name').value = data.name || '';
    document.getElementById('edit_sponsor_url').value = data.url || '';
    document.getElementById('edit_sponsor_desc').value = data.description || '';
    
    var isPlat = !!data.is_platinum;
    var platCheckbox = document.getElementById('edit_is_platinum');
    if (platCheckbox) {
      platCheckbox.checked = isPlat;
      toggleEditPlatinumProducts(isPlat);
    }

    var list = document.getElementById('editSponsorProductsList');
    if (list) {
      list.innerHTML = '';
      if (data.products && Array.isArray(data.products) && data.products.length > 0) {
        data.products.forEach(function(p) {
          addProductSlot('editSponsorProductsList', p);
        });
      } else if (isPlat) {
        addProductSlot('editSponsorProductsList');
      }
    }

    document.getElementById('editSponsorModal').style.display = 'flex';
  }

  function closeEditSponsorModal() {
    document.getElementById('editSponsorModal').style.display = 'none';
  }

  window.addEventListener('click', function(e) {
    var newsModal = document.getElementById('editNewsModal');
    if (e.target === newsModal) {
      closeEditNewsModal();
    }
    var sponsorModal = document.getElementById('editSponsorModal');
    if (e.target === sponsorModal) {
      closeEditSponsorModal();
    }
  });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
