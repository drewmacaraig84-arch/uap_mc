<?php
require_once __DIR__ . '/config.php';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); exit; }

try {
    $stmt = $pdo->prepare("SELECT wm.*, u.id as user_id FROM website_members wm LEFT JOIN users u ON wm.user_id = u.id WHERE wm.id = ? AND wm.is_published = 1");
    $stmt->execute([$id]);
    $member = $stmt->fetch();
    if (!$member) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }

    $toUrl = function($path) {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        return '/' . ltrim($path, '/');
    };

    // Build photo URL
    $member['photo_url'] = $toUrl($member['photo_path']);

    // Decode gallery JSON
    $gallery = [];
    if (!empty($member['gallery_json'])) {
        $decoded = json_decode($member['gallery_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as &$g) {
                if (!empty($g['path'])) {
                    $g['url'] = $toUrl($g['path']);
                }
            }
            $gallery = $decoded;
        }
    } elseif ($member['photo_url']) {
        $gallery = [['url' => $member['photo_url'], 'description' => $member['photo_description'] ?? '']];
    }
    $member['gallery'] = $gallery;

    // QR image
    $member['qr_url'] = $toUrl($member['qr_image_path']);

    // Clean up raw paths
    unset($member['photo_path'], $member['gallery_json'], $member['qr_image_path']);

    echo json_encode($member);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
