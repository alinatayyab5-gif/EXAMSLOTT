<?php
// Run once from terminal:  php sql/seed_admin.php
require_once __DIR__ . '/../config/db.php';
$hash = password_hash('Admin@12345', PASSWORD_DEFAULT);
$pdo->prepare("INSERT IGNORE INTO users (email, password_hash, role) VALUES (?, ?, 'admin')")
    ->execute(['admin@examslot.test', $hash]);
echo "Admin created: admin@examslot.test / Admin@12345\n";
