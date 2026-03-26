<?php
/**
 * E2E Fracción II (TSC / TPP / TDR)
 * - Prueba registrarOperacionPLD por subfracción:
 *   A) sin aviso
 *   B) aviso individual
 *   C) aviso por acumulación
 * - Prueba generación XML por subfracción (TSC/TPP/TDR)
 * - Ejecuta en transacción y hace rollback (no deja datos QA)
 *
 * Uso:
 *   php tools/qa_fraccion_ii_e2e.php
 *   php tools/qa_fraccion_ii_e2e.php --subf=servicio_credito
 *   php tools/qa_fraccion_ii_e2e.php --subf=prepago_cupones
 *   php tools/qa_fraccion_ii_e2e.php --subf=devolucion_recompensas
 * Alias válidos en --subf: tsc, tpp, tdr
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_ii.php';
require_once __DIR__ . '/../config/tsc_xml_helper.php';
require_once __DIR__ . '/../config/tpp_xml_helper.php';
require_once __DIR__ . '/../config/tdr_xml_helper.php';

function qaFr2Assert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) {
        $ok[] = $okMsg;
    } else {
        $err[] = $errMsg;
    }
}

function qaFr2Uma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaFr2ResolveIdFraccion(PDO $pdo): ?int
{
    // Resolución tolerante a esquemas viejos (algunas BD no tienen id_status en cat_vulnerables)
    $hasIdStatus = (int)$pdo->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cat_vulnerables'
          AND COLUMN_NAME = 'id_status'
    ")->fetchColumn() > 0;

    // Si la tabla sí tiene id_status, intentamos primero el helper estándar.
    if ($hasIdStatus && function_exists('getIdVulnerableFraccionII')) {
        try {
            $id = getIdVulnerableFraccionII($pdo);
            if (!empty($id)) return (int)$id;
        } catch (Throwable $e) {
            // fallback abajo
        }
    }

    if ($hasIdStatus) {
        $stmt = $pdo->query("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'II' AND (id_status = 1 OR id_status IS NULL) LIMIT 1");
    } else {
        $stmt = $pdo->query("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'II' LIMIT 1");
    }
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return $row ? (int)$row['id_vulnerable'] : null;
}

function qaFr2NormalizeSubfArg(?string $raw): string
{
    $v = strtolower(trim((string)$raw));
    if ($v === '') return '';
    $map = [
        'tsc' => 'servicio_credito',
        'servicio_credito' => 'servicio_credito',
        'servicio-credito' => 'servicio_credito',
        'tpp' => 'prepago_cupones',
        'prepago_cupones' => 'prepago_cupones',
        'prepago-cupones' => 'prepago_cupones',
        'tdr' => 'devolucion_recompensas',
        'devolucion_recompensas' => 'devolucion_recompensas',
        'devolucion-recompensas' => 'devolucion_recompensas',
    ];
    return $map[$v] ?? '';
}

function qaFr2GetSubfArg(array $argv): string
{
    foreach ($argv as $arg) {
        if (strpos((string)$arg, '--subf=') === 0) {
            $val = substr((string)$arg, 7);
            return qaFr2NormalizeSubfArg($val);
        }
    }
    return '';
}

function qaFr2FindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
{
    $rows = $pdo->query("
        SELECT id_cliente, no_contrato, expediente_completo
        FROM clientes
        WHERE COALESCE(id_status, 1) <> 4
        ORDER BY COALESCE(expediente_completo,0) DESC, id_cliente DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        $logs[] = 'No hay clientes candidatos.';
        return null;
    }

    $today = date('Y-m-d');
    foreach ($rows as $c) {
        $probe = registrarOperacionPLD($pdo, [
            'id_cliente' => (int)$c['id_cliente'],
            'monto' => 10.00,
            'fecha_operacion' => $today,
            'id_fraccion' => $idFraccion,
            'tipo_operacion' => 'II:servicio_credito:E2E_PROBE',
            'umbral_identificacion_uma_override' => 805.0,
            'umbral_aviso_uma_override' => 1285.0,
            'umbral_acumulacion_uma_override' => 1285.0,
        ]);
        if (!empty($probe['success'])) {
            $logs[] = 'Cliente apto id=' . (int)$c['id_cliente'] . ' contrato=' . ($c['no_contrato'] ?? '');
            return $c;
        }
        $logs[] = 'Cliente bloqueado id=' . (int)$c['id_cliente'] . ': ' . ($probe['message'] ?? 'sin mensaje');
    }

    return null;
}

function qaFr2XmlPayload(string $subfraccion, string $claveActividad): array
{
    $base = [
        'informe' => [[
            'mes_reportado' => date('Ym'),
            'sujeto_obligado' => [
                'clave_sujeto_obligado' => 'ABC010101AB1',
                'clave_actividad' => $claveActividad,
            ],
            'aviso' => [[
                'referencia_aviso' => 'FR2E2E' . date('dHis'),
                'prioridad' => '1',
                'alerta' => ['tipo_alerta' => '100'],
                'persona_aviso' => [[
                    'tipo_persona' => [
                        'persona_fisica' => [
                            'nombre' => 'JUAN',
                            'apellido_paterno' => 'PEREZ',
                            'apellido_materno' => 'LOPEZ',
                            'fecha_nacimiento' => '19900101',
                            'rfc' => 'PELJ900101AB1',
                            'curp' => 'PELJ900101HDFRPN09',
                            'pais_nacionalidad' => 'MX',
                            'actividad_economica' => '1000000',
                        ],
                    ],
                    'tipo_domicilio' => [
                        'nacional' => [
                            'colonia' => 'CENTRO',
                            'calle' => 'PRINCIPAL',
                            'numero_exterior' => '10',
                            'codigo_postal' => '06000',
                        ],
                    ],
                    'telefono' => [
                        'clave_pais' => 'MX',
                        'numero_telefono' => '5512345678',
                        'correo_electronico' => 'QA@MAIL.COM',
                    ],
                ]],
                'detalle_operaciones' => [[
                    'datos_operacion' => [[]],
                ]],
            ]],
        ]],
    ];

    if ($subfraccion === 'servicio_credito') {
        $base['informe'][0]['aviso'][0]['alerta']['tipo_alerta'] = '2201';
        $base['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0] = [
            'fecha_periodo' => date('Ym'),
            'tipo_operacion' => '1701',
            'tipo_tarjeta' => '1',
            'numero_identificador' => 'ABC123456789',
            'monto_gasto' => '25000.00',
        ];
        return $base;
    }

    if ($subfraccion === 'prepago_cupones') {
        $base['informe'][0]['aviso'][0]['alerta']['tipo_alerta'] = '2301';
        $base['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0] = [
            'fecha_operacion' => date('Ymd'),
            'codigo_postal' => '06000',
            'tipo_operacion' => '231',
            'cantidad' => '50',
            'datos_liquidacion' => [[
                'fecha_pago' => date('Ymd'),
                'instrumento_monetario' => '1',
                'moneda' => '1',
                'monto_operacion' => '15500.00',
            ]],
        ];
        return $base;
    }

    // devolucion_recompensas (TDR)
    $base['informe'][0]['aviso'][0]['alerta']['tipo_alerta'] = '3701';
    $base['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0] = [
        'fecha_operacion' => date('Ymd'),
        'codigo_postal' => '06000',
        'tipo_operacion' => '262',
        'cantidad' => '150',
        'datos_liquidacion' => [[
            'moneda' => '3',
            'monto_operacion' => '15500.00',
        ]],
    ];
    return $base;
}

$ok = [];
$err = [];
$logs = [];
$acumLogs = [];

try {
    $idFraccion = qaFr2ResolveIdFraccion($pdo);
    if (!$idFraccion) {
        throw new RuntimeException('No se encontró Fracción II en cat_vulnerables.');
    }

    $defs = getSubfraccionesIIDefinition();
    if (empty($defs) || !is_array($defs)) {
        throw new RuntimeException('No hay definición de subfracciones II.');
    }
    $filterSubf = qaFr2GetSubfArg($argv ?? []);
    if ($filterSubf !== '') {
        if (!isset($defs[$filterSubf])) {
            throw new RuntimeException('Subfracción inválida para --subf. Use: servicio_credito|prepago_cupones|devolucion_recompensas (o tsc|tpp|tdr).');
        }
        $defs = [$filterSubf => $defs[$filterSubf]];
        $logs[] = 'Modo subfracción individual: ' . $filterSubf;
    }

    // Buscar cliente apto sin persistir
    $pdo->beginTransaction();
    $candidate = qaFr2FindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) {
        throw new RuntimeException('No se encontró cliente apto para E2E Fracción II.');
    }

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaFr2Uma($pdo);
    $today = date('Y-m-d');
    $runId = date('YmdHis');

    $pdo->beginTransaction();

    foreach ($defs as $key => $meta) {
        if (!is_array($meta)) continue;

        $nombre = (string)($meta['nombre'] ?? $key);
        $claveActividad = (string)getSubfraccionIIClaveActividad($key);
        $umbralIdentUma = (float)getUmbralIdentificacionIIPorSubfraccion($key);
        $umbralAvisoUma = (float)getUmbralAvisoIIPorSubfraccion($key);
        $umbralAvisoMxn = $umbralAvisoUma * $uma;

        $prefix = 'II:' . $key . ':E2E:' . $runId . ':';

        // A) No aviso
        $montoA = max(100.0, min(1000.0, $umbralAvisoMxn * 0.02));
        $resA = registrarOperacionPLD($pdo, [
            'id_cliente' => $idCliente,
            'monto' => $montoA,
            'fecha_operacion' => $today,
            'id_fraccion' => (int)$idFraccion,
            'tipo_operacion' => $prefix . 'A_NO_AVISO',
            'umbral_identificacion_uma_override' => $umbralIdentUma,
            'umbral_aviso_uma_override' => $umbralAvisoUma,
            'umbral_acumulacion_uma_override' => $umbralAvisoUma,
        ]);
        qaFr2Assert(!empty($resA['success']), "[{$key}] Caso A registra", "[{$key}] Caso A no registró", $ok, $err);
        qaFr2Assert(empty($resA['requiere_aviso']), "[{$key}] Caso A sin aviso", "[{$key}] Caso A generó aviso inesperado", $ok, $err);

        // B) Aviso individual
        $montoB = $umbralAvisoMxn + max(1000.0, $umbralAvisoMxn * 0.02);
        $resB = registrarOperacionPLD($pdo, [
            'id_cliente' => $idCliente,
            'monto' => $montoB,
            'fecha_operacion' => $today,
            'id_fraccion' => (int)$idFraccion,
            'tipo_operacion' => $prefix . 'B_INDIVIDUAL',
            'umbral_identificacion_uma_override' => $umbralIdentUma,
            'umbral_aviso_uma_override' => $umbralAvisoUma,
            'umbral_acumulacion_uma_override' => $umbralAvisoUma,
        ]);
        qaFr2Assert(!empty($resB['success']), "[{$key}] Caso B registra", "[{$key}] Caso B no registró", $ok, $err);
        qaFr2Assert(!empty($resB['requiere_aviso']), "[{$key}] Caso B genera aviso individual", "[{$key}] Caso B no generó aviso individual", $ok, $err);
        qaFr2Assert(!empty($resB['id_aviso']), "[{$key}] Caso B genera id_aviso", "[{$key}] Caso B sin id_aviso", $ok, $err);

        // C) Acumulación
        $triggeredAcum = false;
        $base = max(100.0, floor(($umbralAvisoMxn / 5.0) / 100.0) * 100.0);
        if ($base >= $umbralAvisoMxn) {
            $base = max(100.0, floor(($umbralAvisoMxn / 2.0) / 100.0) * 100.0);
        }
        for ($i = 0; $i < 8; $i++) {
            $montoC = $base + ($i * 31.0);
            $resC = registrarOperacionPLD($pdo, [
                'id_cliente' => $idCliente,
                'monto' => $montoC,
                'fecha_operacion' => $today,
                'id_fraccion' => (int)$idFraccion,
                'tipo_operacion' => $prefix . 'C_ACUM',
                'umbral_identificacion_uma_override' => $umbralIdentUma,
                'umbral_aviso_uma_override' => $umbralAvisoUma,
                'umbral_acumulacion_uma_override' => $umbralAvisoUma,
            ]);
            $acumLogs[] = "[{$key}] step=" . ($i + 1) . " monto={$montoC} success=" . (!empty($resC['success']) ? '1' : '0')
                . " requiere_aviso=" . (!empty($resC['requiere_aviso']) ? '1' : '0') . " tipo=" . ($resC['tipo_aviso'] ?? '-');
            if (!empty($resC['success']) && ($resC['tipo_aviso'] ?? '') === 'acumulacion') {
                $triggeredAcum = true;
                break;
            }
        }
        qaFr2Assert($triggeredAcum, "[{$key}] Caso C dispara acumulación", "[{$key}] Caso C no disparó acumulación", $ok, $err);

        // XML por subfracción
        $xmlPayload = qaFr2XmlPayload($key, $claveActividad);
        if ($key === 'servicio_credito') {
            $xmlRes = generateTSCXml($xmlPayload);
            $xml = $xmlRes['xml'] ?? '';
            qaFr2Assert(strpos($xml, '/recepcion/tsc') !== false, "[{$key}] XML namespace TSC", "[{$key}] XML namespace inválido", $ok, $err);
        } elseif ($key === 'prepago_cupones') {
            $xmlRes = generateTPPXml($xmlPayload);
            $xml = $xmlRes['xml'] ?? '';
            qaFr2Assert(strpos($xml, '/recepcion/tpp') !== false, "[{$key}] XML namespace TPP", "[{$key}] XML namespace inválido", $ok, $err);
            qaFr2Assert(strpos($xml, '<fecha_pago>') !== false, "[{$key}] XML incluye fecha_pago", "[{$key}] XML sin fecha_pago", $ok, $err);
            qaFr2Assert(strpos($xml, '<instrumento_monetario>') !== false, "[{$key}] XML incluye instrumento_monetario", "[{$key}] XML sin instrumento_monetario", $ok, $err);
        } else {
            $xmlRes = generateTDRXml($xmlPayload);
            $xml = $xmlRes['xml'] ?? '';
            qaFr2Assert(strpos($xml, '/recepcion/tdr') !== false, "[{$key}] XML namespace TDR", "[{$key}] XML namespace inválido", $ok, $err);
            qaFr2Assert(strpos($xml, '<fecha_pago>') === false, "[{$key}] XML sin fecha_pago", "[{$key}] XML TDR incluyó fecha_pago", $ok, $err);
            qaFr2Assert(strpos($xml, '<instrumento_monetario>') === false, "[{$key}] XML sin instrumento_monetario", "[{$key}] XML TDR incluyó instrumento_monetario", $ok, $err);
        }
        qaFr2Assert(!empty($xml), "[{$key}] XML generado", "[{$key}] XML no generado", $ok, $err);
    }

    $pdo->rollBack();

    echo "=== E2E FRACCION II (TSC/TPP/TDR) ===\n";
    echo "Cliente usado: {$idCliente}\n";
    echo "Fracción II id_vulnerable: {$idFraccion}\n";
    echo "UMA: {$uma}\n";
    echo "---\n";
    foreach ($logs as $line) {
        echo "[LOG] {$line}\n";
    }
    foreach ($acumLogs as $line) {
        echo "[ACUM] {$line}\n";
    }
    echo "---\n";
    echo "OK: " . count($ok) . "\n";
    foreach ($ok as $line) {
        echo " + {$line}\n";
    }
    if (!empty($err)) {
        echo "ERRORS: " . count($err) . "\n";
        foreach ($err as $line) {
            echo " - {$line}\n";
        }
        echo "RESULT: FAIL\n";
        exit(1);
    }
    echo "RESULT: PASS\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "RESULT: FAIL\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
