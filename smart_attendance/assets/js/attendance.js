// =====================================================================
//  MODULE 2 - LIVE FACE ATTENDANCE  (assets/js/attendance.js)
//  Used by index.php. Every important line is explained for the viva.
//
//  HOW IT WORKS (short story):
//   1. Load AI models + all student faces from MySQL (via PHP API)
//   2. Open webcam
//   3. LIVENESS LOOP  (every 100 ms)  : find face + detect eye BLINK (anti-spoof)
//   4. RECOGNITION LOOP (every 1000 ms): make 128-number embedding + compare with database faces
//   5. If matched + blinked -> call PHP mark_attendance.php -> beep + toast + green animation
// =====================================================================

// ---------------------------------------------------------------------
// SETTINGS
// ---------------------------------------------------------------------
const BASE            = window.APP.base;             // '/smart_attendance' (from PHP)
const MODEL_URL       = BASE + '/models';            // folder with the AI model files
const MATCH_THRESHOLD = window.APP.threshold;        // 0.6 -> distance below this = same person
const EAR_CLOSED      = 0.21;                        // eye aspect ratio below this = eye closed
const EAR_OPEN        = 0.25;                        // eye aspect ratio above this = eye open again
const BLINK_VALID_MS  = 6000;                        // a blink counts for 6 seconds
const REQUIRED_MATCHES = 2;                          // same person must match 2 times in a row
const LIVE_INTERVAL   = 100;                         // liveness loop speed (ms)
const RECOG_INTERVAL  = 1000;                        // recognition loop speed (ms)

// ---------------------------------------------------------------------
// PAGE ELEMENTS
// ---------------------------------------------------------------------
const video        = document.getElementById('video');        // webcam <video>
const overlay      = document.getElementById('overlay');      // <canvas> for the face box
const cameraBox    = document.getElementById('cameraBox');    // box with CSS animations
const successCheck = document.getElementById('successCheck'); // big green tick
const livenessHint = document.getElementById('livenessHint'); // "Please blink" message
const statusPill   = document.getElementById('statusPill');
const statusText   = document.getElementById('statusText');
const startOverlay = document.getElementById('startOverlay');
const startInfo    = document.getElementById('startInfo');
const btnStart     = document.getElementById('btnStart');
const toastBox     = document.getElementById('toastBox');
const countToday   = document.getElementById('countToday');

// ---------------------------------------------------------------------
// STATE (values that change while the page runs)
// ---------------------------------------------------------------------
let faceMatcher = null;              // object that compares a new face with all saved faces
let studentsById = {};               // {1: {id, name, reg_no, photo}, 2: {...}}
const markedToday = new Set();       // student ids already marked today
const sending = new Set();           // student ids being sent to the server right now
const notifiedAlready = new Set();   // students we already showed "already marked" for
let eyesClosed = false;              // are the eyes closed right now?
let lastBlinkAt = 0;                 // time of the last blink
let lastFaceSeenAt = 0;              // last time any face was seen
let current = null;                  // latest recognition result (used for the label)
let streak = { id: null, count: 0 }; // how many times in a row the same student matched
let liveBusy = false, recogBusy = false; // stop two AI runs overlapping
let audioCtx = null;                 // Web Audio object used for the beep

// Detector settings: Tiny Face Detector is fast enough for a normal laptop
const detectorOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });

// ---------------------------------------------------------------------
// SMALL HELPERS
// ---------------------------------------------------------------------

// Change the status badge at the top (type = loading / ready / error)
function setStatus(type, text) {
  statusPill.className = 'status-pill ' + type;      // changes dot colour
  statusText.textContent = text;                     // changes the words
}

// Show a Bootstrap toast message (type = success / info / danger)
function showToast(text, type) {
  const el = document.createElement('div');          // create the toast element
  el.className = 'toast align-items-center text-bg-' + type + ' border-0';
  el.setAttribute('role', 'alert');
  el.innerHTML = '<div class="d-flex"><div class="toast-body fw-semibold"></div>' +
                 '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  el.querySelector('.toast-body').textContent = text; // textContent = safe, no HTML injection
  toastBox.appendChild(el);                          // add to the page
  new bootstrap.Toast(el, { delay: 4000 }).show();   // show for 4 seconds
  el.addEventListener('hidden.bs.toast', () => el.remove()); // clean up after hiding
}

