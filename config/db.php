<?php
// config/db.php

$host = 'localhost'; // Cambia 127.0.0.1 por localhost para mayor compatibilidad
$db   = 'investor';
$user = 'root';      
$pass = 'Antoniomtz1022';          // IMPORTANTE: Deja esto vacío para XAMPP estándar
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
    // Esto te dará un mensaje más claro si vuelve a fallar
    die("Error de conexión: " . $e->getMessage());
}