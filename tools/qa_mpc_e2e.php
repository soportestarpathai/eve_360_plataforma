<?php
/**
 * E2E MPC (Fracción IV)
 * - Prueba registrarOperacionPLD: sin aviso, aviso individual y acumulación
 * - Prueba generación XML MPC
 * - Ejecuta en transacción y hace rollback (no deja datos QA)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_iv.php';
require_once __DIR__ . '/../config/mpc_xml_helper.php';

function qaMpcAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) $ok[] = $okMsg; else $err[] = $errMsg;
}

function qaMpcUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaMpcResolveIdFraccion(PDO $pdo): ?int
{
    if (function_exists('getIdVulnerableFraccionIV')) {
        $id = getIdVulnerableFraccionIV($pdo);
        if (!empty($id)) return (int)$id;
    }
    $stmt = $pdo->query("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'IV' LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return $row ? (int)$row['id_vulnerable'] : null;
}

function qaMpcThresholdUma(PDO $pdo, int $idFraccion, string $column, float $fallback): float
{
    $stmt = $pdo->prepare("SELECT {$column} AS val FROM cat_vulnerables WHERE id_vulnerable = ? LIMIT 1");
    $stmt->execute([$idFraccion]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!empty($row['val'])) {
        $v = (float)$row['val'];
        return $v > 0 ? $v : $fallback;
    }
    return $fallback;
}

function qaMpcFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
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
            'tipo_operacion' => 'IV:MPC:E2E:PROBE',
            'umbral_aviso_uma_override' => 1605.0,
            'umbral_acumulacion_uma_override' => 1605.0
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
    $idFraccion = qaMpcResolveIdFraccion($pdo);
    if (!$idFraccion) throw new RuntimeException('No se encontró Fracción IV en cat_vulnerables.');

    $pdo->beginTransaction();
    $candidate = qaMpcFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) throw new RuntimeException('No se encontró cliente apto para E2E MPC.');

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaMpcUma($pdo);
    $umbralAvisoUma = qaMpcThresholdUma($pdo, (int)$idFraccion, 'umbral_aviso_uma', 1605.0);
    $umbralAcumUma = qaMpcThresholdUma($pdo, (int)$idFraccion, 'umbral_acumulacion_uma', 1605.0);
    $umbralAvisoMxn = $umbralAvisoUma * $uma;
    $umbralAcumMxn = $umbralAcumUma * $uma;
    $today = date('Y-m-d');
    $label = 'IV:MPC:E2E:' . date('YmdHis');

    $pdo->beginTransaction();

    $montoA = max(100.0, min(1000.0, $umbralAvisoMxn * 0.01));
    $resA = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoA,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':A:NO_AVISO'
    ]);
    qaMpcAssert(!empty($resA['success']), 'Caso A registra operación', 'Caso A no registró operación', $ok, $err);
    qaMpcAssert(empty($resA['requiere_aviso']), 'Caso A sin aviso', 'Caso A generó aviso inesperado', $ok, $err);

    $montoB = $umbralAvisoMxn + max(1000.0, $umbralAvisoMxn * 0.02);
    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoB,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:INDIVIDUAL'
    ]);
    qaMpcAssert(!empty($resB['success']), 'Caso B registra operación', 'Caso B no registró operación', $ok, $err);
    qaMpcAssert(!empty($resB['requiere_aviso']), 'Caso B genera aviso individual', 'Caso B no generó aviso individual', $ok, $err);
    qaMpcAssert(!empty($resB['id_aviso']), 'Caso B genera id_aviso', 'Caso B sin id_aviso', $ok, $err);

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
            'tipo_operacion' => $tipoAcum
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
    qaMpcAssert($triggered, 'Caso C dispara acumulación', 'Caso C no disparó acumulación', $ok, $err);

    $xmlPayload = [
        'informe' => [[
            'mes_reportado' => date('Ym'),
            'sujeto_obligado' => [
                'clave_sujeto_obligado' => 'OGA751212G56',
                'clave_actividad' => 'MPC'
            ],
            'aviso' => [[
                'referencia_aviso' => 'REF' . date('dHis'),
                'prioridad' => '1',
                'alerta' => ['tipo_alerta' => '2803'],
                'persona_aviso' => [[
                    'tipo_persona' => [
                        'persona_moral' => [
                            'denominacion_razon' => 'LA MORALISTA',
                            'fecha_constitucion' => '20051212',
                            'pais_nacionalidad' => 'MX',
                            'giro_mercantil' => '1000000',
                            'representante_apoderado' => [
                                'nombre' => 'JUAN',
                                'apellido_paterno' => 'GOMEZ',
                                'apellido_materno' => 'PEREZ',
                                'fecha_nacimiento' => '19801212',
                                'rfc' => 'GOPJ801212R45'
                            ]
                        ]
                    ],
                    'tipo_domicilio' => [
                        'nacional' => [
                            'colonia' => 'GUADALUPE INN',
                            'calle' => 'REVOLUCION',
                            'numero_exterior' => '458',
                            'codigo_postal' => '01020'
                        ]
                    ]
                ]],
                'detalle_operaciones' => [[
                    'datos_operacion' => [[
                        'fecha_operacion' => date('Ymd'),
                        'codigo_postal' => '09230',
                        'tipo_operacion' => '402',
                        'datos_garantia' => [[
                            'tipo_garantia' => '2',
                            'datos_bien_mutuo' => [
                                'datos_inmueble' => [
                                    'tipo_inmueble' => '1',
                                    'valor_referencia' => '5555555.00',
                                    'codigo_postal' => '09230',
                                    'folio_real' => '211561'
                                ]
                            ],
                            'tipo_persona' => [
                                'persona_fisica' => [
                                    'nombre' => 'JOSE',
                                    'apellido_paterno' => 'RODRIGUEZ',
                                    'apellido_materno' => 'SOLANO',
                                    'fecha_nacimiento' => '19780522'
                                ]
                            ]
                        ]],
                        'datos_liquidacion' => [[
                            'fecha_disposicion' => date('Ymd'),
                            'instrumento_monetario' => '1',
                            'moneda' => '2',
                            'monto_operacion' => '2342343.00'
                        ]]
                    ]]
                ]]
            ]]
        ]]
    ];

    $gen = generateMPCXml($xmlPayload);
    $xml = (string)($gen['xml'] ?? '');
    $xmlErrs = (array)($gen['errors'] ?? []);
    qaMpcAssert($xml !== '', 'XML MPC generado', 'No se generó XML MPC', $ok, $err);
    qaMpcAssert(empty($xmlErrs), 'XML MPC sin errores helper', 'XML MPC con errores helper: ' . implode('; ', $xmlErrs), $ok, $err);
    qaMpcAssert(strpos($xml, '<clave_actividad>MPC</clave_actividad>') !== false, 'XML incluye clave_actividad MPC', 'XML no incluye clave_actividad MPC', $ok, $err);
    qaMpcAssert(strpos($xml, '<datos_garantia>') !== false, 'XML incluye datos_garantia', 'XML no incluye datos_garantia', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E MPC ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fracción IV id_vulnerable: {$idFraccion}\n";
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

