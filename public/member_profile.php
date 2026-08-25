<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$memberId = (int)($_GET['id'] ?? 0);
$prcNumber = trim($_GET['prc'] ?? '');
$nameQuery = trim($_GET['name'] ?? '');

$member = null;

// 1. Fetch from database
if ($memberId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM website_members WHERE id = ? AND is_published = 1");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
} elseif ($prcNumber !== '') {
    $stmt = $pdo->prepare("SELECT * FROM website_members WHERE id_number = ? AND is_published = 1");
    $stmt->execute([$prcNumber]);
    $member = $stmt->fetch();
} elseif ($nameQuery !== '') {
    $stmt = $pdo->prepare("SELECT * FROM website_members WHERE name = ? AND is_published = 1");
    $stmt->execute([$nameQuery]);
    $member = $stmt->fetch();
}

// 2. Check 7-day grace period / good standing for registered members
if ($member && !empty($member['user_id']) && function_exists('is_good_member')) {
    if (!is_good_member($pdo, (int)$member['user_id'])) {
        $member = null; // Do not show if unpaid past grace period
    }
}

// 3. Fallback mock data if viewing default sample members
if (!$member && ($prcNumber !== '' || $nameQuery !== '')) {
    $mockMembers = [
        '0084521' => [
            'name' => 'Ar. Juan Dela Cruz',
            'role_title' => 'Senior Architect',
            'specialty' => 'Sustainable & Residential Design',
            'id_number' => '0084521',
            'location' => 'Calapan City',
            'achievements' => 'Lead Architect for over 45 residential and commercial eco-sustainable projects in Oriental Mindoro. Advocate of vernacular architecture and climate-responsive construction.',
            'awards' => 'UAP Regional Design Excellence Award 2024, Mindoro Green Building Award 2023.',
            'qr_image_path' => null
        ],
        '0091234' => [
            'name' => 'Ar. Maria Santos',
            'role_title' => 'Principal Architect',
            'specialty' => 'Urban Planning & Commercial',
            'id_number' => '0091234',
            'location' => 'Puerto Galera',
            'achievements' => 'Principal consultant for coastal resort master planning and urban zoning initiatives across Northern Mindoro.',
            'awards' => 'Outstanding Chapter Member 2025, Regional Urban Designer Award.',
            'qr_image_path' => null
        ],
        '0076543' => [
            'name' => 'Ar. Pedro Reyes',
            'role_title' => 'Associate Architect',
            'specialty' => 'Heritage Conservation',
            'id_number' => '0076543',
            'location' => 'San Jose',
            'achievements' => 'Specialist in historical preservation and adaptive reuse of heritage municipal structures in Occidental Mindoro.',
            'awards' => 'National Heritage Council Citation 2022.',
            'qr_image_path' => null
        ],
        '0098712' => [
            'name' => 'Ar. Elena Torralba',
            'role_title' => 'Project Director',
            'specialty' => 'Healthcare & Hospitality',
            'id_number' => '0098712',
            'location' => 'Roxas',
            'achievements' => 'Over 15 years experience in hospital planning, medical centers, and boutique island resort development.',
            'awards' => 'Hospitality Architecture Award 2024.',
            'qr_image_path' => null
        ]
    ];

    if ($prcNumber && isset($mockMembers[$prcNumber])) {
        $member = $mockMembers[$prcNumber];
    } elseif ($nameQuery) {
        foreach ($mockMembers as $mock) {
            if (strcasecmp($mock['name'], $nameQuery) === 0) {
                $member = $mock;
                break;
            }
        }
    }
}

