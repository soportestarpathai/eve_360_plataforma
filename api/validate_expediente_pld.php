<?php
session_start();
require_once '../config/db.php';
require_once '../config/pld_expediente.php';
header('Content-Type: application/json');

// Verificar conexión a la base de datos
if (!isset($pdo) || $pdo === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id_cliente = $_GET['id_cliente'] ?? $_POST['id_cliente'] ?? null;

if (!$id_cliente) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de cliente requerido']);
    exit;
}

try {
    // Validar completitud (VAL-PLD-005)
    $resultCompleto = validateExpedienteCompleto($pdo, $id_cliente);
    
    // Validar actualización (VAL-PLD-006)
    $resultActualizacion = validateActualizacionExpediente($pdo, $id_cliente);
    
    // Asegurar que faltantes sea un array
    if (!isset($resultCompleto['faltantes']) || !is_array($resultCompleto['faltantes'])) {
        $resultCompleto['faltantes'] = [];
    }
    
    // Log para debug
    error_log("API validate_expediente_pld Cliente $id_cliente: " .
             "Completo=" . ($resultCompleto['completo'] ? 'SÍ' : 'NO') . ", " .
             "Faltantes=" . count($resultCompleto['faltantes']) . ", " .
             "Actualizado=" . ($resultActualizacion['actualizado'] ? 'SÍ' : 'NO'));
    
    echo json_encode([
        'status' => 'success',
        'completitud' => $resultCompleto,
        'actualizacion' => $resultActualizacion,
        'valido' => $resultCompleto['completo'] && $resultActualizacion['actualizado']
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en validate_expediente_pld.php: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al validar expediente: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    http_response_code(500);
    error_log("Error fatal en validate_expediente_pld.php: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error fatal al validar expediente: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
