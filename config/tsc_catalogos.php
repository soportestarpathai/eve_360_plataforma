<?php
/**
 * Catálogos UIF para TSC (Tarjetas de Servicios de Crédito) — Fracción II
 * Portal de Prevención de Lavado de Dinero — SAT/UIF
 * Actividad: Emisión o comercialización de tarjetas de servicios o de crédito
 * (no emitidas por Entidades Financieras)
 */

$TSC_CATALOGOS = [];

/* ═══════════════════════════════════════════
 * 0. SUBFRACCIÓN II (selección funcional en la plataforma)
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['subfraccion_ii'] = [
    'servicio_credito' => 'Tarjetas de Servicio y Crédito',
    'prepago_cupones' => 'Tarjetas de Prepago y Cupones',
    'devolucion_recompensas' => 'Tarjetas de Devolución y Recompensas',
];

/* ═══════════════════════════════════════════
 * 0b. CLAVE DE ACTIVIDAD (clave_actividad)
 *    TSC/TPP/TDR según subfracción II
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['clave_actividad'] = [
    'TSC' => 'Tarjetas de Servicio y de Crédito (emisión o comercialización habitual/profesional)',
    'TPP' => 'La emisión o comercialización, habitual o profesional de tarjetas prepagadas, vales o cupones, impresos o electrónicos, que puedan ser utilizados o canjeados para la adquisición de bienes o servicios, que no sean emitidos o comercializados por Entidades Financieras.',
    'TDR' => 'La emisión o comercialización, habitual o profesional, de monederos electrónicos, certificados o cupones, en los que sean abonados recursos provenientes de premios, promociones o devoluciones derivadas de recompensas comerciales.',
];

/* ═══════════════════════════════════════════
 * 1. TIPO DE TARJETA UIF — TSC (Fracción II)
 *    Campo: tipo_tarjeta — digito_1_type (XSD: 1 dígito)
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['tipo_tarjeta'] = [
    '1' => 'Tarjeta de Servicio',
    '2' => 'Tarjeta de Crédito',
];

/* ═══════════════════════════════════════════
 * 2. TIPO DE OPERACIÓN UIF — TSC (Fracción II)
 *    Campo: tipo_operacion — digito_3-4_type
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['tipo_operacion'] = [
    '1701' => 'Emisión de tarjeta de servicios o de crédito',
    '1702' => 'Comercialización de tarjeta de servicios o de crédito',
    '1703' => 'Recarga de tarjeta de prepago',
    '1704' => 'Actividad u operación con tarjeta de servicios o de crédito',
    '9999' => 'Otro (especificar)',
];

/* ═══════════════════════════════════════════
 * 2b. TIPO DE OPERACIÓN — TPP (catálogo UIF específico)
 *    Campo: tipo_operacion — digito_3-4_type
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['tipo_operacion_tpp'] = [
    '231' => 'Comercialización de Tarjetas Prepagadas (Carga o recarga)',
    '232' => 'Comercialización de vales o cupones',
];

/* ═══════════════════════════════════════════
 * 2c. TIPO DE OPERACIÓN — TDR (catálogo UIF específico)
 *    Campo: tipo_operacion — digito_3-4_type
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['tipo_operacion_tdr'] = [
    '261' => 'Abono de recursos por devoluciones',
    '262' => 'Abono de recursos por premios o promociones',
    '263' => 'Abono de recursos por programa de recompensas comerciales',
];

/* ═══════════════════════════════════════════
 * 3. EXENTO (Sí/No)
 *    Campo: exento — booleano
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['exento'] = [
    '0' => 'No',
    '1' => 'Sí',
];

/* ═══════════════════════════════════════════
 * 4. PRIORIDAD (igual que DIN)
 *    Campo: prioridad — prioridad_type
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['prioridad'] = [
    '1' => 'Normal',
    '2' => 'Prioritario (24 horas)',
];

/* ═══════════════════════════════════════════
 * 5. TIPO DE ALERTA UIF — TSC (Fracción II)
 *    Campo: tipo_alerta — digito_3-4_type
 *    Catálogo específico para Tarjetas de Servicios de Crédito
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['tipo_alerta'] = [
    '100'  => 'Sin alerta',
    '2201' => 'El cliente realiza operaciones que salen de su perfil transaccional',
    '2202' => 'El cliente recurrentemente realiza el pago del saldo de la tarjeta en efectivo por cantidades elevadas por medio de canales no bancarios',
    '2203' => 'La tarjeta registra la mayoría de sus operaciones en lugares distantes de donde se tiene domiciliada sin razón aparente',
    '2204' => 'El cliente mantiene de manera regular saldo a favor en su cuenta sin justificación aparente',
    '2205' => 'El cliente realiza una compra por un valor significativamente elevado, pagando en la fecha de corte dicha operación en efectivo',
    '2206' => 'El cliente se rehúsa a proporcionar documentos personales que lo identifiquen',
    '2207' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación',
    '2208' => 'El monto de las operaciones realizadas con la tarjeta no es acorde con la actividad económica o giro mercantil declarado por el cliente',
    '2209' => 'Hay indicios, o certeza, que las partes no están actuando en nombre propio y están tratando de ocultar la identidad del cliente real',
    '2210' => 'El cliente intenta sobornar, extorsionar o amenaza al promotor con el fin de que se otorgue la tarjeta fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso',
    '2211' => 'La información y documentación presentada por el cliente es inconsistente o de difícil verificación por parte del Sujeto Obligado',
    '2212' => 'La tarjeta es pagada por un tercero sin relación aparente con el cliente',
    '2213' => 'La tarjeta se liquida por medio de una transferencia proveniente de un país extranjero',
    '2214' => 'El cliente insiste liquidar el saldo de la tarjeta en efectivo en divisas diferentes a la moneda nacional, a pesar de indicarle que esto no está permitido',
    '2215' => 'El cliente solicita varias tarjetas con características similares sin que exista justificación para ello',
    '2216' => 'Otorgamiento de múltiples tarjetas adicionales o suplementarias sin causa justificada',
    '9999' => 'Otra alerta',
];

/* ═══════════════════════════════════════════
 * 5d. TIPO DE ALERTA — TPP (catálogo UIF específico)
 *    Campo: tipo_alerta — digito_3-4_type
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['tipo_alerta_tpp'] = [
    '100'  => 'Sin alerta',
    '2301' => 'El cliente o usuario realiza operaciones de carga en diferentes tarjetas de forma periódica rebasando el umbral de aviso.',
    '2302' => 'El cliente o usuario realiza la operación de carga o recarga por montos elevados liquidando en efectivo',
    '2303' => 'Se observa que el cliente o usuario realiza operaciones de carga o recarga por montos por arriba del umbral de identificación utilizando diversas tarjetas',
    '2304' => 'El cliente o usuario realiza diversas operaciones de carga o recarga por montos elevados en un periodo corto de tiempo.',
    '2305' => 'Se observa que diferentes clientes o usuarios realizan operaciones de carga o recarga por arriba del umbral de identificación en la misma tarjeta.',
    '2306' => 'Se observa que se realizan operaciones de carga o recarga en diferentes localidades, estados o jurisdicciones por arriba del umbral de identificación en la misma tarjeta',
    '2307' => 'El cliente o usuario se rehúsa a proporcionar documentos personales que lo identifiquen.',
    '2308' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación.',
    '2309' => 'El monto de carga o recarga no es acorde con la actividad económica o giro mercantil declarado por el cliente o usuario.',
    '2310' => 'Hay indicios, o certeza, que las partes no están actuando en nombre propio y están tratando de ocultar la identidad del cliente o usuario real',
    '2311' => 'Uso de divisas en efectivo sin justificación alguna.',
    '2312' => 'El cliente o usuario intenta sobornar, extorsionar o amenaza con el fin de realizar la operación fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso.',
    '2313' => 'La información y documentación presentada por el cliente o usuario es inconsistente o de difícil verificación por parte del Sujeto Obligado.',
    '2314' => 'El cliente o usuario no quiere ser relacionado con la operación realizada.',
    '2315' => 'La operación la paga un tercero sin relación aparente con el cliente o usuario.',
    '2316' => 'El cliente o usuario liquida la operación por medio de una transferencia proveniente de un país extranjero.',
    '9999' => 'Otra alerta',
];

/* ═══════════════════════════════════════════
 * 5f. TIPO DE ALERTA — TDR (catálogo UIF específico)
 *    Campo: tipo_alerta — digito_3-4_type
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['tipo_alerta_tdr'] = [
    '100'  => 'Sin alerta.',
    '3701' => 'El cliente o usuario se rehúsa a proporcionar documentos personales que lo identifiquen.',
    '3702' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación.',
    '3703' => 'El monto disponible en la tarjeta no es acorde con la actividad económica o giro mercantil declarado por el cliente o usuario.',
    '3704' => 'Hay indicios, o certeza, que las partes no están actuando en nombre propio y están tratando de ocultar la identidad del cliente o usuario real.',
    '3705' => 'El cliente o usuario intenta sobornar, extorsionar o amenaza al vendedor con el fin de realizar la operación fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso.',
    '3706' => 'La información y documentación presentada por el cliente o usuario es inconsistente o de difícil verificación por parte del Sujeto Obligado.',
    '3707' => 'El cliente o usuario realiza una o más compras por un valor significativamente elevado sin lógica aparente, con el objeto de retirar efectivo con las recompensas ganadas.',
    '3708' => 'El cliente o usuario realiza una o más compras por un valor significativamente elevado, con el objeto de obtener la devolución del monto pagado.',
    '3709' => 'Uso de divisas en efectivo para la adquisición de un bien con el único propósito de obtener la devolución en moneda nacional en un periodo corto de tiempo.',
    '3710' => 'El cliente o usuario no quiere ser relacionado con la operación realizada.',
    '3711' => 'El cliente o usuario o personas relacionadas con él realizan múltiples operaciones en un periodo muy corto sin razón aparente.',
    '3712' => 'La operación la realiza un tercero sin relación aparente con el cliente o usuario.',
    '9999' => 'Otra alerta.',
];

/* ═══════════════════════════════════════════
 * 5e. INSTRUMENTO MONETARIO (catálogo UIF general)
 *    Usado en detalle de liquidación de TPP.
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['instrumento_monetario'] = [
    '1' => 'Efectivo',
    '2' => 'Tarjeta de crédito',
    '3' => 'Tarjeta de débito',
    '4' => 'Transferencia interbancaria',
    '5' => 'Transferencia internacional',
    '6' => 'Fondos de la cuenta en la plataforma',
    '7' => 'Activos virtuales',
    '99' => 'Otros',
];

/* ═══════════════════════════════════════════
 * 5b. TIPO DE ACTO (catálogo general UIF — claves 1-30)
 *    Uso: clasificación de operaciones/actos jurídicos
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['tipo_acto'] = [
    '1'  => 'ACTO JURIDICO',
    '2'  => 'APLICACION DE RECURSOS EN PLAZOS NO MAYORES A 180 DIAS',
    '3'  => 'APLICACION DE RECURSOS EN PLAZOS MAYORES A 180 DIAS',
    '4'  => 'COMPRAVENTA DE BIENES INMUEBLES',
    '5'  => 'COMPRAVENTA DE JOYAS Y/O METALES Y PIEDRAS PRECIOSAS',
    '6'  => 'COMPRAVENTA DE OBRAS DE ARTE',
    '7'  => 'COMPRAVENTA DE VEHICULOS',
    '8'  => 'CONSTITUCION DE DERECHOS REALES',
    '9'  => 'CONSTITUCION DE FIDUCIOS',
    '10' => 'COOPERATIVA DE AHORRO Y PRESTAMO',
    '11' => 'EMISION DE TARJETAS PREPAGADAS',
    '12' => 'INTERMEDIACION FINANCIERA',
    '13' => 'JUEGOS Y SORTEOS',
    '14' => 'PRESTAMO CON GARANTIA DE BIENES INMUEBLES',
    '15' => 'PRESTAMO CON GARANTIA DE OBRAS DE ARTE',
    '16' => 'PRESTAMO CON GARANTIA DE JOYAS Y/O METALES Y PIEDRAS PRECIOSAS',
    '17' => 'PRESTAMO CON GARANTIA DE VEHICULOS',
    '18' => 'RECEPCION DE DEPOSITOS EN PLAZOS NO MAYORES A 180 DIAS',
    '19' => 'RECEPCION DE DEPOSITOS EN PLAZOS MAYORES A 180 DIAS',
    '20' => 'REGISTRO DE INMUEBLES',
    '21' => 'SERVICIOS DE COMERCIO EXTERIOR',
    '22' => 'SERVICIOS PROFESIONALES INDEPENDIENTES',
    '23' => 'TRANSMISION DE DERECHOS SOBRE BIENES INMUEBLES',
    '24' => 'TRANSMISION DE DERECHOS SOBRE OBRAS DE ARTE',
    '25' => 'TRANSMISION DE DERECHOS SOBRE JOYAS Y/O METALES Y PIEDRAS PRECIOSAS',
    '26' => 'TRANSMISION DE DERECHOS SOBRE VEHICULOS',
    '27' => 'VALORES, EMISION Y COMERCIALIZACION DE',
    '28' => 'RECEPCION DE RECURSOS DE TERCEROS',
    '29' => 'PAGO O TRANSFERENCIA DE FONDOS',
    '30' => 'OTRO',
];

/* ═══════════════════════════════════════════
 * 5c. FORMA JURÍDICA — Catálogo UIF (Clave–Concepto)
 *    Uso: clasificación de tipo de entidad/sociedad para persona moral
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['forma_juridica'] = [
    '1'   => 'SOCIEDAD ANONIMA',
    '2'   => 'SOCIEDAD DE RESPONSABILIDAD LIMITADA',
    '3'   => 'SOCIEDAD CIVIL',
    '4'   => 'ASOCIACION CIVIL',
    '5'   => 'SOCIEDAD COOPERATIVA',
    '6'   => 'SOCIEDAD EN NOMBRE COLECTIVO',
    '7'   => 'SOCIEDAD EN COMANDITA SIMPLE',
    '8'   => 'SOCIEDAD EN COMANDITA POR ACCIONES',
    '9'   => 'SOCIEDAD POR ACCIONES SIMPLIFICADA',
    '10'  => 'ASOCIACION EN PARTICIPACION',
    '11'  => 'PERSONA FISICA',
    '12'  => 'FIDEICOMISO',
    '13'  => 'SOCIEDAD DE INVERSION',
    '14'  => 'EMPRESA DE INDUSTRIA DE RED',
    '15'  => 'SOCIEDAD FINANCIERA POPULAR',
    '16'  => 'FONDO DE INVERSION',
    '17'  => 'CONTRATO',
    '18'  => 'PATRIMONIO AUTONOMO',
    '19'  => 'GOBIERNO FEDERAL',
    '20'  => 'GOBIERNO ESTATAL',
    '21'  => 'GOBIERNO MUNICIPAL',
    '22'  => 'ORGANISMO DESCENTRALIZADO',
    '23'  => 'ENTIDAD PARAESTATAL',
    '24'  => 'FONDO O FIDEICOMISO PUBLICO',
    '25'  => 'ORGANISMOS AUTONOMOS Y ENTIDADES PARAESTATALES',
    '26'  => 'INSTITUCION DE ASISTENCIA PRIVADA',
    '27'  => 'SINDICATO',
    '28'  => 'PARTIDOS POLITICOS',
    '29'  => 'ASOCIACION RELIGIOSA',
    '30'  => 'ORGANISMOS INTERNACIONALES Y EXTRATERRITORIALES',
    '31'  => 'SOCIEDAD NACIONAL DE CREDITO',
    '32'  => 'INSTITUCION DE CREDITO',
    '33'  => 'ARRENDADORA FINANCIERA',
    '34'  => 'ALMACEN GENERAL DE DEPOSITO',
    '35'  => 'CASA DE BOLSA',
    '36'  => 'ASEGURADORA',
    '37'  => 'AFIANZADORA',
    '38'  => 'COOPERATIVA DE AHORRO Y PRESTAMO',
    '39'  => 'ADMINISTRADORA DE FONDOS PARA EL RETIRO',
    '40'  => 'OTRO',
];

/* ═══════════════════════════════════════════
 * 6. ACTIVIDAD ECONÓMICA — TSC (persona_fisica, digito_7_type)
 *    Campo: actividad_economica — ocupación SCIAN 7 dígitos
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['actividad_economica'] = [
    '1000000' => 'ASALARIADOS',
    '1110100' => 'CULTIVO DE CEREALES Y OTRAS SIEMBRAS',
    '1220100' => 'GANADERIA',
    '1230100' => 'EXPLOTACION FORESTAL',
    '1240100' => 'PESCA',
    '1300000' => 'MINERIA',
    '2110100' => 'FABRICACION DE ALIMENTOS',
    '2120100' => 'FABRICACION DE BEBIDAS Y TABACO',
    '2130100' => 'FABRICACION DE TEXTILES, CONFECCION Y CUERO',
    '2140100' => 'FABRICACION DE MADERA, PAPEL E IMPRESION',
    '2150100' => 'FABRICACION DE PRODUCTOS QUIMICOS Y PLASTICOS',
    '2160100' => 'FABRICACION DE PRODUCTOS MINERALES NO METALICOS',
    '2170100' => 'FABRICACION DE METAL, MAQUINARIA Y EQUIPO',
    '2180100' => 'OTRAS INDUSTRIAS MANUFACTURERAS',
    '2200000' => 'GENERACION Y DISTRIBUCION DE ENERGIA ELECTRICA Y AGUA',
    '2300000' => 'CONSTRUCCION',
    '3100100' => 'COMERCIO AL POR MAYOR',
    '3210100' => 'COMERCIO AL POR MENOR DE ABARROTES Y ALIMENTOS',
    '3220100' => 'COMERCIO AL POR MENOR DE TEXTILES Y CONFECCIONES',
    '3230100' => 'COMERCIO AL POR MENOR DE ARTICULOS DE USO DOMESTICO',
    '3240100' => 'COMERCIO AL POR MENOR DE PRODUCTOS CULTURALES Y DE OCIO',
    '3250100' => 'COMERCIO AL POR MENOR DE AUTOMOVILES, REFACCIONES Y GAS',
    '3260100' => 'COMERCIO AL POR MENOR DE HERRAMIENTAS Y MATERIALES',
    '3290100' => 'OTROS COMERCIOS AL POR MENOR',
    '4110100' => 'TRANSPORTE TERRESTRE Y SERVICIOS CONEXOS',
    '4120100' => 'TRANSPORTE ACUATICO Y AEREO Y SERVICIOS CONEXOS',
    '4200100' => 'ALMACENAMIENTO Y SERVICIOS CONEXOS',
    '4310100' => 'SERVICIOS DE CORREO Y MENSAJERIA',
    '4320100' => 'TELECOMUNICACIONES',
    '4330100' => 'PROGRAMACION, CONSULTORIA Y OTROS SERVICIOS DE INFORMATICA',
    '4340100' => 'SERVICIOS DE NOTICIAS, MEDIOS MASIVOS Y FOTOGRAFICOS',
    '5100100' => 'SERVICIOS FINANCIEROS Y DE SEGUROS',
    '5200100' => 'SERVICIOS INMOBILIARIOS',
    '5300100' => 'SERVICIOS DE ALQUILER DE BIENES MUEBLES E INMUEBLES',
    '5400100' => 'SERVICIOS CIENTIFICOS Y TECNICOS',
    '5500100' => 'SERVICIOS CORPORATIVOS',
    '5610100' => 'SERVICIOS DE APOYO A LOS NEGOCIOS',
    '5620100' => 'SERVICIOS DE RECOLECCION DE RESIDUOS Y RECUPERACION',
    '6110100' => 'SERVICIOS DE EDUCACION',
    '6210100' => 'SERVICIOS DE SALUD Y ASISTENCIA SOCIAL',
    '7110100' => 'ALOJAMIENTO',
    '7120100' => 'PREPARACION DE ALIMENTOS Y BEBIDAS',
    '7130100' => 'EDITORIALES, AUDIOVISUALES Y JUEGOS',
    '7130200' => 'ARTISTAS, ESCRITORES, COMPOSITORES Y OTROS ARTES',
    '7130300' => 'DEPORTES, ENTRETENIMIENTO Y RECREACION',
    '7130400' => 'MUSEOS, SITIOS HISTORICOS Y SIMILARES',
    '7130500' => 'LOTERIAS Y CASINOS',
    '7200100' => 'REPARACION Y MANTENIMIENTO',
    '7510100' => 'SERVICIOS LEGALES',
    '7520100' => 'SERVICIOS DE CONTADURIA',
    '7530100' => 'SERVICIOS DE ARQUITECTURA E INGENIERIA',
    '7540100' => 'SERVICIOS DE INVESTIGACION CIENTIFICA, DESARROLLO Y OTROS',
    '7550100' => 'SERVICIOS DE CONSULTORIA',
    '7590100' => 'OTROS SERVICIOS PROFESIONALES',
    '8100100' => 'SERVICIOS DE APOYO A LA AGRICULTURA',
    '8100200' => 'SERVICIOS DE APOYO A LA GANADERIA',
    '8100300' => 'SERVICIOS DE APOYO A LA SILVICULTURA',
    '8100400' => 'SERVICIOS DE APOYO A LA PESCA',
    '8100500' => 'SERVICIOS DE APOYO A LA MINERIA',
    '8210100' => 'ORGANISMOS INTERNACIONALES Y EXTRATERRITORIALES',
    '8220100' => 'INSTITUCIONES DEL SECTOR PUBLICO',
    '8230100' => 'SINDICATOS Y ASOCIACIONES GREMIALES',
    '8240100' => 'ORGANIZACIONES RELIGIOSAS',
    '8250100' => 'ASOCIACIONES POLITICAS',
    '8250200' => 'EMPLEADOS EN EL SECTOR PUBLICO',
    '8250300' => 'COMERCIANTES EN ESTABLECIMIENTOS FIJOS',
    '8250400' => 'COMERCIANTES AMBULANTES',
    '8250500' => 'AGRICULTORES',
    '8250600' => 'GANADEROS',
    '8250700' => 'PESCADORES',
    '8250800' => 'FORESTALES',
    '8250900' => 'MINEROS',
    '8260100' => 'PROFESIONALES DE LA SALUD',
    '8260200' => 'PROFESIONALES DE LA EDUCACION',
    '8260300' => 'PROFESIONALES DEL DERECHO',
    '8260400' => 'PROFESIONALES DE LA CONTADURIA',
    '8260500' => 'PROFESIONALES DE LA ARQUITECTURA E INGENIERIA',
    '8260600' => 'PROFESIONALES DE LA CIENCIA Y LA TECNOLOGIA',
    '8260700' => 'PROFESIONALES DEL ARTE Y EL ENTRETENIMIENTO',
    '8260800' => 'PROFESIONALES DEL DEPORTE',
    '8260900' => 'OTROS PROFESIONALES',
    '8270100' => 'RENTISTAS',
    '8280100' => 'JUBILADOS Y PENSIONADOS',
    '8290100' => 'ESTUDIANTES',
    '8290200' => 'AMAS DE CASA',
    '8290300' => 'DESEMPLEADOS',
    '8290400' => 'OTROS TRABAJADORES NO CLASIFICADOS',
    '1044010' => 'SERVICIOS DE TRADUCCION E INTERPRETACION LINGUISTICA',
    '9999999' => 'OTROS',
];

/* ═══════════════════════════════════════════
 * 7. CLAVE PAÍS — TSC (pais_type, XSD: [A-Z]{2})
 *    Para nacionalidad, domicilio, teléfono
 * ═══════════════════════════════════════════ */
