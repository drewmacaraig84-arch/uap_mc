<?php
require_once __DIR__ . '/config.php';
try {
    $toUrl = function($path) {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        return '/' . ltrim($path, '/');
    };

    try {
        $rows = $pdo->query("SELECT id, title, summary, image_path, date_posted, display_order FROM news_announcements WHERE is_active = 1 ORDER BY display_order ASC, id DESC LIMIT 50")->fetchAll();
    } catch (Throwable $qe) {
        $rows = $pdo->query("SELECT id, title, summary, NULL as image_path, date_posted, display_order FROM news_announcements WHERE is_active = 1 ORDER BY display_order ASC, id DESC LIMIT 50")->fetchAll();
    }

    $news = [];
    foreach ($rows as $item) {
        $item['image_url'] = $toUrl($item['image_path'] ?? null);
        $news[] = $item;
    }

    echo json_encode($news);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
