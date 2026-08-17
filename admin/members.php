<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$selected_member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
$editing_member_due_id = isset($_GET['edit_member_due']) ? (int)$_GET['edit_member_due'] : 0;

if ($selected_member_id > 0) {
    $member_lookup = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'member'");
    $member_lookup->execute([$selected_member_id]);
    if (!$member_lookup->fetch()) {
        $selected_member_id = 0;
    }
}

// Handle member account delete — manual cascade for MyISAM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_member') {
    $user_id = (int)$_POST['user_id'];

    // Get all member_dues for this user
    $md_ids = $pdo->prepare("SELECT id FROM member_dues WHERE user_id = ?");
    $md_ids->execute([$user_id]);
    $md_rows = $md_ids->fetchAll(PDO::FETCH_COLUMN);

    if ($md_rows) {
        foreach ($md_rows as $md_id) {
            $pay_ids = $pdo->prepare("SELECT id FROM payments WHERE member_due_id = ?");
            $pay_ids->execute([$md_id]);
            $p_rows = $pay_ids->fetchAll(PDO::FETCH_COLUMN);
            if ($p_rows) {
                $in = implode(',', array_map('intval', $p_rows));
                $pdo->exec("DELETE FROM receipts WHERE payment_id IN ($in)");
                $pdo->exec("DELETE FROM payments WHERE id IN ($in)");
            }
        }
        $in = implode(',', array_map('intval', $md_rows));
        $pdo->exec("DELETE FROM member_dues WHERE id IN ($in)");
    }

    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'member'")->execute([$user_id]);
    header('Location: members.php?deleted=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_member_due') {
    $member_due_id = (int)$_POST['member_due_id'];
    $member_id = (int)$_POST['member_id'];

    $pay_ids = $pdo->prepare("SELECT id FROM payments WHERE member_due_id = ?");
    $pay_ids->execute([$member_due_id]);
    $p_rows = $pay_ids->fetchAll(PDO::FETCH_COLUMN);

    if ($p_rows) {
        $in = implode(',', array_map('intval', $p_rows));
        $pdo->exec("DELETE FROM receipts WHERE payment_id IN ($in)");
        $pdo->exec("DELETE FROM payments WHERE id IN ($in)");
    }

    $pdo->prepare("DELETE FROM member_dues WHERE id = ?")->execute([$member_due_id]);
    header('Location: members.php?member_id=' . $member_id . '&deleted_due=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_member_due') {
    $member_due_id = (int)$_POST['member_due_id'];
    $member_id = (int)$_POST['member_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $due_date = trim($_POST['due_date'] ?? '');
    $term = trim($_POST['term'] ?? '');

    $stmt = $pdo->prepare("UPDATE member_dues SET custom_title=?, custom_description=?, custom_amount=?, custom_due_date=?, custom_term=? WHERE id=?");
    $stmt->execute([
        $title !== '' ? $title : null,
        $description !== '' ? $description : null,
        $amount > 0 ? $amount : null,
        $due_date !== '' ? $due_date : null,
        $term !== '' ? $term : null,
        $member_due_id,
    ]);

    header('Location: members.php?member_id=' . $member_id . '&updated_due=1');
    exit;
}

$members = $pdo->query("
    SELECT u.id, u.name, u.id_number, u.status,
        SUM(CASE WHEN md.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN md.status = 'partial' THEN 1 ELSE 0 END) as partial_count,
        SUM(CASE WHEN md.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN md.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
        COUNT(md.id) as total_dues,
        SUM(COALESCE(md.custom_amount, d.amount)) as total_amount,
        SUM(md.total_paid) as total_paid_sum,
        SUM(COALESCE(md.custom_amount, d.amount)) - SUM(md.total_paid) as remaining_balance
    FROM users u
    LEFT JOIN member_dues md ON md.user_id = u.id
    LEFT JOIN dues d ON md.due_id = d.id
    WHERE u.role = 'member'
    GROUP BY u.id
    ORDER BY u.name ASC
")->fetchAll();

$selected_member = null;
$selected_member_dues = [];
$editing_member_due = null;

if ($selected_member_id) {
    $member_stmt = $pdo->prepare("SELECT id, name, id_number, status FROM users WHERE id = ? AND role = 'member'");
    $member_stmt->execute([$selected_member_id]);
    $selected_member = $member_stmt->fetch();

    if ($selected_member) {
        $dues_stmt = $pdo->prepare("
            SELECT md.id, md.user_id, md.status, md.total_paid, md.payment_type, md.installment_months,
                   d.id as due_id, d.title as base_title, d.description as base_description, d.amount as base_amount, d.due_date as base_due_date, d.term as base_term,
                   md.custom_title, md.custom_description, md.custom_amount, md.custom_due_date, md.custom_term,
                   COALESCE(md.custom_title, d.title) AS title,
                   COALESCE(md.custom_description, d.description) AS description,
                   COALESCE(md.custom_amount, d.amount) AS amount,
                   COALESCE(md.custom_due_date, d.due_date) AS due_date,
                   COALESCE(md.custom_term, d.term) AS term
            FROM member_dues md
            JOIN dues d ON md.due_id = d.id
            WHERE md.user_id = ?
            ORDER BY COALESCE(md.custom_due_date, d.due_date) IS NULL, COALESCE(md.custom_due_date, d.due_date) ASC, d.title ASC
        ");
        $dues_stmt->execute([$selected_member_id]);
        $selected_member_dues = $dues_stmt->fetchAll();

        if ($editing_member_due_id) {
            foreach ($selected_member_dues as $due) {
                if ((int)$due['id'] === $editing_member_due_id) {
                    $editing_member_due = $due;
                    break;
                }
            }
        }
    }
}

$page_title = 'Members';
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h1>Members Overview</h1>
  <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Member account deleted.</div><?php endif; ?>
  <?php if (isset($_GET['updated_due'])): ?><div class="alert alert-success">The member's assigned due was updated.</div><?php endif; ?>
  <?php if (isset($_GET['deleted_due'])): ?><div class="alert alert-success">The member's assigned due was removed.</div><?php endif; ?>

  <div class="toolbar">
    <div class="search-box">
      <input type="text" id="memberSearch" placeholder="Search by name or PRC ID No..." oninput="filterTable()">
    </div>
    <div class="filter-box">
      <select id="statusFilter" onchange="filterTable()">
        <option value="all">All Members</option>
        <option value="fully-paid">Fully Paid</option>
        <option value="partially-paid">Partially Paid</option>
        <option value="pending-verification">Pending Verification</option>
        <option value="has-dues">Has Dues (Unpaid)</option>
        <option value="awaiting-approval">Awaiting Approval</option>
      </select>
    </div>
  </div>

  <div class="table-shell">
  <table id="membersTable">
    <thead>
    <tr><th>Name</th><th>PRC ID No.</th><th>Dues</th><th>Total Amount</th><th>Total Paid</th><th>Remaining Balance</th><th>Overall Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($members as $m):
      // Determine row status key for filtering
      if ($m['status'] === 'pending') {
          $row_status = 'awaiting-approval';
      } elseif ($m['total_dues'] > 0 && $m['paid_count'] == $m['total_dues']) {
          $row_status = 'fully-paid';
      } elseif ($m['partial_count'] > 0) {
          $row_status = 'partially-paid';
      } elseif ($m['pending_count'] > 0) {
          $row_status = 'pending-verification';
      } elseif ($m['unpaid_count'] > 0) {
          $row_status = 'has-dues';
      } else {
          $row_status = 'fully-paid';
      }
      $search_text = strtolower($m['name'] . ' ' . $m['id_number']);
    ?>
    <tr data-status="<?php echo $row_status; ?>" data-search="<?php echo htmlspecialchars($search_text); ?>">
      <td>
        <?php echo htmlspecialchars($m['name']); ?>
        <?php if ($m['status'] === 'pending'): ?> <span class="badge badge-pending">Awaiting Approval</span><?php endif; ?>
        <?php if ($m['status'] === 'rejected'): ?> <span class="badge badge-rejected">Rejected</span><?php endif; ?>
      </td>
      <td><?php echo htmlspecialchars($m['id_number']); ?></td>
      <td><?php echo $m['paid_count']; ?> paid / <?php echo $m['total_dues']; ?> total</td>
      <td>₱<?php echo number_format($m['total_amount'] ?? 0, 2); ?></td>
      <td style="color:#1e7e34;font-weight:600;">₱<?php echo number_format($m['total_paid_sum'] ?? 0, 2); ?></td>
      <td style="color:<?php echo ($m['remaining_balance'] ?? 0) > 0 ? '#b3261e' : '#1e7e34'; ?>;font-weight:600;">
        ₱<?php echo number_format($m['remaining_balance'] ?? 0, 2); ?>
      </td>
      <td>
        <?php if ($m['total_dues'] > 0 && $m['paid_count'] == $m['total_dues']): ?>
          <span class="badge badge-paid">Fully Paid</span>
        <?php elseif ($m['partial_count'] > 0): ?>
          <span class="badge badge-pending">Partially Paid</span>
        <?php elseif ($m['pending_count'] > 0): ?>
          <span class="badge badge-pending">Pending Verification</span>
        <?php elseif ($m['unpaid_count'] > 0): ?>
          <span class="badge badge-unpaid">Has Dues</span>
        <?php else: ?>
          <span class="muted">—</span>
        <?php endif; ?>
      </td>
      <td>
        <a class="btn btn-sm" href="members.php?member_id=<?php echo $m['id']; ?>#member-dues-panel">Manage Dues</a>
        <a class="btn btn-sm" href="account_manager.php?search=<?php echo urlencode($m['id_number']); ?>">Edit</a>
        <form method="post" class="inline" onsubmit="return confirm('Delete <?php echo htmlspecialchars(addslashes($m['name'])); ?>? This cannot be undone.');">
          <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
          <input type="hidden" name="action" value="delete_member">
          <button class="btn btn-sm btn-danger" type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($selected_member): ?>
<div class="card" id="member-dues-panel" style="margin-top:20px;">
  <h2>Assigned Dues for <?php echo htmlspecialchars($selected_member['name']); ?></h2>
  <p class="muted">These are the dues assigned specifically to this member. Editing or deleting here affects only this member's assignment.</p>

  <?php if ($editing_member_due): ?>
  <form method="post" style="margin-bottom:16px;">
    <input type="hidden" name="action" value="update_member_due">
    <input type="hidden" name="member_due_id" value="<?php echo $editing_member_due['id']; ?>">
    <input type="hidden" name="member_id" value="<?php echo $selected_member['id']; ?>">
    <div class="grid-2">
      <div class="field"><label>Due Title</label><input name="title" required value="<?php echo htmlspecialchars($editing_member_due['title'] ?? ''); ?>"></div>
      <div class="field"><label>Description</label><input name="description" value="<?php echo htmlspecialchars($editing_member_due['description'] ?? ''); ?>"></div>
      <div class="field"><label>Amount (₱)</label><input type="number" step="0.01" name="amount" required value="<?php echo htmlspecialchars($editing_member_due['amount'] ?? ''); ?>"></div>
      <div class="field"><label>Due Date</label><input type="date" name="due_date" value="<?php echo htmlspecialchars($editing_member_due['due_date'] ?? ''); ?>"></div>
      <div class="field"><label>Term</label><input name="term" value="<?php echo htmlspecialchars($editing_member_due['term'] ?? ''); ?>"></div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">
      <button class="btn" type="submit">Save Changes</button>
      <a class="btn" href="members.php?member_id=<?php echo $selected_member['id']; ?>" style="background:#6b7280;">Cancel</a>
    </div>
  </form>
  <?php endif; ?>

  <?php if (empty($selected_member_dues)): ?>
    <p class="muted">No dues have been assigned to this member yet.</p>
  <?php else: ?>
  <table>
    <tr><th>Due</th><th>Description</th><th>Amount</th><th>Due Date</th><th>Term</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($selected_member_dues as $due): ?>
    <tr>
      <td><?php echo htmlspecialchars($due['title']); ?></td>
      <td><?php echo htmlspecialchars($due['description'] ?? '—'); ?></td>
      <td>₱<?php echo number_format($due['amount'], 2); ?></td>
      <td><?php echo htmlspecialchars($due['due_date'] ?? '—'); ?></td>
      <td><?php echo htmlspecialchars($due['term'] ?? '—'); ?></td>
      <td>
        <?php
          $badge = $due['status'];
          $label = match($due['status']) {
            'unpaid' => 'Unpaid',
            'pending' => 'Pending Verification',
            'partial' => 'Partially Paid',
            'paid' => 'Fully Paid',
            'rejected' => 'Rejected',
            default => ucfirst($due['status'])
          };
        ?>
        <span class="badge badge-<?php echo $badge === 'partial' ? 'pending' : $badge; ?>"><?php echo $label; ?></span>
      </td>
      <td>
        <a class="btn btn-sm" href="members.php?member_id=<?php echo $selected_member['id']; ?>&edit_member_due=<?php echo $due['id']; ?>">Edit</a>
        <form method="post" class="inline" onsubmit="return confirm('Remove this due from <?php echo htmlspecialchars(addslashes($selected_member['name'])); ?>?');">
          <input type="hidden" name="action" value="delete_member_due">
          <input type="hidden" name="member_due_id" value="<?php echo $due['id']; ?>">
          <input type="hidden" name="member_id" value="<?php echo $selected_member['id']; ?>">
          <button class="btn btn-sm btn-danger" type="submit">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>
<script>
function filterTable() {
  const search = document.getElementById('memberSearch').value.toLowerCase();
  const status = document.getElementById('statusFilter').value;
  const rows = document.querySelectorAll('#membersTable tbody tr');
  let visible = 0;

  rows.forEach(row => {
    const matchSearch = !search || row.dataset.search.includes(search);
    const matchStatus = status === 'all' || row.dataset.status === status;
    row.style.display = (matchSearch && matchStatus) ? '' : 'none';
    if (matchSearch && matchStatus) visible++;
  });

  // Show no-results message
  let noResults = document.getElementById('noResults');
  if (!noResults) {
    noResults = document.createElement('p');
    noResults.id = 'noResults';
    noResults.className = 'muted';
    noResults.textContent = 'No members match your search/filter.';
    document.getElementById('membersTable').after(noResults);
  }
  noResults.style.display = visible === 0 ? 'block' : 'none';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>