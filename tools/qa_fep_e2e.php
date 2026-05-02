<?php
/**
 * QA FEP: valida generacion XML para las 10 subfracciones recibidas.
 */

require_once __DIR__ . '/../config/fep_xml_helper.php';

$base = [
    'mes_reportado' => '201407',
    'sujeto_obligado' => [
        'clave_sujeto_obligado' => 'OGA751212G56',
        'clave_actividad' => 'FEP',
    ],
    'aviso' => [[
        'referencia_aviso' => 'REF201407',
        'prioridad' => '1',
        'alerta' => ['tipo_alerta' => '100'],
        'persona_aviso' => [[
            'nombre' => 'JOSE',
            'apellido_paterno' => 'RODRIGUEZ',
            'apellido_materno' => 'SOLANO',
            'fecha_nacimiento' => '19780522',
        ]],
        'detalle_operaciones' => [[
            'datos_operacion' => [],
        ]],
    ]],
];

$samples = [
    'otorgamiento_poder' => [
        'otorgamiento_poder' => [
            'datos_poderdante' => ['tipo_persona' => ['persona_moral' => [
                'denominacion_razon' => 'AZTECA', 'fecha_constitucion' => '19800202',
                'pais_nacionalidad' => 'MX', 'giro_mercantil' => '5313110',
            ]]],
            'datos_apoderado' => [[
                'tipo_poder' => '2',
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'ANDREA', 'apellido_paterno' => 'ROBLES', 'apellido_materno' => 'GONZALES',
                    'fecha_nacimiento' => '19921120', 'pais_nacionalidad' => 'MX',
                ]],
            ]],
        ],
    ],
    'constitucion_personas_morales' => [
        'constitucion_personas_morales' => [
            'tipo_persona_moral' => '2',
            'denominacion_razon' => 'LAAZTECA',
            'giro_mercantil' => '4640000',
            'folio_mercantil' => '231GH',
            'numero_total_acciones' => '12334.00',
            'entidad_federativa' => '07',
            'consejo_vigilancia' => 'NO',
            'motivo_constitucion' => '3',
            'instrumento_publico' => '12549655',
            'datos_accionista' => [[
                'cargo_accionista' => '1',
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'MARIBEL', 'apellido_paterno' => 'FLORES', 'apellido_materno' => 'RIO',
                    'fecha_nacimiento' => '19770317', 'pais_nacionalidad' => 'MX',
                ]],
                'numero_acciones' => '30.00',
            ]],
            'capital_social' => ['capital_fijo' => '12810.00', 'capital_variable' => '1541800.44'],
        ],
    ],
    'modificacion_patrimonial' => [
        'modificacion_patrimonial' => [
            'persona_moral_modifica' => [
                'denominacion_razon' => 'CHOCOLATEAZTECA', 'fecha_constitucion' => '20000811',
                'rfc' => 'CHO000811KJL', 'pais_nacionalidad' => 'MX', 'giro_mercantil' => '5231100',
                'numero_total_acciones' => '12334.55', 'motivo_modificacion' => '3', 'instrumento_publico' => '4500',
            ],
            'datos_modificacion' => [
                'tipo_modificacion_capital_fijo' => '2', 'inicial_capital_fijo' => '12300.55', 'final_capital_fijo' => '11560.25',
                'tipo_modificacion_capital_variable' => '2', 'inicial_capital_variable' => '10056.00', 'final_capital_variable' => '100567.34',
                'datos_accionista' => [[
                    'tipo_persona' => ['persona_fisica' => [
                        'nombre' => 'ALMA', 'apellido_paterno' => 'SALAS', 'apellido_materno' => 'VILLA',
                        'fecha_nacimiento' => '19771015', 'pais_nacionalidad' => 'MX',
                    ]],
                    'numero_acciones' => '50.00',
                ]],
            ],
        ],
    ],
    'fusion' => [
        'fusion' => [
            'tipo_fusion' => '1',
            'datos_fusionadas' => ['datos_fusionada' => [[
                'denominacion_razon' => 'SAISOLUCIONES', 'fecha_constitucion' => '20001102', 'rfc' => 'SAI001102HJK',
                'pais_nacionalidad' => 'MX', 'giro_mercantil' => '5222200', 'capital_social_fijo' => '2200.00',
                'capital_social_variable' => '1250033.00', 'folio_mercantil' => '718183FG',
            ]]],
            'datos_fusionante' => ['fusionante_determinadas' => 'SI'],
        ],
    ],
    'escision' => [
        'escision' => [
            'datos_escindente' => [
                'denominacion_razon' => 'AVANTE', 'fecha_constitucion' => '19780713', 'rfc' => 'AVA780713IIO',
                'pais_nacionalidad' => 'MX', 'giro_mercantil' => '5411100', 'capital_social_fijo' => '12000036.00',
                'capital_social_variable' => '566000333.00', 'folio_mercantil' => 'YUT678', 'escindente_subsiste' => 'SI',
            ],
            'datos_escindidas' => ['escindidas_determinadas' => 'SI'],
        ],
    ],
    'compra_venta_acciones' => [
        'compra_venta_acciones' => [
            'tipo_operacion' => '1',
            'persona_moral_acciones' => [
                'denominacion_razon' => 'PEDRO', 'fecha_constitucion' => '18000101',
                'pais_nacionalidad' => 'AL', 'valor_nominal' => '112223215158.00', 'numero_acciones' => '45.00',
                'datos_vendedor' => [[
                    'numero_acciones_vendidas' => '45.00',
                    'tipo_persona' => ['persona_moral' => [
                        'denominacion_razon' => 'AZTECA', 'fecha_constitucion' => '19800202', 'pais_nacionalidad' => 'MX',
                    ]],
                ]],
                'datos_comprador' => [[
                    'numero_acciones_compradas' => '40.00',
                    'tipo_persona' => ['persona_fisica' => [
                        'nombre' => 'ANDREA', 'apellido_paterno' => 'ROBLES', 'apellido_materno' => 'GONZALES',
                        'fecha_nacimiento' => '19921120', 'pais_nacionalidad' => 'MX',
                    ]],
                ]],
            ],
            'datos_liquidacion' => ['fecha_pago' => '20140701', 'instrumento_monetario' => '1', 'moneda' => '1', 'monto_operacion' => '1800000.12'],
        ],
    ],
    'constitucion_modificacion_fideicomiso' => [
        'constitucion_modificacion_fideicomiso' => [
            'tipo_movimiento' => '4', 'tipo_fideicomiso' => '1', 'descripcion' => 'DESCRIPCION FIDEICOMISO',
            'identificador_fideicomiso' => '1A', 'denominacion_razon' => 'FID', 'monto_patrimonio' => '5900000.40',
            'datos_fideicomitente' => [[
                'tipo_movimiento_fideicomitente' => '1',
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'C', 'apellido_paterno' => 'C', 'apellido_materno' => 'C',
                    'fecha_nacimiento' => '19901212', 'pais_nacionalidad' => 'AF', 'actividad_economica' => '0300004',
                ]],
            ]],
            'datos_fideicomisarios' => ['datos_fideicomisarios_determinados' => 'NO'],
            'datos_miembro_comite_tecnico' => ['comite_tecnico' => 'SI', 'modificacion_comite_tecnico' => 'NO'],
        ],
    ],
    'cesion_derechos_fideicomitente_fideicomisario' => [
        'cesion_derechos_fideicomitente_fideicomisario' => [
            'identificador_fideicomiso' => '44564', 'rfc' => 'ROS7805225M4',
            'denominacion_razon' => 'ASDASDASDASDD', 'tipo_cesion' => '1',
            'datos_cedente' => ['tipo_persona' => ['persona_fisica' => [
                'nombre' => 'C', 'apellido_paterno' => 'C', 'apellido_materno' => 'C',
                'fecha_nacimiento' => '19901212', 'pais_nacionalidad' => 'AF', 'actividad_economica' => '0300004',
            ]]],
            'datos_cesionario' => ['tipo_persona' => ['persona_fisica' => [
                'nombre' => 'D', 'apellido_paterno' => 'D', 'apellido_materno' => 'D',
                'fecha_nacimiento' => '19981212', 'pais_nacionalidad' => 'AD', 'actividad_economica' => '1300003',
            ]]],
            'datos_cesion' => ['monto_cesion' => '115621261.00'],
        ],
    ],
    'contrato_mutuo_credito' => [
        'contrato_mutuo_credito' => [
            'tipo_otorgamiento' => '2',
            'datos_acreedor' => ['tipo_persona' => ['persona_fisica' => [
                'nombre' => 'C', 'apellido_paterno' => 'C', 'apellido_materno' => 'C',
                'fecha_nacimiento' => '19801212', 'pais_nacionalidad' => 'AQ', 'actividad_economica' => '1300003',
            ]]],
            'datos_deudor' => ['tipo_persona' => ['persona_fisica' => [
                'nombre' => 'C', 'apellido_paterno' => 'C', 'apellido_materno' => 'C',
                'fecha_nacimiento' => '19801212', 'pais_nacionalidad' => 'AQ', 'actividad_economica' => '1300003',
            ]]],
            'datos_garantia' => [[
                'tipo_garantia' => '2',
                'datos_bien_mutuo' => ['datos_inmueble' => [
                    'tipo_inmueble' => '1', 'valor_referencia' => '1000000.00',
                    'codigo_postal' => '06000', 'folio_real' => '1111111',
                ]],
            ]],
            'datos_liquidacion' => ['moneda' => '1', 'monto_operacion' => '10000000.00'],
        ],
    ],
    'avaluo' => [
        'avaluo' => [
            'tipo_bien' => '1',
            'valor_avaluo' => '580000.98',
            'datos_propietario' => [
                'propietario_solicita' => 'NO',
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'MAR', 'apellido_paterno' => 'CAS', 'apellido_materno' => 'OLI',
                    'fecha_nacimiento' => '19820903', 'pais_nacionalidad' => 'AF',
                ]],
            ],
        ],
    ],
];

$ok = 0;
$err = 0;
foreach ($samples as $name => $tipoActividad) {
    $inf = $base;
    $inf['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][] = [
        'instrumento_publico' => '123456',
        'fecha_operacion' => '20140101',
        'tipo_actividad' => $tipoActividad,
    ];
    $out = generateFEPXml(['informe' => [$inf]]);
    $xml = (string)($out['xml'] ?? '');
    $schemaOk = false;
    if ($xml !== '') {
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        libxml_use_internal_errors(true);
        $schemaOk = $doc->schemaValidate(__DIR__ . '/../fep.xsd');
        if (!$schemaOk) {
            foreach (libxml_get_errors() as $e) {
                echo "XSD $name: " . trim($e->message) . "\n";
            }
            libxml_clear_errors();
        }
    }
    if ($xml !== '' && $schemaOk && strpos($xml, '<' . $name . '>') !== false && strpos($xml, '<clave_actividad>FEP</clave_actividad>') !== false) {
        $ok++;
    } else {
        $err++;
        echo "ERR: $name\n";
    }
}

echo "OK: $ok\n";
echo "ERR: $err\n";
exit($err > 0 ? 1 : 0);
