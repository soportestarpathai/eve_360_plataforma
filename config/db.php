<?php
// config/db.php

$host = '127.0.0.1'; // Or your IP: 70.35.200.34 if connecting remotely
$db   = 'investor';
$user = 'root';      // Replace with your actual database username
$pass = '1234';          // Replace with your actual database password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // In production, log this to a file instead of echoing
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>