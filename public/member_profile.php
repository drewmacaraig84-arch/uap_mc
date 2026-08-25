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
    <style>
        :root {
            --bg-black: #080808;
            --bg-card: #121212;
            --bg-inner-card: #182232;
            --accent-yellow: #f5b800;
            --accent-yellow-hover: #d9a300;
            --text-light: #f5f5f5;
            --text-muted: #a0a0a0;
            --border-dark: #262626;
            --border-yellow-subtle: rgba(245, 184, 0, 0.3);
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--bg-black);
            color: var(--text-light);
            font-family: var(--font-family);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a { color: inherit; text-decoration: none; }

        header {
            background-color: #000000;
            border-bottom: 3px solid var(--accent-yellow);
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
        }

        .header-container {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 2rem;
            gap: 1.2rem;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-logo img {
            height: 42px;
            width: auto;
            max-width: 50px;
            object-fit: contain;
            display: block;
        }

        .brand-title {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #ffffff;
            white-space: nowrap;
        }

        .brand-title span {
            color: var(--accent-yellow);
        }

        .header-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 1.2rem;
        }

        nav ul {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 2rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        nav a {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: color 0.2s ease;
        }

        nav a:hover { color: var(--accent-yellow); }

        .login-btn {
            display: inline-block;
            padding: 0.7rem 1.2rem;
            background: var(--accent-yellow);
            color: #000000;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1;
            transition: background-color 0.2s ease;
        }

        .login-btn:hover {
            background: var(--accent-yellow-hover);
        }

        .main-wrapper {
            flex: 1 0 auto;
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
            padding: 32px 20px 60px;
        }

        .back-nav {
            margin-bottom: 24px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-yellow);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: transform 0.2s ease;
        }

        .back-link:hover {
            transform: translateX(-4px);
            color: #ffffff;
        }

        .profile-hero {
            background: linear-gradient(135deg, #141f32 0%, #0d1522 100%);
            border: 1px solid rgba(245, 184, 0, 0.35);
            border-radius: 16px;
            padding: 32px 36px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
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
            background: linear-gradient(90deg, #f5b800, #fbbf24, #10b981);
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
            background: linear-gradient(135deg, #f5b800, #b45309);
            color: #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 900;
            box-shadow: 0 8px 24px rgba(245, 184, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.2);
            flex-shrink: 0;
        }

        .profile-title-group h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0 0 4px 0;
            line-height: 1.2;
        }

        .profile-title-group .role-tag {
            color: var(--accent-yellow);
            font-size: 1.1rem;
            font-weight: 700;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        @media (max-width: 850px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            .header-container {
                padding: 0.8rem 1rem;
            }
            nav ul {
                display: none;
            }
        }

        .profile-card {
            background: var(--bg-card);
            border: 1px solid var(--border-dark);
            border-radius: 14px;
            padding: 26px;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .profile-card h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent-yellow);
            margin: 0 0 16px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid var(--border-dark);
            padding-bottom: 12px;
        }

        .info-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        @media (max-width: 540px) {
            .info-list {
                grid-template-columns: 1fr;
            }
        }

        .info-item label {
            display: block;
            font-size: 0.78rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .info-item span {
            font-size: 1.05rem;
            color: #ffffff;
            font-weight: 600;
        }

        .content-box {
            color: #d1d5db;
            font-size: 0.95rem;
            line-height: 1.7;
            background: #181818;
            padding: 16px 18px;
            border-radius: 10px;
            border-left: 3px solid var(--accent-yellow);
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
            max-width: 180px;
            max-height: 180px;
            object-fit: contain;
            display: block;
        }

        .btn-yellow {
            display: inline-block;
            background-color: var(--accent-yellow);
            color: #000000;
            padding: 0.8rem 1.6rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 4px;
            transition: all 0.2s ease;
            font-size: 0.85rem;
        }

        .btn-yellow:hover {
            background-color: var(--accent-yellow-hover);
        }

        footer {
            background-color: #000000;
            border-top: 1px solid var(--border-dark);
            padding: 3rem 2rem 1.5rem;
            width: 100%;
            margin-top: auto;
        }

        .footer-container {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2.5rem;
            margin-bottom: 2rem;
        }

        .footer-col h1 {
            font-size: 1.1rem;
            color: var(--accent-yellow);
            margin-bottom: 1rem;
        }

        .footer-col h4 {
            color: var(--text-light);
            font-size: 0.95rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .contact-details p {
            color: var(--text-muted);
            font-size: 0.88rem;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .contact-details strong { color: var(--text-light); }

        .copyright {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-dark);
            color: var(--text-muted);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

    <!-- STICKY HEADER NAVBAR -->
    <header>
        <div class="header-container">
            <div class="brand-logo">
                <a href="<?php echo BASE_URL; ?>/index.php" style="display:flex;align-items:center;gap:14px;text-decoration:none;">
                    <img src="<?php echo BASE_URL; ?>/uploads/uap_logo.jpg" alt="UAP Mindoro Logo" onerror="this.style.display='none'">
                    <div class="brand-title">UAP-<span>Mindoro Chapter</span></div>
                </a>
            </div>

            <div class="header-actions">
                <nav>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#home">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#members">Members</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#about">About</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#news">News</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#contact">Contact</a></li>
                    </ul>
                </nav>
                <a href="<?php echo BASE_URL; ?>/auth/login.php" class="login-btn">Portal Login</a>
            </div>
        </div>
    </header>

    <!-- MAIN PROFILE VIEW CONTAINER -->
    <div class="main-wrapper">
        
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
                </div>
            </div>

            <!-- PROFILE MAIN CONTENT GRID -->
            <div class="profile-grid">
                
                <!-- LEFT COLUMN -->
                <div>
                    <!-- PROFESSIONAL CREDENTIALS -->
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
                            <div style="background: #181818; padding: 24px 16px; border-radius: 10px; border: 1px dashed var(--border-dark); margin: 12px 0;">
                                <span style="font-size: 2rem; display: block; margin-bottom: 6px;">🏛️</span>
                                <span style="font-size: 0.85rem; color: var(--text-muted);">UAP Mindoro Registered Member</span>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-dark);">
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
    <footer id="contact">
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
