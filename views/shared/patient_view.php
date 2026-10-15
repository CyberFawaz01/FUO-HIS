<?php
// CA-RBAC HIS — Patient EHR View (PEP) + Doctor Guidance Panel
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/PolicyDecisionPoint.php';
require_once __DIR__ . '/../../includes/DoctorGuidance.php';

$auth = new AuthController();
$auth->requireLogin();
$pdp = new PolicyDecisionPoint();
$db  = Database::getInstance();

$userId   = $_SESSION['user_id'];
$roleId   = $_SESSION['role_id'];
$roleName = $_SESSION['role_name'];

$patientId = (int)($_GET['id'] ?? 0);
if (!$patientId) { header('Location: ' . APP_URL . '/views/shared/patients.php'); exit; }

$patient = $db->fetchOne(
    "SELECT p.*, d.dept_name FROM patients p LEFT JOIN departments d ON p.department_id=d.id WHERE p.id=?",
    [$patientId]
);
if (!$patient) { header('Location: ' . APP_URL . '/views/shared/patients.php'); exit; }

// Policy check
$authResult = $pdp->evaluate($userId, 'read_ehr', $patientId);

// Get latest symptom report
$latestSymptoms = null;
try {
    $latestSymptoms = $db->fetchOne(
        "SELECT * FROM patient_symptoms WHERE patient_id=? ORDER BY created_at DESC LIMIT 1",
        [$patientId]
    );
} catch (Exception $e) { $latestSymptoms = null; }

// Run guidance engine
$guidance = null;
if ($latestSymptoms && in_array($roleName, ['doctor','surgeon','consultant','admin'])) {
    $guidance = DoctorGuidance::analyse($latestSymptoms['symptoms']);
}

// Handle acknowledge
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['acknowledge_symptoms'])) {
    $sid = (int)$_POST['symptom_id'];
    $db->query(
        "UPDATE patient_symptoms SET status='acknowledged', acknowledged_by=?, acknowledged_at=NOW() WHERE id=?",
        [$userId, $sid]
    );
    header("Location: " . APP_URL . "/views/shared/patient_view.php?id=$patientId");
    exit;
}

// Record types per role
$roleRecordTypes = [
    'admin'      => ['diagnosis','medication','allergy','treatment','lab_request','surgery_note','consultation','vitals'],
    'doctor'     => ['diagnosis','medication','allergy','treatment','lab_request','consultation','vitals'],
    'nurse'      => ['vitals','treatment','consultation'],
    'surgeon'    => ['surgery_note','diagnosis','treatment','lab_request','vitals'],
    'consultant' => ['diagnosis','medication','allergy','treatment','lab_request','surgery_note','consultation','vitals'],
    'lab_tech'   => ['lab_report'],
];
$allowedTypes = $roleRecordTypes[$roleName] ?? [];

$typeLabels = [
    'diagnosis'    => 'Diagnosis',
    'medication'   => 'Medication / Prescription',
    'allergy'      => 'Allergy',
    'treatment'    => 'Treatment Note',
    'lab_request'  => 'Lab Test Request',
    'lab_report'   => 'Lab Report (Upload)',
    'surgery_note' => 'Surgery Note',
    'consultation' => 'Consultation Note',
    'vitals'       => 'Vital Signs',
];

$pageTitle = 'Patient EHR — ' . htmlspecialchars($patient['full_name']);
include __DIR__ . '/../../includes/header.php';
?>

