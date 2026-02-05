<?php 
session_start();
require_once 'config/db.php';
require_once 'config/pld_middleware.php';

// VAL-PLD-001: Verificar habilitación PLD
if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

$page_title = 'Operaciones PLD';
include 'templates/header.php'; 
?>
<title>Operaciones PLD - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/operaciones_pld.css">
</head>
<body>

<?php 
$is_sub_page = true;
include 'templates/top_bar.php'; 
?>

<div class="content-wrapper">
    <div class="page-header">
        <div class="page-header-title">
            <h2 class="fw-bold text-primary mb-0">
                <i class="fa-solid fa-file-invoice-dollar me-2"></i>
                Operaciones PLD
            </h2>
            <p class="text-muted">Registro y gestión de operaciones sujetas a aviso PLD</p>
        </div>
        <div class="page-header-actions">
            <button class="btn btn-primary shadow-sm" onclick="abrirModalOperacion()">
                <i class="fa-solid fa-plus me-2"></i>Registrar Operación
            </button>
        </div>
    </div>

    <!-- Información VAL-PLD-008 -->
    <div class="card mb-4 border-primary" style="background: linear-gradient(135deg, #e7f3ff 0%, #f0f8ff 100%);">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-1 text-center">
                    <i class="fa-solid fa-shield-halved fa-3x text-primary"></i>
                </div>
                <div class="col-md-11">
                    <h5 class="mb-2 text-primary">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        VAL-PLD-008 | Aviso por Umbral Individual
                    </h5>
                    <p class="mb-2">
                        <strong>Generalidad:</strong> Las operaciones que superen el umbral configurado (en UMAs) deben avisarse al SPPLD antes del <strong>día 17 del mes siguiente</strong> a la fecha de operación.
                    </p>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fa-solid fa-check-circle me-1 text-success"></i>
                                <strong>Validaciones:</strong> Monto ≥ umbral configurado (UMAs) | Fecha de operación válida
                            </small>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fa-solid fa-exclamation-triangle me-1 text-warning"></i>
                                <strong>Resultado:</strong> Rebase → <code>AVISO_REQUERIDO</code> | Deadline → día 17 del mes siguiente
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Información VAL-PLD-009 -->
    <div class="card mb-4 border-info" style="background: linear-gradient(135deg, #e7f5ff 0%, #f0f9ff 100%);">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-1 text-center">
                    <i class="fa-solid fa-layer-group fa-3x text-info"></i>
                </div>
                <div class="col-md-11">
                    <h5 class="mb-2 text-info">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        VAL-PLD-009 | Aviso por Acumulación (6 meses)
                    </h5>
                    <p class="mb-2">
                        <strong>Generalidad:</strong> La acumulación por tipo de acto genera obligación de aviso. Se calcula una <strong>ventana móvil de 6 meses</strong> desde la primera operación hacia adelante.
                    </p>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fa-solid fa-check-circle me-1 text-success"></i>
                                <strong>Validaciones:</strong> Suma acumulada en ventana de 6 meses | Cómputo desde la primera operación
                            </small>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fa-solid fa-exclamation-triangle me-1 text-warning"></i>
                                <strong>Resultado:</strong> Rebase → <code>GENERAR_AVISO</code> | Deadline → día 17 del mes siguiente a la primera operación
                            </small>
                        </div>
                    </div>
                    <div class="alert alert-info mt-2 mb-0">
                        <i class="fa-solid fa-lightbulb me-2"></i>
                        <strong>Nota:</strong> La acumulación se calcula sumando todas las operaciones del mismo cliente (y fracción/tipo si se especifica) en los últimos 6 meses desde la fecha de la operación actual.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas de Avisos Pendientes -->
    <div id="alertas-avisos" class="mb-4"></div>

    <!-- Estadísticas Rápidas -->
    <div class="row mb-4" id="estadisticas-rapidas">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body">
                    <i class="fa-solid fa-file-invoice-dollar fa-2x text-primary mb-2"></i>
                    <h4 class="mb-0" id="total-operaciones">-</h4>
                    <small class="text-muted">Total Operaciones</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body">
                    <i class="fa-solid fa-bell fa-2x text-warning mb-2"></i>
                    <h4 class="mb-0" id="avisos-pendientes-count">-</h4>
                    <small class="text-muted">Avisos Pendientes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-danger">
                <div class="card-body">
                    <i class="fa-solid fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                    <h4 class="mb-0" id="avisos-vencidos-count">-</h4>
                    <small class="text-muted">Avisos Vencidos</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body">
                    <i class="fa-solid fa-check-circle fa-2x text-success mb-2"></i>
                    <h4 class="mb-0" id="avisos-presentados-count">-</h4>
                    <small class="text-muted">Avisos Presentados</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="operacionesTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="operaciones-tab" data-bs-toggle="tab" data-bs-target="#operaciones" type="button" role="tab">
                <i class="fa-solid fa-list me-2"></i>Operaciones
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="avisos-tab" data-bs-toggle="tab" data-bs-target="#avisos" type="button" role="tab">
                <i class="fa-solid fa-bell me-2"></i>Avisos
                <span class="badge bg-danger ms-2" id="badge-avisos-pendientes">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="acumulaciones-tab" data-bs-toggle="tab" data-bs-target="#acumulaciones" type="button" role="tab">
                <i class="fa-solid fa-layer-group me-2"></i>Acumulaciones (VAL-PLD-009)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="informes-tab" data-bs-toggle="tab" data-bs-target="#informes" type="button" role="tab">
                <i class="fa-solid fa-file-circle-check me-2"></i>Informes No Operaciones (VAL-PLD-012)
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="operacionesTabContent">
        <!-- Tab Operaciones -->
        <div class="tab-pane fade show active" id="operaciones" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tabla-operaciones">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Monto</th>
                                    <th>Monto (UMA)</th>
                                    <th>Tipo</th>
                                    <th>Requiere Aviso</th>
                                    <th>Deadline</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="operaciones-tbody">
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando operaciones...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Avisos -->
        <div class="tab-pane fade" id="avisos" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <!-- Filtros -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <select class="form-select" id="filtro-estatus-aviso">
                                <option value="">Todos los estatus</option>
                                <option value="pendiente">Pendientes</option>
                                <option value="presentado">Presentados</option>
                                <option value="vencido">Vencidos</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" id="filtro-tipo-aviso">
                                <option value="">Todos los tipos</option>
                                <option value="umbral_individual">Umbral Individual</option>
                                <option value="acumulacion">Acumulación</option>
                                <option value="sospechosa_24h">Sospechosa (24H)</option>
                                <option value="listas_restringidas_24h">Listas Restringidas (24H)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-primary w-100" onclick="cargarAvisos()">
                                <i class="fa-solid fa-filter me-2"></i>Filtrar
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover" id="tabla-avisos">
                            <thead>
                                <tr>
                                    <th>Fecha Operación</th>
                                    <th>Cliente</th>
                                    <th>Tipo Aviso</th>
                                    <th>Monto</th>
                                    <th>Deadline</th>
                                    <th>Estatus</th>
                                    <th>Folio SPPLD</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="avisos-tbody">
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando avisos...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Acumulaciones -->
        <div class="tab-pane fade" id="acumulaciones" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        <strong>Ventana Móvil de 6 Meses:</strong> Las acumulaciones se calculan sumando todas las operaciones del mismo cliente en los últimos 6 meses desde la fecha de cada operación.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="tabla-acumulaciones">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Fracción</th>
                                    <th>Primera Operación</th>
                                    <th>Última Operación</th>
                                    <th>Cantidad Operaciones</th>
                                    <th>Monto Acumulado</th>
                                    <th>Monto (UMA)</th>
                                    <th>Días en Ventana</th>
                                    <th>Requiere Aviso</th>
                                    <th>Deadline</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="acumulaciones-tbody">
                                <tr>
                                    <td colspan="11" class="text-center">
                                        <i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando acumulaciones...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Informes de No Operaciones -->
        <div class="tab-pane fade" id="informes" role="tabpanel">
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-warning mb-3">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                        <strong>VAL-PLD-012 | Informe de No Operaciones:</strong> Si no hubo operaciones avisables en un periodo, debe presentarse un informe antes del día 17 del mes siguiente.
                    </div>
                    
                    <!-- Periodos Pendientes -->
                    <div class="card mb-4 border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fa-solid fa-clock me-2"></i>Periodos Pendientes de Informe
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="periodos-pendientes-list"></div>
                        </div>
                    </div>

                    <!-- Informes Registrados -->
                    <h5 class="mb-3">
                        <i class="fa-solid fa-list me-2"></i>Informes Registrados
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover" id="tabla-informes">
                            <thead>
                                <tr>
                                    <th>Periodo</th>
                                    <th>Fecha Límite</th>
                                    <th>Fecha Presentación</th>
                                    <th>Folio SPPLD</th>
                                    <th>Estatus</th>
                                    <th>Observaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="informes-tbody">
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando informes...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Registrar Operación -->
<div class="modal fade" id="modalOperacion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-file-invoice-dollar me-2"></i>Registrar Operación PLD
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formOperacion">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cliente *</label>
                            <select class="form-select" id="operacion_id_cliente" required>
                                <option value="">-- Seleccione Cliente --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de Operación *</label>
                            <input type="date" class="form-control" id="operacion_fecha" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Monto (MXN) *</label>
                            <input type="number" class="form-control" id="operacion_monto" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Fracción (Opcional)
                                <i class="fa-solid fa-info-circle ms-1 text-info" 
                                   data-bs-toggle="tooltip" 
                                   data-bs-placement="top" 
                                   title="Fracción de actividad vulnerable según LFPIORPI (ej: V, V Bis, VI, XIII)"></i>
                            </label>
                            <select class="form-select" id="operacion_id_fraccion">
                                <option value="">-- Seleccione Fracción --</option>
                            </select>
                            <small class="text-muted">
                                <i class="fa-solid fa-lightbulb me-1"></i>
                                Ejemplos: V (Inmuebles), V Bis (Muebles), VI (Intermediación), XIII (Donativos)
                            </small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Operación (Opcional)</label>
                            <input type="text" class="form-control" id="operacion_tipo" placeholder="Ej: Venta, Compra, etc.">
                        </div>
                        <div class="col-12 mb-3">
                            <hr>
                            <h6 class="text-danger">
                                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                VAL-PLD-010 | Operación Sospechosa (Aviso 24H)
                            </h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Operación Sospechosa
                                <i class="fa-solid fa-info-circle ms-1 text-info" 
                                   data-bs-toggle="tooltip" 
                                   data-bs-placement="top" 
                                   title="Si la operación presenta indicios de posible ilícito, debe avisarse en 24 horas"></i>
                            </label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="operacion_sospechosa">
                                <label class="form-check-label" for="operacion_sospechosa">
                                    <strong class="text-danger">Marcar como sospechosa (AVISO_24H)</strong>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3" id="fecha-conocimiento-sospecha" style="display: none;">
                            <label class="form-label">Fecha de Conocimiento de Sospecha *</label>
                            <input type="datetime-local" class="form-control" id="operacion_fecha_sospecha" required>
                            <small class="text-muted">Deadline: 24 horas desde esta fecha</small>
                        </div>
                        <div class="col-12 mb-3">
                            <hr>
                            <h6 class="text-dark">
                                <i class="fa-solid fa-ban me-2"></i>
                                VAL-PLD-011 | Listas Restringidas (Aviso 24H)
                            </h6>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Match en Listas Restringidas
                                <i class="fa-solid fa-info-circle ms-1 text-info" 
                                   data-bs-toggle="tooltip" 
                                   data-bs-placement="top" 
                                   title="Si hay coincidencia con listas restringidas, debe avisarse en 24 horas"></i>
                            </label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="operacion_match_listas">
                                <label class="form-check-label" for="operacion_match_listas">
                                    <strong class="text-danger">Marcar como match (AVISO_24H)</strong>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3" id="fecha-conocimiento-match" style="display: none;">
                            <label class="form-label">Fecha de Conocimiento del Match *</label>
                            <input type="datetime-local" class="form-control" id="operacion_fecha_match" required>
                            <small class="text-muted">Deadline: 24 horas desde esta fecha</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarOperacion()">
                    <i class="fa-solid fa-save me-2"></i>Registrar Operación
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Actualizar Aviso -->
<div class="modal fade" id="modalAviso" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-bell me-2"></i>Actualizar Aviso PLD
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAviso">
                    <input type="hidden" id="aviso_id_aviso">
                    <div class="mb-3">
                        <label class="form-label">Folio SPPLD</label>
                        <input type="text" class="form-control" id="aviso_folio_sppld" placeholder="Folio del aviso en SPPLD">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha de Presentación</label>
                        <input type="date" class="form-control" id="aviso_fecha_presentacion">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estatus</label>
                        <select class="form-select" id="aviso_estatus">
                            <option value="pendiente">Pendiente</option>
                            <option value="presentado">Presentado</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="actualizarAviso()">
                    <i class="fa-solid fa-save me-2"></i>Actualizar Aviso
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let clientesList = [];
let fraccionesList = [];

