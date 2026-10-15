<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
$auth = new AuthController();
$auth->requireRole(['doctor']);
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$ctx    = $_SESSION['context'] ?? [];
$pageTitle = 'Doctor Dashboard — CA-RBAC HIS';

$myPatients = $db->fetchAll(
    "SELECT p.*, d.dept_name FROM patients p
     JOIN assignments a ON a.patient_id=p.id AND a.user_id=? AND a.is_active=1
     LEFT JOIN departments d ON p.department_id=d.id
     ORDER BY p.is_emergency DESC, p.admission_status",
    [$userId]
);
$emergencyCount = count(array_filter($myPatients, fn($p) => $p['is_emergency']));
$admittedCount  = count(array_filter($myPatients, fn($p) => $p['admission_status']==='admitted'));

// Active patient emergencies
$activeEmergencies = $db->fetchAll(
    "SELECT pe.*, p.full_name AS patient_name, p.patient_id AS pid
     FROM patient_emergencies pe
     JOIN patients p ON pe.patient_id=p.id
     WHERE pe.status IN ('active','claimed')
     ORDER BY FIELD(pe.severity,'Critical','Severe','Moderate','Mild'), pe.created_at ASC"
);

$recentRecords = $db->fetchAll(
    "SELECT pr.*, p.full_name AS patient_name, p.patient_id AS pid
     FROM patient_records pr
     JOIN patients p ON pr.patient_id=p.id
     WHERE pr.created_by=? ORDER BY pr.record_date DESC LIMIT 5",
    [$userId]
);

// FIX for line 69 error: use variable for array_slice result before end()
$slicedEmergencies = array_slice($activeEmergencies, 0, 2);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-stethoscope" style="color:var(--teal);margin-right:8px"></i>Doctor Dashboard</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></p>
  </div>
  <div class="d-flex" style="gap:8px;align-items:center">
    <span class="badge <?= ($ctx['is_on_shift']??false)?'badge-green':'badge-amber' ?>" style="padding:8px 14px;font-size:13px">
      <i class="bi bi-<?= ($ctx['is_on_shift']??false)?'check-circle':'clock' ?>"></i>
      <?= ($ctx['is_on_shift']??false)?'On Duty':'Off Shift' ?>
    </span>
  </div>
</div>

