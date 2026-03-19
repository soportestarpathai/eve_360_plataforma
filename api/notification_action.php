<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Cookie');

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? 0;
$action = $data['action'] ?? ''; // 'dismiss' or 'snooze'
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$id = (int)$id;

if ($userId <= 0 || $id <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error']);
    exit;
}

try {
    $stmtAuth = $pdo->prepare("
        SELECT n.id_notificacion
        FROM notificaciones n
        LEFT JOIN clientes c ON c.id_cliente = n.id_cliente
        WHERE n.id_notificacion = ?
          AND n.id_usuario = ?
          AND (
                n.id_cliente IS NULL
                OR c.id_usuario = ?
              )
        LIMIT 1
    ");
    $stmtAuth->execute([$id, $userId, $userId]);
    if (!$stmtAuth->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }

    if ($action === 'dismiss') {
        // Hide permanently from UI
        $stmt = $pdo->prepare("UPDATE notificaciones SET estado = 'descartado' WHERE id_notificacion = ? AND id_usuario = ?");
        $stmt->execute([$id, $userId]);
    } 
    elseif ($action === 'snooze') {
        // Hide for 24 hours
        $stmt = $pdo->prepare("UPDATE notificaciones SET estado = 'pospuesto', snooze_until = DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE id_notificacion = ? AND id_usuario = ?");
        $stmt->execute([$id, $userId]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
        exit;
    }

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    error_log('notification_action: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno al actualizar la notificación.']);
}
?>