document.addEventListener('DOMContentLoaded', function() {
    cargarClientes();
    cargarFracciones();
    cargarOperaciones();
    cargarAvisos();
    cargarInformes();
    cargarAlertasAvisos();
    actualizarTotalOperaciones();
    
    // Cargar acumulaciones cuando se cambia al tab (solo si el elemento existe)
    const acumulacionesTab = document.getElementById('acumulaciones-tab');
    if (acumulacionesTab) {
        acumulacionesTab.addEventListener('shown.bs.tab', function() {
            cargarAcumulaciones();
        });
    }
    
    // Cargar informes cuando se cambia al tab
    const informesTab = document.getElementById('informes-tab');
    if (informesTab) {
        informesTab.addEventListener('shown.bs.tab', function() {
            cargarInformes();
        });
    }
    
    // Toggle fecha conocimiento sospecha
    const operacionSospechosa = document.getElementById('operacion_sospechosa');
    if (operacionSospechosa) {
        operacionSospechosa.addEventListener('change', function() {
            const fechaDiv = document.getElementById('fecha-conocimiento-sospecha');
            if (fechaDiv) {
                fechaDiv.style.display = this.checked ? 'block' : 'none';
                if (this.checked && !document.getElementById('operacion_fecha_sospecha').value) {
                    // Establecer fecha/hora actual si no hay valor
                    const now = new Date();
                    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                    document.getElementById('operacion_fecha_sospecha').value = now.toISOString().slice(0, 16);
                }
            }
        });
    }
    
    // Toggle fecha conocimiento match
    const operacionMatchListas = document.getElementById('operacion_match_listas');
    if (operacionMatchListas) {
        operacionMatchListas.addEventListener('change', function() {
            const fechaDiv = document.getElementById('fecha-conocimiento-match');
            if (fechaDiv) {
                fechaDiv.style.display = this.checked ? 'block' : 'none';
                if (this.checked && !document.getElementById('operacion_fecha_match').value) {
                    // Establecer fecha/hora actual si no hay valor
                    const now = new Date();
                    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                    document.getElementById('operacion_fecha_match').value = now.toISOString().slice(0, 16);
                }
            }
        });
    }
});

