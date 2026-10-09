<?php
require_once __DIR__ . '/includes/auth.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$hash  = hash('sha256', (string)$token);
$errors = [];

// Token must exist, match, and not be expired
$st = $pdo->prepare('SELECT id FROM users WHERE reset_token_hash = ? AND reset_expires > NOW()');
$st->execute([$hash]);
$user = ($token !== '') ? $st->fetch() : false;

if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['confirm'] ?? '';
    if (strlen($p1) < 8 || !preg_match('/[A-Za-z]/', $p1) || !preg_match('/\d/', $p1))
        $errors[] = 'Password must be at least 8 characters and contain a letter and a number.';
    if ($p1 !== $p2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        // Single use: token is cleared in the same query that sets the password
        $up = $pdo->prepare('UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expires = NULL
                             WHERE id = ? AND reset_token_hash = ?');
        $up->execute([password_hash($p1, PASSWORD_DEFAULT), $user['id'], $hash]);
        if ($up->rowCount() === 1) {
            flash_set('success', 'Password set. You can now log in.');
            header('Location: login.php'); exit;
        }
        $errors[] = 'This link was already used. Please request a new one.';
    }
}
?>
<?php $title = 'Set Password'; require __DIR__ . '/includes/auth_top.php'; ?>
<?php if (!$user): ?>
  <h2>Link not valid</h2>
  <div class="ban er" style="margin-top:14px">This link is invalid, expired or has already been used.</div>
  <a href="forgot_password.php" class="btn p" style="width:100%;text-align:center">Request a new link</a>
<?php else: ?>
  <h2>Set your password</h2><p class="mu" style="margin-bottom:14px">Choose a password of at least 8 characters.</p>
  <?php foreach ($errors as $er): ?><div class="ban er"><?= e($er) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label>New password</label><input type="password" name="password" required minlength="8">
    <label>Confirm password</label><input type="password" name="confirm" required minlength="8">
    <button class="btn p" style="width:100%;margin-top:16px">Save password</button>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/auth_bottom.php'; ?>
