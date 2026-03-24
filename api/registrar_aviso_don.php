<?php
/**
 * API: Registrar Aviso DON (Fracción XIII - Donativos)
 * - Valida reglas clave del instructivo DON (estructura/catálogos/campos obligatorios)
 * - Registra operación PLD para disparar avisos y acumulación
 * - Genera XML DON y lo guarda en operaciones_pld
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _donJsonError(string $msg, int $code = 500): void
{
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function _donUpper($value): string
{
    return mb_strtoupper(trim((string)$value), 'UTF-8');
}

function _donIsDate8($value): bool
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

function _donIsMonth6($value): bool
{
    $v = preg_replace('/\D+/', '', (string)$value);
    if (!preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $v)) {
        return false;
    }
    return $v >= '201309' && $v <= date('Ym');
}

function _donMatches(string $value, string $pattern): bool
{
    return (bool)preg_match($pattern, $value);
}

function _donChoiceCount(array $node, array $keys): int
{
    $count = 0;
    foreach ($keys as $k) {
        if (isset($node[$k]) && is_array($node[$k])) {
            $count++;
        }
    }
    return $count;
}

function _donValidateNombre(string $value, string $label): void
{
    $u = _donUpper($value);
    if (!_donMatches($u, '/^[A-ZÑ ]{1,200}$/u')) {
        _donJsonError($label . ' inválido (solo A-Z/Ñ y espacios, 1-200).', 400);
    }
}

function _donValidateRFCFisica(string $value, string $label): void
{
    $u = _donUpper($value);
    if ($u !== '' && !_donMatches($u, '/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/u')) {
        _donJsonError($label . ' inválido (RFC persona física).', 400);
    }
}

function _donValidateRFCMoral(string $value, string $label): void
{
    $u = _donUpper($value);
    if ($u !== '' && !_donMatches($u, '/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u')) {
        _donJsonError($label . ' inválido (RFC persona moral).', 400);
    }
}

function _donValidateCURP(string $value, string $label): void
{
    $u = _donUpper($value);
    if ($u !== '' && !_donMatches($u, '/^[A-Z]{4}\d{6}[MH][A-Z]{5}\d{2}$/')) {
        _donJsonError($label . ' inválida.', 400);
    }
}

function _donValidatePais(string $value, string $label): void
{
    $u = _donUpper($value);
    if (!_donMatches($u, '/^[A-Z]{2}$/')) {
        _donJsonError($label . ' inválido (clave país de 2 letras).', 400);
    }
}

function _donValidateDenominacion(string $value, string $label): void
{
    $u = _donUpper($value);
    if (!_donMatches($u, "/^[A-ZÑ\\d #\\-\\.&,_@'()]{1,254}$/u")) {
        _donJsonError($label . ' inválida (1-254, formato XSD DON).', 400);
    }
}

function _donValidateIdentificadorFideicomiso(string $value): void
{
    $u = _donUpper($value);
    if ($u !== '' && !_donMatches($u, "/^[A-ZÑ\\d _\\-\\.&,'#@]{1,40}$/u")) {
        _donJsonError('identificador_fideicomiso inválido (1-40, formato XSD DON).', 400);
    }
}

function _donValidateDescripcion3000(string $value, string $label): void
{
    $u = _donUpper($value);
    if ($u === '' || !_donMatches($u, "/^[A-ZÑ\\d \\-\\.,':\\/\\$]{1,3000}$/u")) {
        _donJsonError($label . ' inválida (1-3000, formato XSD DON).', 400);
    }
}

function _donValidateDireccion(string $value, string $pattern, string $label): void
{
    $u = _donUpper($value);
    if (!$u || !_donMatches($u, $pattern)) {
        _donJsonError($label . ' inválido.', 400);
    }
}

function _donCatalogHas(array $cat, $value): bool
{
    $v = trim((string)$value);
    if ($v === '') {
        return false;
    }
    return array_key_exists($v, $cat);
}

function _donTableExists(PDO $pdo, string $table): bool
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

function _donCpExisteSepomex(PDO $pdo, string $cp): bool
{
    static $cpCache = [];
    if (!preg_match('/^\d{5}$/', $cp)) {
        return false;
    }
    if (array_key_exists($cp, $cpCache)) {
        return $cpCache[$cp];
    }
    if (!_donTableExists($pdo, 'cat_sepomex')) {
        $cpCache[$cp] = true; // No bloquear si el catálogo no existe.
        return true;
    }
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM cat_sepomex WHERE codigo_postal = ? LIMIT 1");
        $stmt->execute([$cp]);
        $cpCache[$cp] = (bool)$stmt->fetch(PDO::FETCH_NUM);
        return $cpCache[$cp];
    } catch (Throwable $e) {
        $cpCache[$cp] = true; // fail-open para no romper operación
        return true;
    }
}

function _donValidatePersona(array $tipoPersona, bool $isDuenoBeneficiario = false): void
{
    $choiceCount = _donChoiceCount($tipoPersona, ['persona_fisica', 'persona_moral', 'fideicomiso']);
    if ($choiceCount !== 1) {
        _donJsonError('tipo_persona debe contener exactamente una opción válida.', 400);
    }

    if (isset($tipoPersona['persona_fisica']) && is_array($tipoPersona['persona_fisica'])) {
        $pf = $tipoPersona['persona_fisica'];
        if (_donUpper($pf['nombre'] ?? '') === '' || _donUpper($pf['apellido_paterno'] ?? '') === '' || _donUpper($pf['apellido_materno'] ?? '') === '') {
            _donJsonError('Persona física requiere nombre y apellidos.', 400);
        }
        _donValidateNombre((string)($pf['nombre'] ?? ''), 'Nombre de persona física');
        _donValidateNombre((string)($pf['apellido_paterno'] ?? ''), 'Apellido paterno de persona física');
        _donValidateNombre((string)($pf['apellido_materno'] ?? ''), 'Apellido materno de persona física');
        if (_donUpper($pf['pais_nacionalidad'] ?? '') === '') {
            _donJsonError('Persona física requiere país de nacionalidad.', 400);
        }
        _donValidatePais((string)($pf['pais_nacionalidad'] ?? ''), 'País de nacionalidad de persona física');
        if (!$isDuenoBeneficiario && trim((string)($pf['actividad_economica'] ?? '')) === '') {
            _donJsonError('Persona física requiere actividad económica.', 400);
        }
        if (!$isDuenoBeneficiario && !_donMatches(trim((string)($pf['actividad_economica'] ?? '')), '/^\d{7}$/')) {
            _donJsonError('actividad_economica inválida (7 dígitos).', 400);
        }
        if (!empty($pf['fecha_nacimiento']) && !_donIsDate8($pf['fecha_nacimiento'])) {
            _donJsonError('Fecha de nacimiento de persona física inválida.', 400);
        }
        _donValidateRFCFisica((string)($pf['rfc'] ?? ''), 'RFC de persona física');
        _donValidateCURP((string)($pf['curp'] ?? ''), 'CURP de persona física');
        return;
    }

    if (isset($tipoPersona['persona_moral']) && is_array($tipoPersona['persona_moral'])) {
        $pm = $tipoPersona['persona_moral'];
        if (_donUpper($pm['denominacion_razon'] ?? '') === '') {
            _donJsonError('Persona moral requiere denominación o razón social.', 400);
        }
        _donValidateDenominacion((string)($pm['denominacion_razon'] ?? ''), 'Denominación/razón social');
        if (_donUpper($pm['pais_nacionalidad'] ?? '') === '') {
            _donJsonError('Persona moral requiere país de nacionalidad.', 400);
        }
        _donValidatePais((string)($pm['pais_nacionalidad'] ?? ''), 'País de nacionalidad de persona moral');
        if (!$isDuenoBeneficiario && trim((string)($pm['giro_mercantil'] ?? '')) === '') {
            _donJsonError('Persona moral requiere giro mercantil.', 400);
        }
        if (!$isDuenoBeneficiario && !_donMatches(trim((string)($pm['giro_mercantil'] ?? '')), '/^\d{7}$/')) {
            _donJsonError('giro_mercantil inválido (7 dígitos).', 400);
        }
        if (!empty($pm['fecha_constitucion']) && !_donIsDate8($pm['fecha_constitucion'])) {
            _donJsonError('Fecha de constitución de persona moral inválida.', 400);
        }
        _donValidateRFCMoral((string)($pm['rfc'] ?? ''), 'RFC de persona moral');
        if (!$isDuenoBeneficiario) {
            $rep = is_array($pm['representante_apoderado'] ?? null) ? $pm['representante_apoderado'] : [];
            if (_donUpper($rep['nombre'] ?? '') === '' || _donUpper($rep['apellido_paterno'] ?? '') === '' || _donUpper($rep['apellido_materno'] ?? '') === '') {
                _donJsonError('Persona moral requiere representante/apoderado con nombre y apellidos.', 400);
            }
            _donValidateNombre((string)($rep['nombre'] ?? ''), 'Nombre de representante/apoderado');
            _donValidateNombre((string)($rep['apellido_paterno'] ?? ''), 'Apellido paterno de representante/apoderado');
            _donValidateNombre((string)($rep['apellido_materno'] ?? ''), 'Apellido materno de representante/apoderado');
            if (!empty($rep['fecha_nacimiento']) && !_donIsDate8($rep['fecha_nacimiento'])) {
                _donJsonError('Fecha de nacimiento del representante (persona moral) inválida.', 400);
            }
            _donValidateRFCFisica((string)($rep['rfc'] ?? ''), 'RFC de representante/apoderado');
            _donValidateCURP((string)($rep['curp'] ?? ''), 'CURP de representante/apoderado');
        }
        return;
    }

    if (isset($tipoPersona['fideicomiso']) && is_array($tipoPersona['fideicomiso'])) {
        $fi = $tipoPersona['fideicomiso'];
        if (_donUpper($fi['denominacion_razon'] ?? '') === '') {
            _donJsonError('Fideicomiso requiere denominación o razón social del fiduciario.', 400);
        }
        _donValidateDenominacion((string)($fi['denominacion_razon'] ?? ''), 'Denominación/razón social de fideicomiso');
        _donValidateRFCMoral((string)($fi['rfc'] ?? ''), 'RFC de fideicomiso');
        _donValidateIdentificadorFideicomiso((string)($fi['identificador_fideicomiso'] ?? ''));
        if (!$isDuenoBeneficiario) {
            $ap = is_array($fi['apoderado_delegado'] ?? null) ? $fi['apoderado_delegado'] : [];
            if (_donUpper($ap['nombre'] ?? '') === '' || _donUpper($ap['apellido_paterno'] ?? '') === '' || _donUpper($ap['apellido_materno'] ?? '') === '') {
                _donJsonError('Fideicomiso requiere apoderado/delegado con nombre y apellidos.', 400);
            }
            _donValidateNombre((string)($ap['nombre'] ?? ''), 'Nombre de apoderado/delegado');
            _donValidateNombre((string)($ap['apellido_paterno'] ?? ''), 'Apellido paterno de apoderado/delegado');
            _donValidateNombre((string)($ap['apellido_materno'] ?? ''), 'Apellido materno de apoderado/delegado');
            if (!empty($ap['fecha_nacimiento']) && !_donIsDate8($ap['fecha_nacimiento'])) {
                _donJsonError('Fecha de nacimiento del apoderado/delegado (fideicomiso) inválida.', 400);
            }
            _donValidateRFCFisica((string)($ap['rfc'] ?? ''), 'RFC de apoderado/delegado');
            _donValidateCURP((string)($ap['curp'] ?? ''), 'CURP de apoderado/delegado');
        }
        return;
    }

    _donJsonError('tipo_persona inválido; debe indicar persona_fisica, persona_moral o fideicomiso.', 400);
}

function _donValidateDomicilio(PDO $pdo, array $tipoDomicilio): void
{
    $choiceCount = _donChoiceCount($tipoDomicilio, ['nacional', 'extranjero']);
    if ($choiceCount !== 1) {
        _donJsonError('tipo_domicilio debe contener exactamente una opción válida.', 400);
    }

    if (isset($tipoDomicilio['nacional']) && is_array($tipoDomicilio['nacional'])) {
        $n = $tipoDomicilio['nacional'];
        if (_donUpper($n['colonia'] ?? '') === '' || _donUpper($n['calle'] ?? '') === '' || _donUpper($n['numero_exterior'] ?? '') === '') {
            _donJsonError('Domicilio nacional requiere colonia, calle y número exterior.', 400);
        }
        _donValidateDireccion((string)($n['colonia'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/()]{1,50}$/u", 'Colonia (nacional)');
        _donValidateDireccion((string)($n['calle'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,100}$/u", 'Calle (nacional)');
        _donValidateDireccion((string)($n['numero_exterior'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,56}$/u", 'Número exterior (nacional)');
        if (trim((string)($n['numero_interior'] ?? '')) !== '') {
            _donValidateDireccion((string)($n['numero_interior'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,40}$/u", 'Número interior (nacional)');
        }
        $cp = preg_replace('/\D+/', '', (string)($n['codigo_postal'] ?? ''));
        if (!preg_match('/^\d{5}$/', $cp)) {
            _donJsonError('Código postal nacional inválido (5 dígitos).', 400);
        }
        if (!_donCpExisteSepomex($pdo, $cp)) {
            _donJsonError('El código postal nacional no existe en catálogo SEPOMEX.', 400);
        }
        return;
    }

    if (isset($tipoDomicilio['extranjero']) && is_array($tipoDomicilio['extranjero'])) {
        $x = $tipoDomicilio['extranjero'];
        if (_donUpper($x['pais'] ?? '') === '' || _donUpper($x['estado_provincia'] ?? '') === '' || _donUpper($x['ciudad_poblacion'] ?? '') === '' || _donUpper($x['colonia'] ?? '') === '' || _donUpper($x['calle'] ?? '') === '' || _donUpper($x['numero_exterior'] ?? '') === '') {
            _donJsonError('Domicilio extranjero requiere país, estado/provincia, ciudad, colonia, calle y número exterior.', 400);
        }
        _donValidatePais((string)($x['pais'] ?? ''), 'País (domicilio extranjero)');
        _donValidateDireccion((string)($x['estado_provincia'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,100}$/u", 'Estado/provincia');
        _donValidateDireccion((string)($x['ciudad_poblacion'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,100}$/u", 'Ciudad/población');
        _donValidateDireccion((string)($x['colonia'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/()]{1,50}$/u", 'Colonia (extranjero)');
        _donValidateDireccion((string)($x['calle'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,100}$/u", 'Calle (extranjero)');
        _donValidateDireccion((string)($x['numero_exterior'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,56}$/u", 'Número exterior (extranjero)');
        if (trim((string)($x['numero_interior'] ?? '')) !== '') {
            _donValidateDireccion((string)($x['numero_interior'] ?? ''), "/^[A-ZÑ\\d \\-\\.,:\\/]{1,40}$/u", 'Número interior (extranjero)');
        }
        $cp = _donUpper($x['codigo_postal'] ?? '');
        if (!preg_match('/^[A-Z0-9]{4,12}$/', $cp)) {
            _donJsonError('Código postal extranjero inválido (4-12 alfanumérico).', 400);
        }
        return;
    }

    _donJsonError('tipo_domicilio inválido; debe indicar nacional o extranjero.', 400);
}

function _donValidateCatalogs(array $data, array $catalogs): void
{
    $map = [
        'prioridad' => 'prioridad',
        'exento' => 'exento',
        'tipo_operacion' => 'tipo_operacion',
        'instrumento_monetario' => 'instrumento_monetario',
        'moneda' => 'moneda',
        'pais_nacionalidad' => 'pais',
        'pais' => 'pais',
        'clave_pais' => 'pais',
        'actividad_economica' => 'actividad_economica',
        'giro_mercantil' => 'giro_mercantil',
        'tipo_inmueble' => 'tipo_inmueble',
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
                if (_donUpper($v) !== 'DON') {
                    $issues[] = "{$curr}: clave_actividad inválida (debe ser DON)";
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
            if (!_donCatalogHas($cat, $norm)) {
                $issues[] = "{$curr}: valor {$norm} fuera de catálogo {$catalogName}";
            }
        }
    };
    $walk($data, '');

    if (!empty($issues)) {
        $max = 8;
        $msg = 'Validación catálogo DON fallida: ' . implode(' | ', array_slice($issues, 0, $max));
        if (count($issues) > $max) {
            $msg .= ' | ...';
        }
        _donJsonError($msg, 400);
    }
}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_xiii.php';
    require_once __DIR__ . '/../config/don_catalogos.php';
    require_once __DIR__ . '/../config/don_xml_helper.php';
} catch (Throwable $e) {
    error_log('registrar_aviso_don init: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    _donJsonError('Error al inicializar: ' . $e->getMessage(), 500);
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    _donJsonError('No autorizado', 401);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!function_exists('userCanAccessDON') || !userCanAccessDON($pdo, $userId)) {
    _donJsonError('Sin permiso para registrar avisos DON', 403);
}

requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionXIIIActiva($pdo);
if (empty($validPatron['habilitado'])) {
    _donJsonError($validPatron['razon'] ?? 'La fracción XIII no está activa en padrón PLD', 403);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['id_cliente'])) {
    _donJsonError('JSON con id_cliente e informe requerido', 400);
}
if (empty($data['informe']) || !is_array($data['informe'])) {
    _donJsonError('Estructura informe requerida', 400);
}

$id_cliente = (int)($data['id_cliente'] ?? 0);
if ($id_cliente <= 0) {
    _donJsonError('id_cliente inválido', 400);
}

_donValidateCatalogs($data, $DON_CATALOGOS);

$informe0 = $data['informe'][0] ?? [];
if (!is_array($informe0)) {
    _donJsonError('informe[0] inválido.', 400);
}
$mesReportado = preg_replace('/\D+/', '', (string)($informe0['mes_reportado'] ?? ''));
if (!_donIsMonth6($mesReportado)) {
    _donJsonError('mes_reportado inválido. Debe estar en formato AAAAMM, entre 201309 y el mes actual.', 400);
}

$so0 = is_array($informe0['sujeto_obligado'] ?? null) ? $informe0['sujeto_obligado'] : [];
$claveSO = $so0['clave_sujeto_obligado'] ?? '';
if (!function_exists('donValidarClaveSO') || !donValidarClaveSO($claveSO)) {
    _donJsonError('Clave Sujeto Obligado inválida (formato RFC con homoclave).', 400);
}
if (!empty($so0['clave_entidad_colegiada']) && !preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', _donUpper($so0['clave_entidad_colegiada']))) {
    _donJsonError('clave_entidad_colegiada inválida.', 400);
}
if (isset($so0['exento']) && trim((string)$so0['exento']) !== '' && (string)$so0['exento'] !== '1') {
    _donJsonError('exento inválido: solo se permite valor 1 cuando se envía.', 400);
}

$aviso0 = $informe0['aviso'][0] ?? [];
if (!is_array($aviso0)) {
    _donJsonError('Debe existir al menos un aviso en informe[0].', 400);
}
if (!preg_match('/^[A-ZÑ0-9]{1,14}$/u', _donUpper($aviso0['referencia_aviso'] ?? ''))) {
    _donJsonError('referencia_aviso inválida (1-14 alfanumérico A-Z/Ñ/0-9).', 400);
}

$alerta = is_array($aviso0['alerta'] ?? null) ? $aviso0['alerta'] : [];
$prioridad = (string)($aviso0['prioridad'] ?? '1');
$tipoAlerta = (string)($alerta['tipo_alerta'] ?? '100');
if (!preg_match('/^\d{3,4}$/', $tipoAlerta)) {
    _donJsonError('tipo_alerta inválido (3-4 dígitos).', 400);
}
if ($prioridad === '2' && $tipoAlerta === '100') {
    _donJsonError('Cuando la prioridad es 2, el tipo de alerta no puede ser 100.', 400);
}
if ($tipoAlerta === '9999' && trim((string)($alerta['descripcion_alerta'] ?? '')) === '') {
    _donJsonError('descripcion_alerta es obligatoria cuando tipo_alerta = 9999.', 400);
}
if (!empty($alerta['descripcion_alerta'])) {
    _donValidateDescripcion3000((string)$alerta['descripcion_alerta'], 'descripcion_alerta');
}

if (isset($aviso0['modificatorio']) && is_array($aviso0['modificatorio'])) {
    $m = $aviso0['modificatorio'];
    $folio = _donUpper($m['folio_modificacion'] ?? '');
    if (!preg_match('/^[2-9]\d{3}-[1-9]\d{0,8}$/', $folio)) {
        _donJsonError('folio_modificacion inválido (formato AAAA-9...).', 400);
    }
    if (_donUpper($m['descripcion_modificacion'] ?? '') === '') {
        _donJsonError('descripcion_modificacion es obligatoria en aviso modificatorio.', 400);
    }
    _donValidateDescripcion3000((string)($m['descripcion_modificacion'] ?? ''), 'descripcion_modificacion');
}

$personaAvisoList = donArrayWrap($aviso0['persona_aviso'] ?? []);
if (empty($personaAvisoList)) {
    _donJsonError('persona_aviso es obligatorio.', 400);
}
foreach ($personaAvisoList as $pa) {
    if (!is_array($pa)) {
        continue;
    }
    $tipoPersona = is_array($pa['tipo_persona'] ?? null) ? $pa['tipo_persona'] : [];
    _donValidatePersona($tipoPersona, false);

    $tipoDomicilio = is_array($pa['tipo_domicilio'] ?? null) ? $pa['tipo_domicilio'] : [];
    _donValidateDomicilio($pdo, $tipoDomicilio);

    $tel = is_array($pa['telefono'] ?? null) ? $pa['telefono'] : [];
    $telNumero = preg_replace('/\D+/', '', (string)($tel['numero_telefono'] ?? ''));
    $telMail = _donUpper((string)($tel['correo_electronico'] ?? ''));
    $telHasData = ($telNumero !== '' || $telMail !== '');
    if ($telHasData) {
        if (_donUpper($tel['clave_pais'] ?? '') === '' || !preg_match('/^\d{10}(\d{2})?$/', $telNumero)) {
            _donJsonError('Teléfono inválido en persona_aviso (requiere clave país y 10 o 12 dígitos).', 400);
        }
        _donValidatePais((string)($tel['clave_pais'] ?? ''), 'clave_pais de teléfono');
        if ($telMail !== '' && (!_donMatches($telMail, "/^[A-Z\\d\\._'\\-]+@[A-Z\\d_'\\-]+\\.[A-Z\\d\\._'\\-]+$/") || strlen($telMail) < 5 || strlen($telMail) > 60)) {
            _donJsonError('correo_electronico inválido en persona_aviso.', 400);
        }
    }
}

foreach (donArrayWrap($aviso0['dueno_beneficiario'] ?? []) as $db) {
    if (!is_array($db)) {
        continue;
    }
    $tipoPersona = is_array($db['tipo_persona'] ?? null) ? $db['tipo_persona'] : [];
    if (empty($tipoPersona)) {
        _donJsonError('dueno_beneficiario requiere tipo_persona.', 400);
    }
    _donValidatePersona($tipoPersona, true);
}

$sumMontos = donArraySumMontosOperacion($data);
if ($sumMontos <= 0) {
    _donJsonError('No se encontraron montos válidos en datos_donativo.', 400);
}

$fechaOperacion = date('Y-m-d');
$tipoOperacionDon = '1301';
$hasDetalle = false;
foreach (donArrayWrap($aviso0['detalle_operaciones'] ?? []) as $det) {
    if (!is_array($det)) {
        continue;
    }
    foreach (donArrayWrap($det['datos_operacion'] ?? []) as $op) {
        if (!is_array($op)) {
            continue;
        }
        $hasDetalle = true;
        $f = donNormalizeDate8($op['fecha_operacion'] ?? '');
        if ($f === '' || !_donIsDate8($f)) {
            _donJsonError('fecha_operacion inválida en datos_operacion.', 400);
        }
        $fechaOperacion = substr($f, 0, 4) . '-' . substr($f, 4, 2) . '-' . substr($f, 6, 2);

        $cpOperacion = preg_replace('/\D+/', '', (string)($op['codigo_postal'] ?? ''));
        if (!preg_match('/^\d{5}$/', $cpOperacion)) {
            _donJsonError('codigo_postal de operación inválido (5 dígitos).', 400);
        }
        if (!_donCpExisteSepomex($pdo, $cpOperacion)) {
            _donJsonError('codigo_postal de operación no existe en SEPOMEX.', 400);
        }

        $tipoOp = preg_replace('/\D+/', '', (string)($op['tipo_operacion'] ?? ''));
        if ($tipoOp === '' || !preg_match('/^\d{3,4}$/', $tipoOp)) {
            _donJsonError('tipo_operacion es obligatorio en datos_operacion.', 400);
        }
        $tipoOperacionDon = $tipoOp;

        $hasTipoDonativo = false;
        foreach (donArrayWrap($op['datos_donativo'] ?? []) as $dd) {
            if (!is_array($dd)) {
                continue;
            }
            foreach (donArrayWrap($dd['tipo_donativo'] ?? []) as $td) {
                if (!is_array($td)) {
                    continue;
                }
                $hasTipoDonativo = true;
                $hasNum = isset($td['liquidacion_numerario']) && is_array($td['liquidacion_numerario']);
                $hasEsp = isset($td['liquidacion_especie']) && is_array($td['liquidacion_especie']);
                if (($hasNum && $hasEsp) || (!$hasNum && !$hasEsp)) {
                    _donJsonError('Cada tipo_donativo debe tener solo una liquidación: numerario o especie.', 400);
                }

                if ($hasNum) {
                    $n = $td['liquidacion_numerario'];
                    if (!_donIsDate8($n['fecha_pago'] ?? '')) {
                        _donJsonError('fecha_pago inválida en liquidacion_numerario.', 400);
                    }
                    $inst = (string)($n['instrumento_monetario'] ?? '');
                    $mon = (string)($n['moneda'] ?? '');
                    $monto = (float)($n['monto_operacion'] ?? 0);
                    if ($inst === '' || $mon === '' || $monto <= 0) {
                        _donJsonError('liquidacion_numerario requiere instrumento, moneda y monto > 0.', 400);
                    }
                    // Regla instructivo: oro/plata amonedada (13/14) solo monedas 159-179.
                    if (in_array($inst, ['13', '14'], true) && ((int)$mon < 159 || (int)$mon > 179)) {
                        _donJsonError('Para instrumento monetario 13/14, la moneda debe estar entre 159 y 179.', 400);
                    }
                    if (!in_array($inst, ['13', '14'], true) && ((int)$mon >= 159 && (int)$mon <= 179)) {
                        _donJsonError('Monedas 159-179 solo aplican a instrumento monetario 13/14.', 400);
                    }
                }

                if ($hasEsp) {
                    $esp = $td['liquidacion_especie'];
                    $bien = (string)($esp['bien_donado'] ?? '');
                    if (!preg_match('/^\d{1,2}$/', $bien)) {
                        _donJsonError('bien_donado inválido (1-2 dígitos).', 400);
                    }
                    $monto = (float)($esp['monto_operacion'] ?? 0);
                    if ($monto <= 0 || trim((string)($esp['moneda'] ?? '')) === '' || $bien === '') {
                        _donJsonError('liquidacion_especie requiere monto > 0, moneda y bien_donado.', 400);
                    }
                    $datosBien = is_array($esp['datos_bien_donado'] ?? null) ? $esp['datos_bien_donado'] : [];
                    if (($bien === '1' || $bien === '99') && empty($datosBien)) {
                        _donJsonError('datos_bien_donado es obligatorio para bien_donado = Inmueble u Otro.', 400);
                    }
                    $hasDatosInmueble = isset($datosBien['datos_inmueble']) && is_array($datosBien['datos_inmueble']);
                    $hasDatosOtro = isset($datosBien['datos_otro']) && is_array($datosBien['datos_otro']);
                    if ($hasDatosInmueble && $hasDatosOtro) {
                        _donJsonError('datos_bien_donado debe contener solo una opción: datos_inmueble o datos_otro.', 400);
                    }
                    if ($bien === '1') {
                        if (!$hasDatosInmueble) {
                            _donJsonError('datos_inmueble es obligatorio cuando bien_donado = 1.', 400);
                        }
                        $di = is_array($datosBien['datos_inmueble'] ?? null) ? $datosBien['datos_inmueble'] : [];
                        if (trim((string)($di['tipo_inmueble'] ?? '')) === '' || trim((string)($di['folio_real'] ?? '')) === '') {
                            _donJsonError('datos_inmueble requiere tipo_inmueble y folio_real.', 400);
                        }
                        $cpInm = preg_replace('/\D+/', '', (string)($di['codigo_postal'] ?? ''));
                        if (!preg_match('/^\d{5}$/', $cpInm)) {
                            _donJsonError('datos_inmueble.codigo_postal inválido (5 dígitos).', 400);
                        }
                        if (!_donCpExisteSepomex($pdo, $cpInm)) {
                            _donJsonError('datos_inmueble.codigo_postal no existe en SEPOMEX.', 400);
                        }
                        if (!_donMatches(_donUpper((string)$di['folio_real']), '/^[A-Z\d\-_]{1,200}$/')) {
                            _donJsonError('folio_real inválido (1-200, formato XSD DON).', 400);
                        }
                    }
                    if ($bien === '99') {
                        if (!$hasDatosOtro) {
                            _donJsonError('datos_otro es obligatorio cuando bien_donado = 99.', 400);
                        }
                        $do = is_array($datosBien['datos_otro'] ?? null) ? $datosBien['datos_otro'] : [];
                        if (_donUpper($do['descripcion_bien_donado'] ?? '') === '') {
                            _donJsonError('datos_otro.descripcion_bien_donado es obligatorio cuando bien_donado = 99.', 400);
                        }
                        _donValidateDescripcion3000((string)($do['descripcion_bien_donado'] ?? ''), 'descripcion_bien_donado');
                    }
                    if (!in_array($bien, ['1', '99'], true) && !empty($datosBien)) {
                        _donJsonError('datos_bien_donado solo debe enviarse cuando bien_donado = 1 o 99.', 400);
                    }
                }
            }
        }
        if (!$hasTipoDonativo) {
            _donJsonError('Debe existir al menos un tipo_donativo por datos_operacion.', 400);
        }
    }
}

if (!$hasDetalle) {
    _donJsonError('detalle_operaciones/datos_operacion es obligatorio.', 400);
}

$idFraccion = getIdVulnerableFraccionXIII($pdo);
if (!$idFraccion) {
    _donJsonError('No se pudo resolver la Fracción XIII (cat_vulnerables).', 400);
}

$operacionData = [
    'id_cliente' => $id_cliente,
    'monto' => $sumMontos,
    'fecha_operacion' => $fechaOperacion,
    'id_fraccion' => (int)$idFraccion,
    'tipo_operacion' => 'DON_' . $tipoOperacionDon,
    'es_sospechosa' => $data['es_sospechosa'] ?? 0,
    'fecha_conocimiento_sospecha' => $data['fecha_conocimiento_sospecha'] ?? null,
    'match_listas_restringidas' => $data['match_listas_restringidas'] ?? 0,
    'fecha_conocimiento_match' => $data['fecha_conocimiento_match'] ?? null,
];

try {
    $result = registrarOperacionPLD($pdo, $operacionData);
    if (!($result['success'] ?? false)) {
        _donJsonError($result['message'] ?? 'Error al registrar aviso DON', 400);
    }

    $id_operacion = (int)$result['id_operacion'];
    $gen = generateDONXml($data);
    $xml = $gen['xml'] ?? '';
    $xmlErrors = $gen['errors'] ?? [];

    if (!empty($xmlErrors) && function_exists('logChange')) {
        logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_DON', 'operaciones_pld', $id_operacion, null, ['xml_errors' => $xmlErrors]);
    }

    if ($xml !== '') {
        $xmlNombre = 'don_' . date('Ymd_His') . '_op' . $id_operacion . '.xml';
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
        logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_DON', 'operaciones_pld', $id_operacion, null, $operacionData);
    }

    $resp = [
        'status' => 'success',
        'message' => 'Aviso DON registrado correctamente.',
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
    error_log('registrar_aviso_don: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    _donJsonError('Error al registrar DON: ' . $e->getMessage(), 500);
}
