<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
$auth = new AuthController(); $auth->requireRole(['consultant']);
$db = Database::getInstance(); $userId = $_SESSION['user_id'];
$pageTitle = 'Consultant Dashboard — CA-RBAC HIS';
$allPatients = $db->fetchAll("SELECT p.*, d.dept_name FROM patients p LEFT JOIN departments d ON p.department_id=d.id ORDER BY p.is_emergency DESC, p.sensitivity_level DESC LIMIT 20");
$totalPts = $db->fetchOne("SELECT COUNT(*) c FROM patients")['c'];
$criticalPts = $db->fetchOne("SELECT COUNT(*) c FROM patients WHERE sensitivity_level='Critical' OR is_emergency=1")['c'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <div><h4><i class="bi bi-person-workspace me-2"></i>Consultant Dashboard</h4><small class="text-muted">Senior access — <?= htmlspecialchars($_SESSION['full_name']) ?></small></div>
  <span class="badge bg-dark fs-6"><i class="bi bi-shield-check me-1"></i>Consultant Privilege</span>
</div>
<div class="alert alert-info border-info alert-context trusted">
  <i class="bi bi-info-circle me-2"></i>
  <strong>Consultant Access:</strong> As a consultant, you have broad read access to patient records.
  All your accesses are fully audited regardless of context.
</div>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="stat-card stat-card-blue"><div class="d-flex justify-content-between"><div><div class="small opacity-75">All Patients</div><div class="display-6 fw-bold"><?= $totalPts ?></div></div><i class="bi bi-people stat-icon"></i></div></div></div>
  <div class="col-md-3"><div class="stat-card stat-card-red"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Critical/Emergency</div><div class="display-6 fw-bold"><?= $criticalPts ?></div></div><i class="bi bi-heart-pulse stat-icon"></i></div></div></div>
</div>
<div class="card">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-person-lines-fill me-2"></i>Patient Overview (All)</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th class="ps-3">Patient ID</th><th>Name</th><th>Department</th><th>Status</th><th>Sensitivity</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($allPatients as $p): ?>
          <?php $sb = ['admitted'=>'primary','discharged'=>'secondary','outpatient'=>'info','emergency'=>'danger'][$p['admission_status']]??'secondary'; ?>
          <tr class="<?= $p['is_emergency']?'table-danger':'' ?>">
            <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($p['patient_id']) ?></td>
            <td><?= htmlspecialchars($p['full_name']) ?><?php if($p['is_emergency']): ?><span class="badge bg-danger ms-1">EMRG</span><?php endif; ?></td>
            <td class="small"><?= htmlspecialchars($p['dept_name']??'—') ?></td>
            <td><span class="badge bg-<?= $sb ?>"><?= ucfirst($p['admission_status']) ?></span></td>
            <td><span class="badge bg-<?= ['Low'=>'success','Medium'=>'warning','High'=>'danger','Critical'=>'dark'][$p['sensitivity_level']] ?>"><?= $p['sensitivity_level'] ?></span></td>
            <td><a href="<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-folder2-open"></i> Open</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
