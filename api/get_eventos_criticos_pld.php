<?php
/**
 * API: Obtener eventos críticos PLD (VAL-PLD-014)
 * Filtro por usuario: no admins solo ven eventos de sus visitas (id_usuario_registro) o visitas (id_usuario)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'eventos_criticos_pld'");
    if ($stmt->rowCount() === 0) {
        echo json_encode(['status' => 'success', 'eventos' => [], 'tabla_no_existe' => true]);
        exit;
    }

    $id_usuario = $_SESSION['user_id'] ?? 0;
    $isAdmin = false;
    if ($id_usuario > 0) {
        $stmtAdmin = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
        $stmtAdmin->execute([$id_usuario]);
        $perm = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
        $isAdmin = $perm && (int)($perm['administracion'] ?? 0) > 0;
    }

    $limit = (int)($_GET['limit'] ?? 100);
    $limit = max(1, min(500, $limit));
    $sql = "SELECT ec.* FROM eventos_criticos_pld ec WHERE ec.id_status = 1";
    $params = [];
    if (!$isAdmin && $id_usuario > 0) {
        $stmtCol = $pdo->query("SHOW COLUMNS FROM visitas_verificacion_pld LIKE 'id_usuario'");
        if ($stmtCol->rowCount() > 0) {
            $sql = "SELECT ec.* FROM eventos_criticos_pld ec
                    LEFT JOIN visitas_verificacion_pld v ON ec.id_visita = v.id_visita
                    WHERE ec.id_status = 1 AND (ec.id_usuario_registro = ? OR v.id_usuario = ?)";
            $params = [$id_usuario, $id_usuario];
        } else {
            $sql .= " AND ec.id_usuario_registro = ?";
            $params = [$id_usuario];
        }
    }
    $sql .= " ORDER BY ec.fecha_evento DESC LIMIT " . $limit;
    $stmt = empty($params) ? $pdo->query($sql) : $pdo->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    }
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($eventos as &$e) {
        if (!empty($e['detalle_json'])) {
            $e['detalle'] = json_decode($e['detalle_json'], true);
        }
    }
    unset($e);
    
    echo json_encode([
        'status' => 'success',
        'eventos' => $eventos
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    error_log("get_eventos_criticos_pld.php: " . $e->getMessage());
}
