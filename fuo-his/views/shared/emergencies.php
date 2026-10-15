<?php
// =====================================================
// CA-RBAC HIS — Patient Emergencies (Staff View)
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/PolicyDecisionPoint.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireLogin();
$db    = Database::getInstance();
$pdp   = new PolicyDecisionPoint();
$audit = new AuditLogger();

$userId   = $_SESSION['user_id'];
$roleId   = $_SESSION['role_id'];
$roleName = $_SESSION['role_name'];
$pageTitle = 'Patient Emergencies — CA-RBAC HIS';

$canClaim = in_array($roleName, ['doctor','surgeon','consultant']);
$canView  = in_array($roleName, ['doctor','surgeon','consultant','nurse','admin']);

if (!$canView) { header('Location: '.APP_URL.'/views/shared/unauthorized.php'); exit; }

$success = $error = '';

// Handle claiming emergency (doctor only, first one wins)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['claim_emergency']) && $canClaim) {
    $eid = (int)$_POST['emergency_id'];

    // Check if already claimed
    $emrg = $db->fetchOne("SELECT * FROM patient_emergencies WHERE id=? AND status='active'", [$eid]);
    if (!$emrg) {
        $error = 'This emergency has already been claimed or resolved.';
    } else {
        // Claim it
        $db->query(
            "UPDATE patient_emergencies SET status='claimed', claimed_by=?, claimed_at=NOW() WHERE id=? AND status='active'",
            [$userId, $eid]
        );
        if ($db->fetchOne("SELECT claimed_by FROM patient_emergencies WHERE id=?", [$eid])['claimed_by'] == $userId) {
            // Assign doctor to patient automatically
            $patientId = $emrg['patient_id'];
            $exists = $db->fetchOne("SELECT id FROM assignments WHERE user_id=? AND patient_id=? AND is_active=1", [$userId, $patientId]);
            if (!$exists) {
                $db->query("INSERT INTO assignments (user_id,patient_id,assigned_by,notes) VALUES (?,?,?,'Auto-assigned via emergency claim')", [$userId, $patientId, $userId]);
            }
            // Mark notifications read
            $db->query("UPDATE emergency_notifications SET is_read=1, read_at=NOW() WHERE emergency_id=? AND doctor_id=?", [$eid, $userId]);
            $audit->log($userId,'EMERGENCY_CLAIMED','emergency',$patientId,'granted',null,null,null,"Doctor claimed patient emergency #$eid");
            $success = 'Emergency claimed. You are now the responding doctor. Patient record is accessible.';
        } else {
            $error = 'Another doctor has already claimed this emergency.';
        }
    }
}

// Handle resolve
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['resolve_emergency']) && $canClaim) {
    $eid   = (int)$_POST['emergency_id'];
    $notes = trim($_POST['resolution_notes'] ?? '');
    $emrg  = $db->fetchOne("SELECT * FROM patient_emergencies WHERE id=? AND claimed_by=?", [$eid, $userId]);
    if ($emrg) {
        $db->query("UPDATE patient_emergencies SET status='resolved', resolved_at=NOW(), resolution_notes=? WHERE id=?", [$notes, $eid]);
        $db->query("UPDATE patients SET is_emergency=0, emergency_condition=NULL WHERE id=?", [$emrg['patient_id']]);
        $audit->log($userId,'EMERGENCY_RESOLVED','emergency',$emrg['patient_id'],'info',null,null,null,"Emergency #$eid resolved");
        $success = 'Emergency marked as resolved.';
    }
}

// Fetch emergencies
$activeEmergencies = $db->fetchAll(
    "SELECT pe.*, p.full_name AS patient_name, p.patient_id AS pid, p.date_of_birth, p.blood_group,
            u.full_name AS claimed_by_name, u.staff_id AS claimed_staff_id
     FROM patient_emergencies pe
     JOIN patients p ON pe.patient_id=p.id
     LEFT JOIN users u ON pe.claimed_by=u.id
     WHERE pe.status IN ('active','claimed')
     ORDER BY pe.severity='Critical' DESC, pe.severity='Severe' DESC, pe.created_at ASC"
);

$resolvedEmergencies = $db->fetchAll(
    "SELECT pe.*, p.full_name AS patient_name, p.patient_id AS pid, u.full_name AS claimed_by_name
     FROM patient_emergencies pe
     JOIN patients p ON pe.patient_id=p.id
     LEFT JOIN users u ON pe.claimed_by=u.id
     WHERE pe.status='resolved'
     ORDER BY pe.resolved_at DESC LIMIT 20"
);

