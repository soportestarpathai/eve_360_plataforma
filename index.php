<?php 
include 'templates/header.php'; 

// Cargar utilidades mejoradas
require_once __DIR__ . '/config/logger.php';
require_once __DIR__ . '/config/cache.php';
require_once __DIR__ . '/config/banxico_api.php';
require_once __DIR__ . '/config/pld_validation.php';
require_once __DIR__ . '/config/pld_middleware.php';
require_once __DIR__ . '/config/pld_revalidation.php';

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

// --- 2. STATISTICS DATA (Optimizado - Una sola consulta con GROUP BY) ---
$statsData = [
    'total_clientes' => 0,
    'clientes_activos' => 0,
    'clientes_inactivos' => 0,
    'clientes_pendientes' => 0,
    'notificaciones_pendientes' => 0,
    'total_usuarios' => 0
];

try {
    // Optimizado: Una sola consulta con GROUP BY en lugar de múltiples COUNT
    $stmtStats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN id_status = 1 THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN id_status = 0 THEN 1 ELSE 0 END) as inactivos,
            SUM(CASE WHEN id_status = 2 THEN 1 ELSE 0 END) as pendientes
        FROM clientes
    ");
    $clientStats = $stmtStats->fetch(PDO::FETCH_ASSOC);
    $statsData['total_clientes'] = (int)($clientStats['total'] ?? 0);
    $statsData['clientes_activos'] = (int)($clientStats['activos'] ?? 0);
    $statsData['clientes_inactivos'] = (int)($clientStats['inactivos'] ?? 0);
    $statsData['clientes_pendientes'] = (int)($clientStats['pendientes'] ?? 0);
    
    // Notificaciones pendientes
    if (isset($_SESSION['user_id'])) {
        try {
            $stmtNotif = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE id_usuario = ? AND estado != 'descartado' AND (snooze_until IS NULL OR snooze_until <= NOW())");
            $stmtNotif->execute([$_SESSION['user_id']]);
            $statsData['notificaciones_pendientes'] = (int)$stmtNotif->fetchColumn();
        } catch (Exception $e) {
            $statsData['notificaciones_pendientes'] = 0;
        }
    }
    
    // Total de usuarios activos
    try {
        $stmtUsers = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE id_status_usuario = 1");
        $statsData['total_usuarios'] = (int)$stmtUsers->fetchColumn();
    } catch (Exception $e) {
        $statsData['total_usuarios'] = 0;
    }
    
    $logger->debug('Dashboard Stats: Datos calculados', $statsData);
    
} catch (Exception $e) {
    $logger->error('Error al calcular estadísticas', [
        'error' => $e->getMessage(),
        'file' => __FILE__,
        'line' => $e->getLine()
    ]);
}

