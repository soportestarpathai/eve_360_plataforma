<?php
/**
 * Catálogos MJR (Fracción VI - Metales, Joyas y Relojes).
 * Reutiliza país/actividad/giro/moneda desde catálogos generales.
 */

$MJR_CATALOGOS = [];

$MJR_CATALOGOS['clave_actividad'] = [
    'MJR' => 'La comercialización o intermediación habitual o profesional de metales preciosos, piedras preciosas, joyas o relojes.',
];

$MJR_CATALOGOS['prioridad'] = [
    '1' => '1 - Normal',
    '2' => '2 - 24 horas con operaciones',
];

$MJR_CATALOGOS['exento'] = [
    '0' => 'No',
    '1' => 'Sí',
];

$MJR_CATALOGOS['tipo_alerta'] = [
    '100' => 'Sin alerta.',
    '2901' => 'El cliente o usuario se rehúsa a proporcionar documentos personales que lo identifiquen.',
    '2902' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación.',
    '2903' => 'El cliente o usuario compra o vende grandes cantidades de metales preciosos, piedras preciosas, joyas y/o relojes sin justificar su procedencia.',
    '2904' => 'El cliente o usuario o personas relacionadas con él realizan diversas operaciones de compra o venta de grandes cantidades de metales preciosos, piedras preciosas, joyas y/o relojes en un periodo muy corto de tiempo sin justificación aparente.',
    '2905' => 'El cliente o usuario realiza compras indiscriminadas de mercancía (sin importar tamaño, color, precio) de metales preciosos, piedras preciosas, joyas y/o relojes, sin que estén asociadas a su actividad.',
    '2906' => 'El cliente o usuario no quiere ser relacionado con la operación realizada.',
    '2907' => 'La operación no es acorde con la actividad económica o giro mercantil declarado por el cliente o usuario.',
    '2908' => 'Hay indicios, o certeza, que las partes no están actuando en nombre propio y están tratando de ocultar la identidad del cliente o usuario real.',
    '2909' => 'Uso de divisas en efectivo sin justificación alguna.',
    '2910' => 'El cliente o usuario liquida la operación por medio de una transferencia proveniente de un país extranjero.',
    '2911' => 'El cliente o usuario insiste en liquidar o pagar la operación en efectivo rebasando el umbral permitido en la Ley.',
    '2912' => 'El cliente o usuario intenta sobornar, extorsionar o amenaza al vendedor con el fin de realizar la operación fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso.',
    '2913' => 'La información y documentación presentada por el cliente o usuario es inconsistente o de difícil verificación por parte del Sujeto Obligado.',
    '2914' => 'Se conoce que el cliente o usuario recolecta metales preciosos (ej. oro) y después lo funde con la intención de venderlo con una calidad diferente a la declarada.',
    '2915' => 'Exporta e importa joyería, relojes, metales y piedras preciosas de países riesgosos sin que haya justificación aparente.',
    '2916' => 'El cliente o usuario vende pedacería de metales preciosos que podría provenir de actividades ilícitas como el robo.',
    '2917' => 'El cliente o usuario compra pedacería de metales preciosos por cantidades bajas a precios que exceden los de mercado o viceversa.',
    '2918' => 'El cliente o usuario liquida la mayoría de sus operaciones con otras divisas, sin que su actividad lo justifique.',
    '2919' => 'La operación se liquida a través de cheques de caja con la intención de ocultar el origen de los recursos.',
    '2920' => 'Para liquidar sus operaciones el cliente o usuario utiliza recursos de diferentes cuentas, instituciones financieras, denominaciones o con diversos instrumentos monetarios, con la intención de dificultar el rastreo de los recursos.',
    '2921' => 'El cliente o usuario opera en grupos, sin que exista algún parentesco entre ellos.',
    '2922' => 'El cliente o usuario solicita que el pago le sea depositado en diferentes cuentas sin razón aparente.',
    '2923' => 'El cliente o usuario hace uso de servicios de paquetería para entregar la mercancía vendida.',
    '2924' => 'Al cliente o usuario parece no importarle pagar precios superiores a los del mercado con la finalidad de que la operación se realice fuera de los parámetros establecidos.',
    '2925' => 'La operación la paga un tercero sin relación aparente con el cliente o usuario.',
    '9999' => 'Otra alerta.',
];

$MJR_CATALOGOS['tipo_operacion'] = [
    '601' => 'Venta',
    '602' => 'Compra',
];

