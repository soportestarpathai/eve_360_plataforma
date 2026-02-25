<?php
/**
 * API Endpoint: Registrar Transacción PLD (VAL-PLD-008)
 * Registra una operación y valida si requiere aviso por umbral individual
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/pld_middleware.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// VAL-PLD-001: Bloquear si no está habilitado
requirePLDHabilitado($pdo, true);

$id_usuario_actual = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (!$data) {
            $data = $_POST;
        }
        
        // Debug: Log datos recibidos (solo en desarrollo)
        error_log("Datos recibidos en registrar_operacion_pld.php: " . print_r($data, true));
        
        // Validar datos requeridos
        $id_cliente = $data['id_cliente'] ?? null;
        $monto = isset($data['monto']) ? floatval($data['monto']) : 0;
        $fecha_operacion = $data['fecha_operacion'] ?? date('Y-m-d');
        
        $errores = [];
        if (!$id_cliente || $id_cliente === '') {
            $errores[] = 'id_cliente es requerido';
        }
        if ($monto <= 0) {
            $errores[] = 'monto debe ser mayor a 0';
        }
        
        if (!empty($errores)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Datos incompletos: ' . implode(', ', $errores),
                'debug' => [
                    'id_cliente' => $id_cliente,
                    'monto' => $monto,
                    'fecha_operacion' => $fecha_operacion,
                    'datos_recibidos' => $data
                ]
            ]);
            exit;
        }
        
        // Registrar transacción PLD
        $result = registrarOperacionPLD($pdo, $data);
        
        if ($result['success'] ?? false) {
            // Log
            logChange($pdo, $id_usuario_actual, 'REGISTRAR_OPERACION_PLD', 
                     'operaciones_pld', $result['id_operacion'] ?? null, null, $data);
            
            echo json_encode([
                'status' => 'success',
                'message' => $result['message'] ?? 'Transacción registrada correctamente',
                'id_operacion' => $result['id_operacion'] ?? null,
                'id_aviso' => $result['id_aviso'] ?? null,
                'requiere_aviso' => $result['requiere_aviso'] ?? false,
                'tipo_aviso' => $result['tipo_aviso'] ?? null,
                'fecha_deadline' => $result['fecha_deadline'] ?? null,
                'validacion_umbral' => $result['validacion_umbral'] ?? null,
                'validacion_acumulacion' => $result['validacion_acumulacion'] ?? null
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $result['message'] ?? 'Error desconocido al registrar transacción',
                'debug' => $result
            ]);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en registrar_operacion_pld.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al procesar solicitud: ' . $e->getMessage()
    ]);
}

