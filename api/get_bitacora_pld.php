<?php
/**
 * Obtiene registros de bitácora relacionados con PLD (avisos_pld, operaciones_pld).
 * Permite filtrar por tabla e id para ver historial de un registro específico.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    $tabla = $_GET['tabla'] ?? null;
    $id_afectado = isset($_GET['id']) ? (int)$_GET['id'] : null;

    $sql = "SELECT b.*, u.nombre as usuario_nombre
            FROM bitacora b
            LEFT JOIN usuarios u ON b.id_usuario = u.id_usuario
            WHERE b.tabla_afectada IN ('avisos_pld', 'operaciones_pld')";
    $params = [];

    if ($tabla && in_array($tabla, ['avisos_pld', 'operaciones_pld'])) {
        $sql .= " AND b.tabla_afectada = ?";
        $params[] = $tabla;
    }
    if ($id_afectado) {
        $sql .= " AND b.id_afectado = ?";
        $params[] = $id_afectado;
    }

    $sql .= " ORDER BY b.fecha DESC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tieneDeshacer = false;
    try {
        $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bitacora' AND COLUMN_NAME = 'deshacer_aplicado'");
        $tieneDeshacer = $chk && $chk->fetchColumn() > 0;
    } catch (Exception $e) { /* ignorar */ }

    echo json_encode([
        'status' => 'success',
        'registros' => $registros,
        'tiene_deshacer' => $tieneDeshacer
    ]);

} catch (Exception $e) {
    error_log("get_bitacora_pld: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
