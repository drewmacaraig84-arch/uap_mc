<?php
$_GET['id'] = 1;
ob_start();
include __DIR__ . '/../website/api/member.php';
$json = ob_get_clean();
$data = json_decode($json, true);

echo "API Response Status:\n";
if (!empty($data['projects'])) {
    echo "SUCCESS: Found " . count($data['projects']) . " projects!\n";
    foreach ($data['projects'] as $p) {
        echo " - [{$p['category']}] {$p['title']} ({$p['location']})\n";
        echo "   Cover: {$p['cover_url']}\n";
        echo "   Photos: " . count($p['photos']) . " photos\n";
        echo "   Team: {$p['project_team']}\n";
    }
} else {
    echo "ERROR or no projects found: " . substr($json, 0, 300) . "\n";
}
