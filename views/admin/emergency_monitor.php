<?php
// =====================================================
// CA-RBAC HIS — Emergency Monitor (Admin Only)
// =====================================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

$auth = new AuthController();
$auth->requireRole('admin');
$db = Database::getInstance();
$pageTitle = 'Emergency Monitor — CA-RBAC HIS';

// All emergencies with full context
$emergencies = $db->fetchAll(
    "SELECT pe.*,
            p.full_name AS patient_name, p.patient_id AS pid, p.gender,
            p.blood_group, p.admission_status,
            u.full_name AS claimed_by_name, u.staff_id AS claimed_by_sid,
            u.specialization,
            -- Who declared it (nurse or patient)
            CASE
                WHEN pe.symptoms LIKE '%[NURSE]%' THEN 'Nurse'
                ELSE 'Patient'
            END AS declared_by_type,
            -- Time active in minutes
            TIMESTAMPDIFF(MINUTE, pe.created_at, NOW()) AS minutes_active,
            -- Time since resolved
            TIMESTAMPDIFF(MINUTE, pe.resolved_at, NOW()) AS minutes_since_resolved
     FROM patient_emergencies pe
     JOIN patients p ON pe.patient_id = p.id
     LEFT JOIN users u ON pe.claimed_by = u.id
     ORDER BY
       FIELD(pe.status, 'active', 'claimed', 'resolved'),
       pe.created_at DESC"
);

// Stats
$totalActive   = count(array_filter($emergencies, fn($e) => $e['status'] === 'active'));
$totalClaimed  = count(array_filter($emergencies, fn($e) => $e['status'] === 'claimed'));
$totalResolved = count(array_filter($emergencies, fn($e) => $e['status'] === 'resolved'));
$total         = count($emergencies);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1><i class="bi bi-heart-pulse-fill" style="color:var(--red);margin-right:8px"></i>Emergency Monitor</h1>
    <p>Real-time view of all patient emergencies — all time history</p>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <span id="liveClock" style="font-size:13px;color:var(--gray-500);font-weight:500"></span>
    <button onclick="location.reload()" class="btn btn-outline btn-sm">
      <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card stat-red">
    <div class="stat-top">
      <div><div class="stat-label">Active</div><div class="stat-value"><?= $totalActive ?></div></div>
      <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
    </div>
    <div style="font-size:12px;color:var(--gray-500)">Awaiting doctor response</div>
  </div>
  <div class="stat-card stat-amber">
    <div class="stat-top">
      <div><div class="stat-label">Claimed</div><div class="stat-value"><?= $totalClaimed ?></div></div>
      <div class="stat-icon"><i class="bi bi-person-check"></i></div>
    </div>
    <div style="font-size:12px;color:var(--gray-500)">Doctor responding</div>
  </div>
  <div class="stat-card stat-green">
    <div class="stat-top">
      <div><div class="stat-label">Resolved</div><div class="stat-value"><?= $totalResolved ?></div></div>
      <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
    </div>
    <div style="font-size:12px;color:var(--gray-500)">Successfully handled</div>
  </div>
  <div class="stat-card stat-blue">
    <div class="stat-top">
      <div><div class="stat-label">Total All Time</div><div class="stat-value"><?= $total ?></div></div>
      <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
    </div>
  </div>
</div>

<!-- Active Emergencies Alert -->
<?php if ($totalActive > 0): ?>
<div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);border-left:4px solid var(--red);border-radius:10px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px">
  <i class="bi bi-exclamation-triangle-fill" style="color:var(--red);font-size:20px;flex-shrink:0;animation:pulse 1.5s infinite"></i>
  <div>
    <strong style="color:#991b1b"><?= $totalActive ?> active emergency<?= $totalActive>1?'ies':'' ?> awaiting doctor response.</strong>
    <span style="color:#b91c1c;font-size:13px"> All available doctors have been notified.</span>
  </div>
</div>
<?php endif; ?>

