<?php
/**
 * api/get_students.php
 * Sends ALL registered faces to the live attendance page (as JSON).
 * JavaScript turns them into LabeledFaceDescriptors for the face matcher.
 */
require_once __DIR__ . '/../config/db_connect.php';
require_login([], true);                                  // only logged-in users

// 1. Get every student who has a saved face
$stmt = $pdo->query("SELECT id, reg_no, name, course, face_descriptor, photo_path
                     FROM students WHERE face_descriptor IS NOT NULL");

$students = [];
foreach ($stmt as $row) {
    $students[] = [
        'id'         => (int)$row['id'],
        'reg_no'     => $row['reg_no'],
        'name'       => $row['name'],
        'course'     => $row['course'],
        'photo'      => photo_url($row['photo_path']),
        'descriptor' => json_decode($row['face_descriptor'], true), // JSON text -> array of 128 numbers
    ];
}

// 2. IDs of students already marked today (so the kiosk does not ask again)
$marked = $pdo->query("SELECT student_id FROM attendance WHERE `date` = CURDATE()")
              ->fetchAll(PDO::FETCH_COLUMN);

json_response([
    'success'      => true,
    'students'     => $students,
    'marked_today' => array_map('intval', $marked),
]);
