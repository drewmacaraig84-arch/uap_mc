<?php
// Bridge to the shared uap_mc DB connection
// This file is in website/api/ which is 2 levels up from uap_mc root
$uapRoot = __DIR__ . '/../../includes/config.php';
if (!file_exists($uapRoot)) {
    http_response_code(500);
    echo json_encode(['error' => 'Config not found']);
    exit;
}
require_once $uapRoot;

// CORS headers for Vite dev server
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['http://localhost:5173', 'http://127.0.0.1:5173'];
if (in_array($origin, $allowed)) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
