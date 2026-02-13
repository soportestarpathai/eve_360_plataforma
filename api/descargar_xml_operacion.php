<?php
/**
 * API: Descargar XML de operación PLD (DIN)
 */
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain');
    echo 'No autorizado';
    exit;
}

$id_operacion = (int)($_GET['id'] ?? 0);
if (!$id_operacion) {
    http_response_code(400);
    echo 'ID requerido';
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT xml_contenido, xml_nombre_archivo FROM operaciones_pld WHERE id_operacion = ? AND id_status = 1");
    $stmt->execute([$id_operacion]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['xml_contenido'])) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'XML no encontrado para esta operación']);
        exit;
    }

    $filename = $row['xml_nombre_archivo'] ?: ('din_op' . $id_operacion . '.xml');

    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $row['xml_contenido'];

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Error: ' . $e->getMessage();
}
