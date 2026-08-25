<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$filter = $_GET['filter'] ?? 'pending';
$validFilters = ['pending', 'verified', 'rejected', 'all'];
if (!in_array($filter, $validFilters)) {
    $filter = 'pending';
}

$whereClause = "WHERE 1=1";
$params = [];
if ($filter !== 'all') {
    $whereClause .= " AND p.status = ?";
    $params[] = $filter;
}

$stmt = $pdo->prepare("
    SELECT p.*, u.name as member_name, u.id_number,
           COALESCE(md.custom_title, d.title) as due_title,
           COALESCE(md.custom_amount, d.amount) as due_amount,
           md.payment_type, md.installment_months, md.total_paid,
           p.installment_number, r.receipt_number
    FROM payments p
    JOIN member_dues md ON p.member_due_id = md.id
    JOIN users u ON md.user_id = u.id
    JOIN dues d ON md.due_id = d.id
    LEFT JOIN receipts r ON r.payment_id = p.id
    {$whereClause}
    ORDER BY p.submitted_at DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$verifiedCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'verified'")->fetchColumn();
$rejectedCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'rejected'")->fetchColumn();

$page_title = 'Payment Verification & Records';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-hero">
  <div>
    <p class="eyebrow">FINANCIAL VERIFICATION</p>
    <h1>Payment Verification &amp; Records</h1>
    <p class="page-subtitle">Inspect submitted payment proofs, approve transactions, issue official receipts, and audit history.</p>
  </div>
  <div class="hero-badge">
    <?php echo icon('payments', '', 14); ?> <span>Accounting Center</span>
  </div>
</div>

<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
  <a href="payments.php?filter=pending" class="stat-card" style="text-decoration:none;border-top: 3px solid #f59e0b;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Pending Verification</span>
      <div class="stat-card-icon" style="background: rgba(245,158,11,0.12); color: #f59e0b;">
        <?php echo icon('clock', '', 18); ?>
      </div>
    </div>
    <strong style="color:<?php echo $pendingCount > 0 ? '#f59e0b' : 'inherit'; ?>;"><?php echo $pendingCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Awaiting admin approval</span>
  </a>

  <a href="payments.php?filter=verified" class="stat-card" style="text-decoration:none;border-top: 3px solid #10b981;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Verified Payments</span>
      <div class="stat-card-icon" style="background: rgba(16,185,129,0.12); color: #10b981;">
        <?php echo icon('check', '', 18); ?>
      </div>
    </div>
    <strong style="color:#10b981;"><?php echo $verifiedCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">With Official Receipts</span>
  </a>

  <a href="payments.php?filter=rejected" class="stat-card" style="text-decoration:none;border-top: 3px solid #ef4444;">
    <div class="stat-card-header">
      <span class="muted" style="font-size: 13px; font-weight: 600;">Rejected Submissions</span>
      <div class="stat-card-icon" style="background: rgba(239,68,68,0.12); color: #ef4444;">
        <?php echo icon('x', '', 18); ?>
      </div>
    </div>
    <strong style="color:#ef4444;"><?php echo $rejectedCount; ?></strong>
    <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Declined payment proofs</span>
  </a>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="payments.php?filter=pending" class="btn btn-sm <?php echo $filter === 'pending' ? '' : 'btn-secondary'; ?>">
        <?php echo icon('clock', '', 13); ?> <span>Pending (<?php echo $pendingCount; ?>)</span>
      </a>
      <a href="payments.php?filter=verified" class="btn btn-sm <?php echo $filter === 'verified' ? '' : 'btn-secondary'; ?>">
        <?php echo icon('check', '', 13); ?> <span>Verified (<?php echo $verifiedCount; ?>)</span>
      </a>
      <a href="payments.php?filter=rejected" class="btn btn-sm <?php echo $filter === 'rejected' ? '' : 'btn-secondary'; ?>">
        <?php echo icon('x', '', 13); ?> <span>Rejected (<?php echo $rejectedCount; ?>)</span>
      </a>
      <a href="payments.php?filter=all" class="btn btn-sm <?php echo $filter === 'all' ? '' : 'btn-secondary'; ?>">
        <?php echo icon('filter', '', 13); ?> <span>All Records</span>
      </a>
    </div>
    <a href="export_csv.php" class="btn btn-sm btn-success" style="display:inline-flex;align-items:center;gap:6px;">
      <?php echo icon('download', '', 14); ?> <span>Export to CSV</span>
    </a>
  </div>

  <?php if (empty($payments)): ?>
    <div style="text-align: center; padding: 48px 16px; color: var(--text-secondary);">
      <div style="width: 54px; height: 54px; border-radius: 14px; background: rgba(59,130,246,0.1); color: #3b82f6; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
        <?php echo icon('check', '', 28); ?>
      </div>
      <strong style="display: block; font-size: 16px; color: var(--text-primary);">No payments in this category</strong>
      <p class="muted" style="margin-top: 4px; font-size: 13px;">There are no records matching your selected filter.</p>
    </div>
  <?php else: ?>
    <div class="table-shell">
      <table>
        <thead>
          <tr>
            <th>Member</th>
            <th>Due Item</th>
            <th>Type</th>
            <th>Amount Paid</th>
            <th>Method</th>
            <th>Reference No.</th>
            <th>Proof</th>
            <th>Status</th>
            <th>Submitted</th>
            <th style="text-align: right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td>
                <strong><?php echo htmlspecialchars($p['member_name']); ?></strong><br>
                <span class="muted" style="font-size:11px;"><?php echo htmlspecialchars($p['id_number']); ?></span>
              </td>
              <td><?php echo htmlspecialchars($p['due_title']); ?></td>
              <td>
                <?php
                switch ($p['installment_number']) {
                    case 0:
                        echo '<span class="badge-pill badge-paid">Full</span>';
                        break;
                    case 1:
                        echo '<span class="badge-pill badge-pending">1st Half</span>';
                        break;
                    case 2:
                        echo '<span class="badge-pill badge-pending">2nd Half</span>';
                        break;
                    default:
                        echo '<span class="badge-pill badge-partial">Installment</span>';
                }
                ?>
              </td>
              <td><strong style="color: #10b981;">₱<?php echo number_format($p['amount_paid'], 2); ?></strong></td>
              <td><span class="badge-pill badge-paid"><?php echo strtoupper(htmlspecialchars($p['method'])); ?></span></td>
              <td><code><?php echo htmlspecialchars($p['reference_number'] ?: '—'); ?></code></td>
              <td>
                <?php if ($p['proof_image']): ?>
                  <?php 
                    $proofPath = '../' . htmlspecialchars($p['proof_image']); 
                    $isPdf = strtolower(pathinfo($p['proof_image'], PATHINFO_EXTENSION)) === 'pdf';
                  ?>
                  <?php if ($isPdf): ?>
                    <a href="<?php echo $proofPath; ?>" target="_blank" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                      <?php echo icon('file', '', 12); ?> PDF
                    </a>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-secondary" style="padding: 4px 8px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;" onclick="openProofModal('<?php echo $proofPath; ?>', '<?php echo htmlspecialchars(addslashes($p['member_name'])); ?>', '<?php echo htmlspecialchars(addslashes($p['reference_number'])); ?>')">
                      <?php echo icon('image', '', 12); ?> View
                    </button>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge-pill badge-<?php echo $p['status'] === 'verified' ? 'paid' : ($p['status'] === 'rejected' ? 'unpaid' : 'pending'); ?>">
                  <?php echo ucfirst($p['status']); ?>
                </span>
                <?php if ($p['receipt_number']): ?>
                  <br><a href="../receipt.php?payment_id=<?php echo $p['id']; ?>" target="_blank" style="font-size:11px;color:var(--accent-primary);display:inline-flex;align-items:center;gap:3px;margin-top:2px;">
                    <?php echo icon('file', '', 10); ?> <span><?php echo htmlspecialchars($p['receipt_number']); ?></span>
                  </a>
                <?php endif; ?>
              </td>
              <td><span class="muted" style="font-size:12px;"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($p['submitted_at']))); ?></span></td>
              <td style="white-space:nowrap; text-align: right;">
                <?php if ($p['status'] === 'pending'): ?>
                  <form method="post" action="verify.php" class="inline" style="display:inline-block;margin-right:4px;"
                        data-confirm="Approve payment of ₱<?php echo number_format($p['amount_paid'], 2); ?> for <?php echo htmlspecialchars($p['member_name']); ?>?"
                        data-confirm-title="Approve Payment"
                        data-confirm-btn="Approve"
                        data-confirm-class="btn-success">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn btn-sm btn-success" type="submit" style="display:inline-flex;align-items:center;gap:4px;">
                      <?php echo icon('check', '', 12); ?> <span>Approve</span>
                    </button>
                  </form>
                  <form method="post" action="verify.php" class="inline" style="display:inline-block;"
                        data-confirm="Reject this payment submission from <?php echo htmlspecialchars($p['member_name']); ?>?"
                        data-confirm-title="Reject Payment"
                        data-confirm-btn="Reject"
                        data-confirm-class="btn-danger">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <button class="btn btn-sm btn-danger" type="submit" style="display:inline-flex;align-items:center;gap:4px;">
                      <?php echo icon('x', '', 12); ?> <span>Reject</span>
                    </button>
                  </form>
                <?php elseif ($p['status'] === 'verified'): ?>
                  <a href="../receipt.php?payment_id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm btn-secondary" style="display:inline-flex;align-items:center;gap:4px;">
                    <?php echo icon('file', '', 12); ?> <span>Receipt</span>
                  </a>
                <?php else: ?>
                  <span class="muted" style="font-size:12px;">Rejected</span>
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
<div id="proofModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.75);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px);">
  <div style="background:var(--card-bg, #131d33);border-radius:16px;max-width:600px;width:100%;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.6);border:1px solid var(--border-color);">
    <div style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border-color);">
      <h3 id="modalMemberTitle" style="margin:0;font-size:16px;color:var(--text-primary);">Proof of Payment</h3>
      <button type="button" onclick="closeProofModal()" style="border:none;background:transparent;cursor:pointer;color:var(--text-primary);display:flex;align-items:center;justify-content:center;padding:4px;">
        <?php echo icon('x', '', 18); ?>
      </button>
    </div>
    <div style="padding:18px;overflow-y:auto;text-align:center;background:rgba(0,0,0,0.1);">
      <img id="modalProofImg" src="" alt="Proof Preview" style="max-width:100%;max-height:65vh;object-fit:contain;border-radius:10px;border:1px solid var(--border-color);">
      <div id="modalRefInfo" style="margin-top:10px;font-size:13px;color:var(--text-secondary);"></div>
    </div>
    <div style="padding:12px 20px;display:flex;justify-content:flex-end;border-top:1px solid var(--border-color);gap:8px;">
      <a id="modalDirectLink" href="#" target="_blank" class="btn btn-sm btn-secondary" style="display:inline-flex;align-items:center;gap:4px;">
        <?php echo icon('external_link', '', 12); ?> <span>Open Full Image</span>
      </a>
      <button type="button" class="btn btn-sm btn-secondary" onclick="closeProofModal()">Close</button>
    </div>
  </div>
</div>

<script>
function openProofModal(imgSrc, memberName, refNo) {
  document.getElementById('modalProofImg').src = imgSrc;
  document.getElementById('modalDirectLink').href = imgSrc;
  document.getElementById('modalMemberTitle').innerText = 'Payment Proof: ' + memberName;
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
