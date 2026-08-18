<?php
/**
 * UAP-MC Application Router
 * 
 * This is the central entry point for the application.
 * It routes requests based on authentication status and user role.
 * 
 * Application Structure:
 * ├── /auth/           - Authentication pages (login, register, logout)
 * ├── /admin/          - Admin dashboard and management pages
 * ├── /member/         - Member dashboard and user pages
 * ├── /public/         - Public website pages (homepage, etc.)
 * ├── /includes/       - Shared resources (config, auth functions, header, footer)
 * ├── /database/       - Database migrations and seeds
 * ├── /uploads/        - User uploaded files
 * └── /images/         - Static images
 */

require_once __DIR__ . '/includes/config.php';

// Do not force a redirect from the root page so multiple sections can be opened
// in different tabs/windows of the same browser session without interfering.
if (isset($_GET['portal'])) {
    $portal = strtolower($_GET['portal']);

    if ($portal === 'admin') {
        require_once __DIR__ . '/includes/auth.php';
        require_admin();
        include __DIR__ . '/admin/dashboard.php';
        exit;
    }

    if ($portal === 'member') {
        require_once __DIR__ . '/includes/auth.php';
        require_member();
        include __DIR__ . '/member/dashboard.php';
        exit;
    }
}

// Default: always show the public homepage.
// Logged-in users can still open admin/member pages directly by URL or tabs.
include __DIR__ . '/public/homepage.php';
