<?php
/**
 * Catálogos DON (Fracción XIII — Donativos)
 * Basado en instructivo DON y catálogos UIF compartidos.
 */

$DON_CATALOGOS = [];

$DON_CATALOGOS['clave_actividad'] = [
    'DON' => 'Donativos',
];

$DON_CATALOGOS['prioridad'] = [
    '1' => '1 - Normal',
    '2' => '2 - 24 horas con operaciones',
];

$DON_CATALOGOS['exento'] = [
    '0' => 'No',
    '1' => 'Sí',
];

// Tipo de Alerta - DON (catálogo UIF compartido por el usuario).
$DON_CATALOGOS['tipo_alerta'] = [
    '100'  => 'Sin alerta.',
    '2701' => 'El donante condiciona o pretende condicionar el otorgamiento del donativo a la imposición de ciertas condiciones poco usuales para el uso del mismo.',
    '2702' => 'El donante otorga uno o varios donativos por un monto elevado sin mostrar interés en el objeto de la organización.',
    '2703' => 'El donante intenta o realiza un donativo en especie de bienes poco usuales o sin relación con el objeto de la organización.',
    '2704' => 'Hay indicios, o certeza, de que los bienes donados provienen de actividades ilícitas.',
    '2705' => 'El donante se rehúsa a proporcionar documentos personales que lo identifiquen.',
    '2706' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación.',
    '2707' => 'El donante no quiere ser relacionado con el donativo.',
    '2708' => 'El monto donado no es acorde con la actividad económica o giro mercantil del donante.',
    '2709' => 'Hay indicios, o certeza, que las partes no están actuando en nombre propio y están tratando de ocultar la identidad del donante real.',
    '2710' => 'El donativo se realiza en efectivo por un monto elevado.',
    '2711' => 'El donativo se realiza usando divisas en efectivo en montos elevados.',
    '2712' => 'El donativo se realiza por medio de una transferencia proveniente de un país extranjero.',
    '2713' => 'La información y documentación presentada por el donante es inconsistente o de difícil verificación por parte del Sujeto Obligado.',
    '2714' => 'El donante realiza un elevado número de donaciones en efectivo y en cantidades elevadas mensualmente, o en periodos identificablemente cortos.',
    '2715' => 'El donante intenta sobornar, extorsionar o amenaza a la institución con el fin de realizar la donación fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso.',
    '9999' => 'Otra alerta.',
];

// Tipos de operación DON (UIF). Se deja opción "Otro" para compatibilidad.
$DON_CATALOGOS['tipo_operacion'] = [
    '1301' => 'Recepción de donativos',
];

$DON_CATALOGOS['bien_donado'] = [
    '1'  => 'Inmueble',
    '2'  => 'Mueble',
    '7'  => 'Otro bien',
    '99' => 'Otro',
];

if (file_exists(__DIR__ . '/din_catalogos.php')) {
    require_once __DIR__ . '/din_catalogos.php';
    $din = isset($DIN_CATALOGOS) ? $DIN_CATALOGOS : [];
    $DON_CATALOGOS['instrumento_monetario'] = $din['instrumento_monetario'] ?? [];
    $DON_CATALOGOS['moneda'] = $din['moneda'] ?? [];
}

if (file_exists(__DIR__ . '/tsc_catalogos.php')) {
    require_once __DIR__ . '/tsc_catalogos.php';
    $tsc = isset($TSC_CATALOGOS) ? $TSC_CATALOGOS : [];
    $DON_CATALOGOS['pais'] = $tsc['pais'] ?? [];
    $DON_CATALOGOS['actividad_economica'] = $tsc['actividad_economica'] ?? [];
    $DON_CATALOGOS['giro_mercantil'] = $tsc['giro_mercantil'] ?? [];
}

if (file_exists(__DIR__ . '/spr_catalogos.php')) {
    require_once __DIR__ . '/spr_catalogos.php';
    $spr = isset($SPR_CATALOGOS) ? $SPR_CATALOGOS : [];
    $DON_CATALOGOS['tipo_inmueble'] = $spr['tipo_inmueble'] ?? [];
}

if (!function_exists('donCatalogoOptions')) {
    function donCatalogoOptions(string $catalogoName, string $selectedValue = '', ?array $soloClaves = null, bool $prependPlaceholder = true): string {
        global $DON_CATALOGOS;
        $cat = $DON_CATALOGOS[$catalogoName] ?? [];
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

if (!function_exists('donCatalogosJson')) {
    function donCatalogosJson(): string {
        global $DON_CATALOGOS;
        return json_encode($DON_CATALOGOS, JSON_UNESCAPED_UNICODE);
    }
}
