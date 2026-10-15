<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireRole(['lab_tech']);
$db    = Database::getInstance();
$audit = new AuditLogger();
$userId   = $_SESSION['user_id'];
$pageTitle = 'Lab Dashboard — CA-RBAC HIS';
$success = $error = '';

// Handle uploading results for a lab request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_results'])) {
    $requestId = (int)$_POST['request_id'];
    $results   = trim($_POST['results'] ?? '');
    $sens      = $_POST['sensitivity_level'] ?? 'Low';

    // Get the original request
    $request = $db->fetchOne(
        "SELECT * FROM patient_records WHERE id=? AND record_type='lab_request'",
        [$requestId]
    );

    if (!$request) {
        $error = 'Lab request not found.';
    } elseif (empty($results)) {
        $error = 'Please enter the test results.';
    } else {
        // Create lab report record
        $db->query(
            "INSERT INTO patient_records (patient_id, record_type, title, content, created_by, sensitivity_level)
             VALUES (?, 'lab_report', ?, ?, ?, ?)",
            [
                $request['patient_id'],
                'Lab Result: ' . $request['title'],
                $results,
                $userId,
                $sens
            ]
        );

        // Mark request as completed
        $db->query(
            "UPDATE patient_records SET content = CONCAT('[COMPLETED]\n', content) WHERE id=?",
            [$requestId]
        );

        $audit->log($userId, 'LAB_RESULTS_UPLOADED', 'lab', $request['patient_id'],
            'granted', null, null, null, "Lab results uploaded for: " . $request['title']);

        $success = 'Lab results uploaded and report created successfully.';
    }
}

// Stats
$pendingRequests  = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='lab_request' AND content NOT LIKE '[COMPLETED]%'")['c'];
$completedToday   = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='lab_report' AND DATE(record_date)=CURDATE()")['c'];
$totalReports     = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='lab_report'")['c'];
$totalPatients    = $db->fetchOne("SELECT COUNT(*) c FROM patients")['c'];

// Pending lab requests from doctors
$pendingList = $db->fetchAll(
    "SELECT pr.*, p.full_name AS patient_name, p.patient_id AS pid,
            p.blood_group, p.date_of_birth,
            u.full_name AS doctor_name, d.dept_name
     FROM patient_records pr
     JOIN patients p ON pr.patient_id=p.id
     JOIN users u ON pr.created_by=u.id
     LEFT JOIN departments d ON p.department_id=d.id
     WHERE pr.record_type='lab_request' AND pr.content NOT LIKE '[COMPLETED]%'
     ORDER BY pr.record_date DESC"
);

// Recently completed lab reports
$completedList = $db->fetchAll(
    "SELECT pr.*, p.full_name AS patient_name, p.patient_id AS pid, u.full_name AS tech_name
     FROM patient_records pr
     JOIN patients p ON pr.patient_id=p.id
     LEFT JOIN users u ON pr.created_by=u.id
     WHERE pr.record_type='lab_report'
     ORDER BY pr.record_date DESC LIMIT 10"
);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-flask" style="color:var(--teal);margin-right:8px"></i>Laboratory Dashboard</h1>
    <p>Process lab requests and upload test results</p>
  </div>
  <a href="<?= APP_URL ?>/views/lab/reports.php" class="btn btn-outline">
    <i class="bi bi-list-ul"></i> All Reports
  </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card stat-amber">
    <div class="stat-top">
      <div><div class="stat-label">Pending Requests</div><div class="stat-value"><?= $pendingRequests ?></div></div>
      <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
    </div>
    <div style="font-size:12px;color:var(--gray-500)">Awaiting processing</div>
  </div>
  <div class="stat-card stat-green">
    <div class="stat-top">
      <div><div class="stat-label">Completed Today</div><div class="stat-value"><?= $completedToday ?></div></div>
      <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
    </div>
  </div>
  <div class="stat-card stat-teal">
    <div class="stat-top">
      <div><div class="stat-label">Total Reports</div><div class="stat-value"><?= $totalReports ?></div></div>
      <div class="stat-icon"><i class="bi bi-flask"></i></div>
    </div>
  </div>
  <div class="stat-card stat-blue">
    <div class="stat-top">
      <div><div class="stat-label">Total Patients</div><div class="stat-value"><?= $totalPatients ?></div></div>
      <div class="stat-icon"><i class="bi bi-people"></i></div>
    </div>
  </div>
</div>

