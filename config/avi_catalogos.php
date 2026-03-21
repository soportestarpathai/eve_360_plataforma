<?php
/**
 * Catálogos base para AVI (Activos Virtuales) — Fracción XVI
 * Basado en instructivo AV y reutilizando catálogos generales existentes.
 */

require_once __DIR__ . '/din_catalogos.php';
require_once __DIR__ . '/tsc_catalogos.php';

$AVI_CATALOGOS = [];

$AVI_CATALOGOS['clave_actividad'] = [
    'AVI' => 'Activos virtuales (Fracción XVI)'
];

$AVI_CATALOGOS['prioridad'] = $TSC_CATALOGOS['prioridad'] ?? [
    '1' => 'Normal',
    '2' => '24 horas con operaciones'
];

$AVI_CATALOGOS['tipo_alerta'] = [
    '100' => 'Sin alerta',
    '4101' => 'Rapido movimiento de recursos sin justificacion aparente',
    '4102' => 'Poca permanencia de los recursos en la plataforma sin justificacion aparente',
    '4103' => 'Compra y venta de activos virtuales sin importar perdidas o ganancias',
    '4104' => 'Cuentas inactivas por largo periodo que empiezan a operar altos volumenes de forma repentina sin justificacion aparente',
    '4105' => 'Los datos del cliente no coinciden con documentos de identificacion',
    '4106' => 'Cambios repentinos en el perfil transaccional del cliente sin justificacion aparente',
    '4107' => 'Nombre del cliente identificado en listas negras nacionales o internacionales (ONU, OFAC)',
    '4108' => 'El cliente se niega a entregar documentacion que lo identifique',
    '4109' => 'El cliente actua a nombre de un tercero sin declararlo previamente',
    '4110' => 'Ingresos no acorde al perfil transaccional o actividad economica',
    '4111' => 'Inactividad en cuenta tras grandes volumenes de operacion sin justificacion aparente',
    '4112' => 'Envio o recepcion de recursos desde/hacia una cuenta en pais de alto riesgo',
    '4113' => 'El cliente no puede justificar el origen de recursos o es diferente al declarado al inicio',
    '4114' => 'Indicios de que recursos provienen de actividad ilicita',
    '4115' => 'Cliente con multiples cuentas y perfiles de operacion similares sin justificacion aparente',
    '4116' => 'Operaciones desde direccion IP o dispositivo distinto a la actividad normal sin justificacion',
    '4117' => 'Recepcion de recursos de cuenta de un tercero sin justificacion aparente',
    '4118' => 'Envio de recursos a cuenta de un tercero sin justificacion aparente',
    '4119' => 'Persona Politicamente Expuesta identificada como de alto riesgo',
    '4120' => 'Indicios de que no actua en nombre propio y oculta identidad del propietario real',
    '4121' => 'Operaciones fraccionadas por debajo del umbral para evitar aviso',
    '4122' => 'Identificador de transaccion coincide con listas nacionales o internacionales de cadenas ilicitas (OFAC)',
    '9999' => 'Otra alerta'
];

$AVI_CATALOGOS['exento'] = $TSC_CATALOGOS['exento'] ?? [
    '0' => 'No',
    '1' => 'Sí'
];

