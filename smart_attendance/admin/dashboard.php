<?php
/**
 * admin/dashboard.php  -  MODULE 3: Dashboard
 * Cards: Total Students, Present Today, Absent Today, Attendance rate
 * Chart.js line chart: attendance of the last 7 days
 */
require_once __DIR__ . '/../config/db_connect.php';
require_login();

// ---------- 1. Card numbers ----------
$total   = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$present = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE `date` = CURDATE()")->fetchColumn();
$absent  = max(0, $total - $present);                     // absent = total - present
$rate    = $total > 0 ? round($present / $total * 100) : 0;

// ---------- 2. Last 7 days for the chart ----------
$labels = [];
$counts = [];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE `date` = ?");
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));           // 6 days ago ... today
    $stmt->execute([$day]);
    $labels[] = date('D d M', strtotime($day));           // e.g. Mon 02 Nov
    $counts[] = (int)$stmt->fetchColumn();
}

// ---------- 3. Latest check-ins today ----------
$recent = $pdo->query("SELECT s.name, s.reg_no, s.photo_path, a.time_in, a.status, a.confidence_score
                       FROM attendance a JOIN students s ON s.id = a.student_id
                       WHERE a.`date` = CURDATE()
                       ORDER BY a.time_in DESC LIMIT 8")->fetchAll();

$pageTitle = 'Dashboard';
$active    = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h1 class="h3 fw-bold mb-1">Dashboard</h1>
    <p class="text-secondary mb-0"><?= date('l, d F Y') ?></p>
  </div>
  <a href="<?= BASE_URL ?>/index.php" class="btn btn-gradient"><i class="bi bi-camera-video me-1"></i>Open Live Attendance</a>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="glass-card stat-card">
    <div class="stat-icon bg-soft-cyan"><i class="bi bi-people"></i></div>
    <div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Students</div></div>
  </div></div>
  <div class="col-sm-6 col-xl-3"><div class="glass-card stat-card">
    <div class="stat-icon bg-soft-green"><i class="bi bi-person-check"></i></div>
    <div><div class="stat-value"><?= $present ?></div><div class="stat-label">Present Today</div></div>
  </div></div>
  <div class="col-sm-6 col-xl-3"><div class="glass-card stat-card">
    <div class="stat-icon bg-soft-red"><i class="bi bi-person-x"></i></div>
    <div><div class="stat-value"><?= $absent ?></div><div class="stat-label">Absent Today</div></div>
  </div></div>
  <div class="col-sm-6 col-xl-3"><div class="glass-card stat-card">
    <div class="stat-icon bg-soft-violet"><i class="bi bi-graph-up-arrow"></i></div>
    <div><div class="stat-value"><?= $rate ?>%</div><div class="stat-label">Attendance Rate</div></div>
  </div></div>
</div>

<div class="row g-4">
  <!-- ===== CHART ===== -->
  <div class="col-lg-8">
    <div class="glass-card p-4">
      <h2 class="h6 fw-bold mb-3">Attendance - Last 7 Days</h2>
      <div style="height:320px"><canvas id="weekChart"></canvas></div>
    </div>
  </div>

  <!-- ===== RECENT CHECK-INS ===== -->
  <div class="col-lg-4">
    <div class="glass-card p-4 h-100">
      <h2 class="h6 fw-bold mb-3">Latest Check-ins Today</h2>
      <?php if (!$recent): ?>
        <p class="text-secondary small mb-0">No attendance marked yet today.</p>
      <?php endif; ?>
      <?php foreach ($recent as $r): ?>
        <div class="d-flex align-items-center gap-3 mb-3">
          <img class="avatar" src="<?= e(photo_url($r['photo_path'])) ?>" alt="">
          <div class="flex-grow-1">
            <div class="fw-semibold"><?= e($r['name']) ?></div>
            <div class="small text-secondary"><?= e($r['reg_no']) ?> &middot; <?= e($r['time_in']) ?></div>
          </div>
          <span class="badge text-bg-<?= $r['status'] === 'Late' ? 'warning' : 'success' ?>"><?= e($r['status']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Chart.js library (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  // Data comes from PHP (json_encode turns PHP arrays into JavaScript arrays)
  const labels = <?= json_encode($labels) ?>;   // x axis: dates
  const counts = <?= json_encode($counts) ?>;   // y axis: students present

  Chart.defaults.color = '#9aa4bf';             // light text for the dark theme
  new Chart(document.getElementById('weekChart'), {
    type: 'line',                               // line chart
    data: {
      labels: labels,
      datasets: [{
        label: 'Students present',
        data: counts,
        borderColor: '#22d3ee',                 // line colour
        backgroundColor: 'rgba(34, 211, 238, 0.15)', // area under the line
        fill: true, tension: 0.35,              // smooth curve
        pointBackgroundColor: '#8b5cf6', pointRadius: 5
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(255,255,255,.07)' } },
        x: { grid: { display: false } }
      }
    }
  });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
