<?php
/**
 * API: Registrar Aviso CHV (Fracción III - Cheques de viajero).
 * Registra la operación, evalúa umbral UMA y genera el XML del aviso CHV.
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _chvErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function _chvUp($v): string { return mb_strtoupper(trim((string)$v), 'UTF-8'); }
function _chvMonth6($v): string { return substr(preg_replace('/\D+/', '', (string)$v), 0, 6); }
function _chvDate8($v): string { $x = preg_replace('/\D+/', '', (string)$v); return strlen($x) >= 8 ? substr($x, 0, 8) : ''; }
function _chvYmdFrom8(string $d8): string
{
    if (!preg_match('/^\d{8}$/', $d8)) return '';
    $y = (int)substr($d8, 0, 4);
    $m = (int)substr($d8, 4, 2);
    $d = (int)substr($d8, 6, 2);
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
}
function _chvReq(bool $ok, string $msg): void { if (!$ok) _chvErr($msg, 400); }
function _chvHas(array $cat, $k): bool
{
    $k = trim((string)$k);
    return $k !== '' && array_key_exists($k, $cat);
}
function _chvSanText($v): string { return preg_replace('/[^A-ZÑ0-9 \-\.,:\/#&,_@\'()]/u', '', _chvUp($v)); }
function _chvDateTo8($v): string { return _chvDate8((string)$v); }
function _chvOnlyDigits($v): string { return preg_replace('/\D+/', '', (string)$v); }
function _chvRfcFisica($v): string
{
    $v = _chvUp($v);
    return preg_match('/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/u', $v) ? $v : '';
}
function _chvRfcMoral($v): string
{
    $v = _chvUp($v);
    return preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $v) ? $v : '';
}
function _chvCurp($v): string
{
    $v = _chvUp($v);
    return preg_match('/^[A-Z]{4}\d{6}[MH][A-Z]{5}[0-9]{2}$/', $v) ? $v : '';
}

function _chvTableExists(PDO $pdo, string $table): bool
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

function _chvCpSepomex(PDO $pdo, string $cp): bool
{
    if (!preg_match('/^\d{5}$/', $cp)) return false;
    if (!_chvTableExists($pdo, 'cat_sepomex')) return true;

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

function _chvPaisClave(PDO $pdo, $idPais, string $fallback = 'MX'): string
{
    $idPais = (int)$idPais;
    if ($idPais <= 0) return $fallback;
    try {
        $stmt = $pdo->prepare("SELECT clave FROM cat_pais WHERE id_pais = ? LIMIT 1");
        $stmt->execute([$idPais]);
        $clave = _chvUp($stmt->fetchColumn() ?: '');
        return preg_match('/^[A-Z]{2}$/', $clave) ? $clave : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function _chvFirstPaisCliente(PDO $pdo, int $idCliente): string
{
    try {
        $stmt = $pdo->prepare("SELECT id_pais FROM clientes_nacionalidades WHERE id_cliente = ? AND (id_status = 1 OR id_status IS NULL) ORDER BY id_cliente_nacionalidad LIMIT 1");
        $stmt->execute([$idCliente]);
        return _chvPaisClave($pdo, $stmt->fetchColumn(), 'MX');
    } catch (Throwable $e) {
        return 'MX';
    }
}

function _chvFirstDireccionCliente(PDO $pdo, int $idCliente): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes_direcciones WHERE id_cliente = ? ORDER BY id_cliente_direccion LIMIT 1");
        $stmt->execute([$idCliente]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $cp = _chvOnlyDigits($row['codigo_postal'] ?? '');
        if (!preg_match('/^\d{5}$/', $cp)) return null;
        return [
            'nacional' => [
                'colonia' => _chvSanText($row['colonia'] ?? 'NO PROPORCIONADO'),
                'calle' => _chvSanText($row['calle'] ?? 'NO PROPORCIONADO'),
                'numero_exterior' => _chvSanText($row['numero_exterior'] ?? 'SN') ?: 'SN',
                'numero_interior' => _chvSanText($row['numero_interior'] ?? ''),
                'codigo_postal' => $cp,
            ],
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function _chvFirstTelefonoCliente(PDO $pdo, int $idCliente): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT dato_contacto FROM clientes_contactos WHERE id_cliente = ? AND (id_status = 1 OR id_status IS NULL) ORDER BY id_cliente_contacto");
        $stmt->execute([$idCliente]);
        $tel = '';
        $mail = '';
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dato = trim((string)($r['dato_contacto'] ?? ''));
            if ($mail === '' && strpos($dato, '@') !== false) $mail = _chvUp($dato);
            $digits = _chvOnlyDigits($dato);
            if ($tel === '' && preg_match('/^\d{10,12}$/', $digits)) $tel = $digits;
        }
        if ($tel === '' && $mail === '') return null;
        return [
            'clave_pais' => 'MX',
            'numero_telefono' => $tel,
            'correo_electronico' => $mail,
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function _chvBuildRepresentante(PDO $pdo, int $idCliente): ?array
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
            'nombre' => _chvSanText($r['nombre'] ?? ''),
            'apellido_paterno' => _chvSanText($r['apellido_paterno'] ?? ''),
            'apellido_materno' => _chvSanText($r['apellido_materno'] ?? ''),
            'fecha_nacimiento' => _chvDateTo8($r['fecha_nacimiento'] ?? ''),
            'rfc' => _chvRfcFisica($r['tax_id'] ?? $r['rfc'] ?? ''),
            'curp' => _chvCurp($r['CURP'] ?? $r['curp'] ?? ''),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function _chvBuildPersonaAviso(PDO $pdo, int $idCliente): array
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
    _chvReq((bool)$base, 'Cliente no encontrado para persona_aviso');

    $pais = _chvFirstPaisCliente($pdo, $idCliente);
    $persona = [];

    if ((int)($base['es_fisica'] ?? 0) > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_fisicas WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $persona = [
            'tipo_persona' => [
                'persona_fisica' => [
                    'nombre' => _chvSanText($f['nombre'] ?? ''),
                    'apellido_paterno' => _chvSanText($f['apellido_paterno'] ?? ''),
                    'apellido_materno' => _chvSanText($f['apellido_materno'] ?? ''),
                    'fecha_nacimiento' => _chvDateTo8($f['fecha_nacimiento'] ?? ''),
                    'rfc' => _chvRfcFisica($f['tax_id'] ?? $f['rfc'] ?? ''),
                    'curp' => _chvCurp($f['CURP'] ?? $f['curp'] ?? ''),
                    'pais_nacionalidad' => $pais,
                    'actividad_economica' => '1000000',
                ],
            ],
        ];
        _chvReq($persona['tipo_persona']['persona_fisica']['nombre'] !== '' && $persona['tipo_persona']['persona_fisica']['apellido_paterno'] !== '' && $persona['tipo_persona']['persona_fisica']['apellido_materno'] !== '', 'Cliente físico incompleto para persona_aviso');
    } elseif ((int)($base['es_moral'] ?? 0) > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_morales WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $rep = _chvBuildRepresentante($pdo, $idCliente);
        _chvReq((bool)$rep, 'Persona moral requiere representante/apoderado para XML CHV');
        $persona = [
            'tipo_persona' => [
                'persona_moral' => [
                    'denominacion_razon' => _chvSanText($m['razon_social'] ?? ''),
                    'fecha_constitucion' => _chvDateTo8($m['fecha_constitucion'] ?? ''),
                    'rfc' => _chvRfcMoral($m['tax_id'] ?? $m['rfc'] ?? ''),
                    'pais_nacionalidad' => $pais,
                    'giro_mercantil' => '1000000',
                    'representante_apoderado' => $rep,
                ],
            ],
        ];
        _chvReq($persona['tipo_persona']['persona_moral']['denominacion_razon'] !== '', 'Persona moral incompleta para persona_aviso');
    } else {
        $stmt = $pdo->prepare("SELECT * FROM clientes_fideicomisos WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $fi = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $rep = _chvBuildRepresentante($pdo, $idCliente);
        _chvReq((bool)$rep, 'Fideicomiso requiere apoderado/delegado para XML CHV');
        $persona = [
            'tipo_persona' => [
                'fideicomiso' => [
                    'denominacion_razon' => _chvSanText($fi['institucion_fiduciaria'] ?? 'FIDEICOMISO'),
                    'rfc' => _chvRfcMoral($fi['rfc'] ?? ''),
                    'identificador_fideicomiso' => _chvSanText($fi['numero_fideicomiso'] ?? ''),
                    'apoderado_delegado' => $rep,
                ],
            ],
        ];
    }

    $dom = _chvFirstDireccionCliente($pdo, $idCliente);
    if ($dom) $persona['tipo_domicilio'] = $dom;
    $tel = _chvFirstTelefonoCliente($pdo, $idCliente);
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
    require_once __DIR__ . '/../config/pld_fraccion_iii.php';
    require_once __DIR__ . '/../config/chv_catalogos.php';
    require_once __DIR__ . '/../config/chv_xml_helper.php';
} catch (Throwable $e) {
    _chvErr('Error al inicializar: ' . $e->getMessage(), 500);
}

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_chvReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_chvReq(function_exists('userCanAccessCHV') && userCanAccessCHV($pdo, $userId), 'Sin permiso para registrar avisos CHV');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionIIIActiva($pdo);
_chvReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fracción III no está activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_chvReq(is_array($data), 'JSON inválido');

$idCliente = (int)($data['id_cliente'] ?? 0);
_chvReq($idCliente > 0, 'id_cliente es obligatorio');

$mesReportado = _chvMonth6($data['mes_reportado'] ?? '');
_chvReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mesReportado) && $mesReportado >= '201309' && $mesReportado <= date('Ym'), 'mes_reportado inválido');

$claveSO = _chvUp($data['clave_sujeto_obligado'] ?? '');
_chvReq((bool)preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $claveSO), 'clave_sujeto_obligado inválida');

$claveEntidad = _chvUp($data['clave_entidad_colegiada'] ?? '');
if ($claveEntidad !== '') {
    _chvReq((bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $claveEntidad), 'clave_entidad_colegiada inválida');
}

$referenciaAviso = _chvUp($data['referencia_aviso'] ?? '');
_chvReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $referenciaAviso), 'referencia_aviso inválida');

$prioridad = trim((string)($data['prioridad'] ?? '1'));
_chvReq(in_array($prioridad, ['1', '2'], true), 'prioridad inválida');

$tipoAlerta = preg_replace('/\D+/', '', (string)($data['tipo_alerta'] ?? '100'));
_chvReq((bool)preg_match('/^\d{3,4}$/', $tipoAlerta), 'tipo_alerta inválido');
if (!empty($CHV_CATALOGOS['tipo_alerta'])) {
    _chvReq(_chvHas($CHV_CATALOGOS['tipo_alerta'], $tipoAlerta), 'tipo_alerta fuera de catálogo CHV');
}
_chvReq(!($prioridad === '2' && $tipoAlerta === '100'), 'Cuando prioridad=2, tipo_alerta debe ser diferente de 100');

$descripcionAlerta = _chvUp($data['descripcion_alerta'] ?? '');
if ($tipoAlerta === '9999') _chvReq($descripcionAlerta !== '', 'descripcion_alerta es obligatoria cuando tipo_alerta=9999');

$fechaOperacion8 = _chvDate8($data['fecha_operacion'] ?? '');
_chvReq($fechaOperacion8 !== '' && _chvYmdFrom8($fechaOperacion8) !== '', 'fecha_operacion inválida');
$fechaOperacionYmd = _chvYmdFrom8($fechaOperacion8);
_chvReq($fechaOperacionYmd >= '2013-09-01' && $fechaOperacionYmd <= date('Y-m-d'), 'fecha_operacion fuera de rango');

$codigoPostal = preg_replace('/\D+/', '', (string)($data['codigo_postal'] ?? ''));
_chvReq((bool)preg_match('/^\d{5}$/', $codigoPostal), 'codigo_postal inválido');
_chvReq(_chvCpSepomex($pdo, $codigoPostal), 'codigo_postal de operación no existe en SEPOMEX');

$tipoOperacion = preg_replace('/\D+/', '', (string)($data['tipo_operacion'] ?? ''));
_chvReq($tipoOperacion !== '', 'tipo_operacion es obligatorio');
if (!empty($CHV_CATALOGOS['tipo_operacion'])) {
    _chvReq(_chvHas($CHV_CATALOGOS['tipo_operacion'], $tipoOperacion), 'tipo_operacion fuera de catálogo CHV');
}

$rawCheques = is_array($data['datos_cheque'] ?? null) ? $data['datos_cheque'] : [[
    'numero_cheques' => $data['numero_cheques'] ?? '',
    'moneda_cheques' => $data['moneda_cheques'] ?? '',
]];
$datosCheque = [];
foreach ($rawCheques as $idx => $chq) {
    _chvReq(is_array($chq), 'datos_cheque inválido en partida ' . ($idx + 1));
    $numeroCheques = preg_replace('/\D+/', '', (string)($chq['numero_cheques'] ?? ''));
    _chvReq((bool)preg_match('/^\d{1,18}$/', $numeroCheques) && (int)$numeroCheques > 0, 'numero_cheques inválido en partida ' . ($idx + 1));

    $monedaCheques = preg_replace('/\D+/', '', (string)($chq['moneda_cheques'] ?? ''));
    _chvReq($monedaCheques !== '', 'moneda_cheques es obligatoria en partida ' . ($idx + 1));
    if (!empty($CHV_CATALOGOS['moneda_cheques'])) {
        _chvReq(_chvHas($CHV_CATALOGOS['moneda_cheques'], $monedaCheques), 'moneda_cheques fuera de catálogo CHV en partida ' . ($idx + 1));
    }
    $datosCheque[] = [
        'numero_cheques' => $numeroCheques,
        'moneda_cheques' => $monedaCheques,
    ];
}
_chvReq(!empty($datosCheque), 'Debe incluir al menos un datos_cheque');

$rawLiquidaciones = is_array($data['datos_liquidacion'] ?? null) ? $data['datos_liquidacion'] : [[
    'fecha_pago' => $data['fecha_pago'] ?? '',
    'instrumento_monetario' => $data['instrumento_monetario'] ?? '',
    'moneda' => $data['moneda'] ?? '',
    'monto_operacion' => $data['monto'] ?? 0,
]];
$datosLiquidacion = [];
$montoTotal = 0.0;
foreach ($rawLiquidaciones as $idx => $liq) {
    _chvReq(is_array($liq), 'datos_liquidacion inválido en partida ' . ($idx + 1));
    $fechaPago8Item = _chvDate8($liq['fecha_pago'] ?? '');
    _chvReq($fechaPago8Item !== '' && _chvYmdFrom8($fechaPago8Item) !== '', 'fecha_pago inválida en liquidación ' . ($idx + 1));

    $instrumentoMonetario = preg_replace('/\D+/', '', (string)($liq['instrumento_monetario'] ?? ''));
    _chvReq($instrumentoMonetario !== '', 'instrumento_monetario es obligatorio en liquidación ' . ($idx + 1));
    if (!empty($CHV_CATALOGOS['instrumento_monetario'])) {
        _chvReq(_chvHas($CHV_CATALOGOS['instrumento_monetario'], $instrumentoMonetario), 'instrumento_monetario fuera de catálogo en liquidación ' . ($idx + 1));
    }

    $moneda = preg_replace('/\D+/', '', (string)($liq['moneda'] ?? ''));
    _chvReq($moneda !== '', 'moneda es obligatoria en liquidación ' . ($idx + 1));
    if (!empty($CHV_CATALOGOS['moneda'])) {
        _chvReq(_chvHas($CHV_CATALOGOS['moneda'], $moneda), 'moneda fuera de catálogo en liquidación ' . ($idx + 1));
    }

    $monto = (float)($liq['monto_operacion'] ?? $liq['monto'] ?? 0);
    _chvReq($monto > 0, 'monto_operacion inválido en liquidación ' . ($idx + 1));
    $montoTotal += $monto;
    $datosLiquidacion[] = [
        'fecha_pago' => $fechaPago8Item,
        'instrumento_monetario' => $instrumentoMonetario,
        'moneda' => $moneda,
        'monto_operacion' => number_format($monto, 2, '.', ''),
    ];
}
_chvReq($montoTotal > 0, 'Monto total inválido');

$exento = trim((string)($data['exento'] ?? '0')) === '1' ? '1' : null;

$idFraccion = getIdVulnerableFraccionIII($pdo);
_chvReq((int)$idFraccion > 0, 'No se pudo resolver Fracción III en cat_vulnerables');

$sumMontos = number_format($montoTotal, 2, '.', '');
$tipoOperacionSistema = 'III:CHV:' . $tipoOperacion;

$operacionData = [
    'id_cliente' => $idCliente,
    'monto' => (float)$sumMontos,
    'fecha_operacion' => $fechaOperacionYmd,
    'id_fraccion' => (int)$idFraccion,
    'tipo_operacion' => $tipoOperacionSistema,
    'umbral_aviso_uma_override' => pldFraccionIIIUmbralAviso(),
    'umbral_acumulacion_uma_override' => pldFraccionIIIUmbralAviso(),
];

$result = registrarOperacionPLD($pdo, $operacionData);
if (!($result['success'] ?? false)) {
    _chvErr($result['message'] ?? 'Error al registrar aviso CHV', 400);
}

$personaAviso = _chvBuildPersonaAviso($pdo, $idCliente);
$duenosBeneficiarios = [];
if (is_array($data['dueno_beneficiario'] ?? null)) {
    $duenosBeneficiarios = $data['dueno_beneficiario'];
}

$avisoXml = [
    'referencia_aviso' => $referenciaAviso,
    'prioridad' => $prioridad,
    'alerta' => [
        'tipo_alerta' => $tipoAlerta,
        'descripcion_alerta' => $descripcionAlerta !== '' ? $descripcionAlerta : null,
    ],
    'persona_aviso' => [$personaAviso],
];
if ($duenosBeneficiarios !== []) {
    $avisoXml['dueno_beneficiario'] = $duenosBeneficiarios;
}
$avisoXml['detalle_operaciones'] = [
    [
        'datos_operacion' => [[
            'fecha_operacion' => $fechaOperacion8,
            'codigo_postal' => $codigoPostal,
            'tipo_operacion' => $tipoOperacion,
            'datos_cheque' => $datosCheque,
            'datos_liquidacion' => $datosLiquidacion,
        ]],
    ],
];

$payloadXml = [
    'informe' => [[
        'mes_reportado' => $mesReportado,
        'sujeto_obligado' => [
            'clave_entidad_colegiada' => $claveEntidad !== '' ? $claveEntidad : null,
            'clave_sujeto_obligado' => $claveSO,
            'clave_actividad' => 'CHV',
            'exento' => $exento,
        ],
        'aviso' => [$avisoXml],
    ]],
];

$xmlData = generateCHVXml($payloadXml);
$xml = (string)($xmlData['xml'] ?? '');
$xmlErrors = (array)($xmlData['errors'] ?? []);
_chvReq($xml !== '', 'No se pudo generar XML CHV');

$idOperacion = (int)($result['id_operacion'] ?? 0);
if ($idOperacion > 0 && $xml !== '') {
    try {
        $xmlNombre = 'chv_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $idOperacion]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_chv xml update: ' . $e->getMessage());
    }
}

if (function_exists('logChange')) {
    try {
        logChange($pdo, $userId, 'REGISTRAR_AVISO_CHV', 'operaciones_pld', $idOperacion, null, [
            'id_cliente' => $idCliente,
            'id_fraccion' => (int)$idFraccion,
            'tipo_operacion' => $tipoOperacionSistema,
            'numero_cheques' => array_sum(array_map(fn($x) => (int)($x['numero_cheques'] ?? 0), $datosCheque)),
            'monto' => (float)$sumMontos,
            'umbral_identificacion' => 'SIEMPRE',
            'umbral_aviso_uma' => pldFraccionIIIUmbralAviso(),
        ]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_chv bitacora: ' . $e->getMessage());
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso CHV registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => ($xml !== ''),
    'xml_warnings' => $xmlErrors,
], JSON_UNESCAPED_UNICODE);
