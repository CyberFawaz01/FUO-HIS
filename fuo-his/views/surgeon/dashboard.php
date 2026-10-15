<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
$auth = new AuthController(); $auth->requireRole(['surgeon']);
$db = Database::getInstance(); $userId = $_SESSION['user_id'];
$pageTitle = 'Surgeon Dashboard — CA-RBAC HIS';
$myPatients = $db->fetchAll(
    "SELECT p.*, d.dept_name FROM patients p JOIN assignments a ON a.patient_id=p.id AND a.user_id=? AND a.is_active=1 LEFT JOIN departments d ON p.department_id=d.id ORDER BY p.is_emergency DESC",
    [$userId]
);
$surgeryNotes = $db->fetchAll(
    "SELECT pr.*, p.full_name AS patient_name, p.patient_id AS pid FROM patient_records pr JOIN patients p ON pr.patient_id=p.id WHERE pr.record_type='surgery_note' AND pr.created_by=? ORDER BY pr.record_date DESC LIMIT 10",
    [$userId]
);
include __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <div><h4><i class="bi bi-scissors me-2"></i>Surgeon Dashboard</h4><small class="text-muted">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></small></div>
  <a href="<?= APP_URL ?>/views/shared/emergency.php" class="btn btn-emergency"><i class="bi bi-shield-exclamation me-1"></i>Emergency Override</a>
</div>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="stat-card stat-card-blue"><div class="d-flex justify-content-between"><div><div class="small opacity-75">My Patients</div><div class="display-6 fw-bold"><?= count($myPatients) ?></div></div><i class="bi bi-people stat-icon"></i></div></div></div>
  <div class="col-md-3"><div class="stat-card stat-card-purple"><div class="d-flex justify-content-between"><div><div class="small opacity-75">Surgery Notes</div><div class="display-6 fw-bold"><?= count($surgeryNotes) ?></div></div><i class="bi bi-scissors stat-icon"></i></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-2"></i>My Patients</div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th class="ps-3">Patient</th><th>Status</th><th>Dept</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($myPatients as $p): ?>
            <tr class="<?= $p['is_emergency']?'table-danger':'' ?>">
              <td class="ps-3"><strong><?= htmlspecialchars($p['patient_id']) ?></strong><br><span class="small"><?= htmlspecialchars($p['full_name']) ?></span></td>
              <td><span class="badge bg-<?= ['admitted'=>'primary','discharged'=>'secondary','outpatient'=>'info','emergency'=>'danger'][$p['admission_status']]??'secondary' ?>"><?= ucfirst($p['admission_status']) ?></span></td>
              <td class="small"><?= htmlspecialchars($p['dept_name']??'—') ?></td>
              <td><a href="<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-folder2-open"></i></a></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($myPatients)): ?><tr><td colspan="4" class="text-center py-3 text-muted">No patients.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-scissors me-2"></i>My Surgery Notes</div>
      <div class="card-body p-0">
        <?php foreach ($surgeryNotes as $sn): ?>
        <div class="border-bottom p-3 small">
          <div class="fw-semibold"><?= htmlspecialchars($sn['title']) ?></div>
          <div class="text-muted"><?= htmlspecialchars($sn['patient_name']) ?> — <?= date('d M H:i', strtotime($sn['record_date'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($surgeryNotes)): ?><div class="text-center py-3 text-muted small">No surgery notes yet.</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
