<?php
$configFile = __DIR__ . '/../includes/config.php';
$configExample = __DIR__ . '/../includes/config.example.php';
if (!file_exists($configFile) && file_exists($configExample)) {
    copy($configExample, $configFile);
}
require_once $configFile;

$migrationDir = __DIR__ . '/migrations';
$files = glob($migrationDir . '/*.sql');

if ($files === false || empty($files)) {
    echo "No migration files found in {$migrationDir}.\n";
    exit(1);
}

sort($files, SORT_NATURAL);

foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "Could not read migration: " . basename($file) . "\n";
        exit(1);
    }

    try {
        $pdo->exec($sql);
        echo "Ran migration: " . basename($file) . "\n";
    } catch (PDOException $e) {
        echo "Migration failed: " . basename($file) . "\n";
        echo $e->getMessage() . "\n";
        exit(1);
    }
}

echo "All migrations completed successfully.\n";
