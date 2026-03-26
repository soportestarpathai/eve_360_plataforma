<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_expediente.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = (int)$_SESSION['user_id'];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if ($limit < 1) $limit = 1;
    if ($limit > 30) $limit = 30;

    $baseWhere = "
        c.id_status != 4
        AND (
            c.id_status = 2
            OR COALESCE(c.identificacion_incompleta, 0) = 1
            OR COALESCE(c.expediente_completo, 0) = 0
        )
    ";

    // Aislar siempre por usuario en sesión para evitar cruces entre cuentas.
    $params = [$userId];
    $baseWhere .= " AND c.id_usuario = ?";

    $sqlTotal = "SELECT COUNT(*) FROM clientes c WHERE {$baseWhere}";
    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute($params);
    $totalPendientes = (int)$stmtTotal->fetchColumn();

    $sqlList = "
        SELECT
            c.id_cliente,
            c.no_contrato,
            c.id_status,
            COALESCE(c.identificacion_incompleta, 0) AS identificacion_incompleta,
            COALESCE(c.expediente_completo, 0) AS expediente_completo,
            CASE
                WHEN c.alias IS NOT NULL AND c.alias != '' THEN c.alias
                WHEN cf.nombre IS NOT NULL THEN CONCAT(cf.nombre, ' ', cf.apellido_paterno, ' ', COALESCE(cf.apellido_materno, ''))
                WHEN cm.razon_social IS NOT NULL THEN cm.razon_social
                ELSE 'Sin nombre'
            END AS nombre_cliente
        FROM clientes c
        LEFT JOIN clientes_fisicas cf ON cf.id_cliente = c.id_cliente
        LEFT JOIN clientes_morales cm ON cm.id_cliente = c.id_cliente
        WHERE {$baseWhere}
        ORDER BY c.id_cliente DESC
        LIMIT {$limit}
    ";

    $stmtList = $pdo->prepare($sqlList);
    $stmtList->execute($params);
    $rows = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    $pendientes = [];
    foreach ($rows as $row) {
        $idCliente = (int)($row['id_cliente'] ?? 0);
        $exp = validateExpedienteCompleto($pdo, $idCliente, false);
        $faltantes = is_array($exp['faltantes'] ?? null) ? $exp['faltantes'] : [];
        $pendientes[] = [
            'id_cliente' => $idCliente,
            'no_contrato' => (string)($row['no_contrato'] ?? ''),
            'nombre_cliente' => trim((string)($row['nombre_cliente'] ?? 'Sin nombre')),
            'id_status' => (int)($row['id_status'] ?? 0),
            'identificacion_incompleta' => (int)($row['identificacion_incompleta'] ?? 0),
            'expediente_completo' => (int)($row['expediente_completo'] ?? 0),
            'faltantes' => $faltantes,
        ];
    }

    echo json_encode([
        'status' => 'success',
        'total' => $totalPendientes,
        'pendientes' => $pendientes
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno al obtener clientes pendientes.'
    ]);
    error_log('get_pending_clients_alert.php: ' . $e->getMessage());
}
