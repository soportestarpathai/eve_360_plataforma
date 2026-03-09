<?php
/**
 * Catálogos UIF para SPR (Servicios Profesionales) — Fracción XI
 * XSD: http://www.uif.shcp.gob.mx/recepcion/spr
 * Actividad: Servicios profesionales independientes que realicen actos u operaciones
 * de compraventa, cesión de derechos, administración de recursos, constitución de sociedades, etc.
 */

$SPR_CATALOGOS = [];

/* ═══════════════════════════════════════════
 * 0. CLAVE DE ACTIVIDAD — Actividad Vulnerable (clave_actividad)
 *    Catálogo UIF: Actividades Vulnerables — SPR = Fracción XI
 *    Para SPR siempre es "SPR"
 * ═══════════════════════════════════════════ */
$SPR_CATALOGOS['clave_actividad'] = [
    'SPR' => 'La prestación de servicios profesionales, de manera independiente, sin que medie relación laboral con el cliente respectivo, en aquellos casos en que se prepare para un cliente o se lleven a cabo en nombre y representación del cliente cualquiera de las operaciones establecidas en el Artículo 17 fracción XI de la LFPIORPI.',
];

/* ═══════════════════════════════════════════
 * 1. OCUPACIÓN — Sujeto obligado (tipo_ocupacion)
 *    Catálogo UIF: Ocupación Fracción XI — Clave XI.Letra NN
 *    Aplica a Fracción XI y todas sus subfracciones
 * ═══════════════════════════════════════════ */
$SPR_CATALOGOS['tipo_ocupacion'] = [
    'XI.A 01' => 'ACTORES, MÚSICOS Y COMPOSITORES',
    'XI.A 02' => 'ACTORES',
    'XI.A 03' => 'MÚSICOS',
    'XI.A 04' => 'COMPOSITORES',
    'XI.B 01' => 'ARTESANOS',
    'XI.C 01' => 'CONTADORES PÚBLICOS Y PRIVADOS',
    'XI.D 01' => 'DENTISTAS',
    'XI.E 01' => 'DEPORTISTAS PROFESIONALES',
    'XI.F 01' => 'DISEÑADORES GRÁFICOS E INDUSTRIALES',
    'XI.G 01' => 'DISEÑADORES DE MODAS E INTERIORES',
    'XI.H 01' => 'EMPLEADOS',
    'XI.I 01' => 'ENTRENADORES FÍSICOS Y DEPORTIVOS',
    'XI.J 01' => 'ESCRITORES Y PERIODISTAS',
    'XI.K 01' => 'ESTUDIANTES',
    'XI.L 01' => 'FOTÓGRAFOS Y CAMARÓGRAFOS',
    'XI.M 01' => 'GUIONISTAS',
    'XI.N 01' => 'JUBILADOS Y PENSIONADOS',
    'XI.O 01' => 'MAESTROS Y CATEDRÁTICOS',
    'XI.P 01' => 'MECÁNICOS Y TÉCNICOS AUTOMOTRICES',
    'XI.Q 01' => 'MÉDICOS Y CIRUJANOS',
    'XI.R 01' => 'OFICIOS VARIOS Y TRABAJADORES EN GENERAL',
    'XI.S 01' => 'PROFESORES E INVESTIGADORES',
    'XI.T 01' => 'PUBLICISTAS Y PROFESIONALES DE MARKETING',
    'XI.U 01' => 'QUÍMICOS Y FARMACÉUTICOS',
    'XI.V 01' => 'RELIGIOSOS',
    'XI.W 01' => 'SERVIDORES PÚBLICOS',
    'XI.X 01' => 'TÉCNICOS EN COMPUTACIÓN E INFORMÁTICA',
    'XI.Y 01' => 'ABOGADOS Y LICENCIADOS EN DERECHO',
    'XI.Z 01' => 'ARQUITECTOS E INGENIEROS CIVILES',
    'XI.Z 02' => 'INGENIEROS AGRÓNOMOS',
    'XI.Z 03' => 'INGENIEROS AMBIENTALES',
    'XI.Z 04' => 'INGENIEROS BIOMÉDICOS',
    '99'     => 'Otro (especificar)',
];

/* ═══════════════════════════════════════════
 * 2. TIPO DE ACTIVIDAD — Subfracciones (tipo_actividad)
 *    Las 10 actividades del detalle_operaciones
 * ═══════════════════════════════════════════ */
