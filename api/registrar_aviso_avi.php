<?php
/**
 * API: Registrar Aviso AVI (Activos Virtuales) - Fracción XVI
 * - Evalúa umbral dual:
 *   a) monto operación >= 210 UMA
 *   b) contraprestación servicio >= 4 UMA
 * - Genera XML AVI y lo almacena en operaciones_pld
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function _aviJsonError(string $msg, int $code = 500): void {
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('aviArrayGetNumberSumByKeys')) {
    function aviArrayGetNumberSumByKeys($node, array $keys): float {
        $sum = 0.0;
        if (is_array($node)) {
            foreach ($node as $k => $v) {
                if (is_string($k) && in_array($k, $keys, true)) {
                    $sum += (float)$v;
                } else {
                    $sum += aviArrayGetNumberSumByKeys($v, $keys);
                }
            }
        }
        return $sum;
    }
}

if (!function_exists('aviArrayFindFirstValueByKey')) {
    function aviArrayFindFirstValueByKey($node, string $wantedKey): ?string {
        if (!is_array($node)) return null;
        foreach ($node as $k => $v) {
            if ($k === $wantedKey && !is_array($v) && $v !== null && $v !== '') {
                return (string)$v;
            }
            if (is_array($v)) {
                $f = aviArrayFindFirstValueByKey($v, $wantedKey);
                if ($f !== null) return $f;
            }
        }
        return null;
    }
}

if (!function_exists('aviExtractFechaOperacionYmd')) {
    function aviExtractFechaOperacionYmd(array $data): string {
        $raw = aviArrayFindFirstValueByKey($data, 'fecha_hora_operacion');
        if (!$raw) {
            return date('Y-m-d');
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) >= 8) {
            $y = substr($digits, 0, 4);
            $m = substr($digits, 4, 2);
            $d = substr($digits, 6, 2);
            if (checkdate((int)$m, (int)$d, (int)$y)) {
                return $y . '-' . $m . '-' . $d;
            }
        }
        return date('Y-m-d');
    }
}

if (!function_exists('aviRecursiveUnsetKeys')) {
    function aviRecursiveUnsetKeys(&$node, array $keysToUnset): void {
        if (!is_array($node)) return;
        foreach ($keysToUnset as $k) {
            if (array_key_exists($k, $node)) {
                unset($node[$k]);
            }
        }
        foreach ($node as &$child) {
            if (is_array($child)) {
                aviRecursiveUnsetKeys($child, $keysToUnset);
            }
        }
    }
}

if (!function_exists('aviPrepareXmlPayload')) {
    function aviPrepareXmlPayload(array $data): array {
        $xmlPayload = ['informe' => $data['informe'] ?? []];
        // Campos de control de negocio (no definidos en XSD AVI)
        aviRecursiveUnsetKeys($xmlPayload, [
            'monto_contraprestacion_servicio',
            'monto_operacion_control'
        ]);
        return $xmlPayload;
    }
}

if (!function_exists('aviDetectTipoOperacion')) {
    function aviDetectTipoOperacion(array $data): string {
        $map = [
            'operaciones_compra' => 'compra',
            'operaciones_venta' => 'venta',
            'operaciones_intercambio' => 'intercambio',
            'operaciones_transferencia' => 'transferencia',
            'operaciones_fondos' => 'fondos'
        ];
        $flat = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (!is_string($flat)) return 'general';
        foreach ($map as $k => $tipo) {
            if (strpos($flat, '"' . $k . '"') !== false) return $tipo;
        }
        return 'general';
    }
}

if (!function_exists('aviNormalizeCatalogValue')) {
    function aviNormalizeCatalogValue($value): string {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string)$value;
        }
        if (is_float($value)) {
            if (floor($value) == $value) {
                return (string)((int)$value);
            }
            return rtrim(rtrim((string)$value, '0'), '.');
        }
        return strtoupper(trim((string)$value));
    }
}

if (!function_exists('aviValidatePayloadCatalogs')) {
    /**
     * Valida claves de catálogo relevantes del payload AVI.
     * Devuelve lista de incidencias (vacía si todo OK).
     */
    function aviValidatePayloadCatalogs(array $data): array {
        global $AVI_CATALOGOS;

        $issues = [];
        $catalogByKey = [
            'prioridad' => 'prioridad',
            'tipo_alerta' => 'tipo_alerta',
            'exento' => 'exento',
            'moneda_cuenta' => 'moneda',
            'moneda_operacion' => 'moneda',
            'moneda' => 'moneda',
            'tipo_identificacion' => 'tipo_identificacion',
            'pais_nacionalidad' => 'pais',
            'pais' => 'pais',
            'clave_pais' => 'pais',
            'actividad_economica' => 'actividad_economica',
            'giro_mercantil' => 'giro_mercantil',
            'instrumento_monetario' => 'instrumento_monetario',
            'activo_virtual_operado' => 'activo_virtual_operado',
            'clave_institucion_financiera' => 'clave_institucion_financiera',
        ];

        $walk = function ($node, string $path = '') use (&$walk, &$issues, $catalogByKey, $AVI_CATALOGOS): void {
            if (!is_array($node)) return;
            foreach ($node as $k => $v) {
                $currPath = ($path === '') ? (string)$k : ($path . '.' . (string)$k);
                if (is_array($v)) {
                    $walk($v, $currPath);
                    continue;
                }

                if ($k === 'clave_actividad') {
                    if (aviNormalizeCatalogValue($v) !== 'AVI') {
                        $issues[] = "{$currPath}: clave_actividad inválida (debe ser AVI)";
                    }
                    continue;
                }

                if (!isset($catalogByKey[$k])) {
                    continue;
                }

                $catalogName = $catalogByKey[$k];
                $cat = $AVI_CATALOGOS[$catalogName] ?? [];
                if (!is_array($cat) || empty($cat)) {
                    continue;
                }
                $norm = aviNormalizeCatalogValue($v);
                if (!array_key_exists($norm, $cat)) {
                    $issues[] = "{$currPath}: valor {$norm} fuera de catálogo {$catalogName}";
                }
            }
        };

        $walk($data, '');
        return $issues;
    }
}

