<?php
// logout.php - destroy the session and go back to login
require_once __DIR__ . '/config/db_connect.php';
$_SESSION = [];          // clear all session data
session_destroy();       // end the session
header('Location: ' . BASE_URL . '/login.php');
exit;
