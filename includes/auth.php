<?php
require_once __DIR__ . '/config.php';

function ensure_user_profile_photo_column($pdo) {
    static $checked = false;
    if ($checked || !$pdo) return;
    $checked = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER status");
        }
    } catch (Throwable $e) {}
}

function require_login() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    if (isset($pdo)) {
        ensure_user_profile_photo_column($pdo);
    }
}

function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . BASE_URL . '/member/dashboard.php');
        exit;
    }
}

function require_member() {
    require_login();
    if ($_SESSION['role'] !== 'member') {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function is_good_member($pdo, $userId) {
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }

    $userStmt = $pdo->prepare("SELECT status FROM users WHERE id = ? AND role = 'member'");
    $userStmt->execute([$userId]);
    $status = $userStmt->fetchColumn();
    if ($status !== 'approved') {
        return false;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM member_dues WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $totalDues = (int) $countStmt->fetchColumn();

    if ($totalDues === 0) {
        return true;
    }

    // A member loses good standing ONLY IF they have an overdue/expired unpaid due.
    // Dues expire if:
    // 1) The specified due date has passed: COALESCE(md.custom_due_date, d.due_date) < CURDATE()
    // 2) OR 7 days have passed since the due was created: DATE_ADD(COALESCE(d.created_at, CURDATE()), INTERVAL 7 DAY) < CURDATE()
    // While within the 7-day grace period (and before due date), the member retains good standing & directory access.
    $expiredUnpaidStmt = $pdo->prepare("SELECT COUNT(*)
        FROM member_dues md
        JOIN dues d ON d.id = md.due_id
        WHERE md.user_id = ?
          AND md.total_paid < COALESCE(md.custom_amount, d.amount)
          AND (
              (COALESCE(md.custom_due_date, d.due_date) IS NOT NULL AND COALESCE(md.custom_due_date, d.due_date) < CURDATE())
              OR DATE_ADD(COALESCE(d.created_at, CURDATE()), INTERVAL 7 DAY) < CURDATE()
          )");
    $expiredUnpaidStmt->execute([$userId]);
    $expiredCount = (int) $expiredUnpaidStmt->fetchColumn();

    return $expiredCount === 0;
}

function get_directory_application($pdo, $userId) {
    $userId = (int) $userId;
    if ($userId <= 0) return null;
    try {
        $stmt = $pdo->prepare("SELECT da.*, md.status as payment_status, md.total_paid, md.id as member_due_id_val,
                               COALESCE(md.custom_amount, d.amount) as due_amount
                               FROM directory_applications da
                               LEFT JOIN member_dues md ON da.member_due_id = md.id
                               LEFT JOIN dues d ON md.due_id = d.id
                               WHERE da.user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function has_unlocked_website_directory($pdo, $userId) {
    $app = get_directory_application($pdo, $userId);
    if (!$app) return false;
    // Unlocked if application status is paid, or linked member due is paid
    if ($app['status'] === 'paid' || ($app['payment_status'] ?? '') === 'paid') {
        return true;
    }
    return false;
}



function get_site_logo($pdo) {
    static $cached = null;
    if ($cached !== null) return $cached ?: 'public/logo.jpg';

    $cacheKey = 'site_setting:logo';
    if (function_exists('cache_get')) {
        $cached = cache_get($cacheKey);
        if ($cached !== null && $cached !== '') {
            return $cached;
        }
    }

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'logo'");
        $stmt->execute();
        $row = $stmt->fetch();
        $cached = ($row && !empty($row['setting_value'])) ? $row['setting_value'] : 'public/logo.jpg';
        if (function_exists('cache_set')) {
            cache_set($cacheKey, $cached);
        }
        return $cached;
    } catch (Exception $e) {
        return 'public/logo.jpg';
    }
}
