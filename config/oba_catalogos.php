<?php
/**
 * Catálogos OBA (Fracción VII - Obras de arte).
 * Estructura tomada de instructivo_oba.xlsx. Los catálogos comunes se
 * reutilizan de los catálogos ya existentes en la plataforma.
 */

require_once __DIR__ . '/din_catalogos.php';

if (!isset($OBA_CATALOGOS) || !is_array($OBA_CATALOGOS)) {
    $OBA_CATALOGOS = [];
}

$OBA_CATALOGOS['clave_actividad'] = [
    'OBA' => 'Subasta o comercialización habitual o profesional de obras de arte',
];

$OBA_CATALOGOS['prioridad'] = [
    '1' => '1 - Normal',
    '2' => '2 - 24 horas con operaciones',
];

$OBA_CATALOGOS['exento'] = [
    '0' => 'No',
    '1' => 'Sí',
];

$OBA_CATALOGOS['tipo_alerta'] = [
    '100' => 'Sin alerta.',
    '3301' => 'El cliente se rehúsa a proporcionar documentos personales que lo identifiquen.',
    '3302' => 'El cliente no muestra tener interés en las características de la obra, el autor de la misma o en el precio y condiciones de la transacción.',
    '3303' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación.',
    '3304' => 'El cliente no quiere ser relacionado con la compra de la obra.',
    '3305' => 'La operación no es acorde con la actividad económica o giro mercantil declarado por el cliente.',
    '3306' => 'Transacciones sucesivas de compra y venta de la misma obra en un periodo corto de tiempo, con cambios injustificados del valor de la misma.',
    '3307' => 'La operación se lleva acabo a un valor de venta o compra significativamente diferente (mucho mayor o mucho menor) a partir del precio estimado de la obra o a los valores de mercado.',
    '3308' => 'Hay indicios, o certeza, que las partes no están actuando en nombre propio y están tratando de ocultar la identidad del cliente real.',
    '3309' => 'El cliente realiza múltiples compras liquidándolas en efectivo por montos elevados sin justificación aparente.',
    '3310' => 'Uso de divisas en efectivo sin justificación alguna.',
    '3311' => 'El cliente liquida la operación por medio de una transferencia proveniente de un país extranjero.',
    '3312' => 'La información y documentación presentada por el cliente es inconsistente o de difícil verificación por parte del Sujeto Obligado.',
    '3313' => 'El cliente insiste en liquidar o pagar la operación en efectivo rebasando el umbral permitido en la Ley.',
    '3314' => 'El cliente intenta sobornar, extorsionar o amenaza con el fin de realizar la operación fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso.',
    '3315' => 'El cliente o personas relacionadas con él realizan múltiples operaciones en un periodo muy corto sin razón aparente.',
    '3316' => 'La operación la paga un tercero sin relación aparente con el cliente.',
    '3317' => 'El agente que vende la obra a la casa de subastas se niega a proporcionar el nombre del propietario real al que representa.',
    '3318' => 'El cliente solicita que la factura refleje un precio mucho menor del pactado, y el resto liquidarlo en efectivo.',
    '3319' => 'El cliente pretende liquidar la operación con monedas virtuales.',
    '9999' => 'Otra alerta.',
];

$OBA_CATALOGOS['tipo_operacion'] = [
    '701' => 'Compra',
    '702' => 'Venta directa',
    '703' => 'Venta por subasta',
];

$OBA_CATALOGOS['tipo_objeto'] = [
    '1' => 'Pintura',
    '2' => 'Escultura',
    '3' => 'Grabado',
    '4' => 'Dibujo',
    '5' => 'Fotografía',
    '6' => 'Artes aplicadas (diseño gráfico, industrial, moda, decoración)',
    '7' => 'Artes decorativas (cerámica, joyería, muebles)',
    '8' => 'Antigüedades',
    '9' => 'Artículos Coleccionables',
    '99' => 'Otros',
];

$OBA_CATALOGOS['forma_pago'] = [
    '1' => 'Contado',
    '2' => 'Diferido o en parcialidades',
    '3' => 'Dación en pago',
    '4' => 'Préstamo o crédito',
    '5' => 'Permuta',
];

$OBA_CATALOGOS['instrumento_monetario'] = $DIN_CATALOGOS['instrumento_monetario'] ?? [
    '1' => 'Efectivo',
    '2' => 'Tarjeta de Crédito',
    '3' => 'Tarjeta de Débito',
    '4' => 'Tarjeta de Prepago',
    '5' => 'Cheque Nominativo',
    '6' => 'Cheque de Caja',
    '7' => 'Cheques de Viajero',
    '8' => 'Transferencia Interbancaria',
    '9' => 'Transferencia Misma Institución',
    '10' => 'Transferencia Internacional',
    '11' => 'Orden de Pago',
    '12' => 'Giro',
    '13' => 'Oro o Platino Amonedados',
    '14' => 'Plata Amonedada',
    '15' => 'Metales Preciosos',
    '16' => 'Activos Virtuales',
    '99' => 'Otros',
];

$OBA_CATALOGOS['moneda'] = $DIN_CATALOGOS['moneda'] ?? [
    '1' => 'MXN',
    '2' => 'USD',
    '3' => 'EUR',
];

$OBA_CATALOGOS['pais'] = $DIN_CATALOGOS['pais'] ?? [];
$OBA_CATALOGOS['actividad_economica'] = $DIN_CATALOGOS['actividad_economica'] ?? [];
$OBA_CATALOGOS['giro_mercantil'] = $DIN_CATALOGOS['giro_mercantil'] ?? [];

if (!function_exists('obaCatalogoOptions')) {
    function obaCatalogoOptions(string $key, ?string $selected = null, ?callable $labeler = null, bool $withPlaceholder = true): string
    {
        global $OBA_CATALOGOS;
        $items = $OBA_CATALOGOS[$key] ?? [];
        $html = $withPlaceholder ? '<option value="">-- Seleccione --</option>' : '';
        foreach ($items as $k => $v) {
            $label = $labeler ? $labeler((string)$k, (string)$v) : ((string)$k . ' - ' . (string)$v);
            $sel = ((string)$selected === (string)$k) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        return $html;
    }
}

if (!function_exists('obaCatalogosJson')) {
    function obaCatalogosJson(): string
    {
        global $OBA_CATALOGOS;
        return json_encode($OBA_CATALOGOS, JSON_UNESCAPED_UNICODE);
    }
}
