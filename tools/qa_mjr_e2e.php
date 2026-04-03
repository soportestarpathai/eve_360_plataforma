<?php
/**
 * E2E MJR (Fracción VI)
 * - Prueba registrarOperacionPLD: sin aviso, aviso individual y acumulación
 * - Prueba generación XML MJR
 * - Ejecuta en transacción y hace rollback (no deja datos QA)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_vi.php';
require_once __DIR__ . '/../config/mjr_xml_helper.php';

function qaMjrAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) {
        $ok[] = $okMsg;
    } else {
        $err[] = $errMsg;
    }
}

function qaMjrUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaMjrResolveIdFraccion(PDO $pdo): ?int
{
    $hasIdStatus = (int)$pdo->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cat_vulnerables'
          AND COLUMN_NAME = 'id_status'
    ")->fetchColumn() > 0;

    if (function_exists('getIdVulnerableFraccionVI')) {
        try {
            $id = getIdVulnerableFraccionVI($pdo);
            if (!empty($id)) return (int)$id;
        } catch (Throwable $e) {
            // fallback abajo
        }
    }

    if ($hasIdStatus) {
        $stmt = $pdo->query("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'VI' AND (id_status = 1 OR id_status IS NULL) LIMIT 1");
    } else {
        $stmt = $pdo->query("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'VI' LIMIT 1");
    }
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return $row ? (int)$row['id_vulnerable'] : null;
}

function qaMjrThresholdUma(PDO $pdo, int $idFraccion, string $column, float $fallback): float
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

function qaMjrFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
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
            'tipo_operacion' => 'VI:MJR:E2E:PROBE',
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
    $idFraccion = qaMjrResolveIdFraccion($pdo);
    if (!$idFraccion) {
        throw new RuntimeException('No se encontró Fracción VI en cat_vulnerables.');
    }

    // Buscar cliente apto sin persistir
    $pdo->beginTransaction();
    $candidate = qaMjrFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) {
        throw new RuntimeException('No se encontró cliente apto para E2E MJR.');
    }

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaMjrUma($pdo);
    $umbralAvisoUma = qaMjrThresholdUma($pdo, (int)$idFraccion, 'umbral_aviso_uma', 1605.0);
    $umbralAcumUma = qaMjrThresholdUma($pdo, (int)$idFraccion, 'umbral_acumulacion_uma', 1605.0);
    $umbralAvisoMxn = $umbralAvisoUma * $uma;
    $umbralAcumMxn = $umbralAcumUma * $uma;

    $today = date('Y-m-d');
    $label = 'VI:MJR:E2E:' . date('YmdHis');

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
    qaMjrAssert(!empty($resA['success']), 'Caso A registra operación', 'Caso A no registró operación', $ok, $err);
    qaMjrAssert(empty($resA['requiere_aviso']), 'Caso A sin aviso', 'Caso A generó aviso inesperado', $ok, $err);

    // Caso B: aviso individual
    $montoB = $umbralAvisoMxn + max(1000.0, $umbralAvisoMxn * 0.02);
    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoB,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:INDIVIDUAL'
    ]);
    qaMjrAssert(!empty($resB['success']), 'Caso B registra operación', 'Caso B no registró operación', $ok, $err);
    qaMjrAssert(!empty($resB['requiere_aviso']), 'Caso B genera aviso individual', 'Caso B no generó aviso individual', $ok, $err);
    qaMjrAssert(!empty($resB['id_aviso']), 'Caso B genera id_aviso', 'Caso B sin id_aviso', $ok, $err);

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
    qaMjrAssert($triggered, 'Caso C dispara acumulación', 'Caso C no disparó acumulación', $ok, $err);

    // XML MJR (apegado a estructura esperada)
    $xmlPayload = [
        'informe' => [[
            'mes_reportado' => date('Ym'),
            'sujeto_obligado' => [
                'clave_sujeto_obligado' => 'OCA881212G56',
                'clave_actividad' => 'MJR'
            ],
            'aviso' => [[
                'referencia_aviso' => 'REF' . date('dHis'),
                'prioridad' => '1',
                'alerta' => ['tipo_alerta' => '602'],
                'persona_aviso' => [[
                    'tipo_persona' => [
                        'persona_fisica' => [
                            'nombre' => 'JUAN',
                            'apellido_paterno' => 'PEREZ',
                            'apellido_materno' => 'GOMEZ',
                            'fecha_nacimiento' => '19950515',
                            'pais_nacionalidad' => 'RO',
                            'actividad_economica' => '4640000'
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
                'dueno_beneficiario' => [[
                    'tipo_persona' => [
                        'persona_moral' => [
                            'denominacion_razon' => 'LA MORALISTA',
                            'fecha_constitucion' => '19820215',
                            'pais_nacionalidad' => 'ES'
                        ]
                    ]
                ]],
                'detalle_operaciones' => [[
                    'datos_operacion' => [[
                        'fecha_operacion' => date('Ymd'),
                        'codigo_postal' => '01030',
                        'tipo_operacion' => '601',
                        'datos_bien' => [
                            ['tipo_bien' => '1', 'unidad_comercializada' => '2', 'cantidad_comercializada' => '756.00'],
                            ['tipo_bien' => '6', 'unidad_comercializada' => '1', 'cantidad_comercializada' => '2.00']
                        ],
                        'datos_liquidacion' => [
                            ['fecha_pago' => date('Ymd'), 'forma_pago' => '1', 'instrumento_monetario' => '1', 'moneda' => '2', 'monto_operacion' => '1547896.59'],
                            ['fecha_pago' => date('Ymd'), 'forma_pago' => '1', 'instrumento_monetario' => '1', 'moneda' => '1', 'monto_operacion' => '500.00']
                        ]
                    ]]
                ]]
            ]]
        ]]
    ];

    $gen = generateMJRXml($xmlPayload);
    $xml = (string)($gen['xml'] ?? '');
    $xmlErrs = (array)($gen['errors'] ?? []);
    qaMjrAssert($xml !== '', 'XML MJR generado', 'No se generó XML MJR', $ok, $err);
    qaMjrAssert(empty($xmlErrs), 'XML MJR sin errores helper', 'XML MJR con errores helper: ' . implode('; ', $xmlErrs), $ok, $err);
    qaMjrAssert(strpos($xml, '<clave_actividad>MJR</clave_actividad>') !== false, 'XML incluye clave_actividad MJR', 'XML no incluye clave_actividad MJR', $ok, $err);
    qaMjrAssert(strpos($xml, '<datos_bien>') !== false, 'XML incluye datos_bien', 'XML no incluye datos_bien', $ok, $err);
    qaMjrAssert(strpos($xml, '<datos_liquidacion>') !== false, 'XML incluye datos_liquidacion', 'XML no incluye datos_liquidacion', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E MJR ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fracción VI id_vulnerable: {$idFraccion}\n";
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

