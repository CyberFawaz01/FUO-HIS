<?php
$roleName    = $_SESSION['role_name']    ?? '';
$roleDisplay = $_SESSION['role_display'] ?? 'User';
$fullName    = $_SESSION['full_name']    ?? 'Guest';
$staffId     = $_SESSION['staff_id']     ?? '';
$loginRisk   = $_SESSION['login_risk']   ?? ['level'=>'Low','score'=>0];
$ctx         = $_SESSION['context']      ?? [];
$db2 = Database::getInstance();
$emergencyCount = 0;
if (in_array($roleName, ['doctor','surgeon','consultant','nurse','admin'])) {
    $emergencyCount = $db2->fetchOne("SELECT COUNT(*) c FROM patient_emergencies WHERE status='active'")['c'] ?? 0;
}
$nav = [];
$dashLinks = ['admin'=>'views/admin/dashboard.php','doctor'=>'views/doctor/dashboard.php','nurse'=>'views/nurse/dashboard.php','pharmacist'=>'views/pharmacist/dashboard.php','lab_tech'=>'views/lab/dashboard.php','surgeon'=>'views/surgeon/dashboard.php','consultant'=>'views/consultant/dashboard.php'];
$myDash = APP_URL . '/' . ($dashLinks[$roleName] ?? 'index.php');
$nav[] = ['label'=>'Dashboard','icon'=>'bi-speedometer2','href'=>$myDash];
$nav[] = ['label'=>'Patients','icon'=>'bi-person-lines-fill','href'=>APP_URL.'/views/shared/patients.php'];

