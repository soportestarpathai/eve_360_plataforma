<?php
/**
 * API: Registrar visita de verificación (VAL-PLD-014)
 * Si expedientes no disponibles → se registra evento crítico
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_conservacion.php';
require_once __DIR__ . '/../config/bitacora.php';

try {
    $pdo = getDBConnection();
    $id_usuario = $_SESSION['id_usuario'] ?? null;
    
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $fecha_visita = $input['fecha_visita'] ?? null;
    $autoridad = $input['autoridad'] ?? null;
    $tipo_requerimiento = $input['tipo_requerimiento'] ?? null;
    $expedientes_solicitados = $input['expedientes_solicitados'] ?? null;
    $observaciones = $input['observaciones'] ?? null;
    
    if (!$fecha_visita) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Fecha de visita es requerida']);
        exit;
    }
    
    $data = [
        'fecha_visita' => $fecha_visita,
        'autoridad' => $autoridad,
        'tipo_requerimiento' => $tipo_requerimiento,
        'expedientes_solicitados' => $expedientes_solicitados,
        'observaciones' => $observaciones,
        'id_usuario' => $id_usuario
    ];
    
    $result = registrarVisitaVerificacion($pdo, $data);
    
    if (!$result['success']) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $result['message'] ?? 'Error al registrar visita']);
        exit;
    }
    
    if (!empty($result['evento_critico']) && $id_usuario) {
        logChange($pdo, (int)$id_usuario, 'REGISTRAR_VISITA_VERIFICACION_EVENTO_CRITICO',
            'visitas_verificacion_pld', (int)$result['id_visita'], null,
            ['expedientes_disponibles' => 0, 'evento_critico' => true]);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => $result['message'],
        'id_visita' => $result['id_visita'],
        'expedientes_disponibles' => $result['expedientes_disponibles'] ?? true,
        'evento_critico' => $result['evento_critico'] ?? false
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    error_log("registrar_visita_verificacion.php: " . $e->getMessage());
}
