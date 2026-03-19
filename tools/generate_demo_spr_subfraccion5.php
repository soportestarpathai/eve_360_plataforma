<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/spr_xml_helper.php';

$personaAviso = [
    [
        'tipo_persona' => [
            'persona_fisica' => [
                'nombre' => 'JOSE IGNACIO',
                'apellido_paterno' => 'ANGELES',
                'apellido_materno' => 'SALCEDO',
                'fecha_nacimiento' => '19780921',
                'rfc' => 'ANSJ780921BH3',
                'curp' => 'ANSJ780921HNEPSD75',
                'pais_nacionalidad' => 'FO',
                'actividad_economica' => '4330100',
            ],
        ],
        'tipo_domicilio' => [
            'nacional' => [
                'colonia' => 'EX HACIENDA DE ABAJO',
                'calle' => 'CALLE 01',
                'numero_exterior' => 'NUM EXT 01',
                'numero_interior' => 'NUM INT 01',
                'codigo_postal' => '46404',
            ],
        ],
        'telefono' => [
            'clave_pais' => 'EC',
            'numero_telefono' => '9876543210',
            'correo_electronico' => 'ANGELES.JS@MAIL.COM',
        ],
    ],
    [
        'tipo_persona' => [
            'persona_moral' => [
                'denominacion_razon' => 'EMPRESA MEXICANA',
                'fecha_constitucion' => '19790808',
                'rfc' => 'DZZ790808XF3',
                'pais_nacionalidad' => 'MX',
                'giro_mercantil' => '1100001',
                'representante_apoderado' => [
                    'nombre' => 'JUAN',
                    'apellido_paterno' => 'FRANCIS',
                    'apellido_materno' => 'CERVERA',
                    'fecha_nacimiento' => '19800925',
                    'rfc' => 'FRCJ800925JO1',
                    'curp' => 'FRCJ800925HNEXXX84',
                ],
            ],
        ],
        'tipo_domicilio' => [
            'nacional' => [
                'colonia' => 'INFONAVIT HERMENEGILDO J. ALDANA',
                'calle' => 'CALLE 02',
                'numero_exterior' => 'NUM EXT 02',
                'numero_interior' => 'NUM INT 02',
                'codigo_postal' => '72120',
            ],
        ],
        'telefono' => [
            'clave_pais' => 'MX',
            'numero_telefono' => '550102030405',
            'correo_electronico' => 'EMPRESA_MEXICANA@MAIL.COM',
        ],
    ],
    [
        'tipo_persona' => [
            'fideicomiso' => [
                'denominacion_razon' => 'FID123',
                'rfc' => 'FQU850511UR8',
                'identificador_fideicomiso' => 'F-930655',
                'apoderado_delegado' => [
                    [
                        'nombre' => 'JOSE LUIS',
                        'apellido_paterno' => 'HERNANDEZ',
                        'apellido_materno' => 'GARCIA',
                        'fecha_nacimiento' => '19920807',
                        'rfc' => 'HEGJ920807XB3',
                        'curp' => 'HEGJ920807HNEXXX17',
                    ],
                    [
                        'nombre' => 'JOSE',
                        'apellido_paterno' => 'GONZALEZ',
                        'apellido_materno' => 'FRANCO',
                        'fecha_nacimiento' => '19611227',
                        'rfc' => 'GOFJ611227SZ1',
                        'curp' => 'GOFJ611227HNEXXX65',
                    ],
                ],
            ],
        ],
    ],
    [
        'tipo_persona' => [
            'persona_fisica' => [
                'nombre' => 'ROBERTO AUGUSTO',
                'apellido_paterno' => 'MARTINEZ',
                'apellido_materno' => 'CASTAÑO',
                'fecha_nacimiento' => '19930306',
                'rfc' => 'MACR930306GP1',
                'curp' => 'MACR930306MNEMDP78',
                'pais_nacionalidad' => 'PS',
                'actividad_economica' => '6430300',
                'representante_apoderado' => [
                    'nombre' => 'EDUARDO',
                    'apellido_paterno' => 'GUITART',
                    'apellido_materno' => 'VARGAS',
                    'fecha_nacimiento' => '19640914',
                    'rfc' => 'GUVE640914HI4',
                    'curp' => 'GUVE640914MNEXXX44',
                ],
            ],
        ],
        'tipo_domicilio' => [
            'extranjero' => [
                'pais' => 'AG',
                'estado_provincia' => 'ESTADO 04',
                'ciudad_poblacion' => 'CUIDAD 04',
                'colonia' => 'COLONIA 04',
                'calle' => 'CALLE 04',
                'numero_exterior' => 'NUM EXT 04',
                'numero_interior' => 'NUM INT 04',
                'codigo_postal' => 'CP-EXT-04',
            ],
        ],
        'telefono' => [
            'clave_pais' => 'AR',
            'numero_telefono' => '001122334455',
            'correo_electronico' => 'MARTINEZ_RC@MAIL.COM',
        ],
    ],
];