$AVI_CATALOGOS['pais'] = $TSC_CATALOGOS['pais'] ?? [];
$AVI_CATALOGOS['actividad_economica'] = $TSC_CATALOGOS['actividad_economica'] ?? [];
$AVI_CATALOGOS['giro_mercantil'] = $TSC_CATALOGOS['giro_mercantil'] ?? [];
$AVI_CATALOGOS['instrumento_monetario'] = [
    '1' => 'Efectivo',
    '2' => 'Tarjeta de crédito',
    '3' => 'Tarjeta de débito',
    '4' => 'Transferencia interbancaria',
    '5' => 'Transferencia internacional',
    '6' => 'Fondos de la cuenta en la plataforma',
    '7' => 'Activos virtuales',
    '99' => 'Otros'
];
$AVI_CATALOGOS['clave_institucion_financiera'] = [
    '101' => 'BANCO NACIONAL DE COMERCIO EXTERIOR, SOCIEDAD NACIONAL DE CREDITO, INSTITUCION DE BANCA DE DESARROLLO',
    '102' => 'BANCO NACIONAL DE OBRAS Y SERVICIOS PUBLICOS, SOCIEDAD NACIONAL DE CREDITO, INSTITUCION DE BANCA DE DESARROLLO',
    '103' => 'BANCO NACIONAL DEL EJERCITO, FUERZA AEREA Y ARMADA, SOCIEDAD NACIONAL DE CREDITO, INSTITUCION DE BANCA DE DESARROLLO',
    '104' => 'NACIONAL FINANCIERA, SOCIEDAD NACIONAL DE CREDITO, INSTITUCION DE BANCA DE DESARROLLO',
    '105' => 'BANCO DEL BIENESTAR, SOCIEDAD NACIONAL DE CREDITO, INSTITUCION DE BANCA DE DESARROLLO',
    '106' => 'SOCIEDAD HIPOTECARIA FEDERAL, SOCIEDAD NACIONAL DE CREDITO, INSTITUCION DE BANCA DE DESARROLLO',
    '107' => 'BANCO NACIONAL DE MEXICO, S.A., INTEGRANTE DEL GRUPO FINANCIERO BANAMEX',
    '108' => 'BBVA BANCOMER, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO BBVA BANCOMER',
    '109' => 'BANCO SANTANDER MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO SANTANDER MEXICO',
    '110' => 'HSBC MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO HSBC',
    '111' => 'BANCO DEL BAJIO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '112' => 'BANCO INBURSA, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO INBURSA',
    '113' => 'BANCA MIFEL, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO MIFEL',
    '114' => 'SCOTIABANK INVERLAT, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO SCOTIABANK INVERLAT',
    '115' => 'BANCO REGIONAL, S.A., INSTITUCION DE BANCA MULTIPLE, BANREGIO GRUPO FINANCIERO',
    '116' => 'BANCO INVEX, S.A., INSTITUCION DE BANCA MULTIPLE, INVEX GRUPO FINANCIERO',
    '117' => 'BANSI, S.A., INSTITUCION DE BANCA MULTIPLE',
    '118' => 'BANCA AFIRME, S.A., INSTITUCION DE BANCA MULTIPLE, AFIRME GRUPO FINANCIERO',
    '119' => 'BANCO MERCANTIL DEL NORTE, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO BANORTE',
    '120' => 'ACCENDO BANCO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '121' => 'AMERICAN EXPRESS BANK (MEXICO), S.A., INSTITUCION DE BANCA MULTIPLE',
    '122' => 'BANK OF AMERICA MEXICO, S.A.',
    '123' => 'MUFG BANK MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE FILIAL',
    '124' => 'BANCO J.P. MORGAN, S.A., INSTITUCION DE BANCA MULTIPLE, J.P. MORGAN GRUPO FINANCIERO',
    '125' => 'BANCO MONEX, S.A., INSTITUCION DE BANCA MULTIPLE, MONEX GRUPO FINANCIERO',
    '126' => 'BANCO VE POR MAS, S.A.',
    '127' => 'DEUTSCHE BANK MEXICO, S.A.',
    '128' => 'BANCO CREDIT SUISSE MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO CREDIT SUISSE (MEXICO)',
    '129' => 'BANCO AZTECA, S.A., INSTITUCION DE BANCA MULTIPLE',
    '130' => 'BANCO AUTOFIN MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '131' => 'BARCLAYS BANK MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO BARCLAYS MEXICO',
    '132' => 'BANCO COMPARTAMOS, S.A., INSTITUCION DE BANCA MULTIPLE',
    '133' => 'BANCO AHORRO FAMSA, S.A., INSTITUCION DE BANCA MULTIPLE',
    '134' => 'BANCO MULTIVA, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO MULTIVA',
    '135' => 'BANCO ACTINVER, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO ACTINVER',
    '136' => 'INTERCAM BANCO, S.A., INSTITUCION DE BANCA MULTIPLE, INTERCAM GRUPO FINANCIERO',
    '137' => 'BANCO COPPEL, S.A., INSTITUCION DE BANCA MULTIPLE',
    '138' => 'ABC CAPITAL, S.A., INSTITUCION DE BANCA MULTIPLE',
    '139' => 'BANCO DE INVERSION AFIRME, S.A., INSTITUCION DE BANCA MULTIPLE, AFIRME GRUPO FINANCIERO',
    '140' => 'CONSUBANCO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '141' => 'VOLKSWAGEN BANK, S.A., INSTITUCION DE BANCA MULTIPLE',
    '142' => 'CIBANCO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '143' => 'BANCO BASE, S.A., INSTITUCION DE BANCA MULTIPLE, GRUPO FINANCIERO BASE',
    '144' => 'BANKAOOL, S.A., INSTITUCION DE BANCA MULTIPLE',
    '145' => 'BANCO PAGATODO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '146' => 'BANCO FORJADORES, S.A., INSTITUCION DE BANCA MULTIPLE',
    '147' => 'BANCO INMOBILIARIO MEXICANO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '148' => 'FUNDACION DONDE BANCO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '149' => 'BANCO BANCREA, S.A., INSTITUCION DE BANCA MULTIPLE',
    '150' => 'BANCO FINTERRA, S.A., INSTITUCION DE BANCA MULTIPLE',
    '151' => 'INDUSTRIAL AND COMMERCIAL BANK OF CHINA MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '152' => 'BANCO SABADELL, S.A., INSTITUCION DE BANCA MULTIPLE',
    '153' => 'BANCO SHINHAN DE MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '154' => 'MIZUHO BANK MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '155' => 'BANK OF CHINA MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '156' => 'BANCO S3 MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE',
    '157' => 'BANCO KEB HANA MEXICO, S.A., INSTITUCION DE BANCA MULTIPLE FILIAL'
];
$AVI_CATALOGOS['moneda'] = $DIN_CATALOGOS['moneda'] ?? [];
$AVI_CATALOGOS['tipo_identificacion'] = [
    '1' => 'Credencial para votar',
    '2' => 'Pasaporte',
    '3' => 'Cédula Profesional'
];

