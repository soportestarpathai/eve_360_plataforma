<?php 
include 'header.php'; 

// --- DATA LOGIC ---
$currentCompanyType = 1;
$watermarkText = '';
$tickerItems = []; 
$userActive = 0; $userLimit = 10;
$apiUsed = 0; $apiLimit = 500;
$logs = [];

try {
    // 1. Config & Watermark
    $stmtConfig = $pdo->query("SELECT * FROM config_empresa WHERE id_config = 1");
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$config) $config = ['id_tipo_empresa' => 1, 'max_usuarios' => 10, 'max_busquedas_api' => 500, 'nombre_empresa' => 'Empresa', 'logo_url' => '', 'color_primario' => '#0d6efd'];
    if (!empty($_SESSION['user_id'])) {
        $stmtU = $pdo->prepare("SELECT id_tipo_empresa, id_vulnerable, max_usuarios, max_busquedas_api FROM config_empresa_usuario WHERE id_usuario = ?");
        $stmtU->execute([$_SESSION['user_id']]);
        $cu = $stmtU->fetch(PDO::FETCH_ASSOC);
        if ($cu) {
            foreach (['id_tipo_empresa','id_vulnerable','max_usuarios','max_busquedas_api'] as $k) {
                if (isset($cu[$k])) $config[$k] = $cu[$k];
            }
        }
    }
    $currentCompanyType = $config['id_tipo_empresa']; 
    $id_vulnerable = $config['id_vulnerable'] ?? 0;

    if ($id_vulnerable > 0) {
        $stmtVuln = $pdo->prepare("SELECT fraccion FROM cat_vulnerables WHERE id_vulnerable = ?");
        $stmtVuln->execute([$id_vulnerable]);
        $res = $stmtVuln->fetch(PDO::FETCH_ASSOC);
        if ($res) $watermarkText = $res['fraccion'];
    }

    // 2. Financial Ticker
    // UMA Local
    $stmtUMA = $pdo->prepare("SELECT valor, fecha FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1");
    $stmtUMA->execute();
    $umaLocal = $stmtUMA->fetch(PDO::FETCH_ASSOC);
    if ($umaLocal) {
        $year = date('Y', strtotime($umaLocal['fecha']));
        $valor = number_format($umaLocal['valor'], 2);
        $tickerItems[] = "<i class='fa-solid fa-scale-balanced me-2 text-warning'></i>UMA ({$year}): <strong>$ {$valor} MXN</strong>";
    }
    
    // Banxico API
    $banxicoToken = '6210a4bfb2eaae222f81f1fada3b951732d371b30d72984fcd67c5d6d4b4fd0f';
    if (!empty($banxicoToken)) {
        $seriesIds = 'SP68257,SF43718,SF46410,SP74660';
        $apiUrl = "https://www.banxico.org.mx/SieAPIRest/service/v1/series/{$seriesIds}/datos/oportuno";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Bmx-Token: $banxicoToken", "Accept: application/json"]);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['bmx']['series'])) {
                foreach ($data['bmx']['series'] as $serie) {
                    if (empty($serie['datos'])) continue;
                    $val = number_format((float)$serie['datos'][0]['dato'], 2);
                    $date = $serie['datos'][0]['fecha'];
                    switch ($serie['idSerie']) {
                        case 'SP68257': $tickerItems[] = "<i class='fa-solid fa-coins me-2 text-info'></i>UDIS: <strong>$ {$val}</strong>"; break;
                        case 'SF43718': $tickerItems[] = "<i class='fa-solid fa-dollar-sign me-2 text-success'></i>Dólar: <strong>$ {$val} MXN</strong>"; break;
                        case 'SF46410': $tickerItems[] = "<i class='fa-solid fa-euro-sign me-2 text-primary'></i>Euro: <strong>$ {$val} MXN</strong>"; break;
                        case 'SP74660': $tickerItems[] = "<i class='fa-solid fa-chart-line me-2 text-danger'></i>Inflación: <strong>{$val}%</strong>"; break;
                    }
                }
            }
        }
    }

    // 3. Stats & Logs
    $userActive = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE id_status_usuario = 1")->fetchColumn();
    $userLimit = $config['max_usuarios'];
    
    try {
        $currentMonth = date('Y-m');
        // FIX: Added backticks to table and column name
        $usageStmt = $pdo->prepare("SELECT search_count FROM `search_usage` WHERE `year_month` = ?");
        $usageStmt->execute([$currentMonth]);
        $apiUsed = $usageStmt->fetchColumn() ?: 0;
    } catch (Exception $e) { $apiUsed = 0; }
    $apiLimit = $config['max_busquedas_api'];

    // LOGS FETCH
    try {
        $logStmt = $pdo->query("SELECT b.*, u.nombre as usuario_nombre FROM bitacora b LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario ORDER BY b.fecha DESC LIMIT 8");
        $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $logs = []; }

    // 4. Menu Preview Data
    $stmtMenu = $pdo->prepare("SELECT * FROM menu_access WHERE id_tipo_empresa = ? ORDER BY id_menu_access ASC");
    $stmtMenu->execute([$currentCompanyType]);
    $rawMenu = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($_SESSION['user_id'])) {
        try {
            $stmtMU = $pdo->prepare("SELECT id_menu_access, activo FROM menu_access_usuario WHERE id_usuario = ?");
            $stmtMU->execute([$_SESSION['user_id']]);
            $mu = [];
            while ($r = $stmtMU->fetch(PDO::FETCH_ASSOC)) $mu[(int)$r['id_menu_access']] = (int)$r['activo'];
            if (!empty($mu)) {
                $rawMenu = array_values(array_filter($rawMenu, function($m) use ($mu) {
                    $id = (int)$m['id_menu_access'];
                    return !isset($mu[$id]) || $mu[$id] === 1;
                }));
            }
        } catch (Exception $e) { }
    }

    $menuTree = [];
    $ref = [];
    foreach ($rawMenu as $row) {
        $id = $row['id_menu_access'];
        $icon = !empty($row['icon']) ? $row['icon'] : 'fa-solid fa-circle';
        $ref[$id] = [ 'label' => $row['seccion'], 'icon' => $icon, 'submenu' => [] ];
    }
    foreach ($rawMenu as $row) {
        if ($row['id_parent'] == 0) { $menuTree[] = &$ref[$row['id_menu_access']]; }
        elseif (isset($ref[$row['id_parent']])) { $ref[$row['id_parent']]['submenu'][] = &$ref[$row['id_menu_access']]; }
    }
    foreach ($ref as &$node) { if (empty($node['submenu'])) unset($node['submenu']); }
    unset($node);

} catch (Exception $e) { }

