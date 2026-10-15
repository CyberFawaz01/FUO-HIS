<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireRole('admin');
$db    = Database::getInstance();
$audit = new AuditLogger();
$pageTitle = 'Assign Patient by Specialty';
$success = $error = '';

$patientId = (int)($_GET['patient_id'] ?? 0);
if (!$patientId) { header('Location: '.APP_URL.'/views/shared/patients.php'); exit; }

$patient = $db->fetchOne(
    "SELECT p.*, d.dept_name FROM patients p LEFT JOIN departments d ON p.department_id=d.id WHERE p.id=?",
    [$patientId]
);
if (!$patient) { header('Location: '.APP_URL.'/views/shared/patients.php'); exit; }

// Get patient condition context
$latestDiagnosis = $db->fetchOne(
    "SELECT content, title FROM patient_records WHERE patient_id=? AND record_type='diagnosis' ORDER BY record_date DESC LIMIT 1",
    [$patientId]
);
$latestSymptom = null;
try {
    $latestSymptom = $db->fetchOne(
        "SELECT symptoms, severity FROM patient_symptoms WHERE patient_id=? ORDER BY created_at DESC LIMIT 1",
        [$patientId]
    );
} catch (Exception $e) { $latestSymptom = null; }

// Handle assign/unassign
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'assign') {
        $userId = (int)$_POST['user_id'];
        $notes  = trim($_POST['notes'] ?? '');
        if (!$userId) {
            $error = 'Please select a staff member.';
        } else {
            $existing = $db->fetchOne(
                "SELECT id FROM assignments WHERE user_id=? AND patient_id=? AND is_active=1",
                [$userId, $patientId]
            );
            if ($existing) {
                $error = 'This staff member is already assigned to this patient.';
            } else {
                $db->query(
                    "INSERT INTO assignments (user_id, patient_id, assigned_by, notes) VALUES (?,?,?,?)",
                    [$userId, $patientId, $_SESSION['user_id'], $notes]
                );
                $doc = $db->fetchOne("SELECT full_name, specialization FROM users WHERE id=?", [$userId]);
                $audit->log($_SESSION['user_id'], 'PATIENT_ASSIGNED', 'admin', $patientId, 'info',
                    null, null, null, "Assigned {$doc['full_name']} ({$doc['specialization']}) to patient #{$patientId}");
                $success = "{$doc['full_name']} ({$doc['specialization']}) assigned successfully.";
            }
        }
    }
    if ($action === 'unassign') {
        $aid = (int)$_POST['assignment_id'];
        $db->query("UPDATE assignments SET is_active=0, end_date=NOW() WHERE id=?", [$aid]);
        $success = 'Assignment removed.';
    }
}

// Current assignments
$currentAssignments = $db->fetchAll(
    "SELECT a.*, u.full_name, u.staff_id, u.specialization, r.display_name AS role_display
     FROM assignments a JOIN users u ON a.user_id=u.id JOIN roles r ON u.role_id=r.id
     WHERE a.patient_id=? AND a.is_active=1 ORDER BY u.specialization",
    [$patientId]
);
$assignedIds = array_column($currentAssignments, 'user_id');

// Get all medical staff grouped by specialization
$allStaff = $db->fetchAll(
    "SELECT u.id, u.full_name, u.staff_id, u.specialization, r.display_name AS role_display, r.role_name, d.dept_name
     FROM users u JOIN roles r ON u.role_id=r.id LEFT JOIN departments d ON u.department_id=d.id
     WHERE r.role_name IN ('doctor','surgeon','consultant','nurse') AND u.is_active=1
     AND u.id NOT IN (" . (empty($assignedIds) ? '0' : implode(',', array_map('intval', $assignedIds))) . ")
     ORDER BY u.specialization, u.full_name"
);

// Group by specialization
$bySpecialty = [];
foreach ($allStaff as $s) {
    $spec = !empty($s['specialization']) ? $s['specialization'] : 'General ('.$s['role_display'].')';
    $bySpecialty[$spec][] = $s;
}
ksort($bySpecialty);

$specIcons = [
    'Paediatrics'            => ['bi-emoji-smile',      '#0369a1','#dbeafe'],
    'Gynaecology'            => ['bi-gender-female',    '#7c3aed','#ede9fe'],
    'Cardiology'             => ['bi-heart-pulse',      '#dc2626','#fee2e2'],
    'Cardiovascular Surgery' => ['bi-scissors',         '#9d174d','#fce7f3'],
    'Orthopaedics'           => ['bi-bandaid',          '#92400e','#fef9c3'],
    'Neurology'              => ['bi-cpu',              '#5b21b6','#ede9fe'],
    'General Medicine'       => ['bi-clipboard2-pulse', '#0f6b42','#dcfce7'],
    'General Practice'       => ['bi-clipboard2-pulse', '#0f6b42','#dcfce7'],
    'Internal Medicine'      => ['bi-heart',            '#0284c7','#e0f2fe'],
    'General Nursing'        => ['bi-heart-fill',       '#0f6b42','#dcfce7'],
    'ICU Nursing'            => ['bi-hospital',         '#0f6b42','#dcfce7'],
    'Clinical Pharmacy'      => ['bi-capsule',          '#7c3aed','#ede9fe'],
    'Clinical Biochemistry'  => ['bi-flask',            '#0369a1','#dbeafe'],
];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-person-plus" style="color:var(--teal);margin-right:8px"></i>Assign Staff by Specialty</h1>
    <p>Patient: <strong><?= htmlspecialchars($patient['patient_id'].' — '.$patient['full_name']) ?></strong></p>
  </div>
  <a href="<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $patientId ?>" class="btn btn-outline btn-sm">
    <i class="bi bi-arrow-left"></i> Back to EHR
  </a>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Patient Summary -->
