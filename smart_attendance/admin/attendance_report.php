<?php
/**
 * admin/attendance_report.php  -  MODULE 3: Attendance Report
 * Date filter (from - to), table with photo / time in / confidence %,
 * and "Export CSV" button (PHP sends a CSV file using header()).
 */
require_once __DIR__ . '/../config/db_connect.php';
require_login();

// Check that a text is a real date like 2026-11-03
function valid_date(string $d): bool
{
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

// ---------- 1. Read the filter (default = today) ----------
$from = $_GET['from'] ?? date('Y-m-d');
$to   = $_GET['to']   ?? $from;
if (!valid_date($from)) $from = date('Y-m-d');
if (!valid_date($to))   $to   = $from;
if ($to < $from) { [$from, $to] = [$to, $from]; }          // swap if the user entered them backwards

// ---------- 2. Get the attendance rows ----------
$stmt = $pdo->prepare("SELECT a.`date`, a.time_in, a.status, a.confidence_score,
                              s.reg_no, s.name, s.course, s.photo_path
                       FROM attendance a JOIN students s ON s.id = a.student_id
                       WHERE a.`date` BETWEEN ? AND ?
                       ORDER BY a.`date` DESC, a.time_in DESC");
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll();

// ---------- 3. EXPORT TO CSV (runs before any HTML is printed) ----------
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');                       // tell the browser it is a CSV
    header('Content-Disposition: attachment; filename="attendance_' . $from . '_to_' . $to . '.csv"'); // force download
    $out = fopen('php://output', 'w');                                     // write directly to the browser
    fwrite($out, "\xEF\xBB\xBF");                                          // BOM so Excel reads UTF-8 names
    fputcsv($out, ['Reg No', 'Name', 'Course', 'Date', 'Time In', 'Status', 'Confidence %']); // header row
    foreach ($rows as $r) {
        fputcsv($out, [$r['reg_no'], $r['name'], $r['course'], $r['date'], $r['time_in'], $r['status'], $r['confidence_score']]);
    }
    fclose($out);
    exit;                                                                  // stop: no HTML after the CSV
}

// ---------- 4. Absent list (only when one single day is selected) ----------
$absent = [];
if ($from === $to) {
    $stmt = $pdo->prepare("SELECT reg_no, name, course FROM students
                           WHERE id NOT IN (SELECT student_id FROM attendance WHERE `date` = ?)
                           ORDER BY name");
    $stmt->execute([$from]);
    $absent = $stmt->fetchAll();
}

$avg = $rows ? round(array_sum(array_column($rows, 'confidence_score')) / count($rows), 1) : 0;

$pageTitle = 'Attendance Report';
$active    = 'report';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h3 fw-bold mb-1">Attendance Report</h1>
  <p class="text-secondary mb-0">Filter by date and export to Excel (CSV).</p>
</div>

<!-- ===== FILTER FORM ===== -->
<form class="glass-card p-3 mb-4 row g-2 align-items-end" method="get">
  <div class="col-sm-4 col-md-3"><label class="form-label small text-secondary">From</label>
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
  <div class="col-sm-4 col-md-3"><label class="form-label small text-secondary">To</label>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
  <div class="col-sm-4 col-md-6 d-flex gap-2">
    <button class="btn btn-gradient"><i class="bi bi-funnel me-1"></i>Filter</button>
    <a class="btn btn-outline-success" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=1"><i class="bi bi-download me-1"></i>Export CSV</a>
  </div>
</form>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="glass-card stat-card"><div><div class="stat-value"><?= count($rows) ?></div><div class="stat-label">Records</div></div></div></div>
  <div class="col-6 col-md-3"><div class="glass-card stat-card"><div><div class="stat-value"><?= $avg ?>%</div><div class="stat-label">Avg. confidence</div></div></div></div>
  <?php if ($from === $to): ?>
  <div class="col-6 col-md-3"><div class="glass-card stat-card"><div><div class="stat-value"><?= count($absent) ?></div><div class="stat-label">Absent</div></div></div></div>
  <?php endif; ?>
</div>

<!-- ===== ATTENDANCE TABLE ===== -->
<div class="glass-card p-3 mb-4">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr class="text-secondary small">
        <th>#</th><th>Photo</th><th>Reg No</th><th>Name</th><th>Course</th><th>Date</th><th>Time In</th><th>Status</th><th style="min-width:150px">Confidence</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $i => $r):
            $c = (float)$r['confidence_score'];
            $bar = $c >= 70 ? 'success' : ($c >= 55 ? 'warning' : 'danger'); // colour of the bar
      ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><img class="avatar" src="<?= e(photo_url($r['photo_path'])) ?>" alt=""></td>
          <td><?= e($r['reg_no']) ?></td>
          <td class="fw-semibold"><?= e($r['name']) ?></td>
          <td><?= e($r['course']) ?></td>
          <td><?= e($r['date']) ?></td>
          <td><?= e($r['time_in']) ?></td>
          <td><span class="badge text-bg-<?= $r['status'] === 'Late' ? 'warning' : 'success' ?>"><?= e($r['status']) ?></span></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar bg-<?= $bar ?>" style="width:<?= $c ?>%"></div></div>
              <span class="small"><?= number_format($c, 1) ?>%</span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="9" class="text-center text-secondary py-4">No attendance records for this date.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== ABSENT LIST (single day) ===== -->
<?php if ($from === $to && $absent): ?>
<div class="glass-card p-3">
  <h2 class="h6 fw-bold mb-3 text-danger"><i class="bi bi-person-x me-1"></i>Absent on <?= e($from) ?></h2>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($absent as $a): ?>
      <span class="badge rounded-pill text-bg-dark border fw-normal px-3 py-2"><?= e($a['name']) ?> <span class="text-secondary">(<?= e($a['reg_no']) ?>)</span></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
