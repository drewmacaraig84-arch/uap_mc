<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== ENVIRONMENT DETECTION ===" . PHP_EOL;
echo "IS_HOSTED: " . (is_hosted() ? 'TRUE (Production / Docker / Cloud)' : 'FALSE (Local / Laragon)') . PHP_EOL;
echo "IS_LOCAL: " . (is_local() ? 'TRUE' : 'FALSE') . PHP_EOL;
echo "BASE_URL: " . (BASE_URL !== '' ? BASE_URL : '(root)') . PHP_EOL;
echo PHP_EOL;

echo "=== MEDIA RESOLUTION TESTS ===" . PHP_EOL;
$tests = [
    'QR Maya'     => 'uploads/qr_maya_1783479730.jpeg',
    'QR GCash'    => 'uploads/qr_gcash_1783479743.jpeg',
    'QR Bank'     => 'uploads/qr_bank_1783479751.jpeg',
    'QR Subfolder'=> 'uploads/qr_codes/qr_gcash_1783479743.jpeg',
    'Site Logo'   => 'public/logo.jpg',
    'Upload Logo' => 'uploads/logo.jpg',
    'Avatar 1'    => 'uploads/avatars/avatar_admin_1_1787725019_7bf63f.png',
    'Avatar 109'  => 'uploads/avatars/avatar_member_109_1787829305_01f313.jpg',
    'Proof 1'     => 'uploads/proof_1_1787671120_72772d1b.png',
    'Sponsor'     => 'uploads/sponsor_1787829158_de2e35.jpg'
];

$allPass = true;
foreach ($tests as $name => $path) {
    $url = media_url($path);
    $fsPath = resolve_media_filesystem_path($path);
    $exists = $fsPath && file_exists($fsPath);
    echo sprintf("[ %s ] %-15s => URL: %-50s | File on disk: %s\n", $exists ? 'PASS' : 'FAIL', $name, $url, $fsPath ?: 'NOT FOUND');
    if (!$exists) $allPass = false;
}

echo PHP_EOL;
echo "=== DATABASE QR_CODES TABLE ===" . PHP_EOL;
$qrs = $pdo->query("SELECT method, image_path FROM qr_codes")->fetchAll();
foreach ($qrs as $qr) {
    $url = media_url($qr['image_path']);
    $fsPath = resolve_media_filesystem_path($qr['image_path']);
    $exists = $fsPath && file_exists($fsPath);
    echo sprintf("[ %s ] Method: %-8s => DB Path: %-35s => URL: %s\n", $exists ? 'PASS' : 'FAIL', $qr['method'], $qr['image_path'], $url);
    if (!$exists) $allPass = false;
}

echo PHP_EOL;
echo "Overall Status: " . ($allPass ? "ALL ASSETS VERIFIED PERFECTLY!" : "SOME ASSETS MISSING") . PHP_EOL;
