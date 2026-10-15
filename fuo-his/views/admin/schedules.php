<?php
// =====================================================
// CA-RBAC HIS — Staff Schedules (Admin)
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireRole('admin');
$db    = Database::getInstance();
$audit = new AuditLogger();
$pageTitle = 'Staff Schedules — CA-RBAC HIS';
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_shift') {
        $uid      = (int)$_POST['user_id'];
        $shiftName= trim($_POST['shift_name']);
        $date     = $_POST['shift_date'];
        $start    = $_POST['start_time'];
        $end      = $_POST['end_time'];
        $deptId   = (int)$_POST['department_id'] ?: null;
        if ($uid && $shiftName && $date && $start && $end) {
            $db->query(
                "INSERT INTO staff_shifts (user_id, shift_name, shift_date, start_time, end_time, department_id, created_by) VALUES (?,?,?,?,?,?,?)",
                [$uid, $shiftName, $date, $start, $end, $deptId, $_SESSION['user_id']]
            );
            $audit->log($_SESSION['user_id'],'SHIFT_ADDED','admin',null,'info',null,null,null,"Shift added for user $uid on $date");
            $success = "Shift added successfully.";
        } else { $error = 'Please fill all required fields.'; }
    }
    if ($action === 'delete_shift') {
        $sid = (int)$_POST['shift_id'];
        $db->query("DELETE FROM staff_shifts WHERE id=?", [$sid]);
        $success = 'Shift deleted.';
    }
}

$today = date('Y-m-d');
$weekEnd = date('Y-m-d', strtotime('+6 days'));

$shifts = $db->fetchAll(
    "SELECT s.*, u.full_name, u.staff_id, r.role_name, d.dept_name
     FROM staff_shifts s
     JOIN users u ON s.user_id=u.id
     JOIN roles r ON u.role_id=r.id
     LEFT JOIN departments d ON s.department_id=d.id
     WHERE s.shift_date BETWEEN ? AND ?
     ORDER BY s.shift_date, s.start_time",
    [$today, $weekEnd]
);

$users = $db->fetchAll("SELECT u.id, u.full_name, u.staff_id, r.display_name AS role_display FROM users u JOIN roles r ON u.role_id=r.id WHERE u.is_active=1 ORDER BY u.full_name");
$depts = $db->fetchAll("SELECT * FROM departments ORDER BY dept_name");

// Group by date
$byDate = [];
foreach ($shifts as $s) $byDate[$s['shift_date']][] = $s;

