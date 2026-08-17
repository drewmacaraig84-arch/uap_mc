<?php
require_once __DIR__ . '/config.php';

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
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

function get_site_logo($pdo) {
    static $cached = null;
    if ($cached !== null) return $cached ?: null;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'logo'");
        $stmt->execute();
        $row = $stmt->fetch();
        $cached = $row ? $row['setting_value'] : '';
        return $cached ?: null;
    } catch (Exception $e) {
        return null;
    }
}
