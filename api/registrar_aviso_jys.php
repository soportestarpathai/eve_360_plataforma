<?php
/**
 * API: Registrar Aviso JYS (Fracción I - Juegos con apuesta, concursos o sorteos)
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _jysJsonError(string $msg, int $code = 500): void
{
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function _jysUpper($value): string
{
    return mb_strtoupper(trim((string)$value), 'UTF-8');
}

function _jysIsDate8($value): bool
{
    $v = preg_replace('/\D+/', '', (string)$value);
    if (strlen($v) !== 8) {
        return false;
    }
    $y = (int)substr($v, 0, 4);
    $m = (int)substr($v, 4, 2);
    $d = (int)substr($v, 6, 2);
    return checkdate($m, $d, $y);
}

function _jysIsMonth6($value): bool
{
    $v = preg_replace('/\D+/', '', (string)$value);
    if (!preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $v)) {
        return false;
    }
    return $v >= '201309' && $v <= date('Ym');
}

function _jysMatches(string $value, string $pattern): bool
{
    return (bool)preg_match($pattern, $value);
}

function _jysChoiceCount(array $node, array $keys): int
{
    $count = 0;
    foreach ($keys as $k) {
        if (isset($node[$k]) && is_array($node[$k])) {
            $count++;
        }
    }
    return $count;
}

function _jysValidateNombre(string $value, string $label): void
{
    $u = _jysUpper($value);
    if (!_jysMatches($u, '/^[A-ZÑ ]{1,200}$/u')) {
        _jysJsonError($label . ' inválido (solo A-Z/Ñ y espacios, 1-200).', 400);
    }
}

function _jysValidateRFCFisica(string $value, string $label): void
{
    $u = _jysUpper($value);
    if ($u !== '' && !_jysMatches($u, '/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/u')) {
        _jysJsonError($label . ' inválido (RFC persona física).', 400);
    }
}

function _jysValidateRFCMoral(string $value, string $label): void
{
    $u = _jysUpper($value);
    if ($u !== '' && !_jysMatches($u, '/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u')) {
        _jysJsonError($label . ' inválido (RFC persona moral).', 400);
    }
}

function _jysValidateCURP(string $value, string $label): void
{
    $u = _jysUpper($value);
    if ($u !== '' && !_jysMatches($u, '/^[A-Z]{4}\d{6}[MH][A-Z]{5}\d{2}$/')) {
        _jysJsonError($label . ' inválida.', 400);
    }
}

function _jysValidatePais(string $value, string $label): void
{
    $u = _jysUpper($value);
    if (!_jysMatches($u, '/^[A-Z]{2}$/')) {
        _jysJsonError($label . ' inválido (clave país de 2 letras).', 400);
    }
}

function _jysValidateDenominacion(string $value, string $label): void
{
    $u = _jysUpper($value);
    if (!_jysMatches($u, "/^[A-ZÑ\\d #\\-\\.&,_@'()]{1,254}$/u")) {
        _jysJsonError($label . ' inválida (1-254, formato XSD JYS).', 400);
    }
}

function _jysValidateDescripcion3000(string $value, string $label): void
{
    $u = _jysUpper($value);
    if ($u === '' || !_jysMatches($u, "/^[A-ZÑ\\d \\-\\.,':\\/\\$]{1,3000}$/u")) {
        _jysJsonError($label . ' inválida (1-3000, formato XSD JYS).', 400);
    }
}

function _jysValidateDescripcion200(string $value, string $label): void
{
    $u = _jysUpper($value);
    if ($u === '' || !_jysMatches($u, "/^[A-ZÑ\\d \\-_\\.&,'#@]{1,200}$/u")) {
        _jysJsonError($label . ' inválido (1-200, formato XSD JYS).', 400);
    }
}

function _jysValidateIdentificadorFideicomiso(string $value): void
{
    $u = _jysUpper($value);
    if ($u !== '' && !_jysMatches($u, "/^[A-ZÑ\\d _\\-\\.&,'#@]{1,40}$/u")) {
        _jysJsonError('identificador_fideicomiso inválido (1-40, formato XSD JYS).', 400);
    }
}

function _jysValidateDireccion(string $value, string $pattern, string $label): void
{
    $u = _jysUpper($value);
    if (!$u || !_jysMatches($u, $pattern)) {
        _jysJsonError($label . ' inválido.', 400);
    }
}

function _jysCatalogHas(array $cat, $value): bool
{
    $v = trim((string)$value);
    if ($v === '') {
        return false;
    }
    return array_key_exists($v, $cat);
}

function _jysTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $cache[$table] = (bool)$stmt->fetch(PDO::FETCH_NUM);
        return $cache[$table];
    } catch (Throwable $e) {
        $cache[$table] = false;
        return false;
    }
}

function _jysCpExisteSepomex(PDO $pdo, string $cp): bool
{
    static $cpCache = [];
    static $postalCols = null;

    if (!preg_match('/^\d{5}$/', $cp)) {
        return false;
    }
    if (array_key_exists($cp, $cpCache)) {
        return $cpCache[$cp];
    }
    if (!_jysTableExists($pdo, 'cat_sepomex')) {
        $cpCache[$cp] = true;
        return true;
    }

    if ($postalCols === null) {
        $postalCols = [];
        try {
            $stmtCols = $pdo->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'cat_sepomex'
            ");
            $stmtCols->execute();
            $allCols = array_map('strtolower', array_column($stmtCols->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME'));
            foreach (['codigo_postal', 'd_codigo', 'cp', 'codigo_postal_sat', 'codigo_postal_sepomex'] as $candidate) {
                if (in_array($candidate, $allCols, true)) {
                    $postalCols[] = $candidate;
                }
            }
        } catch (Throwable $e) {
            $postalCols = [];
        }
    }

    if (empty($postalCols)) {
        $cpCache[$cp] = true;
        return true;
    }

    try {
        $ok = false;
        foreach ($postalCols as $col) {
            $sql = "SELECT 1 FROM cat_sepomex WHERE TRIM(`{$col}`) = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cp]);
            if ($stmt->fetch(PDO::FETCH_NUM)) {
                $ok = true;
                break;
            }
        }
        $cpCache[$cp] = $ok;
        return $cpCache[$cp];
    } catch (Throwable $e) {
        $cpCache[$cp] = true;
        return true;
    }
}

function _jysValidatePersona(array $tipoPersona, bool $isDuenoBeneficiario = false): void
{
    $choiceCount = _jysChoiceCount($tipoPersona, ['persona_fisica', 'persona_moral', 'fideicomiso']);
    if ($choiceCount !== 1) {
        _jysJsonError('tipo_persona debe contener exactamente una opción válida.', 400);
    }

    if (isset($tipoPersona['persona_fisica']) && is_array($tipoPersona['persona_fisica'])) {
        $pf = $tipoPersona['persona_fisica'];
        if (_jysUpper($pf['nombre'] ?? '') === '' || _jysUpper($pf['apellido_paterno'] ?? '') === '' || _jysUpper($pf['apellido_materno'] ?? '') === '') {
            _jysJsonError('Persona física requiere nombre y apellidos.', 400);
        }
        _jysValidateNombre((string)($pf['nombre'] ?? ''), 'Nombre de persona física');
        _jysValidateNombre((string)($pf['apellido_paterno'] ?? ''), 'Apellido paterno de persona física');
        _jysValidateNombre((string)($pf['apellido_materno'] ?? ''), 'Apellido materno de persona física');
        if (_jysUpper($pf['pais_nacionalidad'] ?? '') === '') {
            _jysJsonError('Persona física requiere país de nacionalidad.', 400);
        }
        _jysValidatePais((string)($pf['pais_nacionalidad'] ?? ''), 'País de nacionalidad de persona física');
        if (!$isDuenoBeneficiario && trim((string)($pf['actividad_economica'] ?? '')) === '') {
            _jysJsonError('Persona física requiere actividad económica.', 400);
        }
        if (!$isDuenoBeneficiario && !_jysMatches(trim((string)($pf['actividad_economica'] ?? '')), '/^\d{7}$/')) {
            _jysJsonError('actividad_economica inválida (7 dígitos).', 400);
        }
        if (!empty($pf['fecha_nacimiento']) && !_jysIsDate8($pf['fecha_nacimiento'])) {
            _jysJsonError('Fecha de nacimiento de persona física inválida.', 400);
        }
        _jysValidateRFCFisica((string)($pf['rfc'] ?? ''), 'RFC de persona física');
        _jysValidateCURP((string)($pf['curp'] ?? ''), 'CURP de persona física');
        return;
    }

    if (isset($tipoPersona['persona_moral']) && is_array($tipoPersona['persona_moral'])) {
        $pm = $tipoPersona['persona_moral'];
        if (_jysUpper($pm['denominacion_razon'] ?? '') === '') {
            _jysJsonError('Persona moral requiere denominación o razón social.', 400);
        }
        _jysValidateDenominacion((string)($pm['denominacion_razon'] ?? ''), 'Denominación/razón social');
        if (_jysUpper($pm['pais_nacionalidad'] ?? '') === '') {
            _jysJsonError('Persona moral requiere país de nacionalidad.', 400);
        }
        _jysValidatePais((string)($pm['pais_nacionalidad'] ?? ''), 'País de nacionalidad de persona moral');
        if (!$isDuenoBeneficiario && trim((string)($pm['giro_mercantil'] ?? '')) === '') {
            _jysJsonError('Persona moral requiere giro mercantil.', 400);
        }
        if (!$isDuenoBeneficiario && !_jysMatches(trim((string)($pm['giro_mercantil'] ?? '')), '/^\d{7}$/')) {
            _jysJsonError('giro_mercantil inválido (7 dígitos).', 400);
        }
        if (!empty($pm['fecha_constitucion']) && !_jysIsDate8($pm['fecha_constitucion'])) {
            _jysJsonError('Fecha de constitución de persona moral inválida.', 400);
        }
        _jysValidateRFCMoral((string)($pm['rfc'] ?? ''), 'RFC de persona moral');
        if (!$isDuenoBeneficiario) {
            $rep = is_array($pm['representante_apoderado'] ?? null) ? $pm['representante_apoderado'] : [];
            if (_jysUpper($rep['nombre'] ?? '') === '' || _jysUpper($rep['apellido_paterno'] ?? '') === '' || _jysUpper($rep['apellido_materno'] ?? '') === '') {
                _jysJsonError('Persona moral requiere representante/apoderado con nombre y apellidos.', 400);
            }
            _jysValidateNombre((string)($rep['nombre'] ?? ''), 'Nombre de representante/apoderado');
            _jysValidateNombre((string)($rep['apellido_paterno'] ?? ''), 'Apellido paterno de representante/apoderado');
            _jysValidateNombre((string)($rep['apellido_materno'] ?? ''), 'Apellido materno de representante/apoderado');
            if (!empty($rep['fecha_nacimiento']) && !_jysIsDate8($rep['fecha_nacimiento'])) {
                _jysJsonError('Fecha de nacimiento del representante inválida.', 400);
            }
            _jysValidateRFCFisica((string)($rep['rfc'] ?? ''), 'RFC de representante/apoderado');
            _jysValidateCURP((string)($rep['curp'] ?? ''), 'CURP de representante/apoderado');
        }
        return;
    }

    if (isset($tipoPersona['fideicomiso']) && is_array($tipoPersona['fideicomiso'])) {
        $fi = $tipoPersona['fideicomiso'];
        if (_jysUpper($fi['denominacion_razon'] ?? '') === '') {
            _jysJsonError('Fideicomiso requiere denominación o razón social del fiduciario.', 400);
        }
        _jysValidateDenominacion((string)($fi['denominacion_razon'] ?? ''), 'Denominación/razón social de fideicomiso');
        _jysValidateRFCMoral((string)($fi['rfc'] ?? ''), 'RFC de fideicomiso');
        _jysValidateIdentificadorFideicomiso((string)($fi['identificador_fideicomiso'] ?? ''));
        if (!$isDuenoBeneficiario) {
            $ap = is_array($fi['apoderado_delegado'] ?? null) ? $fi['apoderado_delegado'] : [];
            if (_jysUpper($ap['nombre'] ?? '') === '' || _jysUpper($ap['apellido_paterno'] ?? '') === '' || _jysUpper($ap['apellido_materno'] ?? '') === '') {
                _jysJsonError('Fideicomiso requiere apoderado/delegado con nombre y apellidos.', 400);
            }
            _jysValidateNombre((string)($ap['nombre'] ?? ''), 'Nombre de apoderado/delegado');
            _jysValidateNombre((string)($ap['apellido_paterno'] ?? ''), 'Apellido paterno de apoderado/delegado');
            _jysValidateNombre((string)($ap['apellido_materno'] ?? ''), 'Apellido materno de apoderado/delegado');
            if (!empty($ap['fecha_nacimiento']) && !_jysIsDate8($ap['fecha_nacimiento'])) {
                _jysJsonError('Fecha de nacimiento del apoderado/delegado inválida.', 400);
            }
            _jysValidateRFCFisica((string)($ap['rfc'] ?? ''), 'RFC de apoderado/delegado');
            _jysValidateCURP((string)($ap['curp'] ?? ''), 'CURP de apoderado/delegado');
        }
        return;
    }

    _jysJsonError('tipo_persona inválido; debe indicar persona_fisica, persona_moral o fideicomiso.', 400);
}

function _jysValidateDomicilio(PDO $pdo, array $tipoDomicilio): void
{
    $choiceCount = _jysChoiceCount($tipoDomicilio, ['nacional', 'extranjero']);
    if ($choiceCount !== 1) {
        _jysJsonError('tipo_domicilio debe contener exactamente una opción válida.', 400);
    }

    if (isset($tipoDomicilio['nacional']) && is_array($tipoDomicilio['nacional'])) {
        $n = $tipoDomicilio['nacional'];
        if (_jysUpper($n['colonia'] ?? '') === '' || _jysUpper($n['calle'] ?? '') === '' || _jysUpper($n['numero_exterior'] ?? '') === '') {
            _jysJsonError('Domicilio nacional requiere colonia, calle y número exterior.', 400);
        }
        _jysValidateDireccion((string)($n['colonia'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/()]{1,50}$/u", 'Colonia (nacional)');
        _jysValidateDireccion((string)($n['calle'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,100}$/u", 'Calle (nacional)');
        _jysValidateDireccion((string)($n['numero_exterior'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,56}$/u", 'Número exterior (nacional)');
        if (trim((string)($n['numero_interior'] ?? '')) !== '') {
            _jysValidateDireccion((string)($n['numero_interior'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,40}$/u", 'Número interior (nacional)');
        }
        $cp = preg_replace('/\D+/', '', (string)($n['codigo_postal'] ?? ''));
        if (!preg_match('/^\d{5}$/', $cp)) {
            _jysJsonError('Código postal nacional inválido (5 dígitos).', 400);
        }
        if (!_jysCpExisteSepomex($pdo, $cp)) {
            _jysJsonError('El código postal nacional no existe en catálogo SEPOMEX.', 400);
        }
        return;
    }

    if (isset($tipoDomicilio['extranjero']) && is_array($tipoDomicilio['extranjero'])) {
        $x = $tipoDomicilio['extranjero'];
        if (_jysUpper($x['pais'] ?? '') === '' || _jysUpper($x['estado_provincia'] ?? '') === '' || _jysUpper($x['ciudad_poblacion'] ?? '') === '' || _jysUpper($x['colonia'] ?? '') === '' || _jysUpper($x['calle'] ?? '') === '' || _jysUpper($x['numero_exterior'] ?? '') === '') {
            _jysJsonError('Domicilio extranjero requiere país, estado/provincia, ciudad, colonia, calle y número exterior.', 400);
        }
        _jysValidatePais((string)($x['pais'] ?? ''), 'País (domicilio extranjero)');
        _jysValidateDireccion((string)($x['estado_provincia'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,100}$/u", 'Estado/provincia');
        _jysValidateDireccion((string)($x['ciudad_poblacion'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,100}$/u", 'Ciudad/población');
        _jysValidateDireccion((string)($x['colonia'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/()]{1,50}$/u", 'Colonia (extranjero)');
        _jysValidateDireccion((string)($x['calle'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,100}$/u", 'Calle (extranjero)');
        _jysValidateDireccion((string)($x['numero_exterior'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,56}$/u", 'Número exterior (extranjero)');
        if (trim((string)($x['numero_interior'] ?? '')) !== '') {
            _jysValidateDireccion((string)($x['numero_interior'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,40}$/u", 'Número interior (extranjero)');
        }
        $cp = _jysUpper($x['codigo_postal'] ?? '');
        if (!preg_match('/^[A-Z0-9]{4,12}$/', $cp)) {
            _jysJsonError('Código postal extranjero inválido (4-12 alfanumérico).', 400);
        }
        return;
    }

    _jysJsonError('tipo_domicilio inválido; debe indicar nacional o extranjero.', 400);
}

function _jysValidateCatalogs(array $data, array $catalogs): void
{
    $map = [
        'prioridad' => 'prioridad',
        'exento' => 'exento',
        'instrumento_monetario' => 'instrumento_monetario',
        'moneda' => 'moneda',
        'pais_nacionalidad' => 'pais',
        'pais' => 'pais',
        'clave_pais' => 'pais',
        'actividad_economica' => 'actividad_economica',
        'giro_mercantil' => 'giro_mercantil',
        'tipo_inmueble' => 'tipo_inmueble',
        'tipo_operacion' => 'tipo_operacion',
        'linea_negocio' => 'linea_negocio',
        'medio_operacion' => 'medio_operacion',
        'bien_liquidacion' => 'bien_liquidacion',
    ];

    $issues = [];
    $walk = function ($node, string $path = '') use (&$walk, $map, $catalogs, &$issues) {
        if (!is_array($node)) {
            return;
        }
        foreach ($node as $k => $v) {
            $curr = $path === '' ? (string)$k : ($path . '.' . (string)$k);
            if (is_array($v)) {
                $walk($v, $curr);
                continue;
            }
            if ((string)$k === 'clave_actividad') {
                if (_jysUpper($v) !== 'JYS') {
                    $issues[] = "{$curr}: clave_actividad inválida (debe ser JYS)";
                }
                continue;
            }
            if (!isset($map[$k])) {
                continue;
            }
            $catalogName = $map[$k];
            $cat = $catalogs[$catalogName] ?? [];
            if (!is_array($cat) || empty($cat)) {
                continue;
            }
            $norm = trim((string)$v);
            if ((string)$k === 'exento' && $norm === '') {
                continue;
            }
            if (!_jysCatalogHas($cat, $norm)) {
                $issues[] = "{$curr}: valor {$norm} fuera de catálogo {$catalogName}";
            }
        }
    };
    $walk($data, '');

    if (!empty($issues)) {
        $max = 8;
        $msg = 'Validación catálogo JYS fallida: ' . implode(' | ', array_slice($issues, 0, $max));
        if (count($issues) > $max) {
            $msg .= ' | ...';
        }
        _jysJsonError($msg, 400);
    }
}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_i.php';
    require_once __DIR__ . '/../config/jys_catalogos.php';
    require_once __DIR__ . '/../config/jys_xml_helper.php';
} catch (Throwable $e) {
    error_log('registrar_aviso_jys init: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    _jysJsonError('Error al inicializar: ' . $e->getMessage(), 500);
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    _jysJsonError('No autorizado', 401);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!function_exists('userCanAccessJYS') || !userCanAccessJYS($pdo, $userId)) {
    _jysJsonError('Sin permiso para registrar avisos JYS', 403);
}

requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionIActiva($pdo);
if (empty($validPatron['habilitado'])) {
    _jysJsonError($validPatron['razon'] ?? 'La fracción I no está activa en padrón PLD', 403);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['id_cliente'])) {
    _jysJsonError('JSON con id_cliente e informe requerido', 400);
}
if (empty($data['informe']) || !is_array($data['informe'])) {
    _jysJsonError('Estructura informe requerida', 400);
}

$id_cliente = (int)($data['id_cliente'] ?? 0);
if ($id_cliente <= 0) {
    _jysJsonError('id_cliente inválido', 400);
}

_jysValidateCatalogs($data, $JYS_CATALOGOS);

$informe0 = $data['informe'][0] ?? [];
if (!is_array($informe0)) {
    _jysJsonError('informe[0] inválido.', 400);
}

$mesReportado = preg_replace('/\D+/', '', (string)($informe0['mes_reportado'] ?? ''));
if (!_jysIsMonth6($mesReportado)) {
    _jysJsonError('mes_reportado inválido. Debe estar en formato AAAAMM, entre 201309 y el mes actual.', 400);
}

$so0 = is_array($informe0['sujeto_obligado'] ?? null) ? $informe0['sujeto_obligado'] : [];
$claveSO = $so0['clave_sujeto_obligado'] ?? '';
if (!function_exists('jysValidarClaveSO') || !jysValidarClaveSO($claveSO)) {
    _jysJsonError('Clave Sujeto Obligado inválida (formato RFC con homoclave).', 400);
}
if (!empty($so0['clave_entidad_colegiada']) && !preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', _jysUpper($so0['clave_entidad_colegiada']))) {
    _jysJsonError('clave_entidad_colegiada inválida.', 400);
}
if (isset($so0['exento']) && trim((string)$so0['exento']) !== '' && (string)$so0['exento'] !== '1') {
    _jysJsonError('exento inválido: solo se permite valor 1 cuando se envía.', 400);
}

$aviso0 = $informe0['aviso'][0] ?? [];
if (!is_array($aviso0)) {
    _jysJsonError('Debe existir al menos un aviso en informe[0].', 400);
}
if (!preg_match('/^[A-ZÑ0-9]{1,14}$/u', _jysUpper($aviso0['referencia_aviso'] ?? ''))) {
    _jysJsonError('referencia_aviso inválida (1-14 alfanumérico A-Z/Ñ/0-9).', 400);
}

$alerta = is_array($aviso0['alerta'] ?? null) ? $aviso0['alerta'] : [];
$prioridad = (string)($aviso0['prioridad'] ?? '1');
$tipoAlerta = (string)($alerta['tipo_alerta'] ?? '100');
if (!preg_match('/^\d{3,4}$/', $tipoAlerta)) {
    _jysJsonError('tipo_alerta inválido (3-4 dígitos).', 400);
}
if ($prioridad === '2' && $tipoAlerta === '100') {
    _jysJsonError('Cuando la prioridad es 2, el tipo de alerta no puede ser 100.', 400);
}
if ($tipoAlerta === '9999' && trim((string)($alerta['descripcion_alerta'] ?? '')) === '') {
    _jysJsonError('descripcion_alerta es obligatoria cuando tipo_alerta = 9999.', 400);
}
if (!empty($alerta['descripcion_alerta'])) {
    _jysValidateDescripcion3000((string)$alerta['descripcion_alerta'], 'descripcion_alerta');
}

if (isset($aviso0['modificatorio']) && is_array($aviso0['modificatorio'])) {
    $m = $aviso0['modificatorio'];
    $folio = _jysUpper($m['folio_modificacion'] ?? '');
    if (!preg_match('/^[2-9]\d{3}-[1-9]\d{0,8}$/', $folio)) {
        _jysJsonError('folio_modificacion inválido (formato AAAA-9...).', 400);
    }
    if (_jysUpper($m['descripcion_modificacion'] ?? '') === '') {
        _jysJsonError('descripcion_modificacion es obligatoria en aviso modificatorio.', 400);
    }
    _jysValidateDescripcion3000((string)($m['descripcion_modificacion'] ?? ''), 'descripcion_modificacion');
}

$personaAvisoList = jysArrayWrap($aviso0['persona_aviso'] ?? []);
if (empty($personaAvisoList)) {
    _jysJsonError('persona_aviso es obligatorio.', 400);
}
foreach ($personaAvisoList as $pa) {
    if (!is_array($pa)) {
        continue;
    }
    $tipoPersona = is_array($pa['tipo_persona'] ?? null) ? $pa['tipo_persona'] : [];
    _jysValidatePersona($tipoPersona, false);
    $esFideicomiso = isset($tipoPersona['fideicomiso']) && is_array($tipoPersona['fideicomiso']);

    $tipoDomicilio = is_array($pa['tipo_domicilio'] ?? null) ? $pa['tipo_domicilio'] : [];
    if (!empty($tipoDomicilio)) {
        _jysValidateDomicilio($pdo, $tipoDomicilio);
    } elseif (!$esFideicomiso) {
        _jysJsonError('tipo_domicilio es obligatorio para persona física o moral.', 400);
    }

    $tel = is_array($pa['telefono'] ?? null) ? $pa['telefono'] : [];
    $telNumero = preg_replace('/\D+/', '', (string)($tel['numero_telefono'] ?? ''));
    $telMail = _jysUpper((string)($tel['correo_electronico'] ?? ''));
    $telHasData = ($telNumero !== '' || $telMail !== '');
    if (!$telHasData && !$esFideicomiso) {
        _jysJsonError('telefono es obligatorio para persona física o moral.', 400);
    }
    if ($telHasData) {
        if (_jysUpper($tel['clave_pais'] ?? '') === '' || !preg_match('/^\d{10}(\d{2})?$/', $telNumero)) {
            _jysJsonError('Teléfono inválido en persona_aviso (requiere clave país y 10 o 12 dígitos).', 400);
        }
        _jysValidatePais((string)($tel['clave_pais'] ?? ''), 'clave_pais de teléfono');
        if ($telMail !== '' && (!_jysMatches($telMail, "/^[A-Z\\d\\._'\\-]+@[A-Z\\d_'\\-]+\\.[A-Z\\d\\._'\\-]+$/") || strlen($telMail) < 5 || strlen($telMail) > 60)) {
            _jysJsonError('correo_electronico inválido en persona_aviso.', 400);
        }
    }
}

foreach (jysArrayWrap($aviso0['dueno_beneficiario'] ?? []) as $db) {
    if (!is_array($db)) {
        continue;
    }
    $tipoPersona = is_array($db['tipo_persona'] ?? null) ? $db['tipo_persona'] : [];
    if (empty($tipoPersona)) {
        _jysJsonError('dueno_beneficiario requiere tipo_persona.', 400);
    }
    _jysValidatePersona($tipoPersona, true);
}

$sumMontos = jysArraySumMontosOperacion($data);
if ($sumMontos <= 0) {
    _jysJsonError('No se encontraron montos válidos en datos_liquidacion.', 400);
}

$fechaOperacion = date('Y-m-d');
$tipoOperacionJys = '101';
$hasDetalle = false;

foreach (jysArrayWrap($aviso0['detalle_operaciones'] ?? []) as $det) {
    if (!is_array($det)) {
        continue;
    }
    foreach (jysArrayWrap($det['datos_operacion'] ?? []) as $op) {
        if (!is_array($op)) {
            continue;
        }
        $hasDetalle = true;

        $f = jysNormalizeDate8($op['fecha_operacion'] ?? '');
        if ($f === '' || !_jysIsDate8($f)) {
            _jysJsonError('fecha_operacion inválida en datos_operacion.', 400);
        }
        if ($f < '20130901') {
            _jysJsonError('fecha_operacion debe ser >= 20130901.', 400);
        }
        if ($f > date('Ymd')) {
            _jysJsonError('fecha_operacion no puede ser futura.', 400);
        }
        $fechaOperacion = substr($f, 0, 4) . '-' . substr($f, 4, 2) . '-' . substr($f, 6, 2);

        $ts = is_array($op['tipo_sucursal'] ?? null) ? $op['tipo_sucursal'] : [];
        $choiceSucursal = _jysChoiceCount($ts, ['datos_sucursal_propia', 'datos_sucursal_operador']);
        if ($choiceSucursal !== 1) {
            _jysJsonError('tipo_sucursal debe contener exactamente una opción: propia u operador.', 400);
        }
        if (isset($ts['datos_sucursal_propia']) && is_array($ts['datos_sucursal_propia'])) {
            $sp = $ts['datos_sucursal_propia'];
            $cp = preg_replace('/\D+/', '', (string)($sp['codigo_postal'] ?? ''));
            if (!preg_match('/^\d{5}$/', $cp)) {
                _jysJsonError('codigo_postal en datos_sucursal_propia inválido (5 dígitos).', 400);
            }
            if (!_jysCpExisteSepomex($pdo, $cp)) {
                _jysJsonError('codigo_postal en datos_sucursal_propia no existe en SEPOMEX.', 400);
            }
        }
        if (isset($ts['datos_sucursal_operador']) && is_array($ts['datos_sucursal_operador'])) {
            $so2 = $ts['datos_sucursal_operador'];
            if (_jysUpper($so2['nombre_operador'] ?? '') === '') {
                _jysJsonError('datos_sucursal_operador requiere nombre_operador.', 400);
            }
            _jysValidateDescripcion200((string)($so2['nombre_operador'] ?? ''), 'nombre_operador');
            $cp = preg_replace('/\D+/', '', (string)($so2['codigo_postal'] ?? ''));
            if (!preg_match('/^\d{5}$/', $cp)) {
                _jysJsonError('codigo_postal en datos_sucursal_operador inválido (5 dígitos).', 400);
            }
            if (!_jysCpExisteSepomex($pdo, $cp)) {
                _jysJsonError('codigo_postal en datos_sucursal_operador no existe en SEPOMEX.', 400);
            }
        }

        $tipoOp = preg_replace('/\D+/', '', (string)($op['tipo_operacion'] ?? ''));
        if ($tipoOp === '' || !preg_match('/^\d{3,4}$/', $tipoOp)) {
            _jysJsonError('tipo_operacion es obligatorio (3-4 dígitos).', 400);
        }
        $tipoOperacionJys = $tipoOp;

        $linea = preg_replace('/\D+/', '', (string)($op['linea_negocio'] ?? ''));
        if ($linea === '' || !preg_match('/^\d{1,2}$/', $linea)) {
            _jysJsonError('linea_negocio es obligatoria (1-2 dígitos).', 400);
        }
        $medio = preg_replace('/\D+/', '', (string)($op['medio_operacion'] ?? ''));
        if ($medio === '' || !preg_match('/^\d{1}$/', $medio)) {
            _jysJsonError('medio_operacion es obligatorio (1 dígito).', 400);
        }

        $liqList = jysArrayWrap($op['datos_liquidacion'] ?? []);
        if (empty($liqList)) {
            _jysJsonError('datos_liquidacion es obligatorio en datos_operacion.', 400);
        }
        foreach ($liqList as $dl) {
            if (!is_array($dl)) {
                continue;
            }
            $hasNum = isset($dl['liquidacion_numerario']) && is_array($dl['liquidacion_numerario']);
            $hasEsp = isset($dl['liquidacion_especie']) && is_array($dl['liquidacion_especie']);
            if (($hasNum && $hasEsp) || (!$hasNum && !$hasEsp)) {
                _jysJsonError('Cada datos_liquidacion debe tener solo una opción: numerario o especie.', 400);
            }

            if ($hasNum) {
                $n = $dl['liquidacion_numerario'];
                if (!_jysIsDate8($n['fecha_pago'] ?? '')) {
                    _jysJsonError('fecha_pago inválida en liquidacion_numerario.', 400);
                }
                $inst = preg_replace('/\D+/', '', (string)($n['instrumento_monetario'] ?? ''));
                $mon = preg_replace('/\D+/', '', (string)($n['moneda'] ?? ''));
                $monto = (float)($n['monto_operacion'] ?? 0);
                if ($inst === '' || $mon === '' || $monto <= 0) {
                    _jysJsonError('liquidacion_numerario requiere instrumento, moneda y monto > 0.', 400);
                }
                if (in_array($inst, ['13', '14'], true) && ((int)$mon < 159 || (int)$mon > 179)) {
                    _jysJsonError('Para instrumento monetario 13/14, la moneda debe estar entre 159 y 179.', 400);
                }
                if (!in_array($inst, ['13', '14'], true) && ((int)$mon >= 159 && (int)$mon <= 179)) {
                    _jysJsonError('Monedas 159-179 solo aplican a instrumento monetario 13/14.', 400);
                }
            }

            if ($hasEsp) {
                $e = $dl['liquidacion_especie'];
                $valor = (float)($e['valor_bien'] ?? 0);
                $mon = preg_replace('/\D+/', '', (string)($e['moneda'] ?? ''));
                $bien = preg_replace('/\D+/', '', (string)($e['bien_liquidacion'] ?? ''));
                if ($valor <= 0 || $mon === '' || $bien === '') {
                    _jysJsonError('liquidacion_especie requiere valor_bien > 0, moneda y bien_liquidacion.', 400);
                }

                $datosBien = is_array($e['datos_bien_liquidacion'] ?? null) ? $e['datos_bien_liquidacion'] : [];
                $hasInm = isset($datosBien['datos_inmueble']) && is_array($datosBien['datos_inmueble']);
                $hasOtro = isset($datosBien['datos_otro']) && is_array($datosBien['datos_otro']);
                if ($hasInm && $hasOtro) {
                    _jysJsonError('datos_bien_liquidacion debe contener solo una opción: datos_inmueble o datos_otro.', 400);
                }

                if ($bien === '1') {
                    if (!$hasInm) {
                        _jysJsonError('datos_inmueble es obligatorio cuando bien_liquidacion = 1.', 400);
                    }
                    $di = $datosBien['datos_inmueble'];
                    if (!preg_match('/^\d{1,3}$/', (string)($di['tipo_inmueble'] ?? ''))) {
                        _jysJsonError('tipo_inmueble inválido en datos_inmueble.', 400);
                    }
                    $cpInm = preg_replace('/\D+/', '', (string)($di['codigo_postal'] ?? ''));
                    if (!preg_match('/^\d{5}$/', $cpInm)) {
                        _jysJsonError('codigo_postal inválido en datos_inmueble (5 dígitos).', 400);
                    }
                    if (!_jysCpExisteSepomex($pdo, $cpInm)) {
                        _jysJsonError('codigo_postal en datos_inmueble no existe en SEPOMEX.', 400);
                    }
                    $folioReal = _jysUpper($di['folio_real'] ?? '');
                    if ($folioReal === '') {
                        _jysJsonError('folio_real es obligatorio en datos_inmueble.', 400);
                    }
                    if (!preg_match('/^[A-Z\\d\\-_]{1,200}$/', $folioReal)) {
                        _jysJsonError('folio_real inválido en datos_inmueble (formato XSD JYS).', 400);
                    }
                } elseif ($bien === '99') {
                    if (!$hasOtro) {
                        _jysJsonError('datos_otro es obligatorio cuando bien_liquidacion = 99.', 400);
                    }
                    $do = $datosBien['datos_otro'];
                    if (_jysUpper($do['descripcion_bien_liquidacion'] ?? '') === '') {
                        _jysJsonError('descripcion_bien_liquidacion es obligatoria cuando bien_liquidacion = 99.', 400);
                    }
                    _jysValidateDescripcion3000((string)($do['descripcion_bien_liquidacion'] ?? ''), 'descripcion_bien_liquidacion');
                } elseif (!empty($datosBien)) {
                    _jysJsonError('datos_bien_liquidacion solo debe enviarse cuando bien_liquidacion = 1 o 99.', 400);
                }
            }
        }
    }
}

if (!$hasDetalle) {
    _jysJsonError('detalle_operaciones/datos_operacion es obligatorio.', 400);
}

$idFraccion = getIdVulnerableFraccionI($pdo);
if (!$idFraccion) {
    _jysJsonError('No se pudo resolver la Fracción I (cat_vulnerables).', 400);
}

$operacionData = [
    'id_cliente' => $id_cliente,
    'monto' => $sumMontos,
    'fecha_operacion' => $fechaOperacion,
    'id_fraccion' => (int)$idFraccion,
    'tipo_operacion' => 'JYS_' . $tipoOperacionJys,
    // Fracción I: umbral aviso/acumulación legal = 645 UMA
    'umbral_aviso_uma_override' => function_exists('getUmbralAvisoJYS') ? getUmbralAvisoJYS() : 645.0,
    'umbral_acumulacion_uma_override' => function_exists('getUmbralAvisoJYS') ? getUmbralAvisoJYS() : 645.0,
    'es_sospechosa' => $data['es_sospechosa'] ?? 0,
    'fecha_conocimiento_sospecha' => $data['fecha_conocimiento_sospecha'] ?? null,
    'match_listas_restringidas' => $data['match_listas_restringidas'] ?? 0,
    'fecha_conocimiento_match' => $data['fecha_conocimiento_match'] ?? null,
];

try {
    $result = registrarOperacionPLD($pdo, $operacionData);
    if (!($result['success'] ?? false)) {
        _jysJsonError($result['message'] ?? 'Error al registrar aviso JYS', 400);
    }

    $id_operacion = (int)$result['id_operacion'];
    $gen = generateJYSXml($data);
    $xml = $gen['xml'] ?? '';
    $xmlErrors = $gen['errors'] ?? [];

    if (!empty($xmlErrors) && function_exists('logChange')) {
        logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_JYS', 'operaciones_pld', $id_operacion, null, ['xml_errors' => $xmlErrors]);
    }

    if ($xml !== '') {
        $xmlNombre = 'jys_' . date('Ymd_His') . '_op' . $id_operacion . '.xml';
        try {
            $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
            $stmt->execute([$xml, $xmlNombre, $id_operacion]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'xml_contenido') === false) {
                throw $e;
            }
        }
    }

    if (function_exists('logChange')) {
        logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_JYS', 'operaciones_pld', $id_operacion, null, $operacionData);
    }

    $resp = [
        'status' => 'success',
        'message' => 'Aviso JYS registrado correctamente.',
        'id_operacion' => $id_operacion,
        'id_aviso' => $result['id_aviso'] ?? null,
        'requiere_aviso' => $result['requiere_aviso'] ?? false,
        'tipo_aviso' => $result['tipo_aviso'] ?? null,
        'fecha_deadline' => $result['fecha_deadline'] ?? null,
        'xml_generado' => ($xml !== ''),
        'monto_operacion' => $sumMontos,
    ];
    if (!empty($xmlErrors)) {
        $resp['xml_advertencia'] = implode('; ', $xmlErrors);
    }
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('registrar_aviso_jys: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    _jysJsonError('Error al registrar JYS: ' . $e->getMessage(), 500);
}
