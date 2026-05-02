<?php
/**
 * E2E ADU (Fraccion XIV)
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_xiv.php';
require_once __DIR__ . '/../config/adu_catalogos.php';
require_once __DIR__ . '/../config/adu_xml_helper.php';

function qaAduAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) $ok[] = $okMsg; else $err[] = $errMsg;
}

function qaAduUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaAduFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
{
    $rows = $pdo->query("
        SELECT id_cliente, no_contrato, expediente_completo
        FROM clientes
        WHERE COALESCE(id_status, 1) <> 4
        ORDER BY COALESCE(expediente_completo,0) DESC, id_cliente DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $c) {
        $probe = registrarOperacionPLD($pdo, [
            'id_cliente' => (int)$c['id_cliente'],
            'monto' => 10.00,
            'fecha_operacion' => date('Y-m-d'),
            'id_fraccion' => $idFraccion,
            'tipo_operacion' => 'XIV:ADU:E2E:PROBE',
            'umbral_aviso_uma_override' => pldFraccionXIVUmbralAviso('MJR'),
            'umbral_acumulacion_uma_override' => pldFraccionXIVUmbralAviso('MJR'),
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

try {
    global $ADU_CATALOGOS;
    qaAduAssert(pldFraccionXIVUmbralAviso('VEH') === 0.0, 'VEH aviso siempre', 'VEH no es siempre', $ok, $err);
    qaAduAssert(pldFraccionXIVUmbralAviso('MJR') === 485.0, 'MJR 485 UMA', 'MJR incorrecto', $ok, $err);
    qaAduAssert(pldFraccionXIVUmbralAviso('OBA') === 4815.0, 'OBA 4815 UMA', 'OBA incorrecto', $ok, $err);
    qaAduAssert(isset($ADU_CATALOGOS['clave_actividad']['ADU']), 'Catalogo clave_actividad ADU', 'Falta clave ADU', $ok, $err);
    qaAduAssert(isset($ADU_CATALOGOS['actividad_vulnerable']['JYS'], $ADU_CATALOGOS['actividad_vulnerable']['TSC'], $ADU_CATALOGOS['actividad_vulnerable']['TPP'], $ADU_CATALOGOS['actividad_vulnerable']['TDR'], $ADU_CATALOGOS['actividad_vulnerable']['MJR'], $ADU_CATALOGOS['actividad_vulnerable']['OBA'], $ADU_CATALOGOS['actividad_vulnerable']['VEH'], $ADU_CATALOGOS['actividad_vulnerable']['ADU'], $ADU_CATALOGOS['actividad_vulnerable']['ARI']), 'Catalogo actividades vulnerables ADU', 'Faltan actividades vulnerables ADU', $ok, $err);
    qaAduAssert(count($ADU_CATALOGOS['moneda'] ?? []) >= 184, 'Catalogo moneda ADU completo', 'Moneda ADU incompleta', $ok, $err);

    $xmlPayload = ['informe' => [[
        'mes_reportado' => date('Ym'),
        'sujeto_obligado' => [
            'clave_sujeto_obligado' => 'OJA781213J89',
            'clave_actividad' => 'ADU',
        ],
    ]]];

    $xml = (string)((generateADUXml($xmlPayload)['xml'] ?? ''));
    qaAduAssert($xml !== '', 'XML ADU generado', 'No se genero XML ADU', $ok, $err);
    qaAduAssert(strpos($xml, 'http://www.uif.shcp.gob.mx/recepcion/adu') !== false, 'XML usa namespace ADU', 'XML no usa namespace ADU', $ok, $err);
    qaAduAssert(strpos($xml, '<clave_actividad>ADU</clave_actividad>') !== false, 'XML incluye clave ADU', 'XML no incluye clave ADU', $ok, $err);
    qaAduAssert(strpos($xml, '<aviso>') === false && strpos($xml, '<detalle_operaciones>') === false, 'XML ADU sigue instructivo mínimo', 'XML ADU incluye nodos no indicados por instructivo', $ok, $err);
    qaAduAssert(strpos($xml, 'adu.xsd') === false, 'XML ADU no referencia XSD inexistente', 'XML ADU referencia adu.xsd inexistente', $ok, $err);

    $idFraccion = getIdVulnerableFraccionXIV($pdo);
    if (!$idFraccion) throw new RuntimeException('No se encontro Fraccion XIV.');
    $pdo->beginTransaction();
    $candidate = qaAduFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) throw new RuntimeException('No se encontro cliente apto para E2E ADU.');

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaAduUma($pdo);
    $label = 'XIV:ADU:E2E:' . date('YmdHis');
    $pdo->beginTransaction();

    $resA = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => 100.00,
        'fecha_operacion' => date('Y-m-d'),
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':A:SIEMPRE',
        'umbral_aviso_uma_override' => pldFraccionXIVUmbralAviso('VEH'),
        'umbral_acumulacion_uma_override' => pldFraccionXIVUmbralAviso('VEH'),
        'requiere_aviso_forzado' => true,
        'tipo_aviso_forzado' => 'umbral_individual',
    ]);
    qaAduAssert(!empty($resA['success']), 'Caso A registra operacion', 'Caso A no registro operacion', $ok, $err);
    qaAduAssert(!empty($resA['requiere_aviso']), 'Caso A genera aviso siempre', 'Caso A no genero aviso siempre', $ok, $err);

    $resD = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => (485.0 * $uma) + 1000.00,
        'fecha_operacion' => date('Y-m-d'),
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':D:UMBRAL',
        'umbral_aviso_uma_override' => pldFraccionXIVUmbralAviso('MJR'),
        'umbral_acumulacion_uma_override' => pldFraccionXIVUmbralAviso('MJR'),
    ]);
    qaAduAssert(!empty($resD['success']), 'Caso D registra operacion', 'Caso D no registro operacion', $ok, $err);
    qaAduAssert(!empty($resD['requiere_aviso']), 'Caso D genera aviso por 485 UMA', 'Caso D no genero aviso', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E ADU ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fraccion XIV id_vulnerable: {$idFraccion}\n";
    echo "UMA: {$uma}\n---\n";
    foreach ($logs as $line) echo "[LOG] {$line}\n";
    echo "---\nOK: " . count($ok) . "\n";
    foreach ($ok as $line) echo "[OK] {$line}\n";
    echo "ERR: " . count($err) . "\n";
    foreach ($err as $line) echo "[ERR] {$line}\n";
    exit(empty($err) ? 0 : 1);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    echo "=== E2E ADU ===\n";
    echo "[FATAL] " . $e->getMessage() . "\n";
    foreach ($logs as $line) echo "[LOG] {$line}\n";
    exit(1);
}
