<?php
/**
 * Flash Message Helper
 */

function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function get_flash_messages() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function display_flash() {
    $messages = get_flash_messages();
    if (empty($messages)) {
        return;
    }
    
    foreach ($messages as $msg) {
        $alertClass = 'alert-info';
        if ($msg['type'] === 'success') $alertClass = 'alert-success';
        if ($msg['type'] === 'error' || $msg['type'] === 'danger') $alertClass = 'alert-error';
        if ($msg['type'] === 'warning') $alertClass = 'alert-warning';
        
        echo '<div class="alert ' . htmlspecialchars($alertClass, ENT_QUOTES, 'UTF-8') . '" style="margin-bottom: 16px;">';
        echo htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8');
        echo '</div>';
    }
}
