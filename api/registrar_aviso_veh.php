<?php
/**
 * API: Registrar Aviso VEH (Fracción VIII - Vehículos)
 * Implementación alineada a instructivo VEH (estructura + reglas críticas).
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _vehErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function _vehUp($v): string { return mb_strtoupper(trim((string)$v), 'UTF-8'); }
function _vehDate8($v): string { $x = preg_replace('/\D+/', '', (string)$v); return strlen($x) === 8 ? $x : ''; }
function _vehMonth6($v): string { return substr(preg_replace('/\D+/', '', (string)$v), 0, 6); }
function _vehYmd(string $d8): string {
    if (!preg_match('/^\d{8}$/', $d8)) return '';
    $y=(int)substr($d8,0,4); $m=(int)substr($d8,4,2); $d=(int)substr($d8,6,2);
    return checkdate($m,$d,$y) ? sprintf('%04d-%02d-%02d',$y,$m,$d) : '';
}
function _vehPickList($raw, string $marker): array { return (is_array($raw) && isset($raw[$marker])) ? [$raw] : (is_array($raw) ? $raw : []); }
function _vehHas(array $cat, $k): bool { $k = trim((string)$k); return $k !== '' && array_key_exists($k, $cat); }
function _vehChoice(array $node, array $keys): int { $n=0; foreach ($keys as $k) if (isset($node[$k]) && is_array($node[$k])) $n++; return $n; }
function _vehReq(bool $ok, string $msg): void { if (!$ok) _vehErr($msg, 400); }
function _vehTipoVehiculoList($raw): array {
    $keys = ['datos_vehiculo_terrestre','datos_vehiculo_maritimo','datos_vehiculo_aereo'];
    if (!is_array($raw)) return [];
    foreach ($keys as $k) {
        if (isset($raw[$k]) && is_array($raw[$k])) return [$raw];
    }
    $out = [];
    foreach ($raw as $item) {
        if (is_array($item)) $out[] = $item;
    }
    return $out;
}

function _vehTableExists(PDO $pdo, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $cache[$table] = (bool)$stmt->fetch(PDO::FETCH_NUM);
    } catch (Throwable $e) { $cache[$table] = false; }
    return $cache[$table];
}

function _vehCpSepomex(PDO $pdo, string $cp): bool
{
    if (!preg_match('/^\d{5}$/', $cp)) return false;
    if (!_vehTableExists($pdo, 'cat_sepomex')) return true;
    static $cols = null;
    if ($cols === null) {
        $cols = [];
        try {
            $q = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cat_sepomex'");
            $all = array_map('strtolower', array_column($q->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
            foreach (['codigo_postal','d_codigo','cp','codigo_postal_sat','codigo_postal_sepomex'] as $c) {
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

function _vehValRFCFisica(string $v): bool { return $v === '' || (bool)preg_match('/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/u', _vehUp($v)); }
function _vehValRFCMoral(string $v): bool { return $v === '' || (bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', _vehUp($v)); }
function _vehValCURP(string $v): bool { return $v === '' || (bool)preg_match('/^[A-Z]{4}\d{6}[MH][A-Z]{5}[0-9A-Z]{2}$/', _vehUp($v)); }
function _vehValPais(string $v): bool { return (bool)preg_match('/^[A-Z]{2}$/', _vehUp($v)); }

function _vehValidateTipoPersona(array $tp, array $cat, bool $isDueno = false): void
{
    _vehReq(_vehChoice($tp, ['persona_fisica','persona_moral','fideicomiso']) === 1, 'tipo_persona debe contener exactamente una opción.');

    if (isset($tp['persona_fisica'])) {
        $pf = $tp['persona_fisica'];
        _vehReq(_vehUp($pf['nombre'] ?? '') !== '' && _vehUp($pf['apellido_paterno'] ?? '') !== '' && _vehUp($pf['apellido_materno'] ?? '') !== '', 'Persona física requiere nombre y apellidos.');
        _vehReq(_vehValPais((string)($pf['pais_nacionalidad'] ?? '')), 'País nacionalidad persona física inválido.');
        $act = trim((string)($pf['actividad_economica'] ?? ''));
        if (!$isDueno) _vehReq((bool)preg_match('/^\d{7}$/', $act), 'Persona física requiere actividad_economica de 7 dígitos.');
        if ($act !== '' && !empty($cat['actividad_economica'])) _vehReq(_vehHas($cat['actividad_economica'], $act), 'actividad_economica fuera de catálogo.');
        _vehReq(_vehValRFCFisica((string)($pf['rfc'] ?? '')), 'RFC persona física inválido.');
        _vehReq(_vehValCURP((string)($pf['curp'] ?? '')), 'CURP persona física inválida.');
        if (!empty($pf['fecha_nacimiento'])) _vehReq(_vehYmd(_vehDate8($pf['fecha_nacimiento'])) !== '', 'fecha_nacimiento persona física inválida.');
        return;
    }

    if (isset($tp['persona_moral'])) {
        $pm = $tp['persona_moral'];
        _vehReq(_vehUp($pm['denominacion_razon'] ?? '') !== '', 'Persona moral requiere denominación o razón social.');
        _vehReq(_vehValPais((string)($pm['pais_nacionalidad'] ?? '')), 'País nacionalidad persona moral inválido.');
        $g = trim((string)($pm['giro_mercantil'] ?? ''));
        if (!$isDueno) _vehReq((bool)preg_match('/^\d{7}$/', $g), 'Persona moral requiere giro_mercantil de 7 dígitos.');
        if ($g !== '' && !empty($cat['giro_mercantil'])) _vehReq(_vehHas($cat['giro_mercantil'], $g), 'giro_mercantil fuera de catálogo.');
        _vehReq(_vehValRFCMoral((string)($pm['rfc'] ?? '')), 'RFC persona moral inválido.');
        if (!empty($pm['fecha_constitucion'])) _vehReq(_vehYmd(_vehDate8($pm['fecha_constitucion'])) !== '', 'fecha_constitucion persona moral inválida.');
        if (!$isDueno) {
            $r = is_array($pm['representante_apoderado'] ?? null) ? $pm['representante_apoderado'] : [];
            _vehReq(_vehUp($r['nombre'] ?? '') !== '' && _vehUp($r['apellido_paterno'] ?? '') !== '' && _vehUp($r['apellido_materno'] ?? '') !== '', 'Representante/apoderado es obligatorio.');
            _vehReq(_vehValRFCFisica((string)($r['rfc'] ?? '')), 'RFC representante inválido.');
            _vehReq(_vehValCURP((string)($r['curp'] ?? '')), 'CURP representante inválida.');
            if (!empty($r['fecha_nacimiento'])) _vehReq(_vehYmd(_vehDate8($r['fecha_nacimiento'])) !== '', 'fecha_nacimiento representante inválida.');
        }
        return;
    }

    $fi = $tp['fideicomiso'];
    _vehReq(_vehUp($fi['denominacion_razon'] ?? '') !== '', 'Fideicomiso requiere denominación.');
    _vehReq(_vehValRFCMoral((string)($fi['rfc'] ?? '')), 'RFC fideicomiso inválido.');
    if (!$isDueno) {
        $a = is_array($fi['apoderado_delegado'] ?? null) ? $fi['apoderado_delegado'] : [];
        _vehReq(_vehUp($a['nombre'] ?? '') !== '' && _vehUp($a['apellido_paterno'] ?? '') !== '' && _vehUp($a['apellido_materno'] ?? '') !== '', 'Apoderado/delegado de fideicomiso es obligatorio.');
        _vehReq(_vehValRFCFisica((string)($a['rfc'] ?? '')), 'RFC apoderado/delegado inválido.');
        _vehReq(_vehValCURP((string)($a['curp'] ?? '')), 'CURP apoderado/delegado inválida.');
        if (!empty($a['fecha_nacimiento'])) _vehReq(_vehYmd(_vehDate8($a['fecha_nacimiento'])) !== '', 'fecha_nacimiento apoderado/delegado inválida.');
    }
}

function _vehValidateDomicilio(PDO $pdo, array $td, array $cat): void
{
    _vehReq(_vehChoice($td, ['nacional','extranjero']) === 1, 'tipo_domicilio debe contener exactamente una opción.');
    if (isset($td['nacional'])) {
        $n = $td['nacional'];
        _vehReq(_vehUp($n['colonia'] ?? '') !== '' && _vehUp($n['calle'] ?? '') !== '' && _vehUp($n['numero_exterior'] ?? '') !== '', 'Domicilio nacional incompleto.');
        $cp = preg_replace('/\D+/', '', (string)($n['codigo_postal'] ?? ''));
        _vehReq((bool)preg_match('/^\d{5}$/', $cp), 'Código postal nacional inválido.');
        _vehReq(_vehCpSepomex($pdo, $cp), 'Código postal nacional no existe en SEPOMEX.');
        return;
    }
    $x = $td['extranjero'];
    _vehReq(_vehValPais((string)($x['pais'] ?? '')), 'País domicilio extranjero inválido.');
    if (!empty($cat['pais'])) _vehReq(_vehHas($cat['pais'], (string)($x['pais'] ?? '')), 'País domicilio extranjero fuera de catálogo.');
    _vehReq(_vehUp($x['estado_provincia'] ?? '') !== '' && _vehUp($x['ciudad_poblacion'] ?? '') !== '' && _vehUp($x['colonia'] ?? '') !== '' && _vehUp($x['calle'] ?? '') !== '' && _vehUp($x['numero_exterior'] ?? '') !== '', 'Domicilio extranjero incompleto.');
    _vehReq((bool)preg_match('/^[A-Z0-9]{4,12}$/', _vehUp((string)($x['codigo_postal'] ?? ''))), 'Código postal extranjero inválido.');
}

function _vehValidateVehiculo(array $op, array $cat): void
{
    $vehiculos = _vehTipoVehiculoList($op['tipo_vehiculo'] ?? null);
    _vehReq(!empty($vehiculos), 'tipo_vehiculo es obligatorio.');

    $checkBase = function(array $v, string $ctx): void {
        _vehReq(_vehUp($v['marca_fabricante'] ?? '') !== '' && _vehUp($v['modelo'] ?? '') !== '' && preg_match('/^\d{4}$/', (string)($v['anio'] ?? '')), "{$ctx} requiere marca_fabricante, modelo y anio válido.");
    };
    $checkBlind = function(array $v, array $cat, string $ctx): void {
        $b = trim((string)($v['nivel_blindaje'] ?? ''));
        _vehReq((bool)preg_match('/^[1-9]$/', $b), "{$ctx}: nivel_blindaje inválido.");
        if (!empty($cat['tipo_blindaje'])) _vehReq(_vehHas($cat['tipo_blindaje'], $b), "{$ctx}: nivel_blindaje fuera de catálogo.");
    };

    foreach ($vehiculos as $idx => $tv) {
        _vehReq(is_array($tv), 'tipo_vehiculo inválido.');
        _vehReq(_vehChoice($tv, ['datos_vehiculo_terrestre','datos_vehiculo_maritimo','datos_vehiculo_aereo']) === 1, 'tipo_vehiculo debe tener exactamente una rama por elemento.');
        $ctxItem = 'Vehículo #' . ($idx + 1);

        if (isset($tv['datos_vehiculo_terrestre'])) {
            $v = $tv['datos_vehiculo_terrestre']; $checkBase($v, "{$ctxItem} terrestre");
            $vin = _vehUp((string)($v['vin'] ?? '')); $rep = _vehUp((string)($v['repuve'] ?? '')); $pla = _vehUp((string)($v['placas'] ?? ''));
            _vehReq(!($vin === '' && $rep === '' && $pla === ''), "{$ctxItem} terrestre requiere al menos VIN, REPUVE o placas.");
            if ($vin !== '') _vehReq((bool)preg_match('/^[A-Z0-9\-_]{17}$/', $vin), "{$ctxItem}: VIN inválido.");
            if ($rep !== '') _vehReq((bool)preg_match('/^[A-Z0-9\-_]{8}$/', $rep), "{$ctxItem}: REPUVE inválido.");
            if ($pla !== '') _vehReq((bool)preg_match('/^[A-Z0-9\-_]{1,12}$/', $pla), "{$ctxItem}: placas inválidas.");
            $checkBlind($v, $cat, "{$ctxItem} terrestre");
            continue;
        }

        if (isset($tv['datos_vehiculo_maritimo'])) {
            $v = $tv['datos_vehiculo_maritimo']; $checkBase($v, "{$ctxItem} marítimo");
            _vehReq((bool)preg_match('/^[A-Z0-9\-_]{1,20}$/', _vehUp((string)($v['numero_serie'] ?? ''))), "{$ctxItem}: número de serie marítimo inválido.");
            if (_vehUp((string)($v['bandera'] ?? '')) !== '') {
                _vehReq(_vehValPais((string)($v['bandera'] ?? '')), "{$ctxItem}: bandera marítima inválida.");
                if (!empty($cat['pais'])) _vehReq(_vehHas($cat['pais'], (string)($v['bandera'] ?? '')), "{$ctxItem}: bandera marítima fuera de catálogo.");
            }
            if (_vehUp((string)($v['matricula'] ?? '')) !== '') _vehReq((bool)preg_match('/^[A-Z0-9\-_]{1,12}$/', _vehUp((string)($v['matricula'] ?? ''))), "{$ctxItem}: matrícula marítima inválida.");
            $checkBlind($v, $cat, "{$ctxItem} marítimo");
            continue;
        }

        $v = $tv['datos_vehiculo_aereo']; $checkBase($v, "{$ctxItem} aéreo");
        _vehReq((bool)preg_match('/^[A-Z0-9\-_]{1,20}$/', _vehUp((string)($v['numero_serie'] ?? ''))), "{$ctxItem}: número de serie aéreo inválido.");
        if (_vehUp((string)($v['bandera'] ?? '')) !== '') {
            _vehReq(_vehValPais((string)($v['bandera'] ?? '')), "{$ctxItem}: bandera aérea inválida.");
            if (!empty($cat['pais'])) _vehReq(_vehHas($cat['pais'], (string)($v['bandera'] ?? '')), "{$ctxItem}: bandera aérea fuera de catálogo.");
        }
        if (_vehUp((string)($v['matricula'] ?? '')) !== '') _vehReq((bool)preg_match('/^[A-Z0-9\-_]{1,12}$/', _vehUp((string)($v['matricula'] ?? ''))), "{$ctxItem}: matrícula aérea inválida.");
        $checkBlind($v, $cat, "{$ctxItem} aéreo");
    }
}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_viii.php';
    require_once __DIR__ . '/../config/veh_catalogos.php';
    require_once __DIR__ . '/../config/veh_xml_helper.php';
} catch (Throwable $e) {
    _vehErr('Error al inicializar: ' . $e->getMessage(), 500);
}
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_vehReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_vehReq(function_exists('userCanAccessVEH') && userCanAccessVEH($pdo, $userId), 'Sin permiso para registrar avisos VEH');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionVIIIActiva($pdo);
_vehReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fracción VIII no está activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_vehReq(is_array($data), 'JSON inválido');
$idCliente = (int)($data['id_cliente'] ?? 0);
_vehReq($idCliente > 0, 'id_cliente es obligatorio');

$informe = is_array($data['informe'][0] ?? null) ? $data['informe'][0] : [];
$sujeto = is_array($informe['sujeto_obligado'] ?? null) ? $informe['sujeto_obligado'] : [];
$aviso = is_array($informe['aviso'][0] ?? null) ? $informe['aviso'][0] : [];
$alerta = is_array($aviso['alerta'] ?? null) ? $aviso['alerta'] : [];

$mes = _vehMonth6($informe['mes_reportado'] ?? ($data['mes_reportado'] ?? ''));
_vehReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mes) && $mes >= '201309' && $mes <= date('Ym'), 'mes_reportado inválido');

$claveSO = _vehUp($sujeto['clave_sujeto_obligado'] ?? ($data['clave_sujeto_obligado'] ?? ''));
_vehReq(function_exists('vehValidarClaveSO') && vehValidarClaveSO($claveSO), 'clave_sujeto_obligado inválida');
_vehReq(_vehUp($sujeto['clave_actividad'] ?? ($data['clave_actividad'] ?? 'VEH')) === 'VEH', 'clave_actividad debe ser VEH');

$claveEntidad = _vehUp($sujeto['clave_entidad_colegiada'] ?? ($data['clave_entidad_colegiada'] ?? ''));
if ($claveEntidad !== '') _vehReq((bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $claveEntidad), 'clave_entidad_colegiada inválida');

$exento = trim((string)($sujeto['exento'] ?? ($data['exento'] ?? '')));
_vehReq(in_array($exento, ['', '0', '1'], true), 'exento inválido');

$ref = _vehUp($aviso['referencia_aviso'] ?? ($data['referencia_aviso'] ?? ''));
_vehReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $ref), 'referencia_aviso inválida');
$prioridad = trim((string)($aviso['prioridad'] ?? ($data['prioridad'] ?? '1')));
_vehReq(in_array($prioridad, ['1', '2'], true), 'prioridad inválida');

$tipoAlerta = trim((string)($alerta['tipo_alerta'] ?? ($data['tipo_alerta'] ?? '100')));
_vehReq((bool)preg_match('/^\d{3,4}$/', $tipoAlerta), 'tipo_alerta inválido');
if (!empty($VEH_CATALOGOS['tipo_alerta'])) _vehReq(_vehHas($VEH_CATALOGOS['tipo_alerta'], $tipoAlerta), 'tipo_alerta fuera de catálogo');
$descAlerta = _vehUp($alerta['descripcion_alerta'] ?? ($data['descripcion_alerta'] ?? ''));
if ($tipoAlerta === '9999') _vehReq($descAlerta !== '', 'descripcion_alerta obligatoria cuando tipo_alerta=9999');

$mod = is_array($aviso['modificatorio'] ?? null) ? $aviso['modificatorio'] : [];
if (!empty($mod)) {
    _vehReq((bool)preg_match('/^\d{4}\-\d{1,9}$/', trim((string)($mod['folio_modificacion'] ?? ''))), 'folio_modificacion inválido');
    _vehReq(_vehUp($mod['descripcion_modificacion'] ?? '') !== '', 'descripcion_modificacion obligatoria');
}

$personas = _vehPickList($aviso['persona_aviso'] ?? [], 'tipo_persona');
_vehReq(!empty($personas), 'Debe incluir al menos una persona_aviso');
foreach ($personas as $pa) {
    _vehReq(is_array($pa), 'persona_aviso inválida');
    _vehValidateTipoPersona((array)($pa['tipo_persona'] ?? []), $VEH_CATALOGOS, false);
    $td = is_array($pa['tipo_domicilio'] ?? null) ? $pa['tipo_domicilio'] : [];
    if (!empty($td)) _vehValidateDomicilio($pdo, $td, $VEH_CATALOGOS);
    $tel = is_array($pa['telefono'] ?? null) ? $pa['telefono'] : [];
    if (!empty($tel)) {
        $cp = trim((string)($tel['clave_pais'] ?? '')); if ($cp !== '') { _vehReq(_vehValPais($cp), 'clave_pais teléfono inválida'); if (!empty($VEH_CATALOGOS['pais'])) _vehReq(_vehHas($VEH_CATALOGOS['pais'], $cp), 'clave_pais teléfono fuera de catálogo'); }
        $nt = trim((string)($tel['numero_telefono'] ?? '')); if ($nt !== '') _vehReq((bool)preg_match('/^\d{10,12}$/', $nt), 'numero_telefono inválido');
        $em = _vehUp($tel['correo_electronico'] ?? ''); if ($em !== '') _vehReq((bool)preg_match('/^[A-Z\d\._\'\-]+@[A-Z\d_\'\-]+\.[A-Z\d\._\'\-]+$/', $em), 'correo_electronico inválido');
    }
}

$duenos = _vehPickList($aviso['dueno_beneficiario'] ?? [], 'tipo_persona');
foreach ($duenos as $db) { _vehReq(is_array($db), 'dueno_beneficiario inválido'); _vehValidateTipoPersona((array)($db['tipo_persona'] ?? []), $VEH_CATALOGOS, true); }

$detalle = is_array($aviso['detalle_operaciones'][0] ?? null) ? $aviso['detalle_operaciones'][0] : [];
$ops = _vehPickList($detalle['datos_operacion'] ?? [], 'fecha_operacion');
_vehReq(!empty($ops), 'detalle_operaciones.datos_operacion es obligatorio');

$montoTotal = 0.0; $fechaOpSistema = ''; $tipoOpSistema = '';
foreach ($ops as $i => $op) {
    _vehReq(is_array($op), 'datos_operacion inválido');
    $f8 = _vehDate8($op['fecha_operacion'] ?? ''); $fymd = _vehYmd($f8);
    _vehReq($fymd !== '' && $fymd >= '2013-09-01' && $fymd <= date('Y-m-d'), 'fecha_operacion inválida en operación ' . ($i+1));
    $cp = preg_replace('/\D+/', '', (string)($op['codigo_postal'] ?? ''));
    _vehReq((bool)preg_match('/^\d{5}$/', $cp), 'codigo_postal inválido en operación ' . ($i+1));
    _vehReq(_vehCpSepomex($pdo, $cp), 'codigo_postal no existe en SEPOMEX en operación ' . ($i+1));
    $tipoOp = preg_replace('/\D+/', '', (string)($op['tipo_operacion'] ?? ''));
    _vehReq($tipoOp !== '' && strlen($tipoOp) >= 3 && strlen($tipoOp) <= 4, 'tipo_operacion inválido en operación ' . ($i+1));
    if (!empty($VEH_CATALOGOS['tipo_operacion'])) _vehReq(_vehHas($VEH_CATALOGOS['tipo_operacion'], $tipoOp), 'tipo_operacion fuera de catálogo en operación ' . ($i+1));

    _vehValidateVehiculo($op, $VEH_CATALOGOS);

    $liqs = _vehPickList($op['datos_liquidacion'] ?? [], 'monto_operacion');
    foreach ($liqs as $j => $liq) {
        _vehReq(is_array($liq), 'datos_liquidacion inválido');
        _vehReq(_vehYmd(_vehDate8($liq['fecha_pago'] ?? '')) !== '', 'fecha_pago inválida en operación ' . ($i+1));
        $fp = trim((string)($liq['forma_pago'] ?? ''));
        _vehReq((bool)preg_match('/^[1-9]$/', $fp), 'forma_pago inválida en operación ' . ($i+1));
        if (!empty($VEH_CATALOGOS['forma_pago'])) _vehReq(_vehHas($VEH_CATALOGOS['forma_pago'], $fp), 'forma_pago fuera de catálogo en operación ' . ($i+1));

        $inst = trim((string)($liq['instrumento_monetario'] ?? ''));
        $instrumentoObligatorio = in_array($fp, ['1', '2', '5'], true); // Contado, Diferido, Permuta
        if ($instrumentoObligatorio) {
            _vehReq((bool)preg_match('/^\d{1,2}$/', $inst), 'instrumento_monetario inválido en operación ' . ($i+1));
            if (!empty($VEH_CATALOGOS['instrumento_monetario'])) _vehReq(_vehHas($VEH_CATALOGOS['instrumento_monetario'], $inst), 'instrumento_monetario fuera de catálogo en operación ' . ($i+1));
        } elseif ($inst !== '') {
            _vehReq((bool)preg_match('/^\d{1,2}$/', $inst), 'instrumento_monetario inválido en operación ' . ($i+1));
            if (!empty($VEH_CATALOGOS['instrumento_monetario'])) _vehReq(_vehHas($VEH_CATALOGOS['instrumento_monetario'], $inst), 'instrumento_monetario fuera de catálogo en operación ' . ($i+1));
        }
        if ($fp === '5') _vehReq(in_array($inst, ['16','99'], true), 'Cuando forma_pago es Permuta, instrumento debe ser 16 o 99');

        $mon = trim((string)($liq['moneda'] ?? ''));
        _vehReq((bool)preg_match('/^\d{1,3}$/', $mon), 'moneda inválida en operación ' . ($i+1));
        if (!empty($VEH_CATALOGOS['moneda'])) _vehReq(_vehHas($VEH_CATALOGOS['moneda'], $mon), 'moneda fuera de catálogo en operación ' . ($i+1));
        if (in_array($inst, ['13', '14'], true)) _vehReq(((int)$mon >= 159 && (int)$mon <= 179), 'Para instrumento 13/14 la moneda debe estar entre 159 y 179');
        if ($inst !== '' && !in_array($inst, ['13', '14'], true)) _vehReq(!((int)$mon >= 159 && (int)$mon <= 179), 'Monedas 159-179 solo aplican a instrumento 13/14');

        $monto = (float)($liq['monto_operacion'] ?? 0);
        _vehReq($monto > 0, 'monto_operacion debe ser mayor a 0 en operación ' . ($i+1));
        $montoTotal += $monto;
    }
    if ($fechaOpSistema === '') { $fechaOpSistema = $fymd; $tipoOpSistema = $tipoOp; }
}
_vehReq($montoTotal > 0, 'Monto total inválido');

$idFraccion = getIdVulnerableFraccionVIII($pdo);
_vehReq((int)$idFraccion > 0, 'No se pudo resolver Fracción VIII en cat_vulnerables');

$operacionData = [
    'id_cliente' => $idCliente,
    'monto' => $montoTotal,
    'fecha_operacion' => $fechaOpSistema,
    'id_fraccion' => $idFraccion,
    'tipo_operacion' => 'VIII:' . ($tipoOpSistema ?: '0000'),
    'es_sospechosa' => $data['es_sospechosa'] ?? 0,
    'fecha_conocimiento_sospecha' => $data['fecha_conocimiento_sospecha'] ?? null,
    'match_listas_restringidas' => $data['match_listas_restringidas'] ?? 0,
    'fecha_conocimiento_match' => $data['fecha_conocimiento_match'] ?? null,
    'umbral_identificacion_uma_override' => getUmbralIdentificacionVeh(),
    'umbral_aviso_uma_override' => getUmbralAvisoVeh(),
    'umbral_acumulacion_uma_override' => getUmbralAvisoVeh(),
];

$result = registrarOperacionPLD($pdo, $operacionData);
_vehReq(($result['success'] ?? false), $result['message'] ?? 'No fue posible registrar');
$idOperacion = (int)($result['id_operacion'] ?? 0);
_vehReq($idOperacion > 0, 'No fue posible obtener id_operacion');

$payloadXml = [
    'informe' => [[
        'mes_reportado' => $mes,
        'sujeto_obligado' => [
            'clave_entidad_colegiada' => $claveEntidad,
            'clave_sujeto_obligado' => $claveSO,
            'clave_actividad' => 'VEH',
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

$gen = generateVEHXml($payloadXml);
$xml = (string)($gen['xml'] ?? '');
_vehReq($xml !== '', 'No se pudo generar XML VEH');
$xmlErrors = $gen['errors'] ?? [];
$xmlNombre = 'veh_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
try {
    $st = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
    $st->execute([$xml, $xmlNombre, $idOperacion]);
} catch (Throwable $e) {
    error_log('registrar_aviso_veh xml update: ' . $e->getMessage());
}

try {
    if (function_exists('logChange')) {
        logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_VEH', 'operaciones_pld', $idOperacion, null, [
            'id_cliente' => $idCliente,
            'tipo_operacion' => $tipoOpSistema,
            'monto' => $montoTotal,
            'umbral_identificacion_uma' => getUmbralIdentificacionVeh(),
            'umbral_aviso_uma' => getUmbralAvisoVeh(),
            'xml_errors' => $xmlErrors,
        ]);
    }
} catch (Throwable $e) { error_log('registrar_aviso_veh bitacora: ' . $e->getMessage()); }

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso VEH registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => true,
    'xml_nombre' => $xmlNombre,
    'xml_advertencia' => !empty($xmlErrors) ? implode('; ', $xmlErrors) : null,
], JSON_UNESCAPED_UNICODE);
