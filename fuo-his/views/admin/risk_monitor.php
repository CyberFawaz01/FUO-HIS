<?php
// =====================================================
// CA-RBAC HIS — Risk Monitor (Admin)
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/RiskEngine.php';

$auth = new AuthController();
$auth->requireRole('admin');
$db   = Database::getInstance();
$risk = new RiskEngine();
$pageTitle = 'Risk Monitor — CA-RBAC HIS';

// Hourly distribution (last 24h)
$hourly = $db->fetchAll(
    "SELECT HOUR(created_at) h, risk_level, COUNT(*) cnt
     FROM audit_logs
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND risk_level IS NOT NULL
     GROUP BY h, risk_level ORDER BY h"
);

// Top high-risk users (7 days)
$topRiskUsers = $db->fetchAll(
    "SELECT u.full_name, u.staff_id, r.role_name,
            ROUND(AVG(al.risk_score),1) avg_score,
            COUNT(al.id) total_events,
            SUM(al.decision='denied') denied_count
     FROM audit_logs al
     JOIN users u ON al.user_id = u.id
     JOIN roles r ON u.role_id = r.id
     WHERE al.created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND al.risk_score IS NOT NULL
     GROUP BY u.id ORDER BY avg_score DESC LIMIT 10"
);

// Recent high-risk events
$highRiskEvents = $db->fetchAll(
    "SELECT al.*, u.full_name, u.staff_id, r.role_name, p.full_name AS patient_name
     FROM audit_logs al
     LEFT JOIN users u ON al.user_id=u.id
     LEFT JOIN roles r ON u.role_id=r.id
     LEFT JOIN patients p ON al.patient_id=p.id
     WHERE al.risk_level IN ('High','Critical')
     ORDER BY al.created_at DESC LIMIT 20"
);

// Access request breakdown (last 7 days)
$requestBreakdown = $db->fetchAll(
    "SELECT
        SUM(decision='granted') granted,
        SUM(decision='denied') denied,
        SUM(decision='conditional') conditional,
        SUM(is_emergency=1) emergency,
        SUM(is_trusted_network=0) untrusted_net,
        SUM(is_on_shift=0) off_shift
     FROM access_requests
     WHERE requested_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$breakdown = $requestBreakdown[0] ?? [];

// Risk score histogram
$histogram = $db->fetchAll(
    "SELECT
        CASE
          WHEN risk_score BETWEEN 0  AND 20  THEN '0-20 (Low)'
          WHEN risk_score BETWEEN 21 AND 50  THEN '21-50 (Medium)'
          WHEN risk_score BETWEEN 51 AND 80  THEN '51-80 (High)'
          ELSE '81+ (Critical)'
        END as bucket,
        COUNT(*) cnt
     FROM audit_logs
     WHERE risk_score IS NOT NULL AND created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)
     GROUP BY bucket ORDER BY MIN(risk_score)"
);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h4><i class="bi bi-graph-up-arrow me-2 text-danger"></i>Risk Monitor</h4>
    <small class="text-muted">Real-time CA-RBAC risk analysis and threat detection</small>
  </div>
  <span class="badge bg-success fs-6"><i class="bi bi-shield-check me-1"></i>Monitoring Active</span>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="text-muted mb-3">Access Decisions (7 Days)</h6>
        <div class="d-flex justify-content-around text-center">
          <div><div class="fs-4 fw-bold text-success"><?= $breakdown['granted'] ?? 0 ?></div><div class="small text-muted">Granted</div></div>
          <div><div class="fs-4 fw-bold text-danger"><?= $breakdown['denied'] ?? 0 ?></div><div class="small text-muted">Denied</div></div>
          <div><div class="fs-4 fw-bold text-warning"><?= $breakdown['conditional'] ?? 0 ?></div><div class="small text-muted">Conditional</div></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="text-muted mb-3">Context Violations (7 Days)</h6>
        <div class="d-flex justify-content-around text-center">
          <div><div class="fs-4 fw-bold text-warning"><?= $breakdown['untrusted_net'] ?? 0 ?></div><div class="small text-muted">Untrusted Net</div></div>
          <div><div class="fs-4 fw-bold text-danger"><?= $breakdown['off_shift'] ?? 0 ?></div><div class="small text-muted">Off-Shift</div></div>
          <div><div class="fs-4 fw-bold text-dark"><?= $breakdown['emergency'] ?? 0 ?></div><div class="small text-muted">Emergency</div></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <h6 class="text-muted mb-3">Score Threshold Guide</h6>
        <div class="small">
          <div class="d-flex justify-content-between mb-1"><span class="risk-badge risk-Low">Low</span><span>0 – 20 → Auto-grant</span></div>
          <div class="d-flex justify-content-between mb-1"><span class="risk-badge risk-Medium">Medium</span><span>21 – 50 → Conditional</span></div>
          <div class="d-flex justify-content-between mb-1"><span class="risk-badge risk-High">High</span><span>51 – 80 → Override needed</span></div>
          <div class="d-flex justify-content-between"><span class="risk-badge risk-Critical">Critical</span><span>81+ → Auto-deny</span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
  <!-- Hourly Heatmap Line Chart -->
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold border-0">
        <i class="bi bi-clock me-2 text-primary"></i>Hourly Risk Events (Last 24h)
      </div>
      <div class="card-body">
        <div class="chart-wrapper"><canvas id="hourlyChart"></canvas></div>
      </div>
    </div>
  </div>

  <!-- Histogram -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold border-0">
        <i class="bi bi-bar-chart-steps me-2 text-primary"></i>Score Distribution (7 Days)
      </div>
      <div class="card-body">
        <div class="chart-wrapper"><canvas id="histChart"></canvas></div>
      </div>
    </div>
  </div>
