<?php
/**
 * config/db_connect.php
 * ---------------------------------------------------------
 * 1. Project settings (constants)
 * 2. Database class  -> PDO connection to MySQL (OOP)
 * 3. Small helper functions used by every page
 * Every page / API starts with:  require_once __DIR__ . '/../config/db_connect.php';
 * ---------------------------------------------------------
 */

// ---------- 1. SETTINGS (change here only) ----------
define('DB_HOST', 'localhost');          // WAMP MySQL host
define('DB_NAME', 'smart_attendance');   // database name (from the .sql file)
define('DB_USER', 'root');               // WAMP default user
define('DB_PASS', '');                   // WAMP default password is empty

define('BASE_URL', '/smart_attendance'); // URL path of the project in the browser
define('MATCH_THRESHOLD', 0.6);          // face distance below this = same person
define('LATE_AFTER', '09:15:00');        // arrive after this time = status "Late"

date_default_timezone_set('Asia/Colombo'); // Sri Lanka time for PHP

// Start the PHP session once (used for login)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- 2. DATABASE CLASS (PDO) ----------
class Database
{
    private static ?PDO $pdo = null;      // one shared connection

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw errors
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // rows as arrays
                    PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared statements
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                die('Database connection failed. Is WAMP running and smart_attendance.sql imported?');
            }
        }
        return self::$pdo;
    }
}

$pdo = Database::connect(); // ready-to-use connection for all pages

// ---------- 3. HELPER FUNCTIONS ----------

// Send JSON back to JavaScript and stop the script (used by /api files)
function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Escape text before printing in HTML (stops XSS)
function e(?string $text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Block the page unless user is logged in. $roles = allowed roles (empty = any role)
function require_login(array $roles = [], bool $isApi = false): void
{
    $loggedIn = isset($_SESSION['user_id']);
    $allowed  = $loggedIn && (empty($roles) || in_array($_SESSION['role'], $roles, true));

    if ($allowed) {
        return;
    }
    if ($isApi) {                                   // API call -> JSON error
        json_response(['success' => false, 'message' => 'Please log in first.'], 401);
    }
    header('Location: ' . BASE_URL . '/login.php'); // normal page -> login page
    exit;
}

// URL of a student's photo (or a placeholder if none)
function photo_url(?string $path): string
{
    return $path ? BASE_URL . '/' . $path : BASE_URL . '/assets/img/user.svg';
}
