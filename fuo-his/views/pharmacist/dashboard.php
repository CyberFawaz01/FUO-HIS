<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireRole(['pharmacist']);
$db    = Database::getInstance();
$audit = new AuditLogger();
$userId   = $_SESSION['user_id'];
$pageTitle = 'Pharmacist Dashboard — CA-RBAC HIS';
$success = $error = '';

// Add dispensed_status column if not exists (safe to run multiple times)
// We track dispensed using a separate column instead of title prefix
// Check if column exists first
try {
    $db->fetchOne("SELECT dispensed FROM patient_records LIMIT 1");
} catch (Exception $e) {
    // Column doesn't exist, add it
    $db->query("ALTER TABLE patient_records ADD COLUMN `dispensed` TINYINT(1) DEFAULT 0 AFTER `is_confidential`");
    $db->query("ALTER TABLE patient_records ADD COLUMN `dispensed_by` INT(11) DEFAULT NULL AFTER `dispensed`");
    $db->query("ALTER TABLE patient_records ADD COLUMN `dispensed_at` DATETIME DEFAULT NULL AFTER `dispensed_by`");
}

// Handle dispense action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispense'])) {
    $recId = (int)$_POST['record_id'];
    $rec   = $db->fetchOne(
        "SELECT * FROM patient_records WHERE id=? AND record_type='medication' AND (dispensed=0 OR dispensed IS NULL)",
        [$recId]
    );
    if ($rec) {
        $db->query(
            "UPDATE patient_records SET dispensed=1, dispensed_by=?, dispensed_at=NOW() WHERE id=?",
            [$userId, $recId]
        );
        $audit->log($userId, 'MEDICATION_DISPENSED', 'pharmacy', $rec['patient_id'],
            'granted', null, null, null, "Dispensed: " . $rec['title']);
        $success = 'Medication marked as dispensed successfully.';
    } else {
        $error = 'Prescription not found or already dispensed.';
    }
}

// Live counts (recalculated after any dispense action)
$totalRx    = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='medication'")['c'];
$dispensedRx= $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='medication' AND dispensed=1")['c'];
$pendingRx  = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='medication' AND (dispensed=0 OR dispensed IS NULL)")['c'];
$todayRx    = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='medication' AND DATE(record_date)=CURDATE()")['c'];

// Pending prescriptions (not dispensed)
$pendingList = $db->fetchAll(
    "SELECT pr.*, p.full_name AS patient_name, p.patient_id AS pid,
            p.blood_group, u.full_name AS doctor_name, d.dept_name
     FROM patient_records pr
     JOIN patients p ON pr.patient_id=p.id
     JOIN users u ON pr.created_by=u.id
     LEFT JOIN departments d ON p.department_id=d.id
     WHERE pr.record_type='medication' AND (pr.dispensed=0 OR pr.dispensed IS NULL)
     ORDER BY pr.record_date DESC"
);

// Recently dispensed (last 10)
$dispensedList = $db->fetchAll(
    "SELECT pr.*, p.full_name AS patient_name, p.patient_id AS pid,
            u.full_name AS doctor_name, du.full_name AS dispensed_by_name
     FROM patient_records pr
     JOIN patients p ON pr.patient_id=p.id
     JOIN users u ON pr.created_by=u.id
     LEFT JOIN users du ON pr.dispensed_by=du.id
     WHERE pr.record_type='medication' AND pr.dispensed=1
     ORDER BY pr.dispensed_at DESC LIMIT 10"
);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-capsule" style="color:var(--teal);margin-right:8px"></i>Pharmacy Dashboard</h1>
    <p>Manage and dispense patient prescriptions</p>
  </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Live Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card stat-amber">
    <div class="stat-top">
      <div><div class="stat-label">Pending Dispense</div><div class="stat-value"><?= $pendingRx ?></div></div>
      <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
    </div>
    <div style="font-size:12px;color:var(--gray-500)">Awaiting dispensing</div>
  </div>
  <div class="stat-card stat-green">
    <div class="stat-top">
      <div><div class="stat-label">Dispensed</div><div class="stat-value"><?= $dispensedRx ?></div></div>
      <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
    </div>
    <div style="font-size:12px;color:var(--gray-500)">Completed</div>
  </div>
  <div class="stat-card stat-teal">
    <div class="stat-top">
      <div><div class="stat-label">Total Prescriptions</div><div class="stat-value"><?= $totalRx ?></div></div>
      <div class="stat-icon"><i class="bi bi-capsule"></i></div>
    </div>
  </div>
  <div class="stat-card stat-blue">
    <div class="stat-top">
      <div><div class="stat-label">Prescribed Today</div><div class="stat-value"><?= $todayRx ?></div></div>
      <div class="stat-icon"><i class="bi bi-calendar-day"></i></div>
    </div>
  </div>
