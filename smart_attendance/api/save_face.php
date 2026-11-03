<?php
/**
 * api/save_face.php
 * Receives (as JSON) the 128-number face descriptor + photo from register_face.php
 *  - NEW student : INSERT a row
 *  - RETAKE      : student_id is sent -> UPDATE the face + photo only
 */
require_once __DIR__ . '/../config/db_connect.php';
require_login([], true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST only.'], 405);
}

// 1. Read the JSON body sent by JavaScript fetch()
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    json_response(['success' => false, 'message' => 'Invalid data.'], 400);
}

// 2. Validate the face descriptor: must be exactly 128 numbers
$descriptor = $in['descriptor'] ?? null;
if (!is_array($descriptor) || count($descriptor) !== 128) {
    json_response(['success' => false, 'message' => 'Face descriptor must contain 128 numbers.'], 422);
}
foreach ($descriptor as $n) {
    if (!is_numeric($n)) {
        json_response(['success' => false, 'message' => 'Descriptor has invalid values.'], 422);
    }
}
$descriptorJson = json_encode(array_map('floatval', $descriptor)); // stored in TEXT column

// 3. Validate the photo (base64 JPEG sent from the canvas)
$photo = $in['photo'] ?? '';
if (!preg_match('#^data:image/jpeg;base64,#', $photo)) {
    json_response(['success' => false, 'message' => 'Photo must be a JPEG.'], 422);
}
$imageData = base64_decode(substr($photo, strpos($photo, ',') + 1), true);
if ($imageData === false || strlen($imageData) > 2 * 1024 * 1024 || !@getimagesizefromstring($imageData)) {
    json_response(['success' => false, 'message' => 'Photo is not a valid image (max 2MB).'], 422);
}

// 4. Find out: new student or retake?
$studentId = (int)($in['student_id'] ?? 0);

if ($studentId > 0) {
    // ----- RETAKE: student must already exist -----
    $stmt = $pdo->prepare("SELECT id, reg_no, photo_path FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    if (!$student) {
        json_response(['success' => false, 'message' => 'Student not found.'], 404);
    }
    $regNo = $student['reg_no'];
    $oldPhoto = $student['photo_path'];
} else {
    // ----- NEW STUDENT: validate the form fields -----
    $regNo  = trim($in['reg_no'] ?? '');
    $name   = trim($in['name'] ?? '');
    $course = trim($in['course'] ?? '');
    if ($regNo === '' || $name === '') {
        json_response(['success' => false, 'message' => 'Reg No and Name are required.'], 422);
    }
    $check = $pdo->prepare("SELECT id FROM students WHERE reg_no = ?");
    $check->execute([$regNo]);
    if ($check->fetch()) {
        json_response(['success' => false, 'message' => 'This Reg No is already registered.'], 409);
    }
    $oldPhoto = null;
}

// 5. Save the photo file in /uploads/  (file name = safe reg no + time)
$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$safeName  = preg_replace('/[^A-Za-z0-9_-]/', '_', $regNo);   // remove unsafe characters
$fileName  = $safeName . '_' . time() . '.jpg';
file_put_contents($uploadDir . '/' . $fileName, $imageData);
$photoPath = 'uploads/' . $fileName;

// 6. Save to database (prepared statements = safe from SQL injection)
if ($studentId > 0) {
    $stmt = $pdo->prepare("UPDATE students SET face_descriptor = ?, photo_path = ? WHERE id = ?");
    $stmt->execute([$descriptorJson, $photoPath, $studentId]);
    if ($oldPhoto && is_file(__DIR__ . '/../' . $oldPhoto)) {
        unlink(__DIR__ . '/../' . $oldPhoto);                 // delete the old photo
    }
    json_response(['success' => true, 'message' => 'Face updated successfully.']);
}

$stmt = $pdo->prepare("INSERT INTO students (reg_no, name, course, face_descriptor, photo_path)
                       VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$regNo, $name, $course, $descriptorJson, $photoPath]);
json_response(['success' => true, 'message' => "Student $name registered successfully."]);