<?php if (!$authResult['allowed']): ?>
<!-- ACCESS DENIED — contextual messages -->
<?php
$denialType = $authResult['decision'] ?? 'denied';
$icons = [
    'location_denied'  => ['bi-geo-alt-slash',     '#dc2626', 'rgba(220,38,38,0.08)', 'rgba(220,38,38,0.3)'],
    'shift_denied'     => ['bi-clock-history',      '#d97706', 'rgba(217,119,6,0.08)', 'rgba(217,119,6,0.3)'],
    'unassigned_denied'=> ['bi-person-x',           '#7c3aed', 'rgba(124,58,237,0.08)','rgba(124,58,237,0.3)'],
    'denied'           => ['bi-shield-x',           '#dc2626', 'rgba(220,38,38,0.08)', 'rgba(220,38,38,0.3)'],
    'override_required'=> ['bi-shield-exclamation', '#dc2626', 'rgba(220,38,38,0.08)', 'rgba(220,38,38,0.3)'],
];
$ic = $icons[$denialType] ?? $icons['denied'];
$titles = [
    'location_denied'   => '📍 Outside Hospital Premises',
    'shift_denied'      => '🕐 Not Currently On Duty',
    'unassigned_denied' => '👤 Patient Not Assigned To You',
    'denied'            => '🚫 Access Denied',
    'override_required' => '⚠️ High Risk — Override Required',
];
$title = $titles[$denialType] ?? 'Access Denied';
?>
<div style="background:<?= $ic[2] ?>;border:1px solid <?= $ic[3] ?>;border-left:5px solid <?= $ic[1] ?>;border-radius:14px;padding:28px;margin-bottom:20px">
  <div style="display:flex;align-items:flex-start;gap:18px">
    <div style="width:54px;height:54px;background:<?= $ic[3] ?>;border-radius:14px;display:flex;align-items:center;justify-content:center;color:<?= $ic[1] ?>;font-size:24px;flex-shrink:0">
      <i class="bi <?= $ic[0] ?>"></i>
    </div>
    <div style="flex:1">
      <h5 style="color:<?= $ic[1] ?>;font-weight:800;margin-bottom:8px;font-size:16px"><?= $title ?></h5>
      <p style="color:var(--gray-700);margin-bottom:16px;font-size:14px;line-height:1.6"><?= htmlspecialchars($authResult['reason']) ?></p>

      <?php if ($denialType === 'location_denied'): ?>
      <!-- Location specific info only -->
      <div style="background:white;border-radius:10px;padding:14px 16px;margin-bottom:14px;border:1px solid <?= $ic[3] ?>">
        <div style="font-size:12px;font-weight:600;color:<?= $ic[1] ?>;text-transform:uppercase;margin-bottom:8px">Location Details</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px">
          <div><span style="color:var(--gray-500)">Your Location:</span><br><strong><?= htmlspecialchars($context['geolocation_address'] ?? 'GPS detected') ?></strong></div>
          <div><span style="color:var(--gray-500)">Distance from Hospital:</span><br><strong style="color:<?= $ic[1] ?>"><?= isset($context['distance_from_hospital']) ? (($context['distance_from_hospital'] >= 1000) ? round($context['distance_from_hospital']/1000,1).' km' : $context['distance_from_hospital'].' metres') : 'Unknown' ?></strong></div>
          <div><span style="color:var(--gray-500)">Required Zone:</span><br><strong><?= HOSPITAL_RADIUS_METRES ?>m radius</strong></div>
          <div><span style="color:var(--gray-500)">Hospital:</span><br><strong><?= HOSPITAL_NAME ?></strong></div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($denialType === 'shift_denied'): ?>
      <!-- Shift specific info only -->
      <div style="background:white;border-radius:10px;padding:14px 16px;margin-bottom:14px;border:1px solid <?= $ic[3] ?>">
        <div style="font-size:12px;font-weight:600;color:<?= $ic[1] ?>;text-transform:uppercase;margin-bottom:8px">Shift Information</div>
        <div style="font-size:13px;display:flex;flex-direction:column;gap:8px">
          <div style="display:flex;align-items:center;gap:8px">
            <i class="bi bi-circle-fill" style="color:<?= $ic[1] ?>;font-size:8px"></i>
            <span><span style="color:var(--gray-500)">Current Status:</span> <strong style="color:<?= $ic[1] ?>">Off Duty</strong></span>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <i class="bi bi-circle-fill" style="color:var(--gray-400);font-size:8px"></i>
            <?php if (!empty($context['shift_data']['next_shift'])): ?>
            <span><span style="color:var(--gray-500)">Next Shift Starts:</span> <strong><?= htmlspecialchars($context['shift_data']['next_shift']) ?></strong></span>
            <?php else: ?>
            <span><span style="color:var(--gray-500)">Next Shift:</span> <strong>No upcoming shift — contact admin to schedule</strong></span>
            <?php endif; ?>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <i class="bi bi-info-circle" style="color:var(--gray-400);font-size:13px"></i>
            <span style="font-size:12px;color:var(--gray-500)">If a patient needs urgent attention, ask them to declare an emergency from their patient portal.</span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($denialType === 'unassigned_denied'): ?>
      <!-- Unassigned specific info -->
      <div style="background:white;border-radius:10px;padding:14px 16px;margin-bottom:14px;border:1px solid <?= $ic[3] ?>">
        <div style="font-size:12px;font-weight:600;color:<?= $ic[1] ?>;text-transform:uppercase;margin-bottom:8px">Assignment Information</div>
        <div style="font-size:13px;display:flex;flex-direction:column;gap:8px">
          <div style="display:flex;align-items:center;gap:8px">
            <i class="bi bi-person-x" style="color:<?= $ic[1] ?>;font-size:15px"></i>
            <span>This patient is <strong>not assigned</strong> to you.</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <i class="bi bi-info-circle" style="color:var(--gray-400);font-size:13px"></i>
            <span style="font-size:12px;color:var(--gray-500)">Contact the administrator to be assigned to this patient, or ask the patient to declare an emergency if urgent.</span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Back button only — no override button for doctors -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <a href="<?= APP_URL ?>/views/shared/patients.php" class="btn btn-outline btn-sm">
          <i class="bi bi-arrow-left"></i> Back to Patients
        </a>
      </div>

      <!-- Audit note -->
      <div style="margin-top:14px;font-size:11px;color:var(--gray-400);display:flex;align-items:center;gap:5px">
        <i class="bi bi-journal-text"></i>
        This access attempt has been logged in the audit trail.
      </div>
    </div>
  </div>