$shiftColors = ['Morning Shift'=>'primary','Evening Shift'=>'warning','Night Shift'=>'dark','On-Call'=>'danger','Emergency Shift'=>'danger'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h4><i class="bi bi-calendar3 me-2"></i>Staff Schedules</h4>
    <small class="text-muted">Week: <?= date('d M', strtotime($today)) ?> – <?= date('d M Y', strtotime($weekEnd)) ?></small>
  </div>
  <button class="btn btn-hospital" data-bs-toggle="modal" data-bs-target="#addShiftModal">
    <i class="bi bi-plus-lg me-1"></i>Add Shift
  </button>
</div>

<?php if ($success): ?><div class="alert alert-success alert-auto-dismiss"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Weekly Calendar View -->
<div class="row g-3 mb-4">
  <?php for ($i = 0; $i < 7; $i++): ?>
  <?php
    $day = date('Y-m-d', strtotime("+$i days"));
    $dayLabel = date('D d M', strtotime($day));
    $isToday = $day === $today;
    $dayShifts = $byDate[$day] ?? [];
  ?>
  <div class="col-md-6 col-xl-3">
    <div class="card h-100 <?= $isToday ? 'border-primary' : '' ?>">
      <div class="card-header <?= $isToday ? 'bg-primary text-white' : 'bg-white' ?> fw-semibold">
        <i class="bi bi-calendar-day me-1"></i><?= $dayLabel ?> <?= $isToday ? '(Today)' : '' ?>
      </div>
      <div class="card-body p-2">
        <?php if (empty($dayShifts)): ?>
        <div class="text-muted small text-center py-2">No shifts</div>
        <?php else: ?>
        <?php foreach ($dayShifts as $s): ?>
        <?php $clr = $shiftColors[$s['shift_name']] ?? 'secondary'; ?>
        <div class="card border-<?= $clr ?> mb-2 shadow-none">
          <div class="card-body py-1 px-2 small">
            <div class="fw-semibold text-<?= $clr ?>"><?= htmlspecialchars($s['shift_name']) ?></div>
            <div><?= htmlspecialchars($s['full_name']) ?>
              <span class="badge bg-light text-dark border ms-1"><?= $s['role_name'] ?></span>
            </div>
            <div class="text-muted"><?= $s['start_time'] ?> – <?= $s['end_time'] ?></div>
            <?php if ($s['dept_name']): ?>
            <div class="text-muted"><i class="bi bi-building me-1"></i><?= $s['dept_name'] ?></div>
            <?php endif; ?>
            <form method="POST" class="mt-1">
              <input type="hidden" name="action" value="delete_shift">
              <input type="hidden" name="shift_id" value="<?= $s['id'] ?>">
              <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"
                      onclick="return confirm('Delete this shift?')">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endfor; ?>
</div>

<!-- Shift Table -->
<div class="card">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-table me-2"></i>All Shifts This Week</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead>
          <tr>
            <th class="ps-3">Date</th><th>Staff</th><th>Role</th><th>Shift</th>
            <th>Start</th><th>End</th><th>Department</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($shifts as $s): ?>
          <?php $clr = $shiftColors[$s['shift_name']] ?? 'secondary'; ?>
          <tr>
            <td class="ps-3 small"><?= date('D d M', strtotime($s['shift_date'])) ?></td>
            <td class="small">
              <span class="fw-semibold"><?= htmlspecialchars($s['staff_id']) ?></span><br>
              <span class="text-muted"><?= htmlspecialchars($s['full_name']) ?></span>
            </td>
            <td class="small"><?= $s['role_name'] ?></td>
            <td><span class="badge bg-<?= $clr ?>"><?= htmlspecialchars($s['shift_name']) ?></span></td>
            <td class="small"><?= $s['start_time'] ?></td>
            <td class="small"><?= $s['end_time'] ?></td>
            <td class="small"><?= htmlspecialchars($s['dept_name'] ?? '—') ?></td>
            <td>
              <form method="POST">
                <input type="hidden" name="action" value="delete_shift">
                <input type="hidden" name="shift_id" value="<?= $s['id'] ?>">
                <button class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this shift?')">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($shifts)): ?><tr><td colspan="8" class="text-center py-3 text-muted">No shifts this week.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Shift Modal -->
<div class="modal fade" id="addShiftModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Add Staff Shift</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add_shift">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Staff Member <span class="text-danger">*</span></label>
            <select class="form-select" name="user_id" required>
              <option value="">Select Staff</option>
              <?php foreach ($users as $u): ?>
              <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['staff_id'] . ' — ' . $u['full_name'] . ' (' . $u['role_display'] . ')') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Shift Type <span class="text-danger">*</span></label>
            <select class="form-select" name="shift_name" required>
              <option>Morning Shift</option>
              <option>Evening Shift</option>
              <option>Night Shift</option>
              <option>On-Call</option>
              <option>Emergency Shift</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="shift_date" value="<?= $today ?>" required>
          </div>
          <div class="row g-2">
            <div class="col">
              <label class="form-label fw-semibold">Start Time <span class="text-danger">*</span></label>
              <input type="time" class="form-control" name="start_time" value="08:00" required>
            </div>
            <div class="col">
              <label class="form-label fw-semibold">End Time <span class="text-danger">*</span></label>
              <input type="time" class="form-control" name="end_time" value="16:00" required>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label fw-semibold">Department</label>
            <select class="form-select" name="department_id">
              <option value="">None</option>
              <?php foreach ($depts as $d): ?>
              <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-hospital"><i class="bi bi-save me-1"></i>Save Shift</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
