<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/fes_catalogos.php';
require_once __DIR__ . '/../config/fes_xml_helper.php';

$base = [
    'mes_reportado' => '201510',
    'tribunal_dependencia' => [
        'clave_tribunal_dependencia' => 'LOU890506DE3',
        'clave_actividad' => 'FES',
    ],
    'aviso' => [[
        'referencia_aviso' => 'REF45',
        'prioridad' => '1',
        'alerta' => ['tipo_alerta' => '100'],
        'detalle_operaciones' => [[
            'datos_operacion' => [[
                'fecha_operacion' => '20151002',
                'tipo_actividad' => [],
            ]],
        ]],
    ]],
];

$cases = [
    'derechos_inmuebles' => [
        'derechos_inmuebles' => [
            'organo' => 'ORG JUR',
            'tipo_juicio' => 'JUICIO',
            'materia' => 'MATERIA',
            'expediente' => 'EXPD123',
            'tipo_acto' => '9',
            'tipo_acto_otro' => 'OTRO TIPO DE ACTO',
            'datos_inmuebles' => [[
                'caracteristicas_inmueble' => [[
                    'tipo_inmueble' => '2',
                    'valor_catastral' => '159000.00',
                    'colonia' => 'TEZOYUCA',
                    'calle' => 'CALLE',
                    'numero_exterior' => '500',
                    'codigo_postal' => '56000',
                    'dimension_terreno' => '150.00',
                    'dimension_construido' => '160.00',
                    'folio_real' => 'FOLIO123',
                ]],
            ]],
            'personas_acto' => [[
                'datos_persona_acto' => [[
                    'caracter' => '1',
                    'tipo_persona' => ['persona_fisica' => [
                        'nombre' => 'JUAN',
                        'apellido_paterno' => 'PEREZ',
                        'apellido_materno' => 'XXXX',
                        'pais_nacionalidad' => 'GH',
                    ]],
                ]],
            ]],
        ],
    ],
    'otorgamiento_poder' => [
        'otorgamiento_poder' => [
            'autoridad' => ['tipo_autoridad' => ['administrativo' => [
                'organo' => 'ORGANO',
                'cargo' => 'CARGO',
                'instrumento_publico' => '1456000',
            ]]],
            'persona_solicita' => [
                'nombre' => 'JORGE',
                'apellido_paterno' => 'XXXX',
                'apellido_materno' => 'HERNANDEZ',
                'pais_nacionalidad' => 'AI',
            ],
            'datos_poderdante' => [[
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'PEDRO',
                    'apellido_paterno' => 'PEREZ',
                    'apellido_materno' => 'PEREZ',
                    'pais_nacionalidad' => 'CC',
                ]],
            ]],
            'datos_apoderado' => [[
                'tipo_poder' => '3',
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'LAURA',
                    'apellido_paterno' => 'GONZALEZ',
                    'apellido_materno' => 'XXXX',
                    'pais_nacionalidad' => 'NG',
                ]],
            ]],
        ],
    ],
    'contrato_mutuo_credito' => [
        'contrato_mutuo_credito' => [
            'autoridad' => ['tipo_autoridad' => ['administrativo' => [
                'organo' => 'ORGADMIN 1',
                'cargo' => 'CAGO AUT',
                'instrumento_publico' => 'INSTPUB123',
            ]]],
            'tipo_otorgamiento' => '2',
            'persona_solicita' => [
                'nombre' => 'FERNANDO',
                'apellido_paterno' => 'XXXX',
                'apellido_materno' => 'PEREX',
                'pais_nacionalidad' => 'GG',
            ],
            'datos_acreedor' => [[
                'tipo_persona' => ['persona_fisica' => [
                    'nombre' => 'PABLO',
                    'apellido_paterno' => 'BELTRAN',
                    'apellido_materno' => 'XXXX',
                    'pais_nacionalidad' => 'IL',
                ]],
            ]],
            'datos_deudor' => [[
                'tipo_persona' => ['persona_moral' => [
                    'denominacion_razon' => 'PERSONA MORAL',
                    'pais_nacionalidad' => 'NP',
                ]],
            ]],
            'datos_liquidacion' => [['moneda' => '2', 'monto_operacion' => '195000.00']],
        ],
    ],
    'avaluo' => [
        'avaluo' => [
            'organo' => 'ORGADMIN1',
            'cargo' => 'CARGO AUTORIDAD',
            'expediente_oficio' => 'OFIC. 1 / 2015',
            'persona_solicita' => [
                'nombre' => 'JORGE',
                'apellido_paterno' => 'XXXX',
                'apellido_materno' => 'MARTINEZ',
                'pais_nacionalidad' => 'AO',
            ],
            'tipo_bien' => '1',
            'valor_avaluo' => '1800000.00',
            'datos_propietario' => [[
                'propietario_solicita' => 'NO',
                'dato_propietario' => [[
                    'tipo_persona' => ['persona_moral' => [
                        'denominacion_razon' => 'RAZON SOCIAL SA DE CV',
                        'pais_nacionalidad' => 'AO',
                    ]],
                ]],
            ]],
        ],
    ],
    'constitucion_personas_morales' => [
        'constitucion_personas_morales' => [
            'autoridad' => ['tipo_autoridad' => ['administrativo' => [
                'organo' => 'ORG ADMIN',
                'cargo' => 'CARGO AUT',
                'instrumento_publico_oficio' => 'INST PUB',
            ]]],
            'persona_solicita' => [
                'nombre' => 'JOSE',
                'apellido_paterno' => 'TORRES',
                'apellido_materno' => 'PEREZ',
                'pais_nacionalidad' => 'ES',
            ],
            'persona_moral_constitucion' => [
                'tipo_persona_moral' => '99',
                'denominacion_razon' => 'RAZON SOCIAL',
                'giro_mercantil' => '1000000',
                'numero_total_acciones' => '15.00',
                'consejo_vigilancia' => 'SI',
                'motivo_constitucion' => '1',
                'datos_accionista' => [[
                    'cargo_accionista' => '1',
                    'tipo_persona' => ['persona_fisica' => [
                        'nombre' => 'MARTIN',
                        'apellido_paterno' => 'SALAS',
                        'apellido_materno' => 'XXXX',
                        'pais_nacionalidad' => 'SB',
                    ]],
                    'numero_acciones' => '85.00',
                ]],
                'capital_social' => ['capital_fijo' => '18700000.00'],
            ],
        ],
    ],
    'modificacion_patrimonial' => [
        'modificacion_patrimonial' => [
            'autoridad' => ['tipo_autoridad' => ['jurisdiccional' => [
                'organo' => 'ORG JUR 1',
                'tipo_juicio' => 'JUICIO 1',
                'materia' => 'MATERIA 1',
                'expediente' => 'EXP. 1',
            ]]],
            'persona_moral_modifica' => [
                'denominacion_razon' => 'DENOMINACION',
                'pais_nacionalidad' => 'KY',
                'giro_mercantil' => '4850007',
                'numero_total_acciones' => '855.00',
                'motivo_modificacion' => '3',
            ],
            'datos_modificacion' => [
                'tipo_modificacion_capital_fijo' => '1',
                'inicial_capital_fijo' => '18000.00',
                'final_capital_fijo' => '20000.00',
                'tipo_modificacion_capital_variable' => '3',
                'inicial_capital_variable' => '25000.00',
                'final_capital_variable' => '25000.00',
                'datos_accionista' => [[
                    'tipo_persona' => ['persona_fisica' => [
                        'nombre' => 'JOSE',
                        'apellido_paterno' => 'HERNANDEZ',
                        'apellido_materno' => 'XXXX',
                        'pais_nacionalidad' => 'GP',
                    ]],
                    'numero_acciones' => '40.00',
                ]],
            ],
        ],
    ],
];

$ok = 0;
$errors = [];
foreach ($cases as $name => $tipoActividad) {
    $payload = $base;
    $payload['aviso'][0]['detalle_operaciones'][0]['datos_operacion'][0]['tipo_actividad'] = $tipoActividad;
    $result = generateFESXml(['informe' => [$payload]]);
    $xml = $result['xml'] ?? '';
    if ($xml !== '' && empty($result['errors'] ?? []) && str_contains($xml, "<{$name}>") && str_contains($xml, 'http://www.uif.shcp.gob.mx/recepcion/fes fes.xsd')) {
        $ok++;
    } else {
        $errors[] = $name;
    }
}

echo 'FES QA OK: ' . $ok . ' / ERR: ' . count($errors) . PHP_EOL;
if ($errors) {
    echo 'Errores: ' . implode(', ', $errors) . PHP_EOL;
    exit(1);
}
