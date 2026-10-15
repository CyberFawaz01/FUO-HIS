<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';
$auth = new AuthController(); $auth->requireRole(['pharmacist']);
$db = Database::getInstance(); $audit = new AuditLogger();
$userId = $_SESSION['user_id']; $pageTitle = 'Prescriptions — CA-RBAC HIS';
$success = $error = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['dispense'])) {
    $recId = (int)$_POST['record_id'];
    $note  = trim($_POST['dispense_note'] ?? '');
    $rec   = $db->fetchOne("SELECT * FROM patient_records WHERE id=? AND record_type='medication'", [$recId]);
    if ($rec) {
        $db->query("INSERT INTO patient_records (patient_id,record_type,title,content,created_by,sensitivity_level) VALUES (?,?,?,?,?,'Low')",
            [$rec['patient_id'],'treatment','Medication Dispensed: '.$rec['title'],'Dispensed by Pharmacist. '.($note?'Note: '.$note:''),$userId]);
        $audit->log($userId,'MEDICATION_DISPENSED','pharmacy',$rec['patient_id'],'granted',null,null,null,'Dispensed: '.$rec['title']);
        $success = 'Medication marked as dispensed.';
    }
}

$search = trim($_GET['q'] ?? '');
$params = []; $where = "pr.record_type='medication'";
if ($search) { $where .= " AND (p.full_name LIKE ? OR pr.title LIKE ? OR u.full_name LIKE ?)"; $params = ["%$search%","%$search%","%$search%"]; }

$prescriptions = $db->fetchAll(
    "SELECT pr.*, p.full_name AS patient_name, p.patient_id AS pid, p.blood_group,
            u.full_name AS doctor_name, d.dept_name
     FROM patient_records pr JOIN patients p ON pr.patient_id=p.id
     JOIN users u ON pr.created_by=u.id LEFT JOIN departments d ON p.department_id=d.id
     WHERE $where ORDER BY pr.record_date DESC", $params
);
$totalRx   = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='medication'")['c'];
$todayRx   = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='medication' AND DATE(record_date)=CURDATE()")['c'];
$dispensed = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='treatment' AND title LIKE 'Medication Dispensed:%'")['c'];
include __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <div><h1><i class="bi bi-capsule" style="color:var(--teal);margin-right:8px"></i>Prescriptions</h1>
  <p>View and dispense medications prescribed by doctors</p></div>
</div>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card stat-teal"><div class="stat-top"><div><div class="stat-label">Total</div><div class="stat-value"><?= $totalRx ?></div></div><div class="stat-icon"><i class="bi bi-capsule"></i></div></div></div>
  <div class="stat-card stat-blue"><div class="stat-top"><div><div class="stat-label">Today</div><div class="stat-value"><?= $todayRx ?></div></div><div class="stat-icon"><i class="bi bi-calendar-check"></i></div></div></div>
  <div class="stat-card stat-green"><div class="stat-top"><div><div class="stat-label">Dispensed</div><div class="stat-value"><?= $dispensed ?></div></div><div class="stat-icon"><i class="bi bi-check-circle"></i></div></div></div>
</div>
<div class="card" style="margin-bottom:16px"><div class="card-body" style="padding:12px 16px">
  <form method="GET" style="display:flex;gap:10px">
    <div style="position:relative;flex:1"><i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--gray-400)"></i>
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search patient, medication, or doctor..." style="width:100%;padding:9px 14px 9px 36px;border:1.5px solid var(--gray-200);border-radius:8px;font-size:13px;font-family:inherit;outline:none" onfocus="this.style.borderColor='var(--teal)'" onblur="this.style.borderColor='var(--gray-200)'"></div>
    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Search</button>
    <?php if ($search): ?><a href="?" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
  </form>
</div></div>
<div class="card"><div class="card-header"><div class="card-title"><i class="bi bi-list-ul"></i>All Prescriptions (<?= count($prescriptions) ?>)</div></div>
<div class="card-body" style="padding:0">
<?php if (empty($prescriptions)): ?>
<div style="text-align:center;padding:60px;color:var(--gray-400)"><i class="bi bi-capsule" style="font-size:3rem"></i><div style="margin-top:12px;font-weight:600">No prescriptions found</div></div>
<?php else: ?>
<div style="overflow-x:auto"><table><thead><tr><th style="padding:10px 20px">Patient</th><th>Medication</th><th>Details</th><th>Doctor</th><th>Date</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($prescriptions as $rx): ?>
<tr style="cursor:default">
  <td style="padding:14px 20px"><div style="font-weight:600;color:var(--navy)"><?= htmlspecialchars($rx['patient_name']) ?></div><div style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($rx['pid']) ?><?php if($rx['blood_group']): ?> · <span style="color:var(--red);font-weight:600"><?= $rx['blood_group'] ?></span><?php endif; ?></div></td>
  <td><div style="font-weight:600;color:var(--gray-800)"><?= htmlspecialchars($rx['title']) ?></div></td>
  <td style="max-width:200px"><div style="font-size:12px;color:var(--gray-600)"><?= htmlspecialchars(substr($rx['content'],0,90)) ?><?= strlen($rx['content'])>90?'...':'' ?></div></td>
  <td><div style="font-size:13px"><?= htmlspecialchars($rx['doctor_name']) ?></div></td>
  <td style="font-size:12px;color:var(--gray-500)"><?= date('d M Y',strtotime($rx['record_date'])) ?></td>
  <td><div style="display:flex;gap:6px">
    <a href="<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $rx['patient_id'] ?>" class="btn btn-outline btn-sm"><i class="bi bi-eye"></i></a>
    <button class="btn btn-teal btn-sm" onclick="showDispense(<?= $rx['id'] ?>,'<?= addslashes($rx['title']) ?>','<?= addslashes($rx['patient_name']) ?>')"><i class="bi bi-check2-circle"></i> Dispense</button>
  </div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div></div>

<div id="dispModal" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,0.7);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:20px;width:100%;max-width:440px;margin:20px;overflow:hidden">
    <div style="padding:18px 24px;background:var(--navy)"><div style="color:white;font-weight:700"><i class="bi bi-capsule" style="color:var(--teal);margin-right:8px"></i>Dispense Medication</div></div>
    <form method="POST"><input type="hidden" name="dispense" value="1"><input type="hidden" name="record_id" id="dispRecId">
    <div style="padding:20px">
      <div style="background:var(--gray-50);border-radius:8px;padding:12px;margin-bottom:14px">
        <div style="font-size:12px;color:var(--gray-500)">Medication</div>
        <div style="font-weight:700;color:var(--navy)" id="dispMed"></div>
        <div style="font-size:13px;color:var(--gray-600)">Patient: <span id="dispPt"></span></div>
      </div>
      <div class="form-group"><label class="form-label">Dispense Note (Optional)</label>
      <textarea class="form-control" name="dispense_note" rows="3" placeholder="e.g. 30 tablets dispensed, patient counselled..."></textarea></div>
    </div>
    <div style="padding:14px 24px;border-top:1px solid var(--gray-100);display:flex;gap:10px;justify-content:flex-end">
      <button type="button" class="btn btn-outline" onclick="document.getElementById('dispModal').style.display='none'">Cancel</button>
      <button type="submit" class="btn btn-teal"><i class="bi bi-check2-circle"></i> Confirm Dispense</button>
    </div></form>
  </div>
</div>
<script>
function showDispense(id,med,pt){document.getElementById('dispRecId').value=id;document.getElementById('dispMed').textContent=med;document.getElementById('dispPt').textContent=pt;document.getElementById('dispModal').style.display='flex';}
document.getElementById('dispModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none';});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