$SPR_CATALOGOS['tipo_actividad'] = [
    'compra_venta_inmuebles'           => '1. Compraventa de Inmuebles',
    'cesion_derechos_inmuebles'        => '2. Cesión de Derechos sobre Inmuebles',
    'administracion_recursos'          => '3. Administración de Recursos',
    'constitucion_sociedades_mercantiles' => '4. Constitución de Personas Morales (incluidas las sociedades mercantiles)',
    'organizacion_aportaciones'        => '5. Organización de Aportaciones',
    'fusion'                           => '6. Fusión',
    'escision'                         => '7. Escisión',
    'administracion_personas_morales'  => '8. Administración de Personas Morales',
    'constitucion_fideicomiso'         => '9. Constitución de Fideicomiso',
    'compra_venta_entidades_mercantiles' => '10. Compra o Venta de Entidades Mercantiles',
];

/* ═══════════════════════════════════════════
 * 3. PRIORIDAD
 *    Campo: prioridad — prioridad_type
 * ═══════════════════════════════════════════ */
$SPR_CATALOGOS['prioridad'] = [
    '1' => 'Normal',
    '2' => '24 hrs. con operaciones',
];

/* ═══════════════════════════════════════════
 * 4. TIPO DE ALERTA — SPR (Fracción XI)
 *    Catálogo UIF: Tipo de Alerta - SPR
 *    Campo: tipo_alerta — tipo_alerta_type (3-4 dígitos)
 * ═══════════════════════════════════════════ */
$SPR_CATALOGOS['tipo_alerta'] = [
    '100'  => 'Sin alerta.',
    '3401' => 'El cliente se rehúsa a proporcionar documentos personales que lo identifiquen.',
    '3402' => 'La operación no es acorde con la actividad económica o giro mercantil declarado por el cliente.',
    '3403' => 'Hay indicios, o certeza, que las partes no están actuando en nombre propio y están tratando de ocultar la identidad del cliente real.',
    '3404' => 'La información y documentación presentada por el cliente es inconsistente o de difícil verificación por parte del Sujeto Obligado.',
    '3405' => 'El cliente intenta sobornar, extorsionar o amenaza con el fin de realizar la operación fuera de los parámetros establecidos, o con la finalidad de evitar el envío del Aviso.',
    '3406' => 'De acuerdo con medios informativos u otras fuentes de información pública, se tiene conocimiento o sospecha de que el cliente, un familiar o persona relacionada, está vinculado con actividades ilícitas o se encuentra bajo proceso de investigación.',
    '3407' => 'El cliente hace uso de un intermediario para realizar operaciones no acordes con la actividad económica o giro mercantil del cliente.',
    '3408' => 'El cliente no quiere ser relacionado con la operación realizada.',
    '3409' => 'El cliente o personas relacionadas con él realizan múltiples operaciones en un periodo muy corto sin razón aparente.',
    '3410' => 'El cliente solicita que los honorarios del servicio sean pagados por un tercero que no participó en la operación.',
    '3411' => 'El cliente se rehúsa a proporcionar información sobre la identidad del beneficiario final.',
    '3412' => 'El cliente insiste en contratar los servicios de un intermediario en todas sus transacciones, evitando el trato personal, sin causa que lo justifique.',
    '3413' => 'Cliente extranjero aparentemente no cuenta con transacciones/negocios significativos en el país y solicita el apoyo de servicios profesionales.',
    '3414' => 'El cliente es el firmante de las cuentas de la persona moral sin que exista causa que justifique el servicio profesional.',
    '3415' => 'Hay indicios de que posterior a la constitución de la empresa, hubo un periodo largo de inactividad seguido de un aumento repentino e inexplicable de las actividades financieras.',
    '3416' => 'El cliente es una persona moral que se describe como un negocio comercial, sin embargo no es posible corroborar esta información en fuentes abiertas.',
    '3417' => 'El cliente (persona moral) está registrado con un nombre que indica actividades o servicios distintos a los que realiza.',
    '3418' => 'La dirección proporcionada por el cliente es la misma que han indicado otros clientes (personas físicas y morales), sin que formen parte de un mismo grupo empresarial.',
    '3419' => 'Se tiene una cantidad inusualmente grande de beneficiarios y participantes mayoritarios.',
    '3420' => 'Se sospecha que la persona moral que ha sido constituida/incorporada plantea un alto riesgo de lavado de dinero o de financiamiento del terrorismo.',
    '3421' => 'La administración de recursos del cliente permite identificar que usa múltiples cuentas bancarias sin justificación aparente.',
    '3422' => 'El cliente no muestra interés en la estructura de la(s) empresa(s) que se está(n) constituyendo.',
    '3423' => 'Se tienen indicios de que el cliente involucra a múltiples profesionales para facilitar (o relacionar) aspectos de una transacción sin una razón que lo justifique.',
    '3424' => 'La operación involucra a dos personas morales con directores, accionistas o beneficiarios finales similares o idénticos.',
    '3425' => 'El cliente (personas físicas y morales) declara una ocupación o actividad distinta a la que aparece en su Cédula de Identificación Fiscal.',
    '9999' => 'Otra alerta.',
];

