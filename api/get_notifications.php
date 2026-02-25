<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
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
              AND (n.id_cliente IS NULL OR COALESCE(c.id_status, 1) != 4)
            ORDER BY n.fecha_generacion DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
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
            AND (n.id_cliente IS NULL OR COALESCE(c.id_status, 1) != 4)
            AND n.estado != 'descartado' 
            AND (n.snooze_until IS NULL OR n.snooze_until <= NOW())
            ORDER BY n.fecha_generacion DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
    }

    $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $notifs]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
