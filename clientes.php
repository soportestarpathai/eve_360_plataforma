<?php include 'templates/header.php'; ?>
<title>Clientes - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/clientes.css">
</head>
<body>

<?php 
$is_sub_page = true; // Show "Back" button
include 'templates/top_bar.php'; 
?>

<div class="content-wrapper">
    <div class="page-header">
        <div class="page-header-title">
            <h2 class="fw-bold text-primary mb-0">Cartera de Clientes</h2>
            <p class="text-muted">Gestión y seguimiento de expedientes</p>
        </div>
        <div class="page-header-actions">
            <a href="check_pld.php" class="btn btn-warning text-dark shadow-sm">
                <i class="fa-solid fa-shield-halved me-2"></i><span>Buscar en Listas</span>
            </a>
            
            <button onclick="initClientCreation()" class="btn btn-primary shadow-sm">
                <i class="fa-solid fa-user-plus me-2"></i><span>Nuevo Cliente</span>
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-header-eve">
                        <tr>
                            <th class="ps-4">Contrato</th>
                            <th>Cliente</th>
                            <th>Riesgo</th>
                            <th>RFC</th>
                            <th>Fecha Alta</th>
                            <th>Expediente PLD</th>
                            <th>Estatus</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="clientsTableBody">
                        <tr><td colspan="8" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando clientes...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="analysisModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa-solid fa-calculator me-2"></i>Análisis de Umbral PLD</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Ingrese los datos de la operación para determinar si es obligatorio identificar al cliente según las reglas de Actividad Vulnerable.
                </p>
                
                <form id="analysisForm">
                    <div class="mb-3" id="subactivityContainer" style="display:none;">
                        <label class="form-label fw-bold">Tipo de Servicio / Actividad</label>
                        <select class="form-select" id="ruleSelect">
                            </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Monto de la Operación (MXN)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="transactionAmount" placeholder="0.00" min="0" step="0.01">
                        </div>
                        <div class="form-text">Valor UMA actual: <span id="umaDisplay" class="fw-bold text-dark">-</span></div>
                    </div>

                    <div id="thresholdWarning" class="alert alert-warning border-warning d-none">
                        <div class="d-flex">
                            <i class="fa-solid fa-circle-exclamation fs-4 me-3"></i>
                            <div>
                                <strong>Atención:</strong> Para este cliente/transacción no se requiere la identificación del cliente.
                                <br><span class="small text-dark mt-1 d-block">¿Aún así quiere darlo de alta?</span>
                            </div>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">No, Cancelar</button>
                            <button type="button" class="btn btn-sm btn-warning fw-bold" onclick="proceedToCreate()">Sí, Continuar</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" id="modalFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="validateThreshold()">Analizar</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/clientes.js"></script>

<?php include 'templates/footer.php'; ?>