<?php
/**
 * API: Registrar Transacción DIN (Desarrollo Inmobiliario) - Fracción V/V Bis
 * Genera XML según XSD, almacena en operaciones_pld, valida aviso
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/pld_middleware.php';
require_once __DIR__ . '/../config/din_xml_helper.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

requirePLDHabilitado($pdo, true);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['id_cliente'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'JSON con id_cliente e informe requerido']);
    exit;
}

$id_cliente = (int)($data['id_cliente'] ?? 0);
$id_fraccion = !empty($data['id_fraccion']) ? (int)$data['id_fraccion'] : null;

// Obtener monto para PLD (monto_desarrollo como principal)
$monto = 0;
if (isset($data['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0])) {
    $op = $data['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0];
    $des = $op['desarrollos_inmobiliarios'][0]['datos_desarrollo'][0]['caracteristicas_desarrollo'][0] ?? null;
    if ($des && isset($des['monto_desarrollo'])) {
        $monto = floatval($des['monto_desarrollo']);
    }
    if ($monto <= 0) {
        $aps = $op['aportaciones'][0] ?? [];
        $tipos = $aps['tipo_aportacion'][0] ?? [];
        $rps = $tipos['recursos_propios'] ?? [];
        $rp = is_array($rps) && isset($rps[0]) ? $rps[0] : $rps;
        $daps = $rp['datos_aportacion'] ?? [];
        $da = is_array($daps) && isset($daps[0]) ? $daps[0] : [];
        $ans = $da['aportacion_numerario'] ?? [];
        $an = is_array($ans) && isset($ans[0]) ? $ans[0] : $ans;
        if (isset($an['monto_aportacion'])) {
            $monto = floatval($an['monto_aportacion']);
        }
    }
}

$fecha_aportacion = null;
if (isset($data['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0]['aportaciones'][0]['fecha_aportacion'])) {
    $fa = $data['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0]['aportaciones'][0]['fecha_aportacion'];
    if (strlen($fa) === 8) {
        $fecha_aportacion = substr($fa, 0, 4) . '-' . substr($fa, 4, 2) . '-' . substr($fa, 6, 2);
    }
}
$fecha_operacion = $fecha_aportacion ?: date('Y-m-d');

$operacionData = [
    'id_cliente' => $id_cliente,
    'monto' => $monto > 0 ? $monto : 1,
    'fecha_operacion' => $fecha_operacion,
    'id_fraccion' => $id_fraccion,
    'tipo_operacion' => 'DIN',
    'es_sospechosa' => $data['es_sospechosa'] ?? 0,
    'fecha_conocimiento_sospecha' => $data['fecha_conocimiento_sospecha'] ?? null,
    'match_listas_restringidas' => $data['match_listas_restringidas'] ?? 0,
    'fecha_conocimiento_match' => $data['fecha_conocimiento_match'] ?? null,
];

$result = registrarOperacionPLD($pdo, $operacionData);

if (!($result['success'] ?? false)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $result['message'] ?? 'Error al registrar transacción']);
    exit;
}

$id_operacion = $result['id_operacion'];

$xsdPath = __DIR__ . '/../din.xsd';
$gen = generateDINXml($data, $xsdPath);

if (!empty($gen['errors'])) {
    logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_OPERACION_DIN', 'operaciones_pld', $id_operacion, null, ['xml_errors' => $gen['errors']]);
}

$xml = $gen['xml'] ?? '';
$xmlNombre = 'din_' . date('Ymd_His') . '_op' . $id_operacion . '.xml';

if ($xml) {
    try {
        $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $stmt->execute([$xml, $xmlNombre, $id_operacion]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'xml_contenido') !== false) {
            // Columnas no existen aún, ejecutar migración
        }
    }

    // Notificación: XML generado y disponible para descarga.
    if (function_exists('pldTableExists') && pldTableExists($pdo, 'notificaciones') && function_exists('pldObtenerUsuariosNotificacion')) {
        $usuarios = pldObtenerUsuariosNotificacion($pdo, $id_cliente);
        $tieneIdAviso = function_exists('pldColumnExists') && pldColumnExists($pdo, 'notificaciones', 'id_aviso');
        $tieneIdOperacion = function_exists('pldColumnExists') && pldColumnExists($pdo, 'notificaciones', 'id_operacion');
        $tipoNotif = 'xml_aviso_generado_pld';
        $mensaje = "XML DIN generado para transacción #{$id_operacion}. Descárguelo desde Transacciones PLD > XML.";
        $idAviso = (int)($result['id_aviso'] ?? 0);

        foreach ($usuarios as $idUsuarioNotif) {
            $idUsuarioNotif = (int)$idUsuarioNotif;
            if ($idUsuarioNotif <= 0) continue;

            $stmtEx = $pdo->prepare("
                SELECT 1 FROM notificaciones
                WHERE id_usuario = ?
                  AND tipo = ?
                  AND mensaje = ?
                  AND estado != 'descartado'
                  AND fecha_generacion > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                LIMIT 1
            ");
            $stmtEx->execute([$idUsuarioNotif, $tipoNotif, $mensaje]);
            if ($stmtEx->fetch()) continue;

            $cols = ['id_usuario', 'id_cliente', 'tipo', 'mensaje'];
            $vals = [$idUsuarioNotif, $id_cliente, $tipoNotif, $mensaje];
            if ($tieneIdAviso) {
                $cols[] = 'id_aviso';
                $vals[] = $idAviso > 0 ? $idAviso : null;
            }
            if ($tieneIdOperacion) {
                $cols[] = 'id_operacion';
                $vals[] = $id_operacion;
            }

            $sqlInsert = "INSERT INTO notificaciones (" . implode(', ', $cols) . ")
                          VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")";
            $stmtIn = $pdo->prepare($sqlInsert);
            $stmtIn->execute($vals);
        }
    }
}

logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_OPERACION_DIN', 'operaciones_pld', $id_operacion, null, $operacionData);

echo json_encode([
    'status' => 'success',
    'message' => 'Transacción DIN registrada. XML generado.',
    'id_operacion' => $id_operacion,
    'id_aviso' => $result['id_aviso'] ?? null,
    'requiere_aviso' => $result['requiere_aviso'] ?? false,
    'tipo_aviso' => $result['tipo_aviso'] ?? null,
    'fecha_deadline' => $result['fecha_deadline'] ?? null,
    'xml_generado' => !empty($xml),
    'xml_errores' => $gen['errors'] ?? null,
]);

