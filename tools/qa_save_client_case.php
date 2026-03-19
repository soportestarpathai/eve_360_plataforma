<?php
/**
 * QA runner: ejecuta un caso puntual de alta de cliente contra api/save_client.php.
 * Uso:
 *   php tools/qa_save_client_case.php fisica_pre
 *   php tools/qa_save_client_case.php fisica_acu
 *   php tools/qa_save_client_case.php moral_pre
 *   php tools/qa_save_client_case.php moral_acu
 *   php tools/qa_save_client_case.php fide_pre
 *   php tools/qa_save_client_case.php fide_acu
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se ejecuta por CLI.\n");
    exit(1);
}

// Evita warnings por permisos en session.save_path del entorno WAMP cuando se ejecuta por CLI.
$localSessionPath = __DIR__ . '/../.tmp_sessions';
if (!is_dir($localSessionPath)) {
    @mkdir($localSessionPath, 0777, true);
}
if (is_dir($localSessionPath)) {
    @session_save_path($localSessionPath);
}

$case = strtolower(trim((string)($argv[1] ?? '')));
if ($case === '') {
    fwrite(STDERR, "Caso requerido.\n");
    exit(1);
}

$today = date('Y-m-d');
$nonce = date('YmdHis') . '_' . random_int(1000, 9999);
$contract = 'QA-MTX-' . strtoupper($case) . '-' . $nonce;

$base = [
    'no_contrato' => $contract,
    'alias' => 'QA ' . strtoupper($case),
    'fecha_apertura' => $today,
    'id_status' => '1',
    'kyc_id_actividad' => '1',
    'kyc_antiguedad_anios' => '3',
    'kyc_id_origen_recursos' => '1',
    'kyc_id_profesion' => '1'
];

switch ($case) {
    case 'fisica_pre':
        $_POST = $base + [
            'id_tipo_persona' => '1',
            'es_preregistro' => '1',
            'fisica_nombre' => 'Juan',
            'fisica_ap_paterno' => 'Prueba',
            'fisica_ap_materno' => 'QA',
            'fisica_fecha_nacimiento' => '1990-01-01',
            'fisica_tax_id' => 'QAPR900101AB1',
            'fisica_curp' => '',
            'kyc_empleo_actual' => '',
            'kyc_id_ocupacion' => '',
            'kyc_nivel_estudios' => '',
            'kyc_tiene_familiar_pep' => ''
        ];
        break;

    case 'fisica_acu':
        $_POST = $base + [
            'id_tipo_persona' => '1',
            'fisica_nombre' => 'Juan',
            'fisica_ap_paterno' => 'Prueba',
            'fisica_ap_materno' => 'QA',
            'fisica_fecha_nacimiento' => '1990-01-01',
            'fisica_tax_id' => 'QAPR900101AB1',
            'fisica_curp' => '',
            'kyc_empleo_actual' => 'Analista QA',
            'kyc_id_ocupacion' => '1',
            'kyc_nivel_estudios' => 'Licenciatura',
            'kyc_tiene_familiar_pep' => '0',
            'nacionalidad_id' => ['157'],
            'ident_tipo' => ['1'],
            'ident_numero' => ['QA-ID-' . $nonce],
            'ident_vencimiento' => ['2030-12-31'],
            'dir_tipo' => ['1'],
            'dir_estado' => ['México'],
            'dir_municipio' => ['Texcoco'],
            'dir_cp' => ['56140'],
            'dir_calle' => ['Calle QA 123'],
            'dir_colonia' => ['La Conchita'],
            'dir_pais' => ['157'],
            'contacto_id_tipo' => ['1'],
            'contacto_valor' => ['qa.fisica@example.com']
        ];
        break;

    case 'moral_pre':
        $_POST = $base + [
            'id_tipo_persona' => '2',
            'es_preregistro' => '1',
            'moral_razon_social' => 'QA Moral SA de CV ' . $nonce,
            'moral_nombre_comercial' => 'QA Moral',
            'moral_fecha_constitucion' => '2005-01-01',
            'moral_tax_id' => 'QAM050101AB1'
        ];
        break;

    case 'moral_acu':
        $_POST = $base + [
            'id_tipo_persona' => '2',
            'moral_razon_social' => 'QA Moral SA de CV ' . $nonce,
            'moral_nombre_comercial' => 'QA Moral',
            'moral_fecha_constitucion' => '2005-01-01',
            'moral_tax_id' => 'QAM050101AB1',
            'nacionalidad_id' => ['157'],
            'ident_tipo' => ['1'],
            'ident_numero' => ['QA-MORAL-ID-' . $nonce],
            'ident_vencimiento' => ['2030-12-31'],
            'dir_tipo' => ['1'],
            'dir_estado' => ['México'],
            'dir_municipio' => ['Texcoco'],
            'dir_cp' => ['56140'],
            'dir_calle' => ['Av Moral QA 456'],
            'dir_colonia' => ['San Miguel Tlaixpan'],
            'dir_pais' => ['157'],
            'contacto_id_tipo' => ['1'],
            'contacto_valor' => ['qa.moral@example.com']
        ];
        break;

    case 'fide_pre':
        $_POST = $base + [
            'id_tipo_persona' => '3',
            'es_preregistro' => '1',
            'fide_numero' => 'FIDE-QA-' . $nonce,
            'fide_institucion' => 'Banco Fiduciario QA'
        ];
        break;

    case 'fide_acu':
        $_POST = $base + [
            'id_tipo_persona' => '3',
            'fide_numero' => 'FIDE-QA-' . $nonce,
            'fide_institucion' => 'Banco Fiduciario QA',
            'nacionalidad_id' => ['157'],
            'ident_tipo' => ['1'],
            'ident_numero' => ['QA-FIDE-ID-' . $nonce],
            'ident_vencimiento' => ['2030-12-31'],
            'dir_tipo' => ['1'],
            'dir_estado' => ['México'],
            'dir_municipio' => ['Texcoco'],
            'dir_cp' => ['56140'],
            'dir_calle' => ['Calle Fideicomiso QA 789'],
            'dir_colonia' => ['San Miguel Tlaixpan'],
            'dir_pais' => ['157'],
            'contacto_id_tipo' => ['1'],
            'contacto_valor' => ['qa.fide@example.com']
        ];
        break;

    default:
        fwrite(STDERR, "Caso no soportado: {$case}\n");
        exit(1);
}

$_FILES = [];

session_id('qamtx-' . substr(md5($case . $nonce), 0, 24));
session_start();
$_SESSION['user_id'] = 1;

$apiDir = realpath(__DIR__ . '/../api');
if ($apiDir === false) {
    fwrite(STDERR, "No se encontró carpeta api.\n");
    exit(1);
}
chdir($apiDir);

include 'save_client.php';
