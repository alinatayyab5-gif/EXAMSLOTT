<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// ---------- DELETE (POST + CSRF) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);

    // Safe-delete: block if the course is assigned to students or has exam slots
    $a = $pdo->prepare('SELECT COUNT(*) FROM assignments WHERE course_id = ?'); $a->execute([$id]);
    $s = $pdo->prepare('SELECT COUNT(*) FROM slots WHERE course_id = ?');       $s->execute([$id]);
    if ($a->fetchColumn() > 0 || $s->fetchColumn() > 0) {
        flash_set('error', 'This course is assigned to students or has exam slots, so it cannot be deleted. Set it to Inactive instead.');
    } else {
        try {
            $pdo->prepare('DELETE FROM courses WHERE id = ?')->execute([$id]);
            flash_set('success', 'Course deleted.');
        } catch (PDOException $e) {
            flash_set('error', 'Could not delete this course.');
        }
    }
    header('Location: courses.php'); exit;
}

// ---------- LIST: search + server-side pagination ----------
$q       = trim($_GET['q'] ?? '');
$page    = get_page();
$perPage = get_per_page();
$like    = '%' . $q . '%';

$cnt = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE code LIKE ? OR title LIKE ? OR department LIKE ?');
$cnt->execute([$like, $like, $like]);
$total  = (int)$cnt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare('SELECT * FROM courses WHERE code LIKE :q1 OR title LIKE :q2 OR department LIKE :q3
                       ORDER BY id DESC LIMIT :lim OFFSET :off');
$stmt->bindValue(':q1', $like); $stmt->bindValue(':q2', $like); $stmt->bindValue(':q3', $like);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$title = 'Courses';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h3 class="mb-0">Courses</h3>
  <a href="course_form.php" class="btn btn-primary">+ Add Course</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-12 col-md-6"><input name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search code, title or department"></div>
  <div class="col-6 col-md-2">
    <select name="per_page" class="form-select">
      <?php foreach ([5,10,25,50] as $n): ?>
        <option value="<?= $n ?>" <?= $n === $perPage ? 'selected' : '' ?>><?= $n ?> / page</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-4"><button class="btn btn-dark">Search</button>
    <a href="courses.php" class="btn btn-outline-secondary">Reset</a></div>
</form>

<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr>
    <th>Code</th><th>Title</th><th>Credits</th><th>Department</th><th>Status</th><th class="text-end">Actions</th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="6" class="text-center text-muted py-4">No courses found.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $c): ?>
    <tr>
      <td><?= e($c['code']) ?></td>
      <td><?= e($c['title']) ?></td>
      <td><?= (int)$c['credit_hours'] ?></td>
      <td><?= e($c['department']) ?></td>
      <td><span class="badge text-bg-<?= $c['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($c['status']) ?></span></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-primary" href="course_form.php?id=<?= (int)$c['id'] ?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this course?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
