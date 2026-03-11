<?php
/**
 * API: Registrar Aviso SPR (Servicios Profesionales) - Fracción XII
 * Genera XML según instructivo SPR, almacena en operaciones_pld
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/pld_middleware.php';
require_once __DIR__ . '/../config/pld_fraccion_xi.php';
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

$monto = 0;
$fecha_operacion = date('Y-m-d');
$detalle = $data['informe'][0]['aviso'][0]['detalle_operaciones'] ?? [];
if (isset($detalle['datos_operacion']) && is_array($detalle['datos_operacion'])) {
    foreach ($detalle['datos_operacion'] as $op) {
        $fin = $op['datos_operacion_financiera'] ?? [];
        if (is_array($fin)) {
            foreach ($fin as $f) {
                $m = floatval($f['monto_operacion'] ?? 0);
                if ($m > 0) $monto += $m;
            }
        }
        if (!empty($op['fecha_operacion'])) {
            $fo = $op['fecha_operacion'];
            if (strlen($fo) >= 8) {
                $fecha_operacion = substr($fo, 0, 4) . '-' . substr($fo, 4, 2) . '-' . substr($fo, 6, 2);
            }
        }
    }
}

if ($monto <= 0) $monto = 1;

$id_fraccion = null;
if (function_exists('getIdVulnerableFraccionXI')) {
    $id_fraccion = getIdVulnerableFraccionXI($pdo);
}

// Extraer subfracción XI (tipo_actividad) del informe
$subfraccion_xi = null;
$datosOps = $detalle['datos_operacion'] ?? [];
if (is_array($datosOps) && isset($datosOps[0]['tipo_actividad']) && is_array($datosOps[0]['tipo_actividad'])) {
    $keys = array_keys($datosOps[0]['tipo_actividad']);
    if (!empty($keys)) {
        $subfraccion_xi = $keys[0]; // compra_venta_inmuebles, administracion_recursos, etc.
    }
}

$operacionData = [
    'id_cliente' => $id_cliente,
    'monto' => $monto,
    'fecha_operacion' => $fecha_operacion,
    'id_fraccion' => $id_fraccion,
    'tipo_operacion' => $subfraccion_xi ? "SPR:{$subfraccion_xi}" : 'SPR',
    'subfraccion_xi' => $subfraccion_xi,
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
if (file_exists(__DIR__ . '/../config/spr_xml_helper.php')) {
    require_once __DIR__ . '/../config/spr_xml_helper.php';
    $claveSO = $data['informe'][0]['sujeto_obligado']['clave_sujeto_obligado'] ?? '';
    if (!function_exists('sprValidarClaveSO') || !sprValidarClaveSO($claveSO)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Clave Sujeto Obligado debe tener formato RFC: 3-4 letras + 6 dígitos + 3 caracteres (ej: ABC010203AB1). No usar folio o texto libre.'
        ]);
        exit;
    }
    $gen = generateSPRXml($data);
    $xml = $gen['xml'] ?? '';
    $xmlErrors = $gen['errors'] ?? [];
    if (!empty($xmlErrors) && function_exists('logChange')) {
        logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_SPR', 'operaciones_pld', $id_operacion, null, ['xml_errors' => $xmlErrors]);
    }
}

if ($xml) {
    $xmlNombre = 'spr_' . date('Ymd_His') . '_op' . $id_operacion . '.xml';
    try {
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $id_operacion]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'xml_contenido') === false) {
            error_log('SPR xml save: ' . $e->getMessage());
        }
    }
}

if (function_exists('logChange')) {
    logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_SPR', 'operaciones_pld', $id_operacion, null, $operacionData);
}

$resp = [
    'status' => 'success',
    'message' => 'Aviso SPR registrado correctamente.',
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
