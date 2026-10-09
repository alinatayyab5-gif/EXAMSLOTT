<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// ---------- DELETE (POST + CSRF). Slots chosen by students are protected ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $n = $pdo->prepare('SELECT COUNT(*) FROM selections WHERE slot_id = ?');
    $n->execute([$id]);
    if ((int)$n->fetchColumn() > 0) {
        flash_set('error', 'This slot is chosen by students, so it cannot be deleted.');
    } else {
        try {
            $pdo->prepare('DELETE FROM slots WHERE id = ?')->execute([$id]);
            flash_set('success', 'Slot deleted.');
        } catch (PDOException $e) {
            flash_set('error', 'Could not delete this slot (it may be in use).');
        }
    }
    header('Location: slots.php'); exit;
}

// ---------- LIST: search + filters + server-side pagination ----------
$q        = trim($_GET['q'] ?? '');
$when     = $_GET['when'] ?? '';
if (!in_array($when, ['', 'upcoming', 'past'], true)) $when = '';
$courseId = (int)($_GET['course_id'] ?? 0);
$page     = get_page();
$perPage  = get_per_page();
$like     = '%' . $q . '%';

$where  = '(c.code LIKE :a OR c.title LIKE :b OR DATE_FORMAT(sl.exam_date, \'%Y-%m-%d\') LIKE :c)';
$params = [':a' => $like, ':b' => $like, ':c' => $like];
if ($when === 'upcoming') $where .= ' AND sl.exam_date >= CURDATE()';
if ($when === 'past')     $where .= ' AND sl.exam_date < CURDATE()';
if ($courseId > 0)      { $where .= ' AND sl.course_id = :cid'; $params[':cid'] = $courseId; }

$cnt = $pdo->prepare("SELECT COUNT(*) FROM slots sl JOIN courses c ON c.id = sl.course_id WHERE $where");
$cnt->execute($params);
$total  = (int)$cnt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT sl.id, sl.exam_date, sl.start_time, sl.end_time, c.code, c.title,
        (SELECT COUNT(*) FROM selections x WHERE x.slot_id = sl.id) AS chosen
      FROM slots sl JOIN courses c ON c.id = sl.course_id
      WHERE $where ORDER BY sl.exam_date, sl.start_time, c.code LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) $stmt->bindValue($k, $v, $k === ':cid' ? PDO::PARAM_INT : PDO::PARAM_STR);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$courseList = $pdo->query('SELECT id, code, title FROM courses ORDER BY code')->fetchAll();

$title = 'Exam Schedule';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h3 class="mb-0">Exam Schedule</h3>
  <a href="slot_form.php" class="btn btn-primary">+ Add Slot</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-12 col-md-4"><input name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search course or date (2026-12-01)"></div>
  <div class="col-12 col-md-3">
    <select name="course_id" class="form-select">
      <option value="0">All courses</option>
      <?php foreach ($courseList as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['code']) ?> - <?= e($c['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <select name="when" class="form-select">
      <option value="">All dates</option>
      <option value="upcoming" <?= $when === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
      <option value="past" <?= $when === 'past' ? 'selected' : '' ?>>Past</option>
    </select>
  </div>
  <div class="col-6 col-md-3">
    <select name="per_page" class="form-select">
      <?php foreach ([5,10,25,50] as $n): ?>
        <option value="<?= $n ?>" <?= $n === $perPage ? 'selected' : '' ?>><?= $n ?> / page</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-12"><button class="btn btn-dark">Search</button>
    <a href="slots.php" class="btn btn-outline-secondary">Reset</a></div>
</form>

<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr>
    <th>Course</th><th>Date</th><th>Time</th><th>Chosen by</th><th class="text-end">Actions</th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="5" class="text-center text-muted py-4">No slots found.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $s): $chosen = (int)$s['chosen']; $past = $s['exam_date'] < date('Y-m-d'); ?>
    <tr>
      <td><strong><?= e($s['code']) ?></strong><div class="small text-muted"><?= e($s['title']) ?></div></td>
      <td class="text-nowrap"><?= e(date('D, d M Y', strtotime($s['exam_date']))) ?>
        <?php if ($past): ?><span class="badge text-bg-secondary">past</span><?php endif; ?></td>
      <td class="text-nowrap"><?= e(substr($s['start_time'], 0, 5)) ?><?= $s['end_time'] ? ' - ' . e(substr($s['end_time'], 0, 5)) : '' ?></td>
      <td><?= $chosen ? '<span class="badge text-bg-primary">' . $chosen . ' student(s)</span>' : '<span class="text-muted">-</span>' ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-primary" href="slot_form.php?id=<?= (int)$s['id'] ?>"><?= $chosen ? 'View' : 'Edit' ?></a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this slot?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-sm btn-outline-danger" <?= $chosen ? 'disabled title="Chosen by students"' : '' ?>>Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
  <small class="text-muted">Page <?= $page ?> of <?= $pages ?> &middot; <?= $total ?> result(s)</small>
  <?= render_pagination($page, $pages, ['q' => $q, 'course_id' => $courseId, 'when' => $when, 'per_page' => $perPage]) ?>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
