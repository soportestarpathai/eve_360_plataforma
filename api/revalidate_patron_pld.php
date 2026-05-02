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

// Verificar sesión: permite admin del panel O usuario con permiso administración
$es_admin_panel = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$tiene_permiso = false;

if ($es_admin_panel) {
    $tiene_permiso = true;
} elseif (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    try {
        $stmt = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $perm = $stmt->fetchColumn();
        $tiene_permiso = !empty($perm) && $perm != 0;
    } catch (Exception $e) {
        // Si no hay tabla de permisos, denegar
    }
}

if (!$tiene_permiso) {
    if (!$es_admin_panel && !isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    } else {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'No tienes permisos para revalidar el padrón']);
    }
    exit;
}

$logger = Logger::getInstance();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $id_usuario = isset($_GET['id_usuario']) ? (int)$_GET['id_usuario'] : 0;
        $revalidationStatus = checkRevalidationDue($pdo, $id_usuario);
        $validationResult = validatePatronPLD($pdo, null, $id_usuario);
        
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
            'fracciones' => $data['fracciones'] ?? null,
            'subfracciones_xi' => isset($data['subfracciones_xi']) ? $data['subfracciones_xi'] : null,
            'subfracciones_ii' => isset($data['subfracciones_ii']) ? $data['subfracciones_ii'] : null,
            'subfracciones_xii' => isset($data['subfracciones_xii']) ? $data['subfracciones_xii'] : null,
            'subfracciones_xii_fes' => isset($data['subfracciones_xii_fes']) ? $data['subfracciones_xii_fes'] : null,
            'subfracciones_xiv' => isset($data['subfracciones_xiv']) ? $data['subfracciones_xiv'] : null
        ];
        $confirmarCambios = isset($data['confirmar']) && $data['confirmar'] === true;
        $id_usuario = isset($data['id_usuario']) ? (int)$data['id_usuario'] : 0;
        
        $result = processRevalidation($pdo, $nuevosDatos, $confirmarCambios, $id_usuario);
        // Compatibilidad: el frontend usa data.message, la lógica devuelve mensaje
        if (isset($result['mensaje']) && !isset($result['message'])) {
            $result['message'] = $result['mensaje'];
        }
        if ($result['status'] === 'pending_confirmation') {
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
