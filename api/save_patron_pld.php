<?php
/**
 * API: Guardar configuración Padrón PLD
 * Soporta guardado global (config_empresa) o por usuario (config_empresa_usuario)
 */
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : $_POST;
    $data = $data ?: [];

    $id_usuario = isset($data['id_usuario']) ? (int)$data['id_usuario'] : 0;
    $folio = isset($data['folio']) ? trim((string)$data['folio']) : null;
    $estatus = isset($data['estatus']) ? trim((string)$data['estatus']) : null;
    $fracciones = isset($data['fracciones']) ? $data['fracciones'] : null;

    if (is_array($fracciones)) {
        $fracciones = json_encode($fracciones);
    } elseif (is_string($fracciones) && $fracciones !== '' && substr($fracciones, 0, 1) !== '[') {
        $arr = array_map('trim', explode(',', $fracciones));
        $fracciones = json_encode(array_filter($arr));
    }

    if ($id_usuario > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO config_empresa_usuario 
            (id_usuario, folio_patron_pld, estatus_patron_pld, fecha_revalidacion_patron, fracciones_activas)
            VALUES (?, ?, ?, CURDATE(), ?)
            ON DUPLICATE KEY UPDATE
            folio_patron_pld = VALUES(folio_patron_pld),
            estatus_patron_pld = VALUES(estatus_patron_pld),
            fecha_revalidacion_patron = CURDATE(),
            fracciones_activas = VALUES(fracciones_activas),
            updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$id_usuario, $folio ?: null, $estatus ?: null, $fracciones ?: null]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE config_empresa SET 
            folio_patron_pld = ?, estatus_patron_pld = ?, fecha_revalidacion_patron = CURDATE(), fracciones_activas = ?
            WHERE id_config = 1
        ");
        $stmt->execute([$folio ?: null, $estatus ?: null, $fracciones ?: null]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Configuración del padrón PLD guardada correctamente.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al guardar: ' . $e->getMessage()
    ]);
}
