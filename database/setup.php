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

// Explicit check for good_standing columns across all MySQL versions
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'good_standing_override'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN good_standing_override ENUM('auto', 'revoked', 'granted') NOT NULL DEFAULT 'auto' AFTER status");
        echo "Added column 'good_standing_override' to 'users' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'good_standing_reason'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN good_standing_reason VARCHAR(255) NULL AFTER good_standing_override");
        echo "Added column 'good_standing_reason' to 'users' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'good_standing_updated_at'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN good_standing_updated_at TIMESTAMP NULL AFTER good_standing_reason");
        echo "Added column 'good_standing_updated_at' to 'users' table.\n";
    }
} catch (Throwable $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

// Explicit check for website_members company_name and link columns
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'company_name'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN company_name VARCHAR(255) NULL AFTER location");
        echo "Added column 'company_name' to 'website_members' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'link_url'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN link_url VARCHAR(500) NULL AFTER company_name");
        echo "Added column 'link_url' to 'website_members' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'link_type'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN link_type VARCHAR(50) NULL DEFAULT 'auto' AFTER link_url");
        echo "Added column 'link_type' to 'website_members' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM website_members LIKE 'projects_json'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE website_members ADD COLUMN projects_json LONGTEXT NULL AFTER gallery_json");
        echo "Added column 'projects_json' to 'website_members' table.\n";
    }
} catch (Throwable $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

// Explicit check for sponsors is_platinum and products_json columns
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM sponsors LIKE 'is_platinum'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE sponsors ADD COLUMN is_platinum TINYINT(1) NOT NULL DEFAULT 0 AFTER url");
        echo "Added column 'is_platinum' to 'sponsors' table.\n";
    }
    $colCheck = $pdo->query("SHOW COLUMNS FROM sponsors LIKE 'products_json'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE sponsors ADD COLUMN products_json LONGTEXT NULL AFTER is_platinum");
        echo "Added column 'products_json' to 'sponsors' table.\n";
    }
} catch (Throwable $e) {
    echo "Notice: " . $e->getMessage() . "\n";
}

run_sql_files($pdo, __DIR__ . '/seeds', 'seed');

// ==============================================================================
// SELF-HEALING ASSET & UPLOADS SYNCHRONIZER (Hosted & Local Storage)
// ==============================================================================
try {
    $projectRoot = dirname(__DIR__);
    $uploadDirs = [
        $projectRoot . '/uploads',
        $projectRoot . '/uploads/qr_codes',
        $projectRoot . '/uploads/avatars',
        $projectRoot . '/uploads/members',
        $projectRoot . '/uploads/sponsors',
        $projectRoot . '/uploads/proofs',
        $projectRoot . '/uploads/receipts',
        $projectRoot . '/receipts',
        $projectRoot . '/public'
    ];

    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    // 1. Synchronize seed assets into uploads if mounted on a clean Railway / Docker volume
    $seedUploads = $projectRoot . '/seed_assets/uploads';
    if (is_dir($seedUploads)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($seedUploads, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $destPath = $projectRoot . '/uploads/' . $it->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($destPath)) @mkdir($destPath, 0775, true);
            } else {
                if (!file_exists($destPath)) {
                    @copy($item->getPathname(), $destPath);
                }
            }
        }
    }

    // 2. Cross-sync QR code files between uploads/ and uploads/qr_codes/
    $qrFiles = [
        'qr_bank_1783479751.jpeg',
        'qr_gcash_1783479743.jpeg',
        'qr_maya_1783479730.jpeg'
    ];

    foreach ($qrFiles as $qr) {
        $rootFile = $projectRoot . '/uploads/' . $qr;
        $subFile = $projectRoot . '/uploads/qr_codes/' . $qr;

        if (file_exists($subFile) && !file_exists($rootFile)) {
            @copy($subFile, $rootFile);
        } elseif (file_exists($rootFile) && !file_exists($subFile)) {
            @copy($rootFile, $subFile);
        }
    }

    // 3. Cross-sync Logo files between uploads/ and public/
    $logoFiles = ['logo.jpg', 'uap_logo.jpg'];
    foreach ($logoFiles as $logo) {
        $pubLogo = $projectRoot . '/public/' . $logo;
        $uplLogo = $projectRoot . '/uploads/' . $logo;

        if (file_exists($pubLogo) && !file_exists($uplLogo)) {
            @copy($pubLogo, $uplLogo);
        } elseif (file_exists($uplLogo) && !file_exists($pubLogo)) {
            @copy($uplLogo, $pubLogo);
        }
    }

    // 4. Verify & Heal QR Code database records
    $defaultQRs = [
        'gcash' => 'uploads/qr_gcash_1783479743.jpeg',
        'maya'  => 'uploads/qr_maya_1783479730.jpeg',
        'bank'  => 'uploads/qr_bank_1783479751.jpeg'
    ];

    foreach ($defaultQRs as $method => $defaultRelPath) {
        $stmt = $pdo->prepare("SELECT id, image_path FROM qr_codes WHERE method = ?");
        $stmt->execute([$method]);
        $row = $stmt->fetch();

        $validPath = null;
        if ($row && !empty($row['image_path'])) {
            $checkPath = $projectRoot . '/' . ltrim(str_replace('\\', '/', $row['image_path']), '/');
            if (file_exists($checkPath)) {
                $validPath = $row['image_path'];
            } else {
                // Check if file exists in alternative folder
                $base = basename($row['image_path']);
                if (file_exists($projectRoot . '/uploads/qr_codes/' . $base)) {
                    $validPath = 'uploads/qr_codes/' . $base;
                } elseif (file_exists($projectRoot . '/uploads/' . $base)) {
                    $validPath = 'uploads/' . $base;
                }
            }
        }

        if (!$validPath) {
            $validPath = $defaultRelPath;
        }

        $stmtUpsert = $pdo->prepare("
            INSERT INTO qr_codes (method, image_path, updated_at) VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)
        ");
        $stmtUpsert->execute([$method, $validPath]);
    }

    echo "Asset directories & default media verified.\n";
} catch (Throwable $e) {
    echo "Asset verification notice: " . $e->getMessage() . "\n";
}

echo "Project database setup complete.\n";