$duenoBeneficiario = [
    [
        'tipo_persona' => [
            'persona_fisica' => [
                'nombre' => 'LETICIA DEL CARMEN',
                'apellido_paterno' => 'AVIÑA',
                'apellido_materno' => 'LLORENTE',
                'fecha_nacimiento' => '19851010',
                'rfc' => 'AVLL851010TG2',
                'curp' => 'AVLL851010MNEMDA63',
                'pais_nacionalidad' => 'SO',
            ],
        ],
    ],
    [
        'tipo_persona' => [
            'persona_moral' => [
                'denominacion_razon' => 'EMPRESA BENEFICIARIA',
                'fecha_constitucion' => '19541005',
                'rfc' => 'TKP541005IQ1',
                'pais_nacionalidad' => 'SA',
            ],
        ],
    ],
    [
        'tipo_persona' => [
            'fideicomiso' => [
                'denominacion_razon' => 'FID 456',
                'rfc' => 'HWN540401JE3',
                'identificador_fideicomiso' => 'F-981269',
            ],
        ],
    ],
];

$organizacionAportaciones = [
    'motivo_aportacion' => '3',
    'datos_aportacion' => [
        [
            'datos_persona_aporta' => [
                'persona_fisica' => [
                    'nombre' => 'ANGEL',
                    'apellido_paterno' => 'ALONSO',
                    'apellido_materno' => 'VANEGAS',
                    'fecha_nacimiento' => '19960220',
                    'rfc' => 'ALVA960220ZS5',
                    'curp' => 'ALVA960220MNENXM23',
                    'pais_nacionalidad' => 'SK',
                    'actividad_economica' => '4440100',
                ],
            ],
            'datos_tipo_aportacion' => [
                ['aportacion_monetaria' => ['instrumento_monetario' => '1', 'moneda' => '1', 'monto_operacion' => '699887.00']],
                ['aportacion_inmueble' => ['tipo_inmueble' => '5', 'codigo_postal' => '38785', 'folio_real' => 'FOL-973', 'valor_aportacion' => '362271.00']],
                ['aportacion_otro_bien' => ['descripcion' => 'DESCRIPCION DEL BIEN APORTADO 123', 'valor_aportacion' => '10797.00']],
            ],
        ],
        [
            'datos_persona_aporta' => [
                'persona_fisica' => [
                    'nombre' => 'GABRIELA',
                    'apellido_paterno' => 'ALONSO',
                    'apellido_materno' => 'VIVEROS',
                    'fecha_nacimiento' => '19981110',
                    'rfc' => 'ALVG981110GD1',
                    'curp' => 'ALVG981110MNEPFF10',
                    'pais_nacionalidad' => 'SI',
                    'actividad_economica' => '4530200',
                ],
            ],
            'datos_tipo_aportacion' => [
                ['aportacion_monetaria' => ['instrumento_monetario' => '15', 'moneda' => '178', 'monto_operacion' => '857920.00']],
                ['aportacion_monetaria' => ['instrumento_monetario' => '99', 'moneda' => '1', 'monto_operacion' => '987451.00']],
            ],
        ],
        [
            'datos_persona_aporta' => [
                'persona_moral' => [
                    'denominacion_razon' => 'EMPRESA APORTA 1',
                    'fecha_constitucion' => '19970819',
                    'rfc' => 'HHK970819IW1',
                    'pais_nacionalidad' => 'AN',
                    'giro_mercantil' => '2400002',
                ],
            ],
            'datos_tipo_aportacion' => [
                ['aportacion_monetaria' => ['instrumento_monetario' => '14', 'moneda' => '171', 'monto_operacion' => '304949.00']],
                ['aportacion_inmueble' => ['tipo_inmueble' => '10', 'codigo_postal' => '39086', 'folio_real' => 'FOL-318', 'valor_aportacion' => '922125.00']],
                ['aportacion_otro_bien' => ['descripcion' => 'DESCRIPCION DEL BIEN APORTADO 456', 'valor_aportacion' => '245607.00']],
            ],
        ],
        [
            'datos_persona_aporta' => [
                'persona_moral' => [
                    'denominacion_razon' => 'EMPRESA APORTA 2',
                    'fecha_constitucion' => '19741227',
                    'rfc' => 'AIZ741227WK2',
                    'pais_nacionalidad' => 'SA',
                    'giro_mercantil' => '2500002',
                ],
            ],
            'datos_tipo_aportacion' => [
                ['aportacion_monetaria' => ['instrumento_monetario' => '10', 'moneda' => '3', 'monto_operacion' => '459902.00']],
                ['aportacion_inmueble' => ['tipo_inmueble' => '99', 'codigo_postal' => '74770', 'folio_real' => 'FOL-950', 'valor_aportacion' => '213157.00']],
            ],
        ],
        [
            'datos_persona_aporta' => [
                'fideicomiso' => [
                    'denominacion_razon' => 'FID APORTA AA',
                    'rfc' => 'ZTI980823BL3',
                    'identificador_fideicomiso' => 'F-844136',
                ],
            ],
            'datos_tipo_aportacion' => [
                ['aportacion_monetaria' => ['instrumento_monetario' => '8', 'moneda' => '3', 'monto_operacion' => '521134.00']],
                ['aportacion_inmueble' => ['tipo_inmueble' => '15', 'codigo_postal' => '72814', 'folio_real' => 'FOL-727', 'valor_aportacion' => '573222.00']],
                ['aportacion_otro_bien' => ['descripcion' => 'DESCRIPCION DEL BIEN APORTADO 987', 'valor_aportacion' => '6923.00']],
            ],
        ],
        [
            'datos_persona_aporta' => [
                'fideicomiso' => [
                    'denominacion_razon' => 'FID APORTA WW',
                    'rfc' => 'YMN941011KX4',
                    'identificador_fideicomiso' => 'F-396967',
                ],
            ],
            'datos_tipo_aportacion' => [
                ['aportacion_otro_bien' => ['descripcion' => 'DESCRIPCION DEL BIEN APORTADO 159', 'valor_aportacion' => '687938.00']],
            ],
        ],
    ],
];

