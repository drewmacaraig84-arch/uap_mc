<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// --- 1. KPI QUERIES ---
// Total Verified Collections
$totalVerifiedCollections = (float)$pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE status = 'verified'")->fetchColumn();

// Pending counts
$pendingPaymentsCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$pendingMembersCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'pending'")->fetchColumn();
$totalActionItems = $pendingPaymentsCount + $pendingMembersCount;

// Members breakdown
$totalMembers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'approved'")->fetchColumn();

// Good standing count
$goodMembersCount = 0;
$allMembers = $pdo->query("SELECT id FROM users WHERE role = 'member' AND status = 'approved'")->fetchAll();
foreach ($allMembers as $m) {
    if (is_good_member($pdo, $m['id'])) {
        $goodMembersCount++;
    }
}
$goodStandingRate = $totalMembers > 0 ? round(($goodMembersCount / $totalMembers) * 100) : 100;

// Total Dues Packages
$totalDuesCount = (int)$pdo->query("SELECT COUNT(*) FROM dues")->fetchColumn();

// --- 2. URGENT PENDING PAYMENTS ---
$pendingPayments = $pdo->query("
    SELECT p.*, u.name as member_name, u.id_number,
           COALESCE(md.custom_title, d.title) as due_title,
           COALESCE(md.custom_amount, d.amount) as due_amount,
           md.payment_type, md.installment_months, md.total_paid,
           p.installment_number
    FROM payments p
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN users u ON md.user_id = u.id
    JOIN dues d ON md.due_id = d.id
    WHERE p.status = 'pending'
    ORDER BY p.submitted_at ASC
    LIMIT 6
")->fetchAll();

// --- 3. URGENT PENDING REGISTRATIONS ---
$pendingMembers = $pdo->query("
    SELECT * FROM users
    WHERE role = 'member' AND status = 'pending'
    ORDER BY created_at ASC
    LIMIT 6
")->fetchAll();

// --- 4. DUES COLLECTION HEALTH BREAKDOWN ---
$duesSummary = $pdo->query("
    SELECT d.id, d.title, d.amount as base_amount, d.due_date,
           COUNT(md.id) as assigned_count,
           SUM(COALESCE(md.custom_amount, d.amount)) as total_expected,
           SUM(md.total_paid) as total_collected,
           SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) as fully_paid_count
    FROM dues d
    LEFT JOIN member_dues md ON md.due_id = d.id
    GROUP BY d.id, d.title, d.amount, d.due_date
    ORDER BY d.created_at DESC
    LIMIT 5
")->fetchAll();

// --- 5. RECENT VERIFIED TRANSACTIONS ---
$recentTransactions = $pdo->query("
    SELECT p.id, u.name as member_name, u.id_number,
           COALESCE(md.custom_title, d.title) as due_title,
           p.amount_paid, p.method, p.verified_at, r.receipt_number
    FROM payments p
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN users u ON md.user_id = u.id
    JOIN dues d ON md.due_id = d.id
    LEFT JOIN receipts r ON r.payment_id = p.id
    WHERE p.status = 'verified'
    ORDER BY p.verified_at DESC
    LIMIT 5
")->fetchAll();

$page_title = 'Admin Executive Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- EXECUTIVE HERO SECTION -->
<div class="page-hero">
  <div>
    <p class="eyebrow">UAP Mindoro Chapter • Management Portal</p>
    <h1>Executive Dashboard</h1>
    <p class="page-subtitle">Unified summary of chapter finances, member compliance, pending approvals, and active dues.</p>
  </div>
  <div class="hero-badge">⚡ Command Center</div>
</div>

<!-- TOP KPI METRIC CARDS -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 24px;">
  
  <div class="stat-card" style="border-left: 4px solid #10b981;">
    <span class="muted">💰 Total Verified Collections</span>
    <strong style="color: #10b981; font-size: 24px;">₱<?php echo number_format($totalVerifiedCollections, 2); ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Lifetime approved dues</span>
  </div>

  <div class="stat-card" style="border-left: 4px solid <?php echo $totalActionItems > 0 ? '#f59e0b' : '#3b82f6'; ?>;">
    <span class="muted">⏳ Action Items Needed</span>
    <strong style="color: <?php echo $totalActionItems > 0 ? '#f59e0b' : 'inherit'; ?>; font-size: 24px;">
      <?php echo $totalActionItems; ?>
    </strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
      <?php echo $pendingPaymentsCount; ?> payments, <?php echo $pendingMembersCount; ?> sign-ups
    </span>
  </div>

  <div class="stat-card" style="border-left: 4px solid #3b82f6;">
    <span class="muted">👥 Approved Members</span>
    <strong style="font-size: 24px;"><?php echo $totalMembers; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
      ⭐ <?php echo $goodMembersCount; ?> in Good Standing (<?php echo $goodStandingRate; ?>%)
    </span>
  </div>

  <div class="stat-card" style="border-left: 4px solid #8b5cf6;">
    <span class="muted">📋 Active Dues Packages</span>
    <strong style="font-size: 24px;"><?php echo $totalDuesCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">
      Managed chapter dues
    </span>
  </div>

</div>

<!-- QUICK ACTION SHORTCUTS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px;">
  <a href="payments.php" class="btn" style="text-align: center; text-decoration: none; padding: 12px; font-size: 13px; font-weight: 600;">
    💳 Review Payments (<?php echo $pendingPaymentsCount; ?>)
  </a>
  <a href="approvals.php" class="btn" style="text-align: center; text-decoration: none; padding: 12px; font-size: 13px; font-weight: 600; background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
    👤 Member Approvals (<?php echo $pendingMembersCount; ?>)
  </a>
  <a href="dues.php" class="btn" style="text-align: center; text-decoration: none; padding: 12px; font-size: 13px; font-weight: 600; background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
    ➕ Add New Due
  </a>
  <a href="good_members.php" class="btn" style="text-align: center; text-decoration: none; padding: 12px; font-size: 13px; font-weight: 600; background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
    ⭐ Good Members
  </a>

  <a href="export_csv.php" class="btn" style="text-align: center; text-decoration: none; padding: 12px; font-size: 13px; font-weight: 600; background: #10b981; color: #fff;">
    📥 Download CSV Report
  </a>
</div>

<!-- DUAL COLUMN: PENDING ACTIONS -->
<div class="grid-2" style="margin-bottom: 24px; gap: 20px;">
  
  <!-- COLUMN 1: PENDING PAYMENTS QUEUE -->
  <div class="card" style="margin-bottom: 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h2 style="font-size: 18px; margin: 0;">💳 Pending Payment Proofs</h2>
      <a href="payments.php" style="font-size: 12px; color: var(--accent-primary);">View All (<?php echo $pendingPaymentsCount; ?>) &rarr;</a>
    </div>

    <?php if (empty($pendingPayments)): ?>
      <div style="text-align: center; padding: 30px 10px; color: var(--text-secondary);">
        <span style="font-size: 32px; display: block; margin-bottom: 8px;">🎉</span>
        <strong>All payments are reviewed!</strong>
        <p style="font-size: 12px; margin-top: 4px;" class="muted">No pending payment submissions awaiting verification.</p>
      </div>
    <?php else: ?>
      <div class="table-shell">
        <table>
          <thead>
            <tr>
              <th>Member</th>
              <th>Due</th>
              <th>Amount</th>
              <th>Proof</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingPayments as $p): ?>
              <tr>
                <td>
                  <strong><?php echo htmlspecialchars($p['member_name']); ?></strong><br>
                  <span class="muted" style="font-size: 11px;"><?php echo htmlspecialchars($p['id_number']); ?></span>
                </td>
                <td><span style="font-size: 13px;"><?php echo htmlspecialchars($p['due_title']); ?></span></td>
                <td><strong>₱<?php echo number_format($p['amount_paid'], 2); ?></strong></td>
                <td>
                  <?php if ($p['proof_image']): ?>
                    <?php 
                      $proofPath = '../' . htmlspecialchars($p['proof_image']); 
                      $isPdf = strtolower(pathinfo($p['proof_image'], PATHINFO_EXTENSION)) === 'pdf';
                    ?>
                    <?php if ($isPdf): ?>
                      <a href="<?php echo $proofPath; ?>" target="_blank" class="btn btn-sm" style="padding: 4px 8px; font-size: 11px;">📄 PDF</a>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm" style="padding: 4px 8px; font-size: 11px;" onclick="openProofModal('<?php echo $proofPath; ?>', '<?php echo htmlspecialchars(addslashes($p['member_name'])); ?>', '<?php echo htmlspecialchars(addslashes($p['reference_number'])); ?>')">🖼️ View</button>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
                <td style="white-space: nowrap;">
                  <form method="post" action="verify.php" class="inline" style="display:inline-block;margin-right:2px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success" type="submit" style="padding: 4px 8px; font-size: 11px;" onclick="return confirm('Approve payment of ₱<?php echo number_format($p['amount_paid'], 2); ?> for <?php echo htmlspecialchars(addslashes($p['member_name'])); ?>?');">✓</button>
                  </form>
                  <form method="post" action="verify.php" class="inline" style="display:inline-block;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <button class="btn btn-sm btn-danger" type="submit" style="padding: 4px 8px; font-size: 11px;" onclick="return confirm('Reject this payment?');">✕</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- COLUMN 2: PENDING MEMBER REGISTRATIONS -->
  <div class="card" style="margin-bottom: 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h2 style="font-size: 18px; margin: 0;">👤 Pending Member Sign-Ups</h2>
      <a href="approvals.php" style="font-size: 12px; color: var(--accent-primary);">View All (<?php echo $pendingMembersCount; ?>) &rarr;</a>
    </div>

    <?php if (empty($pendingMembers)): ?>
      <div style="text-align: center; padding: 30px 10px; color: var(--text-secondary);">
        <span style="font-size: 32px; display: block; margin-bottom: 8px;">✨</span>
        <strong>No pending registrations!</strong>
        <p style="font-size: 12px; margin-top: 4px;" class="muted">All member accounts have been processed.</p>
      </div>
    <?php else: ?>
      <div class="table-shell">
        <table>
          <thead>
            <tr>
              <th>Applicant Name</th>
              <th>PRC ID No.</th>
              <th>Registered</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingMembers as $m): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($m['name']); ?></strong></td>
                <td><code><?php echo htmlspecialchars($m['id_number']); ?></code></td>
                <td><span class="muted" style="font-size: 11px;"><?php echo htmlspecialchars(date('M d, Y', strtotime($m['created_at']))); ?></span></td>
                <td style="white-space: nowrap;">
                  <form method="post" action="approvals.php" class="inline" style="display:inline-block;margin-right:2px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success" type="submit" style="padding: 4px 8px; font-size: 11px;" onclick="return confirm('Approve member registration for <?php echo htmlspecialchars(addslashes($m['name'])); ?>?');">Approve</button>
                  </form>
                  <form method="post" action="approvals.php" class="inline" style="display:inline-block;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <button class="btn btn-sm btn-danger" type="submit" style="padding: 4px 8px; font-size: 11px;" onclick="return confirm('Reject registration for <?php echo htmlspecialchars(addslashes($m['name'])); ?>?');">Reject</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- DUES COLLECTION PROGRESS TRACKER -->
<div class="card" style="margin-bottom: 24px;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
    <div>
      <h2 style="font-size: 18px; margin: 0;">📊 Dues Financial Health & Collection Progress</h2>
      <p class="muted" style="font-size: 13px; margin: 4px 0 0;">Monitoring expected targets versus collected funds per due cycle.</p>
    </div>
    <a href="dues.php" class="btn btn-sm" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-primary);">Manage Dues &rarr;</a>
  </div>

  <?php if (empty($duesSummary)): ?>
    <p class="muted" style="text-align: center; padding: 20px;">No dues created yet.</p>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 16px;">
      <?php foreach ($duesSummary as $due):
        $expected = (float)($due['total_expected'] ?? 0);
        $collected = (float)($due['total_collected'] ?? 0);
        $percent = $expected > 0 ? min(100, round(($collected / $expected) * 100, 1)) : 0;
      ?>
        <div style="background: var(--bg-secondary, rgba(0,0,0,0.04)); padding: 14px 16px; border-radius: 10px; border: 1px solid var(--border-color);">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
            <div>
              <strong style="font-size: 15px;"><?php echo htmlspecialchars($due['title']); ?></strong>
              <span class="muted" style="font-size: 12px; margin-left: 8px;">
                (Due: <?php echo date('M d, Y', strtotime($due['due_date'])); ?>)
              </span>
            </div>
            <div style="text-align: right;">
              <span style="font-size: 14px; font-weight: 700; color: #10b981;">₱<?php echo number_format($collected, 2); ?></span>
              <span class="muted" style="font-size: 13px;"> / ₱<?php echo number_format($expected, 2); ?></span>
              <span style="font-weight: 700; margin-left: 8px; color: var(--accent-primary);"><?php echo $percent; ?>%</span>
            </div>
          </div>
          
          <!-- Progress Bar -->
          <div style="width: 100%; height: 8px; background: rgba(0,0,0,0.15); border-radius: 999px; overflow: hidden; margin-bottom: 6px;">
            <div style="height: 100%; width: <?php echo $percent; ?>%; background: linear-gradient(90deg, #f59e0b, #10b981); border-radius: 999px; transition: width 0.3s ease;"></div>
          </div>

          <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary);">
            <span>Assigned: <strong><?php echo (int)$due['assigned_count']; ?> members</strong></span>
            <span>Fully Paid: <strong><?php echo (int)$due['fully_paid_count']; ?> members</strong></span>
            <span>Balance: <strong style="color:<?php echo ($expected - $collected) > 0 ? '#ef4444' : '#10b981'; ?>;">₱<?php echo number_format(max(0, $expected - $collected), 2); ?></strong></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- RECENT TRANSACTIONS STREAM -->
