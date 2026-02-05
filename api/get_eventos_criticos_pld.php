<?php
/**
 * API: Obtener eventos críticos PLD (VAL-PLD-014)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'eventos_criticos_pld'");
    if ($stmt->rowCount() === 0) {
        echo json_encode(['status' => 'success', 'eventos' => [], 'tabla_no_existe' => true]);
        exit;
    }
    
    $limit = (int)($_GET['limit'] ?? 100);
    $sql = "SELECT * FROM eventos_criticos_pld WHERE id_status = 1 ORDER BY fecha_evento DESC LIMIT " . max(1, min(500, $limit));
    $stmt = $pdo->query($sql);
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
