<?php
/**
 * Catalogos FES (Fraccion XII - Fe publica, Servidores Publicos).
 */

require_once __DIR__ . '/fep_catalogos.php';

if (!isset($FES_CATALOGOS) || !is_array($FES_CATALOGOS)) {
    $FES_CATALOGOS = [];
}

$FES_CATALOGOS['clave_actividad'] = [
    'FES' => 'Fe publica - Servidores Publicos',
];

$FES_CATALOGOS['prioridad'] = $FEP_CATALOGOS['prioridad'] ?? ['1' => '1 - Normal', '2' => '2 - 24 horas con operaciones'];
$FES_CATALOGOS['si_no'] = $FEP_CATALOGOS['si_no'] ?? ['SI' => 'Si', 'NO' => 'No'];

$FES_CATALOGOS['subactividad'] = [
    'derechos_inmuebles' => '1 - Transmision o constitucion de derechos reales sobre inmuebles',
    'otorgamiento_poder' => '2 - Otorgamiento de poder irrevocable',
    'contrato_mutuo_credito' => '3 - Contrato de mutuo o credito',
    'avaluo' => '4 - Realizacion de avaluos',
    'constitucion_personas_morales' => '5 - Constitucion de personas morales',
    'modificacion_patrimonial' => '6 - Modificacion patrimonial',
];

$FES_CATALOGOS['tipo_alerta'] = [
    '100' => 'Sin alerta',
    '1250' => 'Uno o mas sujetos o partes se rehusan a proporcionar documentos personales.',
    '1251' => 'La operacion o acto parece estar fuera del alcance por ocupacion.',
    '1252' => 'La operacion o acto parece estar fuera del alcance por ingresos.',
    '1253' => 'Indicios de ocultamiento de identidad del beneficiario o controlador real.',
    '1254' => 'Interes inusual en realizar la operacion o acto con rapidez.',
    '1255' => 'Datos falsos o documentos apocrifos.',
    '1256' => 'Intento de soborno o extorsion a una autoridad.',
    '1257' => 'Condiciones especiales poco usuales.',
    '1258' => 'Operacion con organizacion sin fines de lucro no consistente con su objeto.',
    '1259' => 'Historial criminal de sujetos, familiares o personas relacionadas.',
    '1260' => 'Uso de intermediario sin causa justificada.',
    '1261' => 'Multiples operaciones en periodo corto.',
    '1262' => 'Sin interes en caracteristicas del inmueble o condiciones de transaccion.',
    '1263' => 'Valor significativamente diferente al real o de mercado.',
    '1264' => 'Operaciones de mutuo/credito con origen desconocido de bienes empenados.',
    '1265' => 'Garantia poco usual o no acorde con actividad o ingresos.',
    '1266' => 'Garantias o bienes robados o provenientes de actividad ilicita.',
    '1267' => 'Multiples operaciones en periodo muy corto sin razon aparente.',
    '1268' => 'Sin interes en caracteristicas y condiciones del credito.',
    '1269' => 'Liquidacion periodica del prestamo en efectivo al poco tiempo.',
    '9999' => 'Otra alerta',
];

$FES_CATALOGOS['tipo_poder'] = $FEP_CATALOGOS['tipo_poder'] ?? [];
$FES_CATALOGOS['tipo_persona_moral'] = $FEP_CATALOGOS['tipo_persona_moral'] ?? [];
$FES_CATALOGOS['cargo_accionista'] = $FEP_CATALOGOS['cargo_accionista'] ?? [];
$FES_CATALOGOS['entidad_federativa'] = $FEP_CATALOGOS['entidad_federativa'] ?? [];
$FES_CATALOGOS['motivo_constitucion'] = $FEP_CATALOGOS['motivo_constitucion'] ?? [];
$FES_CATALOGOS['tipo_modificacion_capital_fijo'] = $FEP_CATALOGOS['tipo_modificacion_capital_fijo'] ?? [];
$FES_CATALOGOS['tipo_modificacion_capital_variable'] = $FEP_CATALOGOS['tipo_modificacion_capital_variable'] ?? [];
$FES_CATALOGOS['tipo_otorgamiento'] = $FEP_CATALOGOS['tipo_otorgamiento'] ?? [];
$FES_CATALOGOS['tipo_garantia'] = $FEP_CATALOGOS['tipo_garantia'] ?? [];
$FES_CATALOGOS['tipo_bien'] = $FEP_CATALOGOS['tipo_bien'] ?? [];
$FES_CATALOGOS['tipo_inmueble'] = $FEP_CATALOGOS['tipo_inmueble'] ?? [];
$FES_CATALOGOS['moneda'] = $FEP_CATALOGOS['moneda'] ?? [];
$FES_CATALOGOS['pais'] = $FEP_CATALOGOS['pais'] ?? [];
$FES_CATALOGOS['actividad_economica'] = $FEP_CATALOGOS['actividad_economica'] ?? [];
$FES_CATALOGOS['giro_mercantil'] = $FEP_CATALOGOS['giro_mercantil'] ?? [];

$FES_CATALOGOS['tipo_acto'] = [
    '1' => 'Transmision',
    '2' => 'Constitucion',
    '9' => 'Otro',
];

if (!function_exists('fesCatalogoOptions')) {
    function fesCatalogoOptions(string $key, ?string $selected = null, ?callable $labeler = null, bool $withPlaceholder = true): string
    {
        global $FES_CATALOGOS;
        $items = $FES_CATALOGOS[$key] ?? [];
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
