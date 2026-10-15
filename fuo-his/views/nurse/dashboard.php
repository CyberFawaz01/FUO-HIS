<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireRole(['nurse']);
$db    = Database::getInstance();
$audit = new AuditLogger();
$userId = $_SESSION['user_id'];
$ctx    = $_SESSION['context'] ?? [];
$pageTitle = 'Nurse Dashboard — CA-RBAC HIS';
$success = $error = '';

// Only admitted patients assigned to this nurse
$myPatients = $db->fetchAll(
    "SELECT p.*, d.dept_name FROM patients p
     JOIN assignments a ON a.patient_id=p.id AND a.user_id=? AND a.is_active=1
     LEFT JOIN departments d ON p.department_id=d.id
     WHERE p.admission_status = 'admitted'
     ORDER BY p.is_emergency DESC, p.full_name",
    [$userId]
);

// Active emergencies (view only)
$activeEmergencies = $db->fetchAll(
    "SELECT pe.*, p.full_name AS patient_name, p.patient_id AS pid
     FROM patient_emergencies pe
     JOIN patients p ON pe.patient_id=p.id
     WHERE pe.status IN ('active','claimed')
     ORDER BY FIELD(pe.severity,'Critical','Severe','Moderate','Mild'), pe.created_at ASC"
);

