<?php
// =====================================================
// CA-RBAC HIS — Emergency Override (Admin View + Approve/Reject)
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/RiskEngine.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireRole('admin');  // Admin only
$db    = Database::getInstance();
$risk  = new RiskEngine();
$audit = new AuditLogger();
$userId   = $_SESSION['user_id'];
$pageTitle = 'Emergency Override — CA-RBAC HIS';
$success = $error = '';

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    $eid    = (int)($_POST['override_id'] ?? 0);

    if ($action === 'approve') {
        $hrs     = (int)($_POST['hours'] ?? EMERGENCY_ACCESS_DURATION);
        $hrs     = max(1, min(8, $hrs));
        $expires = date('Y-m-d H:i:s', time() + ($hrs * 3600));
        $db->query(
            "UPDATE emergency_access SET access_granted=1, expires_at=?, is_expired=0 WHERE id=?",
            [$expires, $eid]
        );
        $override = $db->fetchOne("SELECT * FROM emergency_access WHERE id=?", [$eid]);
        $audit->log($userId,'OVERRIDE_APPROVED','admin',$override['patient_id']??null,'granted',null,null,null,"Admin approved emergency override #$eid for $hrs hours");
        $success = "Emergency override approved for $hrs hour(s).";
    }

    if ($action === 'reject') {
        $db->query("UPDATE emergency_access SET access_granted=0, is_expired=1 WHERE id=?", [$eid]);
        $audit->log($userId,'OVERRIDE_REJECTED','admin',null,'denied',null,null,null,"Admin rejected emergency override #$eid");
        $success = 'Emergency override rejected.';
    }

    if ($action === 'revoke') {
        $db->query("UPDATE emergency_access SET is_expired=1, expires_at=NOW() WHERE id=?", [$eid]);
        $audit->log($userId,'OVERRIDE_REVOKED','admin',null,'denied',null,null,null,"Admin revoked emergency override #$eid");
        $success = 'Emergency override revoked.';
    }
}

// Pending overrides (requested but not yet approved/rejected)
$pendingOverrides = $db->fetchAll(
    "SELECT ea.*, u.full_name, u.staff_id, r.role_name, p.full_name AS patient_name, p.patient_id AS pid
     FROM emergency_access ea
     JOIN users u ON ea.user_id=u.id
     JOIN roles r ON u.role_id=r.id
     LEFT JOIN patients p ON ea.patient_id=p.id
     WHERE ea.access_granted=0 AND ea.is_expired=0
     ORDER BY ea.created_at DESC"
);

// Active approved overrides
$activeOverrides = $db->fetchAll(
    "SELECT ea.*, u.full_name, u.staff_id, r.role_name, p.full_name AS patient_name, p.patient_id AS pid
     FROM emergency_access ea
     JOIN users u ON ea.user_id=u.id
     JOIN roles r ON u.role_id=r.id
     LEFT JOIN patients p ON ea.patient_id=p.id
     WHERE ea.access_granted=1 AND ea.is_expired=0 AND ea.expires_at>NOW()
     ORDER BY ea.created_at DESC"
);

// History
$historyOverrides = $db->fetchAll(
    "SELECT ea.*, u.full_name, u.staff_id, r.role_name, p.full_name AS patient_name
     FROM emergency_access ea
     JOIN users u ON ea.user_id=u.id
     JOIN roles r ON u.role_id=r.id
     LEFT JOIN patients p ON ea.patient_id=p.id
     WHERE ea.is_expired=1 OR ea.access_granted=0
     ORDER BY ea.created_at DESC LIMIT 30"
);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-shield-exclamation" style="color:var(--red);margin-right:8px"></i>Emergency Override Management</h1>
    <p>Review, approve and revoke emergency access override requests</p>
  </div>
  <div class="d-flex" style="gap:8px">
    <span class="badge badge-amber" style="padding:8px 16px;font-size:13px"><?= count($pendingOverrides) ?> Pending</span>
    <span class="badge badge-red" style="padding:8px 16px;font-size:13px"><?= count($activeOverrides) ?> Active</span>
  </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Pending Requests -->
