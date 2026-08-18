<?php
require_once __DIR__ . '/../includes/config.php';

function run_sql_files(PDO $pdo, string $folder, string $label): void {
    $files = glob($folder . '/*.sql');
    if ($files === false || empty($files)) {
        echo "No {$label} files found in {$folder}.\n";
        return;
    }

    sort($files, SORT_NATURAL);

    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            echo "Could not read {$label}: " . basename($file) . "\n";
            exit(1);
        }

        try {
            $pdo->exec($sql);
            echo "Ran {$label}: " . basename($file) . "\n";
        } catch (PDOException $e) {
            echo "{$label} failed: " . basename($file) . "\n";
            echo $e->getMessage() . "\n";
            exit(1);
        }
    }
}

run_sql_files($pdo, __DIR__ . '/migrations', 'migration');
run_sql_files($pdo, __DIR__ . '/seeds', 'seed');

echo "Project database setup complete.\n";
