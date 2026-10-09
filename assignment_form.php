<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

const MIN_COURSES = 4;
const MAX_COURSES = 6;

$studentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$st = $pdo->prepare('SELECT id, full_name, reg_no, program, sheet_saved, sheet_unlocked FROM students WHERE id = ?');
$st->execute([$studentId]);
$student = $st->fetch();
if (!$student) { flash_set('error', 'Student not found.'); header('Location: assignments.php'); exit; }

// Rule after a date sheet is saved: assignments are frozen until the admin approves a
// "change date sheet" request (which sets sheet_unlocked = 1).
$locked = $student['sheet_saved'] && !$student['sheet_unlocked'];

$cur = $pdo->prepare('SELECT course_id FROM assignments WHERE student_id = ?');
$cur->execute([$studentId]);
$assigned = array_map('intval', $cur->fetchAll(PDO::FETCH_COLUMN));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // Whole set is saved at once, so the 4-6 rule can never be broken part-way
    $ids = array_values(array_unique(array_map('intval', (array)($_POST['courses'] ?? []))));
    $ids = array_values(array_filter($ids, fn($v) => $v > 0));

    if ($locked) {
        $errors[] = 'This student has already saved a date sheet. Courses can only be changed after you approve a date sheet change request.';
    } elseif (count($ids) < MIN_COURSES || count($ids) > MAX_COURSES) {
        $errors[] = 'A student must have between ' . MIN_COURSES . ' and ' . MAX_COURSES . ' courses. You selected ' . count($ids) . '.';
    } else {
        // every course must exist and be active (or already assigned to this student)
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $ok = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE id IN ($ph)
                             AND (status = 'active' OR id IN (SELECT course_id FROM assignments WHERE student_id = ?))");
        $ok->execute(array_merge($ids, [$studentId]));
        if ((int)$ok->fetchColumn() !== count($ids)) $errors[] = 'One or more selected courses are not available.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            // lock the student row and re-check the rule inside the transaction
            $lk = $pdo->prepare('SELECT sheet_saved, sheet_unlocked FROM students WHERE id = ? FOR UPDATE');
            $lk->execute([$studentId]);
            $fresh = $lk->fetch();
            if ($fresh['sheet_saved'] && !$fresh['sheet_unlocked']) throw new RuntimeException('locked');

            $c2 = $pdo->prepare('SELECT course_id FROM assignments WHERE student_id = ?');
            $c2->execute([$studentId]);
            $now = array_map('intval', $c2->fetchAll(PDO::FETCH_COLUMN));
            $remove = array_values(array_diff($now, $ids));
            $add    = array_values(array_diff($ids, $now));

            if ($remove) {
                $rp = implode(',', array_fill(0, count($remove), '?'));
                // a removed course must not stay in the student's chosen date sheet
                $pdo->prepare("DELETE FROM selections WHERE student_id = ? AND course_id IN ($rp)")->execute(array_merge([$studentId], $remove));
                $pdo->prepare("DELETE FROM assignments WHERE student_id = ? AND course_id IN ($rp)")->execute(array_merge([$studentId], $remove));
            }
            $ins = $pdo->prepare('INSERT INTO assignments (student_id, course_id) VALUES (?, ?)');
            foreach ($add as $cid) $ins->execute([$studentId, $cid]);
            $pdo->commit();
            flash_set('success', 'Courses saved for ' . $student['full_name'] . ' (' . count($ids) . ' assigned).');
            header('Location: assignments.php'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = ($e instanceof RuntimeException) ? 'This student has already saved a date sheet.' : 'Could not save the courses.';
        }
    }
    $assigned = $ids;   // keep the admin's ticks if validation failed
}

// Courses to show: all active ones + any inactive one the student already has. Also how many exam slots each has.
$cs = $pdo->prepare("SELECT c.id, c.code, c.title, c.credit_hours, c.status,
                            (SELECT COUNT(*) FROM slots sl WHERE sl.course_id = c.id) AS slot_count
                     FROM courses c
                     WHERE c.status = 'active' OR c.id IN (SELECT course_id FROM assignments WHERE student_id = ?)
                     ORDER BY c.code");
$cs->execute([$studentId]);
$courses = $cs->fetchAll();

$title = 'Assign Courses';
require __DIR__ . '/../includes/admin_header.php';
?>
<h3>Assign courses</h3>
<p class="mb-1"><strong><?= e($student['full_name']) ?></strong> &middot; <?= e($student['reg_no']) ?> &middot; <?= e($student['program']) ?></p>
<p class="text-muted small">Choose between <?= MIN_COURSES ?> and <?= MAX_COURSES ?> courses.</p>

<?php if ($locked): ?>
  <div class="alert alert-warning">This student has saved a date sheet, so assignments are locked. They unlock when you approve a "change date sheet" request.</div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card shadow-sm" id="assignForm">
  <?= csrf_field() ?>
  <input type="hidden" name="student_id" value="<?= (int)$studentId ?>">
  <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <input type="search" id="filter" class="form-control form-control-sm" style="max-width:260px" placeholder="Filter courses...">
    <span>Selected: <strong id="count">0</strong> / <?= MAX_COURSES ?></span>
  </div>
  <div class="list-group list-group-flush" id="courseList">
  <?php foreach ($courses as $c): ?>
    <label class="list-group-item d-flex gap-2 align-items-start course-row">
      <input type="checkbox" class="form-check-input mt-1" name="courses[]" value="<?= (int)$c['id'] ?>"
             <?= in_array((int)$c['id'], $assigned, true) ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
      <span class="flex-grow-1">
        <strong><?= e($c['code']) ?></strong> &mdash; <?= e($c['title']) ?>
        <span class="text-muted small">(<?= (int)$c['credit_hours'] ?> cr)</span>
        <?php if ($c['status'] !== 'active'): ?><span class="badge text-bg-secondary">inactive</span><?php endif; ?>
        <?php if (!$c['slot_count']): ?><span class="badge text-bg-warning">no exam slots yet</span><?php endif; ?>
      </span>
    </label>
  <?php endforeach; ?>
  <?php if (!$courses): ?><div class="list-group-item text-muted">No courses available. Add courses first.</div><?php endif; ?>
  </div>
  <div class="card-footer">
    <?php if (!$locked): ?><button class="btn btn-primary" id="saveBtn">Save courses</button><?php endif; ?>
    <a href="assignments.php" class="btn btn-outline-secondary">Back</a>
  </div>
</form>

<script>
// Convenience only. The server enforces the real 4-6 rule.
const MIN = <?= MIN_COURSES ?>, MAX = <?= MAX_COURSES ?>;
const boxes = [...document.querySelectorAll('input[name="courses[]"]')];
const countEl = document.getElementById('count'), saveBtn = document.getElementById('saveBtn');
function refresh() {
  const n = boxes.filter(b => b.checked).length;
  countEl.textContent = n;
  boxes.forEach(b => { if (!<?= $locked ? 'true' : 'false' ?>) b.disabled = !b.checked && n >= MAX; });
  if (saveBtn) saveBtn.disabled = n < MIN || n > MAX;
}
boxes.forEach(b => b.addEventListener('change', refresh));
document.getElementById('filter').addEventListener('input', e => {
  const t = e.target.value.toLowerCase();
  document.querySelectorAll('.course-row').forEach(r => r.style.display = r.textContent.toLowerCase().includes(t) ? '' : 'none');
});
refresh();
</script>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
