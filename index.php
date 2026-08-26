<?php
/**
 * UAP-MC Application Router
 * Directs visitors to the React website by default, or logged-in users to their portal dashboard.
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

// Serve the React Website index.html if it exists, or redirect to root /
if (file_exists(__DIR__ . '/index.html')) {
    include __DIR__ . '/index.html';
    exit;
}

// Fallback to website folder or login if index.html is not yet compiled
header('Location: ' . BASE_URL . '/');
exit;