</div>

<?php else: ?>

<!-- ACCESS GRANTED BANNER -->
<div style="background:<?= $authResult['decision']==='conditional'?'rgba(245,158,11,0.08)':'rgba(16,185,129,0.08)' ?>;border:1px solid <?= $authResult['decision']==='conditional'?'rgba(245,158,11,0.3)':'rgba(16,185,129,0.3)' ?>;border-left:4px solid <?= $authResult['decision']==='conditional'?'var(--amber)':'var(--green)' ?>;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <i class="bi bi-shield-check" style="color:<?= $authResult['decision']==='conditional'?'var(--amber)':'var(--green)' ?>;font-size:18px;flex-shrink:0"></i>
  <div style="flex:1;font-size:13px"><strong>Access <?= $authResult['decision']==='conditional'?'Conditionally Granted':'Granted' ?></strong> — <?= htmlspecialchars($authResult['reason']) ?></div>
  <span class="risk-badge risk-<?= $authResult['risk']['level'] ?>"><?= $authResult['risk']['level'] ?> (<?= $authResult['risk']['score'] ?>)</span>
</div>

<!-- ============================================================ -->
<!-- DOCTOR GUIDANCE PANEL -->
<!-- ============================================================ -->
<?php if ($latestSymptoms && $guidance && in_array($roleName, ['doctor','surgeon','consultant','admin'])): ?>
<div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:14px;overflow:hidden;margin-bottom:20px">

  <!-- Header -->
  <div style="background:linear-gradient(135deg,#0f6b42,#1a8a56);padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
    <div style="display:flex;align-items:center;gap:10px">
      <div style="width:36px;height:36px;background:rgba(255,255,255,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#86efac;font-size:17px">
        <i class="bi bi-lightbulb-fill"></i>
      </div>
      <div>
        <div style="color:white;font-weight:700;font-size:14px">Doctor Guidance Panel</div>
        <div style="color:rgba(255,255,255,0.6);font-size:12px">Based on patient reported symptoms — <?= date('d M Y', strtotime($latestSymptoms['created_at'])) ?></div>
      </div>
    </div>
    <span style="background:<?= $latestSymptoms['status']==='pending'?'rgba(245,158,11,0.3)':'rgba(255,255,255,0.2)' ?>;color:<?= $latestSymptoms['status']==='pending'?'#fde68a':'#86efac' ?>;border-radius:20px;padding:3px 12px;font-size:11px;font-weight:600">
      <?= $latestSymptoms['status']==='pending'?'⏳ Awaiting Review':'✓ Acknowledged' ?>
    </span>
  </div>

  <div style="padding:20px">

    <!-- Patient complaint -->
    <div style="background:white;border:1px solid #bbf7d0;border-radius:10px;padding:14px 16px;margin-bottom:16px">
      <div style="font-size:11px;font-weight:600;color:#166534;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">
        <i class="bi bi-chat-quote-fill" style="margin-right:5px"></i>Patient's Complaint
      </div>
      <div style="font-size:14px;color:#1e293b;line-height:1.6">"<?= htmlspecialchars($latestSymptoms['symptoms']) ?>"</div>
      <div style="display:flex;gap:16px;margin-top:8px;font-size:12px;color:#64748b">
        <?php if ($latestSymptoms['duration']): ?>
        <span><i class="bi bi-clock" style="margin-right:3px"></i>Duration: <strong><?= htmlspecialchars($latestSymptoms['duration']) ?></strong></span>
        <?php endif; ?>
        <span><i class="bi bi-thermometer" style="margin-right:3px"></i>Severity: <strong><?= $latestSymptoms['severity'] ?></strong></span>
      </div>
      <?php if ($latestSymptoms['additional_notes']): ?>
      <div style="margin-top:8px;background:#f8fafc;border-radius:6px;padding:8px 10px;font-size:13px;color:#475569">
        <i class="bi bi-info-circle" style="margin-right:4px"></i><?= htmlspecialchars($latestSymptoms['additional_notes']) ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($guidance['has_guidance']): ?>

    <!-- Matched keywords -->
    <div style="margin-bottom:14px">
      <div style="font-size:11px;font-weight:600;color:#166534;text-transform:uppercase;margin-bottom:6px">Detected Symptoms</div>
      <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php foreach ($guidance['matched_keywords'] as $kw): ?>
        <span style="background:#dcfce7;color:#166534;border:1px solid #86efac;border-radius:20px;padding:3px 12px;font-size:12px;font-weight:600"><?= htmlspecialchars($kw) ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Three columns: Conditions, Tests, Specialty -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:16px">

      <!-- Possible Conditions -->
      <div style="background:white;border:1px solid #bbf7d0;border-radius:10px;padding:14px">
        <div style="font-size:11px;font-weight:700;color:#166534;text-transform:uppercase;margin-bottom:10px;display:flex;align-items:center;gap:6px">
          <i class="bi bi-stethoscope"></i> Possible Conditions
        </div>
        <?php foreach ($guidance['possible_conditions'] as $cond): ?>
        <div style="display:flex;align-items:center;gap:6px;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:13px;color:#1e293b">
          <span style="width:6px;height:6px;border-radius:50%;background:#0f6b42;flex-shrink:0"></span>
          <?= htmlspecialchars($cond) ?>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Recommended Tests -->
      <div style="background:white;border:1px solid #bbf7d0;border-radius:10px;padding:14px">
        <div style="font-size:11px;font-weight:700;color:#166534;text-transform:uppercase;margin-bottom:10px;display:flex;align-items:center;gap:6px">
          <i class="bi bi-flask"></i> Recommended Tests
        </div>
        <?php foreach ($guidance['recommended_tests'] as $test): ?>
        <div style="display:flex;align-items:center;gap:6px;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:13px;color:#1e293b">
          <i class="bi bi-check2" style="color:#0f6b42;flex-shrink:0"></i>
          <?= htmlspecialchars($test) ?>
        </div>
        <?php endforeach; ?>
        <?php if (in_array($roleName,['doctor','surgeon','consultant'])): ?>
        <button onclick="prefillLabRequest('<?= implode(', ', array_slice($guidance['recommended_tests'],0,3)) ?>')"
                style="margin-top:10px;width:100%;padding:7px;background:#0f6b42;color:white;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px">
          <i class="bi bi-plus-circle"></i> Create Lab Request
        </button>
        <?php endif; ?>
      </div>

      <!-- Specialty + Actions -->
      <div style="background:white;border:1px solid #bbf7d0;border-radius:10px;padding:14px">
        <div style="font-size:11px;font-weight:700;color:#166534;text-transform:uppercase;margin-bottom:10px;display:flex;align-items:center;gap:6px">
          <i class="bi bi-person-badge"></i> Suggested Specialty
        </div>
        <div style="background:#f0fdf4;border-radius:8px;padding:12px;text-align:center;margin-bottom:12px">
          <div style="font-size:18px;font-weight:800;color:#0f6b42"><?= htmlspecialchars($guidance['suggested_specialty']) ?></div>
          <div style="font-size:12px;color:#64748b;margin-top:2px">Recommended department</div>
        </div>
        <?php if (in_array($roleName,['doctor','surgeon','consultant'])): ?>
        <button onclick="prefillDiagnosis()"
                style="width:100%;padding:7px;background:linear-gradient(135deg,#0f6b42,#1a8a56);color:white;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;margin-bottom:6px">
          <i class="bi bi-stethoscope"></i> Make Diagnosis
        </button>
        <?php endif; ?>
        <?php if ($latestSymptoms['status']==='pending'): ?>
        <form method="POST">
          <input type="hidden" name="acknowledge_symptoms" value="1">
          <input type="hidden" name="symptom_id" value="<?= $latestSymptoms['id'] ?>">
          <button type="submit" style="width:100%;padding:7px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px">
            <i class="bi bi-check2-circle"></i> Acknowledge Report
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <?php else: ?>
    <div style="background:white;border:1px solid #bbf7d0;border-radius:10px;padding:16px;text-align:center;color:#64748b;font-size:13px">
      <i class="bi bi-info-circle" style="font-size:1.5rem;color:#0f6b42;display:block;margin-bottom:8px"></i>
      No specific conditions matched from symptoms. Review patient complaint manually.
    </div>
    <?php endif; ?>

  </div>