$MJR_CATALOGOS['tipo_bien'] = [
    '1' => 'Metales Preciosos - Oro',
    '2' => 'Metales Preciosos - Plata',
    '3' => 'Metales Preciosos - Platino',
    '4' => 'Piedras Preciosas - Aguamarinas',
    '5' => 'Piedras Preciosas - Diamantes',
    '6' => 'Piedras Preciosas - Esmeraldas',
    '7' => 'Piedras Preciosas - Rubíes',
    '8' => 'Piedras Preciosas - Topacios',
    '9' => 'Piedras Preciosas - Turquesas',
    '10' => 'Piedras Preciosas - Zafiros',
    '11' => 'Joyas',
    '12' => 'Relojes',
];

$MJR_CATALOGOS['unidad_comercializada'] = [
    '1' => 'Pieza',
    '2' => 'Gramos',
    '3' => 'Kilates',
];

$MJR_CATALOGOS['forma_pago'] = [
    '1' => 'Contado',
    '2' => 'Diferido o en parcialidades',
    '3' => 'Dación en pago',
    '4' => 'Préstamo o crédito',
    '5' => 'Permuta',
];

$MJR_CATALOGOS['instrumento_monetario'] = [
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

if (!function_exists('mjrBuildMonedaCatalog')) {
    function mjrBuildMonedaCatalog(array $dinMonedas = []): array
    {
        if (function_exists('vehBuildMonedaCatalog')) {
            return vehBuildMonedaCatalog($dinMonedas);
        }

        // Fallback mínimo (si no está veh_catalogos cargado).
        $out = [];
        foreach ($dinMonedas as $k => $v) {
            $out[(string)$k] = $v;
        }
        return $out;
    }
}

if (file_exists(__DIR__ . '/veh_catalogos.php')) {
    require_once __DIR__ . '/veh_catalogos.php';
    $veh = isset($VEH_CATALOGOS) ? $VEH_CATALOGOS : [];
    if (!empty($veh['moneda'])) $MJR_CATALOGOS['moneda'] = $veh['moneda'];
    if (!empty($veh['pais'])) $MJR_CATALOGOS['pais'] = $veh['pais'];
    if (!empty($veh['actividad_economica'])) $MJR_CATALOGOS['actividad_economica'] = $veh['actividad_economica'];
    if (!empty($veh['giro_mercantil'])) $MJR_CATALOGOS['giro_mercantil'] = $veh['giro_mercantil'];
}

if (file_exists(__DIR__ . '/din_catalogos.php')) {
    require_once __DIR__ . '/din_catalogos.php';
    $din = isset($DIN_CATALOGOS) ? $DIN_CATALOGOS : [];
    if (empty($MJR_CATALOGOS['moneda'])) $MJR_CATALOGOS['moneda'] = mjrBuildMonedaCatalog($din['moneda'] ?? []);
    if (empty($MJR_CATALOGOS['pais'])) $MJR_CATALOGOS['pais'] = $din['pais'] ?? [];
    if (empty($MJR_CATALOGOS['actividad_economica'])) $MJR_CATALOGOS['actividad_economica'] = $din['actividad_economica'] ?? [];
    if (empty($MJR_CATALOGOS['giro_mercantil'])) $MJR_CATALOGOS['giro_mercantil'] = $din['giro_mercantil'] ?? [];
}

if (file_exists(__DIR__ . '/tsc_catalogos.php')) {
    require_once __DIR__ . '/tsc_catalogos.php';
    $tsc = isset($TSC_CATALOGOS) ? $TSC_CATALOGOS : [];
    if (empty($MJR_CATALOGOS['pais'])) $MJR_CATALOGOS['pais'] = $tsc['pais'] ?? [];
    if (empty($MJR_CATALOGOS['actividad_economica'])) $MJR_CATALOGOS['actividad_economica'] = $tsc['actividad_economica'] ?? [];
    if (empty($MJR_CATALOGOS['giro_mercantil'])) $MJR_CATALOGOS['giro_mercantil'] = $tsc['giro_mercantil'] ?? [];
}

if (!function_exists('mjrCatalogoOptions')) {
    function mjrCatalogoOptions(string $catalogoName, string $selectedValue = '', ?array $soloClaves = null, bool $prependPlaceholder = true): string
    {
        global $MJR_CATALOGOS;
        $cat = $MJR_CATALOGOS[$catalogoName] ?? [];
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

if (!function_exists('mjrCatalogosJson')) {
    function mjrCatalogosJson(): string
    {
        global $MJR_CATALOGOS;
        return json_encode($MJR_CATALOGOS, JSON_UNESCAPED_UNICODE);
    }
}
