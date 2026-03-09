<?php
session_start();
require_once '../config/db.php';
require_once '../config/ebr_usuario_helper.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $id_usuario = (int)$_SESSION['user_id'];
    $ranges = getRangosRiesgoUsuario($pdo, $id_usuario);
    // Añadir id_rango simulado para compatibilidad con el frontend
    foreach ($ranges as $i => $r) {
        $ranges[$i]['id_rango'] = $i + 1;
    }
    echo json_encode(['status' => 'success', 'data' => $ranges]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>