</div>
<?php endif; ?>

<!-- PATIENT HEADER -->
<div class="page-header">
  <div style="display:flex;align-items:center;gap:16px">
    <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--navy),var(--navy-light));border-radius:14px;display:flex;align-items:center;justify-content:center;color:var(--teal);font-size:24px;flex-shrink:0">
      <i class="bi bi-person-fill"></i>
    </div>
    <div>
      <h1 style="font-size:20px;margin-bottom:2px">
        <?= htmlspecialchars($patient['full_name']) ?>
        <?php if ($patient['is_emergency']): ?><span class="badge badge-red" style="font-size:12px;margin-left:8px">EMERGENCY</span><?php endif; ?>
      </h1>
      <p><?= htmlspecialchars($patient['patient_id']) ?> | <?= $patient['gender'] ?> | DOB: <?= date('d M Y',strtotime($patient['date_of_birth'])) ?> | Blood: <strong style="color:var(--red)"><?= $patient['blood_group']??'Unknown' ?></strong></p>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php
      $sb =['admitted'=>'badge-blue','discharged'=>'badge-gray','outpatient'=>'badge-teal','emergency'=>'badge-red'][$patient['admission_status']]??'badge-gray';
      $sb2=['Low'=>'badge-green','Medium'=>'badge-amber','High'=>'badge-red','Critical'=>'badge-navy'][$patient['sensitivity_level']]??'badge-gray';
    ?>
    <span class="badge <?= $sb ?>" style="font-size:13px;padding:6px 14px"><?= ucfirst($patient['admission_status']) ?></span>
    <span class="badge <?= $sb2 ?>" style="font-size:13px;padding:6px 14px"><?= $patient['sensitivity_level'] ?> Sensitivity</span>
  </div>
