<?php
/**
 * API: Registrar Aviso BLI (Fracción IX - Blindaje).
 * Base operable con umbrales legales; ajustable a XSD final.
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _bliErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function _bliUp($v): string { return mb_strtoupper(trim((string)$v), 'UTF-8'); }
function _bliMonth6($v): string { return substr(preg_replace('/\D+/', '', (string)$v), 0, 6); }
function _bliDate8($v): string { $x = preg_replace('/\D+/', '', (string)$v); return strlen($x) >= 8 ? substr($x, 0, 8) : ''; }
function _bliYmdFrom8(string $d8): string
{
    if (!preg_match('/^\d{8}$/', $d8)) return '';
    $y = (int)substr($d8, 0, 4);
    $m = (int)substr($d8, 4, 2);
    $d = (int)substr($d8, 6, 2);
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
}
function _bliReq(bool $ok, string $msg): void { if (!$ok) _bliErr($msg, 400); }
function _bliHas(array $cat, $k): bool {
    $k = trim((string)$k);
    return $k !== '' && array_key_exists($k, $cat);
}

function _bliTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $cache[$table] = (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $e) {
        $cache[$table] = false;
    }
    return $cache[$table];
}

function _bliCpSepomex(PDO $pdo, string $cp): bool
{
    if (!preg_match('/^\d{5}$/', $cp)) return false;
    if (!_bliTableExists($pdo, 'cat_sepomex')) return true;

    static $cols = null;
    if ($cols === null) {
        $cols = [];
        try {
            $q = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cat_sepomex'");
            $all = array_map('strtolower', array_column($q->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
            foreach (['codigo_postal', 'd_codigo', 'cp', 'codigo_postal_sat', 'codigo_postal_sepomex'] as $c) {
                if (in_array($c, $all, true)) $cols[] = $c;
            }
        } catch (Throwable $e) {
            $cols = [];
        }
    }
    if (empty($cols)) return true;

    foreach ($cols as $c) {
        $s = $pdo->prepare("SELECT 1 FROM cat_sepomex WHERE TRIM(`{$c}`)=? LIMIT 1");
        $s->execute([$cp]);
        if ($s->fetch(PDO::FETCH_NUM)) return true;
    }
    return false;
}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_ix.php';
    require_once __DIR__ . '/../config/bli_catalogos.php';
    require_once __DIR__ . '/../config/bli_xml_helper.php';
} catch (Throwable $e) {
    _bliErr('Error al inicializar: ' . $e->getMessage(), 500);
}

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_bliReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_bliReq(function_exists('userCanAccessBLI') && userCanAccessBLI($pdo, $userId), 'Sin permiso para registrar avisos BLI');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionIXActiva($pdo);
_bliReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fracción IX no está activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_bliReq(is_array($data), 'JSON inválido');

$idCliente = (int)($data['id_cliente'] ?? 0);
_bliReq($idCliente > 0, 'id_cliente es obligatorio');

$mesReportado = _bliMonth6($data['mes_reportado'] ?? '');
_bliReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mesReportado), 'mes_reportado inválido');

$claveSO = _bliUp($data['clave_sujeto_obligado'] ?? '');
_bliReq((bool)preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $claveSO), 'clave_sujeto_obligado inválida');

$claveEntidad = _bliUp($data['clave_entidad_colegiada'] ?? '');
if ($claveEntidad !== '') {
    _bliReq((bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $claveEntidad), 'clave_entidad_colegiada inválida');
}

$referenciaAviso = _bliUp($data['referencia_aviso'] ?? '');
_bliReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $referenciaAviso), 'referencia_aviso inválida');

$prioridad = trim((string)($data['prioridad'] ?? '1'));
_bliReq(in_array($prioridad, ['1', '2'], true), 'prioridad inválida');

$tipoAlerta = preg_replace('/\D+/', '', (string)($data['tipo_alerta'] ?? '100'));
_bliReq((bool)preg_match('/^\d{3,4}$/', $tipoAlerta), 'tipo_alerta inválido');
if (isset($BLI_CATALOGOS['tipo_alerta']) && is_array($BLI_CATALOGOS['tipo_alerta']) && !empty($BLI_CATALOGOS['tipo_alerta'])) {
    _bliReq(_bliHas($BLI_CATALOGOS['tipo_alerta'], $tipoAlerta), 'tipo_alerta fuera de catálogo BLI');
}
_bliReq(!($prioridad === '2' && $tipoAlerta === '100'), 'Cuando prioridad=2, tipo_alerta debe ser diferente de 100');

$descripcionAlerta = _bliUp($data['descripcion_alerta'] ?? '');
if ($tipoAlerta === '9999') _bliReq($descripcionAlerta !== '', 'descripcion_alerta es obligatoria cuando tipo_alerta=9999');

$fechaOperacion8 = _bliDate8($data['fecha_operacion'] ?? '');
_bliReq($fechaOperacion8 !== '' && _bliYmdFrom8($fechaOperacion8) !== '', 'fecha_operacion inválida');
$fechaOperacionYmd = _bliYmdFrom8($fechaOperacion8);

$codigoPostal = preg_replace('/\D+/', '', (string)($data['codigo_postal'] ?? ''));
_bliReq((bool)preg_match('/^\d{5}$/', $codigoPostal), 'codigo_postal inválido');
_bliReq(_bliCpSepomex($pdo, $codigoPostal), 'codigo_postal de operación no existe en SEPOMEX');

$tipoOperacion = preg_replace('/\D+/', '', (string)($data['tipo_operacion'] ?? ''));
_bliReq($tipoOperacion !== '', 'tipo_operacion es obligatorio');
if (isset($BLI_CATALOGOS['tipo_operacion']) && is_array($BLI_CATALOGOS['tipo_operacion']) && !empty($BLI_CATALOGOS['tipo_operacion'])) {
    _bliReq(_bliHas($BLI_CATALOGOS['tipo_operacion'], $tipoOperacion), 'tipo_operacion fuera de catálogo BLI');
}

$tipoBienBlindado = preg_replace('/\D+/', '', (string)($data['tipo_bien_blindado'] ?? ''));
_bliReq($tipoBienBlindado !== '', 'tipo_bien_blindado es obligatorio');
if (isset($BLI_CATALOGOS['tipo_bien_blindado']) && is_array($BLI_CATALOGOS['tipo_bien_blindado']) && !empty($BLI_CATALOGOS['tipo_bien_blindado'])) {
    _bliReq(_bliHas($BLI_CATALOGOS['tipo_bien_blindado'], $tipoBienBlindado), 'tipo_bien_blindado fuera de catálogo BLI');
}

$tipoInmueble = preg_replace('/\D+/', '', (string)($data['tipo_inmueble'] ?? ''));
if ($tipoBienBlindado === '2') {
    _bliReq($tipoInmueble !== '', 'tipo_inmueble es obligatorio cuando tipo_bien_blindado=2');
}
if ($tipoInmueble !== '') {
    if (isset($BLI_CATALOGOS['tipo_inmueble']) && is_array($BLI_CATALOGOS['tipo_inmueble']) && !empty($BLI_CATALOGOS['tipo_inmueble'])) {
        _bliReq(_bliHas($BLI_CATALOGOS['tipo_inmueble'], $tipoInmueble), 'tipo_inmueble fuera de catálogo BLI');
    }
}

$parteBlindada = preg_replace('/\D+/', '', (string)($data['parte_blindada'] ?? ''));
if ($tipoBienBlindado === '2') {
    _bliReq($parteBlindada !== '', 'parte_blindada es obligatoria cuando tipo_bien_blindado=2');
}
if ($parteBlindada !== '') {
    if (isset($BLI_CATALOGOS['parte_blindada']) && is_array($BLI_CATALOGOS['parte_blindada']) && !empty($BLI_CATALOGOS['parte_blindada'])) {
        _bliReq(_bliHas($BLI_CATALOGOS['parte_blindada'], $parteBlindada), 'parte_blindada fuera de catálogo BLI');
    }
}

$estadoBien = preg_replace('/\D+/', '', (string)($data['estado_bien'] ?? ''));
_bliReq($estadoBien !== '', 'estado_bien es obligatorio');
if (isset($BLI_CATALOGOS['estado_bien']) && is_array($BLI_CATALOGOS['estado_bien']) && !empty($BLI_CATALOGOS['estado_bien'])) {
    _bliReq(_bliHas($BLI_CATALOGOS['estado_bien'], $estadoBien), 'estado_bien fuera de catálogo BLI');
}

$nivelBlindaje = preg_replace('/\D+/', '', (string)($data['nivel_blindaje'] ?? ''));
_bliReq($nivelBlindaje !== '', 'nivel_blindaje es obligatorio');
$catNivelBlindaje = [];
if (isset($BLI_CATALOGOS['nivel_blindaje']) && is_array($BLI_CATALOGOS['nivel_blindaje']) && !empty($BLI_CATALOGOS['nivel_blindaje'])) {
    $catNivelBlindaje = $BLI_CATALOGOS['nivel_blindaje'];
} elseif (isset($BLI_CATALOGOS['tipo_blindaje']) && is_array($BLI_CATALOGOS['tipo_blindaje']) && !empty($BLI_CATALOGOS['tipo_blindaje'])) {
    $catNivelBlindaje = $BLI_CATALOGOS['tipo_blindaje'];
}
if (!empty($catNivelBlindaje)) {
    _bliReq(_bliHas($catNivelBlindaje, $nivelBlindaje), 'nivel_blindaje fuera de catálogo BLI');
}

$instrumentoMonetario = preg_replace('/\D+/', '', (string)($data['instrumento_monetario'] ?? ''));
_bliReq($instrumentoMonetario !== '', 'instrumento_monetario es obligatorio');

$moneda = preg_replace('/\D+/', '', (string)($data['moneda'] ?? ''));
_bliReq($moneda !== '', 'moneda es obligatoria');

$monto = (float)($data['monto'] ?? 0);
_bliReq($monto > 0, 'monto inválido');

$descripcionServicio = _bliUp($data['descripcion_servicio'] ?? '');
$exento = trim((string)($data['exento'] ?? '0')) === '1' ? '1' : null;

$idFraccion = getIdVulnerableFraccionIX($pdo);
_bliReq((int)$idFraccion > 0, 'No se pudo resolver Fracción IX en cat_vulnerables');

$sumMontos = number_format($monto, 2, '.', '');
$tipoOperacionSistema = 'IX:BLI:' . $tipoOperacion;

$operacionData = [
    'id_cliente' => $idCliente,
    'monto' => (float)$sumMontos,
    'fecha_operacion' => $fechaOperacionYmd,
    'id_fraccion' => (int)$idFraccion,
    'tipo_operacion' => $tipoOperacionSistema,
    'umbral_identificacion_uma_override' => pldFraccionIXUmbralIdentificacion(),
    'umbral_aviso_uma_override' => pldFraccionIXUmbralAviso(),
    'umbral_acumulacion_uma_override' => pldFraccionIXUmbralAviso()
];

$result = registrarOperacionPLD($pdo, $operacionData);
if (!($result['success'] ?? false)) {
    _bliErr($result['message'] ?? 'Error al registrar aviso BLI', 400);
}

$payloadXml = [
    'informe' => [[
        'mes_reportado' => $mesReportado,
        'sujeto_obligado' => [
            'clave_entidad_colegiada' => $claveEntidad !== '' ? $claveEntidad : null,
            'clave_sujeto_obligado' => $claveSO,
            'clave_actividad' => 'BLI',
            'exento' => $exento
        ],
        'aviso' => [[
            'referencia_aviso' => $referenciaAviso,
            'prioridad' => $prioridad,
            'alerta' => [
                'tipo_alerta' => $tipoAlerta,
                'descripcion_alerta' => $descripcionAlerta !== '' ? $descripcionAlerta : null
            ],
            'detalle_operaciones' => [[
                'datos_operacion' => [[
                    'fecha_operacion' => $fechaOperacion8,
                    'codigo_postal' => $codigoPostal,
                    'tipo_operacion' => $tipoOperacion,
                    'tipo_bien_blindado' => $tipoBienBlindado,
                    'tipo_inmueble' => $tipoInmueble !== '' ? $tipoInmueble : null,
                    'parte_blindada' => $parteBlindada !== '' ? $parteBlindada : null,
                    'estado_bien' => $estadoBien,
                    'nivel_blindaje' => $nivelBlindaje,
                    'descripcion_servicio' => $descripcionServicio !== '' ? $descripcionServicio : null,
                    'datos_liquidacion' => [[
                        'fecha_pago' => $fechaOperacion8,
                        'instrumento_monetario' => $instrumentoMonetario,
                        'moneda' => $moneda,
                        'monto_operacion' => $sumMontos
                    ]]
                ]]
            ]]
        ]]
    ]]
];

$xmlData = generateBLIXml($payloadXml);
$xml = (string)($xmlData['xml'] ?? '');
$xmlErrors = (array)($xmlData['errors'] ?? []);

$idOperacion = (int)($result['id_operacion'] ?? 0);
if ($idOperacion > 0 && $xml !== '') {
    try {
        $xmlNombre = 'bli_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $idOperacion]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_bli xml update: ' . $e->getMessage());
    }
}

if (function_exists('logChange')) {
    try {
        logChange($pdo, $userId, 'REGISTRAR_AVISO_BLI', 'operaciones_pld', $idOperacion, null, [
            'id_cliente' => $idCliente,
            'id_fraccion' => (int)$idFraccion,
            'tipo_operacion' => $tipoOperacionSistema,
            'monto' => (float)$sumMontos,
            'umbral_identificacion_uma' => pldFraccionIXUmbralIdentificacion(),
            'umbral_aviso_uma' => pldFraccionIXUmbralAviso()
        ]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_bli bitacora: ' . $e->getMessage());
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso BLI registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => ($xml !== ''),
    'xml_warnings' => $xmlErrors
], JSON_UNESCAPED_UNICODE);
