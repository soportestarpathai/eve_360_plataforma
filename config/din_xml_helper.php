<?php
/**
 * Helper para generar XML DIN (Desarrollo Inmobiliario) según XSD UIF/SHCP
 * Usado por generate_din.php y api/registrar_operacion_din.php
 */

if (!function_exists('formatMontoDin')) {
    function formatMontoDin($val): string {
        if ($val === null || $val === '') return '0.00';
        $n = floatval($val);
        return number_format($n, 2, '.', '');
    }
}

if (!function_exists('generateDINXml')) {
    /**
     * Genera XML DIN desde estructura de datos
     * @param array $data Debe contener informe[]
     * @param string|null $xsdPath Ruta al din.xsd para validación (opcional)
     * @return array ['xml' => string, 'errors' => array] o ['xml' => string] si ok
     */
    function generateDINXml(array $data, ?string $xsdPath = null): array {
        $NS_DIN = 'http://www.uif.shcp.gob.mx/recepcion/din';
        $NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $addTextEl = function(DOMDocument $dom, DOMElement $parent, string $ns, string $name, $value) {
            if ($value === null) return null;
            if (is_numeric($value) && in_array($name, ['monto_desarrollo', 'unidades_comercializadas', 'costo_unidad', 'monto_aportacion', 'monto_estimado'])) {
                $value = formatMontoDin($value);
            }
            $value = trim((string)$value);
            if ($value === '') return null;
            $el = $dom->createElementNS($ns, $name);
            $el->appendChild($dom->createTextNode($value));
            $parent->appendChild($el);
            return $el;
        };

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $archivo = $dom->createElementNS($NS_DIN, 'archivo');
        $archivo->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $NS_XSI);
        $archivo->setAttributeNS($NS_XSI, 'xsi:schemaLocation', $NS_DIN . ' din.xsd');
        $dom->appendChild($archivo);

        foreach ($data['informe'] as $inf) {
            if (!is_array($inf)) continue;
            $informeEl = $dom->createElementNS($NS_DIN, 'informe');
            $archivo->appendChild($informeEl);

            $addTextEl($dom, $informeEl, $NS_DIN, 'mes_reportado', $inf['mes_reportado'] ?? null);

            $so = $inf['sujeto_obligado'] ?? [];
            if (!is_array($so)) $so = [];
            $soEl = $dom->createElementNS($NS_DIN, 'sujeto_obligado');
            $informeEl->appendChild($soEl);
            $addTextEl($dom, $soEl, $NS_DIN, 'clave_entidad_colegiada', $so['clave_entidad_colegiada'] ?? null);
            $addTextEl($dom, $soEl, $NS_DIN, 'clave_sujeto_obligado', $so['clave_sujeto_obligado'] ?? null);
            $addTextEl($dom, $soEl, $NS_DIN, 'clave_actividad', $so['clave_actividad'] ?? null);

            $avisos = $inf['aviso'] ?? [];
            if (!is_array($avisos)) $avisos = [];
            foreach ($avisos as $av) {
                if (!is_array($av)) continue;
                $avisoEl = $dom->createElementNS($NS_DIN, 'aviso');
                $informeEl->appendChild($avisoEl);

                $addTextEl($dom, $avisoEl, $NS_DIN, 'referencia_aviso', $av['referencia_aviso'] ?? null);
                if (isset($av['modificatorio']) && is_array($av['modificatorio'])) {
                    $m = $av['modificatorio'];
                    $modEl = $dom->createElementNS($NS_DIN, 'modificatorio');
                    $avisoEl->appendChild($modEl);
                    $addTextEl($dom, $modEl, $NS_DIN, 'folio_modificacion', $m['folio_modificacion'] ?? null);
                    $addTextEl($dom, $modEl, $NS_DIN, 'descripcion_modificacion', $m['descripcion_modificacion'] ?? null);
                }
                $addTextEl($dom, $avisoEl, $NS_DIN, 'prioridad', $av['prioridad'] ?? null);

                $alerta = $av['alerta'] ?? [];
                if (!is_array($alerta)) $alerta = [];
                $alertaEl = $dom->createElementNS($NS_DIN, 'alerta');
                $avisoEl->appendChild($alertaEl);
                $addTextEl($dom, $alertaEl, $NS_DIN, 'tipo_alerta', $alerta['tipo_alerta'] ?? null);
                $addTextEl($dom, $alertaEl, $NS_DIN, 'descripcion_alerta', $alerta['descripcion_alerta'] ?? null);

                $detalleEl = $dom->createElementNS($NS_DIN, 'detalle_operaciones');
                $avisoEl->appendChild($detalleEl);

                $detalles = $av['detalle_operaciones'] ?? [];
                if (!is_array($detalles)) $detalles = [];
                foreach ($detalles as $det) {
                    if (!is_array($det)) continue;
                    $ops = $det['datos_operacion'] ?? [];
                    if (!is_array($ops)) $ops = [];
                    foreach ($ops as $op) {
                        if (!is_array($op)) continue;
                        $opEl = $dom->createElementNS($NS_DIN, 'datos_operacion');
                        $detalleEl->appendChild($opEl);

                        $addTextEl($dom, $opEl, $NS_DIN, 'tipo_operacion', $op['tipo_operacion'] ?? null);

                        $desarrollosEl = $dom->createElementNS($NS_DIN, 'desarrollos_inmobiliarios');
                        $opEl->appendChild($desarrollosEl);
                        $desarrollos = $op['desarrollos_inmobiliarios'] ?? [];
                        if (!is_array($desarrollos)) $desarrollos = [];
                        foreach ($desarrollos as $d) {
                            if (!is_array($d)) continue;
                            $datosDes = $d['datos_desarrollo'] ?? [];
                            if (!is_array($datosDes)) $datosDes = [];
                            foreach ($datosDes as $dd) {
                                if (!is_array($dd)) continue;
                                $ddEl = $dom->createElementNS($NS_DIN, 'datos_desarrollo');
                                $desarrollosEl->appendChild($ddEl);
                                $addTextEl($dom, $ddEl, $NS_DIN, 'objeto_aviso_anterior', $dd['objeto_aviso_anterior'] ?? null);
                                $addTextEl($dom, $ddEl, $NS_DIN, 'modificacion', $dd['modificacion'] ?? null);
                                $addTextEl($dom, $ddEl, $NS_DIN, 'entidad_federativa', $dd['entidad_federativa'] ?? null);
                                $addTextEl($dom, $ddEl, $NS_DIN, 'registro_licencia', $dd['registro_licencia'] ?? null);

                                $caracts = $dd['caracteristicas_desarrollo'] ?? [];
                                if (!is_array($caracts)) $caracts = [];
                                foreach ($caracts as $c) {
                                    if (!is_array($c)) continue;
                                    $cEl = $dom->createElementNS($NS_DIN, 'caracteristicas_desarrollo');
                                    $ddEl->appendChild($cEl);
                                    $addTextEl($dom, $cEl, $NS_DIN, 'codigo_postal', $c['codigo_postal'] ?? null);
                                    $addTextEl($dom, $cEl, $NS_DIN, 'colonia', $c['colonia'] ?? null);
                                    $addTextEl($dom, $cEl, $NS_DIN, 'calle', $c['calle'] ?? null);
                                    $addTextEl($dom, $cEl, $NS_DIN, 'tipo_desarrollo', $c['tipo_desarrollo'] ?? null);
                                    $addTextEl($dom, $cEl, $NS_DIN, 'descripcion_desarrollo', $c['descripcion_desarrollo'] ?? null);
                                    $addTextEl($dom, $cEl, $NS_DIN, 'monto_desarrollo', $c['monto_desarrollo'] ?? null);
                                    $addTextEl($dom, $cEl, $NS_DIN, 'unidades_comercializadas', $c['unidades_comercializadas'] ?? null);
                                    $addTextEl($dom, $cEl, $NS_DIN, 'costo_unidad', $c['costo_unidad'] ?? null);
                                    $addTextEl($dom, $cEl, $NS_DIN, 'otras_empresas', $c['otras_empresas'] ?? null);
                                }
                            }
                        }

                        $aportEl = $dom->createElementNS($NS_DIN, 'aportaciones');
                        $opEl->appendChild($aportEl);
                        $aportaciones = $op['aportaciones'] ?? [];
                        if (!is_array($aportaciones)) $aportaciones = [];
                        foreach ($aportaciones as $ap) {
                            if (!is_array($ap)) continue;
                            $addTextEl($dom, $aportEl, $NS_DIN, 'fecha_aportacion', $ap['fecha_aportacion'] ?? null);
                            $tipos = $ap['tipo_aportacion'] ?? [];
                            if (!is_array($tipos)) $tipos = [];
                            foreach ($tipos as $ta) {
                                if (!is_array($ta)) continue;
                                $tipoAportEl = $dom->createElementNS($NS_DIN, 'tipo_aportacion');
                                $aportEl->appendChild($tipoAportEl);
                                if (isset($ta['recursos_propios']) && is_array($ta['recursos_propios'])) {
                                    $rpEl = $dom->createElementNS($NS_DIN, 'recursos_propios');
                                    $tipoAportEl->appendChild($rpEl);
                                    foreach ($ta['recursos_propios'] as $rp) {
                                        if (!is_array($rp)) continue;
                                        $daps = $rp['datos_aportacion'] ?? [];
                                        if (!is_array($daps)) $daps = [];
                                        foreach ($daps as $da) {
                                            if (!is_array($da)) continue;
                                            $daEl = $dom->createElementNS($NS_DIN, 'datos_aportacion');
                                            $rpEl->appendChild($daEl);
                                            if (isset($da['aportacion_numerario']) && is_array($da['aportacion_numerario'])) {
                                                foreach ($da['aportacion_numerario'] as $an) {
                                                    if (!is_array($an)) continue;
                                                    $anEl = $dom->createElementNS($NS_DIN, 'aportacion_numerario');
                                                    $daEl->appendChild($anEl);
                                                    $addTextEl($dom, $anEl, $NS_DIN, 'instrumento_monetario', $an['instrumento_monetario'] ?? null);
                                                    $addTextEl($dom, $anEl, $NS_DIN, 'moneda', $an['moneda'] ?? null);
                                                    $addTextEl($dom, $anEl, $NS_DIN, 'monto_aportacion', $an['monto_aportacion'] ?? null);
                                                    $addTextEl($dom, $anEl, $NS_DIN, 'aportacion_fideicomiso', $an['aportacion_fideicomiso'] ?? null);
                                                    $addTextEl($dom, $anEl, $NS_DIN, 'nombre_institucion', $an['nombre_institucion'] ?? null);
                                                }
                                            } elseif (isset($da['aportacion_especie']) && is_array($da['aportacion_especie'])) {
                                                foreach ($da['aportacion_especie'] as $ae) {
                                                    if (!is_array($ae)) continue;
                                                    $aeEl = $dom->createElementNS($NS_DIN, 'aportacion_especie');
                                                    $daEl->appendChild($aeEl);
                                                    $addTextEl($dom, $aeEl, $NS_DIN, 'descripcion_bien', $ae['descripcion_bien'] ?? null);
                                                    $addTextEl($dom, $aeEl, $NS_DIN, 'monto_estimado', $ae['monto_estimado'] ?? null);
                                                }
                                            }
                                        }
                                    }
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