/* ═══════════════════════════════════════════
 * 5a. TIPO OPERACIÓN COMPRAVENTA (compra_venta_inmuebles)
 *    Campo: tipo_operacion — digito_1_type
 * ═══════════════════════════════════════════ */
$SPR_CATALOGOS['tipo_operacion_compraventa'] = [
    '1' => 'Compra',
    '2' => 'Venta',
    '3' => 'Compra y Venta',
    '4' => 'Permuta',
    '5' => 'Donación',
    '6' => 'Adjudicación',
    '7' => 'Remate',
    '8' => 'Arrendamiento con opción a compra',
    '9' => 'Otro',
];

/* ═══════════════════════════════════════════
 * 5b. CESIÓN DE DERECHOS (cesion_derechos_inmuebles)
 *    figura_cliente: papel del cliente en la cesión
 *    tipo_cesion: tipo de cesión
 * ═══════════════════════════════════════════ */
$SPR_CATALOGOS['figura_cliente_cesion'] = [
    '1' => 'Cedente',
    '2' => 'Cesionario',
];
$SPR_CATALOGOS['tipo_cesion'] = [
    '1' => 'Onerosa',
    '2' => 'Gratuita',
    '3' => 'Otra',
];

/* ═══════════════════════════════════════════
 * 5c. TIPO INMUEBLE (caracteristicas_inmueble)
 *    Campo: tipo_inmueble — digito_1-2_type
 * ═══════════════════════════════════════════ */
$SPR_CATALOGOS['tipo_inmueble'] = [
    '1'  => 'Casa habitación',
    '2'  => 'Departamento',
    '3'  => 'Local comercial',
    '4'  => 'Bodega',
    '5'  => 'Oficina',
    '6'  => 'Terreno',
    '7'  => 'Edificio',
    '8'  => 'Nave industrial',
    '9'  => 'Rural / Agrícola',
    '10' => 'Condominio horizontal',
    '11' => 'Estacionamiento',
    '12' => 'Consultorio',
    '13' => 'Local en plaza comercial',
    '14' => 'Rancho',
    '15' => 'Establo / Corral',
    '16' => 'Inmueble mixto',
    '99' => 'Otro',
];

/* ═══════════════════════════════════════════
 * 5d. ADMINISTRACIÓN DE RECURSOS (administracion_recursos)
 *    activo_banco: estatus_manejo, clave_tipo_institucion
 *    activo_outsourcing: tipo_area_servicio, tipo_activo_administrado
 * ═══════════════════════════════════════════ */
$SPR_CATALOGOS['estatus_manejo'] = [
    '1' => 'Apertura',
    '2' => 'Cierre',
    '3' => 'Modificación',
];
$SPR_CATALOGOS['clave_tipo_institucion'] = [];
$SPR_CATALOGOS['tipo_area_servicio'] = [
    '1'  => 'Recursos humanos',
    '2'  => 'Contabilidad',
    '3'  => 'Finanzas',
    '4'  => 'Legal',
    '5'  => 'Fiscal',
    '6'  => 'Tecnologías de información',
    '7'  => 'Administración de inmuebles',
    '8'  => 'Seguridad',
    '9'  => 'Otros servicios',
    '99' => 'Otro (especificar)',
];
$SPR_CATALOGOS['tipo_activo_administrado'] = [
    '1'  => 'Recursos humanos',
    '2'  => 'Inmuebles',
    '3'  => 'Valores',
    '4'  => 'Cuentas bancarias',
    '5'  => 'Ahorro',
    '6'  => 'Inversiones',
    '7'  => 'Activos fijos',
    '8'  => 'Inventarios',
    '9'  => 'Cartera',
    '10' => 'Otros activos',
    '99' => 'Otro (especificar)',
];

