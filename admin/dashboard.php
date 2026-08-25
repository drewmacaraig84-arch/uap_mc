<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$stmt = $pdo->query("
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
");
$pending = $stmt->fetchAll();

$page_title = 'Pending Payments';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-hero">
  <div>
    <p class="eyebrow">Administrative overview</p>
    <h1>Pending Payments</h1>
    <p class="page-subtitle">Review incoming payments, verify submissions, and keep dues processing moving smoothly.</p>
  </div>
  <div class="hero-badge">Approval workflow</div>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <span class="muted">Pending submissions</span>
    <strong><?php echo count($pending); ?></strong>
  </div>
  <div class="stat-card">
    <span class="muted">Review status</span>
    <strong><?php echo count($pending) > 0 ? 'Needs attention' : 'All clear'; ?></strong>
  </div>
</div>

<div class="card">
  <?php if (empty($pending)): ?>
    <p class="muted">No pending payments right now. 🎉</p>
  <?php else: ?>
  <div class="table-shell">
  <table>
    <thead>
      <tr>
        <th>Member</th>
        <th>Due</th>
        <th>Type</th>
        <th>Amount Submitted</th>
        <th>Progress</th>
        <th>Method</th>
        <th>Reference</th>
        <th>Proof</th>
        <th>Submitted</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pending as $p):
        $due_amount = (float)$p['due_amount'];
        $total_paid = (float)$p['total_paid'];
        $remaining = max(0, round($due_amount - $total_paid, 2));
      ?>
      <tr>
        <td>
          <strong><?php echo htmlspecialchars($p['member_name']); ?></strong><br>
          <span class="muted"><?php echo htmlspecialchars($p['id_number']); ?></span>
        </td>
        <td><?php echo htmlspecialchars($p['due_title']); ?></td>
        <td>
          <?php
          switch ($p['installment_number']) {
              case 0:
                  echo '<span class="badge badge-paid">Full Payment</span>';
                  break;
              case 1:
                  echo '<span class="badge badge-pending">First Half</span>';
                  break;
              case 2:
                  echo '<span class="badge badge-warning">Second Half</span>';
                  break;
              default:
                  echo '<span class="badge">Payment</span>';
          }
          ?>
        </td>
        <td><strong>₱<?php echo number_format($p['amount_paid'], 2); ?></strong></td>
        <td>
          <span class="muted">Paid: ₱<?php echo number_format($total_paid, 2); ?></span><br>
          <span class="muted">Balance: ₱<?php echo number_format($remaining, 2); ?></span>
        </td>
        <td><span class="badge"><?php echo strtoupper(htmlspecialchars($p['method'])); ?></span></td>
        <td><code><?php echo htmlspecialchars($p['reference_number'] ?: '—'); ?></code></td>
        <td>
          <?php if ($p['proof_image']): ?>
            <?php 
              $proofPath = '../' . htmlspecialchars($p['proof_image']); 
              $isPdf = strtolower(pathinfo($p['proof_image'], PATHINFO_EXTENSION)) === 'pdf';
            ?>
            <?php if ($isPdf): ?>
              <a href="<?php echo $proofPath; ?>" target="_blank" class="btn btn-sm" style="background:#eef2f9;color:#18243a;">📄 PDF</a>
            <?php else: ?>
              <button type="button" class="btn btn-sm" style="background:#eef2f9;color:#18243a;" onclick="openProofModal('<?php echo $proofPath; ?>', '<?php echo htmlspecialchars(addslashes($p['member_name'])); ?>', '<?php echo htmlspecialchars(addslashes($p['reference_number'])); ?>')">🖼️ View</button>
            <?php endif; ?>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </td>
        <td><span class="muted" style="font-size:12px;"><?php echo htmlspecialchars($p['submitted_at']); ?></span></td>
        <td style="white-space:nowrap;">
          <form method="post" action="verify.php" class="inline" style="display:inline-block;margin-right:4px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
            <input type="hidden" name="action" value="approve">
            <button class="btn btn-sm btn-success" type="submit" onclick="return confirm('Approve this payment of ₱<?php echo number_format($p['amount_paid'], 2); ?> for <?php echo htmlspecialchars(addslashes($p['member_name'])); ?>?');">Approve</button>
          </form>
          <form method="post" action="verify.php" class="inline" style="display:inline-block;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
            <input type="hidden" name="action" value="reject">
            <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Reject this payment submission?');">Reject</button>
          </form>
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
  <div style="background:var(--card-bg, #fff);border-radius:12px;max-width:600px;width:100%;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.3);">
    <div style="padding:14px 18px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(0,0,0,0.08);">
      <h3 id="modalMemberTitle" style="margin:0;font-size:16px;">Proof of Payment</h3>
      <button type="button" onclick="closeProofModal()" style="border:none;background:transparent;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
    </div>
    <div style="padding:16px;overflow-y:auto;text-align:center;background:rgba(0,0,0,0.02);">
      <img id="modalProofImg" src="" alt="Proof Preview" style="max-width:100%;max-height:65vh;object-fit:contain;border-radius:8px;border:1px solid rgba(0,0,0,0.1);">
      <div id="modalRefInfo" style="margin-top:10px;font-size:13px;color:var(--muted-text,#666);"></div>
    </div>
    <div style="padding:10px 18px;display:flex;justify-content:flex-end;border-top:1px solid rgba(0,0,0,0.08);">
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
