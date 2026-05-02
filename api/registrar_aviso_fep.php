<?php
/**
 * API: Registrar Aviso FEP (Fraccion XII - Fe publica).
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _fepErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function _fepReq(bool $ok, string $msg): void { if (!$ok) _fepErr($msg, 400); }
function _fepUp($v): string { return fepToUpper($v); }
function _fepDigits($v): string { return preg_replace('/\D+/', '', (string)$v); }
function _fepMonth6($v): string { return substr(_fepDigits($v), 0, 6); }
function _fepDate8($v): string { $x = _fepDigits($v); return strlen($x) >= 8 ? substr($x, 0, 8) : ''; }
function _fepYmdFrom8(string $d8): string
{
    if (!preg_match('/^\d{8}$/', $d8)) return '';
    $y = (int)substr($d8, 0, 4);
    $m = (int)substr($d8, 4, 2);
    $d = (int)substr($d8, 6, 2);
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
}
function _fepHas(array $cat, $k): bool { $k = trim((string)$k); return $k !== '' && array_key_exists($k, $cat); }

function _fepFirstNumericAmount($value): float
{
    if (is_array($value)) {
        foreach ($value as $k => $v) {
            $lk = strtolower((string)$k);
            if (preg_match('/(monto|valor|capital|acciones)$/', $lk) && !is_array($v) && is_numeric($v)) {
                return (float)$v;
            }
            $found = _fepFirstNumericAmount($v);
            if ($found > 0) return $found;
        }
        return 0.0;
    }
    return 0.0;
}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_xii.php';
    require_once __DIR__ . '/../config/fep_catalogos.php';
    require_once __DIR__ . '/../config/fep_xml_helper.php';
} catch (Throwable $e) {
    _fepErr('Error al inicializar: ' . $e->getMessage(), 500);
}

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_fepReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_fepReq(function_exists('userCanAccessFEP') && userCanAccessFEP($pdo, $userId), 'Sin permiso para registrar avisos FEP');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionXIIActiva($pdo);
_fepReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fraccion XII no esta activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_fepReq(is_array($data), 'JSON invalido');

$idCliente = (int)($data['id_cliente'] ?? 0);
_fepReq($idCliente > 0, 'id_cliente es obligatorio');
$mesReportado = _fepMonth6($data['mes_reportado'] ?? '');
_fepReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mesReportado) && $mesReportado >= '201309' && $mesReportado <= date('Ym'), 'mes_reportado invalido');
$claveSO = _fepUp($data['clave_sujeto_obligado'] ?? '');
_fepReq((bool)preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $claveSO), 'clave_sujeto_obligado invalida');
$claveEntidad = _fepUp($data['clave_entidad_colegiada'] ?? '');
if ($claveEntidad !== '') _fepReq((bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $claveEntidad), 'clave_entidad_colegiada invalida');
$referencia = _fepUp($data['referencia_aviso'] ?? '');
_fepReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $referencia), 'referencia_aviso invalida');
$prioridad = trim((string)($data['prioridad'] ?? '1'));
_fepReq(in_array($prioridad, ['1','2'], true), 'prioridad invalida');
$tipoAlerta = _fepDigits($data['tipo_alerta'] ?? '100');
_fepReq(_fepHas($FEP_CATALOGOS['tipo_alerta'] ?? [], $tipoAlerta), 'tipo_alerta fuera de catalogo FEP');
$descAlerta = _fepUp($data['descripcion_alerta'] ?? '');
if ($tipoAlerta === '9999') _fepReq($descAlerta !== '', 'descripcion_alerta es obligatoria cuando tipo_alerta=9999');

$fechaOperacion8 = _fepDate8($data['fecha_operacion'] ?? '');
_fepReq($fechaOperacion8 !== '' && _fepYmdFrom8($fechaOperacion8) !== '', 'fecha_operacion invalida');
$instrumentoPublico = _fepUp($data['instrumento_publico'] ?? '');
_fepReq($instrumentoPublico !== '', 'instrumento_publico es obligatorio');
$subactividad = trim((string)($data['subactividad'] ?? ''));
_fepReq(_fepHas($FEP_CATALOGOS['subactividad'] ?? [], $subactividad), 'subactividad FEP invalida');
$subfraccionesPermitidas = function_exists('getSubfraccionesXIIActivas') ? getSubfraccionesXIIActivas($pdo, $userId) : [];
if (!empty($subfraccionesPermitidas)) {
    _fepReq(in_array($subactividad, $subfraccionesPermitidas, true), 'Sin permiso para esta subfraccion FEP');
}
$tipoActividad = $data['tipo_actividad'] ?? null;
_fepReq(is_array($tipoActividad) && isset($tipoActividad[$subactividad]) && is_array($tipoActividad[$subactividad]), 'tipo_actividad debe incluir la subactividad seleccionada');

$personaAviso = $data['persona_aviso'] ?? [];
_fepReq(is_array($personaAviso), 'persona_aviso invalida');
foreach (['nombre', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento'] as $k) {
    _fepReq(trim((string)($personaAviso[$k] ?? '')) !== '', 'persona_aviso.' . $k . ' es obligatorio');
}

$monto = (float)($data['monto_operacion'] ?? 0);
if ($monto <= 0) $monto = _fepFirstNumericAmount($tipoActividad);
_fepReq($monto > 0, 'monto_operacion invalido');

$idFraccion = getIdVulnerableFraccionXIIFEP($pdo);
_fepReq((int)$idFraccion > 0, 'No se pudo resolver Fraccion XII/FEP en cat_vulnerables');

$umbralAviso = pldFraccionXIIUmbralAviso($subactividad);
$avisoSiempre = pldFraccionXIIAvisoSiempre($subactividad);
$registro = [
    'id_cliente' => $idCliente,
    'monto' => $monto,
    'fecha_operacion' => _fepYmdFrom8($fechaOperacion8),
    'id_fraccion' => (int)$idFraccion,
    'tipo_operacion' => 'XII:FEP:' . $subactividad,
    'umbral_aviso_uma_override' => $umbralAviso,
    'umbral_acumulacion_uma_override' => $umbralAviso,
];
if ($avisoSiempre) {
    $registro['requiere_aviso_forzado'] = true;
    $registro['tipo_aviso_forzado'] = 'umbral_individual';
}
$result = registrarOperacionPLD($pdo, $registro);
if (!($result['success'] ?? false)) _fepErr($result['message'] ?? 'Error al registrar aviso FEP', 400);

$payloadXml = ['informe' => [[
    'mes_reportado' => $mesReportado,
    'sujeto_obligado' => [
        'clave_entidad_colegiada' => $claveEntidad !== '' ? $claveEntidad : null,
        'clave_sujeto_obligado' => $claveSO,
        'clave_actividad' => 'FEP',
        'exento' => trim((string)($data['exento'] ?? '0')) === '1' ? '1' : null,
    ],
    'aviso' => [[
        'referencia_aviso' => $referencia,
        'prioridad' => $prioridad,
        'alerta' => ['tipo_alerta' => $tipoAlerta, 'descripcion_alerta' => $descAlerta !== '' ? $descAlerta : null],
        'persona_aviso' => [[
            'nombre' => $personaAviso['nombre'] ?? '',
            'apellido_paterno' => $personaAviso['apellido_paterno'] ?? '',
            'apellido_materno' => $personaAviso['apellido_materno'] ?? '',
            'fecha_nacimiento' => _fepDate8($personaAviso['fecha_nacimiento'] ?? ''),
            'rfc' => $personaAviso['rfc'] ?? null,
            'curp' => $personaAviso['curp'] ?? null,
        ]],
        'detalle_operaciones' => [[
            'datos_operacion' => [[
                'instrumento_publico' => $instrumentoPublico,
                'fecha_operacion' => $fechaOperacion8,
                'tipo_actividad' => $tipoActividad,
            ]],
        ]],
    ]],
]]];

$xmlData = generateFEPXml($payloadXml);
$xml = (string)($xmlData['xml'] ?? '');
_fepReq($xml !== '', 'No se pudo generar XML FEP');

$idOperacion = (int)($result['id_operacion'] ?? 0);
if ($idOperacion > 0) {
    try {
        $xmlNombre = 'fep_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $idOperacion]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_fep xml update: ' . $e->getMessage());
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso FEP registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => true,
], JSON_UNESCAPED_UNICODE);