/* ═══════════════════════════════════════════
 * 5e. CONSTITUCIÓN DE SOCIEDADES MERCANTILES
 *    tipo_persona_moral, cargo_accionista, consejo_vigilancia, motivo_constitucion
 * ═══════════════════════════════════════════ */
$SPR_CATALOGOS['tipo_persona_moral'] = [
    '1' => 'Sociedad Anónima (S.A.)',
    '2' => 'Sociedad de Responsabilidad Limitada (S. de R.L.)',
    '3' => 'Sociedad en Nombre Colectivo (S. en N.C.)',
    '4' => 'Sociedad en Comandita Simple (S. en C.S.)',
    '5' => 'Sociedad en Comandita por Acciones (S. en C. por A.)',
    '6' => 'Sociedad Anónima de Capital Variable (S.A. de C.V.)',
    '7' => 'Sociedad de Responsabilidad Limitada de Capital Variable (S. de R.L. de C.V.)',
    '99' => 'Otra',
];
$SPR_CATALOGOS['cargo_accionista'] = [
    '1' => 'Presidente',
    '2' => 'Secretario',
    '3' => 'Tesorero',
    '4' => 'Accionista',
    '5' => 'Comisario',
    '6' => 'Director',
    '99' => 'Otro',
];
$SPR_CATALOGOS['consejo_vigilancia'] = [
    'SI' => 'Sí',
    'NO' => 'No',
];
$SPR_CATALOGOS['motivo_constitucion'] = [
    '1' => 'Actividad comercial',
    '2' => 'Actividad industrial',
    '3' => 'Servicios',
    '4' => 'Inversión',
    '99' => 'Otro',
];

/* ═══════════════════════════════════════════
 * 5. EXENTO (Art. 27 Bis)
 *    Campo: exento — exento_type
 * ═══════════════════════════════════════════ */
$SPR_CATALOGOS['exento'] = [
    '0' => 'No',
    '1' => 'Sí',
];

/* ═══════════════════════════════════════════
 * Catálogos compartidos con DIN/TSC
 * ═══════════════════════════════════════════ */
if (file_exists(__DIR__ . '/din_catalogos.php')) {
    require_once __DIR__ . '/din_catalogos.php';
    $din = isset($DIN_CATALOGOS) ? $DIN_CATALOGOS : [];
    $SPR_CATALOGOS['clave_tipo_institucion'] = $din['tipo_institucion'] ?? [];
    $SPR_CATALOGOS['entidad_federativa'] = $din['entidad_federativa'] ?? [];
    $SPR_CATALOGOS['moneda'] = $din['moneda'] ?? [];
    $SPR_CATALOGOS['instrumento_monetario'] = $din['instrumento_monetario'] ?? [];
}
if (file_exists(__DIR__ . '/tsc_catalogos.php')) {
    require_once __DIR__ . '/tsc_catalogos.php';
    $tsc = isset($TSC_CATALOGOS) ? $TSC_CATALOGOS : [];
    $SPR_CATALOGOS['pais'] = $tsc['pais'] ?? [];
    $SPR_CATALOGOS['actividad_economica'] = $tsc['actividad_economica'] ?? [];
    $SPR_CATALOGOS['giro_mercantil'] = $tsc['giro_mercantil'] ?? [];
}

/**
 * Genera opciones HTML <option> desde un catálogo SPR
 */
function sprCatalogoOptions(string $catalogoName, string $selectedValue = '', ?array $soloClaves = null): string {
    global $SPR_CATALOGOS;
    $cat = $SPR_CATALOGOS[$catalogoName] ?? [];
    $html = '<option value="">-- Seleccione --</option>';
    foreach ($cat as $clave => $descripcion) {
        if ($soloClaves !== null && !in_array($clave, $soloClaves, true)) continue;
        $sel = ((string)$clave === (string)$selectedValue) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($clave) . '"' . $sel . '>'
                . htmlspecialchars($clave . (is_numeric($clave) ? ' - ' : ' ') . $descripcion)
                . '</option>';
    }
    return $html;
}

/**
 * Devuelve catálogos SPR como JSON
 */
function sprCatalogosJson(): string {
    global $SPR_CATALOGOS;
    return json_encode($SPR_CATALOGOS, JSON_UNESCAPED_UNICODE);
}