<!-- Emergency Alert -->
<?php if (count($activeEmergencies) > 0): ?>
<div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);border-left:4px solid var(--red);border-radius:10px;padding:16px 20px;margin-bottom:20px">
  <div class="d-flex align-center justify-between" style="justify-content:space-between">
    <div class="d-flex align-center" style="gap:12px">
      <i class="bi bi-heart-pulse-fill" style="color:var(--red);font-size:22px"></i>
      <div>
        <div style="font-weight:700;color:#991b1b;font-size:14px"><?= count($activeEmergencies) ?> Patient Emergency<?= count($activeEmergencies)>1?'s':'' ?> Requiring Attention</div>
        <div style="color:#b91c1c;font-size:13px">
          <?php foreach ($slicedEmergencies as $idx => $e): ?>
          <?= htmlspecialchars($e['patient_name']) ?> (<?= $e['severity'] ?>)<?= $idx < count($slicedEmergencies)-1 ? ', ' : '' ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <a href="<?= APP_URL ?>/views/shared/emergencies.php" class="btn btn-danger btn-sm">
      <i class="bi bi-arrow-right"></i> Respond Now
    </a>
  </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card stat-blue">
    <div class="stat-top"><div><div class="stat-label">My Patients</div><div class="stat-value"><?= count($myPatients) ?></div></div><div class="stat-icon"><i class="bi bi-people"></i></div></div>
  </div>
  <div class="stat-card stat-red">
    <div class="stat-top"><div><div class="stat-label">Patient Emergencies</div><div class="stat-value"><?= count($activeEmergencies) ?></div></div><div class="stat-icon"><i class="bi bi-heart-pulse"></i></div></div>
    <a href="<?= APP_URL ?>/views/shared/emergencies.php" style="font-size:12px;color:var(--red);text-decoration:none;font-weight:600">View →</a>
  </div>
  <div class="stat-card stat-green">
    <div class="stat-top"><div><div class="stat-label">Admitted</div><div class="stat-value"><?= $admittedCount ?></div></div><div class="stat-icon"><i class="bi bi-hospital"></i></div></div>
  </div>
  <div class="stat-card stat-teal">
    <div class="stat-top"><div><div class="stat-label">Records Today</div>
      <div class="stat-value"><?= $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE created_by=? AND DATE(record_date)=CURDATE()",[$userId])['c'] ?></div>
    </div><div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:16px">
  <!-- Patient List -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-people"></i>My Patients (<?= count($myPatients) ?>)</div>
      <a href="<?= APP_URL ?>/views/shared/patients.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding:0">
      <table>
        <thead>
          <tr>
            <th style="padding-left:20px">Patient</th>
            <th>Department</th>
            <th>Status</th>
            <th>Sensitivity</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($myPatients as $p): ?>
          <?php
            $sb=['admitted'=>'badge-blue','discharged'=>'badge-gray','outpatient'=>'badge-teal','emergency'=>'badge-red'][$p['admission_status']]??'badge-gray';
            $sensBadge=['Low'=>'badge-green','Medium'=>'badge-amber','High'=>'badge-red','Critical'=>'badge-navy'][$p['sensitivity_level']]??'badge-gray';
          ?>
          <tr onclick="window.location='<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $p['id'] ?>'">
            <td style="padding-left:20px">
              <div style="font-weight:600"><?= htmlspecialchars($p['full_name']) ?>
                <?php if($p['is_emergency']): ?><span class="badge badge-red" style="margin-left:6px">EMRG</span><?php endif; ?>
              </div>
              <div style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($p['patient_id']) ?></div>
            </td>
            <td style="font-size:13px;color:var(--gray-600)"><?= htmlspecialchars($p['dept_name']??'—') ?></td>
            <td><span class="badge <?= $sb ?>"><?= ucfirst($p['admission_status']) ?></span></td>
            <td><span class="badge <?= $sensBadge ?>"><?= $p['sensitivity_level'] ?></span></td>
            <td>
              <a href="<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $p['id'] ?>"
                 class="btn btn-outline btn-sm" onclick="event.stopPropagation()">
                <i class="bi bi-folder2-open"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($myPatients)): ?>
          <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--gray-400)">No patients assigned to you yet</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Right panel -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <!-- Quick Actions -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="bi bi-lightning"></i>Quick Actions</div></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
        <a href="<?= APP_URL ?>/views/shared/patients.php" class="btn btn-outline w-100">
          <i class="bi bi-file-earmark-medical"></i> Patient Records
        </a>
        <a href="<?= APP_URL ?>/views/shared/emergencies.php" class="btn btn-emergency w-100">
          <i class="bi bi-heart-pulse-fill"></i> Emergency Response
          <?= count($activeEmergencies)>0 ? '('.count($activeEmergencies).')' : '' ?>
        </a>
      </div>
    </div>

    <!-- Recent Records -->
    <div class="card" style="flex:1">
      <div class="card-header"><div class="card-title"><i class="bi bi-clock-history"></i>My Recent Records</div></div>
      <div class="card-body" style="padding:0">
        <?php foreach ($recentRecords as $rec): ?>
        <div style="padding:12px 16px;border-bottom:1px solid var(--gray-100)">
          <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($rec['title']) ?></div>
          <div style="color:var(--gray-500);font-size:12px"><?= htmlspecialchars($rec['patient_name']) ?></div>
          <div style="color:var(--gray-400);font-size:11px;margin-top:2px"><?= date('d M H:i',strtotime($rec['record_date'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($recentRecords)): ?>
        <div style="text-align:center;padding:30px;color:var(--gray-400);font-size:13px">No records yet</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
