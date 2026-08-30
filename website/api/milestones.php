<?php
require_once __DIR__ . '/config.php';
try {
    $milestones = $pdo->query(
        "SELECT id, year, title, content FROM chapter_milestones ORDER BY sort_order ASC, year ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($milestones);
} catch (Exception $e) {
    // Return empty array if table not yet created
    echo json_encode([]);
}
