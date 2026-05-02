<?php
/**
 * Catalogos ARI (Fraccion XV - Uso o goce de bienes inmuebles).
 */

require_once __DIR__ . '/inm_catalogos.php';
require_once __DIR__ . '/ari_catalogos_extra.php';

if (!isset($ARI_CATALOGOS) || !is_array($ARI_CATALOGOS)) {
    $ARI_CATALOGOS = [];
}

$ARI_CATALOGOS['clave_actividad'] = [
    'ARI' => 'Derechos personales de uso o goce de bienes inmuebles',
];

$ARI_CATALOGOS['prioridad'] = $INM_CATALOGOS['prioridad'] ?? [
    '1' => '1 - Normal',
    '2' => '2 - 24 horas con operaciones',
];
$ARI_CATALOGOS['exento'] = $INM_CATALOGOS['exento'] ?? ['0' => 'No', '1' => 'Si'];
$ARI_CATALOGOS['tipo_alerta'] = [
    '100' => 'Sin alerta.',
    '3001' => 'El cliente o usuario paga u ofrece pagar en efectivo, y por adelantado, las rentas correspondientes a un periodo largo de tiempo sin justificación lógica para ello.',
    '3002' => 'El cliente o usuario paga por adelantado las rentas de un periodo largo de tiempo en efectivo y posteriormente pide la cancelación del contrato y solicita el reembolso del monto pagado por medio de otro instrumento monetario diferente al efectivo.',
    '3003' => 'El cliente o usuario se niega a dar información sobre el uso que se dará al inmueble por arrendar.',
    '3004' => 'Hay indicios, o certeza, de que el inmueble arrendado no está siendo utilizado para el propósito expresado por el cliente o usuario, sino para posibles actividades ilícitas.',
    '3005' => 'El cliente o usuario se rehúsa a proporcionar documentos personales que lo identifiquen.',
    '3006' => 'El pago de las rentas del inmueble es realizado por un tercero sin relación aparente con el cliente o usuario.',
    '3007' => 'El cliente o usuario o personas relacionadas con él, realizan múltiples operaciones de arrendamiento con el mismo Sujeto Obligado, en un periodo muy corto de tiempo y sin razón aparente.',
    '3008' => 'El cliente o usuario no muestra tener interés en las características del inmueble objeto de arrendamiento, o en el monto de la renta y condiciones del contrato.',
    '3009' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación.',
    '3010' => 'El cliente o usuario no quiere ser relacionado con la operación de arrendamiento.',
    '3011' => 'La operación no es acorde con la actividad económica o giro mercantil declarado por el cliente o usuario.',
    '3012' => 'El cliente o usuario ofrece pagar un monto de arrendamiento superior al solicitado con la finalidad de que la operación se realice fuera de los parámetros establecidos.',
    '3013' => 'Hay indicios, o certeza, que las partes no están actuando en nombre propio y están tratando de ocultar la identidad del cliente o usuario real.',
    '3014' => 'El cliente o usuario realiza pagos mediante el uso de divisas en efectivo sin justificación alguna.',
    '3015' => 'El cliente o usuario realiza pagos por medio de transferencia(s) proveniente(s) de un país extranjero.',
    '3016' => 'El cliente o usuario insiste en liquidar o pagar la operación en efectivo rebasando el umbral permitido en la Ley.',
    '3017' => 'El cliente o usuario intenta sobornar, extorsionar o amenaza al intermediario o dueño del inmueble con el fin de realizar la operación fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso.',
    '3018' => 'La información y documentación presentada por el cliente o usuario es inconsistente o de difícil verificación por parte del Sujeto Obligado.',
    '3019' => 'El cliente o usuario realiza los pagos con cheques de caja, sin causa que lo justifique.',
    '3020' => 'Se tiene sospecha de que el inmueble es utilizado como centro que brinda ayuda a la comunidad o grupos vulnerables que pudieran estar vinculados con actividades u organizaciones delictivas.',
    '3021' => 'El cliente o usuario es una persona moral que cambia constantemente de razón social o de representante legal.',
    '3022' => 'Un mismo representante legal solicita arrendamiento a nombre de distintas personas (físicas o morales) que no pertenecen al mismo grupo.',
    '3023' => 'El cliente o usuario pretende liquidar o pagar la operación con monedas virtuales.',
    '3024' => 'El cliente o usuario, su representante o personas ajenas a estos, intenta(n) sobornar o amenazar al Sujeto Obligado o al intermediario para que se agregue una cláusula de subarrendamiento al contrato, sin justificación lógica.',
    '9999' => 'Otra alerta.',
];
$ARI_CATALOGOS['tipo_operacion'] = [
    '1501' => 'Arrendamiento de inmueble',
];
$ARI_CATALOGOS['tipo_inmueble'] = $INM_CATALOGOS['tipo_inmueble'] ?? [];
$ARI_CATALOGOS['forma_pago'] = $INM_CATALOGOS['forma_pago'] ?? [];
$ARI_CATALOGOS['instrumento_monetario'] = $INM_CATALOGOS['instrumento_monetario'] ?? [];
$ARI_CATALOGOS['moneda'] = $INM_CATALOGOS['moneda'] ?? [];
$ARI_CATALOGOS['pais'] = $ARI_CATALOGOS_EXTRA['pais'] ?? [];
$ARI_CATALOGOS['actividad_economica'] = $ARI_CATALOGOS_EXTRA['actividad_economica'] ?? [];
$ARI_CATALOGOS['giro_mercantil'] = $ARI_CATALOGOS_EXTRA['giro_mercantil'] ?? [];

if (!function_exists('ariCatalogoOptions')) {
    function ariCatalogoOptions(string $key, ?string $selected = null, ?callable $labeler = null, bool $withPlaceholder = true): string
    {
        global $ARI_CATALOGOS;
        $items = $ARI_CATALOGOS[$key] ?? [];
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
