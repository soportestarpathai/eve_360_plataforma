<?php
/**
 * Helper para generar XML TSC (Tarjetas de Servicio y de Crédito) - Fracción II
 * Según XSD http://www.uif.shcp.gob.mx/recepcion/tsc
 * Estructura: archivo > informe > sujeto_obligado > aviso > persona_aviso > detalle_operaciones
 */

if (!function_exists('formatMontoTsc')) {
    function formatMontoTsc($val): string {
        if ($val === null || $val === '') return '0.00';
        $n = floatval($val);
        return number_format($n, 2, '.', '');
    }
}

if (!function_exists('tscToUpper')) {
    /** Convierte a mayúsculas para patrones nombre_type, direccion, etc. */
    function tscToUpper($val): string {
        $v = trim((string)$val);
        if ($v === '') return '';
        return mb_strtoupper($v, 'UTF-8');
    }
}

if (!function_exists('tscSanitizeRef')) {
    /** referencia_aviso: [A-ZÑ0-9]{1,14} | numero_identificador: [A-Z0-9]{1,18} */
    function tscSanitizeRef($val, $allowEnie = true): string {
        $v = tscToUpper($val);
        $v = preg_replace($allowEnie ? '/[^A-Z\x{00D1}0-9]/u' : '/[^A-Z0-9]/u', '', $v);
        return substr($v, 0, $allowEnie ? 14 : 18);
    }
}

if (!function_exists('tscSanitizeDesc')) {
    /** descripcion_1-3000: [A-ZÑ\d \-\.,':/$]{1,3000} */
    function tscSanitizeDesc($val): string {
        $v = tscToUpper(trim((string)$val));
        $v = preg_replace('/[^A-Z\x{00D1}0-9 \-\.,\':\/$]/u', '', $v);
        return substr($v, 0, 3000);
    }
}

/** clave_sujeto_obligado: formato RFC (3-4 letras, 6 dígitos, 3 caracteres). Solo A-ZÑ0-9, 12-13 chars */
if (!function_exists('tscSanitizeClaveSO')) {
    function tscSanitizeClaveSO($val): string {
        $v = tscToUpper(trim((string)$val));
        $v = preg_replace('/[^A-Z\x{00D1}0-9]/u', '', $v);
        return substr($v, 0, 13);
    }
}

/** Valida clave_sujeto_obligado con regex RFC: 3-4 letras + 6 dígitos + 3 caracteres */
if (!function_exists('tscValidarClaveSO')) {
    function tscValidarClaveSO($val): bool {
        $v = trim((string)$val);
        if ($v === '') return false;
        return (bool) preg_match('/^[A-Z\x{00D1}&]{3,4}\d{6}[A-Z0-9]{3}$/u', tscToUpper($v));
    }
}

