<?php
/**
 * E2E AVI (Fracción XVI)
 * - Prueba flujo real de registrarOperacionPLD: transacciones, avisos y acumulación
 * - Prueba generación XML AVI
 * - Ejecuta en transacción y hace ROLLBACK (no deja datos QA)
 *
 * Uso:
 *   php tools/qa_avi_e2e.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_xvi.php';
require_once __DIR__ . '/../config/avi_xml_helper.php';

function qaFindCandidateClient(PDO $pdo, int $idFraccion, string $label, array &$logs): ?array
{
    $rows = $pdo->query("
        SELECT id_cliente, no_contrato, expediente_completo, negativa_identificacion_pld, id_status
        FROM clientes
        WHERE COALESCE(id_status, 1) <> 4
        ORDER BY COALESCE(expediente_completo,0) DESC, id_cliente DESC
        LIMIT 120
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        $logs[] = 'No hay clientes candidatos activos.';
        return null;
    }

    $today = date('Y-m-d');
    foreach ($rows as $c) {
        $res = registrarOperacionPLD($pdo, [
            'id_cliente' => (int)$c['id_cliente'],
            'monto' => 10.00,
            'fecha_operacion' => $today,
            'id_fraccion' => $idFraccion,
            'tipo_operacion' => $label . ':PROBE:' . (int)$c['id_cliente']
        ]);
        if (!empty($res['success'])) {
            $logs[] = 'Cliente candidato OK: id=' . (int)$c['id_cliente'] . ' contrato=' . ($c['no_contrato'] ?? '');
            return $c;
        }
        $logs[] = 'Cliente id=' . (int)$c['id_cliente'] . ' bloqueado: ' . ($res['message'] ?? 'sin mensaje');
    }
    return null;
}

function qaGetUmaValue(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaGetThresholdUma(PDO $pdo, int $idFraccion, string $column, float $defaultUma): float
{
    $stmt = $pdo->prepare("SELECT {$column} AS val FROM cat_vulnerables WHERE id_vulnerable = ? LIMIT 1");
    $stmt->execute([$idFraccion]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $raw = $row['val'] ?? null;
    if ($raw !== null && $raw !== '') {
        $v = (float)$raw;
        return $v > 0 ? $v : $defaultUma;
    }

    $stmt2 = $pdo->query("SELECT {$column} AS val FROM config_empresa WHERE id_config = 1");
    $row2 = $stmt2 ? $stmt2->fetch(PDO::FETCH_ASSOC) : null;
    $raw2 = $row2['val'] ?? null;
    if ($raw2 !== null && $raw2 !== '') {
        $v = (float)$raw2;
        return $v > 0 ? $v : $defaultUma;
    }
    return $defaultUma;
}

function qaAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
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
$label = 'AVI:E2E:' . date('YmdHis');

try {
    $idFraccion = getIdVulnerableFraccionXVI($pdo);
    if (!$idFraccion) {
        throw new RuntimeException('No se encontró fracción XVI en cat_vulnerables.');
    }

    // 1) Buscar cliente utilizable sin contaminar datos
    $pdo->beginTransaction();
    $candidate = qaFindCandidateClient($pdo, (int)$idFraccion, $label, $logs);
    $pdo->rollBack();

    if (!$candidate) {
        throw new RuntimeException('No se encontró cliente apto para prueba E2E. Revisar expediente/KYC.');
    }

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaGetUmaValue($pdo);

    $umbralIndividualUma = qaGetThresholdUma($pdo, (int)$idFraccion, 'umbral_aviso_uma', 1000.0);
    $umbralAcumUma = qaGetThresholdUma($pdo, (int)$idFraccion, 'umbral_acumulacion_uma', 1000.0);
    $umbralIndividualMxn = $umbralIndividualUma * $uma;
    $umbralAcumMxn = $umbralAcumUma * $uma;

    $xviUmbralMxn = pldFraccionXVIUmbralAvisoOperacion() * $uma;
    $xviContrapMxn = pldFraccionXVIUmbralAvisoContraprestacion() * $uma;

    // 2) E2E real en una sola transacción (rollback al final)
    $pdo->beginTransaction();
    $today = date('Y-m-d');

    // Caso A: transacción regular (sin aviso)
    $montoA = max(100.0, min(1000.0, $umbralIndividualMxn * 0.01));
    $resA = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoA,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':A:NO_AVISO'
    ]);
    qaAssert(!empty($resA['success']), 'Caso A registra transacción', 'Caso A no registró transacción', $ok, $err);
    qaAssert(empty($resA['requiere_aviso']), 'Caso A no requiere aviso', 'Caso A marcó aviso inesperado', $ok, $err);

    // Caso B: aviso por umbral XVI (>=210 UMA) usando forzado API
    $montoB = max($xviUmbralMxn + 1000.0, 5000.0);
    $evalB = pldFraccionXVIEvaluaUmbralAviso($uma, $montoB, 0.0);
    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoB,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:UMBRAL_210',
        'requiere_aviso_forzado' => !empty($evalB['requiere_aviso']),
        'tipo_aviso_forzado' => 'umbral_individual',
        'fecha_deadline_forzado' => calcularDeadlineAviso($today)
    ]);
    qaAssert(!empty($resB['success']), 'Caso B registra transacción', 'Caso B no registró transacción', $ok, $err);
    qaAssert(!empty($resB['requiere_aviso']), 'Caso B genera aviso', 'Caso B no generó aviso', $ok, $err);
    qaAssert(!empty($resB['id_aviso']), 'Caso B crea id_aviso', 'Caso B sin id_aviso', $ok, $err);

    // Caso C: aviso por contraprestación XVI (>=4 UMA)
    $montoC = 100.0;
    $contrapC = $xviContrapMxn + 50.0;
    $evalC = pldFraccionXVIEvaluaUmbralAviso($uma, $montoC, $contrapC);
    $montoRegistroC = max($montoC, $contrapC);
    $resC = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoRegistroC,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':C:CONTRAP_4',
        'requiere_aviso_forzado' => !empty($evalC['requiere_aviso']),
        'tipo_aviso_forzado' => 'umbral_individual',
        'fecha_deadline_forzado' => calcularDeadlineAviso($today)
    ]);
    qaAssert(!empty($resC['success']), 'Caso C registra transacción', 'Caso C no registró transacción', $ok, $err);
    qaAssert(!empty($resC['requiere_aviso']), 'Caso C genera aviso por contraprestación', 'Caso C no generó aviso por contraprestación', $ok, $err);

    // Caso D: acumulación (mismo tipo_operacion, sin forzado)
    $tipoAcum = $label . ':D:ACUM';
    $base = max(100.0, floor(($umbralAcumMxn / 4.0) / 100.0) * 100.0);
    if ($base >= $umbralIndividualMxn) {
        $base = max(100.0, floor(($umbralIndividualMxn / 2.0) / 100.0) * 100.0);
    }
    $triggeredAcum = false;
    $acumSteps = [];
    for ($i = 0; $i < 6; $i++) {
        $montoD = $base + ($i * 37.0); // evita duplicados exactos
        $resD = registrarOperacionPLD($pdo, [
            'id_cliente' => $idCliente,
            'monto' => $montoD,
            'fecha_operacion' => $today,
            'id_fraccion' => (int)$idFraccion,
            'tipo_operacion' => $tipoAcum
        ]);
        $acumSteps[] = [
            'step' => $i + 1,
            'monto' => $montoD,
            'success' => !empty($resD['success']),
            'requiere_aviso' => !empty($resD['requiere_aviso']),
            'tipo_aviso' => $resD['tipo_aviso'] ?? null
        ];
        if (!empty($resD['success']) && ($resD['tipo_aviso'] ?? '') === 'acumulacion') {
            $triggeredAcum = true;
            break;
        }
    }
    qaAssert($triggeredAcum, 'Caso D dispara aviso por acumulación', 'Caso D no disparó acumulación en 6 intentos', $ok, $err);

    // XML: estructura básica AVI
    $xmlPayload = [
        'informe' => [[
            'mes_reportado' => date('Ym'),
            'sujeto_obligado' => [
                'clave_sujeto_obligado' => 'ABC010101AB1',
                'clave_actividad' => 'AVI',
                'dominio_plataforma' => 'EVE-360'
            ],
            'aviso' => [[
                'referencia_aviso' => 'E2EAVI001',
                'prioridad' => 1,
                'alerta' => ['tipo_alerta' => 100],
                'operaciones_persona' => [
                    'persona_aviso' => [
                        'datos_cuenta_plataforma' => [
                            'id_usuario' => 'USER001',
                            'cuenta_relacionada' => '123456789',
                            'moneda_cuenta' => 1
                        ],
                        'tipo_persona' => [
                            'persona_fisica' => [
                                'nombre' => 'JUAN',
                                'apellido_paterno' => 'PEREZ',
                                'apellido_materno' => 'LOPEZ',
                                'pais_nacionalidad' => 'MX',
                                'actividad_economica' => '4330100',
                                'documento_identificacion' => [
                                    'tipo_identificacion' => 1,
                                    'numero_identificacion' => 'INE123'
                                ]
                            ]
                        ]
                    ],
                    'detalle_operaciones' => [
                        'operaciones_compra' => [
                            'compra' => [[
                                'fecha_hora_operacion' => date('Ymd') . '101010',
                                'moneda_operacion' => 1,
                                'monto_operacion' => '1000.00',
                                'activo_virtual' => [
                                    'activo_virtual_operado' => 1001,
                                    'tipo_cambio_mn' => '500000.00',
                                    'cantidad_activo_virtual' => '0.0020000000'
                                ],
                                'hash_operacion' => 'ABC123XYZ'
                            ]]
                        ]
                    ]
                ]
            ]]
        ]]
    ];
    $xsdPath = null;
    foreach ([__DIR__ . '/../xsd/avi.xsd', __DIR__ . '/../avi.xsd'] as $candidateXsd) {
        if (is_file($candidateXsd)) {
            $xsdPath = $candidateXsd;
            break;
        }
    }
    $xmlResult = generateAVIXml($xmlPayload, $xsdPath);
    $xml = $xmlResult['xml'] ?? '';
    $xmlErrors = $xmlResult['errors'] ?? [];
    qaAssert(!empty($xml), 'XML AVI se genera en E2E', 'XML AVI no se generó en E2E', $ok, $err);
    if (!empty($xmlErrors)) {
        $err[] = 'Validación XSD en E2E: ' . implode(' | ', $xmlErrors);
    } else {
        $ok[] = 'XML AVI pasa validación XSD disponible';
    }

    $pdo->rollBack();

    echo "=== E2E AVI ===\n";
    echo "Cliente usado: {$idCliente}\n";
    echo "Fracción XVI id_vulnerable: {$idFraccion}\n";
    echo "UMA: {$uma}\n";
    echo "Umbral XVI operación (210 UMA): {$xviUmbralMxn}\n";
    echo "Umbral XVI contraprestación (4 UMA): {$xviContrapMxn}\n";
    echo "Umbral individual general usado por motor: {$umbralIndividualMxn}\n";
    echo "Umbral acumulación usado por motor: {$umbralAcumMxn}\n";
    echo "---\n";
    foreach ($logs as $line) {
        echo "[LOG] {$line}\n";
    }
    foreach ($acumSteps as $s) {
        echo "[ACUM] step={$s['step']} monto={$s['monto']} success=" . ($s['success'] ? '1' : '0') . " requiere_aviso=" . ($s['requiere_aviso'] ? '1' : '0') . " tipo=" . ($s['tipo_aviso'] ?? '-') . "\n";
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

