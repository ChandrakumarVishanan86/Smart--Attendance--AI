<?php
/**
 * api/mark_attendance.php
 * Called automatically by attendance.js when a face is recognised.
 * Receives: { student_id, confidence }
 * Rule: ONE attendance record per student per day (duplicate block).
 */
require_once __DIR__ . '/../config/db_connect.php';
require_login([], true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST only.'], 405);
}

// 1. Read JSON sent by JavaScript
$in         = json_decode(file_get_contents('php://input'), true);
$studentId  = (int)($in['student_id'] ?? 0);
$confidence = (float)($in['confidence'] ?? 0);
$confidence = max(0, min(100, $confidence));            // keep between 0 and 100

// 2. Does this student exist?
$stmt = $pdo->prepare("SELECT id, reg_no, name, photo_path FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();
if (!$student) {
    json_response(['success' => false, 'message' => 'Unknown student.'], 404);
}

$info = [
    'name'   => $student['name'],
    'reg_no' => $student['reg_no'],
    'photo'  => photo_url($student['photo_path']),
];

// 3. DUPLICATE CHECK: already marked today?
$stmt = $pdo->prepare("SELECT time_in FROM attendance WHERE student_id = ? AND `date` = CURDATE()");
$stmt->execute([$studentId]);
$existing = $stmt->fetch();
if ($existing) {
    json_response(['success' => true, 'status' => 'already', 'time_in' => $existing['time_in']] + $info);
}

// 4. Not marked yet -> INSERT (status = Late if after LATE_AFTER time)
try {
    $stmt = $pdo->prepare("INSERT INTO attendance (student_id, `date`, time_in, status, confidence_score)
                           VALUES (?, CURDATE(), CURTIME(), IF(CURTIME() > ?, 'Late', 'Present'), ?)");
    $stmt->execute([$studentId, LATE_AFTER, $confidence]);
} catch (PDOException $e) {
    // Two requests at the same moment: the UNIQUE key blocks the 2nd one
    if ($e->getCode() === '23000') {
        json_response(['success' => true, 'status' => 'already', 'time_in' => date('H:i:s')] + $info);
    }
    throw $e;
}

// 5. Send the saved row back so the screen can show it
$stmt = $pdo->prepare("SELECT time_in, status FROM attendance WHERE student_id = ? AND `date` = CURDATE()");
$stmt->execute([$studentId]);
$row = $stmt->fetch();

json_response([
    'success'    => true,
    'status'     => 'marked',
    'time_in'    => $row['time_in'],
    'att_status' => $row['status'],
    'confidence' => $confidence,
] + $info);
