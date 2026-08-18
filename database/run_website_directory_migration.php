<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $sql = file_get_contents(__DIR__ . '/migrations/003_add_website_directory.sql');
    $pdo->exec($sql);
    echo "✓ website_members table created successfully.\n";
} catch (Throwable $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
