<?php
session_start();
require_once '../config/db.php';
require_once '../config/bitacora.php'; // Include the logger utility
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id_usuario_actual = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);
$ranges = $data['ranges'] ?? [];

if (empty($ranges)) {
    echo json_encode(['status' => 'error', 'message' => 'No data provided']);
    exit;
}

// --- VALIDATION LOGIC ---
// Sort ranges by min value to check sequence
usort($ranges, function($a, $b) {
    return $a['min'] <=> $b['min'];
});

$lastMax = -1;
foreach ($ranges as $r) {
    $min = floatval($r['min']); // Use floatval for decimals
    $max = floatval($r['max']); // Use floatval for decimals
    
    if ($min > $max) {
        echo json_encode(['status' => 'error', 'message' => "Rango inválido: Min ($min) es mayor que Max ($max) en '{$r['nivel']}'"]);
        exit;
    }
    
    // Check overlap (allow touching e.g. 0-30.5, 30.5-70)
    // If current min is strictly less than last max, we have overlap
    if ($min < $lastMax) { 
        echo json_encode(['status' => 'error', 'message' => "Superposición detectada: El rango '{$r['nivel']}' comienza en $min, pero el anterior terminó en $lastMax"]);
        exit;
    }
    
    $lastMax = $max;
}

if ($lastMax > 100) {
     echo json_encode(['status' => 'error', 'message' => "El rango máximo no puede exceder 100"]);
     exit;
}
// --- END VALIDATION ---

try {
    $pdo->beginTransaction();
    
    // 1. Fetch OLD data for logging before deletion
    $stmtOld = $pdo->query("SELECT * FROM config_riesgo_rangos ORDER BY min_valor ASC");
    $oldRanges = $stmtOld->fetchAll(PDO::FETCH_ASSOC);

    // 2. Clear existing ranges
    $pdo->query("DELETE FROM config_riesgo_rangos");

    // 3. Insert NEW ranges
    $stmt = $pdo->prepare("INSERT INTO config_riesgo_rangos (nivel, min_valor, max_valor, color_hex) VALUES (?, ?, ?, ?)");

    foreach ($ranges as $r) {
        $stmt->execute([$r['nivel'], $r['min'], $r['max'], $r['color']]);
    }

    // 4. Log Change to Bitacora
    // We log the entire set of ranges as one "update" action on the configuration table
    // Since there isn't a single ID for the whole configuration, we can use 0 or a reserved ID
    logChange($pdo, $id_usuario_actual, "ACTUALIZAR_CONFIG", "config_riesgo_rangos", 0, $oldRanges, $ranges);

    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>