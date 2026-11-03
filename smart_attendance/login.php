<?php
// login.php - checks username + password from the users table
require_once __DIR__ . '/config/db_connect.php';

// Already logged in? go to the attendance page
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // 1. Find the user (prepared statement)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // 2. password_verify compares the typed password with the saved bcrypt hash
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);                 // new session id after login
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    $error = 'Wrong username or password.';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | Smart Attendance AI</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
  <div class="glass-card p-4 p-md-5" style="width:100%;max-width:420px;">
    <div class="text-center mb-4">
      <div class="brand-orb mx-auto mb-3"><i class="bi bi-person-bounding-box"></i></div>
      <h1 class="h4 fw-bold mb-1">Smart Attendance <span class="text-gradient">AI</span></h1>
      <p class="text-secondary small mb-0">Face Recognition Attendance System</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <div class="mb-3">
        <label class="form-label small text-secondary">Username</label>
        <input type="text" name="username" class="form-control form-control-lg" required autofocus>
      </div>
      <div class="mb-4">
        <label class="form-label small text-secondary">Password</label>
        <input type="password" name="password" class="form-control form-control-lg" required>
      </div>
      <button class="btn btn-gradient btn-lg w-100">Sign In</button>
    </form>
    <p class="text-center text-secondary small mt-4 mb-0">Demo: admin / admin123</p>
  </div>
</body>
</html>
