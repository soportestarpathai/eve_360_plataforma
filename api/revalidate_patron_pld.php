<?php
/**
 * API Endpoint: Revalidación Periódica PLD - VAL-PLD-002
 * Permite revalidar el padrón PLD desde la interfaz
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_validation.php';
require_once __DIR__ . '/../config/pld_revalidation.php';
require_once __DIR__ . '/../config/logger.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// Verificar permisos de administración (opcional, pero recomendado)
try {
    $stmt = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $perm = $stmt->fetchColumn();
    
    if (empty($perm) || $perm == 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'No tienes permisos para revalidar el padrón']);
        exit;
    }
} catch (Exception $e) {
    // Si no hay tabla de permisos, continuar (fallback)
}

$logger = Logger::getInstance();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Obtener estado de revalidación
        $revalidationStatus = checkRevalidationDue($pdo);
        $validationResult = validatePatronPLD($pdo);
        
        echo json_encode([
            'status' => 'success',
            'revalidation_status' => $revalidationStatus,
            'validation_result' => $validationResult
        ]);
        
    } elseif ($method === 'POST') {
        // Procesar revalidación con nuevos datos
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
            exit;
        }
        
        $nuevosDatos = [
            'folio' => $data['folio'] ?? null,
            'estatus' => $data['estatus'] ?? null,
            'fracciones' => $data['fracciones'] ?? null
        ];
        
        $confirmarCambios = isset($data['confirmar']) && $data['confirmar'] === true;
        
        $result = processRevalidation($pdo, $nuevosDatos, $confirmarCambios);
        
        if ($result['status'] === 'pending_confirmation') {
            // Requiere confirmación del usuario
            http_response_code(200);
            echo json_encode($result);
        } elseif ($result['status'] === 'success') {
            echo json_encode($result);
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    $logger->error('PLD Revalidation API Error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al procesar revalidación: ' . $e->getMessage()
    ]);
}
