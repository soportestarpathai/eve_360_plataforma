<?php
/**
 * E2E OBA (Fracción VII)
 * - Prueba registrarOperacionPLD: sin aviso, aviso individual y acumulación
 * - Prueba generación XML OBA con datos_objeto/datos_liquidacion repetibles
 * - Ejecuta en transacción y hace rollback (no deja datos QA)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_vii.php';
require_once __DIR__ . '/../config/oba_catalogos.php';
require_once __DIR__ . '/../config/oba_xml_helper.php';

function qaObaAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) $ok[] = $okMsg; else $err[] = $errMsg;
}

function qaObaUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaObaFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
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
            'tipo_operacion' => 'VII:OBA:E2E:PROBE',
            'umbral_identificacion_uma_override' => pldFraccionVIIUmbralIdentificacion(),
            'umbral_aviso_uma_override' => pldFraccionVIIUmbralAviso(),
            'umbral_acumulacion_uma_override' => pldFraccionVIIUmbralAviso(),
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
    global $OBA_CATALOGOS;

    qaObaAssert(pldFraccionVIIUmbralIdentificacion() === 2410.0, 'Umbral identificación OBA 2410 UMA', 'Umbral identificación OBA incorrecto', $ok, $err);
    qaObaAssert(pldFraccionVIIUmbralAviso() === 4815.0, 'Umbral aviso OBA 4815 UMA', 'Umbral aviso OBA incorrecto', $ok, $err);
    qaObaAssert(isset($OBA_CATALOGOS['tipo_operacion']['701'], $OBA_CATALOGOS['tipo_operacion']['702']), 'Catálogo tipo_operacion OBA mínimo', 'Falta tipo_operacion OBA 701/702', $ok, $err);
    qaObaAssert(isset($OBA_CATALOGOS['tipo_objeto']['1'], $OBA_CATALOGOS['tipo_objeto']['99']), 'Catálogo tipo_objeto OBA oficial mínimo', 'Falta tipo_objeto OBA 1/99', $ok, $err);
    qaObaAssert(isset($OBA_CATALOGOS['forma_pago']['1'], $OBA_CATALOGOS['forma_pago']['5']), 'Catálogo forma_pago OBA mínimo', 'Falta forma_pago OBA 1/5', $ok, $err);
    qaObaAssert(isset($OBA_CATALOGOS['tipo_alerta']['3301'], $OBA_CATALOGOS['tipo_alerta']['3319'], $OBA_CATALOGOS['tipo_alerta']['9999']), 'Catálogo tipo_alerta OBA oficial mínimo', 'Falta tipo_alerta OBA 3301/3319/9999', $ok, $err);

    $xmlPayload = ['informe' => [[
        'mes_reportado' => date('Ym'),
        'sujeto_obligado' => [
            'clave_sujeto_obligado' => 'OJA781213J89',
            'clave_actividad' => 'OBA',
        ],
        'aviso' => [[
            'referencia_aviso' => 'OBA' . date('dHis'),
            'prioridad' => '1',
            'alerta' => ['tipo_alerta' => '3303'],
            'persona_aviso' => [[
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'RUTILIO',
                    'apellido_paterno' => 'PEREZ',
                    'apellido_materno' => 'JUAREZ',
                    'fecha_nacimiento' => '19900516',
                    'pais_nacionalidad' => 'MX',
                    'actividad_economica' => '1000000',
                ]],
                'tipo_domicilio' => ['nacional' => [
                    'colonia' => 'GUADALUPE INN',
                    'calle' => 'REVOLUCION',
                    'numero_exterior' => '458',
                    'codigo_postal' => '01020',
                ]],
                'telefono' => ['correo_electronico' => 'QA@EVE360.COM'],
            ]],
            'dueno_beneficiario' => [[
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'JUANITA',
                    'apellido_paterno' => 'BANANAN',
                    'apellido_materno' => 'URRUTIA',
                    'fecha_nacimiento' => '19900816',
                    'pais_nacionalidad' => 'SE',
                ]],
            ]],
            'detalle_operaciones' => [[
                'datos_operacion' => [[
                    'fecha_operacion' => date('Ymd'),
                    'codigo_postal' => '01020',
                    'tipo_operacion' => '702',
                    'datos_objeto' => [
                        ['tipo_objeto' => '1', 'descripcion' => 'PINTURA AL OLEO', 'numero_registro' => 'REG-001', 'valor_referencia' => '450000.00'],
                        ['tipo_objeto' => '2', 'descripcion' => 'ESCULTURA EN BRONCE', 'valor_referencia' => '125000.00'],
                        ['tipo_objeto' => '5', 'descripcion' => 'FOTOGRAFIA NATURAL', 'valor_referencia' => '100.00'],
                        ['tipo_objeto' => '8', 'descripcion' => 'VOCHITO 1RA EDICION ALEMAN', 'numero_registro' => '654653443'],
                    ],
                    'datos_liquidacion' => [
                        ['fecha_pago' => date('Ymd'), 'forma_pago' => '1', 'instrumento_monetario' => '8', 'moneda' => '1', 'monto_operacion' => '450000.00'],
                        ['fecha_pago' => date('Ymd'), 'forma_pago' => '2', 'moneda' => '1', 'monto_operacion' => '125000.00'],
                    ],
                ]],
            ]],
        ]],
    ]]];

    $gen = generateOBAXml($xmlPayload);
    $xml = (string)($gen['xml'] ?? '');
    $xmlErrs = (array)($gen['errors'] ?? []);
    qaObaAssert($xml !== '', 'XML OBA generado', 'No se generó XML OBA', $ok, $err);
    qaObaAssert(empty($xmlErrs), 'XML OBA sin errores helper', 'XML OBA con errores helper: ' . implode('; ', $xmlErrs), $ok, $err);
    qaObaAssert(strpos($xml, '<clave_actividad>OBA</clave_actividad>') !== false, 'XML incluye clave_actividad OBA', 'XML no incluye clave_actividad OBA', $ok, $err);
    qaObaAssert(substr_count($xml, '<datos_objeto>') === 4, 'XML incluye 4 datos_objeto', 'XML no incluye 4 datos_objeto', $ok, $err);
    qaObaAssert(substr_count($xml, '<datos_liquidacion>') === 2, 'XML incluye 2 datos_liquidacion', 'XML no incluye 2 datos_liquidacion', $ok, $err);
    qaObaAssert(strpos($xml, '<numero_registro>REG-001</numero_registro>') !== false, 'XML incluye numero_registro opcional', 'XML no incluye numero_registro opcional', $ok, $err);
    qaObaAssert(strpos($xml, '<dueno_beneficiario>') !== false, 'XML incluye dueño beneficiario opcional cuando viene', 'XML no incluye dueño beneficiario opcional', $ok, $err);
    qaObaAssert(strpos($xml, '<dueno_beneficiario>') < strpos($xml, '<detalle_operaciones>'), 'XML ordena dueño beneficiario antes de detalle', 'XML no ordena dueño beneficiario antes de detalle', $ok, $err);

    $idFraccion = getIdVulnerableFraccionVII($pdo);
    if (!$idFraccion) throw new RuntimeException('No se encontró Fracción VII en cat_vulnerables.');

    $pdo->beginTransaction();
    $candidate = qaObaFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) throw new RuntimeException('No se encontró cliente apto para E2E OBA.');

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaObaUma($pdo);
    $umbralAvisoUma = pldFraccionVIIUmbralAviso();
    $umbralAvisoMxn = $umbralAvisoUma * $uma;
    $today = date('Y-m-d');
    $label = 'VII:OBA:E2E:' . date('YmdHis');

    $pdo->beginTransaction();

    $montoA = max(100.0, min(1000.0, $umbralAvisoMxn * 0.01));
    $resA = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoA,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':A:NO_AVISO',
        'umbral_identificacion_uma_override' => pldFraccionVIIUmbralIdentificacion(),
        'umbral_aviso_uma_override' => $umbralAvisoUma,
        'umbral_acumulacion_uma_override' => $umbralAvisoUma,
    ]);
    qaObaAssert(!empty($resA['success']), 'Caso A registra operación', 'Caso A no registró operación', $ok, $err);
    qaObaAssert(empty($resA['requiere_aviso']), 'Caso A sin aviso', 'Caso A generó aviso inesperado', $ok, $err);

    $montoB = $umbralAvisoMxn + max(1000.0, $umbralAvisoMxn * 0.02);
    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $montoB,
        'fecha_operacion' => $today,
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:INDIVIDUAL',
        'umbral_identificacion_uma_override' => pldFraccionVIIUmbralIdentificacion(),
        'umbral_aviso_uma_override' => $umbralAvisoUma,
        'umbral_acumulacion_uma_override' => $umbralAvisoUma,
    ]);
    qaObaAssert(!empty($resB['success']), 'Caso B registra operación', 'Caso B no registró operación', $ok, $err);
    qaObaAssert(!empty($resB['requiere_aviso']), 'Caso B genera aviso individual', 'Caso B no generó aviso individual', $ok, $err);
    qaObaAssert(!empty($resB['id_aviso']), 'Caso B genera id_aviso', 'Caso B sin id_aviso', $ok, $err);

    $tipoAcum = $label . ':C:ACUM';
    $base = max(100.0, floor(($umbralAvisoMxn / 5.0) / 100.0) * 100.0);
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
            'umbral_identificacion_uma_override' => pldFraccionVIIUmbralIdentificacion(),
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
    qaObaAssert($triggered, 'Caso C dispara acumulación', 'Caso C no disparó acumulación', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E OBA ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fracción VII id_vulnerable: {$idFraccion}\n";
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
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    echo "=== E2E OBA ===\n";
    echo "[FATAL] " . $e->getMessage() . "\n";
    if (!empty($logs)) {
        echo "--- LOGS ---\n";
        foreach ($logs as $line) echo "[LOG] {$line}\n";
    }
    exit(1);
}
