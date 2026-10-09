<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
$title = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>
<h3>Admin Dashboard</h3>
<p class="text-muted">Use the menu above to manage branches, courses, students and schedules.</p>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