$TSC_CATALOGOS['pais'] = [
    'AF' => 'AFGANISTÁN', 'AL' => 'ALBANIA', 'DE' => 'ALEMANIA', 'AD' => 'ANDORRA', 'AO' => 'ANGOLA',
    'AI' => 'ANGUILA', 'AQ' => 'ANTÁRTIDA', 'AG' => 'ANTIGUA Y BARBUDA', 'SA' => 'ARABIA SAUDITA',
    'DZ' => 'ARGELIA', 'AR' => 'ARGENTINA', 'AM' => 'ARMENIA', 'AW' => 'ARUBA', 'AU' => 'AUSTRALIA',
    'AT' => 'AUSTRIA', 'AZ' => 'AZERBAIYÁN', 'BS' => 'BAHAMAS', 'BH' => 'BAHRÉIN', 'BD' => 'BANGLADÉS',
    'BB' => 'BARBADOS', 'BE' => 'BÉLGICA', 'BZ' => 'BELICE', 'BJ' => 'BENÍN', 'BM' => 'BERMUDAS',
    'BY' => 'BIELORRUSIA', 'MM' => 'BIRMANIA (MYANMAR)', 'BO' => 'BOLIVIA', 'BA' => 'BOSNIA Y HERZEGOVINA',
    'BW' => 'BOTSUANA', 'BR' => 'BRASIL', 'BN' => 'BRUNÉI DARUSSALAM', 'BG' => 'BULGARIA', 'BF' => 'BURKINA FASO',
    'BI' => 'BURUNDI', 'BT' => 'BUTÁN', 'CV' => 'CABO VERDE', 'KH' => 'CAMBOYA', 'CM' => 'CAMERÚN',
    'CA' => 'CANADÁ', 'TD' => 'CHAD', 'CL' => 'CHILE', 'CN' => 'CHINA', 'CY' => 'CHIPRE',
    'VA' => 'CIUDAD DEL VATICANO', 'CO' => 'COLOMBIA', 'KM' => 'COMORAS', 'CD' => 'CONGO (REPÚBLICA DEMOCRÁTICA DEL)',
    'CG' => 'CONGO (REPÚBLICA DEL)', 'KP' => 'COREA (REPÚBLICA POPULAR DEMOCRÁTICA DE)', 'KR' => 'COREA (REPÚBLICA DE)',
    'CI' => 'COSTA DE MARFIL', 'CR' => 'COSTA RICA', 'HR' => 'CROACIA', 'CU' => 'CUBA', 'DK' => 'DINAMARCA',
    'DM' => 'DOMINICA', 'EC' => 'ECUADOR', 'EG' => 'EGIPTO', 'SV' => 'EL SALVADOR', 'AE' => 'EMIRATOS ÁRABES UNIDOS',
    'ER' => 'ERITREA', 'SK' => 'ESLOVAQUIA', 'SI' => 'ESLOVENIA', 'ES' => 'ESPAÑA', 'US' => 'ESTADOS UNIDOS DE AMÉRICA',
    'EE' => 'ESTONIA', 'ET' => 'ETIOPÍA', 'RU' => 'FEDERACIÓN RUSA', 'FJ' => 'FIJI', 'PH' => 'FILIPINAS',
    'FI' => 'FINLANDIA', 'FR' => 'FRANCIA', 'GA' => 'GABÓN', 'GM' => 'GAMBIA', 'GE' => 'GEORGIA',
    'GH' => 'GHANA', 'GI' => 'GIBRALTAR', 'GD' => 'GRANADA', 'GR' => 'GRECIA', 'GL' => 'GROENLANDIA',
    'GP' => 'GUADALUPE', 'GU' => 'GUAM', 'GT' => 'GUATEMALA', 'GF' => 'GUAYANA FRANCESA', 'GG' => 'GUERNSEY',
    'GN' => 'GUINEA', 'GQ' => 'GUINEA ECUATORIAL', 'GW' => 'GUINEA-BISSAU', 'GY' => 'GUYANA', 'HT' => 'HAITÍ',
    'HN' => 'HONDURAS', 'HK' => 'HONG KONG', 'HU' => 'HUNGRÍA', 'IN' => 'INDIA', 'ID' => 'INDONESIA',
    'IR' => 'IRÁN (REPÚBLICA ISLÁMICA DE)', 'IQ' => 'IRAK', 'IE' => 'IRLANDA', 'IS' => 'ISLANDIA',
    'AX' => 'ISLAS ALAND', 'KY' => 'ISLAS CAIMÁN', 'CK' => 'ISLAS COOK', 'FO' => 'ISLAS FAROE',
    'FK' => 'ISLAS MALVINAS', 'MP' => 'ISLAS MARIANAS DEL NORTE', 'MH' => 'ISLAS MARSHALL', 'NF' => 'ISLAS NORFOLK',
    'PN' => 'ISLAS PITCAIRN', 'SB' => 'ISLAS SALOMÓN', 'TC' => 'ISLAS TURCAS Y CAICOS', 'VG' => 'ISLAS VÍRGENES BRITÁNICAS',
    'VI' => 'ISLAS VÍRGENES DE LOS ESTADOS UNIDOS', 'IL' => 'ISRAEL', 'IT' => 'ITALIA', 'JM' => 'JAMAICA',
    'JP' => 'JAPÓN', 'JE' => 'JERSEY', 'JO' => 'JORDANIA', 'KZ' => 'KAZAJISTÁN', 'KE' => 'KENIA',
    'KG' => 'KIRGUISTÁN', 'KI' => 'KIRIBATI', 'KW' => 'KUWAIT', 'LA' => 'LAOS', 'LS' => 'LESOTO',
    'LV' => 'LETONIA', 'LB' => 'LÍBANO', 'LR' => 'LIBERIA', 'LY' => 'LIBIA', 'LI' => 'LIECHTENSTEIN',
    'LT' => 'LITUANIA', 'LU' => 'LUXEMBURGO', 'MO' => 'MACAO', 'MK' => 'MACEDONIA', 'MG' => 'MADAGASCAR',
    'MY' => 'MALASIA', 'MW' => 'MALAUI', 'MV' => 'MALDIVAS', 'ML' => 'MALÍ', 'MT' => 'MALTA',
    'MA' => 'MARRUECOS', 'MQ' => 'MARTINICA', 'MU' => 'MAURICIO', 'MR' => 'MAURITANIA', 'YT' => 'MAYOTTE',
    'MX' => 'MÉXICO', 'FM' => 'MICRONESIA', 'MD' => 'MOLDAVIA', 'MC' => 'MÓNACO', 'MN' => 'MONGOLIA',
    'ME' => 'MONTENEGRO', 'MS' => 'MONTSERRAT', 'MZ' => 'MOZAMBIQUE', 'NA' => 'NAMIBIA', 'NR' => 'NAURU',
    'NP' => 'NEPAL', 'NI' => 'NICARAGUA', 'NE' => 'NÍGER', 'NG' => 'NIGERIA', 'NU' => 'NIUE',
    'NO' => 'NORUEGA', 'NC' => 'NUEVA CALEDONIA', 'NZ' => 'NUEVA ZELANDA', 'OM' => 'OMÁN', 'NL' => 'PAÍSES BAJOS',
    'PK' => 'PAKISTÁN', 'PW' => 'PALAOS', 'PS' => 'PALESTINA', 'PA' => 'PANAMÁ', 'PG' => 'PAPÚA NUEVA GUINEA',
    'PY' => 'PARAGUAY', 'PE' => 'PERÚ', 'PF' => 'POLINESIA FRANCESA', 'PL' => 'POLONIA', 'PT' => 'PORTUGAL',
    'PR' => 'PUERTO RICO', 'QA' => 'QATAR', 'GB' => 'REINO UNIDO', 'CF' => 'REPÚBLICA CENTROAFRICANA',
    'CZ' => 'REPÚBLICA CHECA', 'DO' => 'REPÚBLICA DOMINICANA', 'SS' => 'REPÚBLICA DE SUDÁN DEL SUR',
    'RW' => 'RUANDA', 'RO' => 'RUMANÍA', 'EH' => 'SÁHARA OCCIDENTAL', 'WS' => 'SAMOA', 'AS' => 'SAMOA AMERICANA',
    'SM' => 'SAN MARINO', 'KN' => 'SAN CRISTÓBAL Y NIEVES', 'PM' => 'SAN PEDRO Y MIQUELÓN',
    'VC' => 'SAN VICENTE Y LAS GRANADINAS', 'SH' => 'SANTA HELENA, ASCENSIÓN Y TRISTÁN DE ACUÑA',
    'LC' => 'SANTA LUCÍA', 'ST' => 'SANTO TOMÉ Y PRÍNCIPE', 'SN' => 'SENEGAL', 'RS' => 'SERBIA',
    'SC' => 'SEYCHELLES', 'SL' => 'SIERRA LEONA', 'SG' => 'SINGAPUR', 'SY' => 'SIRIA', 'SO' => 'SOMALIA',
    'LK' => 'SRI LANKA', 'SZ' => 'SUAZILANDIA', 'ZA' => 'SUDÁFRICA', 'SD' => 'SUDÁN', 'SE' => 'SUECIA',
    'CH' => 'SUIZA', 'SR' => 'SURINAM', 'TH' => 'TAILANDIA', 'TW' => 'TAIWÁN', 'TZ' => 'TANZANIA',
    'TJ' => 'TAYIKISTÁN', 'IO' => 'TERRITORIO BRITÁNICO DEL OCÉANO ÍNDICO', 'TL' => 'TIMOR-LESTE',
    'TG' => 'TOGO', 'TK' => 'TOKELAU', 'TO' => 'TONGA', 'TT' => 'TRINIDAD Y TOBAGO', 'TN' => 'TÚNEZ',
    'TM' => 'TURKMENISTÁN', 'TR' => 'TURQUÍA', 'TV' => 'TUVALU', 'UA' => 'UCRANIA', 'UG' => 'UGANDA',
    'UY' => 'URUGUAY', 'UZ' => 'UZBEKISTÁN', 'VU' => 'VANUATU', 'VE' => 'VENEZUELA', 'VN' => 'VIETNAM',
    'YE' => 'YEMEN', 'DJ' => 'YIBUTI', 'ZM' => 'ZAMBIA', 'ZW' => 'ZIMBABWE',
];

