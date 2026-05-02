<?php
/**
 * Catálogos MPC (Fracción IV - Mutuo, garantía, préstamos o créditos).
 */

$MPC_CATALOGOS = [];

$MPC_CATALOGOS['clave_actividad'] = [
    'MPC' => 'El ofrecimiento habitual o profesional de operaciones de mutuo, garantía, préstamos o créditos (con o sin garantía), por sujetos distintos a entidades financieras.',
];

$MPC_CATALOGOS['prioridad'] = [
    '1' => '1 - Normal',
    '2' => '2 - 24 horas con operaciones',
];

$MPC_CATALOGOS['tipo_operacion'] = [
    '401' => 'Otorgamiento de Mutuo, Préstamo o Crédito sin Garantía',
    '402' => 'Otorgamiento de Mutuo, Préstamo o Crédito con Garantía',
];

$MPC_CATALOGOS['tipo_garantia'] = [
    '2'  => 'Inmueble',
    '3'  => 'Vehículo terrestre',
    '4'  => 'Vehículo aéreo',
    '5'  => 'Vehículo marítimo',
    '6'  => 'Piedras Preciosas',
    '7'  => 'Metales Preciosos',
    '8'  => 'Joyas o relojes',
    '9'  => 'Obras de arte o antigüedades',
    '10' => 'Acciones o partes sociales',
    '11' => 'Derechos fiduciarios',
    '12' => 'Derechos de crédito',
    '15' => 'Garantía Quirografaria',
    '99' => 'Otro (Especificar)',
];

$MPC_CATALOGOS['tipo_inmueble'] = [
    '1'  => 'Casa / Casa en condominio',
    '2'  => 'Departamento',
    '3'  => 'Edificio habitacional',
    '4'  => 'Edificio comercial',
    '5'  => 'Edificio oficinas',
    '6'  => 'Local comercial independiente',
    '7'  => 'Local en centro comercial',
    '8'  => 'Oficina',
    '9'  => 'Bodega comercial',
    '10' => 'Bodega industrial',
    '11' => 'Nave Industrial',
    '12' => 'Terreno urbano habitacional',
    '13' => 'Terreno no urbano habitacional',
    '14' => 'Terreno urbano comercial o industrial',
    '15' => 'Terreno no urbano comercial o industrial',
    '16' => 'Terreno ejidal',
    '17' => 'Rancho / Hacienda / Quinta',
    '18' => 'Huerta',
    '99' => 'Otro',
];

$MPC_CATALOGOS['instrumento_monetario'] = [
    '1'  => 'Efectivo',
    '2'  => 'Tarjeta de Crédito',
    '3'  => 'Tarjeta de Débito',
    '4'  => 'Tarjeta de Prepago',
    '5'  => 'Cheque Nominativo',
    '6'  => 'Cheque de Caja',
    '7'  => 'Cheques de Viajero',
    '8'  => 'Transferencia Interbancaria',
    '9'  => 'Transferencia Misma Institución',
    '10' => 'Transferencia Internacional',
    '11' => 'Orden de Pago',
    '12' => 'Giro',
    '13' => 'Oro o Platino Amonedados',
    '14' => 'Plata Amonedada',
    '15' => 'Metales Preciosos',
];

