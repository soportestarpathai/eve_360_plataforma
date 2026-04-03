<?php
/**
 * API: Registrar Aviso MJR (Fracción VI - Metales, Joyas y Relojes)
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _mjrErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function _mjrUp($v): string { return mb_strtoupper(trim((string)$v), 'UTF-8'); }
function _mjrDate8($v): string { $x = preg_replace('/\D+/', '', (string)$v); return strlen($x) === 8 ? $x : ''; }
function _mjrMonth6($v): string { return substr(preg_replace('/\D+/', '', (string)$v), 0, 6); }
function _mjrYmd(string $d8): string
{
    if (!preg_match('/^\d{8}$/', $d8)) return '';
    $y = (int)substr($d8, 0, 4);
    $m = (int)substr($d8, 4, 2);
    $d = (int)substr($d8, 6, 2);
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
}
function _mjrReq(bool $ok, string $msg): void { if (!$ok) _mjrErr($msg, 400); }
function _mjrHas(array $cat, $k): bool { $k = trim((string)$k); return $k !== '' && array_key_exists($k, $cat); }
function _mjrChoice(array $node, array $keys): int { $n = 0; foreach ($keys as $k) if (isset($node[$k]) && is_array($node[$k])) $n++; return $n; }
function _mjrPickList($raw, string $marker): array { return (is_array($raw) && isset($raw[$marker])) ? [$raw] : (is_array($raw) ? $raw : []); }

function _mjrTableExists(PDO $pdo, string $table): bool
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

function _mjrCpSepomex(PDO $pdo, string $cp): bool
{
    if (!preg_match('/^\d{5}$/', $cp)) return false;
    if (!_mjrTableExists($pdo, 'cat_sepomex')) return true;

    static $cols = null;
    if ($cols === null) {
        $cols = [];
        try {
            $q = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cat_sepomex'");
            $all = array_map('strtolower', array_column($q->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
            foreach (['codigo_postal', 'd_codigo', 'cp', 'codigo_postal_sat', 'codigo_postal_sepomex'] as $c) {
                if (in_array($c, $all, true)) $cols[] = $c;
            }
        } catch (Throwable $e) { $cols = []; }
    }
    if (empty($cols)) return true;

    foreach ($cols as $c) {
        $s = $pdo->prepare("SELECT 1 FROM cat_sepomex WHERE TRIM(`{$c}`)=? LIMIT 1");
        $s->execute([$cp]);
        if ($s->fetch(PDO::FETCH_NUM)) return true;
    }
    return false;
}

function _mjrValRFCFisica(string $v): bool { return $v === '' || (bool)preg_match('/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/u', _mjrUp($v)); }
function _mjrValRFCMoral(string $v): bool { return $v === '' || (bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', _mjrUp($v)); }
function _mjrValCURP(string $v): bool { return $v === '' || (bool)preg_match('/^[A-Z]{4}\d{6}[MH][A-Z]{5}[0-9A-Z]{2}$/', _mjrUp($v)); }
function _mjrValPais(string $v): bool { return (bool)preg_match('/^[A-Z]{2}$/', _mjrUp($v)); }

function _mjrValidateTipoPersona(array $tp, array $cat, bool $isDueno = false): void
{
    _mjrReq(_mjrChoice($tp, ['persona_fisica', 'persona_moral', 'fideicomiso']) === 1, 'tipo_persona debe contener exactamente una opción.');

    if (isset($tp['persona_fisica'])) {
        $pf = $tp['persona_fisica'];
        _mjrReq(_mjrUp($pf['nombre'] ?? '') !== '' && _mjrUp($pf['apellido_paterno'] ?? '') !== '' && _mjrUp($pf['apellido_materno'] ?? '') !== '', 'Persona física requiere nombre y apellidos.');
        _mjrReq(_mjrValPais((string)($pf['pais_nacionalidad'] ?? '')), 'País nacionalidad persona física inválido.');
        $act = trim((string)($pf['actividad_economica'] ?? ''));
        if (!$isDueno) _mjrReq((bool)preg_match('/^\d{7}$/', $act), 'Persona física requiere actividad_economica de 7 dígitos.');
        _mjrReq(_mjrValRFCFisica((string)($pf['rfc'] ?? '')), 'RFC persona física inválido.');
        _mjrReq(_mjrValCURP((string)($pf['curp'] ?? '')), 'CURP persona física inválida.');
        if (!empty($pf['fecha_nacimiento'])) _mjrReq(_mjrYmd(_mjrDate8($pf['fecha_nacimiento'])) !== '', 'fecha_nacimiento persona física inválida.');
        return;
    }

    if (isset($tp['persona_moral'])) {
        $pm = $tp['persona_moral'];
        _mjrReq(_mjrUp($pm['denominacion_razon'] ?? '') !== '', 'Persona moral requiere denominación o razón social.');
        _mjrReq(_mjrValPais((string)($pm['pais_nacionalidad'] ?? '')), 'País nacionalidad persona moral inválido.');
        $g = trim((string)($pm['giro_mercantil'] ?? ''));
        if (!$isDueno) _mjrReq((bool)preg_match('/^\d{7}$/', $g), 'Persona moral requiere giro_mercantil de 7 dígitos.');
        _mjrReq(_mjrValRFCMoral((string)($pm['rfc'] ?? '')), 'RFC persona moral inválido.');
        if (!empty($pm['fecha_constitucion'])) _mjrReq(_mjrYmd(_mjrDate8($pm['fecha_constitucion'])) !== '', 'fecha_constitucion persona moral inválida.');
        if (!$isDueno) {
            $r = is_array($pm['representante_apoderado'] ?? null) ? $pm['representante_apoderado'] : [];
            _mjrReq(_mjrUp($r['nombre'] ?? '') !== '' && _mjrUp($r['apellido_paterno'] ?? '') !== '' && _mjrUp($r['apellido_materno'] ?? '') !== '', 'Representante/apoderado es obligatorio.');
            _mjrReq(_mjrValRFCFisica((string)($r['rfc'] ?? '')), 'RFC representante inválido.');
            _mjrReq(_mjrValCURP((string)($r['curp'] ?? '')), 'CURP representante inválida.');
            if (!empty($r['fecha_nacimiento'])) _mjrReq(_mjrYmd(_mjrDate8($r['fecha_nacimiento'])) !== '', 'fecha_nacimiento representante inválida.');
        }
        return;
    }

    $fi = $tp['fideicomiso'];
    _mjrReq(_mjrUp($fi['denominacion_razon'] ?? '') !== '', 'Fideicomiso requiere denominación.');
    _mjrReq(_mjrValRFCMoral((string)($fi['rfc'] ?? '')), 'RFC fideicomiso inválido.');
    if (!$isDueno) {
        $a = is_array($fi['apoderado_delegado'] ?? null) ? $fi['apoderado_delegado'] : [];
        _mjrReq(_mjrUp($a['nombre'] ?? '') !== '' && _mjrUp($a['apellido_paterno'] ?? '') !== '' && _mjrUp($a['apellido_materno'] ?? '') !== '', 'Apoderado/delegado de fideicomiso es obligatorio.');
        _mjrReq(_mjrValRFCFisica((string)($a['rfc'] ?? '')), 'RFC apoderado/delegado inválido.');
        _mjrReq(_mjrValCURP((string)($a['curp'] ?? '')), 'CURP apoderado/delegado inválida.');
        if (!empty($a['fecha_nacimiento'])) _mjrReq(_mjrYmd(_mjrDate8($a['fecha_nacimiento'])) !== '', 'fecha_nacimiento apoderado/delegado inválida.');
    }
}

function _mjrValidateDomicilio(PDO $pdo, array $td, array $cat): void
{
    _mjrReq(_mjrChoice($td, ['nacional', 'extranjero']) === 1, 'tipo_domicilio debe contener exactamente una opción.');
    if (isset($td['nacional'])) {
        $n = $td['nacional'];
        _mjrReq(_mjrUp($n['colonia'] ?? '') !== '' && _mjrUp($n['calle'] ?? '') !== '' && _mjrUp($n['numero_exterior'] ?? '') !== '', 'Domicilio nacional incompleto.');
        $cp = preg_replace('/\D+/', '', (string)($n['codigo_postal'] ?? ''));
        _mjrReq((bool)preg_match('/^\d{5}$/', $cp), 'Código postal nacional inválido.');
        return;
    }
    $x = $td['extranjero'];
    _mjrReq(_mjrValPais((string)($x['pais'] ?? '')), 'País domicilio extranjero inválido.');
    _mjrReq(_mjrUp($x['estado_provincia'] ?? '') !== '' && _mjrUp($x['ciudad_poblacion'] ?? '') !== '' && _mjrUp($x['colonia'] ?? '') !== '' && _mjrUp($x['calle'] ?? '') !== '' && _mjrUp($x['numero_exterior'] ?? '') !== '', 'Domicilio extranjero incompleto.');
    _mjrReq((bool)preg_match('/^[A-Z0-9]{4,12}$/', _mjrUp((string)($x['codigo_postal'] ?? ''))), 'Código postal extranjero inválido.');
}

function _mjrValidateBien(array $b): void
{
    $tipo = trim((string)($b['tipo_bien'] ?? ''));
    $unidad = trim((string)($b['unidad_comercializada'] ?? ''));
    $cantidad = (float)($b['cantidad_comercializada'] ?? 0);

    _mjrReq((bool)preg_match('/^\d{1,2}$/', $tipo), 'tipo_bien inválido.');
    _mjrReq((bool)preg_match('/^\d{1}$/', $unidad), 'unidad_comercializada inválida.');
    _mjrReq($cantidad > 0, 'cantidad_comercializada debe ser mayor a 0.');

}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_vi.php';
    require_once __DIR__ . '/../config/mjr_catalogos.php';
    require_once __DIR__ . '/../config/mjr_xml_helper.php';
} catch (Throwable $e) {
    _mjrErr('Error al inicializar: ' . $e->getMessage(), 500);
}
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_mjrReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_mjrReq(function_exists('userCanAccessMJR') && userCanAccessMJR($pdo, $userId), 'Sin permiso para registrar avisos MJR');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionVIActiva($pdo);
_mjrReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fracción VI no está activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_mjrReq(is_array($data), 'JSON inválido');
$idCliente = (int)($data['id_cliente'] ?? 0);
_mjrReq($idCliente > 0, 'id_cliente es obligatorio');

$informe = is_array($data['informe'][0] ?? null) ? $data['informe'][0] : [];
$sujeto = is_array($informe['sujeto_obligado'] ?? null) ? $informe['sujeto_obligado'] : [];
$aviso = is_array($informe['aviso'][0] ?? null) ? $informe['aviso'][0] : [];
$alerta = is_array($aviso['alerta'] ?? null) ? $aviso['alerta'] : [];

$mes = _mjrMonth6($informe['mes_reportado'] ?? ($data['mes_reportado'] ?? ''));
_mjrReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mes) && $mes >= '201309' && $mes <= date('Ym'), 'mes_reportado inválido');

$claveSO = _mjrUp($sujeto['clave_sujeto_obligado'] ?? ($data['clave_sujeto_obligado'] ?? ''));
_mjrReq(function_exists('mjrValidarClaveSO') && mjrValidarClaveSO($claveSO), 'clave_sujeto_obligado inválida');
_mjrReq(_mjrUp($sujeto['clave_actividad'] ?? ($data['clave_actividad'] ?? 'MJR')) === 'MJR', 'clave_actividad debe ser MJR');

$claveEntidad = _mjrUp($sujeto['clave_entidad_colegiada'] ?? ($data['clave_entidad_colegiada'] ?? ''));
if ($claveEntidad !== '') _mjrReq((bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $claveEntidad), 'clave_entidad_colegiada inválida');

$exento = trim((string)($sujeto['exento'] ?? ($data['exento'] ?? '')));
_mjrReq(in_array($exento, ['', '0', '1'], true), 'exento inválido');

$ref = _mjrUp($aviso['referencia_aviso'] ?? ($data['referencia_aviso'] ?? ''));
_mjrReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $ref), 'referencia_aviso inválida');
$prioridad = trim((string)($aviso['prioridad'] ?? ($data['prioridad'] ?? '1')));
_mjrReq(in_array($prioridad, ['1', '2'], true), 'prioridad inválida');

$tipoAlerta = trim((string)($alerta['tipo_alerta'] ?? ($data['tipo_alerta'] ?? '100')));
_mjrReq((bool)preg_match('/^\d{3,4}$/', $tipoAlerta), 'tipo_alerta inválido');
$descAlerta = _mjrUp($alerta['descripcion_alerta'] ?? ($data['descripcion_alerta'] ?? ''));

$mod = is_array($aviso['modificatorio'] ?? null) ? $aviso['modificatorio'] : [];
if (!empty($mod)) {
    _mjrReq((bool)preg_match('/^\d{4}\-\d{1,9}$/', trim((string)($mod['folio_modificacion'] ?? ''))), 'folio_modificacion inválido');
    _mjrReq(_mjrUp($mod['descripcion_modificacion'] ?? '') !== '', 'descripcion_modificacion obligatoria');
}

$personas = _mjrPickList($aviso['persona_aviso'] ?? [], 'tipo_persona');
_mjrReq(!empty($personas), 'Debe incluir al menos una persona_aviso');
foreach ($personas as $pa) {
    _mjrReq(is_array($pa), 'persona_aviso inválida');
    _mjrValidateTipoPersona((array)($pa['tipo_persona'] ?? []), $MJR_CATALOGOS, false);

    $td = is_array($pa['tipo_domicilio'] ?? null) ? $pa['tipo_domicilio'] : [];
    if (!empty($td)) _mjrValidateDomicilio($pdo, $td, $MJR_CATALOGOS);

    $tel = is_array($pa['telefono'] ?? null) ? $pa['telefono'] : [];
    if (!empty($tel)) {
        $cp = trim((string)($tel['clave_pais'] ?? ''));
        if ($cp !== '') {
            _mjrReq(_mjrValPais($cp), 'clave_pais teléfono inválida');
        }
        $nt = trim((string)($tel['numero_telefono'] ?? ''));
        if ($nt !== '') _mjrReq((bool)preg_match('/^\d{10,12}$/', $nt), 'numero_telefono inválido');
        $em = _mjrUp($tel['correo_electronico'] ?? '');
        if ($em !== '') _mjrReq((bool)preg_match('/^[A-Z\d\._\'\-]+@[A-Z\d_\'\-]+\.[A-Z\d\._\'\-]+$/', $em), 'correo_electronico inválido');
    }
}

$duenos = _mjrPickList($aviso['dueno_beneficiario'] ?? [], 'tipo_persona');
foreach ($duenos as $db) {
    _mjrReq(is_array($db), 'dueno_beneficiario inválido');
    _mjrValidateTipoPersona((array)($db['tipo_persona'] ?? []), $MJR_CATALOGOS, true);
}

$detalle = is_array($aviso['detalle_operaciones'][0] ?? null) ? $aviso['detalle_operaciones'][0] : [];
$ops = _mjrPickList($detalle['datos_operacion'] ?? [], 'fecha_operacion');
_mjrReq(!empty($ops), 'detalle_operaciones.datos_operacion es obligatorio');

$montoTotal = 0.0;
$fechaOpSistema = '';
$tipoOpSistema = '';
foreach ($ops as $i => $op) {
    _mjrReq(is_array($op), 'datos_operacion inválido');
    $f8 = _mjrDate8($op['fecha_operacion'] ?? '');
    $fymd = _mjrYmd($f8);
    _mjrReq($fymd !== '' && $fymd >= '2013-09-01' && $fymd <= date('Y-m-d'), 'fecha_operacion inválida en operación ' . ($i + 1));

    $cp = preg_replace('/\D+/', '', (string)($op['codigo_postal'] ?? ''));
    _mjrReq((bool)preg_match('/^\d{5}$/', $cp), 'codigo_postal inválido en operación ' . ($i + 1));

    $tipoOp = preg_replace('/\D+/', '', (string)($op['tipo_operacion'] ?? ''));
    _mjrReq($tipoOp !== '' && strlen($tipoOp) >= 3 && strlen($tipoOp) <= 4, 'tipo_operacion inválido en operación ' . ($i + 1));

    $bienes = _mjrPickList($op['datos_bien'] ?? [], 'tipo_bien');
    _mjrReq(!empty($bienes), 'datos_bien es obligatorio en operación ' . ($i + 1));
    foreach ($bienes as $b) {
        _mjrReq(is_array($b), 'Elemento datos_bien inválido en operación ' . ($i + 1));
        _mjrValidateBien($b);
    }

    $liqs = _mjrPickList($op['datos_liquidacion'] ?? [], 'monto_operacion');
    _mjrReq(!empty($liqs), 'datos_liquidacion es obligatorio en operación ' . ($i + 1));
    foreach ($liqs as $liq) {
        _mjrReq(is_array($liq), 'datos_liquidacion inválido');
        _mjrReq(_mjrYmd(_mjrDate8($liq['fecha_pago'] ?? '')) !== '', 'fecha_pago inválida en operación ' . ($i + 1));

        $fp = trim((string)($liq['forma_pago'] ?? ''));
        _mjrReq((bool)preg_match('/^[1-9]$/', $fp), 'forma_pago inválida en operación ' . ($i + 1));

        $inst = trim((string)($liq['instrumento_monetario'] ?? ''));
        _mjrReq((bool)preg_match('/^\d{1,2}$/', $inst), 'instrumento_monetario inválido en operación ' . ($i + 1));

        $mon = trim((string)($liq['moneda'] ?? ''));
        _mjrReq((bool)preg_match('/^\d{1,3}$/', $mon), 'moneda inválida en operación ' . ($i + 1));

        $monto = (float)($liq['monto_operacion'] ?? 0);
        _mjrReq($monto > 0, 'monto_operacion debe ser mayor a 0 en operación ' . ($i + 1));
        $montoTotal += $monto;
    }

    if ($fechaOpSistema === '') {
        $fechaOpSistema = $fymd;
        $tipoOpSistema = $tipoOp;
    }
}
_mjrReq($montoTotal > 0, 'Monto total inválido');

$idFraccion = getIdVulnerableFraccionVI($pdo);
_mjrReq((int)$idFraccion > 0, 'No se pudo resolver Fracción VI en cat_vulnerables');

$operacionData = [
    'id_cliente' => $idCliente,
    'monto' => $montoTotal,
    'fecha_operacion' => $fechaOpSistema,
    'id_fraccion' => $idFraccion,
    'tipo_operacion' => 'VI:' . ($tipoOpSistema ?: '0000'),
    'es_sospechosa' => $data['es_sospechosa'] ?? 0,
    'fecha_conocimiento_sospecha' => $data['fecha_conocimiento_sospecha'] ?? null,
    'match_listas_restringidas' => $data['match_listas_restringidas'] ?? 0,
    'fecha_conocimiento_match' => $data['fecha_conocimiento_match'] ?? null,
    'umbral_identificacion_uma_override' => getUmbralIdentificacionMetalesJoyas(),
    'umbral_aviso_uma_override' => getUmbralAvisoMetalesJoyas(),
    'umbral_acumulacion_uma_override' => getUmbralAvisoMetalesJoyas(),
];

$result = registrarOperacionPLD($pdo, $operacionData);
_mjrReq(($result['success'] ?? false), $result['message'] ?? 'No fue posible registrar');
$idOperacion = (int)($result['id_operacion'] ?? 0);
_mjrReq($idOperacion > 0, 'No fue posible obtener id_operacion');

$payloadXml = [
    'informe' => [[
        'mes_reportado' => $mes,
        'sujeto_obligado' => [
            'clave_entidad_colegiada' => $claveEntidad,
            'clave_sujeto_obligado' => $claveSO,
            'clave_actividad' => 'MJR',
            'exento' => ($exento === '1') ? '1' : '',
        ],
        'aviso' => [[
            'referencia_aviso' => $ref,
            'modificatorio' => !empty($mod) ? [
                'folio_modificacion' => (string)($mod['folio_modificacion'] ?? ''),
                'descripcion_modificacion' => (string)($mod['descripcion_modificacion'] ?? ''),
            ] : null,
            'prioridad' => $prioridad,
            'alerta' => ['tipo_alerta' => $tipoAlerta, 'descripcion_alerta' => $descAlerta],
            'persona_aviso' => $personas,
            'dueno_beneficiario' => $duenos,
            'detalle_operaciones' => [['datos_operacion' => $ops]],
        ]],
    ]],
];

$gen = generateMJRXml($payloadXml);
$xml = (string)($gen['xml'] ?? '');
_mjrReq($xml !== '', 'No se pudo generar XML MJR');
$xmlErrors = $gen['errors'] ?? [];
$xmlNombre = 'mjr_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
try {
    $st = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
    $st->execute([$xml, $xmlNombre, $idOperacion]);
} catch (Throwable $e) {
    error_log('registrar_aviso_mjr xml update: ' . $e->getMessage());
}

try {
    if (function_exists('logChange')) {
        logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_MJR', 'operaciones_pld', $idOperacion, null, [
            'id_cliente' => $idCliente,
            'tipo_operacion' => $tipoOpSistema,
            'monto' => $montoTotal,
            'umbral_identificacion_uma' => getUmbralIdentificacionMetalesJoyas(),
            'umbral_aviso_uma' => getUmbralAvisoMetalesJoyas(),
            'xml_errors' => $xmlErrors,
        ]);
    }
} catch (Throwable $e) {
    error_log('registrar_aviso_mjr bitacora: ' . $e->getMessage());
}

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso MJR registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => true,
    'xml_nombre' => $xmlNombre,
    'xml_advertencia' => !empty($xmlErrors) ? implode('; ', $xmlErrors) : null,
], JSON_UNESCAPED_UNICODE);
