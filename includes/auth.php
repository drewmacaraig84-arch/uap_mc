<?php
require_once __DIR__ . '/config.php';

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
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


function get_site_logo($pdo) {
    static $cached = null;
    if ($cached !== null) return $cached ?: null;

    $cacheKey = 'site_setting:logo';
    $cached = cache_get($cacheKey);
    if ($cached !== null) {
        return $cached ?: null;
    }

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'logo'");
        $stmt->execute();
        $row = $stmt->fetch();
        $cached = $row ? $row['setting_value'] : '';
        if ($cached !== '') {
            cache_set($cacheKey, $cached);
        }
        return $cached ?: null;
    } catch (Exception $e) {
        return null;
    }
}
