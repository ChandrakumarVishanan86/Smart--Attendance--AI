<?php
/**
 * student/register_face.php  -  MODULE 1: Student Face Registration
 * Left: webcam   |   Right: form (reg_no, name, course)
 * Add  ?retake=ID  to this page to re-capture the face of an existing student.
 */
require_once __DIR__ . '/../config/db_connect.php';
require_login();

// Retake mode: load the student whose face will be replaced
$retake = null;
if (isset($_GET['retake'])) {
    $stmt = $pdo->prepare("SELECT id, reg_no, name, course FROM students WHERE id = ?");
    $stmt->execute([(int)$_GET['retake']]);
    $retake = $stmt->fetch() ?: null;
}

$pageTitle = $retake ? 'Retake Face' : 'Register Face';
$active    = 'register';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <h1 class="h3 fw-bold mb-1"><?= $retake ? 'Retake Face' : 'Register Student Face' ?></h1>
  <p class="text-secondary mb-0">Look straight at the camera in good light, then press <b>Capture &amp; Save</b>.</p>
</div>

<div class="row g-4">
  <!-- ============ LEFT: WEBCAM ============ -->
  <div class="col-lg-7">
    <div class="glass-card p-3">
      <div class="camera-wrap" id="cameraBox">
        <video id="video" autoplay muted playsinline></video>      <!-- live webcam -->
        <canvas id="overlay"></canvas>                              <!-- face box drawn here -->
        <div class="camera-placeholder" id="placeholder">
          <i class="bi bi-camera-video-off"></i><span>Camera is off</span>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mt-3">
        <span class="status-pill loading" id="modelStatus"><span class="status-dot"></span><span id="modelText">Loading AI models...</span></span>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-light" id="btnStart" disabled><i class="bi bi-camera-video me-1"></i>Start Camera</button>
          <button class="btn btn-gradient" id="btnCapture" disabled><i class="bi bi-camera me-1"></i>Capture &amp; Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ RIGHT: FORM ============ -->
  <div class="col-lg-5">
    <div class="glass-card p-4">
      <form id="regForm" onsubmit="return false;">
        <div class="mb-3">
          <label class="form-label text-secondary small">Registration No *</label>
          <input type="text" id="reg_no" class="form-control form-control-lg" placeholder="ICT/2021/045"
                 value="<?= e($retake['reg_no'] ?? '') ?>" <?= $retake ? 'readonly' : '' ?> required>
        </div>
        <div class="mb-3">
          <label class="form-label text-secondary small">Full Name *</label>
          <input type="text" id="name" class="form-control form-control-lg" placeholder="Naveen Kumar"
                 value="<?= e($retake['name'] ?? '') ?>" <?= $retake ? 'readonly' : '' ?> required>
        </div>
        <div class="mb-3">
          <label class="form-label text-secondary small">Course</label>
          <input type="text" id="course" class="form-control form-control-lg" placeholder="BSc in ICT"
                 value="<?= e($retake['course'] ?? '') ?>" <?= $retake ? 'readonly' : '' ?>>
        </div>
        <div id="message" class="alert d-none py-2"></div>

        <div class="d-flex align-items-center gap-3 mt-4" id="previewRow" style="display:none!important">
          <img id="preview" class="avatar" style="width:72px;height:72px" alt="captured face">
          <div class="small text-secondary">Captured photo<br><span class="text-success">128-point face embedding ready</span></div>
        </div>
      </form>
      <hr class="border-secondary-subtle">
      <p class="small text-secondary mb-0">
        <i class="bi bi-info-circle me-1"></i>The system does not store the photo for matching.
        It stores a <b>128-number face descriptor</b> (embedding) created by the AI model.
      </p>
    </div>
  </div>
</div>

<!-- face-api.js from CDN. If the internet is down, the local copy is loaded instead. -->
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>window.faceapi || document.write('<script src="<?= BASE_URL ?>/assets/js/vendor/face-api.min.js"><\/script>');</script>

<script>
// =====================================================================
//  MODULE 1 - FACE REGISTRATION (every line explained for viva)
// =====================================================================

// ---- Settings coming from PHP ----
const MODEL_URL  = '<?= BASE_URL ?>/models';          // folder that contains the AI model files
const SAVE_URL   = '<?= BASE_URL ?>/api/save_face.php'; // PHP file that saves to MySQL
const RETAKE_ID  = <?= $retake ? (int)$retake['id'] : 0 ?>; // 0 = new student, >0 = retake face

// ---- Page elements ----
const video      = document.getElementById('video');        // <video> showing webcam
const overlay    = document.getElementById('overlay');      // <canvas> on top of the video
const cameraBox  = document.getElementById('cameraBox');    // box that gets the glow animation
const placeholder= document.getElementById('placeholder');  // "Camera is off" text
const btnStart   = document.getElementById('btnStart');
const btnCapture = document.getElementById('btnCapture');
const modelStatus= document.getElementById('modelStatus');
const modelText  = document.getElementById('modelText');
const message    = document.getElementById('message');

let stream = null;          // webcam stream (so we can stop it later)
let liveTimer = null;       // timer that draws the live face box

// Show a Bootstrap alert message under the form
function showMessage(text, type) {
  message.className = 'alert py-2 alert-' + type;   // alert-success / alert-danger ...
  message.textContent = text;                       // put the text inside
}

