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
        <div class="page-header-actions clients-toolbar">
            <div class="filters-toolbar d-flex flex-wrap align-items-center gap-2">
                <div class="input-group input-group-sm search-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Buscar cliente, RFC o contrato">
                    <button id="searchBtn" class="btn btn-outline-primary" type="button">Buscar</button>
                </div>

                <select id="tipoPersonaFilter" class="form-select form-select-sm filter-select">
                    <option value="">Tipo persona</option>
                    <option value="fisica">Física</option>
                    <option value="moral">Moral</option>
                    <option value="fideicomiso">Fideicomiso</option>
                </select>

                <select id="riesgoFilter" class="form-select form-select-sm filter-select">
                    <option value="">Nivel riesgo</option>
                    <option value="bajo">Bajo</option>
                    <option value="medio">Medio</option>
                    <option value="alto">Alto</option>
                    <option value="sin_calcular">Sin calcular</option>
                </select>

                <select id="expedienteFilter" class="form-select form-select-sm filter-select">
                    <option value="">Expediente PLD</option>
                    <option value="completo">Completo</option>
                    <option value="incompleto">Incompleto</option>
                </select>

                <select id="estatusFilter" class="form-select form-select-sm filter-select">
                    <option value="">Estatus cliente</option>
                    <option value="activos">Activo</option>
                    <option value="inactivos">Inactivo</option>
                    <option value="cancelados">Cancelado</option>
                    <option value="pendientes">Pendiente</option>
                </select>

                <button id="clearFiltersBtn" class="btn btn-sm btn-outline-secondary" type="button">
                    <i class="fa-solid fa-filter-circle-xmark me-1"></i>Limpiar
                </button>
            </div>

            <div class="toolbar-main-actions d-flex flex-wrap align-items-center gap-2">
                <a href="check_pld.php" class="btn btn-warning text-dark shadow-sm">
                    <i class="fa-solid fa-shield-halved me-2"></i><span>Buscar en Listas</span>
                </a>
                
                <button onclick="initClientCreation()" class="btn btn-primary shadow-sm">
                    <i class="fa-solid fa-user-plus me-2"></i><span>Nuevo Cliente</span>
                </button>
            </div>
        </div>
    </div>

    <div id="filterLabel" class="mb-2" style="display: none;"></div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-header-eve">
                        <tr>
                            <th class="ps-4">
                                <button class="sort-btn" type="button" data-sort="no_contrato">Contrato <i class="fa-solid fa-sort ms-1"></i></button>
                            </th>
                            <th>
                                <button class="sort-btn" type="button" data-sort="nombre_cliente">Cliente <i class="fa-solid fa-sort ms-1"></i></button>
                            </th>
                            <th>
                                <button class="sort-btn" type="button" data-sort="nivel_riesgo">Riesgo <i class="fa-solid fa-sort ms-1"></i></button>
                            </th>
                            <th>
                                <button class="sort-btn" type="button" data-sort="rfc">RFC <i class="fa-solid fa-sort ms-1"></i></button>
                            </th>
                            <th>
                                <button class="sort-btn" type="button" data-sort="fecha_apertura">Fecha Alta <i class="fa-solid fa-sort ms-1"></i></button>
                            </th>
                            <th>
                                <button class="sort-btn" type="button" data-sort="expediente_pld">Expediente PLD <i class="fa-solid fa-sort ms-1"></i></button>
                            </th>
                            <th>
                                <button class="sort-btn" type="button" data-sort="estatus_cliente">Estatus <i class="fa-solid fa-sort ms-1"></i></button>
                            </th>
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
                    Ingrese los datos de la transacción para determinar si es obligatorio identificar al cliente según las reglas de la actividad vulnerable.
                </p>
                
                <form id="analysisForm">
                    <div class="mb-3" id="activityContainer" style="display:none;">
                        <label class="form-label fw-bold">Actividad Vulnerable</label>
                        <select class="form-select" id="activitySelect"></select>
                    </div>

                    <div class="mb-3" id="subactivityContainer" style="display:none;">
                        <label class="form-label fw-bold">Tipo de Servicio</label>
                        <select class="form-select" id="ruleSelect"></select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Monto de la Transacción (MXN)</label>
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