</div>

<!-- CONTENT GRID -->
<div style="display:grid;grid-template-columns:300px 1fr;gap:16px">

  <!-- Demographics -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="bi bi-person-vcard"></i>Demographics</div></div>
      <div class="card-body" style="padding:0">
        <?php
        $fields=[
          ['Patient ID',$patient['patient_id']],['Full Name',$patient['full_name']],
          ['Date of Birth',date('d M Y',strtotime($patient['date_of_birth']))],
          ['Gender',$patient['gender']],['Blood Group',$patient['blood_group']??'Unknown'],
          ['Phone',$patient['phone']??'—'],['Department',$patient['dept_name']??'—'],
          ['Admitted',$patient['admitted_date']?date('d M Y',strtotime($patient['admitted_date'])):'N/A'],
        ];
        foreach ($fields as $f): ?>
        <div style="display:flex;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--gray-100);font-size:13px">
          <span style="color:var(--gray-500);font-weight:500"><?= $f[0] ?></span>
          <span style="color:var(--gray-800);font-weight:600;text-align:right;max-width:160px"><?= htmlspecialchars($f[1]) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if ($patient['is_emergency'] && $patient['emergency_condition']): ?>
        <div style="padding:10px 16px;background:rgba(239,68,68,0.06)">
          <div style="font-size:11px;color:var(--red);font-weight:600;margin-bottom:4px">EMERGENCY CONDITION</div>
          <div style="font-size:13px;color:var(--gray-700)"><?= htmlspecialchars($patient['emergency_condition']) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php if (!empty($allowedTypes) && $pdp->checkRBACPermission($roleId,'write_ehr')): ?>
    <button class="btn btn-teal w-100" style="width:100%"
            onclick="document.getElementById('addRecordModal').style.display='flex'">
      <i class="bi bi-plus-lg"></i> Add EHR Record
    </button>
    <?php endif; ?>
  </div>

  <!-- EHR Records -->
  <div class="card">
    <div class="card-header"><div class="card-title"><i class="bi bi-clipboard2-pulse"></i>Electronic Health Records</div></div>
    <div class="card-body">
      <?php
      $records = $db->fetchAll(
          "SELECT pr.*, u.full_name AS created_by_name FROM patient_records pr
           LEFT JOIN users u ON pr.created_by=u.id
           WHERE pr.patient_id=? ORDER BY pr.record_date DESC",
          [$patientId]
      );
      ?>
      <?php if (empty($records)): ?>
      <div style="text-align:center;padding:40px;color:var(--gray-400)">
        <i class="bi bi-folder2-open" style="font-size:2.5rem"></i>
        <div style="margin-top:10px;font-weight:600">No records yet</div>
      </div>
      <?php else: ?>
      <?php foreach ($records as $rec):
        $typeColors=['diagnosis'=>'var(--blue)','medication'=>'var(--teal)','allergy'=>'var(--amber)','treatment'=>'var(--blue)','lab_report'=>'var(--purple)','lab_request'=>'#f97316','surgery_note'=>'#ec4899','consultation'=>'var(--teal)','vitals'=>'var(--green)'];
        $tc=$typeColors[$rec['record_type']]??'var(--gray-400)';
        $typeBadge=['diagnosis'=>'badge-blue','medication'=>'badge-teal','allergy'=>'badge-amber','treatment'=>'badge-blue','lab_report'=>'badge-purple','lab_request'=>'badge-amber','surgery_note'=>'badge-navy','consultation'=>'badge-teal','vitals'=>'badge-green'];
        $tb=$typeBadge[$rec['record_type']]??'badge-gray';
        $isCompleted=strpos($rec['content'],'[COMPLETED]')===0;
      ?>
      <div style="background:var(--white);border:1px solid var(--gray-200);border-left:3px solid <?= $tc ?>;border-radius:10px;padding:14px 16px;margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;flex-wrap:wrap;gap:6px">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span class="badge <?= $tb ?>"><?= htmlspecialchars($typeLabels[$rec['record_type']]??ucfirst(str_replace('_',' ',$rec['record_type']))) ?></span>
            <strong style="font-size:13px"><?= htmlspecialchars($rec['title']) ?></strong>
            <?php if ($rec['record_type']==='lab_request'): ?>
            <span class="badge <?= $isCompleted?'badge-green':'badge-amber' ?>" style="font-size:10px"><?= $isCompleted?'Completed':'Pending Lab' ?></span>
            <?php endif; ?>
          </div>
          <span style="font-size:11px;color:var(--gray-400);white-space:nowrap"><?= date('d M Y H:i',strtotime($rec['record_date'])) ?></span>
        </div>
        <p style="margin:0;font-size:13px;color:var(--gray-700);line-height:1.6"><?= nl2br(htmlspecialchars(str_replace('[COMPLETED]\n','',$rec['content']))) ?></p>
        <div style="margin-top:6px;font-size:11px;color:var(--gray-400)">By: <?= htmlspecialchars($rec['created_by_name']??'System') ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ADD RECORD MODAL -->
