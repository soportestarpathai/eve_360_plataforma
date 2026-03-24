<?php
/**
 * Helper XML DON (Fracción XIII - Donativos)
 * Estructura esperada: archivo > informe > sujeto_obligado > aviso > persona_aviso > detalle_operaciones.
 */

if (!function_exists('donToUpper')) {
    function donToUpper($val): string {
        $v = trim((string)$val);
        if ($v === '') {
            return '';
        }
        return mb_strtoupper($v, 'UTF-8');
    }
}

if (!function_exists('donFormatMonto')) {
    function donFormatMonto($value): string {
        if ($value === null || $value === '') {
            return '0.00';
        }
        return number_format((float)$value, 2, '.', '');
    }
}

if (!function_exists('donSanitizeRef')) {
    function donSanitizeRef($val): string {
        $v = donToUpper($val);
        $v = preg_replace('/[^A-Z\x{00D1}0-9]/u', '', $v);
        return substr($v, 0, 14);
    }
}

if (!function_exists('donSanitizeDesc')) {
    function donSanitizeDesc($val, int $max = 3000): string {
        $v = donToUpper($val);
        $v = preg_replace('/[^A-Z\x{00D1}0-9 \-\.,\':\/$]/u', '', $v);
        $v = trim($v);
        return substr($v, 0, $max);
    }
}

if (!function_exists('donSanitizeClaveSO')) {
    function donSanitizeClaveSO($val): string {
        $v = donToUpper($val);
        $v = preg_replace('/[^A-Z\x{00D1}0-9&]/u', '', $v);
        return substr($v, 0, 13);
    }
}

if (!function_exists('donValidarClaveSO')) {
    function donValidarClaveSO($val): bool {
        $v = trim((string)$val);
        if ($v === '') {
            return false;
        }
        return (bool)preg_match('/^[A-Z\x{00D1}&]{3,4}\d{6}[A-Z0-9]{3}$/u', donToUpper($v));
    }
}

if (!function_exists('donNormalizeDate8')) {
    function donNormalizeDate8($val): string {
        $v = preg_replace('/[^0-9]/', '', (string)$val);
        return strlen($v) === 8 ? $v : '';
    }
}

if (!function_exists('donNormalizeDate6')) {
    function donNormalizeDate6($val): string {
        $v = preg_replace('/[^0-9]/', '', (string)$val);
        return strlen($v) === 6 ? $v : '';
    }
}

if (!function_exists('donArrayWrap')) {
    function donArrayWrap($value): array {
        if (!is_array($value)) {
            return [];
        }
        if (array_is_list($value)) {
            return $value;
        }
        return [$value];
    }
}

if (!function_exists('donArraySumMontosOperacion')) {
    /**
     * Suma montos de operaciones DON (numerario y especie).
     */
    function donArraySumMontosOperacion(array $data): float {
        $sum = 0.0;
        $informes = donArrayWrap($data['informe'] ?? []);
        foreach ($informes as $inf) {
            if (!is_array($inf)) {
                continue;
            }
            foreach (donArrayWrap($inf['aviso'] ?? []) as $av) {
                if (!is_array($av)) {
                    continue;
                }
                foreach (donArrayWrap($av['detalle_operaciones'] ?? []) as $det) {
                    if (!is_array($det)) {
                        continue;
                    }
                    foreach (donArrayWrap($det['datos_operacion'] ?? []) as $op) {
                        if (!is_array($op)) {
                            continue;
                        }
                        foreach (donArrayWrap($op['datos_donativo'] ?? []) as $dd) {
                            if (!is_array($dd)) {
                                continue;
                            }
                            foreach (donArrayWrap($dd['tipo_donativo'] ?? []) as $td) {
                                if (!is_array($td)) {
                                    continue;
                                }
                                if (isset($td['liquidacion_numerario']) && is_array($td['liquidacion_numerario'])) {
                                    $sum += (float)($td['liquidacion_numerario']['monto_operacion'] ?? 0);
                                }
                                if (isset($td['liquidacion_especie']) && is_array($td['liquidacion_especie'])) {
                                    $sum += (float)($td['liquidacion_especie']['monto_operacion'] ?? 0);
                                }
                            }
                        }
                    }
                }
            }
        }
        return $sum;
    }
}

