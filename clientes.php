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
                    <option value="preregistros">Pre-registro</option>
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

<div class="modal fade" id="analysisModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="analysisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable analysis-modal-dialog">
        <div class="modal-content analysis-modal-content">
            <div class="modal-header analysis-modal-header">
                <h5 class="modal-title analysis-modal-title" id="analysisModalLabel">
                    <i class="fa-solid fa-calculator me-2"></i>Análisis de Umbral PLD
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body analysis-modal-body">
                <p class="analysis-helper mb-3">
                    Evalúa rápidamente si la operación requiere identificación inmediata o si conviene iniciar como pre-registro para control por acumulación.
                </p>
                
                <form id="analysisForm">
                    <div class="mb-3 analysis-field" id="activityContainer" style="display:none;">
                        <label class="form-label fw-bold">
                            <i class="fa-solid fa-layer-group me-2 text-primary"></i>Actividad Vulnerable
                        </label>
                        <select class="form-select" id="activitySelect"></select>
                    </div>

                    <div class="mb-3 analysis-field" id="subactivityContainer" style="display:none;">
                        <label class="form-label fw-bold">
                            <i class="fa-solid fa-briefcase me-2 text-primary"></i>Tipo de Servicio
                        </label>
                        <select class="form-select" id="ruleSelect"></select>
                    </div>

                    <div class="mb-3 analysis-field">
                        <label class="form-label fw-bold">
                            <i class="fa-solid fa-coins me-2 text-primary"></i>Monto de la Transacción (MXN)
                        </label>
                        <div class="input-group analysis-input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="transactionAmount" placeholder="0.00" min="0" step="0.01">
                        </div>
                        <div class="form-text analysis-uma-note">
                            Valor UMA actual: <span id="umaDisplay" class="fw-bold text-dark">-</span>
                        </div>
                    </div>

                    <div id="thresholdWarning" class="alert alert-warning border-warning d-none analysis-warning">
                        <div class="d-flex align-items-start">
                            <i class="fa-solid fa-circle-exclamation fs-4 me-3 mt-1"></i>
                            <div>
                                <strong>Atención:</strong> Esta operación está por debajo del umbral de identificación para aviso.
                                <span class="small text-dark mt-1 d-block">Puedes iniciar como pre-registro (recomendado) o darlo de alta para seguimiento por acumulación futura.</span>
                                <div class="small mt-2 analysis-summary-grid">
                                    <div class="analysis-summary-item"><strong>Actividad:</strong> <span id="thresholdActivity">-</span></div>
                                    <div class="analysis-summary-item"><strong>Monto capturado:</strong> <span id="thresholdAmount">-</span></div>
                                    <div class="analysis-summary-item"><strong>Umbral:</strong> <span id="thresholdUma">-</span> UMA (<span id="thresholdMxn">-</span>)</div>
                                    <div class="analysis-summary-item"><strong>Faltante para umbral:</strong> <span id="thresholdDifference">-</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 analysis-warning-actions">
                            <button type="button" class="btn btn-outline-dark" onclick="proceedToPreRegistro()">Iniciar pre-registro</button>
                            <button type="button" class="btn btn-warning fw-bold" onclick="proceedToCreate({ fromAccumulation: true })">Dar de alta por acumulación</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer analysis-modal-footer" id="modalFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="validateThreshold()">Analizar</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/clientes.js"></script>

<?php include 'templates/footer.php'; ?>
