<?php
// =====================================================
// CA-RBAC HIS — Lab Reports (All Patients)
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/PolicyDecisionPoint.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireRole(['lab_tech']);
$db    = Database::getInstance();
$pdp   = new PolicyDecisionPoint();
$audit = new AuditLogger();
$userId   = $_SESSION['user_id'];
$pageTitle = 'Lab Reports — CA-RBAC HIS';
$success = $error = '';

// Handle upload new lab report
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['upload_report'])) {
    $patientId = (int)$_POST['patient_id'];
    $title     = trim($_POST['title'] ?? '');
    $content   = trim($_POST['content'] ?? '');
    $sens      = $_POST['sensitivity_level'] ?? 'Low';

    if (!$patientId || !$title || !$content) {
        $error = 'Please fill all required fields.';
    } else {
        $db->query(
            "INSERT INTO patient_records (patient_id,record_type,title,content,created_by,sensitivity_level) VALUES (?,?,?,?,?,?)",
            [$patientId,'lab_report',$title,$content,$userId,$sens]
        );
        $audit->log($userId,'LAB_REPORT_UPLOADED','lab',$patientId,'granted',null,null,null,"Lab report uploaded: $title");
        $success = "Lab report '$title' uploaded successfully.";
    }
}

// All patients for dropdown
$allPatients = $db->fetchAll("SELECT id, patient_id, full_name FROM patients ORDER BY full_name");

// Filters
$filterPatient = (int)($_GET['patient_id'] ?? 0);
$search        = trim($_GET['q'] ?? '');

$where  = ["pr.record_type='lab_report'"];
$params = [];

