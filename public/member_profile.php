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
            --bg-inner-card: #1a1a1a;
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

        .btn-yellow {
            display: inline-block;
            padding: 0.7rem 1.6rem;
            background-color: var(--accent-yellow);
            color: #000000;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
            font-size: 0.8rem;
        }

        .btn-yellow:hover { background-color: var(--accent-yellow-hover); }

        .main-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem 4rem;
            flex: 1 0 auto;
        }

        .dark-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-dark);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 1.8rem;
        }

        .section-heading {
            border-left: 4px solid var(--accent-yellow);
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }

        .section-heading h2 {
            font-size: 1.35rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #ffffff;
        }

        .profile-hero-card {
            background: linear-gradient(135deg, #141c28 0%, #0e141e 100%);
            border: 1px solid rgba(245, 184, 0, 0.35);
            border-radius: 12px;
            padding: 28px 32px;
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 1.8rem;
            box-shadow: 0 12px 30px rgba(0,0,0,0.4);
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
            flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(245, 184, 0, 0.3);
        }

        .profile-hero-info h1 {
            font-size: 2.2rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 4px;
            line-height: 1.2;
        }

        .profile-hero-info .role-tag {
            color: var(--accent-yellow);
            font-size: 1.15rem;
            font-weight: 700;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.2rem;
        }

        .info-cell {
            background: var(--bg-inner-card);
            border: 1px solid var(--border-dark);
            border-radius: 6px;
            padding: 16px 18px;
        }

        .info-cell label {
            display: block;
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .info-cell span {
            font-size: 1.05rem;
            color: #ffffff;
            font-weight: 600;
        }

        .content-box {
            background: var(--bg-inner-card);
            border-left: 3px solid var(--accent-yellow);
            border-radius: 0 6px 6px 0;
            padding: 18px 22px;
            color: #d1d5db;
            font-size: 0.98rem;
            line-height: 1.75;
        }

        .qr-wrapper {
            background: #ffffff;
            padding: 12px;
            border-radius: 10px;
            display: inline-block;
            margin: 12px 0;
        }

        .qr-wrapper img {
            max-width: 160px;
            max-height: 160px;
            object-fit: contain;
            display: block;
        }

        footer {
            background-color: #000000;
            border-top: 1px solid var(--border-dark);
            padding: 3rem 2rem 1.5rem;
            width: 100%;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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

        @media (max-width: 768px) {
            nav ul { display: none; }
            .profile-hero-card { flex-direction: column; text-align: center; }
            .header-container { padding: 0.8rem 1rem; }
        }
    </style>
</head>
<body>

    <!-- STICKY HEADER -->
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
                <a href="<?php echo BASE_URL; ?>/auth/login.php" class="login-btn">Login</a>
            </div>
        </div>
    </header>

    <!-- MAIN CONTAINER (NO SPONSOR SIDEBARS) -->
    <div class="main-container">
        
        <div style="margin-bottom: 1.4rem;">
            <a href="<?php echo BASE_URL; ?>/index.php#members" class="btn-yellow" style="display:inline-flex; align-items:center; gap:8px;">
                &larr; Back to Chapter Directory
            </a>
        </div>

        <?php if (!$member): ?>
            <section class="dark-card" style="text-align:center; padding: 4rem 2rem;">
                <div style="font-size: 3rem; margin-bottom: 12px;">🏛️</div>
                <h2 style="color:#fff; margin-bottom: 10px;">Member Profile Not Found</h2>
                <p style="color:var(--text-muted); max-width: 500px; margin: 0 auto 20px;">
                    This member profile is either not published yet or is currently undergoing annual chapter dues verification.
                </p>
                <a href="<?php echo BASE_URL; ?>/index.php#members" class="btn-yellow">Return to Directory</a>
            </section>
        <?php else: 
            $initials = '';
            $parts = explode(' ', preg_replace('/^(Ar\.|Arch\.|Architect)\s+/i', '', $member['name']));
            foreach ($parts as $p) {
                if (!empty($p)) $initials .= strtoupper($p[0]);
            }
            $initials = substr($initials, 0, 2) ?: 'AR';
        ?>

            <!-- HERO MEMBER HEADER -->
            <div class="profile-hero-card">
                <div class="profile-avatar-circle">
                    <?php echo htmlspecialchars($initials); ?>
                </div>
                <div class="profile-hero-info">
                    <h1><?php echo htmlspecialchars($member['name']); ?></h1>
                    <div class="role-tag"><?php echo htmlspecialchars($member['role_title'] ?: 'Architect'); ?></div>
                </div>
            </div>

            <!-- PROFESSIONAL CREDENTIALS -->
            <section class="dark-card">
                <div class="section-heading">
                    <h2>🏛️ Professional Credentials</h2>
                </div>
                <div class="info-grid">
                    <div class="info-cell">
                        <label>PRC License Number</label>
                        <span><?php echo htmlspecialchars($member['id_number'] ?: '—'); ?></span>
                    </div>
                    <div class="info-cell">
                        <label>Chapter Affiliation</label>
                        <span>UAP Mindoro Chapter (121)</span>
                    </div>
                    <div class="info-cell">
                        <label>Architectural Specialty</label>
                        <span><?php echo htmlspecialchars($member['specialty'] ?: 'General Practice'); ?></span>
                    </div>
                    <div class="info-cell">
                        <label>Primary Location / Base</label>
                        <span><?php echo htmlspecialchars($member['location'] ?: 'Mindoro, Philippines'); ?></span>
                    </div>
                </div>
            </section>

            <!-- FEATURED PROJECT / WORK PHOTO -->
            <?php if (!empty($member['photo_path'])): ?>
            <section class="dark-card">
                <div class="section-heading">
                    <h2>📸 Featured Project / Work</h2>
                </div>
                <div style="text-align:center; margin-bottom:1rem;">
                    <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($member['photo_path']); ?>" 
                         alt="<?php echo htmlspecialchars($member['name']); ?> Featured Work" 
                         style="max-width:100%; max-height:520px; width:auto; border-radius:8px; object-fit:cover; border:1px solid var(--border-dark); box-shadow:0 12px 32px rgba(0,0,0,0.6);">
                </div>
                <?php if (!empty($member['photo_description'])): ?>
                <div class="content-box">
                    <?php echo nl2br(htmlspecialchars($member['photo_description'])); ?>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <!-- ACHIEVEMENTS -->
            <section class="dark-card">
                <div class="section-heading">
                    <h2>🏆 Career Achievements & Practice</h2>
                </div>
                <div class="content-box">
                    <?php echo nl2br(htmlspecialchars($member['achievements'] ?: 'No specific achievements submitted yet.')); ?>
                </div>
            </section>

            <!-- AWARDS -->
            <section class="dark-card">
                <div class="section-heading">
                    <h2>🎖️ Honors, Distinctions & Awards</h2>
                </div>
                <div class="content-box">
                    <?php echo nl2br(htmlspecialchars($member['awards'] ?: 'No awards or distinctions listed.')); ?>
                </div>
            </section>

            <!-- CHAPTER VERIFICATION & QR -->
            <section class="dark-card" style="text-align: center;">
                <div class="section-heading" style="text-align: left;">
                    <h2>📱 Chapter Verification</h2>
                </div>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.2rem;">
                    Official registered architect verified under the United Architects of the Philippines – Mindoro Chapter.
                </p>
                
                <?php if (!empty($member['qr_image_path'])): ?>
                    <div class="qr-wrapper">
                        <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($member['qr_image_path']); ?>" alt="Architect QR Card">
                    </div>
                    <p style="font-size: 0.82rem; color: var(--text-muted);">Scan to verify / contact</p>
                <?php else: ?>
                    <div style="background: var(--bg-inner-card); border: 1px dashed var(--border-dark); border-radius: 8px; padding: 24px; max-width: 440px; margin: 0 auto;">
                        <span style="font-size: 2.2rem; display: block; margin-bottom: 6px;">🏛️</span>
                        <strong style="color: #ffffff; font-size: 0.95rem;">UAP Mindoro Chapter Registered Member</strong>
                    </div>
                <?php endif; ?>
            </section>

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
