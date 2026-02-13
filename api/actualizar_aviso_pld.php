<?php
/**
 * API Endpoint: Actualizar Aviso PLD
 * Actualiza el estatus, folio SPPLD y fecha de presentación de un aviso.
 * Requiere permiso: administrador o responsable PLD del cliente.
 * Bitácora: permite auditoría y deshacer.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/pld_permisos.php';
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
    $stmt = $pdo->prepare("SELECT * FROM avisos_pld WHERE id_aviso = ? AND id_status = 1");
    $stmt->execute([$id_aviso]);
    $oldAviso = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldAviso) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Aviso no encontrado']);
        exit;
    }

    // Validar permiso: admin o responsable PLD del cliente
    if (!canModifyPLD($pdo, $id_usuario_actual, (int)$oldAviso['id_cliente'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => mensajeSinPermisoPLD()]);
        exit;
    }
    
    // Actualizar aviso (solo si sigue activo, evita modificar registros dados de baja)
    $stmt = $pdo->prepare("UPDATE avisos_pld 
                           SET folio_sppld = ?, 
                               fecha_presentacion = ?, 
                               estatus = ?
                           WHERE id_aviso = ? AND id_status = 1");
    
    // Usar array_key_exists para permitir vaciar folio/fecha (?: trataría '' como falsy)
    $folioFinal = array_key_exists('folio_sppld', $data) ? ($data['folio_sppld'] ?? '') : ($oldAviso['folio_sppld'] ?? '');
    $fechaFinal = array_key_exists('fecha_presentacion', $data) ? ($data['fecha_presentacion'] ?? '') : ($oldAviso['fecha_presentacion'] ?? '');
    
    $stmt->execute([
        $folioFinal,
        $fechaFinal,
        $estatus,
        $id_aviso
    ]);

    // rowCount()=0 puede significar: aviso eliminado, o valores idénticos (sin cambios).
    // Re-verificar: si sigue activo → éxito; si no → fue dado de baja.
    if ($stmt->rowCount() === 0) {
        $chk = $pdo->prepare("SELECT 1 FROM avisos_pld WHERE id_aviso = ? AND id_status = 1");
        $chk->execute([$id_aviso]);
        if (!$chk->fetch()) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'El aviso ya no está activo o fue dado de baja']);
            exit;
        }
        // Aviso sigue activo → no hubo cambios en los campos; éxito sin bitácora
    } else {
        $newAviso = $oldAviso;
        $newAviso['folio_sppld'] = $folioFinal;
        $newAviso['fecha_presentacion'] = $fechaFinal;
        $newAviso['estatus'] = $estatus;
        logChange($pdo, $id_usuario_actual, 'ACTUALIZAR_AVISO_PLD',
                 'avisos_pld', $id_aviso, $oldAviso, $newAviso);
    }

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
