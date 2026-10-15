<?php
// CA-RBAC HIS — Patient Dashboard (Light Theme + Tell Your Doctor)
require_once __DIR__ . '/../../config/database.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict']);
if (session_status()===PHP_SESSION_NONE) session_start();
if (empty($_SESSION['patient_id'])) { header('Location: '.APP_URL.'/index.php'); exit; }

$db        = Database::getInstance();
$patientId = $_SESSION['patient_id'];
$patient   = $db->fetchOne("SELECT p.*, d.dept_name FROM patients p LEFT JOIN departments d ON p.department_id=d.id WHERE p.id=?", [$patientId]);
if (!$patient) { session_destroy(); header('Location: '.APP_URL.'/index.php'); exit; }

$diagnoses     = $db->fetchAll("SELECT pr.*, u.full_name AS dr FROM patient_records pr LEFT JOIN users u ON pr.created_by=u.id WHERE pr.patient_id=? AND pr.record_type='diagnosis' ORDER BY pr.record_date DESC", [$patientId]);
$prescriptions = $db->fetchAll("SELECT pr.*, u.full_name AS dr FROM patient_records pr LEFT JOIN users u ON pr.created_by=u.id WHERE pr.patient_id=? AND pr.record_type='medication' ORDER BY pr.record_date DESC", [$patientId]);
$labReports    = $db->fetchAll("SELECT pr.*, u.full_name AS dr FROM patient_records pr LEFT JOIN users u ON pr.created_by=u.id WHERE pr.patient_id=? AND pr.record_type='lab_report' ORDER BY pr.record_date DESC", [$patientId]);
$vitals        = $db->fetchAll("SELECT pr.*, u.full_name AS dr FROM patient_records pr LEFT JOIN users u ON pr.created_by=u.id WHERE pr.patient_id=? AND pr.record_type='vitals' ORDER BY pr.record_date DESC LIMIT 10", [$patientId]);
$allRecords    = $db->fetchAll("SELECT pr.*, u.full_name AS dr FROM patient_records pr LEFT JOIN users u ON pr.created_by=u.id WHERE pr.patient_id=? ORDER BY pr.record_date DESC LIMIT 20", [$patientId]);
$careTeam      = $db->fetchAll("SELECT u.full_name, u.specialization, r.display_name AS role_display, d.dept_name FROM assignments a JOIN users u ON a.user_id=u.id JOIN roles r ON u.role_id=r.id LEFT JOIN departments d ON u.department_id=d.id WHERE a.patient_id=? AND a.is_active=1", [$patientId]);
$activeEmergency = $db->fetchOne("SELECT pe.*, u.full_name AS claimed_by_name FROM patient_emergencies pe LEFT JOIN users u ON pe.claimed_by=u.id WHERE pe.patient_id=? AND pe.status IN ('active','claimed') ORDER BY pe.created_at DESC LIMIT 1", [$patientId]);

// Today symptom report
$todayReport = null;
try {
    $todayReport = $db->fetchOne("SELECT ps.*, u.full_name AS ack_by_name FROM patient_symptoms ps LEFT JOIN users u ON ps.acknowledged_by=u.id WHERE ps.patient_id=? AND ps.report_date=CURDATE() ORDER BY ps.created_at DESC LIMIT 1", [$patientId]);
} catch (Exception $e) { $todayReport = null; }

$symptomSuccess = $symptomError = $emergencySuccess = $emergencyError = '';

