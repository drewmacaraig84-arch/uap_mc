<?php
/**
 * API: /api/home_images.php
 * Returns a JSON array of URLs for images in uploads/home_images/
 * No DB required — just scans the filesystem.
 * Images persist on Railway volumes across deploys.
 */

// CORS for Vite dev server
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ['http://localhost:5173', 'http://127.0.0.1:5173'])) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204); exit;
}

$dir     = __DIR__ . '/../../uploads/home_images/';
$baseUrl = '/uploads/home_images/';
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

$images = [];
if (is_dir($dir)) {
    $files = scandir($dir);
    natsort($files);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $images[] = $baseUrl . rawurlencode($file);
        }
    }
}

echo json_encode($images);
