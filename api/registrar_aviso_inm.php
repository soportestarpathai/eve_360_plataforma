<?php
/**
 * API: Registrar Aviso INM (Fracción V Bis independiente de DIN).
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _inmErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function _inmReq(bool $ok, string $msg): void { if (!$ok) _inmErr($msg, 400); }
function _inmUp($v): string { return mb_strtoupper(trim((string)$v), 'UTF-8'); }
function _inmDigits($v): string { return preg_replace('/\D+/', '', (string)$v); }
function _inmMonth6($v): string { return substr(_inmDigits($v), 0, 6); }
function _inmDate8($v): string { $x = _inmDigits($v); return strlen($x) >= 8 ? substr($x, 0, 8) : ''; }
function _inmYmdFrom8(string $d8): string { if (!preg_match('/^\d{8}$/', $d8)) return ''; $y=(int)substr($d8,0,4); $m=(int)substr($d8,4,2); $d=(int)substr($d8,6,2); return checkdate($m,$d,$y) ? sprintf('%04d-%02d-%02d',$y,$m,$d) : ''; }
function _inmSanText($v): string { return preg_replace('/[^A-ZÑ0-9 \-\.,;:\/#&,_@\'"+\[\]{}()]/u', '', _inmUp($v)); }
function _inmHas(array $cat, $k): bool { $k = trim((string)$k); return $k !== '' && array_key_exists($k, $cat); }
function _inmFolio($v, int $max = 20): string { return substr(preg_replace('/[^A-Z0-9\-_]/', '', _inmUp($v)), 0, $max); }
function _inmRfcFisica($v): string { $v = _inmUp($v); return preg_match('/^[A-ZÑ&%+]{4}\d{6}[A-Z0-9]{3}$/u', $v) ? $v : ''; }
function _inmRfcMoral($v): string { $v = _inmUp($v); return preg_match('/^[A-ZÑ&%+]{3}\d{6}[A-Z0-9]{3}$/u', $v) ? $v : ''; }
function _inmCurp($v): string { $v = _inmUp($v); return preg_match('/^[A-Z]{4}\d{6}[MH][A-Z]{5}[A-Z0-9]{2}$/', $v) ? $v : ''; }

function _inmPaisClave(PDO $pdo, $idPais, string $fallback = 'MX'): string
{
    $idPais = (int)$idPais;
    if ($idPais <= 0) return $fallback;
    try {
        $stmt = $pdo->prepare("SELECT clave FROM cat_pais WHERE id_pais = ? LIMIT 1");
        $stmt->execute([$idPais]);
        $clave = _inmUp($stmt->fetchColumn() ?: '');
        return preg_match('/^[A-Z]{2}$/', $clave) ? $clave : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function _inmFirstPaisCliente(PDO $pdo, int $idCliente): string
{
    try {
        $stmt = $pdo->prepare("SELECT id_pais FROM clientes_nacionalidades WHERE id_cliente = ? AND (id_status = 1 OR id_status IS NULL) ORDER BY id_cliente_nacionalidad LIMIT 1");
        $stmt->execute([$idCliente]);
        return _inmPaisClave($pdo, $stmt->fetchColumn(), 'MX');
    } catch (Throwable $e) {
        return 'MX';
    }
}

function _inmFirstDireccionCliente(PDO $pdo, int $idCliente): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes_direcciones WHERE id_cliente = ? ORDER BY id_cliente_direccion LIMIT 1");
        $stmt->execute([$idCliente]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $cp = _inmDigits($row['codigo_postal'] ?? '');
        if (!preg_match('/^\d{5}$/', $cp)) return null;
        return ['nacional' => [
            'colonia' => _inmSanText($row['colonia'] ?? 'NO PROPORCIONADO'),
            'calle' => _inmSanText($row['calle'] ?? 'NO PROPORCIONADO'),
            'numero_exterior' => _inmSanText($row['numero_exterior'] ?? 'SN') ?: 'SN',
            'numero_interior' => _inmSanText($row['numero_interior'] ?? ''),
            'codigo_postal' => $cp,
        ]];
    } catch (Throwable $e) {
        return null;
    }
}

function _inmFirstTelefonoCliente(PDO $pdo, int $idCliente): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT dato_contacto FROM clientes_contactos WHERE id_cliente = ? AND (id_status = 1 OR id_status IS NULL) ORDER BY id_cliente_contacto");
        $stmt->execute([$idCliente]);
        $tel = '';
        $mail = '';
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dato = trim((string)($r['dato_contacto'] ?? ''));
            if ($mail === '' && strpos($dato, '@') !== false) $mail = _inmUp($dato);
            $digits = _inmDigits($dato);
            if ($tel === '' && preg_match('/^\d{10,12}$/', $digits)) $tel = $digits;
        }
        if ($tel === '' && $mail === '') return null;
        return ['clave_pais' => 'MX', 'numero_telefono' => $tel, 'correo_electronico' => $mail];
    } catch (Throwable $e) {
        return null;
    }
}

function _inmBuildRepresentante(PDO $pdo, int $idCliente): ?array
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
            'nombre' => _inmSanText($r['nombre'] ?? ''),
            'apellido_paterno' => _inmSanText($r['apellido_paterno'] ?? ''),
            'apellido_materno' => _inmSanText($r['apellido_materno'] ?? ''),
            'fecha_nacimiento' => _inmDate8($r['fecha_nacimiento'] ?? ''),
            'rfc' => _inmRfcFisica($r['tax_id'] ?? $r['rfc'] ?? ''),
            'curp' => _inmCurp($r['CURP'] ?? $r['curp'] ?? ''),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function _inmBuildPersonaAviso(PDO $pdo, int $idCliente): array
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
    _inmReq((bool)$base, 'Cliente no encontrado para persona_aviso');

    $pais = _inmFirstPaisCliente($pdo, $idCliente);
    if ((int)($base['es_fisica'] ?? 0) > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_fisicas WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $persona = ['tipo_persona' => ['persona_fisica' => [
            'nombre' => _inmSanText($f['nombre'] ?? ''),
            'apellido_paterno' => _inmSanText($f['apellido_paterno'] ?? ''),
            'apellido_materno' => _inmSanText($f['apellido_materno'] ?? ''),
            'fecha_nacimiento' => _inmDate8($f['fecha_nacimiento'] ?? ''),
            'rfc' => _inmRfcFisica($f['tax_id'] ?? $f['rfc'] ?? ''),
            'curp' => _inmCurp($f['CURP'] ?? $f['curp'] ?? ''),
            'pais_nacionalidad' => $pais,
            'actividad_economica' => '1000000',
        ]]];
        _inmReq($persona['tipo_persona']['persona_fisica']['nombre'] !== '' && $persona['tipo_persona']['persona_fisica']['apellido_paterno'] !== '' && $persona['tipo_persona']['persona_fisica']['apellido_materno'] !== '', 'Cliente físico incompleto para persona_aviso');
    } elseif ((int)($base['es_moral'] ?? 0) > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_morales WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $rep = _inmBuildRepresentante($pdo, $idCliente);
        _inmReq((bool)$rep, 'Persona moral requiere representante/apoderado para XML INM');
        $persona = ['tipo_persona' => ['persona_moral' => [
            'denominacion_razon' => _inmSanText($m['razon_social'] ?? ''),
            'fecha_constitucion' => _inmDate8($m['fecha_constitucion'] ?? ''),
            'rfc' => _inmRfcMoral($m['tax_id'] ?? $m['rfc'] ?? ''),
            'pais_nacionalidad' => $pais,
            'giro_mercantil' => '1000000',
            'representante_apoderado' => $rep,
        ]]];
        _inmReq($persona['tipo_persona']['persona_moral']['denominacion_razon'] !== '', 'Persona moral incompleta para persona_aviso');
    } else {
        $stmt = $pdo->prepare("SELECT * FROM clientes_fideicomisos WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $fi = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $rep = _inmBuildRepresentante($pdo, $idCliente);
        _inmReq((bool)$rep, 'Fideicomiso requiere apoderado/delegado para XML INM');
        $persona = ['tipo_persona' => ['fideicomiso' => [
            'denominacion_razon' => _inmSanText($fi['institucion_fiduciaria'] ?? 'FIDEICOMISO'),
            'rfc' => _inmRfcMoral($fi['rfc'] ?? ''),
            'identificador_fideicomiso' => _inmSanText($fi['numero_fideicomiso'] ?? ''),
            'apoderado_delegado' => $rep,
        ]]];
    }

    $dom = _inmFirstDireccionCliente($pdo, $idCliente);
    if ($dom) $persona['tipo_domicilio'] = $dom;
    $tel = _inmFirstTelefonoCliente($pdo, $idCliente);
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
    require_once __DIR__ . '/../config/pld_fraccion_v_bis.php';
    require_once __DIR__ . '/../config/inm_catalogos.php';
    require_once __DIR__ . '/../config/inm_xml_helper.php';
} catch (Throwable $e) {
    _inmErr('Error al inicializar: ' . $e->getMessage(), 500);
}

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_inmReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_inmReq(function_exists('userCanAccessINM') && userCanAccessINM($pdo, $userId), 'Sin permiso para registrar avisos INM');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionVBisActiva($pdo);
_inmReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fracción V Bis no está activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_inmReq(is_array($data), 'JSON inválido');

$idCliente = (int)($data['id_cliente'] ?? 0);
_inmReq($idCliente > 0, 'id_cliente es obligatorio');
$mesReportado = _inmMonth6($data['mes_reportado'] ?? '');
_inmReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mesReportado) && $mesReportado >= '201309' && $mesReportado <= date('Ym'), 'mes_reportado inválido');
$claveSO = _inmUp($data['clave_sujeto_obligado'] ?? '');
_inmReq((bool)preg_match('/^[A-ZÑ&%+]{3,4}\d{6}[A-Z0-9]{3}$/u', $claveSO), 'clave_sujeto_obligado inválida');
$claveEntidad = _inmUp($data['clave_entidad_colegiada'] ?? '');
if ($claveEntidad !== '') _inmReq((bool)preg_match('/^[A-ZÑ&%+]{3}\d{6}[A-Z0-9]{3}$/u', $claveEntidad), 'clave_entidad_colegiada inválida');
$referencia = _inmUp($data['referencia_aviso'] ?? '');
_inmReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $referencia), 'referencia_aviso inválida');
$prioridad = trim((string)($data['prioridad'] ?? '1'));
_inmReq(in_array($prioridad, ['1','2'], true), 'prioridad inválida');
$tipoAlerta = _inmDigits($data['tipo_alerta'] ?? '100');
_inmReq(_inmHas($INM_CATALOGOS['tipo_alerta'] ?? [], $tipoAlerta), 'tipo_alerta fuera de catálogo INM');
_inmReq(!($prioridad === '2' && $tipoAlerta === '100'), 'Cuando prioridad=2, tipo_alerta debe ser diferente de 100');
$descAlerta = _inmUp($data['descripcion_alerta'] ?? '');
if ($tipoAlerta === '9999') _inmReq($descAlerta !== '', 'descripcion_alerta es obligatoria cuando tipo_alerta=9999');

$fechaOperacion8 = _inmDate8($data['fecha_operacion'] ?? '');
_inmReq($fechaOperacion8 !== '' && _inmYmdFrom8($fechaOperacion8) !== '', 'fecha_operacion inválida');
$fechaOperacionYmd = _inmYmdFrom8($fechaOperacion8);
$tipoOperacion = _inmDigits($data['tipo_operacion'] ?? '');
_inmReq(_inmHas($INM_CATALOGOS['tipo_operacion'] ?? [], $tipoOperacion), 'tipo_operacion fuera de catálogo INM');
$figuraCliente = _inmDigits($data['figura_cliente'] ?? '');
_inmReq(_inmHas($INM_CATALOGOS['figura_cliente'] ?? [], $figuraCliente), 'figura_cliente fuera de catálogo INM');
$figuraSo = _inmDigits($data['figura_so'] ?? '');
_inmReq(_inmHas($INM_CATALOGOS['figura_so'] ?? [], $figuraSo), 'figura_so fuera de catálogo INM');

$inmueble = is_array($data['caracteristicas_inmueble'] ?? null) ? $data['caracteristicas_inmueble'] : [];
$tipoInmueble = _inmDigits($inmueble['tipo_inmueble'] ?? '');
_inmReq(_inmHas($INM_CATALOGOS['tipo_inmueble'] ?? [], $tipoInmueble), 'tipo_inmueble fuera de catálogo INM');
$valorPactado = (float)($inmueble['valor_pactado'] ?? 0);
_inmReq($valorPactado > 0, 'valor_pactado inválido');
$cpInmueble = _inmDigits($inmueble['codigo_postal'] ?? '');
_inmReq((bool)preg_match('/^\d{5}$/', $cpInmueble), 'codigo_postal del inmueble inválido');
$dimensionTerreno = (float)($inmueble['dimension_terreno'] ?? 1);
$dimensionConstruido = (float)($inmueble['dimension_construido'] ?? 1);
_inmReq($dimensionTerreno > 0 && $dimensionTerreno <= 9999999.99, 'dimension_terreno inválida');
_inmReq($dimensionConstruido > 0 && $dimensionConstruido <= 9999999.99, 'dimension_construido inválida');
$caracteristicas = [
    'tipo_inmueble' => $tipoInmueble,
    'valor_pactado' => number_format($valorPactado, 2, '.', ''),
    'colonia' => _inmSanText($inmueble['colonia'] ?? ''),
    'calle' => _inmSanText($inmueble['calle'] ?? ''),
    'numero_exterior' => _inmSanText($inmueble['numero_exterior'] ?? 'SN') ?: 'SN',
    'numero_interior' => _inmSanText($inmueble['numero_interior'] ?? ''),
    'codigo_postal' => $cpInmueble,
    'dimension_terreno' => number_format($dimensionTerreno, 2, '.', ''),
    'dimension_construido' => number_format($dimensionConstruido, 2, '.', ''),
    'folio_real' => _inmFolio($inmueble['folio_real'] ?? 'SIN_FOLIO', 200),
];
_inmReq($caracteristicas['colonia'] !== '' && $caracteristicas['calle'] !== '' && $caracteristicas['folio_real'] !== '', 'características de inmueble incompletas');

$instrumentoContrato = trim((string)($data['instrumento_o_contrato'] ?? 'contrato'));
if ($instrumentoContrato === 'instrumento') {
    $numeroInstrumento = _inmFolio($data['numero_instrumento_publico'] ?? '1', 20);
    $fechaInstrumento = _inmDate8($data['fecha_instrumento_publico'] ?? $fechaOperacion8);
    $notarioInstrumento = _inmFolio($data['notario_instrumento_publico'] ?? '1', 8);
    $entidadInstrumento = _inmDigits($data['entidad_instrumento_publico'] ?? '9');
    $valorAvaluo = (float)($data['valor_avaluo_catastral'] ?? $valorPactado);
    _inmReq($numeroInstrumento !== '', 'numero_instrumento_publico inválido');
    _inmReq($fechaInstrumento !== '' && _inmYmdFrom8($fechaInstrumento) !== '', 'fecha_instrumento_publico inválida');
    _inmReq($notarioInstrumento !== '', 'notario_instrumento_publico inválido');
    _inmReq((bool)preg_match('/^\d{1,2}$/', $entidadInstrumento), 'entidad_instrumento_publico inválida');
    _inmReq($valorAvaluo > 0, 'valor_avaluo_catastral inválido');
    $contratoInstrumento = [
        'datos_instrumento_publico' => [
            'numero_instrumento_publico' => $numeroInstrumento,
            'fecha_instrumento_publico' => $fechaInstrumento,
            'notario_instrumento_publico' => $notarioInstrumento,
            'entidad_instrumento_publico' => $entidadInstrumento,
            'valor_avaluo_catastral' => number_format($valorAvaluo, 2, '.', ''),
        ],
    ];
} else {
    $fechaContrato = _inmDate8($data['fecha_contrato'] ?? $fechaOperacion8);
    _inmReq($fechaContrato !== '' && _inmYmdFrom8($fechaContrato) !== '', 'fecha_contrato inválida');
    $contratoInstrumento = ['datos_contrato' => ['fecha_contrato' => $fechaContrato]];
}

$rawLiquidaciones = is_array($data['datos_liquidacion'] ?? null) ? $data['datos_liquidacion'] : [[
    'fecha_pago' => $data['fecha_pago'] ?? $fechaOperacion8,
    'forma_pago' => $data['forma_pago'] ?? '',
    'instrumento_monetario' => $data['instrumento_monetario'] ?? '',
    'moneda' => $data['moneda'] ?? '',
    'monto_operacion' => $data['monto'] ?? 0,
]];
$liqs = [];
$montoTotal = 0.0;
foreach ($rawLiquidaciones as $idx => $liq) {
    _inmReq(is_array($liq), 'datos_liquidacion inválido ' . ($idx + 1));
    $fechaPago = _inmDate8($liq['fecha_pago'] ?? '');
    _inmReq($fechaPago !== '' && _inmYmdFrom8($fechaPago) !== '', 'fecha_pago inválida ' . ($idx + 1));
    $formaPago = _inmDigits($liq['forma_pago'] ?? '');
    _inmReq(_inmHas($INM_CATALOGOS['forma_pago'] ?? [], $formaPago), 'forma_pago fuera de catálogo ' . ($idx + 1));
    $instrumento = _inmDigits($liq['instrumento_monetario'] ?? '');
    _inmReq($instrumento === '' || _inmHas($INM_CATALOGOS['instrumento_monetario'] ?? [], $instrumento), 'instrumento_monetario fuera de catálogo ' . ($idx + 1));
    $moneda = _inmDigits($liq['moneda'] ?? '');
    _inmReq(_inmHas($INM_CATALOGOS['moneda'] ?? [], $moneda), 'moneda fuera de catálogo ' . ($idx + 1));
    $monto = (float)($liq['monto_operacion'] ?? 0);
    _inmReq($monto > 0, 'monto_operacion inválido ' . ($idx + 1));
    $montoTotal += $monto;
    $liqs[] = [
        'fecha_pago' => $fechaPago,
        'forma_pago' => $formaPago,
        'instrumento_monetario' => $instrumento !== '' ? $instrumento : null,
        'moneda' => $moneda,
        'monto_operacion' => number_format($monto, 2, '.', ''),
    ];
}

$idFraccion = getIdVulnerableFraccionVBis($pdo);
_inmReq((int)$idFraccion > 0, 'No se pudo resolver Fracción V Bis en cat_vulnerables');
$result = registrarOperacionPLD($pdo, [
    'id_cliente' => $idCliente,
    'monto' => $montoTotal,
    'fecha_operacion' => $fechaOperacionYmd,
    'id_fraccion' => (int)$idFraccion,
    'tipo_operacion' => 'VBIS:INM:' . $tipoOperacion,
    'umbral_aviso_uma_override' => getUmbralAvisoVBis(),
    'umbral_acumulacion_uma_override' => getUmbralAvisoVBis(),
]);
if (!($result['success'] ?? false)) _inmErr($result['message'] ?? 'Error al registrar aviso INM', 400);

$personaAviso = is_array($data['persona_aviso'] ?? null) ? $data['persona_aviso'] : [_inmBuildPersonaAviso($pdo, $idCliente)];

$avisoXml = [
    'referencia_aviso' => $referencia,
    'prioridad' => $prioridad,
    'alerta' => ['tipo_alerta' => $tipoAlerta, 'descripcion_alerta' => $descAlerta !== '' ? $descAlerta : null],
    'persona_aviso' => $personaAviso,
];
if (is_array($data['dueno_beneficiario'] ?? null) && $data['dueno_beneficiario'] !== []) {
    $avisoXml['dueno_beneficiario'] = $data['dueno_beneficiario'];
}
$avisoXml['detalle_operaciones'] = [[
    'datos_operacion' => [[
        'fecha_operacion' => $fechaOperacion8,
        'tipo_operacion' => $tipoOperacion,
        'figura_cliente' => $figuraCliente,
        'figura_so' => $figuraSo,
        'caracteristicas_inmueble' => [$caracteristicas],
        'contrato_instrumento_publico' => $contratoInstrumento,
        'datos_liquidacion' => $liqs,
    ]],
]];

$payloadXml = ['informe' => [[
    'mes_reportado' => $mesReportado,
    'sujeto_obligado' => [
        'clave_entidad_colegiada' => $claveEntidad !== '' ? $claveEntidad : null,
        'clave_sujeto_obligado' => $claveSO,
        'clave_actividad' => 'INM',
        'exento' => trim((string)($data['exento'] ?? '0')) === '1' ? '1' : null,
    ],
    'aviso' => [$avisoXml],
]]];
$xmlData = generateINMXml($payloadXml);
$xml = (string)($xmlData['xml'] ?? '');
_inmReq($xml !== '', 'No se pudo generar XML INM');

$idOperacion = (int)($result['id_operacion'] ?? 0);
if ($idOperacion > 0) {
    try {
        $xmlNombre = 'inm_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $idOperacion]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_inm xml update: ' . $e->getMessage());
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso INM registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => ($xml !== ''),
], JSON_UNESCAPED_UNICODE);
