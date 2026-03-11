<?php
/**
 * Helper para generar XML DIN (Desarrollo Inmobiliario) según XSD UIF/SHCP
 * Cubre TODOS los campos del XSD: archivo > informe > sujeto_obligado > aviso >
 *   detalle_operaciones > datos_operacion > desarrollos_inmobiliarios + aportaciones
 * Tipos de aportación: recursos_propios, socios, terceros,
 *   prestamo_financiero, prestamo_no_financiero, financiamiento_bursatil
 */

if (!function_exists('formatMontoDin')) {
    function formatMontoDin($val): string {
        if ($val === null || $val === '') return '0.00';
        $n = floatval($val);
        return number_format($n, 2, '.', '');
    }
}

if (!function_exists('dinToUpper')) {
    /** Convierte a mayúsculas para patrones XSD (nombre_type, direccion, correo, etc.) */
    function dinToUpper($val): string {
        $v = trim((string)$val);
        if ($v === '') return '';
        return mb_strtoupper($v, 'UTF-8');
    }
}

/** clave_sujeto_obligado: formato RFC (clave_so_type) 12-13 chars, solo A-ZÑ0-9 */
if (!function_exists('dinSanitizeClaveSO')) {
    function dinSanitizeClaveSO($val): string {
        $v = dinToUpper(trim((string)$val));
        $v = preg_replace('/[^A-Z\x{00D1}0-9]/u', '', $v);
        return substr($v, 0, 13);
    }
}

/** Valida clave_sujeto_obligado con regex RFC: 3-4 letras + 6 dígitos + 3 caracteres */
if (!function_exists('dinValidarClaveSO')) {
    function dinValidarClaveSO($val): bool {
        $v = trim((string)$val);
        if ($v === '') return false;
        return (bool) preg_match('/^[A-Z\x{00D1}&]{3,4}\d{6}[A-Z0-9]{3}$/u', dinToUpper($v));
    }
}

