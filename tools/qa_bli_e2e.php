<?php
/**
 * E2E BLI (Fracción IX)
 * - Prueba registrarOperacionPLD: sin aviso, aviso individual y acumulación
 * - Prueba generación XML BLI
 * - Ejecuta en transacción y hace rollback (no deja datos QA)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_ix.php';
require_once __DIR__ . '/../config/bli_xml_helper.php';

function qaBliAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) {
        $ok[] = $okMsg;
    } else {
        $err[] = $errMsg;
    }
}

function qaBliUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaBliThresholdUma(PDO $pdo, int $idFraccion, string $column, float $fallback): float
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

function qaBliFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
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
            'tipo_operacion' => 'IX:BLI:E2E:PROBE',
            'umbral_aviso_uma_override' => 4815.0,
            'umbral_acumulacion_uma_override' => 4815.0
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
    $idFraccion = getIdVulnerableFraccionIX($pdo);
    if (!$idFraccion) {
        throw new RuntimeException('No se encontró Fracción IX en cat_vulnerables.');
    }

    $pdo->beginTransaction();
    $candidate = qaBliFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) {
        throw new RuntimeException('No se encontró cliente apto para E2E BLI.');
    }

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaBliUma($pdo);
    $umbralAvisoUma = qaBliThresholdUma($pdo, (int)$idFraccion, 'umbral_aviso_uma', pldFraccionIXUmbralAviso());
    $umbralAcumUma = qaBliThresholdUma($pdo, (int)$idFraccion, 'umbral_acumulacion_uma', pldFraccionIXUmbralAviso());
    $umbralAvisoMxn = $umbralAvisoUma * $uma;
    $umbralAcumMxn = $umbralAcumUma * $uma;

    $today = date('Y-m-d');
    $label = 'IX:BLI:E2E:' . date('YmdHis');

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
    qaBliAssert(!empty($resA['success']), 'Caso A registra operación', 'Caso A no registró operación', $ok, $err);
    qaBliAssert(empty($resA['requiere_aviso']), 'Caso A sin aviso', 'Caso A generó aviso inesperado', $ok, $err);

    // Caso B: aviso individual
    $montoB = $umbralAvisoMxn + max(1000.0, $umbralAvisoMxn * 0.02);
    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoB,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:INDIVIDUAL'
    ]);
    qaBliAssert(!empty($resB['success']), 'Caso B registra operación', 'Caso B no registró operación', $ok, $err);
    qaBliAssert(!empty($resB['requiere_aviso']), 'Caso B genera aviso individual', 'Caso B no generó aviso individual', $ok, $err);
    qaBliAssert(!empty($resB['id_aviso']), 'Caso B genera id_aviso', 'Caso B sin id_aviso', $ok, $err);

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
    qaBliAssert($triggered, 'Caso C dispara acumulación', 'Caso C no disparó acumulación', $ok, $err);

    // XML BLI
    $xmlPayload = [
        'informe' => [[
            'mes_reportado' => date('Ym'),
            'sujeto_obligado' => [
                'clave_sujeto_obligado' => 'ABC010203AB1',
                'clave_actividad' => 'BLI'
            ],
            'aviso' => [[
                'referencia_aviso' => 'BLI' . date('dHis'),
                'prioridad' => '1',
                'alerta' => ['tipo_alerta' => '100'],
                'detalle_operaciones' => [[
                    'datos_operacion' => [[
                        'fecha_operacion' => date('Ymd'),
                        'codigo_postal' => '01020',
                        'tipo_operacion' => '901',
                        'tipo_bien_blindado' => '1',
                        'estado_bien' => '1',
                        'nivel_blindaje' => '3',
                        'descripcion_servicio' => 'BLINDAJE NIVEL 3',
                        'datos_liquidacion' => [[
                            'fecha_pago' => date('Ymd'),
                            'instrumento_monetario' => '1',
                            'moneda' => '1',
                            'monto_operacion' => '125000.00'
                        ]]
                    ]]
                ]]
            ]]
        ]]
    ];

    $gen = generateBLIXml($xmlPayload);
    $xml = (string)($gen['xml'] ?? '');
    $xmlErrs = (array)($gen['errors'] ?? []);
    qaBliAssert($xml !== '', 'XML BLI generado', 'No se generó XML BLI', $ok, $err);
    qaBliAssert(empty($xmlErrs), 'XML BLI sin errores helper', 'XML BLI con errores helper: ' . implode('; ', $xmlErrs), $ok, $err);
    qaBliAssert(strpos($xml, '<clave_actividad>BLI</clave_actividad>') !== false, 'XML incluye clave_actividad BLI', 'XML no incluye clave_actividad BLI', $ok, $err);
    qaBliAssert(strpos($xml, '<tipo_bien_blindado>') !== false, 'XML incluye tipo_bien_blindado', 'XML no incluye tipo_bien_blindado', $ok, $err);
    qaBliAssert(strpos($xml, '<nivel_blindaje>') !== false, 'XML incluye nivel_blindaje', 'XML no incluye nivel_blindaje', $ok, $err);
    qaBliAssert(strpos($xml, '<datos_liquidacion>') !== false, 'XML incluye datos_liquidacion', 'XML no incluye datos_liquidacion', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E BLI ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fracción IX id_vulnerable: {$idFraccion}\n";
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
