<?php
require_once __DIR__ . '/../includes/config.php';

$pageTitle = 'UAP - Mindoro Chapter | United Architects of the Philippines';

// Fetch website directory members if published
$members = [
    [
        'name' => 'Ar. Juan Dela Cruz',
        'role' => 'Senior Architect',
        'specialty' => 'Sustainable & Residential Design',
        'prc' => '0084521',
        'location' => 'Calapan City',
        'status' => 'Active Member'
    ],
    [
        'name' => 'Ar. Maria Santos',
        'role' => 'Principal Architect',
        'specialty' => 'Urban Planning & Commercial',
        'prc' => '0091234',
        'location' => 'Puerto Galera',
        'status' => 'Active Member'
    ],
    [
        'name' => 'Ar. Pedro Reyes',
        'role' => 'Associate Architect',
        'specialty' => 'Heritage Conservation',
        'prc' => '0076543',
        'location' => 'San Jose',
        'status' => 'Active Member'
    ],
    [
        'name' => 'Ar. Elena Torralba',
        'role' => 'Project Director',
        'specialty' => 'Healthcare & Hospitality',
        'prc' => '0098712',
        'location' => 'Roxas',
        'status' => 'Active Member'
    ]
];

try {
    $publishedWebsiteMembers = $pdo->query("SELECT * FROM website_members WHERE is_published = 1 ORDER BY name ASC")->fetchAll();
    if (!empty($publishedWebsiteMembers)) {
        $members = [];
        foreach ($publishedWebsiteMembers as $wm) {
            $members[] = [
                'name' => $wm['name'],
                'role' => $wm['role_title'] ?: 'Architect',
                'specialty' => $wm['specialty'] ?: 'Professional Architect',
                'prc' => $wm['id_number'] ?: '—',
                'location' => $wm['location'] ?: 'Mindoro',
                'status' => 'Good Member',
                'achievements' => $wm['achievements'] ?: 'No achievements listed.',
                'awards' => $wm['awards'] ?: 'No awards listed.',
                'qr_image_path' => $wm['qr_image_path'] ?? ''
            ];
        }
    }
} catch (Exception $e) {
    // Ignore until website_members table exists
}

// Fetch sponsors from database
$sponsors = [];
try {
    $sponsors = $pdo->query("SELECT * FROM sponsors WHERE is_active = 1 ORDER BY display_order ASC LIMIT 6")->fetchAll();
} catch (Exception $e) {
    // Table might not exist yet
}

// Fetch about us from database
$aboutUsText = 'The United Architects of the Philippines (UAP) Mindoro Chapter brings together registered architects across Oriental and Occidental Mindoro. We are dedicated to advocating architectural excellence, professional integrity, and community resilience.';
try {
    $about = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'about_us'")->fetch();
    if ($about && !empty($about['setting_value'])) {
        $aboutUsText = $about['setting_value'];
    }
} catch (Exception $e) {
    // Table might not exist yet
}

