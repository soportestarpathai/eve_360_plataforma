<?php
/**
 * API Endpoint: Designar Responsable PLD - VAL-PLD-003
 * Permite designar un responsable PLD para un cliente (persona moral o fideicomiso)
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_responsable_validation.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/logger.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// Verificar permisos de administración
try {
    $stmt = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $perm = $stmt->fetchColumn();
    
    if (empty($perm) || $perm == 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'No tienes permisos para designar responsables PLD']);
        exit;
    }
} catch (Exception $e) {
    // Si no hay tabla de permisos, continuar (fallback)
}

$logger = Logger::getInstance();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Obtener información del responsable PLD de un cliente
        $id_cliente = $_GET['id_cliente'] ?? 0;
        
        if (!$id_cliente) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID de cliente requerido']);
            exit;
        }
        
        $validation = validateResponsablePLD($pdo, $id_cliente);
        
        echo json_encode([
            'status' => 'success',
            'validation' => $validation
        ]);
        
    } elseif ($method === 'POST') {
        // Designar responsable PLD
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
            exit;
        }
        
        $id_cliente = $data['id_cliente'] ?? 0;
        $id_usuario_responsable = $data['id_usuario_responsable'] ?? 0;
        $observaciones = $data['observaciones'] ?? null;
        
        if (!$id_cliente || !$id_usuario_responsable) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID de cliente y usuario responsable son requeridos']);
            exit;
        }
        
        $result = designarResponsablePLD($pdo, $id_cliente, $id_usuario_responsable, $observaciones);
        
        if ($result['status'] === 'success') {
            // Log en bitácora
            logChange($pdo, $_SESSION['user_id'], 'DESIGNAR_RESPONSABLE_PLD', 'clientes_responsable_pld', $result['id_responsable'], null, [
                'id_cliente' => $id_cliente,
                'id_usuario_responsable' => $id_usuario_responsable,
                'responsable_nombre' => $result['responsable_nombre']
            ]);
            
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode($result);
        }
        
    } elseif ($method === 'DELETE') {
        // Remover responsable PLD
        $data = json_decode(file_get_contents('php://input'), true);
        $id_cliente = $data['id_cliente'] ?? 0;
        
        if (!$id_cliente) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID de cliente requerido']);
            exit;
        }
        
        // Desactivar responsables activos
        $stmt = $pdo->prepare("
            UPDATE clientes_responsable_pld 
            SET activo = 0, fecha_baja = CURDATE()
            WHERE id_cliente = ? AND activo = 1
        ");
        $stmt->execute([$id_cliente]);
        
        // Actualizar flag de restricción
        $validation = validateResponsablePLD($pdo, $id_cliente);
        updateRestriccionUsuarioFlag($pdo, $id_cliente, $validation['restriccion']);
        
        // Log en bitácora
        logChange($pdo, $_SESSION['user_id'], 'REMOVER_RESPONSABLE_PLD', 'clientes_responsable_pld', 0, null, [
            'id_cliente' => $id_cliente
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Responsable PLD removido correctamente'
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    $logger->error('PLD Responsable API Error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al procesar solicitud: ' . $e->getMessage()
    ]);
}
