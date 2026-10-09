<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Escape output (stops XSS). Use on EVERY echo of user data.
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// CSRF protection for forms
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403); exit('Invalid request.');
    }
}

// Role guards: call at the top of every protected page
function require_role($role) {
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== $role) {
        header('Location: /examslot/login.php'); exit;   // adjust base folder
    }
}
function require_admin()   { require_role('admin'); }
function require_student() { require_role('student'); }

require_once __DIR__ . '/helpers.php';
