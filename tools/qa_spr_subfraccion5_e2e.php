<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se ejecuta por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_middleware.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/pld_fraccion_xi.php';
require_once __DIR__ . '/../config/spr_xml_helper.php';

/**
 * Devuelve candidatos de cliente para prueba.
 */
function getCandidateClients(PDO $pdo, ?int $forcedId = null): array
{
    $candidates = [];
    if ($forcedId && $forcedId > 0) {
        $stmt = $pdo->prepare("SELECT id_cliente, no_contrato FROM clientes WHERE id_cliente = ?");
        $stmt->execute([$forcedId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $candidates[] = $row;
        }
    } else {
        $stmt = $pdo->query("
            SELECT id_cliente, no_contrato
            FROM clientes
            WHERE id_status = 1 OR id_status IS NULL
            ORDER BY id_cliente DESC
            LIMIT 200
        ");
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return $candidates;
}

/**
 * Ajustes temporales de QA para no quedar bloqueados por metadata faltante del expediente.
 * Se ejecuta dentro de una transacción y luego se hace rollback.
 */
function applyTransientQaFixes(PDO $pdo, int $idCliente): void
{
    $hasDocSeen = $pdo->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clientes'
          AND COLUMN_NAME = 'documentos_vistos_original_certificado'
    ")->fetchColumn();
    if ((int)$hasDocSeen > 0) {
        $pdo->prepare("
            UPDATE clientes
            SET documentos_vistos_original_certificado = 1
            WHERE id_cliente = ?
        ")->execute([$idCliente]);
    }

    $hasFechaUpd = $pdo->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clientes'
          AND COLUMN_NAME = 'fecha_ultima_actualizacion_expediente'
    ")->fetchColumn();
    if ((int)$hasFechaUpd > 0) {
        $pdo->prepare("
            UPDATE clientes
            SET fecha_ultima_actualizacion_expediente = CURDATE()
            WHERE id_cliente = ?
        ")->execute([$idCliente]);
    }
}

try {
    $pdo->beginTransaction();

    $forcedClientId = isset($argv[1]) ? (int)$argv[1] : null;
    $candidates = getCandidateClients($pdo, $forcedClientId);
    if (empty($candidates)) {
        throw new RuntimeException('No hay clientes candidatos para prueba');
    }

    $pick = null;
    $eligibilityErrors = [];
    foreach ($candidates as $c) {
        $cid = (int)($c['id_cliente'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        try {
            applyTransientQaFixes($pdo, $cid);
            if (function_exists('requireExpedienteCompleto')) {
                requireExpedienteCompleto($pdo, $cid, false);
            }
            if (function_exists('requireBeneficiarioControlador')) {
                requireBeneficiarioControlador($pdo, $cid, false);
            }
            if (function_exists('requireNoNegativaIdentificacion')) {
                requireNoNegativaIdentificacion($pdo, $cid, false);
            }
            $pick = ['id_cliente' => $cid, 'no_contrato' => (string)($c['no_contrato'] ?? '')];
            break;
        } catch (Throwable $e) {
            $eligibilityErrors[] = "Cliente {$cid}: " . $e->getMessage();
        }
    }
    if (!$pick) {
        throw new RuntimeException("No se encontró cliente elegible. " . implode(' || ', array_slice($eligibilityErrors, 0, 5)));
    }

    $idCliente = (int)$pick['id_cliente'];
    $ref = 'QA5' . date('ymdHis') . random_int(10, 99);
    $fechaOperacion = date('Ymd');
    $fechaOperacionSql = date('Y-m-d');

    $payload = [
        'id_cliente' => $idCliente,
        'informe' => [[
            'mes_reportado' => date('Ym'),
            'sujeto_obligado' => [
                'clave_sujeto_obligado' => 'ABC010203AB1',
                'ocupacion' => ['tipo_ocupacion' => 'XI.C 01'],
                'clave_actividad' => 'SPR',
                'exento' => '0',
            ],
            'aviso' => [[
                'referencia_aviso' => $ref,
                'prioridad' => '1',
                'alerta' => ['tipo_alerta' => '100'],
                'persona_aviso' => [[
                    'tipo_persona' => [
                        'persona_fisica' => [
                            'nombre' => 'QA',
                            'apellido_paterno' => 'SPR',
                            'apellido_materno' => 'SUB5',
                            'fecha_nacimiento' => '19900101',
                            'rfc' => 'QASP900101AB1',
                            'curp' => 'QASP900101HDFRRA01',
                            'pais_nacionalidad' => 'MX',
                            'actividad_economica' => '4330100',
                        ],
                    ],
                    'tipo_domicilio' => [
                        'nacional' => [
                            'colonia' => 'CENTRO',
                            'calle' => 'CALLE QA',
                            'numero_exterior' => '1',
                            'codigo_postal' => '06000',
                        ],
                    ],
                    'telefono' => [
                        'clave_pais' => 'MX',
                        'numero_telefono' => '5512345678',
                        'correo_electronico' => 'qa.spr5@example.com',
                    ],
                ]],
                'detalle_operaciones' => [
                    'datos_operacion' => [[
                        'fecha_operacion' => $fechaOperacion,
                        'tipo_actividad' => [
                            'organizacion_aportaciones' => [
                                'motivo_aportacion' => '3',
                                'datos_aportacion' => [[
                                    'datos_persona_aporta' => [
                                        'persona_fisica' => [
                                            'nombre' => 'ANGEL',
                                            'apellido_paterno' => 'ALONSO',
                                            'apellido_materno' => 'VANEGAS',
                                            'fecha_nacimiento' => '19960220',
                                            'rfc' => 'ALVA960220ZS5',
                                            'curp' => 'ALVA960220MNENXM23',
                                            'pais_nacionalidad' => 'MX',
                                            'actividad_economica' => '4440100',
                                        ],
                                    ],
                                    'datos_tipo_aportacion' => [
                                        ['aportacion_monetaria' => ['instrumento_monetario' => '1', 'moneda' => '1', 'monto_operacion' => '1500.00']],
                                        ['aportacion_inmueble' => ['tipo_inmueble' => '5', 'codigo_postal' => '38785', 'folio_real' => 'FOL-973', 'valor_aportacion' => '500.00']],
                                        ['aportacion_otro_bien' => ['descripcion' => 'EQUIPO DE OFICINA', 'valor_aportacion' => '250.00']],
                                    ],
                                ]],
                            ],
                        ],
                        'datos_operacion_financiera' => [[
                            'fecha_pago' => $fechaOperacion,
                            'instrumento_monetario' => '1',
                            'moneda' => '1',
                            'monto_operacion' => '10.00',
                        ]],
                    ]],
                ],
            ]],
        ]],
    ];

    $idFraccion = function_exists('getIdVulnerableFraccionXI') ? getIdVulnerableFraccionXI($pdo) : null;
    $operacionData = [
        'id_cliente' => $idCliente,
        'monto' => 10.00,
        'fecha_operacion' => $fechaOperacionSql,
        'id_fraccion' => $idFraccion,
        'tipo_operacion' => 'SPR:organizacion_aportaciones',
        'subfraccion_xi' => 'organizacion_aportaciones',
        'es_sospechosa' => 0,
        'fecha_conocimiento_sospecha' => null,
        'match_listas_restringidas' => 0,
        'fecha_conocimiento_match' => null,
    ];

    $reg = registrarOperacionPLD($pdo, $operacionData);
    if (!($reg['success'] ?? false)) {
        throw new RuntimeException('registro_operacion: ' . ($reg['message'] ?? 'error desconocido'));
    }

    $idOperacion = (int)($reg['id_operacion'] ?? 0);
    if ($idOperacion <= 0) {
        throw new RuntimeException('No se obtuvo id_operacion');
    }

    $xmlRes = generateSPRXml($payload);
    $xml = $xmlRes['xml'] ?? '';
    $xmlErrors = $xmlRes['errors'] ?? [];
    if ($xml === '') {
        throw new RuntimeException('generateSPRXml devolvió XML vacío. Errores: ' . implode('; ', $xmlErrors));
    }
    if (strpos($xml, '<organizacion_aportaciones>') === false) {
        throw new RuntimeException('El XML no contiene organizacion_aportaciones');
    }

    $xmlName = 'qa_spr5_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
    try {
        $up = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
        $up->execute([$xml, $xmlName, $idOperacion]);
    } catch (Throwable $e) {
        // Algunas instalaciones no tienen columnas XML; no bloquea QA.
    }

    $stmt = $pdo->prepare("SELECT id_operacion, tipo_operacion, subfraccion_xi, monto, requiere_aviso FROM operaciones_pld WHERE id_operacion = ?");
    $stmt->execute([$idOperacion]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('No se pudo reconsultar la operación registrada');
    }
    if (($row['subfraccion_xi'] ?? '') !== 'organizacion_aportaciones') {
        throw new RuntimeException('subfraccion_xi no coincide: ' . ($row['subfraccion_xi'] ?? 'NULL'));
    }

    fwrite(STDOUT, "QA E2E SPR subfracción 5: OK\n");
    fwrite(STDOUT, "Cliente: {$idCliente} ({$pick['no_contrato']})\n");
    fwrite(STDOUT, "id_operacion: {$idOperacion}\n");
    fwrite(STDOUT, "tipo_operacion: {$row['tipo_operacion']}\n");
    fwrite(STDOUT, "subfraccion_xi: {$row['subfraccion_xi']}\n");
    fwrite(STDOUT, "requiere_aviso: " . ((int)($row['requiere_aviso'] ?? 0) === 1 ? 'SI' : 'NO') . "\n");
    fwrite(STDOUT, "xml_bytes: " . strlen($xml) . "\n");
    if (!empty($xmlErrors)) {
        fwrite(STDOUT, "xml_warnings: " . implode('; ', $xmlErrors) . "\n");
    }

    // Modo seguro: no persistir cambios de QA.
    $pdo->rollBack();
    fwrite(STDOUT, "rollback: SI (no se persistieron cambios)\n");
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "QA E2E SPR subfracción 5: ERROR\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
