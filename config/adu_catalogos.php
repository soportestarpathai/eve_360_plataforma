<?php
/**
 * Catalogos ADU (Fraccion XIV - Comercio exterior).
 * Base inicial con incisos y catalogos comunes. Sustituir/afinar con XSD
 * y catalogos UIF especificos cuando se reciban completos.
 */

require_once __DIR__ . '/din_catalogos.php';

if (!isset($ADU_CATALOGOS) || !is_array($ADU_CATALOGOS)) {
    $ADU_CATALOGOS = [];
}

$ADU_CATALOGOS['clave_actividad'] = [
    'ADU' => 'Prestacion de servicios de comercio exterior',
];

$ADU_CATALOGOS['prioridad'] = [
    '1' => '1 - Normal',
    '2' => '2 - 24 horas con operaciones',
];

$ADU_CATALOGOS['exento'] = [
    '0' => 'No',
    '1' => 'Si',
];

$ADU_CATALOGOS['actividad_vulnerable'] = [
    'JYS' => 'La venta de boletos, fichas o cualquier otro tipo de comprobante similar para la practica de juegos con apuesta, concursos o sorteos.',
    'TSC' => 'La emision o comercializacion habitual o profesional de tarjetas de servicios o de credito no emitidas por Entidades Financieras.',
    'TPP' => 'La emision o comercializacion habitual o profesional de tarjetas prepagadas, vales o cupones.',
    'TDR' => 'La emision o comercializacion habitual o profesional de monederos electronicos, certificados o cupones.',
    'CHV' => 'La emision y comercializacion habitual o profesional de cheques de viajero.',
    'MPC' => 'Operaciones de mutuo, garantia, prestamos o creditos por sujetos distintos a Entidades Financieras.',
    'INM' => 'Servicios de construccion, desarrollo de bienes inmuebles o intermediacion en transmision de propiedad.',
    'MJR' => 'Comercializacion o intermediacion de metales preciosos, piedras preciosas, joyas o relojes.',
    'OBA' => 'Subasta o comercializacion habitual o profesional de obras de arte.',
    'VEH' => 'Comercializacion o distribucion habitual profesional de vehiculos nuevos o usados.',
    'BLI' => 'Servicios de blindaje de vehiculos terrestres y bienes inmuebles.',
    'TCV' => 'Traslado o custodia de dinero o valores.',
    'SPR' => 'Servicios profesionales independientes en operaciones del articulo 17 fraccion XI.',
    'FEP' => 'Servicios de fe publica en terminos del articulo 17 fraccion XII.',
    'DON' => 'Recepcion de donativos por asociaciones y sociedades sin fines de lucro.',
    'ADU' => 'Servicios de comercio exterior como agente o apoderado aduanal.',
    'ARI' => 'Constitucion de derechos personales de uso o goce de bienes inmuebles.',
];

$ADU_CATALOGOS['tipo_operacion'] = [
    '1401' => 'Importacion',
    '1402' => 'Exportacion',
    '1403' => 'Despacho aduanero',
];

$ADU_CATALOGOS['tipo_alerta'] = [
    '100' => 'Sin alerta.',
    '9999' => 'Otra alerta.',
];

$ADU_CATALOGOS['instrumento_monetario'] = $DIN_CATALOGOS['instrumento_monetario'] ?? [];
$ADU_CATALOGOS['moneda'] = $DIN_CATALOGOS['moneda'] ?? [];
$ADU_CATALOGOS['pais'] = $DIN_CATALOGOS['pais'] ?? [];
$ADU_CATALOGOS['actividad_economica'] = $DIN_CATALOGOS['actividad_economica'] ?? [];
$ADU_CATALOGOS['giro_mercantil'] = $DIN_CATALOGOS['giro_mercantil'] ?? [];

if (!function_exists('aduCatalogoOptions')) {
    function aduCatalogoOptions(string $key, ?string $selected = null, ?callable $labeler = null, bool $withPlaceholder = true): string
    {
        global $ADU_CATALOGOS;
        $items = $ADU_CATALOGOS[$key] ?? [];
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
