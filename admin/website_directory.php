<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

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

    // Action 2: Directly Unlock / Mark Paid
    if ($action === 'manual_unlock') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = $pdo->prepare("UPDATE directory_applications SET status = 'paid' WHERE user_id = ?");
            $stmt->execute([$userId]);
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
$paymentApps = $pdo->query("SELECT da.*, u.name, u.id_number, md.status as payment_status, md.total_paid
    FROM directory_applications da
    JOIN users u ON da.user_id = u.id
    LEFT JOIN member_dues md ON da.member_due_id = md.id
    WHERE da.status = 'fee_set' AND (md.status != 'paid' OR md.status IS NULL)
    ORDER BY da.updated_at DESC")->fetchAll();

// 3. Fetch Active Paid Directory Members
$activeMembers = $pdo->query("SELECT da.*, u.name, u.id_number, wm.role_title, wm.specialty, wm.photo_path, wm.gallery_json, wm.updated_at as profile_updated
    FROM directory_applications da
    JOIN users u ON da.user_id = u.id
    LEFT JOIN website_members wm ON wm.user_id = u.id
    WHERE da.status = 'paid'
    ORDER BY u.name ASC")->fetchAll();

$page_title = 'Website Directory Manager';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
    <div>
      <h1 style="margin-bottom:4px;">Website Directory & Advertising Manager</h1>
      <p class="muted">Review member applications, assign directory advertising fees, and oversee published profiles.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>/index.php#members" target="_blank" class="btn btn-sm" style="background:transparent; border:1px solid var(--accent-primary, #f5b800); color:var(--accent-primary, #f5b800); font-weight:700;">
      🌐 View Live Public Directory
    </a>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
</div>

<!-- ================= QUEUE 1: PENDING DIRECTORY APPLICATIONS ================= -->
<div class="card" style="margin-top:20px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <h2 style="font-size:18px; margin:0; display:flex; align-items:center; gap:8px;">
      📢 New Directory Applications
      <span class="badge badge-pending" style="font-size:12px;"><?php echo count($pendingApps); ?> Pending</span>
    </h2>
  </div>

  <?php if (empty($pendingApps)): ?>
    <div style="text-align:center; padding:28px 12px; color:var(--text-secondary);">
      <span style="font-size:32px; display:block; margin-bottom:8px;">✨</span>
      <strong>No pending directory applications!</strong>
      <p class="muted" style="font-size:12px; margin-top:4px;">When members apply to be featured on the website directory, they will appear here for fee assignment.</p>
    </div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Applicant</th>
            <th>PRC ID No.</th>
            <th>Applied On</th>
            <th>Set Advertising Fee & Due Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingApps as $p): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
              <td><code><?php echo htmlspecialchars($p['id_number']); ?></code></td>
              <td><span class="muted" style="font-size:12px;"><?php echo date('M d, Y H:i', strtotime($p['created_at'])); ?></span></td>
              <td>
                <form method="post" id="feeForm_<?php echo $p['id']; ?>" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;"
                      data-confirm="Assign advertising fee for <?php echo htmlspecialchars($p['name']); ?>?"
                      data-confirm-title="Assign Directory Advertising Fee"
                      data-confirm-btn="Assign Fee"
                      data-confirm-class="btn-success"
                      data-confirm-icon="💳">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="set_directory_fee">
                  <input type="hidden" name="app_id" value="<?php echo $p['id']; ?>">
                  <input type="hidden" name="user_id" value="<?php echo $p['user_id']; ?>">
                  
                  <div style="display:flex; align-items:center; gap:4px;">
                    <span style="font-weight:700; color:var(--accent-primary, #f5b800);">₱</span>
                    <input type="number" step="0.01" name="fee_amount" value="500.00" required placeholder="Fee amount" style="width:110px; padding:6px 10px; font-size:13px; font-weight:700;">
                  </div>

                  <div style="display:flex; align-items:center; gap:4px;">
                    <span class="muted" style="font-size:11px;">Due:</span>
                    <input type="date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" style="padding:5px 8px; font-size:12px;">
                  </div>
              </td>
              <td style="white-space:nowrap;">
                  <button type="submit" class="btn btn-sm btn-success" style="padding:6px 12px; font-size:12px; font-weight:700;">
                    Assign Fee &rarr;
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
<div class="card" style="margin-top:20px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <h2 style="font-size:18px; margin:0; display:flex; align-items:center; gap:8px;">
      💳 Awaiting Member Payment & Verification
      <span class="badge" style="font-size:12px;"><?php echo count($paymentApps); ?> Active</span>
    </h2>
  </div>

  <?php if (empty($paymentApps)): ?>
    <p class="muted" style="font-size:13px;">No members currently in payment pending state.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>PRC ID No.</th>
            <th>Assigned Fee</th>
            <th>Payment Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($paymentApps as $pa): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($pa['name']); ?></strong></td>
              <td><code><?php echo htmlspecialchars($pa['id_number']); ?></code></td>
              <td><strong style="color:var(--accent-primary, #f5b800);">₱<?php echo number_format($pa['fee_amount'], 2); ?></strong></td>
              <td>
                <?php if ($pa['payment_status'] === 'pending'): ?>
                  <span class="badge badge-pending">Payment Submitted (Pending Verification)</span>
                <?php else: ?>
                  <span class="badge badge-unpaid">Unpaid</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="post" style="display:inline-block;"
                      data-confirm="Manually unlock Website Directory feature for <?php echo htmlspecialchars($pa['name']); ?>?"
                      data-confirm-title="Manual Feature Unlock"
                      data-confirm-btn="Unlock Feature"
                      data-confirm-class="btn-success"
                      data-confirm-icon="🔓">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="action" value="manual_unlock">
                  <input type="hidden" name="user_id" value="<?php echo $pa['user_id']; ?>">
                  <button type="submit" class="btn btn-sm" style="font-size:11px; padding:4px 8px;">Force Unlock</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ================= QUEUE 3: ACTIVE UNLOCKED DIRECTORY MEMBERS ================= -->
<div class="card" style="margin-top:20px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <h2 style="font-size:18px; margin:0; display:flex; align-items:center; gap:8px;">
      ✨ Unlocked & Published Directory Advertisers (<?php echo count($activeMembers); ?>)
    </h2>
  </div>

  <?php if (empty($activeMembers)): ?>
    <p class="muted" style="font-size:13px;">No directory members have completed verification yet.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>PRC ID No.</th>
            <th>Role / Title</th>
            <th>Specialty</th>
            <th>Portfolio Photos</th>
            <th>Actions</th>
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
          ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($am['name']); ?></strong></td>
              <td><code><?php echo htmlspecialchars($am['id_number']); ?></code></td>
              <td><?php echo htmlspecialchars($am['role_title'] ?: 'Architect'); ?></td>
              <td><?php echo htmlspecialchars($am['specialty'] ?: 'General Practice'); ?></td>
              <td>
                <span class="badge badge-paid" style="font-size:11px;">
                  📸 <?php echo $photoCount; ?> Photo<?php echo $photoCount !== 1 ? 's' : ''; ?>
                </span>
              </td>
              <td>
                <a href="<?php echo BASE_URL; ?>/public/member_profile.php?prc=<?php echo urlencode($am['id_number']); ?>" target="_blank" class="btn btn-sm btn-success" style="font-size:11px; padding:4px 10px;">
                  View Live Profile &rarr;
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