<?php if (!empty($allowedTypes) && $pdp->checkRBACPermission($roleId,'write_ehr')): ?>
<div id="addRecordModal" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,0.7);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:20px;width:100%;max-width:560px;margin:20px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,0.2);max-height:90vh;overflow-y:auto">
    <div style="background:linear-gradient(135deg,var(--navy),var(--navy-light));padding:20px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0">
      <div style="color:white;font-weight:700;font-size:16px"><i class="bi bi-plus-circle" style="margin-right:8px;color:var(--teal)"></i>Add EHR Record</div>
      <button onclick="document.getElementById('addRecordModal').style.display='none'"
              style="border:none;background:rgba(255,255,255,0.15);color:white;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:16px">&times;</button>
    </div>
    <form method="POST" action="<?= APP_URL ?>/controllers/add_record.php">
      <input type="hidden" name="patient_id" value="<?= $patientId ?>">
      <div style="padding:24px">
        <div class="form-group">
          <label class="form-label">Record Type <span style="color:var(--red)">*</span></label>
          <select class="form-select" name="record_type" required onchange="updateHint(this.value)">
            <option value="">-- Select Type --</option>
            <?php foreach ($allowedTypes as $t): ?>
            <option value="<?= $t ?>"><?= htmlspecialchars($typeLabels[$t]??ucfirst(str_replace('_',' ',$t))) ?></option>
            <?php endforeach; ?>
          </select>
          <div id="typeHint" style="font-size:11px;color:var(--gray-500);margin-top:4px"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Title <span style="color:var(--red)">*</span></label>
          <input type="text" class="form-control" name="title" id="recordTitle" required placeholder="e.g. Malaria, Amlodipine 10mg...">
        </div>
        <div class="form-group">
          <label class="form-label" id="contentLabel">Content / Notes <span style="color:var(--red)">*</span></label>
          <textarea class="form-control" name="content" rows="5" required id="recordContent" placeholder="Enter details..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Sensitivity Level <span style="color:var(--red)">*</span></label>
          <select class="form-select" name="sensitivity_level" required>
            <option value="Low">Low</option><option value="Medium">Medium</option>
            <option value="High">High</option><option value="Critical">Critical</option>
          </select>
        </div>
      </div>
      <div style="padding:16px 24px;border-top:1px solid var(--gray-100);display:flex;gap:10px;justify-content:flex-end;position:sticky;bottom:0;background:white">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('addRecordModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-teal"><i class="bi bi-save"></i> Save Record</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
document.getElementById('addRecordModal') && document.getElementById('addRecordModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none'});