<div class="card mb-4">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-hourglass-split" style="color:var(--amber)"></i>Pending Approval (<?= count($pendingOverrides) ?>)</div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($pendingOverrides)): ?>
    <div style="text-align:center;padding:40px;color:var(--gray-400)">
      <i class="bi bi-check-circle" style="font-size:2.5rem;color:var(--green)"></i>
      <div style="margin-top:10px;font-weight:600">No pending requests</div>
    </div>
    <?php else: ?>
    <?php foreach ($pendingOverrides as $o): ?>
    <div style="border-bottom:1px solid var(--gray-100);padding:20px;background:rgba(245,158,11,0.03)">
      <div class="d-flex" style="gap:16px;align-items:flex-start">
        <div style="width:44px;height:44px;background:rgba(245,158,11,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--amber);font-size:20px;flex-shrink:0">
          <i class="bi bi-shield-exclamation"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div class="d-flex align-center" style="gap:10px;margin-bottom:6px;flex-wrap:wrap">
            <span style="font-weight:700;font-size:14px"><?= htmlspecialchars($o['full_name']) ?></span>
            <span class="badge badge-navy"><?= htmlspecialchars($o['staff_id']) ?></span>
            <span class="badge badge-blue"><?= htmlspecialchars($o['role_name']) ?></span>
          </div>
          <div style="color:var(--gray-700);font-size:13px;margin-bottom:6px;line-height:1.5">
            <strong>Reason:</strong> <?= htmlspecialchars($o['reason']) ?>
          </div>
          <div style="color:var(--gray-500);font-size:12px;display:flex;gap:14px;flex-wrap:wrap">
            <span><i class="bi bi-person" style="margin-right:4px"></i>Patient: <?= htmlspecialchars($o['patient_name']??'General Access') ?></span>
            <span><i class="bi bi-clock" style="margin-right:4px"></i><?= date('d M Y H:i',strtotime($o['created_at'])) ?></span>
            <span><i class="bi bi-geo-alt" style="margin-right:4px"></i><?= htmlspecialchars($o['ip_address']??'Unknown IP') ?></span>
          </div>
        </div>
        <div class="d-flex" style="gap:8px;flex-shrink:0;flex-direction:column">
          <!-- Approve form -->
          <form method="POST" class="d-flex" style="gap:6px;align-items:center">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="override_id" value="<?= $o['id'] ?>">
            <select name="hours" class="form-select" style="width:90px;padding:5px 8px;font-size:12px">
              <option value="1">1 hr</option>
              <option value="2" selected>2 hrs</option>
              <option value="4">4 hrs</option>
              <option value="8">8 hrs</option>
            </select>
            <button type="submit" class="btn btn-teal btn-sm" onclick="return confirm('Approve this emergency override?')">
              <i class="bi bi-check2"></i> Approve
            </button>
          </form>
          <!-- Reject form -->
          <form method="POST">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="override_id" value="<?= $o['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm w-100" onclick="return confirm('Reject this override request?')">
              <i class="bi bi-x-lg"></i> Reject
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Active Overrides -->
<div class="card mb-4">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-shield-fill-check" style="color:var(--green)"></i>Active Overrides (<?= count($activeOverrides) ?>)</div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($activeOverrides)): ?>
    <div style="text-align:center;padding:30px;color:var(--gray-400);font-size:13px">No active overrides</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th style="padding-left:20px">Staff</th>
          <th>Role</th>
          <th>Patient</th>
          <th>Risk Score</th>
          <th>Expires</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($activeOverrides as $o): ?>
        <?php $rl = $risk->getRiskLevel($o['risk_score']??0); ?>
        <tr>
          <td style="padding-left:20px">
            <div style="font-weight:600"><?= htmlspecialchars($o['full_name']) ?></div>
            <div style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($o['staff_id']) ?></div>
          </td>
          <td><span class="badge badge-blue"><?= htmlspecialchars($o['role_name']) ?></span></td>
          <td style="font-size:13px"><?= htmlspecialchars($o['patient_name']??'General') ?></td>
          <td><span class="risk-badge risk-<?= $rl ?>"><?= $rl ?> (<?= $o['risk_score']??0 ?>)</span></td>
          <td style="font-size:12px;color:var(--gray-600)"><?= date('d M H:i',strtotime($o['expires_at'])) ?></td>
          <td>
            <form method="POST">
              <input type="hidden" name="action" value="revoke">
              <input type="hidden" name="override_id" value="<?= $o['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Revoke this override immediately?')">
                <i class="bi bi-stop-circle"></i> Revoke
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- History -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-clock-history"></i>Override History (Last 30)</div>
  </div>
  <div class="card-body" style="padding:0">
    <table>
      <thead>
        <tr>
          <th style="padding-left:20px">Date</th>
          <th>Staff</th>
          <th>Role</th>
          <th>Patient</th>
          <th>Status</th>
          <th>Reason</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($historyOverrides as $h): ?>
        <tr>
          <td style="padding-left:20px;font-size:12px;color:var(--gray-500)"><?= date('d M Y H:i',strtotime($h['created_at'])) ?></td>
          <td>
            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($h['full_name']) ?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($h['staff_id']) ?></div>
          </td>
          <td style="font-size:13px"><?= htmlspecialchars($h['role_name']) ?></td>
          <td style="font-size:13px"><?= htmlspecialchars($h['patient_name']??'General') ?></td>
          <td>
            <?php if ($h['is_expired'] && !$h['access_granted']): ?>
            <span class="badge badge-red">Rejected/Revoked</span>
            <?php elseif ($h['is_expired'] && $h['access_granted']): ?>
            <span class="badge badge-gray">Expired</span>
            <?php else: ?>
            <span class="badge badge-amber">Pending</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--gray-600);max-width:200px"><?= htmlspecialchars(substr($h['reason'],0,80)) ?>...</td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($historyOverrides)): ?>
        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--gray-400)">No history</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