function cargarClientes() {
    fetch('api/get_clients.php')
        .then(res => res.json())
        .then(data => {
            clientesList = data;
            const select = document.getElementById('operacion_id_cliente');
            select.innerHTML = '<option value="">-- Seleccione Cliente --</option>';
            data.forEach(cliente => {
                const option = document.createElement('option');
                option.value = cliente.id_cliente;
                option.textContent = cliente.nombre_cliente || `Cliente #${cliente.id_cliente}`;
                select.appendChild(option);
            });
        })
        .catch(err => console.error('Error al cargar clientes:', err));
}

function cargarFracciones() {
    fetch('api/get_catalogos.php')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.data.vulnerables) {
                fraccionesList = data.data.vulnerables;
                const select = document.getElementById('operacion_id_fraccion');
                data.data.vulnerables.forEach(fraccion => {
                    const option = document.createElement('option');
                    option.value = fraccion.id_vulnerable;
                    option.textContent = `${fraccion.nombre} (${fraccion.fraccion})`;
                    select.appendChild(option);
                });
            }
        })
        .catch(err => console.error('Error al cargar fracciones:', err));
}

function cargarOperaciones() {
    fetch('api/get_operaciones_pld.php')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                renderOperaciones(data.operaciones || []);
            } else {
                document.getElementById('operaciones-tbody').innerHTML = 
                    '<tr><td colspan="8" class="text-center text-danger">Error al cargar operaciones</td></tr>';
            }
        })
        .catch(err => {
            console.error('Error al cargar operaciones:', err);
            document.getElementById('operaciones-tbody').innerHTML = 
                '<tr><td colspan="8" class="text-center text-danger">Error de conexión</td></tr>';
        });
}

