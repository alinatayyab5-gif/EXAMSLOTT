<?php
require_once __DIR__ . '/includes/auth.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if ($u && $u['password_hash'] && password_verify($pass, $u['password_hash'])) {
        session_regenerate_id(true);               // prevents session fixation
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['role']    = $u['role'];
        if ($u['role'] === 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            // students go to branch selection when it is open (first login, or an approved one-time change)
            $bs = $pdo->prepare('SELECT branch_id, branch_unlocked FROM students WHERE user_id = ?');
            $bs->execute([$u['id']]);
            $row = $bs->fetch();
            $open = $row && ($row['branch_id'] === null || (int)$row['branch_unlocked'] === 1);
            header('Location: ' . ($open ? 'student/branch.php' : 'student/dashboard.php'));
        }
        exit;
    }
    $error = 'Invalid email or password.';         // same message for both cases
}
?>
<?php $title = 'Login'; require __DIR__ . '/includes/auth_top.php'; ?>
<h2>Welcome back</h2><p class="mu" style="margin-bottom:18px">Sign in to your ExamSlot portal.</p>
<?php if ($f = flash_get()): ?><div class="ban <?= $f['type'] === 'success' ? 'ok' : 'er' ?>"><?= e($f['msg']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="ban er"><?= e($error) ?></div><?php endif; ?>
<form method="post">
  <?= csrf_field() ?>
  <label>Email</label><input type="email" name="email" autocomplete="username" required autofocus>
  <label>Password</label>
  <div class="ig"><input type="password" id="pw" name="password" autocomplete="current-password" required><button type="button" class="btn" id="tg" aria-label="Show or hide password">Show</button></div>
  <div class="lg-fp"><a href="forgot_password.php">Forgot password?</a></div>
  <button class="btn p" style="width:100%">Sign in</button>
</form>
<p class="lg-note" style="margin-top:22px">Accounts are created by the university administration. There is no self sign-up.</p>
<script>document.getElementById('tg').onclick=function(){var i=document.getElementById('pw'),s=i.type=='password';i.type=s?'text':'password';this.textContent=s?'Hide':'Show'};</script>
<?php require __DIR__ . '/includes/auth_bottom.php'; ?>