<!-- Emergency Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><i class="bi bi-table"></i>All Emergencies</div>
    <div style="display:flex;gap:8px">
      <button onclick="filterTable('all')"      class="btn btn-outline btn-sm filter-btn active" data-filter="all">All</button>
      <button onclick="filterTable('active')"   class="btn btn-outline btn-sm filter-btn" data-filter="active" style="color:var(--red)">Active</button>
      <button onclick="filterTable('claimed')"  class="btn btn-outline btn-sm filter-btn" data-filter="claimed" style="color:var(--amber)">Claimed</button>
      <button onclick="filterTable('resolved')" class="btn btn-outline btn-sm filter-btn" data-filter="resolved" style="color:var(--green)">Resolved</button>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($emergencies)): ?>
    <div style="text-align:center;padding:60px;color:var(--gray-400)">
      <i class="bi bi-heart-pulse" style="font-size:3rem;display:block;margin-bottom:12px;color:var(--green)"></i>
      <div style="font-weight:600;font-size:15px">No emergencies recorded</div>
      <div style="font-size:13px;margin-top:4px">All clear — no emergency history found</div>
    </div>
    <?php else: ?>
    <table id="emergencyTable">
      <thead>
        <tr>
          <th style="padding-left:20px">Patient</th>
          <th>Declared By</th>
          <th>Symptoms</th>
          <th>Severity</th>
          <th>Status</th>
          <th>Claimed By</th>
          <th>Duration</th>
          <th>Declared At</th>
          <th>Resolution</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($emergencies as $e):
          $statusBadge = [
            'active'   => ['badge-red',   'Active'],
            'claimed'  => ['badge-amber', 'Claimed'],
            'resolved' => ['badge-green', 'Resolved'],
          ][$e['status']] ?? ['badge-gray','Unknown'];

          $sevColor = [
            'Critical' => '#dc2626',
            'Severe'   => '#ea580c',
            'Moderate' => '#d97706',
            'Mild'     => '#16a34a',
          ][$e['severity']] ?? 'var(--gray-500)';

          // Format duration
          $mins = (int)$e['minutes_active'];
          if ($mins < 60) {
              $duration = $mins . ' min' . ($mins !== 1 ? 's' : '');
          } elseif ($mins < 1440) {
              $duration = round($mins/60, 1) . ' hrs';
          } else {
              $duration = round($mins/1440, 1) . ' days';
          }
        ?>
        <tr class="emrg-row" data-status="<?= $e['status'] ?>">
          <td style="padding-left:20px">
            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($e['patient_name']) ?></div>
            <div style="font-size:11px;color:var(--gray-500)"><?= htmlspecialchars($e['pid']) ?></div>
            <div style="font-size:11px;color:var(--gray-400)"><?= $e['gender'] ?> · <span style="color:var(--red);font-weight:600"><?= $e['blood_group'] ?></span></div>
          </td>
          <td>
            <span style="background:<?= $e['declared_by_type']==='Nurse'?'#dbeafe':'#dcfce7' ?>;color:<?= $e['declared_by_type']==='Nurse'?'#1d4ed8':'#166534' ?>;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600">
              <i class="bi bi-<?= $e['declared_by_type']==='Nurse'?'person-badge':'person-heart' ?>"></i>
              <?= $e['declared_by_type'] ?>
            </span>
          </td>
          <td style="max-width:200px">
            <div style="font-size:13px;color:var(--gray-700);line-height:1.4"><?= htmlspecialchars(substr(str_replace('[NURSE] ','',$e['symptoms']),0,80)) ?><?= strlen($e['symptoms'])>80?'...':'' ?></div>
          </td>
          <td>
            <span style="background:<?= $sevColor ?>22;color:<?= $sevColor ?>;border-radius:20px;padding:3px 10px;font-size:12px;font-weight:600">
              <?= $e['severity'] ?>
            </span>
          </td>
          <td><span class="badge <?= $statusBadge[0] ?>"><?= $statusBadge[1] ?></span></td>
          <td>
            <?php if ($e['claimed_by_name']): ?>
            <div style="font-size:13px;font-weight:600"><?= htmlspecialchars($e['claimed_by_name']) ?></div>
            <div style="font-size:11px;color:var(--gray-500)"><?= htmlspecialchars($e['claimed_by_sid']) ?></div>
            <?php if ($e['specialization']): ?>
            <div style="font-size:11px;color:var(--teal)"><?= htmlspecialchars($e['specialization']) ?></div>
            <?php endif; ?>
            <?php else: ?>
            <span style="color:var(--gray-400);font-size:12px">Not yet claimed</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($e['status'] === 'active' || $e['status'] === 'claimed'): ?>
            <span class="live-timer" data-start="<?= $e['created_at'] ?>"
                  style="font-weight:600;font-size:13px;color:<?= $e['status']==='active'?'var(--red)':'var(--amber)' ?>">
              <?= $duration ?>
            </span>
            <?php else: ?>
            <span style="font-size:13px;color:var(--gray-500)"><?= $duration ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--gray-500);white-space:nowrap">
            <?= date('d M Y', strtotime($e['created_at'])) ?><br>
            <span style="font-size:11px"><?= date('H:i', strtotime($e['created_at'])) ?></span>
          </td>
          <td style="max-width:180px">
            <?php if ($e['status'] === 'resolved'): ?>
            <div style="font-size:12px;color:var(--green);font-weight:600;margin-bottom:2px">
              <i class="bi bi-check-circle-fill"></i> Resolved
            </div>
            <?php if ($e['resolved_at']): ?>
            <div style="font-size:11px;color:var(--gray-400)"><?= date('d M Y H:i',strtotime($e['resolved_at'])) ?></div>
            <?php endif; ?>
            <?php if ($e['resolution_notes']): ?>
            <div style="font-size:11px;color:var(--gray-600);margin-top:3px;line-height:1.4"><?= htmlspecialchars(substr($e['resolution_notes'],0,60)) ?></div>
            <?php endif; ?>
            <?php else: ?>
            <span style="color:var(--gray-400);font-size:12px">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<style>
@keyframes pulse {0%,100%{opacity:1}50%{opacity:0.4}}
.filter-btn.active{background:var(--navy);color:white;border-color:var(--navy)}
</style>

<script>
// Live clock
function updateClock() {
    const now = new Date();
    document.getElementById('liveClock').textContent =
        now.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) + ' ' +
        now.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
updateClock();
setInterval(updateClock, 1000);

// Live timers for active/claimed emergencies
function updateTimers() {
    document.querySelectorAll('.live-timer').forEach(function(el) {
        const start = new Date(el.dataset.start.replace(' ','T'));
        const now   = new Date();
        const mins  = Math.floor((now - start) / 60000);
        if (mins < 60) {
            el.textContent = mins + ' min' + (mins !== 1 ? 's' : '');
        } else if (mins < 1440) {
            el.textContent = (mins/60).toFixed(1) + ' hrs';
        } else {
            el.textContent = (mins/1440).toFixed(1) + ' days';
        }
    });
}
updateTimers();
setInterval(updateTimers, 30000);

// Filter table
function filterTable(status) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-filter="'+status+'"]').classList.add('active');
    document.querySelectorAll('.emrg-row').forEach(function(row) {
        row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
}

// Auto refresh every 60 seconds
setTimeout(function() { location.reload(); }, 60000);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