const hints={
  'diagnosis':'Describe the patient condition based on clinical findings.',
  'medication':'Drug name — Dose — Frequency — Route — Duration',
  'allergy':'Allergen, reaction type, and severity.',
  'treatment':'Treatment plan, procedures, and expected outcomes.',
  'lab_request':'Specify tests required and clinical instructions for the laboratory.',
  'lab_report':'Test results, values, reference ranges, and remarks.',
  'surgery_note':'Surgical procedure, findings, and post-op plan.',
  'consultation':'Consultation summary, recommendations, and follow-up plan.',
  'vitals':'BP: /  mmHg | Pulse:  bpm | Temp:  °C | RR:  /min | SpO2:  % | Weight:  kg',
};

function updateHint(type){
  document.getElementById('typeHint').textContent=hints[type]||'';
  document.getElementById('contentLabel').innerHTML=type==='lab_request'?'Lab Test Instructions <span style="color:var(--red)">*</span>':'Content / Notes <span style="color:var(--red)">*</span>';
}

function prefillLabRequest(tests){
  document.getElementById('addRecordModal').style.display='flex';
  setTimeout(()=>{
    const sel=document.querySelector('select[name="record_type"]');
    if(sel){sel.value='lab_request';updateHint('lab_request');}
    const t=document.getElementById('recordTitle');
    if(t) t.value='Lab Tests Required';
    const c=document.getElementById('recordContent');
    if(c) c.value='Tests Required:\n'+tests.split(', ').map(t=>'- '+t).join('\n')+'\n\nClinical Indication: Based on patient reported symptoms.';
  },100);
}

function prefillDiagnosis(){
  document.getElementById('addRecordModal').style.display='flex';
  setTimeout(()=>{
    const sel=document.querySelector('select[name="record_type"]');
    if(sel){sel.value='diagnosis';updateHint('diagnosis');}
  },100);
}
</script>

<?php endif; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