$pageTitle = $member ? htmlspecialchars($member['name']) . ' - Chapter Directory Profile' : 'Member Profile - UAP Mindoro Chapter';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/homepage.css">
    <style>
        .profile-container {
            max-width: 1000px;
            margin: 40px auto 60px;
            padding: 0 20px;
        }
        .back-nav {
            margin-bottom: 24px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-yellow, #e5a91e);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: transform 0.2s ease;
        }
        .back-link:hover {
            transform: translateX(-4px);
        }
        .profile-hero {
            background: linear-gradient(135deg, rgba(24, 36, 58, 0.95), rgba(15, 23, 42, 0.95));
            border: 1px solid rgba(229, 169, 30, 0.25);
            border-radius: 18px;
            padding: 36px 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
            margin-bottom: 28px;
        }
        .profile-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #e5a91e, #f59e0b, #10b981);
        }
        .profile-header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        .profile-avatar-block {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .profile-avatar-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e5a91e, #b45309);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            box-shadow: 0 8px 24px rgba(229, 169, 30, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.2);
            flex-shrink: 0;
        }
        .profile-title-group h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 6px 0;
            line-height: 1.2;
        }
        .profile-title-group .role-tag {
            color: var(--accent-yellow, #e5a91e);
            font-size: 1.1rem;
            font-weight: 600;
        }
        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        @media (max-width: 800px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
        .profile-card {
            background: rgba(24, 36, 58, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 28px;
            backdrop-filter: blur(10px);
            margin-bottom: 24px;
        }
        .profile-card h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent-yellow, #e5a91e);
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 10px;
        }
        .info-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        @media (max-width: 500px) {
            .info-list {
                grid-template-columns: 1fr;
            }
        }
        .info-item label {
            display: block;
            font-size: 0.8rem;
            color: var(--text-muted, #94a3b8);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .info-item span {
            font-size: 1.05rem;
            color: #f1f5f9;
            font-weight: 600;
        }
        .content-box {
            color: #cbd5e1;
            font-size: 0.95rem;
            line-height: 1.7;
            background: rgba(0, 0, 0, 0.2);
            padding: 16px 18px;
            border-radius: 10px;
            border-left: 3px solid var(--accent-yellow, #e5a91e);
        }
        .qr-card {
            text-align: center;
        }
        .qr-image-wrapper {
            background: #ffffff;
            padding: 14px;
            border-radius: 12px;
            display: inline-block;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            margin: 14px 0;
        }
        .qr-image-wrapper img {
            max-width: 160px;
            max-height: 160px;
            object-fit: contain;
            display: block;
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="<?php echo BASE_URL; ?>/index.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff;">
                    <img src="<?php echo BASE_URL; ?>/uploads/uap_logo.jpg" alt="UAP Logo">
                    <span>UAP Mindoro</span>
                </a>
            </div>
            <div class="nav-actions">
                <a href="<?php echo BASE_URL; ?>/index.php#members" class="btn-yellow">Member Directory</a>
                <a href="<?php echo BASE_URL; ?>/auth/login.php" class="btn-portal">Portal Login</a>
            </div>
        </div>
    </nav>

    <div class="profile-container">
        
        <div class="back-nav">
            <a href="<?php echo BASE_URL; ?>/index.php#members" class="back-link">
                &larr; Back to Chapter Directory
            </a>
        </div>

        <?php if (!$member): ?>
            <div class="profile-hero" style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 3rem; margin-bottom: 12px;">🏛️</div>
                <h1 style="color:#fff; margin-bottom: 10px;">Member Profile Not Found</h1>
                <p style="color:var(--text-muted); max-width: 500px; margin: 0 auto 24px;">
                    This member profile is either not published yet or is currently undergoing annual chapter dues verification.
                </p>
                <a href="<?php echo BASE_URL; ?>/index.php#members" class="btn-yellow">Return to Directory</a>
            </div>
        <?php else: 
            $initials = '';
            $parts = explode(' ', preg_replace('/^(Ar\.|Arch\.|Architect)\s+/i', '', $member['name']));
            foreach ($parts as $p) {
                if (!empty($p)) $initials .= strtoupper($p[0]);
            }
            $initials = substr($initials, 0, 2) ?: 'AR';
        ?>

            <!-- HERO PROFILE HEADER -->
            <div class="profile-hero">
                <div class="profile-header-content">
                    <div class="profile-avatar-block">
                        <div class="profile-avatar-circle">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <div class="profile-title-group">
                            <h1><?php echo htmlspecialchars($member['name']); ?></h1>
                            <div class="role-tag"><?php echo htmlspecialchars($member['role_title'] ?: 'Architect'); ?></div>
                        </div>
                    </div>
                    <div>
                        <span class="verified-badge">
                            ✓ Good Standing Member
                        </span>
                    </div>
                </div>
            </div>

            <!-- PROFILE MAIN CONTENT GRID -->
            <div class="profile-grid">
                
                <!-- LEFT COLUMN -->
                <div>
                    <!-- PROFESSIONAL OVERVIEW -->
                    <div class="profile-card">
                        <h2>🏛️ Professional Credentials</h2>
                        <div class="info-list">
                            <div class="info-item">
                                <label>PRC License Number</label>
                                <span><?php echo htmlspecialchars($member['id_number'] ?: '—'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Chapter Affiliation</label>
                                <span>UAP Mindoro Chapter (121)</span>
                            </div>
                            <div class="info-item">
                                <label>Architectural Specialty</label>
                                <span><?php echo htmlspecialchars($member['specialty'] ?: 'General Practice'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Primary Location / Base</label>
                                <span><?php echo htmlspecialchars($member['location'] ?: 'Mindoro, Philippines'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- ACHIEVEMENTS -->
                    <div class="profile-card">
                        <h2>🏆 Career Achievements & Practice</h2>
                        <div class="content-box">
                            <?php echo nl2br(htmlspecialchars($member['achievements'] ?: 'No specific achievements submitted yet.')); ?>
                        </div>
                    </div>

                    <!-- AWARDS & HONORS -->
                    <div class="profile-card">
                        <h2>🎖️ Honors, Distinctions & Awards</h2>
                        <div class="content-box">
                            <?php echo nl2br(htmlspecialchars($member['awards'] ?: 'No awards or distinctions listed.')); ?>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN (SIDEBAR) -->
                <div>
                    <!-- CHAPTER VERIFICATION CARD -->
                    <div class="profile-card qr-card">
                        <h2>📱 Chapter Verification</h2>
                        <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin: 0 0 12px 0;">
                            Official registered architect verified under United Architects of the Philippines.
                        </p>
                        
                        <?php if (!empty($member['qr_image_path'])): ?>
                            <div class="qr-image-wrapper">
                                <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($member['qr_image_path']); ?>" alt="Architect QR Card">
                            </div>
                            <span style="font-size: 0.8rem; color: var(--text-muted); display: block;">Scan to verify / contact</span>
                        <?php else: ?>
                            <div style="background: rgba(0,0,0,0.2); padding: 24px 16px; border-radius: 10px; border: 1px dashed rgba(255,255,255,0.15); margin: 12px 0;">
                                <span style="font-size: 2rem; display: block; margin-bottom: 6px;">🏛️</span>
                                <span style="font-size: 0.85rem; color: var(--text-muted);">UAP Mindoro Registered Member</span>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.08);">
                            <a href="<?php echo BASE_URL; ?>/index.php#members" class="btn-yellow" style="display: block; width: 100%; text-align: center; text-decoration: none;">
                                View All Chapter Members
                            </a>
                        </div>
                    </div>
                </div>

            </div>

        <?php endif; ?>

    </div>

    <!-- FOOTER -->
    <footer id="contact" style="margin-top: 60px;">
        <div class="footer-container">
            <div class="footer-col">
                <h1>UAP Mindoro Chapter</h1>
                <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.6;">
                    Official chapter of the United Architects of the Philippines (IAPOA Chapter 121) serving architects and communities across Mindoro.
                </p>
            </div>
            <div class="footer-col">
                <h4>Contact Us</h4>
                <div class="contact-details">
                    <p>📍 <strong>Address:</strong> Calapan City, Oriental Mindoro, Philippines</p>
                    <p>📧 <strong>Email:</strong> uapmindorochapter@gmail.com</p>
                </div>
            </div>
        </div>
        <div class="copyright">
            &copy; <?php echo date('Y'); ?> United Architects of the Philippines - Mindoro Chapter. All rights reserved.
        </div>
    </footer>

</body>
</html>
