<?php
/**
 * API: Registrar Aviso Fracción II (TSC/TPP/TDR)
 * Genera XML según la subfracción seleccionada y almacena en operaciones_pld
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _tscJsonError($msg, $code = 500) {
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

function _fr2TableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function _fr2SepomexCpExists(PDO $pdo, string $cp): bool {
    if (!preg_match('/^\d{5}$/', $cp)) return false;
    if (!_fr2TableExists($pdo, 'cat_sepomex')) return true;
    $stmt = $pdo->prepare("SELECT 1 FROM cat_sepomex WHERE codigo_postal = ? LIMIT 1");
    $stmt->execute([$cp]);
    return (bool)$stmt->fetchColumn();
}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_ii.php';
} catch (Throwable $e) {
    error_log('registrar_aviso_tsc init: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    _tscJsonError('Error al inicializar: ' . $e->getMessage(), 500);
}
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!function_exists('userCanAccessTSC') || !userCanAccessTSC($pdo, $userId)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Sin permiso para registrar avisos TSC']);
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
$subfraccionII = trim((string)($data['subfraccion_ii'] ?? ''));
if ($subfraccionII === '' && isset($data['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0]['subfraccion_ii'])) {
    $subfraccionII = trim((string)$data['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0]['subfraccion_ii']);
}
$subfraccionMeta = function_exists('getSubfraccionIIData') ? getSubfraccionIIData($subfraccionII) : null;
if (!is_array($subfraccionMeta)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Subfracción II inválida o no enviada']);
    exit;
}
$detalleTipoII = function_exists('getSubfraccionIIDetalleTipo') ? getSubfraccionIIDetalleTipo($subfraccionII) : '';
$isTPP = ($detalleTipoII === 'tpp' || $subfraccionII === 'prepago_cupones');
$isTDR = ($detalleTipoII === 'tdr' || $subfraccionII === 'devolucion_recompensas');

$subfraccionesPermitidas = function_exists('getSubfraccionesIIActivas') ? getSubfraccionesIIActivas($pdo, $userId) : [];
if (is_array($subfraccionesPermitidas) && !empty($subfraccionesPermitidas) && !in_array($subfraccionII, $subfraccionesPermitidas, true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Subfracción II no autorizada para su usuario']);
    exit;
}

try {

// Obtener monto/fecha desde detalle_operaciones según subfracción II
$monto = 0;
$fecha_periodo = null;
$tipoOperacionDetalle = '';
$fechaOperacionRaw = '';
$codigoPostalOperacion = '';
if (isset($data['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0])) {
    $op = $data['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0];
    if (isset($op['tipo_operacion'])) {
        $tipoOperacionDetalle = preg_replace('/[^0-9]/', '', (string)$op['tipo_operacion']);
    }

    if ($isTPP || $isTDR) {
        $fechaOperacionRaw = preg_replace('/[^0-9]/', '', (string)($op['fecha_operacion'] ?? ''));
        $codigoPostalOperacion = preg_replace('/[^0-9]/', '', (string)($op['codigo_postal'] ?? ''));
        $liqRaw = $op['datos_liquidacion'] ?? [];
        $liqList = (is_array($liqRaw) && isset($liqRaw['monto_operacion'])) ? [$liqRaw] : (is_array($liqRaw) ? $liqRaw : []);
        foreach ($liqList as $liq) {
            if (!is_array($liq)) continue;
            $monto += floatval($liq['monto_operacion'] ?? 0);
        }
    } else {
        if (isset($op['monto_gasto'])) {
            $monto = floatval($op['monto_gasto']);
        }
        if (isset($op['fecha_periodo']) && strlen((string)$op['fecha_periodo']) === 6) {
            $fecha_periodo = (string)$op['fecha_periodo'];
        }
    }
}
if ($monto <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Monto inválido: debe ser mayor a 0']);
    exit;
}

// Fecha operación según subfracción
$fecha_operacion = date('Y-m-d');
if ($isTPP || $isTDR) {
    if (strlen($fechaOperacionRaw) === 8) {
        $y = (int)substr($fechaOperacionRaw, 0, 4);
        $m = (int)substr($fechaOperacionRaw, 4, 2);
        $d = (int)substr($fechaOperacionRaw, 6, 2);
        if (checkdate($m, $d, $y)) {
            $fecha_operacion = sprintf('%04d-%02d-%02d', $y, $m, $d);
            if ($fecha_operacion < '2013-09-01') {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Fecha de operación Fracción II debe ser >= 2013-09-01']);
                exit;
            }
            if ($fecha_operacion > date('Y-m-d')) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Fecha de operación Fracción II no puede ser futura']);
                exit;
            }
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Fecha de operación Fracción II inválida']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Fecha de operación Fracción II obligatoria (AAAAMMDD)']);
        exit;
    }
    if (!preg_match('/^\d{5}$/', $codigoPostalOperacion)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Código postal de operación Fracción II inválido (5 dígitos)']);
        exit;
    }
    if (!_fr2SepomexCpExists($pdo, $codigoPostalOperacion)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Código postal de operación Fracción II no existe en SEPOMEX']);
        exit;
    }
} else {
    // TSC: último día del periodo (AAAAMM)
    if ($fecha_periodo) {
        $y = substr($fecha_periodo, 0, 4);
        $m = substr($fecha_periodo, 4, 2);
        if (checkdate((int)$m, 1, (int)$y)) {
            $fecha_operacion = date('Y-m-t', strtotime("$y-$m-01"));
        }
    }
}

$id_fraccion = null;
if (function_exists('getIdVulnerableFraccionII')) {
    $id_fraccion = getIdVulnerableFraccionII($pdo);
}

$operacionData = [
    'id_cliente' => $id_cliente,
    'monto' => $monto,
    'fecha_operacion' => $fecha_operacion,
    'id_fraccion' => $id_fraccion,
    'tipo_operacion' => 'II:' . $subfraccionII . ':' . ($tipoOperacionDetalle ?: '0000'),
    'es_sospechosa' => $data['es_sospechosa'] ?? 0,
    'fecha_conocimiento_sospecha' => $data['fecha_conocimiento_sospecha'] ?? null,
    'match_listas_restringidas' => $data['match_listas_restringidas'] ?? 0,
    'fecha_conocimiento_match' => $data['fecha_conocimiento_match'] ?? null,
    'umbral_identificacion_uma_override' => function_exists('getUmbralIdentificacionIIPorSubfraccion') ? getUmbralIdentificacionIIPorSubfraccion($subfraccionII) : null,
    'umbral_aviso_uma_override' => function_exists('getUmbralAvisoIIPorSubfraccion') ? getUmbralAvisoIIPorSubfraccion($subfraccionII) : null,
    'umbral_acumulacion_uma_override' => function_exists('getUmbralAvisoIIPorSubfraccion') ? getUmbralAvisoIIPorSubfraccion($subfraccionII) : null,
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
if (!isset($data['informe'][0]['sujeto_obligado']) || !is_array($data['informe'][0]['sujeto_obligado'])) {
    $data['informe'][0]['sujeto_obligado'] = [];
}
if (function_exists('getSubfraccionIIClaveActividad')) {
    $data['informe'][0]['sujeto_obligado']['clave_actividad'] = getSubfraccionIIClaveActividad($subfraccionII);
}
$claveSO = $data['informe'][0]['sujeto_obligado']['clave_sujeto_obligado'] ?? '';

if ($isTPP) {
    if (file_exists(__DIR__ . '/../config/tpp_xml_helper.php')) {
        require_once __DIR__ . '/../config/tpp_xml_helper.php';
        if (!function_exists('tppValidarClaveSO') || !tppValidarClaveSO($claveSO)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Clave Sujeto Obligado debe tener formato RFC: 3-4 letras + 6 dígitos + 3 caracteres (ej: ABC010203AB1).'
            ]);
            exit;
        }
        $gen = generateTPPXml($data);
        $xml = $gen['xml'] ?? '';
        $xmlErrors = $gen['errors'] ?? [];
    }
} elseif ($isTDR) {
    if (file_exists(__DIR__ . '/../config/tdr_xml_helper.php')) {
        require_once __DIR__ . '/../config/tdr_xml_helper.php';
        if (!function_exists('tdrValidarClaveSO') || !tdrValidarClaveSO($claveSO)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Clave Sujeto Obligado debe tener formato RFC: 3-4 letras + 6 dígitos + 3 caracteres (ej: ABC010203AB1).'
            ]);
            exit;
        }
        $gen = generateTDRXml($data);
        $xml = $gen['xml'] ?? '';
        $xmlErrors = $gen['errors'] ?? [];
    }
} else {
    if (file_exists(__DIR__ . '/../config/tsc_xml_helper.php')) {
        require_once __DIR__ . '/../config/tsc_xml_helper.php';
        if (!function_exists('tscValidarClaveSO') || !tscValidarClaveSO($claveSO)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Clave Sujeto Obligado debe tener formato RFC: 3-4 letras + 6 dígitos + 3 caracteres (ej: ABC010203AB1). No usar folio o texto libre.'
            ]);
            exit;
        }
        $gen = generateTSCXml($data);
        $xml = $gen['xml'] ?? '';
        $xmlErrors = $gen['errors'] ?? [];
    }
}
if (!empty($xmlErrors) && function_exists('logChange')) {
    logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_TSC', 'operaciones_pld', $id_operacion, null, ['xml_errors' => $xmlErrors]);
}

if ($xml) {
    $prefijoXml = $isTPP ? 'tpp' : ($isTDR ? 'tdr' : 'tsc');
    $xmlNombre = $prefijoXml . '_' . date('Ymd_His') . '_op' . $id_operacion . '.xml';
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
    'message' => 'Aviso Fracción II registrado correctamente.',
    'subfraccion_ii' => $subfraccionII,
    'clave_actividad' => function_exists('getSubfraccionIIClaveActividad') ? getSubfraccionIIClaveActividad($subfraccionII) : 'TSC',
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

} catch (Throwable $e) {
    error_log('registrar_aviso_tsc: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $msg = $e->getMessage();
    if (strpos($msg, 'syntax error') !== false || $e instanceof \ParseError || $e instanceof \Error) {
        $msg .= ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']';
    }
    _tscJsonError('Error al registrar: ' . $msg, 500);
}