function renderOperaciones(operaciones) {
    const tbody = document.getElementById('operaciones-tbody');
    if (operaciones.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-5">
                    <i class="fa-solid fa-inbox fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                    <p class="mb-0">No hay operaciones registradas</p>
                    <small>Haz clic en "Registrar Operación" para comenzar</small>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = operaciones.map(op => {
        const requiereAviso = op.requiere_aviso == 1;
        const badgeAviso = requiereAviso 
            ? `<span class="badge bg-warning"><i class="fa-solid fa-exclamation-triangle me-1"></i>Requiere Aviso</span>` 
            : `<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>No requiere</span>`;
        
        let deadline = '-';
        if (op.fecha_deadline_aviso) {
            const fechaDeadline = new Date(op.fecha_deadline_aviso);
            const hoy = new Date();
            const isVencido = fechaDeadline < hoy;
            const diasRestantes = Math.ceil((fechaDeadline - hoy) / (1000 * 60 * 60 * 24));
            
            if (isVencido) {
                deadline = `<span class="text-danger fw-bold"><i class="fa-solid fa-exclamation-circle me-1"></i>${op.fecha_deadline_aviso} (Vencido)</span>`;
            } else if (diasRestantes <= 7) {
                deadline = `<span class="text-warning fw-bold"><i class="fa-solid fa-clock me-1"></i>${op.fecha_deadline_aviso} (${diasRestantes} días)</span>`;
            } else {
                deadline = `<span class="text-success">${op.fecha_deadline_aviso}</span>`;
            }
        }
        
        return `
            <tr class="${requiereAviso && op.fecha_deadline_aviso && new Date(op.fecha_deadline_aviso) < new Date() ? 'table-danger' : ''}">
                <td><strong>${op.fecha_operacion}</strong></td>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-user me-2 text-primary"></i>
                        ${op.cliente_nombre || `Cliente #${op.id_cliente}`}
                    </div>
                </td>
                <td>
                    <strong class="text-primary">$${parseFloat(op.monto).toLocaleString('es-MX', {minimumFractionDigits: 2})}</strong>
                </td>
                <td>
                    <span class="badge bg-info">${parseFloat(op.monto_uma || 0).toFixed(2)} UMAs</span>
                </td>
                <td>${op.tipo_operacion || '<span class="text-muted">-</span>'}</td>
                <td>${badgeAviso}</td>
                <td>${deadline}</td>
                <td>
                    ${requiereAviso ? `
                        <button class="btn btn-sm btn-info" onclick="verAviso(${op.id_aviso_generado})" title="Ver Aviso">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    ` : '<span class="text-muted">-</span>'}
                </td>
            </tr>
        `;
    }).join('');
}

function cargarAvisos() {
    const estatus = document.getElementById('filtro-estatus-aviso').value;
    const tipo = document.getElementById('filtro-tipo-aviso').value;
    
    let url = 'api/get_avisos_pld.php?';
    if (estatus) url += `estatus=${estatus}&`;
    if (tipo) url += `tipo_aviso=${tipo}`;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                renderAvisos(data.avisos || []);
                actualizarBadgeAvisos(data.contadores?.pendientes || 0);
                actualizarEstadisticas(data.contadores);
            } else {
                document.getElementById('avisos-tbody').innerHTML = 
                    '<tr><td colspan="8" class="text-center text-danger">Error al cargar avisos</td></tr>';
            }
        })
        .catch(err => {
            console.error('Error al cargar avisos:', err);
            document.getElementById('avisos-tbody').innerHTML = 
                '<tr><td colspan="8" class="text-center text-danger">Error de conexión</td></tr>';
        });
}

function actualizarEstadisticas(contadores) {
    if (contadores) {
        document.getElementById('avisos-pendientes-count').textContent = contadores.pendientes || 0;
        document.getElementById('avisos-vencidos-count').textContent = contadores.vencidos || 0;
        document.getElementById('avisos-presentados-count').textContent = contadores.presentados || 0;
    }
}

function actualizarTotalOperaciones() {
    fetch('api/get_operaciones_pld.php')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('total-operaciones').textContent = (data.operaciones || []).length;
            }
        })
        .catch(err => console.error('Error al cargar total operaciones:', err));
}

function cargarInformes() {
    fetch('api/get_informes_no_operaciones.php')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                renderInformes(data.informes || []);
                renderPeriodosPendientes(data.periodos_pendientes || []);
            } else {
                const tbody = document.getElementById('informes-tbody');
                if (tbody) {
                    tbody.innerHTML = 
                        '<tr><td colspan="7" class="text-center text-danger">Error al cargar informes</td></tr>';
                }
            }
        })
        .catch(err => {
            console.error('Error al cargar informes:', err);
            const tbody = document.getElementById('informes-tbody');
            if (tbody) {
                tbody.innerHTML = 
                    '<tr><td colspan="7" class="text-center text-danger">Error de conexión</td></tr>';
            }
        });
}

function renderPeriodosPendientes(periodos) {
    const container = document.getElementById('periodos-pendientes-list');
    if (!container) return;
    
    if (periodos.length === 0) {
        container.innerHTML = '<p class="text-muted mb-0">No hay periodos pendientes de informe.</p>';
        return;
    }
    
    container.innerHTML = periodos.map(periodo => {
        const diasRestantes = Math.ceil(periodo.dias_restantes);
        const isVencido = diasRestantes < 0;
        const isUrgente = diasRestantes <= 7 && diasRestantes >= 0;
        
        const badgeClass = isVencido ? 'bg-danger' : isUrgente ? 'bg-warning' : 'bg-info';
        const badgeText = isVencido ? 'Vencido' : isUrgente ? `Urgente (${diasRestantes} días)` : `${diasRestantes} días restantes`;
        
        return `
            <div class="card mb-2 ${isVencido ? 'border-danger' : isUrgente ? 'border-warning' : ''}">
                <div class="card-body p-3">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <strong>${periodo.periodo_nombre}</strong>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Fecha Límite:</small><br>
                            <strong>${periodo.fecha_limite}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="badge ${badgeClass}">${badgeText}</span>
                        </div>
                        <div class="col-md-3 text-end">
                            <button class="btn btn-sm btn-primary" onclick="registrarInforme(${periodo.periodo_mes}, ${periodo.periodo_anio})">
                                <i class="fa-solid fa-plus me-1"></i>Registrar Informe
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function renderInformes(informes) {
    const tbody = document.getElementById('informes-tbody');
    if (!tbody) return;
    
    if (informes.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-5">
                    <i class="fa-solid fa-file-circle-check fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                    <p class="mb-0">No hay informes registrados</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = informes.map(informe => {
        const estatus = informe.estatus || 'pendiente';
        const badgeEstatus = estatus === 'presentado' 
            ? '<span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i>Presentado</span>'
            : '<span class="badge bg-warning"><i class="fa-solid fa-clock me-1"></i>Pendiente</span>';
        
        const nombreMes = new Date(2000, informe.periodo_mes - 1, 1).toLocaleString('es-MX', { month: 'long' });
        const periodoNombre = `${nombreMes.charAt(0).toUpperCase() + nombreMes.slice(1)} ${informe.periodo_anio}`;
        
        let fechaLimiteClass = '';
        if (informe.fecha_limite) {
            const fechaLimite = new Date(informe.fecha_limite);
            const hoy = new Date();
            if (fechaLimite < hoy && estatus !== 'presentado') {
                fechaLimiteClass = 'text-danger fw-bold';
            } else if (fechaLimite < new Date(hoy.getTime() + 7 * 24 * 60 * 60 * 1000)) {
                fechaLimiteClass = 'text-warning fw-bold';
            }
        }
        
        return `
            <tr>
                <td><strong>${periodoNombre}</strong></td>
                <td class="${fechaLimiteClass}">${informe.fecha_limite || '-'}</td>
                <td>${informe.fecha_presentacion || '<span class="text-muted">-</span>'}</td>
                <td>
                    ${informe.folio_sppld ? `<span class="badge bg-secondary"><i class="fa-solid fa-hashtag me-1"></i>${informe.folio_sppld}</span>` : '<span class="text-muted">-</span>'}
                </td>
                <td>${badgeEstatus}</td>
                <td>
                    ${informe.observaciones ? `<small>${informe.observaciones.substring(0, 50)}${informe.observaciones.length > 50 ? '...' : ''}</small>` : '<span class="text-muted">-</span>'}
                </td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="verInforme(${informe.id_informe})" title="Ver Detalles">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function registrarInforme(mes, anio) {
    Swal.fire({
        title: 'Registrar Informe de No Operaciones',
        html: `
            <form id="formInforme">
                <div class="mb-3">
                    <label class="form-label">Periodo</label>
                    <input type="text" class="form-control" value="${new Date(2000, mes - 1, 1).toLocaleString('es-MX', { month: 'long' }).charAt(0).toUpperCase() + new Date(2000, mes - 1, 1).toLocaleString('es-MX', { month: 'long' }).slice(1)} ${anio}" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha de Presentación *</label>
                    <input type="date" class="form-control" id="informe_fecha_presentacion" value="${new Date().toISOString().split('T')[0]}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Folio SPPLD</label>
                    <input type="text" class="form-control" id="informe_folio_sppld" placeholder="Folio del informe en SPPLD">
                </div>
                <div class="mb-3">
                    <label class="form-label">Observaciones</label>
                    <textarea class="form-control" id="informe_observaciones" rows="3" placeholder="Observaciones adicionales"></textarea>
                </div>
            </form>
        `,
        showCancelButton: true,
        confirmButtonText: 'Registrar Informe',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d6efd',
        preConfirm: () => {
            const fecha = document.getElementById('informe_fecha_presentacion').value;
            if (!fecha) {
                Swal.showValidationMessage('La fecha de presentación es requerida');
                return false;
            }
            return {
                periodo_mes: mes,
                periodo_anio: anio,
                fecha_presentacion: fecha,
                folio_sppld: document.getElementById('informe_folio_sppld').value || null,
                observaciones: document.getElementById('informe_observaciones').value || null
            };
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            fetch('api/registrar_informe_no_operaciones.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(result.value)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Éxito', 'Informe registrado correctamente', 'success');
                    cargarInformes();
                } else {
                    Swal.fire('Error', data.message || 'Error al registrar informe', 'error');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                Swal.fire('Error', 'Error de conexión', 'error');
            });
        }
    });
}

function verInforme(idInforme) {
    // TODO: Implementar modal para ver detalles del informe
    Swal.fire({
        title: 'Detalles del Informe',
        text: 'Funcionalidad en desarrollo',
        icon: 'info'
    });
}

function cargarAcumulaciones() {
    const tbody = document.getElementById('acumulaciones-tbody');
    if (!tbody) {
        console.warn('Tab de acumulaciones no encontrado');
        return;
    }
    
    fetch('api/get_acumulaciones_pld.php')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                renderAcumulaciones(data.acumulaciones || []);
            } else {
                tbody.innerHTML = 
                    '<tr><td colspan="11" class="text-center text-danger">Error al cargar acumulaciones</td></tr>';
            }
        })
        .catch(err => {
            console.error('Error al cargar acumulaciones:', err);
            tbody.innerHTML = 
                '<tr><td colspan="11" class="text-center text-danger">Error de conexión</td></tr>';
        });
}

function renderAcumulaciones(acumulaciones) {
    const tbody = document.getElementById('acumulaciones-tbody');
    if (!tbody) {
        console.warn('Tabla de acumulaciones no encontrada');
        return;
    }
    
    if (acumulaciones.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="11" class="text-center text-muted py-5">
                    <i class="fa-solid fa-layer-group fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                    <p class="mb-0">No hay acumulaciones registradas</p>
                    <small>Las acumulaciones se generan automáticamente al registrar operaciones</small>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = acumulaciones.map(acum => {
        const requiereAviso = acum.requiere_aviso == 1;
        const badgeAviso = requiereAviso 
            ? '<span class="badge bg-warning"><i class="fa-solid fa-exclamation-triangle me-1"></i>Requiere Aviso</span>' 
            : '<span class="badge bg-secondary"><i class="fa-solid fa-check me-1"></i>No requiere</span>';
        
        const diasVentana = acum.dias_ventana || 0;
        const diasRestantes = acum.dias_restantes_ventana || 0;
        const porcentajeVentana = Math.min(100, (diasVentana / 180) * 100);
        
        let deadline = '-';
        if (acum.fecha_deadline_aviso) {
            const fechaDeadline = new Date(acum.fecha_deadline_aviso);
            const hoy = new Date();
            const isVencido = fechaDeadline < hoy;
            const diasRestantesDeadline = Math.ceil((fechaDeadline - hoy) / (1000 * 60 * 60 * 24));
            
            if (isVencido) {
                deadline = `<span class="text-danger fw-bold"><i class="fa-solid fa-exclamation-circle me-1"></i>${acum.fecha_deadline_aviso} (Vencido)</span>`;
            } else if (diasRestantesDeadline <= 7) {
                deadline = `<span class="text-warning fw-bold"><i class="fa-solid fa-clock me-1"></i>${acum.fecha_deadline_aviso} (${diasRestantesDeadline} días)</span>`;
            } else {
                deadline = `<span class="text-success">${acum.fecha_deadline_aviso}</span>`;
            }
        }
        
        return `
            <tr class="${requiereAviso && acum.fecha_deadline_aviso && new Date(acum.fecha_deadline_aviso) < new Date() ? 'table-danger' : ''}">
                <td>
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-user me-2 text-primary"></i>
                        ${acum.cliente_nombre || `Cliente #${acum.id_cliente}`}
                    </div>
                </td>
                <td>
                    ${acum.fraccion_codigo ? `<span class="badge bg-secondary">${acum.fraccion_codigo}</span><br><small>${acum.fraccion_nombre || ''}</small>` : '<span class="text-muted">-</span>'}
                </td>
                <td><strong>${acum.fecha_primera_operacion}</strong></td>
                <td><strong>${acum.fecha_ultima_operacion}</strong></td>
                <td>
                    <span class="badge bg-info">${acum.cantidad_operaciones || 0}</span>
                </td>
                <td>
                    <strong class="text-primary">$${parseFloat(acum.monto_acumulado || 0).toLocaleString('es-MX', {minimumFractionDigits: 2})}</strong>
                </td>
                <td>
                    <span class="badge bg-info">${parseFloat(acum.monto_acumulado_uma || 0).toFixed(2)} UMAs</span>
                </td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar ${porcentajeVentana >= 100 ? 'bg-danger' : porcentajeVentana >= 80 ? 'bg-warning' : 'bg-success'}" 
                             role="progressbar" 
                             style="width: ${porcentajeVentana}%"
                             title="${diasVentana} días / ${diasRestantes} días restantes">
                            ${diasVentana} días
                        </div>
                    </div>
                    <small class="text-muted">${diasRestantes} días restantes</small>
                </td>
                <td>${badgeAviso}</td>
                <td>${deadline}</td>
                <td>
                    ${requiereAviso && acum.id_aviso_generado ? `
                        <button class="btn btn-sm btn-info" onclick="verAviso(${acum.id_aviso_generado})" title="Ver Aviso">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    ` : '<span class="text-muted">-</span>'}
                </td>
            </tr>
        `;
    }).join('');
}

function renderAvisos(avisos) {
    const tbody = document.getElementById('avisos-tbody');
    if (avisos.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-muted py-5">
                    <i class="fa-solid fa-bell-slash fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                    <p class="mb-0">No hay avisos</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = avisos.map(aviso => {
        const estatusReal = aviso.estatus_real || aviso.estatus;
        let badgeEstatus = '';
        if (estatusReal === 'pendiente') {
            const isVencido = new Date(aviso.fecha_deadline) < new Date();
            badgeEstatus = isVencido 
                ? '<span class="badge bg-danger"><i class="fa-solid fa-exclamation-triangle me-1"></i>Vencido</span>'
                : '<span class="badge bg-warning"><i class="fa-solid fa-clock me-1"></i>Pendiente</span>';
        } else if (estatusReal === 'presentado') {
            badgeEstatus = '<span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i>Presentado</span>';
        } else {
            badgeEstatus = '<span class="badge bg-secondary">' + estatusReal + '</span>';
        }
        
        const tipoAvisoLabels = {
            'umbral_individual': '<span class="badge bg-primary"><i class="fa-solid fa-chart-line me-1"></i>Umbral Individual</span>',
            'acumulacion': '<span class="badge bg-info"><i class="fa-solid fa-layer-group me-1"></i>Acumulación (6 meses)</span>',
            'sospechosa_24h': '<span class="badge bg-danger"><i class="fa-solid fa-exclamation-triangle me-1"></i>Sospechosa (24H)</span>',
            'listas_restringidas_24h': '<span class="badge bg-dark"><i class="fa-solid fa-ban me-1"></i>Listas Restringidas (24H)</span>'
        };
        
        // Información adicional para acumulación
        let infoAdicional = '';
        if (aviso.tipo_aviso === 'acumulacion') {
            const cantidadOps = aviso.cantidad_operaciones || 'N/A';
            const fechaPrimera = aviso.fecha_primera_operacion || aviso.fecha_operacion;
            const montoUMA = aviso.monto_acumulado_uma ? parseFloat(aviso.monto_acumulado_uma).toFixed(2) : 'N/A';
            infoAdicional = `
                <br><small class="text-muted">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    <strong>${cantidadOps}</strong> operaciones acumuladas | 
                    Primera: <strong>${fechaPrimera}</strong> | 
                    Monto: <strong>${montoUMA} UMAs</strong>
                </small>
            `;
        }
        
        const fechaDeadline = new Date(aviso.fecha_deadline);
        const hoy = new Date();
        const isVencido = fechaDeadline < hoy;
        const diasRestantes = Math.ceil((fechaDeadline - hoy) / (1000 * 60 * 60 * 24));
        
        let deadlineClass = '';
        let deadlineIcon = '';
        if (isVencido) {
            deadlineClass = 'text-danger fw-bold';
            deadlineIcon = '<i class="fa-solid fa-exclamation-circle me-1"></i>';
        } else if (diasRestantes <= 7) {
            deadlineClass = 'text-warning fw-bold';
            deadlineIcon = '<i class="fa-solid fa-clock me-1"></i>';
        } else {
            deadlineClass = 'text-success';
            deadlineIcon = '<i class="fa-solid fa-calendar-check me-1"></i>';
        }
        
        return `
            <tr class="${estatusReal === 'vencido' ? 'table-danger' : ''}">
                <td><strong>${aviso.fecha_operacion}</strong></td>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-user me-2 text-primary"></i>
                        ${aviso.cliente_nombre || `Cliente #${aviso.id_cliente}`}
                    </div>
                </td>
                <td>
                    ${tipoAvisoLabels[aviso.tipo_aviso] || aviso.tipo_aviso}
                    ${infoAdicional}
                </td>
                <td>
                    ${aviso.monto ? `<strong class="text-primary">$${parseFloat(aviso.monto).toLocaleString('es-MX', {minimumFractionDigits: 2})}</strong>` : '<span class="text-muted">-</span>'}
                </td>
                <td class="${deadlineClass}">
                    ${deadlineIcon}${aviso.fecha_deadline}
                    ${!isVencido && diasRestantes <= 7 ? `<br><small class="text-warning">(${diasRestantes} días restantes)</small>` : ''}
                </td>
                <td>${badgeEstatus}</td>
                <td>
                    ${aviso.folio_sppld ? `<span class="badge bg-secondary"><i class="fa-solid fa-hashtag me-1"></i>${aviso.folio_sppld}</span>` : '<span class="text-muted">-</span>'}
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editarAviso(${aviso.id_aviso})" title="Editar Aviso">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function actualizarBadgeAvisos(count) {
    const badge = document.getElementById('badge-avisos-pendientes');
    badge.textContent = count;
    badge.style.display = count > 0 ? 'inline' : 'none';
}

function cargarAlertasAvisos() {
    fetch('api/get_avisos_pld.php?estatus=vencido')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.contadores?.vencidos > 0) {
                const alertDiv = document.getElementById('alertas-avisos');
                alertDiv.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                        <strong>¡Atención!</strong> Tienes <strong>${data.contadores.vencidos}</strong> aviso(s) vencido(s) que requieren atención inmediata.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            }
        })
        .catch(err => console.error('Error al cargar alertas:', err));
}

function abrirModalOperacion() {
    document.getElementById('formOperacion').reset();
    document.getElementById('operacion_fecha').value = new Date().toISOString().split('T')[0];
    document.getElementById('fecha-conocimiento-sospecha').style.display = 'none';
    document.getElementById('fecha-conocimiento-match').style.display = 'none';
    const modal = new bootstrap.Modal(document.getElementById('modalOperacion'));
    modal.show();
}

function guardarOperacion() {
    const form = document.getElementById('formOperacion');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const data = {
        id_cliente: document.getElementById('operacion_id_cliente').value,
        monto: parseFloat(document.getElementById('operacion_monto').value),
        fecha_operacion: document.getElementById('operacion_fecha').value,
        id_fraccion: document.getElementById('operacion_id_fraccion').value || null,
        tipo_operacion: document.getElementById('operacion_tipo').value || null,
        es_sospechosa: document.getElementById('operacion_sospechosa')?.checked ? 1 : 0,
        fecha_conocimiento_sospecha: document.getElementById('operacion_fecha_sospecha')?.value || null,
        match_listas_restringidas: document.getElementById('operacion_match_listas')?.checked ? 1 : 0,
        fecha_conocimiento_match: document.getElementById('operacion_fecha_match')?.value || null
    };
    
    fetch('api/registrar_operacion_pld.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(err => {
                throw new Error(err.message || `Error ${res.status}: ${res.statusText}`);
            });
        }
        return res.json();
    })
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Operación Registrada',
                html: data.requiere_aviso 
                    ? `<p>${data.message}</p>
                       <p><strong>⚠️ Requiere Aviso</strong></p>
                       <p>Tipo: ${data.tipo_aviso}</p>
                       <p>Deadline: ${data.fecha_deadline}</p>`
                    : `<p>${data.message}</p>`,
                confirmButtonText: 'Aceptar'
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('modalOperacion')).hide();
                cargarOperaciones();
                cargarAvisos();
                cargarAlertasAvisos();
                actualizarTotalOperaciones();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }
    })
    .catch(err => {
        console.error('Error:', err);
        let errorMessage = 'Error al registrar operación';
        if (err.message) {
            errorMessage = err.message;
        }
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorMessage,
            footer: 'Revisa la consola para más detalles'
        });
    });
}

