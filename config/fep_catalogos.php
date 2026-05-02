<?php
/**
 * Catalogos FEP (Fraccion XII - Fe publica, Notarios y Corredores Publicos).
 */

require_once __DIR__ . '/inm_catalogos.php';
require_once __DIR__ . '/ari_catalogos_extra.php';

if (!isset($FEP_CATALOGOS) || !is_array($FEP_CATALOGOS)) {
    $FEP_CATALOGOS = [];
}

$FEP_CATALOGOS['clave_actividad'] = [
    'FEP' => 'Fe publica - Notarios y Corredores Publicos',
];

$FEP_CATALOGOS['prioridad'] = [
    '1' => '1 - Normal',
    '2' => '2 - 24 horas con operaciones',
];

$FEP_CATALOGOS['exento'] = [
    '0' => 'No',
    '1' => 'Si',
];

$FEP_CATALOGOS['subactividad'] = [
    'otorgamiento_poder' => '1 - Otorgamiento de poder irrevocable',
    'constitucion_personas_morales' => '2 - Constitucion de personas morales',
    'modificacion_patrimonial' => '3 - Modificacion patrimonial',
    'fusion' => '4 - Fusion',
    'escision' => '5 - Escision',
    'compra_venta_acciones' => '6 - Compra o venta de acciones o partes sociales',
    'constitucion_modificacion_fideicomiso' => '7 - Constitucion o modificacion de fideicomiso',
    'cesion_derechos_fideicomitente_fideicomisario' => '8 - Cesion de derechos de fideicomitente/fideicomisario',
    'contrato_mutuo_credito' => '9 - Contrato de mutuo o credito',
    'avaluo' => '10 - Realizacion de avaluos',
];

$FEP_CATALOGOS['tipo_alerta'] = [
    '100' => 'Sin alerta.',
    '3501' => 'El cliente se rehusa a proporcionar documentos personales que lo identifiquen.',
    '3502' => 'El cliente o usuario no muestra interes en las caracteristicas de la operacion.',
    '3503' => 'De acuerdo con medios informativos, se sospecha vinculacion con actividades ilicitas.',
    '3504' => 'El cliente no quiere ser relacionado con la operacion.',
    '3505' => 'La operacion no es acorde con la actividad economica o giro declarado.',
    '3506' => 'Operaciones sucesivas en periodo corto sin justificacion aparente.',
    '3507' => 'La operacion se lleva a cabo por valor significativamente diferente al mercado.',
    '3508' => 'Hay indicios de ocultamiento de identidad del cliente real.',
    '3509' => 'Uso de divisas en efectivo sin justificacion.',
    '3510' => 'Transferencia proveniente de un pais extranjero sin justificacion.',
    '3511' => 'Documentacion inconsistente o de dificil verificacion.',
    '3512' => 'Pago en efectivo rebasando el umbral permitido en la Ley.',
    '3513' => 'Intento de soborno, extorsion o amenaza.',
    '3514' => 'Multiples operaciones en periodo muy corto sin razon aparente.',
    '3515' => 'Pago por un tercero sin relacion aparente.',
    '3516' => 'Operacion con monedas virtuales.',
    '3517' => 'Otra conducta inusual relacionada con la fe publica.',
    '521' => 'Catalogo ejemplo FEP: alerta 521.',
    '1201' => 'Catalogo ejemplo FEP: alerta 1201.',
    '1501' => 'Catalogo ejemplo FEP: alerta 1501.',
    '9999' => 'Otra alerta.',
];

$FEP_CATALOGOS['tipo_poder'] = [
    '1' => 'Actos de administracion',
    '2' => 'Actos de dominio',
    '3' => 'Administracion y dominio',
];

$FEP_CATALOGOS['tipo_persona_moral'] = [
    '1' => 'Sociedad Anonima',
    '2' => 'Sociedad de Responsabilidad Limitada',
    '3' => 'Sociedad Civil',
    '4' => 'Asociacion Civil',
    '5' => 'Sociedad Cooperativa',
    '6' => 'Sociedad en Nombre Colectivo',
    '7' => 'Sociedad en Comandita Simple',
    '8' => 'Sociedad en Comandita por Acciones',
    '9' => 'Sociedad por Acciones Simplificada',
    '10' => 'Institucion de Asistencia Privada',
    '11' => 'Asociacion Religiosa',
    '12' => 'Partido Politico',
    '13' => 'Sindicato',
    '14' => 'Fideicomiso',
    '15' => 'Entidad extranjera',
    '99' => 'Otro',
];

$FEP_CATALOGOS['cargo_accionista'] = [
    '1' => 'Accionista',
    '2' => 'Socio',
    '3' => 'Administrador',
    '4' => 'Consejero',
    '5' => 'Comisario',
];