if (!function_exists('generateDONXml')) {
    function generateDONXml(array $data): array {
        $NS = 'http://www.uif.shcp.gob.mx/recepcion/don';
        $XSI = 'http://www.w3.org/2001/XMLSchema-instance';
        $errors = [];

        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $mkEl = function (DOMElement $parent, string $name) use ($dom, $NS): DOMElement {
            $el = $dom->createElementNS($NS, $name);
            $parent->appendChild($el);
            return $el;
        };

        $addEl = function (DOMElement $parent, string $name, $value, string $mode = 'text') use ($dom, $NS): ?DOMElement {
            if ($value === null) {
                return null;
            }
            $value = trim((string)$value);
            if ($value === '') {
                return null;
            }
            switch ($mode) {
                case 'upper':
                    $value = donToUpper($value);
                    break;
                case 'date8':
                    $value = donNormalizeDate8($value);
                    break;
                case 'date6':
                    $value = donNormalizeDate6($value);
                    break;
                case 'desc':
                    $value = donSanitizeDesc($value, 3000);
                    break;
                case 'ref':
                    $value = donSanitizeRef($value);
                    break;
                case 'clave_so':
                    $value = donSanitizeClaveSO($value);
                    break;
                case 'cp':
                    $value = substr(preg_replace('/[^0-9]/', '', $value), 0, 5);
                    break;
                case 'cp_ext':
                    $value = substr(preg_replace('/[^A-Z\x{00D1}0-9]/u', '', donToUpper($value)), 0, 12);
                    break;
                case 'monto':
                    $value = donFormatMonto($value);
                    break;
                case 'num':
                    $value = preg_replace('/[^0-9]/', '', $value);
                    break;
                default:
                    break;
            }
            if ($value === '') {
                return null;
            }
            $el = $dom->createElementNS($NS, $name);
            $el->appendChild($dom->createTextNode($value));
            $parent->appendChild($el);
            return $el;
        };

        $writePersona = function (DOMElement $tipoPersonaEl, array $tipoPersona) use ($mkEl, $addEl) {
            if (isset($tipoPersona['persona_fisica']) && is_array($tipoPersona['persona_fisica'])) {
                $pf = $tipoPersona['persona_fisica'];
                $pfEl = $mkEl($tipoPersonaEl, 'persona_fisica');
                $addEl($pfEl, 'nombre', $pf['nombre'] ?? null, 'upper');
                $addEl($pfEl, 'apellido_paterno', $pf['apellido_paterno'] ?? null, 'upper');
                $addEl($pfEl, 'apellido_materno', $pf['apellido_materno'] ?? null, 'upper');
                $addEl($pfEl, 'fecha_nacimiento', $pf['fecha_nacimiento'] ?? null, 'date8');
                $addEl($pfEl, 'rfc', $pf['rfc'] ?? null, 'upper');
                $addEl($pfEl, 'curp', $pf['curp'] ?? null, 'upper');
                $addEl($pfEl, 'pais_nacionalidad', $pf['pais_nacionalidad'] ?? null, 'upper');
                if (array_key_exists('actividad_economica', $pf)) {
                    $addEl($pfEl, 'actividad_economica', $pf['actividad_economica'], 'num');
                }
                return;
            }

            if (isset($tipoPersona['persona_moral']) && is_array($tipoPersona['persona_moral'])) {
                $pm = $tipoPersona['persona_moral'];
                $pmEl = $mkEl($tipoPersonaEl, 'persona_moral');
                $addEl($pmEl, 'denominacion_razon', $pm['denominacion_razon'] ?? null, 'upper');
                $addEl($pmEl, 'fecha_constitucion', $pm['fecha_constitucion'] ?? null, 'date8');
                $addEl($pmEl, 'rfc', $pm['rfc'] ?? null, 'upper');
                $addEl($pmEl, 'pais_nacionalidad', $pm['pais_nacionalidad'] ?? null, 'upper');
                if (array_key_exists('giro_mercantil', $pm)) {
                    $addEl($pmEl, 'giro_mercantil', $pm['giro_mercantil'], 'num');
                }
                if (isset($pm['representante_apoderado']) && is_array($pm['representante_apoderado'])) {
                    $rep = $pm['representante_apoderado'];
                    $repEl = $mkEl($pmEl, 'representante_apoderado');
                    $addEl($repEl, 'nombre', $rep['nombre'] ?? null, 'upper');
                    $addEl($repEl, 'apellido_paterno', $rep['apellido_paterno'] ?? null, 'upper');
                    $addEl($repEl, 'apellido_materno', $rep['apellido_materno'] ?? null, 'upper');
                    $addEl($repEl, 'fecha_nacimiento', $rep['fecha_nacimiento'] ?? null, 'date8');
                    $addEl($repEl, 'rfc', $rep['rfc'] ?? null, 'upper');
                    $addEl($repEl, 'curp', $rep['curp'] ?? null, 'upper');
                }
                return;
            }

            if (isset($tipoPersona['fideicomiso']) && is_array($tipoPersona['fideicomiso'])) {
                $fi = $tipoPersona['fideicomiso'];
                $fiEl = $mkEl($tipoPersonaEl, 'fideicomiso');
                $addEl($fiEl, 'denominacion_razon', $fi['denominacion_razon'] ?? null, 'upper');
                $addEl($fiEl, 'rfc', $fi['rfc'] ?? null, 'upper');
                $addEl($fiEl, 'identificador_fideicomiso', $fi['identificador_fideicomiso'] ?? null, 'upper');
                if (isset($fi['apoderado_delegado']) && is_array($fi['apoderado_delegado'])) {
                    $ap = $fi['apoderado_delegado'];
                    $apEl = $mkEl($fiEl, 'apoderado_delegado');
                    $addEl($apEl, 'nombre', $ap['nombre'] ?? null, 'upper');
                    $addEl($apEl, 'apellido_paterno', $ap['apellido_paterno'] ?? null, 'upper');
                    $addEl($apEl, 'apellido_materno', $ap['apellido_materno'] ?? null, 'upper');
                    $addEl($apEl, 'fecha_nacimiento', $ap['fecha_nacimiento'] ?? null, 'date8');
                    $addEl($apEl, 'rfc', $ap['rfc'] ?? null, 'upper');
                    $addEl($apEl, 'curp', $ap['curp'] ?? null, 'upper');
                }
            }
        };

        $writePersonaSimple = function (DOMElement $tipoPersonaEl, array $tipoPersona) use ($mkEl, $addEl) {
            if (isset($tipoPersona['persona_fisica']) && is_array($tipoPersona['persona_fisica'])) {
                $pf = $tipoPersona['persona_fisica'];
                $pfEl = $mkEl($tipoPersonaEl, 'persona_fisica');
                $addEl($pfEl, 'nombre', $pf['nombre'] ?? null, 'upper');
                $addEl($pfEl, 'apellido_paterno', $pf['apellido_paterno'] ?? null, 'upper');
                $addEl($pfEl, 'apellido_materno', $pf['apellido_materno'] ?? null, 'upper');
                $addEl($pfEl, 'fecha_nacimiento', $pf['fecha_nacimiento'] ?? null, 'date8');
                $addEl($pfEl, 'rfc', $pf['rfc'] ?? null, 'upper');
                $addEl($pfEl, 'curp', $pf['curp'] ?? null, 'upper');
                $addEl($pfEl, 'pais_nacionalidad', $pf['pais_nacionalidad'] ?? null, 'upper');
                return;
            }

            if (isset($tipoPersona['persona_moral']) && is_array($tipoPersona['persona_moral'])) {
                $pm = $tipoPersona['persona_moral'];
                $pmEl = $mkEl($tipoPersonaEl, 'persona_moral');
                $addEl($pmEl, 'denominacion_razon', $pm['denominacion_razon'] ?? null, 'upper');
                $addEl($pmEl, 'fecha_constitucion', $pm['fecha_constitucion'] ?? null, 'date8');
                $addEl($pmEl, 'rfc', $pm['rfc'] ?? null, 'upper');
                $addEl($pmEl, 'pais_nacionalidad', $pm['pais_nacionalidad'] ?? null, 'upper');
                return;
            }

            if (isset($tipoPersona['fideicomiso']) && is_array($tipoPersona['fideicomiso'])) {
                $fi = $tipoPersona['fideicomiso'];
                $fiEl = $mkEl($tipoPersonaEl, 'fideicomiso');
                $addEl($fiEl, 'denominacion_razon', $fi['denominacion_razon'] ?? null, 'upper');
                $addEl($fiEl, 'rfc', $fi['rfc'] ?? null, 'upper');
                $addEl($fiEl, 'identificador_fideicomiso', $fi['identificador_fideicomiso'] ?? null, 'upper');
            }
        };

        $writeDomicilio = function (DOMElement $tipoDomicilioEl, array $tipoDomicilio) use ($mkEl, $addEl) {
            if (isset($tipoDomicilio['nacional']) && is_array($tipoDomicilio['nacional'])) {
                $n = $tipoDomicilio['nacional'];
                $nEl = $mkEl($tipoDomicilioEl, 'nacional');
                $addEl($nEl, 'colonia', $n['colonia'] ?? null, 'upper');
                $addEl($nEl, 'calle', $n['calle'] ?? null, 'upper');
                $addEl($nEl, 'numero_exterior', $n['numero_exterior'] ?? null, 'upper');
                $addEl($nEl, 'numero_interior', $n['numero_interior'] ?? null, 'upper');
                $addEl($nEl, 'codigo_postal', $n['codigo_postal'] ?? null, 'cp');
                return;
            }

            if (isset($tipoDomicilio['extranjero']) && is_array($tipoDomicilio['extranjero'])) {
                $x = $tipoDomicilio['extranjero'];
                $xEl = $mkEl($tipoDomicilioEl, 'extranjero');
                $addEl($xEl, 'pais', $x['pais'] ?? null, 'upper');
                $addEl($xEl, 'estado_provincia', $x['estado_provincia'] ?? null, 'upper');
                $addEl($xEl, 'ciudad_poblacion', $x['ciudad_poblacion'] ?? null, 'upper');
                $addEl($xEl, 'colonia', $x['colonia'] ?? null, 'upper');
                $addEl($xEl, 'calle', $x['calle'] ?? null, 'upper');
                $addEl($xEl, 'numero_exterior', $x['numero_exterior'] ?? null, 'upper');
                $addEl($xEl, 'numero_interior', $x['numero_interior'] ?? null, 'upper');
                $addEl($xEl, 'codigo_postal', $x['codigo_postal'] ?? null, 'cp_ext');
            }
        };

        $archivo = $dom->createElementNS($NS, 'archivo');
        $archivo->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $XSI);
        $archivo->setAttributeNS($XSI, 'xsi:schemaLocation', $NS . ' don.xsd');
        $dom->appendChild($archivo);

        foreach (donArrayWrap($data['informe'] ?? []) as $informe) {
            if (!is_array($informe)) {
                continue;
            }
            $informeEl = $mkEl($archivo, 'informe');
            $addEl($informeEl, 'mes_reportado', $informe['mes_reportado'] ?? null, 'date6');

            $so = is_array($informe['sujeto_obligado'] ?? null) ? $informe['sujeto_obligado'] : [];
            $soEl = $mkEl($informeEl, 'sujeto_obligado');
            if (trim((string)($so['clave_entidad_colegiada'] ?? '')) !== '') {
                $addEl($soEl, 'clave_entidad_colegiada', $so['clave_entidad_colegiada'], 'upper');
            }
            $addEl($soEl, 'clave_sujeto_obligado', $so['clave_sujeto_obligado'] ?? null, 'clave_so');
            $addEl($soEl, 'clave_actividad', $so['clave_actividad'] ?? 'DON', 'upper');
            if ((string)($so['exento'] ?? '') === '1') {
                $addEl($soEl, 'exento', '1', 'num');
            }

            foreach (donArrayWrap($informe['aviso'] ?? []) as $aviso) {
                if (!is_array($aviso)) {
                    continue;
                }
                $avisoEl = $mkEl($informeEl, 'aviso');
                $addEl($avisoEl, 'referencia_aviso', $aviso['referencia_aviso'] ?? null, 'ref');

                if (isset($aviso['modificatorio']) && is_array($aviso['modificatorio'])) {
                    $m = $aviso['modificatorio'];
                    $mEl = $mkEl($avisoEl, 'modificatorio');
                    $addEl($mEl, 'folio_modificacion', $m['folio_modificacion'] ?? null, 'upper');
                    $addEl($mEl, 'descripcion_modificacion', $m['descripcion_modificacion'] ?? null, 'desc');
                }

                $addEl($avisoEl, 'prioridad', $aviso['prioridad'] ?? null, 'num');

                $alerta = is_array($aviso['alerta'] ?? null) ? $aviso['alerta'] : [];
                $alertaEl = $mkEl($avisoEl, 'alerta');
                $addEl($alertaEl, 'tipo_alerta', $alerta['tipo_alerta'] ?? null, 'num');
                if (trim((string)($alerta['descripcion_alerta'] ?? '')) !== '') {
                    $addEl($alertaEl, 'descripcion_alerta', $alerta['descripcion_alerta'] ?? null, 'desc');
                }

                foreach (donArrayWrap($aviso['persona_aviso'] ?? []) as $pa) {
                    if (!is_array($pa)) {
                        continue;
                    }
                    $paEl = $mkEl($avisoEl, 'persona_aviso');

                    $tp = is_array($pa['tipo_persona'] ?? null) ? $pa['tipo_persona'] : [];
                    $tpEl = $mkEl($paEl, 'tipo_persona');
                    $writePersona($tpEl, $tp);

                    if (isset($pa['tipo_domicilio']) && is_array($pa['tipo_domicilio'])) {
                        $tdEl = $mkEl($paEl, 'tipo_domicilio');
                        $writeDomicilio($tdEl, $pa['tipo_domicilio']);
                    }

                    if (isset($pa['telefono']) && is_array($pa['telefono'])) {
                        $tel = $pa['telefono'];
                        if (trim((string)($tel['numero_telefono'] ?? '')) !== '') {
                            $telEl = $mkEl($paEl, 'telefono');
                            $addEl($telEl, 'clave_pais', $tel['clave_pais'] ?? null, 'upper');
                            $addEl($telEl, 'numero_telefono', $tel['numero_telefono'] ?? null, 'num');
                            if (trim((string)($tel['correo_electronico'] ?? '')) !== '') {
                                $addEl($telEl, 'correo_electronico', $tel['correo_electronico'], 'upper');
                            }
                        }
                    }
                }

                foreach (donArrayWrap($aviso['dueno_beneficiario'] ?? []) as $db) {
                    if (!is_array($db)) {
                        continue;
                    }
                    $tp = is_array($db['tipo_persona'] ?? null) ? $db['tipo_persona'] : [];
                    if (empty($tp)) {
                        continue;
                    }
                    $dbEl = $mkEl($avisoEl, 'dueno_beneficiario');
                    $dbTpEl = $mkEl($dbEl, 'tipo_persona');
                    $writePersonaSimple($dbTpEl, $tp);
                }

                $detalleEl = $mkEl($avisoEl, 'detalle_operaciones');
                foreach (donArrayWrap($aviso['detalle_operaciones'] ?? []) as $det) {
                    if (!is_array($det)) {
                        continue;
                    }
                    foreach (donArrayWrap($det['datos_operacion'] ?? []) as $op) {
                        if (!is_array($op)) {
                            continue;
                        }
                        $opEl = $mkEl($detalleEl, 'datos_operacion');
                        $addEl($opEl, 'fecha_operacion', $op['fecha_operacion'] ?? null, 'date8');
                        $addEl($opEl, 'codigo_postal', $op['codigo_postal'] ?? null, 'cp');
                        $addEl($opEl, 'tipo_operacion', $op['tipo_operacion'] ?? null, 'num');

                        $datosDonativoEl = $mkEl($opEl, 'datos_donativo');
                        foreach (donArrayWrap($op['datos_donativo'] ?? []) as $dd) {
                            if (!is_array($dd)) {
                                continue;
                            }
                            foreach (donArrayWrap($dd['tipo_donativo'] ?? []) as $td) {
                                if (!is_array($td)) {
                                    continue;
                                }
                                $tipoDonEl = $mkEl($datosDonativoEl, 'tipo_donativo');
                                $hasNumerario = isset($td['liquidacion_numerario']) && is_array($td['liquidacion_numerario']);
                                $hasEspecie = isset($td['liquidacion_especie']) && is_array($td['liquidacion_especie']);

                                if ($hasNumerario) {
                                    $n = $td['liquidacion_numerario'];
                                    $nEl = $mkEl($tipoDonEl, 'liquidacion_numerario');
                                    $addEl($nEl, 'fecha_pago', $n['fecha_pago'] ?? null, 'date8');
                                    $addEl($nEl, 'instrumento_monetario', $n['instrumento_monetario'] ?? null, 'num');
                                    $addEl($nEl, 'moneda', $n['moneda'] ?? null, 'num');
                                    $addEl($nEl, 'monto_operacion', $n['monto_operacion'] ?? null, 'monto');
                                } elseif ($hasEspecie) {
                                    $e = $td['liquidacion_especie'];
                                    $eEl = $mkEl($tipoDonEl, 'liquidacion_especie');
                                    $addEl($eEl, 'monto_operacion', $e['monto_operacion'] ?? null, 'monto');
                                    $addEl($eEl, 'moneda', $e['moneda'] ?? null, 'num');
                                    $addEl($eEl, 'bien_donado', $e['bien_donado'] ?? null, 'num');

                                    $datosBien = is_array($e['datos_bien_donado'] ?? null) ? $e['datos_bien_donado'] : [];
                                    if (!empty($datosBien)) {
                                        $dbEl = $mkEl($eEl, 'datos_bien_donado');
                                        if (isset($datosBien['datos_inmueble']) && is_array($datosBien['datos_inmueble'])) {
                                            $di = $datosBien['datos_inmueble'];
                                            $diEl = $mkEl($dbEl, 'datos_inmueble');
                                            $addEl($diEl, 'tipo_inmueble', $di['tipo_inmueble'] ?? null, 'num');
                                            $addEl($diEl, 'codigo_postal', $di['codigo_postal'] ?? null, 'cp');
                                            $addEl($diEl, 'folio_real', $di['folio_real'] ?? null, 'upper');
                                        } elseif (isset($datosBien['datos_otro']) && is_array($datosBien['datos_otro'])) {
                                            $do = $datosBien['datos_otro'];
                                            $doEl = $mkEl($dbEl, 'datos_otro');
                                            $addEl($doEl, 'descripcion_bien_donado', $do['descripcion_bien_donado'] ?? null, 'desc');
                                        }
                                    }
                                } else {
                                    $errors[] = 'tipo_donativo sin liquidacion_numerario ni liquidacion_especie';
                                }
                            }
                        }
                    }
                }
            }
        }

        return ['xml' => $dom->saveXML(), 'errors' => $errors];
    }
}
