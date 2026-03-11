<?php
/**
 * API: Datos KYC del cliente para formulario PLD (prellenado, solo lectura)
 * Usado en operacion_din.php y operaciones_pld.php para mostrar datos del cliente sin permitir edición
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_cliente_kyc.php';
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id_cliente = (int)($_GET['id'] ?? 0);
if (!$id_cliente) {
    echo json_encode(['status' => 'error', 'message' => 'id_cliente requerido']);
    exit;
}

try {
    $kyc = pldGetClienteKycData($pdo, $id_cliente);
    if (!$kyc) {
        echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado']);
        exit;
    }

    echo json_encode(['status' => 'success', 'kyc' => $kyc]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
