<?php
require_once __DIR__ . '/../includes/config.php';

$uploadDir = __DIR__ . '/../uploads/members/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Copy default logo / sample images to members if available
$sampleFiles = [
    'proj_casa_1.jpg' => __DIR__ . '/../uploads/sponsor_1787829158_de2e35.jpg',
    'proj_casa_2.jpg' => __DIR__ . '/../uploads/uap_logo.jpg',
    'proj_casa_3.jpg' => __DIR__ . '/../uploads/logo.jpg',
    'proj_dlsud_1.jpg' => __DIR__ . '/../uploads/sponsor_1787829158_de2e35.jpg',
    'proj_dlsud_2.jpg' => __DIR__ . '/../uploads/uap_logo.jpg'
];

foreach ($sampleFiles as $dest => $src) {
    if (file_exists($src)) {
        copy($src, $uploadDir . $dest);
    }
}

$sampleProjects = [
    [
        'id' => 'proj_casa_san_gregorio',
        'title' => 'CASA SAN GREGORIO',
        'category' => 'RESIDENTIAL',
        'location' => 'MAKATI, MANILA',
        'description' => "Interiors dictate architectural form in Casa San Gregorio, a three-story, multi-generational family home whose volumetric composition both functionally serves and reflects the personality of its art-collecting home owner. Given the client's reputation as a prolific collector of pieces ranging from old masters' paintings, to towering sculptures, to vintage furniture, Arch. Nazareno realized the need for this home to take on an orthogonal architectural form. Interior walls and spaces could then house pieces of various heights and widths, and would allow for different means of letting in natural light into the home. NAD worked closely with landscaper and avid art collector Bobby Gopiao to integrate pieces from the client's art collection to the home's outdoors. Concrete blocks were also used for the home's lap pool, marrying the material language of the backyard with that of the home's various exterior volumes, predominantly clad in boardform concrete.",
        'project_team' => "Ar. Anthony Nazareno\nAr. Vladimir Banks\nIDr. Marielle Saguibo",
        'cover_photo' => 'uploads/members/proj_casa_1.jpg',
        'photos' => [
            'uploads/members/proj_casa_1.jpg',
            'uploads/members/proj_casa_2.jpg',
            'uploads/members/proj_casa_3.jpg'
        ]
    ],
    [
        'id' => 'proj_dlsud_ceat',
        'title' => 'DLSU-D : College of Engineering, Architecture and Technology',
        'category' => 'INSTITUTIONAL',
        'location' => 'DASMARIÑAS, CAVITE',
        'description' => "A modern academic and laboratory complex built with bioclimatic passive cooling louvers, generous natural ventilation corridors, and sustainable concrete finishes to foster interdisciplinary design and engineering collaboration.",
        'project_team' => "Ar. Aries King Nieto\nAr. Vladimir Banks\nEngr. J. Santos",
        'cover_photo' => 'uploads/members/proj_dlsud_1.jpg',
        'photos' => [
            'uploads/members/proj_dlsud_1.jpg',
            'uploads/members/proj_dlsud_2.jpg'
        ]
    ]
];

$projectsJson = json_encode($sampleProjects);
$stmt = $pdo->prepare("UPDATE website_members SET projects_json = ? WHERE id = 1");
$stmt->execute([$projectsJson]);

echo "Updated website_member #1 with " . count($sampleProjects) . " Completed Works!\n";
