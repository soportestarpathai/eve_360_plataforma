<?php
/**
 * E2E CHV (Fracción III)
 * - Prueba registrarOperacionPLD: aviso individual y acumulación con umbral 645 UMA
 * - Prueba catálogos mínimos CHV
 * - Prueba generación XML CHV con datos_cheque/datos_liquidacion repetibles
 * - Ejecuta en transacción y hace rollback (no deja datos QA)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_iii.php';
require_once __DIR__ . '/../config/chv_catalogos.php';
require_once __DIR__ . '/../config/chv_xml_helper.php';

function qaChvAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) {
        $ok[] = $okMsg;
    } else {
        $err[] = $errMsg;
    }
}

function qaChvUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaChvResolveIdFraccion(PDO $pdo): ?int
{
    if (function_exists('getIdVulnerableFraccionIII')) {
        $id = getIdVulnerableFraccionIII($pdo);
        if (!empty($id)) return (int)$id;
    }
    $stmt = $pdo->query("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'III' LIMIT 1");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    return $row ? (int)$row['id_vulnerable'] : null;
}

function qaChvFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
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
            'tipo_operacion' => 'III:CHV:E2E:PROBE',
            'umbral_aviso_uma_override' => pldFraccionIIIUmbralAviso(),
            'umbral_acumulacion_uma_override' => pldFraccionIIIUmbralAviso(),
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
    global $CHV_CATALOGOS;

    qaChvAssert(pldFraccionIIIIdentificacionSiempre(), 'Identificación CHV siempre activa', 'Identificación CHV no marcada como siempre', $ok, $err);
    qaChvAssert(pldFraccionIIIUmbralAviso() === 645.0, 'Umbral CHV 645 UMA', 'Umbral CHV distinto a 645 UMA', $ok, $err);
    qaChvAssert(isset($CHV_CATALOGOS['tipo_operacion']['301'], $CHV_CATALOGOS['tipo_operacion']['303']), 'Catálogo tipo_operacion CHV completo mínimo', 'Falta tipo_operacion 301/303', $ok, $err);
    qaChvAssert(isset($CHV_CATALOGOS['moneda_cheques']['1'], $CHV_CATALOGOS['moneda_cheques']['9']), 'Catálogo moneda_cheques CHV completo mínimo', 'Falta moneda_cheques 1/9', $ok, $err);
    qaChvAssert(isset($CHV_CATALOGOS['instrumento_monetario']['7']), 'Catálogo instrumento incluye cheques de viajero', 'Falta instrumento_monetario 7', $ok, $err);
    qaChvAssert(isset($CHV_CATALOGOS['tipo_alerta']['304'], $CHV_CATALOGOS['tipo_alerta']['9999']), 'Catálogo alertas CHV incluye 304/9999', 'Faltan alertas 304/9999', $ok, $err);

    $xmlPayload = [
        'informe' => [[
            'mes_reportado' => date('Ym'),
            'sujeto_obligado' => [
                'clave_sujeto_obligado' => 'OJA781213J89',
                'clave_actividad' => 'CHV',
            ],
            'aviso' => [[
                'referencia_aviso' => 'CHV' . date('dHis'),
                'prioridad' => '1',
                'alerta' => ['tipo_alerta' => '304'],
                'persona_aviso' => [[
                    'tipo_persona' => [
                        'persona_fisica' => [
                            'nombre' => 'RUTILIO',
                            'apellido_paterno' => 'PEREZ',
                            'apellido_materno' => 'JUAREZ',
                            'fecha_nacimiento' => '19900516',
                            'pais_nacionalidad' => 'DE',
                            'actividad_economica' => '1000000',
                        ],
                    ],
                    'tipo_domicilio' => [
                        'nacional' => [
                            'colonia' => 'GUADALUPE INN',
                            'calle' => 'REVOLUCION',
                            'numero_exterior' => '458',
                            'codigo_postal' => '01020',
                        ],
                    ],
                    'telefono' => ['correo_electronico' => 'QA@EVE360.COM'],
                ]],
                'detalle_operaciones' => [[
                    'datos_operacion' => [[
                        'fecha_operacion' => date('Ymd'),
                        'codigo_postal' => '01020',
                        'tipo_operacion' => '301',
                        'datos_cheque' => [
                            ['numero_cheques' => '40825', 'moneda_cheques' => '4'],
                            ['numero_cheques' => '45', 'moneda_cheques' => '9'],
                        ],
                        'datos_liquidacion' => [
                            ['fecha_pago' => date('Ymd'), 'instrumento_monetario' => '2', 'moneda' => '3', 'monto_operacion' => '15000.00'],
                            ['fecha_pago' => date('Ymd'), 'instrumento_monetario' => '7', 'moneda' => '2', 'monto_operacion' => '154848.00'],
                        ],
                    ]],
                ]],
            ]],
        ]],
    ];

    $gen = generateCHVXml($xmlPayload);
    $xml = (string)($gen['xml'] ?? '');
    $xmlErrs = (array)($gen['errors'] ?? []);
    qaChvAssert($xml !== '', 'XML CHV generado', 'No se generó XML CHV', $ok, $err);
    qaChvAssert(empty($xmlErrs), 'XML CHV sin errores helper', 'XML CHV con errores helper: ' . implode('; ', $xmlErrs), $ok, $err);
    qaChvAssert(strpos($xml, '<clave_actividad>CHV</clave_actividad>') !== false, 'XML incluye clave_actividad CHV', 'XML no incluye clave_actividad CHV', $ok, $err);
    qaChvAssert(substr_count($xml, '<datos_cheque>') === 2, 'XML incluye 2 datos_cheque', 'XML no incluye 2 datos_cheque', $ok, $err);
    qaChvAssert(substr_count($xml, '<datos_liquidacion>') === 2, 'XML incluye 2 datos_liquidacion', 'XML no incluye 2 datos_liquidacion', $ok, $err);
    qaChvAssert(strpos($xml, '<dueno_beneficiario') === false, 'XML omite dueño beneficiario cuando no viene', 'XML generó dueño beneficiario vacío', $ok, $err);
    qaChvAssert(strpos($xml, 'chv.xsd') !== false, 'XML incluye schemaLocation CHV', 'XML no incluye schemaLocation CHV', $ok, $err);

    $idFraccion = qaChvResolveIdFraccion($pdo);
    if (!$idFraccion) throw new RuntimeException('No se encontró Fracción III en cat_vulnerables.');

    $pdo->beginTransaction();
    $candidate = qaChvFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) throw new RuntimeException('No se encontró cliente apto para E2E CHV.');

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaChvUma($pdo);
    $umbralAvisoUma = pldFraccionIIIUmbralAviso();
    $umbralAvisoMxn = $umbralAvisoUma * $uma;
    $today = date('Y-m-d');
    $label = 'III:CHV:E2E:' . date('YmdHis');

    $pdo->beginTransaction();

    $montoA = max(100.0, min(1000.0, $umbralAvisoMxn * 0.01));
    $resA = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoA,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':A:NO_AVISO',
        'umbral_aviso_uma_override' => $umbralAvisoUma,
        'umbral_acumulacion_uma_override' => $umbralAvisoUma,
    ]);
    qaChvAssert(!empty($resA['success']), 'Caso A registra operación', 'Caso A no registró operación', $ok, $err);
    qaChvAssert(empty($resA['requiere_aviso']), 'Caso A sin aviso', 'Caso A generó aviso inesperado', $ok, $err);

    $montoB = $umbralAvisoMxn + max(1000.0, $umbralAvisoMxn * 0.02);
    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoB,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:INDIVIDUAL',
        'umbral_aviso_uma_override' => $umbralAvisoUma,
        'umbral_acumulacion_uma_override' => $umbralAvisoUma,
    ]);
    qaChvAssert(!empty($resB['success']), 'Caso B registra operación', 'Caso B no registró operación', $ok, $err);
    qaChvAssert(!empty($resB['requiere_aviso']), 'Caso B genera aviso individual', 'Caso B no generó aviso individual', $ok, $err);
    qaChvAssert(!empty($resB['id_aviso']), 'Caso B genera id_aviso', 'Caso B sin id_aviso', $ok, $err);

    $tipoAcum = $label . ':C:ACUM';
    $base = max(100.0, floor(($umbralAvisoMxn / 5.0) / 100.0) * 100.0);
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
            'tipo_operacion' => $tipoAcum,
            'umbral_aviso_uma_override' => $umbralAvisoUma,
            'umbral_acumulacion_uma_override' => $umbralAvisoUma,
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
    qaChvAssert($triggered, 'Caso C dispara acumulación', 'Caso C no disparó acumulación', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E CHV ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fracción III id_vulnerable: {$idFraccion}\n";
    echo "UMA: {$uma}\n";
    echo "Umbral aviso/acumulación: {$umbralAvisoUma} UMA ({$umbralAvisoMxn} MXN)\n";
    echo "---\n";
    foreach ($logs as $line) echo "[LOG] {$line}\n";
    foreach ($acumLogs as $line) echo "{$line}\n";
    echo "---\n";
    echo "OK: " . count($ok) . "\n";
    foreach ($ok as $line) echo "[OK] {$line}\n";
    echo "ERR: " . count($err) . "\n";
    foreach ($err as $line) echo "[ERR] {$line}\n";
    exit(empty($err) ? 0 : 1);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "=== E2E CHV ===\n";
    echo "[FATAL] " . $e->getMessage() . "\n";
    if (!empty($logs)) {
        echo "--- LOGS ---\n";
        foreach ($logs as $line) echo "[LOG] {$line}\n";
    }
    exit(1);
}
