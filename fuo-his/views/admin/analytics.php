<?php
// =====================================================
// CA-RBAC HIS — Analytics (Admin)
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
$auth = new AuthController(); $auth->requireRole('admin');
$db = Database::getInstance();
$pageTitle = 'Analytics — CA-RBAC HIS';

// 30-day trend
$trend30 = $db->fetchAll("SELECT DATE(created_at) d, COUNT(*) cnt, SUM(decision='granted') granted, SUM(decision='denied') denied FROM audit_logs WHERE created_at >= DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY d");

// Action type breakdown
$actionBreakdown = $db->fetchAll("SELECT action, COUNT(*) cnt FROM audit_logs GROUP BY action ORDER BY cnt DESC LIMIT 10");

// Role activity
$roleActivity = $db->fetchAll("SELECT r.display_name, COUNT(al.id) cnt FROM roles r LEFT JOIN users u ON u.role_id=r.id LEFT JOIN audit_logs al ON al.user_id=u.id WHERE al.created_at >= DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY r.id,r.display_name ORDER BY cnt DESC");

// Patient sensitivity distribution
$sensDist = $db->fetchAll("SELECT sensitivity_level, COUNT(*) cnt FROM patients GROUP BY sensitivity_level");

// Emergency overrides over time
$emergencyTrend = $db->fetchAll("SELECT DATE(created_at) d, COUNT(*) cnt FROM emergency_access WHERE created_at >= DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY d");

// Department patient counts
$deptPts = $db->fetchAll("SELECT d.dept_name, COUNT(p.id) cnt FROM departments d LEFT JOIN patients p ON p.department_id=d.id GROUP BY d.id,d.dept_name ORDER BY cnt DESC");

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div><h4><i class="bi bi-bar-chart me-2"></i>Analytics</h4><small class="text-muted">System-wide statistics — last 30 days</small></div>
</div>

<div class="row g-3 mb-4">
  <!-- 30-day Access Trend -->
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-graph-up me-2 text-primary"></i>Access Events — 30 Days</div>
      <div class="card-body"><div class="chart-wrapper"><canvas id="trend30Chart"></canvas></div></div>
    </div>
  </div>

  <!-- Sensitivity Distribution -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-2 text-primary"></i>Patient Sensitivity</div>
      <div class="card-body"><div class="chart-wrapper"><canvas id="sensChart"></canvas></div></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Action Breakdown -->
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-list-columns me-2 text-primary"></i>Top Action Types</div>
      <div class="card-body"><div class="chart-wrapper"><canvas id="actionChart"></canvas></div></div>
    </div>
  </div>

  <!-- Role Activity -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-2 text-primary"></i>Activity by Role</div>
      <div class="card-body"><div class="chart-wrapper"><canvas id="roleChart"></canvas></div></div>
    </div>
  </div>

  <!-- Dept Patient Distribution -->
  <div class="col-lg-3">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-building me-2 text-primary"></i>Patients by Dept</div>
      <div class="card-body">
        <?php foreach ($deptPts as $dp): ?>
        <div class="d-flex justify-content-between small mb-1">
          <span><?= htmlspecialchars($dp['dept_name']) ?></span>
          <span class="badge bg-primary"><?= $dp['cnt'] ?></span>
        </div>
        <div class="progress mb-2" style="height:5px">
          <div class="progress-bar bg-primary" style="width:<?= $dp['cnt'] > 0 ? min(100,($dp['cnt']/max(array_column($deptPts,'cnt')))*100) : 0 ?>%"></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
// 30-day trend
const t30 = <?= json_encode($trend30) ?>;
new Chart(document.getElementById('trend30Chart'), {
    type:'line',
    data:{
        labels: t30.map(r=>r.d),
        datasets:[
            {label:'Total',data:t30.map(r=>r.cnt),borderColor:'#0a3d6b',backgroundColor:'rgba(10,61,107,0.08)',tension:0.3,fill:true},
            {label:'Granted',data:t30.map(r=>r.granted),borderColor:'#198754',tension:0.3},
            {label:'Denied',data:t30.map(r=>r.denied),borderColor:'#dc3545',tension:0.3},
        ]
    },
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true}}}
});

// Sensitivity pie
const sd = <?= json_encode($sensDist) ?>;
new Chart(document.getElementById('sensChart'), {
    type:'doughnut',
    data:{
        labels:sd.map(r=>r.sensitivity_level),
        datasets:[{data:sd.map(r=>r.cnt),backgroundColor:['#198754','#ffc107','#dc3545','#1a1a2e']}]
    },
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}
});

// Actions bar
const ab = <?= json_encode($actionBreakdown) ?>;
new Chart(document.getElementById('actionChart'), {
    type:'bar',
    data:{labels:ab.map(r=>r.action),datasets:[{data:ab.map(r=>r.cnt),backgroundColor:'#1a5fa8',borderRadius:4}]},
    options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true}}}
});

// Role activity
const ra = <?= json_encode($roleActivity) ?>;
new Chart(document.getElementById('roleChart'), {
    type:'bar',
    data:{labels:ra.map(r=>r.display_name),datasets:[{data:ra.map(r=>r.cnt),backgroundColor:'#00b4d8',borderRadius:4}]},
    options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true}}}
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