// Calculations
$userAvailable = max(0, $userLimit - $userActive);
$apiAvailable = max(0, $apiLimit - $apiUsed);
?>

<title>Admin Dashboard - EVE 360</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root { --dash-primary: <?= $config['color_primario'] ?? '#1B8FEA' ?>; }

    body { background: linear-gradient(135deg, #f0f4f8 0%, #e8f0f5 50%, #f5f9fc 100%); overflow-y: auto !important; }
    .dashboard-container { position: relative; width: 100%; min-height: 100vh; z-index: 10; padding-bottom: 3rem; }

    /* Welcome / Header */
    .dash-welcome {
        background: linear-gradient(135deg, var(--eve-blue-deep, #0B3C8A) 0%, var(--eve-blue-dark, #0B486B) 100%);
        border-radius: 20px;
        padding: 1.75rem 2rem;
        margin-bottom: 1.5rem;
        color: #fff;
        box-shadow: 0 12px 40px rgba(11, 60, 138, 0.25);
        position: relative;
        overflow: hidden;
    }
    .dash-welcome::before {
        content: ''; position: absolute; top: -50%; right: -20%;
        width: 60%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,.08) 0%, transparent 60%);
    }
    .dash-welcome h1 { font-size: 1.75rem; font-weight: 800; margin: 0; letter-spacing: -0.02em; position: relative; z-index: 1; }
    .dash-welcome p { margin: 0.35rem 0 0; opacity: 0.9; font-size: 0.95rem; position: relative; z-index: 1; }

    /* Ticker */
    .news-ticker {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #fff; height: 44px;
        overflow: hidden; position: relative; display: flex; align-items: center;
        margin-bottom: 1.5rem; border-radius: 14px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        border: 1px solid rgba(255,255,255,0.06);
    }
    .ticker-track {
        display: flex; white-space: nowrap; position: absolute;
        will-change: transform; animation: marquee <?= max(30, count($tickerItems) * 12) ?>s linear infinite;
    }
    .ticker-item { display: inline-flex; align-items: center; padding: 0 4rem; font-size: 0.9rem; }
    .ticker-item strong { color: #2ED1FF; margin-left: 6px; }
    @keyframes marquee { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
    .news-ticker:hover .ticker-track { animation-play-state: paused; }

    /* Watermark */
    .watermark {
        position: fixed; top: 50%; left: 300px; transform: translate(0, -50%);
        font-size: 28vw; font-weight: 800; color: rgba(11, 60, 138, 0.04);
        z-index: 0; pointer-events: none; font-family: system-ui, sans-serif;
        user-select: none; line-height: 1; letter-spacing: -0.02em;
    }

    /* Stat Cards */
    .stat-card-mod {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.04);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        overflow: hidden;
        position: relative;
    }
    .stat-card-mod:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(11, 60, 138, 0.12);
    }
    .stat-card-mod .stat-header {
        font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px;
        color: #64748b; margin-bottom: 0.75rem;
    }
    .stat-card-mod .stat-value { font-size: 2rem; font-weight: 800; color: #0f172a; }
    .stat-card-mod .stat-badge {
        display: inline-block; margin-top: 0.5rem; padding: 0.25rem 0.75rem;
        border-radius: 8px; font-size: 0.75rem; font-weight: 600;
        background: #f1f5f9; color: #64748b;
    }
    .stat-card-mod.users .stat-header { color: #1B8FEA; }
    .stat-card-mod.api .stat-header { color: #f59e0b; }

    /* Activity Card */
    .activity-card {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.04);
    }
    .activity-card .card-header {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #fff; border: none; padding: 1rem 1.25rem;
        font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .activity-card .table { font-size: 0.8rem; }
    .activity-card .table thead { background: #f8fafc; color: #475569; position: sticky; top: 0; z-index: 5; }
    .activity-card .table thead th { border: none; padding: 0.75rem 1rem; font-weight: 600; font-size: 0.7rem; background: #f8fafc; }
    .activity-card .table tbody tr:hover { background: #f8fafc; }
    .activity-card .table tbody td { padding: 0.75rem 1rem; vertical-align: middle; }
    .activity-card .table-responsive { max-height: 280px; overflow-y: auto; }

    /* Menu Preview */
    .menu-preview-container {
        position: relative; width: 100%; height: 360px; overflow: hidden;
        background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
        border-radius: 16px;
        border: 1px solid #e2e8f0;
    }
    .donut-center {
        position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
        width: 120px; height: 120px; border-radius: 50%;
        display: flex; justify-content: center; align-items: center; flex-direction: column;
        z-index: 10; background: #fff;
        text-align: center; padding: 12px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        border: 2px solid #e2e8f0;
    }
    .donut-center h6 { font-size: 0.65rem; letter-spacing: 1.5px; text-transform: uppercase; color: var(--dash-primary); margin: 0; font-weight: 700; }
    .donut-center small { font-size: 0.75rem; color: #64748b; margin-top: 2px; }

    .menu-item {
        position: absolute; top: 50%; left: 50%;
        width: 72px; height: 72px; border-radius: 50%; background: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        display: flex; flex-direction: column; justify-content: center; align-items: center;
        text-decoration: none; color: #475569; font-size: 0.6rem; z-index: 20;
        margin-left: -36px; margin-top: -36px;
        transition: transform 0.25s ease, box-shadow 0.25s ease; opacity: 0;
        border: 1px solid #e2e8f0; font-weight: 600;
    }
    .menu-item:hover { transform: scale(1.12) !important; color: var(--dash-primary); z-index: 30; box-shadow: 0 8px 24px rgba(27, 143, 234, 0.25); }
    .menu-item.visible { opacity: 1; }
    .menu-item i { font-size: 1.25rem; margin-bottom: 4px; }

    /* Quick Links */
    .quick-links-card {
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.04);
        overflow: hidden;
    }
    .quick-link-btn {
        display: flex; align-items: center; padding: 0.75rem 1rem;
        border-radius: 10px; text-decoration: none; color: #334155;
        transition: background 0.2s, transform 0.2s; font-weight: 500; font-size: 0.9rem;
    }
    .quick-link-btn:hover { background: #f1f5f9; color: var(--dash-primary); transform: translateX(4px); }
    .quick-link-btn i { width: 24px; margin-right: 0.75rem; color: var(--dash-primary); font-size: 1rem; }

    @keyframes dashFadeIn {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .stat-card-mod { animation: dashFadeIn 0.5s ease-out backwards; }
    .stat-card-mod.api { animation-delay: 0.08s; }
    .activity-card { animation: dashFadeIn 0.5s ease-out 0.12s backwards; }
    .dash-welcome { animation: dashFadeIn 0.5s ease-out; }
</style>

<?php if (!empty($watermarkText)): ?>
    <div class="watermark"><?= htmlspecialchars($watermarkText) ?></div>
<?php endif; ?>

<div class="dashboard-container">
    <?php if (!empty($tickerItems)): ?>
    <div class="news-ticker">
        <div class="ticker-track">
            <?php foreach ($tickerItems as $item): ?>
                <div class="ticker-item"><?= $item ?></div>
            <?php endforeach; ?>
            <?php foreach ($tickerItems as $item): ?>
                <div class="ticker-item"><?= $item ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="dash-welcome">
        <h1><i class="fa-solid fa-gauge-high me-2"></i>Panel de Control</h1>
        <p><?= htmlspecialchars($config['nombre_empresa'] ?? 'EVE 360') ?> — Resumen de estado y actividad reciente</p>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="row mb-4 g-3">
                <div class="col-md-6">
                    <div class="stat-card-mod users h-100">
                        <div class="stat-header"><i class="fa-solid fa-users me-1"></i>Licencias Usuarios</div>
                        <div class="d-flex align-items-center">
                            <div style="width: 110px; height: 110px; flex-shrink: 0;">
                                <canvas id="userChart"></canvas>
                            </div>
                            <div class="ms-3">
                                <div class="stat-value"><?= $userActive ?></div>
                                <small class="text-muted">activos</small>
                                <div class="stat-badge">Límite: <?= $userLimit ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="stat-card-mod api h-100">
                        <div class="stat-header"><i class="fa-solid fa-code me-1"></i>Consultas API (<?= date('M') ?>)</div>
                        <div class="d-flex align-items-center">
                            <div style="width: 110px; height: 110px; flex-shrink: 0;">
                                <canvas id="apiChart"></canvas>
                            </div>
                            <div class="ms-3">
                                <div class="stat-value"><?= $apiUsed ?></div>
                                <small class="text-muted">realizadas</small>
                                <div class="stat-badge">Límite: <?= $apiLimit ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="activity-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-clock-rotate-left me-2"></i>Última Actividad</span>
                    <button class="btn btn-sm btn-outline-light py-1 px-2" onclick="location.reload()" title="Actualizar"><i class="fa-solid fa-arrows-rotate"></i></button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Tabla</th>
                                <th style="width: 20%;">Anterior</th>
                                <th style="width: 20%;">Nuevo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($logs)): ?>
                                <tr><td colspan="7" class="text-center py-3 text-muted">Sin registros recientes.</td></tr>
                            <?php else: ?>
                                <?php foreach($logs as $log): ?>
                                    <tr>
                                        <td class="text-muted"><?= date('d/m/Y H:i', strtotime($log['fecha'])) ?></td>
                                        
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($log['usuario_nombre'] ?? 'Sistema') ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($log['accion']) ?></span></td>
                                        
                                        <td>
                                            <?= htmlspecialchars($log['tabla_afectada'] ?? $log['tabla'] ?? '-') ?> 
                                            <span class="text-muted small d-block">ID: <?= $log['id_afectado'] ?? $log['id_registro'] ?? '?' ?></span>
                                        </td>
                                        
                                        <td class="text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($log['valor_anterior'] ?? '') ?>">
                                            <?php 
                                                $valorAnterior = $log['valor_anterior'] ?? '';
                                                $valorAnteriorStr = is_string($valorAnterior) ? $valorAnterior : (is_array($valorAnterior) ? json_encode($valorAnterior) : '');
                                                echo htmlspecialchars(substr($valorAnteriorStr, 0, 40)) . (strlen($valorAnteriorStr) > 40 ? '...' : '');
                                            ?>
                                        </td>
                                        
                                        <td class="text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($log['valor_nuevo'] ?? '') ?>">
                                            <?php 
                                                $valorNuevo = $log['valor_nuevo'] ?? '';
                                                $valorNuevoStr = is_string($valorNuevo) ? $valorNuevo : (is_array($valorNuevo) ? json_encode($valorNuevo) : '');
                                                echo htmlspecialchars(substr($valorNuevoStr, 0, 40)) . (strlen($valorNuevoStr) > 40 ? '...' : '');
                                            ?>
                                        </td>
                                        
                                        <td class="text-end">
                                            <?php if($log['accion'] !== 'ELIMINAR'): ?>
                                                <button class="btn btn-link p-0 text-muted" onclick="undoAction(<?= $log['id_bitacora'] ?>)" title="Deshacer"><i class="fa-solid fa-rotate-left"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4" style="border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.06);">
                <div class="card-header bg-white fw-bold text-secondary small py-3" style="border-bottom:1px solid #e2e8f0;">
                    <i class="fa-solid fa-compass me-2"></i>Vista Previa Menú
                </div>
                <div class="card-body p-0">
                    <div class="menu-preview-container" id="menuPreview">
                        <div class="donut-center">
                            <h6><?= htmlspecialchars($config['nombre_empresa']) ?></h6>
                            <small id="menuLabel">Menú</small>
                            <div id="backBtn" style="display:none; font-size:1rem; cursor:pointer; color:#64748b; margin-top:4px;" onclick="goBack()">
                                <i class="fa-solid fa-arrow-left"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-center small text-muted py-2" style="border-top:1px solid #e2e8f0;">
                    Configuración activa (Tipo: <?= $currentCompanyType ?>)
                </div>
            </div>

            <div class="quick-links-card card">
                <div class="card-header py-3" style="background:linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color:#fff; border:none;">
                    <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-bolt me-2"></i>Accesos Directos</h6>
                </div>
                <div class="card-body p-3">
                    <a href="users.php" class="quick-link-btn d-block mb-2">
                        <i class="fa-solid fa-user-plus"></i>Crear Nuevo Usuario
                    </a>
                    <a href="config.php" class="quick-link-btn d-block mb-2">
                        <i class="fa-solid fa-sliders"></i>Config. Usuarios
                    </a>
                    <a href="../index.php" target="_blank" class="quick-link-btn d-block">
                        <i class="fa-solid fa-external-link-alt"></i>Ir al Sitio Principal
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // --- UNDO ---
    function undoAction(id) { alert('Funcionalidad de reversión pendiente de implementación en backend.'); }

    // --- CHARTS ---
    const primaryColor = '<?= addslashes($config["color_primario"] ?? "#1B8FEA") ?>';
    const commonOpts = { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false } } };

    new Chart(document.getElementById('userChart'), {
        type: 'doughnut',
        data: { labels: ['Usado', 'Libre'], datasets: [{ data: [<?= $userActive ?>, <?= $userAvailable ?>], backgroundColor: [primaryColor, '#e2e8f0'], borderWidth: 0 }] },
        options: commonOpts
    });

    new Chart(document.getElementById('apiChart'), {
        type: 'doughnut',
        data: { labels: ['Usado', 'Libre'], datasets: [{ data: [<?= $apiUsed ?>, <?= $apiAvailable ?>], backgroundColor: ['#f59e0b', '#e2e8f0'], borderWidth: 0 }] },
        options: commonOpts
    });

    // --- MENU PREVIEW LOGIC ---
    const menuData = <?= json_encode($menuTree) ?>;
    const container = document.getElementById('menuPreview');
    const menuLabel = document.getElementById('menuLabel');
    const backBtn = document.getElementById('backBtn');
    let menuStack = [];
    const radius = 100;

    function renderMenu(items) {
        container.querySelectorAll('.menu-item').forEach(el => el.remove());
        if(!items || items.length === 0) return;
        
        const total = items.length;
        const startAngle = -90;

        items.forEach((data, index) => {
            const el = document.createElement('a');
            el.className = 'menu-item';
            el.href = 'javascript:void(0)'; 
            el.innerHTML = `<i class="fa-solid ${data.icon}"></i><span>${data.label}</span>`;
            
            el.addEventListener('click', () => {
                if(data.submenu && data.submenu.length > 0) {
                    menuStack.push({ items: items, title: menuLabel.textContent });
                    menuLabel.textContent = data.label;
                    backBtn.style.display = 'block';
                    renderMenu(data.submenu);
                }
            });

            const angleDeg = startAngle + (360 / total) * index;
            const angleRad = angleDeg * (Math.PI / 180);
            const x = Math.cos(angleRad) * radius;
            const y = Math.sin(angleRad) * radius;
            
            container.appendChild(el);
            
            setTimeout(() => {
                el.classList.add('visible');
                el.style.transform = `translate(${x}px, ${y}px) scale(1)`;
            }, 50 * index);
        });
    }

    function goBack() {
        if(menuStack.length === 0) return;
        const prev = menuStack.pop();
        menuLabel.textContent = prev.title;
        renderMenu(prev.items);
        if(menuStack.length === 0) backBtn.style.display = 'none';
    }

    renderMenu(menuData);
</script>

<?php include '../templates/footer.php'; ?>