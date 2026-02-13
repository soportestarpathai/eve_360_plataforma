<?php
/**
 * Baja lógica (soft delete) de aviso PLD.
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
$id_aviso = (int)($data['id_aviso'] ?? $data['id'] ?? 0);

if (!$id_aviso) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de aviso requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM avisos_pld WHERE id_aviso = ?");
    $stmt->execute([$id_aviso]);
    $aviso = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$aviso) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Aviso no encontrado']);
        exit;
    }

    if (!canModifyPLD($pdo, $id_usuario, (int)$aviso['id_cliente'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => mensajeSinPermisoPLD()]);
        exit;
    }

    $antes = $aviso;
    $despues = $aviso;
    $despues['id_status'] = 0;

    $pdo->prepare("UPDATE avisos_pld SET id_status = 0 WHERE id_aviso = ?")->execute([$id_aviso]);

    logChange($pdo, $id_usuario, 'BAJA_AVISO_PLD', 'avisos_pld', $id_aviso, $antes, $despues);

    echo json_encode([
        'status' => 'success',
        'message' => 'Aviso dado de baja (histórico conservado)',
        'id_aviso' => $id_aviso
    ]);

} catch (Exception $e) {
    error_log("baja_aviso_pld: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
