<?php
// =====================================================
// CA-RBAC HIS — My Profile
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
$auth = new AuthController(); $auth->requireLogin();
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$pageTitle = 'My Profile — CA-RBAC HIS';
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $user = $db->fetchOne("SELECT password_hash FROM users WHERE id=?", [$userId]);
    if (!password_verify($current, $user['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $db->query("UPDATE users SET password_hash=? WHERE id=?", [$hash, $userId]);
        $success = 'Password changed successfully.';
    }
}

$user = $auth->getCurrentUser();
$role = $db->fetchOne("SELECT * FROM roles WHERE id=?", [$user['role_id']]);
$dept = $user['department_id'] ? $db->fetchOne("SELECT dept_name FROM departments WHERE id=?", [$user['department_id']]) : null;
$shifts = $db->fetchAll("SELECT * FROM staff_shifts WHERE user_id=? AND shift_date >= CURDATE() ORDER BY shift_date,start_time LIMIT 7", [$userId]);
$perms = $db->fetchAll("SELECT p.display_name, p.module FROM role_permissions rp JOIN permissions p ON rp.permission_id=p.id WHERE rp.role_id=?", [$user['role_id']]);

$ctx = $_SESSION['context'] ?? [];
$loginRisk = $_SESSION['login_risk'] ?? ['score'=>0,'level'=>'Low'];

include __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <div><h4><i class="bi bi-person-circle me-2"></i>My Profile</h4></div>
</div>
<?php if($success): ?><div class="alert alert-success alert-auto-dismiss"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-3">
  <!-- Profile Card -->
  <div class="col-lg-4">
    <div class="card text-center">
      <div class="card-body py-4">
        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width:80px;height:80px;font-size:2rem">
          <i class="bi bi-person-fill"></i>
        </div>
        <h5 class="fw-bold mb-0"><?= htmlspecialchars($user['full_name']) ?></h5>
        <div class="text-muted small mb-2"><?= htmlspecialchars($user['staff_id']) ?></div>
        <span class="badge bg-primary fs-6 mb-3"><?= htmlspecialchars($role['display_name']) ?></span>
        <div class="text-start small">
          <div class="mb-1"><i class="bi bi-envelope me-2 text-muted"></i><?= htmlspecialchars($user['email']) ?></div>
          <div class="mb-1"><i class="bi bi-phone me-2 text-muted"></i><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></div>
          <div class="mb-1"><i class="bi bi-building me-2 text-muted"></i><?= htmlspecialchars($dept['dept_name'] ?? 'N/A') ?></div>
          <div class="mb-1"><i class="bi bi-briefcase me-2 text-muted"></i><?= htmlspecialchars($user['specialization'] ?? 'N/A') ?></div>
          <div class="mb-1"><i class="bi bi-clock me-2 text-muted"></i>Last Login: <?= $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : 'N/A' ?></div>
        </div>
      </div>
    </div>

    <!-- Session Context -->
    <div class="card mt-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-shield me-2"></i>Current Session</div>
      <div class="card-body small">
        <div class="mb-1"><strong>IP:</strong> <?= htmlspecialchars($ctx['ip_address'] ?? '?') ?></div>
        <div class="mb-1"><strong>Network:</strong> <?= htmlspecialchars($ctx['network_name'] ?? 'Unknown') ?>
          <span class="badge bg-<?= ($ctx['is_trusted_network']??false) ? 'success' : 'warning' ?>"><?= ($ctx['is_trusted_network']??false) ? 'Trusted' : 'Untrusted' ?></span>
        </div>
        <div class="mb-1"><strong>Device:</strong> <?= htmlspecialchars($ctx['device_type'] ?? '?') ?>
          <span class="badge bg-<?= ($ctx['device_trusted']??false) ? 'success' : 'secondary' ?>"><?= ($ctx['device_trusted']??false) ? 'Trusted' : 'New' ?></span>
        </div>
        <div class="mb-1"><strong>Shift:</strong>
          <span class="badge bg-<?= ($ctx['is_on_shift']??false) ? 'success' : 'warning' ?>"><?= ($ctx['is_on_shift']??false) ? 'On Duty' : 'Off Shift' ?></span>
        </div>
        <div><strong>Login Risk:</strong>
          <span class="risk-badge risk-<?= $loginRisk['level'] ?>"><?= $loginRisk['level'] ?> (<?= $loginRisk['score'] ?>)</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <!-- Permissions -->
    <div class="card mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-key me-2"></i>My Permissions (<?= count($perms) ?>)</div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($perms as $p): ?>
          <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['display_name']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Upcoming Shifts -->
    <div class="card mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar3 me-2"></i>Upcoming Shifts</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th class="ps-3">Date</th><th>Shift</th><th>Start</th><th>End</th></tr></thead>
          <tbody>
            <?php foreach ($shifts as $s): ?>
            <tr><td class="ps-3"><?= date('D d M', strtotime($s['shift_date'])) ?></td><td><?= htmlspecialchars($s['shift_name']) ?></td><td><?= $s['start_time'] ?></td><td><?= $s['end_time'] ?></td></tr>
            <?php endforeach; ?>
            <?php if(empty($shifts)): ?><tr><td colspan="4" class="text-center py-2 text-muted">No upcoming shifts.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-lock me-2"></i>Change Password</div>
      <div class="card-body">
        <form method="POST" class="row g-3">
          <input type="hidden" name="change_password" value="1">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Current Password</label>
            <input type="password" class="form-control" name="current_password" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">New Password</label>
            <input type="password" class="form-control" name="new_password" required minlength="8">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Confirm New Password</label>
            <input type="password" class="form-control" name="confirm_password" required>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-hospital"><i class="bi bi-save me-1"></i>Update Password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
