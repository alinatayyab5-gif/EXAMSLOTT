<?php
require_once __DIR__ . '/../includes/student.php';
require_student();

$student = current_student($pdo);
$isChange = $student['branch_id'] !== null;     // true when this is an approved change, not the first pick

// Backend rule: once a branch is saved the page is closed (until an admin unlock).
if (!branch_open($student)) {
    flash_set('error', 'You have already selected your exam branch.');
    header('Location: dashboard.php'); exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $bid = (int)($_POST['branch_id'] ?? 0);

    $b = $pdo->prepare("SELECT id, name FROM branches WHERE id = ? AND status = 'active'");
    $b->execute([$bid]);
    $branch = $b->fetch();
    if (!$branch) {
        $errors[] = 'Please choose one of the available branches.';
    } else {
        try {
            $pdo->beginTransaction();
            // Lock this student's row and re-check inside the transaction, so two simultaneous
            // requests (double click, second tab, direct API call) cannot both succeed.
            $lk = $pdo->prepare('SELECT branch_id, branch_unlocked FROM students WHERE id = ? FOR UPDATE');
            $lk->execute([(int)$student['id']]);
            $fresh = $lk->fetch();
            if ($fresh['branch_id'] !== null && !(int)$fresh['branch_unlocked']) throw new RuntimeException('closed');

            // Saving uses up the one-time unlock (branch_unlocked back to 0)
            $pdo->prepare('UPDATE students SET branch_id = ?, branch_unlocked = 0 WHERE id = ?')
                ->execute([$bid, (int)$student['id']]);
            $pdo->commit();
            flash_set('success', 'Exam branch saved: ' . $branch['name'] . '.');
            header('Location: dashboard.php'); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof RuntimeException) {
                flash_set('error', 'You have already selected your exam branch.');
                header('Location: dashboard.php'); exit;
            }
            $errors[] = 'Could not save your branch. Please try again.';
        }
    }
}

$branches = $pdo->query("SELECT id, name, code, city, address, contact FROM branches WHERE status = 'active' ORDER BY city, name")->fetchAll();

$title = 'Select Exam Branch';
require __DIR__ . '/../includes/student_header.php';
?>
<h3>Select your exam branch</h3>
<?php if ($isChange): ?>
  <div class="alert alert-info">Your branch change request was approved. You can change your branch <strong>one time</strong>.
    Your current branch is <strong><?= e($student['branch_name']) ?></strong>.</div>
<?php else: ?>
  <div class="alert alert-warning">Choose the branch where you will sit your papers. <strong>You can do this only once.</strong>
    Later changes need admin approval.</div>
<?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (!$branches): ?>
  <div class="alert alert-secondary">No branches are open right now. Please contact the administration.</div>
<?php else: ?>
<form method="post" id="branchForm">
  <?= csrf_field() ?>
  <div class="row g-3">
  <?php foreach ($branches as $b): ?>
    <div class="col-12 col-md-6 col-lg-4">
      <label class="card h-100 shadow-sm" style="cursor:pointer">
        <div class="card-body d-flex gap-2">
          <input type="radio" name="branch_id" value="<?= (int)$b['id'] ?>" class="form-check-input mt-1" required
                 <?= (int)$student['branch_id'] === (int)$b['id'] ? 'checked' : '' ?>>
          <div>
            <div class="fw-bold"><?= e($b['name']) ?> <span class="text-muted small">(<?= e($b['code']) ?>)</span></div>
            <div><?= e($b['city']) ?></div>
            <div class="small text-muted"><?= e($b['address']) ?></div>
            <div class="small text-muted"><?= e($b['contact']) ?></div>
          </div>
        </div>
      </label>
    </div>
  <?php endforeach; ?>
  </div>
  <div class="mt-4 d-flex gap-2 flex-wrap">
    <button class="btn btn-primary">Save branch</button>
    <?php if ($isChange): ?><a href="dashboard.php" class="btn btn-outline-secondary">Keep my current branch for now</a><?php endif; ?>
  </div>
</form>
<script>
document.getElementById('branchForm').addEventListener('submit', function (e) {
  const r = document.querySelector('input[name="branch_id"]:checked');
  if (!r) return;
  const name = r.closest('.card').querySelector('.fw-bold').textContent.trim();
  if (!confirm('Save "' + name + '" as your exam branch? You cannot change it later without admin approval.')) e.preventDefault();
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/student_footer.php'; ?>
