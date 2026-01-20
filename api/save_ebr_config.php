<?php
session_start();
require_once '../config/db.php';
require_once '../config/bitacora.php'; // Include Logger
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id_usuario_actual = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);
$id_factor = $data['id_factor'] ?? 0;
$items = $data['items'] ?? []; 

if (!$id_factor || empty($items)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Fetch OLD values for this factor for logging
    $stmtOld = $pdo->prepare("SELECT * FROM config_riesgo_valores WHERE id_factor = ?");
    $stmtOld->execute([$id_factor]);
    $oldValues = $stmtOld->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        INSERT INTO config_riesgo_valores (id_factor, id_valor_catalogo, nivel_riesgo) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE nivel_riesgo = VALUES(nivel_riesgo)
    ");

    foreach ($items as $item) {
        $stmt->execute([$id_factor, $item['id_item'], $item['risk']]);
    }

    // 2. Fetch NEW values (to have a complete snapshot of what changed)
    $stmtNew = $pdo->prepare("SELECT * FROM config_riesgo_valores WHERE id_factor = ?");
    $stmtNew->execute([$id_factor]);
    $newValues = $stmtNew->fetchAll(PDO::FETCH_ASSOC);

    // 3. Log Change
    // We log this as a bulk update to the values of a specific factor
    logChange($pdo, $id_usuario_actual, "ACTUALIZAR_VALORES_FACTOR", "config_riesgo_valores", $id_factor, $oldValues, $newValues);

    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>