// Fetch news and announcements from database
$newsItems = [
    [
        'title' => 'Mindoro Architecture Week 2026',
        'date' => 'AUG 12 - 18, 2026',
        'summary' => 'Annual symposium featuring sustainable building practices tailored to island province developments.'
    ],
    [
        'title' => 'CPD Accredited Seminar: Green Building Code',
        'date' => 'SEP 05, 2026',
        'summary' => 'Comprehensive discussion on local energy efficiency standards and resilient design.'
    ],
    [
        'title' => 'Heritage Mapping Project Launch',
        'date' => 'OCT 14, 2026',
        'summary' => 'Documenting historical structures and architectural heritage across Oriental and Occidental Mindoro.'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
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
        }

        a { color: inherit; text-decoration: none; }

        .member-modal {
            position: fixed;
            inset: 0;
            background: rgba(4, 8, 12, 0.78);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 24px;
        }

        .member-modal.visible {
            display: flex;
        }

        .member-modal-content {
            width: min(860px, 100%);
            background: linear-gradient(180deg, rgba(18, 29, 35, 0.98), rgba(11, 18, 24, 0.98));
            border: 1px solid rgba(245, 184, 0, 0.45);
            border-radius: 20px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.42), inset 0 1px 0 rgba(255,255,255,0.04);
            padding: 28px 30px 24px;
            position: relative;
        }

        .member-close {
            position: absolute;
            right: 18px;
            top: 16px;
            width: 42px;
            height: 42px;
            border: 1px solid rgba(245, 184, 0, 0.4);
            background: rgba(245, 184, 0, 0.08);
            color: #ffffff;
            font-size: 30px;
            line-height: 1;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .member-close:hover {
            background: rgba(245, 184, 0, 0.18);
            transform: rotate(90deg);
        }

        .member-modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 18px;
            margin-bottom: 22px;
        }

        .member-modal-header h3 {
            margin: 0;
            font-size: clamp(2rem, 3vw, 3rem);
            font-weight: 800;
            line-height: 1.1;
            color: #f7f7f7;
            letter-spacing: -0.04em;
        }

        .member-modal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px 28px;
            margin-bottom: 20px;
        }

        .member-detail-label {
            color: rgba(229, 232, 236, 0.7);
            display: block;
            font-size: 0.72rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 700;
        }

        .member-detail-value {
            color: #f5f5f5;
            font-size: 1.12rem;
            line-height: 1.5;
            word-break: break-word;
        }

        .member-detail-section {
            margin-top: 18px;
        }

        .member-qr-box {
            border: 1px dashed rgba(245, 184, 0, 0.5);
            border-radius: 12px;
            min-height: 170px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(245, 184, 0, 0.02);
            color: rgba(255,255,255,0.62);
            text-align: center;
            padding: 18px;
            font-size: 0.95rem;
        }

        .member-qr-box img {
            max-width: 160px;
            max-height: 160px;
            object-fit: contain;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

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
            flex: 1 1 auto;
        }

        .brand-logo img {
            height: 42px;
            width: auto;
            object-fit: contain;
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
            flex: 1 1 auto;
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

        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.82), rgba(8, 8, 8, 0.95)), url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?q=80&w=1600&auto=format&fit=crop') center/cover;
            padding: 4rem 2rem;
            text-align: center;
            border-bottom: 1px solid var(--border-dark);
        }

        .hero-content { max-width: 850px; margin: 0 auto; }

        .hero h1 {
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero h1 span { color: var(--accent-yellow); }

        .hero p {
            color: var(--text-muted);
            font-size: 1.05rem;
            margin-bottom: 1.8rem;
        }

        .btn-yellow {
            display: inline-block;
            padding: 0.8rem 2.2rem;
            background-color: var(--accent-yellow);
            color: #000000;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        .btn-yellow:hover { background-color: var(--accent-yellow-hover); }

        .main-container {
            width: 100%;
            max-width: 1800px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem;
        }

        .page-layout {
            display: grid;
            grid-template-columns: 280px 1fr 280px;
            gap: 1.5rem;
            align-items: start;
        }

        .section-heading {
            border-left: 4px solid var(--accent-yellow);
            padding-left: 1rem;
            margin-bottom: 1.5rem;
        }

        .section-heading h2 {
            font-size: 1.35rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #ffffff;
        }

        .dark-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-dark);
            border-radius: 8px;
            padding: 1.8rem;
            margin-bottom: 2rem;
        }

        .table-responsive { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background-color: #000000;
            color: var(--accent-yellow);
            padding: 0.85rem;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid var(--accent-yellow);
        }

        td {
            padding: 0.85rem;
            border-bottom: 1px solid var(--border-dark);
            font-size: 0.9rem;
        }

        tr:hover td { background-color: var(--bg-inner-card); }

        .badge-status {
            background-color: rgba(245, 184, 0, 0.15);
            color: var(--accent-yellow);
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.72rem;
            font-weight: bold;
            border: 1px solid var(--accent-yellow);
        }

        .about-text {
            font-size: 0.95rem;
            line-height: 1.7;
            color: var(--text-muted);
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .news-card {
            background-color: var(--bg-inner-card);
            border: 1px solid var(--border-yellow-subtle);
            border-radius: 6px;
            padding: 1.2rem;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .news-card:hover {
            border-color: var(--accent-yellow);
            transform: translateY(-2px);
        }

        .news-date {
            font-size: 0.75rem;
            font-weight: bold;
            color: var(--accent-yellow);
            display: block;
            margin-bottom: 0.4rem;
        }

        .news-card h3 {
            font-size: 0.98rem;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .news-card p {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .sponsors-sidebar {
            position: sticky;
            top: 5rem;
            background-color: var(--bg-card);
            border: 2px dashed var(--accent-yellow);
            border-radius: 8px;
            padding: 1.5rem 1rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.2rem;
        }

        .sponsors-sidebar h3 {
            color: var(--accent-yellow);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--border-dark);
            padding-bottom: 0.5rem;
            width: 100%;
        }

        .sponsor-box {
            width: 100%;
            height: 140px;
            background-color: var(--bg-inner-card);
            border: 1px solid var(--border-dark);
            border-radius: 6px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text-muted);
            font-weight: bold;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: border-color 0.2s ease, background-color 0.2s ease;
        }

        .sponsor-box:hover {
            border-color: var(--accent-yellow);
            background-color: #222222;
            color: #ffffff;
        }

        footer {
            background-color: #000000;
            border-top: 3px solid var(--accent-yellow);
            padding: 3.5rem 2rem 1.5rem;
            color: var(--text-light);
        }

        .footer-container {
            max-width: 1800px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2.5rem;
            margin-bottom: 2.5rem;
        }

        .footer-col h4 {
            color: var(--accent-yellow);
            margin-bottom: 1.2rem;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--border-dark);
            padding-bottom: 0.4rem;
        }

        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 0.6rem; }
        .footer-col ul li a {
            color: var(--text-muted);
            font-size: 0.88rem;
            transition: color 0.2s ease;
        }

        .footer-col ul li a:hover { color: var(--accent-yellow); }

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

        @media (max-width: 1200px) {
            .page-layout { grid-template-columns: 1fr; }
            .sponsors-sidebar { position: static; }
        }

        @media (max-width: 768px) {
            nav ul { display: none; }
            .hero h1 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <div class="brand-logo">
                <img src="<?php echo BASE_URL; ?>/uploads/uap_logo.jpg" alt="UAP Mindoro Logo" onerror="this.style.display='none'">
                <div class="brand-title">UAP-<span>Mindoro Chapter</span></div>
            </div>

            <div class="header-actions">
                <nav>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#members">Members</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#news">News</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </nav>
                <a href="<?php echo BASE_URL; ?>/auth/login.php" class="login-btn">Login</a>
            </div>
        </div>
    </header>

    <section class="hero" id="home">
        <div class="hero-content">
            <h1>United Architects of the Philippines <span>Mindoro Chapter</span></h1>
            <p>Integrated & Accredited Professional Organization of Architects (IAPOA Chapter 121) promoting design excellence and sustainable development across Mindoro.</p>
            <a href="#members" class="btn-yellow">View Chapter Directory</a>
        </div>
    </section>

    <div class="main-container">
        <div class="page-layout">
            <aside class="sponsors-sidebar">
                <h3>Major Sponsors</h3>
                <?php 
                $displaySponsors = array_slice($sponsors, 0, 3);
                if (empty($displaySponsors)): 
                ?>
                    <div class="sponsor-box">No sponsors yet</div>
                <?php else: ?>
                    <?php foreach ($displaySponsors as $sponsor): ?>
                        <div class="sponsor-box" style="padding:6px;height:auto;">
                            <?php if (!empty(trim((string)($sponsor['url'] ?? '')))): ?>
                                <a href="<?php echo htmlspecialchars(trim((string)$sponsor['url'])); ?>" target="_blank" rel="noopener noreferrer" style="display:block;">
                                    <img src="../<?php echo htmlspecialchars($sponsor['logo_path']); ?>" alt="<?php echo htmlspecialchars($sponsor['name']); ?>" style="max-width:100%;max-height:120px;object-fit:contain;display:block;cursor:pointer;">
                                </a>
                            <?php else: ?>
                                <img src="../<?php echo htmlspecialchars($sponsor['logo_path']); ?>" alt="<?php echo htmlspecialchars($sponsor['name']); ?>" style="max-width:100%;max-height:120px;object-fit:contain;display:block;">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>

            <main class="center-content">
                <section class="dark-card" id="members">
                    <div class="section-heading">
                        <h2>Chapter Members Directory</h2>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Architect Name</th>
                                    <th>Role / Title</th>
                                    <th>Architectural Specialty</th>
                                    <th>PRC No.</th>
                                    <th>Location</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($member['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($member['role']); ?></td>
                                        <td><?php echo htmlspecialchars($member['specialty']); ?></td>
                                        <td><?php echo htmlspecialchars($member['prc']); ?></td>
                                        <td><?php echo htmlspecialchars($member['location']); ?></td>
                                        <td>
                                            <button
                                                class="badge-status view-member-btn"
                                                type="button"
                                                data-name="<?php echo htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-role="<?php echo htmlspecialchars($member['role'] ?? 'Architect', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-specialty="<?php echo htmlspecialchars($member['specialty'] ?? 'Professional Architect', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-prc="<?php echo htmlspecialchars($member['prc'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-location="<?php echo htmlspecialchars($member['location'] ?? 'Mindoro', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-achievements="<?php echo htmlspecialchars($member['achievements'] ?? 'No achievements listed.', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-awards="<?php echo htmlspecialchars($member['awards'] ?? 'No awards listed.', ENT_QUOTES, 'UTF-8'); ?>"
                                                data-qr="<?php echo htmlspecialchars($member['qr_image_path'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                style="cursor:pointer;"
                                            >
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="dark-card" id="about">
                    <div class="section-heading">
                        <h2>About Us</h2>
                    </div>
                    <div class="about-text">
                        <p><?php echo htmlspecialchars($aboutUsText); ?></p>
                    </div>
                </section>

                <section class="dark-card" id="news">
                    <div class="section-heading">
                        <h2>Latest News & Announcements</h2>
                    </div>
                    <div class="news-grid">
                        <?php 
                        // Try to fetch from database, fallback to defaults
                        $dbNews = [];
                        try {
                            $dbNews = $pdo->query("SELECT * FROM news_announcements WHERE is_active = 1 ORDER BY display_order ASC")->fetchAll();
                        } catch (Exception $e) {}
                        
                        $newsToShow = !empty($dbNews) ? $dbNews : $newsItems;
                        foreach ($newsToShow as $news): 
                        ?>
                            <article class="news-card">
                                <span class="news-date"><?php echo isset($news['date_posted']) ? date('M d, Y', strtotime($news['date_posted'])) : htmlspecialchars($news['date'] ?? ''); ?></span>
                                <h3><?php echo htmlspecialchars($news['title']); ?></h3>
                                <p><?php echo htmlspecialchars($news['summary']); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </main>

            <aside class="sponsors-sidebar">
                <h3>Official Partners</h3>
                <?php 
                $displayPartners = array_slice($sponsors, 3, 3);
                if (empty($displayPartners)): 
                ?>
                    <div class="sponsor-box">No partners yet</div>
                <?php else: ?>
                    <?php foreach ($displayPartners as $partner): ?>
                        <div class="sponsor-box" style="padding:6px;height:auto;">
                            <?php if (!empty(trim((string)($partner['url'] ?? '')))): ?>
                                <a href="<?php echo htmlspecialchars(trim((string)$partner['url'])); ?>" target="_blank" rel="noopener noreferrer" style="display:block;">
                                    <img src="../<?php echo htmlspecialchars($partner['logo_path']); ?>" alt="<?php echo htmlspecialchars($partner['name']); ?>" style="max-width:100%;max-height:120px;object-fit:contain;display:block;cursor:pointer;">
                                </a>
                            <?php else: ?>
                                <img src="../<?php echo htmlspecialchars($partner['logo_path']); ?>" alt="<?php echo htmlspecialchars($partner['name']); ?>" style="max-width:100%;max-height:120px;object-fit:contain;display:block;">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>
        </div>
    </div>

    <div id="memberModal" class="member-modal" aria-hidden="true">
        <div class="member-modal-content" role="dialog" aria-modal="true" aria-labelledby="memberModalTitle">
            <button type="button" class="member-close" aria-label="Close">&times;</button>
            <div class="member-modal-header">
                <h3 id="memberModalTitle" style="margin:0; font-size:1.8rem; color:#f5f5f5;">Member Details</h3>
            </div>

            <div class="member-modal-grid">
                <div>
                    <span class="member-detail-label">Architect Name</span>
                    <div class="member-detail-value" id="memberDetailName"></div>
                </div>
                <div>
                    <span class="member-detail-label">Role / Title</span>
                    <div class="member-detail-value" id="memberDetailRole"></div>
                </div>
                <div>
                    <span class="member-detail-label">Architectural Specialty</span>
                    <div class="member-detail-value" id="memberDetailSpecialty"></div>
                </div>
                <div>
                    <span class="member-detail-label">PRC No.</span>
                    <div class="member-detail-value" id="memberDetailPrc"></div>
                </div>
                <div>
                    <span class="member-detail-label">Location</span>
                    <div class="member-detail-value" id="memberDetailLocation"></div>
                </div>
                <div>
                    <span class="member-detail-label">QR</span>
                    <div class="member-qr-box" id="memberDetailQr">QR image placeholder</div>
                </div>
            </div>

            <div class="member-detail-section" style="margin-bottom: 14px;">
                <span class="member-detail-label">Achievements</span>
                <div class="member-detail-value" id="memberDetailAchievements"></div>
            </div>

            <div class="member-detail-section">
                <span class="member-detail-label">Awards</span>
                <div class="member-detail-value" id="memberDetailAwards"></div>
            </div>
        </div>
    </div>

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
                    <p>📞 <strong>Phone:</strong> +63 917 123 4567</p>
                    <p>🌐 <strong>Facebook:</strong> <a href="https://www.facebook.com/UAPMindoroChapter/" target="_blank" style="color: var(--accent-yellow);">facebook.com/UAPMindoroChapter</a></p>
                </div>
            </div>

            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#members">Member Directory</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#news">News & Announcements</a></li>
                </ul>
            </div>
        </div>

        <div class="copyright">
            &copy; <?php echo date('Y'); ?> United Architects of the Philippines - Mindoro Chapter. All rights reserved.
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('memberModal');
            const closeButton = document.querySelector('.member-close');
            const buttons = document.querySelectorAll('.view-member-btn');

            buttons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const name = button.dataset.name || 'Member';
                    const role = button.dataset.role || 'Architect';
                    const specialty = button.dataset.specialty || 'Professional Architect';
                    const prc = button.dataset.prc || '—';
                    const location = button.dataset.location || 'Mindoro';
                    const achievements = button.dataset.achievements || 'No achievements listed.';
                    const awards = button.dataset.awards || 'No awards listed.';
                    const qr = button.dataset.qr || '';

                    document.getElementById('memberModalTitle').textContent = name;
                    document.getElementById('memberDetailName').textContent = name;
                    document.getElementById('memberDetailRole').textContent = role;
                    document.getElementById('memberDetailSpecialty').textContent = specialty;
                    document.getElementById('memberDetailPrc').textContent = prc;
                    document.getElementById('memberDetailLocation').textContent = location;
                    document.getElementById('memberDetailAchievements').textContent = achievements;
                    document.getElementById('memberDetailAwards').textContent = awards;

                    const qrBox = document.getElementById('memberDetailQr');
                    if (qr) {
                        qrBox.innerHTML = '<img src="../' + qr + '" alt="' + name + ' QR Code" style="max-width:120px; max-height:120px; object-fit:contain;">';
                    } else {
                        qrBox.textContent = 'QR image placeholder';
                    }

                    modal.classList.add('visible');
                    modal.setAttribute('aria-hidden', 'false');
                });
            });

            function closeModal() {
                modal.classList.remove('visible');
                modal.setAttribute('aria-hidden', 'true');
            }

            closeButton.addEventListener('click', closeModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('visible')) {
                    closeModal();
                }
            });
        });
    </script>
</body>
</html>
