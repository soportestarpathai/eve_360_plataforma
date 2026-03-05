<?php
/**
 * API: Obtener visitas de verificación (VAL-PLD-014)
 * Filtro por usuario: no admins solo ven sus visitas
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_conservacion.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    $estatus = $_GET['estatus'] ?? null;
    $id_usuario = $_SESSION['user_id'] ?? 0;
    $isAdmin = false;
    if ($id_usuario > 0) {
        $stmtAdmin = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
        $stmtAdmin->execute([$id_usuario]);
        $perm = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
        $isAdmin = $perm && (int)($perm['administracion'] ?? 0) > 0;
    }

    $sql = "SELECT * FROM visitas_verificacion_pld WHERE id_status = 1";
    $params = [];
    if (!$isAdmin && $id_usuario > 0) {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM visitas_verificacion_pld LIKE 'id_usuario'");
        if ($stmtCol->rowCount() > 0) {
            $sql .= " AND id_usuario = ?";
            $params[] = $id_usuario;
        }
    }
    if ($estatus) {
        $sql .= " AND estatus = ?";
        $params[] = $estatus;
    }
    $sql .= " ORDER BY fecha_visita DESC, id_visita DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $visitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($visitas as &$v) {
        if (!empty($v['expedientes_solicitados'])) {
            $v['expedientes_solicitados_ids'] = json_decode($v['expedientes_solicitados'], true);
        }
    }
    unset($v);
    
    echo json_encode([
        'status' => 'success',
        'visitas' => $visitas
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    error_log("get_visitas_verificacion.php: " . $e->getMessage());
}
