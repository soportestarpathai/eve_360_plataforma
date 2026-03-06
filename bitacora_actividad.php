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
requireReporteActivo($pdo, 'bitacora_actividad.php');

// VAL-PLD-001: Verificar habilitación PLD
if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

include 'templates/header.php';

// 1. OBTENER FILTROS
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$aviso_filter = $_GET['aviso'] ?? '';
$search = $_GET['search'] ?? '';

// 2. CONSTRUIR CONSULTA SQL DINÁMICA
$where_clauses = ["DATE(COALESCE(fecha_registro, fecha_operacion)) BETWEEN ? AND ?"];
$params = [$fecha_inicio, $fecha_fin];

if ($aviso_filter !== '') {
    $where_clauses[] = "tipo_aviso = ?";
    $params[] = $aviso_filter;
}

if ($search !== '') {
    // Buscamos dentro del JSON del cliente, el nombre del XML o el tipo de aviso
    $searchLike = "%$search%";
    $where_clauses[] = "(COALESCE(kyc_snapshot_json,'') LIKE ? OR COALESCE(xml_nombre_archivo,'') LIKE ? OR COALESCE(tipo_aviso,'') LIKE ?)";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$where_sql = implode(' AND ', $where_clauses);

// 3. EJECUTAR CONSULTA (columnas opcionales: kyc_snapshot_json, xml_contenido, xml_nombre_archivo)
$resultados_db = [];
$error_msj = "";

try {
    $query = "SELECT * FROM operaciones_pld WHERE $where_sql ORDER BY COALESCE(fecha_registro, fecha_operacion) DESC LIMIT 1000";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $resultados_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_msj = "Error SQL: " . $e->getMessage();
}

// 4. PREPARAR DATOS (Limpieza y extracción)
$reporte = [];
$countAcumulacion = 0; $countSospechosa = 0; $countXMLs = 0;

foreach ($resultados_db as $r) {
    $tipo_aviso = strtolower(trim($r['tipo_aviso'] ?? ''));
    $nombre_xml = $r['xml_nombre_archivo'] ?? '';
    
    // Contadores
    if (strpos($tipo_aviso, 'acumulacion') !== false) $countAcumulacion++;
    if (($r['es_sospechosa'] ?? 0) == 1 || strpos($tipo_aviso, 'sospechosa') !== false) $countSospechosa++;
    if ($nombre_xml !== '') $countXMLs++;

    // Mapeo de colores
    $badgeClase = 'bg-secondary';
    if (strpos($tipo_aviso, 'acumulacion') !== false) $badgeClase = 'bg-warning text-dark';
    if (($r['es_sospechosa'] ?? 0) == 1 || strpos($tipo_aviso, 'sospechosa') !== false) $badgeClase = 'bg-danger';

    // Extraer nombre del cliente desde el JSON (si existe)
    $cliente_nombre = "Cliente ID: " . ($r['id_cliente'] ?? 'N/A');
    if (!empty($r['kyc_snapshot_json'])) {
        $kyc = json_decode($r['kyc_snapshot_json'], true);
        if (is_array($kyc)) {
            if (!empty($kyc['alias'])) {
                $cliente_nombre = $kyc['alias'];
            } elseif (!empty($kyc['nombre'])) {
                $cliente_nombre = $kyc['nombre'];
            } elseif (!empty($kyc['razon_social'])) {
                $cliente_nombre = $kyc['razon_social'];
            } elseif (!empty($kyc['denominacion_razon'])) {
                $cliente_nombre = $kyc['denominacion_razon'];
            }
        }
    }

    $xml_raw = $r['xml_contenido'] ?? '';
    $xml_limpio = $xml_raw !== '' ? mb_convert_encoding((string)$xml_raw, 'UTF-8', 'auto') : '';

    $fechaTs = strtotime($r['fecha_registro'] ?? $r['fecha_operacion'] ?? 'now');

    $reporte[] = [
        'id' => $r['id_operacion'],
        'fecha_formateada' => date("d/m/Y H:i:s", $fechaTs),
        'fecha_raw' => $fechaTs,
        'cliente' => $cliente_nombre,
        'tipo_aviso' => strtoupper($tipo_aviso !== '' ? $tipo_aviso : 'SIN AVISO'),
        'clase_badge' => $badgeClase,
        'monto' => (float)($r['monto'] ?? 0),
        'monto_formateado' => '$ ' . number_format((float)($r['monto'] ?? 0), 2),
        'nombre_xml' => $nombre_xml,
        'xml_contenido' => $xml_limpio
    ];
}
?>

<title>Bitácora de actividad (SAT) - <?= htmlspecialchars($appConfig['nombre_empresa'] ?? 'EVE360') ?></title>
<link rel="stylesheet" href="assets/css/clientes.css">
<style>
:root {
    --ba-primary: #4361ee;
    --ba-primary-dark: #3a0ca3;
    --ba-dark: #1d3557;
    --ba-border: #e2e8f0;
    --ba-shadow: 0 4px 24px rgba(0,0,0,.06);
    --ba-radius: 16px;
    --ba-radius-sm: 10px;
    --ba-transition: .25s cubic-bezier(.4,0,.2,1);
}
.ba-wrapper { max-width: 1400px; margin: 0 auto; }
.ba-page-header {
    background: linear-gradient(135deg, var(--ba-primary) 0%, var(--ba-primary-dark) 100%);
    color: #fff; border-radius: var(--ba-radius); padding: 1.75rem 2rem; margin-bottom: 1.5rem;
    position: relative; overflow: hidden;
}
.ba-page-header::before {
    content: ''; position: absolute; top: -50%; right: -10%; width: 280px; height: 280px;
    background: rgba(255,255,255,.06); border-radius: 50%;
}
.ba-page-header h2 { font-size: 1.5rem; font-weight: 800; margin-bottom: .25rem; }
.ba-page-header p { opacity: .9; margin: 0; font-size: .9rem; }
.ba-kpi-card {
    border: none; border-radius: var(--ba-radius); padding: 1.25rem 1.5rem;
    transition: var(--ba-transition); cursor: default; overflow: hidden; position: relative;
    box-shadow: var(--ba-shadow); height: 110px; display: flex; flex-direction: column; justify-content: center;
}
.ba-kpi-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
}
.ba-kpi-card:hover {
    transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,.12);
}
.ba-kpi-card.orange { background: linear-gradient(135deg, #f77f00 0%, #e85d04 100%); color: #fff; }
.ba-kpi-card.orange::before { background: rgba(255,255,255,.4); }
.ba-kpi-card.red { background: linear-gradient(135deg, #d62828 0%, #9d0208 100%); color: #fff; }
.ba-kpi-card.red::before { background: rgba(255,255,255,.4); }
.ba-kpi-card.blue { background: linear-gradient(135deg, #4cc9f0 0%, #0077b6 100%); color: #fff; }
.ba-kpi-card.blue::before { background: rgba(255,255,255,.4); }
.ba-kpi-label { font-size: .85rem; opacity: .9; font-weight: 500; }
.ba-kpi-value { font-size: 1.75rem; font-weight: 800; letter-spacing: -0.02em; }
.ba-filter-card {
    background: #fff; border: none; border-radius: var(--ba-radius);
    box-shadow: var(--ba-shadow); margin-bottom: 1.5rem;
}
.ba-filter-card .card-body {
    padding: 1.25rem 1.5rem; background: linear-gradient(180deg, #fafbff 0%, #fff 100%);
    border-radius: var(--ba-radius); border: 1px solid var(--ba-border);
}
.ba-filter-card .form-control, .ba-filter-card .form-select {
    border-radius: 8px; border: 1.5px solid var(--ba-border); padding: .55rem .85rem;
}
.ba-filter-card .form-control:focus, .ba-filter-card .form-select:focus {
    border-color: var(--ba-primary); box-shadow: 0 0 0 3px rgba(67,97,238,.12);
}
.ba-filter-card .form-label { font-size: .8rem; font-weight: 600; color: var(--ba-dark); margin-bottom: .35rem; }
.ba-table-card {
    border: none; border-radius: var(--ba-radius); overflow: hidden; box-shadow: var(--ba-shadow);
}
.ba-table-card .table-responsive {
    -webkit-overflow-scrolling: touch;
}
.ba-table-card .table { margin-bottom: 0; }
.ba-table-card .table thead th {
    background: linear-gradient(135deg, #1d3557 0%, #2d4a6f 100%) !important;
    color: #fff !important; font-weight: 600; font-size: .78rem;
    text-transform: uppercase; letter-spacing: .03em; padding: 1rem .75rem; border: none;
}
.ba-table-card .table tbody tr { transition: var(--ba-transition); }
.ba-table-card .table tbody tr:hover {
    background: linear-gradient(90deg, rgba(67,97,238,.04) 0%, transparent 100%);
}
.ba-table-card .table tbody td {
    padding: 1rem .75rem; vertical-align: middle; border-color: var(--ba-border);
}
.ba-sort-btn {
    background: transparent; border: none; color: #fff !important;
    font-weight: 600; padding: 14px 10px; width: 100%; text-align: left;
    text-transform: uppercase; font-size: .78rem; transition: var(--ba-transition);
}
.ba-sort-btn:hover { background: rgba(255,255,255,.12); }
.ba-sort-btn.center { text-align: center; }
.ba-sort-btn.center[style*="cursor"] { cursor: default; }
.ba-xml-badge {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    background: #f1f5f9; padding: 5px 10px; border-radius: 8px;
    border: 1px solid var(--ba-border); font-size: .8rem; color: var(--ba-dark);
    display: inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis;
}
.ba-download-btn {
    background: linear-gradient(135deg, var(--ba-primary), var(--ba-primary-dark));
    border: none; color: #fff !important; font-weight: 600; padding: .35rem .75rem;
    border-radius: 8px; font-size: .8rem; transition: var(--ba-transition);
}
.ba-download-btn:hover { transform: translateY(-1px); color: #fff !important; }
.text-monto-ba { font-family: 'JetBrains Mono', 'Fira Code', monospace; font-weight: 600; font-size: 1rem; }
@media (max-width: 991px) {
    .ba-page-header { padding: 1.25rem 1.5rem; }
    .ba-page-header h2 { font-size: 1.25rem; }
    .ba-kpi-card { height: 100px; padding: 1rem 1.25rem; }
    .ba-kpi-value { font-size: 1.5rem; }
}
@media (max-width: 768px) {
    .ba-page-header { padding: 1rem 1.25rem; }
    .ba-page-header h2 { font-size: 1.1rem; }
    .ba-page-header p { font-size: .8rem; }
    .ba-kpi-card { height: 90px; padding: .9rem 1rem; }
    .ba-kpi-value { font-size: 1.35rem; }
    .ba-kpi-label { font-size: .75rem; }
}
@media (max-width: 576px) {
    .ba-wrapper { padding: 0 .5rem !important; }
    .ba-filter-card .card-body { padding: 1rem; }
    .ba-table-card .table thead { display: none; }
    .ba-table-card .table, .ba-table-card tbody, .ba-table-card tr { display: block; }
    .ba-table-card tr.ba-empty-row td { display: block; padding: 2rem 1rem; border: none; }
    .ba-table-card tr.ba-empty-row td::before { display: none; }
    .ba-table-card tbody tr {
        margin-bottom: 1rem; padding: 1rem; border-radius: var(--ba-radius-sm);
        border: 1px solid var(--ba-border); background: #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,.04);
    }
    .ba-table-card tbody td {
        display: flex; align-items: center; justify-content: space-between; gap: .75rem;
        padding: .5rem 0; border: none; border-bottom: 1px solid #f1f5f9;
        font-size: .9rem;
    }
    .ba-table-card tbody td:last-child { border-bottom: none; }
    .ba-table-card tbody td::before {
        content: attr(data-label); font-weight: 600; color: var(--ba-dark);
        font-size: .75rem; flex-shrink: 0; min-width: 90px;
    }
    .ba-sort-btn { font-size: .7rem; padding: 10px 6px; }
    .ba-xml-badge { font-size: .7rem; max-width: 100%; }
}
</style>
</head>
<body>
<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="container-fluid px-4 pb-5 pt-3">
<div class="ba-wrapper">

    <div class="ba-page-header">
        <h2><i class="fa-solid fa-clock-rotate-left me-2"></i>Bitácora de actividad de usuarios (SAT)</h2>
        <p>Registro de actividad ante el SAT — cumplimiento y trazabilidad</p>
    </div>

    <?php if ($error_msj !== ""): ?>
        <div class="alert alert-danger shadow-sm border-0 border-start border-danger border-5 mb-4">
            <h5 class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-2"></i>Error SQL</h5>
            <p class="mb-0 font-monospace small"><?= htmlspecialchars($error_msj) ?></p>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="ba-kpi-card orange">
                <div class="ba-kpi-label"><i class="fa-solid fa-layer-group me-1"></i>Avisos por Acumulación</div>
                <div class="ba-kpi-value"><?= $countAcumulacion ?></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="ba-kpi-card red">
                <div class="ba-kpi-label"><i class="fa-solid fa-triangle-exclamation me-1"></i>Operaciones Sospechosas</div>
                <div class="ba-kpi-value"><?= $countSospechosa ?></div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="ba-kpi-card blue">
                <div class="ba-kpi-label"><i class="fa-solid fa-file-code me-1"></i>XMLs Generados (SAT)</div>
                <div class="ba-kpi-value"><?= $countXMLs ?></div>
            </div>
        </div>
    </div>

    <div class="card ba-filter-card">
        <div class="card-body">
            <h6 class="mb-3 fw-bold text-dark"><i class="fa-solid fa-filter me-2"></i>Filtros</h6>
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fecha_inicio) ?>">
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fecha_fin) ?>">
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label">Tipo de Registro</label>
                    <select name="aviso" class="form-select">
                        <option value="">Todos</option>
                        <option value="acumulacion" <?= $aviso_filter == 'acumulacion' ? 'selected' : '' ?>>Acumulación</option>
                        <option value="sospechosa" <?= $aviso_filter == 'sospechosa' ? 'selected' : '' ?>>Sospechosa</option>
                        <option value="sospechosa_24h" <?= $aviso_filter == 'sospechosa_24h' ? 'selected' : '' ?>>Sospechosa 24h</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-8 col-lg-4">
                    <label class="form-label">Buscar (Cliente o Archivo)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Término de búsqueda..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100 fw-600"><i class="fa-solid fa-filter me-1"></i>Aplicar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card ba-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><button class="ba-sort-btn" data-sort="fecha_raw">Fecha <i class="fa-solid fa-sort ms-1 opacity-50"></i></button></th>
                        <th><button class="ba-sort-btn" data-sort="cliente">Cliente <i class="fa-solid fa-sort ms-1 opacity-50"></i></button></th>
                        <th><button class="ba-sort-btn" data-sort="tipo_aviso">Tipo <i class="fa-solid fa-sort ms-1 opacity-50"></i></button></th>
                        <th><button class="ba-sort-btn" data-sort="monto">Monto <i class="fa-solid fa-sort ms-1 opacity-50"></i></button></th>
                        <th><button class="ba-sort-btn" data-sort="nombre_xml">Archivo XML <i class="fa-solid fa-sort ms-1 opacity-50"></i></button></th>
                        <th><div class="ba-sort-btn center" style="cursor:default;">Descargar</div></th>
                    </tr>
                </thead>
                <tbody id="avisosTableBody">
                    <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fa-solid fa-spinner fa-spin fa-2x mb-2"></i><br>Cargando bitácora...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let tableData = <?= json_encode($reporte, JSON_INVALID_UTF8_SUBSTITUTE) ?>; 
    const tbody = document.getElementById('avisosTableBody');
    let currentSortKey = '';
    let sortAscending = true;

    window.descargarXML = function(index) {
        const row = tableData[index];
        if (!row.xml_contenido) {
            alert('El XML no ha sido generado o no está en la base de datos.');
            return;
        }
        const blob = new Blob([row.xml_contenido], { type: 'text/xml' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = row.nombre_xml || 'aviso_sat.xml';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    };

    function esc(s) {
        if (s == null || s === undefined) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function renderTable() {
        if (!tableData || tableData.length === 0) {
            tbody.innerHTML = `<tr class="ba-empty-row"><td colspan="6" class="text-center py-5 text-muted"><i class="fa-solid fa-folder-open fa-3x mb-3 text-light"></i><br>No hay actividad registrada ante el SAT en este periodo.</td></tr>`;
            return;
        }
        
        let html = '';
        tableData.forEach((r, index) => {
            html += `
            <tr>
                <td data-label="Fecha" class="ps-3"><div><span class="d-block fw-bold text-dark">${esc(r.fecha_formateada)}</span><small class="text-muted"><i class="fa-regular fa-clock me-1"></i>Op #${r.id}</small></div></td>
                <td data-label="Cliente"><div class="fw-bold text-dark text-uppercase">${esc(r.cliente)}</div></td>
                <td data-label="Tipo"><span class="badge ${r.clase_badge} px-2 py-1">${esc(r.tipo_aviso)}</span></td>
                <td data-label="Monto"><span class="text-monto-ba text-success">${r.monto_formateado}</span></td>
                <td data-label="Archivo XML">
                    ${r.nombre_xml ? `<span class="ba-xml-badge"><i class="fa-solid fa-file-code text-primary me-1"></i>${esc(r.nombre_xml)}</span>` : '<span class="text-muted fst-italic small">No requiere XML</span>'}
                </td>
                <td data-label="Descargar" class="text-center">
                    ${r.nombre_xml && r.xml_contenido ? `
                        <button onclick="descargarXML(${index})" class="btn btn-sm ba-download-btn">
                            <i class="fa-solid fa-download me-1"></i>XML
                        </button>
                    ` : `<span class="text-muted small"><i class="fa-solid fa-minus"></i></span>`}
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    setTimeout(renderTable, 200);

    const sortButtons = document.querySelectorAll('.ba-sort-btn[data-sort]');
    sortButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const sortKey = this.getAttribute('data-sort');
            if (currentSortKey === sortKey) { sortAscending = !sortAscending; } 
            else { currentSortKey = sortKey; sortAscending = true; }

            sortButtons.forEach(b => {
                const icon = b.querySelector('i');
                if(icon) icon.className = 'fa-solid fa-sort ms-1 opacity-50';
            });
            const activeIcon = this.querySelector('i');
            if (activeIcon) {
                activeIcon.className = sortAscending ? 'fa-solid fa-sort-up ms-1 text-white' : 'fa-solid fa-sort-down ms-1 text-white';
            }

            tableData.sort((a, b) => {
                let valA = a[sortKey];
                let valB = b[sortKey];
                if (typeof valA === 'string') valA = valA.toLowerCase();
                if (typeof valB === 'string') valB = valB.toLowerCase();
                if (valA < valB) return sortAscending ? -1 : 1;
                if (valA > valB) return sortAscending ? 1 : -1;
                return 0;
            });
            renderTable();
        });
    });
});
</script>
<?php include 'templates/footer.php'; ?>