// My claimed emergency
$myClaim = $canClaim ? $db->fetchOne("SELECT * FROM patient_emergencies WHERE claimed_by=? AND status='claimed'", [$userId]) : null;

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-heart-pulse-fill" style="color:var(--red);margin-right:8px"></i>Patient Emergencies</h1>
    <p><?= count($activeEmergencies) ?> active · <?= count($resolvedEmergencies) ?> resolved today</p>
  </div>
  <?php if ($roleName==='nurse'): ?>
  <div class="badge badge-amber" style="padding:8px 16px;font-size:13px">
    <i class="bi bi-eye"></i> View Only — Nurse Access
  </div>
  <?php endif; ?>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- My Active Claim -->
<?php if ($myClaim): ?>
<?php $myPt = $db->fetchOne("SELECT * FROM patients WHERE id=?", [$myClaim['patient_id']]); ?>
<div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);border-left:4px solid var(--green);border-radius:10px;padding:16px 20px;margin-bottom:20px">
  <div class="d-flex align-center justify-between">
    <div>
      <div style="font-weight:700;color:var(--green);font-size:14px"><i class="bi bi-person-check-fill"></i> You are handling: <?= htmlspecialchars($myPt['full_name']??'') ?></div>
      <div style="color:var(--gray-600);font-size:13px;margin-top:4px"><?= htmlspecialchars($myClaim['symptoms']) ?></div>
    </div>
    <div class="d-flex" style="gap:8px">
      <a href="<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $myClaim['patient_id'] ?>" class="btn btn-teal btn-sm">
        <i class="bi bi-folder2-open"></i> Open EHR
      </a>
      <button class="btn btn-outline btn-sm" data-resolve="<?= $myClaim['id'] ?>" onclick="showResolveModal(<?= $myClaim['id'] ?>)">
        <i class="bi bi-check2-circle"></i> Mark Resolved
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Active Emergencies -->
<div class="card mb-4">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-heart-pulse-fill" style="color:var(--red)"></i>Active Emergencies (<?= count($activeEmergencies) ?>)</div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($activeEmergencies)): ?>
    <div style="text-align:center;padding:60px;color:var(--gray-400)">
      <i class="bi bi-check-circle" style="font-size:3rem;color:var(--green)"></i>
      <div style="margin-top:12px;font-weight:600">No active emergencies</div>
      <div style="font-size:13px">All patients are stable</div>
    </div>
    <?php else: ?>
    <?php foreach ($activeEmergencies as $e): ?>
    <?php
      $sevColors = ['Critical'=>['var(--red)','rgba(239,68,68,0.08)'],'Severe'=>['#dc2626','rgba(220,38,38,0.06)'],'Moderate'=>['var(--amber)','rgba(245,158,11,0.06)'],'Mild'=>['var(--green)','rgba(16,185,129,0.06)']];
      $sc = $sevColors[$e['severity']] ?? ['var(--gray-500)','var(--gray-50)'];
      $isMyClaim = ($e['claimed_by']==$userId && $e['status']==='claimed');
      $isClaimed = ($e['status']==='claimed');
    ?>
    <div style="border-bottom:1px solid var(--gray-100);padding:20px;background:<?= $sc[1] ?>">
      <div class="d-flex" style="gap:16px;align-items:flex-start">
        <!-- Severity indicator -->
        <div style="width:48px;height:48px;border-radius:12px;background:<?= $sc[0] ?>22;display:flex;align-items:center;justify-content:center;color:<?= $sc[0] ?>;font-size:22px;flex-shrink:0">
          <i class="bi bi-heart-pulse-fill"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div class="d-flex align-center" style="gap:10px;margin-bottom:6px;flex-wrap:wrap">
            <span style="font-weight:700;font-size:15px;color:var(--gray-900)"><?= htmlspecialchars($e['patient_name']) ?></span>
            <span style="background:var(--navy);color:white;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600"><?= htmlspecialchars($e['pid']) ?></span>
            <span style="background:<?= $sc[0] ?>22;color:<?= $sc[0] ?>;border:1px solid <?= $sc[0] ?>44;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700"><?= $e['severity'] ?></span>
            <?php if ($isClaimed): ?>
            <span style="background:rgba(16,185,129,0.1);color:var(--green);border:1px solid rgba(16,185,129,0.2);border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600">
              <i class="bi bi-person-check"></i> Claimed by <?= $isMyClaim ? 'You' : htmlspecialchars($e['claimed_by_name']) ?>
            </span>
            <?php endif; ?>
          </div>
          <div style="color:var(--gray-700);font-size:13px;margin-bottom:8px;line-height:1.5"><?= htmlspecialchars($e['symptoms']) ?></div>
          <div style="color:var(--gray-500);font-size:12px;display:flex;gap:16px;flex-wrap:wrap">
            <span><i class="bi bi-clock" style="margin-right:4px"></i><?= date('d M Y H:i', strtotime($e['created_at'])) ?></span>
            <?php if ($e['geolocation_address']): ?>
            <span><i class="bi bi-geo-alt" style="margin-right:4px;color:var(--teal)"></i><?= htmlspecialchars(substr($e['geolocation_address'],0,60)) ?></span>
            <?php endif; ?>
            <?php if ($e['blood_group']): ?>
            <span><i class="bi bi-droplet" style="margin-right:4px;color:var(--red)"></i>Blood: <?= $e['blood_group'] ?></span>
            <?php endif; ?>
          </div>
        </div>
        <!-- Actions -->
        <div class="d-flex" style="gap:8px;flex-shrink:0;flex-direction:column;align-items:flex-end">
          <?php if ($roleName==='nurse'): ?>
          <!-- Nurse: view only, no access to record -->
          <span class="badge badge-gray"><i class="bi bi-eye"></i> View Only</span>
          <?php elseif ($isMyClaim): ?>
          <a href="<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $e['patient_id'] ?>" class="btn btn-teal btn-sm">
            <i class="bi bi-folder2-open"></i> Open EHR
          </a>
          <button class="btn btn-outline btn-sm" onclick="showResolveModal(<?= $e['id'] ?>)">
            <i class="bi bi-check2-circle"></i> Resolve
          </button>
          <?php elseif (!$isClaimed && $canClaim): ?>
          <form method="POST">
            <input type="hidden" name="claim_emergency" value="1">
            <input type="hidden" name="emergency_id" value="<?= $e['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Claim this emergency? You will be the only doctor with immediate access.')">
              <i class="bi bi-hand-index-thumb"></i> Claim & Respond
            </button>
          </form>
          <?php elseif ($isClaimed && !$isMyClaim): ?>
          <span class="badge badge-green"><i class="bi bi-check-circle"></i> Being Handled</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Resolved Emergencies -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-clock-history"></i>Recently Resolved</div>
  </div>
  <div class="card-body" style="padding:0">
    <table>
      <thead>
        <tr>
          <th style="padding:10px 20px">Patient</th>
          <th>Symptoms</th>
          <th>Severity</th>
          <th>Responded by</th>
          <th>Resolved At</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($resolvedEmergencies as $r): ?>
        <tr>
          <td style="padding:12px 20px">
            <div style="font-weight:600"><?= htmlspecialchars($r['patient_name']) ?></div>
            <div style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($r['pid']) ?></div>
          </td>
          <td style="max-width:250px;color:var(--gray-600);font-size:13px"><?= htmlspecialchars(substr($r['symptoms'],0,80)) ?>...</td>
          <td><span class="badge badge-gray"><?= $r['severity'] ?></span></td>
          <td style="font-size:13px"><?= htmlspecialchars($r['claimed_by_name']??'Unassigned') ?></td>
          <td style="font-size:12px;color:var(--gray-500)"><?= $r['resolved_at'] ? date('d M Y H:i',strtotime($r['resolved_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($resolvedEmergencies)): ?>
        <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--gray-400)">No resolved emergencies</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Resolve Modal -->
<div id="resolveModal" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,0.7);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:20px;width:100%;max-width:480px;margin:20px;overflow:hidden;box-shadow:var(--shadow-lg)">
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--gray-100)">
      <div style="font-size:16px;font-weight:700;color:var(--navy)"><i class="bi bi-check2-circle" style="color:var(--green);margin-right:8px"></i>Resolve Emergency</div>
    </div>
    <form method="POST">
      <input type="hidden" name="resolve_emergency" value="1">
      <input type="hidden" name="emergency_id" id="resolveEmergencyId">
      <div style="padding:24px">
        <div class="form-group">
          <label class="form-label">Resolution Notes</label>
          <textarea class="form-control" name="resolution_notes" rows="3" placeholder="Briefly describe how the emergency was handled..."></textarea>
        </div>
      </div>
      <div style="padding:16px 24px;border-top:1px solid var(--gray-100);display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('resolveModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-teal"><i class="bi bi-check2"></i> Mark Resolved</button>
      </div>
    </form>
  </div>
</div>

<script>
function showResolveModal(eid) {
    document.getElementById('resolveEmergencyId').value = eid;
    document.getElementById('resolveModal').style.display = 'flex';
}
document.getElementById('resolveModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});

// Auto-refresh every 30 seconds to catch new emergencies
setTimeout(() => location.reload(), 30000);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
