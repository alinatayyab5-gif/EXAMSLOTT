<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();

// ---------- POST actions: delete / resend setup email ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare('SELECT s.user_id, s.full_name, s.sheet_saved, u.email FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
    $st->execute([$id]);
    $stu = $st->fetch();

    if (!$stu) {
        flash_set('error', 'Student not found.');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        if ($stu['sheet_saved']) {
            flash_set('error', 'This student has a saved date sheet and cannot be deleted.');
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$stu['user_id']]); // cascades to the student row
            flash_set('success', 'Student deleted.');
        }
    } elseif (($_POST['action'] ?? '') === 'resend') {
        [$sent, $link] = send_setup_email($pdo, (int)$stu['user_id'], $stu['email'], $stu['full_name']);
        flash_set($sent ? 'success' : 'error',
            $sent ? 'Setup email sent.' . (mail_cfg('mode') === 'log' ? ' (Dev mode) Link: ' . $link : '')
                  : 'Could not send the email. Check your mail settings.');
    }
    header('Location: students.php'); exit;
}

// ---------- LIST: search + server-side pagination ----------
$q       = trim($_GET['q'] ?? '');
$page    = get_page();
$perPage = get_per_page();
$like    = '%' . $q . '%';
$where   = '(s.full_name LIKE :a OR s.reg_no LIKE :b OR u.email LIKE :c OR s.program LIKE :d)';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM students s JOIN users u ON u.id = s.user_id WHERE $where");
$cnt->execute([':a' => $like, ':b' => $like, ':c' => $like, ':d' => $like]);
$total  = (int)$cnt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT s.id, s.full_name, s.reg_no, s.program, s.sheet_saved, u.email,
                              (u.password_hash IS NOT NULL) AS pw_set, b.name AS branch_name,
                              (SELECT COUNT(*) FROM assignments a WHERE a.student_id = s.id) AS course_count
                       FROM students s JOIN users u ON u.id = s.user_id
                       LEFT JOIN branches b ON b.id = s.branch_id
                       WHERE $where ORDER BY s.id DESC LIMIT :lim OFFSET :off");
foreach ([':a', ':b', ':c', ':d'] as $k) $stmt->bindValue($k, $like);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$title = 'Students';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h3 class="mb-0">Students</h3>
  <a href="student_form.php" class="btn btn-primary">+ Add Student</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-12 col-md-6"><input name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search name, reg. no, email or program"></div>
  <div class="col-6 col-md-2">
    <select name="per_page" class="form-select">
      <?php foreach ([5,10,25,50] as $n): ?>
        <option value="<?= $n ?>" <?= $n === $perPage ? 'selected' : '' ?>><?= $n ?> / page</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-4"><button class="btn btn-dark">Search</button>
    <a href="students.php" class="btn btn-outline-secondary">Reset</a></div>
</form>

<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr>
    <th>Name</th><th>Reg. no</th><th>Email</th><th>Account</th><th>Courses</th><th>Date sheet</th><th class="text-end">Actions</th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="7" class="text-center text-muted py-4">No students found.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $s): ?>
    <tr>
      <td><?= e($s['full_name']) ?><div class="small text-muted"><?= e($s['program']) ?></div></td>
      <td><?= e($s['reg_no']) ?></td>
      <td><?= e($s['email']) ?></td>
      <td><span class="badge text-bg-<?= $s['pw_set'] ? 'success' : 'warning' ?>"><?= $s['pw_set'] ? 'Password set' : 'Setup pending' ?></span></td>
      <td>
        <?= (int)$s['course_count'] ?>
        <?php if ($s['course_count'] < 4): ?><span class="badge text-bg-danger">Assignment incomplete</span><?php endif; ?>
      </td>
      <td><span class="badge text-bg-<?= $s['sheet_saved'] ? 'success' : 'secondary' ?>"><?= $s['sheet_saved'] ? 'Saved' : 'Not saved' ?></span>
          <?php if ($s['branch_name']): ?><div class="small text-muted"><?= e($s['branch_name']) ?></div><?php endif; ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-primary" href="student_form.php?id=<?= (int)$s['id'] ?>">Edit</a>
        <a class="btn btn-sm btn-outline-success" href="assignment_form.php?student_id=<?= (int)$s['id'] ?>">Courses</a>
        <?php if (!$s['pw_set']): ?>
        <form method="post" class="d-inline">
          <?= csrf_field() ?><input type="hidden" name="action" value="resend"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-sm btn-outline-secondary">Resend email</button>
        </form>
        <?php endif; ?>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this student and all their data?');">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-sm btn-outline-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
  <small class="text-muted">Page <?= $page ?> of <?= $pages ?> &middot; <?= $total ?> result(s)</small>
  <?= render_pagination($page, $pages, ['q' => $q, 'per_page' => $perPage]) ?>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