/* ═══════════════════════════════════════════
 * Catálogos compartidos con DIN (reutilizar)
 * Se incluyen por referencia o se cargan dinámicamente
 * ═══════════════════════════════════════════ */
// entidad_federativa, moneda, giro_mercantil (compartidos con DIN). actividad_economica ya definido arriba para TSC.
if (file_exists(__DIR__ . '/din_catalogos.php')) {
    require_once __DIR__ . '/din_catalogos.php';
    $din = isset($DIN_CATALOGOS) ? $DIN_CATALOGOS : [];
    $TSC_CATALOGOS['entidad_federativa'] = $din['entidad_federativa'] ?? [];
    $TSC_CATALOGOS['moneda'] = $din['moneda'] ?? [];
    $TSC_CATALOGOS['giro_mercantil'] = $din['giro_mercantil'] ?? [];
}

/**
 * Genera opciones HTML <option> desde un catálogo TSC
 */
function tscCatalogoOptions(string $catalogoName, string $selectedValue = ''): string {
    global $TSC_CATALOGOS;
    $cat = $TSC_CATALOGOS[$catalogoName] ?? [];
    $html = '<option value="">-- Seleccione --</option>';
    foreach ($cat as $clave => $descripcion) {
        $sel = ((string)$clave === (string)$selectedValue) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($clave) . '"' . $sel . '>'
                . htmlspecialchars($clave . ' - ' . $descripcion)
                . '</option>';
    }
    return $html;
}

/**
 * Devuelve catálogos TSC como JSON
 */
function tscCatalogosJson(): string {
    global $TSC_CATALOGOS;
    return json_encode($TSC_CATALOGOS, JSON_UNESCAPED_UNICODE);
}
