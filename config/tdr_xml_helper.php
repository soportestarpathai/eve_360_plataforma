<?php
/**
 * Helper para generar XML TDR (Tarjetas de Devolución y Recompensas) - Fracción II
 * Según instructivo F-II-3 Tarj Dev y Rwd.
 * Estructura: archivo > informe > sujeto_obligado > aviso > persona_aviso > detalle_operaciones
 */

if (!function_exists('formatMontoTdr')) {
    function formatMontoTdr($val): string {
        if ($val === null || $val === '') return '0.00';
        $n = floatval($val);
        return number_format($n, 2, '.', '');
    }
}

if (!function_exists('tdrToUpper')) {
    function tdrToUpper($val): string {
        $v = trim((string)$val);
        if ($v === '') return '';
        $v = mb_strtoupper($v, 'UTF-8');
        // Normaliza acentos y variantes para cumplir patrones XSD (ASCII + Ñ)
        $map = [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C',
        ];
        return strtr($v, $map);
    }
}

if (!function_exists('tdrSanitizeRef')) {
    function tdrSanitizeRef($val, $allowEnie = true): string {
        $v = tdrToUpper($val);
        $v = preg_replace($allowEnie ? '/[^A-Z\x{00D1}0-9]/u' : '/[^A-Z0-9]/u', '', $v);
        return substr($v, 0, $allowEnie ? 14 : 18);
    }
}

if (!function_exists('tdrSanitizeDesc')) {
    function tdrSanitizeDesc($val): string {
        $v = tdrToUpper(trim((string)$val));
        $v = preg_replace('/[^A-Z\x{00D1}0-9 \-\.,\':\/$]/u', '', $v);
        return substr($v, 0, 3000);
    }
}

if (!function_exists('tdrSanitizeClaveSO')) {
    function tdrSanitizeClaveSO($val): string {
        $v = tdrToUpper(trim((string)$val));
        $v = preg_replace('/[^A-Z\x{00D1}0-9]/u', '', $v);
        return substr($v, 0, 13);
    }
}

if (!function_exists('tdrValidarClaveSO')) {
    function tdrValidarClaveSO($val): bool {
        $v = trim((string)$val);
        if ($v === '') return false;
        return (bool) preg_match('/^[A-Z\x{00D1}&]{3,4}\d{6}[A-Z0-9]{3}$/u', tdrToUpper($v));
    }
}

