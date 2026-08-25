<?php
require_once __DIR__ . '/../includes/config.php';

try {
    // Check if photo_path column exists
    $cols = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'photo_path'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN photo_path VARCHAR(255) NULL AFTER qr_image_path");
        echo "✓ Added photo_path column.\n";
    } else {
        echo "- photo_path column already exists.\n";
    }

    // Check if photo_description column exists
    $cols2 = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'photo_description'")->fetchAll();
    if (empty($cols2)) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN photo_description TEXT NULL AFTER photo_path");
        echo "✓ Added photo_description column.\n";
    } else {
        echo "- photo_description column already exists.\n";
    }

    echo "Migration completed successfully.\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
