<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    require_csrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $term = trim($_POST['term'] ?? '');
    $assign_type = $_POST['assign_type'] ?? 'all';
    $specific_members = $_POST['specific_members'] ?? [];

    if ($title === '' || $amount <= 0) {
        if (function_exists('set_flash')) {
            set_flash('error', 'Please provide a valid title and amount.');
        }
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO dues (title, description, amount, due_date, term) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $amount, $due_date, $term]);
            $due_id = $pdo->lastInsertId();

            $ins = $pdo->prepare("INSERT IGNORE INTO member_dues (user_id, due_id, status) VALUES (?, ?, 'unpaid')");

            if ($assign_type === 'all') {
                $members = $pdo->query("SELECT id FROM users WHERE role = 'member' AND status = 'approved'")->fetchAll();
                foreach ($members as $m) {
                    $ins->execute([$m['id'], $due_id]);
                }
            } else {
                foreach ($specific_members as $uid) {
                    $ins->execute([(int)$uid, $due_id]);
                }
            }

            $pdo->commit();
            if (function_exists('set_flash')) {
                set_flash('success', 'Due created and assigned successfully.');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (function_exists('set_flash')) {
                set_flash('error', 'Failed to create due.');
            }
        }
    }

    header('Location: dues.php');
    exit;
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    require_csrf();
    $due_id = (int)$_POST['due_id'];
    $stmt = $pdo->prepare("UPDATE dues SET title=?, description=?, amount=?, due_date=?, term=? WHERE id=?");
    $stmt->execute([trim($_POST['title']), trim($_POST['description']), (float)$_POST['amount'], !empty($_POST['due_date']) ? $_POST['due_date'] : null, trim($_POST['term']), $due_id]);
    
    if (function_exists('set_flash')) {
        set_flash('success', 'Due item updated.');
    }
    header('Location: dues.php');
    exit;
}

// Handle delete — manual cascade with transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_csrf();
    $due_id = (int)$_POST['due_id'];

    try {
        $pdo->beginTransaction();

        // Get all member_due IDs for this due
        $md_ids = $pdo->prepare("SELECT id FROM member_dues WHERE due_id = ?");
        $md_ids->execute([$due_id]);
        $md_rows = $md_ids->fetchAll(PDO::FETCH_COLUMN);

        if ($md_rows) {
            // Delete receipts → payments → member_dues manually
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
        $pdo->prepare("DELETE FROM dues WHERE id=?")->execute([$due_id]);
        $pdo->commit();
        if (function_exists('set_flash')) {
            set_flash('success', 'Due deleted and removed from assigned members.');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (function_exists('set_flash')) {
            set_flash('error', 'Failed to delete due.');
        }
    }

    header('Location: dues.php');
    exit;
}

// Handle assign past due to specific member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign') {
    require_csrf();
    $due_id = (int)$_POST['due_id'];
    $user_id = (int)$_POST['user_id'];
    $pdo->prepare("INSERT IGNORE INTO member_dues (user_id, due_id, status) VALUES (?, ?, 'unpaid')")->execute([$user_id, $due_id]);
    
    if (function_exists('set_flash')) {
        set_flash('success', 'Due assigned to member.');
    }
    header('Location: dues.php');
    exit;
}

// Load due for editing
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM dues WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}

$dues = $pdo->query("SELECT * FROM dues ORDER BY created_at DESC")->fetchAll();
$all_members = $pdo->query("SELECT id, name, id_number FROM users WHERE role = 'member' AND status = 'approved' ORDER BY name ASC")->fetchAll();

