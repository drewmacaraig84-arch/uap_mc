<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS directory_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        status ENUM('pending_fee', 'fee_set', 'paid', 'rejected') DEFAULT 'pending_fee',
        fee_amount DECIMAL(10,2) NULL,
        member_due_id INT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_dir_app_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "✓ directory_applications table created successfully.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
