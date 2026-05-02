<?php
/**
 * API: Registrar Aviso TCV (Fracción X).
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _tcvErr(string $msg, int $code = 500): void
{
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function _tcvReq(bool $ok, string $msg): void { if (!$ok) _tcvErr($msg, 400); }
function _tcvUp($v): string { return mb_strtoupper(trim((string)$v), 'UTF-8'); }
function _tcvDigits($v): string { return preg_replace('/\D+/', '', (string)$v); }
function _tcvMonth6($v): string { return substr(_tcvDigits($v), 0, 6); }
function _tcvDate8($v): string { $x = _tcvDigits($v); return strlen($x) >= 8 ? substr($x, 0, 8) : ''; }
function _tcvYmdFrom8(string $d8): string { if (!preg_match('/^\d{8}$/', $d8)) return ''; $y=(int)substr($d8,0,4); $m=(int)substr($d8,4,2); $d=(int)substr($d8,6,2); return checkdate($m,$d,$y) ? sprintf('%04d-%02d-%02d',$y,$m,$d) : ''; }
function _tcvSanText($v): string { return preg_replace('/[^A-ZÑ0-9 \-\.,:\/#&,_@\'$]/u', '', _tcvUp($v)); }
function _tcvHas(array $cat, $k): bool { $k = trim((string)$k); return $k !== '' && array_key_exists($k, $cat); }
function _tcvRfcFisica($v): string { $v = _tcvUp($v); return preg_match('/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/u', $v) ? $v : ''; }
function _tcvRfcMoral($v): string { $v = _tcvUp($v); return preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $v) ? $v : ''; }
function _tcvCurp($v): string { $v = _tcvUp($v); return preg_match('/^[A-Z]{4}\d{6}[MH][A-Z]{5}[0-9]{2}$/', $v) ? $v : ''; }

function _tcvPaisClave(PDO $pdo, $idPais, string $fallback = 'MX'): string
{
    $idPais = (int)$idPais;
    if ($idPais <= 0) return $fallback;
    try {
        $stmt = $pdo->prepare("SELECT clave FROM cat_pais WHERE id_pais = ? LIMIT 1");
        $stmt->execute([$idPais]);
        $clave = _tcvUp($stmt->fetchColumn() ?: '');
        return preg_match('/^[A-Z]{2}$/', $clave) ? $clave : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function _tcvFirstPaisCliente(PDO $pdo, int $idCliente): string
{
    try {
        $stmt = $pdo->prepare("SELECT id_pais FROM clientes_nacionalidades WHERE id_cliente = ? AND (id_status = 1 OR id_status IS NULL) ORDER BY id_cliente_nacionalidad LIMIT 1");
        $stmt->execute([$idCliente]);
        return _tcvPaisClave($pdo, $stmt->fetchColumn(), 'MX');
    } catch (Throwable $e) {
        return 'MX';
    }
}

function _tcvFirstDireccionCliente(PDO $pdo, int $idCliente): ?array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes_direcciones WHERE id_cliente = ? ORDER BY id_cliente_direccion LIMIT 1");
        $stmt->execute([$idCliente]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $cp = _tcvDigits($row['codigo_postal'] ?? '');
        if (!preg_match('/^\d{5}$/', $cp)) return null;
        return ['nacional' => [
            'colonia' => _tcvSanText($row['colonia'] ?? 'NO PROPORCIONADO'),
            'calle' => _tcvSanText($row['calle'] ?? 'NO PROPORCIONADO'),
            'numero_exterior' => _tcvSanText($row['numero_exterior'] ?? 'SN') ?: 'SN',
            'numero_interior' => _tcvSanText($row['numero_interior'] ?? ''),
            'codigo_postal' => $cp,
        ]];
    } catch (Throwable $e) {
        return null;
    }
}

function _tcvBuildRepresentante(PDO $pdo, int $idCliente): ?array
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
            'nombre' => _tcvSanText($r['nombre'] ?? ''),
            'apellido_paterno' => _tcvSanText($r['apellido_paterno'] ?? ''),
            'apellido_materno' => _tcvSanText($r['apellido_materno'] ?? ''),
            'fecha_nacimiento' => _tcvDate8($r['fecha_nacimiento'] ?? ''),
            'rfc' => _tcvRfcFisica($r['tax_id'] ?? $r['rfc'] ?? ''),
            'curp' => _tcvCurp($r['CURP'] ?? $r['curp'] ?? ''),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function _tcvBuildPersonaAviso(PDO $pdo, int $idCliente): array
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
    _tcvReq((bool)$base, 'Cliente no encontrado para persona_aviso');
    $pais = _tcvFirstPaisCliente($pdo, $idCliente);
    if ((int)($base['es_fisica'] ?? 0) > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_fisicas WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $f = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $persona = ['tipo_persona' => ['persona_fisica' => [
            'nombre' => _tcvSanText($f['nombre'] ?? ''),
            'apellido_paterno' => _tcvSanText($f['apellido_paterno'] ?? ''),
            'apellido_materno' => _tcvSanText($f['apellido_materno'] ?? ''),
            'fecha_nacimiento' => _tcvDate8($f['fecha_nacimiento'] ?? ''),
            'rfc' => _tcvRfcFisica($f['tax_id'] ?? $f['rfc'] ?? ''),
            'curp' => _tcvCurp($f['CURP'] ?? $f['curp'] ?? ''),
            'pais_nacionalidad' => $pais,
            'actividad_economica' => '1000000',
        ]]];
        _tcvReq($persona['tipo_persona']['persona_fisica']['nombre'] !== '' && $persona['tipo_persona']['persona_fisica']['apellido_paterno'] !== '' && $persona['tipo_persona']['persona_fisica']['apellido_materno'] !== '', 'Cliente físico incompleto para persona_aviso');
    } elseif ((int)($base['es_moral'] ?? 0) > 0) {
        $stmt = $pdo->prepare("SELECT * FROM clientes_morales WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$idCliente]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $persona = ['tipo_persona' => ['persona_moral' => [
            'denominacion_razon' => _tcvSanText($m['razon_social'] ?? ''),
            'fecha_constitucion' => _tcvDate8($m['fecha_constitucion'] ?? ''),
            'rfc' => _tcvRfcMoral($m['tax_id'] ?? $m['rfc'] ?? ''),
            'pais_nacionalidad' => $pais,
            'giro_mercantil' => '1000000',
            'representante_apoderado' => _tcvBuildRepresentante($pdo, $idCliente) ?: [
                'nombre' => 'NO PROPORCIONADO',
                'apellido_paterno' => 'NO PROPORCIONADO',
                'apellido_materno' => 'NO PROPORCIONADO',
            ],
        ]]];
        _tcvReq($persona['tipo_persona']['persona_moral']['denominacion_razon'] !== '', 'Persona moral incompleta para persona_aviso');
    } else {
        $persona = ['tipo_persona' => ['fideicomiso' => [
            'denominacion_razon' => 'FIDEICOMISO',
            'identificador_fideicomiso' => 'SIN IDENTIFICADOR',
            'apoderado_delegado' => _tcvBuildRepresentante($pdo, $idCliente) ?: [
                'nombre' => 'NO PROPORCIONADO',
                'apellido_paterno' => 'NO PROPORCIONADO',
                'apellido_materno' => 'NO PROPORCIONADO',
            ],
        ]]];
    }
    $dom = _tcvFirstDireccionCliente($pdo, $idCliente);
    if ($dom) $persona['tipo_domicilio'] = $dom;
    return $persona;
}

function _tcvBuildDestinatario(array $data): array
{
    $same = _tcvUp($data['destinatario_persona_aviso'] ?? 'SI');
    if (!in_array($same, ['SI', 'NO'], true)) $same = 'SI';
    $dest = ['destinatario_persona_aviso' => $same];
    if ($same === 'SI') return $dest;

    $tipo = strtolower(trim((string)($data['destinatario_tipo_persona'] ?? 'fisica')));
    if ($tipo === 'moral') {
        $pm = [
            'denominacion_razon' => _tcvSanText($data['destinatario_denominacion_razon'] ?? ''),
            'fecha_constitucion' => _tcvDate8($data['destinatario_fecha_constitucion'] ?? ''),
            'rfc' => _tcvRfcMoral($data['destinatario_rfc'] ?? ''),
        ];
        _tcvReq($pm['denominacion_razon'] !== '', 'Destinatario moral requiere denominación/razón social.');
        $dest['tipo_persona'] = ['persona_moral' => $pm];
        return $dest;
    }

    if ($tipo === 'fideicomiso') {
        $fid = [
            'denominacion_razon' => _tcvSanText($data['destinatario_denominacion_razon'] ?? ''),
            'rfc' => _tcvRfcMoral($data['destinatario_rfc'] ?? ''),
            'identificador_fideicomiso' => _tcvSanText($data['destinatario_identificador_fideicomiso'] ?? ''),
        ];
        _tcvReq($fid['denominacion_razon'] !== '', 'Destinatario fideicomiso requiere denominación/razón.');
        $dest['tipo_persona'] = ['fideicomiso' => $fid];
        return $dest;
    }

    $pf = [
        'nombre' => _tcvSanText($data['destinatario_nombre'] ?? ''),
        'apellido_paterno' => _tcvSanText($data['destinatario_apellido_paterno'] ?? ''),
        'apellido_materno' => _tcvSanText($data['destinatario_apellido_materno'] ?? ''),
        'fecha_nacimiento' => _tcvDate8($data['destinatario_fecha_nacimiento'] ?? ''),
        'rfc' => _tcvRfcFisica($data['destinatario_rfc'] ?? ''),
        'curp' => _tcvCurp($data['destinatario_curp'] ?? ''),
    ];
    _tcvReq($pf['nombre'] !== '' && $pf['apellido_paterno'] !== '' && $pf['apellido_materno'] !== '', 'Destinatario físico requiere nombre y apellidos.');
    $dest['tipo_persona'] = ['persona_fisica' => $pf];
    return $dest;
}

function _tcvBuildDuenoBeneficiario(array $data): ?array
{
    if (empty($data['dueno_beneficiario_incluir'])) return null;
    $tipo = strtolower(trim((string)($data['dueno_beneficiario_tipo_persona'] ?? 'fisica')));

    if ($tipo === 'moral') {
        $pm = [
            'denominacion_razon' => _tcvSanText($data['dueno_beneficiario_denominacion_razon'] ?? ''),
            'fecha_constitucion' => _tcvDate8($data['dueno_beneficiario_fecha_constitucion'] ?? ''),
            'rfc' => _tcvRfcMoral($data['dueno_beneficiario_rfc'] ?? ''),
            'pais_nacionalidad' => preg_match('/^[A-Z]{2}$/', _tcvUp($data['dueno_beneficiario_pais_nacionalidad'] ?? '')) ? _tcvUp($data['dueno_beneficiario_pais_nacionalidad']) : '',
        ];
        _tcvReq($pm['denominacion_razon'] !== '', 'Dueño beneficiario moral requiere denominación/razón social.');
        return ['tipo_persona' => ['persona_moral' => $pm]];
    }

    if ($tipo === 'fideicomiso') {
        $fid = [
            'denominacion_razon' => _tcvSanText($data['dueno_beneficiario_denominacion_razon'] ?? ''),
            'rfc' => _tcvRfcMoral($data['dueno_beneficiario_rfc'] ?? ''),
            'identificador_fideicomiso' => _tcvSanText($data['dueno_beneficiario_identificador_fideicomiso'] ?? ''),
        ];
        _tcvReq($fid['denominacion_razon'] !== '', 'Dueño beneficiario fideicomiso requiere denominación/razón.');
        return ['tipo_persona' => ['fideicomiso' => $fid]];
    }

    $pf = [
        'nombre' => _tcvSanText($data['dueno_beneficiario_nombre'] ?? ''),
        'apellido_paterno' => _tcvSanText($data['dueno_beneficiario_apellido_paterno'] ?? ''),
        'apellido_materno' => _tcvSanText($data['dueno_beneficiario_apellido_materno'] ?? ''),
        'fecha_nacimiento' => _tcvDate8($data['dueno_beneficiario_fecha_nacimiento'] ?? ''),
        'rfc' => _tcvRfcFisica($data['dueno_beneficiario_rfc'] ?? ''),
        'curp' => _tcvCurp($data['dueno_beneficiario_curp'] ?? ''),
        'pais_nacionalidad' => preg_match('/^[A-Z]{2}$/', _tcvUp($data['dueno_beneficiario_pais_nacionalidad'] ?? '')) ? _tcvUp($data['dueno_beneficiario_pais_nacionalidad']) : '',
    ];
    _tcvReq($pf['nombre'] !== '' && $pf['apellido_paterno'] !== '' && $pf['apellido_materno'] !== '', 'Dueño beneficiario físico requiere nombre y apellidos.');
    return ['tipo_persona' => ['persona_fisica' => $pf]];
}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_x.php';
    require_once __DIR__ . '/../config/tcv_catalogos.php';
    require_once __DIR__ . '/../config/tcv_xml_helper.php';
} catch (Throwable $e) {
    _tcvErr('Error al inicializar: ' . $e->getMessage(), 500);
}

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

_tcvReq(isset($_SESSION['user_id']), 'No autorizado');
$userId = (int)($_SESSION['user_id'] ?? 0);
_tcvReq(function_exists('userCanAccessTCV') && userCanAccessTCV($pdo, $userId), 'Sin permiso para registrar avisos TCV');
requirePLDHabilitado($pdo, true);
$validPatron = requireFraccionXActiva($pdo);
_tcvReq(($validPatron['habilitado'] ?? false), $validPatron['razon'] ?? 'La fracción X no está activa');

$data = json_decode((string)file_get_contents('php://input'), true);
_tcvReq(is_array($data), 'JSON inválido');

$idCliente = (int)($data['id_cliente'] ?? 0);
_tcvReq($idCliente > 0, 'id_cliente es obligatorio');
$mesReportado = _tcvMonth6($data['mes_reportado'] ?? '');
_tcvReq((bool)preg_match('/^[2-9]\d{3}(0[1-9]|1[0-2])$/', $mesReportado) && $mesReportado >= '201309' && $mesReportado <= date('Ym'), 'mes_reportado inválido');
$claveSO = _tcvUp($data['clave_sujeto_obligado'] ?? '');
_tcvReq((bool)preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $claveSO), 'clave_sujeto_obligado inválida');
$claveEntidad = _tcvUp($data['clave_entidad_colegiada'] ?? '');
if ($claveEntidad !== '') _tcvReq((bool)preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/u', $claveEntidad), 'clave_entidad_colegiada inválida');
$referencia = _tcvUp($data['referencia_aviso'] ?? '');
_tcvReq((bool)preg_match('/^[A-ZÑ0-9]{1,14}$/u', $referencia), 'referencia_aviso inválida');
$prioridad = trim((string)($data['prioridad'] ?? '1'));
_tcvReq(in_array($prioridad, ['1','2'], true), 'prioridad inválida');
$tipoAlerta = _tcvDigits($data['tipo_alerta'] ?? '100');
_tcvReq(_tcvHas($TCV_CATALOGOS['tipo_alerta'] ?? [], $tipoAlerta), 'tipo_alerta fuera de catálogo TCV');
_tcvReq(!($prioridad === '2' && $tipoAlerta === '100'), 'Cuando prioridad=2, tipo_alerta debe ser diferente de 100');
$descAlerta = _tcvUp($data['descripcion_alerta'] ?? '');
if ($tipoAlerta === '9999') _tcvReq($descAlerta !== '', 'descripcion_alerta es obligatoria cuando tipo_alerta=9999');

$fechaOperacion8 = _tcvDate8($data['fecha_operacion'] ?? '');
_tcvReq($fechaOperacion8 !== '' && _tcvYmdFrom8($fechaOperacion8) !== '', 'fecha_operacion inválida');
$tipoOperacion = _tcvDigits($data['tipo_operacion'] ?? '');
_tcvReq(_tcvHas($TCV_CATALOGOS['tipo_operacion'] ?? [], $tipoOperacion), 'tipo_operacion fuera de catálogo TCV');
$montoNoDeterminado = !empty($data['monto_no_determinado']);
$bienTipo = trim((string)($data['bien_tipo'] ?? 'efectivo'));
$montoTotal = 0.0;

if ($bienTipo === 'valores') {
    $tipoValor = _tcvDigits($data['tipo_valor'] ?? '');
    _tcvReq(_tcvHas($TCV_CATALOGOS['tipo_valor'] ?? [], $tipoValor), 'tipo_valor fuera de catálogo TCV');
    $valorObjeto = (float)($data['valor_objeto'] ?? 0);
    if (!$montoNoDeterminado) _tcvReq($valorObjeto > 0, 'valor_objeto inválido');
    $montoTotal = $montoNoDeterminado ? (pldFraccionXUmbralAviso() * 999999.0) : $valorObjeto;
    $tipoBien = ['datos_valores' => [
        'tipo_valor' => $tipoValor,
        'valor_objeto' => number_format(max(0.01, $valorObjeto), 2, '.', ''),
        'descripcion' => _tcvSanText($data['descripcion_valor'] ?? 'VALOR CUSTODIADO'),
    ]];
} else {
    $instrumento = _tcvDigits($data['instrumento_monetario'] ?? '');
    _tcvReq(_tcvHas($TCV_CATALOGOS['instrumento_monetario'] ?? [], $instrumento), 'instrumento_monetario fuera de catálogo TCV');
    $moneda = _tcvDigits($data['moneda'] ?? '');
    _tcvReq(_tcvHas($TCV_CATALOGOS['moneda'] ?? [], $moneda), 'moneda fuera de catálogo TCV');
    $monto = (float)($data['monto_operacion'] ?? 0);
    if (!$montoNoDeterminado) _tcvReq($monto > 0, 'monto_operacion inválido');
    $montoTotal = $montoNoDeterminado ? (pldFraccionXUmbralAviso() * 999999.0) : $monto;
    $tipoBien = ['datos_efectivo_instrumentos' => [
        'instrumento_monetario' => $instrumento,
        'moneda' => $moneda,
        'monto_operacion' => number_format(max(0.01, $monto), 2, '.', ''),
    ]];
}

$cpRecepcion = _tcvDigits($data['cp_recepcion'] ?? '');
_tcvReq((bool)preg_match('/^\d{5}$/', $cpRecepcion), 'cp_recepcion inválido');
$recepcion = [
    'tipo_servicio' => _tcvDigits($data['tipo_servicio'] ?? '1'),
    'fecha_recepcion' => _tcvDate8($data['fecha_recepcion'] ?? $fechaOperacion8),
    'codigo_postal' => $cpRecepcion,
];
_tcvReq(_tcvHas($TCV_CATALOGOS['tipo_servicio'] ?? [], $recepcion['tipo_servicio']), 'tipo_servicio fuera de catálogo TCV');

$datosOperacion = [
    'fecha_operacion' => $fechaOperacion8,
    'tipo_operacion' => $tipoOperacion,
    'tipo_bien' => [$tipoBien],
    'recepcion' => $recepcion,
];

if (in_array($tipoOperacion, ['1002','1003'], true)) {
    $fechaInicio = _tcvDate8($data['fecha_inicio'] ?? $fechaOperacion8);
    $fechaFin = _tcvDate8($data['fecha_fin'] ?? $fechaOperacion8);
    _tcvReq($fechaInicio !== '' && _tcvYmdFrom8($fechaInicio) !== '', 'fecha_inicio inválida');
    _tcvReq($fechaFin !== '' && _tcvYmdFrom8($fechaFin) !== '', 'fecha_fin inválida');
    _tcvReq($fechaFin >= $fechaInicio, 'fecha_fin debe ser mayor o igual a fecha_inicio');
    $datosOperacion['custodia'] = [
        'fecha_inicio' => $fechaInicio,
        'fecha_fin' => $fechaFin,
        'tipo_custodia' => ['datos_sucursal' => ['codigo_postal' => _tcvDigits($data['cp_custodia'] ?? $cpRecepcion)]],
    ];
}

if (in_array($tipoOperacion, ['1001','1003'], true)) {
    $fechaEntrega = _tcvDate8($data['fecha_entrega'] ?? $fechaOperacion8);
    _tcvReq($fechaEntrega !== '' && _tcvYmdFrom8($fechaEntrega) !== '', 'fecha_entrega inválida');
    $cpEntrega = _tcvDigits($data['cp_entrega'] ?? '');
    _tcvReq((bool)preg_match('/^\d{5}$/', $cpEntrega), 'cp_entrega inválido');
    $datosOperacion['entrega'] = [
        'fecha_entrega' => $fechaEntrega,
        'tipo_entrega' => ['nacional' => ['codigo_postal' => $cpEntrega]],
    ];
    $datosOperacion['destinatario'] = _tcvBuildDestinatario($data);
}

$idFraccion = getIdVulnerableFraccionX($pdo);
_tcvReq((int)$idFraccion > 0, 'No se pudo resolver Fracción X en cat_vulnerables');
$result = registrarOperacionPLD($pdo, [
    'id_cliente' => $idCliente,
    'monto' => $montoTotal,
    'fecha_operacion' => _tcvYmdFrom8($fechaOperacion8),
    'id_fraccion' => (int)$idFraccion,
    'tipo_operacion' => 'X:TCV:' . $tipoOperacion,
    'umbral_aviso_uma_override' => pldFraccionXUmbralAviso(),
    'umbral_acumulacion_uma_override' => pldFraccionXUmbralAviso(),
]);
if (!($result['success'] ?? false)) _tcvErr($result['message'] ?? 'Error al registrar aviso TCV', 400);

$duenoBeneficiario = _tcvBuildDuenoBeneficiario($data);
$avisoXml = [
    'referencia_aviso' => $referencia,
    'prioridad' => $prioridad,
    'alerta' => ['tipo_alerta' => $tipoAlerta, 'descripcion_alerta' => $descAlerta !== '' ? $descAlerta : null],
    'persona_aviso' => [_tcvBuildPersonaAviso($pdo, $idCliente)],
];
if ($duenoBeneficiario !== null) {
    $avisoXml['dueno_beneficiario'] = [$duenoBeneficiario];
}
$avisoXml['detalle_operaciones'] = [['datos_operacion' => [$datosOperacion]]];

$payloadXml = ['informe' => [[
    'mes_reportado' => $mesReportado,
    'sujeto_obligado' => [
        'clave_entidad_colegiada' => $claveEntidad !== '' ? $claveEntidad : null,
        'clave_sujeto_obligado' => $claveSO,
        'clave_actividad' => 'TCV',
        'exento' => trim((string)($data['exento'] ?? '0')) === '1' ? '1' : null,
    ],
    'aviso' => [$avisoXml],
]]];
$xmlData = generateTCVXml($payloadXml);
$xml = (string)($xmlData['xml'] ?? '');
_tcvReq($xml !== '', 'No se pudo generar XML TCV');

$idOperacion = (int)($result['id_operacion'] ?? 0);
if ($idOperacion > 0) {
    try {
        $xmlNombre = 'tcv_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $idOperacion]);
    } catch (Throwable $e) {
        error_log('registrar_aviso_tcv xml update: ' . $e->getMessage());
    }
}

echo json_encode([
    'status' => 'success',
    'message' => 'Aviso TCV registrado correctamente.',
    'id_operacion' => $idOperacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => !empty($result['requiere_aviso']),
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => ($xml !== ''),
], JSON_UNESCAPED_UNICODE);
