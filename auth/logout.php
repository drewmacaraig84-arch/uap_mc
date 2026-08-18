<?php
require_once __DIR__ . '/../includes/config.php';

// Properly destroy the session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Clear session data
$_SESSION = [];

// Redirect to login
header('Location: ' . BASE_URL . '/auth/login.php', true, 302);
exit;
