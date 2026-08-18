<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $sql = file_get_contents(__DIR__ . '/migrations/002_add_website_content_tables.sql');
    $pdo->exec($sql);
    echo "✓ Migration completed successfully!\n";
    echo "- sponsors table created\n";
    echo "- news_announcements table created\n";
    echo "- Default about_us content inserted\n";
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
}
?>
