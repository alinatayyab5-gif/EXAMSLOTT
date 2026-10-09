<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// ---------- LIST of students with their assignments: search + filter + server-side pagination ----------
$q       = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';
if (!in_array($status, ['', 'complete', 'incomplete'], true)) $status = '';
$page    = get_page();
$perPage = get_per_page();
$like    = '%' . $q . '%';

$where = '(s.full_name LIKE :a OR s.reg_no LIKE :b OR s.program LIKE :c)';
$cntSql = '(SELECT COUNT(*) FROM assignments x WHERE x.student_id = s.id)';
if ($status === 'incomplete') $where .= " AND $cntSql < 4";
if ($status === 'complete')   $where .= " AND $cntSql >= 4";

$cnt = $pdo->prepare("SELECT COUNT(*) FROM students s WHERE $where");
$cnt->execute([':a' => $like, ':b' => $like, ':c' => $like]);
$total  = (int)$cnt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT s.id, s.full_name, s.reg_no, s.program, s.sheet_saved, s.sheet_unlocked,
        $cntSql AS course_count,
        (SELECT GROUP_CONCAT(c.code ORDER BY c.code SEPARATOR ', ') FROM assignments a
           JOIN courses c ON c.id = a.course_id WHERE a.student_id = s.id) AS course_codes
      FROM students s WHERE $where ORDER BY s.id DESC LIMIT :lim OFFSET :off");
foreach ([':a', ':b', ':c'] as $k) $stmt->bindValue($k, $like);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$title = 'Assignments';
require __DIR__ . '/../includes/admin_header.php';
?>
<h3 class="mb-3">Course Assignments</h3>
<p class="text-muted small">Each student needs 4 to 6 courses before they can design a date sheet.</p>

<form class="row g-2 mb-3" method="get">
  <div class="col-12 col-md-5"><input name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search name, reg. no or program"></div>
  <div class="col-6 col-md-2">
    <select name="status" class="form-select">
      <option value="">All</option>
      <option value="incomplete" <?= $status === 'incomplete' ? 'selected' : '' ?>>Incomplete</option>
      <option value="complete" <?= $status === 'complete' ? 'selected' : '' ?>>Complete</option>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <select name="per_page" class="form-select">
      <?php foreach ([5,10,25,50] as $n): ?>
        <option value="<?= $n ?>" <?= $n === $perPage ? 'selected' : '' ?>><?= $n ?> / page</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-12 col-md-3"><button class="btn btn-dark">Search</button>
    <a href="assignments.php" class="btn btn-outline-secondary">Reset</a></div>
</form>

<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr>
    <th>Student</th><th>Reg. no</th><th>Assigned courses</th><th>Status</th><th class="text-end">Action</th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="5" class="text-center text-muted py-4">No students found.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $s): $n = (int)$s['course_count']; $locked = $s['sheet_saved'] && !$s['sheet_unlocked']; ?>
    <tr>
      <td><?= e($s['full_name']) ?><div class="small text-muted"><?= e($s['program']) ?></div></td>
      <td><?= e($s['reg_no']) ?></td>
      <td style="min-width:180px"><?= $n ? e($s['course_codes']) : '<span class="text-muted">None</span>' ?></td>
      <td>
        <?php if ($n >= 4): ?><span class="badge text-bg-success">Complete (<?= $n ?>)</span>
        <?php else: ?><span class="badge text-bg-danger">Assignment incomplete (<?= $n ?>)</span><?php endif; ?>
        <?php if ($locked): ?><span class="badge text-bg-secondary">Date sheet saved</span><?php endif; ?>
      </td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="assignment_form.php?student_id=<?= (int)$s['id'] ?>"><?= $locked ? 'View' : 'Manage courses' ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
  <small class="text-muted">Page <?= $page ?> of <?= $pages ?> &middot; <?= $total ?> result(s)</small>
  <?= render_pagination($page, $pages, ['q' => $q, 'status' => $status, 'per_page' => $perPage]) ?>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
