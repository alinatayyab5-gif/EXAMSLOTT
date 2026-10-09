<?php
require_once __DIR__ . '/../includes/student.php';
require_student();

$student = current_student($pdo);
$sid = (int)$student['id'];
if ($student['branch_id'] === null) { header('Location: branch.php'); exit; }

$q = $pdo->prepare('SELECT c.code, c.title, sl.exam_date, sl.start_time, sl.end_time
                    FROM selections se
                    JOIN slots sl ON sl.id = se.slot_id
                    JOIN courses c ON c.id = se.course_id
                    WHERE se.student_id = ?
                    ORDER BY sl.exam_date, sl.start_time, c.code');
$q->execute([$sid]);
$rows = $q->fetchAll();

if (!$student['sheet_saved'] || !$rows) {
    flash_set('error', 'You have not saved a date sheet yet.');
    header('Location: dashboard.php'); exit;
}

$title = 'Date Sheet';
require __DIR__ . '/../includes/student_header.php';
?>
<style>
@media print {
  body { background: #fff !important; }
  .card { border: 0 !important; box-shadow: none !important; }
  .container { max-width: 100% !important; }
  table { font-size: 12pt; }
  a[href]:after { content: none !important; }
}
</style>

<div class="d-flex flex-wrap gap-2 mb-3 d-print-none">
  <button class="btn btn-primary" onclick="window.print()">Print Date Sheet</button>
  <a href="dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
</div>

<?php if ((int)$student['sheet_unlocked'] === 1): ?>
  <div class="alert alert-info d-print-none">An admin approved a change to your date sheet.
    <a href="dashboard.php">Edit it now</a> (one time only).</div>
<?php endif; ?>

<div class="card shadow-sm"><div class="card-body">
  <div class="text-center mb-3">
    <h4 class="mb-0">Exam Date Sheet</h4>
    <div class="text-muted">Virtual University &middot; ExamSlot</div>
  </div>
  <div class="row g-2 mb-3">
    <div class="col-12 col-md-6"><strong>Name:</strong> <?= e($student['full_name']) ?></div>
    <div class="col-12 col-md-6"><strong>Registration no.:</strong> <?= e($student['reg_no']) ?></div>
    <div class="col-12 col-md-6"><strong>Program:</strong> <?= e($student['program']) ?></div>
    <div class="col-12 col-md-6"><strong>Exam branch:</strong> <?= e($student['branch_name']) ?> (<?= e($student['branch_city']) ?>)</div>
  </div>
  <div class="table-responsive">
    <table class="table table-bordered align-middle mb-0">
      <thead class="table-light"><tr><th>#</th><th>Course code</th><th>Course title</th><th>Date</th><th>Day</th><th>Time</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $i => $r): $ts = strtotime($r['exam_date']); ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td class="text-nowrap"><?= e($r['code']) ?></td>
          <td><?= e($r['title']) ?></td>
          <td class="text-nowrap"><?= e(date('d M Y', $ts)) ?></td>
          <td><?= e(date('l', $ts)) ?></td>
          <td class="text-nowrap"><?= e(substr($r['start_time'], 0, 5)) ?><?= $r['end_time'] ? ' - ' . e(substr($r['end_time'], 0, 5)) : '' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="small text-muted mt-3">Generated on <?= e(date('d M Y, H:i')) ?>. Please carry your university ID card to the exam hall.</div>
</div></div>
<?php require __DIR__ . '/../includes/student_footer.php'; ?>