function editarAviso(idAviso) {
    fetch(`api/get_avisos_pld.php`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const aviso = data.avisos.find(a => a.id_aviso == idAviso);
                if (aviso) {
                    document.getElementById('aviso_id_aviso').value = aviso.id_aviso;
                    document.getElementById('aviso_folio_sppld').value = aviso.folio_sppld || '';
                    document.getElementById('aviso_fecha_presentacion').value = aviso.fecha_presentacion || '';
                    document.getElementById('aviso_estatus').value = aviso.estatus;
                    const modal = new bootstrap.Modal(document.getElementById('modalAviso'));
                    modal.show();
                }
            }
        });
}

function actualizarAviso() {
    const data = {
        id_aviso: document.getElementById('aviso_id_aviso').value,
        folio_sppld: document.getElementById('aviso_folio_sppld').value,
        fecha_presentacion: document.getElementById('aviso_fecha_presentacion').value,
        estatus: document.getElementById('aviso_estatus').value
    };
    
    fetch('api/actualizar_aviso_pld.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(err => {
                throw new Error(err.message || `Error ${res.status}: ${res.statusText}`);
            });
        }
        return res.json();
    })
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Aviso Actualizado',
                text: data.message
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('modalAviso')).hide();
                cargarAvisos();
                cargarAlertasAvisos();
                actualizarTotalOperaciones();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
            });
        }
    });
}

function verAviso(idAviso) {
    // Cambiar a tab de avisos y filtrar
    document.getElementById('avisos-tab').click();
    // TODO: Scroll al aviso específico
}
</script>

<?php include 'templates/footer.php'; ?>
