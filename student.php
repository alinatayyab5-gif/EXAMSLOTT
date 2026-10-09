<?php
// Student-side helpers. Loaded by every student page (after auth.php).
require_once __DIR__ . '/auth.php';

// The logged-in student's record (always looked up by session user id, never by a URL parameter,
// so a student can only ever see their own data).
function current_student(PDO $pdo) {
    $st = $pdo->prepare('SELECT s.*, u.email, b.name AS branch_name, b.code AS branch_code, b.city AS branch_city
                         FROM students s
                         JOIN users u ON u.id = s.user_id
                         LEFT JOIN branches b ON b.id = s.branch_id
                         WHERE s.user_id = ?');
    $st->execute([(int)$_SESSION['user_id']]);
    $s = $st->fetch();
    if (!$s) { session_destroy(); header('Location: /examslot/login.php'); exit; }
    return $s;
}

// Branch page is open when no branch is chosen yet, or an admin-approved change is unlocked (single use).
function branch_open(array $s) { return $s['branch_id'] === null || (int)$s['branch_unlocked'] === 1; }
