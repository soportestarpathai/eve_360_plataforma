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
            LIMIT 300
        ");
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return $candidates;
}

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

function buildTipoActividadData(string $subfraccion): array
{
    switch ($subfraccion) {
        case 'compra_venta_inmuebles':
            return [
                'compra_venta_inmuebles' => [
                    'tipo_operacion' => '2',
                    'valor_pactado' => '250000.00',
                    'datos_contraparte' => [[
                        'tipo_persona' => [
                            'persona_fisica' => [
                                'nombre' => 'CONTRA',
                                'apellido_paterno' => 'PARTE',
                                'apellido_materno' => 'UNO',
                                'fecha_nacimiento' => '19900101',
                                'rfc' => 'COPU900101AB1',
                                'curp' => 'COPU900101HDFRRA01',
                                'pais_nacionalidad' => 'MX',
                            ],
                        ],
                    ]],
                    'caracteristicas_inmueble' => [[
                        'tipo_inmueble' => '1',
                        'colonia' => 'CENTRO',
                        'calle' => 'CALLE QA',
                        'numero_exterior' => '10',
                        'numero_interior' => 'A',
                        'codigo_postal' => '06000',
                        'dimension_terreno' => '120.00',
                        'dimension_construido' => '110.00',
                        'folio_real' => 'FOL-QA-001',
                        'contrato_instrumento_publico' => [
                            'contrato' => [
                                'fecha_contrato' => date('Ymd'),
                                'valor_referencia' => '250000.00',
                            ],
                        ],
                    ]],
                ],
            ];

        case 'cesion_derechos_inmuebles':
            return [
                'cesion_derechos_inmuebles' => [
                    'figura_cliente' => '2',
                    'tipo_cesion' => '1',
                    'datos_contraparte' => [[
                        'tipo_persona' => [
                            'persona_moral' => [
                                'denominacion_razon' => 'EMPRESA CONTRAPARTE QA',
                                'fecha_constitucion' => '20010101',
                                'rfc' => 'ECQ010101AB1',
                                'pais_nacionalidad' => 'MX',
                            ],
                        ],
                    ]],
                    'caracteristicas_inmueble' => [[
                        'tipo_inmueble' => '5',
                        'valor_referencia' => '150000.00',
                        'colonia' => 'CENTRO',
                        'calle' => 'CALLE CESION',
                        'numero_exterior' => '20',
                        'numero_interior' => 'B',
                        'codigo_postal' => '06000',
                        'dimension_terreno' => '80.00',
                        'dimension_construido' => '75.00',
                        'folio_real' => 'FOL-QA-002',
                    ]],
                ],
            ];

        case 'administracion_recursos':
            return [
                'administracion_recursos' => [
                    'tipo_activo' => [
                        [
                            'activo_banco' => [
                                'estatus_manejo' => '1',
                                'clave_tipo_institucion' => '40',
                                'nombre_institucion' => 'BANCO QA',
                                'numero_cuenta' => '1234567890',
                            ],
                        ],
                        [
                            'activo_otros' => [
                                'descripcion_activo_administrado' => 'PORTAFOLIO INVERSION QA',
                            ],
                        ],
                    ],
                    'numero_operaciones' => '2',
                ],
            ];

        case 'constitucion_sociedades_mercantiles':
            return [
                'constitucion_sociedades_mercantiles' => [
                    'tipo_persona_moral' => '6',
                    'denominacion_razon' => 'SOCIEDAD QA SA DE CV',
                    'giro_mercantil' => '1100001',
                    'folio_mercantil' => 'FOL-QA-003',
                    'numero_total_acciones' => '1000.00',
                    'entidad_federativa' => '14',
                    'consejo_vigilancia' => 'SI',
                    'motivo_constitucion' => '1',
                    'instrumento_publico' => 'INST-QA-003',
                    'datos_accionista' => [[
                        'cargo_accionista' => '4',
                        'tipo_persona' => [
                            'persona_fisica' => [
                                'nombre' => 'SOCIO',
                                'apellido_paterno' => 'UNO',
                                'apellido_materno' => 'QA',
                                'fecha_nacimiento' => '19850505',
                                'rfc' => 'SOUQ850505AB1',
                                'curp' => 'SOUQ850505HDFRRA01',
                                'pais_nacionalidad' => 'MX',
                            ],
                        ],
                        'numero_acciones' => '1000.00',
                    ]],
                    'capital_social' => [
                        'capital_fijo' => '500000.00',
                        'capital_variable' => '10000.00',
                    ],
                ],
            ];

        case 'organizacion_aportaciones':
            return [
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
            ];

        case 'fusion':
            return [
                'fusion' => [
                    'tipo_fusion' => '2',
                    'datos_fusionadas' => [
                        'datos_fusionada' => [[
                            'denominacion_razon' => 'EMPRESA FUSIONADA QA 01',
                            'fecha_constitucion' => '20080701',
                            'rfc' => 'EFQ080701AB1',
                            'pais_nacionalidad' => 'MX',
                            'giro_mercantil' => '1100001',
                            'capital_social_fijo' => '1111.00',
                            'capital_social_variable' => '12.00',
                            'folio_mercantil' => 'FOL-FUS-QA-01',
                        ]],
                    ],
                    'datos_fusionante' => [
                        'fusionante_determinadas' => 'SI',
                        'fusionante' => [
                            'denominacion_razon' => 'MORAL FUSIONANTE QA',
                            'fecha_constitucion' => '20121212',
                            'rfc' => 'MFQ121212AB1',
                            'pais_nacionalidad' => 'MX',
                            'giro_mercantil' => '4810007',
                            'capital_social_fijo' => '987654321.00',
                            'capital_social_variable' => '123.12',
                            'folio_mercantil' => 'F-QA-2012',
                            'numero_total_acciones' => '9999.99',
                            'datos_accionista' => [[
                                'tipo_persona' => [
                                    'persona_fisica' => [
                                        'nombre' => 'FRANCISCA',
                                        'apellido_paterno' => 'VALENCIA',
                                        'apellido_materno' => 'GONZALEZ',
                                        'fecha_nacimiento' => '19500805',
                                        'rfc' => 'VAGF500805ZP2',
                                        'curp' => 'VAGF500805MNEBPR90',
                                        'pais_nacionalidad' => 'MX',
                                    ],
                                ],
                                'numero_acciones' => '682103.00',
                            ]],
                        ],
                    ],
                ],
            ];

        case 'escision':
            return [
                'escision' => [
                    'datos_escindente' => [
                        'denominacion_razon' => 'EMPRESA ESCINDENTE QA',
                        'fecha_constitucion' => '20010101',
                        'rfc' => 'REQ010101AB1',
                        'pais_nacionalidad' => 'MX',
                        'giro_mercantil' => '5340013',
                        'capital_social_fijo' => '999999.99',
                        'capital_social_variable' => '1111.00',
                        'folio_mercantil' => 'F-QA-ESC-001',
                        'escindente_subsiste' => 'SI',
                        'datos_accionista_escindente' => [[
                            'tipo_persona' => [
                                'persona_fisica' => [
                                    'nombre' => 'ALFREDO',
                                    'apellido_paterno' => 'RUIZ',
                                    'apellido_materno' => 'VILLEGAS',
                                    'fecha_nacimiento' => '19570701',
                                    'rfc' => 'RUVA570701BX9',
                                    'curp' => 'RUVA570701HNEFLT28',
                                    'pais_nacionalidad' => 'MX',
                                ],
                            ],
                            'numero_acciones' => '452833.00',
                        ]],
                    ],
                    'datos_escindidas' => [
                        'escindidas_determinadas' => 'SI',
                        'dato_escindida' => [[
                            'denominacion_razon' => 'EMPRESA ESCINDIDA QA 1',
                            'fecha_constitucion' => '19861219',
                            'rfc' => 'EQQ861219AB1',
                            'pais_nacionalidad' => 'MX',
                            'giro_mercantil' => '3120005',
                            'capital_social_fijo' => '838406.00',
                            'capital_social_variable' => '66801.00',
                            'folio_mercantil' => 'FOL-QA-ESC-1',
                            'numero_total_acciones' => '564.00',
                            'datos_accionista' => [[
                                'tipo_persona' => [
                                    'persona_moral' => [
                                        'denominacion_razon' => 'EMPRESA ACCIONISTA ESCINDIDA QA',
                                        'fecha_constitucion' => '19891011',
                                        'rfc' => 'EAQ891011AB1',
                                        'pais_nacionalidad' => 'MX',
                                    ],
                                ],
                                'numero_acciones' => '80.00',
                            ]],
                        ]],
                    ],
                ],
            ];

        case 'administracion_personas_morales':
            return [
                'administracion_personas_morales' => [
                    'tipo_administracion' => 'ADMINISTRACION DE VEHICULO CORPORATIVO QA',
                    'tipo_operacion' => 'OPERACION DE PERSONA MORAL QA',
                    'persona_moral_aviso' => 'NO',
                    'tipo_persona' => [
                        'persona_moral' => [
                            'denominacion_razon' => 'EMPRESA ADMINISTRADA QA',
                            'fecha_constitucion' => '20010101',
                            'rfc' => 'EAQ010101AB1',
                            'pais_nacionalidad' => 'MX',
                        ],
                    ],
                ],
            ];

        case 'constitucion_fideicomiso':
            return [
                'constitucion_fideicomiso' => [
                    'rfc' => 'RFC010101XX1',
                    'identificador_fideicomiso' => 'IDENT-QA-0101',
                    'denominacion_razon' => 'FIDEICOMISO QA CONSTITUIDO',
                    'objeto_fideicomiso' => '5220013',
                    'monto_total_patrimonio' => '987654.32',
                    'datos_fideicomitente' => [[
                        'tipo_persona' => [
                            'persona_fisica' => [
                                'nombre' => 'ABRAHAM',
                                'apellido_paterno' => 'ABARCA',
                                'apellido_materno' => 'LOZANO',
                                'fecha_nacimiento' => '19690519',
                                'rfc' => 'ABLA690519TW2',
                                'curp' => 'ABLA690519MNEDBH29',
                                'pais_nacionalidad' => 'MX',
                            ],
                        ],
                        'datos_tipo_patrimonio' => [
                            ['patrimonio_monetario' => ['moneda' => '1', 'monto_operacion' => '782029.00']],
                            ['patrimonio_inmueble' => ['tipo_inmueble' => '1', 'codigo_postal' => '50587', 'folio_real' => 'FOL-350', 'importe_garantia' => '99932.00']],
                            ['patrimonio_otro_bien' => ['descripcion' => 'PATRIMONIO OTRO BIEN QA', 'valor_bien' => '95032.00']],
                        ],
                    ]],
                    'datos_fideicomisario' => [[
                        'datos_fideicomisarios_determinados' => 'SI',
                        'tipo_persona' => [
                            'persona_moral' => [
                                'denominacion_razon' => 'EMPRESA FIDEICOMISARIO QA',
                                'fecha_constitucion' => '19841115',
                                'rfc' => 'EFQ841115AB1',
                                'pais_nacionalidad' => 'MX',
                            ],
                        ],
                    ]],
                    'datos_miembro_comite_tecnico' => [
                        'comite_tecnico' => 'SI',
                    ],
                ],
            ];

        case 'compra_venta_entidades_mercantiles':
            return [
                'compra_venta_entidades_mercantiles' => [
                    'tipo_operacion' => '1',
                    'datos_sociedad_mercantil' => [[
                        'denominacion_razon' => 'EMPRESA QA ENTIDAD 01',
                        'giro_mercantil' => '4310006',
                        'fecha_constitucion' => '19510725',
                        'rfc' => 'EQA510725NA7',
                        'pais_nacionalidad' => 'MX',
                        'folio_mercantil' => 'FOL-QA-CVEM-01',
                        'acciones_adquiridas' => '53.00',
                        'acciones_totales' => '879.00',
                        'datos_contraparte' => [
                            'persona_fisica' => [
                                'nombre' => 'ANTONIO',
                                'apellido_paterno' => 'FEREZ',
                                'apellido_materno' => 'ARAUZ',
                                'fecha_nacimiento' => '19731013',
                                'rfc' => 'FEAA731013MF4',
                                'curp' => 'FEAA731013HNELTL76',
                                'pais_nacionalidad' => 'MX',
                            ],
                        ],
                    ]],
                ],
            ];
    }

    throw new InvalidArgumentException('Subfracción no soportada en QA: ' . $subfraccion);
}

