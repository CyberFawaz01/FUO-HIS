<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../includes/AuditLogger.php';
require_once __DIR__ . '/../../includes/RiskEngine.php';

$auth = new AuthController();
$auth->requireRole('admin');
$db    = Database::getInstance();
$audit = new AuditLogger();
$pageTitle = 'Admin Dashboard — CA-RBAC HIS';

$totalUsers      = $db->fetchOne("SELECT COUNT(*) c FROM users")['c'];
$totalPatients   = $db->fetchOne("SELECT COUNT(*) c FROM patients")['c'];
$emergencyPts    = $db->fetchOne("SELECT COUNT(*) c FROM patient_emergencies WHERE status IN ('active','claimed')")['c'];
$todayLogins     = $db->fetchOne("SELECT COUNT(*) c FROM audit_logs WHERE action='LOGIN_SUCCESS' AND DATE(created_at)=CURDATE()")['c'];
$todayDenied     = $db->fetchOne("SELECT COUNT(*) c FROM audit_logs WHERE decision='denied' AND DATE(created_at)=CURDATE()")['c'];
$activeOverrides = $db->fetchOne("SELECT COUNT(*) c FROM emergency_access WHERE is_expired=0 AND expires_at>NOW()")['c'];
$highRiskEvents  = $db->fetchOne("SELECT COUNT(*) c FROM audit_logs WHERE risk_level IN ('High','Critical') AND DATE(created_at)=CURDATE()")['c'];
$lockedUsers     = $db->fetchAll("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id=r.id WHERE u.is_locked=1");

// Chart data - 7 day trend
$dailyTrend = $db->fetchAll(
    "SELECT DATE(created_at) d, COUNT(*) cnt,
            SUM(decision='granted') granted,
            SUM(decision='denied') denied
     FROM audit_logs
     WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)
     GROUP BY DATE(created_at) ORDER BY d"
);

// Decision breakdown
$decisionData = $db->fetchAll(
    "SELECT decision, COUNT(*) cnt FROM audit_logs
     WHERE DATE(created_at) >= DATE_SUB(CURDATE(),INTERVAL 7 DAY) AND decision IN ('granted','denied','conditional')
     GROUP BY decision"
);

// Risk distribution 24h
$riskDist = $db->fetchAll(
    "SELECT risk_level, COUNT(*) cnt FROM audit_logs
     WHERE created_at >= DATE_SUB(NOW(),INTERVAL 24 HOUR) AND risk_level IS NOT NULL
     GROUP BY risk_level"
);
$riskMap = [];
foreach ($riskDist as $r) $riskMap[$r['risk_level']] = $r['cnt'];

// Recent logs
$recentLogs = $audit->getLogs(10);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-speedometer2" style="color:var(--teal);margin-right:8px"></i>Admin Dashboard</h1>
    <p>Real-time system overview — <span id="live-clock"></span></p>
  </div>
  <span class="badge badge-green" style="padding:8px 14px;font-size:13px"><i class="bi bi-circle-fill" style="font-size:8px"></i> System Active</span>
</div>

<!-- Alerts -->
<?php if ($lockedUsers): ?>
<div class="alert alert-danger">
  <i class="bi bi-lock-fill" style="flex-shrink:0"></i>
  <div><strong><?= count($lockedUsers) ?> account(s) locked</strong> — <a href="<?= APP_URL ?>/views/admin/users.php" style="color:inherit;font-weight:700">Manage Users →</a></div>
</div>
<?php endif; ?>

<?php if ($activeOverrides > 0): ?>
<div class="alert alert-warning">
  <i class="bi bi-shield-exclamation" style="flex-shrink:0"></i>
  <div><strong><?= $activeOverrides ?> active emergency override(s)</strong> — <a href="<?= APP_URL ?>/views/shared/emergency.php" style="color:inherit;font-weight:700">Review →</a></div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card stat-blue">
    <div class="stat-top">
      <div><div class="stat-label">Total Staff</div><div class="stat-value"><?= $totalUsers ?></div></div>
      <div class="stat-icon"><i class="bi bi-people"></i></div>
    </div>
    <a href="<?= APP_URL ?>/views/admin/users.php" style="font-size:12px;color:var(--blue);text-decoration:none;font-weight:600">Manage →</a>
  </div>
  <div class="stat-card stat-teal">
    <div class="stat-top">
      <div><div class="stat-label">Total Patients</div><div class="stat-value"><?= $totalPatients ?></div></div>
      <div class="stat-icon"><i class="bi bi-person-lines-fill"></i></div>
    </div>
    <div style="font-size:12px;color:var(--gray-500)"><?= $emergencyPts ?> active emergencies</div>
  </div>
  <div class="stat-card stat-green">
    <div class="stat-top">
      <div><div class="stat-label">Logins Today</div><div class="stat-value"><?= $todayLogins ?></div></div>
      <div class="stat-icon"><i class="bi bi-box-arrow-in-right"></i></div>
    </div>
    <div style="font-size:12px;color:var(--gray-500)"><?= $todayDenied ?> denied</div>
  </div>
  <div class="stat-card stat-red">
    <div class="stat-top">
      <div><div class="stat-label">High Risk Events</div><div class="stat-value"><?= $highRiskEvents ?></div></div>
      <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
    </div>
    <a href="<?= APP_URL ?>/views/admin/risk_monitor.php" style="font-size:12px;color:var(--red);text-decoration:none;font-weight:600">Monitor →</a>
  </div>
  <div class="stat-card stat-amber">
    <div class="stat-top">
      <div><div class="stat-label">Active Emergencies</div><div class="stat-value"><?= $emergencyPts ?></div></div>
      <div class="stat-icon"><i class="bi bi-heart-pulse"></i></div>
    </div>
    <a href="<?= APP_URL ?>/views/shared/emergencies.php" style="font-size:12px;color:var(--amber);text-decoration:none;font-weight:600">View All →</a>
  </div>
  <div class="stat-card stat-purple">
    <div class="stat-top">
      <div><div class="stat-label">Override Sessions</div><div class="stat-value"><?= $activeOverrides ?></div></div>
      <div class="stat-icon"><i class="bi bi-shield-exclamation"></i></div>
    </div>
    <a href="<?= APP_URL ?>/views/shared/emergency.php" style="font-size:12px;color:var(--purple);text-decoration:none;font-weight:600">Review →</a>
  </div>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px">
  <!-- Trend chart -->
  <div class="card" style="grid-column:span 2">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-graph-up"></i>Access Events — 7 Days</div>
    </div>
    <div class="card-body">
      <div class="chart-wrapper"><canvas id="trendChart"></canvas></div>
    </div>
  </div>

  <!-- Decision pie -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-pie-chart"></i>Decisions (7 Days)</div>
    </div>
    <div class="card-body">
      <div class="chart-wrapper"><canvas id="decisionChart"></canvas></div>
    </div>
  </div>
