<?php
// =====================================================
// CA-RBAC HIS — Roles & Permissions (Admin)
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireRole('admin');
$db    = Database::getInstance();
$audit = new AuditLogger();
$pageTitle = 'Roles & Permissions — CA-RBAC HIS';
$success = $error = '';

// Handle permission toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_permission') {
        $roleId = (int)$_POST['role_id'];
        $permId = (int)$_POST['perm_id'];
        $existing = $db->fetchOne("SELECT id FROM role_permissions WHERE role_id=? AND permission_id=?", [$roleId, $permId]);
        if ($existing) {
            $db->query("DELETE FROM role_permissions WHERE role_id=? AND permission_id=?", [$roleId, $permId]);
            $success = 'Permission removed.';
        } else {
            $db->query("INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)", [$roleId, $permId]);
            $success = 'Permission granted.';
        }
        $audit->log($_SESSION['user_id'], 'PERMISSION_CHANGED', 'admin', null, 'info', null, null, null,
            "Role {$roleId} permission {$permId} toggled: " . ($existing ? 'removed' : 'added'));
    }
}

$roles       = $db->fetchAll("SELECT * FROM roles ORDER BY id");
$permissions = $db->fetchAll("SELECT * FROM permissions ORDER BY module, display_name");

// Build permission matrix
$matrix = [];
$rolePerms = $db->fetchAll("SELECT role_id, permission_id FROM role_permissions");
foreach ($rolePerms as $rp) {
    $matrix[$rp['role_id']][$rp['permission_id']] = true;
}

// Group permissions by module
$permsByModule = [];
foreach ($permissions as $p) {
    $permsByModule[$p['module']][] = $p;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h4><i class="bi bi-key me-2"></i>Roles & Permissions Matrix</h4>
    <small class="text-muted">Configure which permissions each role has</small>
  </div>
  <span class="badge bg-warning text-dark fs-6"><i class="bi bi-exclamation-triangle me-1"></i>Changes take effect immediately</span>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-auto-dismiss"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header bg-white">
    <div class="small text-muted">Click any cell to toggle a permission. <span class="badge bg-success">✓</span> = granted, <span class="badge bg-light text-muted border">✗</span> = denied</div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-bordered table-sm mb-0" style="min-width:900px">
        <thead class="table-dark">
          <tr>
            <th class="ps-3" style="min-width:200px">Permission</th>
            <th>Module</th>
            <?php foreach ($roles as $r): ?>
            <th class="text-center small" style="min-width:90px">
              <?= htmlspecialchars($r['display_name']) ?>
            </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($permsByModule as $module => $perms): ?>
          <tr class="table-light">
            <td colspan="<?= 2 + count($roles) ?>" class="small fw-bold text-uppercase text-muted ps-3 py-1">
              <i class="bi bi-folder me-1"></i><?= ucfirst($module) ?> Module
            </td>
          </tr>
          <?php foreach ($perms as $perm): ?>
          <tr>
            <td class="ps-3 small fw-semibold"><?= htmlspecialchars($perm['display_name']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= $perm['module'] ?></span></td>
            <?php foreach ($roles as $r): ?>
            <?php $hasIt = isset($matrix[$r['id']][$perm['id']]); ?>
            <td class="text-center p-1">
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle_permission">
                <input type="hidden" name="role_id" value="<?= $r['id'] ?>">
                <input type="hidden" name="perm_id" value="<?= $perm['id'] ?>">
                <button type="submit" class="btn btn-<?= $hasIt ? 'success' : 'light' ?> btn-sm border"
                        title="<?= $hasIt ? 'Click to remove' : 'Click to grant' ?>"
                        style="width:40px;padding:2px;">
                  <?= $hasIt ? '✓' : '✗' ?>
                </button>
              </form>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Role Detail Cards -->
<h5 class="mt-4 mb-3"><i class="bi bi-person-badge me-2"></i>Role Profiles</h5>
<div class="row g-3">
  <?php foreach ($roles as $r): ?>
  <?php
    $rPerms = $db->fetchAll(
        "SELECT p.display_name, p.module FROM role_permissions rp JOIN permissions p ON rp.permission_id=p.id WHERE rp.role_id=?",
        [$r['id']]
    );
    $userCount = $db->fetchOne("SELECT COUNT(*) c FROM users WHERE role_id=?", [$r['id']])['c'];
  ?>
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header bg-primary text-white">
        <div class="d-flex justify-content-between">
          <span class="fw-semibold"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($r['display_name']) ?></span>
          <span class="badge bg-white text-primary"><?= $userCount ?> user(s)</span>
        </div>
      </div>
      <div class="card-body">
        <div class="small text-muted mb-2"><?= htmlspecialchars($r['description'] ?? '') ?></div>
        <?php if ($r['can_emergency_override']): ?>
        <span class="badge bg-danger mb-2"><i class="bi bi-shield-exclamation me-1"></i>Emergency Override</span>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-1 mt-2">
          <?php foreach ($rPerms as $rp): ?>
          <span class="badge bg-light text-dark border small"><?= htmlspecialchars($rp['display_name']) ?></span>
          <?php endforeach; ?>
          <?php if (empty($rPerms)): ?>
          <span class="text-muted small">No permissions assigned</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