$AVI_CATALOGOS['origen_hosting'] = [
    'NACIONAL' => 'Nacional',
    'EXTRANJERO' => 'Extranjero',
    'MIXTO' => 'Mixto',
    'OTRO' => 'Otro'
];

$AVI_CATALOGOS['tipo_operacion'] = [
    'compra' => 'Operaciones de compra',
    'venta' => 'Operaciones de venta',
    'intercambio' => 'Operaciones de intercambio',
    'transferencia_envio' => 'Transferencias enviadas',
    'transferencia_recepcion' => 'Transferencias recibidas',
    'fondos_retiro' => 'Fondos retirados',
    'fondos_deposito' => 'Fondos depositados'
];

$AVI_CATALOGOS['activo_virtual_operado'] = [
    '1001' => 'BITCOIN (BTC)',
    '1002' => 'ETHEREUM (ETH)',
    '1003' => 'RIPPLE (XRP)',
    '1004' => 'BITCOIN CASH (BCH)',
    '1005' => 'LITECOIN (LTC)',
    '1006' => 'EOS (EOS)',
    '1007' => 'BINANCE COIN (BNB)',
    '1008' => 'BITCOIN SV (BSV)',
    '1009' => 'STELLAR (XLM)',
    '1010' => 'MONERO (XMR)',
    '1011' => 'CARDANO (ADA)',
    '1012' => 'TRON (TRX)',
    '1013' => 'IOTA (MIOTA)',
    '1014' => 'DASH (DASH)',
    '1015' => 'TEZOS (XTZ)',
    '1016' => 'ETHEREUM CLASSIC (ETC)',
    '1017' => 'NEO (NEO)',
    '1018' => 'COSMOS (ATOM)',
    '1019' => 'NEM (XEM)',
    '1020' => 'ONTOLOGY (ONT)',
    '1021' => 'ZCASH (ZEC)',
    '1022' => 'DOGECOIN (DOGE)',
    '1023' => 'VECHAIN (VET)',
    '1024' => 'DECRED (DCR)',
    '1025' => 'QTUM (QTUM)',
    '1026' => 'V SYSTEMS (VSYS)',
    '1027' => 'BITCOIN GOLD (BTG)',
    '1028' => 'RAVECOIN (RVN)',
    '1029' => 'ABBC COIN (ABBC)',
    '1030' => 'LISK (LSK)',
    '1031' => 'NANO (NANO)',
    '1032' => 'DIGIBYTE (DGB)',
    '1033' => 'BITCOIN DIAMOND (BCD)',
    '1034' => 'WAVES (WAVES)',
    '1035' => 'ICON (ICX)',
    '1036' => 'BYTECOIN (BCN)',
    '1037' => 'BITSHARES (BTS)',
    '1038' => 'THETA (THETA)',
    '1039' => 'HYPERCASH (HC)',
    '1040' => 'MONACOIN (MONA)',
    '1041' => 'ENERGI (NRG)',
    '1042' => 'ALGORAND (ALGO)',
    '1043' => 'KOMODO (KMD)',
    '1044' => 'SIACOIN (SC)',
    '1045' => 'IOST (IOST)',
    '1046' => 'BYTOM (BTM)',
    '1047' => 'ARDOR (ARDR)',
    '1048' => 'VERGE (XVG)',
    '1049' => 'METAVERSE ETP (ETP)',
    '1050' => 'STEEM (STEEM)',
    '999999' => 'OTRO NO CONTENIDO EN EL CATALOGO'
];

// Alineación con instructivo AVI: "NO APLICA" en giro mercantil usa clave 1000000.
if (!isset($AVI_CATALOGOS['giro_mercantil']['1000000'])) {
    $AVI_CATALOGOS['giro_mercantil']['1000000'] =
        $AVI_CATALOGOS['giro_mercantil']['0000000'] ?? 'NO APLICA';
}

if (!function_exists('aviCatalogoOptions')) {
    function aviCatalogoOptions(string $catalogoName, ?string $selected = null): string {
        global $AVI_CATALOGOS;
        $cat = $AVI_CATALOGOS[$catalogoName] ?? [];
        $html = '';
        foreach ($cat as $key => $label) {
            $sel = ((string)$key === (string)$selected) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                  . htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8')
                  . '</option>';
        }
        return $html;
    }
}

if (!function_exists('aviCatalogosJson')) {
    function aviCatalogosJson(): string {
        global $AVI_CATALOGOS;
        return json_encode($AVI_CATALOGOS, JSON_UNESCAPED_UNICODE);
    }
}