<div class="card">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
    <h2 style="font-size: 18px; margin: 0;">🧾 Recent Verified Transactions</h2>
    <a href="reports.php" style="font-size: 12px; color: var(--accent-primary);">View All Reports &rarr;</a>
  </div>

  <?php if (empty($recentTransactions)): ?>
    <p class="muted" style="text-align: center; padding: 20px;">No verified transactions yet.</p>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>Due Title</th>
            <th>Amount Paid</th>
            <th>Method</th>
            <th>Receipt #</th>
            <th>Verified Date</th>
            <th>Receipt</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentTransactions as $tx): ?>
            <tr>
              <td>
                <strong><?php echo htmlspecialchars($tx['member_name']); ?></strong><br>
                <span class="muted" style="font-size: 11px;"><?php echo htmlspecialchars($tx['id_number']); ?></span>
              </td>
              <td><?php echo htmlspecialchars($tx['due_title']); ?></td>
              <td><strong style="color: #10b981;">₱<?php echo number_format($tx['amount_paid'], 2); ?></strong></td>
              <td><span class="badge"><?php echo strtoupper(htmlspecialchars($tx['method'])); ?></span></td>
              <td><code><?php echo htmlspecialchars($tx['receipt_number'] ?: '—'); ?></code></td>
              <td><span class="muted" style="font-size: 12px;"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($tx['verified_at']))); ?></span></td>
              <td>
                <?php if ($tx['receipt_number']): ?>
                  <a href="../receipt.php?payment_id=<?php echo $tx['id']; ?>" target="_blank" class="btn btn-sm" style="padding: 4px 8px; font-size: 11px;">View Receipt</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Proof Lightbox Modal -->
