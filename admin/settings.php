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

    // ============ ABOUT US UPDATE ============
    if ($action === 'update_about' && !empty($_POST['about_us'] ?? '')) {
        $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('about_us', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$_POST['about_us']]);
        if (function_exists('cache_delete')) {
            cache_delete('site_setting:about_us');
        }
        $success = 'Website About Us section updated successfully.';
    }

    // ============ SPONSOR MANAGEMENT ============
    if ($action === 'add_sponsor' && isset($_FILES['sponsor_logo'])) {
        if ($_FILES['sponsor_logo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please choose a sponsor logo to upload.';
        } else {
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

                    $stmt = $pdo->prepare("INSERT INTO sponsors (name, logo_path, description, url, display_order) VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(display_order), 0) + 1 FROM sponsors s2))");
                    $stmt->execute([$_POST['sponsor_name'] ?? '', $logo_path, $_POST['sponsor_desc'] ?? '', $_POST['sponsor_url'] ?? '']);
                    $success = 'Sponsor partner added successfully.';
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
$org_name = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'org_name'")->fetch();
$org_name_val = $org_name ? $org_name['setting_value'] : 'United Architects of the Philippines - Mindoro Chapter';

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
        <div style="width: 80px; height: 80px; border-radius: 12px; background: #fff; padding: 4px; border: 1px solid rgba(0,0,0,0.08); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.06); flex-shrink: 0;">
          <?php
            $logo_src = $logo ? (BASE_URL . '/' . htmlspecialchars($logo['setting_value'])) : (BASE_URL . '/public/logo.jpg');
          ?>
          <img src="<?php echo $logo_src; ?>" alt="Current Logo" onerror="if(this.src.indexOf('public/logo.jpg')===-1)this.src='<?php echo BASE_URL; ?>/public/logo.jpg';" style="max-width: 100%; max-height: 100%; object-fit: contain;">
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
          <?php echo icon('upload', '', 14); ?> <span><?php echo $logo ? 'Replace Chapter Logo' : 'Upload Logo'; ?></span>
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

<!-- TAB 2: WEBSITE ABOUT US -->
<div id="websiteTab" class="settings-panel">
  <div class="card" style="max-width: 860px; margin: 0;">
    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
      <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(16,185,129,0.12); color: #10b981; display: flex; align-items: center; justify-content: center;">
        <?php echo icon('info', '', 18); ?>
      </div>
      <h2 style="font-size: 16px; margin: 0;">Website About Us Content</h2>
    </div>
    <p class="muted" style="margin-bottom: 16px; font-size: 13px;">Manage the overview and mission text for the UAP Mindoro public landing page.</p>

    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="update_about">
      <div class="field" style="margin-bottom: 16px;">
        <label>About Us Paragraph / Mission Statement</label>
        <textarea name="about_us" rows="6" required style="width: 100%; padding: 12px; font-family: inherit; font-size: 14px; line-height: 1.6;"><?php echo htmlspecialchars($about_us['setting_value'] ?? ''); ?></textarea>
      </div>
      <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('check', '', 14); ?> <span>Save About Us Content</span>
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
    <p class="muted" style="margin-bottom: 16px; font-size: 13px;">Add sponsor logos and links for showcase on the chapter website and directory.</p>

    <form method="post" enctype="multipart/form-data" style="background: var(--bg-secondary); padding: 18px; border-radius: 12px; border: 1px solid var(--border-color);">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_sponsor">
      <div class="grid-2" style="gap: 14px; margin-bottom: 14px;">
        <div class="field" style="margin: 0;">
          <label>Sponsor / Partner Name</label>
          <input type="text" name="sponsor_name" placeholder="e.g. Boysen Paints Philippines" required>
        </div>
        <div class="field" style="margin: 0;">
          <label>Logo Image</label>
          <input type="file" name="sponsor_logo" accept=".jpg,.jpeg,.png,.webp" required>
        </div>
      </div>
      <div class="grid-2" style="gap: 14px; margin-bottom: 16px;">
        <div class="field" style="margin: 0;">
          <label>Description (Optional)</label>
          <input type="text" name="sponsor_desc" placeholder="e.g. Official Platinum Chapter Sponsor">
        </div>
        <div class="field" style="margin: 0;">
          <label>Website URL (Optional)</label>
          <input type="url" name="sponsor_url" placeholder="https://example.com">
        </div>
      </div>
      <button class="btn btn-sm" type="submit" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('plus', '', 14); ?> <span>Add Sponsor</span>
      </button>
    </form>
  </div>

  <div class="card">
    <h3 style="font-size: 15px; margin: 0 0 16px 0;">Current Sponsors (<?php echo count($sponsors); ?>)</h3>
    <?php if (count($sponsors) > 0): ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px;">
        <?php foreach ($sponsors as $sponsor): ?>
          <div style="background: var(--bg-secondary); padding: 16px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-direction: column; text-align: center;">
            <div style="height: 90px; display: flex; align-items: center; justify-content: center; background: #fff; border-radius: 8px; padding: 8px; margin-bottom: 12px; border: 1px solid rgba(0,0,0,0.06);">
              <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($sponsor['logo_path']); ?>" alt="<?php echo htmlspecialchars($sponsor['name']); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
            </div>
            <strong style="font-size: 14px; margin-bottom: 4px; display: block;"><?php echo htmlspecialchars($sponsor['name']); ?></strong>
            <?php if (!empty($sponsor['description'])): ?>
              <span class="muted" style="font-size: 12px; margin-bottom: 8px; display: block;"><?php echo htmlspecialchars($sponsor['description']); ?></span>
            <?php endif; ?>
            <?php if (!empty($sponsor['url'])): ?>
              <a href="<?php echo htmlspecialchars($sponsor['url']); ?>" target="_blank" style="font-size: 12px; margin-bottom: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                <?php echo icon('external_link', '', 12); ?> <span>Visit Site</span>
              </a>
            <?php endif; ?>
            <form method="post" style="margin-top: auto;"
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
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 32px 10px; color: var(--text-secondary);">
        <span style="font-size: 32px; display: block; margin-bottom: 8px;">🤝</span>
        <strong>No sponsors added yet</strong>
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
        <?php echo icon('plus', '', 14); ?> <span>Publish Announcement</span>
      </button>
    </form>
  </div>

  <div class="card">
    <h3 style="font-size: 15px; margin: 0 0 16px 0;">Published News &amp; Announcements (<?php echo count($news); ?>)</h3>
    <?php if (count($news) > 0): ?>
      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
        <?php foreach ($news as $item): ?>
          <div style="background: var(--bg-secondary); padding: 16px; border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-direction: column;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
              <span class="badge-pill" style="background: rgba(242,184,53,0.15); color: #f2b835; font-size: 11px; padding: 2px 8px; display: inline-flex; align-items: center; gap: 4px;">
                <?php echo icon('clock', '', 11); ?> <?php echo date('M d, Y', strtotime($item['date_posted'])); ?>
              </span>
            </div>
            <strong style="font-size: 14px; margin-bottom: 6px; line-height: 1.4; color: var(--text-primary);"><?php echo htmlspecialchars($item['title']); ?></strong>
            <p class="muted" style="font-size: 13px; line-height: 1.5; margin: 0 0 16px 0; flex-grow: 1;"><?php echo htmlspecialchars($item['summary']); ?></p>
            <form method="post" style="margin-top: auto;"
                  data-confirm="Delete news announcement '<?php echo htmlspecialchars($item['title']); ?>'?"
                  data-confirm-title="Delete News Announcement"
                  data-confirm-btn="Delete"
                  data-confirm-class="btn-danger"
                  data-confirm-icon="trash">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="delete_news">
              <input type="hidden" name="news_id" value="<?php echo $item['id']; ?>">
              <button type="submit" class="btn btn-sm btn-danger" style="width: 100%; font-size: 12px; padding: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                <?php echo icon('trash', '', 12); ?> <span>Delete</span>
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div style="text-align: center; padding: 32px 10px; color: var(--text-secondary);">
        <span style="font-size: 32px; display: block; margin-bottom: 8px;">📰</span>
        <strong>No announcements published yet</strong>
        <p class="muted" style="font-size: 12px; margin-top: 4px;">Publish chapter news using the form above.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
