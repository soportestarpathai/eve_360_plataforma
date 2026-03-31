<?php
/**
 * Catálogos VEH (Fracción VIII - Vehículos)
 * Nota: mientras se completa el paquete final de catálogos VEH UIF,
 * se reutilizan catálogos generales compartidos (país, moneda, instrumentos, etc.)
 * y se dejan catálogos VEH mínimos para no bloquear operación.
 */

$VEH_CATALOGOS = [];

$VEH_CATALOGOS['clave_actividad'] = [
    'VEH' => 'La comercialización o distribución habitual o profesional de vehículos, nuevos o usados, ya sean aéreos, marítimos o terrestres.',
];

$VEH_CATALOGOS['prioridad'] = [
    '1' => '1 - Normal',
    '2' => '2 - 24 horas con operaciones',
];

$VEH_CATALOGOS['exento'] = [
    '0' => 'No',
    '1' => 'Sí',
];

$VEH_CATALOGOS['tipo_alerta'] = [
    '100'  => 'Sin alerta.',
    '2501' => 'El cliente o usuario se rehúsa a proporcionar documentos personales que lo identifiquen.',
    '2502' => 'La operación es pagada, en parte o en su totalidad, por uno o más terceros sin relación aparente con el cliente o usuario.',
    '2503' => 'El cliente o usuario solicita el reembolso del pago del vehículo poco tiempo después de ser adquirido.',
    '2504' => 'El cliente o usuario compra múltiples vehículos en un periodo muy corto de tiempo, sin tener la preocupación sobre el costo, condiciones o tipo de vehículos.',
    '2505' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación.',
    '2506' => 'El cliente o usuario no quiere ser relacionado con la compra del vehículo.',
    '2507' => 'La operación no es acorde con la actividad económica o giro mercantil declarado por el cliente o usuario.',
    '2508' => 'El cliente o usuario vende su vehículo a precios muy por debajo del precio de mercado (aplica para Sujetos Obligados que se dedican a la compra de autos).',
    '2509' => 'Hay indicios, o certeza, que las partes no están actuando en nombre propio y están tratando de ocultar la identidad del cliente o usuario real.',
    '2510' => 'Uso de divisas en efectivo sin justificación alguna.',
    '2511' => 'El cliente o usuario liquida la operación por medio de una transferencia proveniente de un país extranjero.',
    '2512' => 'El cliente o usuario insiste en liquidar/pagar la operación en efectivo rebasando el umbral permitido en la Ley.',
    '2513' => 'El cliente o usuario intenta sobornar, extorsionar o amenaza con el fin de realizar la operación fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso.',
    '2514' => 'La información y documentación presentada por el cliente o usuario es inconsistente o de difícil verificación por parte del Sujeto Obligado.',
    '2515' => 'Al cliente o usuario parece no importarle pagar precios superiores a los del mercado con la finalidad de que la operación se realice fuera de los parámetros establecidos.',
    '2516' => 'El cliente o usuario o personas relacionadas con él realizan múltiples operaciones en un periodo muy corto de tiempo sin razón aparente.',
    '2517' => 'El cliente o usuario registra el mismo domicilio que otros clientes sin que exista relación aparente entre ellos.',
    '2518' => 'El cliente o usuario es menor de edad y no cuenta con la capacidad de decisión ni la documentación necesaria para realizar la operación.',
    '2519' => 'Hay indicios o certeza de que los vehículos adquiridos son para exportación.',
    '2520' => 'El cliente o usuario solicita que la operación se realice en un lugar distinto al establecimiento sin que exista causa justificada.',
    '2521' => 'El cliente o usuario pretende liquidar la operación con monedas virtuales.',
    '9999' => 'Otra alerta.',
];

$VEH_CATALOGOS['tipo_operacion'] = [
    '801' => 'Venta de vehículo nuevo',
    '802' => 'Venta de vehículo usado',
    '805' => 'Intercambio',
];

// Campo vehicular; puede mostrarse como selección lógica de rama XML.
$VEH_CATALOGOS['tipo_vehiculo'] = [
    'terrestre' => 'Vehículo terrestre',
    'maritimo'  => 'Vehículo marítimo',
    'aereo'     => 'Vehículo aéreo',
];

// Temporal funcional en tanto se recibe catálogo oficial de forma de pago VEH.
$VEH_CATALOGOS['forma_pago'] = [
    '1' => 'Contado',
    '2' => 'Diferido o en parcialidades',
    '3' => 'Dación en pago',
    '4' => 'Préstamo o crédito',
    '5' => 'Permuta',
];

// Instrumento monetario — catálogo VEH.
$VEH_CATALOGOS['instrumento_monetario'] = [
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
    '16' => 'Activos Virtuales',
    '99' => 'Otros',
];

$VEH_CATALOGOS['tipo_blindaje'] = [
    '1' => 'Nivel A',
    '2' => 'Nivel B',
    '3' => 'Nivel B Plus',
    '4' => 'Nivel C',
    '5' => 'Nivel C Plus',
    '6' => 'Nivel D',
    '7' => 'Nivel E',
    '9' => 'No Aplica',
];

