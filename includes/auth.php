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

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM member_dues WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $totalDues = (int) $countStmt->fetchColumn();

    if ($totalDues === 0) {
        return true;
    }

    $overdueStmt = $pdo->prepare("SELECT COUNT(*)
        FROM member_dues md
        JOIN dues d ON d.id = md.due_id
        WHERE md.user_id = ?
          AND COALESCE(md.custom_due_date, d.due_date) < CURDATE()
          AND md.total_paid < COALESCE(md.custom_amount, d.amount)");
    $overdueStmt->execute([$userId]);
    if ((int) $overdueStmt->fetchColumn() > 0) {
        return false;
    }

    $unpaidStmt = $pdo->prepare("SELECT COUNT(*)
        FROM member_dues md
        JOIN dues d ON d.id = md.due_id
        WHERE md.user_id = ?
          AND md.total_paid < COALESCE(md.custom_amount, d.amount)");
    $unpaidStmt->execute([$userId]);
    return ((int) $unpaidStmt->fetchColumn()) === 0;
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
