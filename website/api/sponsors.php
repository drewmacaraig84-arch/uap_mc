<?php
require_once __DIR__ . '/config.php';
try {
    // Paths stored as 'uploads/xxx.jpg' — prepend / for root-relative URL
    // Works for Vite dev proxy (/uploads → Apache) and Railway production (app at root)
    $toUrl = function($path) {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        return '/' . ltrim($path, '/');
    };

    $sponsors = $pdo->query("SELECT id, name, logo_path, description, url FROM sponsors WHERE is_active = 1 ORDER BY display_order ASC, id ASC")->fetchAll();
    foreach ($sponsors as &$s) {
        $s['logo_url'] = $toUrl($s['logo_path']);
    }
    echo json_encode($sponsors);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
