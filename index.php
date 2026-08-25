<?php
/**
 * UAP-MC Application Router
 * Directs users straight to the Admin / Member Management Portal
 */
require_once __DIR__ . '/includes/config.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    } else {
        header('Location: ' . BASE_URL . '/member/dashboard.php');
        exit;
    }
}

// Redirect unauthenticated visitors straight to the Login page
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
