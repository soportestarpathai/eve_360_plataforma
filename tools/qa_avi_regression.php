<?php
/**
 * QA rápido de regresión para AVI (Fracción XVI)
 * - Catálogos críticos
 * - Umbrales 210 UMA / 4 UMA
 * - Estructura XML base y tags relevantes
 */

require_once __DIR__ . '/../config/avi_catalogos.php';
require_once __DIR__ . '/../config/avi_xml_helper.php';
require_once __DIR__ . '/../config/pld_fraccion_xvi.php';

$errors = [];
$ok = [];

$assert = function (bool $cond, string $msg) use (&$errors, &$ok): void {
    if ($cond) {
        $ok[] = $msg;
    } else {
        $errors[] = $msg;
    }
};

// 1) Catálogo tipo_alerta AVI
$alertaReq = ['100', '4101', '4122', '9999'];
foreach ($alertaReq as $k) {
    $assert(isset($AVI_CATALOGOS['tipo_alerta'][$k]), "tipo_alerta contiene {$k}");
}

// 2) Catálogo institución financiera 101..157
$instCat = $AVI_CATALOGOS['clave_institucion_financiera'] ?? [];
$missingInst = [];
for ($i = 101; $i <= 157; $i++) {
    if (!isset($instCat[(string)$i]) && !isset($instCat[$i])) {
        $missingInst[] = $i;
    }
}
$assert(empty($missingInst), 'clave_institucion_financiera cubre 101..157');

// 3) Umbrales XVI
$uma = 113.14;
$evalMonto = pldFraccionXVIEvaluaUmbralAviso($uma, 30000.00, 0.00);
$evalContra = pldFraccionXVIEvaluaUmbralAviso($uma, 100.00, 500.00);
$evalNo = pldFraccionXVIEvaluaUmbralAviso($uma, 100.00, 100.00);
$assert(!empty($evalMonto['requiere_aviso_por_monto']), 'umbral monto >= 210 UMA dispara aviso');
$assert(!empty($evalContra['requiere_aviso_por_contraprestacion']), 'umbral contraprestación >= 4 UMA dispara aviso');
$assert(empty($evalNo['requiere_aviso']), 'montos bajos no disparan aviso');

// 4) XML AVI con dueños múltiples + institución financiera
$payload = [
    'informe' => [[
        'mes_reportado' => date('Ym'),
        'sujeto_obligado' => [
            'clave_sujeto_obligado' => 'ABC010101AB1',
            'clave_actividad' => 'AVI',
            'dominio_plataforma' => 'EVE-360'
        ],
        'aviso' => [[
            'referencia_aviso' => 'AVIQA001',
            'prioridad' => 1,
            'alerta' => ['tipo_alerta' => 100],
            'operaciones_persona' => [
                'persona_aviso' => [
                    'datos_cuenta_plataforma' => [
                        'id_usuario' => 'USR001',
                        'cuenta_relacionada' => '1234567890',
                        'clabe_interbancaria' => '012345678912345678',
                        'moneda_cuenta' => 1
                    ],
                    'tipo_persona' => [
                        'persona_fisica' => [
                            'nombre' => 'JUAN',
                            'apellido_paterno' => 'PEREZ',
                            'apellido_materno' => 'LOPEZ',
                            'fecha_nacimiento' => '19900101',
                            'rfc' => 'PELJ900101A12',
                            'curp' => 'PELJ900101HDFRPN03',
                            'pais_nacionalidad' => 'MX',
                            'actividad_economica' => '4330100',
                            'documento_identificacion' => [
                                'tipo_identificacion' => 1,
                                'numero_identificacion' => 'INE123456'
                            ]
                        ]
                    ],
                    'tipo_domicilio' => [
                        'nacional' => [
                            'colonia' => 'CENTRO',
                            'calle' => 'AV REFORMA',
                            'numero_exterior' => '10',
                            'codigo_postal' => '06000'
                        ]
                    ],
                    'telefono' => [
                        'clave_pais' => 'MX',
                        'numero_telefono' => '5512345678',
                        'correo_electronico' => 'QA_AVI@MAIL.COM'
                    ]
                ],
                'dueno_beneficiario' => [
                    ['tipo_persona' => ['persona_fisica' => [
                        'nombre' => 'ANA',
                        'apellido_paterno' => 'GARCIA',
                        'apellido_materno' => 'LOPEZ',
                        'fecha_nacimiento' => '19920202',
                        'rfc' => 'GALA920202AB1',
                        'curp' => 'GALA920202MDFRPN04',
                        'pais_nacionalidad' => 'MX'
                    ]]],
                    ['tipo_persona' => ['persona_moral' => [
                        'denominacion_razon' => 'EMPRESA QA',
                        'fecha_constitucion' => '20100101',
                        'rfc' => 'EQA100101AB1',
                        'pais_nacionalidad' => 'MX'
                    ]]]
                ],
                'detalle_operaciones' => [
                    'operaciones_fondos' => [
                        'fondos_retirados' => [
                            'retiro' => [[
                                'fecha_hora_operacion' => date('Ymd') . '101010',
                                'instrumento_monetario' => 4,
                                'moneda_operacion' => 1,
                                'monto_operacion' => '1000.00',
                                'datos_beneficiario' => [
                                    'tipo_persona' => [
                                        'persona_fisica' => [
                                            'nombre' => 'JUAN',
                                            'apellido_paterno' => 'PEREZ',
                                            'apellido_materno' => 'LOPEZ'
                                        ]
                                    ],
                                    'nacionalidad_cuenta' => [
                                        'nacional' => [
                                            'clabe_destino' => '012345678912345678',
                                            'clave_institucion_financiera' => 101
                                        ]
                                    ]
                                ]
                            ]]
                        ]
                    ]
                ]
            ]
        ]]
    ]]
];

$xsdPath = null;
foreach ([__DIR__ . '/../xsd/avi.xsd', __DIR__ . '/../avi.xsd'] as $candidate) {
    if (is_file($candidate)) {
        $xsdPath = $candidate;
        break;
    }
}
$xmlRes = generateAVIXml($payload, $xsdPath);
$xml = $xmlRes['xml'] ?? '';
$xmlErr = $xmlRes['errors'] ?? [];
$assert(!empty($xml), 'genera XML AVI');
$assert(strpos($xml, '<clave_actividad>AVI</clave_actividad>') !== false, 'XML incluye clave_actividad AVI');
$assert(strpos($xml, '<tipo_alerta>100</tipo_alerta>') !== false, 'XML incluye tipo_alerta 100');
$assert(strpos($xml, '<clave_institucion_financiera>101</clave_institucion_financiera>') !== false, 'XML incluye clave_institucion_financiera 101');
$assert(substr_count($xml, '<dueno_beneficiario>') >= 2, 'XML incluye dueños beneficiarios múltiples');
if (!empty($xmlErr)) {
    $errors[] = 'XML validación XSD: ' . implode(' | ', $xmlErr);
}

echo "=== QA AVI REGRESSION ===\n";
echo "OK: " . count($ok) . "\n";
foreach ($ok as $line) {
    echo "  + {$line}\n";
}
if (!empty($errors)) {
    echo "ERRORS: " . count($errors) . "\n";
    foreach ($errors as $line) {
        echo "  - {$line}\n";
    }
    exit(1);
}
echo "RESULT: PASS\n";
exit(0);

