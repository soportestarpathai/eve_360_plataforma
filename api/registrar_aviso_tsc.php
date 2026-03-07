<?php
/**
 * API: Registrar Aviso TSC (Tarjetas de Servicios de Crédito) - Fracción II
 * Genera XML según instructivo TSC, almacena en operaciones_pld
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/pld_middleware.php';
require_once __DIR__ . '/../config/pld_fraccion_ii.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

requirePLDHabilitado($pdo, true);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['id_cliente'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'JSON con id_cliente e informe requerido']);
    exit;
}

if (empty($data['informe']) || !is_array($data['informe'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Estructura informe requerida']);
    exit;
}

$id_cliente = (int)($data['id_cliente'] ?? 0);

// Obtener monto desde detalle_operaciones TSC (monto_gasto)
$monto = 0;
$fecha_periodo = null;
if (isset($data['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0])) {
    $op = $data['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0];
    if (isset($op['monto_gasto'])) {
        $monto = floatval($op['monto_gasto']);
    }
    if (isset($op['fecha_periodo']) && strlen($op['fecha_periodo']) === 6) {
        $fecha_periodo = $op['fecha_periodo'];
    }
}

// Fecha operación: último día del periodo (AAAAMM) o hoy
$fecha_operacion = date('Y-m-d');
if ($fecha_periodo) {
    $y = substr($fecha_periodo, 0, 4);
    $m = substr($fecha_periodo, 4, 2);
    if (checkdate((int)$m, 1, (int)$y)) {
        $fecha_operacion = date('Y-m-t', strtotime("$y-$m-01"));
    }
}

$id_fraccion = null;
if (function_exists('getIdVulnerableFraccionII')) {
    $id_fraccion = getIdVulnerableFraccionII($pdo);
}

$operacionData = [
    'id_cliente' => $id_cliente,
    'monto' => $monto > 0 ? $monto : 1,
    'fecha_operacion' => $fecha_operacion,
    'id_fraccion' => $id_fraccion,
    'tipo_operacion' => 'TSC',
    'es_sospechosa' => $data['es_sospechosa'] ?? 0,
    'fecha_conocimiento_sospecha' => $data['fecha_conocimiento_sospecha'] ?? null,
    'match_listas_restringidas' => $data['match_listas_restringidas'] ?? 0,
    'fecha_conocimiento_match' => $data['fecha_conocimiento_match'] ?? null,
];

$result = registrarOperacionPLD($pdo, $operacionData);

if (!($result['success'] ?? false)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $result['message'] ?? 'Error al registrar aviso']);
    exit;
}

$id_operacion = $result['id_operacion'];

$xml = '';
$xmlNombre = '';
$xmlErrors = [];
if (file_exists(__DIR__ . '/../config/tsc_xml_helper.php')) {
    require_once __DIR__ . '/../config/tsc_xml_helper.php';
    $gen = generateTSCXml($data);
    $xml = $gen['xml'] ?? '';
    $xmlErrors = $gen['errors'] ?? [];
    if (!empty($xmlErrors) && function_exists('logChange')) {
        logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_TSC', 'operaciones_pld', $id_operacion, null, ['xml_errors' => $xmlErrors]);
    }
}

if ($xml) {
    $xmlNombre = 'tsc_' . date('Ymd_His') . '_op' . $id_operacion . '.xml';
    try {
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $id_operacion]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'xml_contenido') !== false) {
            // Columnas no existen
        }
    }
}

if (function_exists('logChange')) {
    logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_TSC', 'operaciones_pld', $id_operacion, null, $operacionData);
}

$resp = [
    'status' => 'success',
    'message' => 'Aviso TSC registrado correctamente.',
    'id_operacion' => $id_operacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => $result['requiere_aviso'] ?? false,
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => !empty($xml),
];
if (!empty($xmlErrors)) {
    $resp['xml_advertencia'] = implode('; ', $xmlErrors);
}
echo json_encode($resp);
