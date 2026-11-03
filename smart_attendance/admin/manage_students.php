<?php
/**
 * admin/manage_students.php  -  MODULE 3: Manage Students (CRUD)
 *  C = Add   -> button opens student/register_face.php
 *  R = Read  -> table below
 *  U = Edit  -> Bootstrap modal (name, reg no, course)  + "Retake Face" link
 *  D = Delete-> POST form with confirmation
 * Only the admin role can open this page.
 */
require_once __DIR__ . '/../config/db_connect.php';
require_login(['admin']);

// ---------- Handle Edit / Delete (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'update') {
        // UPDATE student details
        $regNo  = trim($_POST['reg_no'] ?? '');
        $name   = trim($_POST['name'] ?? '');
        $course = trim($_POST['course'] ?? '');
        if ($regNo === '' || $name === '') {
            $_SESSION['flash'] = ['danger', 'Reg No and Name are required.'];
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE students SET reg_no = ?, name = ?, course = ? WHERE id = ?");
                $stmt->execute([$regNo, $name, $course, $id]);
                $_SESSION['flash'] = ['success', 'Student updated.'];
            } catch (PDOException $e) {
                // 23000 = duplicate reg_no (UNIQUE key)
                $_SESSION['flash'] = ['danger', $e->getCode() === '23000' ? 'That Reg No already exists.' : 'Database error.'];
            }
        }
    } elseif ($action === 'delete') {
        // DELETE student (attendance rows are removed automatically by the foreign key)
        $stmt = $pdo->prepare("SELECT photo_path FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $photo = $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
        if ($photo && is_file(__DIR__ . '/../' . $photo)) {
            unlink(__DIR__ . '/../' . $photo);                 // remove the photo file too
        }
        $_SESSION['flash'] = ['success', 'Student deleted.'];
    }
    header('Location: manage_students.php');                   // reload page (avoids form resubmit)
    exit;
}

// ---------- Read all students ----------
$students = $pdo->query("SELECT id, reg_no, name, course, photo_path, (face_descriptor IS NOT NULL) AS has_face
                         FROM students ORDER BY name")->fetchAll();
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Manage Students';
$active    = 'students';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div>
    <h1 class="h3 fw-bold mb-1">Manage Students</h1>
    <p class="text-secondary mb-0"><?= count($students) ?> registered</p>
  </div>
  <div class="d-flex gap-2">
    <input type="search" id="search" class="form-control" placeholder="Search name / reg no..." style="width:240px">
    <a href="<?= BASE_URL ?>/student/register_face.php" class="btn btn-gradient text-nowrap"><i class="bi bi-person-plus me-1"></i>Add Student</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash[0]) ?> py-2"><?= e($flash[1]) ?></div>
<?php endif; ?>

<div class="glass-card p-3">
  <div class="table-responsive">
    <table class="table align-middle mb-0" id="studentTable">
      <thead><tr class="text-secondary small">
        <th>Photo</th><th>Reg No</th><th>Name</th><th>Course</th><th>Face</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($students as $s): ?>
        <tr>
          <td><img class="avatar" src="<?= e(photo_url($s['photo_path'])) ?>" alt=""></td>
          <td><?= e($s['reg_no']) ?></td>
          <td class="fw-semibold"><?= e($s['name']) ?></td>
          <td><?= e($s['course']) ?></td>
          <td><?= $s['has_face'] ? '<span class="badge text-bg-success">Registered</span>' : '<span class="badge text-bg-secondary">Missing</span>' ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-info" href="<?= BASE_URL ?>/student/register_face.php?retake=<?= (int)$s['id'] ?>"><i class="bi bi-camera me-1"></i>Retake Face</a>
            <button class="btn btn-sm btn-outline-light btn-edit"
                    data-id="<?= (int)$s['id'] ?>" data-reg="<?= e($s['reg_no']) ?>"
                    data-name="<?= e($s['name']) ?>" data-course="<?= e($s['course']) ?>"><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete <?= e(addslashes($s['name'])) ?> and all attendance records?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$students): ?>
        <tr><td colspan="6" class="text-center text-secondary py-4">No students yet. Click "Add Student".</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== EDIT MODAL ===== -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content glass-card" style="background:#10162a">
      <div class="modal-header border-secondary-subtle">
        <h5 class="modal-title">Edit Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="mb-3"><label class="form-label small text-secondary">Reg No</label>
          <input class="form-control" name="reg_no" id="edit_reg" required></div>
        <div class="mb-3"><label class="form-label small text-secondary">Name</label>
          <input class="form-control" name="name" id="edit_name" required></div>
        <div class="mb-3"><label class="form-label small text-secondary">Course</label>
          <input class="form-control" name="course" id="edit_course"></div>
      </div>
      <div class="modal-footer border-secondary-subtle">
        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-gradient">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Fill the edit modal with the clicked student's data
  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('edit_id').value     = btn.dataset.id;      // hidden id
      document.getElementById('edit_reg').value    = btn.dataset.reg;
      document.getElementById('edit_name').value   = btn.dataset.name;
      document.getElementById('edit_course').value = btn.dataset.course;
      new bootstrap.Modal(document.getElementById('editModal')).show();   // open modal
    });
  });

  // Live search: hide table rows that do not contain the typed text
  document.getElementById('search').addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('#studentTable tbody tr').forEach(tr => {
      tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