if ($filterPatient) {
    $where[]  = 'pr.patient_id = ?';
    $params[] = $filterPatient;
}
if ($search) {
    $where[]  = '(pr.title LIKE ? OR p.full_name LIKE ? OR p.patient_id LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$whereStr = implode(' AND ', $where);

$labReports = $db->fetchAll(
    "SELECT pr.*, p.full_name AS patient_name, p.patient_id AS pid,
            p.blood_group, u.full_name AS tech_name
     FROM patient_records pr
     JOIN patients p ON pr.patient_id = p.id
     LEFT JOIN users u ON pr.created_by = u.id
     WHERE $whereStr
     ORDER BY pr.record_date DESC",
    $params
);

// Stats
$totalReports  = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='lab_report'")['c'];
$todayReports  = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='lab_report' AND DATE(record_date)=CURDATE()")['c'];
$patientsCount = $db->fetchOne("SELECT COUNT(DISTINCT patient_id) c FROM patient_records WHERE record_type='lab_report'")['c'];
$myReports     = $db->fetchOne("SELECT COUNT(*) c FROM patient_records WHERE record_type='lab_report' AND created_by=?", [$userId])['c'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-flask" style="color:var(--teal);margin-right:8px"></i>Lab Reports</h1>
    <p>Upload and manage laboratory reports for all patients</p>
  </div>
  <button class="btn btn-teal" onclick="document.getElementById('uploadModal').style.display='flex'">
    <i class="bi bi-plus-lg"></i> Upload New Report
  </button>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card stat-teal">
    <div class="stat-top"><div><div class="stat-label">Total Reports</div><div class="stat-value"><?= $totalReports ?></div></div><div class="stat-icon"><i class="bi bi-flask"></i></div></div>
  </div>
  <div class="stat-card stat-blue">
    <div class="stat-top"><div><div class="stat-label">Patients Tested</div><div class="stat-value"><?= $patientsCount ?></div></div><div class="stat-icon"><i class="bi bi-people"></i></div></div>
  </div>
  <div class="stat-card stat-green">
    <div class="stat-top"><div><div class="stat-label">Today</div><div class="stat-value"><?= $todayReports ?></div></div><div class="stat-icon"><i class="bi bi-calendar-day"></i></div></div>
  </div>
  <div class="stat-card stat-purple">
    <div class="stat-top"><div><div class="stat-label">My Reports</div><div class="stat-value"><?= $myReports ?></div></div><div class="stat-icon"><i class="bi bi-person-check"></i></div></div>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body" style="padding:14px 20px">
    <form class="d-flex" style="gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1;min-width:200px">
        <label class="form-label">Search</label>
        <div class="input-group">
          <i class="bi bi-search input-icon"></i>
          <input type="text" class="form-control" name="q" placeholder="Report title, patient name or ID..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div style="min-width:200px">
        <label class="form-label">Filter by Patient</label>
        <select class="form-select" name="patient_id">
          <option value="">All Patients</option>
          <?php foreach ($allPatients as $pt): ?>
          <option value="<?= $pt['id'] ?>" <?= $filterPatient==$pt['id']?'selected':'' ?>>
            <?= htmlspecialchars($pt['patient_id'].' — '.$pt['full_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="align-self:flex-end">
        <i class="bi bi-search"></i> Filter
      </button>
      <a href="?" class="btn btn-outline" style="align-self:flex-end">Clear</a>
    </form>
  </div>
</div>

<!-- Reports Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-table"></i>Lab Reports (<?= count($labReports) ?>)</div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($labReports)): ?>
    <div style="text-align:center;padding:60px;color:var(--gray-400)">
      <i class="bi bi-flask" style="font-size:3rem"></i>
      <div style="margin-top:12px;font-weight:600">No lab reports found</div>
      <div style="font-size:13px;margin-top:4px">
        <button class="btn btn-teal btn-sm mt-3" onclick="document.getElementById('uploadModal').style.display='flex'">
          <i class="bi bi-plus-lg"></i> Upload First Report
        </button>
      </div>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th style="padding-left:20px">Patient</th>
          <th>Report Title</th>
          <th>Results Summary</th>
          <th>Sensitivity</th>
          <th>Uploaded By</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($labReports as $r): ?>
        <?php $sensBadge = ['Low'=>'badge-green','Medium'=>'badge-amber','High'=>'badge-red','Critical'=>'badge-navy'][$r['sensitivity_level']] ?? 'badge-gray'; ?>
        <tr>
          <td style="padding-left:20px">
            <div style="font-weight:600;color:var(--navy)"><?= htmlspecialchars($r['patient_name']) ?></div>
            <div style="font-size:12px;color:var(--gray-500)"><?= htmlspecialchars($r['pid']) ?></div>
          </td>
          <td>
            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($r['title']) ?></div>
          </td>
          <td style="max-width:250px">
            <div style="font-size:12px;color:var(--gray-600)"><?= htmlspecialchars(substr($r['content'],0,90)) ?>...</div>
          </td>
          <td><span class="badge <?= $sensBadge ?>"><?= $r['sensitivity_level'] ?></span></td>
          <td style="font-size:13px"><?= htmlspecialchars($r['tech_name']??'Unknown') ?></td>
          <td style="font-size:12px;color:var(--gray-500);white-space:nowrap"><?= date('d M Y H:i',strtotime($r['record_date'])) ?></td>
          <td>
            <a href="<?= APP_URL ?>/views/shared/patient_view.php?id=<?= $r['patient_id'] ?>"
               class="btn btn-outline btn-sm"><i class="bi bi-eye"></i> View Patient</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,0.7);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:20px;width:100%;max-width:560px;margin:20px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,0.2)">
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between">
      <div style="font-size:16px;font-weight:700;color:var(--navy)"><i class="bi bi-flask" style="color:var(--teal);margin-right:8px"></i>Upload Lab Report</div>
      <button onclick="document.getElementById('uploadModal').style.display='none'"
              style="border:none;background:none;font-size:18px;color:var(--gray-400);cursor:pointer">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="upload_report" value="1">
      <div style="padding:24px">
        <div class="form-group">
          <label class="form-label">Patient <span style="color:var(--red)">*</span></label>
          <select class="form-select" name="patient_id" required>
            <option value="">Select Patient</option>
            <?php foreach ($allPatients as $pt): ?>
            <option value="<?= $pt['id'] ?>" <?= $filterPatient==$pt['id']?'selected':'' ?>>
              <?= htmlspecialchars($pt['patient_id'].' — '.$pt['full_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Report Title <span style="color:var(--red)">*</span></label>
          <input type="text" class="form-control" name="title" placeholder="e.g. Full Blood Count, Liver Function Test..." required>
        </div>
        <div class="form-group">
          <label class="form-label">Results / Content <span style="color:var(--red)">*</span></label>
          <textarea class="form-control" name="content" rows="5" required
                    placeholder="Enter test results, values, reference ranges, and any remarks..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Sensitivity Level</label>
          <select class="form-select" name="sensitivity_level">
            <option value="Low">Low</option>
            <option value="Medium">Medium</option>
            <option value="High">High</option>
            <option value="Critical">Critical</option>
          </select>
        </div>
      </div>
      <div style="padding:16px 24px;border-top:1px solid var(--gray-100);display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('uploadModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn btn-teal"><i class="bi bi-upload"></i> Upload Report</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('uploadModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
