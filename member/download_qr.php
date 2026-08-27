<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/qr_helper.php';
require_member();

$userId = current_user_id();

// Find website directory profile for current member
$stmt = $pdo->prepare("SELECT * FROM website_members WHERE user_id = ?");
$stmt->execute([$userId]);
$wm = $stmt->fetch();

if (!$wm) {
    die("No directory profile found for your account.");
}

// Generate or fetch QR code relative path
$qrPath = generate_member_directory_qr($pdo, (int)$wm['id']);
if (!$qrPath) {
    die("Unable to generate QR code at this time.");
}

$fullPath = __DIR__ . '/../' . ltrim(str_replace('\\', '/', $qrPath), '/');

if (!file_exists($fullPath)) {
    die("QR Code file not found on server.");
}

$safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $wm['name'] ?: 'Member');
$downloadFilename = "UAP-Mindoro-QR-{$safeName}.png";

header('Content-Description: File Transfer');
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));

readfile($fullPath);
exit;
