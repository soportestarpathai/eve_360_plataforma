<?php
/**
 * E2E JYS (Fracción I)
 * - Prueba registrarOperacionPLD: sin aviso, aviso individual y acumulación
 * - Prueba generación XML JYS
 * - Ejecuta en transacción y hace rollback (no deja datos QA)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_i.php';
require_once __DIR__ . '/../config/jys_xml_helper.php';

function qaJysAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) $ok[] = $okMsg; else $err[] = $errMsg;
}

function qaJysUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaJysResolveIdFraccion(PDO $pdo): ?int
{
    if (function_exists('getIdVulnerableFraccionI')) {
        $id = getIdVulnerableFraccionI($pdo);
        if (!empty($id)) return (int)$id;
    }
    $stmt = $pdo->query("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'I' LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return $row ? (int)$row['id_vulnerable'] : null;
}

function qaJysThresholdUma(PDO $pdo, int $idFraccion, string $column, float $fallback): float
{
    $stmt = $pdo->prepare("SELECT {$column} AS val FROM cat_vulnerables WHERE id_vulnerable = ? LIMIT 1");
    $stmt->execute([$idFraccion]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $raw = $row['val'] ?? null;
    if ($raw !== null && $raw !== '') {
        $v = (float)$raw;
        if ($v > 0) return $v;
    }

    $stmtCfg = $pdo->prepare("SELECT {$column} AS val FROM config_empresa WHERE id_config = 1 LIMIT 1");
    $stmtCfg->execute();
    $cfg = $stmtCfg->fetch(PDO::FETCH_ASSOC);
    $rawCfg = $cfg['val'] ?? null;
    if ($rawCfg !== null && $rawCfg !== '') {
        $vCfg = (float)$rawCfg;
        if ($vCfg > 0) return $vCfg;
    }

    return $fallback;
}

function qaJysFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
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
            'tipo_operacion' => 'I:JYS:E2E:PROBE',
            'umbral_aviso_uma_override' => 645.0,
            'umbral_acumulacion_uma_override' => 645.0
        ]);
        if (!empty($probe['success'])) {
            $logs[] = 'Cliente apto id=' . (int)$c['id_cliente'] . ' contrato=' . ($c['no_contrato'] ?? '');
            return $c;
        }
        $logs[] = 'Cliente bloqueado id=' . (int)$c['id_cliente'] . ': ' . ($probe['message'] ?? 'sin mensaje');
    }
    return null;
}

$ok = [];
$err = [];
$logs = [];
$acumLogs = [];

try {
    $idFraccion = qaJysResolveIdFraccion($pdo);
    if (!$idFraccion) throw new RuntimeException('No se encontró Fracción I en cat_vulnerables.');

    $pdo->beginTransaction();
    $candidate = qaJysFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) throw new RuntimeException('No se encontró cliente apto para E2E JYS.');

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaJysUma($pdo);
    $umbralAvisoUma = qaJysThresholdUma($pdo, (int)$idFraccion, 'umbral_aviso_uma', 645.0);
    $umbralAcumUma = qaJysThresholdUma($pdo, (int)$idFraccion, 'umbral_acumulacion_uma', 645.0);
    $umbralAvisoMxn = $umbralAvisoUma * $uma;
    $umbralAcumMxn = $umbralAcumUma * $uma;
    $today = date('Y-m-d');
    $label = 'I:JYS:E2E:' . date('YmdHis');

    $pdo->beginTransaction();

    $montoA = max(100.0, min(1000.0, $umbralAvisoMxn * 0.01));
    $resA = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoA,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':A:NO_AVISO',
        'umbral_aviso_uma_override' => 645.0,
        'umbral_acumulacion_uma_override' => 645.0
    ]);
    qaJysAssert(!empty($resA['success']), 'Caso A registra operación', 'Caso A no registró operación', $ok, $err);
    qaJysAssert(empty($resA['requiere_aviso']), 'Caso A sin aviso', 'Caso A generó aviso inesperado', $ok, $err);

    $montoB = $umbralAvisoMxn + max(1000.0, $umbralAvisoMxn * 0.02);
    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoB,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:INDIVIDUAL',
        'umbral_aviso_uma_override' => 645.0,
        'umbral_acumulacion_uma_override' => 645.0
    ]);
    qaJysAssert(!empty($resB['success']), 'Caso B registra operación', 'Caso B no registró operación', $ok, $err);
    qaJysAssert(!empty($resB['requiere_aviso']), 'Caso B genera aviso individual', 'Caso B no generó aviso individual', $ok, $err);
    qaJysAssert(!empty($resB['id_aviso']), 'Caso B genera id_aviso', 'Caso B sin id_aviso', $ok, $err);

    $tipoAcum = $label . ':C:ACUM';
    $base = max(100.0, floor(($umbralAcumMxn / 5.0) / 100.0) * 100.0);
    if ($base >= $umbralAvisoMxn) $base = max(100.0, floor(($umbralAvisoMxn / 2.0) / 100.0) * 100.0);
    $triggered = false;
    for ($i = 0; $i < 8; $i++) {
        $m = $base + ($i * 31.0);
        $resC = registrarOperacionPLD($pdo, [
            'id_cliente' => $idCliente,
            'monto' => $m,
            'fecha_operacion' => $today,
            'id_fraccion' => (int)$idFraccion,
            'tipo_operacion' => $tipoAcum,
            'umbral_aviso_uma_override' => 645.0,
            'umbral_acumulacion_uma_override' => 645.0
        ]);
        $acumLogs[] = '[ACUM] step=' . ($i + 1)
            . ' monto=' . $m
            . ' success=' . (!empty($resC['success']) ? '1' : '0')
            . ' requiere_aviso=' . (!empty($resC['requiere_aviso']) ? '1' : '0')
            . ' tipo=' . ($resC['tipo_aviso'] ?? '-');
        if (!empty($resC['success']) && ($resC['tipo_aviso'] ?? '') === 'acumulacion') {
            $triggered = true;
            break;
        }
    }
    qaJysAssert($triggered, 'Caso C dispara acumulación', 'Caso C no disparó acumulación', $ok, $err);

    $xmlPayload = [
        'informe' => [[
            'mes_reportado' => date('Ym'),
            'sujeto_obligado' => [
                'clave_sujeto_obligado' => 'GAMJ860503HJU',
                'clave_actividad' => 'JYS'
            ],
            'aviso' => [[
                'referencia_aviso' => 'REF' . date('dHis'),
                'prioridad' => '1',
                'alerta' => ['tipo_alerta' => '2101'],
                'persona_aviso' => [[
                    'tipo_persona' => [
                        'persona_fisica' => [
                            'nombre' => 'JORGE',
                            'apellido_paterno' => 'TONO',
                            'apellido_materno' => 'XXXX',
                            'fecha_nacimiento' => '19870101',
                            'rfc' => 'TOJX870101RE4',
                            'curp' => 'TOJX870101HDFJHR09',
                            'pais_nacionalidad' => 'DE',
                            'actividad_economica' => '1000000'
                        ]
                    ],
                    'tipo_domicilio' => [
                        'nacional' => [
                            'colonia' => 'TLALNEPANTLA CENTRO',
                            'calle' => 'CALLE',
                            'numero_exterior' => '1',
                            'numero_interior' => 'B',
                            'codigo_postal' => '54000'
                        ]
                    ]
                ]],
                'detalle_operaciones' => [[
                    'datos_operacion' => [[
                        'fecha_operacion' => date('Ymd'),
                        'tipo_sucursal' => [
                            'datos_sucursal_propia' => ['codigo_postal' => '54100']
                        ],
                        'tipo_operacion' => '103',
                        'linea_negocio' => '1',
                        'medio_operacion' => '1',
                        'datos_liquidacion' => [[
                            'liquidacion_numerario' => [
                                'fecha_pago' => date('Ymd'),
                                'instrumento_monetario' => '3',
                                'moneda' => '2',
                                'monto_operacion' => '570000.00'
                            ]
                        ]]
                    ]]
                ]]
            ]]
        ]]
    ];

    $gen = generateJYSXml($xmlPayload);
    $xml = (string)($gen['xml'] ?? '');
    $xmlErrs = (array)($gen['errors'] ?? []);
    qaJysAssert($xml !== '', 'XML JYS generado', 'No se generó XML JYS', $ok, $err);
    qaJysAssert(empty($xmlErrs), 'XML JYS sin errores helper', 'XML JYS con errores helper: ' . implode('; ', $xmlErrs), $ok, $err);
    qaJysAssert(strpos($xml, '<clave_actividad>JYS</clave_actividad>') !== false, 'XML incluye clave_actividad JYS', 'XML no incluye clave_actividad JYS', $ok, $err);
    qaJysAssert(strpos($xml, '<datos_liquidacion>') !== false, 'XML incluye datos_liquidacion', 'XML no incluye datos_liquidacion', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E JYS ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fracción I id_vulnerable: {$idFraccion}\n";
    echo "UMA: {$uma}\n";
    echo "Umbral aviso: {$umbralAvisoUma} UMA ({$umbralAvisoMxn} MXN)\n";
    echo "Umbral acumulación: {$umbralAcumUma} UMA ({$umbralAcumMxn} MXN)\n";
    echo "---\n";
    foreach ($logs as $line) echo "[LOG] {$line}\n";
    foreach ($acumLogs as $line) echo "{$line}\n";
    echo "---\n";
    echo "OK: " . count($ok) . "\n";
    foreach ($ok as $line) echo " + {$line}\n";
    if (!empty($err)) {
        echo "ERRORS: " . count($err) . "\n";
        foreach ($err as $line) echo " - {$line}\n";
        exit(1);
    }
    echo "RESULT: PASS\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "RESULT: FAIL\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