<div id="proofModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.75);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:var(--card-bg, #fff);border-radius:12px;max-width:600px;width:100%;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.3);border:1px solid var(--border-color);">
    <div style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);">
      <h3 id="modalMemberTitle" style="margin:0;font-size:16px;color:var(--text-primary);">Proof of Payment</h3>
      <button type="button" onclick="closeProofModal()" style="border:none;background:transparent;font-size:22px;cursor:pointer;line-height:1;color:var(--text-primary);">&times;</button>
    </div>
    <div style="padding:16px;overflow-y:auto;text-align:center;background:rgba(0,0,0,0.02);">
      <img id="modalProofImg" src="" alt="Proof Preview" style="max-width:100%;max-height:65vh;object-fit:contain;border-radius:8px;border:1px solid var(--border-color);">
      <div id="modalRefInfo" style="margin-top:10px;font-size:13px;color:var(--muted-text,#666);"></div>
    </div>
    <div style="padding:10px 18px;display:flex;justify-content:flex-end;border-top:1px solid var(--border-color);">
      <a id="modalDirectLink" href="#" target="_blank" class="btn btn-sm" style="margin-right:8px;">Open Full Image</a>
      <button type="button" class="btn btn-sm" onclick="closeProofModal()">Close</button>
    </div>
  </div>
</div>

<script>
function openProofModal(imgSrc, memberName, refNo) {
  document.getElementById('modalProofImg').src = imgSrc;
  document.getElementById('modalDirectLink').href = imgSrc;
  document.getElementById('modalMemberTitle').innerText = 'Proof: ' + memberName;
  document.getElementById('modalRefInfo').innerText = refNo ? 'Reference Number: ' + refNo : '';
  document.getElementById('proofModal').style.display = 'flex';
}

function closeProofModal() {
  document.getElementById('proofModal').style.display = 'none';
  document.getElementById('modalProofImg').src = '';
}

document.getElementById('proofModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeProofModal();
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
