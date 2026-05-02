<?php
/**
 * API: Registrar Aviso ARI (Fraccion XV - Uso o goce de inmuebles).
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _ariErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function _ariReq(bool $ok, string $msg): void { if (!$ok) _ariErr($msg, 400); }
function _ariUp($v): string { return mb_strtoupper(trim((string)$v), 'UTF-8'); }
function _ariDigits($v): string { return preg_replace('/\D+/', '', (string)$v); }
function _ariMonth6($v): string { return substr(_ariDigits($v), 0, 6); }
function _ariDate8($v): string { $x = _ariDigits($v); return strlen($x) >= 8 ? substr($x, 0, 8) : ''; }
function _ariYmdFrom8(string $d8): string
{
    if (!preg_match('/^\d{8}$/', $d8)) return '';
    $y = (int)substr($d8, 0, 4);
    $m = (int)substr($d8, 4, 2);
    $d = (int)substr($d8, 6, 2);
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
}
function _ariSanText($v): string { return preg_replace('/[^A-ZÑ0-9 \-\.,;:\/#&,_@\'"+\[\]{}()]/u', '', _ariUp($v)); }
function _ariHas(array $cat, $k): bool { $k = trim((string)$k); return $k !== '' && array_key_exists($k, $cat); }
function _ariFolio($v, int $max = 40): string { return substr(preg_replace('/[^A-Z0-9\-_]/', '', _ariUp($v)), 0, $max); }
function _ariRfcFisica($v): string { $v = _ariUp($v); return preg_match('/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/u', $v) ? $v : ''; }
function _ariRfcMoral($v): string { $v = _ariUp($v); return preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $v) ? $v : ''; }
function _ariCurp($v): string { $v = _ariUp($v); return preg_match('/^[A-Z]{4}\d{6}[MH][A-Z]{5}[0-9]{2}$/', $v) ? $v : ''; }

function _ariPaisClave(PDO $pdo, $idPais, string $fallback = 'MX'): string
{
    $idPais = (int)$idPais;
    if ($idPais <= 0) return $fallback;
    try {
        $stmt = $pdo->prepare("SELECT clave FROM cat_pais WHERE id_pais = ? LIMIT 1");
        $stmt->execute([$idPais]);
        $clave = _ariUp($stmt->fetchColumn() ?: '');
        return preg_match('/^[A-Z]{2}$/', $clave) ? $clave : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function _ariFirstPaisCliente(PDO $pdo, int $idCliente): string
{
    try {
        $stmt = $pdo->prepare("SELECT id_pais FROM clientes_nacionalidades WHERE id_cliente = ? AND (id_status = 1 OR id_status IS NULL) ORDER BY id_cliente_nacionalidad LIMIT 1");
        $stmt->execute([$idCliente]);
        return _ariPaisClave($pdo, $stmt->fetchColumn(), 'MX');
    } catch (Throwable $e) {
        return 'MX';
    }
}

function _ariFirstDireccionCliente(PDO $pdo, int $idCliente): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes_direcciones WHERE id_cliente = ? ORDER BY id_cliente_direccion LIMIT 1");
        $stmt->execute([$idCliente]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $cp = _ariDigits($row['codigo_postal'] ?? '');
        if (!preg_match('/^\d{5}$/', $cp)) return null;
        return ['nacional' => [
            'colonia' => _ariSanText($row['colonia'] ?? 'NO PROPORCIONADO'),
            'calle' => _ariSanText($row['calle'] ?? 'NO PROPORCIONADO'),
            'numero_exterior' => _ariSanText($row['numero_exterior'] ?? 'SN') ?: 'SN',
            'numero_interior' => _ariSanText($row['numero_interior'] ?? ''),
            'codigo_postal' => $cp,
        ]];
    } catch (Throwable $e) {
        return null;
    }
}

function _ariFirstTelefonoCliente(PDO $pdo, int $idCliente): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT dato_contacto FROM clientes_contactos WHERE id_cliente = ? AND (id_status = 1 OR id_status IS NULL) ORDER BY id_cliente_contacto");
        $stmt->execute([$idCliente]);
        $tel = '';
        $mail = '';
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dato = trim((string)($r['dato_contacto'] ?? ''));
            if ($mail === '' && strpos($dato, '@') !== false) $mail = _ariUp($dato);
            $digits = _ariDigits($dato);
            if ($tel === '' && preg_match('/^\d{10,12}$/', $digits)) $tel = $digits;
        }
        if ($tel === '' && $mail === '') return null;
        return ['clave_pais' => 'MX', 'numero_telefono' => $tel, 'correo_electronico' => $mail];
    } catch (Throwable $e) {
        return null;
    }
}

function _ariBuildRepresentante(PDO $pdo, int $idCliente): ?array
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
            'nombre' => _ariSanText($r['nombre'] ?? ''),
            'apellido_paterno' => _ariSanText($r['apellido_paterno'] ?? ''),
            'apellido_materno' => _ariSanText($r['apellido_materno'] ?? ''),
            'fecha_nacimiento' => _ariDate8($r['fecha_nacimiento'] ?? ''),
            'rfc' => _ariRfcFisica($r['tax_id'] ?? $r['rfc'] ?? ''),
            'curp' => _ariCurp($r['CURP'] ?? $r['curp'] ?? ''),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function _ariBuildPersonaAviso(PDO $pdo, int $idCliente): array
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
    _ariReq((bool)$base, 'Cliente no encontrado para persona_aviso');

    $pais = _ariFirstPaisCliente($pdo, $idCliente);
    if ((int)($base['es_fisica'] ?? 0) > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_fisicas WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $persona = ['tipo_persona' => ['persona_fisica' => [
            'nombre' => _ariSanText($f['nombre'] ?? ''),
            'apellido_paterno' => _ariSanText($f['apellido_paterno'] ?? ''),
            'apellido_materno' => _ariSanText($f['apellido_materno'] ?? ''),
            'fecha_nacimiento' => _ariDate8($f['fecha_nacimiento'] ?? ''),
            'rfc' => _ariRfcFisica($f['tax_id'] ?? $f['rfc'] ?? ''),
            'curp' => _ariCurp($f['CURP'] ?? $f['curp'] ?? ''),
            'pais_nacionalidad' => $pais,
            'actividad_economica' => '1000000',
        ]]];
        _ariReq($persona['tipo_persona']['persona_fisica']['nombre'] !== '' && $persona['tipo_persona']['persona_fisica']['apellido_paterno'] !== '' && $persona['tipo_persona']['persona_fisica']['apellido_materno'] !== '', 'Cliente físico incompleto para persona_aviso');
    } elseif ((int)($base['es_moral'] ?? 0) > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_morales WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $rep = _ariBuildRepresentante($pdo, $idCliente);
        _ariReq((bool)$rep, 'Persona moral requiere representante/apoderado para XML ARI');
        $persona = ['tipo_persona' => ['persona_moral' => [
            'denominacion_razon' => _ariSanText($m['razon_social'] ?? ''),
            'fecha_constitucion' => _ariDate8($m['fecha_constitucion'] ?? ''),
            'rfc' => _ariRfcMoral($m['tax_id'] ?? $m['rfc'] ?? ''),
            'pais_nacionalidad' => $pais,
            'giro_mercantil' => '1000000',
            'representante_apoderado' => $rep,
        ]]];
        _ariReq($persona['tipo_persona']['persona_moral']['denominacion_razon'] !== '', 'Persona moral incompleta para persona_aviso');
    } else {
        $stmt = $pdo->prepare("SELECT * FROM clientes_fideicomisos WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $fi = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $rep = _ariBuildRepresentante($pdo, $idCliente);
        _ariReq((bool)$rep, 'Fideicomiso requiere apoderado/delegado para XML ARI');
        $persona = ['tipo_persona' => ['fideicomiso' => [
            'denominacion_razon' => _ariSanText($fi['institucion_fiduciaria'] ?? 'FIDEICOMISO'),
            'rfc' => _ariRfcMoral($fi['rfc'] ?? ''),
            'identificador_fideicomiso' => _ariSanText($fi['numero_fideicomiso'] ?? ''),
            'apoderado_delegado' => $rep,
        ]]];
    }

    $dom = _ariFirstDireccionCliente($pdo, $idCliente);
    if ($dom) $persona['tipo_domicilio'] = $dom;
    $tel = _ariFirstTelefonoCliente($pdo, $idCliente);
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
    require_once __DIR__ . '/../config/pld_fraccion_xv.php';
    require_once __DIR__ . '/../config/ari_catalogos.php';
    require_once __DIR__ . '/../config/ari_xml_helper.php';
} catch (Throwable $e) {
    _ariErr('Error al inicializar: ' . $e->getMessage(), 500);
}

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_ariReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_ariReq(function_exists('userCanAccessARI') && userCanAccessARI($pdo, $userId), 'Sin permiso para registrar avisos ARI');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionXVActiva($pdo);
_ariReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fraccion XV no esta activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_ariReq(is_array($data), 'JSON invalido');

$idCliente = (int)($data['id_cliente'] ?? 0);
_ariReq($idCliente > 0, 'id_cliente es obligatorio');
$mesReportado = _ariMonth6($data['mes_reportado'] ?? '');
_ariReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mesReportado) && $mesReportado >= '201309' && $mesReportado <= date('Ym'), 'mes_reportado invalido');
$claveSO = _ariUp($data['clave_sujeto_obligado'] ?? '');
_ariReq((bool)preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $claveSO), 'clave_sujeto_obligado invalida');
$claveEntidad = _ariUp($data['clave_entidad_colegiada'] ?? '');
if ($claveEntidad !== '') _ariReq((bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $claveEntidad), 'clave_entidad_colegiada invalida');
$referencia = _ariUp($data['referencia_aviso'] ?? '');
_ariReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $referencia), 'referencia_aviso invalida');
$prioridad = trim((string)($data['prioridad'] ?? '1'));
_ariReq(in_array($prioridad, ['1','2'], true), 'prioridad invalida');
$tipoAlerta = _ariDigits($data['tipo_alerta'] ?? '100');
_ariReq(_ariHas($ARI_CATALOGOS['tipo_alerta'] ?? [], $tipoAlerta), 'tipo_alerta fuera de catalogo ARI');
_ariReq(!($prioridad === '2' && $tipoAlerta === '100'), 'Cuando prioridad=2, tipo_alerta debe ser diferente de 100');
$descAlerta = _ariSanText($data['descripcion_alerta'] ?? '');
if ($tipoAlerta === '9999') _ariReq($descAlerta !== '', 'descripcion_alerta es obligatoria cuando tipo_alerta=9999');

$fechaOperacion8 = _ariDate8($data['fecha_operacion'] ?? '');
_ariReq($fechaOperacion8 !== '' && _ariYmdFrom8($fechaOperacion8) !== '', 'fecha_operacion invalida');
$tipoOperacion = _ariDigits($data['tipo_operacion'] ?? '');
_ariReq(_ariHas($ARI_CATALOGOS['tipo_operacion'] ?? [], $tipoOperacion), 'tipo_operacion fuera de catalogo ARI');
$tipoInmueble = _ariDigits($data['tipo_inmueble'] ?? '');
_ariReq(_ariHas($ARI_CATALOGOS['tipo_inmueble'] ?? [], $tipoInmueble), 'tipo_inmueble fuera de catalogo ARI');
$cp = _ariDigits($data['codigo_postal'] ?? '');
_ariReq((bool)preg_match('/^\d{5}$/', $cp), 'codigo_postal invalido');
$colonia = _ariSanText($data['colonia'] ?? '');
$calle = _ariSanText($data['calle'] ?? '');
$numeroExterior = _ariSanText($data['numero_exterior'] ?? '');
$numeroInterior = _ariSanText($data['numero_interior'] ?? '');
_ariReq($colonia !== '' && $calle !== '' && $numeroExterior !== '', 'domicilio del inmueble incompleto');
$fechaInicio8 = _ariDate8($data['fecha_inicio'] ?? $fechaOperacion8);
$fechaTermino8 = _ariDate8($data['fecha_termino'] ?? '');
_ariReq($fechaInicio8 !== '' && _ariYmdFrom8($fechaInicio8) !== '', 'fecha_inicio invalida');
_ariReq($fechaTermino8 !== '' && _ariYmdFrom8($fechaTermino8) !== '' && $fechaTermino8 >= $fechaInicio8, 'fecha_termino invalida');
$valorReferencia = (float)($data['valor_referencia'] ?? 0);
_ariReq($valorReferencia > 0, 'valor_referencia invalido');
$folioReal = _ariFolio($data['folio_real'] ?? '', 200);
_ariReq($folioReal !== '', 'folio_real es obligatorio');

$formaPago = _ariDigits($data['forma_pago'] ?? '');
_ariReq(_ariHas($ARI_CATALOGOS['forma_pago'] ?? [], $formaPago), 'forma_pago fuera de catalogo ARI');
$instrumento = _ariDigits($data['instrumento_monetario'] ?? '');
_ariReq(_ariHas($ARI_CATALOGOS['instrumento_monetario'] ?? [], $instrumento), 'instrumento_monetario fuera de catalogo');
$moneda = _ariDigits($data['moneda'] ?? '');
_ariReq(_ariHas($ARI_CATALOGOS['moneda'] ?? [], $moneda), 'moneda fuera de catalogo');
if ($formaPago === '5') _ariReq(in_array($instrumento, ['16', '99'], true), 'Cuando forma_pago es Permuta, instrumento_monetario debe ser Activos Virtuales u Otros');
if (in_array($instrumento, ['13', '14'], true)) {
    _ariReq((int)$moneda >= 159 && (int)$moneda <= 179, 'Para oro/plata amonedada, moneda debe estar entre 159 y 179');
} else {
    _ariReq(!((int)$moneda >= 159 && (int)$moneda <= 179), 'La moneda 159-179 solo aplica para oro/plata amonedada');
}
$monto = (float)($data['monto_operacion'] ?? 0);
_ariReq($monto > 0, 'monto_operacion invalido');
$montoFmt = number_format($monto, 2, '.', '');

$idFraccion = getIdVulnerableFraccionXV($pdo);
_ariReq((int)$idFraccion > 0, 'No se pudo resolver Fraccion XV en cat_vulnerables');
$result = registrarOperacionPLD($pdo, [
    'id_cliente' => $idCliente,
    'monto' => $monto,
    'fecha_operacion' => _ariYmdFrom8($fechaOperacion8),
    'id_fraccion' => (int)$idFraccion,
    'tipo_operacion' => 'XV:ARI:' . $tipoOperacion,
    'umbral_identificacion_uma_override' => pldFraccionXVUmbralIdentificacion(),
    'umbral_aviso_uma_override' => pldFraccionXVUmbralAviso(),
    'umbral_acumulacion_uma_override' => pldFraccionXVUmbralAviso(),
]);
if (!($result['success'] ?? false)) _ariErr($result['message'] ?? 'Error al registrar aviso ARI', 400);

$payloadXml = ['informe' => [[
    'mes_reportado' => $mesReportado,
    'sujeto_obligado' => [
        'clave_entidad_colegiada' => $claveEntidad !== '' ? $claveEntidad : null,
        'clave_sujeto_obligado' => $claveSO,
        'clave_actividad' => 'ARI',
        'exento' => trim((string)($data['exento'] ?? '0')) === '1' ? '1' : null,
    ],
    'aviso' => [[
        'referencia_aviso' => $referencia,
        'prioridad' => $prioridad,
        'alerta' => ['tipo_alerta' => $tipoAlerta, 'descripcion_alerta' => $descAlerta !== '' ? $descAlerta : null],
        'persona_aviso' => [_ariBuildPersonaAviso($pdo, $idCliente)],
        'detalle_operaciones' => [[
            'datos_operacion' => [[
                'fecha_operacion' => $fechaOperacion8,
                'tipo_operacion' => $tipoOperacion,
                'caracteristicas' => [
                    'fecha_inicio' => $fechaInicio8,
                    'fecha_termino' => $fechaTermino8,
                    'tipo_inmueble' => $tipoInmueble,
                    'valor_referencia' => number_format($valorReferencia, 2, '.', ''),
                    'colonia' => $colonia,
                    'calle' => $calle,
                    'numero_exterior' => $numeroExterior,
                    'numero_interior' => $numeroInterior !== '' ? $numeroInterior : null,
                    'codigo_postal' => $cp,
                    'folio_real' => $folioReal,
                ],
                'datos_liquidacion' => [[
                    'fecha_pago' => $fechaOperacion8,
                    'forma_pago' => $formaPago,
                    'instrumento_monetario' => $instrumento !== '' ? $instrumento : null,
                    'moneda' => $moneda,
                    'monto_operacion' => $montoFmt,
                ]],
            ]],
        ]],
    ]],
]]];
$xmlData = generateARIXml($payloadXml);
$xml = (string)($xmlData['xml'] ?? '');
$idOperacion = (int)($result['id_operacion'] ?? 0);
if ($idOperacion > 0 && $xml !== '') {
    try {
        $xmlNombre = 'ari_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $idOperacion]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_ari xml update: ' . $e->getMessage());
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso ARI registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => ($xml !== ''),
], JSON_UNESCAPED_UNICODE);
