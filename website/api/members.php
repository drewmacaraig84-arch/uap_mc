<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../includes/auth.php';

try {
    $toUrl = function($path) {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        return '/' . ltrim($path, '/');
    };

    $rows = $pdo->query("SELECT wm.id, wm.name, wm.id_number, wm.role_title, wm.specialty, wm.location, wm.company_name, wm.link_url, wm.link_type, wm.links_json, wm.achievements, wm.awards, wm.user_id, COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path 
                           FROM website_members wm 
                           LEFT JOIN users u ON wm.user_id = u.id 
                           WHERE wm.is_published = 1 
                           ORDER BY wm.name ASC")->fetchAll();
    
    $members = [];
    foreach ($rows as $m) {
        if (!empty($m['user_id']) && function_exists('has_unlocked_website_directory') && !has_unlocked_website_directory($pdo, (int)$m['user_id'])) {
            continue; // Exclude if pending fee assignment or payment
        }
        if (!empty($m['user_id']) && function_exists('is_good_member') && !is_good_member($pdo, (int)$m['user_id'])) {
            continue; // Exclude if good standing is revoked or overdue
        }
        $photo = $m['photo_path'];
        if ($photo && str_contains($photo, 'proj_')) {
            $photo = null;
        }
        $m['photo_url'] = $toUrl($photo);
        unset($m['photo_path']);

        $links = [];
        if (!empty($m['links_json'])) {
            $decodedLinks = json_decode($m['links_json'], true);
            if (is_array($decodedLinks)) {
                foreach ($decodedLinks as $lnk) {
                    if (!empty($lnk['url'])) {
                        $u = trim($lnk['url']);
                        if (!preg_match('#^https?://#i', $u)) $u = 'https://' . ltrim($u, '/');
                        $t = function_exists('detect_social_link_type') ? detect_social_link_type($u, $lnk['type'] ?? 'auto') : ($lnk['type'] ?? 'website');
                        $links[] = ['url' => $u, 'type' => $t];
                    }
                }
            }
        }
        if (empty($links) && !empty($m['link_url'])) {
            $u = trim($m['link_url']);
            if (!preg_match('#^https?://#i', $u)) $u = 'https://' . ltrim($u, '/');
            $t = function_exists('detect_social_link_type') ? detect_social_link_type($u, $m['link_type'] ?? 'auto') : 'website';
            $links[] = ['url' => $u, 'type' => $t];
        }
        $m['links'] = $links;
        if (!empty($links[0])) {
            $m['link_url'] = $links[0]['url'];
            $m['link_type'] = $links[0]['type'];
        }
        unset($m['links_json']);

        $members[] = $m;
    }
    echo json_encode($members);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