</div>

<!-- Risk distribution + recent activity -->
<div style="display:grid;grid-template-columns:280px 1fr;gap:16px">
  <!-- Risk -->
  <div class="card">
    <div class="card-header"><div class="card-title"><i class="bi bi-speedometer"></i>Risk (24h)</div></div>
    <div class="card-body">
      <?php foreach (['Low','Medium','High','Critical'] as $lvl): ?>
      <?php $cnt=$riskMap[$lvl]??0; $total=max(1,array_sum($riskMap)); $pct=round($cnt/$total*100);
            $colors=['Low'=>'var(--green)','Medium'=>'var(--amber)','High'=>'var(--red)','Critical'=>'var(--navy)']; ?>
      <div style="margin-bottom:14px">
        <div class="d-flex justify-between align-center mb-1" style="justify-content:space-between">
          <span class="badge risk-<?= $lvl ?>"><?= $lvl ?></span>
          <span style="font-size:12px;color:var(--gray-500)"><?= $cnt ?> (<?= $pct ?>%)</span>
        </div>
        <div style="height:6px;background:var(--gray-100);border-radius:3px;overflow:hidden">
          <div style="height:100%;width:<?= $pct ?>%;background:<?= $colors[$lvl] ?>;border-radius:3px;transition:.5s"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <a href="<?= APP_URL ?>/views/admin/risk_monitor.php" class="btn btn-outline w-100" style="margin-top:8px;width:100%">
        <i class="bi bi-graph-up-arrow"></i> Full Monitor
      </a>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="bi bi-activity"></i>Recent Activity</div>
      <a href="<?= APP_URL ?>/views/admin/audit_logs.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding:0">
      <table>
        <thead>
          <tr>
            <th style="padding-left:20px">Time</th>
            <th>User</th>
            <th>Action</th>
            <th>Decision</th>
            <th>Risk</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentLogs as $log): ?>
          <?php $decBadge=['granted'=>'badge-green','denied'=>'badge-red','conditional'=>'badge-amber','info'=>'badge-gray','alert'=>'badge-red'][$log['decision']]??'badge-gray'; ?>
          <tr onclick="void(0)" style="cursor:default">
            <td style="padding-left:20px;font-size:12px;color:var(--gray-500);white-space:nowrap"><?= date('H:i:s',strtotime($log['created_at'])) ?></td>
            <td>
              <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($log['full_name']??'System') ?></div>
              <div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($log['role_name']??'') ?></div>
            </td>
            <td style="font-size:12px;font-weight:600;color:var(--gray-700)"><?= htmlspecialchars($log['action']) ?></td>
            <td><span class="badge <?= $decBadge ?>"><?= ucfirst($log['decision']) ?></span></td>
            <td>
              <?php if ($log['risk_score']!==null): ?>
              <span class="risk-badge risk-<?= $log['risk_level']??'Low' ?>"><?= $log['risk_level']??'Low' ?> (<?= $log['risk_score'] ?>)</span>
              <?php else: ?><span style="color:var(--gray-400)">—</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
const trendData  = <?= json_encode($dailyTrend) ?>;
const decData    = <?= json_encode($decisionData) ?>;

// Trend Chart
new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: trendData.map(r => r.d),
    datasets: [
      { label:'Total', data:trendData.map(r=>r.cnt), borderColor:'#0b1f3a', backgroundColor:'rgba(11,31,58,0.06)', tension:0.4, fill:true, borderWidth:2 },
      { label:'Granted', data:trendData.map(r=>r.granted), borderColor:'#10b981', backgroundColor:'transparent', tension:0.4, borderWidth:2 },
      { label:'Denied', data:trendData.map(r=>r.denied), borderColor:'#ef4444', backgroundColor:'transparent', tension:0.4, borderWidth:2 },
    ]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ position:'top', labels:{ font:{ size:12 }, padding:16 } } },
    scales:{ y:{ beginAtZero:true, grid:{ color:'rgba(0,0,0,0.04)' } }, x:{ grid:{ display:false } } }
  }
});

// Decision Chart
const dLabels = decData.map(r=>r.decision.charAt(0).toUpperCase()+r.decision.slice(1));
const dValues = decData.map(r=>r.cnt);
new Chart(document.getElementById('decisionChart'), {
  type: 'doughnut',
  data: {
    labels: dLabels,
    datasets:[{ data:dValues, backgroundColor:['#10b981','#ef4444','#f59e0b'], borderWidth:3, borderColor:'#fff' }]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ position:'bottom', labels:{ font:{ size:12 }, padding:12 } } },
    cutout:'65%'
  }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
