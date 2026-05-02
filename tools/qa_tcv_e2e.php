<?php
/**
 * E2E TCV (Fracción X)
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_x.php';
require_once __DIR__ . '/../config/tcv_catalogos.php';
require_once __DIR__ . '/../config/tcv_xml_helper.php';

function qaTcvAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) $ok[] = $okMsg; else $err[] = $errMsg;
}

function qaTcvUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaTcvFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
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
            'tipo_operacion' => 'X:TCV:E2E:PROBE',
            'umbral_aviso_uma_override' => pldFraccionXUmbralAviso(),
            'umbral_acumulacion_uma_override' => pldFraccionXUmbralAviso(),
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
    global $TCV_CATALOGOS;
    qaTcvAssert(pldFraccionXUmbralIdentificacion() === 0.0, 'Identificación TCV siempre obligatoria', 'TCV no marca identificación siempre', $ok, $err);
    qaTcvAssert(pldFraccionXUmbralAviso() === 3210.0, 'Umbral aviso TCV 3210 UMA', 'Umbral aviso TCV incorrecto', $ok, $err);
    qaTcvAssert(isset($TCV_CATALOGOS['clave_actividad']['TCV']), 'Catálogo clave_actividad TCV', 'Falta clave_actividad TCV', $ok, $err);
    qaTcvAssert(isset($TCV_CATALOGOS['tipo_alerta']['100'], $TCV_CATALOGOS['tipo_alerta']['3201'], $TCV_CATALOGOS['tipo_alerta']['3218'], $TCV_CATALOGOS['tipo_alerta']['9999']), 'Catálogo tipo_alerta TCV oficial mínimo', 'Falta tipo_alerta TCV 100/3201/3218/9999', $ok, $err);
    qaTcvAssert(isset($TCV_CATALOGOS['tipo_operacion']['1001'], $TCV_CATALOGOS['tipo_operacion']['1002'], $TCV_CATALOGOS['tipo_operacion']['1003']), 'Catálogo tipo_operacion TCV mínimo', 'Falta tipo_operacion TCV', $ok, $err);
    qaTcvAssert(isset($TCV_CATALOGOS['tipo_servicio']['1'], $TCV_CATALOGOS['tipo_servicio']['2'], $TCV_CATALOGOS['tipo_servicio']['3'], $TCV_CATALOGOS['tipo_servicio']['4']), 'Catálogo tipo_servicio TCV oficial', 'Falta tipo_servicio TCV', $ok, $err);
    qaTcvAssert(isset($TCV_CATALOGOS['tipo_valor']['1'], $TCV_CATALOGOS['tipo_valor']['2'], $TCV_CATALOGOS['tipo_valor']['3'], $TCV_CATALOGOS['tipo_valor']['4'], $TCV_CATALOGOS['tipo_valor']['5'], $TCV_CATALOGOS['tipo_valor']['6'], $TCV_CATALOGOS['tipo_valor']['8'], $TCV_CATALOGOS['tipo_valor']['99']) && !isset($TCV_CATALOGOS['tipo_valor']['7']), 'Catálogo tipo_valor TCV oficial', 'Falta tipo_valor TCV o contiene clave no oficial', $ok, $err);
    qaTcvAssert(isset($TCV_CATALOGOS['instrumento_monetario']['1'], $TCV_CATALOGOS['instrumento_monetario']['15']) && !isset($TCV_CATALOGOS['instrumento_monetario']['8']), 'Catálogo instrumento_monetario TCV oficial mínimo', 'Instrumento_monetario TCV no coincide con oficial', $ok, $err);
    qaTcvAssert(count($TCV_CATALOGOS['moneda'] ?? []) >= 184 && isset($TCV_CATALOGOS['moneda']['1'], $TCV_CATALOGOS['moneda']['184']), 'Catálogo moneda TCV completo', 'Catálogo moneda TCV incompleto', $ok, $err);
    qaTcvAssert(count($TCV_CATALOGOS['pais'] ?? []) >= 200 && isset($TCV_CATALOGOS['pais']['MX'], $TCV_CATALOGOS['pais']['US']), 'Catálogo país TCV completo mínimo', 'Catálogo país TCV incompleto', $ok, $err);
    qaTcvAssert(count($TCV_CATALOGOS['giro_mercantil'] ?? []) >= 120 && isset($TCV_CATALOGOS['giro_mercantil']['1000000'], $TCV_CATALOGOS['giro_mercantil']['1100001'], $TCV_CATALOGOS['giro_mercantil']['9880024']), 'Catálogo giro_mercantil TCV completo', 'Catálogo giro_mercantil TCV incompleto', $ok, $err);
    qaTcvAssert(count($TCV_CATALOGOS['actividad_economica'] ?? []) >= 150 && isset($TCV_CATALOGOS['actividad_economica']['1000000'], $TCV_CATALOGOS['actividad_economica']['1136080']), 'Catálogo actividad_economica TCV completo', 'Catálogo actividad_economica TCV incompleto', $ok, $err);

    $xmlPayload = ['informe' => [[
        'mes_reportado' => date('Ym'),
        'sujeto_obligado' => [
            'clave_sujeto_obligado' => 'OJA781213J89',
            'clave_actividad' => 'TCV',
        ],
        'aviso' => [[
            'referencia_aviso' => 'TCV' . date('dHis'),
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
            ]],
            'detalle_operaciones' => [[
                'datos_operacion' => [[
                    'fecha_operacion' => date('Ymd'),
                    'tipo_operacion' => '1003',
                    'tipo_bien' => [[
                        'datos_efectivo_instrumentos' => [
                            'instrumento_monetario' => '1',
                            'moneda' => '1',
                            'monto_operacion' => '100000.00',
                        ],
                    ]],
                    'recepcion' => [
                        'tipo_servicio' => '1',
                        'fecha_recepcion' => date('Ymd'),
                        'codigo_postal' => '01020',
                    ],
                    'custodia' => [
                        'fecha_inicio' => date('Ymd'),
                        'fecha_fin' => date('Ymd'),
                        'tipo_custodia' => ['datos_sucursal' => ['codigo_postal' => '01020']],
                    ],
                    'entrega' => [
                        'fecha_entrega' => date('Ymd'),
                        'tipo_entrega' => ['nacional' => ['codigo_postal' => '01020']],
                    ],
                    'destinatario' => ['destinatario_persona_aviso' => 'SI'],
                ]],
            ]],
        ]],
    ]]];

    $gen = generateTCVXml($xmlPayload);
    $xml = (string)($gen['xml'] ?? '');
    qaTcvAssert($xml !== '', 'XML TCV generado', 'No se generó XML TCV', $ok, $err);
    qaTcvAssert(strpos($xml, 'http://www.uif.shcp.gob.mx/recepcion/tcv') !== false, 'XML usa namespace TCV', 'XML no usa namespace TCV', $ok, $err);
    qaTcvAssert(strpos($xml, '<clave_actividad>TCV</clave_actividad>') !== false, 'XML incluye clave_actividad TCV', 'XML no incluye clave_actividad TCV', $ok, $err);
    qaTcvAssert(strpos($xml, '<recepcion>') !== false && strpos($xml, '<custodia>') !== false && strpos($xml, '<entrega>') !== false, 'XML incluye recepción/custodia/entrega', 'XML incompleto en recepción/custodia/entrega', $ok, $err);

    $xmlPayloadValores = $xmlPayload;
    $xmlPayloadValores['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0]['tipo_bien'] = [[
        'datos_valores' => [
            'tipo_valor' => '1',
            'valor_objeto' => '1234.56',
            'descripcion' => 'METALES PRECIOSOS EN CUSTODIA',
        ],
    ]];
    $xmlValores = (string)((generateTCVXml($xmlPayloadValores)['xml'] ?? ''));
    qaTcvAssert(strpos($xmlValores, '<datos_valores>') !== false, 'XML TCV genera datos_valores', 'XML no genera datos_valores', $ok, $err);
    qaTcvAssert(strpos($xmlValores, '<tipo_valor>1</tipo_valor>') !== false, 'XML TCV conserva tipo_valor como dígito', 'XML formatea mal tipo_valor', $ok, $err);
    qaTcvAssert(strpos($xmlValores, '<valor_objeto>1234.56</valor_objeto>') !== false, 'XML TCV formatea valor_objeto como monto', 'XML formatea mal valor_objeto', $ok, $err);

    $xmlPayloadDest = $xmlPayload;
    $xmlPayloadDest['informe'][0]['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0]['destinatario'] = [
        'destinatario_persona_aviso' => 'NO',
        'tipo_persona' => ['persona_fisica' => [
            'nombre' => 'JOSE',
            'apellido_paterno' => 'RODRIGUEZ',
            'apellido_materno' => 'SOLANO',
            'fecha_nacimiento' => '19780522',
        ]],
    ];
    $xmlDest = (string)((generateTCVXml($xmlPayloadDest)['xml'] ?? ''));
    qaTcvAssert(strpos($xmlDest, '<destinatario_persona_aviso>NO</destinatario_persona_aviso>') !== false, 'XML TCV genera destinatario NO', 'XML no genera destinatario NO', $ok, $err);
    qaTcvAssert(strpos($xmlDest, '<destinatario>') !== false && strpos($xmlDest, '<persona_fisica>') !== false && strpos($xmlDest, '<nombre>JOSE</nombre>') !== false, 'XML TCV genera datos de destinatario', 'XML no genera datos de destinatario', $ok, $err);

    $xmlPayloadDueno = $xmlPayload;
    $xmlPayloadDueno['informe'][0]['aviso'][0]['dueno_beneficiario'] = [[
        'tipo_persona' => ['persona_fisica' => [
            'nombre' => 'PEDRO',
            'apellido_paterno' => 'LOPEZ',
            'apellido_materno' => 'X',
            'fecha_nacimiento' => '19890516',
            'pais_nacionalidad' => 'AD',
        ]],
    ]];
    $xmlPayloadDueno['informe'][0]['aviso'][0] = [
        'referencia_aviso' => $xmlPayloadDueno['informe'][0]['aviso'][0]['referencia_aviso'],
        'prioridad' => $xmlPayloadDueno['informe'][0]['aviso'][0]['prioridad'],
        'alerta' => $xmlPayloadDueno['informe'][0]['aviso'][0]['alerta'],
        'persona_aviso' => $xmlPayloadDueno['informe'][0]['aviso'][0]['persona_aviso'],
        'dueno_beneficiario' => $xmlPayloadDueno['informe'][0]['aviso'][0]['dueno_beneficiario'],
        'detalle_operaciones' => $xmlPayloadDueno['informe'][0]['aviso'][0]['detalle_operaciones'],
    ];
    $xmlDueno = (string)((generateTCVXml($xmlPayloadDueno)['xml'] ?? ''));
    qaTcvAssert(strpos($xmlDueno, '<dueno_beneficiario>') !== false && strpos($xmlDueno, '<nombre>PEDRO</nombre>') !== false, 'XML TCV genera dueño beneficiario', 'XML no genera dueño beneficiario', $ok, $err);
    qaTcvAssert(strpos($xmlDueno, '<dueno_beneficiario>') < strpos($xmlDueno, '<detalle_operaciones>'), 'XML TCV ordena dueño antes de detalle', 'XML ordena mal dueño beneficiario', $ok, $err);

    $idFraccion = getIdVulnerableFraccionX($pdo);
    if (!$idFraccion) throw new RuntimeException('No se encontró Fracción X en cat_vulnerables.');
    $pdo->beginTransaction();
    $candidate = qaTcvFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) throw new RuntimeException('No se encontró cliente apto para E2E TCV.');

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaTcvUma($pdo);
    $umbralAvisoUma = pldFraccionXUmbralAviso();
    $umbralAvisoMxn = $umbralAvisoUma * $uma;
    $label = 'X:TCV:E2E:' . date('YmdHis');
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
    qaTcvAssert(!empty($resA['success']), 'Caso A registra operación', 'Caso A no registró operación', $ok, $err);
    qaTcvAssert(empty($resA['requiere_aviso']), 'Caso A sin aviso', 'Caso A generó aviso inesperado', $ok, $err);

    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $umbralAvisoMxn + max(1000.0, $umbralAvisoMxn * 0.02),
        'fecha_operacion' => date('Y-m-d'),
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:INDIVIDUAL',
        'umbral_aviso_uma_override' => $umbralAvisoUma,
        'umbral_acumulacion_uma_override' => $umbralAvisoUma,
    ]);
    qaTcvAssert(!empty($resB['success']), 'Caso B registra operación', 'Caso B no registró operación', $ok, $err);
    qaTcvAssert(!empty($resB['requiere_aviso']), 'Caso B genera aviso individual', 'Caso B no generó aviso individual', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E TCV ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fracción X id_vulnerable: {$idFraccion}\n";
    echo "UMA: {$uma}\n";
    echo "Umbral aviso/acumulación: {$umbralAvisoUma} UMA ({$umbralAvisoMxn} MXN)\n---\n";
    foreach ($logs as $line) echo "[LOG] {$line}\n";
    echo "---\nOK: " . count($ok) . "\n";
    foreach ($ok as $line) echo "[OK] {$line}\n";
    echo "ERR: " . count($err) . "\n";
    foreach ($err as $line) echo "[ERR] {$line}\n";
    exit(empty($err) ? 0 : 1);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    echo "=== E2E TCV ===\n";
    echo "[FATAL] " . $e->getMessage() . "\n";
    foreach ($logs as $line) echo "[LOG] {$line}\n";
    exit(1);
}