// Handle symptom submission
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_symptoms'])) {
    $symptoms = trim($_POST['symptoms'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $severity = $_POST['severity'] ?? 'Moderate';
    $notes    = trim($_POST['additional_notes'] ?? '');
    if (empty($symptoms)) {
        $symptomError = 'Please describe your symptoms.';
    } elseif ($todayReport) {
        $symptomError = 'You already submitted a report today. Wait for your doctor to respond.';
    } else {
        try {
            $db->query("INSERT INTO patient_symptoms (patient_id,symptoms,duration,severity,additional_notes,report_date) VALUES (?,?,?,?,?,CURDATE())", [$patientId,$symptoms,$duration,$severity,$notes]);
            $symptomSuccess = 'Your symptoms have been reported. Your doctor will be notified.';
            $todayReport = $db->fetchOne("SELECT * FROM patient_symptoms WHERE patient_id=? AND report_date=CURDATE() ORDER BY created_at DESC LIMIT 1", [$patientId]);
        } catch (Exception $e) {
            $symptomError = 'Error: Please run symptoms_table.sql in phpMyAdmin first.';
        }
    }
}

// Handle emergency
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['declare_emergency'])) {
    $symptoms = trim($_POST['symptoms'] ?? '');
    $severity = $_POST['severity'] ?? 'Moderate';
    if ($activeEmergency) {
        $emergencyError = 'You already have an active emergency.';
    } else {
        $db->query("INSERT INTO patient_emergencies (patient_id,symptoms,severity,status) VALUES (?,?,?,'active')", [$patientId,$symptoms,$severity]);
        $eid = $db->lastInsertId();
        $db->query("UPDATE patients SET is_emergency=1, emergency_condition=? WHERE id=?", [$symptoms,$patientId]);
        $doctors = $db->fetchAll("SELECT id FROM users WHERE role_id IN (2,6,7) AND is_active=1");
        foreach ($doctors as $doc) { $db->query("INSERT INTO emergency_notifications (emergency_id,doctor_id) VALUES (?,?)", [$eid,$doc['id']]); }
        $emergencySuccess = 'Emergency declared. All doctors have been notified.';
        $activeEmergency  = $db->fetchOne("SELECT * FROM patient_emergencies WHERE id=?", [$eid]);
    }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_emergency'])) {
    $eid = (int)$_POST['emergency_id'];
    $db->query("UPDATE patient_emergencies SET status='resolved',resolved_at=NOW(),resolution_notes='Cancelled by patient' WHERE id=? AND patient_id=?", [$eid,$patientId]);
    $db->query("UPDATE patients SET is_emergency=0,emergency_condition=NULL WHERE id=?", [$patientId]);
    $emergencySuccess = 'Emergency cancelled.';
    $activeEmergency  = null;
}