// Handle nurse declaring emergency for an admitted assigned patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['declare_emergency'])) {
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $symptoms  = trim($_POST['symptoms'] ?? '');
    $severity  = $_POST['severity'] ?? 'Moderate';

    // Verify patient is assigned to this nurse AND is admitted
    $validPatient = $db->fetchOne(
        "SELECT p.id FROM patients p
         JOIN assignments a ON a.patient_id=p.id AND a.user_id=? AND a.is_active=1
         WHERE p.id=? AND p.admission_status='admitted'",
        [$userId, $patientId]
    );

    if (!$validPatient) {
        $error = 'You can only declare emergency for your admitted assigned patients.';
    } elseif (strlen($symptoms) < 10) {
        $error = 'Please describe the symptoms in more detail (at least 10 characters).';
    } else {
        // Check no active emergency already
        $existing = $db->fetchOne(
            "SELECT id FROM patient_emergencies WHERE patient_id=? AND status IN ('active','claimed')",
            [$patientId]
        );
        if ($existing) {
            $error = 'This patient already has an active emergency declaration.';
        } else {
            $db->query(
                "INSERT INTO patient_emergencies (patient_id, symptoms, severity, status) VALUES (?,?,?,'active')",
                [$patientId, $symptoms, $severity]
            );
            $eid = $db->lastInsertId();

            // Update patient emergency flag
            $db->query(
                "UPDATE patients SET is_emergency=1, emergency_condition=? WHERE id=?",
                [$symptoms, $patientId]
            );

            // Notify all doctors and surgeons
            $doctors = $db->fetchAll("SELECT id FROM users WHERE role_id IN (2,6,7) AND is_active=1");
            foreach ($doctors as $doc) {
                $db->query(
                    "INSERT INTO emergency_notifications (emergency_id, doctor_id) VALUES (?,?)",
                    [$eid, $doc['id']]
                );
            }

            $audit->log($userId, 'NURSE_EMERGENCY_DECLARED', 'emergency', $patientId, 'granted',
                null, null, null, "Nurse declared emergency for patient #$patientId: $symptoms");

            $success = 'Emergency declared successfully. All doctors have been notified.';

            // Refresh patient list
            $myPatients = $db->fetchAll(
                "SELECT p.*, d.dept_name FROM patients p
                 JOIN assignments a ON a.patient_id=p.id AND a.user_id=? AND a.is_active=1
                 LEFT JOIN departments d ON p.department_id=d.id
                 WHERE p.admission_status = 'admitted'
                 ORDER BY p.is_emergency DESC, p.full_name",
                [$userId]
            );
            $activeEmergencies = $db->fetchAll(
                "SELECT pe.*, p.full_name AS patient_name, p.patient_id AS pid
                 FROM patient_emergencies pe
                 JOIN patients p ON pe.patient_id=p.id
                 WHERE pe.status IN ('active','claimed')
                 ORDER BY FIELD(pe.severity,'Critical','Severe','Moderate','Mild'), pe.created_at ASC"
            );
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-heart-pulse me-2" style="color:var(--teal)"></i>Nurse Dashboard</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></p>
  </div>
  <span class="badge <?= ($ctx['is_on_shift']??false)?'badge-green':'badge-amber' ?>" style="padding:8px 14px;font-size:13px">
    <?= ($ctx['is_on_shift']??false)?'On Duty':'Off Shift' ?>
  </span>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Emergency Alert Banner (view only) -->
<?php if (count($activeEmergencies) > 0): ?>
<div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);border-left:4px solid var(--red);border-radius:10px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
  <i class="bi bi-exclamation-triangle-fill" style="color:var(--red);font-size:20px;flex-shrink:0"></i>
  <div style="flex:1">
    <strong style="color:#991b1b"><?= count($activeEmergencies) ?> active emergency<?= count($activeEmergencies)>1?'s':'' ?></strong>
    <span style="color:#b91c1c;font-size:13px"> — Doctors have been notified</span>
  </div>
  <a href="<?= APP_URL ?>/views/shared/emergencies.php" class="btn btn-outline btn-sm">View</a>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card stat-blue">
    <div class="stat-top">
      <div><div class="stat-label">Admitted Patients</div><div class="stat-value"><?= count($myPatients) ?></div></div>
      <div class="stat-icon"><i class="bi bi-people"></i></div>
    </div>
  </div>
  <div class="stat-card stat-red">
    <div class="stat-top">
      <div><div class="stat-label">Active Emergencies</div><div class="stat-value"><?= count($activeEmergencies) ?></div></div>
      <div class="stat-icon"><i class="bi bi-heart-pulse"></i></div>
    </div>
  </div>
  <div class="stat-card stat-teal">
    <div class="stat-top">
      <div><div class="stat-label">Records Today</div>
        <div class="stat-value"><?= $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE created_by=? AND DATE(record_date)=CURDATE()",[$userId])['c'] ?></div>
      </div>
      <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:16px">

  <!-- Patient List -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-people"></i>My Admitted Patients (<?= count($myPatients) ?>)</div>
      <a href="<?= APP_URL ?>/views/shared/patients.php" class="btn btn-outline btn-sm">All Patients</a>
    </div>
    <div class="card-body" style="padding:0">
      <table>
        <thead>
          <tr>
            <th style="padding-left:20px">Patient</th>
            <th>Department</th>
            <th>Sensitivity</th>
            <th>Emergency</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($myPatients as $p): ?>
          <?php
            $sensBadge = ['Low'=>'badge-green','Medium'=>'badge-amber','High'=>'badge-red','Critical'=>'badge-navy'][$p['sensitivity_level']] ?? 'badge-gray';
          ?>
          <tr>
            <td style="padding-left:20px">
              <div style="font-weight:600"><?= htmlspecialchars($p['full_name']) ?>
                <?php if($p['is_emergency']): ?><span class="badge badge-red" style="margin-left:6px">EMRG</span><?php endif; ?>
              </div>
              <div style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($p['patient_id']) ?></div>
            </td>
            <td style="font-size:13px;color:var(--gray-600)"><?= htmlspecialchars($p['dept_name']??'—') ?></td>
            <td><span class="badge <?= $sensBadge ?>"><?= $p['sensitivity_level'] ?></span></td>
            <td>
              <?php if($p['is_emergency']): ?>
              <span class="badge badge-red"><i class="bi bi-heart-pulse-fill"></i> Active</span>
              <?php else: ?>
              <span style="color:var(--gray-400);font-size:12px">Stable</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex" style="gap:6px">
                <a href="<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $p['id'] ?>"
                   class="btn btn-outline btn-sm"><i class="bi bi-folder2-open"></i> EHR</a>
                <?php if (!$p['is_emergency']): ?>
                <button class="btn btn-danger btn-sm"
                        onclick="openDeclareModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['full_name'], ENT_QUOTES) ?>')">
                  <i class="bi bi-exclamation-triangle"></i> Emergency
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($myPatients)): ?>
          <tr>
            <td colspan="5" style="text-align:center;padding:40px;color:var(--gray-400)">
              No admitted patients assigned to you
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right Panel -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Active Emergencies (view only) -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="bi bi-heart-pulse-fill" style="color:var(--red)"></i>Active Emergencies</div>
        <a href="<?= APP_URL ?>/views/shared/emergencies.php" class="btn btn-outline btn-sm">View All</a>
      </div>
      <div class="card-body" style="padding:0">
        <?php if (empty($activeEmergencies)): ?>
        <div style="text-align:center;padding:24px;color:var(--gray-400);font-size:13px">
          <i class="bi bi-check-circle" style="color:var(--green);font-size:1.5rem"></i>
          <div style="margin-top:6px">No active emergencies</div>
        </div>
        <?php else: ?>
        <?php foreach ($activeEmergencies as $e): ?>
        <?php
          $sevColor = ['Critical'=>'var(--red)','Severe'=>'#dc2626','Moderate'=>'var(--amber)','Mild'=>'var(--green)'][$e['severity']] ?? 'var(--gray-500)';
        ?>
        <div style="padding:12px 16px;border-bottom:1px solid var(--gray-100)">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
            <span style="font-weight:600;font-size:13px"><?= htmlspecialchars($e['patient_name']) ?></span>
            <span style="background:<?= $sevColor ?>22;color:<?= $sevColor ?>;border-radius:20px;padding:2px 8px;font-size:11px;font-weight:600"><?= $e['severity'] ?></span>
          </div>
          <div style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars(substr($e['symptoms'],0,60)) ?>...</div>
          <div style="font-size:11px;color:var(--gray-400);margin-top:4px">
            <?= $e['status']==='claimed' ? '<i class="bi bi-person-check" style="color:var(--green)"></i> Doctor responding' : '<i class="bi bi-hourglass-split" style="color:var(--amber)"></i> Awaiting doctor' ?>
          </div>
          <div style="margin-top:6px">
            <span class="badge badge-gray" style="font-size:10px"><i class="bi bi-eye-slash"></i> View Only — No EHR Access</span>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="bi bi-lightning"></i>Quick Actions</div></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
        <a href="<?= APP_URL ?>/views/shared/patients.php" class="btn btn-outline w-100">
          <i class="bi bi-file-earmark-medical"></i> Patient Records
        </a>
        <a href="<?= APP_URL ?>/views/shared/emergencies.php" class="btn btn-outline w-100">
          <i class="bi bi-heart-pulse"></i> View Emergencies
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Declare Emergency Modal -->
<div id="declareModal" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,0.7);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:20px;width:100%;max-width:500px;margin:20px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,0.2)">
    <div style="background:linear-gradient(135deg,#dc2626,#ef4444);padding:20px 24px;display:flex;align-items:center;justify-content:space-between">
      <div style="color:white;font-weight:700;font-size:16px">
        <i class="bi bi-exclamation-triangle-fill" style="margin-right:8px"></i>
        Declare Patient Emergency
      </div>
      <button onclick="document.getElementById('declareModal').style.display='none'"
              style="border:none;background:rgba(255,255,255,0.2);color:white;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:16px">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="declare_emergency" value="1">
      <input type="hidden" name="patient_id" id="declarePatientId">
      <div style="padding:24px">
        <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#991b1b">
          <i class="bi bi-info-circle" style="margin-right:6px"></i>
          Declaring emergency for: <strong id="declarePatientName"></strong>
        </div>
        <div class="form-group">
          <label class="form-label">Patient Symptoms / Reason <span style="color:var(--red)">*</span></label>
          <textarea class="form-control" name="symptoms" rows="4" required minlength="10"
                    placeholder="Describe the patient's condition, symptoms, and reason for emergency declaration..."></textarea>
          <div style="font-size:11px;color:var(--gray-400);margin-top:4px">Minimum 10 characters required</div>
        </div>
        <div class="form-group">
          <label class="form-label">Severity Level <span style="color:var(--red)">*</span></label>
          <select class="form-select" name="severity" required>
            <option value="Mild">Mild</option>
            <option value="Moderate" selected>Moderate</option>
            <option value="Severe">Severe</option>
            <option value="Critical">Critical</option>
          </select>
        </div>
        <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.3);border-radius:8px;padding:10px 14px;font-size:12px;color:#92400e">
          <i class="bi bi-bell-fill" style="margin-right:6px"></i>
          This will immediately notify all available doctors.
        </div>
      </div>
      <div style="padding:16px 24px;border-top:1px solid var(--gray-100);display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('declareModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-danger">
          <i class="bi bi-exclamation-triangle-fill"></i> Declare Emergency
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openDeclareModal(patientId, patientName) {
    document.getElementById('declarePatientId').value  = patientId;
    document.getElementById('declarePatientName').textContent = patientName;
    document.getElementById('declareModal').style.display = 'flex';
}
document.getElementById('declareModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
