<?php /* Expects $title (and optional $__cur) set by the page */ $flash = flash_get(); $__cur = basename($_SERVER['SCRIPT_NAME'], '.php'); $__root = (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'admin' || basename(dirname($_SERVER['SCRIPT_NAME'])) === 'student') ? '../' : ''; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Student') ?> | ExamSlot</title>
  <script src="<?= $__root ?>js/theme.js"></script>
  <link rel="stylesheet" href="<?= $__root ?>css/style.css">
</head>
<body class="stu">
<div id="app">

<header class="top"><button class="ham" type="button" onclick="document.body.classList.toggle('open')">&#9776;</button><b>ExamSlot</b><nav><a class="nv <?= $__cur==='dashboard' ? 'on' : '' ?>" href="dashboard.php">Dashboard</a></nav><span class="sp"></span>
  <button type="button" class="ib" onclick="toggleTheme()" aria-label="Switch light or dark theme" title="Switch light / dark"><svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><svg class="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z"/></svg></button>
  <a class="btn s" href="../logout.php">Logout</a></header>
<main class="main">
<?php if ($flash): ?>
  <div class="alert d-print-none alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><?= e($flash['msg']) ?></div>
<?php endif; ?>
