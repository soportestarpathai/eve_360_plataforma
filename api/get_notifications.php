<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Cookie');

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$todas = isset($_GET['todas']) && ($_GET['todas'] === '1' || strtolower($_GET['todas']) === 'true');

try {
    if ($todas) {
        // Todas las notificaciones del usuario (pendientes, pospuestas, descartadas)
        $sql = "
            SELECT n.*, 
                   COALESCE(cf.nombre, cm.razon_social, 'Sin Nombre') as nombre_cliente
            FROM notificaciones n
            LEFT JOIN clientes c ON n.id_cliente = c.id_cliente
            LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
            LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
            WHERE n.id_usuario = ?
              AND (
                    n.id_cliente IS NULL
                    OR (c.id_usuario = ? AND COALESCE(c.id_status, 1) != 4)
                  )
            ORDER BY n.fecha_generacion DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
    } else {
        // Comportamiento original: solo activas (no descartadas, no snoozed)
        $sql = "
            SELECT n.*, 
                   COALESCE(cf.nombre, cm.razon_social, 'Sin Nombre') as nombre_cliente
            FROM notificaciones n
            LEFT JOIN clientes c ON n.id_cliente = c.id_cliente
            LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
            LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
            WHERE n.id_usuario = ? 
            AND (
                n.id_cliente IS NULL
                OR (c.id_usuario = ? AND COALESCE(c.id_status, 1) != 4)
            )
            AND n.estado != 'descartado' 
            AND (n.snooze_until IS NULL OR n.snooze_until <= NOW())
            ORDER BY n.fecha_generacion DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId]);
    }

    $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $notifs]);

} catch (Exception $e) {
    error_log('get_notifications: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno al obtener notificaciones.']);
}
?>
