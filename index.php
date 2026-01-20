<?php 
include 'templates/header.php'; 

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

    // B. Ticker: UMA
    $stmtUMA = $pdo->prepare("SELECT valor, fecha FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1");
    $stmtUMA->execute();
    $umaLocal = $stmtUMA->fetch(PDO::FETCH_ASSOC);
    if ($umaLocal) {
        $year = date('Y', strtotime($umaLocal['fecha']));
        $valor = number_format($umaLocal['valor'], 2);
        $tickerItems[] = "<i class='fa-solid fa-scale-balanced me-2 text-warning'></i>UMA {$year}: <strong>$ {$valor} MXN</strong>";
    }

    // C. Ticker: Banxico
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

} catch (Exception $e) { }

// --- 2. RISK CHART DATA ---
$riskCounts = [];
$riskLabels = [];
$riskColors = [];

try {
    // 1. Get Ranges (e.g. 0-30 Bajo, 31-70 Medio, 71-100 Alto)
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

    // 2. Get Active Clients Scores
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

} catch (Exception $e) { }

// --- 3. MENU DATA ---
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
?>

<title>Dashboard - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root { 
        --primary-color: <?= htmlspecialchars($appConfig['color_primario']) ?>; 
        --bg-color: #f0f2f5; 
        --item-size: 110px; 
    }
    
    body { background-color: var(--bg-color); overflow-x: hidden; }
    
    .dashboard-wrapper {
        min-height: calc(100vh - 100px); /* Height minus headers */
        display: flex;
        align-items: center;
        position: relative;
        z-index: 10;
    }

    /* --- TICKER --- */
    .news-ticker {
        background-color: #212529; color: #fff; height: 40px;
        overflow: hidden; position: relative; display: flex; align-items: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-bottom: 1px solid #343a40;
    }
    .ticker-track {
        display: flex; white-space: nowrap; position: absolute;
        will-change: transform; animation: marquee <?= max(30, count($tickerItems) * 12) ?>s linear infinite; 
    }
    .ticker-item { display: inline-flex; align-items: center; padding: 0 4rem; font-size: 0.9rem; }
    .ticker-item strong { color: #ffc107; margin-left: 6px; }
    @keyframes marquee { 0% { transform: translateX(100vw); } 100% { transform: translateX(-100%); } }
    .news-ticker:hover .ticker-track { animation-play-state: paused; }

    /* --- WATERMARK --- */
    .watermark {
        position: fixed; top: 55%; left: 0; transform: translate(-30%, -50%); 
        font-size: 40vw; font-weight: bold; color: rgba(0, 0, 0, 0.05);
        z-index: 0; pointer-events: none; font-family: 'Times New Roman', serif;
        user-select: none; white-space: nowrap;
    }

    /* --- LEFT MENU (Circular) --- */
    .menu-container {
        position: relative; height: 500px; width: 100%;
        display: flex; justify-content: center; align-items: center;
    }
    .donut-center { 
        position: absolute; 
        width: 240px; height: 240px; border-radius: 50%; 
        border: 2px dashed #d0d0d0; 
        display: flex; flex-direction: column; justify-content: center; align-items: center; 
        z-index: 5; background-color: rgba(255,255,255,0.6); backdrop-filter: blur(5px);
        transition: all 0.3s ease;
    }
    .donut-center h5 { color: var(--primary-color); font-weight: bold; margin: 0; text-transform: uppercase; text-align: center; font-size: 0.9rem; letter-spacing: 1px; }
    .donut-center .back-btn { margin-top: 15px; cursor: pointer; font-size: 1.5rem; color: #6c757d; transition: 0.2s; }
    .donut-center .back-btn:hover { color: var(--primary-color); transform: scale(1.1); }

    .menu-item {
        position: absolute; top: 50%; left: 50%;
        width: var(--item-size); height: var(--item-size);
        margin-left: calc(var(--item-size) / -2); margin-top: calc(var(--item-size) / -2);
        background: white; border-radius: 50%;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        display: flex; flex-direction: column; justify-content: center; align-items: center;
        text-decoration: none; color: #495057; cursor: pointer; z-index: 20;
        opacity: 0; transform: scale(0.5); 
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.4s ease;
    }
    .menu-item:hover { background-color: #fff; color: var(--primary-color); box-shadow: 0 8px 25px rgba(0,0,0,0.15); z-index: 30; }
    .menu-item i { font-size: 1.8rem; margin-bottom: 6px; }
    .menu-item span { font-size: 0.75rem; font-weight: 700; text-align: center; line-height: 1.1; max-width: 90%; }

    /* --- RIGHT CHART --- */
    .chart-card {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        padding: 2rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    @media (max-width: 992px) {
        .dashboard-wrapper { flex-direction: column; height: auto; padding-top: 2rem; }
        .menu-container { height: 400px; } /* Smaller on tablet */
    }
    
    @media (max-width: 768px) {
        .menu-container { height: auto; display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; padding: 20px; }
        .donut-center { display: none; }
        .menu-item { position: relative; top: auto; left: auto; margin: 0; width: 100%; height: 120px; border-radius: 12px; opacity: 1; transform: none !important; }
        .watermark { opacity: 0.05; }
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
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-users-slash fa-3x mb-3"></i><br>
                            Sin clientes activos para analizar.
                        </div>
                    <?php else: ?>
                        <div style="position: relative; height: 300px; width: 100%;">
                            <canvas id="riskChart"></canvas>
                        </div>
                        <div class="text-center mt-3 small text-muted">
                            Distribución de Clientes Activos por Nivel de Riesgo
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

<script>
    // --- MENU LOGIC ---
    const menuData = <?= json_encode($menuTree) ?>;
    const isMobile = window.innerWidth <= 768;
    const radius = isMobile ? 0 : 200; // Adjusted radius for column layout
    const container = document.getElementById('menuContainer');
    const centerTitle = document.querySelector('#centerInfo h5');
    const backBtn = document.getElementById('backBtn');
    
    let menuStack = [];

    document.addEventListener('DOMContentLoaded', () => {
        renderMenu(menuData);
        initChart();
    });

    function renderMenu(items) {
        container.querySelectorAll('.menu-item').forEach(el => el.remove());
        if (!items || items.length === 0) return;

        const total = items.length;
        const startAngle = -90; 

        items.forEach((data, index) => {
            const el = document.createElement('a');
            el.className = 'menu-item';
            
            const hasSubmenu = (data.submenu && data.submenu.length > 0);
            el.href = hasSubmenu ? '#' : data.link; 

            el.innerHTML = `<i class="fa-solid ${data.icon}"></i><span>${data.label}</span>`;
            
            el.addEventListener('click', (e) => {
                if (hasSubmenu) {
                    e.preventDefault();
                    menuStack.push({ items: items, title: centerTitle.textContent });
                    centerTitle.textContent = data.label;
                    backBtn.style.display = 'block';
                    renderMenu(data.submenu);
                }
            });

            if (!isMobile) {
                const angleDeg = startAngle + (360 / total) * index;
                const angleRad = angleDeg * (Math.PI / 180);
                
                const x = Math.cos(angleRad) * radius;
                const y = Math.sin(angleRad) * radius;
                
                container.appendChild(el);

                requestAnimationFrame(() => {
                    el.style.opacity = '1';
                    el.style.transform = `translate(${x}px, ${y}px) scale(1)`;
                });
            } else {
                container.appendChild(el);
                el.style.opacity = '1';
                el.style.transform = 'scale(1)';
            }
        });
    }

    function goBack() {
        if (menuStack.length === 0) return;
        const previousState = menuStack.pop();
        centerTitle.textContent = previousState.title;
        renderMenu(previousState.items);
        if (menuStack.length === 0) backBtn.style.display = 'none';
    }

    // --- CHART LOGIC ---
    function initChart() {
        const ctx = document.getElementById('riskChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($riskLabels) ?>,
                datasets: [{
                    data: <?= json_encode($riskCounts) ?>,
                    backgroundColor: <?= json_encode($riskColors) ?>,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.raw || 0;
                                let total = context.chart._metasets[context.datasetIndex].total;
                                let percentage = Math.round((value / total) * 100) + '%';
                                return `${label}: ${value} (${percentage})`;
                            }
                        }
                    }
                },
                cutout: '60%' // Makes it a nice donut
            }
        });
    }
    
    window.addEventListener('resize', () => { /* Handle resize if needed */ });
</script>

<?php include 'templates/footer.php'; ?>