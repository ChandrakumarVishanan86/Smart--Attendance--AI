<?php
/**
 * index.php  -  MODULE 2: Live Face Attendance (main demo page)
 * Full-screen camera. Top: time/date.  Bottom: last marked student.
 * All the AI logic is in assets/js/attendance.js
 */
require_once __DIR__ . '/config/db_connect.php';
require_login();                                       // must be logged in
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Live Attendance | Smart Attendance AI</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="kiosk">

<div class="kiosk-stage">
  <!-- Camera + canvas (canvas draws the face box on top of the video) -->
  <div class="camera-wrap" id="cameraBox">
    <video id="video" autoplay muted playsinline></video>
    <canvas id="overlay"></canvas>
    <div class="success-check" id="successCheck"><i class="bi bi-check-lg"></i></div>
    <div class="liveness-hint" id="livenessHint"><i class="bi bi-eye me-1"></i>Please blink to verify you are real</div>
  </div>

  <!-- TOP BAR: brand, time + date, links -->
  <div class="kiosk-top">
    <div class="d-flex align-items-center gap-3">
      <span class="brand-orb sm"><i class="bi bi-person-bounding-box"></i></span>
      <div><div class="fw-bold">Smart Attendance <span class="text-gradient">AI</span></div>
        <span class="status-pill loading" id="statusPill"><span class="status-dot"></span><span id="statusText">Loading AI models...</span></span>
      </div>
    </div>
    <div class="text-center">
      <div class="clock" id="clock">--:--:--</div>
      <div class="text-secondary" id="dateText">--</div>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <span class="badge text-bg-dark border d-none d-md-inline">Today: <span id="countToday">0</span> present</span>
      <a class="btn btn-sm btn-outline-light" href="<?= BASE_URL ?>/admin/dashboard.php"><i class="bi bi-speedometer2"></i><span class="d-none d-md-inline ms-1">Dashboard</span></a>
      <a class="btn btn-sm btn-outline-light" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>

  <!-- BOTTOM BAR: last marked student -->
  <div class="kiosk-bottom">
    <div class="last-card" id="lastCard">
      <img id="lastPhoto" src="<?= BASE_URL ?>/assets/img/user.svg" alt="">
      <div>
        <div class="text-secondary small" id="lastTitle">Last marked student</div>
        <div class="fw-bold fs-5" id="lastName">Waiting for a face...</div>
        <div class="small text-secondary" id="lastMeta">&nbsp;</div>
      </div>
    </div>
    <div class="text-end small text-secondary d-none d-md-block">
      <i class="bi bi-shield-check text-success me-1"></i>Blink detection (anti-spoof) is ON
    </div>
  </div>

  <!-- START SCREEN (a click is needed to allow camera + sound) -->
  <div class="start-overlay" id="startOverlay">
    <div class="brand-orb"><i class="bi bi-person-bounding-box"></i></div>
    <h1 class="h2 fw-bold mb-0">Face Recognition <span class="text-gradient">Attendance</span></h1>
    <p class="text-secondary mb-0" id="startInfo">Loading AI models and student faces...</p>
    <button class="btn btn-gradient btn-lg px-5" id="btnStart" disabled><i class="bi bi-play-fill me-1"></i>Start Scanning</button>
    <?php if ($isAdmin): ?><a class="small text-secondary" href="<?= BASE_URL ?>/student/register_face.php">+ Register a new student face</a><?php endif; ?>
  </div>
</div>

<!-- Container for Bootstrap toasts ("Attendance Marked for ...") -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="margin-top:90px" id="toastBox"></div>

<script>
  // Settings from PHP -> JavaScript (used inside attendance.js)
  window.APP = {
    base: '<?= BASE_URL ?>',
    threshold: <?= (float)MATCH_THRESHOLD ?>
  };
</script>
<!-- face-api.js from CDN. If the internet is down, the local copy is loaded instead. -->
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>window.faceapi || document.write('<script src="<?= BASE_URL ?>/assets/js/vendor/face-api.min.js"><\/script>');</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/attendance.js"></script>
</body>
</html>
