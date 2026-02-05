<?php
/**
 * API Endpoint: Obtener Acumulaciones PLD
 * Lista acumulaciones registradas (VAL-PLD-009)
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
    $requiere_aviso = $_GET['requiere_aviso'] ?? null;
    
    $sql = "SELECT a.*, 
                   c.alias as cliente_alias,
                   COALESCE(cf.nombre, cm.razon_social, 'Sin nombre') as cliente_nombre,
                   cv.fraccion as fraccion_codigo,
                   cv.nombre as fraccion_nombre
            FROM operaciones_pld_acumulacion a
            LEFT JOIN clientes c ON a.id_cliente = c.id_cliente
            LEFT JOIN clientes_fisicas cf ON a.id_cliente = cf.id_cliente
            LEFT JOIN clientes_morales cm ON a.id_cliente = cm.id_cliente
            LEFT JOIN cat_vulnerables cv ON a.id_fraccion = cv.id_vulnerable
            WHERE a.id_status = 1";
    
    $params = [];
    
    if ($id_cliente) {
        $sql .= " AND a.id_cliente = ?";
        $params[] = $id_cliente;
    }
    
    if ($requiere_aviso !== null) {
        $sql .= " AND a.requiere_aviso = ?";
        $params[] = $requiere_aviso ? 1 : 0;
    }
    
    $sql .= " ORDER BY a.fecha_ultima_operacion DESC, a.monto_acumulado DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $acumulaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular días en ventana para cada acumulación
    foreach ($acumulaciones as &$acum) {
        $fechaPrimera = new DateTime($acum['fecha_primera_operacion']);
        $fechaUltima = new DateTime($acum['fecha_ultima_operacion']);
        $diasVentana = $fechaPrimera->diff($fechaUltima)->days;
        $acum['dias_ventana'] = $diasVentana;
        $acum['dias_restantes_ventana'] = max(0, 180 - $diasVentana); // 6 meses = 180 días
    }
    
    echo json_encode([
        'status' => 'success',
        'acumulaciones' => $acumulaciones,
        'total' => count($acumulaciones)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_acumulaciones_pld.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener acumulaciones: ' . $e->getMessage()
    ]);
}
