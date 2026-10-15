<?php
// =====================================================
// CA-RBAC HIS — Audit Logs (Admin)
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';

$auth = new AuthController();
$auth->requireRole('admin');
$audit = new AuditLogger();
$db = Database::getInstance();

$pageTitle = 'Audit Logs — CA-RBAC HIS';

// Filters
$filters = [
    'user_id'   => (int)($_GET['user_id']   ?? 0) ?: null,
    'decision'  => $_GET['decision']  ?? '',
    'module'    => $_GET['module']    ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to'   => $_GET['date_to']   ?? '',
];
$limit = (int)($_GET['limit'] ?? 100);
$logs = $audit->getLogs($limit, array_filter($filters));
$stats = $audit->getStats();

// Users for filter dropdown
$users = $db->fetchAll("SELECT id, full_name, staff_id FROM users ORDER BY full_name");

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h4><i class="bi bi-journal-text me-2"></i>Audit Logs</h4>
    <small class="text-muted">Complete activity and access log — <?= number_format($stats['total']) ?> total entries</small>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary btn-sm" onclick="exportLogs()">
      <i class="bi bi-download me-1"></i>Export CSV
    </button>
  </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card text-center py-3">
      <div class="display-6 fw-bold text-success"><?= number_format($stats['granted']) ?></div>
      <div class="small text-muted">Granted</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center py-3">
      <div class="display-6 fw-bold text-danger"><?= number_format($stats['denied']) ?></div>
      <div class="small text-muted">Denied</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center py-3">
      <div class="display-6 fw-bold text-primary"><?= number_format($stats['today']) ?></div>
      <div class="small text-muted">Today</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center py-3">
      <div class="display-6 fw-bold text-warning"><?= number_format($stats['high_risk']) ?></div>
      <div class="small text-muted">High/Critical Risk</div>
    </div>
  </div>
</div>

<!-- Filter Form -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form class="row g-2 align-items-end" method="GET">
      <div class="col-md-2">
        <label class="form-label small mb-1">User</label>
        <select class="form-select form-select-sm" name="user_id">
          <option value="">All Users</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= $filters['user_id'] == $u['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['staff_id'] . ' — ' . $u['full_name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Decision</label>
        <select class="form-select form-select-sm" name="decision">
          <option value="">All Decisions</option>
          <?php foreach (['granted','denied','conditional','info','warning','alert'] as $d): ?>
          <option <?= $filters['decision'] === $d ? 'selected' : '' ?>><?= $d ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Module</label>
        <select class="form-select form-select-sm" name="module">
          <option value="">All Modules</option>
          <?php foreach (['auth','ehr','admin','emergency','pharmacy','lab'] as $m): ?>
          <option <?= $filters['module'] === $m ? 'selected' : '' ?>><?= $m ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">From Date</label>
        <input type="date" class="form-control form-control-sm" name="date_from" value="<?= $filters['date_from'] ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">To Date</label>
        <input type="date" class="form-control form-control-sm" name="date_to" value="<?= $filters['date_to'] ?>">
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">
          <i class="bi bi-funnel me-1"></i>Filter
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Log Table -->
<div class="card">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Showing <?= count($logs) ?> entries</span>
    <select class="form-select form-select-sm w-auto" onchange="window.location.href='?limit='+this.value">
      <?php foreach ([50,100,250,500] as $l): ?>
      <option <?= $limit==$l?'selected':'' ?> value="<?= $l ?>"><?= $l ?> per page</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0" id="logTable">
        <thead>
          <tr>
            <th class="ps-3">#</th>
            <th>Timestamp</th>
            <th>User</th>
            <th>Role</th>
            <th>Action</th>
            <th>Module</th>
            <th>Patient</th>
            <th>Decision</th>
            <th>Risk</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $i => $log): ?>
          <?php
            $decBadge = ['granted'=>'success','denied'=>'danger','conditional'=>'warning','info'=>'secondary','alert'=>'danger','warning'=>'warning'][$log['decision']] ?? 'secondary';
            $rl = $log['risk_level'] ?? 'Low';
          ?>
          <tr data-bs-toggle="tooltip" title="<?= htmlspecialchars($log['details'] ?? '') ?>">
            <td class="ps-3 text-muted small"><?= $log['id'] ?></td>
            <td class="small"><?= date('d/m/y H:i:s', strtotime($log['created_at'])) ?></td>
            <td class="small">
              <span class="fw-semibold"><?= htmlspecialchars($log['staff_id'] ?? '—') ?></span><br>
              <span class="text-muted"><?= htmlspecialchars($log['full_name'] ?? 'System') ?></span>
            </td>
            <td class="small"><?= htmlspecialchars($log['role_name'] ?? '—') ?></td>
            <td class="small fw-semibold"><?= htmlspecialchars($log['action']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= $log['module'] ?></span></td>
            <td class="small"><?= $log['patient_name'] ? htmlspecialchars($log['patient_code'] . ' — ' . $log['patient_name']) : '<span class="text-muted">—</span>' ?></td>
            <td><span class="badge bg-<?= $decBadge ?>"><?= ucfirst($log['decision']) ?></span></td>
            <td>
              <?php if ($log['risk_score'] !== null): ?>
              <span class="risk-badge risk-<?= $rl ?>"><?= $rl ?> (<?= $log['risk_score'] ?>)</span>
              <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td class="small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($logs)): ?>
          <tr><td colspan="10" class="text-center py-4 text-muted">No log entries found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function exportLogs() {
    const table = document.getElementById('logTable');
    const rows = [...table.querySelectorAll('tr')];
    const csv = rows.map(row =>
        [...row.querySelectorAll('th,td')].map(cell =>
            '"' + cell.innerText.replace(/"/g,'""').replace(/\n/g,' ') + '"'
        ).join(',')
    ).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = 'ca_rbac_audit_log_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