if (!function_exists('generateDINXml')) {
    function generateDINXml(array $data, ?string $xsdPath = null): array {
        $NS  = 'http://www.uif.shcp.gob.mx/recepcion/din';
        $XSI = 'http://www.w3.org/2001/XMLSchema-instance';

        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $MONTO_FIELDS = [
            'monto_desarrollo','unidades_comercializadas','costo_unidad',
            'monto_aportacion','monto_estimado','monto_prestamo',
            'monto_solicitado','monto_recibido','valor_inmueble_preventa'
        ];

        $UPPER_FIELDS = [
            'nombre','apellido_paterno','apellido_materno','denominacion_razon',
            'colonia','calle','numero_exterior','numero_interior',
            'estado_provincia','ciudad_poblacion','descripcion_alerta',
            'descripcion_modificacion','descripcion_bien','descripcion_desarrollo',
            'descripcion_tercero','nombre_institucion','institucion',
            'objeto_aviso_anterior','modificacion','otras_empresas',
            'tipo_desarrollo','instrumento_monetario','aportacion_fideicomiso'
        ];

        $addEl = function(DOMDocument $dom, DOMElement $parent, string $ns, string $name, $value) use (&$MONTO_FIELDS, $UPPER_FIELDS) {
            if ($value === null) return null;
            $value = trim((string)$value);
            if ($value === '') return null;
            if (in_array($name, $MONTO_FIELDS)) {
                $value = formatMontoDin($value);
            } elseif ($name === 'clave_sujeto_obligado') {
                $value = dinSanitizeClaveSO($value);
            } elseif ($name === 'correo_electronico') {
                $value = dinToUpper($value);
            } elseif (in_array($name, ['fecha_nacimiento', 'fecha_constitucion', 'fecha_aportacion', 'fecha_emision'])) {
                $value = preg_replace('/[^0-9]/', '', $value);
                if (strlen($value) !== 8) return null;
            } elseif (in_array($name, $UPPER_FIELDS)) {
                $value = dinToUpper($value);
            }
            $el = $dom->createElementNS($ns, $name);
            $el->appendChild($dom->createTextNode($value));
            $parent->appendChild($el);
            return $el;
        };

        $mkEl = function(DOMDocument $dom, DOMElement $parent, string $ns, string $name) {
            $el = $dom->createElementNS($ns, $name);
            $parent->appendChild($el);
            return $el;
        };

        /* ─── Helper: persona_fisica / persona_moral / fideicomiso ─── */
        $writePersona = function(DOMDocument $dom, DOMElement $parent, string $ns, array $pData) use ($addEl, $mkEl) {
            if (isset($pData['persona_fisica']) && is_array($pData['persona_fisica'])) {
                $pf = $pData['persona_fisica'];
                $pfEl = $mkEl($dom, $parent, $ns, 'persona_fisica');
                $addEl($dom, $pfEl, $ns, 'nombre', $pf['nombre'] ?? null);
                $addEl($dom, $pfEl, $ns, 'apellido_paterno', $pf['apellido_paterno'] ?? null);
                $addEl($dom, $pfEl, $ns, 'apellido_materno', $pf['apellido_materno'] ?? null);
                $addEl($dom, $pfEl, $ns, 'fecha_nacimiento', $pf['fecha_nacimiento'] ?? null);
                $addEl($dom, $pfEl, $ns, 'rfc', $pf['rfc'] ?? null);
                $addEl($dom, $pfEl, $ns, 'curp', $pf['curp'] ?? null);
                $addEl($dom, $pfEl, $ns, 'pais_nacionalidad', $pf['pais_nacionalidad'] ?? null);
                $addEl($dom, $pfEl, $ns, 'actividad_economica', $pf['actividad_economica'] ?? null);
            } elseif (isset($pData['persona_moral']) && is_array($pData['persona_moral'])) {
                $pm = $pData['persona_moral'];
                $pmEl = $mkEl($dom, $parent, $ns, 'persona_moral');
                $addEl($dom, $pmEl, $ns, 'denominacion_razon', $pm['denominacion_razon'] ?? null);
                $addEl($dom, $pmEl, $ns, 'rfc', $pm['rfc'] ?? null);
                $addEl($dom, $pmEl, $ns, 'fecha_constitucion', $pm['fecha_constitucion'] ?? null);
                $addEl($dom, $pmEl, $ns, 'pais_nacionalidad', $pm['pais_nacionalidad'] ?? null);
                $addEl($dom, $pmEl, $ns, 'giro_mercantil', $pm['giro_mercantil'] ?? null);
                if (isset($pm['representante_apoderado']) && is_array($pm['representante_apoderado'])) {
                    $rep = $pm['representante_apoderado'];
                    $repEl = $mkEl($dom, $pmEl, $ns, 'representante_apoderado');
                    $addEl($dom, $repEl, $ns, 'nombre', $rep['nombre'] ?? null);
                    $addEl($dom, $repEl, $ns, 'apellido_paterno', $rep['apellido_paterno'] ?? null);
                    $addEl($dom, $repEl, $ns, 'apellido_materno', $rep['apellido_materno'] ?? null);
                    $addEl($dom, $repEl, $ns, 'fecha_nacimiento', $rep['fecha_nacimiento'] ?? null);
                    $addEl($dom, $repEl, $ns, 'rfc', $rep['rfc'] ?? null);
                    $addEl($dom, $repEl, $ns, 'curp', $rep['curp'] ?? null);
                }
            } elseif (isset($pData['fideicomiso']) && is_array($pData['fideicomiso'])) {
                $fi = $pData['fideicomiso'];
                $fiEl = $mkEl($dom, $parent, $ns, 'fideicomiso');
                $addEl($dom, $fiEl, $ns, 'denominacion_razon', $fi['denominacion_razon'] ?? null);
                $addEl($dom, $fiEl, $ns, 'rfc', $fi['rfc'] ?? null);
                $addEl($dom, $fiEl, $ns, 'identificador_fideicomiso', $fi['identificador_fideicomiso'] ?? null);
            }
        };

        /* ─── Helper: domicilio nacional / extranjero ─── */
        $writeDomicilio = function(DOMDocument $dom, DOMElement $parent, string $ns, array $dData) use ($addEl, $mkEl) {
            if (isset($dData['nacional']) && is_array($dData['nacional'])) {
                $n = $dData['nacional'];
                $nEl = $mkEl($dom, $parent, $ns, 'nacional');
                $addEl($dom, $nEl, $ns, 'colonia', $n['colonia'] ?? null);
                $addEl($dom, $nEl, $ns, 'calle', $n['calle'] ?? null);
                $addEl($dom, $nEl, $ns, 'numero_exterior', $n['numero_exterior'] ?? null);
                $addEl($dom, $nEl, $ns, 'numero_interior', $n['numero_interior'] ?? null);
                $addEl($dom, $nEl, $ns, 'codigo_postal', $n['codigo_postal'] ?? null);
            } elseif (isset($dData['extranjero']) && is_array($dData['extranjero'])) {
                $x = $dData['extranjero'];
                $xEl = $mkEl($dom, $parent, $ns, 'extranjero');
                $addEl($dom, $xEl, $ns, 'pais', $x['pais'] ?? null);
                $addEl($dom, $xEl, $ns, 'estado_provincia', $x['estado_provincia'] ?? null);
                $addEl($dom, $xEl, $ns, 'ciudad_poblacion', $x['ciudad_poblacion'] ?? null);
                $addEl($dom, $xEl, $ns, 'colonia', $x['colonia'] ?? null);
                $addEl($dom, $xEl, $ns, 'calle', $x['calle'] ?? null);
                $addEl($dom, $xEl, $ns, 'numero_exterior', $x['numero_exterior'] ?? null);
                $addEl($dom, $xEl, $ns, 'numero_interior', $x['numero_interior'] ?? null);
                $addEl($dom, $xEl, $ns, 'codigo_postal', $x['codigo_postal'] ?? null);
            }
        };

        /* ─── Helper: teléfono ─── */
        $writeTelefono = function(DOMDocument $dom, DOMElement $parent, string $ns, array $t) use ($addEl, $mkEl) {
            $tEl = $mkEl($dom, $parent, $ns, 'telefono');
            $addEl($dom, $tEl, $ns, 'clave_pais', $t['clave_pais'] ?? null);
            $addEl($dom, $tEl, $ns, 'numero_telefono', $t['numero_telefono'] ?? null);
            $addEl($dom, $tEl, $ns, 'correo_electronico', $t['correo_electronico'] ?? null);
        };

        /* ─── Helper: datos_aportacion (numerario / especie) ─── */
        $writeDatosAportacion = function(DOMDocument $dom, DOMElement $parent, string $ns, array $da, bool $includeValorPreventa = false) use ($addEl, $mkEl) {
            $daEl = $mkEl($dom, $parent, $ns, 'datos_aportacion');
            if (isset($da['aportacion_numerario']) && is_array($da['aportacion_numerario'])) {
                $anArr = is_array($da['aportacion_numerario']) && isset($da['aportacion_numerario'][0])
                    ? $da['aportacion_numerario'] : [$da['aportacion_numerario']];
                foreach ($anArr as $an) {
                    if (!is_array($an)) continue;
                    $anEl = $mkEl($dom, $daEl, $ns, 'aportacion_numerario');
                    $addEl($dom, $anEl, $ns, 'instrumento_monetario', $an['instrumento_monetario'] ?? null);
                    $addEl($dom, $anEl, $ns, 'moneda', $an['moneda'] ?? null);
                    $addEl($dom, $anEl, $ns, 'monto_aportacion', $an['monto_aportacion'] ?? null);
                    $addEl($dom, $anEl, $ns, 'aportacion_fideicomiso', $an['aportacion_fideicomiso'] ?? null);
                    $addEl($dom, $anEl, $ns, 'nombre_institucion', $an['nombre_institucion'] ?? null);
                    if ($includeValorPreventa) {
                        $addEl($dom, $anEl, $ns, 'valor_inmueble_preventa', $an['valor_inmueble_preventa'] ?? null);
                    }
                }
            } elseif (isset($da['aportacion_especie']) && is_array($da['aportacion_especie'])) {
                $aeArr = is_array($da['aportacion_especie']) && isset($da['aportacion_especie'][0])
                    ? $da['aportacion_especie'] : [$da['aportacion_especie']];
                foreach ($aeArr as $ae) {
                    if (!is_array($ae)) continue;
                    $aeEl = $mkEl($dom, $daEl, $ns, 'aportacion_especie');
                    $addEl($dom, $aeEl, $ns, 'descripcion_bien', $ae['descripcion_bien'] ?? null);
                    $addEl($dom, $aeEl, $ns, 'monto_estimado', $ae['monto_estimado'] ?? null);
                }
            }
            return $daEl;
        };

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $archivo = $dom->createElementNS($NS, 'archivo');
        $archivo->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $XSI);
        $archivo->setAttributeNS($XSI, 'xsi:schemaLocation', $NS . ' din.xsd');
        $dom->appendChild($archivo);

        foreach ($data['informe'] as $inf) {
            if (!is_array($inf)) continue;
            $informeEl = $mkEl($dom, $archivo, $NS, 'informe');

            $addEl($dom, $informeEl, $NS, 'mes_reportado', $inf['mes_reportado'] ?? null);

            /* ── sujeto_obligado ── */
            $so = $inf['sujeto_obligado'] ?? [];
            if (!is_array($so)) $so = [];
            $soEl = $mkEl($dom, $informeEl, $NS, 'sujeto_obligado');
            $addEl($dom, $soEl, $NS, 'clave_entidad_colegiada', $so['clave_entidad_colegiada'] ?? null);
            $addEl($dom, $soEl, $NS, 'clave_sujeto_obligado', $so['clave_sujeto_obligado'] ?? null);
            $addEl($dom, $soEl, $NS, 'clave_actividad', $so['clave_actividad'] ?? null);
            $addEl($dom, $soEl, $NS, 'exento', $so['exento'] ?? null);

            /* ── avisos ── */
            $avisos = $inf['aviso'] ?? [];
            if (!is_array($avisos)) $avisos = [];
            foreach ($avisos as $av) {
                if (!is_array($av)) continue;
                $avisoEl = $mkEl($dom, $informeEl, $NS, 'aviso');

                $addEl($dom, $avisoEl, $NS, 'referencia_aviso', $av['referencia_aviso'] ?? null);

                /* modificatorio */
                if (isset($av['modificatorio']) && is_array($av['modificatorio'])) {
                    $m = $av['modificatorio'];
                    $modEl = $mkEl($dom, $avisoEl, $NS, 'modificatorio');
                    $addEl($dom, $modEl, $NS, 'folio_modificacion', $m['folio_modificacion'] ?? null);
                    $addEl($dom, $modEl, $NS, 'descripcion_modificacion', $m['descripcion_modificacion'] ?? null);
                }

                $addEl($dom, $avisoEl, $NS, 'prioridad', $av['prioridad'] ?? null);

                /* alerta */
                $alerta = $av['alerta'] ?? [];
                if (!is_array($alerta)) $alerta = [];
                $alertaEl = $mkEl($dom, $avisoEl, $NS, 'alerta');
                $addEl($dom, $alertaEl, $NS, 'tipo_alerta', $alerta['tipo_alerta'] ?? null);
                $addEl($dom, $alertaEl, $NS, 'descripcion_alerta', $alerta['descripcion_alerta'] ?? null);

                /* detalle_operaciones */
                $detalleEl = $mkEl($dom, $avisoEl, $NS, 'detalle_operaciones');
                $detalles = $av['detalle_operaciones'] ?? [];
                if (!is_array($detalles)) $detalles = [];

                foreach ($detalles as $det) {
                    if (!is_array($det)) continue;
                    $ops = $det['datos_operacion'] ?? [];
                    if (!is_array($ops)) $ops = [];

                    foreach ($ops as $op) {
                        if (!is_array($op)) continue;
                        $opEl = $mkEl($dom, $detalleEl, $NS, 'datos_operacion');
                        $addEl($dom, $opEl, $NS, 'tipo_operacion', $op['tipo_operacion'] ?? null);

                        /* ──── desarrollos_inmobiliarios ──── */
                        $desarrollosEl = $mkEl($dom, $opEl, $NS, 'desarrollos_inmobiliarios');
                        $desarrollos = $op['desarrollos_inmobiliarios'] ?? [];
                        if (!is_array($desarrollos)) $desarrollos = [];
                        foreach ($desarrollos as $d) {
                            if (!is_array($d)) continue;
                            $datosDes = $d['datos_desarrollo'] ?? [];
                            if (!is_array($datosDes)) $datosDes = [];
                            foreach ($datosDes as $dd) {
                                if (!is_array($dd)) continue;
                                $ddEl = $mkEl($dom, $desarrollosEl, $NS, 'datos_desarrollo');
                                $addEl($dom, $ddEl, $NS, 'objeto_aviso_anterior', $dd['objeto_aviso_anterior'] ?? null);
                                $addEl($dom, $ddEl, $NS, 'modificacion', $dd['modificacion'] ?? null);
                                $addEl($dom, $ddEl, $NS, 'entidad_federativa', $dd['entidad_federativa'] ?? null);
                                $addEl($dom, $ddEl, $NS, 'registro_licencia', $dd['registro_licencia'] ?? null);

                                $caracts = $dd['caracteristicas_desarrollo'] ?? [];
                                if (!is_array($caracts)) $caracts = [];
                                foreach ($caracts as $c) {
                                    if (!is_array($c)) continue;
                                    $cEl = $mkEl($dom, $ddEl, $NS, 'caracteristicas_desarrollo');
                                    $addEl($dom, $cEl, $NS, 'codigo_postal', $c['codigo_postal'] ?? null);
                                    $addEl($dom, $cEl, $NS, 'colonia', $c['colonia'] ?? null);
                                    $addEl($dom, $cEl, $NS, 'calle', $c['calle'] ?? null);
                                    $addEl($dom, $cEl, $NS, 'tipo_desarrollo', $c['tipo_desarrollo'] ?? null);
                                    $addEl($dom, $cEl, $NS, 'descripcion_desarrollo', $c['descripcion_desarrollo'] ?? null);
                                    $addEl($dom, $cEl, $NS, 'monto_desarrollo', $c['monto_desarrollo'] ?? null);
                                    $addEl($dom, $cEl, $NS, 'unidades_comercializadas', $c['unidades_comercializadas'] ?? null);
                                    $addEl($dom, $cEl, $NS, 'costo_unidad', $c['costo_unidad'] ?? null);
                                    $addEl($dom, $cEl, $NS, 'otras_empresas', $c['otras_empresas'] ?? null);
                                }
                            }
                        }

                        /* ──── aportaciones ──── */
                        $aportEl = $mkEl($dom, $opEl, $NS, 'aportaciones');
                        $aportaciones = $op['aportaciones'] ?? [];
                        if (!is_array($aportaciones)) $aportaciones = [];

                        foreach ($aportaciones as $ap) {
                            if (!is_array($ap)) continue;
                            $addEl($dom, $aportEl, $NS, 'fecha_aportacion', $ap['fecha_aportacion'] ?? null);

                            $tipos = $ap['tipo_aportacion'] ?? [];
                            if (!is_array($tipos)) $tipos = [];

                            foreach ($tipos as $ta) {
                                if (!is_array($ta)) continue;
                                $tipoAportEl = $mkEl($dom, $aportEl, $NS, 'tipo_aportacion');

                                /* ── 3.5.1.3.2.1 recursos_propios ── */
                                if (isset($ta['recursos_propios']) && is_array($ta['recursos_propios'])) {
                                    $rpEl = $mkEl($dom, $tipoAportEl, $NS, 'recursos_propios');
                                    foreach ($ta['recursos_propios'] as $rp) {
                                        if (!is_array($rp)) continue;
                                        $daps = $rp['datos_aportacion'] ?? [];
                                        if (!is_array($daps)) $daps = [];
                                        foreach ($daps as $da) {
                                            if (!is_array($da)) continue;
                                            $writeDatosAportacion($dom, $rpEl, $NS, $da);
                                        }
                                    }
                                }

                                /* ── 3.5.1.3.2.2 socios ── */
                                if (isset($ta['socios']) && is_array($ta['socios'])) {
                                    $socData = $ta['socios'];
                                    $sociosEl = $mkEl($dom, $tipoAportEl, $NS, 'socios');
                                    $addEl($dom, $sociosEl, $NS, 'numero_socios', $socData['numero_socios'] ?? null);

                                    $detSocEl = $mkEl($dom, $sociosEl, $NS, 'detalle_socios');
                                    $sociosList = $socData['detalle_socios'] ?? [];
                                    if (!is_array($sociosList)) $sociosList = [];

                                    foreach ($sociosList as $soc) {
                                        if (!is_array($soc)) continue;
                                        $dsEl = $mkEl($dom, $detSocEl, $NS, 'datos_socio');
                                        $addEl($dom, $dsEl, $NS, 'aportacion_anterior_socio', $soc['aportacion_anterior_socio'] ?? null);
                                        $addEl($dom, $dsEl, $NS, 'rfc_socio', $soc['rfc_socio'] ?? null);

                                        $tpsEl = $mkEl($dom, $dsEl, $NS, 'tipo_persona_socio');
                                        $writePersona($dom, $tpsEl, $NS, $soc['tipo_persona_socio'] ?? []);

                                        $tdsEl = $mkEl($dom, $dsEl, $NS, 'tipo_domicilio_socio');
                                        $writeDomicilio($dom, $tdsEl, $NS, $soc['tipo_domicilio_socio'] ?? []);

                                        if (isset($soc['telefono']) && is_array($soc['telefono'])) {
                                            $writeTelefono($dom, $dsEl, $NS, $soc['telefono']);
                                        }

                                        $detAportSocEl = $mkEl($dom, $dsEl, $NS, 'detalle_aportaciones');
                                        $socAports = $soc['detalle_aportaciones'] ?? [];
                                        if (!is_array($socAports)) $socAports = [];
                                        foreach ($socAports as $sa) {
                                            if (!is_array($sa) || !isset($sa['datos_aportacion'])) continue;
                                            $writeDatosAportacion($dom, $detAportSocEl, $NS, $sa['datos_aportacion']);
                                        }
                                    }
                                }

                                /* ── 3.5.1.3.2.3 terceros ── */
                                if (isset($ta['terceros']) && is_array($ta['terceros'])) {
                                    $terData = $ta['terceros'];
                                    $tercerosEl = $mkEl($dom, $tipoAportEl, $NS, 'terceros');
                                    $addEl($dom, $tercerosEl, $NS, 'numero_terceros', $terData['numero_terceros'] ?? null);

                                    $detTerEl = $mkEl($dom, $tercerosEl, $NS, 'detalle_terceros');
                                    $tercerosList = $terData['detalle_terceros'] ?? [];
                                    if (!is_array($tercerosList)) $tercerosList = [];

                                    foreach ($tercerosList as $ter) {
                                        if (!is_array($ter)) continue;
                                        $dtEl = $mkEl($dom, $detTerEl, $NS, 'datos_tercero');
                                        $addEl($dom, $dtEl, $NS, 'tipo_tercero', $ter['tipo_tercero'] ?? null);
                                        $addEl($dom, $dtEl, $NS, 'descripcion_tercero', $ter['descripcion_tercero'] ?? null);

                                        $tptEl = $mkEl($dom, $dtEl, $NS, 'tipo_persona_tercero');
                                        $writePersona($dom, $tptEl, $NS, $ter['tipo_persona_tercero'] ?? []);

                                        $detAportTerEl = $mkEl($dom, $dtEl, $NS, 'detalle_aportaciones');
                                        $terAports = $ter['detalle_aportaciones'] ?? [];
                                        if (!is_array($terAports)) $terAports = [];
                                        foreach ($terAports as $taItem) {
                                            if (!is_array($taItem) || !isset($taItem['datos_aportacion'])) continue;
                                            $da = $taItem['datos_aportacion'];
                                            if (isset($ter['valor_inmueble_preventa']) && isset($da['aportacion_numerario'])) {
                                                $anData = is_array($da['aportacion_numerario']) && isset($da['aportacion_numerario'][0])
                                                    ? $da['aportacion_numerario'] : [$da['aportacion_numerario']];
                                                foreach ($anData as &$anItem) {
                                                    if (!is_array($anItem)) continue;
                                                    $anItem['valor_inmueble_preventa'] = $ter['valor_inmueble_preventa'];
                                                }
                                                $da['aportacion_numerario'] = $anData;
                                            }
                                            $writeDatosAportacion($dom, $detAportTerEl, $NS, $da, true);
                                        }
                                    }
                                }

                                /* ── 3.5.1.3.2.4 prestamo_financiero ── */
                                if (isset($ta['prestamo_financiero']) && is_array($ta['prestamo_financiero'])) {
                                    $pfData = $ta['prestamo_financiero'];
                                    $pfEl = $mkEl($dom, $tipoAportEl, $NS, 'prestamo_financiero');
                                    $dp = $pfData['datos_prestamo'] ?? $pfData;
                                    if (!is_array($dp)) $dp = [];
                                    $dpEl = $mkEl($dom, $pfEl, $NS, 'datos_prestamo');
                                    $addEl($dom, $dpEl, $NS, 'tipo_institucion', $dp['tipo_institucion'] ?? null);
                                    $addEl($dom, $dpEl, $NS, 'institucion', $dp['institucion'] ?? null);
                                    $addEl($dom, $dpEl, $NS, 'tipo_credito', $dp['tipo_credito'] ?? null);
                                    $addEl($dom, $dpEl, $NS, 'monto_prestamo', $dp['monto_prestamo'] ?? null);
                                    $addEl($dom, $dpEl, $NS, 'moneda', $dp['moneda'] ?? null);
                                    $addEl($dom, $dpEl, $NS, 'plazo_meses', $dp['plazo_meses'] ?? null);
                                }

                                /* ── 3.5.1.3.2.5 prestamo_no_financiero ── */
                                if (isset($ta['prestamo_no_financiero']) && is_array($ta['prestamo_no_financiero'])) {
                                    $pnfData = $ta['prestamo_no_financiero'];
                                    $pnfEl = $mkEl($dom, $tipoAportEl, $NS, 'prestamo_no_financiero');
                                    $dp = $pnfData['datos_prestamo'] ?? $pnfData;
                                    if (!is_array($dp)) $dp = [];
                                    $dpEl = $mkEl($dom, $pnfEl, $NS, 'datos_prestamo');
                                    $addEl($dom, $dpEl, $NS, 'monto_prestamo', $dp['monto_prestamo'] ?? null);
                                    $addEl($dom, $dpEl, $NS, 'moneda', $dp['moneda'] ?? null);
                                    $addEl($dom, $dpEl, $NS, 'plazo_meses', $dp['plazo_meses'] ?? null);

                                    $acreedores = $dp['detalle_acreedores'] ?? [];
                                    if (is_array($acreedores) && !empty($acreedores)) {
                                        $detAcrEl = $mkEl($dom, $dpEl, $NS, 'detalle_acreedores');
                                        foreach ($acreedores as $acr) {
                                            if (!is_array($acr)) continue;
                                            $tpaEl = $mkEl($dom, $detAcrEl, $NS, 'tipo_persona_acreedor');
                                            $writePersona($dom, $tpaEl, $NS, $acr['tipo_persona_acreedor'] ?? $acr);
                                        }
                                    }
                                }

                                /* ── 3.5.1.3.2.6 financiamiento_bursatil ── */
                                if (isset($ta['financiamiento_bursatil']) && is_array($ta['financiamiento_bursatil'])) {
                                    $fbData = $ta['financiamiento_bursatil'];
                                    $fbEl = $mkEl($dom, $tipoAportEl, $NS, 'financiamiento_bursatil');
                                    $addEl($dom, $fbEl, $NS, 'fecha_emision', $fbData['fecha_emision'] ?? null);
                                    $addEl($dom, $fbEl, $NS, 'monto_solicitado', $fbData['monto_solicitado'] ?? null);
                                    $addEl($dom, $fbEl, $NS, 'monto_recibido', $fbData['monto_recibido'] ?? null);
                                }
                            }
                        }
                    }
                }
            }
        }

        $xml = $dom->saveXML();
        if ($xml === false) {
            return ['xml' => '', 'errors' => ['Error al serializar XML']];
        }

        $errors = [];
        if ($xsdPath && is_file($xsdPath)) {
            $ok = $dom->schemaValidate($xsdPath);
            if (!$ok) {
                foreach (libxml_get_errors() as $e) {
                    $errors[] = trim($e->message) . " (línea {$e->line})";
                }
                libxml_clear_errors();
            }
        }

        return $errors ? ['xml' => $xml, 'errors' => $errors] : ['xml' => $xml];
    }
}