if (!function_exists('generateTSCXml')) {
    function generateTSCXml(array $data): array {
        $NS  = 'http://www.uif.shcp.gob.mx/recepcion/tsc';
        $XSI = 'http://www.w3.org/2001/XMLSchema-instance';

        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $UPPER_FIELDS = ['nombre','apellido_paterno','apellido_materno','denominacion_razon','colonia','calle','numero_exterior','numero_interior','estado_provincia','ciudad_poblacion','descripcion_alerta','descripcion_modificacion'];
        $addEl = function(DOMDocument $dom, DOMElement $parent, string $ns, string $name, $value, $sanitize = null) use ($UPPER_FIELDS) {
            if ($value === null) return null;
            $value = trim((string)$value);
            if ($value === '') return null;
            if (in_array($name, ['monto_gasto'])) {
                $value = formatMontoTsc($value);
            } elseif ($name === 'clave_sujeto_obligado') {
                $value = tscSanitizeClaveSO($value);
            } elseif ($name === 'correo_electronico') {
                $value = tscToUpper($value);
            } elseif (in_array($name, ['fecha_nacimiento', 'fecha_constitucion'])) {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = strlen($value) === 8 ? $value : '';
            } elseif ($name === 'referencia_aviso') {
                $value = tscSanitizeRef($value, true);
            } elseif ($name === 'numero_identificador') {
                $value = tscSanitizeRef($value, false);
            } elseif (in_array($name, ['descripcion_alerta','descripcion_modificacion'])) {
                $value = tscSanitizeDesc($value);
            } elseif (in_array($name, ['tipo_operacion', 'tipo_alerta'])) {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = strlen($value) >= 3 ? substr($value, 0, 4) : str_pad($value, 3, '0', STR_PAD_LEFT);
            } elseif ($name === 'actividad_economica') {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = str_pad(substr($value, 0, 7), 7, '0', STR_PAD_LEFT);
            } elseif (in_array($name, $UPPER_FIELDS)) {
                $value = tscToUpper($value);
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
                $addEl($dom, $pmEl, $ns, 'fecha_constitucion', $pm['fecha_constitucion'] ?? null);
                $addEl($dom, $pmEl, $ns, 'rfc', $pm['rfc'] ?? null);
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
                if (isset($fi['apoderado_delegado']) && is_array($fi['apoderado_delegado'])) {
                    $ap = $fi['apoderado_delegado'];
                    $apEl = $mkEl($dom, $fiEl, $ns, 'apoderado_delegado');
                    $addEl($dom, $apEl, $ns, 'nombre', $ap['nombre'] ?? null);
                    $addEl($dom, $apEl, $ns, 'apellido_paterno', $ap['apellido_paterno'] ?? null);
                    $addEl($dom, $apEl, $ns, 'apellido_materno', $ap['apellido_materno'] ?? null);
                    $addEl($dom, $apEl, $ns, 'fecha_nacimiento', $ap['fecha_nacimiento'] ?? null);
                    $addEl($dom, $apEl, $ns, 'rfc', $ap['rfc'] ?? null);
                    $addEl($dom, $apEl, $ns, 'curp', $ap['curp'] ?? null);
                }
            }
        };

        /** tipo_persona_simple (dueno_beneficiario): sin actividad_economica, sin representante en persona_moral/fideicomiso completo */
        $writePersonaSimple = function(DOMDocument $dom, DOMElement $parent, string $ns, array $pData) use ($addEl, $mkEl) {
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
            } elseif (isset($pData['persona_moral']) && is_array($pData['persona_moral'])) {
                $pm = $pData['persona_moral'];
                $pmEl = $mkEl($dom, $parent, $ns, 'persona_moral');
                $addEl($dom, $pmEl, $ns, 'denominacion_razon', $pm['denominacion_razon'] ?? null);
                $addEl($dom, $pmEl, $ns, 'fecha_constitucion', $pm['fecha_constitucion'] ?? null);
                $addEl($dom, $pmEl, $ns, 'rfc', $pm['rfc'] ?? null);
                $addEl($dom, $pmEl, $ns, 'pais_nacionalidad', $pm['pais_nacionalidad'] ?? null);
            } elseif (isset($pData['fideicomiso']) && is_array($pData['fideicomiso'])) {
                $fi = $pData['fideicomiso'];
                $fiEl = $mkEl($dom, $parent, $ns, 'fideicomiso');
                $addEl($dom, $fiEl, $ns, 'denominacion_razon', $fi['denominacion_razon'] ?? null);
                $addEl($dom, $fiEl, $ns, 'rfc', $fi['rfc'] ?? null);
                $addEl($dom, $fiEl, $ns, 'identificador_fideicomiso', $fi['identificador_fideicomiso'] ?? null);
            }
        };

        $writeDomicilio = function(DOMDocument $dom, DOMElement $parent, string $ns, array $dData) use ($addEl, $mkEl) {
            if (isset($dData['nacional']) && is_array($dData['nacional'])) {
                $n = $dData['nacional'];
                $nEl = $mkEl($dom, $parent, $ns, 'nacional');
                $addEl($dom, $nEl, $ns, 'colonia', $n['colonia'] ?? null);
                $addEl($dom, $nEl, $ns, 'calle', $n['calle'] ?? null);
                $addEl($dom, $nEl, $ns, 'numero_exterior', $n['numero_exterior'] ?? null);
                if (trim($n['numero_interior'] ?? '') !== '') {
                    $addEl($dom, $nEl, $ns, 'numero_interior', $n['numero_interior']);
                }
                $addEl($dom, $nEl, $ns, 'codigo_postal', $n['codigo_postal'] ?? null);
            } elseif (isset($dData['extranjero']) && is_array($dData['extranjero'])) {
                $x = $dData['extranjero'];
                $xEl = $mkEl($dom, $parent, $ns, 'extranjero');
                $cpExt = trim($x['codigo_postal'] ?? '');
                if ($cpExt !== '') {
                    $cpExt = substr(preg_replace('/[^A-Z\x{00D1}0-9]/u', '', tscToUpper($cpExt)), 0, 12);
                }
                $addEl($dom, $xEl, $ns, 'pais', $x['pais'] ?? null);
                $addEl($dom, $xEl, $ns, 'estado_provincia', $x['estado_provincia'] ?? null);
                $addEl($dom, $xEl, $ns, 'ciudad_poblacion', $x['ciudad_poblacion'] ?? null);
                $addEl($dom, $xEl, $ns, 'colonia', $x['colonia'] ?? null);
                $addEl($dom, $xEl, $ns, 'calle', $x['calle'] ?? null);
                $addEl($dom, $xEl, $ns, 'numero_exterior', $x['numero_exterior'] ?? null);
                if (trim($x['numero_interior'] ?? '') !== '') {
                    $addEl($dom, $xEl, $ns, 'numero_interior', $x['numero_interior']);
                }
                $addEl($dom, $xEl, $ns, 'codigo_postal', $cpExt !== '' ? $cpExt : ($x['codigo_postal'] ?? null));
            }
        };

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $archivo = $dom->createElementNS($NS, 'archivo');
        $archivo->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $XSI);
        $archivo->setAttributeNS($XSI, 'xsi:schemaLocation', $NS . ' tsc.xsd');
        $dom->appendChild($archivo);

        foreach ($data['informe'] as $inf) {
            if (!is_array($inf)) continue;
            $informeEl = $mkEl($dom, $archivo, $NS, 'informe');

            $addEl($dom, $informeEl, $NS, 'mes_reportado', $inf['mes_reportado'] ?? null);

            $so = $inf['sujeto_obligado'] ?? [];
            if (!is_array($so)) $so = [];
            $soEl = $mkEl($dom, $informeEl, $NS, 'sujeto_obligado');
            if (trim($so['clave_entidad_colegiada'] ?? '') !== '') {
                $addEl($dom, $soEl, $NS, 'clave_entidad_colegiada', $so['clave_entidad_colegiada']);
            }
            $addEl($dom, $soEl, $NS, 'clave_sujeto_obligado', $so['clave_sujeto_obligado'] ?? null);
            $addEl($dom, $soEl, $NS, 'clave_actividad', !empty($so['clave_actividad']) ? $so['clave_actividad'] : 'TSC');
            if (isset($so['exento']) && (string)$so['exento'] === '1') {
                $addEl($dom, $soEl, $NS, 'exento', '1');
            }

            $avisos = $inf['aviso'] ?? [];
            if (!is_array($avisos)) $avisos = [];
            foreach ($avisos as $av) {
                if (!is_array($av)) continue;
                $avisoEl = $mkEl($dom, $informeEl, $NS, 'aviso');

                $addEl($dom, $avisoEl, $NS, 'referencia_aviso', $av['referencia_aviso'] ?? null);

                if (isset($av['modificatorio']) && is_array($av['modificatorio'])) {
                    $m = $av['modificatorio'];
                    $modEl = $mkEl($dom, $avisoEl, $NS, 'modificatorio');
                    $addEl($dom, $modEl, $NS, 'folio_modificacion', $m['folio_modificacion'] ?? null);
                    $addEl($dom, $modEl, $NS, 'descripcion_modificacion', $m['descripcion_modificacion'] ?? null);
                }

                $addEl($dom, $avisoEl, $NS, 'prioridad', $av['prioridad'] ?? null);

                $alerta = $av['alerta'] ?? [];
                if (is_array($alerta)) {
                    $alertaEl = $mkEl($dom, $avisoEl, $NS, 'alerta');
                    $addEl($dom, $alertaEl, $NS, 'tipo_alerta', $alerta['tipo_alerta'] ?? null);
                    $addEl($dom, $alertaEl, $NS, 'descripcion_alerta', $alerta['descripcion_alerta'] ?? null);
                }

                /* persona_aviso (maxOccurs="unbounded" - puede ser array o objeto único) */
                $personaAvisoRaw = $av['persona_aviso'] ?? [];
                $personasList = is_array($personaAvisoRaw) && isset($personaAvisoRaw['tipo_persona'])
                    ? [$personaAvisoRaw]
                    : (is_array($personaAvisoRaw) ? $personaAvisoRaw : []);
                $duenoBeneficiarioData = null;
                foreach ($personasList as $personaAviso) {
                    if (!is_array($personaAviso)) continue;
                    if (isset($personaAviso['dueno_beneficiario']) && $duenoBeneficiarioData === null) {
                        $duenoBeneficiarioData = $personaAviso['dueno_beneficiario'];
                    }
                    $paEl = $mkEl($dom, $avisoEl, $NS, 'persona_aviso');
                    $tipoPersona = $personaAviso['tipo_persona'] ?? [];
                    if (is_array($tipoPersona) && !empty($tipoPersona)) {
                        $tpEl = $mkEl($dom, $paEl, $NS, 'tipo_persona');
                        $writePersona($dom, $tpEl, $NS, $tipoPersona);
                    }
                    $tipoDomicilio = $personaAviso['tipo_domicilio'] ?? [];
                    if (is_array($tipoDomicilio) && !empty($tipoDomicilio)) {
                        $tdEl = $mkEl($dom, $paEl, $NS, 'tipo_domicilio');
                        $writeDomicilio($dom, $tdEl, $NS, $tipoDomicilio);
                    }
                    $telefono = $personaAviso['telefono'] ?? [];
                    $numTel = trim($telefono['numero_telefono'] ?? '');
                    if (is_array($telefono) && $numTel !== '') {
                        $telEl = $mkEl($dom, $paEl, $NS, 'telefono');
                        $addEl($dom, $telEl, $NS, 'clave_pais', $telefono['clave_pais'] ?? null);
                        $addEl($dom, $telEl, $NS, 'numero_telefono', $telefono['numero_telefono'] ?? null);
                        $addEl($dom, $telEl, $NS, 'correo_electronico', $telefono['correo_electronico'] ?? null);
                    }
                }
                /* dueno_beneficiario (opcional, a nivel aviso) — XSD: solo tipo_persona (tipo_persona_simple_type) */
                $duenoBenef = $duenoBeneficiarioData ?? $av['dueno_beneficiario'] ?? null;
                if (is_array($duenoBenef)) {
                    $dbList = isset($duenoBenef['tipo_persona']) ? [$duenoBenef] : $duenoBenef;
                    foreach ($dbList as $db) {
                        if (!is_array($db)) continue;
                        $dbTp = $db['tipo_persona'] ?? [];
                        $dbHasData = false;
                        if (isset($dbTp['persona_fisica']) && is_array($dbTp['persona_fisica']) && trim($dbTp['persona_fisica']['nombre'] ?? '') !== '') $dbHasData = true;
                        elseif (isset($dbTp['persona_moral']) && is_array($dbTp['persona_moral']) && trim($dbTp['persona_moral']['denominacion_razon'] ?? '') !== '') $dbHasData = true;
                        elseif (isset($dbTp['fideicomiso']) && is_array($dbTp['fideicomiso']) && trim($dbTp['fideicomiso']['denominacion_razon'] ?? '') !== '') $dbHasData = true;
                        if ($dbHasData) {
                            $dbEl = $mkEl($dom, $avisoEl, $NS, 'dueno_beneficiario');
                            $dbTpEl = $mkEl($dom, $dbEl, $NS, 'tipo_persona');
                            $writePersonaSimple($dom, $dbTpEl, $NS, $dbTp);
                        }
                    }
                }

                /* detalle_operaciones TSC */
                $detalleEl = $mkEl($dom, $avisoEl, $NS, 'detalle_operaciones');
                $detallesRaw = $av['detalle_operaciones'] ?? [];
                $detalles = (is_array($detallesRaw) && isset($detallesRaw['datos_operacion']))
                    ? [$detallesRaw]
                    : (is_array($detallesRaw) ? $detallesRaw : []);

                foreach ($detalles as $det) {
                    if (!is_array($det)) continue;
                    $ops = $det['datos_operacion'] ?? [];
                    if (!is_array($ops)) $ops = [];

                    foreach ($ops as $op) {
                        if (!is_array($op)) continue;
                        $opEl = $mkEl($dom, $detalleEl, $NS, 'datos_operacion');
                        $addEl($dom, $opEl, $NS, 'fecha_periodo', $op['fecha_periodo'] ?? null);
                        $addEl($dom, $opEl, $NS, 'tipo_operacion', $op['tipo_operacion'] ?? null);
                        $addEl($dom, $opEl, $NS, 'tipo_tarjeta', $op['tipo_tarjeta'] ?? null);
                        $addEl($dom, $opEl, $NS, 'numero_identificador', $op['numero_identificador'] ?? null);
                        $addEl($dom, $opEl, $NS, 'monto_gasto', $op['monto_gasto'] ?? null);
                    }
                }
            }
        }

        $xml = $dom->saveXML();
        return ['xml' => $xml, 'errors' => []];
    }
}
