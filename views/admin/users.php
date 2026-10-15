<?php
// =====================================================
// CA-RBAC HIS — Manage Users (Admin)
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireRole('admin');
$db = Database::getInstance();
$audit = new AuditLogger();

$pageTitle = 'Manage Users — CA-RBAC HIS';
$success = $error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $staffId  = trim($_POST['staff_id']  ?? '');
        $name     = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email']     ?? '');
        $password = $_POST['password'] ?? '';
        $roleId   = (int)($_POST['role_id'] ?? 0);
        $deptId   = (int)($_POST['department_id'] ?? 0) ?: null;
        $phone    = trim($_POST['phone'] ?? '');
        $spec     = trim($_POST['specialization'] ?? '');

        if ($staffId && $name && $email && $password && $roleId) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            try {
                $db->query(
                    "INSERT INTO users (staff_id, full_name, email, password_hash, role_id, department_id, phone, specialization) VALUES (?,?,?,?,?,?,?,?)",
                    [$staffId, $name, $email, $hash, $roleId, $deptId, $phone, $spec]
                );
                $audit->log($_SESSION['user_id'], 'USER_CREATED', 'admin', null, 'info', null, null, null,
                    "Created user: $staffId ($name)");
                $success = "User $staffId created successfully.";
            } catch (Exception $e) {
                $error = 'Error: Staff ID or email already exists.';
            }
        } else {
            $error = 'Please fill all required fields.';
        }
    }

    if ($action === 'toggle_lock') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $user = $db->fetchOne("SELECT is_locked, staff_id FROM users WHERE id=?", [$uid]);
        if ($user) {
            $newLock = $user['is_locked'] ? 0 : 1;
            $db->query("UPDATE users SET is_locked=?, failed_attempts=0 WHERE id=?", [$newLock, $uid]);
            $audit->log($_SESSION['user_id'], 'USER_' . ($newLock ? 'LOCKED' : 'UNLOCKED'), 'admin', null, 'info', null, null, null,
                "User {$user['staff_id']} " . ($newLock ? 'locked' : 'unlocked'));
            $success = "User {$user['staff_id']} has been " . ($newLock ? 'locked' : 'unlocked') . ".";
        }
    }

    if ($action === 'toggle_active') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $user = $db->fetchOne("SELECT is_active, staff_id FROM users WHERE id=?", [$uid]);
        if ($user && $uid != $_SESSION['user_id']) {
            $newActive = $user['is_active'] ? 0 : 1;
            $db->query("UPDATE users SET is_active=? WHERE id=?", [$newActive, $uid]);
            $audit->log($_SESSION['user_id'], 'USER_' . ($newActive ? 'ACTIVATED' : 'DEACTIVATED'), 'admin');
            $success = "User {$user['staff_id']} " . ($newActive ? 'activated' : 'deactivated') . ".";
        }
    }
}

$users = $db->fetchAll(
    "SELECT u.*, r.display_name AS role_display, r.role_name, d.dept_name
     FROM users u
     JOIN roles r ON u.role_id = r.id
     LEFT JOIN departments d ON u.department_id = d.id
     ORDER BY u.created_at DESC"
);

$roles = $db->fetchAll("SELECT * FROM roles ORDER BY display_name");
$depts = $db->fetchAll("SELECT * FROM departments ORDER BY dept_name");

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h4><i class="bi bi-people me-2"></i>Manage Users</h4>
    <small class="text-muted"><?= count($users) ?> registered users</small>
  </div>
  <button class="btn btn-hospital" data-bs-toggle="modal" data-bs-target="#addUserModal">
    <i class="bi bi-person-plus me-1"></i>Add User
  </button>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-auto-dismiss alert-dismissible fade show">
  <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th class="ps-3">Staff ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Department</th>
            <th>Status</th>
            <th>Locked</th>
            <th>Last Login</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr class="<?= !$u['is_active'] ? 'table-secondary text-muted' : ($u['is_locked'] ? 'table-warning' : '') ?>">
            <td class="ps-3 fw-bold"><?= htmlspecialchars($u['staff_id']) ?></td>
            <td><?= htmlspecialchars($u['full_name']) ?></td>
            <td class="small"><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <span class="badge bg-primary"><?= htmlspecialchars($u['role_display']) ?></span>
            </td>
            <td class="small"><?= htmlspecialchars($u['dept_name'] ?? '—') ?></td>
            <td>
              <span class="badge bg-<?= $u['is_active'] ? 'success' : 'secondary' ?>">
                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td>
              <?php if ($u['is_locked']): ?>
              <span class="badge bg-danger"><i class="bi bi-lock me-1"></i>Locked (<?= $u['failed_attempts'] ?>x)</span>
              <?php else: ?>
              <span class="badge bg-light text-success border"><i class="bi bi-unlock me-1"></i>Open</span>
              <?php endif; ?>
            </td>
            <td class="small text-muted"><?= $u['last_login'] ? date('d M H:i', strtotime($u['last_login'])) : 'Never' ?></td>
            <td>
              <div class="d-flex gap-1">
                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <!-- Lock/Unlock -->
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="toggle_lock">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button class="btn btn-<?= $u['is_locked'] ? 'success' : 'warning' ?> btn-sm" title="<?= $u['is_locked'] ? 'Unlock' : 'Lock' ?>">
                    <i class="bi bi-<?= $u['is_locked'] ? 'unlock' : 'lock' ?>"></i>
                  </button>
                </form>
                <!-- Activate/Deactivate -->
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button class="btn btn-<?= $u['is_active'] ? 'secondary' : 'success' ?> btn-sm" title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                    <i class="bi bi-<?= $u['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                  </button>
                </form>
                <?php else: ?>
                <span class="badge bg-info">You</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="">
        <input type="hidden" name="action" value="add_user">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Staff ID <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="staff_id" placeholder="e.g. DOC004" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="full_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" name="email" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
              <input type="password" class="form-control" name="password" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
              <select class="form-select" name="role_id" required>
                <option value="">Select Role</option>
                <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['display_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Department</label>
              <select class="form-select" name="department_id">
                <option value="">None</option>
                <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['dept_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Phone</label>
              <input type="text" class="form-control" name="phone">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Specialization</label>
              <input type="text" class="form-control" name="specialization">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-hospital"><i class="bi bi-save me-1"></i>Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
