<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AuthController.php';

session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict']);
if (session_status()===PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    $r = ['admin'=>'views/admin/dashboard.php','doctor'=>'views/doctor/dashboard.php','nurse'=>'views/nurse/dashboard.php','pharmacist'=>'views/pharmacist/dashboard.php','lab_tech'=>'views/lab/dashboard.php','surgeon'=>'views/surgeon/dashboard.php','consultant'=>'views/consultant/dashboard.php'];
    header('Location: '.APP_URL.'/'.($r[$_SESSION['role_name']]??'index.php')); exit;
}
if (!empty($_SESSION['patient_id'])) {
    header('Location: '.APP_URL.'/views/patient/dashboard.php'); exit;
}

$auth  = new AuthController();
$error = $warning = '';
if (isset($_GET['timeout'])) $warning = 'Your session expired. Please log in again.';
if (isset($_GET['registered'])) $success = 'Registration successful! You can now log in.';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $type     = $_POST['login_type'] ?? 'staff';
    $staffId  = trim($_POST['staff_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($_POST['geo_lat'])) $_SESSION['geo_lat']  = $_POST['geo_lat'];
    if (!empty($_POST['geo_lng'])) $_SESSION['geo_lng']  = $_POST['geo_lng'];
    if (!empty($_POST['geo_addr']))$_SESSION['geo_addr'] = $_POST['geo_addr'];

    if ($type === 'patient') {
        $db = Database::getInstance();
        $patient = $db->fetchOne("SELECT * FROM patients WHERE patient_id=?", [$staffId]);
        if (!$patient || !password_verify($password, $patient['password_hash']??'')) {
            $error = 'Invalid Patient ID or password.';
        } else {
            $_SESSION['patient_id']   = $patient['id'];
            $_SESSION['patient_name'] = $patient['full_name'];
            $_SESSION['patient_pid']  = $patient['patient_id'];
            $_SESSION['login_time']   = time();
            $_SESSION['last_activity']= time();
            header('Location: '.APP_URL.'/views/patient/dashboard.php'); exit;
        }
    } else {
        if (empty($staffId)||empty($password)) { $error='Please enter Staff ID and password.'; }
        else {
            $result = $auth->login($staffId, $password);
            if ($result['success']) { header('Location: '.APP_URL.'/'.$result['redirect']); exit; }
            else { $error=$result['message']; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CA-RBAC Hospital Information System — Secure Login</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    * { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --green-dark:  #0a4a2e;
      --green-main:  #0f6b42;
      --green-mid:   #1a8a56;
      --green-light: #22c55e;
      --green-pale:  #dcfce7;
      --green-glow:  rgba(34,197,94,0.15);
      --white:       #ffffff;
      --gray-50:     #f8fafc;
      --gray-100:    #f1f5f9;
      --gray-200:    #e2e8f0;
      --gray-400:    #94a3b8;
      --gray-600:    #475569;
      --gray-800:    #1e293b;
      --red:         #ef4444;
      --amber:       #f59e0b;
    }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: #0a4a2e;
      min-height: 100vh;
      display: flex;
      align-items: stretch;
    }

    /* ===== LEFT PANEL ===== */
    .left-panel {
      width: 55%;
      background: linear-gradient(160deg, #0a4a2e 0%, #0f6b42 40%, #1a8a56 100%);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 48px;
      position: relative;
      overflow: hidden;
    }

    .left-panel::before {
      content: '';
      position: absolute;
      top: -100px; right: -100px;
      width: 400px; height: 400px;
      border-radius: 50%;
      background: rgba(34,197,94,0.08);
    }

    .left-panel::after {
      content: '';
      position: absolute;
      bottom: -80px; left: -80px;
      width: 300px; height: 300px;
      border-radius: 50%;
      background: rgba(255,255,255,0.04);
    }

    .hospital-image-container {
      width: 100%;
      height: 240px;
      border-radius: 20px;
      overflow: hidden;
      position: relative;
      margin-bottom: 32px;
      border: 2px solid rgba(255,255,255,0.15);
    }

    .hospital-image-container img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .hospital-image-placeholder {
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, rgba(255,255,255,0.08), rgba(255,255,255,0.04));
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: rgba(255,255,255,0.5);
    }

    .hospital-image-placeholder i { font-size: 4rem; margin-bottom: 12px; }
    .hospital-image-placeholder p { font-size: 14px; }

    .left-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 40px;
      position: relative;
      z-index: 1;
    }

    .left-brand-icon {
      width: 48px; height: 48px;
      background: rgba(255,255,255,0.15);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #86efac;
      font-size: 22px;
    }

    .left-brand-text { color: white; }
    .left-brand-text .name { font-size: 18px; font-weight: 700; }
    .left-brand-text .sub { font-size: 12px; color: rgba(255,255,255,0.5); }

    .left-content { position: relative; z-index: 1; }

    .left-content h1 {
      font-size: 36px;
      font-weight: 800;
      color: white;
      line-height: 1.2;
      margin-bottom: 16px;
      letter-spacing: -0.5px;
    }

    .left-content h1 .highlight {
      color: #86efac;
    }

    .left-content p {
      color: rgba(255,255,255,0.6);
      font-size: 15px;
      line-height: 1.7;
      margin-bottom: 32px;
    }

    .feature-list {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .feature-item {
      display: flex;
      align-items: center;
      gap: 12px;
      color: rgba(255,255,255,0.75);
      font-size: 14px;
    }

    .feature-dot {
      width: 8px; height: 8px;
      background: #86efac;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .left-footer {
      color: rgba(255,255,255,0.3);
      font-size: 12px;
      position: relative;
      z-index: 1;
    }

    /* ===== RIGHT PANEL ===== */
    .right-panel {
      width: 45%;
      background: var(--white);
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 48px 52px;
      overflow-y: auto;
    }

    .right-header {
      margin-bottom: 32px;
    }

    .right-header .greeting {
      font-size: 28px;
      font-weight: 800;
      color: var(--green-dark);
      margin-bottom: 6px;
      letter-spacing: -0.5px;
    }

    .right-header .sub {
      font-size: 14px;
      color: var(--gray-400);
    }

    /* Login type pills */
    .login-pills {
      display: flex;
      gap: 8px;
      margin-bottom: 28px;
      background: var(--gray-100);
      padding: 5px;
      border-radius: 12px;
    }

    .login-pill {
      flex: 1;
      padding: 10px 16px;
      border: none;
      border-radius: 9px;
      font-family: 'Inter', sans-serif;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      color: var(--gray-600);
      background: transparent;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .login-pill.active {
      background: white;
      color: var(--green-main);
      box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    }

    /* Alerts */
    .alert {
      padding: 11px 14px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .alert-danger  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

    /* Location bar */
    .location-bar {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      border-radius: 8px;
      padding: 8px 12px;
      margin-bottom: 20px;
      font-size: 12px;
      color: #166534;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .loc-dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: var(--amber);
      flex-shrink: 0;
      animation: blink 1s infinite;
    }

    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }

    /* Form */
    .form-group { margin-bottom: 18px; }

    .form-label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: var(--gray-800);
      margin-bottom: 6px;
      letter-spacing: 0.3px;
    }

    .input-wrap { position: relative; }

    .input-wrap i.icon {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gray-400);
      font-size: 15px;
      pointer-events: none;
    }

    .form-input {
      width: 100%;
      padding: 11px 14px 11px 40px;
      border: 1.5px solid var(--gray-200);
      border-radius: 10px;
      font-family: 'Inter', sans-serif;
      font-size: 14px;
      color: var(--gray-800);
      background: var(--white);
      outline: none;
      transition: all 0.2s;
    }

    .form-input:focus {
      border-color: var(--green-light);
      box-shadow: 0 0 0 3px rgba(34,197,94,0.12);
    }

    .eye-btn {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: none;
      cursor: pointer;
      color: var(--gray-400);
      font-size: 15px;
      padding: 0;
    }

    /* Submit button */
    .btn-login {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, var(--green-main), var(--green-mid));
      color: white;
      border: none;
      border-radius: 10px;
      font-family: 'Inter', sans-serif;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-bottom: 16px;
      letter-spacing: 0.2px;
    }

    .btn-login:hover {
      background: linear-gradient(135deg, var(--green-dark), var(--green-main));
      transform: translateY(-1px);
      box-shadow: 0 4px 16px rgba(15,107,66,0.3);
    }

    /* Patient register link */
    .register-link {
      text-align: center;
      font-size: 13px;
      color: var(--gray-400);
      margin-top: 8px;
    }

    .register-link a {
      color: var(--green-main);
      font-weight: 600;
      text-decoration: none;
    }

    .register-link a:hover { text-decoration: underline; }

    /* Security badges */
    .security-badges {
      display: flex;
      gap: 8px;
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid var(--gray-100);
      flex-wrap: wrap;
    }

    .sec-badge {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      color: var(--gray-400);
      background: var(--gray-50);
      border: 1px solid var(--gray-200);
      border-radius: 6px;
      padding: 4px 8px;
    }

    .sec-badge i { color: var(--green-main); }

    @media (max-width: 900px) {
      body { flex-direction: column; }
      .left-panel, .right-panel { width: 100%; }
      .left-panel { padding: 32px 24px; }
      .right-panel { padding: 32px 24px; }
    }
  </style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
  <div>
    <div class="left-brand">
      <div class="left-brand-icon"><i class="bi bi-hospital"></i></div>
      <div class="left-brand-text">
        <div class="name">CA-RBAC HIS</div>
        <div class="sub">Hospital Information System</div>
      </div>
    </div>

    <!-- Hospital Image -->
    <div class="hospital-image-container">
      <?php
        // Check if hospital image exists
        $imgPath = __DIR__ . '/assets/images/hospital.jpg';
        $imgPathPng = __DIR__ . '/assets/images/hospital.png';
      ?>
      <?php if (file_exists($imgPath)): ?>
        <img src="<?= APP_URL ?>/assets/images/hospital.jpg" alt="Hospital Building">
      <?php elseif (file_exists($imgPathPng)): ?>
        <img src="<?= APP_URL ?>/assets/images/hospital.png" alt="Hospital Building">
      <?php else: ?>
        <div class="hospital-image-placeholder">
          <i class="bi bi-building"></i>
          <p>Fountain University Teaching Hospital</p>
          <p style="font-size:11px;margin-top:4px;opacity:0.6">Add hospital.jpg to assets/images/</p>
        </div>
      <?php endif; ?>
    </div>

    <div class="left-content">
      <h1>FUO <span class="highlight">Context-Aware</span><br>Health Records Access</h1>
      <p>Your location, device, and shift status are verified before granting access to patient records.</p>
    </div>
  </div>

 
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
  <div class="right-header">
    <div class="greeting">Welcome Back</div>
    <div class="sub">Sign in to access the hospital system securely</div>
  </div>

  <!-- Login Type Pills -->
  <div class="login-pills">
    <button class="login-pill active" id="pillStaff" onclick="switchTab('staff')">
      <i class="bi bi-person-badge"></i> Staff Login
    </button>
    <button class="login-pill" id="pillPatient" onclick="switchTab('patient')">
      <i class="bi bi-person-heart"></i> Patient Login
    </button>
  </div>

  <?php if (!empty($warning)): ?>
  <div class="alert alert-warning"><i class="bi bi-clock"></i> <?= htmlspecialchars($warning) ?></div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
  <div class="alert alert-danger"><i class="bi bi-shield-x"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Location Bar -->
  <div class="location-bar">
    <span class="loc-dot" id="locDot"></span>
    <i class="bi bi-geo-alt-fill"></i>
    <span id="locText">Detecting your location...</span>
  </div>

  <form method="POST" id="loginForm">
    <input type="hidden" name="login_type" id="loginType" value="staff">
    <input type="hidden" name="geo_lat"  id="geoLat">
    <input type="hidden" name="geo_lng"  id="geoLng">
    <input type="hidden" name="geo_addr" id="geoAddr">

    <div class="form-group">
      <label class="form-label" id="idLabel">STAFF ID</label>
      <div class="input-wrap">
        <i class="bi bi-person-badge icon"></i>
        <input type="text" class="form-input" name="staff_id" id="staffIdInput"
               placeholder="e.g. DOC001"
               value="<?= htmlspecialchars($_POST['staff_id']??'') ?>"
               required autofocus>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">PASSWORD</label>
      <div class="input-wrap">
        <i class="bi bi-lock-fill icon"></i>
        <input type="password" class="form-input" name="password"
               id="pwdInput" placeholder="Enter your password"
               required style="padding-right:42px">
        <button type="button" class="eye-btn" onclick="togglePwd()">
          <i class="bi bi-eye" id="eyeIcon"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="btn-login">
      <i class="bi bi-shield-lock-fill"></i>
      Secure Sign In
    </button>
  </form>

  <!-- Patient register link (only visible on patient tab) -->
  <div class="register-link" id="registerLink" style="display:none">
    Don't have an account?
    <a href="<?= APP_URL ?>/views/patient/register.php">Register as Patient</a>
  </div>

  <!-- Security Badges -->
  <div class="security-badges">
    <div class="sec-badge"><i class="bi bi-geo-alt-fill"></i> Location Verified</div>
    <div class="sec-badge"><i class="bi bi-shield-check"></i> CA-RBAC Protected</div>
    <div class="sec-badge"><i class="bi bi-journal-text"></i> Fully Audited</div>
    <div class="sec-badge"><i class="bi bi-device-hdd"></i> Device Tracked</div>
  </div>
</div>

<script>
function switchTab(type) {
    document.getElementById('loginType').value = type;
    document.getElementById('pillStaff').classList.toggle('active', type==='staff');
    document.getElementById('pillPatient').classList.toggle('active', type==='patient');
    document.getElementById('idLabel').textContent = type==='staff' ? 'STAFF ID' : 'PATIENT ID';
    document.getElementById('staffIdInput').placeholder = type==='staff' ? 'e.g. DOC001' : 'e.g. PAT001';
    document.getElementById('registerLink').style.display = type==='patient' ? 'block' : 'none';
}

function togglePwd() {
    const i = document.getElementById('pwdInput');
    const e = document.getElementById('eyeIcon');
    i.type = i.type==='password' ? 'text' : 'password';
    e.className = i.type==='text' ? 'bi bi-eye-slash' : 'bi bi-eye';
}

// GPS Detection
function detectLocation() {
    const dot  = document.getElementById('locDot');
    const text = document.getElementById('locText');

    if (!navigator.geolocation) {
        text.textContent = 'GPS not supported — IP-based evaluation active';
        dot.style.background = '#f59e0b';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        pos => {
            const lat = pos.coords.latitude.toFixed(6);
            const lng = pos.coords.longitude.toFixed(6);
            document.getElementById('geoLat').value = lat;
            document.getElementById('geoLng').value = lng;

            fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json`)
                .then(r => r.json())
                .then(data => {
                    const addr = data.display_name || `${lat}, ${lng}`;
                    document.getElementById('geoAddr').value = addr.substring(0,200);
                    dot.style.background = '#22c55e';
                    dot.style.animation = 'none';
                    const place = data.address?.building || data.address?.road || data.address?.suburb || 'Location detected';
                    text.textContent = `📍 ${place} — GPS active`;
                }).catch(() => {
                    document.getElementById('geoAddr').value = `${lat}, ${lng}`;
                    dot.style.background = '#22c55e';
                    dot.style.animation = 'none';
                    text.textContent = `📍 GPS: ${lat}, ${lng}`;
                });
        },
        err => {
            dot.style.background = '#ef4444';
            if (err.code === 1) {
                text.textContent = '⚠️ Location denied — access to patient records may be restricted';
            } else {
                text.textContent = '⚠️ Location unavailable — IP-based evaluation active';
            }
        },
        { timeout: 10000, enableHighAccuracy: true }
    );
}

detectLocation();

<?php if (($_POST['login_type']??'')==='patient'): ?>
switchTab('patient');
<?php endif; ?>
</script>
</body>
</html>