<!-- Pending Lab Requests -->
<div class="card mb-4">
  <div class="card-header">
    <div class="card-title">
      <i class="bi bi-hourglass-split" style="color:var(--amber)"></i>
      Pending Lab Requests from Doctors (<?= $pendingRequests ?>)
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($pendingList)): ?>
    <div style="text-align:center;padding:50px;color:var(--gray-400)">
      <i class="bi bi-check-circle" style="font-size:3rem;color:var(--green)"></i>
      <div style="margin-top:12px;font-weight:600;font-size:15px">No pending lab requests</div>
      <div style="font-size:13px;margin-top:4px">All lab requests have been processed.</div>
    </div>
    <?php else: ?>
    <?php foreach ($pendingList as $req): ?>
    <div style="border-bottom:1px solid var(--gray-100);padding:20px">
      <div style="display:flex;gap:16px;align-items:flex-start">
        <div style="width:44px;height:44px;background:rgba(245,158,11,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--amber);font-size:20px;flex-shrink:0">
          <i class="bi bi-flask"></i>
        </div>
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;flex-wrap:wrap">
            <span style="font-weight:700;font-size:14px;color:var(--navy)"><?= htmlspecialchars($req['title']) ?></span>
            <span class="badge badge-navy"><?= htmlspecialchars($req['pid']) ?></span>
            <?php if($req['blood_group']): ?>
            <span class="badge badge-red"><?= $req['blood_group'] ?></span>
            <?php endif; ?>
          </div>
          <div style="color:var(--gray-700);font-size:13px;margin-bottom:6px">
            <strong>Patient:</strong> <?= htmlspecialchars($req['patient_name']) ?>
          </div>
          <div style="color:var(--gray-600);font-size:13px;margin-bottom:6px;background:var(--gray-50);border-radius:6px;padding:8px 12px">
            <strong>Doctor's Instructions:</strong><br><?= nl2br(htmlspecialchars($req['content'])) ?>
          </div>
          <div style="color:var(--gray-400);font-size:12px;display:flex;gap:14px">
            <span><i class="bi bi-person-circle" style="margin-right:4px"></i>Requested by Dr. <?= htmlspecialchars($req['doctor_name']) ?></span>
            <span><i class="bi bi-clock" style="margin-right:4px"></i><?= date('d M Y H:i', strtotime($req['record_date'])) ?></span>
          </div>
        </div>
        <!-- Upload Results Button -->
        <button class="btn btn-teal btn-sm" style="flex-shrink:0"
                onclick="openResultsModal(<?= $req['id'] ?>, '<?= htmlspecialchars($req['title'], ENT_QUOTES) ?>', '<?= htmlspecialchars($req['patient_name'], ENT_QUOTES) ?>')">
          <i class="bi bi-upload"></i> Upload Results
        </button>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Lab Reports -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-clock-history" style="color:var(--green)"></i>Recent Lab Reports</div>
    <a href="<?= APP_URL ?>/views/lab/reports.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($completedList)): ?>
    <div style="text-align:center;padding:30px;color:var(--gray-400);font-size:13px">No lab reports yet</div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th style="padding-left:20px">Patient</th>
          <th>Report</th>
          <th>Sensitivity</th>
          <th>Uploaded By</th>
          <th>Date</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($completedList as $r): ?>
        <?php $s=['Low'=>'badge-green','Medium'=>'badge-amber','High'=>'badge-red','Critical'=>'badge-navy'][$r['sensitivity_level']]??'badge-gray'; ?>
        <tr>
          <td style="padding-left:20px">
            <div style="font-weight:600"><?= htmlspecialchars($r['patient_name']) ?></div>
            <div style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($r['pid']) ?></div>
          </td>
          <td style="font-size:13px;font-weight:500"><?= htmlspecialchars($r['title']) ?></td>
          <td><span class="badge <?= $s ?>"><?= $r['sensitivity_level'] ?></span></td>
          <td style="font-size:13px"><?= htmlspecialchars($r['tech_name']??'Unknown') ?></td>
          <td style="font-size:12px;color:var(--gray-500)"><?= date('d M Y H:i',strtotime($r['record_date'])) ?></td>
          <td>
            <a href="<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $r['patient_id'] ?>"
               class="btn btn-outline btn-sm"><i class="bi bi-eye"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Upload Results Modal -->
<div id="resultsModal" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,0.7);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:20px;width:100%;max-width:560px;margin:20px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,0.2)">
    <div style="background:linear-gradient(135deg,var(--navy),var(--navy-light));padding:20px 24px;display:flex;align-items:center;justify-content:space-between">
      <div style="color:white;font-weight:700;font-size:16px">
        <i class="bi bi-flask" style="margin-right:8px;color:var(--teal)"></i>
        Upload Lab Results
      </div>
      <button onclick="document.getElementById('resultsModal').style.display='none'"
              style="border:none;background:rgba(255,255,255,0.15);color:white;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:16px">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="upload_results" value="1">
      <input type="hidden" name="request_id" id="modalRequestId">
      <div style="padding:24px">
        <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:var(--gray-600)">
          <div><strong>Test:</strong> <span id="modalTestName"></span></div>
          <div><strong>Patient:</strong> <span id="modalPatientName"></span></div>
        </div>
        <div class="form-group">
          <label class="form-label">Test Results <span style="color:var(--red)">*</span></label>
          <textarea class="form-control" name="results" rows="6" required
                    placeholder="Enter complete test results including values, reference ranges, and any remarks..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Sensitivity Level <span style="color:var(--red)">*</span></label>
          <select class="form-select" name="sensitivity_level" required>
            <option value="Low">Low</option>
            <option value="Medium">Medium</option>
            <option value="High">High</option>
            <option value="Critical">Critical</option>
          </select>
        </div>
      </div>
      <div style="padding:16px 24px;border-top:1px solid var(--gray-100);display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('resultsModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-teal"><i class="bi bi-upload"></i> Upload Results</button>
      </div>
    </form>
  </div>
</div>

<script>
function openResultsModal(requestId, testName, patientName) {
    document.getElementById('modalRequestId').value    = requestId;
    document.getElementById('modalTestName').textContent   = testName;
    document.getElementById('modalPatientName').textContent = patientName;
    document.getElementById('resultsModal').style.display  = 'flex';
}
document.getElementById('resultsModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
