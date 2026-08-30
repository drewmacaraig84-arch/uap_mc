<?php
require_once __DIR__ . '/config.php';
try {
    $news = $pdo->query("SELECT id, title, summary, date_posted FROM news_announcements WHERE is_active = 1 ORDER BY display_order ASC, id DESC LIMIT 50")->fetchAll();
    echo json_encode($news);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
