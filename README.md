# Architecture Org Dues System

A simple PHP + MySQL website for tracking membership dues with manual payment verification (GCash, Maya, Card, Online Banking).

## What's included

- Member registration/login
- Member dashboard: view unpaid/pending/paid dues, submit payment with proof upload
- Admin dashboard: verify/reject pending payments
- Auto-generated official receipts (sequential numbering: ORG-2026-00001)
- Members overview (paid/unpaid status per member)
- Reports page + CSV export (opens directly in Excel)

## How payment verification works

This uses **manual verification**, not a live payment gateway (no PayMongo/Xendit needed):

1. Member selects a payment method, enters their reference/transaction number, and uploads a screenshot of their GCash/Maya/bank transfer.
2. This goes into a "Pending" queue.
3. Admin manually checks the actual GCash/Maya/bank account, then clicks Approve or Reject.
4. On approval, the system automatically marks the due as paid and generates an official receipt.

## Setup Instructions

### 1. Requirements
- PHP 7.4+ with PDO MySQL extension (any shared hosting like Hostinger, InfinityFree, or XAMPP locally works)
- MySQL database

### 2. Database setup
1. Create a MySQL database (e.g. `dues_system`).
2. Import `schema.sql` into it (via phpMyAdmin: Import tab, or `mysql -u root -p dues_system < schema.sql`).
3. This creates all tables and one default admin account:
   - Email: `admin@org.com`
   - Password: `admin123`
   - **Change this password immediately after first login** — there's no "change password" UI yet, so for now update it directly in the database, or update it via a quick PHP password_hash() snippet.

### 3. Configure database connection
Open `includes/config.php` and edit these lines to match your hosting/database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'dues_system');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Upload files
Upload the entire `dues-system` folder contents to your web host's public folder (e.g. `public_html`, or `htdocs` for local XAMPP).

Make sure the `uploads/` folder is writable (chmod 755 or 777 depending on host) — this is where payment proof screenshots get stored.

### 5. Test it
- Visit your site URL → redirects to login
- Log in as admin (admin@org.com / admin123)
- Go to "Dues" → create a due item (e.g. "1st Sem Dues", ₱500)
- Register a test member account
- Log in as that member → you'll see the due, click "Pay Now", fill the form, upload any test image
- Log back in as admin → "Pending Payments" → Approve it
- Member can now view their receipt under "Payment History"

## Local testing with XAMPP (recommended before deploying)

1. Install XAMPP, start Apache + MySQL
2. Copy this folder into `htdocs/dues-system`
3. Open phpMyAdmin (localhost/phpmyadmin), create database `dues_system`, import schema.sql
4. Visit `localhost/dues-system` in your browser

## Things to add later if there's time

- Change password feature for admin/members
- Email notifications when a payment is approved/rejected
- "Forgot password" flow
- Filtering/search on the Members and Reports pages
- PDF receipts instead of HTML print view (optional — current print view works fine via browser's Print to PDF)
- If the org later gets DTI/SEC registration, this can be upgraded to live PayMongo/Xendit gateway integration — the database schema already has a `method` field ready for that transition.

## File structure

```
UAP-MC/
├── index.php              (central router — redirects based on login state)
│
├── auth/                  (Authentication pages — login, register, logout)
│   ├── login.php
│   ├── register.php
│   └── logout.php
│
├── public/                (Public website pages)
│   └── homepage.php       (public homepage for non-logged-in users)
│
├── member/                (Member dashboard and pages)
│   ├── dashboard.php
│   ├── pay.php
│   └── history.php
│
├── admin/                 (Admin dashboard and management)
│   ├── dashboard.php
│   ├── verify.php
│   ├── approvals.php
│   ├── members.php
│   ├── dues.php
│   ├── reports.php
│   ├── export_csv.php
│   ├── settings.php
│   ├── qr_codes.php
│   ├── account_manager.php
│   └── change_password.php
│
├── includes/              (Shared resources)
│   ├── config.php         (DB credentials — edit this)
│   ├── auth.php           (login guards & auth functions)
│   ├── header.php         (header template)
│   ├── footer.php         (footer template)
│   └── theme.css
│
├── database/              (Database setup & migrations)
│   ├── migrate.php
│   ├── seed.php
│   ├── setup.php
│   ├── migrations/
│   │   └── 001_create_initial_schema.sql
│   └── seeds/
│       └── 001_admin_seed.sql
│
├── receipt.php            (printable receipt — used by member/admin)
├── schema.sql             (main database schema)
├── stress-test.php        (testing utility)
├── cookies.txt            (configuration/cache)
│
├── uploads/               (payment proof images — must be writable)
├── images/                (static images)
├── query/                 (helper scripts)
│
└── README.md              (this file)
```

### Backward Compatibility

The old root-level files (`login.php`, `register.php`, `logout.php`, `public_homepage.php`) are preserved as redirects to the new locations. This ensures any external links or bookmarks continue to work.
