<?php
require_once __DIR__ . '/../includes/config.php';

// Check current sponsors
$s = $pdo->query("SELECT * FROM sponsors LIMIT 1")->fetch();
if ($s) {
    $products = [
        [
            'id' => 'prod_davies_1',
            'name' => 'Davies Sun & Rain Elastomeric Paint',
            'description' => '100% Acrylic Elastomeric Paint with high waterproofing performance, crack-bridging capability, and outstanding UV resistance for exterior walls.',
            'link_url' => 'https://daviespaints.com.ph',
            'image_path' => 'uploads/davies.png'
        ],
        [
            'id' => 'prod_davies_2',
            'name' => 'Davies Megacryl 100% Acrylic Latex Paint',
            'description' => 'Premium interior/exterior architectural coating providing low odor, low VOC, superior hiding power, and washable smooth matte finish.',
            'link_url' => 'https://daviespaints.com.ph',
            'image_path' => 'uploads/davies.png'
        ]
    ];

    $stmt = $pdo->prepare("UPDATE sponsors SET is_platinum = 1, products_json = ? WHERE id = ?");
    $stmt->execute([json_encode($products), $s['id']]);
    echo "Updated Sponsor ID {$s['id']} ({$s['name']}) to Platinum with 2 sample promotional products!\n";
} else {
    echo "No sponsors found to update.\n";
}
