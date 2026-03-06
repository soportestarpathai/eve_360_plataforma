<?php
/**
 * API: Eliminar visita de verificación (VAL-PLD-014) - soft delete
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/bitacora.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    $id_usuario = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id_visita = (int)($input['id_visita'] ?? 0);

    if ($id_visita <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID de visita inválido']);
        exit;
    }

    $stmtAdmin = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
    $stmtAdmin->execute([$id_usuario]);
    $perm = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    $isAdmin = $perm && (int)($perm['administracion'] ?? 0) > 0;

    $stmtCol = $pdo->query("SHOW COLUMNS FROM visitas_verificacion_pld LIKE 'id_usuario'");
    $tieneIdUsuario = $stmtCol->rowCount() > 0;

    if (!$isAdmin) {
        if (!$tieneIdUsuario) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No se puede verificar la propiedad de la visita. Contacte al administrador.']);
            exit;
        }
        $chk = $pdo->prepare("SELECT id_visita FROM visitas_verificacion_pld WHERE id_visita = ? AND id_usuario = ? AND id_status = 1");
        $chk->execute([$id_visita, $id_usuario]);
        if (!$chk->fetch()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No puede eliminar esta visita']);
            exit;
        }
    } else {
        $chk = $pdo->prepare("SELECT id_visita FROM visitas_verificacion_pld WHERE id_visita = ? AND id_status = 1");
        $chk->execute([$id_visita]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Visita no encontrada']);
            exit;
        }
    }

    $stmt = $pdo->prepare("UPDATE visitas_verificacion_pld SET id_status = 0 WHERE id_visita = ?");
    $stmt->execute([$id_visita]);

    logChange($pdo, (int)$id_usuario, 'ELIMINAR_VISITA_VERIFICACION', 'visitas_verificacion_pld', $id_visita, null, null);

    echo json_encode(['status' => 'success', 'message' => 'Visita eliminada']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    error_log("eliminar_visita_verificacion.php: " . $e->getMessage());
}
