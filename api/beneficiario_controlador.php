<?php
/**
 * API Endpoint: Gestión de Beneficiarios Controladores (VAL-PLD-007, VAL-PLD-015)
 * Permite crear, actualizar, eliminar y listar beneficiarios controladores
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_beneficiario_controlador.php';
require_once __DIR__ . '/../config/bitacora.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id_usuario_actual = $_SESSION['user_id'];

try {
    switch ($method) {
        case 'GET':
            // Listar beneficiarios de un cliente
            $id_cliente = $_GET['id_cliente'] ?? null;
            
            if (!$id_cliente) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ID de cliente requerido']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM clientes_beneficiario_controlador 
                                   WHERE id_cliente = ? AND id_status = 1
                                   ORDER BY fecha_registro DESC");
            $stmt->execute([$id_cliente]);
            $beneficiarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Validar estado
            $validacion = validateBeneficiarioControlador($pdo, $id_cliente);
            
            echo json_encode([
                'status' => 'success',
                'beneficiarios' => $beneficiarios,
                'validacion' => $validacion
            ]);
            break;
            
        case 'POST':
            // Crear o actualizar beneficiario
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                $data = $_POST;
            }
            
            $id_cliente = $data['id_cliente'] ?? null;
            $id_beneficiario = $data['id_beneficiario'] ?? null;
            
            if (!$id_cliente) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ID de cliente requerido']);
                exit;
            }
            
            // Manejar archivos si vienen en FormData
            if (isset($_FILES['documento_identificacion']) && $_FILES['documento_identificacion']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/beneficiarios/' . $id_cliente . '/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = time() . '_' . bin2hex(random_bytes(4)) . '_' . basename($_FILES['documento_identificacion']['name']);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['documento_identificacion']['tmp_name'], $filePath)) {
                    $data['documento_identificacion'] = str_replace('../', '', $filePath);
                }
            }
            
            if (isset($_FILES['declaracion_jurada']) && $_FILES['declaracion_jurada']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/beneficiarios/' . $id_cliente . '/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = time() . '_' . bin2hex(random_bytes(4)) . '_' . basename($_FILES['declaracion_jurada']['name']);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['declaracion_jurada']['tmp_name'], $filePath)) {
                    $data['declaracion_jurada'] = str_replace('../', '', $filePath);
                }
            }
            
            $result = registrarBeneficiarioControlador($pdo, $data);
            
            if ($result['success']) {
                // Log
                $accion = $id_beneficiario ? 'ACTUALIZAR' : 'CREAR';
                logChange($pdo, $id_usuario_actual, $accion . '_BENEFICIARIO_CONTROLADOR', 
                         'clientes_beneficiario_controlador', $result['id_beneficiario'] ?? $id_beneficiario, 
                         null, $data);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => $result['message'],
                    'id_beneficiario' => $result['id_beneficiario']
                ]);
            } else {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => $result['message']]);
            }
            break;
            
        case 'DELETE':
            // Eliminar (desactivar) beneficiario
            $id_beneficiario = $_GET['id_beneficiario'] ?? null;
            
            if (!$id_beneficiario) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ID de beneficiario requerido']);
                exit;
            }
            
            // Obtener datos antes de eliminar para el log
            $stmt = $pdo->prepare("SELECT * FROM clientes_beneficiario_controlador WHERE id_beneficiario = ?");
            $stmt->execute([$id_beneficiario]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Desactivar (soft delete)
            $stmt = $pdo->prepare("UPDATE clientes_beneficiario_controlador SET id_status = 0 WHERE id_beneficiario = ?");
            $stmt->execute([$id_beneficiario]);
            
            // Log
            logChange($pdo, $id_usuario_actual, 'ELIMINAR_BENEFICIARIO_CONTROLADOR', 
                     'clientes_beneficiario_controlador', $id_beneficiario, $oldData, null);
            
            echo json_encode(['status' => 'success', 'message' => 'Beneficiario eliminado correctamente']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en beneficiario_controlador.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al procesar solicitud: ' . $e->getMessage()
    ]);
}
