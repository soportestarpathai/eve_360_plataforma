<?php
/**
 * Catálogos JYS (Fracción I — Juegos con apuesta, concursos o sorteos).
 *
 * Nota:
 * - Se incluyen catálogos UIF compartidos (país, moneda, instrumento, actividad, giro).
 * - Los catálogos específicos JYS se dejan con valores mínimos operativos hasta cargar
 *   el catálogo definitivo del instructivo.
 */

$JYS_CATALOGOS = [];

$JYS_CATALOGOS['clave_actividad'] = [
    'JYS' => 'Juegos con apuesta, concursos o sorteos',
];

$JYS_CATALOGOS['prioridad'] = [
    '1' => '1 - Normal',
    '2' => '2 - 24 horas con operaciones',
];

$JYS_CATALOGOS['exento'] = [
    '0' => 'No',
    '1' => 'Si',
];

// Catálogo de tipo de alerta JYS (UIF).
$JYS_CATALOGOS['tipo_alerta'] = [
    '100' => 'Sin alerta.',
    '2101' => 'El cliente o usuario introduce fondos en las máquinas de juego y de inmediato pide la devolución de los mismos en forma de créditos.',
    '2102' => 'El cliente o usuario solicita el pago del crédito de una máquina de juego sin haber ganado un premio.',
    '2103' => 'El cliente o usuario solicita los pagos del crédito de diversas máquinas por un monto elevado.',
    '2104' => 'Se observa un cambio drástico en el patrón de gasto o apuesta del cliente o usuario.',
    '2105' => 'El cliente o usuario realiza apuestas en juegos en los que cubre ambos lados de la apuesta de manera recurrente.',
    '2106' => 'El cliente o usuario compra fichas o crédito para jugar y solicita el reintegro después de haber tenido poca actividad de juego.',
    '2107' => 'El cliente o usuario realiza múltiples retiros de crédito o solicitudes de reintegro de fichas en el mismo día.',
    '2108' => 'El cliente o usuario realiza la compra de crédito o fichas en efectivo y solicita el reintegro por medio de un cheque o transferencia.',
    '2109' => 'Varios clientes o usuarios solicitan el pago del crédito obtenido por medio de una transferencia o cheque a nombre de un mismo sujeto.',
    '2110' => 'El cliente o usuario solicita el pago del crédito o premio por medio de varios cheques al portador.',
    '2111' => 'El cliente o usuario realiza operaciones frecuentes por debajo del umbral de identificación o aviso.',
    '2112' => 'El cliente o usuario solicita que el pago de un premio o fichas sea realizado por medio de una combinación de efectivo, cheques o transferencias.',
    '2113' => 'El cliente o usuario frecuentemente realiza reclamaciones de premios de alto valor ganados por sorteos.',
    '2114' => 'El cliente o usuario solicita el pago de premios o fichas sin que se tenga registro de que dicho cliente o usuario haya comprado billetes de lotería, crédito o fichas para jugar.',
    '2115' => 'El cliente o usuario se rehúsa a proporcionar documentos personales que lo identifiquen.',
    '2116' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación.',
    '2117' => 'La operación no es acorde con la actividad económica o giro mercantil declarado por el cliente o usuario.',
    '2118' => 'Hay indicios, o certeza, que el cliente o usuario no está actuando en nombre propio y está tratando de ocultar la identidad del cliente o usuario real.',
    '2119' => 'Uso de divisas en efectivo sin justificación alguna.',
    '2120' => 'El cliente o usuario liquida la operación por medio de una transferencia proveniente de un país extranjero.',
    '2121' => 'El cliente o usuario insiste en liquidar o pagar la operación en efectivo rebasando el umbral permitido en la Ley.',
    '2122' => 'El cliente o usuario intenta sobornar, extorsionar o amenaza con el fin de realizar la operación fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso.',
    '2123' => 'La información y documentación presentada por el cliente o usuario es inconsistente o de difícil verificación por parte del Sujeto Obligado.',
    '2124' => 'El cliente o usuario no quiere ser relacionado con la operación realizada.',
    '2125' => 'El cliente o usuario o personas relacionadas con él realizan múltiples operaciones en un periodo muy corto sin razón aparente.',
    '2126' => 'El cliente o usuario solicita que el pago de los premios sea realizado a la cuenta bancaria de una tercera persona.',
    '2127' => 'La operación la paga un tercero sin relación aparente con el cliente o usuario.',
    '9999' => 'Otra alerta.',
];