// Play a short beep using the browser's Web Audio API (no sound file needed)
function beep() {
  if (!audioCtx) return;                             // audio not allowed yet
  const osc = audioCtx.createOscillator();           // sound generator
  const gain = audioCtx.createGain();                // volume control
  osc.type = 'sine';                                 // smooth tone
  osc.frequency.value = 880;                         // pitch (Hz)
  gain.gain.value = 0.15;                            // volume
  osc.connect(gain);                                 // generator -> volume
  gain.connect(audioCtx.destination);                // volume -> speakers
  osc.start();                                       // start sound
  osc.stop(audioCtx.currentTime + 0.2);              // stop after 0.2 seconds
}

// Live clock + date at the top of the screen
function updateClock() {
  const now = new Date();                            // current date and time
  document.getElementById('clock').textContent = now.toLocaleTimeString('en-GB'); // 14:05:09
  document.getElementById('dateText').textContent =
    now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}
setInterval(updateClock, 1000);                      // run every second
updateClock();                                       // and once right now

// ---------------------------------------------------------------------
// STEP 1: LOAD AI MODELS + STUDENT FACES
// ---------------------------------------------------------------------
async function loadModels() {
  await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);   // finds the face position
  await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);  // finds 68 points (eyes, nose, mouth)
  await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL); // creates the 128-number embedding
}

async function loadStudents() {
  const res = await fetch(BASE + '/api/get_students.php');      // ask PHP for all saved faces
  if (res.status === 401) { location.href = BASE + '/login.php'; return false; } // not logged in
  const data = await res.json();                                // JSON -> JavaScript object

  // Create one LabeledFaceDescriptors for each student
  // label = student id (as text), descriptor = 128 numbers (must be Float32Array)
  const labeled = data.students.map(s => {
    studentsById[s.id] = s;                                     // remember student details by id
    return new faceapi.LabeledFaceDescriptors(String(s.id), [new Float32Array(s.descriptor)]);
  });

  data.marked_today.forEach(id => markedToday.add(id));         // students already present today
  countToday.textContent = markedToday.size;                    // show how many

  if (labeled.length === 0) return false;                       // nobody registered yet
  faceMatcher = new faceapi.FaceMatcher(labeled, MATCH_THRESHOLD); // the comparer (distance 0.6)
  return true;
}

async function init() {
  try {
    setStatus('loading', 'Loading AI models...');
    await loadModels();                                         // load the 3 models
    setStatus('loading', 'Loading student faces...');
    const hasStudents = await loadStudents();                   // load faces from MySQL
    if (!hasStudents) {
      setStatus('error', 'No registered faces');
      startInfo.innerHTML = 'No student faces found. <a href="' + BASE + '/student/register_face.php">Register a student first</a>.';
      return;
    }
    setStatus('ready', Object.keys(studentsById).length + ' faces loaded');
    startInfo.textContent = Object.keys(studentsById).length + ' student faces ready. Press Start to begin.';
    btnStart.disabled = false;                                  // allow the user to start
  } catch (err) {
    console.error(err);                                         // details in browser console (F12)
    setStatus('error', 'Failed to load');
    startInfo.textContent = 'Could not load AI models. Check that the /models folder is in place.';
  }
}

// ---------------------------------------------------------------------
// STEP 2: START CAMERA
// ---------------------------------------------------------------------
btnStart.addEventListener('click', async () => {
  try {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)(); // allowed because of the click
    const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } }); // open webcam
    video.addEventListener('playing', startLoops, { once: true }); // when video plays -> start AI loops
    video.srcObject = stream;                                   // show webcam in <video>
    startOverlay.style.display = 'none';                        // hide start screen
    cameraBox.classList.add('scanning');                        // glowing scanning border
    setStatus('ready', 'Scanning...');
  } catch (err) {
    startInfo.textContent = 'Camera blocked. Allow camera permission and open http://localhost/smart_attendance';
  }
});

function startLoops() {
  faceapi.matchDimensions(overlay, { width: video.videoWidth, height: video.videoHeight }); // canvas = video size
  setInterval(livenessTick, LIVE_INTERVAL);          // fast loop: face box + blink detection
  setInterval(recognitionTick, RECOG_INTERVAL);      // slow loop: who is this person?
}

