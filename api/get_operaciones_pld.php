<?php
/**
 * API Endpoint: Obtener Operaciones PLD
 * Lista todas las operaciones PLD registradas
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_permisos.php';
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
    $incluirHistorico = (isset($_GET['historico']) && $_GET['historico'] === '1');
    
    $tieneXmlCols = false;
    try {
        $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operaciones_pld' AND COLUMN_NAME = 'xml_contenido'");
        $tieneXmlCols = ($chk->fetchColumn() > 0);
    } catch (Exception $e) { /* ignorar */ }
    
    $selXml = $tieneXmlCols
        ? "o.xml_nombre_archivo, CASE WHEN o.xml_contenido IS NOT NULL AND LENGTH(o.xml_contenido) > 0 THEN 1 ELSE 0 END as tiene_xml,"
        : "NULL as xml_nombre_archivo, 0 as tiene_xml,";
    
    $sql = "SELECT o.id_operacion, o.id_cliente, o.id_fraccion, o.id_status, o.tipo_operacion, o.monto, o.monto_uma,
                   o.fecha_operacion, o.fecha_registro, o.es_sospechosa, o.match_listas_restringidas,
                   o.requiere_aviso, o.tipo_aviso, o.fecha_deadline_aviso, o.id_aviso_generado,
                   $selXml
                   c.alias as cliente_alias,
                   COALESCE(cf.nombre, cm.razon_social, 'Sin nombre') as cliente_nombre,
                   cv.nombre as fraccion_nombre,
                   a.folio_sppld, a.fecha_presentacion
            FROM operaciones_pld o
            LEFT JOIN clientes c ON o.id_cliente = c.id_cliente
            LEFT JOIN clientes_fisicas cf ON o.id_cliente = cf.id_cliente
            LEFT JOIN clientes_morales cm ON o.id_cliente = cm.id_cliente
            LEFT JOIN cat_vulnerables cv ON o.id_fraccion = cv.id_vulnerable
            LEFT JOIN avisos_pld a ON o.id_aviso_generado = a.id_aviso
            WHERE " . ($incluirHistorico ? "(o.id_status = 1 OR o.id_status = 0)" : "o.id_status = 1") . " AND COALESCE(c.id_status, 1) != 4";
    
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

    $id_usuario = $_SESSION['user_id'] ?? 0;
    foreach ($operaciones as &$op) {
        $op['puede_modificar'] = canModifyPLD($pdo, $id_usuario, (int)($op['id_cliente'] ?? 0));
    }
    unset($op);
    
    echo json_encode([
        'status' => 'success',
        'operaciones' => $operaciones
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_operaciones_pld.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener transacciones: ' . $e->getMessage()
    ]);
}
