<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
$success = '';

// Load default fee from site_settings
$defaultFee = '500.00';
try {
    $sStmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'website_directory_fee'");
    $sStmt->execute();
    $sRow = $sStmt->fetch();
    if ($sRow && !empty($sRow['setting_value'])) {
        $defaultFee = number_format((float)$sRow['setting_value'], 2, '.', '');
    }
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    // Action: Save Default Website Directory Fee
    if ($action === 'save_default_fee') {
        $newDefault = (float)($_POST['default_fee'] ?? 0);
        if ($newDefault <= 0) {
            $error = 'Please enter a valid default fee amount greater than 0.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('website_directory_fee', ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([number_format($newDefault, 2, '.', '')]);
            $defaultFee = number_format($newDefault, 2, '.', '');
            $success = 'Default website directory advertising fee updated to ₱' . number_format($newDefault, 2);
        }
    }

    // Action 1: Set Fee & Assign Due to Member
    if ($action === 'set_directory_fee') {
        $appId = (int)($_POST['app_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $feeAmount = (float)($_POST['fee_amount'] ?? 0);
        $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : date('Y-m-d', strtotime('+14 days'));

        if ($feeAmount <= 0) {
            $error = 'Please enter a valid advertising fee amount greater than 0.';
        } elseif ($userId > 0) {
            try {
                $pdo->beginTransaction();

                // Create due program specifically for this directory advertising fee
                $dStmt = $pdo->prepare("INSERT INTO dues (title, description, amount, due_date, term) 
                                       VALUES ('Website Directory Advertising Fee', 'Annual chapter website directory listing and portfolio showcase', ?, ?, 'Annual')");
                $dStmt->execute([$feeAmount, $dueDate]);
                $dueId = (int)$pdo->lastInsertId();

                // Assign to member in member_dues
                $mStmt = $pdo->prepare("INSERT INTO member_dues (user_id, due_id, status) VALUES (?, ?, 'unpaid')");
                $mStmt->execute([$userId, $dueId]);
                $memberDueId = (int)$pdo->lastInsertId();

                // Update application record
                $aStmt = $pdo->prepare("UPDATE directory_applications 
                                       SET status = 'fee_set', fee_amount = ?, member_due_id = ? 
                                       WHERE user_id = ?");
                $aStmt->execute([$feeAmount, $memberDueId, $userId]);

                $pdo->commit();
                $success = 'Advertising fee of ₱' . number_format($feeAmount, 2) . ' assigned to member successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to assign advertising fee: ' . $e->getMessage();
            }
        }
    }

    // Action: Update existing assigned fee amount
    if ($action === 'update_assigned_fee') {
        $appId = (int)($_POST['app_id'] ?? 0);
        $memberDueId = (int)($_POST['member_due_id'] ?? 0);
        $newAmount = (float)($_POST['new_amount'] ?? 0);

        if ($newAmount <= 0) {
            $error = 'Please enter a valid fee amount greater than 0.';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE directory_applications SET fee_amount = ? WHERE id = ?")->execute([$newAmount, $appId]);
                
                if ($memberDueId > 0) {
                    $pdo->prepare("UPDATE member_dues SET custom_amount = ? WHERE id = ?")->execute([$newAmount, $memberDueId]);
                }
                $pdo->commit();
                $success = 'Updated advertising fee to ₱' . number_format($newAmount, 2);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to update fee: ' . $e->getMessage();
            }
        }
    }

    // Action 2: Directly Unlock / Mark Paid
    if ($action === 'manual_unlock') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = $pdo->prepare("UPDATE directory_applications SET status = 'paid' WHERE user_id = ?");
            $stmt->execute([$userId]);

            // Auto-initialize website_members with profile photo if no row exists yet
            $wmCheck = $pdo->prepare("SELECT id FROM website_members WHERE user_id = ?");
            $wmCheck->execute([$userId]);
            if (!$wmCheck->fetch()) {
                $uData = $pdo->prepare("SELECT name, id_number, profile_photo FROM users WHERE id = ?");
                $uData->execute([$userId]);
                $u = $uData->fetch();
                if ($u) {
                    $pdo->prepare("INSERT INTO website_members (user_id, name, id_number, role_title, specialty, location, photo_path, is_published) 
                                   VALUES (?, ?, ?, 'Architect', 'General Practice', 'Mindoro', ?, 1)")
                        ->execute([$userId, $u['name'], $u['id_number'], $u['profile_photo'] ?: null]);
                }
            } else {
                $pdo->prepare("UPDATE website_members SET is_published = 1 WHERE user_id = ?")->execute([$userId]);
            }

            $success = 'Directory feature manually unlocked for member.';
        }
    }

    // Action 3: Reject Application
    if ($action === 'reject_app') {
        $appId = (int)($_POST['app_id'] ?? 0);
        if ($appId > 0) {
            $stmt = $pdo->prepare("UPDATE directory_applications SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$appId]);
            $success = 'Directory application rejected.';
        }
    }
}

// 1. Fetch Pending Applications (Awaiting Fee)
$pendingApps = $pdo->query("SELECT da.*, u.name, u.id_number
    FROM directory_applications da
    JOIN users u ON da.user_id = u.id
    WHERE da.status = 'pending_fee'
    ORDER BY da.created_at DESC")->fetchAll();

// 2. Fetch Applications Awaiting Payment / Verification
$paymentApps = $pdo->query("SELECT da.*, u.name, u.id_number, md.status as payment_status, md.total_paid, md.id as member_due_id_val
    FROM directory_applications da
    JOIN users u ON da.user_id = u.id
    LEFT JOIN member_dues md ON da.member_due_id = md.id
    WHERE da.status = 'fee_set' AND (md.status != 'paid' OR md.status IS NULL)
    ORDER BY da.updated_at DESC")->fetchAll();

// 3. Fetch Active Paid Directory Members
$activeMembers = $pdo->query("SELECT da.*, u.name, u.id_number, wm.role_title, wm.specialty, wm.company_name, wm.link_url, wm.link_type, wm.photo_path, wm.gallery_json, wm.updated_at as profile_updated
    FROM directory_applications da
    JOIN users u ON da.user_id = u.id
    LEFT JOIN website_members wm ON wm.user_id = u.id
    WHERE da.status = 'paid'
    ORDER BY u.name ASC")->fetchAll();

$page_title = 'Website Directory Manager • UAP Mindoro Chapter';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">PORTAL &amp; PUBLIC PROFILES</p>
    <h1>Website Directory &amp; Showcase Manager</h1>
    <p class="page-subtitle">Set directory advertising fees, review architect showcase submissions, and manage published directory profiles.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('website_directory', '', 14); ?> <span><?php echo count($activeMembers); ?> Published</span>
  </div>
</div>

<?php if ($error): ?>
  <div class="alert alert-error">
    <div style="display:flex;align-items:center;gap:8px;">
      <?php echo icon('alert', '', 18); ?>
      <span><?php echo htmlspecialchars($error); ?></span>
    </div>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success">
    <div style="display:flex;align-items:center;gap:8px;">
      <?php echo icon('check', '', 18); ?>
      <span><?php echo htmlspecialchars($success); ?></span>
    </div>
  </div>
<?php endif; ?>

<!-- DEFAULT FEE SETTINGS CARD -->
<div class="card" style="margin-bottom: 24px;">
  <form method="post" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save_default_fee">
    <div style="display: flex; align-items: center; gap: 12px;">
      <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(245,158,11,0.12); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
        <?php echo icon('settings', '', 20); ?>
      </div>
      <div>
        <strong style="color:var(--text-primary); font-size:14px; display:block;">Default Directory Advertising Fee</strong>
        <span class="muted" style="font-size:12px;">Pre-filled base amount when assigning advertising dues to new applicants</span>
      </div>
    </div>
    <div style="display:flex; align-items:center; gap:8px;">
      <div style="display:flex; align-items:center; gap:4px;">
        <span style="font-weight:800; color:var(--accent-primary); font-size:15px;">₱</span>
        <input type="number" step="0.01" name="default_fee" value="<?php echo htmlspecialchars($defaultFee); ?>" required style="width:120px; padding:7px 10px; font-size:14px; font-weight:700;">
      </div>
      <button type="submit" class="btn btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
        <?php echo icon('check', '', 14); ?> <span>Save Default Fee</span>
      </button>
    </div>
  </form>
</div>

<!-- ================= QUEUE 1: PENDING DIRECTORY APPLICATIONS ================= -->
<div class="card" style="margin-bottom: 24px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div style="display: flex; align-items: center; gap: 8px;">
      <span style="color: #f59e0b;"><?php echo icon('clock', '', 18); ?></span>
      <h2 style="font-size:16px; margin:0;">New Directory Applications</h2>
    </div>
    <span class="badge-pill badge-pending"><?php echo count($pendingApps); ?> Pending</span>
  </div>

  <?php if (empty($pendingApps)): ?>
    <div style="text-align:center; padding:32px 12px; color:var(--text-secondary);">
      <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59,130,246,0.1); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 10px;">
        <?php echo icon('check', '', 24); ?>
      </div>
      <strong style="display: block; font-size: 14px; color: var(--text-primary);">No pending directory applications!</strong>
      <p class="muted" style="font-size:12px; margin-top:4px;">When members apply to be featured on the chapter directory, they will appear here to have their fee set.</p>
    </div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Applicant Name</th>
            <th>PRC ID No.</th>
            <th>Application Date</th>
            <th>Set Advertising Fee &amp; Due Date</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingApps as $p): ?>
            <tr>
              <td><strong style="color: var(--text-primary);"><?php echo htmlspecialchars($p['name']); ?></strong></td>
              <td><code><?php echo htmlspecialchars($p['id_number']); ?></code></td>
              <td><span class="muted" style="font-size:12px;"><?php echo date('M d, Y h:i A', strtotime($p['created_at'])); ?></span></td>
              <td>
                <form method="post" id="feeForm_<?php echo $p['id']; ?>" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;"
                      data-confirm="Assign advertising fee for <?php echo htmlspecialchars($p['name']); ?>?"
                      data-confirm-title="Assign Directory Advertising Fee"
                      data-confirm-btn="Assign Fee"
                      data-confirm-class="btn-success">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="set_directory_fee">
                  <input type="hidden" name="app_id" value="<?php echo $p['id']; ?>">
                  <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                  
                  <div style="display:flex; align-items:center; gap:4px;">
                    <span style="font-weight:700; color:var(--accent-primary);">₱</span>
                    <input type="number" step="0.01" name="fee_amount" value="<?php echo htmlspecialchars($defaultFee); ?>" required placeholder="Fee amount" style="width:100px; padding:6px 10px; font-size:13px; font-weight:700;">
                  </div>

                  <div style="display:flex; align-items:center; gap:4px;">
                    <span class="muted" style="font-size:11px;">Due:</span>
                    <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" style="padding:5px 8px; font-size:12px; width: 140px;">
                  </div>
              </td>
              <td style="white-space:nowrap; text-align: right;">
                  <button type="submit" class="btn btn-sm btn-success" style="display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('check', '', 12); ?> <span>Assign Fee</span>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ================= QUEUE 2: AWAITING PAYMENT / VERIFICATION ================= -->
<div class="card" style="margin-bottom: 24px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div style="display: flex; align-items: center; gap: 8px;">
      <span style="color: #3b82f6;"><?php echo icon('payments', '', 18); ?></span>
      <h2 style="font-size:16px; margin:0;">Awaiting Member Payment &amp; Verification</h2>
    </div>
    <span class="badge-pill badge-partial"><?php echo count($paymentApps); ?> Active</span>
  </div>

  <?php if (empty($paymentApps)): ?>
    <p class="muted" style="font-size:13px; text-align: center; padding: 24px;">No members currently in pending directory payment state.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>PRC ID No.</th>
            <th>Assigned Fee</th>
            <th>Payment Status</th>
            <th style="text-align: right;">Actions / Fee Adjustment</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($paymentApps as $pa): ?>
            <tr>
              <td><strong style="color: var(--text-primary);"><?php echo htmlspecialchars($pa['name']); ?></strong></td>
              <td><code><?php echo htmlspecialchars($pa['id_number']); ?></code></td>
              <td>
                <strong style="color: #10b981; font-size:14px;">₱<?php echo number_format($pa['fee_amount'], 2); ?></strong>
              </td>
              <td>
                <?php if ($pa['payment_status'] === 'pending'): ?>
                  <span class="badge-pill badge-pending">Payment Submitted (Pending Verification)</span>
                <?php else: ?>
                  <span class="badge-pill badge-unpaid">Unpaid</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <div style="display:flex; align-items:center; justify-content: flex-end; gap:8px; flex-wrap:wrap;">
                  <!-- Change/Edit Fee Form -->
                  <form method="post" style="display:flex; align-items:center; gap:4px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_assigned_fee">
                    <input type="hidden" name="app_id" value="<?php echo $pa['id']; ?>">
                    <input type="hidden" name="member_due_id" value="<?php echo $pa['member_due_id_val'] ?? 0; ?>">
                    <input type="number" step="0.01" name="new_amount" value="<?php echo htmlspecialchars($pa['fee_amount']); ?>" style="width:85px; padding:4px 6px; font-size:12px; font-weight:700;">
                    <button type="submit" class="btn btn-sm btn-secondary" style="font-size:11px; padding:5px 8px;">Update</button>
                  </form>

                  <!-- Force Unlock -->
                  <form method="post" style="display:inline-block;"
                        data-confirm="Manually unlock Website Directory feature for <?php echo htmlspecialchars($pa['name']); ?>?"
                        data-confirm-title="Manual Feature Unlock"
                        data-confirm-btn="Unlock Feature"
                        data-confirm-class="btn-success">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="manual_unlock">
                    <input type="hidden" name="user_id" value="<?php echo $pa['user_id']; ?>">
                    <button type="submit" class="btn btn-sm btn-success" style="font-size:11px; padding:5px 10px; display: inline-flex; align-items: center; gap: 4px;">
                      <?php echo icon('check', '', 11); ?> <span>Unlock</span>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ================= QUEUE 3: ACTIVE UNLOCKED DIRECTORY MEMBERS ================= -->
<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div style="display: flex; align-items: center; gap: 8px;">
      <span style="color: #10b981;"><?php echo icon('good_members', '', 18); ?></span>
      <h2 style="font-size:16px; margin:0;">Published Directory Profiles</h2>
    </div>
    <span class="badge-pill badge-paid"><?php echo count($activeMembers); ?> Published</span>
  </div>

  <?php if (empty($activeMembers)): ?>
    <p class="muted" style="font-size:13px; text-align: center; padding: 24px;">No directory members have completed verification yet.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>PRC ID No.</th>
            <th>Company / Firm</th>
            <th>Role &amp; Specialty</th>
            <th>Portfolio Showcase &amp; Link</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($activeMembers as $am): 
            $photoCount = 0;
            if (!empty($am['gallery_json'])) {
                $g = json_decode($am['gallery_json'], true);
                if (is_array($g)) $photoCount = count($g);
            } elseif (!empty($am['photo_path'])) {
                $photoCount = 1;
            }
            $linkIcon = !empty($am['link_url']) && function_exists('detect_social_link_type') 
              ? detect_social_link_type($am['link_url'], $am['link_type'] ?? 'auto') 
              : 'globe';
          ?>
            <tr>
              <td><strong style="color: var(--text-primary);"><?php echo htmlspecialchars($am['name']); ?></strong></td>
              <td><code><?php echo htmlspecialchars($am['id_number']); ?></code></td>
              <td>
                <?php if (!empty($am['company_name'])): ?>
                  <span style="display:inline-flex; align-items:center; gap:5px; font-weight:600; color:var(--text-primary); font-size:13px;">
                    <?php echo icon('briefcase', '', 13); ?> <?php echo htmlspecialchars($am['company_name']); ?>
                  </span>
                <?php else: ?>
                  <span class="muted" style="font-size:12px;">None specified</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-size:13px; font-weight:600; color:var(--accent-primary);"><?php echo htmlspecialchars($am['role_title'] ?: 'Architect'); ?></div>
                <div class="muted" style="font-size:11.5px;"><?php echo htmlspecialchars($am['specialty'] ?: 'General Practice'); ?></div>
              </td>
              <td>
                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                  <span class="badge-pill badge-paid" style="font-size:11px; display: inline-flex; align-items: center; gap: 4px;">
                    <?php echo icon('image', '', 12); ?> <?php echo $photoCount; ?> Photo<?php echo $photoCount !== 1 ? 's' : ''; ?>
                  </span>
                  <?php if (!empty($am['link_url'])): ?>
                    <a href="<?php echo htmlspecialchars($am['link_url']); ?>" target="_blank" rel="noopener noreferrer" class="badge-pill" style="font-size:11px; text-decoration:none; background:rgba(59,130,246,0.12); color:#60a5fa; display:inline-flex; align-items:center; gap:4px;">
                      <?php echo icon($linkIcon === 'website' ? 'globe' : $linkIcon, '', 11); ?>
                      <span><?php echo ucfirst($linkIcon); ?></span>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
              <td style="text-align: right;">
                <div style="display:inline-flex; align-items:center; gap:6px; justify-content:flex-end;">
                  <a href="download_qr.php?id=<?php echo (int)$am['id']; ?>" class="btn btn-sm btn-primary" style="font-size:11px; padding:4px 9px; display:inline-flex; align-items:center; gap:4px;" title="Download Public Directory QR Code">
                    <?php echo icon('download', '', 12); ?> <span>Download QR</span>
                  </a>
                  <a href="<?php echo BASE_URL; ?>/profile/<?php echo (int)$am['id']; ?>" target="_blank" class="btn btn-sm btn-secondary" style="font-size:11px; padding:4px 9px; display:inline-flex; align-items:center; gap:4px;">
                    <?php echo icon('external_link', '', 12); ?> <span>View Profile</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
