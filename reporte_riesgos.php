<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
include 'templates/header.php';
?>
<title>Reporte de riesgos - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/clientes.css">
</head>
<body>
<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>
<div class="content-wrapper">
    <div class="page-header">
        <div class="page-header-title">
            <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-chart-line me-2"></i>Reporte de riesgos</h2>
            <p class="text-muted">Consultas y reportes por nivel de riesgo de clientes</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fa-solid fa-screwdriver-wrench fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">En construcción</h5>
            <p class="mb-0 small text-muted">Este reporte permitirá consultar y exportar información por nivel de riesgo de clientes.</p>
        </div>
    </div>
</div>
<?php include 'templates/footer.php'; ?>
