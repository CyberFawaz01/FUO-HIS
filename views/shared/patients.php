<?php
// =====================================================
// CA-RBAC HIS — Patients List (Shared)
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/PolicyDecisionPoint.php';

$auth = new AuthController();
$auth->requireLogin();
$pdp = new PolicyDecisionPoint();
$db = Database::getInstance();

$userId  = $_SESSION['user_id'];
$roleId  = $_SESSION['role_id'];
$role    = $_SESSION['role_name'];

$pageTitle = 'Patient Records — CA-RBAC HIS';

// Check if user has read_ehr permission
$canRead = $pdp->checkRBACPermission($roleId, 'read_ehr');
if (!$canRead) {
    header('Location: ' . APP_URL . '/views/shared/unauthorized.php');
    exit;
}

// Filter: assigned patients only (unless admin/consultant)
$searchQuery = trim($_GET['q'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterSens   = $_GET['sensitivity'] ?? '';

if (in_array($role, ['admin', 'consultant'])) {
    // See all patients
    $where = ['1=1'];
    $params = [];
} else {
    // Only assigned patients
    $where = ['a.user_id = ?'];
    $params = [$userId];
}

if ($searchQuery) {
    $where[] = '(p.full_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)';
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}
if ($filterStatus) { $where[] = 'p.admission_status = ?'; $params[] = $filterStatus; }
if ($filterSens)   { $where[] = 'p.sensitivity_level = ?'; $params[] = $filterSens; }

$whereStr = implode(' AND ', $where);

if (in_array($role, ['admin', 'consultant'])) {
    $patients = $db->fetchAll(
        "SELECT p.*, d.dept_name, u.full_name AS doctor_name
         FROM patients p
         LEFT JOIN departments d ON p.department_id = d.id
         LEFT JOIN users u ON p.attending_doctor = u.id
         WHERE $whereStr ORDER BY p.is_emergency DESC, p.created_at DESC",
        $params
    );
} else {
    $patients = $db->fetchAll(
        "SELECT p.*, d.dept_name, u.full_name AS doctor_name
         FROM patients p
         JOIN assignments a ON a.patient_id = p.id AND a.is_active = 1
         LEFT JOIN departments d ON p.department_id = d.id
         LEFT JOIN users u ON p.attending_doctor = u.id
         WHERE $whereStr ORDER BY p.is_emergency DESC, p.created_at DESC",
        $params
    );
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h4><i class="bi bi-file-earmark-medical me-2"></i>Patient Records</h4>
    <small class="text-muted">
      <?= in_array($role, ['admin','consultant']) ? 'All patients' : 'Your assigned patients' ?>
      — <?= count($patients) ?> record(s)
    </small>
  </div>
  <?php if (in_array($role, ['admin'])): ?>
  <a href="<?= APP_URL ?>/views/admin/add_patient.php" class="btn btn-hospital">
    <i class="bi bi-plus-lg me-1"></i>Add Patient
  </a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form class="row g-2 align-items-end" method="GET">
      <div class="col-md-5">
        <input type="text" class="form-control form-control-sm" name="q"
               placeholder="Search name, ID, phone..." value="<?= htmlspecialchars($searchQuery) ?>">
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="status">
          <option value="">All Status</option>
          <?php foreach (['admitted','discharged','outpatient','emergency'] as $s): ?>
          <option <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="sensitivity">
          <option value="">All Sensitivity</option>
          <?php foreach (['Low','Medium','High','Critical'] as $s): ?>
          <option <?= $filterSens === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-auto">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-search me-1"></i>Filter
        </button>
        <a href="?" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
      </div>
    </form>
  </div>
</div>

<?php if (empty($patients)): ?>
<div class="card">
  <div class="card-body text-center py-5">
    <i class="bi bi-person-x fs-1 text-muted"></i>
    <div class="mt-2 text-muted">No patients found.</div>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th class="ps-3">Patient ID</th>
            <th>Name</th>
            <th>Department</th>
            <th>Status</th>
            <th>Sensitivity</th>
            <th>Emergency</th>
            <th>Attending</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($patients as $p): ?>
          <?php
            $statusBadge = ['admitted'=>'primary','discharged'=>'secondary','outpatient'=>'info','emergency'=>'danger'][$p['admission_status']] ?? 'secondary';
            $sensBadge   = ['Low'=>'success','Medium'=>'warning','High'=>'danger','Critical'=>'dark'][$p['sensitivity_level']] ?? 'secondary';
          ?>
          <tr class="<?= $p['is_emergency'] ? 'table-danger' : '' ?>"
              onclick="window.location='<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $p['id'] ?>'">
            <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($p['patient_id']) ?></td>
            <td>
              <?= htmlspecialchars($p['full_name']) ?>
              <?php if ($p['is_emergency']): ?>
              <span class="badge bg-danger ms-1 animate__animated"><i class="bi bi-exclamation-triangle-fill"></i> EMRG</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['dept_name'] ?? '—') ?></td>
            <td><span class="badge bg-<?= $statusBadge ?>"><?= ucfirst($p['admission_status']) ?></span></td>
            <td><span class="badge bg-<?= $sensBadge ?>"><?= $p['sensitivity_level'] ?></span></td>
            <td class="text-center">
              <?= $p['is_emergency'] ? '<i class="bi bi-heart-pulse-fill text-danger"></i>' : '<i class="bi bi-dash text-muted"></i>' ?>
            </td>
            <td><?= htmlspecialchars($p['doctor_name'] ?? 'Unassigned') ?></td>
            <td onclick="event.stopPropagation()">
              <a href="<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $p['id'] ?>"
                 class="btn btn-outline-primary btn-sm" title="View EHR">
                <i class="bi bi-folder2-open"></i>
              </a>
              <?php if ($role === 'admin'): ?>
              <a href="<?= APP_URL ?>/views/admin/assign_patient.php?patient_id=<?= $p['id'] ?>"
                 class="btn btn-outline-secondary btn-sm" title="Manage Assignments">
                <i class="bi bi-people"></i>
              </a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
