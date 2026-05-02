<?php
/**
 * E2E INM (Fracción V Bis)
 * - Prueba umbral de aviso/acumulación 8,025 UMA
 * - Prueba generación XML INM independiente de DIN
 * - Ejecuta registros en transacción y rollback
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_v_bis.php';
require_once __DIR__ . '/../config/inm_catalogos.php';
require_once __DIR__ . '/../config/inm_xml_helper.php';

function qaInmAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) $ok[] = $okMsg; else $err[] = $errMsg;
}

function qaInmUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaInmFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
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

    foreach ($rows as $c) {
        $probe = registrarOperacionPLD($pdo, [
            'id_cliente' => (int)$c['id_cliente'],
            'monto' => 10.00,
            'fecha_operacion' => date('Y-m-d'),
            'id_fraccion' => $idFraccion,
            'tipo_operacion' => 'VBIS:INM:E2E:PROBE',
            'umbral_aviso_uma_override' => getUmbralAvisoVBis(),
            'umbral_acumulacion_uma_override' => getUmbralAvisoVBis(),
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
    global $INM_CATALOGOS;

    qaInmAssert(requiereExpedienteVBis() === true, 'Identificación V Bis siempre obligatoria', 'V Bis no marca identificación siempre', $ok, $err);
    qaInmAssert(getUmbralAvisoVBis() === 8025.0, 'Umbral aviso INM 8025 UMA', 'Umbral aviso INM incorrecto', $ok, $err);
    qaInmAssert(isset($INM_CATALOGOS['clave_actividad']['INM']), 'Catálogo clave_actividad INM', 'Falta clave_actividad INM', $ok, $err);
    qaInmAssert(isset($INM_CATALOGOS['tipo_operacion']['501']), 'Catálogo tipo_operacion INM mínimo', 'Falta tipo_operacion INM 501', $ok, $err);
    qaInmAssert(isset($INM_CATALOGOS['figura_cliente']['1'], $INM_CATALOGOS['figura_so']['1']), 'Catálogos figura cliente/SO mínimos', 'Faltan catálogos figura cliente/SO', $ok, $err);
    qaInmAssert(isset($INM_CATALOGOS['tipo_inmueble']['1'], $INM_CATALOGOS['tipo_inmueble']['18'], $INM_CATALOGOS['tipo_inmueble']['99']), 'Catálogo tipo_inmueble INM oficial mínimo', 'Falta tipo_inmueble INM 1/18/99', $ok, $err);
    qaInmAssert(isset($INM_CATALOGOS['tipo_alerta']['100'], $INM_CATALOGOS['tipo_alerta']['3101'], $INM_CATALOGOS['tipo_alerta']['3120'], $INM_CATALOGOS['tipo_alerta']['9999']), 'Catálogo tipo_alerta INM oficial mínimo', 'Falta tipo_alerta INM 100/3101/3120/9999', $ok, $err);
    qaInmAssert(isset($INM_CATALOGOS['entidad_federativa']['1'], $INM_CATALOGOS['entidad_federativa']['32']), 'Catálogo entidad_federativa INM oficial mínimo', 'Falta entidad_federativa INM 1/32', $ok, $err);
    qaInmAssert(isset($INM_CATALOGOS['forma_pago']['1'], $INM_CATALOGOS['forma_pago']['5']), 'Catálogo forma_pago INM oficial mínimo', 'Falta forma_pago INM 1/5', $ok, $err);
    qaInmAssert(isset($INM_CATALOGOS['instrumento_monetario']['1'], $INM_CATALOGOS['instrumento_monetario']['16'], $INM_CATALOGOS['instrumento_monetario']['99']), 'Catálogo instrumento_monetario INM oficial mínimo', 'Falta instrumento_monetario INM 1/16/99', $ok, $err);
    qaInmAssert(count($INM_CATALOGOS['moneda'] ?? []) === 184 && isset($INM_CATALOGOS['moneda']['1'], $INM_CATALOGOS['moneda']['184']), 'Catálogo moneda INM oficial 184 claves', 'Catálogo moneda INM incompleto', $ok, $err);

    $xmlPayload = ['informe' => [[
        'mes_reportado' => date('Ym'),
        'sujeto_obligado' => [
            'clave_sujeto_obligado' => 'OJA781213J89',
            'clave_actividad' => 'INM',
        ],
        'aviso' => [[
            'referencia_aviso' => 'INM' . date('dHis'),
            'prioridad' => '1',
            'alerta' => ['tipo_alerta' => '100'],
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
            ]],
            'dueno_beneficiario' => [[
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'JUANITA',
                    'apellido_paterno' => 'PEREZ',
                    'apellido_materno' => 'LOPEZ',
                    'pais_nacionalidad' => 'MX',
                ]],
            ]],
            'detalle_operaciones' => [[
                'datos_operacion' => [[
                    'fecha_operacion' => date('Ymd'),
                    'tipo_operacion' => '501',
                    'figura_cliente' => '1',
                    'figura_so' => '1',
                    'caracteristicas_inmueble' => [[
                        'tipo_inmueble' => '1',
                        'valor_pactado' => '1000000.00',
                        'colonia' => 'GUADALUPE INN',
                        'calle' => 'REVOLUCION',
                        'numero_exterior' => '458',
                        'codigo_postal' => '01020',
                        'dimension_terreno' => '120.00',
                        'dimension_construido' => '90.00',
                        'folio_real' => 'FR-001',
                    ]],
                    'contrato_instrumento_publico' => ['datos_contrato' => ['fecha_contrato' => date('Ymd')]],
                    'datos_liquidacion' => [[
                        'fecha_pago' => date('Ymd'),
                        'forma_pago' => '1',
                        'instrumento_monetario' => '8',
                        'moneda' => '1',
                        'monto_operacion' => '1000000.00',
                    ]],
                ]],
            ]],
        ]],
    ]]];

    $gen = generateINMXml($xmlPayload);
    $xml = (string)($gen['xml'] ?? '');
    qaInmAssert($xml !== '', 'XML INM generado', 'No se generó XML INM', $ok, $err);
    qaInmAssert(strpos($xml, 'http://www.uif.shcp.gob.mx/recepcion/inm') !== false, 'XML usa namespace INM', 'XML no usa namespace INM', $ok, $err);
    qaInmAssert(strpos($xml, '<clave_actividad>INM</clave_actividad>') !== false, 'XML incluye clave_actividad INM', 'XML no incluye clave_actividad INM', $ok, $err);
    qaInmAssert(strpos($xml, '<figura_cliente>1</figura_cliente>') !== false, 'XML incluye figura_cliente', 'XML no incluye figura_cliente', $ok, $err);
    qaInmAssert(strpos($xml, '<caracteristicas_inmueble>') !== false, 'XML incluye caracteristicas_inmueble', 'XML no incluye caracteristicas_inmueble', $ok, $err);
    qaInmAssert(strpos($xml, '<datos_contrato>') !== false, 'XML incluye datos_contrato', 'XML no incluye datos_contrato', $ok, $err);
    qaInmAssert(strpos($xml, '<dueno_beneficiario>') !== false, 'XML incluye dueño beneficiario opcional', 'XML no incluye dueño beneficiario opcional', $ok, $err);
    qaInmAssert(strpos($xml, '<dueno_beneficiario>') < strpos($xml, '<detalle_operaciones>'), 'XML ordena dueño beneficiario antes de detalle', 'XML no ordena dueño beneficiario antes de detalle', $ok, $err);

    $idFraccion = getIdVulnerableFraccionVBis($pdo);
    if (!$idFraccion) throw new RuntimeException('No se encontró Fracción V Bis en cat_vulnerables.');

    $pdo->beginTransaction();
    $candidate = qaInmFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) throw new RuntimeException('No se encontró cliente apto para E2E INM.');

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaInmUma($pdo);
    $umbralAvisoUma = getUmbralAvisoVBis();
    $umbralAvisoMxn = $umbralAvisoUma * $uma;
    $label = 'VBIS:INM:E2E:' . date('YmdHis');

    $pdo->beginTransaction();

    $resA = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => max(100.0, $umbralAvisoMxn * 0.01),
        'fecha_operacion' => date('Y-m-d'),
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':A:NO_AVISO',
        'umbral_aviso_uma_override' => $umbralAvisoUma,
        'umbral_acumulacion_uma_override' => $umbralAvisoUma,
    ]);
    qaInmAssert(!empty($resA['success']), 'Caso A registra operación', 'Caso A no registró operación', $ok, $err);
    qaInmAssert(empty($resA['requiere_aviso']), 'Caso A sin aviso', 'Caso A generó aviso inesperado', $ok, $err);

    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $umbralAvisoMxn + max(1000.0, $umbralAvisoMxn * 0.02),
        'fecha_operacion' => date('Y-m-d'),
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:INDIVIDUAL',
        'umbral_aviso_uma_override' => $umbralAvisoUma,
        'umbral_acumulacion_uma_override' => $umbralAvisoUma,
    ]);
    qaInmAssert(!empty($resB['success']), 'Caso B registra operación', 'Caso B no registró operación', $ok, $err);
    qaInmAssert(!empty($resB['requiere_aviso']), 'Caso B genera aviso individual', 'Caso B no generó aviso individual', $ok, $err);

    $tipoAcum = $label . ':C:ACUM';
    $base = max(100.0, floor(($umbralAvisoMxn / 5.0) / 100.0) * 100.0);
    $triggered = false;
    for ($i = 0; $i < 8; $i++) {
        $resC = registrarOperacionPLD($pdo, [
            'id_cliente' => $idCliente,
            'monto' => $base + ($i * 31.0),
            'fecha_operacion' => date('Y-m-d'),
            'id_fraccion' => (int)$idFraccion,
            'tipo_operacion' => $tipoAcum,
            'umbral_aviso_uma_override' => $umbralAvisoUma,
            'umbral_acumulacion_uma_override' => $umbralAvisoUma,
        ]);
        $acumLogs[] = '[ACUM] step=' . ($i + 1) . ' requiere_aviso=' . (!empty($resC['requiere_aviso']) ? '1' : '0') . ' tipo=' . ($resC['tipo_aviso'] ?? '-');
        if (!empty($resC['success']) && ($resC['tipo_aviso'] ?? '') === 'acumulacion') {
            $triggered = true;
            break;
        }
    }
    qaInmAssert($triggered, 'Caso C dispara acumulación', 'Caso C no disparó acumulación', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E INM ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fracción V Bis id_vulnerable: {$idFraccion}\n";
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
    echo "=== E2E INM ===\n";
    echo "[FATAL] " . $e->getMessage() . "\n";
    foreach ($logs as $line) echo "[LOG] {$line}\n";
    exit(1);
}
