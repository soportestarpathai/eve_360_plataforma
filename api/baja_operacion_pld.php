<?php
/**
 * Baja lógica (soft delete) de transacción PLD.
 * Mantiene histórico: id_status = 0.
 * Requiere permiso admin o responsable PLD. Bitácora registrada.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/pld_permisos.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id_usuario = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id_operacion = (int)($data['id_operacion'] ?? $data['id'] ?? 0);

if (!$id_operacion) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de transacción requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM operaciones_pld WHERE id_operacion = ?");
    $stmt->execute([$id_operacion]);
    $op = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$op) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Transacción no encontrada']);
        exit;
    }

    if (!canModifyPLD($pdo, $id_usuario, (int)$op['id_cliente'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => mensajeSinPermisoPLD()]);
        exit;
    }

    $antes = $op;
    $despues = $op;
    $despues['id_status'] = 0;

    $pdo->prepare("UPDATE operaciones_pld SET id_status = 0 WHERE id_operacion = ?")->execute([$id_operacion]);

    logChange($pdo, $id_usuario, 'BAJA_OPERACION_PLD', 'operaciones_pld', $id_operacion, $antes, $despues);

    echo json_encode([
        'status' => 'success',
        'message' => 'Transacción dada de baja (histórico conservado)',
        'id_operacion' => $id_operacion
    ]);

} catch (Exception $e) {
    error_log("baja_operacion_pld: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