$page_title = 'Manage Dues';
include __DIR__ . '/../includes/header.php';
?>
<style>
.member-search-box { 
  border: 1px solid var(--border-color, rgba(255,255,255,0.12)); 
  border-radius: 8px; 
  padding: 12px; 
  background: var(--bg-secondary, rgba(0,0,0,0.15)); 
  color: var(--text-primary);
  margin-bottom: 12px;
}
.member-results {
  max-height: 220px;
  overflow-y: auto;
  margin-top: 8px;
  border: 1px solid var(--border-color, rgba(255,255,255,0.08));
  border-radius: 6px;
  background: var(--field-bg, rgba(0,0,0,0.2));
}
.member-row {
  display: grid;
  grid-template-columns: 36px 1fr;
  align-items: center;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.06));
  cursor: pointer;
  transition: background 0.15s ease;
  color: var(--text-primary);
}
.member-row:last-child {
  border-bottom: none;
}
.member-row:hover {
  background: var(--hover-row-bg, rgba(255,255,255,0.05));
}
.member-row input[type="checkbox"] {
  justify-self: center;
  cursor: pointer;
  accent-color: var(--accent-primary);
}
.member-info {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}
.member-name {
  font-weight: 600;
  font-size: 13px;
  color: var(--text-primary);
}
.member-id {
  color: var(--text-secondary);
  font-size: 12px;
}
.selected-members { 
  margin-top: 10px; 
}
.selected-tag { 
  display: inline-block; 
  background: var(--accent-primary, #f5b800); 
  color: #000; 
  font-weight: 600;
  border-radius: 20px; 
  padding: 3px 10px; 
  font-size: 12px; 
  margin: 3px; 
}
</style>

<?php if (isset($_GET['created'])): ?><div class="alert alert-success">Due created and assigned successfully.</div><?php endif; ?>
<?php if (isset($_GET['updated'])): ?><div class="alert alert-success">Due updated.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Due deleted and removed from all members.</div><?php endif; ?>
<?php if (isset($_GET['assigned'])): ?><div class="alert alert-success">Due assigned to member.</div><?php endif; ?>

<div class="grid-2">
  <!-- Create / Edit form -->
  <div class="card">
    <?php if ($editing): ?>
      <h1>Edit Due</h1>
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="due_id" value="<?php echo $editing['id']; ?>">
        <div class="field"><label>Title</label><input name="title" required value="<?php echo htmlspecialchars($editing['title']); ?>"></div>
        <div class="field"><label>Description</label><input name="description" value="<?php echo htmlspecialchars($editing['description']); ?>"></div>
        <div class="field"><label>Amount (₱)</label><input type="number" step="0.01" name="amount" required value="<?php echo $editing['amount']; ?>"></div>
        <div class="field"><label>Due Date</label><input type="date" name="due_date" value="<?php echo htmlspecialchars($editing['due_date'] ?? ''); ?>"></div>
        <div class="field"><label>Term</label><input name="term" value="<?php echo htmlspecialchars($editing['term']); ?>"></div>
        <button class="btn" type="submit" style="width:100%;margin-bottom:8px;">Save Changes</button>
        <a href="dues.php" class="btn" style="width:100%;background:#6b7280;display:block;text-align:center;">Cancel</a>
      </form>
    <?php else: ?>
      <h1>Create New Due</h1>
      <form method="post" id="createForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="field"><label>Title</label><input name="title" required placeholder="e.g. Annual Membership Fee"></div>
        <div class="field"><label>Description</label><input name="description"></div>
        <div class="field"><label>Amount (₱)</label><input type="number" step="0.01" name="amount" required></div>
        <div class="field"><label>Due Date</label><input type="date" name="due_date"></div>
        <div class="field"><label>Term</label><input name="term" placeholder="e.g. 2026-2027"></div>

        <div class="field">
          <label>Assign To</label>
          <select name="assign_type" id="assignType" onchange="toggleMemberSearch()">
            <option value="all">All Approved Members</option>
            <option value="specific">Specific Members Only</option>
          </select>
        </div>

        <div id="memberSearchBox" class="member-search-box" style="display:none;">
          <label style="font-weight:700;color:var(--text-primary);">Search Members</label>
          <input type="text" id="memberSearchInput" placeholder="Type name or PRC ID No..." oninput="filterMembers()" style="margin-top:6px;">
          <div class="member-results" id="memberResults">

            <?php foreach ($all_members as $m): ?>
           <label class="member-row">
    <input type="checkbox"
        name="specific_members[]"
        value="<?php echo $m['id']; ?>"
        onchange="updateSelected(this,
        '<?php echo htmlspecialchars(addslashes($m['name'])); ?>',
        '<?php echo htmlspecialchars($m['id_number']); ?>')">

    <div class="member-info">
        <div class="member-name">
            <?php echo htmlspecialchars($m['name']); ?>
        </div>
        <div class="member-id">
            (<?php echo htmlspecialchars($m['id_number']); ?>)
        </div>
    </div>