// Catálogo de tipo de operación JYS (UIF).
$JYS_CATALOGOS['tipo_operacion'] = [
    '101' => 'Venta de boletos /fichas /recibos u otros instrumentos de juego similares',
    '102' => 'Pago de boletos /fichas /recibos u otros instrumentos de juego similares',
    '103' => 'Pago de premios',
];
$JYS_CATALOGOS['linea_negocio'] = [
    '1' => 'Hipódromo',
    '2' => 'Galgódromo',
    '3' => 'Frontones',
    '4' => 'Centros de Apuesta Remotas (libros foráneos)',
    '5' => 'Salas de Sorteos de números',
    '6' => 'Cruce de apuestas en ferias',
    '7' => 'Carreras de caballos en escenarios temporales',
    '8' => 'Peleas de gallos en escenarios temporales',
    '9' => 'Sorteos',
    '10' => 'Concursos',
];
$JYS_CATALOGOS['medio_operacion'] = [
    '1' => 'Presencial',
    '2' => 'Internet',
    '3' => 'Telefónica-voz',
    '4' => 'Telefónica-mensajedetexto',
    '5' => 'Otro',
];
$JYS_CATALOGOS['bien_liquidacion'] = [
    '1' => 'Inmueble',
    '2' => 'Vehículo terrestre',
    '3' => 'Vehículo aéreo',
    '4' => 'Vehículo marítimo',
    '5' => 'Piedras Preciosas',
    '6' => 'Metales Preciosos',
    '7' => 'Joyas o relojes',
    '8' => 'Obras de arte o antigüedades',
    '99' => 'Otro (Especificar)',
];

// Reuso de catálogos compartidos.
if (file_exists(__DIR__ . '/din_catalogos.php')) {
    require_once __DIR__ . '/din_catalogos.php';
    $din = isset($DIN_CATALOGOS) ? $DIN_CATALOGOS : [];
    $JYS_CATALOGOS['instrumento_monetario'] = $din['instrumento_monetario'] ?? [];
    $JYS_CATALOGOS['moneda'] = $din['moneda'] ?? [];
}

if (file_exists(__DIR__ . '/tsc_catalogos.php')) {
    require_once __DIR__ . '/tsc_catalogos.php';
    $tsc = isset($TSC_CATALOGOS) ? $TSC_CATALOGOS : [];
    $JYS_CATALOGOS['pais'] = $tsc['pais'] ?? [];
    $JYS_CATALOGOS['actividad_economica'] = $tsc['actividad_economica'] ?? [];
    $JYS_CATALOGOS['giro_mercantil'] = $tsc['giro_mercantil'] ?? [];
}

if (file_exists(__DIR__ . '/spr_catalogos.php')) {
    require_once __DIR__ . '/spr_catalogos.php';
    $spr = isset($SPR_CATALOGOS) ? $SPR_CATALOGOS : [];
    $JYS_CATALOGOS['tipo_inmueble'] = $spr['tipo_inmueble'] ?? [];
}

if (!function_exists('jysCatalogoOptions')) {
    function jysCatalogoOptions(string $catalogoName, string $selectedValue = '', ?array $soloClaves = null, bool $prependPlaceholder = true): string
    {
        global $JYS_CATALOGOS;
        $cat = $JYS_CATALOGOS[$catalogoName] ?? [];
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

if (!function_exists('jysCatalogosJson')) {
    function jysCatalogosJson(): string
    {
        global $JYS_CATALOGOS;
        return json_encode($JYS_CATALOGOS, JSON_UNESCAPED_UNICODE);
    }
}
