<?php
// includes/header.php - shared top part (head + navbar) for admin/student pages
// Before including, a page sets:  $pageTitle  and  $active  (which menu item is highlighted)
$pageTitle = $pageTitle ?? 'Smart Attendance';
$active    = $active ?? '';
$isAdmin   = ($_SESSION['role'] ?? '') === 'admin';

// Small helper: menu item with "active" class
function nav_item(string $key, string $active, string $url, string $icon, string $label): void
{
    $cls = $key === $active ? 'nav-link active' : 'nav-link';
    echo '<li class="nav-item"><a class="' . $cls . '" href="' . BASE_URL . $url . '">'
       . '<i class="bi ' . $icon . ' me-1"></i>' . $label . '</a></li>';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | Smart Attendance AI</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg app-nav sticky-top">
  <div class="container-xl">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/index.php">
      <span class="brand-orb sm me-2"><i class="bi bi-person-bounding-box"></i></span>Smart Attendance <span class="text-gradient">AI</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <?php
        nav_item('scan',      $active, '/index.php',                    'bi-camera-video', 'Live Attendance');
        nav_item('register',  $active, '/student/register_face.php',    'bi-person-plus',  'Register Face');
        if ($isAdmin) {
            nav_item('students', $active, '/admin/manage_students.php', 'bi-people',       'Students');
        }
        nav_item('dashboard', $active, '/admin/dashboard.php',          'bi-speedometer2', 'Dashboard');
        nav_item('report',    $active, '/admin/attendance_report.php',  'bi-file-earmark-bar-graph', 'Reports');
        ?>
      </ul>
      <span class="badge rounded-pill text-bg-dark border me-3">
        <i class="bi bi-person-circle me-1"></i><?= e($_SESSION['username'] ?? '') ?> (<?= e($_SESSION['role'] ?? '') ?>)
      </span>
      <a class="btn btn-sm btn-outline-light" href="<?= BASE_URL ?>/logout.php">Logout</a>
    </div>
  </div>
</nav>
<main class="container-xl py-4">
