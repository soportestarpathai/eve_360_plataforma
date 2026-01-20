<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? 0;
$action = $data['action'] ?? ''; // 'dismiss' or 'snooze'

if (!isset($_SESSION['user_id']) || !$id) {
    echo json_encode(['status' => 'error']);
    exit;
}

try {
    if ($action === 'dismiss') {
        // Hide permanently from UI
        $stmt = $pdo->prepare("UPDATE notificaciones SET estado = 'descartado' WHERE id_notificacion = ? AND id_usuario = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
    } 
    elseif ($action === 'snooze') {
        // Hide for 24 hours
        $stmt = $pdo->prepare("UPDATE notificaciones SET estado = 'pospuesto', snooze_until = DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE id_notificacion = ? AND id_usuario = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
    }

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>