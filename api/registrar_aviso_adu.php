<?php
/**
 * API: Registrar Aviso ADU (Fraccion XIV - Comercio exterior).
 * Base operable con umbrales por inciso; pendiente de XSD/catálogos oficiales completos.
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _aduErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function _aduReq(bool $ok, string $msg): void { if (!$ok) _aduErr($msg, 400); }
function _aduUp($v): string { return mb_strtoupper(trim((string)$v), 'UTF-8'); }
function _aduDigits($v): string { return preg_replace('/\D+/', '', (string)$v); }
function _aduMonth6($v): string { return substr(_aduDigits($v), 0, 6); }
function _aduDate8($v): string { $x = _aduDigits($v); return strlen($x) >= 8 ? substr($x, 0, 8) : ''; }
function _aduYmdFrom8(string $d8): string
{
    if (!preg_match('/^\d{8}$/', $d8)) return '';
    $y = (int)substr($d8, 0, 4);
    $m = (int)substr($d8, 4, 2);
    $d = (int)substr($d8, 6, 2);
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
}
function _aduSanText($v): string { return preg_replace('/[^A-ZÑ0-9 \-\.,:\/#&,_@\'$]/u', '', _aduUp($v)); }
function _aduHas(array $cat, $k): bool { $k = trim((string)$k); return $k !== '' && array_key_exists($k, $cat); }

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_xiv.php';
    require_once __DIR__ . '/../config/adu_catalogos.php';
    require_once __DIR__ . '/../config/adu_xml_helper.php';
} catch (Throwable $e) {
    _aduErr('Error al inicializar: ' . $e->getMessage(), 500);
}

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_aduReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_aduReq(function_exists('userCanAccessADU') && userCanAccessADU($pdo, $userId), 'Sin permiso para registrar avisos ADU');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionXIVActiva($pdo);
_aduReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fraccion XIV no esta activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_aduReq(is_array($data), 'JSON invalido');

$idCliente = (int)($data['id_cliente'] ?? 0);
_aduReq($idCliente > 0, 'id_cliente es obligatorio');

$mesReportado = _aduMonth6($data['mes_reportado'] ?? '');
_aduReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mesReportado) && $mesReportado >= '201309' && $mesReportado <= date('Ym'), 'mes_reportado invalido');

$claveSO = _aduUp($data['clave_sujeto_obligado'] ?? '');
_aduReq((bool)preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $claveSO), 'clave_sujeto_obligado invalida');
$claveEntidad = _aduUp($data['clave_entidad_colegiada'] ?? '');
if ($claveEntidad !== '') _aduReq((bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $claveEntidad), 'clave_entidad_colegiada invalida');

$referencia = _aduUp($data['referencia_aviso'] ?? '');
_aduReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $referencia), 'referencia_aviso invalida');
$prioridad = trim((string)($data['prioridad'] ?? '1'));
_aduReq(in_array($prioridad, ['1','2'], true), 'prioridad invalida');
$tipoAlerta = _aduDigits($data['tipo_alerta'] ?? '100');
_aduReq(_aduHas($ADU_CATALOGOS['tipo_alerta'] ?? [], $tipoAlerta), 'tipo_alerta fuera de catalogo ADU');
_aduReq(!($prioridad === '2' && $tipoAlerta === '100'), 'Cuando prioridad=2, tipo_alerta debe ser diferente de 100');
$descAlerta = _aduSanText($data['descripcion_alerta'] ?? '');
if ($tipoAlerta === '9999') _aduReq($descAlerta !== '', 'descripcion_alerta es obligatoria cuando tipo_alerta=9999');

$fechaOperacion8 = _aduDate8($data['fecha_operacion'] ?? '');
_aduReq($fechaOperacion8 !== '' && _aduYmdFrom8($fechaOperacion8) !== '', 'fecha_operacion invalida');
$actividadVulnerable = _aduUp($data['actividad_vulnerable'] ?? ($data['inciso'] ?? ''));
_aduReq(_aduHas($ADU_CATALOGOS['actividad_vulnerable'] ?? [], $actividadVulnerable), 'actividad_vulnerable fuera de catalogo ADU');
$subfraccionesPermitidas = function_exists('getSubfraccionesXIVActivas') ? getSubfraccionesXIVActivas($pdo, $userId) : [];
if (!empty($subfraccionesPermitidas)) {
    _aduReq(in_array($actividadVulnerable, $subfraccionesPermitidas, true), 'Sin permiso para esta actividad vulnerable ADU');
}
$tipoOperacion = _aduDigits($data['tipo_operacion'] ?? '');
_aduReq(_aduHas($ADU_CATALOGOS['tipo_operacion'] ?? [], $tipoOperacion), 'tipo_operacion fuera de catalogo ADU');
$codigoPostal = _aduDigits($data['codigo_postal'] ?? '');
_aduReq((bool)preg_match('/^\d{5}$/', $codigoPostal), 'codigo_postal invalido');
$paisOrigen = _aduUp($data['pais_origen'] ?? 'MX');
_aduReq(_aduHas($ADU_CATALOGOS['pais'] ?? [], $paisOrigen), 'pais_origen fuera de catalogo');
$paisDestino = _aduUp($data['pais_destino'] ?? 'MX');
_aduReq(_aduHas($ADU_CATALOGOS['pais'] ?? [], $paisDestino), 'pais_destino fuera de catalogo');
$pedimento = preg_replace('/[^A-Z0-9\-_]/u', '', _aduUp($data['pedimento'] ?? ''));
$descripcionMercancia = _aduSanText($data['descripcion_mercancia'] ?? $ADU_CATALOGOS['actividad_vulnerable'][$actividadVulnerable]);

$instrumento = _aduDigits($data['instrumento_monetario'] ?? '');
_aduReq(_aduHas($ADU_CATALOGOS['instrumento_monetario'] ?? [], $instrumento), 'instrumento_monetario fuera de catalogo');
$moneda = _aduDigits($data['moneda'] ?? '');
_aduReq(_aduHas($ADU_CATALOGOS['moneda'] ?? [], $moneda), 'moneda fuera de catalogo');
$monto = (float)($data['monto_operacion'] ?? 0);
_aduReq($monto > 0, 'monto_operacion invalido');
$montoFmt = number_format($monto, 2, '.', '');

$umbralIdent = pldFraccionXIVUmbralIdentificacion($actividadVulnerable);
$umbralAviso = pldFraccionXIVUmbralAviso($actividadVulnerable);
$avisoSiempre = in_array($actividadVulnerable, ['VEH', 'JYS', 'TSC', 'TPP', 'TDR'], true);

$idFraccion = getIdVulnerableFraccionXIV($pdo);
_aduReq((int)$idFraccion > 0, 'No se pudo resolver Fraccion XIV en cat_vulnerables');

$operacionPLD = [
    'id_cliente' => $idCliente,
    'monto' => $monto,
    'fecha_operacion' => _aduYmdFrom8($fechaOperacion8),
    'id_fraccion' => (int)$idFraccion,
    'tipo_operacion' => 'XIV:ADU:' . $actividadVulnerable . ':' . $tipoOperacion,
    'umbral_identificacion_uma_override' => $umbralIdent,
    'umbral_aviso_uma_override' => $umbralAviso,
    'umbral_acumulacion_uma_override' => $umbralAviso,
];
if ($avisoSiempre) {
    $operacionPLD['requiere_aviso_forzado'] = true;
    $operacionPLD['tipo_aviso_forzado'] = 'umbral_individual';
}
$result = registrarOperacionPLD($pdo, $operacionPLD);
if (!($result['success'] ?? false)) _aduErr($result['message'] ?? 'Error al registrar aviso ADU', 400);

$payloadXml = ['informe' => [[
    'mes_reportado' => $mesReportado,
    'sujeto_obligado' => [
        'clave_entidad_colegiada' => $claveEntidad !== '' ? $claveEntidad : null,
        'clave_sujeto_obligado' => $claveSO,
        'clave_actividad' => 'ADU',
    ],
]]];

$xmlData = generateADUXml($payloadXml);
$xml = (string)($xmlData['xml'] ?? '');
$idOperacion = (int)($result['id_operacion'] ?? 0);
if ($idOperacion > 0 && $xml !== '') {
    try {
        $xmlNombre = 'adu_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $idOperacion]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_adu xml update: ' . $e->getMessage());
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso ADU registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => ($xml !== ''),
], JSON_UNESCAPED_UNICODE);
