<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';

$done = false; $devLink = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $st = $pdo->prepare("SELECT u.id, COALESCE(s.full_name, 'Student') AS name FROM users u
                         LEFT JOIN students s ON s.user_id = u.id WHERE u.email = ? AND u.role = 'student'");
    $st->execute([$email]);
    if ($u = $st->fetch()) {
        [, $link] = send_setup_email($pdo, (int)$u['id'], $email, $u['name'], true);
        if (mail_cfg('mode') === 'log') $devLink = $link;   // dev mode only: lets you test without SMTP
    }
    $done = true;   // same message whether or not the email exists (no account guessing)
}
?>
<?php $title = 'Forgot password'; require __DIR__ . '/includes/auth_top.php'; ?>
<h2>Forgot password</h2><p class="mu" style="margin-bottom:18px">We will email you a link to set a new password.</p>
<?php if ($done): ?>
  <div class="ban ok">If that email belongs to a student account, a password link has been sent.</div>
  <?php if ($devLink): ?><div class="ban w">Dev mode link: <a href="<?= e($devLink) ?>"><?= e($devLink) ?></a></div><?php endif; ?>
<?php endif; ?>
<form method="post">
  <?= csrf_field() ?>
  <label>Email</label><input type="email" name="email" required autofocus>
  <button class="btn p" style="width:100%;margin-top:16px">Send link</button>
</form>
<p class="lg-note" style="margin-top:18px"><a href="login.php">Back to login</a></p>
<?php require __DIR__ . '/includes/auth_bottom.php'; ?>
