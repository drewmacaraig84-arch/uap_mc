<?php
require_once __DIR__ . '/includes/config.php';
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . ($_SESSION['role'] === 'admin' ? '/admin/dashboard.php' : '/member/dashboard.php'));
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
