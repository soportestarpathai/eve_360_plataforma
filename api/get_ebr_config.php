<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'get_factors';

try {
    if ($action === 'get_factors') {
        // Get list of all risk factors
        $stmt = $pdo->query("SELECT * FROM config_factores_riesgo ORDER BY id_factor");
        $factors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $factors]);
    } 
    elseif ($action === 'get_values') {
        $id_factor = $_GET['id_factor'];
        if (!$id_factor) throw new Exception("Missing Factor ID");

        // 1. Get factor details
        $stmtF = $pdo->prepare("SELECT * FROM config_factores_riesgo WHERE id_factor = ?");
        $stmtF->execute([$id_factor]);
        $factor = $stmtF->fetch(PDO::FETCH_ASSOC);

        // --- FIXED: Check for valid table configuration ---
        if (!$factor || empty($factor['tabla_catalogo']) || empty($factor['campo_clave']) || empty($factor['campo_nombre'])) {
            // Return empty list instead of crashing SQL
            echo json_encode([
                'status' => 'success', 
                'data' => [],
                'message' => 'Factor no configurado con tabla de catálogo.'
            ]);
            exit;
        }

        // 2. Dynamic Query
        $sql = "
            SELECT 
                c.{$factor['campo_clave']} as id_item,
                c.{$factor['campo_nombre']} as nombre_item,
                COALESCE(r.nivel_riesgo, 0) as nivel_riesgo
            FROM {$factor['tabla_catalogo']} c
            LEFT JOIN config_riesgo_valores r 
                ON r.id_valor_catalogo = c.{$factor['campo_clave']} 
                AND r.id_factor = ?
            ORDER BY c.{$factor['campo_nombre']} ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_factor]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'data' => $items]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>