try {
    $pdo->beginTransaction();

    $forcedClientId = isset($argv[1]) ? (int)$argv[1] : null;
    $montoBase = isset($argv[2]) ? (float)$argv[2] : 250000.00;
    if ($montoBase <= 0) {
        $montoBase = 250000.00;
    }
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
    $idFraccion = function_exists('getIdVulnerableFraccionXI') ? getIdVulnerableFraccionXI($pdo) : null;
    $fechaOperacion = date('Ymd');
    $fechaOperacionSql = date('Y-m-d');
    $mesReportado = date('Ym');

    $subfracciones = [
        'compra_venta_inmuebles' => 'compra_venta_inmuebles',
        'cesion_derechos_inmuebles' => 'cesion_derechos_inmuebles',
        'administracion_recursos' => 'administracion_recursos',
        'constitucion_sociedades_mercantiles' => 'constitucion_sociedades_mercantiles',
        'organizacion_aportaciones' => 'organizacion_aportaciones',
        'fusion' => 'fusion',
        'escision' => 'escision',
        'administracion_personas_morales' => 'administracion_personas_morales',
        'constitucion_fideicomiso' => 'constitucion_fideicomiso',
        'compra_venta_entidades_mercantiles' => 'compra_venta_entidades_mercantiles',
    ];

    $results = [];
    $idx = 0;

    foreach ($subfracciones as $subfraccion => $expectedTag) {
        $idx++;
        $ref = 'QA' . $idx . date('ymdHis') . random_int(10, 99);
        $tipoActividadData = buildTipoActividadData($subfraccion);

        $monto = $montoBase + $idx;
        $montoStr = number_format($monto, 2, '.', '');

        $payload = [
            'id_cliente' => $idCliente,
            'informe' => [[
                'mes_reportado' => $mesReportado,
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
                                'apellido_materno' => 'SUB',
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
                            'correo_electronico' => 'qa.spr@example.com',
                        ],
                    ]],
                    'detalle_operaciones' => [
                        'datos_operacion' => [[
                            'fecha_operacion' => $fechaOperacion,
                            'tipo_actividad' => $tipoActividadData,
                            'datos_operacion_financiera' => [[
                                'fecha_pago' => $fechaOperacion,
                                'instrumento_monetario' => '1',
                                'moneda' => '1',
                                'monto_operacion' => $montoStr,
                            ]],
                        ]],
                    ],
                ]],
            ]],
        ];

        $operacionData = [
            'id_cliente' => $idCliente,
            'monto' => $monto,
            'fecha_operacion' => $fechaOperacionSql,
            'id_fraccion' => $idFraccion,
            'tipo_operacion' => "SPR:{$subfraccion}",
            'subfraccion_xi' => $subfraccion,
            'es_sospechosa' => 0,
            'fecha_conocimiento_sospecha' => null,
            'match_listas_restringidas' => 0,
            'fecha_conocimiento_match' => null,
        ];

        $reg = registrarOperacionPLD($pdo, $operacionData);
        if (!($reg['success'] ?? false)) {
            throw new RuntimeException("registro_operacion ({$subfraccion}): " . ($reg['message'] ?? 'error desconocido'));
        }

        $idOperacion = (int)($reg['id_operacion'] ?? 0);
        if ($idOperacion <= 0) {
            throw new RuntimeException("No se obtuvo id_operacion para {$subfraccion}");
        }

        $xmlRes = generateSPRXml($payload);
        $xml = $xmlRes['xml'] ?? '';
        $xmlErrors = $xmlRes['errors'] ?? [];
        if ($xml === '') {
            throw new RuntimeException("generateSPRXml vacío ({$subfraccion}). Errores: " . implode('; ', $xmlErrors));
        }

        if (strpos($xml, "<{$expectedTag}>") === false) {
            throw new RuntimeException("El XML no contiene <{$expectedTag}> para {$subfraccion}");
        }

        try {
            $xmlName = 'qa_spr_' . $subfraccion . '_' . date('Ymd_His') . '_op' . $idOperacion . '.xml';
            $up = $pdo->prepare("UPDATE operaciones_pld SET xml_contenido = ?, xml_nombre_archivo = ? WHERE id_operacion = ?");
            $up->execute([$xml, $xmlName, $idOperacion]);
        } catch (Throwable $e) {
            // Algunas instalaciones no tienen columnas XML; no bloquea QA.
        }

        $stmt = $pdo->prepare("SELECT id_operacion, tipo_operacion, subfraccion_xi, monto, requiere_aviso FROM operaciones_pld WHERE id_operacion = ?");
        $stmt->execute([$idOperacion]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException("No se pudo reconsultar la operación {$idOperacion} ({$subfraccion})");
        }
        if (($row['subfraccion_xi'] ?? '') !== $subfraccion) {
            throw new RuntimeException("subfraccion_xi no coincide para {$subfraccion}: " . ($row['subfraccion_xi'] ?? 'NULL'));
        }

        $results[] = [
            'subfraccion' => $subfraccion,
            'id_operacion' => $idOperacion,
            'tipo_operacion' => (string)($row['tipo_operacion'] ?? ''),
            'requiere_aviso' => ((int)($row['requiere_aviso'] ?? 0) === 1 ? 'SI' : 'NO'),
            'xml_bytes' => strlen($xml),
        ];
    }

    fwrite(STDOUT, "QA E2E SPR subfracciones 1-10: OK\n");
    fwrite(STDOUT, "Cliente: {$idCliente} ({$pick['no_contrato']})\n");
    fwrite(STDOUT, "monto_base: " . number_format($montoBase, 2, '.', '') . "\n");
    foreach ($results as $r) {
        fwrite(
            STDOUT,
            "- {$r['subfraccion']} | op={$r['id_operacion']} | {$r['tipo_operacion']} | requiere_aviso={$r['requiere_aviso']} | xml_bytes={$r['xml_bytes']}\n"
        );
    }

    $pdo->rollBack();
    fwrite(STDOUT, "rollback: SI (no se persistieron cambios)\n");
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "QA E2E SPR subfracciones 1-10: ERROR\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