try {
    session_start();
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/pld_avisos.php';
    require_once __DIR__ . '/../config/bitacora.php';
    require_once __DIR__ . '/../config/pld_middleware.php';
    require_once __DIR__ . '/../config/pld_permisos.php';
    require_once __DIR__ . '/../config/pld_fraccion_xvi.php';
    require_once __DIR__ . '/../config/avi_catalogos.php';
    require_once __DIR__ . '/../config/avi_xml_helper.php';
} catch (Throwable $e) {
    error_log('registrar_aviso_avi init: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    _aviJsonError('Error al inicializar: ' . $e->getMessage(), 500);
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    _aviJsonError('No autorizado', 401);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!function_exists('userCanAccessAVI') || !userCanAccessAVI($pdo, $userId)) {
    _aviJsonError('Sin permiso para registrar avisos AVI', 403);
}

requirePLDHabilitado($pdo, true);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['id_cliente'])) {
    _aviJsonError('JSON con id_cliente e informe requerido', 400);
}
if (empty($data['informe']) || !is_array($data['informe'])) {
    _aviJsonError('Estructura informe requerida', 400);
}

$catalogIssues = aviValidatePayloadCatalogs($data);
if (!empty($catalogIssues)) {
    $max = 10;
    $msg = 'Validación catálogo AVI fallida: ' . implode(' | ', array_slice($catalogIssues, 0, $max));
    if (count($catalogIssues) > $max) {
        $msg .= ' | ...';
    }
    _aviJsonError($msg, 400);
}

$id_cliente = (int)($data['id_cliente'] ?? 0);
$claveSO = $data['informe'][0]['sujeto_obligado']['clave_sujeto_obligado'] ?? '';
if (!aviValidarClaveSO($claveSO)) {
    _aviJsonError('Clave Sujeto Obligado inválida (RFC 12-13).', 400);
}

