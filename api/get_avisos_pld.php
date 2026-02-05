<?php
/**
 * API Endpoint: Obtener Avisos PLD
 * Lista avisos pendientes, presentados y vencidos
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
    $estatus = $_GET['estatus'] ?? null; // pendiente, presentado, vencido
    $tipo_aviso = $_GET['tipo_aviso'] ?? ($_GET['tipo'] ?? null); // Compatibilidad con ambos nombres
    
    // Verificar si existe la columna datos_adicionales
    $stmt = $pdo->query("SHOW COLUMNS FROM avisos_pld LIKE 'datos_adicionales'");
    $tieneDatosAdicionales = $stmt->rowCount() > 0;
    
    if ($tieneDatosAdicionales) {
        $sql = "SELECT a.*, 
                       c.alias as cliente_alias,
                       COALESCE(cf.nombre, cm.razon_social, 'Sin nombre') as cliente_nombre,
                       CASE 
                           WHEN a.fecha_deadline < CURDATE() AND a.estatus = 'pendiente' THEN 'vencido'
                           ELSE a.estatus
                       END as estatus_real,
                       JSON_EXTRACT(a.datos_adicionales, '$.cantidad_operaciones') as cantidad_operaciones,
                       JSON_EXTRACT(a.datos_adicionales, '$.fecha_primera_operacion') as fecha_primera_operacion,
                       JSON_EXTRACT(a.datos_adicionales, '$.fecha_ultima_operacion') as fecha_ultima_operacion,
                       JSON_EXTRACT(a.datos_adicionales, '$.monto_acumulado_uma') as monto_acumulado_uma
                FROM avisos_pld a
                LEFT JOIN clientes c ON a.id_cliente = c.id_cliente
                LEFT JOIN clientes_fisicas cf ON a.id_cliente = cf.id_cliente
                LEFT JOIN clientes_morales cm ON a.id_cliente = cm.id_cliente
                WHERE a.id_status = 1";
    } else {
        $sql = "SELECT a.*, 
                       c.alias as cliente_alias,
                       COALESCE(cf.nombre, cm.razon_social, 'Sin nombre') as cliente_nombre,
                       CASE 
                           WHEN a.fecha_deadline < CURDATE() AND a.estatus = 'pendiente' THEN 'vencido'
                           ELSE a.estatus
                       END as estatus_real,
                       NULL as cantidad_operaciones,
                       NULL as fecha_primera_operacion,
                       NULL as fecha_ultima_operacion,
                       NULL as monto_acumulado_uma
                FROM avisos_pld a
                LEFT JOIN clientes c ON a.id_cliente = c.id_cliente
                LEFT JOIN clientes_fisicas cf ON a.id_cliente = cf.id_cliente
                LEFT JOIN clientes_morales cm ON a.id_cliente = cm.id_cliente
                WHERE a.id_status = 1";
    }
    
    $params = [];
    
    if ($id_cliente) {
        $sql .= " AND a.id_cliente = ?";
        $params[] = $id_cliente;
    }
    
    if ($estatus) {
        if ($estatus === 'vencido') {
            $sql .= " AND a.fecha_deadline < CURDATE() AND a.estatus = 'pendiente'";
        } else {
            $sql .= " AND a.estatus = ?";
            $params[] = $estatus;
        }
    }
    
    if ($tipo_aviso) {
        $sql .= " AND a.tipo_aviso = ?";
        $params[] = $tipo_aviso;
    }
    
    $sql .= " ORDER BY a.fecha_deadline ASC, a.fecha_operacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar datos adicionales si existen
    foreach ($avisos as &$aviso) {
        // Limpiar valores JSON_EXTRACT que pueden venir como strings con comillas
        if (isset($aviso['cantidad_operaciones']) && is_string($aviso['cantidad_operaciones'])) {
            $aviso['cantidad_operaciones'] = trim($aviso['cantidad_operaciones'], '"');
        }
        if (isset($aviso['fecha_primera_operacion']) && is_string($aviso['fecha_primera_operacion'])) {
            $aviso['fecha_primera_operacion'] = trim($aviso['fecha_primera_operacion'], '"');
        }
        if (isset($aviso['fecha_ultima_operacion']) && is_string($aviso['fecha_ultima_operacion'])) {
            $aviso['fecha_ultima_operacion'] = trim($aviso['fecha_ultima_operacion'], '"');
        }
        if (isset($aviso['monto_acumulado_uma']) && is_string($aviso['monto_acumulado_uma'])) {
            $aviso['monto_acumulado_uma'] = trim($aviso['monto_acumulado_uma'], '"');
        }
    }
    unset($aviso); // Liberar referencia
    
    // Contar por estatus
    $contadores = [
        'pendientes' => 0,
        'presentados' => 0,
        'vencidos' => 0,
        'total' => count($avisos)
    ];
    
    foreach ($avisos as $aviso) {
        $estatus_real = $aviso['estatus_real'];
        if ($estatus_real === 'pendiente') {
            $contadores['pendientes']++;
        } elseif ($estatus_real === 'presentado') {
            $contadores['presentados']++;
        } elseif ($estatus_real === 'vencido') {
            $contadores['vencidos']++;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'avisos' => $avisos,
        'contadores' => $contadores
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_avisos_pld.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener avisos: ' . $e->getMessage()
    ]);
}