if ($roleName==='pharmacist') $nav[] = ['label'=>'Prescriptions','icon'=>'bi-capsule','href'=>APP_URL.'/views/pharmacist/prescriptions.php'];
if ($roleName==='lab_tech')   $nav[] = ['label'=>'Lab Reports','icon'=>'bi-flask','href'=>APP_URL.'/views/lab/reports.php'];
if ($roleName==='admin') {

    $nav[] = ['label'=>'Users','icon'=>'bi-people','href'=>APP_URL.'/views/admin/users.php'];
    $nav[] = ['label'=>'Roles & Perms','icon'=>'bi-key','href'=>APP_URL.'/views/admin/roles.php'];
    $nav[] = ['label'=>'Emergency Monitor','icon'=>'bi-heart-pulse-fill','href'=>APP_URL.'/views/admin/emergency_monitor.php'];
    $nav[] = ['label'=>'Shift Management','icon'=>'bi-calendar3','href'=>APP_URL.'/views/admin/schedules.php'];
    $nav[] = ['label'=>'Audit Logs','icon'=>'bi-journal-text','href'=>APP_URL.'/views/admin/audit_logs.php'];
    $nav[] = ['label'=>'Risk Monitor','icon'=>'bi-graph-up-arrow','href'=>APP_URL.'/views/admin/risk_monitor.php'];
    
    
}
$initials = '';
foreach (explode(' ', $fullName) as $w) $initials .= strtoupper(substr($w,0,1));
$initials = substr($initials,0,2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>
<header class="topbar">
  <a href="<?= $myDash ?>" class="topbar-brand">
    <div class="brand-icon"><i class="bi bi-hospital"></i></div>
    <div><div class="brand-name">CA-RBAC HIS</div><span class="brand-sub">Hospital Information System</span></div>
  </a>
  <div class="topbar-right">
    <div class="risk-badge risk-<?= $loginRisk['level'] ?>" style="padding:6px 12px">
      <i class="bi bi-activity"></i> Risk: <?= $loginRisk['level'] ?> (<?= $loginRisk['score'] ?>)
    </div>
    <div class="topbar-btn" title="Network: <?= htmlspecialchars($ctx['network_name'] ?? 'Unknown') ?>">
      <i class="bi bi-<?= ($ctx['is_trusted_network']??false)?'shield-check':'shield-x' ?>"
         style="color:<?= ($ctx['is_trusted_network']??false)?'var(--green)':'var(--amber)' ?>"></i>
    </div>
    <?php if ($emergencyCount > 0 && in_array($roleName,['doctor','surgeon','consultant','nurse','admin'])): ?>
    <a href="<?= APP_URL ?>/views/shared/emergencies.php" class="topbar-btn" style="border-color:var(--red)">
      <i class="bi bi-bell-fill" style="color:var(--red)"></i>
      <span class="notif-dot"></span>
    </a>
    <?php endif; ?>
    <div class="dropdown">
      <div class="topbar-user" onclick="toggleDropdown('userMenu')">
        <div class="user-avatar"><?= $initials ?></div>
        <div><div class="user-name"><?= htmlspecialchars($fullName) ?></div><div class="user-role"><?= htmlspecialchars($roleDisplay) ?></div></div>
        <i class="bi bi-chevron-down" style="font-size:11px;color:var(--gray-400);margin-left:4px"></i>
      </div>
      <div class="dropdown-menu" id="userMenu">
        <div class="dropdown-header"><?= htmlspecialchars($staffId) ?></div>
        <a class="dropdown-item" href="<?= APP_URL ?>/views/shared/profile.php"><i class="bi bi-person"></i> My Profile</a>
        <a class="dropdown-item" href="<?= APP_URL ?>/views/shared/my_sessions.php"><i class="bi bi-clock-history"></i> My Sessions</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item danger" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </div>
  </div>
</header>
<nav class="sidebar">
  <span class="sidebar-label">Menu</span>
  <?php foreach ($nav as $item): ?>
  <?php $active = (strpos($_SERVER['REQUEST_URI'], basename($item['href'])) !== false); ?>
  <a href="<?= $item['href'] ?>" class="sidebar-link <?= $active?'active':'' ?>">
    <i class="bi <?= $item['icon'] ?> s-icon"></i>
    <?= $item['label'] ?>
    <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
    <span class="s-badge"><?= $item['badge'] ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
  <div class="sidebar-bottom">
    <div class="sidebar-ctx">
      <div class="ctx-row"><span class="ctx-dot <?= ($ctx['is_trusted_network']??false)?'green':'amber' ?>"></span><span><?= htmlspecialchars(substr($ctx['network_name']??'Unknown',0,26)) ?></span></div>
      <div class="ctx-row"><span class="ctx-dot <?= ($ctx['is_on_shift']??false)?'green':'amber' ?>"></span><span><?= ($ctx['is_on_shift']??false)?'On Duty':'Off Shift' ?></span></div>
      <div class="ctx-row"><span class="ctx-dot <?= ($ctx['is_after_hours']??false)?'amber':'green' ?>"></span><span><?= ($ctx['is_after_hours']??false)?'After Hours':'Business Hours' ?></span></div>
      <?php if (!empty($ctx['geolocation_address'])): ?>
      <div class="ctx-row"><span class="ctx-dot green"></span><span><?= htmlspecialchars(substr($ctx['geolocation_address'],0,26)) ?></span></div>
      <?php endif; ?>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="sidebar-link" style="color:rgba(255,100,100,0.7);margin-top:8px">
      <i class="bi bi-box-arrow-left s-icon"></i> Sign Out
    </a>
  </div>
</nav>
<main class="main-content">
<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_SESSION['flash_success']) ?></div>
<?php unset($_SESSION['flash_success']); endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($_SESSION['flash_error']) ?></div>
<?php unset($_SESSION['flash_error']); endif; ?>
<?php if ($emergencyCount > 0 && in_array($roleName,['doctor','surgeon','consultant','admin'])): ?>
<div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);border-left:4px solid var(--red);border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <i class="bi bi-exclamation-triangle-fill" style="color:var(--red);font-size:18px;flex-shrink:0"></i>
  <div style="flex:1"><strong style="color:#991b1b"><?= $emergencyCount ?> active patient emergency<?= $emergencyCount>1?'s':'' ?></strong><span style="color:#b91c1c;font-size:13px"> — Immediate attention required</span></div>
  <a href="<?= APP_URL ?>/views/shared/emergencies.php" class="btn btn-danger btn-sm">View Now</a>
</div>
<?php endif; ?>