<div class="card mb-4">
  <div class="card-body" style="padding:16px 20px">
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">
      <div style="width:50px;height:50px;background:linear-gradient(135deg,var(--navy),var(--navy-light));border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--teal);font-size:22px;flex-shrink:0">
        <i class="bi bi-person-fill"></i>
      </div>
      <div style="flex:1">
        <div style="font-weight:700;font-size:15px;color:var(--navy)"><?= htmlspecialchars($patient['full_name']) ?></div>
        <div style="font-size:13px;color:var(--gray-500)"><?= htmlspecialchars($patient['patient_id']) ?> · <?= $patient['gender'] ?> · Blood: <strong style="color:var(--red)"><?= $patient['blood_group']??'Unknown' ?></strong></div>
      </div>
      <?php
        $sb =['admitted'=>'badge-blue','discharged'=>'badge-gray','outpatient'=>'badge-teal','emergency'=>'badge-red'][$patient['admission_status']]??'badge-gray';
        $sb2=['Low'=>'badge-green','Medium'=>'badge-amber','High'=>'badge-red','Critical'=>'badge-navy'][$patient['sensitivity_level']]??'badge-gray';
      ?>
      <span class="badge <?= $sb ?>"><?= ucfirst($patient['admission_status']) ?></span>
      <span class="badge <?= $sb2 ?>"><?= $patient['sensitivity_level'] ?> Sensitivity</span>
    </div>

    <!-- Patient condition context -->
    <?php if ($latestSymptom || $latestDiagnosis || $patient['emergency_condition']): ?>
    <div style="margin-top:14px;display:grid;grid-template-columns:<?= ($latestSymptom && $latestDiagnosis)?'1fr 1fr':'1fr' ?>;gap:12px">
      <?php if ($latestSymptom): ?>
      <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px 14px">
        <div style="font-size:11px;font-weight:600;color:#c2410c;text-transform:uppercase;margin-bottom:4px">
          <i class="bi bi-chat-heart" style="margin-right:4px"></i>Patient Reported Symptoms
        </div>
        <div style="font-size:13px;color:#1e293b"><?= htmlspecialchars($latestSymptom['symptoms']) ?></div>
        <div style="font-size:11px;color:#94a3b8;margin-top:4px">Severity: <?= $latestSymptom['severity'] ?></div>
      </div>
      <?php endif; ?>
      <?php if ($latestDiagnosis): ?>
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 14px">
        <div style="font-size:11px;font-weight:600;color:#1d4ed8;text-transform:uppercase;margin-bottom:4px">
          <i class="bi bi-stethoscope" style="margin-right:4px"></i>Latest Diagnosis
        </div>
        <div style="font-size:13px;color:#1e293b;font-weight:600"><?= htmlspecialchars($latestDiagnosis['title']) ?></div>
        <div style="font-size:12px;color:#64748b;margin-top:2px"><?= htmlspecialchars(substr($latestDiagnosis['content'],0,100)) ?>...</div>
      </div>
      <?php endif; ?>
      <?php if ($patient['emergency_condition']): ?>
      <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 14px">
        <div style="font-size:11px;font-weight:600;color:#991b1b;text-transform:uppercase;margin-bottom:4px">
          <i class="bi bi-exclamation-triangle-fill" style="margin-right:4px"></i>Emergency Condition
        </div>
        <div style="font-size:13px;color:#1e293b"><?= htmlspecialchars($patient['emergency_condition']) ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:16px">

  <!-- Staff grouped by specialty -->
  <div>
    <div style="font-weight:700;font-size:15px;color:var(--navy);margin-bottom:14px">
      <i class="bi bi-people" style="color:var(--teal);margin-right:6px"></i>
      Available Staff — Grouped by Specialty
    </div>

    <?php if (empty($bySpecialty)): ?>
    <div class="card">
      <div style="text-align:center;padding:50px;color:var(--gray-400)">
        <i class="bi bi-people" style="font-size:2.5rem"></i>
        <div style="margin-top:10px;font-weight:600">All available staff already assigned</div>
      </div>
    </div>
    <?php else: ?>
    <?php foreach ($bySpecialty as $specialty => $staffList):
      $icon = $specIcons[$specialty] ?? ['bi-person-badge','var(--teal)','rgba(0,194,168,0.1)'];
    ?>
    <div class="card mb-3">
      <div class="card-header">
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:36px;height:36px;border-radius:9px;background:<?= $icon[2] ?>;display:flex;align-items:center;justify-content:center;color:<?= $icon[1] ?>;font-size:16px;flex-shrink:0">
            <i class="bi <?= $icon[0] ?>"></i>
          </div>
          <div>
            <div style="font-weight:700;font-size:14px;color:var(--navy)"><?= htmlspecialchars($specialty) ?></div>
            <div style="font-size:12px;color:var(--gray-500)"><?= count($staffList) ?> available</div>
          </div>
        </div>
      </div>
      <div class="card-body" style="padding:0">
        <?php foreach ($staffList as $s): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--gray-100)">
          <div style="width:40px;height:40px;border-radius:10px;background:var(--gray-100);display:flex;align-items:center;justify-content:center;color:var(--gray-500);font-size:18px;flex-shrink:0">
            <i class="bi bi-person-fill"></i>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:13px;color:var(--navy)"><?= htmlspecialchars($s['full_name']) ?></div>
            <div style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($s['staff_id']) ?> · <?= htmlspecialchars($s['role_display']) ?></div>
            <?php if ($s['dept_name']): ?>
            <div style="font-size:11px;color:var(--gray-400)"><i class="bi bi-building" style="margin-right:3px"></i><?= htmlspecialchars($s['dept_name']) ?></div>
            <?php endif; ?>
          </div>
          <form method="POST">
            <input type="hidden" name="action"  value="assign">
            <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
            <input type="hidden" name="notes"   value="Assigned based on specialty: <?= htmlspecialchars($specialty) ?>">
            <button type="submit" class="btn btn-teal btn-sm"
                    onclick="return confirm('Assign <?= htmlspecialchars($s['full_name'],ENT_QUOTES) ?> to this patient?')">
              <i class="bi bi-plus-lg"></i> Assign
            </button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Current Assignments + Specialty Guide -->
  <div>
    <div style="font-weight:700;font-size:15px;color:var(--navy);margin-bottom:14px">
      <i class="bi bi-person-check" style="color:var(--green);margin-right:6px"></i>
      Currently Assigned (<?= count($currentAssignments) ?>)
    </div>

    <div class="card mb-3">
      <div class="card-body" style="padding:0">
        <?php if (empty($currentAssignments)): ?>
        <div style="text-align:center;padding:40px;color:var(--gray-400);font-size:13px">
          <i class="bi bi-person-x" style="font-size:2rem;display:block;margin-bottom:8px"></i>
          No staff assigned yet
        </div>
        <?php else: ?>
        <?php foreach ($currentAssignments as $a): ?>
        <div style="padding:14px 16px;border-bottom:1px solid var(--gray-100)">
          <div style="display:flex;align-items:center;gap:12px">
            <div style="width:38px;height:38px;border-radius:9px;background:rgba(0,194,168,0.1);display:flex;align-items:center;justify-content:center;color:var(--teal);font-size:17px;flex-shrink:0">
              <i class="bi bi-person-check-fill"></i>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($a['full_name']) ?></div>
              <div style="font-size:12px;color:var(--teal);font-weight:500"><?= htmlspecialchars($a['specialization']??$a['role_display']) ?></div>
              <div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($a['role_display']) ?> · <?= htmlspecialchars($a['staff_id']) ?></div>
            </div>
            <form method="POST">
              <input type="hidden" name="action"        value="unassign">
              <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 10px"
                      onclick="return confirm('Remove this assignment?')">
                <i class="bi bi-x-lg"></i>
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Specialty Guide -->
    <div class="card" style="background:var(--gray-50)">
      <div class="card-body" style="padding:14px 16px">
        <div style="font-size:12px;font-weight:600;color:var(--gray-500);margin-bottom:10px;text-transform:uppercase">
          <i class="bi bi-lightbulb" style="color:var(--amber);margin-right:5px"></i>Specialty Guide
        </div>
        <?php
        $guide = [
          'Child patients (under 12)'   => 'Paediatrics',
          'Heart / chest problems'      => 'Cardiology',
          'Women\'s health / pregnancy' => 'Gynaecology',
          'Bone / joint problems'       => 'Orthopaedics',
          'Brain / nerve issues'        => 'Neurology',
          'Surgery required'            => 'Cardiovascular Surgery',
          'General illness / fever'     => 'General Medicine',
          'Internal organ problems'     => 'Internal Medicine',
        ];
        foreach ($guide as $condition => $spec): ?>
        <div style="display:flex;justify-content:space-between;font-size:12px;padding:6px 0;border-bottom:1px solid var(--gray-200)">
          <span style="color:var(--gray-600)"><?= $condition ?></span>
          <span style="font-weight:600;color:var(--navy)"><?= $spec ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
