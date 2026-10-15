<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
$auth = new AuthController(); $auth->requireLogin();
$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$pageTitle = 'My Sessions — CA-RBAC HIS';
$sessions = $db->fetchAll("SELECT * FROM login_sessions WHERE user_id=? ORDER BY login_at DESC LIMIT 20", [$userId]);
include __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div><h4><i class="bi bi-clock-history me-2"></i>My Login Sessions</h4></div></div>
<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover table-sm mb-0">
      <thead><tr><th class="ps-3">Login Time</th><th>IP Address</th><th>Network</th><th>Device</th><th>Last Activity</th><th>Status</th><th>Logout</th></tr></thead>
      <tbody>
        <?php foreach ($sessions as $s): ?>
        <tr>
          <td class="ps-3 small"><?= date('d M Y H:i', strtotime($s['login_at'])) ?></td>
          <td class="small"><?= htmlspecialchars($s['ip_address'] ?? '—') ?></td>
          <td>
            <span class="badge bg-<?= $s['is_trusted_network'] ? 'success' : 'warning' ?>">
              <?= $s['is_trusted_network'] ? 'Trusted' : 'Untrusted' ?>
            </span>
          </td>
          <td class="small text-muted"><?= strlen($s['user_agent']??'') > 40 ? substr($s['user_agent'],0,40).'...' : ($s['user_agent']??'—') ?></td>
          <td class="small"><?= $s['last_activity'] ? date('d M H:i', strtotime($s['last_activity'])) : '—' ?></td>
          <td>
            <span class="badge bg-<?= $s['is_active'] ? 'success' : 'secondary' ?>">
              <?= $s['is_active'] ? 'Active' : 'Ended' ?>
            </span>
          </td>
          <td class="small text-muted"><?= $s['logout_at'] ? date('d M H:i', strtotime($s['logout_at'])) : ($s['is_active'] ? '<em>Current</em>' : 'Expired') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($sessions)): ?><tr><td colspan="7" class="text-center py-3 text-muted">No session history.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
