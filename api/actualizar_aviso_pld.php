<?php
/**
 * API Endpoint: Actualizar Aviso PLD
 * Actualiza el estatus, folio SPPLD y fecha de presentación de un aviso
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/bitacora.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id_usuario_actual = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    $data = $_POST;
}

try {
    $id_aviso = $data['id_aviso'] ?? null;
    $folio_sppld = $data['folio_sppld'] ?? null;
    $fecha_presentacion = $data['fecha_presentacion'] ?? null;
    $estatus = $data['estatus'] ?? 'pendiente';
    
    if (!$id_aviso) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID de aviso requerido']);
        exit;
    }
    
    // Obtener aviso actual para el log
    $stmt = $pdo->prepare("SELECT * FROM avisos_pld WHERE id_aviso = ?");
    $stmt->execute([$id_aviso]);
    $oldAviso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldAviso) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Aviso no encontrado']);
        exit;
    }
    
    // Actualizar aviso
    $stmt = $pdo->prepare("UPDATE avisos_pld 
                           SET folio_sppld = ?, 
                               fecha_presentacion = ?, 
                               estatus = ?
                           WHERE id_aviso = ?");
    
    $stmt->execute([
        $folio_sppld ?: $oldAviso['folio_sppld'],
        $fecha_presentacion ?: $oldAviso['fecha_presentacion'],
        $estatus,
        $id_aviso
    ]);
    
    // Log
    $newAviso = $oldAviso;
    $newAviso['folio_sppld'] = $folio_sppld ?: $oldAviso['folio_sppld'];
    $newAviso['fecha_presentacion'] = $fecha_presentacion ?: $oldAviso['fecha_presentacion'];
    $newAviso['estatus'] = $estatus;
    
    logChange($pdo, $id_usuario_actual, 'ACTUALIZAR_AVISO_PLD', 
             'avisos_pld', $id_aviso, $oldAviso, $newAviso);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Aviso actualizado correctamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en actualizar_aviso_pld.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al actualizar aviso: ' . $e->getMessage()
    ]);
}
