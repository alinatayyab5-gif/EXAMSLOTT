<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$slot = null;
if ($id) {
    $st = $pdo->prepare('SELECT * FROM slots WHERE id = ?');
    $st->execute([$id]);
    $slot = $st->fetch();
    if (!$slot) { flash_set('error', 'Slot not found.'); header('Location: slots.php'); exit; }
}

// A slot already chosen by students is read-only: moving it would silently change their date sheets.
$chosen = 0;
if ($slot) {
    $n = $pdo->prepare('SELECT COUNT(*) FROM selections WHERE slot_id = ?');
    $n->execute([$id]);
    $chosen = (int)$n->fetchColumn();
}

// Normalises "HH:MM" (or "HH:MM:SS") to "HH:MM:SS"; returns null if invalid
function norm_time($t) {
    $t = trim((string)$t);
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $t)) return null;
    return strlen($t) === 5 ? $t . ':00' : $t;
}
function valid_date($d) {
    $dt = DateTime::createFromFormat('Y-m-d', (string)$d);
    return $dt && $dt->format('Y-m-d') === $d;
}

$errors = [];
$f = [
    'course_id'  => $slot['course_id'] ?? '',
    'exam_date'  => $slot['exam_date'] ?? '',
    'start_time' => isset($slot['start_time']) ? substr($slot['start_time'], 0, 5) : '',
    'end_time'   => isset($slot['end_time']) && $slot['end_time'] ? substr($slot['end_time'], 0, 5) : '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if ($chosen) {
        $errors[] = 'This slot is chosen by ' . $chosen . ' student(s) and cannot be edited.';
    } else {
        $f['course_id']  = (int)($_POST['course_id'] ?? 0);
        $f['exam_date']  = trim($_POST['exam_date'] ?? '');
        $f['start_time'] = trim($_POST['start_time'] ?? '');
        $f['end_time']   = trim($_POST['end_time'] ?? '');

        // course must exist and be active (or already be this slot's course)
        $c = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND (status = 'active' OR id = ?)");
        $c->execute([$f['course_id'], (int)($slot['course_id'] ?? 0)]);
        if (!$c->fetch()) $errors[] = 'Choose a valid active course.';

        $start = norm_time($f['start_time']);
        $end   = $f['end_time'] === '' ? null : norm_time($f['end_time']);
        if (!valid_date($f['exam_date'])) $errors[] = 'Enter a valid exam date.';
        if ($start === null) $errors[] = 'Enter a valid start time.';
        if ($f['end_time'] !== '' && $end === null) $errors[] = 'Enter a valid end time.';

        // no slot in the past (today is allowed only if the start time is still ahead)
        if (!$errors) {
            if ($f['exam_date'] < date('Y-m-d')) {
                $errors[] = 'The exam date cannot be in the past.';
            } elseif ($f['exam_date'] === date('Y-m-d') && $start <= date('H:i:s')) {
                $errors[] = 'The start time has already passed today.';
            }
            if ($end !== null && $end <= $start) $errors[] = 'End time must be after start time.';
        }

        // no duplicate slot for the same course (same date + start time)
        if (!$errors) {
            $d = $pdo->prepare('SELECT COUNT(*) FROM slots WHERE course_id = ? AND exam_date = ? AND start_time = ? AND id <> ?');
            $d->execute([$f['course_id'], $f['exam_date'], $start, $id]);
            if ((int)$d->fetchColumn() > 0) $errors[] = 'This course already has a slot at that date and time.';
        }

        if (!$errors) {
            try {
                if ($id) {
                    $pdo->prepare('UPDATE slots SET course_id = ?, exam_date = ?, start_time = ?, end_time = ? WHERE id = ?')
                        ->execute([$f['course_id'], $f['exam_date'], $start, $end, $id]);
                    flash_set('success', 'Slot updated.');
                } else {
                    $pdo->prepare('INSERT INTO slots (course_id, exam_date, start_time, end_time) VALUES (?, ?, ?, ?)')
                        ->execute([$f['course_id'], $f['exam_date'], $start, $end]);
                    flash_set('success', 'Slot added.');
                }
                header('Location: slots.php'); exit;
            } catch (PDOException $e) {
                // the UNIQUE (course_id, exam_date, start_time) key is the last line of defence
                $errors[] = ($e->getCode() === '23000') ? 'This course already has a slot at that date and time.' : 'Could not save the slot.';
            }
        }
    }
}

$courses = $pdo->prepare("SELECT id, code, title FROM courses WHERE status = 'active' OR id = ? ORDER BY code");
$courses->execute([(int)($slot['course_id'] ?? 0)]);
$courses = $courses->fetchAll();

$title = $id ? 'Edit Slot' : 'Add Slot';
require __DIR__ . '/../includes/admin_header.php';
$ro = $chosen ? 'disabled' : '';
?>
<h3><?= e($title) ?></h3>

<?php if ($chosen): ?>
  <div class="alert alert-warning">This slot is chosen by <?= $chosen ?> student(s), so it is read-only. Add a new slot instead.</div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card shadow-sm" style="max-width:560px">
  <div class="card-body">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="mb-3">
      <label class="form-label">Course</label>
      <select name="course_id" class="form-select" required <?= $ro ?>>
        <option value="">Select a course</option>
        <?php foreach ($courses as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$f['course_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?> - <?= e($c['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Exam date</label>
      <input type="date" name="exam_date" class="form-control" min="<?= e(date('Y-m-d')) ?>" value="<?= e($f['exam_date']) ?>" required <?= $ro ?>>
    </div>
    <div class="row g-3 mb-3">
      <div class="col-6"><label class="form-label">Start time</label>
        <input type="time" name="start_time" class="form-control" value="<?= e($f['start_time']) ?>" required <?= $ro ?>></div>
      <div class="col-6"><label class="form-label">End time <span class="text-muted small">(optional)</span></label>
        <input type="time" name="end_time" class="form-control" value="<?= e($f['end_time']) ?>" <?= $ro ?>></div>
    </div>
  </div>
  <div class="card-footer">
    <?php if (!$chosen): ?><button class="btn btn-primary">Save slot</button><?php endif; ?>
    <a href="slots.php" class="btn btn-outline-secondary">Back</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
