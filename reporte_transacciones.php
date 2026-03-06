<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/modules_helper.php';
require_once __DIR__ . '/config/pld_middleware.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
requireModuleActive($pdo, 'reports');
requireReporteActivo($pdo, 'reporte_transacciones.php');

// VAL-PLD-001: Verificar habilitación PLD
if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

// Filtro por usuario: solo operaciones de sus clientes (admins ven todas)
$userIdRep = $_SESSION['user_id'] ?? 0;
$isAdminRep = false;
if ($userIdRep > 0) {
    $stmtAdmin = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
    $stmtAdmin->execute([$userIdRep]);
    $perm = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    $isAdminRep = $perm && (int)($perm['administracion'] ?? 0) > 0;
}
$opUserFilter = "";
$opUserParams = [];
if (!$isAdminRep && $userIdRep > 0) {
    $opUserFilter = " AND c.id_usuario = ?";
    $opUserParams[] = $userIdRep;
}

// 1. ESTADÍSTICAS GLOBALES (KPIs) - filtradas por usuario
$sqlStats = "SELECT COUNT(*) as total_ops, IFNULL(SUM(op.monto), 0) as monto_total, SUM(CASE WHEN op.requiere_aviso = 1 THEN 1 ELSE 0 END) as total_avisos FROM operaciones_pld op JOIN clientes c ON op.id_cliente = c.id_cliente WHERE op.id_status = 1" . ($opUserFilter ? $opUserFilter : "");
$stmtStats = $pdo->prepare($sqlStats);
$stmtStats->execute($opUserParams);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// 2. KPIs POR CLIENTE (agregación) - filtradas por usuario
$sqlKpi = "SELECT op.id_cliente, COUNT(*) as ops, IFNULL(SUM(op.monto), 0) as monto_total, SUM(CASE WHEN op.requiere_aviso = 1 THEN 1 ELSE 0 END) as avisos FROM operaciones_pld op JOIN clientes c ON op.id_cliente = c.id_cliente WHERE op.id_status = 1" . ($opUserFilter ? $opUserFilter : "") . " GROUP BY op.id_cliente";
$stmtClientes = $pdo->prepare($sqlKpi);
$stmtClientes->execute($opUserParams);
$kpiPorCliente = [];
while ($r = $stmtClientes->fetch(PDO::FETCH_ASSOC)) {
    $kpiPorCliente[$r['id_cliente']] = $r;
}