if (!function_exists('vehBuildMonedaCatalog')) {
    /**
     * Construye catálogo de moneda VEH en el orden usado por UIF:
     * 1=MXN, 2=USD, 3=EUR ... y bloque especial 159-183.
     */
    function vehBuildMonedaCatalog(array $dinMonedas = []): array
    {
        $byCode = [];
        foreach ($dinMonedas as $desc) {
            $txt = (string)$desc;
            if (preg_match('/^(.+?)\s-\s([A-Z]{3})\s-\s(.+)$/u', $txt, $m)) {
                if (!isset($byCode[$m[2]])) {
                    $byCode[$m[2]] = $txt;
                }
            }
        }

        // Orden solicitado por catálogo de moneda UIF (fracciones operativas).
        $ordenBase = [
            'MXN','USD','EUR','AED','AFN','ALL','AMD','ANG','AOA','ARS','AUD','AWG','AZN','BAM','BBD','BDT','BGN','BHD','BIF','BMD','BND','BOB','BRL','BSD','BTN','BWP','BYR','BZD','CAD','CDF','CHF','CLP','CNY','COP','CRC','CSD','CUC','CUP','CVE','CZK','DJF','DKK','DOP','DZD','EGP','ERN','ETB','FJD','FKP','GBP','GEL','GHS','GIP','GMD','GNF','GTQ','GYD','HKD','HNL','HRK','HTG','HUF','IDR','ILS','INR','IQD','IRR','ISK','JMD','JOD','JPY','KES','KGS','KHR','KMF','KPW','KRW','KWD','KYD','KZT','LAK','LBP','LKR','LRD','LSL','LTL','LVL','LYD','MAD','MDL','MGA','MKD','MMK','MNT','MOP','MRO','MUR','MVR','MWK','MYR','MZN','NAD','NGN','NIO','NOK','NPR','NZD','OMR','PAB','PEN','PGK','PHP','PKR','PLN','PYG','QAR','RON','RSD','RUB','RWF','SAR','SBD','SCR','SDG','SEK','SGD','SHP','SLL','SOS','SRD','SSP','STD','SVC','SYP','SZL','THB','TJS','TMT','TND','TOP','TRY','TTD','TWD','TZS','UAH','UGX','UYU','UZS','VEF','VND','VUV','WST','XDR','YER','ZAR','ZMK','ZMW','ZWL'
        ];

        // Bloque especial (metales/monedas especiales) en 159-183.
        $ordenEspecial = [
            'MXA','MXB','MXC','MXD','MXE','MXG','MXH','MXI','MXJ','MXK','MXL','MXM','MXN','MXO','MXP',
            'XAG','XAU','XFO','XPD','XPT','XAF','XCD','XFU','XPF','XOF'
        ];

        // Fallbacks para códigos que no estén en DIN histórico.
        $fallback = [
            'MXN' => 'Peso Mexicano - MXN - México',
            'USD' => 'Dólar Estadounidense - USD - Estados Unidos',
            'EUR' => 'Euro - EUR - Unión Europea',
            'SSP' => 'Libra de Sudán del Sur - SSP - Sudán del Sur',
            'XDR' => 'Derechos Especiales de Giro - XDR - N/A',
            'ZMK' => 'Kwacha Zambiano - ZMK - Zambia',
            'MXA' => 'Centenario - MXA - México',
            'MXB' => 'Azteca - MXB - México',
            'MXC' => '1/2 Hidalgo - MXC - México',
            'MXD' => '1/4 Hidalgo - MXD - México',
            'MXE' => '1/5 Hidalgo - MXE - México',
            'MXG' => '1 Oz Libertad de Oro - MXG - México',
            'MXH' => '1/2 Oz Libertad de Oro - MXH - México',
            'MXI' => '1/4 Oz Libertad de Oro - MXI - México',
            'MXJ' => '1/10 Oz Libertad de Oro - MXJ - México',
            'MXK' => '1/20 Oz Libertad de Oro - MXK - México',
            'MXL' => '1 Oz Libertad de Plata - MXL - México',
            'MXM' => '1/2 Oz Libertad de Plata - MXM - México',
            'MXO' => '1/10 Oz Libertad de Plata - MXO - México',
            'MXP' => '1/20 Oz Libertad de Plata - MXP - México',
            'XAG' => 'Onza de Plata - XAG - N/A',
            'XAU' => 'Onza de Oro - XAU - N/A',
            'XFO' => 'Franco de Oro (Special settlement currency) - XFO - N/A',
            'XPD' => 'Onza de Paladio - XPD - N/A',
            'XPT' => 'Onza de Platino - XPT - N/A',
            'XAF' => 'Franco CFA de Africa Central - XAF - N/A',
            'XCD' => 'Dólar del Caribe Oriental - XCD - N/A',
            'XFU' => 'Franco UIC (Special settlement currency) - XFU - N/A',
            'XPF' => 'Franco CFP - XPF - N/A',
            'XOF' => 'Franco CFA de Africa Occidental - XOF - N/A',
        ];
        $fallbackEspecial = [
            'MXA' => 'Centenario - MXA - México',
            'MXB' => 'Azteca - MXB - México',
            'MXC' => '1/2 Hidalgo - MXC - México',
            'MXD' => '1/4 Hidalgo - MXD - México',
            'MXE' => '1/5 Hidalgo - MXE - México',
            'MXG' => '1 Oz Libertad de Oro - MXG - México',
            'MXH' => '1/2 Oz Libertad de Oro - MXH - México',
            'MXI' => '1/4 Oz Libertad de Oro - MXI - México',
            'MXJ' => '1/10 Oz Libertad de Oro - MXJ - México',
            'MXK' => '1/20 Oz Libertad de Oro - MXK - México',
            'MXL' => '1 Oz Libertad de Plata - MXL - México',
            'MXM' => '1/2 Oz Libertad de Plata - MXM - México',
            'MXN' => '1/4 Oz Libertad de Plata - MXN - México',
            'MXO' => '1/10 Oz Libertad de Plata - MXO - México',
            'MXP' => '1/20 Oz Libertad de Plata - MXP - México',
            'XAG' => 'Onza de Plata - XAG - N/A',
            'XAU' => 'Onza de Oro - XAU - N/A',
            'XFO' => 'Franco de Oro (Special settlement currency) - XFO - N/A',
            'XPD' => 'Onza de Paladio - XPD - N/A',
            'XPT' => 'Onza de Platino - XPT - N/A',
            'XAF' => 'Franco CFA de Africa Central - XAF - N/A',
            'XCD' => 'Dólar del Caribe Oriental - XCD - N/A',
            'XFU' => 'Franco UIC (Special settlement currency) - XFU - N/A',
            'XPF' => 'Franco CFP - XPF - N/A',
            'XOF' => 'Franco CFA de Africa Occidental - XOF - N/A',
        ];

        $out = [];
        $k = 1;
        foreach ($ordenBase as $code) {
            if (isset($byCode[$code])) {
                $out[(string)$k] = $byCode[$code];
            } elseif (isset($fallback[$code])) {
                $out[(string)$k] = $fallback[$code];
            } else {
                $out[(string)$k] = $code . ' - ' . $code . ' - N/A';
            }
            $k++;
        }

        $k = 159;
        foreach ($ordenEspecial as $code) {
            if (isset($fallbackEspecial[$code])) {
                $out[(string)$k] = $fallbackEspecial[$code];
            } elseif (isset($byCode[$code])) {
                $out[(string)$k] = $byCode[$code];
            } else {
                $out[(string)$k] = $code . ' - ' . $code . ' - N/A';
            }
            $k++;
        }

        ksort($out, SORT_NUMERIC);
        return $out;
    }
}

