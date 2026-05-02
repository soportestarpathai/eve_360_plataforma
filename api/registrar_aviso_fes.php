<?php
/**
 * API: Registrar Aviso FES (Fraccion XII - Fe publica, Servidores Publicos).
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _fesErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function _fesReq(bool $ok, string $msg): void { if (!$ok) _fesErr($msg, 400); }
function _fesUp($v): string { return fesToUpper($v); }
function _fesDigits($v): string { return preg_replace('/\D+/', '', (string)$v); }
function _fesMonth6($v): string { return substr(_fesDigits($v), 0, 6); }
function _fesDate8($v): string { $x = _fesDigits($v); return strlen($x) >= 8 ? substr($x, 0, 8) : ''; }
function _fesYmdFrom8(string $d8): string
{
    if (!preg_match('/^\d{8}$/', $d8)) return '';
    $y = (int)substr($d8, 0, 4);
    $m = (int)substr($d8, 4, 2);
    $d = (int)substr($d8, 6, 2);
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
}
function _fesHas(array $cat, $k): bool { $k = trim((string)$k); return $k !== '' && array_key_exists($k, $cat); }
function _fesFirstNumericAmount($value): float
{
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            $lk = strtolower((string)$k);
            if (preg_match('/(monto|valor|capital|acciones|avaluo)$/', $lk) && !is_array($v) && is_numeric($v)) return (float)$v;
            $found = _fesFirstNumericAmount($v);
            if ($found > 0) return $found;
        }
    }
    return 0.0;
}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_xii.php';
    require_once __DIR__ . '/../config/fes_catalogos.php';
    require_once __DIR__ . '/../config/fes_xml_helper.php';
} catch (Throwable $e) {
    _fesErr('Error al inicializar: ' . $e->getMessage(), 500);
}

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_fesReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_fesReq(function_exists('userCanAccessFES') && userCanAccessFES($pdo, $userId), 'Sin permiso para registrar avisos FES');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionXIIActiva($pdo);
_fesReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fraccion XII no esta activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_fesReq(is_array($data), 'JSON invalido');

$idCliente = (int)($data['id_cliente'] ?? 0);
_fesReq($idCliente > 0, 'id_cliente es obligatorio');
$mesReportado = _fesMonth6($data['mes_reportado'] ?? '');
_fesReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mesReportado) && $mesReportado >= '201309' && $mesReportado <= date('Ym'), 'mes_reportado invalido');
$claveTribunal = _fesUp($data['clave_tribunal_dependencia'] ?? '');
_fesReq((bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $claveTribunal), 'clave_tribunal_dependencia invalida');
$referencia = _fesUp($data['referencia_aviso'] ?? '');
_fesReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $referencia), 'referencia_aviso invalida');
$prioridad = trim((string)($data['prioridad'] ?? '1'));
_fesReq(in_array($prioridad, ['1','2'], true), 'prioridad invalida');
$tipoAlerta = _fesDigits($data['tipo_alerta'] ?? '100');
_fesReq(_fesHas($FES_CATALOGOS['tipo_alerta'] ?? [], $tipoAlerta), 'tipo_alerta fuera de catalogo FES');
$descAlerta = _fesUp($data['descripcion_alerta'] ?? '');
if ($tipoAlerta === '9999') _fesReq($descAlerta !== '', 'descripcion_alerta es obligatoria cuando tipo_alerta=9999');

$fechaOperacion8 = _fesDate8($data['fecha_operacion'] ?? '');
_fesReq($fechaOperacion8 !== '' && _fesYmdFrom8($fechaOperacion8) !== '', 'fecha_operacion invalida');
$subactividad = trim((string)($data['subactividad'] ?? ''));
_fesReq(_fesHas($FES_CATALOGOS['subactividad'] ?? [], $subactividad), 'subactividad FES invalida');
$subfraccionesPermitidas = function_exists('getSubfraccionesXIIFESActivas') ? getSubfraccionesXIIFESActivas($pdo, $userId) : [];
if (!empty($subfraccionesPermitidas)) {
    _fesReq(in_array($subactividad, $subfraccionesPermitidas, true), 'Sin permiso para esta subfraccion FES');
}
$tipoActividad = $data['tipo_actividad'] ?? null;
_fesReq(is_array($tipoActividad) && isset($tipoActividad[$subactividad]) && is_array($tipoActividad[$subactividad]), 'tipo_actividad debe incluir la subactividad seleccionada');

$monto = (float)($data['monto_operacion'] ?? 0);
if ($monto <= 0) $monto = _fesFirstNumericAmount($tipoActividad);
_fesReq($monto > 0, 'monto_operacion invalido');

$idFraccion = getIdVulnerableFraccionXIIFES($pdo);
_fesReq((int)$idFraccion > 0, 'No se pudo resolver Fraccion XII/FES en cat_vulnerables');

$umbralAviso = in_array($subactividad, ['derechos_inmuebles'], true) ? PLD_FRACCION_XII_UMA_AVISO_INMUEBLES : (in_array($subactividad, ['avaluo'], true) ? PLD_FRACCION_XII_UMA_AVISO_AVALUO : null);
$avisoSiempre = $umbralAviso === null;
$registro = [
    'id_cliente' => $idCliente,
    'monto' => $monto,
    'fecha_operacion' => _fesYmdFrom8($fechaOperacion8),
    'id_fraccion' => (int)$idFraccion,
    'tipo_operacion' => 'XII:FES:' . $subactividad,
    'umbral_aviso_uma_override' => $umbralAviso,
    'umbral_acumulacion_uma_override' => $umbralAviso,
];
if ($avisoSiempre) {
    $registro['requiere_aviso_forzado'] = true;
    $registro['tipo_aviso_forzado'] = 'umbral_individual';
}
$result = registrarOperacionPLD($pdo, $registro);
if (!($result['success'] ?? false)) _fesErr($result['message'] ?? 'Error al registrar aviso FES', 400);

$payloadXml = ['informe' => [[
    'mes_reportado' => $mesReportado,
    'tribunal_dependencia' => [
        'clave_tribunal_dependencia' => $claveTribunal,
        'clave_actividad' => 'FES',
    ],
    'aviso' => [[
        'referencia_aviso' => $referencia,
        'prioridad' => $prioridad,
        'alerta' => ['tipo_alerta' => $tipoAlerta, 'descripcion_alerta' => $descAlerta !== '' ? $descAlerta : null],
        'detalle_operaciones' => [[
            'datos_operacion' => [[
                'fecha_operacion' => $fechaOperacion8,
                'tipo_actividad' => $tipoActividad,
            ]],
        ]],
    ]],
]]];

$xmlData = generateFESXml($payloadXml);
$xml = (string)($xmlData['xml'] ?? '');
_fesReq($xml !== '', 'No se pudo generar XML FES');

$idOperacion = (int)($result['id_operacion'] ?? 0);
if ($idOperacion > 0) {
    try {
        $xmlNombre = 'fes_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $idOperacion]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_fes xml update: ' . $e->getMessage());
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso FES registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => true,
], JSON_UNESCAPED_UNICODE);