</div>

<!-- Top Risk Users + Recent High-Risk Events -->
<div class="row g-3">
  <!-- Top Risk Users -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold border-0">
        <i class="bi bi-person-exclamation me-2 text-danger"></i>Top Risk Users (7 Days)
      </div>
      <div class="card-body p-0">
        <table class="table table-hover table-sm mb-0">
          <thead>
            <tr>
              <th class="ps-3">#</th>
              <th>Staff</th>
              <th>Role</th>
              <th>Avg Score</th>
              <th>Events</th>
              <th>Denied</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topRiskUsers as $i => $u): ?>
            <?php $lvl = $risk->getRiskLevel($u['avg_score']); ?>
            <tr>
              <td class="ps-3 text-muted small"><?= $i+1 ?></td>
              <td class="small">
                <span class="fw-semibold"><?= htmlspecialchars($u['staff_id']) ?></span><br>
                <span class="text-muted"><?= htmlspecialchars($u['full_name']) ?></span>
              </td>
              <td class="small"><?= $u['role_name'] ?></td>
              <td><span class="risk-badge risk-<?= $lvl ?>"><?= $u['avg_score'] ?></span></td>
              <td class="small"><?= $u['total_events'] ?></td>
              <td class="small text-danger fw-bold"><?= $u['denied_count'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($topRiskUsers)): ?>
            <tr><td colspan="6" class="text-center py-3 text-muted">No data</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Recent High-Risk Events -->
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold border-0">
        <i class="bi bi-exclamation-octagon me-2 text-danger"></i>Recent High/Critical Risk Events
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0">
            <thead>
              <tr>
                <th class="ps-3">Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Risk</th>
                <th>Decision</th>
                <th>IP</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($highRiskEvents as $e): ?>
              <?php $rl = $e['risk_level'] ?? 'High'; ?>
              <tr>
                <td class="ps-3 small text-muted"><?= date('d/m H:i', strtotime($e['created_at'])) ?></td>
                <td class="small">
                  <?= htmlspecialchars($e['staff_id'] ?? '?') ?><br>
                  <span class="text-muted"><?= htmlspecialchars($e['role_name'] ?? '') ?></span>
                </td>
                <td class="small fw-semibold"><?= htmlspecialchars($e['action']) ?></td>
                <td><span class="risk-badge risk-<?= $rl ?>"><?= $rl ?> (<?= $e['risk_score'] ?>)</span></td>
                <td>
                  <span class="badge bg-<?= $e['decision']==='granted' ? 'success' : ($e['decision']==='denied' ? 'danger' : 'warning') ?>">
                    <?= ucfirst($e['decision']) ?>
                  </span>
                </td>
                <td class="small text-muted"><?= htmlspecialchars($e['ip_address'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($highRiskEvents)): ?>
              <tr><td colspan="6" class="text-center py-3 text-muted">No high-risk events recorded.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Build hourly data
const hours = Array.from({length:24},(_,i)=>i);
const hourlyRaw = <?= json_encode($hourly) ?>;
const levels = ['Low','Medium','High','Critical'];
const colors  = {'Low':'#198754','Medium':'#ffc107','High':'#dc3545','Critical':'#1a1a2e'};

const datasets = levels.map(lvl => ({
    label: lvl,
    data: hours.map(h => {
        const row = hourlyRaw.find(r => parseInt(r.h)===h && r.risk_level===lvl);
        return row ? parseInt(row.cnt) : 0;
    }),
    borderColor: colors[lvl],
    backgroundColor: colors[lvl]+'33',
    tension: 0.3, fill: true
}));

new Chart(document.getElementById('hourlyChart'), {
    type: 'line',
    data: { labels: hours.map(h => h+':00'), datasets },
    options: { responsive:true, maintainAspectRatio:false,
        scales: { y: { beginAtZero:true, stacked:false } },
        plugins: { legend: { position:'top' } } }
});

// Histogram
const histData = <?= json_encode($histogram) ?>;
new Chart(document.getElementById('histChart'), {
    type: 'bar',
    data: {
        labels: histData.map(h=>h.bucket),
        datasets: [{ data: histData.map(h=>h.cnt),
            backgroundColor:['#d1fae5','#fef9c3','#fee2e2','#1a1a2e'],
            borderRadius:6 }]
    },
    options: { responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{display:false} },
        scales:{ y:{beginAtZero:true} } }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