$data = [
    'informe' => [[
        'mes_reportado' => '202108',
        'sujeto_obligado' => [
            'clave_sujeto_obligado' => 'RSOB470613HT5',
            'ocupacion' => ['tipo_ocupacion' => 'XI.C 01'],
            'clave_actividad' => 'SPR',
        ],
        'aviso' => [[
            'referencia_aviso' => 'ID1526F0037',
            'prioridad' => '1',
            'alerta' => [
                'tipo_alerta' => '9999',
                'descripcion_alerta' => 'DESCRIPCION DE LA ALERTA QUE NO SE ENCUENTRA EN EL CATALOGO',
            ],
            'persona_aviso' => $personaAviso,
            'dueno_beneficiario' => $duenoBeneficiario,
            'detalle_operaciones' => [
                'datos_operacion' => [[
                    'fecha_operacion' => '20210808',
                    'tipo_actividad' => [
                        'organizacion_aportaciones' => $organizacionAportaciones,
                    ],
                    'datos_operacion_financiera' => [
                        ['fecha_pago' => '20210801', 'instrumento_monetario' => '1', 'moneda' => '1', 'monto_operacion' => '63990361.00'],
                        ['fecha_pago' => '20210801', 'instrumento_monetario' => '8', 'moneda' => '2', 'monto_operacion' => '53930461.00'],
                        ['fecha_pago' => '20210805', 'instrumento_monetario' => '13', 'moneda' => '176', 'monto_operacion' => '54458675.00'],
                        ['fecha_pago' => '20210805', 'instrumento_monetario' => '14', 'moneda' => '175', 'monto_operacion' => '35293536.00'],
                        ['fecha_pago' => '20210805', 'instrumento_monetario' => '15', 'moneda' => '178', 'monto_operacion' => '98424600.00'],
                        ['fecha_pago' => '20210812', 'instrumento_monetario' => '16', 'activo_virtual' => ['tipo_activo_virtual' => '1001', 'cantidad_activo_virtual' => '0.00523'], 'monto_operacion' => '78529379.00'],
                        ['fecha_pago' => '20210812', 'instrumento_monetario' => '16', 'activo_virtual' => ['tipo_activo_virtual' => '1022', 'cantidad_activo_virtual' => '12.34'], 'monto_operacion' => '73578123.00'],
                        ['fecha_pago' => '20210812', 'instrumento_monetario' => '16', 'activo_virtual' => ['tipo_activo_virtual' => '999999', 'descripcion_activo_virtual' => 'ULTRA COIN MASTER', 'cantidad_activo_virtual' => '1234567890.0123456789'], 'monto_operacion' => '32519370.00'],
                    ],
                ]],
            ],
        ]],
    ]],
];

$res = generateSPRXml($data);
$errors = $res['errors'] ?? [];
if (!empty($errors)) {
    fwrite(STDERR, "Errores XML: " . implode('; ', $errors) . PHP_EOL);
    exit(1);
}

$xml = $res['xml'] ?? '';
if ($xml === '') {
    fwrite(STDERR, "No se genero XML." . PHP_EOL);
    exit(1);
}

$dest = __DIR__ . '/../demo_spr_subfraccion5.xml';
file_put_contents($dest, $xml);

$dom = new DOMDocument();
$isValidXml = $dom->loadXML($xml);
echo "Archivo generado: demo_spr_subfraccion5.xml" . PHP_EOL;
echo "Bytes: " . strlen($xml) . PHP_EOL;
echo "XML valido: " . ($isValidXml ? 'SI' : 'NO') . PHP_EOL;