// --- 3. RISK CHART DATA ---
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

    // 2. Get Active Clients Scores (Optimizado - Procesamiento más eficiente)
    // Índice sugerido: CREATE INDEX idx_clientes_status_riesgo ON clientes(id_status, nivel_riesgo);
    $stmtClients = $pdo->query("SELECT nivel_riesgo FROM clientes WHERE id_status = 1");
    $allScores = $stmtClients->fetchAll(PDO::FETCH_COLUMN); // Más eficiente que fetch en loop
    
    foreach ($allScores as $score) {
        $score = floatval($score);
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

// --- 4. MENU DATA ---
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

// Reportes apunta a Conservación PLD; quitar "Conservación PLD" como ítem apartado
foreach ($menuTree as &$item) {
    if (isset($item['label']) && $item['label'] === 'Reportes') {
        $item['link'] = 'conservacion_pld.php';
    }
}
unset($item);
$menuTree = array_values(array_filter($menuTree, function ($item) {
    return (isset($item['label']) && $item['label'] !== 'Conservación PLD');
}));

$logger->debug('Menu: Estructura cargada', [
    'companyType' => $currentCompanyType,
    'menuItems' => count($rawMenu)
]);

// --- 5. ADDITIONAL CHARTS DATA ---
// Optimizado: Una sola consulta para todos los meses usando GROUP BY
// Índice sugerido: CREATE INDEX idx_clientes_fecha_apertura_status ON clientes(fecha_apertura, id_status);
$monthlyClients = ['labels' => [], 'data' => []];
$statusComparison = ['labels' => [], 'activos' => [], 'inactivos' => []];
$months = [];

try {
    // Preparar meses y labels una sola vez
    for ($i = 5; $i >= 0; $i--) {
        $date = date('Y-m', strtotime("-$i months"));
        $months[] = $date;
        $monthlyClients['labels'][] = date('M Y', strtotime($date . '-01'));
    }
    $statusComparison['labels'] = $monthlyClients['labels'];
    
    // Optimizado: Una sola consulta con GROUP BY para obtener todos los meses
    $startDate = date('Y-m-01', strtotime('-5 months'));
    $endDate = date('Y-m-t');
    
    $stmtMonthly = $pdo->prepare("
        SELECT 
            DATE_FORMAT(fecha_apertura, '%Y-%m') as mes,
            COUNT(*) as total
        FROM clientes
        WHERE fecha_apertura IS NOT NULL 
          AND fecha_apertura BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(fecha_apertura, '%Y-%m')
        ORDER BY mes ASC
    ");
    $stmtMonthly->execute([$startDate, $endDate]);
    $monthlyData = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir a array asociativo para búsqueda rápida
    $monthlyMap = [];
    foreach ($monthlyData as $row) {
        $monthlyMap[$row['mes']] = (int)$row['total'];
    }
    
    // Llenar datos para cada mes
    foreach ($months as $month) {
        $monthlyClients['data'][] = $monthlyMap[$month] ?? 0;
    }
    
    // Optimizado: Calcular acumulados hasta cada mes usando consultas preparadas eficientes
    $stmtAct = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE id_status = 1 AND fecha_apertura IS NOT NULL AND fecha_apertura <= ?");
    $stmtInact = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE id_status = 0 AND fecha_apertura IS NOT NULL AND fecha_apertura <= ?");
    
    foreach ($months as $month) {
        $endDate = date('Y-m-t', strtotime($month . '-01'));
        try {
            $stmtAct->execute([$endDate]);
            $statusComparison['activos'][] = (int)$stmtAct->fetchColumn();
            
            $stmtInact->execute([$endDate]);
            $statusComparison['inactivos'][] = (int)$stmtInact->fetchColumn();
        } catch (Exception $e) {
            $statusComparison['activos'][] = 0;
            $statusComparison['inactivos'][] = 0;
        }
    }
    
    $logger->debug('Monthly Clients: Datos calculados', $monthlyClients);
    $logger->debug('Status Comparison: Datos calculados', $statusComparison);
    
} catch (Exception $e) {
    $logger->error('Error al calcular gráficos adicionales', ['error' => $e->getMessage()]);
    // Asegurar datos por defecto
    if (empty($monthlyClients['labels'])) {
        for ($i = 5; $i >= 0; $i--) {
            $monthlyClients['labels'][] = date('M Y', strtotime("-$i months"));
            $monthlyClients['data'][] = 0;
        }
        $statusComparison['labels'] = $monthlyClients['labels'];
        $statusComparison['activos'] = array_fill(0, 6, 0);
        $statusComparison['inactivos'] = array_fill(0, 6, 0);
    }
}

// Gráfico de barras horizontal: Top 5 niveles de riesgo
$topRiskLevels = ['labels' => [], 'data' => [], 'colors' => []];
try {
    // Ya tenemos los datos de riesgo, los ordenamos y tomamos top 5
    $riskDataArray = [];
    foreach ($ranges as $r) {
        if (isset($stats[$r['nivel']]) && $stats[$r['nivel']]['count'] > 0) {
            $riskDataArray[] = [
                'label' => $r['nivel'],
                'count' => $stats[$r['nivel']]['count'],
                'color' => $r['color_hex']
            ];
        }
    }
    
    // Agregar "Sin Clasificar" si tiene datos
    if ($stats['Sin Clasificar']['count'] > 0) {
        $riskDataArray[] = [
            'label' => 'Sin Clasificar',
            'count' => $stats['Sin Clasificar']['count'],
            'color' => '#6c757d'
        ];
    }
    
    // Ordenar por count descendente y tomar top 5
    usort($riskDataArray, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    $topRiskLevels['labels'] = array_column(array_slice($riskDataArray, 0, 5), 'label');
    $topRiskLevels['data'] = array_column(array_slice($riskDataArray, 0, 5), 'count');
    $topRiskLevels['colors'] = array_column(array_slice($riskDataArray, 0, 5), 'color');
    
    $logger->debug('Top Risk Levels: Datos calculados', $topRiskLevels);
    
} catch (Exception $e) {
    $logger->error('Error al calcular top niveles de riesgo', ['error' => $e->getMessage()]);
}

// --- 6. RECENT CLIENTS DATA ---
// Optimizado: Usa fecha_apertura que es el campo que realmente se guarda
// Índice sugerido: CREATE INDEX idx_clientes_fecha_apertura ON clientes(fecha_apertura DESC);
$recentClients = [];
try {
    $stmtRecent = $pdo->prepare("
        SELECT c.id_cliente, 
               COALESCE(
                   CONCAT(cf.nombre, ' ', cf.apellido_paterno, ' ', COALESCE(cf.apellido_materno, '')),
                   cm.razon_social, 
                   'Sin Nombre'
               ) as nombre_cliente,
               c.nivel_riesgo,
               c.id_status,
               c.fecha_apertura as fecha_registro
        FROM clientes c
        LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
        LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
        WHERE c.fecha_apertura IS NOT NULL
        ORDER BY c.fecha_apertura DESC
        LIMIT 5
    ");
    $stmtRecent->execute();
    $recentClients = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
    
    $logger->debug('Recent Clients: Datos obtenidos', ['count' => count($recentClients)]);
    
} catch (Exception $e) {
    $logger->error('Error al obtener clientes recientes', ['error' => $e->getMessage()]);
}

// --- 7. RECENT NOTIFICATIONS DATA ---
$recentNotifications = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmtNotif = $pdo->prepare("
            SELECT n.*, 
                   COALESCE(cf.nombre, cm.razon_social, 'Sin Nombre') as nombre_cliente
            FROM notificaciones n
            LEFT JOIN clientes c ON n.id_cliente = c.id_cliente
            LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
            LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
            WHERE n.id_usuario = ?
            ORDER BY n.fecha_generacion DESC
            LIMIT 5
        ");
        $stmtNotif->execute([$_SESSION['user_id']]);
        $recentNotifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);
        
        $logger->debug('Recent Notifications: Datos obtenidos', ['count' => count($recentNotifications)]);
        
    } catch (Exception $e) {
        $logger->error('Error al obtener notificaciones recientes', ['error' => $e->getMessage()]);
    }
}

// --- 8. VALIDACIÓN PLD PATRÓN (VAL-PLD-001) ---
// IMPORTANTE: Esta validación es por EMPRESA (sujeto obligado), NO por cliente
$pldValidationResult = null;
$isPLDHabilitado = false;

try {
    // Validar padrón PLD del sujeto obligado (la empresa que usa la plataforma)
    $pldValidationResult = validatePatronPLD($pdo);
    $isPLDHabilitado = $pldValidationResult['habilitado'] === true;
    
    // Actualizar flag en base de datos
    updateHabilitadoPLDFlag($pdo, $isPLDHabilitado);
    
    $logger->debug('PLD Patrón Validation', [
        'habilitado' => $isPLDHabilitado,
        'estatus' => $pldValidationResult['estatus'] ?? 'UNKNOWN'
    ]);
    
} catch (Exception $e) {
    $logger->error('Error al validar padrón PLD', ['error' => $e->getMessage()]);
    $isPLDHabilitado = false;
    $pldValidationResult = [
        'habilitado' => false,
        'estatus' => 'NO_HABILITADO_PLD',
        'razon' => 'Error al validar: ' . $e->getMessage()
    ];
}

// --- 9. REVALIDACIÓN PERIÓDICA (VAL-PLD-002) ---
$revalidationStatus = null;
try {
    $revalidationStatus = checkRevalidationDue($pdo);
    $logger->debug('PLD Revalidation Status', $revalidationStatus);
} catch (Exception $e) {
    $logger->error('Error al verificar revalidación periódica', ['error' => $e->getMessage()]);
    $revalidationStatus = [
        'requiere_revalidacion' => false,
        'mensaje' => 'Error al verificar'
    ];
}
?>

<title>Dashboard - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="assets/css/dashboard.css">

<style>
    /* Variables dinámicas desde PHP */
    :root { 
        --primary-color: <?= !empty($appConfig['color_primario']) ? htmlspecialchars($appConfig['color_primario']) : '#1B8FEA' ?>;
    }
    
    /* Animación del ticker deshabilitada por rendimiento */
    /* .ticker-track {
        animation-duration: <?= max(30, count($tickerItems) * 12) ?>s;
    } */
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
    
    <?php if (!$isPLDHabilitado): ?>
    <!-- Alerta NO_HABILITADO_PLD -->
    <div class="alert alert-danger alert-dismissible fade show mx-3 mt-3" role="alert" style="border-left: 4px solid #dc3545;">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-triangle-exclamation fa-2x me-3"></i>
            <div class="flex-grow-1">
                <h5 class="alert-heading mb-1">
                    <strong>NO HABILITADO PARA OPERAR PLD</strong>
                </h5>
                <p class="mb-1">
                    <strong>Razón:</strong> <?= htmlspecialchars($pldValidationResult['razon'] ?? 'Validación de padrón PLD fallida') ?>
                </p>
                <p class="mb-0 small">
                    <strong>Estatus:</strong> <?= htmlspecialchars($pldValidationResult['estatus'] ?? 'NO_HABILITADO_PLD') ?>
                </p>
                <?php if (isset($pldValidationResult['detalles']) && !empty($pldValidationResult['detalles'])): ?>
                <details class="mt-2">
                    <summary class="small" style="cursor: pointer;">Ver detalles</summary>
                    <ul class="small mt-2 mb-0">
                        <?php foreach ($pldValidationResult['detalles'] as $key => $value): ?>
                            <li><strong><?= htmlspecialchars($key) ?>:</strong> 
                                <?= is_array($value) ? json_encode($value) : htmlspecialchars($value) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
                <?php endif; ?>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($revalidationStatus && $revalidationStatus['requiere_revalidacion']): ?>
    <!-- Alerta Revalidación Periódica (VAL-PLD-002) -->
    <div class="alert <?= $revalidationStatus['vencida'] ? 'alert-danger' : 'alert-warning' ?> alert-dismissible fade show mx-3 mt-3" role="alert" style="border-left: 4px solid <?= $revalidationStatus['vencida'] ? '#dc3545' : '#ffc107' ?>;">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-clock-rotate-left fa-2x me-3"></i>
            <div class="flex-grow-1">
                <h5 class="alert-heading mb-1">
                    <strong>REVALIDACIÓN PERIÓDICA PLD REQUERIDA</strong>
                </h5>
                <p class="mb-1">
                    <?= htmlspecialchars($revalidationStatus['mensaje'] ?? 'Revalidación periódica requerida') ?>
                </p>
                <p class="mb-0 small">
                    <strong>Periodo:</strong> Cada 3 meses | 
                    <strong>Última revalidación:</strong> 
                    <?php 
                        $stmt = $pdo->query("SELECT fecha_revalidacion_patron FROM config_empresa WHERE id_config = 1");
                        $fecha = $stmt->fetchColumn();
                        echo $fecha ? date('d/m/Y', strtotime($fecha)) : 'Nunca';
                    ?>
                </p>
                <div class="mt-2">
                    <a href="admin/config.php#pld-revalidation" class="btn btn-sm <?= $revalidationStatus['vencida'] ? 'btn-danger' : 'btn-warning' ?>">
                        <i class="fa-solid fa-arrow-right me-1"></i>Ir a Revalidar
                    </a>
                </div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="container-fluid dashboard-wrapper">
        <!-- Statistics Cards (Duralux Style) -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #1B8FEA 0%, #0B3C8A 100%);">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?= number_format($statsData['total_clientes']) ?></h3>
                        <p class="stat-label">Total Clientes</p>
                        <small class="stat-change">
                            <i class="fa-solid fa-arrow-up"></i>
                            <?= $statsData['clientes_activos'] ?> activos
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #2ED1FF 0%, #1B8FEA 100%);">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?= number_format($statsData['clientes_activos']) ?></h3>
                        <p class="stat-label">Clientes Activos</p>
                        <small class="stat-change text-success">
                            <i class="fa-solid fa-check-circle"></i>
                            <?= $statsData['total_clientes'] > 0 ? round(($statsData['clientes_activos'] / $statsData['total_clientes']) * 100) : 0 ?>% del total
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #0B486B 0%, #0B3C8A 100%);">
                        <i class="fa-solid fa-user-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?= number_format($statsData['clientes_pendientes']) ?></h3>
                        <p class="stat-label">Clientes Pendientes</p>
                        <small class="stat-change text-warning">
                            <i class="fa-solid fa-clock"></i>
                            Requieren atención
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);">
                        <i class="fa-solid fa-bell"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-value"><?= number_format($statsData['notificaciones_pendientes']) ?></h3>
                        <p class="stat-label">Notificaciones</p>
                        <small class="stat-change">
                            <i class="fa-solid fa-exclamation-circle"></i>
                            <?= $statsData['notificaciones_pendientes'] > 0 ? 'Pendientes' : 'Sin notificaciones' ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content Row -->
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
                    <h4 class="text-center mb-4">
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
        
        <!-- Info Widgets Section -->
        <div class="row g-4 mt-4">
            <!-- Clientes Recientes -->
            <div class="col-lg-6">
                <div class="info-widget">
                    <div class="widget-header">
                        <h5 class="widget-title">
                            <i class="fa-solid fa-clock-rotate-left me-2"></i>Clientes Recientes
                        </h5>
                        <a href="clientes.php" class="widget-link">
                            Ver todos <i class="fa-solid fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                    <div class="widget-body">
                        <?php if (empty($recentClients)): ?>
                            <div class="empty-widget">
                                <i class="fa-solid fa-users-slash fa-3x mb-3"></i>
                                <p class="mb-0">No hay clientes registrados</p>
                            </div>
                        <?php else: ?>
                            <div class="recent-list">
                                <?php foreach ($recentClients as $client): ?>
                                    <div class="recent-item">
                                        <div class="recent-avatar">
                                            <i class="fa-solid fa-user"></i>
                                        </div>
                                        <div class="recent-info">
                                            <h6 class="recent-name"><?= htmlspecialchars($client['nombre_cliente']) ?></h6>
                                            <small class="recent-date">
                                                <i class="fa-solid fa-calendar me-1"></i>
                                                <?= date('d/m/Y', strtotime($client['fecha_registro'])) ?>
                                            </small>
                                        </div>
                                        <div class="recent-badge">
                                            <span class="badge badge-risk" style="background-color: <?= 
                                                $client['nivel_riesgo'] >= 70 ? '#dc3545' : 
                                                ($client['nivel_riesgo'] >= 30 ? '#ffc107' : '#28a745')
                                            ?>">
                                                <?= number_format($client['nivel_riesgo'], 1) ?>
                                            </span>
                                        </div>
                                        <a href="cliente_detalle.php?id=<?= $client['id_cliente'] ?>" class="recent-action">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
        </div>
    </div>

            <!-- Notificaciones Recientes -->
            <div class="col-lg-6">
                <div class="info-widget">
                    <div class="widget-header">
                        <h5 class="widget-title">
                            <i class="fa-solid fa-bell me-2"></i>Notificaciones Recientes
                        </h5>
                        <a href="#" class="widget-link" onclick="toggleNotifPanel(); return false;">
                            Ver todas <i class="fa-solid fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                    <div class="widget-body">
                        <?php if (empty($recentNotifications)): ?>
                            <div class="empty-widget">
                                <i class="fa-solid fa-bell-slash fa-3x mb-3"></i>
                                <p class="mb-0">No hay notificaciones</p>
                            </div>
                        <?php else: ?>
                            <div class="notification-list">
                                <?php foreach ($recentNotifications as $notif): 
                                    $timeAgo = '';
                                    $notifDate = new DateTime($notif['fecha_generacion']);
                                    $now = new DateTime();
                                    $diff = $now->diff($notifDate);
                                    
                                    if ($diff->days > 0) {
                                        $timeAgo = $diff->days . ' día' . ($diff->days > 1 ? 's' : '');
                                    } elseif ($diff->h > 0) {
                                        $timeAgo = $diff->h . ' hora' . ($diff->h > 1 ? 's' : '');
                                    } else {
                                        $timeAgo = $diff->i . ' min';
                                    }
                                    
                                    $notifClass = 'notification-default';
                                    if (stripos($notif['tipo'], 'pld') !== false) $notifClass = 'notification-danger';
                                    elseif (stripos($notif['tipo'], 'pep') !== false || stripos($notif['tipo'], 'listas') !== false) $notifClass = 'notification-dark';
                                    elseif (stripos($notif['tipo'], 'vencida') !== false) $notifClass = 'notification-warning';
                                    elseif (stripos($notif['tipo'], 'kyc') !== false) $notifClass = 'notification-warning';
                                ?>
                                    <div class="notification-item <?= $notifClass ?>">
                                        <div class="notification-icon">
                                            <i class="fa-solid fa-circle-exclamation"></i>
                                        </div>
                                        <div class="notification-content">
                                            <h6 class="notification-title"><?= htmlspecialchars($notif['tipo']) ?></h6>
                                            <p class="notification-text"><?= htmlspecialchars($notif['nombre_cliente']) ?></p>
                                            <small class="notification-time">
                                                <i class="fa-solid fa-clock me-1"></i>Hace <?= $timeAgo ?>
                                            </small>
                                        </div>
                                        <a href="cliente_detalle.php?id=<?= $notif['id_cliente'] ?>" class="notification-action">
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Widget -->
        <div class="row g-4 mt-4">
            <div class="col-12">
                <div class="quick-actions-widget">
                    <h5 class="widget-title mb-4">
                        <i class="fa-solid fa-bolt me-2"></i>Acciones Rápidas
                    </h5>
                    <div class="quick-actions-grid">
                        <a href="cliente_nuevo.php" class="quick-action-btn">
                            <div class="quick-action-icon" style="background: linear-gradient(135deg, #1B8FEA 0%, #0B3C8A 100%);">
                                <i class="fa-solid fa-user-plus"></i>
                            </div>
                            <span class="quick-action-label">Nuevo Cliente</span>
                        </a>
                        <a href="clientes.php" class="quick-action-btn">
                            <div class="quick-action-icon" style="background: linear-gradient(135deg, #2ED1FF 0%, #1B8FEA 100%);">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <span class="quick-action-label">Ver Clientes</span>
                        </a>
                        <a href="config_ebr.php" class="quick-action-btn">
                            <div class="quick-action-icon" style="background: linear-gradient(135deg, #0B486B 0%, #0B3C8A 100%);">
                                <i class="fa-solid fa-sliders"></i>
                            </div>
                            <span class="quick-action-label">Configurar EBR</span>
                        </a>
                        <a href="conservacion_pld.php" class="quick-action-btn">
                            <div class="quick-action-icon" style="background: linear-gradient(135deg, #FF6B6B 0%, #EE5A6F 100%);">
                                <i class="fa-solid fa-chart-pie"></i>
                            </div>
                            <span class="quick-action-label">Reportes</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Charts Section (Duralux Style) -->
        <div class="row g-4 mt-4">
            <!-- Gráfico de Barras: Clientes por Mes -->
            <div class="col-lg-6">
                <div class="chart-card">
                    <h4 class="mb-4">
                        <i class="fa-solid fa-chart-column me-2"></i>Clientes por Mes
                    </h4>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                    <div class="text-center mt-3">
                        <p class="small mb-0" style="color: var(--eve-blue-deep); font-weight: 500;">
                            <i class="fa-solid fa-info-circle me-2" style="color: var(--eve-blue-medium);"></i>
                            Tendencia de Registros (Últimos 6 Meses)
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de Líneas: Activos vs Inactivos -->
            <div class="col-lg-6">
                <div class="chart-card">
                    <h4 class="mb-4">
                        <i class="fa-solid fa-chart-line me-2"></i>Activos vs Inactivos
                    </h4>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="text-center mt-3">
                        <p class="small mb-0" style="color: var(--eve-blue-deep); font-weight: 500;">
                            <i class="fa-solid fa-info-circle me-2" style="color: var(--eve-blue-medium);"></i>
                            Comparativa de Estados (Últimos 6 Meses)
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de Barras Horizontal: Top Niveles de Riesgo -->
            <div class="col-lg-6">
                <div class="chart-card">
                    <h4 class="mb-4">
                        <i class="fa-solid fa-chart-bar me-2"></i>Top Niveles de Riesgo
                    </h4>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="topRiskChart"></canvas>
                    </div>
                    <div class="text-center mt-3">
                        <p class="small mb-0" style="color: var(--eve-blue-deep); font-weight: 500;">
                            <i class="fa-solid fa-info-circle me-2" style="color: var(--eve-blue-medium);"></i>
                            Top 5 Niveles con Más Clientes
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de Área: Distribución Acumulada -->
            <div class="col-lg-6">
                <div class="chart-card">
                    <h4 class="mb-4">
                        <i class="fa-solid fa-area-chart me-2"></i>Distribución Acumulada
                    </h4>
                    <div style="position: relative; height: 300px; width: 100%;">
                        <canvas id="areaChart"></canvas>
                    </div>
                    <div class="text-center mt-3">
                        <p class="small mb-0" style="color: var(--eve-blue-deep); font-weight: 500;">
                            <i class="fa-solid fa-info-circle me-2" style="color: var(--eve-blue-medium);"></i>
                            Evolución de Clientes por Mes
                        </p>
                    </div>
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
    
    // Datos adicionales para nuevas gráficas
    const monthlyClients = <?= json_encode($monthlyClients) ?>;
    const statusComparison = <?= json_encode($statusComparison) ?>;
    const topRiskLevels = <?= json_encode($topRiskLevels) ?>;
</script>
<script src="assets/js/dashboard.js"></script>

<?php include 'templates/footer.php'; ?>