<?php
require_once __DIR__ . '/config.php';
try {
    // Helper to format stored image paths into public URLs
    $toUrl = function($path) {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        return '/' . ltrim($path, '/');
    };

    $formatSponsor = function($s) use ($toUrl) {
        $s['is_platinum'] = (int)($s['is_platinum'] ?? 0);
        $s['logo_url'] = $toUrl($s['logo_path']);
        
        $products = [];
        if (!empty($s['products_json'])) {
            $decoded = json_decode($s['products_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $p) {
                    $pImg = $p['image_path'] ?? ($p['image_url'] ?? null);
                    $products[] = [
                        'id' => $p['id'] ?? ('prod_' . uniqid()),
                        'name' => $p['name'] ?? '',
                        'description' => $p['description'] ?? '',
                        'link_url' => $p['link_url'] ?? '',
                        'image_path' => $pImg,
                        'image_url' => $toUrl($pImg)
                    ];
                }
            }
        }
        $s['duration_seconds'] = ($s['is_platinum'] === 1 ? 180 : 30);
        $s['products'] = $products;
        return $s;
    };

    // Single sponsor lookup: ?id=X
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT id, name, logo_path, description, url, is_platinum, products_json FROM sponsors WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([(int)$_GET['id']]);
        $sponsor = $stmt->fetch();
        if (!$sponsor) {
            http_response_code(404);
            echo json_encode(['error' => 'Sponsor partner not found']);
            exit;
        }
        echo json_encode($formatSponsor($sponsor));
        exit;
    }

    // List all sponsors
    $sponsors = $pdo->query("SELECT id, name, logo_path, description, url, is_platinum, products_json FROM sponsors WHERE is_active = 1 ORDER BY is_platinum DESC, display_order ASC, id ASC")->fetchAll();
    $result = [];
    foreach ($sponsors as $s) {
        $result[] = $formatSponsor($s);
    }
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
