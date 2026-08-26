<?php
require_once __DIR__ . '/config.php';
try {
    $toUrl = function($path) {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        return '/' . ltrim($path, '/');
    };

    $members = $pdo->query("SELECT wm.id, wm.name, wm.id_number, wm.role_title, wm.specialty, wm.location, wm.achievements, wm.awards, COALESCE(wm.photo_path, u.profile_photo) as photo_path 
                           FROM website_members wm 
                           LEFT JOIN users u ON wm.user_id = u.id 
                           WHERE wm.is_published = 1 
                           ORDER BY wm.name ASC")->fetchAll();
    foreach ($members as &$m) {
        $m['photo_url'] = $toUrl($m['photo_path']);
        unset($m['photo_path']);
    }
    echo json_encode($members);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