</label>
            <?php endforeach; ?>
          </div>
          <div class="selected-members">
            <p class="muted" style="margin-bottom:4px;">Selected members:</p>
            <div id="selectedTags"><span class="muted">None selected yet.</span></div>
          </div>
        </div>

        <button class="btn" type="submit" style="width:100%;margin-top:12px;">Create Due</button>
      </form>
    <?php endif; ?>
  </div>

  <!-- Assign past due to specific member -->
  <div class="card">
    <h2>Assign Past Due to a Member</h2>
    <p class="muted">Use this to add a specific old due to one member who wasn't originally assigned to it.</p>
    <?php if (empty($dues) || empty($all_members)): ?>
      <p class="muted">Need at least one due and one approved member.</p>
    <?php else: ?>
    <form method="post">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="assign">
      <div class="field">
        <label>Select Due</label>
        <select name="due_id" required>
          <option value="">Choose a due...</option>
          <?php foreach ($dues as $d): ?>
            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['title']); ?> — ₱<?php echo number_format($d['amount'], 2); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Select Member</label>
        <select name="user_id" required>
          <option value="">Choose a member...</option>
          <?php foreach ($all_members as $m): ?>
            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?> (<?php echo htmlspecialchars($m['id_number']); ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit" style="width:100%;">Assign Due</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- Existing dues table -->
<div class="card">
  <h2>Existing Dues</h2>
  <?php if (empty($dues)): ?>
    <p class="muted">No dues created yet.</p>
  <?php else: ?>
  <table>
    <tr><th>Title</th><th>Description</th><th>Amount</th><th>Due Date</th><th>Term</th><th>Actions</th></tr>
    <?php foreach ($dues as $d): ?>
    <tr>
      <td><?php echo htmlspecialchars($d['title']); ?></td>
      <td><?php echo htmlspecialchars($d['description'] ?? '—'); ?></td>
      <td>₱<?php echo number_format($d['amount'], 2); ?></td>
      <td><?php echo htmlspecialchars($d['due_date'] ?? '—'); ?></td>
      <td><?php echo htmlspecialchars($d['term'] ?? '—'); ?></td>
      <td>
        <a class="btn btn-sm" href="dues.php?edit=<?php echo $d['id']; ?>">Edit</a>
        <form method="post" class="inline"
              data-confirm="Delete '<?php echo htmlspecialchars($d['title']); ?>'? This will permanently remove it from ALL members who have it assigned."
              data-confirm-title="Delete Due"
              data-confirm-btn="Delete Due"
              data-confirm-class="btn-danger"
              data-confirm-icon="🗑️">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="due_id" value="<?php echo $d['id']; ?>">
          <button class="btn btn-sm btn-danger" type="submit">Delete</button>
        </form>
      </td>

    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<script>
function toggleMemberSearch() {
  document.getElementById('memberSearchBox').style.display =
    document.getElementById('assignType').value === 'specific' ? 'block' : 'none';
}

function filterMembers() {
  const q = document.getElementById('memberSearchInput').value.toLowerCase();
  document.querySelectorAll('#memberResults label').forEach(label => {
    label.style.display = label.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

const selectedMap = {};
function updateSelected(checkbox, name, idNum) {
  if (checkbox.checked) {
    selectedMap[checkbox.value] = name + ' (' + idNum + ')';
  } else {
    delete selectedMap[checkbox.value];
  }
  const container = document.getElementById('selectedTags');
  const keys = Object.keys(selectedMap);
  if (keys.length === 0) {
    container.innerHTML = '<span class="muted">None selected yet.</span>';
  } else {
    container.innerHTML = keys.map(k => `<span class="selected-tag">${selectedMap[k]}</span>`).join('');
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
