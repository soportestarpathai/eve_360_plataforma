<?php
/**
 * API: Registrar Aviso OBA (Fracción VII - Obras de arte).
 * Registra la operación, evalúa umbral UMA y genera el XML del aviso OBA.
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _obaErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function _obaReq(bool $ok, string $msg): void { if (!$ok) _obaErr($msg, 400); }
function _obaUp($v): string { return mb_strtoupper(trim((string)$v), 'UTF-8'); }
function _obaMonth6($v): string { return substr(preg_replace('/\D+/', '', (string)$v), 0, 6); }
function _obaDate8($v): string { $x = preg_replace('/\D+/', '', (string)$v); return strlen($x) >= 8 ? substr($x, 0, 8) : ''; }
function _obaOnlyDigits($v): string { return preg_replace('/\D+/', '', (string)$v); }
function _obaHas(array $cat, $k): bool { $k = trim((string)$k); return $k !== '' && array_key_exists($k, $cat); }
function _obaSanText($v): string { return preg_replace('/[^A-ZÑ0-9 \-\.,:\/#&,_@\'()]/u', '', _obaUp($v)); }
function _obaRfcFisica($v): string { $v = _obaUp($v); return preg_match('/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/u', $v) ? $v : ''; }
function _obaRfcMoral($v): string { $v = _obaUp($v); return preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $v) ? $v : ''; }
function _obaCurp($v): string { $v = _obaUp($v); return preg_match('/^[A-Z]{4}\d{6}[MH][A-Z]{5}[0-9]{2}$/', $v) ? $v : ''; }

function _obaYmdFrom8(string $d8): string
{
    if (!preg_match('/^\d{8}$/', $d8)) return '';
    $y = (int)substr($d8, 0, 4);
    $m = (int)substr($d8, 4, 2);
    $d = (int)substr($d8, 6, 2);
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
}

function _obaTableExists(PDO $pdo, string $table): bool
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

function _obaCpSepomex(PDO $pdo, string $cp): bool
{
    if (!preg_match('/^\d{5}$/', $cp)) return false;
    if (!_obaTableExists($pdo, 'cat_sepomex')) return true;
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

function _obaPaisClave(PDO $pdo, $idPais, string $fallback = 'MX'): string
{
    $idPais = (int)$idPais;
    if ($idPais <= 0) return $fallback;
    try {
        $stmt = $pdo->prepare("SELECT clave FROM cat_pais WHERE id_pais = ? LIMIT 1");
        $stmt->execute([$idPais]);
        $clave = _obaUp($stmt->fetchColumn() ?: '');
        return preg_match('/^[A-Z]{2}$/', $clave) ? $clave : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function _obaFirstPaisCliente(PDO $pdo, int $idCliente): string
{
    try {
        $stmt = $pdo->prepare("SELECT id_pais FROM clientes_nacionalidades WHERE id_cliente = ? AND (id_status = 1 OR id_status IS NULL) ORDER BY id_cliente_nacionalidad LIMIT 1");
        $stmt->execute([$idCliente]);
        return _obaPaisClave($pdo, $stmt->fetchColumn(), 'MX');
    } catch (Throwable $e) {
        return 'MX';
    }
}

function _obaFirstDireccionCliente(PDO $pdo, int $idCliente): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes_direcciones WHERE id_cliente = ? ORDER BY id_cliente_direccion LIMIT 1");
        $stmt->execute([$idCliente]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $cp = _obaOnlyDigits($row['codigo_postal'] ?? '');
        if (!preg_match('/^\d{5}$/', $cp)) return null;
        return ['nacional' => [
            'colonia' => _obaSanText($row['colonia'] ?? 'NO PROPORCIONADO'),
            'calle' => _obaSanText($row['calle'] ?? 'NO PROPORCIONADO'),
            'numero_exterior' => _obaSanText($row['numero_exterior'] ?? 'SN') ?: 'SN',
            'numero_interior' => _obaSanText($row['numero_interior'] ?? ''),
            'codigo_postal' => $cp,
        ]];
    } catch (Throwable $e) {
        return null;
    }
}

function _obaFirstTelefonoCliente(PDO $pdo, int $idCliente): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT dato_contacto FROM clientes_contactos WHERE id_cliente = ? AND (id_status = 1 OR id_status IS NULL) ORDER BY id_cliente_contacto");
        $stmt->execute([$idCliente]);
        $tel = '';
        $mail = '';
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dato = trim((string)($r['dato_contacto'] ?? ''));
            if ($mail === '' && strpos($dato, '@') !== false) $mail = _obaUp($dato);
            $digits = _obaOnlyDigits($dato);
            if ($tel === '' && preg_match('/^\d{10,12}$/', $digits)) $tel = $digits;
        }
        if ($tel === '' && $mail === '') return null;
        return ['clave_pais' => 'MX', 'numero_telefono' => $tel, 'correo_electronico' => $mail];
    } catch (Throwable $e) {
        return null;
    }
}

function _obaBuildRepresentante(PDO $pdo, int $idCliente): ?array
{
    try {
        $stmt = $pdo->prepare("
            SELECT af.*
            FROM clientes_apoderados a
            INNER JOIN clientes_apoderados_fisicas af ON af.id_cliente_apoderado = a.id_cliente_apoderado
            WHERE a.id_cliente = ?
            ORDER BY a.id_cliente_apoderado
            LIMIT 1
        ");
        $stmt->execute([$idCliente]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        return [
            'nombre' => _obaSanText($r['nombre'] ?? ''),
            'apellido_paterno' => _obaSanText($r['apellido_paterno'] ?? ''),
            'apellido_materno' => _obaSanText($r['apellido_materno'] ?? ''),
            'fecha_nacimiento' => _obaDate8($r['fecha_nacimiento'] ?? ''),
            'rfc' => _obaRfcFisica($r['tax_id'] ?? $r['rfc'] ?? ''),
            'curp' => _obaCurp($r['CURP'] ?? $r['curp'] ?? ''),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function _obaBuildPersonaAviso(PDO $pdo, int $idCliente): array
{
    $stmt = $pdo->prepare("
        SELECT c.id_tipo_persona, tp.es_fisica, tp.es_moral, COALESCE(tp.es_fideicomiso, 0) AS es_fideicomiso
        FROM clientes c
        INNER JOIN cat_tipo_persona tp ON tp.id_tipo_persona = c.id_tipo_persona
        WHERE c.id_cliente = ?
        LIMIT 1
    ");
    $stmt->execute([$idCliente]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC);
    _obaReq((bool)$base, 'Cliente no encontrado para persona_aviso');

    $pais = _obaFirstPaisCliente($pdo, $idCliente);
    if ((int)($base['es_fisica'] ?? 0) > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_fisicas WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $persona = ['tipo_persona' => ['persona_fisica' => [
            'nombre' => _obaSanText($f['nombre'] ?? ''),
            'apellido_paterno' => _obaSanText($f['apellido_paterno'] ?? ''),
            'apellido_materno' => _obaSanText($f['apellido_materno'] ?? ''),
            'fecha_nacimiento' => _obaDate8($f['fecha_nacimiento'] ?? ''),
            'rfc' => _obaRfcFisica($f['tax_id'] ?? $f['rfc'] ?? ''),
            'curp' => _obaCurp($f['CURP'] ?? $f['curp'] ?? ''),
            'pais_nacionalidad' => $pais,
            'actividad_economica' => '1000000',
        ]]];
        _obaReq($persona['tipo_persona']['persona_fisica']['nombre'] !== '' && $persona['tipo_persona']['persona_fisica']['apellido_paterno'] !== '' && $persona['tipo_persona']['persona_fisica']['apellido_materno'] !== '', 'Cliente físico incompleto para persona_aviso');
    } elseif ((int)($base['es_moral'] ?? 0) > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_morales WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $rep = _obaBuildRepresentante($pdo, $idCliente);
        _obaReq((bool)$rep, 'Persona moral requiere representante/apoderado para XML OBA');
        $persona = ['tipo_persona' => ['persona_moral' => [
            'denominacion_razon' => _obaSanText($m['razon_social'] ?? ''),
            'fecha_constitucion' => _obaDate8($m['fecha_constitucion'] ?? ''),
            'rfc' => _obaRfcMoral($m['tax_id'] ?? $m['rfc'] ?? ''),
            'pais_nacionalidad' => $pais,
            'giro_mercantil' => '1000000',
            'representante_apoderado' => $rep,
        ]]];
        _obaReq($persona['tipo_persona']['persona_moral']['denominacion_razon'] !== '', 'Persona moral incompleta para persona_aviso');
    } else {
        $stmt = $pdo->prepare("SELECT * FROM clientes_fideicomisos WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $fi = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $rep = _obaBuildRepresentante($pdo, $idCliente);
        _obaReq((bool)$rep, 'Fideicomiso requiere apoderado/delegado para XML OBA');
        $persona = ['tipo_persona' => ['fideicomiso' => [
            'denominacion_razon' => _obaSanText($fi['institucion_fiduciaria'] ?? 'FIDEICOMISO'),
            'rfc' => _obaRfcMoral($fi['rfc'] ?? ''),
            'identificador_fideicomiso' => _obaSanText($fi['numero_fideicomiso'] ?? ''),
            'apoderado_delegado' => $rep,
        ]]];
    }

    $dom = _obaFirstDireccionCliente($pdo, $idCliente);
    if ($dom) $persona['tipo_domicilio'] = $dom;
    $tel = _obaFirstTelefonoCliente($pdo, $idCliente);
    if ($tel) $persona['telefono'] = $tel;
    return $persona;
}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_vii.php';
    require_once __DIR__ . '/../config/oba_catalogos.php';
    require_once __DIR__ . '/../config/oba_xml_helper.php';
} catch (Throwable $e) {
    _obaErr('Error al inicializar: ' . $e->getMessage(), 500);
}

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_obaReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_obaReq(function_exists('userCanAccessOBA') && userCanAccessOBA($pdo, $userId), 'Sin permiso para registrar avisos OBA');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionVIIActiva($pdo);
_obaReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fracción VII no está activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_obaReq(is_array($data), 'JSON inválido');

$idCliente = (int)($data['id_cliente'] ?? 0);
_obaReq($idCliente > 0, 'id_cliente es obligatorio');

$mesReportado = _obaMonth6($data['mes_reportado'] ?? '');
_obaReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mesReportado) && $mesReportado >= '201309' && $mesReportado <= date('Ym'), 'mes_reportado inválido');

$claveSO = _obaUp($data['clave_sujeto_obligado'] ?? '');
_obaReq((bool)preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $claveSO), 'clave_sujeto_obligado inválida');

$claveEntidad = _obaUp($data['clave_entidad_colegiada'] ?? '');
if ($claveEntidad !== '') {
    _obaReq((bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $claveEntidad), 'clave_entidad_colegiada inválida');
}

$referenciaAviso = _obaUp($data['referencia_aviso'] ?? '');
_obaReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $referenciaAviso), 'referencia_aviso inválida');

$prioridad = trim((string)($data['prioridad'] ?? '1'));
_obaReq(in_array($prioridad, ['1', '2'], true), 'prioridad inválida');

$tipoAlerta = preg_replace('/\D+/', '', (string)($data['tipo_alerta'] ?? '100'));
_obaReq((bool)preg_match('/^\d{3,4}$/', $tipoAlerta), 'tipo_alerta inválido');
if (!empty($OBA_CATALOGOS['tipo_alerta'])) _obaReq(_obaHas($OBA_CATALOGOS['tipo_alerta'], $tipoAlerta), 'tipo_alerta fuera de catálogo OBA');
_obaReq(!($prioridad === '2' && $tipoAlerta === '100'), 'Cuando prioridad=2, tipo_alerta debe ser diferente de 100');

$descripcionAlerta = _obaUp($data['descripcion_alerta'] ?? '');
if ($tipoAlerta === '9999') _obaReq($descripcionAlerta !== '', 'descripcion_alerta es obligatoria cuando tipo_alerta=9999');

$fechaOperacion8 = _obaDate8($data['fecha_operacion'] ?? '');
_obaReq($fechaOperacion8 !== '' && _obaYmdFrom8($fechaOperacion8) !== '', 'fecha_operacion inválida');
$fechaOperacionYmd = _obaYmdFrom8($fechaOperacion8);
_obaReq($fechaOperacionYmd >= '2013-09-01' && $fechaOperacionYmd <= date('Y-m-d'), 'fecha_operacion fuera de rango');

$codigoPostal = _obaOnlyDigits($data['codigo_postal'] ?? '');
_obaReq((bool)preg_match('/^\d{5}$/', $codigoPostal), 'codigo_postal inválido');
_obaReq(_obaCpSepomex($pdo, $codigoPostal), 'codigo_postal de operación no existe en SEPOMEX');

$tipoOperacion = _obaOnlyDigits($data['tipo_operacion'] ?? '');
_obaReq($tipoOperacion !== '', 'tipo_operacion es obligatorio');
if (!empty($OBA_CATALOGOS['tipo_operacion'])) _obaReq(_obaHas($OBA_CATALOGOS['tipo_operacion'], $tipoOperacion), 'tipo_operacion fuera de catálogo OBA');

$rawObjetos = is_array($data['datos_objeto'] ?? null) ? $data['datos_objeto'] : [[
    'tipo_objeto' => $data['tipo_objeto'] ?? '',
    'descripcion' => $data['descripcion_objeto'] ?? '',
    'numero_registro' => $data['numero_registro'] ?? '',
    'valor_referencia' => $data['valor_referencia'] ?? '',
]];
$datosObjeto = [];
foreach ($rawObjetos as $idx => $obj) {
    _obaReq(is_array($obj), 'datos_objeto inválido en partida ' . ($idx + 1));
    $tipoObjeto = _obaOnlyDigits($obj['tipo_objeto'] ?? '');
    _obaReq($tipoObjeto !== '', 'tipo_objeto es obligatorio en objeto ' . ($idx + 1));
    if (!empty($OBA_CATALOGOS['tipo_objeto'])) _obaReq(_obaHas($OBA_CATALOGOS['tipo_objeto'], $tipoObjeto), 'tipo_objeto fuera de catálogo OBA en objeto ' . ($idx + 1));
    $descObjeto = _obaSanText($obj['descripcion'] ?? $obj['descripcion_objeto'] ?? '');
    _obaReq($descObjeto !== '', 'descripcion de objeto es obligatoria en objeto ' . ($idx + 1));
    $numeroRegistro = preg_replace('/[^A-Z0-9\-_]/', '', _obaUp($obj['numero_registro'] ?? ''));
    _obaReq($numeroRegistro === '' || (bool)preg_match('/^[A-Z0-9\-_]{1,20}$/', $numeroRegistro), 'numero_registro inválido en objeto ' . ($idx + 1));
    $valorReferencia = (float)($obj['valor_referencia'] ?? 0);
    $datosObjeto[] = [
        'tipo_objeto' => $tipoObjeto,
        'descripcion' => $descObjeto,
        'numero_registro' => $numeroRegistro !== '' ? $numeroRegistro : null,
        'valor_referencia' => $valorReferencia > 0 ? number_format($valorReferencia, 2, '.', '') : null,
    ];
}
_obaReq(!empty($datosObjeto), 'Debe incluir al menos un datos_objeto');

$rawLiquidaciones = is_array($data['datos_liquidacion'] ?? null) ? $data['datos_liquidacion'] : [[
    'fecha_pago' => $data['fecha_pago'] ?? '',
    'forma_pago' => $data['forma_pago'] ?? '',
    'instrumento_monetario' => $data['instrumento_monetario'] ?? '',
    'moneda' => $data['moneda'] ?? '',
    'monto_operacion' => $data['monto'] ?? 0,
]];
$datosLiquidacion = [];
$montoTotal = 0.0;
foreach ($rawLiquidaciones as $idx => $liq) {
    _obaReq(is_array($liq), 'datos_liquidacion inválido en partida ' . ($idx + 1));
    $fechaPago8 = _obaDate8($liq['fecha_pago'] ?? '');
    _obaReq($fechaPago8 !== '' && _obaYmdFrom8($fechaPago8) !== '', 'fecha_pago inválida en liquidación ' . ($idx + 1));
    $formaPago = _obaOnlyDigits($liq['forma_pago'] ?? '');
    _obaReq($formaPago !== '', 'forma_pago es obligatoria en liquidación ' . ($idx + 1));
    if (!empty($OBA_CATALOGOS['forma_pago'])) _obaReq(_obaHas($OBA_CATALOGOS['forma_pago'], $formaPago), 'forma_pago fuera de catálogo en liquidación ' . ($idx + 1));
    $instrumento = _obaOnlyDigits($liq['instrumento_monetario'] ?? '');
    if ($instrumento !== '' && !empty($OBA_CATALOGOS['instrumento_monetario'])) _obaReq(_obaHas($OBA_CATALOGOS['instrumento_monetario'], $instrumento), 'instrumento_monetario fuera de catálogo en liquidación ' . ($idx + 1));
    $moneda = _obaOnlyDigits($liq['moneda'] ?? '');
    _obaReq($moneda !== '', 'moneda es obligatoria en liquidación ' . ($idx + 1));
    if (!empty($OBA_CATALOGOS['moneda'])) _obaReq(_obaHas($OBA_CATALOGOS['moneda'], $moneda), 'moneda fuera de catálogo en liquidación ' . ($idx + 1));
    $monto = (float)($liq['monto_operacion'] ?? $liq['monto'] ?? 0);
    _obaReq($monto > 0, 'monto_operacion inválido en liquidación ' . ($idx + 1));
    $montoTotal += $monto;
    $datosLiquidacion[] = [
        'fecha_pago' => $fechaPago8,
        'forma_pago' => $formaPago,
        'instrumento_monetario' => $instrumento !== '' ? $instrumento : null,
        'moneda' => $moneda,
        'monto_operacion' => number_format($monto, 2, '.', ''),
    ];
}
_obaReq($montoTotal > 0, 'Monto total inválido');

$idFraccion = getIdVulnerableFraccionVII($pdo);
_obaReq((int)$idFraccion > 0, 'No se pudo resolver Fracción VII en cat_vulnerables');

$sumMontos = number_format($montoTotal, 2, '.', '');
$tipoOperacionSistema = 'VII:OBA:' . $tipoOperacion;
$operacionData = [
    'id_cliente' => $idCliente,
    'monto' => (float)$sumMontos,
    'fecha_operacion' => $fechaOperacionYmd,
    'id_fraccion' => (int)$idFraccion,
    'tipo_operacion' => $tipoOperacionSistema,
    'umbral_identificacion_uma_override' => pldFraccionVIIUmbralIdentificacion(),
    'umbral_aviso_uma_override' => pldFraccionVIIUmbralAviso(),
    'umbral_acumulacion_uma_override' => pldFraccionVIIUmbralAviso(),
];

$result = registrarOperacionPLD($pdo, $operacionData);
if (!($result['success'] ?? false)) _obaErr($result['message'] ?? 'Error al registrar aviso OBA', 400);

$personaAviso = _obaBuildPersonaAviso($pdo, $idCliente);
$avisoXml = [
    'referencia_aviso' => $referenciaAviso,
    'prioridad' => $prioridad,
    'alerta' => [
        'tipo_alerta' => $tipoAlerta,
        'descripcion_alerta' => $descripcionAlerta !== '' ? $descripcionAlerta : null,
    ],
    'persona_aviso' => [$personaAviso],
];
if (is_array($data['dueno_beneficiario'] ?? null) && $data['dueno_beneficiario'] !== []) {
    $avisoXml['dueno_beneficiario'] = $data['dueno_beneficiario'];
}
$avisoXml['detalle_operaciones'] = [[
    'datos_operacion' => [[
        'fecha_operacion' => $fechaOperacion8,
        'codigo_postal' => $codigoPostal,
        'tipo_operacion' => $tipoOperacion,
        'datos_objeto' => $datosObjeto,
        'datos_liquidacion' => $datosLiquidacion,
    ]],
]];

$payloadXml = ['informe' => [[
    'mes_reportado' => $mesReportado,
    'sujeto_obligado' => [
        'clave_entidad_colegiada' => $claveEntidad !== '' ? $claveEntidad : null,
        'clave_sujeto_obligado' => $claveSO,
        'clave_actividad' => 'OBA',
        'exento' => trim((string)($data['exento'] ?? '0')) === '1' ? '1' : null,
    ],
    'aviso' => [$avisoXml],
]]];

$xmlData = generateOBAXml($payloadXml);
$xml = (string)($xmlData['xml'] ?? '');
$xmlErrors = (array)($xmlData['errors'] ?? []);
_obaReq($xml !== '', 'No se pudo generar XML OBA');

$idOperacion = (int)($result['id_operacion'] ?? 0);
if ($idOperacion > 0) {
    try {
        $xmlNombre = 'oba_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $idOperacion]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_oba xml update: ' . $e->getMessage());
    }
}

if (function_exists('logChange')) {
    try {
        logChange($pdo, $userId, 'REGISTRAR_AVISO_OBA', 'operaciones_pld', $idOperacion, null, [
            'id_cliente' => $idCliente,
            'id_fraccion' => (int)$idFraccion,
            'tipo_operacion' => $tipoOperacionSistema,
            'monto' => (float)$sumMontos,
            'umbral_identificacion_uma' => pldFraccionVIIUmbralIdentificacion(),
            'umbral_aviso_uma' => pldFraccionVIIUmbralAviso(),
        ]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_oba bitacora: ' . $e->getMessage());
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso OBA registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => ($xml !== ''),
    'xml_warnings' => $xmlErrors,
], JSON_UNESCAPED_UNICODE);