if (!function_exists('generateTDRXml')) {
    function generateTDRXml(array $data): array {
        $NS  = 'http://www.uif.shcp.gob.mx/recepcion/tdr';
        $XSI = 'http://www.w3.org/2001/XMLSchema-instance';

        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $UPPER_FIELDS = [
            'nombre', 'apellido_paterno', 'apellido_materno', 'denominacion_razon',
            'colonia', 'calle', 'numero_exterior', 'numero_interior',
            'estado_provincia', 'ciudad_poblacion',
            'descripcion_alerta', 'descripcion_modificacion'
        ];

        $addEl = function(DOMDocument $dom, DOMElement $parent, string $ns, string $name, $value) use ($UPPER_FIELDS) {
            if ($value === null) return null;
            $value = trim((string)$value);
            if ($value === '') return null;

            if ($name === 'mes_reportado') {
                $value = substr(preg_replace('/[^0-9]/', '', $value), 0, 6);
            } elseif ($name === 'clave_actividad') {
                $value = 'TDR';
            } elseif ($name === 'clave_entidad_colegiada') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}&0-9]/u', '', tdrToUpper($value)), 0, 12);
            } elseif ($name === 'prioridad') {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = in_array($value, ['1', '2'], true) ? $value : '1';
            } elseif ($name === 'exento') {
                $value = ((string)$value === '1') ? '1' : '';
            } elseif ($name === 'folio_modificacion') {
                $tmp = preg_replace('/[^0-9\-]/', '', $value);
                if (preg_match('/^(\d{4})\-(\d{1,9})$/', $tmp, $m)) {
                    $value = $m[1] . '-' . ltrim($m[2], '0');
                    if (substr($value, -1) === '-') {
                        $value .= '1';
                    }
                } else {
                    $value = '';
                }
            } elseif (in_array($name, ['monto_operacion'], true)) {
                $value = formatMontoTdr($value);
            } elseif ($name === 'cantidad') {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = ltrim($value, '0');
                $value = $value === '' ? '0' : substr($value, 0, 8);
            } elseif ($name === 'clave_sujeto_obligado') {
                $value = tdrSanitizeClaveSO($value);
            } elseif ($name === 'rfc') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}&0-9]/u', '', tdrToUpper($value)), 0, 13);
            } elseif ($name === 'curp') {
                $value = substr(preg_replace('/[^A-Z0-9]/u', '', tdrToUpper($value)), 0, 18);
            } elseif (in_array($name, ['pais', 'pais_nacionalidad', 'clave_pais'], true)) {
                $value = substr(preg_replace('/[^A-Z]/', '', tdrToUpper($value)), 0, 2);
            } elseif ($name === 'correo_electronico') {
                $value = substr(preg_replace('/[^A-Z0-9\._\'\-@]/', '', tdrToUpper($value)), 0, 60);
            } elseif ($name === 'numero_telefono') {
                $value = substr(preg_replace('/[^0-9]/', '', $value), 0, 12);
            } elseif (in_array($name, ['fecha_nacimiento', 'fecha_constitucion', 'fecha_operacion'], true)) {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = strlen($value) === 8 ? $value : '';
            } elseif ($name === 'referencia_aviso') {
                $value = tdrSanitizeRef($value, true);
            } elseif (in_array($name, ['descripcion_alerta', 'descripcion_modificacion'], true)) {
                $value = tdrSanitizeDesc($value);
            } elseif ($name === 'identificador_fideicomiso') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \-_\.&,\#@\']/u', '', tdrToUpper($value)), 0, 40);
            } elseif (in_array($name, ['tipo_operacion', 'tipo_alerta'], true)) {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = (strlen($value) >= 3) ? substr($value, 0, 4) : '';
            } elseif (in_array($name, ['moneda'], true)) {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = strlen($value) >= 1 ? substr($value, 0, 3) : '';
            } elseif ($name === 'actividad_economica') {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = str_pad(substr($value, 0, 7), 7, '0', STR_PAD_LEFT);
            } elseif ($name === 'denominacion_razon') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \#\-\.\&,_@\'\(\)]/u', '', tdrToUpper($value)), 0, 254);
            } elseif (in_array($name, ['nombre', 'apellido_paterno', 'apellido_materno'], true)) {
                $value = substr(preg_replace('/[^A-Z\x{00D1} ]/u', '', tdrToUpper($value)), 0, 200);
            } elseif ($name === 'colonia') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \-\.,:\/\(\)]/u', '', tdrToUpper($value)), 0, 50);
            } elseif (in_array($name, ['calle', 'estado_provincia', 'ciudad_poblacion'], true)) {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \-\.,:\/]/u', '', tdrToUpper($value)), 0, 100);
            } elseif ($name === 'numero_exterior') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \-\.,:\/]/u', '', tdrToUpper($value)), 0, 56);
            } elseif ($name === 'numero_interior') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \-\.,:\/]/u', '', tdrToUpper($value)), 0, 40);
            } elseif (in_array($name, $UPPER_FIELDS, true)) {
                $value = tdrToUpper($value);
            }

            if ($value === '') return null;
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
                $cp = substr(preg_replace('/[^0-9]/', '', (string)($n['codigo_postal'] ?? '')), 0, 5);
                $addEl($dom, $nEl, $ns, 'codigo_postal', $cp);
            } elseif (isset($dData['extranjero']) && is_array($dData['extranjero'])) {
                $x = $dData['extranjero'];
                $xEl = $mkEl($dom, $parent, $ns, 'extranjero');
                $cpExt = trim($x['codigo_postal'] ?? '');
                if ($cpExt !== '') {
                    $cpExt = substr(preg_replace('/[^A-Z\x{00D1}0-9]/u', '', tdrToUpper($cpExt)), 0, 12);
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
        $archivo->setAttributeNS($XSI, 'xsi:schemaLocation', $NS . ' tdr.xsd');
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
            $addEl($dom, $soEl, $NS, 'clave_actividad', !empty($so['clave_actividad']) ? $so['clave_actividad'] : 'TDR');
            if (isset($so['exento']) && (string)$so['exento'] === '1') {
                $addEl($dom, $soEl, $NS, 'exento', '1');
            }

            $avisosRaw = $inf['aviso'] ?? [];
            $avisos = (is_array($avisosRaw) && isset($avisosRaw['referencia_aviso']))
                ? [$avisosRaw]
                : (is_array($avisosRaw) ? $avisosRaw : []);
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

                $detalleEl = $mkEl($dom, $avisoEl, $NS, 'detalle_operaciones');
                $detallesRaw = $av['detalle_operaciones'] ?? [];
                $detalles = (is_array($detallesRaw) && isset($detallesRaw['datos_operacion']))
                    ? [$detallesRaw]
                    : (is_array($detallesRaw) ? $detallesRaw : []);

                foreach ($detalles as $det) {
                    if (!is_array($det)) continue;
                    $opsRaw = $det['datos_operacion'] ?? [];
                    $ops = (is_array($opsRaw) && isset($opsRaw['fecha_operacion']))
                        ? [$opsRaw]
                        : (is_array($opsRaw) ? $opsRaw : []);

                    foreach ($ops as $op) {
                        if (!is_array($op)) continue;
                        $opEl = $mkEl($dom, $detalleEl, $NS, 'datos_operacion');
                        $addEl($dom, $opEl, $NS, 'fecha_operacion', $op['fecha_operacion'] ?? null);
                        $addEl($dom, $opEl, $NS, 'codigo_postal', $op['codigo_postal'] ?? null);
                        $addEl($dom, $opEl, $NS, 'tipo_operacion', $op['tipo_operacion'] ?? null);
                        $addEl($dom, $opEl, $NS, 'cantidad', $op['cantidad'] ?? null);

                        $liqRaw = $op['datos_liquidacion'] ?? [];
                        $liqList = (is_array($liqRaw) && isset($liqRaw['monto_operacion']))
                            ? [$liqRaw]
                            : (is_array($liqRaw) ? $liqRaw : []);
                        foreach ($liqList as $liq) {
                            if (!is_array($liq)) continue;
                            $liqEl = $mkEl($dom, $opEl, $NS, 'datos_liquidacion');
                            $addEl($dom, $liqEl, $NS, 'moneda', $liq['moneda'] ?? null);
                            $addEl($dom, $liqEl, $NS, 'monto_operacion', $liq['monto_operacion'] ?? null);
                        }
                    }
                }
            }
        }

        return ['xml' => $dom->saveXML(), 'errors' => []];
    }
}

