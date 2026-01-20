<?php 
include 'templates/header.php'; 

// Cargar utilidades mejoradas
require_once __DIR__ . '/config/logger.php';
require_once __DIR__ . '/config/cache.php';
require_once __DIR__ . '/config/banxico_api.php';

// Inicializar logger y caché
$logger = Logger::getInstance();
$banxicoAPI = new BanxicoAPI();

// --- 1. DYNAMIC MENU LOGIC ---
$currentCompanyType = 1;
$watermarkText = '';
$tickerItems = []; 

try {
    // A. Config & Watermark
    $stmtConfig = $pdo->query("SELECT * FROM config_empresa WHERE id_config = 1");
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC); 
    $currentCompanyType = $config['id_tipo_empresa'] ?? 1; 
    $id_vulnerable = $config['id_vulnerable'] ?? 0;

    if ($id_vulnerable > 0) {
        $stmtVuln = $pdo->prepare("SELECT fraccion FROM cat_vulnerables WHERE id_vulnerable = ?");
        $stmtVuln->execute([$id_vulnerable]);
        $res = $stmtVuln->fetch(PDO::FETCH_ASSOC);
        if ($res) $watermarkText = $res['fraccion'];
    }

    // B. Ticker: UMA (Optimizada con índice sugerido)
    // Índice sugerido: CREATE INDEX idx_indicadores_nombre_fecha ON indicadores(nombre, fecha DESC);
    $stmtUMA = $pdo->prepare("SELECT valor, fecha FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1");
    $stmtUMA->execute();
    $umaLocal = $stmtUMA->fetch(PDO::FETCH_ASSOC);
    if ($umaLocal) {
        $year = date('Y', strtotime($umaLocal['fecha']));
        $valor = number_format($umaLocal['valor'], 2);
        $tickerItems[] = "<i class='fa-solid fa-scale-balanced me-2 text-warning'></i>UMA {$year}: <strong>$ {$valor} MXN</strong>";
    }

    // C. Ticker: Banxico (Mejorado con caché, validación y manejo de errores)
    try {
        $seriesIds = ['SP68257', 'SF43718', 'SF46410', 'SP74660'];
        $banxicoData = $banxicoAPI->getSeriesData($seriesIds, 1800); // 30 minutos de caché
        
        if ($banxicoData && is_array($banxicoData)) {
            foreach ($banxicoData as $serie) {
                $val = number_format($serie['dato'], 2);
                $date = $serie['fecha'];
                
                switch ($serie['idSerie']) {
                    case 'SP68257': 
                        $tickerItems[] = "<i class='fa-solid fa-coins me-2 text-info'></i>UDIS: <strong>$ {$val}</strong>"; 
                        break;
                    case 'SF43718': 
                        $tickerItems[] = "<i class='fa-solid fa-dollar-sign me-2 text-success'></i>Dólar: <strong>$ {$val} MXN</strong>"; 
                        break;
                    case 'SF46410': 
                        $tickerItems[] = "<i class='fa-solid fa-euro-sign me-2 text-primary'></i>Euro: <strong>$ {$val} MXN</strong>"; 
                        break;
                    case 'SP74660': 
                        $tickerItems[] = "<i class='fa-solid fa-chart-line me-2 text-danger'></i>Inflación: <strong>{$val}%</strong>"; 
                        break;
                }
            }
            $logger->debug('BanxicoAPI: Datos obtenidos correctamente', ['count' => count($banxicoData)]);
        } else {
            $logger->warning('BanxicoAPI: No se obtuvieron datos', ['seriesIds' => implode(',', $seriesIds)]);
        }
    } catch (Exception $e) {
        $logger->error('BanxicoAPI: Error al obtener datos', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        // Continuar sin datos de Banxico (degradación elegante)
    }

} catch (Exception $e) {
    $logger->error('Error en lógica de menú dinámico', [
        'error' => $e->getMessage(),
        'file' => __FILE__,
        'line' => $e->getLine()
    ]);
}

// --- 2. RISK CHART DATA ---
$riskCounts = [];
$riskLabels = [];
$riskColors = [];

try {
    // 1. Get Ranges (Optimizada - Índice sugerido: PRIMARY KEY o índice en id_config_riesgo)
    // Índice sugerido: CREATE INDEX idx_riesgo_min_max ON config_riesgo_rangos(min_valor, max_valor);
    $stmtRanges = $pdo->query("SELECT * FROM config_riesgo_rangos ORDER BY min_valor ASC");
    $ranges = $stmtRanges->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize counters
    $stats = [];
    foreach ($ranges as $r) {
        $stats[$r['nivel']] = [
            'count' => 0, 
            'color' => $r['color_hex'], 
            'min' => $r['min_valor'], 
            'max' => $r['max_valor']
        ];
    }
    // Add "Unrated" bucket
    $stats['Sin Clasificar'] = ['count' => 0, 'color' => '#6c757d', 'min' => -1, 'max' => -1];

    // 2. Get Active Clients Scores (Optimizada)
    // Índice sugerido: CREATE INDEX idx_clientes_status_riesgo ON clientes(id_status, nivel_riesgo);
    $stmtClients = $pdo->query("SELECT nivel_riesgo FROM clientes WHERE id_status = 1");
    while ($row = $stmtClients->fetch(PDO::FETCH_ASSOC)) {
        $score = floatval($row['nivel_riesgo']);
        $classified = false;
        
        foreach ($ranges as $r) {
            if ($score >= $r['min_valor'] && $score <= $r['max_valor']) {
                $stats[$r['nivel']]['count']++;
                $classified = true;
                break;
            }
        }
        if (!$classified) {
            $stats['Sin Clasificar']['count']++;
        }
    }

    // 3. Prepare Arrays for Chart.js
    foreach ($stats as $label => $data) {
        // Only include if count > 0 to keep chart clean
        if ($data['count'] > 0) {
            $riskLabels[] = $label;
            $riskCounts[] = $data['count'];
            $riskColors[] = $data['color'];
        }
    }
    
    $logger->debug('Risk Chart: Datos procesados', [
        'totalLevels' => count($riskLabels),
        'totalClients' => array_sum($riskCounts)
    ]);

} catch (Exception $e) {
    $logger->error('Error al procesar datos de riesgo', [
        'error' => $e->getMessage(),
        'file' => __FILE__,
        'line' => $e->getLine()
    ]);
}

