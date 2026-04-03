<?php
/**
 * E2E VEH (Fracción VIII)
 * - Prueba registrarOperacionPLD con umbral individual y acumulación
 * - Prueba generación XML VEH
 * - Ejecuta en transacción y hace rollback
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_viii.php';
require_once __DIR__ . '/../config/veh_xml_helper.php';

function qaVehAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) {
        $ok[] = $okMsg;
    } else {
        $err[] = $errMsg;
    }
}

function qaVehUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaVehThresholdUma(PDO $pdo, int $idFraccion, string $column, float $fallback): float
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

function qaVehFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
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
            'tipo_operacion' => 'VIII:VEH:E2E:PROBE',
            'umbral_aviso_uma_override' => 6420.0,
            'umbral_acumulacion_uma_override' => 6420.0
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
    $idFraccion = getIdVulnerableFraccionVIII($pdo);
    if (!$idFraccion) {
        throw new RuntimeException('No se encontró Fracción VIII en cat_vulnerables.');
    }

    $pdo->beginTransaction();
    $candidate = qaVehFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) {
        throw new RuntimeException('No se encontró cliente apto para E2E VEH.');
    }

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaVehUma($pdo);
    $umbralAvisoUma = qaVehThresholdUma($pdo, (int)$idFraccion, 'umbral_aviso_uma', 6420.0);
    $umbralAcumUma = qaVehThresholdUma($pdo, (int)$idFraccion, 'umbral_acumulacion_uma', 6420.0);
    $umbralAvisoMxn = $umbralAvisoUma * $uma;
    $umbralAcumMxn = $umbralAcumUma * $uma;

    $today = date('Y-m-d');
    $label = 'VIII:VEH:E2E:' . date('YmdHis');

    $pdo->beginTransaction();

    // Caso A: sin aviso
    $montoA = max(100.0, min(1000.0, $umbralAvisoMxn * 0.01));
    $resA = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoA,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':A:NO_AVISO'
    ]);
    qaVehAssert(!empty($resA['success']), 'Caso A registra operación', 'Caso A no registró operación', $ok, $err);
    qaVehAssert(empty($resA['requiere_aviso']), 'Caso A sin aviso', 'Caso A generó aviso inesperado', $ok, $err);

    // Caso B: aviso individual
    $montoB = $umbralAvisoMxn + max(1000.0, $umbralAvisoMxn * 0.02);
    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoB,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:INDIVIDUAL'
    ]);
    qaVehAssert(!empty($resB['success']), 'Caso B registra operación', 'Caso B no registró operación', $ok, $err);
    qaVehAssert(!empty($resB['requiere_aviso']), 'Caso B genera aviso individual', 'Caso B no generó aviso individual', $ok, $err);
    qaVehAssert(!empty($resB['id_aviso']), 'Caso B genera id_aviso', 'Caso B sin id_aviso', $ok, $err);

    // Caso C: acumulación
    $tipoAcum = $label . ':C:ACUM';
    $base = max(100.0, floor(($umbralAcumMxn / 5.0) / 100.0) * 100.0);
    if ($base >= $umbralAvisoMxn) {
        $base = max(100.0, floor(($umbralAvisoMxn / 2.0) / 100.0) * 100.0);
    }
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
    qaVehAssert($triggered, 'Caso C dispara acumulación', 'Caso C no disparó acumulación', $ok, $err);

    // XML VEH
    $xmlPayload = [
        'informe' => [[
            'mes_reportado' => date('Ym'),
            'sujeto_obligado' => [
                'clave_sujeto_obligado' => 'OCA891122K87',
                'clave_actividad' => 'VEH'
            ],
            'aviso' => [[
                'referencia_aviso' => 'REF' . date('dHis'),
                'prioridad' => '1',
                'alerta' => ['tipo_alerta' => '803'],
                'persona_aviso' => [[
                    'tipo_persona' => [
                        'persona_fisica' => [
                            'nombre' => 'JOSE ELIAS',
                            'apellido_paterno' => 'MORENO',
                            'apellido_materno' => 'VALLE',
                            'fecha_nacimiento' => '19890516',
                            'pais_nacionalidad' => 'RW',
                            'actividad_economica' => '6117000'
                        ]
                    ],
                    'tipo_domicilio' => [
                        'nacional' => [
                            'colonia' => 'SAN SIMON TOLNAHUAC',
                            'calle' => 'VIOLANTE',
                            'numero_exterior' => '887',
                            'codigo_postal' => '06920'
                        ]
                    ]
                ]],
                'dueno_beneficiario' => [[
                    'tipo_persona' => [
                        'persona_fisica' => [
                            'nombre' => 'BENITO',
                            'apellido_paterno' => 'PEREZ',
                            'apellido_materno' => 'GALDOS',
                            'fecha_nacimiento' => '19560516',
                            'pais_nacionalidad' => 'AI'
                        ]
                    ]
                ]],
                'detalle_operaciones' => [[
                    'datos_operacion' => [[
                        'fecha_operacion' => date('Ymd'),
                        'codigo_postal' => '01030',
                        'tipo_operacion' => '802',
                        'tipo_vehiculo' => [[
                            'datos_vehiculo_terrestre' => [
                                'marca_fabricante' => 'FORD',
                                'modelo' => 'MUSTANG GT',
                                'anio' => '2014',
                                'vin' => 'JKLSD789SDIFLGHS8',
                                'repuve' => '23452452',
                                'placas' => 'ABC123',
                                'nivel_blindaje' => '3'
                            ]
                        ]],
                        'datos_liquidacion' => [[
                            'fecha_pago' => date('Ymd'),
                            'forma_pago' => '1',
                            'instrumento_monetario' => '1',
                            'moneda' => '3',
                            'monto_operacion' => '2728458.10'
                        ]]
                    ]]
                ]]
            ]]
        ]]
    ];
    $gen = generateVEHXml($xmlPayload);
    $xml = (string)($gen['xml'] ?? '');
    $xmlErrs = (array)($gen['errors'] ?? []);
    qaVehAssert($xml !== '', 'XML VEH generado', 'No se generó XML VEH', $ok, $err);
    qaVehAssert(empty($xmlErrs), 'XML VEH sin errores helper', 'XML VEH con errores helper: ' . implode('; ', $xmlErrs), $ok, $err);
    qaVehAssert(strpos($xml, '<clave_actividad>VEH</clave_actividad>') !== false, 'XML incluye clave_actividad VEH', 'XML no incluye clave_actividad VEH', $ok, $err);
    qaVehAssert(strpos($xml, '<tipo_vehiculo>') !== false, 'XML incluye tipo_vehiculo', 'XML no incluye tipo_vehiculo', $ok, $err);
    qaVehAssert(strpos($xml, '<datos_liquidacion>') !== false, 'XML incluye datos_liquidacion', 'XML no incluye datos_liquidacion', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E VEH ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fracción VIII id_vulnerable: {$idFraccion}\n";
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
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "RESULT: FAIL\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

