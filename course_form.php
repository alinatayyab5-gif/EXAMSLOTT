<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$data = ['code' => '', 'title' => '', 'credit_hours' => 3, 'department' => '', 'status' => 'active'];
$errors = [];

if ($id) {
    $st = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { flash_set('error', 'Course not found.'); header('Location: courses.php'); exit; }
    $data = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['code', 'title', 'department'] as $f) $data[$f] = trim($_POST[$f] ?? '');
    $data['credit_hours'] = $_POST['credit_hours'] ?? '';
    $data['status'] = $_POST['status'] ?? 'active';

    // ---- server-side validation ----
    if (!preg_match('/^[A-Za-z0-9\-]{2,20}$/', $data['code'])) $errors[] = 'Course code must be 2-20 letters, numbers or dashes.';
    if ($data['title'] === '' || mb_strlen($data['title']) > 150) $errors[] = 'Title is required (max 150 characters).';
    if (!ctype_digit((string)$data['credit_hours']) || $data['credit_hours'] < 1 || $data['credit_hours'] > 6)
        $errors[] = 'Credit hours must be a whole number from 1 to 6.';
    if (!in_array($data['status'], ['active', 'inactive'], true)) $errors[] = 'Invalid status.';

    if (!$errors) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE code = ? AND id <> ?');
        $st->execute([$data['code'], $id]);
        if ($st->fetchColumn() > 0) $errors[] = 'This course code already exists.';
    }

    if (!$errors) {
        $vals = [$data['code'], $data['title'], (int)$data['credit_hours'], $data['department'], $data['status']];
        if ($id) {
            $pdo->prepare('UPDATE courses SET code=?, title=?, credit_hours=?, department=?, status=? WHERE id=?')
                ->execute(array_merge($vals, [$id]));
            flash_set('success', 'Course updated.');
        } else {
            $pdo->prepare('INSERT INTO courses (code, title, credit_hours, department, status) VALUES (?,?,?,?,?)')
                ->execute($vals);
            flash_set('success', 'Course created.');
        }
        header('Location: courses.php'); exit;
    }
}

$title = $id ? 'Edit Course' : 'Add Course';
require __DIR__ . '/../includes/admin_header.php';
?>
<h3><?= e($title) ?></h3>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card card-body shadow-sm" style="max-width:640px">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row">
    <div class="col-md-5 mb-3"><label class="form-label">Course code *</label>
      <input name="code" class="form-control" value="<?= e($data['code']) ?>" required></div>
    <div class="col-md-7 mb-3"><label class="form-label">Credit hours *</label>
      <input type="number" name="credit_hours" min="1" max="6" class="form-control" value="<?= e($data['credit_hours']) ?>" required></div>
  </div>
  <div class="mb-3"><label class="form-label">Title *</label>
    <input name="title" class="form-control" value="<?= e($data['title']) ?>" required></div>
  <div class="row">
    <div class="col-md-7 mb-3"><label class="form-label">Department</label>
      <input name="department" class="form-control" value="<?= e($data['department']) ?>"></div>
    <div class="col-md-5 mb-3"><label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select></div>
  </div>
  <div><button class="btn btn-primary">Save</button> <a href="courses.php" class="btn btn-outline-secondary">Cancel</a></div>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
