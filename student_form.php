<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/student_fields.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$allKeys = [];
foreach ($STUDENT_GROUPS as $fields) foreach ($fields as $k => $_) $allKeys[] = $k;
$dbKeys = array_values(array_diff($allKeys, ['email']));      // columns of the students table
$data = array_fill_keys($allKeys, '');
$userId = 0;
$errors = [];

if ($id) {
    $st = $pdo->prepare('SELECT s.*, u.email FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { flash_set('error', 'Student not found.'); header('Location: students.php'); exit; }
    foreach ($allKeys as $k) $data[$k] = $row[$k] ?? '';
    $userId = (int)$row['user_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($allKeys as $k) $data[$k] = trim($_POST[$k] ?? '');

    // ---- validation (server side) ----
    foreach ($STUDENT_GROUPS as $fields) foreach ($fields as $k => [$label]) {
        if ($data[$k] === '') $errors[] = "$label is required.";
        elseif (mb_strlen($data[$k]) > 150) $errors[] = "$label is too long.";
    }
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is not valid.';
    foreach (['phone', 'parent_contact', 'emergency_contact'] as $k)
        if ($data[$k] !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $data[$k])) $errors[] = 'Phone numbers must be 7-20 digits (+ and - allowed).';
    foreach (['cnic', 'parent_cnic'] as $k)
        if ($data[$k] !== '' && !preg_match('/^[0-9\-]{13,15}$/', $data[$k])) $errors[] = 'CNIC / B-Form must be 13 digits (dashes allowed).';
    if ($data['dob'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $data['dob']);
        if (!$d || $d->format('Y-m-d') !== $data['dob'] || $d >= new DateTime('today')) $errors[] = 'Date of birth must be a valid past date.';
    }
    if ($data['gender'] !== '' && !in_array($data['gender'], $STUDENT_GENDERS, true)) $errors[] = 'Invalid gender.';
    if ($data['reg_no'] !== '' && !preg_match('/^[A-Za-z0-9\-\/]{3,30}$/', $data['reg_no'])) $errors[] = 'Registration number format is not valid.';
    if ($data['semester'] !== '' && (!ctype_digit($data['semester']) || $data['semester'] < 1 || $data['semester'] > 12)) $errors[] = 'Semester must be 1 to 12.';
    if ($data['cgpa'] !== '' && (!is_numeric($data['cgpa']) || $data['cgpa'] < 0 || $data['cgpa'] > 100)) $errors[] = 'Marks / CGPA must be a number from 0 to 100.';
    $errors = array_values(array_unique($errors));

    if (!$errors) {
        $q = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
        $q->execute([$data['email'], $userId]);
        if ($q->fetchColumn() > 0) $errors[] = 'This email is already used.';
        $q = $pdo->prepare('SELECT COUNT(*) FROM students WHERE reg_no = ? AND id <> ?');
        $q->execute([$data['reg_no'], $id]);
        if ($q->fetchColumn() > 0) $errors[] = 'This registration number already exists.';
    }

    if (!$errors) {
        $vals = array_map(fn($k) => $data[$k], $dbKeys);
        try {
            $pdo->beginTransaction();
            if ($id) {
                $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$data['email'], $userId]);
                $set = implode(', ', array_map(fn($k) => "$k = ?", $dbKeys));
                $pdo->prepare("UPDATE students SET $set WHERE id = ?")->execute(array_merge($vals, [$id]));
                $pdo->commit();
                flash_set('success', 'Student updated.');
            } else {
                $pdo->prepare("INSERT INTO users (email, role) VALUES (?, 'student')")->execute([$data['email']]);
                $newUser = (int)$pdo->lastInsertId();
                $cols = implode(', ', $dbKeys);
                $ph   = implode(', ', array_fill(0, count($dbKeys), '?'));
                $pdo->prepare("INSERT INTO students (user_id, $cols) VALUES (?, $ph)")->execute(array_merge([$newUser], $vals));
                $pdo->commit();

                // Account created: email the password-setup link (after commit, so a mail problem never loses the student)
                [$sent, $link] = send_setup_email($pdo, $newUser, $data['email'], $data['full_name']);
                if ($sent) flash_set('success', 'Student created and setup email sent.' . (mail_cfg('mode') === 'log' ? ' (Dev mode) Link: ' . $link : ''));
                else flash_set('error', 'Student created, but the email could not be sent. Use "Resend email" after fixing mail settings.');
            }
            header('Location: students.php'); exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = ($e->getCode() === '23000') ? 'Email or registration number already exists.' : 'Could not save the student.';
        }
    }
}

$title = $id ? 'Edit Student' : 'Add Student';
require __DIR__ . '/../includes/admin_header.php';
?>
<h3><?= e($title) ?></h3>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card card-body shadow-sm">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php foreach ($STUDENT_GROUPS as $group => $fields): ?>
    <h5 class="mt-2 mb-3 border-bottom pb-2"><?= e($group) ?></h5>
    <div class="row">
    <?php foreach ($fields as $k => [$label, $type]): ?>
      <div class="col-12 col-md-6 mb-3">
        <label class="form-label"><?= e($label) ?> *</label>
        <?php if ($type === 'select'): ?>
          <select name="<?= e($k) ?>" class="form-select" required>
            <option value="">Select...</option>
            <?php foreach ($STUDENT_GENDERS as $g): ?>
              <option value="<?= e($g) ?>" <?= $data[$k] === $g ? 'selected' : '' ?>><?= e($g) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="<?= e($type) ?>" name="<?= e($k) ?>" value="<?= e($data[$k]) ?>" class="form-control" required
                 <?= $type === 'number' ? 'min="1" max="12"' : '' ?>>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$id): ?><div class="alert alert-info py-2">After saving, an email with a password setup link is sent to the student.</div><?php endif; ?>
  <div><button class="btn btn-primary">Save</button> <a href="students.php" class="btn btn-outline-secondary">Cancel</a></div>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
