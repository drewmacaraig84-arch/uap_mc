# Deploying to InfinityFree (Free Hosting + Domain)

A step-by-step checklist specifically for InfinityFree, with checkboxes so you can track progress.

## 1. Create your account
- [ ] Go to infinityfree.net and sign up (no credit card needed)
- [ ] Create a new hosting account
- [ ] Choose a free subdomain, e.g. `archorg.infinityfreeapp.com` (or whatever variant they currently offer)
- [ ] Wait for account activation (usually a few minutes)

## 2. Create the production database
- [ ] In the control panel (VistaPanel), go to **MySQL Databases**
- [ ] Create a new database — note down:
  - Database host (NOT "localhost" — usually something like `sqlXXX.infinityfree.com`)
  - Database name (usually a long string like `if0_12345678_dues_system`)
  - Database username (often same as the database name)
  - Database password (you set this)
- [ ] Open **phpMyAdmin** from the panel
- [ ] Select your new database → **Import** tab → upload `schema.sql` → Go
- [ ] Confirm all 6 tables were created: `users`, `dues`, `member_dues`, `payments`, `receipts`, `qr_codes`

## 3. Update config.php
- [ ] Open `includes/config.php` in VS Code
- [ ] Comment out the 4 local XAMPP lines (add `//` in front of each)
- [ ] Uncomment the 4 InfinityFree lines below them
- [ ] Fill in the actual host/name/user/password values from Step 2
- [ ] Save the file

## 4. Upload the files
- [ ] In the control panel, open **File Manager** (or use FTP/FileZilla with credentials from the panel)
- [ ] Navigate into the `htdocs` folder
- [ ] Upload **all** project files and folders directly into `htdocs` (not inside a subfolder) — `index.php` should sit right at the top level
- [ ] Confirm `uploads/` folder uploaded too (this is where payment proof screenshots get stored)

## 5. Enable HTTPS
- [ ] In the control panel, find the **SSL** section
- [ ] Enable free SSL (Let's Encrypt) for your subdomain
- [ ] Note: this can take a few minutes up to a few hours to activate — be patient

## 6. Test it live
- [ ] Visit your subdomain in a browser
- [ ] Confirm the login page loads
- [ ] Log in as admin (`ADMIN001` / `admin123`)
- [ ] Immediately change the admin password (directly in phpMyAdmin — see client-guide.md for instructions)
- [ ] Create a test due item
- [ ] Register a test member account (using a test ID Number)
- [ ] Submit a test payment with a dummy proof image
- [ ] Approve it as admin, confirm the receipt generates
- [ ] Try the CSV export from Reports

## 7. Upload real QR codes
- [ ] Log in as admin → QR Codes
- [ ] Upload your org's actual GCash, Maya, and Online Banking QR code images

## Known limitations to keep in mind

- **10-second script execution limit** — won't affect this app, nothing here runs that long
- **No SSH/terminal access** — not needed, this is plain PHP with no build step
- **Free hosting can occasionally be slower** than paid hosting — acceptable for a school org's traffic level
- **Inode limits** (file count caps) — this project is small, won't come close to hitting it

## If something breaks after deployment

The most common issues when moving from local to live hosting:
1. **"Database connection failed"** — double-check the host/name/user/password in `config.php` exactly match what InfinityFree's panel shows (these are easy to mistype since they're long strings)
2. **Links going to the wrong place** — the app auto-detects its folder path, but if anything looks off, use the manual override near the top of `config.php`: uncomment `BASE_URL_OVERRIDE` and set it to `''` (empty string) since the site will be at the root of your subdomain
3. **Upload errors on payment proof / QR codes** — check that the `uploads` folder has write permissions (in File Manager, right-click → Permissions/CHMOD → set to 755)
