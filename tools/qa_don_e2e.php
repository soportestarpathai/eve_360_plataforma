<?php
/**
 * E2E DON (Fraccion XIII)
 * - Prueba registrarOperacionPLD con umbral individual y acumulacion
 * - Prueba generacion XML DON
 * - Ejecuta en transaccion y hace rollback
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_xiii.php';
require_once __DIR__ . '/../config/don_xml_helper.php';

function qaDonFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
{
    $rows = $pdo->query("
        SELECT id_cliente, no_contrato, expediente_completo
        FROM clientes
        WHERE COALESCE(id_status, 1) <> 4
        ORDER BY COALESCE(expediente_completo,0) DESC, id_cliente DESC
        LIMIT 120
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
            'tipo_operacion' => 'DON:E2E:PROBE'
        ]);
        if (!empty($probe['success'])) {
            $logs[] = 'Cliente apto id=' . (int)$c['id_cliente'] . ' contrato=' . ($c['no_contrato'] ?? '');
            return $c;
        }
        $logs[] = 'Cliente bloqueado id=' . (int)$c['id_cliente'] . ': ' . ($probe['message'] ?? 'sin mensaje');
    }
    return null;
}

function qaDonUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaDonThresholdUma(PDO $pdo, int $idFraccion, string $column, float $fallback): float
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

function qaDonOk(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) {
        $ok[] = $okMsg;
    } else {
        $err[] = $errMsg;
    }
}

$ok = [];
$err = [];
$logs = [];

try {
    $idFraccion = getIdVulnerableFraccionXIII($pdo);
    if (!$idFraccion) {
        throw new RuntimeException('No se encontro Fraccion XIII en cat_vulnerables.');
    }

    $pdo->beginTransaction();
    $candidate = qaDonFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) {
        throw new RuntimeException('No se encontro cliente apto para E2E DON.');
    }

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaDonUma($pdo);
    $umbralAvisoUma = qaDonThresholdUma($pdo, (int)$idFraccion, 'umbral_aviso_uma', 3210.0);
    $umbralAcumUma = qaDonThresholdUma($pdo, (int)$idFraccion, 'umbral_acumulacion_uma', 3210.0);
    $umbralAvisoMxn = $umbralAvisoUma * $uma;
    $umbralAcumMxn = $umbralAcumUma * $uma;

    $today = date('Y-m-d');
    $label = 'DON:E2E:' . date('YmdHis');

    $pdo->beginTransaction();

    // Caso A: monto menor a umbral (sin aviso)
    $montoA = max(100.0, min(1000.0, $umbralAvisoMxn * 0.01));
    $resA = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoA,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':A:NO_AVISO'
    ]);
    qaDonOk(!empty($resA['success']), 'Caso A registra operacion', 'Caso A no registro operacion', $ok, $err);
    qaDonOk(empty($resA['requiere_aviso']), 'Caso A sin aviso', 'Caso A genero aviso inesperado', $ok, $err);

    // Caso B: monto mayor a umbral individual (genera aviso)
    $montoB = $umbralAvisoMxn + max(1000.0, $umbralAvisoMxn * 0.02);
    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoB,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:INDIVIDUAL'
    ]);
    qaDonOk(!empty($resB['success']), 'Caso B registra operacion', 'Caso B no registro operacion', $ok, $err);
    qaDonOk(!empty($resB['requiere_aviso']), 'Caso B genera aviso individual', 'Caso B no genero aviso individual', $ok, $err);
    qaDonOk(!empty($resB['id_aviso']), 'Caso B genera id_aviso', 'Caso B sin id_aviso', $ok, $err);

    // Caso C: acumulacion en ventana 6 meses
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
        if (!empty($resC['success']) && ($resC['tipo_aviso'] ?? '') === 'acumulacion') {
            $triggered = true;
            break;
        }
    }
    qaDonOk($triggered, 'Caso C dispara acumulacion', 'Caso C no disparo acumulacion', $ok, $err);

    // XML DON
    $xmlPayload = [
        'informe' => [[
            'mes_reportado' => date('Ym'),
            'sujeto_obligado' => [
                'clave_sujeto_obligado' => 'ABC010101AB1',
                'clave_actividad' => 'DON'
            ],
            'aviso' => [[
                'referencia_aviso' => 'DONTEST01',
                'prioridad' => '1',
                'alerta' => ['tipo_alerta' => '100'],
                'persona_aviso' => [[
                    'tipo_persona' => [
                        'persona_fisica' => [
                            'nombre' => 'JUAN',
                            'apellido_paterno' => 'PEREZ',
                            'apellido_materno' => 'LOPEZ',
                            'pais_nacionalidad' => 'MX',
                            'actividad_economica' => '1000000'
                        ]
                    ],
                    'tipo_domicilio' => [
                        'nacional' => [
                            'colonia' => 'CENTRO',
                            'calle' => 'PRINCIPAL',
                            'numero_exterior' => '10',
                            'codigo_postal' => '06000'
                        ]
                    ],
                    'telefono' => [
                        'clave_pais' => 'MX',
                        'numero_telefono' => '5512345678'
                    ]
                ]],
                'detalle_operaciones' => [[
                    'datos_operacion' => [[
                        'fecha_operacion' => date('Ymd'),
                        'codigo_postal' => '06000',
                        'tipo_operacion' => '1301',
                        'datos_donativo' => [[
                            'tipo_donativo' => [[
                                'liquidacion_numerario' => [
                                    'fecha_pago' => date('Ymd'),
                                    'instrumento_monetario' => '1',
                                    'moneda' => '147',
                                    'monto_operacion' => '5000.00'
                                ]
                            ]]
                        ]]
                    ]]
                ]]
            ]]
        ]]
    ];
    $xmlRes = generateDONXml($xmlPayload);
    qaDonOk(!empty($xmlRes['xml']), 'XML DON generado', 'No se genero XML DON', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E DON ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fraccion XIII id_vulnerable: {$idFraccion}\n";
    echo "UMA: {$uma}\n";
    echo "Umbral aviso: {$umbralAvisoUma} UMA ({$umbralAvisoMxn} MXN)\n";
    echo "Umbral acumulacion: {$umbralAcumUma} UMA ({$umbralAcumMxn} MXN)\n";
    echo "---\n";
    foreach ($logs as $line) {
        echo "[LOG] {$line}\n";
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
