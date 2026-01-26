<?php
session_start();
// Security Check: Ensure user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: ../admin/login.php"); // Redirect to root login if not admin
    exit;
}
require_once '../config/db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<div class="sidebar">
    <div class="text-center mb-4 text-white fw-bold">
        <i class="fa-solid fa-shield-cat"></i> ADMIN CONSOLE
    </div>
    
    <div class="sidebar-header">Principal</div>
    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF'])=='index.php'?'active':'' ?>">
        <i class="fa-solid fa-gauge me-2"></i>Dashboard
    </a>

    <div class="sidebar-header">Administración</div>
    <a href="users.php" class="<?= basename($_SERVER['PHP_SELF'])=='users.php'?'active':'' ?>">
        <i class="fa-solid fa-users me-2"></i>Usuarios
    </a>
    <a href="config.php" class="<?= basename($_SERVER['PHP_SELF'])=='config.php'?'active':'' ?>">
        <i class="fa-solid fa-gears me-2"></i>Configuración
    </a>

    <div class="sidebar-header">Operación</div>
    <a href="modulos.php" class="<?= basename($_SERVER['PHP_SELF'])=='modulos.php'?'active':'' ?>">
        <i class="fa-solid fa-cubes me-2"></i>Módulos
    </a>
    <a href="reportes.php" class="<?= basename($_SERVER['PHP_SELF'])=='reportes.php'?'active':'' ?>">
        <i class="fa-solid fa-chart-pie me-2"></i>Reportes
    </a>

    <a href="logout.php" class="mt-5 text-warning">
        <i class="fa-solid fa-right-from-bracket me-2"></i>Salir
    </a>
</div>

<div class="main-content">