// --- 3. MENU DATA ---
// Índice sugerido: CREATE INDEX idx_menu_tipo_parent ON menu_access(id_tipo_empresa, id_parent, id_menu_access);
$stmtMenu = $pdo->prepare("SELECT * FROM menu_access WHERE id_tipo_empresa = ? ORDER BY id_menu_access ASC");
$stmtMenu->execute([$currentCompanyType]);
$rawMenu = $stmtMenu->fetchAll(PDO::FETCH_ASSOC);

$menuTree = [];
$ref = [];
foreach ($rawMenu as $row) {
    $id = $row['id_menu_access'];
    $icon = !empty($row['icon']) ? $row['icon'] : 'fa-solid fa-circle';
    $ref[$id] = [ 'label' => $row['seccion'], 'icon' => $icon, 'link' => (!empty($row['file_path'])) ? $row['file_path'] : '#', 'submenu' => [] ];
}
foreach ($rawMenu as $row) {
    if ($row['id_parent'] == 0) { $menuTree[] = &$ref[$row['id_menu_access']]; }
    elseif (isset($ref[$row['id_parent']])) { $ref[$row['id_parent']]['submenu'][] = &$ref[$row['id_menu_access']]; }
}
foreach ($ref as &$node) { if (empty($node['submenu'])) unset($node['submenu']); }
unset($node);

$logger->debug('Menu: Estructura cargada', [
    'companyType' => $currentCompanyType,
    'menuItems' => count($rawMenu)
]);
?>

<title>Dashboard - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="assets/css/dashboard.css">

<style>
    /* Variables dinámicas desde PHP */
    :root { 
        --primary-color: <?= !empty($appConfig['color_primario']) ? htmlspecialchars($appConfig['color_primario']) : '#1B8FEA' ?>;
    }
    
    /* Duración de animación del ticker (calculada dinámicamente) */
    .ticker-track {
        animation-duration: <?= max(30, count($tickerItems) * 12) ?>s;
    }
</style>

<body>
    <?php include 'templates/top_bar.php'; ?>
    
    <?php if (!empty($tickerItems)): ?>
    <div class="news-ticker">
        <div class="ticker-track">
            <?php foreach ($tickerItems as $item): ?><div class="ticker-item"><?= $item ?></div><?php endforeach; ?>
            <?php foreach ($tickerItems as $item): ?><div class="ticker-item"><?= $item ?></div><?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($watermarkText)): ?>
        <div class="watermark"><?= htmlspecialchars($watermarkText) ?></div>
    <?php endif; ?>
    
    <div class="container-fluid dashboard-wrapper">
        <div class="row w-100 gx-5">
            
            <div class="col-lg-7 position-relative">
                <div class="menu-container" id="menuContainer">
                    <div class="donut-center" id="centerInfo">
                        <h5>Menu Principal</h5>
                        <div class="back-btn" id="backBtn" style="display: none;" onclick="goBack()">
                            <i class="fa-solid fa-rotate-left"></i>
                        </div>
                    </div>
                    </div>
            </div>

            <div class="col-lg-5">
                <div class="chart-card">
                    <h4 class="text-center text-secondary mb-4">
                        <i class="fa-solid fa-chart-pie me-2"></i>Perfil de Riesgo
                    </h4>
                    
                    <?php if(empty($riskCounts)): ?>
                        <div class="text-center py-5 empty-state">
                            <div class="empty-icon mb-3">
                                <i class="fa-solid fa-chart-pie fa-4x" style="color: var(--eve-gray-light);"></i>
                            </div>
                            <h6 style="color: var(--eve-blue-deep); font-weight: 600;">Sin datos disponibles</h6>
                            <p class="small text-muted mt-2 mb-0">No hay clientes activos para analizar</p>
                        </div>
                    <?php else: ?>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="riskChart"></canvas>
                        </div>
                        <div class="text-center mt-4">
                            <p class="small mb-0" style="color: var(--eve-blue-deep); font-weight: 500;">
                                <i class="fa-solid fa-info-circle me-2" style="color: var(--eve-blue-medium);"></i>
                                Distribución de Clientes Activos por Nivel de Riesgo
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

<script>
    // Variables globales dinámicas desde PHP
    // Estas variables son necesarias para el JavaScript externo
    const menuData = <?= json_encode($menuTree) ?>;
    const riskLabels = <?= json_encode($riskLabels) ?>;
    const riskCounts = <?= json_encode($riskCounts) ?>;
    const riskColors = <?= json_encode($riskColors) ?>;
</script>
<script src="assets/js/dashboard.js"></script>

<?php include 'templates/footer.php'; ?>