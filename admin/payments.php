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

$page_title = 'Payment Management';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-hero">
  <div>
    <p class="eyebrow">Financial Verification</p>
    <h1>Payment Management</h1>
    <p class="page-subtitle">Review incoming payments, verify proofs, inspect receipts, and manage transactions.</p>
  </div>
  <div class="hero-badge">💳 Accounting Center</div>
</div>

<div class="stat-grid">
  <a href="payments.php?filter=pending" class="stat-card" style="text-decoration:none;border-color:<?php echo $filter === 'pending' ? 'var(--accent-primary)' : 'var(--border-color)'; ?>;">
    <span class="muted">Pending Verification</span>
    <strong style="color:<?php echo $pendingCount > 0 ? 'var(--badge-warning-text,#f59e0b)' : 'inherit'; ?>;"><?php echo $pendingCount; ?></strong>
  </a>
  <a href="payments.php?filter=verified" class="stat-card" style="text-decoration:none;border-color:<?php echo $filter === 'verified' ? 'var(--accent-primary)' : 'var(--border-color)'; ?>;">
    <span class="muted">Verified Payments</span>
    <strong style="color:#10b981;"><?php echo $verifiedCount; ?></strong>
  </a>
  <a href="payments.php?filter=rejected" class="stat-card" style="text-decoration:none;border-color:<?php echo $filter === 'rejected' ? 'var(--accent-primary)' : 'var(--border-color)'; ?>;">
    <span class="muted">Rejected</span>
    <strong style="color:#ef4444;"><?php echo $rejectedCount; ?></strong>
  </a>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;gap:8px;">
      <a href="payments.php?filter=pending" class="btn btn-sm <?php echo $filter === 'pending' ? '' : 'btn-secondary'; ?>" style="<?php echo $filter !== 'pending' ? 'background:transparent;border:1px solid var(--border-color);color:var(--text-primary);' : ''; ?>">Pending (<?php echo $pendingCount; ?>)</a>
      <a href="payments.php?filter=verified" class="btn btn-sm <?php echo $filter === 'verified' ? '' : 'btn-secondary'; ?>" style="<?php echo $filter !== 'verified' ? 'background:transparent;border:1px solid var(--border-color);color:var(--text-primary);' : ''; ?>">Verified (<?php echo $verifiedCount; ?>)</a>
      <a href="payments.php?filter=rejected" class="btn btn-sm <?php echo $filter === 'rejected' ? '' : 'btn-secondary'; ?>" style="<?php echo $filter !== 'rejected' ? 'background:transparent;border:1px solid var(--border-color);color:var(--text-primary);' : ''; ?>">Rejected (<?php echo $rejectedCount; ?>)</a>
      <a href="payments.php?filter=all" class="btn btn-sm <?php echo $filter === 'all' ? '' : 'btn-secondary'; ?>" style="<?php echo $filter !== 'all' ? 'background:transparent;border:1px solid var(--border-color);color:var(--text-primary);' : ''; ?>">All Records</a>
    </div>
    <a href="export_csv.php" class="btn btn-sm" style="background:#10b981;color:#fff;">📥 Export to CSV</a>
  </div>

  <?php if (empty($payments)): ?>
    <p class="muted" style="text-align:center;padding:30px 0;">No payments found for this filter. 🎉</p>
  <?php else: ?>
  <div class="table-shell">
  <table>
    <thead>
      <tr>
        <th>Member</th>
        <th>Due</th>
        <th>Type</th>
        <th>Amount</th>
        <th>Method</th>
        <th>Reference</th>
        <th>Proof</th>
        <th>Status</th>
        <th>Submitted</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($payments as $p):
        $due_amount = (float)$p['due_amount'];
        $total_paid = (float)$p['total_paid'];
      ?>
      <tr>
        <td>
          <strong><?php echo htmlspecialchars($p['member_name']); ?></strong><br>
          <span class="muted" style="font-size:12px;"><?php echo htmlspecialchars($p['id_number']); ?></span>
        </td>
        <td><?php echo htmlspecialchars($p['due_title']); ?></td>
        <td>
          <?php
          switch ($p['installment_number']) {
              case 0:
                  echo '<span class="badge badge-paid">Full</span>';
                  break;
              case 1:
                  echo '<span class="badge badge-pending">1st Half</span>';
                  break;
              case 2:
                  echo '<span class="badge badge-warning">2nd Half</span>';
                  break;
              default:
                  echo '<span class="badge">Installment</span>';
          }
          ?>
        </td>
        <td><strong>₱<?php echo number_format($p['amount_paid'], 2); ?></strong></td>
        <td><span class="badge"><?php echo strtoupper(htmlspecialchars($p['method'])); ?></span></td>
        <td><code><?php echo htmlspecialchars($p['reference_number'] ?: '—'); ?></code></td>
        <td>
          <?php if ($p['proof_image']): ?>
            <?php 
              $proofPath = '../' . htmlspecialchars($p['proof_image']); 
              $isPdf = strtolower(pathinfo($p['proof_image'], PATHINFO_EXTENSION)) === 'pdf';
            ?>
            <?php if ($isPdf): ?>
              <a href="<?php echo $proofPath; ?>" target="_blank" class="btn btn-sm" style="background:var(--field-bg);color:var(--text-primary);border:1px solid var(--border-color);">📄 PDF</a>
            <?php else: ?>
              <button type="button" class="btn btn-sm" style="background:var(--field-bg);color:var(--text-primary);border:1px solid var(--border-color);" onclick="openProofModal('<?php echo $proofPath; ?>', '<?php echo htmlspecialchars(addslashes($p['member_name'])); ?>', '<?php echo htmlspecialchars(addslashes($p['reference_number'])); ?>')">🖼️ View</button>
            <?php endif; ?>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </td>
        <td>
          <span class="badge badge-<?php echo $p['status'] === 'verified' ? 'paid' : $p['status']; ?>">
            <?php echo ucfirst($p['status']); ?>
          </span>
          <?php if ($p['receipt_number']): ?>
            <br><a href="../receipt.php?payment_id=<?php echo $p['id']; ?>" target="_blank" style="font-size:11px;color:var(--accent-primary);">Rcpt: <?php echo htmlspecialchars($p['receipt_number']); ?></a>
          <?php endif; ?>
        </td>
        <td><span class="muted" style="font-size:12px;"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($p['submitted_at']))); ?></span></td>
        <td style="white-space:nowrap;">
          <?php if ($p['status'] === 'pending'): ?>
            <form method="post" action="verify.php" class="inline" style="display:inline-block;margin-right:4px;"
                  data-confirm="Approve payment of ₱<?php echo number_format($p['amount_paid'], 2); ?> for <?php echo htmlspecialchars($p['member_name']); ?>?"
                  data-confirm-title="Approve Payment"
                  data-confirm-btn="Approve"
                  data-confirm-class="btn-success"
                  data-confirm-icon="💳">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
              <input type="hidden" name="action" value="approve">
              <button class="btn btn-sm btn-success" type="submit">Approve</button>
            </form>
            <form method="post" action="verify.php" class="inline" style="display:inline-block;"
                  data-confirm="Reject this payment submission from <?php echo htmlspecialchars($p['member_name']); ?>?"
                  data-confirm-title="Reject Payment"
                  data-confirm-btn="Reject"
                  data-confirm-class="btn-danger"
                  data-confirm-icon="❌">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
              <input type="hidden" name="action" value="reject">
              <button class="btn btn-sm btn-danger" type="submit">Reject</button>
            </form>
          <?php elseif ($p['status'] === 'verified'): ?>

            <a href="../receipt.php?payment_id=<?php echo $p['id']; ?>" target="_blank" class="btn btn-sm">Receipt</a>
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
