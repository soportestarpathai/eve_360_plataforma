<?php
// 1. Prevent "Invisible Whitespace" errors
ob_start();
session_start();
require_once '../config/db.php';
ob_end_clean(); // Discard any prior output (spaces, newlines)

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    // 2. Updated Query: Using 'cat_status'
    $sql = "
        SELECT 
            c.id_cliente,
            c.no_contrato,
            c.fecha_apertura,
            c.nivel_riesgo,
            c.id_status,
            COALESCE(s.nombre, 'Desconocido') AS status_nombre,
            
            -- Client Name Logic
            CASE 
                WHEN c.alias IS NOT NULL AND c.alias != '' THEN c.alias
                WHEN cf.nombre IS NOT NULL THEN CONCAT(cf.nombre, ' ', cf.apellido_paterno, ' ', COALESCE(cf.apellido_materno, ''))
                WHEN cm.razon_social IS NOT NULL THEN cm.razon_social
                ELSE 'Sin Nombre'
            END as nombre_cliente,

            -- RFC Logic
            CASE 
                WHEN cf.tax_id IS NOT NULL THEN cf.tax_id
                WHEN cm.tax_id IS NOT NULL THEN cm.tax_id
                ELSE 'N/A'
            END as rfc

        FROM clientes c
        -- FIXED: Table name is 'cat_status', not 'cat_status_cliente'
        LEFT JOIN cat_status s ON c.id_status = s.id_status
        LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
        LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
        WHERE c.id_status != 0 
        ORDER BY c.id_cliente DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($clients);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>