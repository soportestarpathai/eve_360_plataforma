<?php
session_start();
require_once '../config/db.php';
require_once '../config/pld_expediente.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id_cliente = $_POST['id_cliente'] ?? $_GET['id_cliente'] ?? null;

if (!$id_cliente) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de cliente requerido']);
    exit;
}

try {
    $result = actualizarFechaExpediente($pdo, $id_cliente);
    
    if ($result) {
        // Revalidar para obtener estado actualizado
        $resultActualizacion = validateActualizacionExpediente($pdo, $id_cliente);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Fecha de expediente actualizada correctamente',
            'fecha_actualizada' => date('Y-m-d'),
            'actualizacion' => $resultActualizacion
        ]);
    } else {
        throw new Exception('Error al actualizar fecha');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