if (file_exists(__DIR__ . '/din_catalogos.php')) {
    require_once __DIR__ . '/din_catalogos.php';
    $din = isset($DIN_CATALOGOS) ? $DIN_CATALOGOS : [];
    if (empty($VEH_CATALOGOS['instrumento_monetario'])) {
        $VEH_CATALOGOS['instrumento_monetario'] = $din['instrumento_monetario'] ?? [];
    }
    $VEH_CATALOGOS['moneda'] = vehBuildMonedaCatalog($din['moneda'] ?? []);
    $VEH_CATALOGOS['pais'] = $din['pais'] ?? [];
    $VEH_CATALOGOS['actividad_economica'] = $din['actividad_economica'] ?? [];
    $VEH_CATALOGOS['giro_mercantil'] = $din['giro_mercantil'] ?? [];
}

if (file_exists(__DIR__ . '/tsc_catalogos.php')) {
    require_once __DIR__ . '/tsc_catalogos.php';
    $tsc = isset($TSC_CATALOGOS) ? $TSC_CATALOGOS : [];
    if (empty($VEH_CATALOGOS['pais'])) {
        $VEH_CATALOGOS['pais'] = $tsc['pais'] ?? [];
    }
    if (empty($VEH_CATALOGOS['actividad_economica'])) {
        $VEH_CATALOGOS['actividad_economica'] = $tsc['actividad_economica'] ?? [];
    }
    if (empty($VEH_CATALOGOS['giro_mercantil'])) {
        $VEH_CATALOGOS['giro_mercantil'] = $tsc['giro_mercantil'] ?? [];
    }
}

if (!function_exists('vehCatalogoOptions')) {
    function vehCatalogoOptions(string $catalogoName, string $selectedValue = '', ?array $soloClaves = null, bool $prependPlaceholder = true): string
    {
        global $VEH_CATALOGOS;
        $cat = $VEH_CATALOGOS[$catalogoName] ?? [];
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

if (!function_exists('vehCatalogosJson')) {
    function vehCatalogosJson(): string
    {
        global $VEH_CATALOGOS;
        return json_encode($VEH_CATALOGOS, JSON_UNESCAPED_UNICODE);
    }
}