try {
    $montoOperacionControl = isset($data['monto_operacion_control']) ? (float)$data['monto_operacion_control'] : 0.0;
    $sumaMontosOperacion = $montoOperacionControl > 0
        ? $montoOperacionControl
        : aviArrayGetNumberSumByKeys($data, ['monto_operacion', 'monto_operacion_MN', 'monto_operacion_mn']);
    $montoContraprestacion = 0.0;
    if (isset($data['monto_contraprestacion_servicio'])) {
        $montoContraprestacion = (float)$data['monto_contraprestacion_servicio'];
    } elseif (isset($data['informe'][0]['aviso'][0]['monto_contraprestacion_servicio'])) {
        $montoContraprestacion = (float)$data['informe'][0]['aviso'][0]['monto_contraprestacion_servicio'];
    }

    $fecha_operacion = aviExtractFechaOperacionYmd($data);
    $tipoOperacion = aviDetectTipoOperacion($data);

    $stmtUma = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1");
    $umaRow = $stmtUma ? $stmtUma->fetch(PDO::FETCH_ASSOC) : null;
    $valorUma = $umaRow ? (float)$umaRow['valor'] : 100.0;

    $eval = pldFraccionXVIEvaluaUmbralAviso($valorUma, $sumaMontosOperacion, $montoContraprestacion);

    $id_fraccion = getIdVulnerableFraccionXVI($pdo);
    $montoOperacionParaRegistro = $sumaMontosOperacion > 0 ? $sumaMontosOperacion : max(1.0, $montoContraprestacion);

    $operacionData = [
        'id_cliente' => $id_cliente,
        'monto' => $montoOperacionParaRegistro,
        'fecha_operacion' => $fecha_operacion,
        'id_fraccion' => $id_fraccion,
        'tipo_operacion' => 'AVI:' . $tipoOperacion,
        'requiere_aviso_forzado' => $eval['requiere_aviso'] ? 1 : 0,
        'tipo_aviso_forzado' => 'umbral_individual',
        'fecha_deadline_forzado' => calcularDeadlineAviso($fecha_operacion),
        'es_sospechosa' => $data['es_sospechosa'] ?? 0,
        'fecha_conocimiento_sospecha' => $data['fecha_conocimiento_sospecha'] ?? null,
        'match_listas_restringidas' => $data['match_listas_restringidas'] ?? 0,
        'fecha_conocimiento_match' => $data['fecha_conocimiento_match'] ?? null
    ];

    $result = registrarOperacionPLD($pdo, $operacionData);
    if (!($result['success'] ?? false)) {
        _aviJsonError($result['message'] ?? 'Error al registrar aviso AVI', 400);
    }

    $id_operacion = (int)$result['id_operacion'];

    $xml = '';
    $xmlErrors = [];
    $xsdPath = null;
    foreach ([__DIR__ . '/../xsd/avi.xsd', __DIR__ . '/../avi.xsd'] as $candidate) {
        if (is_file($candidate)) {
            $xsdPath = $candidate;
            break;
        }
    }

    $xmlPayload = aviPrepareXmlPayload($data);
    $gen = generateAVIXml($xmlPayload, $xsdPath);
    $xml = $gen['xml'] ?? '';
    $xmlErrors = $gen['errors'] ?? [];

    if ($xml) {
        $xmlNombre = 'avi_' . date('Ymd_His') . '_op' . $id_operacion . '.xml';
        try {
            $stmt = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
            $stmt->execute([$xml, $xmlNombre, $id_operacion]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'xml_contenido') === false) {
                $xmlErrors[] = 'No se pudo guardar XML en BD: ' . $e->getMessage();
            }
        }
    }

    if (function_exists('logChange')) {
        logChange($pdo, $_SESSION['user_id'], 'REGISTRAR_AVISO_AVI', 'operaciones_pld', $id_operacion, null, [
            'id_cliente' => $id_cliente,
            'tipo_operacion' => $operacionData['tipo_operacion'],
            'monto_operacion_mxn' => $sumaMontosOperacion,
            'monto_contraprestacion_mxn' => $montoContraprestacion,
            'eval_umbral' => $eval,
            'xml_errors' => $xmlErrors
        ]);
    }

    $resp = [
        'status' => 'success',
        'message' => 'Aviso AVI registrado correctamente.',
        'id_operacion' => $id_operacion,
        'id_aviso' => $result['id_aviso'] ?? null,
        'requiere_aviso' => $result['requiere_aviso'] ?? $eval['requiere_aviso'],
        'tipo_aviso' => $result['tipo_aviso'] ?? 'umbral_individual',
        'fecha_deadline' => $result['fecha_deadline'] ?? calcularDeadlineAviso($fecha_operacion),
        'xml_generado' => !empty($xml),
        'evaluacion_xvi' => $eval
    ];
    if (!empty($xmlErrors)) {
        $resp['xml_advertencia'] = implode('; ', $xmlErrors);
    }

    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('registrar_aviso_avi: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    _aviJsonError('Error al registrar AVI: ' . $e->getMessage(), 500);
}
