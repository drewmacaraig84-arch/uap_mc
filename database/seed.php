<?php
require_once __DIR__ . '/../includes/config.php';

$seedDir = __DIR__ . '/seeds';
$files = glob($seedDir . '/*.sql');

if ($files === false || empty($files)) {
    echo "No seed files found in {$seedDir}.\n";
    exit(1);
}

sort($files, SORT_NATURAL);

foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "Could not read seed: " . basename($file) . "\n";
        exit(1);
    }

    try {
        $pdo->exec($sql);
        echo "Ran seed: " . basename($file) . "\n";
    } catch (PDOException $e) {
        echo "Seed failed: " . basename($file) . "\n";
        echo $e->getMessage() . "\n";
        exit(1);
    }
}

echo "All seeds completed successfully.\n";