// ---------------------------------------------------------------------
// ANTI-SPOOF: BLINK DETECTION USING EYE ASPECT RATIO (EAR)
// A photo or a phone screen cannot blink. A real person can.
// EAR = (vertical distances) / (2 x horizontal distance) of the 6 eye points
//   open eye  -> about 0.30        closed eye -> about 0.15
// ---------------------------------------------------------------------
function eyeAspectRatio(eye) {
  const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);     // distance between 2 points
  const vertical1 = dist(eye[1], eye[5]);                       // upper lid to lower lid (1)
  const vertical2 = dist(eye[2], eye[4]);                       // upper lid to lower lid (2)
  const horizontal = dist(eye[0], eye[3]);                      // eye corner to eye corner
  return (vertical1 + vertical2) / (2 * horizontal);            // the EAR formula
}

function blinkedRecently() {
  return Date.now() - lastBlinkAt < BLINK_VALID_MS;             // true if blink happened in last 6 s
}

// ---------------------------------------------------------------------
// STEP 3: LIVENESS LOOP (every 100 ms)  -> draws box + detects blink
// ---------------------------------------------------------------------
async function livenessTick() {
  if (liveBusy) return;                                         // previous run not finished
  liveBusy = true;
  try {
    // Find the face and its 68 landmarks (fast, no embedding here)
    const result = await faceapi.detectSingleFace(video, detectorOptions).withFaceLandmarks();
    const ctx = overlay.getContext('2d');
    ctx.clearRect(0, 0, overlay.width, overlay.height);         // erase the previous box

    if (!result) {                                              // no face in the picture
      if (Date.now() - lastFaceSeenAt > 1500) {                 // gone for 1.5 s -> forget everything
        current = null;
        streak = { id: null, count: 0 };
      }
      livenessHint.style.display = 'none';
      return;
    }

    lastFaceSeenAt = Date.now();                                // remember we saw a face

    // Calculate the average EAR of the left and right eye
    const leftEAR  = eyeAspectRatio(result.landmarks.getLeftEye());  // 6 points of the left eye
    const rightEAR = eyeAspectRatio(result.landmarks.getRightEye()); // 6 points of the right eye
    const ear = (leftEAR + rightEAR) / 2;

    // Blink = eyes closed, then opened again
    if (ear < EAR_CLOSED) {
      eyesClosed = true;                                        // eyes are closed now
    } else if (eyesClosed && ear > EAR_OPEN) {
      eyesClosed = false;                                       // eyes opened again
      lastBlinkAt = Date.now();                                 // BLINK DETECTED = real person
    }

    drawFaceBox(result.detection.box);                          // draw the green/red/amber box
  } catch (err) {
    console.error(err);
  } finally {
    liveBusy = false;                                           // allow the next run
  }
}

// Draw the box around the face. Colour + text depend on the latest recognition result
function drawFaceBox(box) {
  let label = 'Scanning...';                                    // default text
  let color = '#22d3ee';                                        // cyan
  let needBlink = false;

  if (current && current.type === 'unknown') {
    label = 'Unknown face';                                     // not in database
    color = '#ef4444';                                          // red
  } else if (current && current.type === 'match') {
    const name = current.student.name;
    const pct = Math.round(current.confidence);                 // 92
    if (markedToday.has(current.student.id)) {
      label = name + ' - Marked';                               // already present today
      color = '#22c55e';                                        // green
    } else if (blinkedRecently()) {
      label = name + ' ' + pct + '%';                           // verified real person
      color = '#22c55e';                                        // green
    } else {
      label = name + ' ' + pct + '% - Blink';                   // waiting for blink
      color = '#f59e0b';                                        // amber
      needBlink = true;
    }
  }
  livenessHint.style.display = needBlink ? 'block' : 'none';    // show / hide "Please blink"
  new faceapi.draw.DrawBox(box, { label: label, boxColor: color, lineWidth: 3 }).draw(overlay); // draw the box
}

