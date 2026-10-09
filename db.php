<?php
// Put real credentials in environment/.env in production; never commit them.
$host = getenv('DB_HOST') ?: 'localhost';
$name = getenv('DB_NAME') ?: 'examslot';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
    ]);
} catch (PDOException $e) {
    exit('Database connection failed.');
}
