<?php
$page_title = $page_title ?? 'Dues System';
?>
<!DOCTYPE html>
<html lang="en" id="htmlElement">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title); ?></title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/includes/theme.css">
<script>
// Apply theme immediately before page renders to prevent flash
(function() {
  const storedTheme = localStorage.getItem('theme');
  const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const theme = storedTheme || (systemPrefersDark ? 'dark' : 'light');
  document.getElementById('htmlElement').setAttribute('data-theme', theme);
  document.documentElement.style.colorScheme = theme;
})();
</script>
<style>
  :root {
    --app-bg: #eef3f6;
    --app-text: #18243a;
    --sidebar-bg: #1b2430;
    --sidebar-text: #dfe7f0;
    --sidebar-active-bg: rgba(255,255,255,0.09);
    --sidebar-active-text: #f5f0dc;
    --header-bg: rgba(247, 249, 251, 0.96);
    --header-text: #111827;
    --header-border: #dfe3e8;
    --field-bg: rgba(255,255,255,0.75);
    --field-border: #dfe3e8;
    --card-bg: rgba(255,255,255,0.96);
    --card-text: #1f2937;
    --muted-text: #5b6c89;
    --button-primary: #f2b835;
    --button-primary-text: #1f2937;
    --button-shadow: rgba(242,184,53,0.25);
    --nav-link: #dfeafc;
  }

  @media (prefers-color-scheme: dark) {
    :root {
      --app-bg: #1d2a34;
      --app-text: #edf3f9;
      --sidebar-bg: #1b2430;
      --sidebar-text: #dfe7f0;
      --sidebar-active-bg: rgba(255,255,255,0.09);
      --sidebar-active-text: #f5f0dc;
      --header-bg: rgba(19, 28, 36, 0.92);
      --header-text: #edf3f9;
      --header-border: rgba(255,255,255,0.08);
      --field-bg: rgba(14, 21, 28, 0.88);
      --field-border: rgba(255,255,255,0.12);
      --card-bg: rgba(27, 42, 52, 0.97);
      --card-text: #edf3f9;
      --muted-text: #bfd0de;
      --button-primary: #f2b835;
      --button-primary-text: #1f2937;
      --button-shadow: rgba(242,184,53,0.25);
      --nav-link: #dfeafc;
    }
  }

  html[data-theme="dark"] {
    --app-bg: #1d2a34;
    --app-text: #edf3f9;
    --sidebar-bg: #1b2430;
    --sidebar-text: #dfe7f0;
    --sidebar-active-bg: rgba(255,255,255,0.09);
    --sidebar-active-text: #f5f0dc;
    --header-bg: rgba(19, 28, 36, 0.92);
    --header-text: #edf3f9;
    --header-border: rgba(255,255,255,0.08);
    --field-bg: rgba(14, 21, 28, 0.88);
    --field-border: rgba(255,255,255,0.12);
    --card-bg: rgba(27, 42, 52, 0.97);
    --card-text: #edf3f9;
    --muted-text: #bfd0de;
    --button-primary: #f2b835;
    --button-primary-text: #1f2937;
    --button-shadow: rgba(242,184,53,0.25);
    --nav-link: #dfeafc;
  }

  html[data-theme="light"] {
    --app-bg: #eef3f6;
    --app-text: #18243a;
    --sidebar-bg: #1b2430;
    --sidebar-text: #dfe7f0;
    --sidebar-active-bg: rgba(255,255,255,0.09);
    --sidebar-active-text: #f5f0dc;
    --header-bg: rgba(247, 249, 251, 0.96);
    --header-text: #111827;
    --header-border: #dfe3e8;
    --field-bg: rgba(255,255,255,0.75);
    --field-border: #dfe3e8;
    --card-bg: rgba(255,255,255,0.96);
    --card-text: #1f2937;
    --muted-text: #5b6c89;
    --button-primary: #f2b835;
    --button-primary-text: #1f2937;
    --button-shadow: rgba(242,184,53,0.25);
    --nav-link: #dfeafc;
  }

  * { box-sizing: border-box; }
  body {
    font-family: Inter, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    background: var(--app-bg);
    margin: 0;
    color: var(--app-text);
    min-height: 100vh;
    line-height: 1.55;
    padding-left: 240px;
    padding-top: 80px;
    transition: background 0.2s ease, color 0.2s ease;
  }
  a { color: var(--nav-link); }
  nav {
    background: var(--sidebar-bg);
    color: var(--sidebar-text);
    padding: 18px 18px 12px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: stretch;
    gap: 18px;
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 240px;
    z-index: 100;
    border-right: 1px solid rgba(255,255,255,0.08);
    box-shadow: none;
  }
  .nav-links { display: flex; flex-direction: column; gap: 4px; align-items: stretch; width: 100%; }
  .nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--sidebar-text);
    text-decoration: none;
    font-size: 14px;
    padding: 12px 14px;
    border-radius: 10px;
    transition: all 0.15s ease;
    min-height: 42px;
  }
  .nav-item:hover { background: rgba(255,255,255,0.05); }
  .nav-item.active {
    background: var(--sidebar-active-bg);
    color: var(--sidebar-active-text);
    font-weight: 600;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.04);
  }
  .nav-icon {
    width: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    line-height: 1;
  }
  .nav-brand {
    font-weight: 800;
    font-size: 17px;
    letter-spacing: 0.2px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #f7fbff;
    padding: 6px 4px 18px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
  }
  .nav-brand img { height: 34px; width: 34px; object-fit: contain; border-radius: 50%; background: #fff; padding: 2px; box-shadow: 0 0 0 1px rgba(0,0,0,0.06); }
  .nav-title { display: flex; flex-direction: column; line-height: 1.2; color: #fff; }
  .nav-subtitle { display: none; }
  body[data-auth="false"] .topbar {
    left: 0 !important;
  }
  .topbar {
    position: fixed;
    top: 0;
    left: 240px;
    right: 0;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 18px;
    background: var(--header-bg);
    color: var(--header-text);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--header-border);
    padding: 0 24px;
    z-index: 90;
  }
  .topbar-search {
    display: flex;
    align-items: center;
    gap: 10px;
    width: min(420px, 52vw);
    height: 42px;
    padding: 0 14px;
    border: 1px solid var(--field-border);
    border-radius: 999px;
    background: var(--field-bg);
    color: var(--muted-text);
  }
  .topbar-search input {
    border: 0;
    background: transparent;
    width: 100%;
    padding: 0;
    outline: none;
    font-size: 14px;
    color: var(--header-text);
  }
  .notification-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding: 0 7px;
    border-radius: 999px;
    background: var(--button-primary);
    color: var(--button-primary-text);
    font-size: 11px;
    font-weight: 800;
    box-shadow: 0 4px 12px var(--button-shadow);
  }
  .notification-bell {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: transparent;
    border: 1px solid var(--field-border);
    cursor: pointer;
    font-size: 20px;
    transition: all 0.2s ease;
  }
  .notification-bell:hover {
    background: var(--field-bg);
    transform: scale(1.05);
  }
  .notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    padding: 0 6px;
    border-radius: 999px;
    background: #f2b835;
    color: #111827;
    font-size: 12px;
    font-weight: 800;
    box-shadow: 0 4px 12px rgba(242, 184, 53, 0.3);
  }
  .user-chip {
    position: relative;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px 6px 6px;
    border-radius: 999px;
    background: var(--field-bg);
    border: 1px solid var(--field-border);
    cursor: pointer;
  }
  .user-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #111827;
    color: #fff;
    font-weight: 700;
    font-size: 12px;
  }
  .user-menu {
    position: absolute;
    right: 0;
    top: calc(100% + 12px);
    min-width: 220px;
    background: var(--card-bg);
    color: var(--card-text);
    border: 1px solid var(--field-border);
    border-radius: 14px;
    box-shadow: 0 16px 30px rgba(0,0,0,0.12);
    padding: 10px;
    display: none;
    z-index: 999;
  }
  .user-chip.open .user-menu {
    display: block;
  }
  .user-menu-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 10px 12px;
    border: 0;
    background: transparent;
    color: var(--card-text);
    border-radius: 10px;
    text-align: left;
    font-size: 14px;
    cursor: pointer;
  }
  .user-menu-item:hover {
    background: rgba(255,255,255,0.04);
  }
  .user-menu-divider {
    height: 1px;
    background: var(--field-border);
    margin: 8px 0;
  }
  .theme-switch {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  .theme-switch .switch-label {
    font-size: 14px;
  }
  .theme-toggle-switch {
    position: relative;
    width: 42px;
    height: 24px;
    border-radius: 999px;
    background: rgba(148,163,184,0.35);
    border: 0;
    cursor: pointer;
    transition: background 0.2s ease;
  }
  .theme-toggle-switch::after {
    content: "";
    position: absolute;
    top: 3px;
    left: 3px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    transition: transform 0.2s ease;
  }
  .theme-toggle-switch.active {
    background: #f2b835;
  }
  .theme-toggle-switch.active::after {
    transform: translateX(18px);
  }
  .logout-btn {
    display: block;
    width: 100%;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: 10px;
    padding: 10px 12px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    text-decoration: none;
    text-align: center;
    transition: background 0.15s ease;
    box-sizing: border-box;
  }
  .logout-btn:hover {
    background: rgba(239, 68, 68, 0.15);
  }
  .logout-form {
    display: contents;
  }
  .change-password-link {
    display: block;
    width: 100%;
    padding: 10px 12px;
    text-decoration: none;
    color: var(--card-text);
    border: 0;
    background: transparent;
    border-radius: 10px;
    text-align: left;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.15s ease;
  }
  .change-password-link:hover {
    background: rgba(255,255,255,0.05);
  }
  .notification-dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 12px);
    min-width: 380px;
    max-height: 480px;
    background: var(--card-bg);
    border: 1px solid var(--field-border);
    border-radius: 14px;
    box-shadow: 0 16px 30px rgba(0,0,0,0.12);
    display: none !important;
    z-index: 999;
    overflow: hidden;
  }
  .notification-bell.open .notification-dropdown {
    display: flex !important;
    flex-direction: column;
  }
  .notification-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--field-border);
    font-weight: 700;
    color: var(--text-primary);
    font-size: 14px;
  }
  .notification-list {
    overflow-y: auto;
    flex: 1;
  }
  .notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    cursor: pointer;
    transition: background 0.15s ease;
  }
  .notification-item:hover {
    background: rgba(255,255,255,0.05);
  }
  .notification-item:last-child {
    border-bottom: none;
  }
  .notification-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 6px;
  }
  .notification-type.approval {
    background: rgba(30, 126, 52, 0.15);
    color: #1e7e34;
  }
  .notification-type.payment {
    background: rgba(59, 93, 168, 0.15);
    color: #3b5da8;
  }
  .notification-member {
    font-weight: 600;
    font-size: 13px;
    color: var(--text-primary);
    margin-bottom: 4px;
  }
  .notification-meta {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 4px;
  }
  .notification-empty {
    padding: 24px 16px;
    text-align: center;
    color: var(--text-secondary);
    font-size: 13px;
  }
  .menu-toggle {
    display: none;
    border: 0;
    background: rgba(255,255,255,0.16);
    color: #fff;
    width: 42px;
    height: 42px;
    border-radius: 10px;
    font-size: 20px;
    cursor: pointer;
  }
  .container { max-width: 1180px; margin: 28px auto 40px; padding: 0 16px; }
  .card {
    background: rgba(27, 42, 52, 0.97);
    border-radius: 18px;
    padding: 24px 28px;
    margin-bottom: 22px;
    box-shadow: 0 10px 24px rgba(0,0,0,0.12);
    border: 1px solid rgba(255,255,255,0.06);
    overflow: hidden;
    color: #edf3f9;
  }
  .page-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    margin-bottom: 20px;
    flex-wrap: wrap;
  }
  .eyebrow {
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 11px;
    font-weight: 800;
    color: #d0dbeb;
    margin: 0 0 6px;
  }
  h1 { font-size: 24px; margin: 0 0 6px; color: #f2f7ff; font-weight: 800; }
  h2 { font-size: 18px; color: #eff5ff; margin-top: 0; }
  .page-subtitle { color: #bfd0de; font-size: 14px; margin: 0; }
  .hero-badge {
    padding: 10px 14px;
    border-radius: 999px;
    background: linear-gradient(120deg, #ebf2ff, #f7f2ff);
    color: #3f5da8;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid rgba(63, 93, 168, 0.14);
  }
  .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin: 18px 0 22px; }
  .stat-card {
    padding: 16px 18px;
    border-radius: 14px;
    background: linear-gradient(135deg, #f9fbff 0%, #f4f7ff 100%);
    border: 1px solid rgba(15, 23, 42, 0.05);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.75);
  }
  .stat-card strong { display: block; font-size: 22px; color: #14213d; margin-top: 6px; }
  .table-shell { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; min-width: 680px; }
  th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid #edf0f5; font-size: 14px; }
  th {
    background: linear-gradient(180deg, #f5f8fd 0%, #eef3fb 100%);
    font-weight: 700;
    color: #3c4f6e;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.06em;
  }
  tr:hover td { background: rgba(58,90,156,0.03); }
  .badge { padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; letter-spacing: 0.2px; display: inline-block; }
  .badge-unpaid { background: linear-gradient(120deg, #ffe2e0, #ffd1ce); color: #b3261e; }
  .badge-pending { background: linear-gradient(120deg, #fff3cd, #ffe9a8); color: #8a6500; }
  .badge-paid { background: linear-gradient(120deg, #d4f4dd, #bdeccb); color: #1e7e34; }
  .badge-rejected { background: linear-gradient(120deg, #e6e7ea, #dcdde2); color: #41464b; }
  .badge-partial { background: linear-gradient(120deg, #fff3cd, #ffe9a8); color: #8a6500; }
  .btn {
    display: inline-block;
    background: linear-gradient(120deg, #1d3557 0%, #3a5a9c 100%);
    color: #fff;
    border: none;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: transform 0.12s, box-shadow 0.15s;
    box-shadow: 0 8px 18px rgba(29,53,87,0.18);
  }
  .btn:hover { box-shadow: 0 10px 22px rgba(29,53,87,0.24); transform: translateY(-1px); }
  .btn-sm { padding: 7px 12px; font-size: 13px; }
  .btn-danger { background: linear-gradient(120deg, #b3261e 0%, #d9483f 100%); box-shadow: 0 8px 18px rgba(179,38,30,0.18); }
  .btn-danger:hover { box-shadow: 0 10px 22px rgba(179,38,30,0.24); }
  .btn-success { background: linear-gradient(120deg, #1e7e34 0%, #2fa84f 100%); box-shadow: 0 8px 18px rgba(30,126,52,0.18); }
  .btn-success:hover { box-shadow: 0 10px 22px rgba(30,126,52,0.24); }
  form.inline { display: inline; }
  input, select, textarea {
    padding: 11px 12px;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 10px;
    width: 100%;
    font-size: 14px;
    margin-top: 4px;
    background: rgba(16, 25, 33, 0.9);
    color: #edf3f9;
    transition: border-color 0.15s, box-shadow 0.15s;
  }
  input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: #f0c75d;
    box-shadow: 0 0 0 3px rgba(240,199,93,0.12);
  }
  label { font-size: 13px; font-weight: 700; color: #edf3f9; }
  .field { margin-bottom: 16px; }
  .alert { 
    padding: 10px 14px; 
    border-radius: 6px; 
    margin-bottom: 14px; 
    font-size: 13px; 
    font-weight: 600;
    border-left: 4px solid !important;
    line-height: 1.4;
    display: block !important;
  }
  .alert-error { 
    background-color: #ff5252 !important; 
    color: #fff !important; 
    border-left-color: #d32f2f !important;
  }
  .alert-error strong {
    color: #fff !important;
  }
  .alert-success { 
    background-color: #4caf50 !important; 
    color: #fff !important; 
    border-left-color: #2e7d32 !important;
  }
  .alert-success strong {
    color: #fff !important;
  }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .auth-shell { max-width: 980px; margin: 44px auto; padding: 0 16px; }
  .auth-card {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 0;
    background: var(--card-bg);
    border-radius: 22px;
    overflow: hidden;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--card-border);
  }
  .auth-illustration {
    background: linear-gradient(135deg, #0f172a 0%, #1d3557 50%, #355ca8 100%);
    padding: 34px 28px;
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .auth-illustration h2 { color: #fff; font-size: 22px; margin-bottom: 8px; }
  .auth-illustration p { opacity: 0.9; margin: 0; }
  .auth-form { padding: 30px 28px; background: var(--bg-primary); color: var(--text-primary); }
  .toolbar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
  .toolbar .search-box { flex: 1; min-width: 220px; }
  .toolbar .filter-box { width: 220px; }
  .footer { max-width: 1180px; margin: 0 auto 28px; padding: 0 16px 10px; color: #64748b; font-size: 13px; text-align: center; }
  @media (max-width: 900px) {
    body { padding-left: 0; padding-top: 0; }
    nav {
      position: sticky;
      top: 0;
      left: 0;
      bottom: auto;
      width: 100%;
      min-height: auto;
      padding: 14px 16px;
      align-items: flex-start;
      border-right: none;
      border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.08));
      background: var(--nav-bg);
      color: var(--text-primary);
    }
    .topbar {
      position: static;
      left: auto;
      right: auto;
      width: 100%;
      padding: 12px 16px;
      height: auto;
      justify-content: space-between;
      gap: 12px;
      background: var(--header-bg);
      border-bottom: 1px solid var(--header-border);
      color: var(--header-text);
      backdrop-filter: blur(12px);
    }
    .topbar-search {
      flex: 1;
      min-width: 0;
      max-width: 100%;
      background: var(--field-bg);
      border-color: var(--field-border);
      color: var(--muted-text);
    }
    .topbar-search input {
      color: var(--header-text);
    }
    .nav-brand { width: 100%; justify-content: space-between; border-bottom: 0; padding: 0; }
    .menu-toggle {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--field-bg, rgba(255,255,255,0.1));
      border: 1px solid var(--field-border, rgba(255,255,255,0.15));
      color: #fff;
      border-radius: 8px;
      width: 38px;
      height: 38px;
      font-size: 18px;
      cursor: pointer;
    }
    .nav-links {
      display: none;
      width: 100%;
      flex-direction: column;
      align-items: flex-start;
      gap: 8px;
      padding-top: 14px;
      border-top: 1px solid rgba(255,255,255,0.08);
      margin-top: 10px;
    }
    .nav-links.open { display: flex; }
    nav a { width: 100%; }
    .auth-card { grid-template-columns: 1fr; }
    .auth-illustration { min-height: 220px; }
  }

  @media (max-width: 600px) {
    .grid-2 { grid-template-columns: 1fr; }
    .container { margin-top: 20px; }
    .card { padding: 18px; }
    .auth-form, .auth-illustration { padding: 20px; }
    .toolbar { flex-direction: column; }
    .toolbar .search-box, .toolbar .filter-box { width: 100%; }
    .page-hero { align-items: flex-start; }
    .hero-badge { width: 100%; text-align: center; }
    .stat-grid { grid-template-columns: 1fr; }
    .btn, .btn-sm { width: 100%; text-align: center; }
    form.inline { display: block; }
    form.inline .btn { margin-top: 6px; }
    table { min-width: 560px; }
  }
  .muted { color: #b9c7d8; font-size: 13px; }

  /* Theme Variable Overrides */
  body {
    background: var(--gradient-bg);
    color: var(--text-primary);
  }
  a { color: var(--accent-primary); }
  nav { background: var(--nav-bg); color: var(--text-primary); }
  nav a { color: #fff; }
  .theme-toggle { background: rgba(255,255,255,0.16); border: none; color: #fff; width: 40px; height: 40px; border-radius: 8px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
  .theme-toggle:hover { background: rgba(255,255,255,0.24); }
  .card { background: var(--card-bg); border: 1px solid var(--card-border); color: var(--text-primary); box-shadow: var(--shadow-lg); }
  h1, h2, h3, h4, h5, h6 { color: var(--text-primary); }
  .eyebrow { color: var(--text-secondary); }
  .page-subtitle { color: var(--text-secondary); }
  .muted { color: var(--text-secondary); }
  .hero-badge { background: var(--bg-secondary); color: var(--accent-primary); border: 1px solid var(--border-color); }
  .stat-card { background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); }
  .stat-card strong { color: var(--text-primary); }
  table { color: var(--text-primary); }
  th { background: var(--th-bg); color: var(--th-text); }
  td { border-color: var(--border-color); color: var(--text-primary); }
  tr:hover td { background: var(--hover-row-bg); }
  .badge-unpaid { background: var(--badge-unpaid-bg); color: var(--badge-unpaid-text); }
  .badge-pending { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
  .badge-paid { background: var(--badge-paid-bg); color: var(--badge-paid-text); }
  .btn { background: var(--button-bg); color: #000; box-shadow: var(--button-shadow); }
  .btn:hover { box-shadow: var(--button-hover-shadow); }
  input, select, textarea { background: var(--input-bg); border-color: var(--input-border); color: var(--text-primary); }
  input:focus, select:focus, textarea:focus { border-color: var(--accent-primary); box-shadow: 0 0 0 3px rgba(229, 169, 30, 0.12); }
  label { color: var(--text-primary); }
  .alert { color: var(--text-primary); }
  .footer { color: var(--text-secondary); }
  .auth-illustration { background: var(--nav-bg); color: #fff; }
  .auth-illustration h2 { color: #fff; }
</style>
</head>
<body data-auth="<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>">
<?php if (isset($_SESSION['user_id'])): ?>
<nav>
  <div class="nav-brand">
    <?php
      $logo_path = function_exists('get_site_logo') ? get_site_logo($pdo) : null;
      if ($logo_path):
    ?>
      <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars($logo_path); ?>" alt="UAP MC Logo">
    <?php endif; ?>
    <div class="nav-title">
      <span>UAP Mindoro Chapter</span>
    </div>
  </div>
  <button class="menu-toggle" type="button" aria-label="Toggle navigation" onclick="toggleMobileMenu()">☰</button>
  <div class="nav-links" id="mobileNavLinks">
    <?php
      $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
      $nav_active = function($pages) use ($current_script) {
          $pages = (array)$pages;
          return in_array($current_script, $pages, true) ? ' active' : '';
      };
    ?>
    <?php if (isset($_SESSION['user_id'])): ?>
      <?php if ($_SESSION['role'] === 'admin'): ?>
        <a class="nav-item<?php echo $nav_active('dashboard.php'); ?>" href="<?php echo BASE_URL; ?>/admin/dashboard.php"><span class="nav-icon">💳</span><span>Pending Payments</span></a>
        <a class="nav-item<?php echo $nav_active('approvals.php'); ?>" href="<?php echo BASE_URL; ?>/admin/approvals.php"><span class="nav-icon">✅</span><span>Approvals</span></a>
        <a class="nav-item<?php echo $nav_active('members.php'); ?>" href="<?php echo BASE_URL; ?>/admin/members.php"><span class="nav-icon">👥</span><span>Members</span></a>
        <a class="nav-item<?php echo $nav_active('good_members.php'); ?>" href="<?php echo BASE_URL; ?>/admin/good_members.php"><span class="nav-icon">⭐</span><span>Good Members</span></a>
        <a class="nav-item<?php echo $nav_active('website_directory.php'); ?>" href="<?php echo BASE_URL; ?>/admin/website_directory.php"><span class="nav-icon">📘</span><span>Website Directory</span></a>
        <a class="nav-item<?php echo $nav_active('account_manager.php'); ?>" href="<?php echo BASE_URL; ?>/admin/account_manager.php"><span class="nav-icon">🛠️</span><span>Edit Accounts</span></a>
        <a class="nav-item<?php echo $nav_active('dues.php'); ?>" href="<?php echo BASE_URL; ?>/admin/dues.php"><span class="nav-icon">📋</span><span>Dues</span></a>
        <a class="nav-item<?php echo $nav_active('qr_codes.php'); ?>" href="<?php echo BASE_URL; ?>/admin/qr_codes.php"><span class="nav-icon">📷</span><span>QR Codes</span></a>
        <a class="nav-item<?php echo $nav_active('reports.php'); ?>" href="<?php echo BASE_URL; ?>/admin/reports.php"><span class="nav-icon">📊</span><span>Reports</span></a>
        <a class="nav-item<?php echo $nav_active(['settings.php', 'change_password.php']); ?>" href="<?php echo BASE_URL; ?>/admin/settings.php"><span class="nav-icon">⚙️</span><span>Settings</span></a>
      <?php else: ?>
        <a class="nav-item<?php echo $nav_active(['dashboard.php', 'pay.php']); ?>" href="<?php echo BASE_URL; ?>/member/dashboard.php"><span class="nav-icon">💸</span><span>My Dues</span></a>
        <?php if (function_exists('is_good_member') && is_good_member($pdo, current_user_id())): ?>
          <a class="nav-item<?php echo $nav_active('website_directory.php'); ?>" href="<?php echo BASE_URL; ?>/member/website_directory.php"><span class="nav-icon">📘</span><span>Website Directory</span></a>
        <?php endif; ?>
        <a class="nav-item<?php echo $nav_active('history.php'); ?>" href="<?php echo BASE_URL; ?>/member/history.php"><span class="nav-icon">🧾</span><span>Payment History</span></a>
      <?php endif; ?>
    <?php else: ?>
      <a class="nav-item<?php echo $nav_active('login.php'); ?>" href="<?php echo BASE_URL; ?>/auth/login.php"><span class="nav-icon">🔐</span><span>Login</span></a>
      <a class="nav-item<?php echo $nav_active('register.php'); ?>" href="<?php echo BASE_URL; ?>/auth/register.php"><span class="nav-icon">📝</span><span>Register</span></a>
    <?php endif; ?>
  </div>
</nav>

<?php endif; ?>
<?php if (isset($_SESSION['user_id'])): ?>
<header class="topbar">
  <div class="topbar-search">
    <span>🔎</span>
    <input id="globalSearchInput" type="text" placeholder="Search" aria-label="Search" />
  </div>
  <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
    <?php
      $pending_approvals = $pdo->query("SELECT id, name, id_number, created_at FROM users WHERE role = 'member' AND status = 'pending' ORDER BY created_at DESC")->fetchAll();
      $pending_payments = $pdo->query("SELECT p.id, p.submitted_at, u.name, u.id_number, d.title FROM payments p JOIN member_dues md ON p.member_due_id = md.id JOIN users u ON md.user_id = u.id JOIN dues d ON md.due_id = d.id WHERE p.status = 'pending' ORDER BY p.submitted_at DESC")->fetchAll();
      $total_notifications = count($pending_approvals) + count($pending_payments);
    ?>
    <button class="notification-bell" id="notificationBell" type="button" aria-label="Notifications">
      🔔
      <?php if ($total_notifications > 0): ?>
        <span class="notification-badge" id="notificationBadge"><?php echo $total_notifications; ?></span>
      <?php endif; ?>
      <div class="notification-dropdown">
        <div class="notification-header">Notifications</div>
        <div class="notification-list">
          <?php if ($total_notifications === 0): ?>
            <div class="notification-empty">No notifications right now</div>
          <?php else: ?>
            <?php foreach ($pending_approvals as $approval): ?>
            <div class="notification-item" onclick="window.location.href='<?php echo BASE_URL; ?>/admin/approvals.php'">
              <div class="notification-type approval">Registration</div>
              <div class="notification-member"><?php echo htmlspecialchars($approval['name']); ?></div>
              <div class="notification-meta">ID: <?php echo htmlspecialchars($approval['id_number']); ?></div>
              <div class="notification-meta">Registered: <?php echo htmlspecialchars($approval['created_at']); ?></div>
            </div>
            <?php endforeach; ?>
            <?php foreach ($pending_payments as $payment): ?>
            <div class="notification-item" onclick="window.location.href='<?php echo BASE_URL; ?>/admin/dashboard.php'">
              <div class="notification-type payment">Payment</div>
              <div class="notification-member"><?php echo htmlspecialchars($payment['name']); ?></div>
              <div class="notification-meta">ID: <?php echo htmlspecialchars($payment['id_number']); ?></div>
              <div class="notification-meta">Due: <?php echo htmlspecialchars($payment['title']); ?></div>
              <div class="notification-meta">Submitted: <?php echo htmlspecialchars($payment['submitted_at']); ?></div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </button>
  <?php endif; ?>
  <div class="user-chip" id="userMenuTrigger">
    <div class="user-avatar">AU</div>
    <div class="user-menu">
      <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
      <a href="<?php echo BASE_URL; ?>/admin/change_password.php" class="change-password-link">🔐 Change Password</a>
      <div class="user-menu-divider"></div>
      <?php endif; ?>
      <button class="user-menu-item theme-switch" type="button" id="themeMenuToggle">
        <span class="switch-label">Dark mode</span>
        <span class="theme-toggle-switch" id="themeSwitchButton"></span>
      </button>
      <div class="user-menu-divider"></div>
      <form method="post" action="<?php echo BASE_URL; ?>/auth/logout.php" class="logout-form" style="margin:0;padding:0;">
        <?php echo csrf_field(); ?>
        <button type="submit" class="logout-btn">Log out</button>
      </form>
    </div>
  </div>
</header>
<?php endif; ?>
<div class="container">
<?php if (function_exists('display_flash')) { display_flash(); } ?>