$FEP_CATALOGOS['entidad_federativa'] = [
    '01' => 'Aguascalientes', '02' => 'Baja California', '03' => 'Baja California Sur',
    '04' => 'Campeche', '05' => 'Coahuila de Zaragoza', '06' => 'Colima',
    '07' => 'Chiapas', '08' => 'Chihuahua', '09' => 'Distrito Federal',
    '10' => 'Durango', '11' => 'Guanajuato', '12' => 'Guerrero',
    '13' => 'Hidalgo', '14' => 'Jalisco', '15' => 'Mexico',
    '16' => 'Michoacan de Ocampo', '17' => 'Morelos', '18' => 'Nayarit',
    '19' => 'Nuevo Leon', '20' => 'Oaxaca', '21' => 'Puebla',
    '22' => 'Queretaro', '23' => 'Quintana Roo', '24' => 'San Luis Potosi',
    '25' => 'Sinaloa', '26' => 'Sonora', '27' => 'Tabasco',
    '28' => 'Tamaulipas', '29' => 'Tlaxcala', '30' => 'Veracruz',
    '31' => 'Yucatan', '32' => 'Zacatecas',
];

$FEP_CATALOGOS['motivo_constitucion'] = ['1' => 'Inicio de actividades', '2' => 'Reestructura', '3' => 'Otro'];
$FEP_CATALOGOS['tipo_modificacion_capital_fijo'] = ['1' => 'Aumento', '2' => 'Disminucion', '3' => 'Sin modificacion'];
$FEP_CATALOGOS['tipo_modificacion_capital_variable'] = $FEP_CATALOGOS['tipo_modificacion_capital_fijo'];
$FEP_CATALOGOS['tipo_fusion'] = ['1' => 'Fusion por incorporacion', '2' => 'Fusion por integracion'];
$FEP_CATALOGOS['tipo_operacion'] = ['1' => 'Compra o venta de acciones o partes sociales'];
$FEP_CATALOGOS['tipo_movimiento'] = ['1' => 'Alta', '2' => 'Baja', '3' => 'Modificacion', '4' => 'Otro'];
$FEP_CATALOGOS['tipo_movimiento_fideicomitente'] = $FEP_CATALOGOS['tipo_movimiento'];
$FEP_CATALOGOS['tipo_movimiento_fideicomisario'] = $FEP_CATALOGOS['tipo_movimiento'];
$FEP_CATALOGOS['tipo_fideicomiso'] = ['1' => 'Traslativo de dominio', '2' => 'Garantia', '3' => 'Administracion', '4' => 'Otro'];
$FEP_CATALOGOS['tipo_cesion'] = ['1' => 'Cesion de derechos de fideicomitente', '2' => 'Cesion de derechos de fideicomisario', '3' => 'Cesion onerosa', '4' => 'Cesion gratuita', '5' => 'Cesion parcial', '6' => 'Cesion total'];
$FEP_CATALOGOS['tipo_otorgamiento'] = ['1' => 'Mutuo', '2' => 'Credito'];
$FEP_CATALOGOS['tipo_garantia'] = [
    '1' => 'Hipotecaria', '2' => 'Prendaria', '3' => 'Fiduciaria', '4' => 'Aval',
    '5' => 'Fianza', '6' => 'Obligacion solidaria', '7' => 'Cesion de derechos',
    '8' => 'Garantia liquida', '9' => 'Garantia mobiliaria', '10' => 'Garantia inmobiliaria',
    '11' => 'Stand by', '12' => 'Carta de credito', '13' => 'Deposito', '99' => 'Otro',
];
$FEP_CATALOGOS['tipo_bien_avaluo'] = [
    '1' => 'Inmueble', '2' => 'Vehiculo', '3' => 'Joya o reloj', '4' => 'Metal o piedra preciosa',
    '5' => 'Obra de arte', '6' => 'Maquinaria/equipo', '7' => 'Activo intangible', '99' => 'Otro',
];
$FEP_CATALOGOS['tipo_bien'] = $FEP_CATALOGOS['tipo_bien_avaluo'];
$FEP_CATALOGOS['si_no'] = ['SI' => 'Si', 'NO' => 'No'];

$FEP_CATALOGOS['tipo_inmueble'] = $INM_CATALOGOS['tipo_inmueble'] ?? [];
$FEP_CATALOGOS['instrumento_monetario'] = $INM_CATALOGOS['instrumento_monetario'] ?? [];
$FEP_CATALOGOS['moneda'] = $INM_CATALOGOS['moneda'] ?? [];
$FEP_CATALOGOS['pais'] = $ARI_CATALOGOS_EXTRA['pais'] ?? [];
$FEP_CATALOGOS['actividad_economica'] = $ARI_CATALOGOS_EXTRA['actividad_economica'] ?? [];
$FEP_CATALOGOS['giro_mercantil'] = $ARI_CATALOGOS_EXTRA['giro_mercantil'] ?? [];

if (!function_exists('fepCatalogoOptions')) {
    function fepCatalogoOptions(string $key, ?string $selected = null, ?callable $labeler = null, bool $withPlaceholder = true): string
    {
        global $FEP_CATALOGOS;
        $items = $FEP_CATALOGOS[$key] ?? [];
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
