<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'templates/header.php';

// 1. OBTENER FILTROS DE BÚSQUEDA
$search = $_GET['search'] ?? '';
$tipo_persona = $_GET['tipo'] ?? '';
$riesgo_filter = $_GET['riesgo'] ?? '';

// 2. CONSTRUIR CONSULTA SQL DINÁMICA
$where_clauses = ["1=1"];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(c.alias LIKE ? OR c.no_contrato LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($tipo_persona !== '') {
    $where_clauses[] = "c.id_tipo_persona = ?";
    $params[] = $tipo_persona;
}

$where_clauses[] = "(c.id_status IS NULL OR c.id_status != 4)"; // Excluir eliminados
$where_sql = implode(' AND ', $where_clauses);

// Permiso para dar de baja clientes
$canDeleteClients = false;
try {
    $stmtPerm = $pdo->prepare("SELECT COALESCE(catalogo_clientes, 0) AS catalogo_clientes, COALESCE(administracion, 0) AS administracion FROM usuarios_permisos WHERE id_usuario = ?");
    $stmtPerm->execute([$_SESSION['user_id'] ?? 0]);
    $perm = $stmtPerm->fetch(PDO::FETCH_ASSOC);
    $canDeleteClients = $perm && (((int)$perm['catalogo_clientes'] > 0) || ((int)$perm['administracion'] > 0));
} catch (Exception $e) { /* ignorar */ }

// 3. EJECUTAR CONSULTA (Traemos todo, JS se encargará de ordenar)
$query = "SELECT c.*, tp.nombre AS tipo_persona_nombre,
          (SELECT COUNT(*) FROM clientes_documentos WHERE id_cliente = c.id_cliente AND id_status = 1) as total_docs
          FROM clientes c
          LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
          WHERE $where_sql
          ORDER BY c.nivel_riesgo DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$resultados_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. RANGOS DE RIESGO (usa config_riesgo_rangos, igual que el motor EBR)
$riesgoRangos = [];
try {
    $stmtR = $pdo->query("SELECT * FROM config_riesgo_rangos ORDER BY min_valor ASC");
    $riesgoRangos = $stmtR ? $stmtR->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) { /* fallback abajo */ }

function obtenerSemaforo($nivel, $docs, $rangos) {
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
                    'texto' => strtoupper($r['nivel'] ?? ''),
                    'icono' => $iconoMap[$val] ?? 'fa-circle',
                    'val' => $val
                ];
            }
    }
    return ['clase' => 'bg-secondary', 'texto' => 'N/A', 'icono' => 'fa-circle', 'val' => 'bajo'];
}

// 5. FILTRAR Y PREPARAR DATOS PARA JAVASCRIPT
$reporte = [];
$countAlto = 0; $countMedio = 0; $countBajo = 0;

foreach ($resultados_db as $r) {
    $s = obtenerSemaforo($r['nivel_riesgo'], $r['total_docs'], $riesgoRangos);
    
    // Contadores para tarjetas
    if ($s['val'] == 'alto') $countAlto++;
    elseif ($s['val'] == 'medio') $countMedio++;
    else $countBajo++;

    // Guardamos solo si pasa el filtro de riesgo
    if ($riesgo_filter === '' || $riesgo_filter === $s['val']) {
        // Formateamos los datos limpios para que Javascript los entienda
        $reporte[] = [
            'id_cliente' => $r['id_cliente'],
            'alias' => $r['alias'],
            'no_contrato' => $r['no_contrato'],
            'tipo_persona' => $r['tipo_persona_nombre'] ?? (($r['id_tipo_persona'] == 1) ? 'Física' : (($r['id_tipo_persona'] == 2) ? 'Moral' : 'Fideicomiso')),
            'fecha_formateada' => date("d/m/Y", strtotime($r['fecha_apertura'])),
            'fecha_raw' => strtotime($r['fecha_apertura']), // Útil para ordenar fechas exactas en JS
            'total_docs' => $r['total_docs'],
            'nivel_riesgo' => (float)$r['nivel_riesgo'],
            'semaforo_clase' => $s['clase'],
            'semaforo_texto' => $s['texto'],
            'semaforo_icono' => $s['icono']
        ];
    }
}
?>
<title>Reporte de Riesgos - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/clientes.css">
<style>
:root {
    --rr-primary: #4361ee;
    --rr-primary-dark: #3a0ca3;
    --rr-danger: #ef476f;
    --rr-warning: #f77f00;
    --rr-success: #06d6a0;
    --rr-dark: #1d3557;
    --rr-light: #f8f9fc;
    --rr-border: #e2e8f0;
    --rr-shadow: 0 4px 24px rgba(0,0,0,.06);
    --rr-radius: 16px;
    --rr-radius-sm: 10px;
    --rr-transition: .25s cubic-bezier(.4,0,.2,1);
}

