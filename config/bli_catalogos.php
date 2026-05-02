<?php
/**
 * Catálogos base BLI (Fracción IX - Blindaje).
 * Nota:
 * - Se prioriza operación base con estructura ampliable.
 * - Los catálogos específicos BLI se pueden completar cuando se reciba
 *   el paquete oficial UIF definitivo.
 */

require_once __DIR__ . '/din_catalogos.php';

if (!isset($BLI_CATALOGOS) || !is_array($BLI_CATALOGOS)) {
    $BLI_CATALOGOS = [];
}

$BLI_CATALOGOS['prioridad'] = [
    '1' => '1 - Normal',
    '2' => '2 - 24 horas con operaciones'
];

$BLI_CATALOGOS['clave_actividad'] = [
    'BLI' => 'Servicios de blindaje de vehículos terrestres e inmuebles'
];

$BLI_CATALOGOS['exento'] = [
    '0' => 'No',
    '1' => 'Sí'
];

$BLI_CATALOGOS['tipo_alerta'] = [
    '100'  => 'Sin alerta.',
    '2601' => 'El cliente o usuario se rehúsa a proporcionar documentos personales que lo identifiquen.',
    '2602' => 'El servicio otorgado es pagado por un tercero sin relación aparente con el cliente o usuario.',
    '2603' => 'El cliente o usuario solicita múltiples servicios en diversos vehículos y/o inmuebles en un periodo muy corto de tiempo sin que su actividad lo justifique.',
    '2604' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación.',
    '2605' => 'Al cliente o usuario parece no importarle pagar precios superiores a los del mercado con la finalidad de que la operación se realice fuera de los parámetros establecidos.',
    '2606' => 'El cliente o usuario no quiere ser relacionado con la contratación del servicio.',
    '2607' => 'El servicio de blindaje no es acorde con la actividad económica o giro mercantil declarado por el cliente o usuario.',
    '2608' => 'Hay indicios, o certeza, que las partes no están actuando en nombre propio y están tratando de ocultar la identidad del cliente o usuario real.',
    '2609' => 'Uso de divisas en efectivo sin justificación alguna.',
    '2610' => 'El cliente o usuario liquida la operación por medio de una transferencia proveniente de un país extranjero.',
    '2611' => 'El cliente o usuario insiste en liquidar o pagar la operación en efectivo rebasando el umbral permitido en la Ley.',
    '2612' => 'El cliente o usuario intenta sobornar, extorsionar o amenaza al vendedor con el fin de realizar la operación fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso.',
    '2613' => 'La información y documentación presentada por el cliente o usuario es inconsistente o de difícil verificación por parte del Sujeto Obligado.',
    '2614' => 'El cliente o usuario contrata el servicio vía remota (ej. teléfono/internet) y da justificaciones para evitar ir a la agencia.',
    '2615' => 'El cliente o usuario solicita el blindaje de bienes muebles o partes de inmuebles poco comunes o de poco valor.',
    '2616' => 'El nombre del solicitante del servicio es diferente al nombre del dueño real del bien a blindarse.',
    '2617' => 'El cliente o usuario pretende liquidar o pagar la operación con monedas virtuales.',
    '9999' => 'Otra alerta.'
];

$BLI_CATALOGOS['tipo_operacion'] = [
    '901' => 'Servicios de blindaje'
];

$BLI_CATALOGOS['tipo_bien'] = [
    '1' => 'Vehículo terrestre',
    '2' => 'Inmueble'
];

// Compatibilidad con versión inicial del formulario BLI.
$BLI_CATALOGOS['tipo_bien_blindado'] = $BLI_CATALOGOS['tipo_bien'];

$BLI_CATALOGOS['estado_bien'] = [
    // Catálogo base operativo (pendiente de catálogo UIF final BLI).
    '1' => 'Nuevo',
    '2' => 'Usado'
];

$BLI_CATALOGOS['nivel_blindaje'] = [
    '1' => 'Nivel A',
    '2' => 'Nivel B',
    '3' => 'Nivel B Plus',
    '4' => 'Nivel C',
    '5' => 'Nivel C Plus',
    '6' => 'Nivel D',
    '7' => 'Nivel E'
];

// Compatibilidad con implementaciones previas.
$BLI_CATALOGOS['tipo_blindaje'] = $BLI_CATALOGOS['nivel_blindaje'];

$BLI_CATALOGOS['parte_blindada'] = [
    '1' => 'Ventanas',
    '2' => 'Puertas',
    '3' => 'Cuarto Expuesto',
    '4' => 'Cuarto Oculto',
    '5' => 'Bodegas',
    '6' => 'Bóveda',
    '7' => 'Bardas o paredes exteriores',
    '9' => 'Otros'
];

$BLI_CATALOGOS['instrumento_monetario'] = $DIN_CATALOGOS['instrumento_monetario'] ?? [
    '1' => 'Efectivo',
    '2' => 'Tarjeta de crédito',
    '3' => 'Tarjeta de débito',
    '8' => 'Transferencia interbancaria',
    '99' => 'Otros'
];

$BLI_CATALOGOS['moneda'] = $DIN_CATALOGOS['moneda'] ?? [
    '1' => 'MXN',
    '2' => 'USD',
    '3' => 'EUR'
];

$BLI_CATALOGOS['tipo_inmueble'] = [
    '1' => 'Casa / Casa en condominio',
    '2' => 'Departamento',
    '3' => 'Edificio habitacional',
    '4' => 'Edificio comercial',
    '5' => 'Edificio oficinas',
    '6' => 'Local comercial independiente',
    '7' => 'Local en centro comercial',
    '8' => 'Oficina',
    '9' => 'Bodega comercial',
    '10' => 'Bodega industrial',
    '11' => 'Nave Industrial',
    '17' => 'Rancho / Hacienda / Quinta',
    '99' => 'Otro'
];

$BLI_CATALOGOS['pais'] = $DIN_CATALOGOS['pais'] ?? [];
$BLI_CATALOGOS['actividad_economica'] = $DIN_CATALOGOS['actividad_economica'] ?? [];
$BLI_CATALOGOS['giro_mercantil'] = $DIN_CATALOGOS['giro_mercantil'] ?? [];

if (!function_exists('bliCatalogoOptions')) {
    /**
     * Genera <option> HTML seguro para un catálogo BLI.
     */
    function bliCatalogoOptions(string $key, ?string $selected = null, ?callable $labeler = null, bool $withPlaceholder = true): string
    {
        global $BLI_CATALOGOS;
        $items = $BLI_CATALOGOS[$key] ?? [];
        $html = '';
        if ($withPlaceholder) {
            $html .= '<option value="">-- Seleccione --</option>';
        }
        foreach ($items as $k => $v) {
            $label = $labeler ? $labeler((string)$k, (string)$v) : ((string)$k . ' - ' . (string)$v);
            $sel = ((string)$selected === (string)$k) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        return $html;
    }
}
