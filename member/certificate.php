<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId = current_user_id();
if ($_SESSION['role'] === 'admin' && isset($_GET['member_id'])) {
    $userId = (int)$_GET['member_id'];
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'member') {
    die('Member not found.');
}

if (!is_good_member($pdo, $userId)) {
    die('Certificate is only available to members in Good Standing with zero overdue or unsettled dues.');
}

$fiscalYear = date('Y') . '-' . (date('Y') + 1);
$issueDate = date('F d, Y');
$validUntil = date('F d, Y', strtotime('+1 year'));
$certNumber = 'UAPMC-CGS-' . date('Y') . '-' . str_pad((string)$user['id'], 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate of Good Standing - <?php echo htmlspecialchars($user['name']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,400&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #555;
    font-family: 'Inter', sans-serif;
    padding: 30px 15px;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-height: 100vh;
  }
  .cert-container {
    background: #fff;
    width: 900px;
    max-width: 100%;
    min-height: 620px;
    padding: 24px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.4);
    position: relative;
    color: #1a2530;
  }
  .cert-border {
    border: 3px solid #1b2e4b;
    padding: 12px;
    height: 100%;
  }
  .cert-inner {
    border: 1px solid #d4af37;
    padding: 36px 40px;
    text-align: center;
    position: relative;
    background: radial-gradient(circle at center, #ffffff 60%, #faf8f2 100%);
  }
  .cert-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-bottom: 20px;
  }
  .cert-logo {
    width: 75px;
    height: 75px;
    object-fit: contain;
  }
  .org-title {
    font-family: 'Cinzel', serif;
    font-size: 18px;
    font-weight: 700;
    color: #1a2e4b;
    letter-spacing: 1px;
    text-transform: uppercase;
  }
  .org-subtitle {
    font-size: 13px;
    color: #556270;
    margin-top: 2px;
    letter-spacing: 0.5px;
  }
  .cert-main-title {
    font-family: 'Cinzel', serif;
    font-size: 26px;
    font-weight: 800;
    color: #d4af37;
    text-shadow: 1px 1px 0px #1a2e4b;
    margin: 16px 0 8px;
    letter-spacing: 2px;
  }
  .cert-sub {
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: #6c7a89;
    margin-bottom: 22px;
  }
  .cert-presented {
    font-style: italic;
    font-family: 'Playfair Display', serif;
    font-size: 15px;
    color: #4a5568;
    margin-bottom: 12px;
  }
  .member-name {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    font-weight: 700;
    color: #1a2e4b;
    border-bottom: 2px solid #d4af37;
    display: inline-block;
    padding: 0 30px 4px;
    margin-bottom: 8px;
  }
  .prc-info {
    font-size: 13px;
    font-weight: 600;
    color: #3b4d66;
    margin-bottom: 16px;
    letter-spacing: 0.5px;
  }
  .cert-body {
    font-size: 13.5px;
    line-height: 1.6;
    color: #334155;
    max-width: 680px;
    margin: 0 auto 28px;
  }
  .cert-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 24px;
    padding: 0 20px;
  }
  .sig-block {
    text-align: center;
    width: 200px;
  }
  .sig-line {
    border-top: 1px solid #1a2e4b;
    margin-top: 36px;
    padding-top: 4px;
    font-size: 12px;
    font-weight: 700;
    color: #1a2e4b;
  }
  .sig-role {
    font-size: 11px;
    color: #64748b;
  }
  .seal-block {
    text-align: center;
    font-size: 11px;
    color: #8c9ba5;
  }
  .seal-circle {
    width: 68px;
    height: 68px;
    border: 2px dashed #d4af37;
    border-radius: 50%;
    margin: 0 auto 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #d4af37;
    font-weight: 700;
    font-size: 10px;
    text-transform: uppercase;
  }
  .cert-meta {
    margin-top: 20px;
    font-size: 11px;
    color: #8c9ba5;
    display: flex;
    justify-content: space-between;
    border-top: 1px solid #edf2f7;
    padding-top: 8px;
  }
  .action-bar {
    margin-top: 20px;
    display: flex;
    gap: 12px;
  }
  .btn {
    padding: 10px 20px;
    background: #d4af37;
    color: #1a2e4b;
    border: none;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    transition: 0.2s;
  }
  .btn:hover { background: #c59f2a; }
  .btn-back { background: #334155; color: #fff; }
  .btn-back:hover { background: #1e293b; }
  @media print {
    body { background: #fff; padding: 0; }
    .action-bar { display: none; }
    .cert-container { box-shadow: none; width: 100%; }
  }
</style>
</head>
<body>

<div class="cert-container">
  <div class="cert-border">
    <div class="cert-inner">
      <div class="cert-header">
        <img src="<?php echo BASE_URL; ?>/uploads/uap_logo.jpg" alt="UAP Logo" class="cert-logo">
        <div>
          <div class="org-title">United Architects of the Philippines</div>
          <div class="org-subtitle">The Integrated and Accredited Professional Organization of Architects</div>
          <div class="org-subtitle" style="font-weight:600;color:#1a2e4b;">Mindoro Chapter</div>
        </div>
      </div>

      <div class="cert-main-title">Certificate of Good Standing</div>
      <div class="cert-sub">Fiscal Year <?php echo htmlspecialchars($fiscalYear); ?></div>

      <p class="cert-presented">This is to certify that</p>
      <div class="member-name"><?php echo htmlspecialchars($user['name']); ?></div>
      <div class="prc-info">PRC Registration No.: <?php echo htmlspecialchars($user['id_number']); ?></div>

      <p class="cert-body">
        is a registered member in <strong>Good Standing</strong> of the United Architects of the Philippines — Mindoro Chapter, having fulfilled all financial obligations and chapter membership dues for the current fiscal term.
      </p>

      <div class="cert-footer">
        <div class="sig-block">
          <div class="sig-line">Chapter Treasurer</div>
          <div class="sig-role">UAP Mindoro Chapter</div>
        </div>

        <div class="seal-block">
          <div class="seal-circle">Official<br>Seal</div>
          <span>Verified Online</span>
        </div>

        <div class="sig-block">
          <div class="sig-line">Chapter President</div>
          <div class="sig-role">UAP Mindoro Chapter</div>
        </div>
      </div>

      <div class="cert-meta">
        <span>Serial No.: <strong><?php echo htmlspecialchars($certNumber); ?></strong></span>
        <span>Issued: <?php echo htmlspecialchars($issueDate); ?></span>
        <span>Valid Until: <?php echo htmlspecialchars($validUntil); ?></span>
      </div>
    </div>
  </div>
</div>

<div class="action-bar">
  <button class="btn" onclick="window.print()">🖨️ Print Certificate</button>
  <a href="<?php echo BASE_URL; ?>/member/dashboard.php" class="btn btn-back">← Back to Dashboard</a>
</div>

</body>
</html>
