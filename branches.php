<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// ---------- DELETE (POST only, CSRF protected) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);

    // Safe-delete rule: a branch chosen by students cannot be deleted
    $st = $pdo->prepare('SELECT COUNT(*) FROM students WHERE branch_id = ?');
    $st->execute([$id]);
    if ($st->fetchColumn() > 0) {
        flash_set('error', 'This branch is chosen by students and cannot be deleted. Set it to Inactive instead.');
    } else {
        try {
            $pdo->prepare('DELETE FROM branches WHERE id = ?')->execute([$id]);
            flash_set('success', 'Branch deleted.');
        } catch (PDOException $e) {
            flash_set('error', 'Could not delete this branch.');
        }
    }
    header('Location: branches.php'); exit;
}

// ---------- LIST: search + SERVER-SIDE pagination ----------
$q        = trim($_GET['q'] ?? '');
$page     = get_page();
$perPage  = get_per_page();
$like     = '%' . $q . '%';

// 1) total of the FILTERED rows (so page counter matches the search)
$cnt = $pdo->prepare('SELECT COUNT(*) FROM branches WHERE name LIKE ? OR code LIKE ? OR city LIKE ?');
$cnt->execute([$like, $like, $like]);
$total = (int)$cnt->fetchColumn();

$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);                 // clamp if page is too big
$offset = ($page - 1) * $perPage;

// 2) only this page's rows
$stmt = $pdo->prepare('SELECT * FROM branches WHERE name LIKE :q1 OR code LIKE :q2 OR city LIKE :q3
                       ORDER BY id DESC LIMIT :lim OFFSET :off');
$stmt->bindValue(':q1', $like); $stmt->bindValue(':q2', $like); $stmt->bindValue(':q3', $like);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$title = 'Branches';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h3 class="mb-0">Branches</h3>
  <a href="branch_form.php" class="btn btn-primary">+ Add Branch</a>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-12 col-md-6"><input name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search name, code or city"></div>
  <div class="col-6 col-md-2">
    <select name="per_page" class="form-select">
      <?php foreach ([5,10,25,50] as $n): ?>
        <option value="<?= $n ?>" <?= $n === $perPage ? 'selected' : '' ?>><?= $n ?> / page</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6 col-md-4"><button class="btn btn-dark">Search</button>
    <a href="branches.php" class="btn btn-outline-secondary">Reset</a></div>
</form>

<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr>
    <th>Name</th><th>Code</th><th>City</th><th>Contact</th><th>Status</th><th class="text-end">Actions</th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="6" class="text-center text-muted py-4">No branches found.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $b): ?>
    <tr>
      <td><?= e($b['name']) ?></td>
      <td><?= e($b['code']) ?></td>
      <td><?= e($b['city']) ?></td>
      <td><?= e($b['contact']) ?></td>
      <td><span class="badge text-bg-<?= $b['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e($b['status']) ?></span></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-primary" href="branch_form.php?id=<?= (int)$b['id'] ?>">Edit</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete this branch?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
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
