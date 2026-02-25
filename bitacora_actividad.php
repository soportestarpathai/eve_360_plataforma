<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
include 'templates/header.php';
?>
<title>Bitácora de actividad (SAT) - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/clientes.css">
</head>
<body>
<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>
<div class="content-wrapper">
    <div class="page-header">
        <div class="page-header-title">
            <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-clock-rotate-left me-2"></i>Bitácora de actividad de usuarios (SAT)</h2>
            <p class="text-muted">Registro de actividad ante el SAT — cumplimiento y trazabilidad</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fa-solid fa-screwdriver-wrench fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">En construcción</h5>
            <p class="mb-0 small text-muted">Aquí se mostrará la bitácora de actividad de usuarios en relación con el cumplimiento ante el SAT.</p>
        </div>
    </div>
</div>
<?php include 'templates/footer.php'; ?>