// 3. RANGOS DE RIESGO (homologados con reporte_riesgos)
$riesgoRangos = [];
try {
    $stmtR = $pdo->query("SELECT * FROM config_riesgo_rangos ORDER BY min_valor ASC");
    $riesgoRangos = $stmtR ? $stmtR->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { /* fallback */ }

function obtenerSemaforo($nivel, $rangos) {
    $nivel = (float)$nivel;
    $claseMap = ['alto' => 'bg-danger', 'medio' => 'bg-warning text-dark', 'bajo' => 'bg-success'];
    $iconoMap = ['alto' => 'fa-triangle-exclamation', 'medio' => 'fa-circle-exclamation', 'bajo' => 'fa-check-circle'];
    foreach ($rangos as $r) {
        if ($nivel >= (float)$r['min_valor'] && $nivel <= (float)$r['max_valor']) {
            $val = strtolower(trim($r['nivel'] ?? ''));
            if (!in_array($val, ['alto', 'medio', 'bajo'], true)) {
                $val = (stripos($r['nivel'], 'alto') !== false) ? 'alto' : ((stripos($r['nivel'], 'medio') !== false) ? 'medio' : 'bajo');
            }
            return [
                'clase' => $claseMap[$val] ?? 'bg-secondary',
                'texto' => strtoupper($val),
                'icono' => $iconoMap[$val] ?? 'fa-circle',
                'val' => $val
            ];
        }
    }
    return ['clase' => 'bg-secondary', 'texto' => 'N/A', 'icono' => 'fa-circle', 'val' => 'bajo'];
}

// 4. TRANSACCIONES DETALLADAS - filtradas por usuario
$sql = "SELECT 
            op.id_operacion, op.id_cliente, op.fecha_operacion, op.monto, op.tipo_operacion, op.requiere_aviso,
            c.no_contrato, c.nivel_riesgo,
            CASE WHEN c.id_tipo_persona = 1 THEN CONCAT(cf.nombre, ' ', cf.apellido_paterno)
                 WHEN c.id_tipo_persona = 2 THEN cm.razon_social ELSE c.alias END AS cliente_nombre,
            COALESCE(cf.tax_id, cm.tax_id) AS rfc_cliente, av.folio_sppld, av.estatus AS estatus_aviso
        FROM operaciones_pld op
        JOIN clientes c ON op.id_cliente = c.id_cliente
        LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
        LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
        LEFT JOIN aviso_transacciones at ON op.id_operacion = at.id_operacion
        LEFT JOIN avisos_pld av ON at.id_aviso = av.id_aviso
        WHERE op.id_status = 1" . ($opUserFilter ? $opUserFilter : "") . "
        ORDER BY op.fecha_operacion DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($opUserParams);
$db_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. DATOS PARA JAVASCRIPT (con riesgo homologado)
$reporte_json = [];
foreach ($db_data as $row) {
    $s = obtenerSemaforo($row['nivel_riesgo'] ?? 0, $riesgoRangos);
    $reporte_json[] = [
        'id' => $row['id_operacion'],
        'id_cliente' => (int)$row['id_cliente'],
        'fecha' => $row['fecha_operacion'],
        'nombre' => mb_strtoupper($row['cliente_nombre'] ?? 'SIN NOMBRE'),
        'contrato' => $row['no_contrato'] ?? 'N/A',
        'rfc' => $row['rfc_cliente'] ?? 'N/A',
        'monto' => floatval($row['monto']),
        'aviso' => (int)$row['requiere_aviso'],
        'tipo' => strtoupper($row['tipo_operacion'] ?? 'VENTA'),
        'folio' => !empty($row['folio_sppld']) ? $row['folio_sppld'] : 'SIN FOLIO',
        'estatus_aviso' => strtoupper($row['estatus_aviso'] ?? 'PENDIENTE'),
        'nivel_riesgo' => (float)($row['nivel_riesgo'] ?? 0),
        'semaforo_clase' => $s['clase'],
        'semaforo_texto' => $s['texto'],
        'semaforo_icono' => $s['icono']
    ];
}

// Resumen por cliente para KPIs
$resumen_clientes = [];
$idsClientes = array_keys($kpiPorCliente);
if (!empty($idsClientes)) {
    $placeholders = implode(',', array_fill(0, count($idsClientes), '?'));
    $stmtCli = $pdo->prepare("
        SELECT c.id_cliente, c.nivel_riesgo, c.alias, c.no_contrato,
            COALESCE(cm.razon_social, TRIM(CONCAT(COALESCE(cf.nombre,''),' ', COALESCE(cf.apellido_paterno,''),' ', COALESCE(cf.apellido_materno,''))), c.alias) AS nombre_display
        FROM clientes c
        LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
        LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
        WHERE c.id_cliente IN ($placeholders)
    ");
    $stmtCli->execute(array_values($idsClientes));
    $clientesMap = [];
    while ($r = $stmtCli->fetch(PDO::FETCH_ASSOC)) {
        $clientesMap[$r['id_cliente']] = $r;
    }
} else {
    $clientesMap = [];
}
foreach ($kpiPorCliente as $idCliente => $kpi) {
    $cli = $clientesMap[$idCliente] ?? [];
    $s = obtenerSemaforo($cli['nivel_riesgo'] ?? 0, $riesgoRangos);
    $nombreDisplay = trim($cli['nombre_display'] ?? $cli['alias'] ?? 'Sin nombre');
    $resumen_clientes[] = [
        'id_cliente' => (int)$idCliente,
        'nombre' => mb_strtoupper($nombreDisplay ?: 'Sin nombre'),
        'contrato' => $cli['no_contrato'] ?? 'N/A',
        'ops' => (int)$kpi['ops'],
        'monto_total' => floatval($kpi['monto_total']),
        'avisos' => (int)$kpi['avisos'],
        'nivel_riesgo' => (float)($cli['nivel_riesgo'] ?? 0),
        'semaforo_clase' => $s['clase'],
        'semaforo_texto' => $s['texto'],
        'semaforo_icono' => $s['icono']
    ];
}

include 'templates/header.php';
?>
<title>Reporte de Transacciones - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/clientes.css">
<style>
:root {
    --rt-primary: #4361ee;
    --rt-primary-dark: #3a0ca3;
    --rt-danger: #ef476f;
    --rt-warning: #f77f00;
    --rt-success: #06d6a0;
    --rt-dark: #1d3557;
    --rt-light: #f8f9fc;
    --rt-border: #e2e8f0;
    --rt-shadow: 0 4px 24px rgba(0,0,0,.06);
    --rt-radius: 16px;
    --rt-radius-sm: 10px;
    --rt-transition: .25s cubic-bezier(.4,0,.2,1);
}
.rt-wrapper { max-width: 1300px; margin: 0 auto; }
.rt-page-header {
    background: linear-gradient(135deg, var(--rt-primary) 0%, var(--rt-primary-dark) 100%);
    color: #fff; border-radius: var(--rt-radius); padding: 1.75rem 2rem; margin-bottom: 1.75rem;
    position: relative; overflow: hidden;
}
.rt-page-header::before {
    content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px;
    background: rgba(255,255,255,.06); border-radius: 50%;
}
.rt-page-header h2 { font-size: 1.5rem; font-weight: 800; margin-bottom: .25rem; }
.rt-page-header p { opacity: .9; margin: 0; font-size: .9rem; }
.rt-kpi-card {
    border: none; border-radius: var(--rt-radius); padding: 1.25rem 1.5rem;
    transition: var(--rt-transition); cursor: default; overflow: hidden; position: relative;
    box-shadow: var(--rt-shadow); height: 110px; display: flex; flex-direction: column; justify-content: center;
}
.rt-kpi-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
}
.rt-kpi-card:hover {
    transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,.12);
}
.rt-kpi-card.rt-total { background: linear-gradient(135deg, var(--rt-primary) 0%, var(--rt-primary-dark) 100%); color: #fff; }
.rt-kpi-card.rt-total::before { background: rgba(255,255,255,.4); }
.rt-kpi-card.rt-monto { background: linear-gradient(135deg, #06d6a0 0%, #028a6e 100%); color: #fff; }
.rt-kpi-card.rt-monto::before { background: rgba(255,255,255,.4); }
.rt-kpi-card.rt-avisos { background: linear-gradient(135deg, #f77f00 0%, #e85d04 100%); color: #fff; }
.rt-kpi-card.rt-avisos::before { background: rgba(255,255,255,.4); }
.rt-kpi-card .rt-kpi-label { font-size: .85rem; opacity: .9; font-weight: 500; }
.rt-kpi-card .rt-kpi-value { font-size: 1.75rem; font-weight: 800; letter-spacing: -0.02em; }
.rt-filter-card {
    background: #fff; border: none; border-radius: var(--rt-radius);
    box-shadow: var(--rt-shadow); margin-bottom: 1.5rem;
}
.rt-filter-card .card-body {
    padding: 1.25rem 1.5rem; background: linear-gradient(180deg, #fafbff 0%, #fff 100%);
    border-radius: var(--rt-radius); border: 1px solid var(--rt-border);
}
.rt-filter-card .form-control, .rt-filter-card .form-select { border-radius: 8px; border: 1.5px solid var(--rt-border); padding: .55rem .85rem; }
.rt-filter-card .form-control:focus, .rt-filter-card .form-select:focus { border-color: var(--rt-primary); box-shadow: 0 0 0 3px rgba(67,97,238,.12); }
.rt-filter-card .form-label { font-size: .8rem; font-weight: 600; color: var(--rt-dark); margin-bottom: .35rem; }
.rt-filter-actions .btn { border-radius: 8px; font-weight: 600; }
.rt-filter-badge { font-size: .75rem; }
.rt-table-card {
    border: none; border-radius: var(--rt-radius); overflow: hidden; box-shadow: var(--rt-shadow);
}
.rt-table-card .table { margin-bottom: 0; }
.rt-table-card .table thead th {
    background: linear-gradient(135deg, #1d3557 0%, #2d4a6f 100%) !important;
    color: #fff !important; font-weight: 600; font-size: .78rem;
    text-transform: uppercase; letter-spacing: .03em; padding: 1rem .75rem; border: none;
}
.rt-table-card .table tbody tr { transition: var(--rt-transition); }
.rt-table-card .table tbody tr:hover {
    background: linear-gradient(90deg, rgba(67,97,238,.04) 0%, transparent 100%);
}
.rt-table-card .table tbody td {
    padding: 1rem .75rem; vertical-align: middle; border-color: var(--rt-border);
}
.sort-btn {
    background: transparent; border: none; color: #fff !important;
    font-weight: 600; padding: 14px 10px; width: 100%; text-align: left;
    text-transform: uppercase; font-size: .78rem; transition: var(--rt-transition);
}
.sort-btn:hover { background: rgba(255,255,255,.12); }
.badge-riesgo {
    padding: 6px 12px; font-size: .75rem; border-radius: 50px;
    display: inline-block; text-align: center; font-weight: 600;
}
.text-monto { font-family: 'JetBrains Mono', 'Fira Code', monospace; font-weight: 600; font-size: 1rem; }
.rt-audit-btn {
    background: linear-gradient(135deg, var(--rt-primary), var(--rt-primary-dark));
    border: none; color: #fff !important; font-weight: 600; padding: .35rem .75rem;
    border-radius: 8px; font-size: .8rem; transition: var(--rt-transition);
}
.rt-audit-btn:hover { transform: translateY(-1px); color: #fff !important; }
.rt-client-section { margin-bottom: 1.5rem; }
.rt-client-section h6 { font-weight: 700; color: var(--rt-dark); margin-bottom: 1rem; }
.rt-client-row { cursor: pointer; transition: var(--rt-transition); }
.rt-client-row:hover { background: linear-gradient(90deg, rgba(67,97,238,.08) 0%, transparent 100%) !important; }
.rt-client-row.rt-selected { background: linear-gradient(90deg, rgba(67,97,238,.15) 0%, rgba(67,97,238,.05) 100%) !important; box-shadow: inset 3px 0 0 var(--rt-primary); }
@media (max-width: 768px) {
    .rt-page-header { padding: 1.25rem; }
    .rt-kpi-card .rt-kpi-value { font-size: 1.4rem; }
}
</style>
</head>
<body>
<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="container-fluid px-4 pb-5 pt-3">
<div class="rt-wrapper">

    <div class="rt-page-header">
        <h2><i class="fa-solid fa-file-invoice-dollar me-2"></i>Reporte de Transacciones</h2>
        <p>Montos por cliente, período y avisos SAT</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="rt-kpi-card rt-total">
                <div class="rt-kpi-label"><i class="fa-solid fa-list-check me-1"></i> Transacciones Totales</div>
                <div class="rt-kpi-value" id="kpiTotalOps"><?= number_format($stats['total_ops']) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="rt-kpi-card rt-monto">
                <div class="rt-kpi-label"><i class="fa-solid fa-money-bill-trend-up me-1"></i> Monto Acumulado</div>
                <div class="rt-kpi-value" id="kpiMontoTotal">$<?= number_format($stats['monto_total'], 2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="rt-kpi-card rt-avisos">
                <div class="rt-kpi-label"><i class="fa-solid fa-bell me-1"></i> Avisos por Umbral</div>
                <div class="rt-kpi-value" id="kpiAvisos"><?= number_format($stats['total_avisos']) ?></div>
            </div>
        </div>
    </div>

    <div id="rtFiltroCliente" class="alert alert-info py-2 mb-3 d-none" role="alert">
        <i class="fa-solid fa-user-check me-2"></i> <span id="rtFiltroClienteNombre"></span>
        <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="rtBtnLimpiarCliente" title="Ver todos"><i class="fa-solid fa-times"></i> Quitar filtro</button>
    </div>

    <div class="card rt-filter-card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-filter me-2"></i>Filtros</h6>
                <button type="button" class="btn btn-outline-secondary btn-sm rt-filter-badge" id="rtBtnLimpiarFiltros" title="Limpiar todos los filtros">
                    <i class="fa-solid fa-eraser me-1"></i> Limpiar filtros
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-4 col-lg-3">
                    <label class="form-label">Buscar</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" id="jsSearchInput" class="form-control border-start-0 ps-0" placeholder="Cliente, RFC, contrato, folio...">
                        <button type="button" class="btn btn-outline-secondary" id="rtBtnClearSearch" title="Limpiar búsqueda" style="display:none;"><i class="fa-solid fa-times"></i></button>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">Fecha desde</label>
                    <input type="date" id="rtFiltroFechaDesde" class="form-control">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">Fecha hasta</label>
                    <input type="date" id="rtFiltroFechaHasta" class="form-control">
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">Riesgo</label>
                    <select id="rtFiltroRiesgo" class="form-select">
                        <option value="">Todos</option>
                        <option value="ALTO">Alto</option>
                        <option value="MEDIO">Medio</option>
                        <option value="BAJO">Bajo</option>
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">Tipo operación</label>
                    <select id="rtFiltroTipo" class="form-select">
                        <option value="">Todos</option>
                        <?php
                        $tiposUnicos = array_unique(array_map(function($r) { return strtoupper(trim($r['tipo_operacion'] ?? '')); }, $db_data));
                        $tiposUnicos = array_filter($tiposUnicos);
                        sort($tiposUnicos);
                        foreach ($tiposUnicos as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 col-lg-2">
                    <label class="form-label">Aviso SAT</label>
                    <select id="rtFiltroAviso" class="form-select">
                        <option value="">Todos</option>
                        <option value="1">Con aviso</option>
                        <option value="0">Sin aviso</option>
                    </select>
                </div>
                <div class="col-12 col-lg-1 d-flex align-items-end rt-filter-actions">
                    <button type="button" class="btn btn-primary w-100" id="btnAplicarFiltros" title="Aplicar filtros">
                        <i class="fa-solid fa-check"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="rt-client-section">
        <h6><i class="fa-solid fa-users me-1"></i> Resumen por Cliente</h6>
        <div class="card rt-table-card mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Cliente</th>
                            <th class="text-center">Ops</th>
                            <th class="text-center">Monto Total</th>
                            <th class="text-center">Avisos</th>
                            <th class="text-center">Riesgo</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="jsClientesBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-table-list me-1"></i> Detalle de Transacciones</h6>
    <div class="card rt-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4"><button class="sort-btn" data-sort="fecha">Fecha <i class="fa-solid fa-sort ms-1 opacity-50"></i></button></th>
                        <th><button class="sort-btn" data-sort="nombre">Cliente <i class="fa-solid fa-sort ms-1 opacity-50"></i></button></th>
                        <th class="text-center"><button class="sort-btn" data-sort="monto">Monto (MXN) <i class="fa-solid fa-sort ms-1 opacity-50"></i></button></th>
                        <th class="text-center">Riesgo</th>
                        <th class="text-center">Estado Aviso</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody id="jsTableBody"></tbody>
            </table>
        </div>
    </div>

</div>
</div>

<script>
const rawData = <?= json_encode($reporte_json) ?>;
const resumenClientes = <?= json_encode($resumen_clientes) ?>;
let dataFiltrada = [...rawData];
let clientesFiltrados = [...resumenClientes];
let sortAsc = 1;
let currentSortKey = '';
let selectedClienteId = null;

function esc(s) {
    if (s == null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function actualizarKPIs() {
    const totalOps = dataFiltrada.length;
    const montoTotal = dataFiltrada.reduce((a, t) => a + t.monto, 0);
    const avisos = dataFiltrada.reduce((a, t) => a + (t.aviso === 1 ? 1 : 0), 0);
    document.getElementById('kpiTotalOps').textContent = totalOps.toLocaleString('es-MX');
    document.getElementById('kpiMontoTotal').textContent = '$' + montoTotal.toLocaleString('es-MX', {minimumFractionDigits: 2});
    document.getElementById('kpiAvisos').textContent = avisos.toLocaleString('es-MX');
}

function renderClientes(items) {
    const tbody = document.getElementById('jsClientesBody');
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">No hay clientes con transacciones.</td></tr>';
        return;
    }
    tbody.innerHTML = items.map(c => `
        <tr class="rt-client-row ${selectedClienteId === c.id_cliente ? 'rt-selected' : ''}" data-id-cliente="${c.id_cliente}" data-nombre="${esc(c.nombre)}" title="Clic para filtrar KPIs por este cliente">
            <td class="ps-4">
                <div class="fw-bold text-dark">${esc(c.nombre)}</div>
                <small class="text-muted">${esc(c.contrato)}</small>
            </td>
            <td class="text-center fw-bold">${c.ops}</td>
            <td class="text-center text-monto">$${c.monto_total.toLocaleString('es-MX', {minimumFractionDigits: 2})}</td>
            <td class="text-center">${c.avisos}</td>
            <td class="text-center">
                <span class="badge badge-riesgo ${c.semaforo_clase}">
                    <i class="fa-solid ${c.semaforo_icono} me-1"></i> ${esc(c.semaforo_texto)}
                </span>
            </td>
            <td class="text-end pe-4" onclick="event.stopPropagation()">
                <a href="cliente_detalle.php?id=${c.id_cliente}" class="btn btn-sm rt-audit-btn"><i class="fa-solid fa-magnifying-glass me-1"></i> Auditar</a>
            </td>
        </tr>
    `).join('');
}

function renderTable(items) {
    const tbody = document.getElementById('jsTableBody');
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">No se encontraron transacciones.</td></tr>';
        return;
    }
    tbody.innerHTML = items.map(t => `
        <tr>
            <td class="ps-4">
                <div class="fw-bold text-dark">${t.fecha}</div>
                <small class="text-muted" style="font-size: 10px;">${esc(t.tipo)}</small>
            </td>
            <td>
                <div class="fw-bold text-dark" style="font-size: 0.9rem;">${esc(t.nombre)}</div>
                <small class="text-muted">RFC: ${esc(t.rfc)} | # ${esc(t.contrato)}</small>
            </td>
            <td class="text-center">
                <div class="text-monto">$${t.monto.toLocaleString('es-MX', {minimumFractionDigits: 2})}</div>
            </td>
            <td class="text-center">
                <span class="badge badge-riesgo ${t.semaforo_clase}">
                    <i class="fa-solid ${t.semaforo_icono} me-1"></i> ${esc(t.semaforo_texto)}
                </span>
            </td>
            <td class="text-center">
                ${t.aviso === 1 
                    ? `<span class="badge bg-warning text-dark badge-riesgo">AVISO: ${esc(t.estatus_aviso)}</span><br><small class="text-muted">SAT: ${esc(t.folio)}</small>`
                    : `<span class="badge bg-light text-muted border badge-riesgo">SIN OBLIGACIÓN</span>`
                }
            </td>
            <td class="text-end pe-4">
                <div class="btn-group">
                    <a href="cliente_detalle.php?id=${t.id_cliente}" class="btn btn-sm btn-outline-primary border-0" title="Auditar"><i class="fa-solid fa-eye"></i></a>
                </div>
            </td>
        </tr>
    `).join('');
}

function aplicarFiltros() {
    const term = document.getElementById('jsSearchInput').value.toLowerCase().trim();
    const fechaDesde = document.getElementById('rtFiltroFechaDesde').value || '';
    const fechaHasta = document.getElementById('rtFiltroFechaHasta').value || '';
    const riesgo = document.getElementById('rtFiltroRiesgo').value || '';
    const tipo = document.getElementById('rtFiltroTipo').value || '';
    const aviso = document.getElementById('rtFiltroAviso').value;

    let base = rawData;

    if (term) {
        base = base.filter(t =>
            t.nombre.toLowerCase().includes(term) ||
            t.rfc.toLowerCase().includes(term) ||
            t.contrato.toLowerCase().includes(term) ||
            t.folio.toLowerCase().includes(term)
        );
    }
    if (fechaDesde) {
        base = base.filter(t => (t.fecha || '').substring(0, 10) >= fechaDesde);
    }
    if (fechaHasta) {
        base = base.filter(t => (t.fecha || '').substring(0, 10) <= fechaHasta);
    }
    if (riesgo) {
        base = base.filter(t => (t.semaforo_texto || '').toUpperCase() === riesgo);
    }
    if (tipo) {
        base = base.filter(t => (t.tipo || '').toUpperCase() === tipo);
    }
    if (aviso !== '' && aviso !== null) {
        const av = parseInt(aviso, 10);
        base = base.filter(t => (t.aviso || 0) === av);
    }

    const idsClientes = [...new Set(base.map(t => t.id_cliente))];
    clientesFiltrados = resumenClientes.filter(c => idsClientes.includes(c.id_cliente));

    if (selectedClienteId !== null && idsClientes.includes(selectedClienteId)) {
        dataFiltrada = base.filter(t => t.id_cliente === selectedClienteId);
    } else {
        if (selectedClienteId !== null && !idsClientes.includes(selectedClienteId)) {
            selectedClienteId = null;
        }
        dataFiltrada = base;
    }

    actualizarKPIs();
    renderClientes(clientesFiltrados);
    aplicarOrden();
    actualizarBadgeFiltro();
    document.getElementById('rtBtnClearSearch').style.display = term ? '' : 'none';
}

function filtrar() {
    aplicarFiltros();
}

function limpiarFiltros() {
    document.getElementById('jsSearchInput').value = '';
    document.getElementById('rtFiltroFechaDesde').value = '';
    document.getElementById('rtFiltroFechaHasta').value = '';
    document.getElementById('rtFiltroRiesgo').value = '';
    document.getElementById('rtFiltroTipo').value = '';
    document.getElementById('rtFiltroAviso').value = '';
    selectedClienteId = null;
    document.getElementById('rtFiltroCliente').classList.add('d-none');
    document.getElementById('rtBtnClearSearch').style.display = 'none';
    aplicarFiltros();
}

function filtrarPorCliente(idCliente) {
    selectedClienteId = idCliente;
    filtrar();
}

function limpiarFiltroCliente() {
    selectedClienteId = null;
    document.getElementById('rtFiltroCliente').classList.add('d-none');
    aplicarFiltros();
}

function actualizarBadgeFiltro() {
    const badge = document.getElementById('rtFiltroCliente');
    const nombre = document.getElementById('rtFiltroClienteNombre');
    if (selectedClienteId !== null) {
        const c = resumenClientes.find(x => x.id_cliente === selectedClienteId);
        nombre.textContent = 'Filtrando por: ' + (c ? c.nombre : '');
        badge.classList.remove('d-none');
    } else {
        badge.classList.add('d-none');
    }
}

function aplicarOrden() {
    if (currentSortKey) {
        const key = currentSortKey;
        const mult = sortAsc;
        dataFiltrada.sort((a, b) => {
            let va = a[key], vb = b[key];
            if (typeof va === 'string') va = va.toLowerCase();
            if (typeof vb === 'string') vb = vb.toLowerCase();
            if (va < vb) return -1 * mult;
            if (va > vb) return 1 * mult;
            return 0;
        });
    }
    renderTable(dataFiltrada);
}

document.getElementById('jsSearchInput').addEventListener('input', function() {
    document.getElementById('rtBtnClearSearch').style.display = this.value.trim() ? '' : 'none';
    aplicarFiltros();
});
document.getElementById('jsSearchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') aplicarFiltros();
});
document.getElementById('rtBtnClearSearch').addEventListener('click', function() {
    document.getElementById('jsSearchInput').value = '';
    this.style.display = 'none';
    aplicarFiltros();
});
document.getElementById('btnAplicarFiltros').addEventListener('click', aplicarFiltros);
document.getElementById('rtBtnLimpiarFiltros').addEventListener('click', limpiarFiltros);
document.getElementById('rtBtnLimpiarCliente').addEventListener('click', limpiarFiltroCliente);
document.getElementById('rtFiltroFechaDesde').addEventListener('change', aplicarFiltros);
document.getElementById('rtFiltroFechaHasta').addEventListener('change', aplicarFiltros);
document.getElementById('rtFiltroRiesgo').addEventListener('change', aplicarFiltros);
document.getElementById('rtFiltroTipo').addEventListener('change', aplicarFiltros);
document.getElementById('rtFiltroAviso').addEventListener('change', aplicarFiltros);

document.getElementById('jsClientesBody').addEventListener('click', function(e) {
    const row = e.target.closest('.rt-client-row');
    if (!row || e.target.closest('a')) return;
    const id = parseInt(row.dataset.idCliente, 10);
    filtrarPorCliente(id);
});

document.querySelectorAll('.sort-btn[data-sort]').forEach(btn => {
    btn.addEventListener('click', function() {
        const key = this.getAttribute('data-sort');
        if (currentSortKey === key) sortAsc *= -1;
        else { currentSortKey = key; sortAsc = 1; }
        document.querySelectorAll('.sort-btn i').forEach(i => i.className = 'fa-solid fa-sort ms-1 opacity-50');
        const icon = this.querySelector('i');
        if (icon) icon.className = sortAsc === 1 ? 'fa-solid fa-sort-up ms-1 text-white' : 'fa-solid fa-sort-down ms-1 text-white';
        aplicarOrden();
    });
});

actualizarKPIs();
renderClientes(clientesFiltrados);
renderTable(dataFiltrada);
</script>

<?php include 'templates/footer.php'; ?>
