<?php
/**
 * API Endpoint: Obtener Operaciones PLD
 * Lista todas las operaciones PLD registradas
 */

session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    $id_cliente = $_GET['id_cliente'] ?? null;
    $fecha_desde = $_GET['fecha_desde'] ?? null;
    $fecha_hasta = $_GET['fecha_hasta'] ?? null;
    
    $sql = "SELECT o.*, 
                   c.alias as cliente_alias,
                   COALESCE(cf.nombre, cm.razon_social, 'Sin nombre') as cliente_nombre,
                   cv.nombre as fraccion_nombre
            FROM operaciones_pld o
            LEFT JOIN clientes c ON o.id_cliente = c.id_cliente
            LEFT JOIN clientes_fisicas cf ON o.id_cliente = cf.id_cliente
            LEFT JOIN clientes_morales cm ON o.id_cliente = cm.id_cliente
            LEFT JOIN cat_vulnerables cv ON o.id_fraccion = cv.id_vulnerable
            WHERE o.id_status = 1";
    
    $params = [];
    
    if ($id_cliente) {
        $sql .= " AND o.id_cliente = ?";
        $params[] = $id_cliente;
    }
    
    if ($fecha_desde) {
        $sql .= " AND o.fecha_operacion >= ?";
        $params[] = $fecha_desde;
    }
    
    if ($fecha_hasta) {
        $sql .= " AND o.fecha_operacion <= ?";
        $params[] = $fecha_hasta;
    }
    
    $sql .= " ORDER BY o.fecha_operacion DESC, o.fecha_registro DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $operaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'operaciones' => $operaciones
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_operaciones_pld.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener operaciones: ' . $e->getMessage()
    ]);
}