// ---------------------------------------------------------------------
// STEP 4: RECOGNITION LOOP (every 1000 ms) -> who is this?
// ---------------------------------------------------------------------
async function recognitionTick() {
  if (recogBusy) return;                                        // previous run not finished
  if (Date.now() - lastFaceSeenAt > 1000) return;               // nobody in front of the camera
  recogBusy = true;
  try {
    // Get face embedding: detect face -> landmarks -> 128-number descriptor
    const detection = await faceapi
      .detectSingleFace(video, detectorOptions)                 // 1. find face
      .withFaceLandmarks()                                      // 2. find 68 points
      .withFaceDescriptor();                                    // 3. 128-number embedding

    if (!detection) { current = null; return; }                 // face disappeared

    // Compare faces: find the closest saved face (smaller distance = more similar)
    const bestMatch = faceMatcher.findBestMatch(detection.descriptor);

    // Distance above 0.6 -> label becomes "unknown"
    if (bestMatch.label === 'unknown') {
      current = { type: 'unknown' };
      streak = { id: null, count: 0 };
      return;
    }

    const student = studentsById[bestMatch.label];              // label = student id
    // Confidence % = (1 - distance) x 100. Example: distance 0.08 -> 92 %
    const confidence = Math.round((1 - bestMatch.distance) * 10000) / 100;
    current = { type: 'match', student: student, confidence: confidence };

    // Count how many times in a row the SAME student was matched
    if (streak.id === student.id) streak.count++;
    else streak = { id: student.id, count: 1 };

    // Already present today -> only tell the user once
    if (markedToday.has(student.id)) {
      if (!notifiedAlready.has(student.id)) {
        notifiedAlready.add(student.id);
        showToast(student.name + ' - attendance already marked today', 'info');
      }
      return;
    }

    // Mark attendance only if: matched twice in a row AND the person blinked (real face)
    if (streak.count >= REQUIRED_MATCHES && blinkedRecently()) {
      await markAttendance(student, confidence);
    }
  } catch (err) {
    console.error(err);
  } finally {
    recogBusy = false;
  }
}

// ---------------------------------------------------------------------
// STEP 5: SEND TO PHP -> SAVE ATTENDANCE
// ---------------------------------------------------------------------
async function markAttendance(student, confidence) {
  if (sending.has(student.id)) return;                          // already sending this student
  sending.add(student.id);
  try {
    // AJAX POST to PHP (PHP also checks "already marked today")
    const res = await fetch(BASE + '/api/mark_attendance.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ student_id: student.id, confidence: confidence })
    });
    const data = await res.json();                              // PHP answer
    if (!data.success) return;                                  // error -> try again next second

    markedToday.add(student.id);                                // remember: done for today
    countToday.textContent = markedToday.size;                  // update counter at the top

    if (data.status === 'marked') {
      // NEW attendance saved
      beep();                                                   // play beep sound
      playSuccessAnimation();                                   // green flash + tick
      updateLastCard(data, 'Marked at ' + data.time_in + ' - ' + Math.round(confidence) + '% match' +
                     (data.att_status === 'Late' ? ' (Late)' : ''));
      showToast('Attendance Marked for ' + data.name + ' - ' + Math.round(confidence) + '% Match', 'success');
    } else {
      // PHP says this student was already saved today
      notifiedAlready.add(student.id);
      updateLastCard(data, 'Already marked at ' + data.time_in);
      showToast(data.name + ' - already marked at ' + data.time_in, 'info');
    }
  } catch (err) {
    console.error(err);
  } finally {
    sending.delete(student.id);
  }
}

// Green flash around the camera + big tick
function playSuccessAnimation() {
  cameraBox.classList.add('matched');                           // CSS @keyframes successFlash
  successCheck.classList.add('show');                           // CSS @keyframes popIn
  setTimeout(() => {
    cameraBox.classList.remove('matched');                      // remove classes so it can play again
    successCheck.classList.remove('show');
  }, 1400);
}

// Update the "Last marked student" card at the bottom
function updateLastCard(data, meta) {
  document.getElementById('lastPhoto').src = data.photo;        // student photo
  document.getElementById('lastName').textContent = data.name;  // student name
  document.getElementById('lastMeta').textContent = data.reg_no + '  |  ' + meta;
  const card = document.getElementById('lastCard');
  card.classList.remove('pop');                                 // restart the slide-up animation
  void card.offsetWidth;                                        // (forces the browser to reset it)
  card.classList.add('pop');
}

// Start everything when the page opens
init();
