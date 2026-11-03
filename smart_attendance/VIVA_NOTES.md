# Viva Notes - Smart Attendance AI
ICT Final Year Project | Core PHP 8 (PDO) + MySQL + Bootstrap 5 + JavaScript + face-api.js | No Python, No Laravel

## 1. Install on WAMP (5 minutes)
1. Copy this whole folder to **`C:\wamp64\www\smart_attendance\`**
2. Start WAMP (icon must be **green**).
3. Open **http://localhost/phpmyadmin** -> *Import* -> choose `sql/smart_attendance.sql` -> *Go*.
4. Check `config/db_connect.php` (default WAMP: user `root`, empty password).
5. Make sure the AI models are inside **`C:\wamp64\www\smart_attendance\models\`** (already included, 7 files).
6. Open **http://localhost/smart_attendance/** (use `localhost`, NOT your IP address - browsers only allow the camera on localhost/https).

Logins: `admin / admin123` (full access) | `lecturer / lecturer123` (no "Students" page)

Models (only if the folder is ever empty) - download these 7 files from
https://github.com/justadudewhohacks/face-api.js/tree/master/weights :
- tiny_face_detector_model-weights_manifest.json + tiny_face_detector_model-shard1
- face_landmark_68_model-weights_manifest.json + face_landmark_68_model-shard1
- face_recognition_model-weights_manifest.json + face_recognition_model-shard1 + face_recognition_model-shard2

## 2. Folder structure
```
smart_attendance/
  index.php                  Live attendance page (Module 2)
  login.php / logout.php     Login using the users table
  config/db_connect.php      Settings + PDO Database class + helper functions
  models/                    face-api.js AI model files
  includes/header.php,footer.php   Shared layout
  assets/css/style.css       Dark theme + CSS animations
  assets/js/attendance.js    Live recognition + blink anti-spoof (fully commented)
  assets/js/vendor/face-api.min.js  Offline backup copy (used only if the CDN is unreachable)
  student/register_face.php  Face registration (Module 1)
  admin/dashboard.php        Cards + Chart.js 7-day line chart
  admin/manage_students.php  CRUD + Retake Face
  admin/attendance_report.php  Date filter + CSV export
  api/get_students.php       All face descriptors -> JSON
  api/save_face.php          Save descriptor + photo
  api/mark_attendance.php    Duplicate check + INSERT with confidence
  sql/smart_attendance.sql   3 tables + default users
  uploads/                   Student photos
```

## 3. How it works (explain this in the viva)
**Registration:** webcam -> face-api.js finds the face -> 68 landmarks -> neural network makes a **128-number face embedding**
-> sent by AJAX (fetch) as JSON -> PHP saves it in `students.face_descriptor`.

**Attendance:** page loads all embeddings from MySQL -> builds `LabeledFaceDescriptors` + `FaceMatcher(0.6)`
-> every second the live face is converted to a new embedding -> `findBestMatch()` calculates the **Euclidean distance**
to every saved face -> distance < 0.6 = same person -> PHP `mark_attendance.php` saves the attendance.

**Confidence %** = (1 - distance) x 100. Example: distance 0.08 -> 92% match. Saved in `attendance.confidence_score`.

**Anti-spoof (blink detection):** with the 68 landmarks, the *Eye Aspect Ratio* is calculated:
`EAR = (|p1-p5| + |p2-p4|) / (2 x |p0-p3|)`. Open eye ~0.30, closed ~0.15. Eye closes then opens = one blink.
Attendance is saved only if the person blinked in the last 6 seconds, so a printed photo cannot mark attendance.

**Duplicate block (3 layers):** (1) JavaScript `markedToday` set, (2) PHP `SELECT ... WHERE student_id=? AND date=CURDATE()`,
(3) MySQL `UNIQUE KEY (student_id, date)`.

**Security:** PDO prepared statements (no SQL injection), `password_hash/password_verify` (bcrypt), `htmlspecialchars` (no XSS),
session login + role check, photo validation (JPEG only, max 2 MB).

## 4. Tuning (top of `assets/js/attendance.js` and `config/db_connect.php`)
| Setting | Default | Meaning |
|---|---|---|
| `MATCH_THRESHOLD` | 0.6 | Lower (0.5) = stricter, fewer false matches |
| `EAR_CLOSED` / `EAR_OPEN` | 0.21 / 0.25 | If blinks are not detected, raise `EAR_CLOSED` a little (0.23) |
| `REQUIRED_MATCHES` | 2 | Same person must match this many times in a row |
| `LATE_AFTER` | 09:15:00 | Arrivals after this time get status "Late" |

## 5. Limitations (good to mention honestly)
- Blink check stops photos, but a pre-recorded video could still fool it (a depth/IR camera would be needed).
- Face matching depends on lighting; register each student in normal light, facing the camera.
- Attendance is trusted from the logged-in kiosk browser (the confidence value is sent by JavaScript).

## 6. Likely viva questions
- *Why face-api.js and not Python?* Runs in the browser, no extra server, privacy (video never leaves the PC), runs on plain WAMP.
- *What is a descriptor / embedding?* 128 numbers that describe a face; the same person gives nearly the same numbers.
- *Why store JSON in a TEXT column?* Simple, and an array of 128 floats is only ~3 KB.
- *What is the threshold 0.6?* Maximum Euclidean distance still treated as the same person (face-api.js default).
