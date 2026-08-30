<?php
/**
 * API: /api/home_images.php
 * Returns a JSON array of URLs for images in uploads/home_images/
 * Images are stored on the Railway volume so they persist across deploys.
 */
require_once __DIR__ . '/config.php';

$dir = __DIR__ . '/../../uploads/home_images/';
$baseUrl = '/uploads/home_images/';
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

$images = [];
if (is_dir($dir)) {
    $files = scandir($dir);
    sort($files);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $images[] = $baseUrl . $file;
        }
    }
}

header('Content-Type: application/json');
echo json_encode($images);
