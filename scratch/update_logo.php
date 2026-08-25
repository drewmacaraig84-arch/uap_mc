<?php
if (!is_dir('uploads')) {
    mkdir('uploads', 0777, true);
}
if (!is_dir('images')) {
    mkdir('images', 0777, true);
}

copy('public/logo.jpg', 'uploads/logo.jpg');
copy('public/logo.jpg', 'uploads/uap_logo.jpg');
copy('public/logo.jpg', 'images/logo.jpg');

require_once 'includes/config.php';
$stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('logo', 'public/logo.jpg') ON DUPLICATE KEY UPDATE setting_value = 'public/logo.jpg'");
$stmt->execute();

echo "Logo copied to uploads/ and images/ and site_settings updated successfully.\n";