// ---------------------------------------------------------------------
// STEP 1: Load the 3 AI models from the /models folder
// ---------------------------------------------------------------------
async function loadModels() {
  try {
    await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL); // model 1: finds WHERE the face is
    await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL); // model 2: finds 68 points (eyes, nose, mouth)
    await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL); // model 3: makes the 128-number face embedding
    modelStatus.className = 'status-pill ready';     // turn the dot green
    modelText.textContent = 'AI models loaded';      // tell the user
    btnStart.disabled = false;                       // now the camera can be started
  } catch (err) {
    modelStatus.className = 'status-pill error';     // red dot
    modelText.textContent = 'Model load failed - check the /models folder';
    console.error(err);                              // full error in browser console (F12)
  }
}

// ---------------------------------------------------------------------
// STEP 2: Start the webcam
// ---------------------------------------------------------------------
btnStart.addEventListener('click', async () => {
  try {
    // Ask the browser for permission and open the camera
    stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } });
    video.srcObject = stream;                        // show the camera inside the <video> tag
    placeholder.style.display = 'none';              // hide "Camera is off"
    cameraBox.classList.add('scanning');             // start the scanning border animation
    btnCapture.disabled = false;                     // allow capture
    btnStart.disabled = true;                        // camera already running
  } catch (err) {
    showMessage('Cannot open camera. Allow camera permission and use http://localhost', 'danger');
  }
});

// When the video really starts playing, match the canvas size to the video size
video.addEventListener('play', () => {
  const size = { width: video.videoWidth, height: video.videoHeight }; // real camera size
  faceapi.matchDimensions(overlay, size);            // canvas = same size as video
  // Every 300 ms draw a box around the face so the user can see it is detected
  liveTimer = setInterval(async () => {
    const found = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()); // detect face
    overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height); // erase old box
    if (found) {
      new faceapi.draw.DrawBox(found.box, { label: 'Face detected', boxColor: '#22d3ee' }).draw(overlay); // draw new box
    }
  }, 300);
});

// ---------------------------------------------------------------------
// STEP 3: Capture -> get face embedding -> send to PHP
// ---------------------------------------------------------------------
btnCapture.addEventListener('click', async () => {
  // Read the form values (for a retake they come from the database)
  const regNo  = document.getElementById('reg_no').value.trim();
  const name   = document.getElementById('name').value.trim();
  const course = document.getElementById('course').value.trim();
  if (!RETAKE_ID && (regNo === '' || name === '')) {
    showMessage('Please enter Registration No and Name first.', 'warning');
    return;                                          // stop here
  }

  btnCapture.disabled = true;                        // prevent double click
  showMessage('Analysing face...', 'info');

  // Get face embedding: detect face -> find 68 landmarks -> compute 128 numbers
  const detection = await faceapi
    .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 })) // 1. find face
    .withFaceLandmarks()                             // 2. find 68 face points
    .withFaceDescriptor();                           // 3. 128-number face embedding

  // If the AI could not see a face, stop
  if (!detection) {
    showMessage('No face detected. Look at the camera and try again.', 'danger');
    btnCapture.disabled = false;
    return;
  }

  // Convert Float32Array -> normal array so it can be turned into JSON
  const descriptor = Array.from(detection.descriptor); // 128 numbers

  // Crop the face from the video into a small JPEG photo (with a little margin)
  const box = detection.detection.box;               // face rectangle {x,y,width,height}
  const pad = box.width * 0.35;                      // 35% extra space around the face
  const sx = Math.max(0, box.x - pad);               // crop start X
  const sy = Math.max(0, box.y - pad);               // crop start Y
  const sw = Math.min(video.videoWidth  - sx, box.width  + pad * 2); // crop width
  const sh = Math.min(video.videoHeight - sy, box.height + pad * 2); // crop height
  const snap = document.createElement('canvas');     // temporary canvas
  snap.width = 240;                                  // photo size 240 x 240
  snap.height = 240;
  snap.getContext('2d').drawImage(video, sx, sy, sw, sh, 0, 0, 240, 240); // copy cropped pixels
  const photo = snap.toDataURL('image/jpeg', 0.9);   // canvas -> JPEG text (base64)

  // Show the captured photo preview
  document.getElementById('preview').src = photo;
  document.getElementById('previewRow').style.cssText = 'display:flex!important';

  // Send everything to PHP using AJAX (fetch) as JSON
  try {
    const response = await fetch(SAVE_URL, {
      method: 'POST',                                // send data
      headers: { 'Content-Type': 'application/json' }, // we are sending JSON
      body: JSON.stringify({ student_id: RETAKE_ID, reg_no: regNo, name: name, course: course,
                             descriptor: descriptor, photo: photo }) // the data
    });
    const result = await response.json();            // PHP answer -> JavaScript object

    if (result.success) {
      showMessage(result.message, 'success');        // green message
      if (RETAKE_ID) {                               // retake -> go back to students list
        setTimeout(() => location.href = '<?= BASE_URL ?>/admin/manage_students.php', 1200);
      } else {                                       // new student -> clear the form for the next one
        document.getElementById('regForm').reset();
      }
    } else {
      showMessage(result.message, 'danger');         // red message (e.g. duplicate reg no)
    }
  } catch (err) {
    showMessage('Server error. Is WAMP running?', 'danger');
  }
  btnCapture.disabled = false;                       // allow the next capture
});

// Stop the camera when the user leaves the page
window.addEventListener('beforeunload', () => {
  if (stream) stream.getTracks().forEach(t => t.stop()); // switch webcam light off
  clearInterval(liveTimer);                              // stop the timer
});

// Start loading the AI models as soon as the page opens
loadModels();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
