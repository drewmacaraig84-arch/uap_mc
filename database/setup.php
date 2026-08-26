<?php
// Define flag so config.php doesn't throw uncaught exception before our retry loop
define('CONFIG_IGNORE_DB_FAILURE', true);

$configFile = __DIR__ . '/../includes/config.php';
$configExample = __DIR__ . '/../includes/config.example.php';

if (!file_exists($configFile) && file_exists($configExample)) {
    echo "Notice: includes/config.php not found. Copying from config.example.php...\n";
    copy($configExample, $configFile);
}

if (!file_exists($configFile)) {
    echo "Error: Database configuration file not found at " . $configFile . "\n";
    exit(1);
}

require_once $configFile;

// Retry connecting to database if not ready yet (common during Docker/Railway startup)
$maxRetries = 10;
$retryDelay = 2; // seconds

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    if ($pdo !== null) {
        try {
            $pdo->query("SELECT 1");
            break; // Connection active and working
        } catch (Throwable $e) {
            $pdo = null;
        }
    }

    try {
        $dbPortStr = defined('DB_PORT') && DB_PORT ? ';port=' . DB_PORT : '';
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . $dbPortStr . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        echo "Database connected successfully.\n";
        break;
    } catch (PDOException $e) {
        echo "Database connection attempt {$attempt}/{$maxRetries} failed (" . $e->getMessage() . "). Retrying in {$retryDelay}s...\n";
        if ($attempt === $maxRetries) {
            echo "Error: Could not connect to database after {$maxRetries} attempts.\n";
            exit(1);
        }
        sleep($retryDelay);
    }
}

function run_sql_files(PDO $pdo, string $folder, string $label): void {
    if (!is_dir($folder)) {
        echo "Directory {$folder} does not exist. Skipping {$label}s.\n";
        return;
    }

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

// Explicit check for users.profile_photo column across all MySQL versions
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_photo'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER status");
        echo "Added column 'profile_photo' to 'users' table.\n";
    }
} catch (Throwable $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

run_sql_files($pdo, __DIR__ . '/seeds', 'seed');

echo "Project database setup complete.\n";
