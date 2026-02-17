<?php
/**
 * API: Obtener visitas de verificación (VAL-PLD-014)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_conservacion.php';

try {
    $estatus = $_GET['estatus'] ?? null;
    
    $sql = "SELECT * FROM visitas_verificacion_pld WHERE id_status = 1";
    $params = [];
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