.rr-wrapper { max-width: 1200px; margin: 0 auto; }

/* Page header */
.rr-page-header {
    background: linear-gradient(135deg, var(--rr-primary) 0%, var(--rr-primary-dark) 100%);
    color: #fff; border-radius: var(--rr-radius); padding: 1.75rem 2rem; margin-bottom: 1.75rem;
    position: relative; overflow: hidden;
}
.rr-page-header::before {
    content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px;
    background: rgba(255,255,255,.06); border-radius: 50%;
}
.rr-page-header h2 { font-size: 1.5rem; font-weight: 800; margin-bottom: .25rem; }
.rr-page-header p { opacity: .9; margin: 0; font-size: .9rem; }

/* KPI cards */
.rr-kpi-card {
    border: none; border-radius: var(--rr-radius); padding: 1.25rem 1.5rem;
    transition: var(--rr-transition); cursor: default; overflow: hidden; position: relative;
    box-shadow: var(--rr-shadow);
}
.rr-kpi-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
}
.rr-kpi-card:hover {
    transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,.12);
}
.rr-kpi-card.rr-alto { background: linear-gradient(135deg, #ef476f 0%, #d62828 100%); color: #fff; }
.rr-kpi-card.rr-alto::before { background: rgba(255,255,255,.4); }
.rr-kpi-card.rr-medio { background: linear-gradient(135deg, #f77f00 0%, #e85d04 100%); color: #fff; }
.rr-kpi-card.rr-medio::before { background: rgba(255,255,255,.4); }
.rr-kpi-card.rr-bajo { background: linear-gradient(135deg, #06d6a0 0%, #028a6e 100%); color: #fff; }
.rr-kpi-card.rr-bajo::before { background: rgba(255,255,255,.4); }
.rr-kpi-card .rr-kpi-label { font-size: .85rem; opacity: .9; font-weight: 500; }
.rr-kpi-card .rr-kpi-value { font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; }

/* Filter card */
.rr-filter-card {
    background: #fff; border: none; border-radius: var(--rr-radius);
    box-shadow: var(--rr-shadow); margin-bottom: 1.5rem;
}
.rr-filter-card .card-body {
    padding: 1.25rem 1.5rem; background: linear-gradient(180deg, #fafbff 0%, #fff 100%);
    border-radius: var(--rr-radius); border: 1px solid var(--rr-border);
}
.rr-filter-card .form-control, .rr-filter-card .form-select {
    border-radius: 8px; border: 1.5px solid var(--rr-border);
    padding: .55rem .85rem; transition: var(--rr-transition);
}
.rr-filter-card .form-control:focus, .rr-filter-card .form-select:focus {
    border-color: var(--rr-primary); box-shadow: 0 0 0 3px rgba(67,97,238,.12);
}
.rr-filter-card .btn-primary {
    background: linear-gradient(135deg, var(--rr-primary), var(--rr-primary-dark));
    border: none; font-weight: 600; border-radius: 8px;
    padding: .55rem 1.25rem; transition: var(--rr-transition);
}
.rr-filter-card .btn-primary:hover {
    transform: translateY(-1px); box-shadow: 0 4px 14px rgba(67,97,238,.35);
}

/* Table card */
.rr-table-card {
    border: none; border-radius: var(--rr-radius); overflow: hidden;
    box-shadow: var(--rr-shadow);
}
.rr-table-card .table { margin-bottom: 0; }
.rr-table-card .table thead th {
    background: linear-gradient(135deg, #1d3557 0%, #2d4a6f 100%) !important;
    color: #fff !important; font-weight: 600; font-size: .78rem;
    text-transform: uppercase; letter-spacing: .03em; padding: 1rem .75rem;
    border: none;
}
.rr-table-card .table tbody tr {
    transition: var(--rr-transition);
}
.rr-table-card .table tbody tr:hover {
    background: linear-gradient(90deg, rgba(67,97,238,.04) 0%, transparent 100%);
}
.rr-table-card .table tbody td {
    padding: 1rem .75rem; vertical-align: middle; border-color: var(--rr-border);
    white-space: nowrap;
}
.rr-table-card .table tbody td:first-child { white-space: normal; min-width: 140px; }
.badge-riesgo {
    width: 110px; padding: 8px 0; font-size: .8rem; border-radius: 50px;
    display: inline-block; text-align: center; font-weight: 600;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.sort-btn {
    background: transparent; border: none; color: #fff !important;
    font-weight: 600; padding: 14px 10px; width: 100%; text-align: center;
    text-transform: uppercase; font-size: .78rem; display: flex;
    justify-content: center; align-items: center; transition: var(--rr-transition);
}
.sort-btn:hover { background: rgba(255,255,255,.12); }
.sort-btn.text-start { justify-content: flex-start; }
.rr-audit-btn {
    background: linear-gradient(135deg, var(--rr-primary), var(--rr-primary-dark));
    border: none; color: #fff !important; font-weight: 600; padding: .4rem 1rem;
    border-radius: 8px; font-size: .8rem; transition: var(--rr-transition);
}
.rr-audit-btn:hover {
    transform: translateY(-1px); box-shadow: 0 4px 12px rgba(67,97,238,.4);
    color: #fff !important;
}

.rr-delete-btn {
    background: transparent; border: 1px solid var(--rr-danger); color: var(--rr-danger) !important;
    font-weight: 600; padding: .4rem .75rem; border-radius: 8px; font-size: .8rem;
    transition: var(--rr-transition);
}
.rr-delete-btn:hover { background: var(--rr-danger); color: #fff !important; }

@media (max-width: 768px) {
    .rr-page-header { padding: 1.25rem; }
    .rr-page-header h2 { font-size: 1.2rem; }
    .rr-kpi-card .rr-kpi-value { font-size: 1.5rem; }
}
</style>
</head>
<body>
<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="container-fluid px-4 pb-5 pt-3">
<div class="rr-wrapper">
    <div class="rr-page-header">
        <h2><i class="fa-solid fa-chart-pie me-2"></i>Reporte de Riesgos</h2>
        <p>Consultas y reportes por nivel de riesgo de clientes</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="rr-kpi-card rr-alto">
                <div class="rr-kpi-label"><i class="fa-solid fa-triangle-exclamation me-1"></i> Riesgo Alto</div>
                <div class="rr-kpi-value"><?= $countAlto ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="rr-kpi-card rr-medio">
                <div class="rr-kpi-label"><i class="fa-solid fa-circle-exclamation me-1"></i> Riesgo Medio</div>
                <div class="rr-kpi-value"><?= $countMedio ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="rr-kpi-card rr-bajo">
                <div class="rr-kpi-label"><i class="fa-solid fa-check-circle me-1"></i> Sin Riesgo</div>
                <div class="rr-kpi-value"><?= $countBajo ?></div>
            </div>
        </div>
    </div>

    <div class="card rr-filter-card">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="origen" value="<?= htmlspecialchars($_GET['origen'] ?? '') ?>">
                <input type="hidden" name="grafica" value="<?= htmlspecialchars($_GET['grafica'] ?? '') ?>">
                <input type="hidden" name="top" value="<?= htmlspecialchars($_GET['top'] ?? '') ?>">
                <div class="col-md-4">
                    <label class="form-label text-muted small fw-bold mb-1">Buscar Cliente</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Nombre o Contrato..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1">Tipo de Persona</label>
                    <select name="tipo" class="form-select">
                        <option value="">Todos los tipos</option>
                        <option value="1" <?= $tipo_persona == '1' ? 'selected' : '' ?>>Física</option>
                        <option value="2" <?= $tipo_persona == '2' ? 'selected' : '' ?>>Moral</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1">Nivel de Riesgo</label>
                    <select name="riesgo" class="form-select">
                        <option value="">Todos los riesgos</option>
                        <option value="alto" <?= $riesgo_filter == 'alto' ? 'selected' : '' ?>>Riesgo Alto</option>
                        <option value="medio" <?= $riesgo_filter == 'medio' ? 'selected' : '' ?>>Riesgo Medio</option>
                        <option value="bajo" <?= $riesgo_filter == 'bajo' ? 'selected' : '' ?>>Sin Riesgo</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-2"></i>Filtrar</button>
                    <?php if($search || $tipo_persona || $riesgo_filter): ?>
                        <?php
                        $limpiarParams = array_filter([
                            'origen' => $_GET['origen'] ?? '',
                            'grafica' => $_GET['grafica'] ?? '',
                            'top' => $_GET['top'] ?? ''
                        ]);
                        $limpiarQ = $limpiarParams ? '?' . http_build_query($limpiarParams) : '';
                        ?>
                        <a href="reporte_riesgos.php<?= $limpiarQ ?>" class="btn btn-light border" title="Limpiar"><i class="fa-solid fa-eraser"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card rr-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="text-uppercase small">
                        <tr>
                            <th class="ps-4" style="width: 25%;">
                                <button class="sort-btn text-start ps-0" type="button" data-sort="no_contrato">Contrato / Cliente <i class="fa-solid fa-sort ms-1 opacity-50"></i></button>
                            </th>
                            <th style="width: 15%;">
                                <button class="sort-btn" type="button" data-sort="tipo_persona">Tipo Persona <i class="fa-solid fa-sort ms-1 opacity-50"></i></button>
                            </th>
                            <th style="width: 15%;">
                                <button class="sort-btn" type="button" data-sort="fecha_raw">Fecha Alta <i class="fa-solid fa-sort ms-1 opacity-50"></i></button>
                            </th>
                            <th style="width: 10%;">
                                <button class="sort-btn" type="button" data-sort="total_docs">Expediente <i class="fa-solid fa-sort ms-1 opacity-50"></i></button>
                            </th>
                            <th style="width: 10%;">
                                <button class="sort-btn" type="button" data-sort="nivel_riesgo">Puntaje <i class="fa-solid fa-sort ms-1 opacity-50"></i></button>
                            </th>
                            <th style="width: 15%;">
                                <button class="sort-btn" type="button" data-sort="nivel_riesgo">Riesgo <i class="fa-solid fa-sort ms-1 opacity-50"></i></button>
                            </th>
                            <th style="width: 10%;">
                                <div class="sort-btn" style="cursor: default;">Acciones</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="riskTableBody">
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="fa-solid fa-spinner fa-spin fa-2x mb-2"></i><br>Cargando reporte...</td></tr>
                    </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const canDeleteClients = <?= $canDeleteClients ? 'true' : 'false' ?>;
    let tableData = <?= json_encode($reporte) ?>;
    const tbody = document.getElementById('riskTableBody');
    let currentSortKey = '';
    let sortAscending = true;

    function esc(s) {
        if (s == null || s === undefined) return '';
        const t = String(s);
        return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function renderTable() {
        if (tableData.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted"><i class="fa-regular fa-folder-open fa-4x mb-3" style="opacity:.3"></i><br><span class="fw-semibold">No se encontraron clientes</span><br><small>Prueba ajustando los filtros de búsqueda</small></td></tr>`;
            return;
        }

        let html = '';
        tableData.forEach(c => {
            html += `
            <tr>
                <td class="ps-4">
                    <div class="fw-bold text-dark fs-6">${esc(c.alias)}</div>
                    <span class="text-primary fw-bold small">ID: ${esc(c.no_contrato)}</span>
                </td>
                <td class="text-center">
                    <span class="badge bg-secondary bg-opacity-75 px-3 py-2 rounded-pill">${esc(c.tipo_persona)}</span>
                </td>
                <td class="text-center text-muted small">
                    <i class="fa-regular fa-calendar me-1"></i> ${esc(c.fecha_formateada)}
                </td>
                <td class="text-center">
                    <span class="badge rounded-pill bg-light border text-dark px-3 py-2">
                        <i class="fa-solid fa-folder-open text-primary me-1"></i> ${c.total_docs} docs
                    </span>
                </td>
                <td class="text-center fw-bold text-dark fs-6">
                    ${c.nivel_riesgo.toFixed(2)}
                </td>
                <td class="text-center">
                    <span class="badge badge-riesgo ${c.semaforo_clase} shadow-sm">
                        <i class="fa-solid ${c.semaforo_icono} me-1"></i> ${c.semaforo_texto}
                    </span>
                </td>
                <td class="text-center">
                    <div class="d-flex gap-1 justify-content-center flex-wrap">
                        <a href="cliente_detalle.php?id=${c.id_cliente}" class="btn btn-sm rr-audit-btn">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Auditar
                        </a>
                        ${canDeleteClients ? `
                        <button type="button" class="btn btn-sm rr-delete-btn rr-btn-delete" data-id="${esc(c.id_cliente)}" data-alias="${esc(c.alias)}" title="Dar de baja">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>` : ''}
                    </div>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    // Inicializamos dibujando la tabla por primera vez
    setTimeout(() => {
        renderTable();
    }, 300);

    // Lógica para ordenar al dar clic en los botones del encabezado
    const sortButtons = document.querySelectorAll('.sort-btn[data-sort]');
    sortButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const sortKey = this.getAttribute('data-sort');

            if (currentSortKey === sortKey) {
                sortAscending = !sortAscending;
            } else {
                currentSortKey = sortKey;
                sortAscending = true;
            }

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

    // Delegación para borrar cliente
    tbody.addEventListener('click', async function(e) {
        const btn = e.target.closest('.rr-btn-delete');
        if (!btn) return;
        const id = parseInt(btn.dataset.id, 10);
        const aliasEsc = (btn.dataset.alias || 'este cliente').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const result = await Swal.fire({
            title: '¿Dar de baja?',
            html: 'Se dará de baja lógica al cliente <strong>' + aliasEsc + '</strong>. El expediente se conservará en histórico.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef476f',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sí, dar de baja',
            cancelButtonText: 'Cancelar'
        });
        if (!result.isConfirmed) return;
        try {
            const res = await fetch('api/delete_client.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_cliente: id })
            });
            const data = await res.json();
            if (data.status === 'success') {
                const removed = tableData.find(c => c.id_cliente === id);
                tableData = tableData.filter(c => c.id_cliente !== id);
                renderTable();
                if (removed) {
                    const key = removed.semaforo_texto === 'ALTO' ? 'alto' : (removed.semaforo_texto === 'MEDIO' ? 'medio' : 'bajo');
                    const el = document.querySelector('.rr-' + key + ' .rr-kpi-value');
                    if (el) { const n = parseInt(el.textContent, 10) - 1; el.textContent = Math.max(0, n); }
                }
                Swal.fire('Hecho', data.message || 'Cliente dado de baja', 'success');
            } else {
                Swal.fire('Error', data.message || 'No se pudo dar de baja', 'error');
            }
        } catch (err) {
            Swal.fire('Error', 'Error de conexión', 'error');
        }
    });
});
</script>
<?php include 'templates/footer.php'; ?>
