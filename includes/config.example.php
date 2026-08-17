<?php
// ===== EDIT THESE TO MATCH YOUR HOSTING/DATABASE =====
// LOCAL (XAMPP) — these are active right now for testing on your laptop:
define('DB_HOST', 'localhost');
define('DB_NAME', 'dues_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// WHEN YOU DEPLOY TO INFINITYFREE: comment out the 4 lines above (add // in
// front of each) and uncomment + fill in the 4 lines below instead.
// Get these exact values from: Control Panel > MySQL Databases
// DB_HOST is usually something like "sqlXXX.infinityfree.com" (NOT "localhost")
// DB_NAME and DB_USER are usually the same long string, e.g. "if0_12345678_dues_system"
//
// define('DB_HOST', 'PASTE_YOUR_INFINITYFREE_DB_HOST_HERE');     // e.g. sql123.infinityfree.com
// define('DB_NAME', 'PASTE_YOUR_INFINITYFREE_DB_NAME_HERE');     // e.g. if0_12345678_dues_system
// define('DB_USER', 'PASTE_YOUR_INFINITYFREE_DB_USER_HERE');     // e.g. if0_12345678
// define('DB_PASS', 'PASTE_YOUR_INFINITYFREE_DB_PASSWORD_HERE'); // the password YOU set when creating the database

// If automatic path detection below ever gives wrong links, uncomment the
// next line and set it manually instead (e.g. '/dues-system' or '' for root):
// define('BASE_URL_OVERRIDE', '/dues-system');
// =======================================================

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

session_start();

// Dynamically detect the base URL path so the app works whether it's
// installed at the web root (e.g. http://localhost/) or in a subfolder
// (e.g. http://localhost/dues-system/). No manual configuration needed.
// This works by comparing the filesystem location of the currently running
// script to the filesystem location of this project's root folder, then
// applying the same "how many folders deep" logic to the script's web path.
$scriptDir   = str_replace('\\', '/', dirname(realpath($_SERVER['SCRIPT_FILENAME'])));
$projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$relative    = ltrim(str_replace($projectRoot, '', $scriptDir), '/');
$depth       = ($relative === '') ? 0 : substr_count($relative, '/') + 1;

$baseUrl = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
for ($i = 0; $i < $depth; $i++) {
    $baseUrl = dirname($baseUrl);
}
if ($baseUrl === '/' || $baseUrl === '\\' || $baseUrl === '.') {
    $baseUrl = '';
}
define('BASE_URL', defined('BASE_URL_OVERRIDE') ? BASE_URL_OVERRIDE : $baseUrl);
