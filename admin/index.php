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
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC); 
    
    if (!$config) $config = ['id_tipo_empresa' => 1, 'max_usuarios' => 10, 'max_busquedas_api' => 500, 'nombre_empresa' => 'Empresa', 'logo_url' => '', 'color_primario' => '#0d6efd'];

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
        $logStmt = $pdo->query("SELECT b.*, u.nombre as usuario_nombre FROM bitacora b LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario ORDER BY b.fecha DESC LIMIT 20");
        $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $logs = []; }

    // 4. Menu Preview Data
    $stmtMenu = $pdo->prepare("SELECT * FROM menu_access WHERE id_tipo_empresa = ? ORDER BY id_menu_access ASC");
    $stmtMenu->execute([$currentCompanyType]);
    $rawMenu = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);

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

<title>Admin Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Admin Specific Styles */
    :root { --primary-color: <?= $config['color_primario'] ?? '#0d6efd' ?>; }

    /* Scroll Fix */
    body { background-color: #f8f9fa; overflow-y: auto !important; }
    .dashboard-container { position: relative; width: 100%; height: auto; min-height: 100vh; z-index: 10; padding-bottom: 50px; }

    /* Ticker */
    .news-ticker {
        background-color: #212529; color: #fff; height: 40px;
        overflow: hidden; position: relative; display: flex; align-items: center;
        margin-bottom: 20px; border-radius: 5px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .ticker-track {
        display: flex; white-space: nowrap; position: absolute;
        will-change: transform; animation: marquee <?= max(30, count($tickerItems) * 12) ?>s linear infinite; 
    }
    .ticker-item { display: inline-flex; align-items: center; padding: 0 4rem; font-size: 0.9rem; }
    .ticker-item strong { color: #ffc107; margin-left: 6px; }
    @keyframes marquee { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
    .news-ticker:hover .ticker-track { animation-play-state: paused; }

    /* Watermark */
    .watermark {
        position: fixed; top: 50%; left: 300px; transform: translate(0, -50%); 
        font-size: 30vw; font-weight: bold; color: rgba(0, 0, 0, 0.05);
        z-index: 0; pointer-events: none; font-family: 'Times New Roman', serif;
        user-select: none; line-height: 1;
    }

    /* Menu Preview */
    .menu-preview-container {
        position: relative; width: 100%; height: 380px; overflow: hidden;
        background: #fff; border-radius: 8px; border: 1px solid #dee2e6;
    }
    .donut-center { 
        position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); 
        width: 130px; height: 130px; border-radius: 50%; border: 2px dashed #ccc; 
        display: flex; justify-content: center; align-items: center; flex-direction: column;
        z-index: 10; background: rgba(255,255,255,0.95);
        text-align: center; padding: 10px;
    }
    .donut-center h6 { font-size: 0.7rem; letter-spacing: 1px; text-transform: uppercase; color: var(--primary-color); margin: 0; }
    
    .menu-item {
        position: absolute; top: 50%; left: 50%;
        width: 80px; height: 80px; border-radius: 50%; background: white;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        display: flex; flex-direction: column; justify-content: center; align-items: center;
        text-decoration: none; color: #555; font-size: 0.65rem; z-index: 20;
        margin-left: -40px; margin-top: -40px; 
        transition: transform 0.3s; opacity: 0;
        border: 1px solid #f0f0f0;
    }
    .menu-item:hover { transform: scale(1.1) !important; color: var(--primary-color); z-index: 30; }
    .menu-item.visible { opacity: 1; }
    .menu-item i { font-size: 1.4rem; margin-bottom: 4px; }
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

    <h2 class="mb-4 text-dark border-bottom pb-2">Panel de Control</h2>

    <div class="row">
        <div class="col-lg-8">
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-header bg-white fw-bold text-primary small">LICENCIAS USUARIOS</div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <div style="width: 150px; height: 150px;">
                                <canvas id="userChart"></canvas>
                            </div>
                            <div class="ms-3 text-center">
                                <h3 class="mb-0 fw-bold text-dark"><?= $userActive ?></h3>
                                <small class="text-muted d-block">Activos</small>
                                <span class="badge bg-light text-secondary border mt-1">Límite: <?= $userLimit ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="card shadow-sm h-100 border-0">
                        <div class="card-header bg-white fw-bold text-warning small">CONSULTAS API (<?= date('M') ?>)</div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <div style="width: 150px; height: 150px;">
                                <canvas id="apiChart"></canvas>
                            </div>
                            <div class="ms-3 text-center">
                                <h3 class="mb-0 fw-bold text-dark"><?= $apiUsed ?></h3>
                                <small class="text-muted d-block">Realizadas</small>
                                <span class="badge bg-light text-secondary border mt-1">Límite: <?= $apiLimit ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-2">
                    <span class="small text-uppercase fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i>Última Actividad</span>
                    <button class="btn btn-xs btn-outline-light py-0" onclick="location.reload()"><i class="fa-solid fa-sync"></i></button>
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
                                        
                                        <td class="text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($log['valor_anterior']) ?>">
                                            <?= htmlspecialchars(substr($log['valor_anterior'], 0, 40)) . (strlen($log['valor_anterior']) > 40 ? '...' : '') ?>
                                        </td>
                                        
                                        <td class="text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($log['valor_nuevo']) ?>">
                                            <?= htmlspecialchars(substr($log['valor_nuevo'], 0, 40)) . (strlen($log['valor_nuevo']) > 40 ? '...' : '') ?>
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
            <div class="card shadow-sm border-0 bg-white mb-3">
                <div class="card-header bg-white fw-bold text-secondary small">VISTA PREVIA MENÚ</div>
                <div class="card-body p-0">
                    <div class="menu-preview-container" id="menuPreview">
                        <div class="donut-center">
                            <h6><?= htmlspecialchars($config['nombre_empresa']) ?></h6>
                            <small class="text-muted" id="menuLabel">Menú</small>
                            <div id="backBtn" style="display:none; font-size:1.2rem; cursor:pointer; color:#666;" onclick="goBack()">
                                <i class="fa-solid fa-rotate-left"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light text-center small text-muted">
                    Configuración activa (Tipo: <?= $currentCompanyType ?>).
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="fw-bold text-secondary">Accesos Directos</h6>
                    <div class="d-grid gap-2">
                        <a href="users.php" class="btn btn-outline-primary btn-sm text-start"><i class="fa-solid fa-user-plus me-2"></i>Crear Nuevo Usuario</a>
                        <a href="config.php" class="btn btn-outline-secondary btn-sm text-start"><i class="fa-solid fa-sliders me-2"></i>Ajustar Límites y Logo</a>
                        <a href="../index.php" target="_blank" class="btn btn-outline-dark btn-sm text-start"><i class="fa-solid fa-external-link-alt me-2"></i>Ir al Sitio Principal</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // --- UNDO ---
    function undoAction(id) { alert('Funcionalidad de reversión pendiente de implementación en backend.'); }

    // --- CHARTS ---
    const commonOpts = { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { legend: { display: false } } };
    
    new Chart(document.getElementById('userChart'), {
        type: 'doughnut',
        data: { labels: ['Usado', 'Libre'], datasets: [{ data: [<?= $userActive ?>, <?= $userAvailable ?>], backgroundColor: ['#0d6efd', '#e9ecef'], borderWidth: 0 }] },
        options: commonOpts
    });

    new Chart(document.getElementById('apiChart'), {
        type: 'doughnut',
        data: { labels: ['Usado', 'Libre'], datasets: [{ data: [<?= $apiUsed ?>, <?= $apiAvailable ?>], backgroundColor: ['#ffc107', '#e9ecef'], borderWidth: 0 }] },
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

<?php include 'footer.php'; ?>