$activeTab = 'tell_doctor';
$initials  = '';
foreach (explode(' ', $patient['full_name']) as $w) $initials .= strtoupper(substr($w,0,1));
$initials  = substr($initials,0,2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Health Portal — <?= htmlspecialchars($patient['full_name']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --gd:#0a4a2e;--gm:#0f6b42;--gmi:#1a8a56;--gl:#22c55e;--gp:#f0fdf4;--gb:#bbf7d0;
  --w:#fff;--g50:#f8fafc;--g100:#f1f5f9;--g200:#e2e8f0;--g400:#94a3b8;
  --g500:#64748b;--g600:#475569;--g800:#1e293b;
  --red:#ef4444;--amb:#f59e0b;--pur:#8b5cf6;
}
body{font-family:'Inter',sans-serif;background:var(--g50);color:var(--g800);min-height:100vh}
/* TOPBAR */
.topbar{background:var(--gd);padding:0 24px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,0.15)}
.tb-brand{display:flex;align-items:center;gap:10px;text-decoration:none}
.tb-icon{width:34px;height:34px;background:rgba(255,255,255,0.12);border-radius:9px;display:flex;align-items:center;justify-content:center;color:#86efac;font-size:17px}
.tb-name{color:white;font-weight:700;font-size:14px}
.tb-sub{color:rgba(255,255,255,0.4);font-size:10px;display:block}
.tb-right{display:flex;align-items:center;gap:10px}
.ep{background:rgba(239,68,68,0.25);border:1px solid rgba(239,68,68,0.5);border-radius:20px;padding:4px 12px;font-size:11px;font-weight:700;color:#fca5a5}
.u-chip{display:flex;align-items:center;gap:8px;color:rgba(255,255,255,0.75);font-size:13px}
.u-av{width:30px;height:30px;border-radius:8px;background:var(--gmi);display:flex;align-items:center;justify-content:center;color:white;font-size:12px;font-weight:700}
.logout{color:rgba(255,255,255,0.4);font-size:12px;text-decoration:none;padding:5px 10px;border-radius:7px;border:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;gap:5px}
.logout:hover{background:rgba(239,68,68,0.15);color:#fca5a5}
/* LAYOUT */
.layout{display:flex;max-width:1300px;margin:0 auto;padding:24px 20px;gap:20px}
/* SIDEBAR */
.sidebar{width:230px;flex-shrink:0;display:flex;flex-direction:column;gap:12px}
.p-card{background:white;border:1px solid var(--g200);border-radius:14px;padding:20px;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.p-av{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--gd),var(--gmi));display:flex;align-items:center;justify-content:center;color:white;font-size:22px;font-weight:700;margin:0 auto 12px}
.p-name{font-weight:700;font-size:14px;color:var(--g800);margin-bottom:2px}
.p-pid{color:var(--gm);font-size:12px;font-weight:600;margin-bottom:10px}
.s-pill{display:inline-block;padding:3px 12px;border-radius:20px;font-size:11px;font-weight:600}
.s-ad{background:#dbeafe;color:#1d4ed8}.s-op{background:#dcfce7;color:#166534}.s-di{background:var(--g100);color:var(--g500)}.s-em{background:#fee2e2;color:#991b1b}
.nav-card{background:white;border:1px solid var(--g200);border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.nav-btn{display:flex;align-items:center;gap:10px;padding:11px 16px;font-size:13px;font-weight:500;color:var(--g600);cursor:pointer;border:none;background:none;width:100%;text-align:left;transition:.15s;border-bottom:1px solid var(--g100)}
.nav-btn:last-child{border-bottom:none}
.nav-btn:hover{background:var(--gp);color:var(--gm)}
.nav-btn.active{background:var(--gp);color:var(--gm);font-weight:600;border-left:3px solid var(--gm)}
.nav-btn i{font-size:15px;width:18px;text-align:center}
.n-badge{margin-left:auto;color:white;border-radius:10px;padding:1px 6px;font-size:10px;font-weight:700}
.q-info{background:white;border:1px solid var(--g200);border-radius:14px;padding:14px 16px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
/* MAIN */
.main{flex:1;min-width:0}
.tab-panel{display:none}
.tab-panel.active{display:block}
/* CARDS */
.card{background:white;border:1px solid var(--g200);border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:16px}
.c-head{padding:14px 20px;border-bottom:1px solid var(--g100);display:flex;align-items:center;justify-content:space-between}
.c-title{font-size:14px;font-weight:600;color:var(--g800);display:flex;align-items:center;gap:8px}
.c-title i{color:var(--gm)}
.c-body{padding:20px}
/* ALERTS */
.alert{padding:12px 16px;border-radius:8px;font-size:13px;display:flex;align-items:flex-start;gap:10px;margin-bottom:14px;border-left:4px solid}
.a-suc{background:#f0fdf4;border-color:var(--gl);color:#166534}
.a-err{background:#fef2f2;border-color:var(--red);color:#991b1b}
.a-war{background:#fffbeb;border-color:var(--amb);color:#92400e}
/* RECORDS */
.rec-item{background:var(--g50);border:1px solid var(--g200);border-radius:10px;padding:14px 16px;margin-bottom:10px}
.rec-item:last-child{margin-bottom:0}
.rec-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;margin-bottom:8px}
.rec-title{font-weight:600;font-size:14px;color:var(--g800);margin-bottom:4px}
.rec-body{font-size:13px;color:var(--g600);line-height:1.6;margin-bottom:6px}
.rec-meta{font-size:11px;color:var(--g400)}
/* FORMS */
.f-group{margin-bottom:16px}
.f-label{display:block;font-size:12px;font-weight:600;color:var(--g600);margin-bottom:5px;text-transform:uppercase;letter-spacing:0.3px}
.f-input,.f-select{width:100%;padding:10px 14px;border:1.5px solid var(--g200);border-radius:8px;font-family:'Inter',sans-serif;font-size:13px;color:var(--g800);outline:none;transition:.2s;background:white;appearance:none}
.f-input:focus,.f-select:focus{border-color:var(--gl);box-shadow:0 0 0 3px rgba(34,197,94,0.1)}
textarea.f-input{resize:vertical;min-height:90px}
.btn-green{padding:11px 20px;background:linear-gradient(135deg,var(--gm),var(--gmi));color:white;border:none;border-radius:8px;font-family:'Inter',sans-serif;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px}
.btn-red{width:100%;padding:12px;background:linear-gradient(135deg,#dc2626,#ef4444);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px}
.btn-gray{padding:8px 18px;background:var(--g100);color:var(--g600);border:1px solid var(--g200);border-radius:8px;font-family:'Inter',sans-serif;font-size:13px;font-weight:600;cursor:pointer}
.emrg-card{background:linear-gradient(135deg,#fef2f2,#fff5f5);border:1px solid #fecaca;border-radius:14px;padding:24px;margin-bottom:16px}
.emrg-active{background:linear-gradient(135deg,#fef2f2,#fff5f5);border:1px solid rgba(239,68,68,0.3);border-radius:14px;padding:20px;margin-bottom:16px;text-align:center}
.empty{text-align:center;padding:50px 20px;color:var(--g400)}
.empty i{font-size:3rem;margin-bottom:12px;display:block}
.r-status{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}
.rs-p{background:#fef9c3;color:#854d0e}
.rs-a{background:#dbeafe;color:#1d4ed8}
.rs-ad{background:#dcfce7;color:#166534}
@media(max-width:768px){.layout{flex-direction:column}.sidebar{width:100%}}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <a href="<?= APP_URL ?>/views/patient/dashboard.php" class="tb-brand">
    <div class="tb-icon"><i class="bi bi-hospital"></i></div>
    <div><div class="tb-name">CA-RBAC HIS</div><span class="tb-sub">Patient Portal</span></div>
  </a>
  <div class="tb-right">
    <?php if ($activeEmergency): ?>
    <div class="ep"><i class="bi bi-heart-pulse-fill"></i> Emergency Active</div>
    <?php endif; ?>
    <div class="u-chip">
      <div class="u-av"><?= $initials ?></div>
      <span><?= htmlspecialchars(explode(' ',$patient['full_name'])[0]) ?></span>
    </div>
    <a href="<?= APP_URL ?>/views/patient/logout.php" class="logout">
      <i class="bi bi-box-arrow-right"></i> Sign Out
    </a>
  </div>
</div>

<div class="layout">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="p-card">
      <div class="p-av"><?= $initials ?></div>
      <div class="p-name"><?= htmlspecialchars($patient['full_name']) ?></div>
      <div class="p-pid"><?= htmlspecialchars($patient['patient_id']) ?></div>
      <?php $sc=['admitted'=>'s-ad','outpatient'=>'s-op','discharged'=>'s-di','emergency'=>'s-em'][$patient['admission_status']]??'s-op'; ?>
      <span class="s-pill <?= $sc ?>"><?= ucfirst($patient['admission_status']) ?></span>
    </div>

    <div class="nav-card">
      <?php
      $navTabs = [
        ['id'=>'tell_doctor',   'icon'=>'bi-chat-heart',           'label'=>'Tell Your Doctor', 'badge'=>($todayReport&&$todayReport['status']==='pending')?1:0, 'bc'=>'var(--amb)'],
        ['id'=>'diagnoses',     'icon'=>'bi-stethoscope',          'label'=>'Diagnoses',        'badge'=>count($diagnoses),    'bc'=>'var(--gm)'],
        ['id'=>'prescriptions', 'icon'=>'bi-capsule',              'label'=>'Prescriptions',    'badge'=>count($prescriptions),'bc'=>'var(--gm)'],
        ['id'=>'lab',           'icon'=>'bi-flask',                'label'=>'Lab Results',      'badge'=>count($labReports),   'bc'=>'var(--gm)'],
        ['id'=>'vitals',        'icon'=>'bi-activity',             'label'=>'Vital Records',    'badge'=>count($vitals),       'bc'=>'var(--gm)'],
        ['id'=>'records',       'icon'=>'bi-file-earmark-medical', 'label'=>'All Records',      'badge'=>count($allRecords),   'bc'=>'var(--gm)'],
        ['id'=>'careteam',      'icon'=>'bi-people',               'label'=>'Care Team',        'badge'=>count($careTeam),     'bc'=>'var(--gm)'],
        ['id'=>'emergency',     'icon'=>'bi-heart-pulse',          'label'=>'Emergency',        'badge'=>$activeEmergency?1:0, 'bc'=>'var(--red)'],
      ];
      foreach ($navTabs as $t): ?>
      <button onclick="showTab('<?= $t['id'] ?>')" id="nav-<?= $t['id'] ?>"
              class="nav-btn <?= $activeTab===$t['id']?'active':'' ?>">
        <i class="bi <?= $t['icon'] ?>"></i>
        <?= $t['label'] ?>
        <?php if ($t['badge'] > 0): ?>
        <span class="n-badge" style="background:<?= $t['bc'] ?>"><?= $t['badge'] ?></span>
        <?php endif; ?>
      </button>
      <?php endforeach; ?>
    </div>

    <div class="q-info">
      <div style="font-size:11px;font-weight:600;color:var(--g400);text-transform:uppercase;margin-bottom:10px">Quick Info</div>
      <div style="font-size:12px;display:flex;flex-direction:column;gap:6px">
        <div><span style="color:var(--g400)">Blood:</span> <strong style="color:var(--red)"><?= $patient['blood_group']??'Unknown' ?></strong></div>
        <div><span style="color:var(--g400)">Gender:</span> <strong><?= $patient['gender'] ?></strong></div>
        <div><span style="color:var(--g400)">DOB:</span> <strong><?= date('d M Y',strtotime($patient['date_of_birth'])) ?></strong></div>
        <div><span style="color:var(--g400)">Dept:</span> <strong><?= $patient['dept_name']??'—' ?></strong></div>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main">

    <?php if ($symptomSuccess): ?><div class="alert a-suc"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($symptomSuccess) ?></div><?php endif; ?>
    <?php if ($symptomError):   ?><div class="alert a-err"><i class="bi bi-x-circle-fill"></i><?= htmlspecialchars($symptomError) ?></div><?php endif; ?>
    <?php if ($emergencySuccess):?><div class="alert a-suc"><i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($emergencySuccess) ?></div><?php endif; ?>
    <?php if ($activeEmergency): ?>
    <div class="alert a-war" style="border-left:4px solid var(--red)">
      <i class="bi bi-heart-pulse-fill" style="color:var(--red);font-size:18px;flex-shrink:0"></i>
      <div><strong>Emergency Active — </strong>
        <?= $activeEmergency['status']==='claimed'&&$activeEmergency['claimed_by_name']
          ? 'Dr. '.htmlspecialchars($activeEmergency['claimed_by_name']).' is responding'
          : 'Notifying doctors...' ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ========== TELL YOUR DOCTOR ========== -->
    <div class="tab-panel active" id="tab-tell_doctor">
      <?php if ($todayReport): ?>
      <div class="card">
        <div class="c-head">
          <div class="c-title"><i class="bi bi-chat-heart"></i>Today's Symptom Report</div>
          <span class="r-status rs-<?= $todayReport['status'][0] ?>"><?= ucfirst($todayReport['status']) ?></span>
        </div>
        <div class="c-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
            <div>
              <div style="font-size:11px;color:var(--g400);font-weight:600;text-transform:uppercase;margin-bottom:4px">Symptoms</div>
              <div style="font-size:14px"><?= nl2br(htmlspecialchars($todayReport['symptoms'])) ?></div>
            </div>
            <div>
              <div style="font-size:11px;color:var(--g400);font-weight:600;text-transform:uppercase;margin-bottom:4px">Duration</div>
              <div style="font-size:14px"><?= htmlspecialchars($todayReport['duration']??'Not specified') ?></div>
            </div>
          </div>
          <?php if ($todayReport['additional_notes']): ?>
          <div style="background:var(--g50);border-radius:8px;padding:12px;margin-bottom:12px;font-size:13px;color:var(--g600)"><?= nl2br(htmlspecialchars($todayReport['additional_notes'])) ?></div>
          <?php endif; ?>
          <?php if ($todayReport['status']==='pending'): ?>
          <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:13px;color:#92400e;display:flex;align-items:center;gap:8px">
            <i class="bi bi-hourglass-split"></i> Your doctor has been notified and will review your symptoms soon.
          </div>
          <?php elseif ($todayReport['status']==='acknowledged'): ?>
          <div style="background:#dbeafe;border:1px solid #93c5fd;border-radius:8px;padding:10px 14px;font-size:13px;color:#1d4ed8;display:flex;align-items:center;gap:8px">
            <i class="bi bi-check-circle-fill"></i> Your symptoms have been reviewed by <?= htmlspecialchars($todayReport['ack_by_name']??'your doctor') ?>.
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="card">
        <div class="c-head">
          <div class="c-title"><i class="bi bi-chat-heart"></i>Tell Your Doctor</div>
          <span style="font-size:12px;color:var(--g400)">One report per day</span>
        </div>
        <div class="c-body">
          <div style="background:var(--gp);border:1px solid var(--gb);border-radius:8px;padding:12px 14px;margin-bottom:20px;font-size:13px;color:#166534;display:flex;align-items:center;gap:8px">
            <i class="bi bi-info-circle-fill"></i>
            Describe how you feel today. Your doctor will use this to guide your diagnosis and treatment.
          </div>
          <form method="POST" action="">
            <input type="hidden" name="submit_symptoms" value="1">
            <div class="f-group">
              <label class="f-label">What symptoms are you experiencing? <span style="color:var(--red)">*</span></label>
              <textarea class="f-input" name="symptoms" required rows="4"
                        placeholder="e.g. I have been having headache, fever, and body pain since yesterday morning..."></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
              <div class="f-group">
                <label class="f-label">How long have you had these symptoms? <span style="color:var(--red)">*</span></label>
                <input type="text" class="f-input" name="duration" required placeholder="e.g. 2 days, since Monday">
              </div>
              <div class="f-group">
                <label class="f-label">Severity <span style="color:var(--red)">*</span></label>
                <select class="f-select" name="severity" required>
                  <option value="Mild">Mild — Slightly uncomfortable</option>
                  <option value="Moderate" selected>Moderate — Affecting daily activities</option>
                  <option value="Severe">Severe — Very painful</option>
                </select>
              </div>
            </div>
            <div class="f-group">
              <label class="f-label">Any other information for your doctor?</label>
              <textarea class="f-input" name="additional_notes" rows="2"
                        placeholder="e.g. I am allergic to penicillin, I have been taking paracetamol..."></textarea>
            </div>
            <button type="submit" class="btn-green">
              <i class="bi bi-send-fill"></i> Send to Doctor
            </button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ========== DIAGNOSES ========== -->
    <div class="tab-panel" id="tab-diagnoses">
      <div class="card">
        <div class="c-head"><div class="c-title"><i class="bi bi-stethoscope"></i>My Diagnoses</div></div>
        <div class="c-body">
          <?php if (empty($diagnoses)): ?>
          <div class="empty"><i class="bi bi-clipboard2-x"></i><p>No diagnoses recorded yet</p></div>
          <?php else: foreach ($diagnoses as $d): ?>
          <div class="rec-item">
            <span class="rec-badge" style="background:#dbeafe;color:#1d4ed8">Diagnosis</span>
            <div class="rec-title"><?= htmlspecialchars($d['title']) ?></div>
            <div class="rec-body"><?= nl2br(htmlspecialchars($d['content'])) ?></div>
            <div class="rec-meta">By Dr. <?= htmlspecialchars($d['dr']??'Unknown') ?> · <?= date('d M Y H:i',strtotime($d['record_date'])) ?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- ========== PRESCRIPTIONS ========== -->
    <div class="tab-panel" id="tab-prescriptions">
      <div class="card">
        <div class="c-head"><div class="c-title"><i class="bi bi-capsule"></i>My Prescriptions</div></div>
        <div class="c-body">
          <?php if (empty($prescriptions)): ?>
          <div class="empty"><i class="bi bi-capsule"></i><p>No prescriptions yet</p></div>
          <?php else: foreach ($prescriptions as $p): $d=$p['dispensed']??0; ?>
          <div class="rec-item" style="border-left:3px solid <?= $d?'var(--gl)':'var(--amb)' ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <span class="rec-badge" style="background:#dcfce7;color:#166534">Prescription</span>
              <span style="font-size:11px;font-weight:600;color:<?= $d?'var(--gm)':'var(--amb)' ?>">
                <i class="bi bi-<?= $d?'check-circle':'hourglass-split' ?>"></i> <?= $d?'Dispensed':'Pending' ?>
              </span>
            </div>
            <div class="rec-title"><?= htmlspecialchars($p['title']) ?></div>
            <div class="rec-body"><?= nl2br(htmlspecialchars($p['content'])) ?></div>
            <div class="rec-meta">By Dr. <?= htmlspecialchars($p['dr']??'Unknown') ?> · <?= date('d M Y',strtotime($p['record_date'])) ?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- ========== LAB RESULTS ========== -->
    <div class="tab-panel" id="tab-lab">
      <div class="card">
        <div class="c-head"><div class="c-title"><i class="bi bi-flask"></i>Lab Results</div></div>
        <div class="c-body">
          <?php if (empty($labReports)): ?>
          <div class="empty"><i class="bi bi-flask"></i><p>No lab results yet</p></div>
          <?php else: foreach ($labReports as $l): ?>
          <div class="rec-item" style="border-left:3px solid var(--pur)">
            <span class="rec-badge" style="background:#ede9fe;color:#7c3aed">Lab Report</span>
            <div class="rec-title"><?= htmlspecialchars($l['title']) ?></div>
            <div class="rec-body"><?= nl2br(htmlspecialchars($l['content'])) ?></div>
            <div class="rec-meta">Lab Tech: <?= htmlspecialchars($l['dr']??'Unknown') ?> · <?= date('d M Y H:i',strtotime($l['record_date'])) ?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- ========== VITALS ========== -->
    <div class="tab-panel" id="tab-vitals">
      <div class="card">
        <div class="c-head"><div class="c-title"><i class="bi bi-activity"></i>Vital Records</div></div>
        <div class="c-body">
          <?php if (empty($vitals)): ?>
          <div class="empty"><i class="bi bi-activity"></i><p>No vital records yet</p></div>
          <?php else: foreach ($vitals as $v): ?>
          <div class="rec-item" style="border-left:3px solid var(--gl)">
            <span class="rec-badge" style="background:#dcfce7;color:#166534">Vitals</span>
            <div class="rec-title"><?= htmlspecialchars($v['title']) ?></div>
            <div class="rec-body"><?= nl2br(htmlspecialchars($v['content'])) ?></div>
            <div class="rec-meta"><?= date('d M Y H:i',strtotime($v['record_date'])) ?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- ========== ALL RECORDS ========== -->
    <div class="tab-panel" id="tab-records">
      <div class="card">
        <div class="c-head"><div class="c-title"><i class="bi bi-file-earmark-medical"></i>All Medical Records</div></div>
        <div class="c-body">
          <?php if (empty($allRecords)): ?>
          <div class="empty"><i class="bi bi-folder2-open"></i><p>No records yet</p></div>
          <?php else:
          $tc=['diagnosis'=>['#dbeafe','#1d4ed8'],'medication'=>['#dcfce7','#166534'],'lab_report'=>['#ede9fe','#7c3aed'],'vitals'=>['#dcfce7','#166634'],'allergy'=>['#fef9c3','#854d0e'],'treatment'=>['#dbeafe','#1d4ed8'],'surgery_note'=>['#fce7f3','#9d174d'],'consultation'=>['#e0f2fe','#0369a1'],'lab_request'=>['#fef9c3','#854d0e']];
          foreach ($allRecords as $rec): $c=$tc[$rec['record_type']]??['var(--g100)','var(--g600)']; ?>
          <div class="rec-item" style="border-left:3px solid <?= $c[1] ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
              <span class="rec-badge" style="background:<?= $c[0] ?>;color:<?= $c[1] ?>"><?= str_replace('_',' ',ucfirst($rec['record_type'])) ?></span>
              <span style="font-size:11px;color:var(--g400)"><?= date('d M Y',strtotime($rec['record_date'])) ?></span>
            </div>
            <div class="rec-title"><?= htmlspecialchars($rec['title']) ?></div>
            <div class="rec-body"><?= nl2br(htmlspecialchars($rec['content'])) ?></div>
            <div class="rec-meta">By <?= htmlspecialchars($rec['dr']??'Unknown') ?></div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- ========== CARE TEAM ========== -->
    <div class="tab-panel" id="tab-careteam">
      <div class="card">
        <div class="c-head"><div class="c-title"><i class="bi bi-people"></i>My Care Team</div></div>
        <div class="c-body">
          <?php if (empty($careTeam)): ?>
          <div class="empty"><i class="bi bi-people"></i><p>No care team assigned yet</p></div>
          <?php else: foreach ($careTeam as $c): ?>
          <div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--g100)">
            <div style="width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg,var(--gd),var(--gmi));display:flex;align-items:center;justify-content:center;color:white;font-size:18px;flex-shrink:0"><i class="bi bi-person-fill"></i></div>
            <div>
              <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($c['full_name']) ?></div>
              <div style="color:var(--gm);font-size:12px;font-weight:500"><?= htmlspecialchars($c['role_display']) ?></div>
              <?php if ($c['specialization']): ?><div style="color:var(--g400);font-size:12px"><?= htmlspecialchars($c['specialization']) ?></div><?php endif; ?>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- ========== EMERGENCY ========== -->
    <div class="tab-panel" id="tab-emergency">
      <?php if ($activeEmergency && in_array($activeEmergency['status'],['active','claimed'])): ?>
      <div class="emrg-active">
        <div style="font-size:3rem;margin-bottom:10px">🚨</div>
        <div style="font-weight:700;font-size:18px;color:#991b1b;margin-bottom:6px">Emergency Active</div>
        <div style="font-size:13px;color:#7f1d1d;margin-bottom:14px">
          <?= $activeEmergency['status']==='claimed'&&$activeEmergency['claimed_by_name']
            ? 'Dr. <strong>'.htmlspecialchars($activeEmergency['claimed_by_name']).'</strong> is responding'
            : 'All doctors notified. Help is on the way.' ?>
        </div>
        <div style="background:rgba(239,68,68,0.08);border-radius:8px;padding:12px;margin-bottom:16px;text-align:left">
          <div style="font-size:11px;color:var(--g400);margin-bottom:3px">Your symptoms:</div>
          <div style="font-size:13px"><?= htmlspecialchars($activeEmergency['symptoms']) ?></div>
        </div>
        <form method="POST">
          <input type="hidden" name="cancel_emergency" value="1">
          <input type="hidden" name="emergency_id" value="<?= $activeEmergency['id'] ?>">
          <button type="submit" class="btn-gray" onclick="return confirm('Cancel your emergency?')">
            <i class="bi bi-x-circle"></i> Cancel Declaration
          </button>
        </form>
      </div>
      <?php else: ?>
      <div class="emrg-card">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
          <div style="width:46px;height:46px;background:rgba(239,68,68,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--red);font-size:22px;flex-shrink:0">
            <i class="bi bi-exclamation-triangle-fill"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:15px;color:var(--red)">Declare Medical Emergency</div>
            <div style="font-size:12px;color:var(--g500)">This immediately notifies all available doctors</div>
          </div>
        </div>
        <form method="POST">
          <input type="hidden" name="declare_emergency" value="1">
          <div class="f-group">
            <label class="f-label">Describe Your Emergency</label>
            <textarea class="f-input" name="symptoms" rows="4"
                      placeholder="Describe what you are experiencing urgently..."></textarea>
          </div>
          <div class="f-group">
            <label class="f-label">Severity</label>
            <select class="f-select" name="severity">
              <option value="Mild">Mild</option>
              <option value="Moderate" selected>Moderate</option>
              <option value="Severe">Severe</option>
              <option value="Critical">Critical</option>
            </select>
          </div>
          <button type="submit" class="btn-red">
            <i class="bi bi-heart-pulse-fill"></i> Declare Emergency Now
          </button>
        </form>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- end main -->
</div><!-- end layout -->

<script>
function showTab(name) {
    // Hide all panels
    document.querySelectorAll('.tab-panel').forEach(function(t) {
        t.classList.remove('active');
    });
    // Remove active from all nav buttons
    document.querySelectorAll('.nav-btn').forEach(function(n) {
        n.classList.remove('active');
    });
    // Show selected panel
    var panel = document.getElementById('tab-' + name);
    var nav   = document.getElementById('nav-' + name);
    if (panel) panel.classList.add('active');
    if (nav)   nav.classList.add('active');
}
</script>
</body>
</html>
