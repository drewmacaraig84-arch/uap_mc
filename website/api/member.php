<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); exit; }

try {
    $stmt = $pdo->prepare("SELECT wm.*, COALESCE(NULLIF(u.profile_photo, ''), NULLIF(wm.photo_path, '')) as photo_path, u.id as user_id 
                          FROM website_members wm 
                          LEFT JOIN users u ON wm.user_id = u.id 
                          WHERE (wm.id = ? OR wm.user_id = ?) AND wm.is_published = 1");
    $stmt->execute([$id, $id]);
    $member = $stmt->fetch();
    if (!$member) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }

    if (!empty($member['user_id'])) {
        if (function_exists('has_unlocked_website_directory') && !has_unlocked_website_directory($pdo, (int)$member['user_id'])) {
            http_response_code(404);
            echo json_encode(['error' => 'This directory profile is currently pending approval or payment.']);
            exit;
        }
        if (function_exists('is_good_member') && !is_good_member($pdo, (int)$member['user_id'])) {
            http_response_code(404);
            echo json_encode(['error' => 'Member profile is currently on administrative hold or pending certification.']);
            exit;
        }
    }

    $toUrl = function($path) {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        return '/' . ltrim($path, '/');
    };

    // Build photo URL
    $member['photo_url'] = $toUrl($member['photo_path']);

    if (!empty($member['link_url']) && function_exists('detect_social_link_type')) {
        $member['link_type'] = detect_social_link_type($member['link_url'], $member['link_type'] ?? 'auto');
    }

    // Decode gallery JSON (legacy support)
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

    // Decode projects JSON (Completed Works)
    $projects = [];
    if (!empty($member['projects_json'])) {
        $decodedProjects = json_decode($member['projects_json'], true);
        if (is_array($decodedProjects)) {
            foreach ($decodedProjects as &$p) {
                $p['cover_url'] = !empty($p['cover_photo']) ? $toUrl($p['cover_photo']) : null;
                $photoUrls = [];
                if (!empty($p['photos']) && is_array($p['photos'])) {
                    foreach ($p['photos'] as $ph) {
                        if (!empty($ph)) {
                            $photoUrls[] = $toUrl($ph);
                        }
                    }
                }
                if (empty($photoUrls) && !empty($p['cover_url'])) {
                    $photoUrls[] = $p['cover_url'];
                }
                $p['photos'] = $photoUrls;
            }
            $projects = $decodedProjects;
        }
    }

    // Fallback: If projects is empty but legacy gallery exists, convert into a Completed Work
    if (empty($projects) && !empty($gallery)) {
        $legacyPhotos = array_map(function($g) { return $g['url'] ?? ''; }, $gallery);
        $legacyPhotos = array_values(array_filter($legacyPhotos));
        if (!empty($legacyPhotos)) {
            $projects[] = [
                'id' => 'proj_1',
                'title' => !empty($gallery[0]['description']) ? $gallery[0]['description'] : ($member['name'] . ' Showcase Project'),
                'category' => !empty($member['specialty']) ? strtoupper(explode(',', $member['specialty'])[0]) : 'RESIDENTIAL',
                'location' => $member['location'] ?? 'Mindoro',
                'description' => $member['achievements'] ?? 'Architectural design, space planning, and development project.',
                'project_team' => $member['name'],
                'cover_url' => $legacyPhotos[0],
                'photos' => array_slice($legacyPhotos, 0, 5)
            ];
        }
    }
    $member['projects'] = $projects;

    // QR image
    $member['qr_url'] = $toUrl($member['qr_image_path']);

    // Clean up raw paths
    unset($member['photo_path'], $member['gallery_json'], $member['projects_json'], $member['qr_image_path']);

    echo json_encode($member);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
