<?php
/**
 * E2E ARI (Fraccion XV)
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_xv.php';
require_once __DIR__ . '/../config/ari_catalogos.php';
require_once __DIR__ . '/../config/ari_xml_helper.php';

function qaAriAssert(bool $cond, string $okMsg, string $errMsg, array &$ok, array &$err): void
{
    if ($cond) $ok[] = $okMsg; else $err[] = $errMsg;
}

function qaAriUma(PDO $pdo): float
{
    $row = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uma = $row ? (float)$row['valor'] : 100.0;
    return $uma > 0 ? $uma : 100.0;
}

function qaAriFindClient(PDO $pdo, int $idFraccion, array &$logs): ?array
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
            'tipo_operacion' => 'XV:ARI:E2E:PROBE',
            'umbral_aviso_uma_override' => pldFraccionXVUmbralAviso(),
            'umbral_acumulacion_uma_override' => pldFraccionXVUmbralAviso(),
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
    global $ARI_CATALOGOS;
    qaAriAssert(pldFraccionXVUmbralIdentificacion() === 1605.0, 'Identificacion XV 1605 UMA', 'Umbral identificacion XV incorrecto', $ok, $err);
    qaAriAssert(pldFraccionXVUmbralAviso() === 3210.0, 'Aviso XV 3210 UMA', 'Umbral aviso XV incorrecto', $ok, $err);
    qaAriAssert(isset($ARI_CATALOGOS['clave_actividad']['ARI']), 'Catalogo clave_actividad ARI', 'Falta clave ARI', $ok, $err);
    qaAriAssert(isset($ARI_CATALOGOS['tipo_alerta']['100'], $ARI_CATALOGOS['tipo_alerta']['3001'], $ARI_CATALOGOS['tipo_alerta']['3024'], $ARI_CATALOGOS['tipo_alerta']['9999']), 'Catalogo tipo_alerta ARI oficial', 'Falta tipo_alerta ARI oficial', $ok, $err);
    qaAriAssert(isset($ARI_CATALOGOS['tipo_operacion']['1501']) && count($ARI_CATALOGOS['tipo_operacion']) === 1, 'Catalogo tipo_operacion ARI oficial', 'Tipo_operacion ARI debe contener solo 1501', $ok, $err);
    qaAriAssert(count($ARI_CATALOGOS['moneda'] ?? []) >= 184, 'Catalogo moneda ARI completo', 'Moneda ARI incompleta', $ok, $err);
    qaAriAssert(count($ARI_CATALOGOS['pais'] ?? []) >= 249 && isset($ARI_CATALOGOS['pais']['MX'], $ARI_CATALOGOS['pais']['US'], $ARI_CATALOGOS['pais']['ZW']), 'Catalogo pais ARI completo', 'Pais ARI incompleto', $ok, $err);
    qaAriAssert(count($ARI_CATALOGOS['actividad_economica'] ?? []) >= 167 && isset($ARI_CATALOGOS['actividad_economica']['1000000'], $ARI_CATALOGOS['actividad_economica']['1136080']), 'Catalogo actividad_economica ARI completo', 'Actividad_economica ARI incompleta', $ok, $err);
    qaAriAssert(count($ARI_CATALOGOS['giro_mercantil'] ?? []) >= 136 && isset($ARI_CATALOGOS['giro_mercantil']['1000000'], $ARI_CATALOGOS['giro_mercantil']['9880024']), 'Catalogo giro_mercantil ARI completo', 'Giro_mercantil ARI incompleto', $ok, $err);

    $xmlPayload = ['informe' => [[
        'mes_reportado' => date('Ym'),
        'sujeto_obligado' => [
            'clave_sujeto_obligado' => 'OJA781213J89',
            'clave_actividad' => 'ARI',
        ],
        'aviso' => [[
            'referencia_aviso' => 'ARI' . date('dHis'),
            'prioridad' => '1',
            'alerta' => ['tipo_alerta' => '100'],
            'persona_aviso' => [[
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'BENITO',
                    'apellido_paterno' => 'PEREZ',
                    'apellido_materno' => 'GALDOS',
                    'fecha_nacimiento' => '19561015',
                    'pais_nacionalidad' => 'MX',
                    'actividad_economica' => '1000000',
                ]],
            ]],
            'detalle_operaciones' => [[
                'datos_operacion' => [[
                    'fecha_operacion' => date('Ymd'),
                    'tipo_operacion' => '1501',
                    'caracteristicas' => [
                        'fecha_inicio' => date('Ymd'),
                        'fecha_termino' => date('Ymd', strtotime('+1 month')),
                        'tipo_inmueble' => '1',
                        'valor_referencia' => '15000.00',
                        'colonia' => 'GUADALUPE INN',
                        'calle' => 'REVOLUCION',
                        'numero_exterior' => '458',
                        'codigo_postal' => '01020',
                        'folio_real' => 'FR123',
                    ],
                    'datos_liquidacion' => [[
                        'fecha_pago' => date('Ymd'),
                        'forma_pago' => '1',
                        'instrumento_monetario' => '1',
                        'moneda' => '1',
                        'monto_operacion' => '15000.00',
                    ]],
                ]],
            ]],
        ]],
    ]]];

    $xml = (string)((generateARIXml($xmlPayload)['xml'] ?? ''));
    qaAriAssert($xml !== '', 'XML ARI generado', 'No se genero XML ARI', $ok, $err);
    qaAriAssert(strpos($xml, 'http://www.uif.shcp.gob.mx/recepcion/ari') !== false, 'XML usa namespace ARI', 'XML no usa namespace ARI', $ok, $err);
    qaAriAssert(strpos($xml, '<clave_actividad>ARI</clave_actividad>') !== false, 'XML incluye clave ARI', 'XML no incluye clave ARI', $ok, $err);
    qaAriAssert(strpos($xml, '<persona_aviso>') !== false && strpos($xml, '<nombre>BENITO</nombre>') !== false, 'XML ARI incluye persona_aviso', 'XML ARI no incluye persona_aviso', $ok, $err);
    qaAriAssert(strpos($xml, '<caracteristicas>') !== false && strpos($xml, '<fecha_termino>') !== false && strpos($xml, '<folio_real>FR123</folio_real>') !== false, 'XML ARI sigue caracteristicas del instructivo', 'XML ARI no sigue caracteristicas del instructivo', $ok, $err);
    qaAriAssert(strpos($xml, '<valor_referencia>15000.00</valor_referencia>') !== false, 'XML formatea valor_referencia ARI', 'XML no formatea valor_referencia ARI', $ok, $err);
    qaAriAssert(strpos($xml, '<monto_operacion>15000.00</monto_operacion>') !== false, 'XML formatea monto ARI', 'XML no formatea monto ARI', $ok, $err);

    $xmlEjemploPayload = ['informe' => [[
        'mes_reportado' => '201407',
        'sujeto_obligado' => [
            'clave_sujeto_obligado' => 'OGA751212G56',
            'clave_actividad' => 'ARI',
        ],
        'aviso' => [[
            'referencia_aviso' => 'REF15454FG454',
            'prioridad' => '1',
            'alerta' => ['tipo_alerta' => '100'],
            'persona_aviso' => [[
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'NEPOMUCENO',
                    'apellido_paterno' => 'ALMONTE',
                    'apellido_materno' => 'JUAREZ',
                    'fecha_nacimiento' => '19560816',
                    'pais_nacionalidad' => 'TG',
                    'actividad_economica' => '3130000',
                ]],
                'tipo_domicilio' => ['extranjero' => [
                    'pais' => 'TG',
                    'estado_provincia' => 'TOGUILLITA',
                    'ciudad_poblacion' => 'TOGUIS',
                    'colonia' => 'NA',
                    'calle' => 'TOGA TOGA',
                    'numero_exterior' => '45',
                    'codigo_postal' => '12448',
                ]],
            ]],
            'detalle_operaciones' => [[
                'datos_operacion' => [[
                    'fecha_operacion' => '20140701',
                    'tipo_operacion' => '1501',
                    'caracteristicas' => [[
                        'fecha_inicio' => '20140101',
                        'fecha_termino' => '20150101',
                        'tipo_inmueble' => '3',
                        'valor_referencia' => '356825.12',
                        'colonia' => '6920',
                        'calle' => 'SAN SIMON TOLNAHUAC',
                        'numero_exterior' => 'VIOLANTE',
                        'numero_interior' => '45',
                        'codigo_postal' => '01058',
                        'folio_real' => 'BG544-FRR-456B-FRR',
                    ]],
                    'datos_liquidacion' => [[
                        'fecha_pago' => '20140701',
                        'forma_pago' => '4',
                        'instrumento_monetario' => '4',
                        'moneda' => '2',
                        'monto_operacion' => '757897.55',
                    ]],
                ]],
            ]],
        ]],
    ]]];
    $xmlEjemplo = (string)((generateARIXml($xmlEjemploPayload)['xml'] ?? ''));
    qaAriAssert(strpos($xmlEjemplo, '<extranjero>') !== false && strpos($xmlEjemplo, '<pais>TG</pais>') !== false && strpos($xmlEjemplo, '<codigo_postal>12448</codigo_postal>') !== false, 'XML ARI genera domicilio extranjero del ejemplo', 'XML ARI no genera domicilio extranjero del ejemplo', $ok, $err);
    qaAriAssert(strpos($xmlEjemplo, '<folio_real>BG544-FRR-456B-FRR</folio_real>') !== false, 'XML ARI conserva folio_real con guiones', 'XML ARI no conserva folio_real con guiones', $ok, $err);
    qaAriAssert(strpos($xmlEjemplo, '<valor_referencia>356825.12</valor_referencia>') !== false && strpos($xmlEjemplo, '<monto_operacion>757897.55</monto_operacion>') !== false, 'XML ARI genera montos del ejemplo', 'XML ARI no genera montos del ejemplo', $ok, $err);

    $idFraccion = getIdVulnerableFraccionXV($pdo);
    if (!$idFraccion) throw new RuntimeException('No se encontro Fraccion XV.');
    $pdo->beginTransaction();
    $candidate = qaAriFindClient($pdo, (int)$idFraccion, $logs);
    $pdo->rollBack();
    if (!$candidate) throw new RuntimeException('No se encontro cliente apto para E2E ARI.');

    $idCliente = (int)$candidate['id_cliente'];
    $uma = qaAriUma($pdo);
    $umbralMxn = pldFraccionXVUmbralAviso() * $uma;
    $label = 'XV:ARI:E2E:' . date('YmdHis');
    $pdo->beginTransaction();

    $resA = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => max(100.0, $umbralMxn * 0.01),
        'fecha_operacion' => date('Y-m-d'),
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':A:NO_AVISO',
        'umbral_aviso_uma_override' => pldFraccionXVUmbralAviso(),
        'umbral_acumulacion_uma_override' => pldFraccionXVUmbralAviso(),
    ]);
    qaAriAssert(!empty($resA['success']), 'Caso A registra operacion', 'Caso A no registro operacion', $ok, $err);
    qaAriAssert(empty($resA['requiere_aviso']), 'Caso A sin aviso', 'Caso A genero aviso inesperado', $ok, $err);

    $resB = registrarOperacionPLD($pdo, [
        'id_cliente' => $idCliente,
        'monto' => $umbralMxn + max(1000.0, $umbralMxn * 0.02),
        'fecha_operacion' => date('Y-m-d'),
        'id_fraccion' => (int)$idFraccion,
        'tipo_operacion' => $label . ':B:INDIVIDUAL',
        'umbral_aviso_uma_override' => pldFraccionXVUmbralAviso(),
        'umbral_acumulacion_uma_override' => pldFraccionXVUmbralAviso(),
    ]);
    qaAriAssert(!empty($resB['success']), 'Caso B registra operacion', 'Caso B no registro operacion', $ok, $err);
    qaAriAssert(!empty($resB['requiere_aviso']), 'Caso B genera aviso individual', 'Caso B no genero aviso individual', $ok, $err);

    $pdo->rollBack();

    echo "=== E2E ARI ===\n";
    echo "Cliente: {$idCliente}\n";
    echo "Fraccion XV id_vulnerable: {$idFraccion}\n";
    echo "UMA: {$uma}\n";
    echo "Umbral aviso/acumulacion: " . pldFraccionXVUmbralAviso() . " UMA ({$umbralMxn} MXN)\n---\n";
    foreach ($logs as $line) echo "[LOG] {$line}\n";
    echo "---\nOK: " . count($ok) . "\n";
    foreach ($ok as $line) echo "[OK] {$line}\n";
    echo "ERR: " . count($err) . "\n";
    foreach ($err as $line) echo "[ERR] {$line}\n";
    exit(empty($err) ? 0 : 1);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    echo "=== E2E ARI ===\n";
    echo "[FATAL] " . $e->getMessage() . "\n";
    foreach ($logs as $line) echo "[LOG] {$line}\n";
    exit(1);
}
