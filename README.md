# Smart--Attendance--AI
AI face recognition attendance system built with Core PHP 8 (PDO), MySQL, Bootstrap 5 and face-api.js.



Smart Attendance AI
AI face recognition attendance system built with Core PHP 8 (PDO), MySQL,
Bootstrap 5 and face-api.js.
It runs entirely on a local WAMP server: no Python, no Laravel, no paid cloud API.
ICT Project
<!-- Add screenshots here, e.g. ![Live attendance](docs/live.png) ![Dashboard]
(docs/dashboard.png) -->
Features
Face registration - the webcam captures a face and the browser creates a 128
number embedding, which is saved in MySQL.
Live attendance - full-screen camera that recognises students in real time and
marks attendance automatically (beep, toast and green success animation).
Anti-spoof - eye-blink detection (Eye Aspect Ratio from 68 landmarks), so a printed
photo cannot mark attendance.
Duplicate protection - one record per student per day, enforced in JavaScript, PHP
and a MySQL unique key.
Confidence score - the match percentage is stored with every attendance record.
Admin dashboard - total, present and absent counts, plus a Chart.js 7-day trend.
Student management - add, edit, delete and retake a face.
Reports - date-range filter with photo and confidence, plus CSV export for Excel.
Roles - admin and lecturer logins with bcrypt passwords.
Dark UI - Bootstrap 5 with scanning and success animations.
Tech stack
Layer
Technology
Backend
PHP 8 (OOP, PDO prepared statements)
Database
MySQL (3 tables: students , attendance , users )
Frontend
HTML5, CSS3, Bootstrap 5, JavaScript (fetch/AJAX)
AI
Charts
face-api.js 0.22.2 (runs in the browser)
Chart.js 4
Server
WAMP (Apache + MySQL)
Requirements
WAMP Server (PHP 8.0+, MySQL 5.7+/MariaDB) on Windows
A webcam and a modern browser (Chrome or Edge recommended)
Internet for the Bootstrap, Chart.js and face-api.js CDNs (a local copy of face-api.js
is included as a backup)
Installation
1. Clone into the WAMP web root:
cd C:/wamp64/www
git clone https://github.com/<your-username>/smart_attendance.git
2. Start WAMP and wait for the icon to turn green.
3. Import the database: open http://localhost/phpmyadmin , choose Import, select
sql/smart_attendance.sql , then click Go.
4. Check the DB settings in config/db_connect.php (WAMP default: user root ,
empty password).
5. Models: the 7 face-api.js model files must be in
C:/wamp64/www/smart_attendance/models/ .
They are included in this repo. If missing, download them from the
face-api.js weights folder:
tiny_face_detector_model-* , face_landmark_68_model-* , face_recognition_model
* .
6. Open http://localhost/smart_attendance/ .
Use localhost , not your IP address, because browsers only allow camera access on
localhost or HTTPS.
Default logins
Role
Username
Password
Admin
admin
admin123
Lecturer
lecturer
lecturer123
Change these passwords before using the system for anything real.
Usage
1. Log in as admin, go to Register Face, press Start Camera, enter Reg No and Name,
then Capture & Save.
2. Open Live Attendance, press Start Scanning. Students look at the camera and
blink once; attendance is marked automatically.
3. View Dashboard and Reports; use Export CSV to download.
How it works
Register:  webcam -> detect face -> 68 landmarks -> 128-number embedding -> fetch() -> PHP -> MySQL
Attend:    load embeddings (get_students.php) -> FaceMatcher(0.6)
           every 100 ms : detect face + blink check (anti-spoof)
           every 1000 ms: embedding -> findBestMatch() -> distance < 0.6 ?
                          -> mark_attendance.php -> duplicate check -> INSERT (+ confidence)
Confidence % = (1 - distance) x 100 . A full explanation for presentations is in
VIVA_NOTES.md.
Project structure
smart_attendance/
├── index.php                    # Live attendance page
├── login.php / logout.php
├── config/db_connect.php        # Settings + PDO Database class
├── includes/                    # Shared header / footer
├── models/                      # face-api.js model files
├── assets/
│   
│   
│   
├── css/style.css
├── js/attendance.js         # Recognition + blink detection
└── js/vendor/face-api.min.js
├── student/register_face.php
├── admin/                       # dashboard, manage_students, attendance_report
├── api/                         # get_students, save_face, mark_attendance
├── sql/smart_attendance.sql
Configuration
Setting File Default Purpose
MATCH_THRESHOLD config/db_connect.php 0.6 Lower = stricter
matching
LATE_AFTER config/db_connect.php 09:15:00 Later arrivals are
marked "Late"
EAR_CLOSED /
EAR_OPEN
assets/js/attendance.js 0.21 /
0.25
Blink sensitivity
REQUIRED_MATCHES assets/js/attendance.js 2 Consecutive
matches needed
Security
PDO prepared statements, bcrypt password hashing, output escaping, session login
with role checks,
validated JPEG uploads, and PHP execution blocked in uploads/.
Troubleshooting
Problem Fix
Camera does not start Use http://localhost/... and allow camera permission
"Model load failed" Check the 7 files in models/ and the path in the browser
console (F12)
Database connection
failed WAMP not green, or smart_attendance.sql not imported
Blink not detected Increase EAR_CLOSED to 0.23, improve lighting
Face not recognised Re-register with Retake Face in good, even light
Limitations
└── uploads/                     # Student photos (git-ignored)
A pre-recorded video could still fool blink detection (a depth or infrared camera
would be needed).
Accuracy depends on lighting and camera quality.
Designed for a single classroom kiosk, not a large multi-site deployment.
Privacy
Face embeddings and photos are personal data. Store them only with consent, keep the
database secured, and do not commit uploads/ to Git.
Credits
face-api.js by Vincent Mühler (MIT)
Bootstrap, 
Chart.js
License
Add a license of your choice (for example MIT) as LICENSE . This project is submitted for
academic purposes.
Author
<Your Name> - ICT Final Year, <Your University>
