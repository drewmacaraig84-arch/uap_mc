<?php
require_once __DIR__ . '/../includes/config.php';

$stmt = $pdo->query("SELECT id, user_id, name, id_number, specialty, company_name, photo_path, projects_json FROM website_members");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($members) . " members in website_members.\n";

foreach ($members as $m) {
    echo "ID: {$m['id']}, Name: {$m['name']}, Company: {$m['company_name']}\n";
}
