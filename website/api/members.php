<?php
require_once __DIR__ . '/config.php';
try {
    $toUrl = function($path) {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        return '/' . ltrim($path, '/');
    };

    $members = $pdo->query("SELECT id, name, id_number, role_title, specialty, location, achievements, awards, photo_path FROM website_members WHERE is_published = 1 ORDER BY name ASC")->fetchAll();
    foreach ($members as &$m) {
        $m['photo_url'] = $toUrl($m['photo_path']);
        unset($m['photo_path']);
    }
    echo json_encode($members);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
