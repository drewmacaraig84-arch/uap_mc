<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'publish_member') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0 && is_good_member($pdo, $userId)) {
            $member = $pdo->prepare("SELECT name, id_number FROM users WHERE id = ? AND role = 'member'");
            $member->execute([$userId]);
            $user = $member->fetch();

            if ($user) {
                $existing = $pdo->prepare("SELECT id FROM website_members WHERE user_id = ?");
                $existing->execute([$userId]);
                $row = $existing->fetch();

                if ($row) {
                    $stmt = $pdo->prepare("UPDATE website_members SET is_published = 1, name = ?, id_number = ? WHERE user_id = ?");
                    $stmt->execute([$user['name'], $user['id_number'], $userId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO website_members (user_id, name, id_number, role_title, specialty, location, achievements, awards, is_published) VALUES (?, ?, ?, '', '', '', '', '', 1)");
                    $stmt->execute([$userId, $user['name'], $user['id_number']]);
                }

                if (function_exists('set_flash')) {
                    set_flash('success', 'Member added to website directory.');
                }
            }
        }
    }

    if ($action === 'save_member_profile') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $idNumber = trim($_POST['id_number'] ?? '');
        $role = trim($_POST['role_title'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $achievements = trim($_POST['achievements'] ?? '');
        $awards = trim($_POST['awards'] ?? '');

        if ($userId > 0 && $name !== '' && $idNumber !== '' && is_good_member($pdo, $userId)) {
            $stmt = $pdo->prepare("INSERT INTO website_members (user_id, name, id_number, role_title, specialty, location, achievements, awards, qr_image_path, is_published)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    id_number = VALUES(id_number),
                    role_title = VALUES(role_title),
                    specialty = VALUES(specialty),
                    location = VALUES(location),
                    achievements = VALUES(achievements),
                    awards = VALUES(awards),
                    qr_image_path = VALUES(qr_image_path),
                    is_published = 1");
            $stmt->execute([$userId, $name, $idNumber, $role, $specialty, $location, $achievements, $awards, null]);

            if (function_exists('set_flash')) {
                set_flash('success', 'Member profile updated.');
            }
        }
    }
}

$allApprovedMembers = $pdo->query("SELECT u.id, u.name, u.id_number
    FROM users u
    WHERE u.role = 'member' AND u.status = 'approved'
    ORDER BY u.name ASC")->fetchAll();

$goodMembers = [];
foreach ($allApprovedMembers as $m) {
    if (is_good_member($pdo, $m['id'])) {
        $goodMembers[] = $m;
    }
}

$publishedMembers = $pdo->query("SELECT * FROM website_members WHERE is_published = 1 ORDER BY name ASC")->fetchAll();


$page_title = 'Website Directory';
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h1>Chapter Members Directory For Website</h1>
  <p class="muted">Only members listed in the Good Members feature can be added to the public website directory.</p>

  <div class="grid-2" style="margin-top: 20px;">
    <section style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 18px;">
      <h2 style="margin-top:0;">Available Good Members</h2>
      <?php foreach ($goodMembers as $member): ?>
        <?php $isListed = false; foreach ($publishedMembers as $published) { if ((int)$published['user_id'] === (int)$member['id']) { $isListed = true; break; } } ?>
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; padding: 10px 12px; border-radius:8px; background: rgba(0,0,0,0.12);">
          <div>
            <strong><?php echo htmlspecialchars($member['name']); ?></strong><br>
            <span class="muted"><?php echo htmlspecialchars($member['id_number']); ?></span>
          </div>
          <?php if (!$isListed): ?>
            <form method="post">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="publish_member">
              <input type="hidden" name="user_id" value="<?php echo (int)$member['id']; ?>">
              <button class="btn btn-sm" type="submit">Add to Website</button>
            </form>
          <?php else: ?>
            <span class="badge badge-paid">Published</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </section>


    <section style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 18px;">
      <h2 style="margin-top:0;">Website Members</h2>
      <?php if (empty($publishedMembers)): ?>
        <p class="muted">No member has been added yet.</p>
      <?php else: ?>
        <?php foreach ($publishedMembers as $member): ?>
          <div style="padding:10px 12px; margin-bottom:10px; border-radius:8px; background: rgba(0,0,0,0.12);">
            <strong><?php echo htmlspecialchars($member['name']); ?></strong><br>
            <span class="muted"><?php echo htmlspecialchars($member['role_title'] ?: 'Member'); ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