$MPC_CATALOGOS['tipo_alerta'] = [
    '100'  => 'Sin alerta.',
    '2801' => 'El cliente o usuario se rehúsa a proporcionar documentos personales que lo identifiquen.',
    '2802' => 'El cliente o usuario realiza varias operaciones en un periodo corto de tiempo en las que se desconoce el origen de los objetos empeñados.',
    '2803' => 'La operación de mutuo o crédito se lleva a cabo por medio de una garantía poco usual o que no corresponde con la actividad o ingresos del cliente o usuario.',
    '2804' => 'Hay indicios, o certeza, de que los bienes empeñados son robados o provienen de una actividad ilícita.',
    '2805' => 'El cliente o usuario o personas relacionadas con él realizan múltiples operaciones en un periodo muy corto sin razón aparente.',
    '2806' => 'El cliente o usuario realiza operaciones de manera periódica en las que se liquida el total del monto del préstamo otorgado en efectivo al poco tiempo de haberlo adquirido.',
    '2807' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación.',
    '2808' => 'El cliente o usuario no quiere ser relacionado con la operación realizada.',
    '2809' => 'Hay indicios, o certeza, que las partes no están actuando en nombre propio y están tratando de ocultar la identidad del cliente o usuario real.',
    '2810' => 'El cliente o usuario intenta sobornar, extorsionar o amenaza al vendedor con el fin de realizar la operación fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso.',
    '2811' => 'La información y documentación presentada por el cliente o usuario es inconsistente o de difícil verificación por parte del Sujeto Obligado.',
    '2812' => 'Hay indicios o certeza de que los recursos solicitados se están utilizando para fines distintos a los declarados por el cliente o usuario.',
    '2813' => 'El cliente o usuario registra domicilios distintos cada vez que solicita un préstamo o crédito.',
    '2814' => 'El cliente pretende liquidar la operación con monedas virtuales.',
    '9999' => 'Otra alerta.',
];

if (file_exists(__DIR__ . '/din_catalogos.php')) {
    require_once __DIR__ . '/din_catalogos.php';
    $din = isset($DIN_CATALOGOS) ? $DIN_CATALOGOS : [];
    $MPC_CATALOGOS['moneda'] = $din['moneda'] ?? [];
    $MPC_CATALOGOS['actividad_economica'] = $din['actividad_economica'] ?? [];
    $MPC_CATALOGOS['giro_mercantil'] = $din['giro_mercantil'] ?? [];
}

if (file_exists(__DIR__ . '/tsc_catalogos.php')) {
    require_once __DIR__ . '/tsc_catalogos.php';
    $tsc = isset($TSC_CATALOGOS) ? $TSC_CATALOGOS : [];
    if (empty($MPC_CATALOGOS['moneda'])) $MPC_CATALOGOS['moneda'] = $tsc['moneda'] ?? [];
    if (empty($MPC_CATALOGOS['actividad_economica'])) $MPC_CATALOGOS['actividad_economica'] = $tsc['actividad_economica'] ?? [];
    if (empty($MPC_CATALOGOS['giro_mercantil'])) $MPC_CATALOGOS['giro_mercantil'] = $tsc['giro_mercantil'] ?? [];
    $MPC_CATALOGOS['pais'] = $tsc['pais'] ?? [];
}

if (!function_exists('mpcCatalogoOptions')) {
    function mpcCatalogoOptions(string $catalogoName, string $selectedValue = '', ?array $soloClaves = null, bool $prependPlaceholder = true): string
    {
        global $MPC_CATALOGOS;
        $cat = $MPC_CATALOGOS[$catalogoName] ?? [];
        $html = $prependPlaceholder ? '<option value="">-- Seleccione --</option>' : '';
        foreach ($cat as $clave => $descripcion) {
            if ($soloClaves !== null && !in_array((string)$clave, array_map('strval', $soloClaves), true)) {
                continue;
            }
            $sel = ((string)$clave === (string)$selectedValue) ? ' selected' : '';
            $label = (is_numeric((string)$clave) || ctype_digit((string)$clave))
                ? ((string)$clave . ' - ' . (string)$descripcion)
                : (string)$descripcion;
            $html .= '<option value="' . htmlspecialchars((string)$clave, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                . '</option>';
        }
        return $html;
    }
}

if (!function_exists('mpcCatalogosJson')) {
    function mpcCatalogosJson(): string
    {
        global $MPC_CATALOGOS;
        return json_encode($MPC_CATALOGOS, JSON_UNESCAPED_UNICODE);
    }
}
