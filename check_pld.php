<?php 
// Define base path for root files
$basePath = './';
include 'templates/header.php'; 

// VAL-PLD-001: Verificar habilitación PLD antes de permitir verificaciones
require_once __DIR__ . '/config/pld_validation.php';
require_once __DIR__ . '/config/pld_middleware.php';

$isPLDHabilitado = checkHabilitadoPLD($pdo);
if (!$isPLDHabilitado) {
    $validationResult = validatePatronPLD($pdo);
    ?>
    <title>Acceso Bloqueado - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
    </head>
    <body>
    <?php $is_sub_page = true; include 'templates/top_bar.php'; ?>
    <div class="container mt-5">
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading"><i class="fa-solid fa-ban me-2"></i>Operación Bloqueada</h4>
            <p><strong>NO HABILITADO PARA OPERAR PLD</strong></p>
            <p>El sujeto obligado no está habilitado para realizar verificaciones PLD.</p>
            <hr>
            <p class="mb-0">
                <strong>Razón:</strong> <?= htmlspecialchars($validationResult['razon'] ?? 'Validación de padrón PLD fallida') ?><br>
                <strong>Estatus:</strong> <?= htmlspecialchars($validationResult['estatus'] ?? 'NO_HABILITADO_PLD') ?>
            </p>
            <div class="mt-3">
                <a href="index.php" class="btn btn-primary">Volver al Dashboard</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                    // Verificar permisos de administración
                    try {
                        $stmt = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $perm = $stmt->fetchColumn();
                        if (!empty($perm) && $perm > 0):
                    ?>
                        <a href="admin/config.php#pld" class="btn btn-warning ms-2">Configurar Padrón PLD</a>
                    <?php endif; } catch (Exception $e) {} ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include 'templates/footer.php'; ?>
    <?php exit; }
?>
<title>Verificación Preventiva PLD - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/check_pld.css">
</head>
<body>

<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="container">
    <div class="check-card">
        <div class="check-header">
            <i class="fa-solid fa-shield-halved"></i>
            <div class="check-header-content">
                <h4 class="mb-0 fw-bold">Verificación Preventiva PLD</h4>
                <small class="text-muted">Consultar listas de riesgo y PEPs antes de vinculación.</small>
            </div>
        </div>

        <form id="pldForm">
            <div class="form-section-type">
                <label class="form-label fw-bold">Tipo de Persona a Consultar</label>
                <div class="radio-group">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tipo_persona" id="typeFisica" value="fisica" checked onchange="toggleType()">
                        <label class="form-check-label" for="typeFisica">Persona Física</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tipo_persona" id="typeMoral" value="moral" onchange="toggleType()">
                        <label class="form-check-label" for="typeMoral">Persona Moral</label>
                    </div>
                </div>
            </div>

            <!-- Fisica Fields -->
            <div id="fieldsFisica" class="persona-specific" style="display: block;">
                <div class="row persona-fields-row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nombre(s)*</label>
                        <input type="text" class="form-control" id="fisica_nombre" placeholder="Ej: Andres Manuel">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Apellido Paterno*</label>
                        <input type="text" class="form-control" id="fisica_paterno" placeholder="Ej: Lopez">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Apellido Materno</label>
                        <input type="text" class="form-control" id="fisica_materno" placeholder="Ej: Obrador">
                    </div>
                </div>
            </div>

            <!-- Moral Fields -->
            <div id="fieldsMoral" class="persona-specific">
                <div class="mb-3">
                    <label class="form-label">Razón Social*</label>
                    <input type="text" class="form-control" id="moral_razon" placeholder="Ej: Empresa Patito S.A. de C.V.">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-submit" id="btnCheck">
                <i class="fa-solid fa-magnifying-glass me-2"></i> Consultar Listas
            </button>
        </form>

        <!-- RESULTS AREA -->
        <div id="resultArea" class="result-box">
            <div class="result-header">
                <div id="resultIcon" class="result-icon"></div>
                <h5 class="fw-bold mb-0" id="resultTitle"></h5>
            </div>
            <hr>
            <div id="resultBody" class="result-body"></div>
            <div class="search-id-display">
                <small class="text-muted fst-italic" id="searchIdDisplay"></small>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/check_pld.js"></script>

<?php include 'templates/footer.php'; ?>