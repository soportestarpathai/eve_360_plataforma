<?php
/**
 * API: Registrar Aviso MPC (Fracción IV - Mutuo, Préstamo o Crédito)
 * Alineado a XSD MPC.
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _mpcErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function _mpcUp($v): string { return mb_strtoupper(trim((string)$v), 'UTF-8'); }
function _mpcDate8($v): string { $x = preg_replace('/\D+/', '', (string)$v); return strlen($x) === 8 ? $x : ''; }
function _mpcMonth6($v): string { return substr(preg_replace('/\D+/', '', (string)$v), 0, 6); }
function _mpcYmd(string $d8): string
{
    if (!preg_match('/^\d{8}$/', $d8)) return '';
    $y = (int)substr($d8, 0, 4);
    $m = (int)substr($d8, 4, 2);
    $d = (int)substr($d8, 6, 2);
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
}
function _mpcReq(bool $ok, string $msg): void { if (!$ok) _mpcErr($msg, 400); }
function _mpcHas(array $cat, $k): bool { $k = trim((string)$k); return $k !== '' && array_key_exists($k, $cat); }
function _mpcChoice(array $node, array $keys): int { $n = 0; foreach ($keys as $k) if (isset($node[$k]) && is_array($node[$k])) $n++; return $n; }
function _mpcPickList($raw, string $marker): array { return (is_array($raw) && isset($raw[$marker])) ? [$raw] : (is_array($raw) ? $raw : []); }

function _mpcTableExists(PDO $pdo, string $table): bool
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

function _mpcCpSepomex(PDO $pdo, string $cp): bool
{
    if (!preg_match('/^\d{5}$/', $cp)) return false;
    if (!_mpcTableExists($pdo, 'cat_sepomex')) return true;

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

function _mpcValRFCFisica(string $v): bool { return $v === '' || (bool)preg_match('/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/u', _mpcUp($v)); }
function _mpcValRFCMoral(string $v): bool { return $v === '' || (bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', _mpcUp($v)); }
function _mpcValCURP(string $v): bool { return $v === '' || (bool)preg_match('/^[A-Z]{4}\d{6}[MH][A-Z]{5}[0-9A-Z]{2}$/', _mpcUp($v)); }
function _mpcValPais(string $v): bool { return (bool)preg_match('/^[A-Z]{2}$/', _mpcUp($v)); }

function _mpcValidateTipoPersona(array $tp, array $cat, bool $isDueno = false): void
{
    _mpcReq(_mpcChoice($tp, ['persona_fisica', 'persona_moral', 'fideicomiso']) === 1, 'tipo_persona debe contener exactamente una opción.');

    if (isset($tp['persona_fisica'])) {
        $pf = $tp['persona_fisica'];
        _mpcReq(_mpcUp($pf['nombre'] ?? '') !== '' && _mpcUp($pf['apellido_paterno'] ?? '') !== '' && _mpcUp($pf['apellido_materno'] ?? '') !== '', 'Persona física requiere nombre y apellidos.');
        _mpcReq(_mpcValPais((string)($pf['pais_nacionalidad'] ?? '')), 'País nacionalidad persona física inválido.');
        if (!empty($cat['pais'])) _mpcReq(_mpcHas($cat['pais'], (string)($pf['pais_nacionalidad'] ?? '')), 'País nacionalidad persona física fuera de catálogo.');

        $act = trim((string)($pf['actividad_economica'] ?? ''));
        if (!$isDueno) _mpcReq((bool)preg_match('/^\d{7}$/', $act), 'Persona física requiere actividad_economica de 7 dígitos.');
        if ($act !== '' && !empty($cat['actividad_economica'])) _mpcReq(_mpcHas($cat['actividad_economica'], $act), 'actividad_economica fuera de catálogo.');

        _mpcReq(_mpcValRFCFisica((string)($pf['rfc'] ?? '')), 'RFC persona física inválido.');
        _mpcReq(_mpcValCURP((string)($pf['curp'] ?? '')), 'CURP persona física inválida.');
        if (!empty($pf['fecha_nacimiento'])) _mpcReq(_mpcYmd(_mpcDate8($pf['fecha_nacimiento'])) !== '', 'fecha_nacimiento persona física inválida.');
        return;
    }

    if (isset($tp['persona_moral'])) {
        $pm = $tp['persona_moral'];
        _mpcReq(_mpcUp($pm['denominacion_razon'] ?? '') !== '', 'Persona moral requiere denominación o razón social.');
        _mpcReq(_mpcValPais((string)($pm['pais_nacionalidad'] ?? '')), 'País nacionalidad persona moral inválido.');
        if (!empty($cat['pais'])) _mpcReq(_mpcHas($cat['pais'], (string)($pm['pais_nacionalidad'] ?? '')), 'País nacionalidad persona moral fuera de catálogo.');

        $g = trim((string)($pm['giro_mercantil'] ?? ''));
        if (!$isDueno) _mpcReq((bool)preg_match('/^\d{7}$/', $g), 'Persona moral requiere giro_mercantil de 7 dígitos.');
        if ($g !== '' && !empty($cat['giro_mercantil'])) _mpcReq(_mpcHas($cat['giro_mercantil'], $g), 'giro_mercantil fuera de catálogo.');

        _mpcReq(_mpcValRFCMoral((string)($pm['rfc'] ?? '')), 'RFC persona moral inválido.');
        if (!empty($pm['fecha_constitucion'])) _mpcReq(_mpcYmd(_mpcDate8($pm['fecha_constitucion'])) !== '', 'fecha_constitucion persona moral inválida.');

        if (!$isDueno) {
            $r = is_array($pm['representante_apoderado'] ?? null) ? $pm['representante_apoderado'] : [];
            _mpcReq(_mpcUp($r['nombre'] ?? '') !== '' && _mpcUp($r['apellido_paterno'] ?? '') !== '' && _mpcUp($r['apellido_materno'] ?? '') !== '', 'Representante/apoderado es obligatorio.');
            _mpcReq(_mpcValRFCFisica((string)($r['rfc'] ?? '')), 'RFC representante inválido.');
            _mpcReq(_mpcValCURP((string)($r['curp'] ?? '')), 'CURP representante inválida.');
            if (!empty($r['fecha_nacimiento'])) _mpcReq(_mpcYmd(_mpcDate8($r['fecha_nacimiento'])) !== '', 'fecha_nacimiento representante inválida.');
        }
        return;
    }

    $fi = $tp['fideicomiso'];
    _mpcReq(_mpcUp($fi['denominacion_razon'] ?? '') !== '', 'Fideicomiso requiere denominación.');
    _mpcReq(_mpcValRFCMoral((string)($fi['rfc'] ?? '')), 'RFC fideicomiso inválido.');
    if (!$isDueno) {
        $a = is_array($fi['apoderado_delegado'] ?? null) ? $fi['apoderado_delegado'] : [];
        _mpcReq(_mpcUp($a['nombre'] ?? '') !== '' && _mpcUp($a['apellido_paterno'] ?? '') !== '' && _mpcUp($a['apellido_materno'] ?? '') !== '', 'Apoderado/delegado de fideicomiso es obligatorio.');
        _mpcReq(_mpcValRFCFisica((string)($a['rfc'] ?? '')), 'RFC apoderado/delegado inválido.');
        _mpcReq(_mpcValCURP((string)($a['curp'] ?? '')), 'CURP apoderado/delegado inválida.');
        if (!empty($a['fecha_nacimiento'])) _mpcReq(_mpcYmd(_mpcDate8($a['fecha_nacimiento'])) !== '', 'fecha_nacimiento apoderado/delegado inválida.');
    }
}

function _mpcValidateTipoGarante(array $tp): void
{
    _mpcReq(_mpcChoice($tp, ['persona_fisica', 'persona_moral', 'fideicomiso']) === 1, 'tipo_persona de garante debe contener exactamente una opción.');

    if (isset($tp['persona_fisica'])) {
        $pf = $tp['persona_fisica'];
        _mpcReq(_mpcUp($pf['nombre'] ?? '') !== '' && _mpcUp($pf['apellido_paterno'] ?? '') !== '' && _mpcUp($pf['apellido_materno'] ?? '') !== '', 'Garante persona física requiere nombre y apellidos.');
        _mpcReq(_mpcValRFCFisica((string)($pf['rfc'] ?? '')), 'RFC garante persona física inválido.');
        _mpcReq(_mpcValCURP((string)($pf['curp'] ?? '')), 'CURP garante persona física inválida.');
        if (!empty($pf['fecha_nacimiento'])) _mpcReq(_mpcYmd(_mpcDate8($pf['fecha_nacimiento'])) !== '', 'fecha_nacimiento garante persona física inválida.');
        return;
    }

    if (isset($tp['persona_moral'])) {
        $pm = $tp['persona_moral'];
        _mpcReq(_mpcUp($pm['denominacion_razon'] ?? '') !== '', 'Garante persona moral requiere denominación.');
        _mpcReq(_mpcValRFCMoral((string)($pm['rfc'] ?? '')), 'RFC garante persona moral inválido.');
        if (!empty($pm['fecha_constitucion'])) _mpcReq(_mpcYmd(_mpcDate8($pm['fecha_constitucion'])) !== '', 'fecha_constitucion garante persona moral inválida.');
        return;
    }

    $fi = $tp['fideicomiso'];
    _mpcReq(_mpcUp($fi['denominacion_razon'] ?? '') !== '', 'Garante fideicomiso requiere denominación.');
    _mpcReq(_mpcValRFCMoral((string)($fi['rfc'] ?? '')), 'RFC garante fideicomiso inválido.');
}

function _mpcValidateDomicilio(PDO $pdo, array $td, array $cat): void
{
    _mpcReq(_mpcChoice($td, ['nacional', 'extranjero']) === 1, 'tipo_domicilio debe contener exactamente una opción.');
    if (isset($td['nacional'])) {
        $n = $td['nacional'];
        _mpcReq(_mpcUp($n['colonia'] ?? '') !== '' && _mpcUp($n['calle'] ?? '') !== '' && _mpcUp($n['numero_exterior'] ?? '') !== '', 'Domicilio nacional incompleto.');
        $cp = preg_replace('/\D+/', '', (string)($n['codigo_postal'] ?? ''));
        _mpcReq((bool)preg_match('/^\d{5}$/', $cp), 'Código postal nacional inválido.');
        _mpcReq(_mpcCpSepomex($pdo, $cp), 'Código postal nacional no existe en SEPOMEX.');
        return;
    }

    $x = $td['extranjero'];
    _mpcReq(_mpcValPais((string)($x['pais'] ?? '')), 'País domicilio extranjero inválido.');
    if (!empty($cat['pais'])) _mpcReq(_mpcHas($cat['pais'], (string)($x['pais'] ?? '')), 'País domicilio extranjero fuera de catálogo.');
    _mpcReq(_mpcUp($x['estado_provincia'] ?? '') !== '' && _mpcUp($x['ciudad_poblacion'] ?? '') !== '' && _mpcUp($x['colonia'] ?? '') !== '' && _mpcUp($x['calle'] ?? '') !== '' && _mpcUp($x['numero_exterior'] ?? '') !== '', 'Domicilio extranjero incompleto.');
    _mpcReq((bool)preg_match('/^[A-Z0-9]{4,12}$/', _mpcUp((string)($x['codigo_postal'] ?? ''))), 'Código postal extranjero inválido.');
}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_iv.php';
    require_once __DIR__ . '/../config/mpc_catalogos.php';
    require_once __DIR__ . '/../config/mpc_xml_helper.php';
} catch (Throwable $e) {
    _mpcErr('Error al inicializar: ' . $e->getMessage(), 500);
}

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_mpcReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_mpcReq(function_exists('userCanAccessMPC') && userCanAccessMPC($pdo, $userId), 'Sin permiso para registrar avisos MPC');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionIVActiva($pdo);
_mpcReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fracción IV no está activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_mpcReq(is_array($data), 'JSON inválido');
$idCliente = (int)($data['id_cliente'] ?? 0);
_mpcReq($idCliente > 0, 'id_cliente es obligatorio');

$informe = is_array($data['informe'][0] ?? null) ? $data['informe'][0] : [];
$sujeto = is_array($informe['sujeto_obligado'] ?? null) ? $informe['sujeto_obligado'] : [];
$aviso = is_array($informe['aviso'][0] ?? null) ? $informe['aviso'][0] : [];
$alerta = is_array($aviso['alerta'] ?? null) ? $aviso['alerta'] : [];

$mes = _mpcMonth6($informe['mes_reportado'] ?? ($data['mes_reportado'] ?? ''));
_mpcReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mes) && $mes >= '201309' && $mes <= date('Ym'), 'mes_reportado inválido');

$claveSO = _mpcUp($sujeto['clave_sujeto_obligado'] ?? ($data['clave_sujeto_obligado'] ?? ''));
_mpcReq(function_exists('mpcValidarClaveSO') && mpcValidarClaveSO($claveSO), 'clave_sujeto_obligado inválida');
_mpcReq(_mpcUp($sujeto['clave_actividad'] ?? ($data['clave_actividad'] ?? 'MPC')) === 'MPC', 'clave_actividad debe ser MPC');

$claveEntidad = _mpcUp($sujeto['clave_entidad_colegiada'] ?? ($data['clave_entidad_colegiada'] ?? ''));
if ($claveEntidad !== '') _mpcReq((bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $claveEntidad), 'clave_entidad_colegiada inválida');

$exento = trim((string)($sujeto['exento'] ?? ($data['exento'] ?? '')));
_mpcReq(in_array($exento, ['', '0', '1'], true), 'exento inválido');

$ref = _mpcUp($aviso['referencia_aviso'] ?? ($data['referencia_aviso'] ?? ''));
_mpcReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $ref), 'referencia_aviso inválida');
$prioridad = trim((string)($aviso['prioridad'] ?? ($data['prioridad'] ?? '1')));
_mpcReq(in_array($prioridad, ['1', '2'], true), 'prioridad inválida');

$tipoAlerta = trim((string)($alerta['tipo_alerta'] ?? ($data['tipo_alerta'] ?? '100')));
_mpcReq((bool)preg_match('/^\d{3,4}$/', $tipoAlerta), 'tipo_alerta inválido');
if (!empty($MPC_CATALOGOS['tipo_alerta'])) _mpcReq(_mpcHas($MPC_CATALOGOS['tipo_alerta'], $tipoAlerta), 'tipo_alerta fuera de catálogo');
$descAlerta = _mpcUp($alerta['descripcion_alerta'] ?? ($data['descripcion_alerta'] ?? ''));
if ($tipoAlerta === '9999') _mpcReq($descAlerta !== '', 'descripcion_alerta obligatoria cuando tipo_alerta=9999');

$mod = is_array($aviso['modificatorio'] ?? null) ? $aviso['modificatorio'] : [];
if (!empty($mod)) {
    _mpcReq((bool)preg_match('/^\d{4}\-\d{1,9}$/', trim((string)($mod['folio_modificacion'] ?? ''))), 'folio_modificacion inválido');
    _mpcReq(_mpcUp($mod['descripcion_modificacion'] ?? '') !== '', 'descripcion_modificacion obligatoria');
}

$personas = _mpcPickList($aviso['persona_aviso'] ?? [], 'tipo_persona');
_mpcReq(!empty($personas), 'Debe incluir al menos una persona_aviso');
foreach ($personas as $pa) {
    _mpcReq(is_array($pa), 'persona_aviso inválida');
    _mpcValidateTipoPersona((array)($pa['tipo_persona'] ?? []), $MPC_CATALOGOS, false);

    $td = is_array($pa['tipo_domicilio'] ?? null) ? $pa['tipo_domicilio'] : [];
    if (!empty($td)) _mpcValidateDomicilio($pdo, $td, $MPC_CATALOGOS);

    $tel = is_array($pa['telefono'] ?? null) ? $pa['telefono'] : [];
    if (!empty($tel)) {
        $cp = trim((string)($tel['clave_pais'] ?? ''));
        if ($cp !== '') {
            _mpcReq(_mpcValPais($cp), 'clave_pais teléfono inválida');
            if (!empty($MPC_CATALOGOS['pais'])) _mpcReq(_mpcHas($MPC_CATALOGOS['pais'], $cp), 'clave_pais teléfono fuera de catálogo');
        }
        $nt = trim((string)($tel['numero_telefono'] ?? ''));
        if ($nt !== '') _mpcReq((bool)preg_match('/^\d{10,12}$/', $nt), 'numero_telefono inválido');
        $em = _mpcUp($tel['correo_electronico'] ?? '');
        if ($em !== '') _mpcReq((bool)preg_match('/^[A-Z\d\._\'\-]+@[A-Z\d_\'\-]+\.[A-Z\d\._\'\-]+$/', $em), 'correo_electronico inválido');
    }
}

$duenos = _mpcPickList($aviso['dueno_beneficiario'] ?? [], 'tipo_persona');
foreach ($duenos as $db) {
    _mpcReq(is_array($db), 'dueno_beneficiario inválido');
    _mpcValidateTipoPersona((array)($db['tipo_persona'] ?? []), $MPC_CATALOGOS, true);
}

$detalle = is_array($aviso['detalle_operaciones'][0] ?? null) ? $aviso['detalle_operaciones'][0] : [];
$ops = _mpcPickList($detalle['datos_operacion'] ?? [], 'fecha_operacion');
_mpcReq(!empty($ops), 'detalle_operaciones.datos_operacion es obligatorio');

$montoTotal = 0.0;
$fechaOpSistema = '';
$tipoOpSistema = '';
foreach ($ops as $i => $op) {
    _mpcReq(is_array($op), 'datos_operacion inválido');

    $f8 = _mpcDate8($op['fecha_operacion'] ?? '');
    $fymd = _mpcYmd($f8);
    _mpcReq($fymd !== '' && $fymd >= '2013-09-01' && $fymd <= date('Y-m-d'), 'fecha_operacion inválida en operación ' . ($i + 1));

    $cp = preg_replace('/\D+/', '', (string)($op['codigo_postal'] ?? ''));
    _mpcReq((bool)preg_match('/^\d{5}$/', $cp), 'codigo_postal inválido en operación ' . ($i + 1));
    _mpcReq(_mpcCpSepomex($pdo, $cp), 'codigo_postal no existe en SEPOMEX en operación ' . ($i + 1));

    $tipoOp = preg_replace('/\D+/', '', (string)($op['tipo_operacion'] ?? ''));
    _mpcReq(in_array($tipoOp, ['401', '402'], true), 'tipo_operacion inválido en operación ' . ($i + 1));
    if (!empty($MPC_CATALOGOS['tipo_operacion'])) _mpcReq(_mpcHas($MPC_CATALOGOS['tipo_operacion'], $tipoOp), 'tipo_operacion fuera de catálogo en operación ' . ($i + 1));

    $garantias = _mpcPickList($op['datos_garantia'] ?? [], 'tipo_garantia');
    if ($tipoOp === '402') _mpcReq(!empty($garantias), 'datos_garantia es obligatorio para tipo_operacion=402 en operación ' . ($i + 1));
    if ($tipoOp === '401') _mpcReq(empty($garantias), 'datos_garantia no aplica para tipo_operacion=401 en operación ' . ($i + 1));

    foreach ($garantias as $g) {
        _mpcReq(is_array($g), 'datos_garantia inválido en operación ' . ($i + 1));
        $tg = trim((string)($g['tipo_garantia'] ?? ''));
        _mpcReq((bool)preg_match('/^\d{1,2}$/', $tg), 'tipo_garantia inválido en operación ' . ($i + 1));
        if (!empty($MPC_CATALOGOS['tipo_garantia'])) _mpcReq(_mpcHas($MPC_CATALOGOS['tipo_garantia'], $tg), 'tipo_garantia fuera de catálogo en operación ' . ($i + 1));

        $dbm = is_array($g['datos_bien_mutuo'] ?? null) ? $g['datos_bien_mutuo'] : [];
        if ($tg === '2') _mpcReq(!empty($dbm), 'datos_bien_mutuo es obligatorio para garantía inmueble (tipo_garantia=2)');

        if (!empty($dbm)) {
            _mpcReq(_mpcChoice($dbm, ['datos_inmueble', 'datos_otro']) === 1, 'datos_bien_mutuo debe contener exactamente una opción.');
            if (isset($dbm['datos_inmueble'])) {
                $di = $dbm['datos_inmueble'];
                $ti = trim((string)($di['tipo_inmueble'] ?? ''));
                _mpcReq((bool)preg_match('/^\d{1,2}$/', $ti), 'tipo_inmueble inválido.');
                if (!empty($MPC_CATALOGOS['tipo_inmueble'])) _mpcReq(_mpcHas($MPC_CATALOGOS['tipo_inmueble'], $ti), 'tipo_inmueble fuera de catálogo.');

                $vr = (float)($di['valor_referencia'] ?? 0);
                _mpcReq($vr > 0, 'valor_referencia debe ser mayor a 0.');

                $cpDi = preg_replace('/\D+/', '', (string)($di['codigo_postal'] ?? ''));
                _mpcReq((bool)preg_match('/^\d{5}$/', $cpDi), 'codigo_postal de datos_inmueble inválido.');
                _mpcReq(_mpcCpSepomex($pdo, $cpDi), 'codigo_postal de datos_inmueble no existe en SEPOMEX.');

                $fr = _mpcUp($di['folio_real'] ?? '');
                _mpcReq($fr !== '' && (bool)preg_match('/^[A-Z0-9\-_]{1,200}$/', $fr), 'folio_real inválido.');
            } else {
                $otro = is_array($dbm['datos_otro'] ?? null) ? $dbm['datos_otro'] : [];
                _mpcReq(_mpcUp($otro['descripcion_garantia'] ?? '') !== '', 'descripcion_garantia es obligatoria en datos_otro.');
            }
        }

        $tgp = is_array($g['tipo_persona'] ?? null) ? $g['tipo_persona'] : [];
        if (!empty($tgp)) _mpcValidateTipoGarante($tgp);
    }

    $liqs = _mpcPickList($op['datos_liquidacion'] ?? [], 'monto_operacion');
    _mpcReq(!empty($liqs), 'datos_liquidacion es obligatorio en operación ' . ($i + 1));
    foreach ($liqs as $liq) {
        _mpcReq(is_array($liq), 'datos_liquidacion inválido en operación ' . ($i + 1));
        _mpcReq(_mpcYmd(_mpcDate8($liq['fecha_disposicion'] ?? '')) !== '', 'fecha_disposicion inválida en operación ' . ($i + 1));

        $inst = trim((string)($liq['instrumento_monetario'] ?? ''));
        _mpcReq((bool)preg_match('/^\d{1,2}$/', $inst), 'instrumento_monetario inválido en operación ' . ($i + 1));
        if (!empty($MPC_CATALOGOS['instrumento_monetario'])) _mpcReq(_mpcHas($MPC_CATALOGOS['instrumento_monetario'], $inst), 'instrumento_monetario fuera de catálogo en operación ' . ($i + 1));

        $mon = trim((string)($liq['moneda'] ?? ''));
        _mpcReq((bool)preg_match('/^\d{1,3}$/', $mon), 'moneda inválida en operación ' . ($i + 1));
        if (!empty($MPC_CATALOGOS['moneda'])) _mpcReq(_mpcHas($MPC_CATALOGOS['moneda'], $mon), 'moneda fuera de catálogo en operación ' . ($i + 1));
        if (in_array($inst, ['13', '14'], true)) _mpcReq(((int)$mon >= 159 && (int)$mon <= 179), 'Para instrumento 13/14 la moneda debe estar entre 159 y 179');
        if (!in_array($inst, ['13', '14'], true)) _mpcReq(!((int)$mon >= 159 && (int)$mon <= 179), 'Monedas 159-179 solo aplican a instrumento 13/14');

        $monto = (float)($liq['monto_operacion'] ?? 0);
        _mpcReq($monto > 0, 'monto_operacion debe ser mayor a 0 en operación ' . ($i + 1));
        $montoTotal += $monto;
    }

    if ($fechaOpSistema === '') {
        $fechaOpSistema = $fymd;
        $tipoOpSistema = $tipoOp;
    }
}
_mpcReq($montoTotal > 0, 'Monto total inválido');

$idFraccion = getIdVulnerableFraccionIV($pdo);
_mpcReq((int)$idFraccion > 0, 'No se pudo resolver Fracción IV en cat_vulnerables');

$operacionData = [
    'id_cliente' => $idCliente,
    'monto' => $montoTotal,
    'fecha_operacion' => $fechaOpSistema,
    'id_fraccion' => $idFraccion,
    'tipo_operacion' => 'IV:' . ($tipoOpSistema ?: '0000'),
    'es_sospechosa' => $data['es_sospechosa'] ?? 0,
    'fecha_conocimiento_sospecha' => $data['fecha_conocimiento_sospecha'] ?? null,
    'match_listas_restringidas' => $data['match_listas_restringidas'] ?? 0,
    'fecha_conocimiento_match' => $data['fecha_conocimiento_match'] ?? null,
    'umbral_aviso_uma_override' => pldFraccionIVUmbralAviso(),
    'umbral_acumulacion_uma_override' => pldFraccionIVUmbralAviso(),
];

$result = registrarOperacionPLD($pdo, $operacionData);
_mpcReq(($result['success'] ?? false), $result['message'] ?? 'No fue posible registrar');
$idOperacion = (int)($result['id_operacion'] ?? 0);
_mpcReq($idOperacion > 0, 'No fue posible obtener id_operacion');

$payloadXml = [
    'informe' => [[
        'mes_reportado' => $mes,
        'sujeto_obligado' => [
            'clave_entidad_colegiada' => $claveEntidad,
            'clave_sujeto_obligado' => $claveSO,
            'clave_actividad' => 'MPC',
            'exento' => ($exento === '1') ? '1' : '',
        ],
        'aviso' => [[
            'referencia_aviso' => $ref,
            'modificatorio' => !empty($mod) ? [
                'folio_modificacion' => (string)($mod['folio_modificacion'] ?? ''),
                'descripcion_modificacion' => (string)($mod['descripcion_modificacion'] ?? ''),
            ] : null,
            'prioridad' => $prioridad,
            'alerta' => [
                'tipo_alerta' => $tipoAlerta,
                'descripcion_alerta' => $descAlerta,
            ],
            'persona_aviso' => $personas,
            'dueno_beneficiario' => $duenos,
            'detalle_operaciones' => [[
                'datos_operacion' => $ops,
            ]],
        ]],
    ]],
];

$gen = generateMPCXml($payloadXml);
$xml = (string)($gen['xml'] ?? '');
_mpcReq($xml !== '', 'No se pudo generar XML MPC');
$xmlErrors = $gen['errors'] ?? [];
$xmlNombre = 'mpc_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
try {
    $st = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
    $st->execute([$xml, $xmlNombre, $idOperacion]);
} catch (Throwable $e) {
    error_log('registrar_aviso_mpc xml update: ' . $e->getMessage());
}

try {
    if (function_exists('logChange')) {
        logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_MPC', 'operaciones_pld', $idOperacion, null, [
            'id_cliente' => $idCliente,
            'tipo_operacion' => $tipoOpSistema,
            'monto' => $montoTotal,
            'umbral_identificacion' => 'SIEMPRE',
            'umbral_aviso_uma' => pldFraccionIVUmbralAviso(),
            'xml_errors' => $xmlErrors,
        ]);
    }
} catch (Throwable $e) {
    error_log('registrar_aviso_mpc bitacora: ' . $e->getMessage());
}

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso MPC registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => true,
    'xml_nombre' => $xmlNombre,
    'xml_advertencia' => !empty($xmlErrors) ? implode('; ', $xmlErrors) : null,
], JSON_UNESCAPED_UNICODE);