</div>

<!-- Pending Prescriptions -->
<div class="card mb-4">
  <div class="card-header">
    <div class="card-title">
      <i class="bi bi-hourglass-split" style="color:var(--amber)"></i>
      Pending Dispensing (<?= $pendingRx ?>)
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($pendingList)): ?>
    <div style="text-align:center;padding:50px;color:var(--gray-400)">
      <i class="bi bi-check-circle" style="font-size:3rem;color:var(--green)"></i>
      <div style="margin-top:12px;font-weight:600;font-size:15px">All prescriptions dispensed!</div>
      <div style="font-size:13px;margin-top:4px">No pending prescriptions at this time.</div>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th style="padding-left:20px">Patient</th>
          <th>Medication</th>
          <th>Instructions</th>
          <th>Prescribed By</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pendingList as $rx): ?>
        <tr>
          <td style="padding-left:20px">
            <div style="font-weight:600;color:var(--navy)"><?= htmlspecialchars($rx['patient_name']) ?></div>
            <div style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($rx['pid']) ?>
              <?php if($rx['blood_group']): ?>· <span style="color:var(--red);font-weight:600"><?= $rx['blood_group'] ?></span><?php endif; ?>
            </div>
          </td>
          <td>
            <div style="font-weight:600;font-size:13px;color:var(--navy)"><?= htmlspecialchars($rx['title']) ?></div>
          </td>
          <td style="max-width:220px">
            <div style="font-size:12px;color:var(--gray-600);line-height:1.4"><?= htmlspecialchars(substr($rx['content'],0,80)) ?>...</div>
          </td>
          <td>
            <div style="font-size:13px;font-weight:500"><?= htmlspecialchars($rx['doctor_name']) ?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($rx['dept_name']??'—') ?></div>
          </td>
          <td style="font-size:12px;color:var(--gray-500);white-space:nowrap">
            <?= date('d M Y', strtotime($rx['record_date'])) ?><br>
            <span style="font-size:11px"><?= date('H:i', strtotime($rx['record_date'])) ?></span>
          </td>
          <td>
            <form method="POST">
              <input type="hidden" name="dispense" value="1">
              <input type="hidden" name="record_id" value="<?= $rx['id'] ?>">
              <button type="submit" class="btn btn-teal btn-sm"
                      onclick="return confirm('Confirm dispensing: <?= htmlspecialchars($rx['title'], ENT_QUOTES) ?> for <?= htmlspecialchars($rx['patient_name'], ENT_QUOTES) ?>?')">
                <i class="bi bi-check2"></i> Dispense
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

<!-- Recently Dispensed -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <i class="bi bi-clock-history" style="color:var(--green)"></i>
      Recently Dispensed (Last 10)
    </div>
    <a href="<?= APP_URL ?>/views/pharmacist/prescriptions.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($dispensedList)): ?>
    <div style="text-align:center;padding:30px;color:var(--gray-400);font-size:13px">No dispensed medications yet</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th style="padding-left:20px">Patient</th>
          <th>Medication</th>
          <th>Prescribed By</th>
          <th>Dispensed By</th>
          <th>Dispensed At</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dispensedList as $rx): ?>
        <tr style="opacity:0.75">
          <td style="padding-left:20px">
            <div style="font-weight:600"><?= htmlspecialchars($rx['patient_name']) ?></div>
            <div style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($rx['pid']) ?></div>
          </td>
          <td>
            <div style="font-size:13px"><?= htmlspecialchars($rx['title']) ?></div>
            <span class="badge badge-green" style="margin-top:3px"><i class="bi bi-check-circle"></i> Dispensed</span>
          </td>
          <td style="font-size:13px"><?= htmlspecialchars($rx['doctor_name']) ?></td>
          <td style="font-size:13px"><?= htmlspecialchars($rx['dispensed_by_name']??'Unknown') ?></td>
          <td style="font-size:12px;color:var(--gray-500)">
            <?= $rx['dispensed_at'] ? date('d M Y H:i', strtotime($rx['dispensed_at'])) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
