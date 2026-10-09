<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$data = ['name' => '', 'code' => '', 'city' => '', 'address' => '', 'contact' => '', 'status' => 'active'];
$errors = [];

// Edit mode: load existing branch
if ($id) {
    $st = $pdo->prepare('SELECT * FROM branches WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { flash_set('error', 'Branch not found.'); header('Location: branches.php'); exit; }
    $data = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name', 'code', 'city', 'address', 'contact'] as $f) $data[$f] = trim($_POST[$f] ?? '');
    $data['status'] = $_POST['status'] ?? 'active';

    // ---- validation (server side) ----
    if ($data['name'] === '' || mb_strlen($data['name']) > 100) $errors[] = 'Name is required (max 100 characters).';
    if (!preg_match('/^[A-Za-z0-9\-]{2,20}$/', $data['code'])) $errors[] = 'Code must be 2-20 letters, numbers or dashes.';
    if ($data['city'] === '') $errors[] = 'City is required.';
    if ($data['contact'] !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $data['contact'])) $errors[] = 'Contact number is not valid.';
    if (!in_array($data['status'], ['active', 'inactive'], true)) $errors[] = 'Invalid status.';

    // unique code (ignore this same record when editing)
    if (!$errors) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM branches WHERE code = ? AND id <> ?');
        $st->execute([$data['code'], $id]);
        if ($st->fetchColumn() > 0) $errors[] = 'This branch code already exists.';
    }

    if (!$errors) {
        if ($id) {
            $pdo->prepare('UPDATE branches SET name=?, code=?, city=?, address=?, contact=?, status=? WHERE id=?')
                ->execute([$data['name'], $data['code'], $data['city'], $data['address'], $data['contact'], $data['status'], $id]);
            flash_set('success', 'Branch updated.');
        } else {
            $pdo->prepare('INSERT INTO branches (name, code, city, address, contact, status) VALUES (?,?,?,?,?,?)')
                ->execute([$data['name'], $data['code'], $data['city'], $data['address'], $data['contact'], $data['status']]);
            flash_set('success', 'Branch created.');
        }
        header('Location: branches.php'); exit;
    }
}

$title = $id ? 'Edit Branch' : 'Add Branch';
require __DIR__ . '/../includes/admin_header.php';
?>
<h3><?= e($title) ?></h3>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="card card-body shadow-sm" style="max-width:640px">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="mb-3"><label class="form-label">Branch name *</label>
    <input name="name" class="form-control" value="<?= e($data['name']) ?>" required></div>
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label">Code *</label>
      <input name="code" class="form-control" value="<?= e($data['code']) ?>" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">City *</label>
      <input name="city" class="form-control" value="<?= e($data['city']) ?>" required></div>
  </div>
  <div class="mb-3"><label class="form-label">Address</label>
    <input name="address" class="form-control" value="<?= e($data['address']) ?>"></div>
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label">Contact number</label>
      <input name="contact" class="form-control" value="<?= e($data['contact']) ?>"></div>
    <div class="col-md-6 mb-3"><label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select></div>
  </div>
  <div><button class="btn btn-primary">Save</button> <a href="branches.php" class="btn btn-outline-secondary">Cancel</a></div>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
