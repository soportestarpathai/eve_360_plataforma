<?php
/**
 * Helper para generar XML SPR (Servicios Profesionales) - Fracción XI
 * XSD: http://www.uif.shcp.gob.mx/recepcion/spr
 * Estructura: archivo > informe > sujeto_obligado > aviso > persona_aviso > dueno_beneficiario > detalle_operaciones
 */

if (!function_exists('sprFormatMonto')) {
    function sprFormatMonto($val): string {
        if ($val === null || $val === '') return '0.00';
        $n = floatval($val);
        return number_format($n, 2, '.', '');
    }
}

if (!function_exists('sprToUpper')) {
    function sprToUpper($val): string {
        $v = trim((string)$val);
        if ($v === '') return '';
        return mb_strtoupper($v, 'UTF-8');
    }
}

if (!function_exists('sprSanitizeRef')) {
    function sprSanitizeRef($val): string {
        $v = sprToUpper($val);
        $v = preg_replace('/[^A-ZÑ0-9]/u', '', $v);
        return substr($v, 0, 14);
    }
}

if (!function_exists('sprSanitizeDesc')) {
    function sprSanitizeDesc($val): string {
        $v = sprToUpper(trim((string)$val));
        $v = preg_replace('/[^A-Z\x{00D1}0-9 \-\.,\':\/$]/u', '', $v);
        return substr($v, 0, 3000);
    }
}

/** Sanitiza clave_sujeto_obligado a formato RFC (clave_so_type): 12-13 chars, solo A-ZÑ0-9 */
if (!function_exists('sprSanitizeClaveSO')) {
    function sprSanitizeClaveSO($val): string {
        $v = sprToUpper(trim((string)$val));
        $v = preg_replace('/[^A-Z\x{00D1}0-9]/u', '', $v);
        return substr($v, 0, 13);
    }
}

/** Valida clave_sujeto_obligado con regex RFC: 3-4 letras + 6 dígitos + 3 caracteres */
if (!function_exists('sprValidarClaveSO')) {
    function sprValidarClaveSO($val): bool {
        $v = trim((string)$val);
        if ($v === '') return false;
        return (bool) preg_match('/^[A-Z\x{00D1}&]{3,4}\d{6}[A-Z0-9]{3}$/u', sprToUpper($v));
    }
}

/** Convierte tipo_ocupacion (XI.X NN) a código XML 1-2 dígitos (XSD digito_1-2_type) */
if (!function_exists('sprMapOcupacionToXml')) {
    function sprMapOcupacionToXml($val): string {
        static $map = [
            'XI.A 01' => '1', 'XI.A 02' => '2', 'XI.A 03' => '3', 'XI.A 04' => '4',
            'XI.B 01' => '5', 'XI.C 01' => '6', 'XI.D 01' => '7', 'XI.E 01' => '8',
            'XI.F 01' => '9', 'XI.G 01' => '10', 'XI.H 01' => '11', 'XI.I 01' => '12',
            'XI.J 01' => '13', 'XI.K 01' => '14', 'XI.L 01' => '15', 'XI.M 01' => '16',
            'XI.N 01' => '17', 'XI.O 01' => '18', 'XI.P 01' => '19', 'XI.Q 01' => '20',
            'XI.R 01' => '21', 'XI.S 01' => '22', 'XI.T 01' => '23', 'XI.U 01' => '24',
            'XI.V 01' => '25', 'XI.W 01' => '26', 'XI.X 01' => '27', 'XI.Y 01' => '28',
            'XI.Z 01' => '29', 'XI.Z 02' => '30', 'XI.Z 03' => '31', 'XI.Z 04' => '32',
            '99' => '99',
        ];
        $k = trim((string)$val);
        if (isset($map[$k])) return $map[$k];
        return preg_replace('/[^0-9]/', '', $k) ?: (strlen($k) <= 2 ? $k : '1');
    }
}

if (!function_exists('generateSPRXml')) {
    function generateSPRXml(array $data): array {
        $NS  = 'http://www.uif.shcp.gob.mx/recepcion/spr';
        $XSI = 'http://www.w3.org/2001/XMLSchema-instance';

        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $addEl = function(DOMDocument $dom, DOMElement $parent, string $ns, string $name, $value) {
            if ($value === null) return null;
            $value = trim((string)$value);
            if ($value === '') return null;
            if (in_array($name, ['monto_operacion', 'valor_pactado', 'valor_referencia', 'dimension_terreno', 'dimension_construido'])) {
                $value = sprFormatMonto($value);
            } elseif ($name === 'referencia_aviso') {
                $value = sprSanitizeRef($value);
            } elseif ($name === 'clave_sujeto_obligado') {
                $value = sprSanitizeClaveSO($value);
            } elseif ($name === 'tipo_ocupacion') {
                $value = sprMapOcupacionToXml($value);
            } elseif ($name === 'correo_electronico') {
                $value = sprToUpper($value);
            } elseif (in_array($name, ['descripcion_alerta', 'descripcion_modificacion', 'tipo_administracion', 'descripcion_otro_area_servicio', 'descripcion_otro_activo_administrado', 'descripcion_activo_administrado'])) {
                $value = sprSanitizeDesc($value);
            } elseif ($name === 'tipo_operacion' && strlen($value) > 2) {
                $value = sprSanitizeDesc($value);
            } elseif (in_array($name, ['nombre','apellido_paterno','apellido_materno','denominacion_razon','colonia','calle','numero_exterior','numero_interior','estado_provincia','ciudad_poblacion'])) {
                $value = sprToUpper($value);
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
                if (isset($pf['representante_apoderado']) && is_array($pf['representante_apoderado'])) {
                    $rep = $pf['representante_apoderado'];
                    $repEl = $mkEl($dom, $pfEl, $ns, 'representante_apoderado');
                    $addEl($dom, $repEl, $ns, 'nombre', $rep['nombre'] ?? null);
                    $addEl($dom, $repEl, $ns, 'apellido_paterno', $rep['apellido_paterno'] ?? null);
                    $addEl($dom, $repEl, $ns, 'apellido_materno', $rep['apellido_materno'] ?? null);
                    $addEl($dom, $repEl, $ns, 'fecha_nacimiento', $rep['fecha_nacimiento'] ?? null);
                    $addEl($dom, $repEl, $ns, 'rfc', $rep['rfc'] ?? null);
                    $addEl($dom, $repEl, $ns, 'curp', $rep['curp'] ?? null);
                }
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
                $apoderados = $fi['apoderado_delegado'] ?? [];
                if (!is_array($apoderados)) $apoderados = [$apoderados];
                foreach ($apoderados as $ap) {
                    if (!is_array($ap)) continue;
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

        /** Persona simple (dueño beneficiario SPR: sin domicilio, sin teléfono) */
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
                if (!empty(trim($n['numero_interior'] ?? ''))) $addEl($dom, $nEl, $ns, 'numero_interior', $n['numero_interior']);
                $addEl($dom, $nEl, $ns, 'codigo_postal', $n['codigo_postal'] ?? null);
            } elseif (isset($dData['extranjero']) && is_array($dData['extranjero'])) {
                $x = $dData['extranjero'];
                $xEl = $mkEl($dom, $parent, $ns, 'extranjero');
                $cpExt = trim($x['codigo_postal'] ?? '');
                if ($cpExt !== '') $cpExt = substr(preg_replace('/[^A-ZÑ0-9]/u', '', sprToUpper($cpExt)), 0, 12);
                $addEl($dom, $xEl, $ns, 'pais', $x['pais'] ?? null);
                $addEl($dom, $xEl, $ns, 'estado_provincia', $x['estado_provincia'] ?? null);
                $addEl($dom, $xEl, $ns, 'ciudad_poblacion', $x['ciudad_poblacion'] ?? null);
                $addEl($dom, $xEl, $ns, 'colonia', $x['colonia'] ?? null);
                $addEl($dom, $xEl, $ns, 'calle', $x['calle'] ?? null);
                $addEl($dom, $xEl, $ns, 'numero_exterior', $x['numero_exterior'] ?? null);
                if (!empty(trim($x['numero_interior'] ?? ''))) $addEl($dom, $xEl, $ns, 'numero_interior', $x['numero_interior']);
                $addEl($dom, $xEl, $ns, 'codigo_postal', $cpExt !== '' ? $cpExt : ($x['codigo_postal'] ?? null));
            }
        };

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $archivo = $dom->createElementNS($NS, 'archivo');
        $archivo->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $XSI);
        $archivo->setAttributeNS($XSI, 'xsi:schemaLocation', $NS . ' spr.xsd');
        $dom->appendChild($archivo);

        foreach ($data['informe'] as $inf) {
            if (!is_array($inf)) continue;
            $informeEl = $mkEl($dom, $archivo, $NS, 'informe');

            $addEl($dom, $informeEl, $NS, 'mes_reportado', $inf['mes_reportado'] ?? null);

            $so = $inf['sujeto_obligado'] ?? [];
            if (!is_array($so)) $so = [];
            $soEl = $mkEl($dom, $informeEl, $NS, 'sujeto_obligado');
            if (!empty(trim($so['clave_entidad_colegiada'] ?? ''))) {
                $addEl($dom, $soEl, $NS, 'clave_entidad_colegiada', $so['clave_entidad_colegiada']);
            }
            $addEl($dom, $soEl, $NS, 'clave_sujeto_obligado', $so['clave_sujeto_obligado'] ?? null);

            if (isset($so['ocupacion']) && is_array($so['ocupacion']) && !empty(trim($so['ocupacion']['tipo_ocupacion'] ?? ''))) {
                $ocEl = $mkEl($dom, $soEl, $NS, 'ocupacion');
                $addEl($dom, $ocEl, $NS, 'tipo_ocupacion', $so['ocupacion']['tipo_ocupacion']);
                if (!empty(trim($so['ocupacion']['descripcion_otra_ocupacion'] ?? ''))) {
                    $addEl($dom, $ocEl, $NS, 'descripcion_otra_ocupacion', $so['ocupacion']['descripcion_otra_ocupacion']);
                }
            }

            $addEl($dom, $soEl, $NS, 'clave_actividad', !empty($so['clave_actividad']) ? $so['clave_actividad'] : 'SPR');
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

                $personasAviso = $av['persona_aviso'] ?? [];
                if (!is_array($personasAviso)) $personasAviso = [$personasAviso];
                foreach ($personasAviso as $pa) {
                    if (!is_array($pa)) continue;
                    $paEl = $mkEl($dom, $avisoEl, $NS, 'persona_aviso');
                    $tipoPersona = $pa['tipo_persona'] ?? [];
                    if (is_array($tipoPersona)) {
                        $tpEl = $mkEl($dom, $paEl, $NS, 'tipo_persona');
                        $writePersona($dom, $tpEl, $NS, $tipoPersona);
                    }
                    $tipoDomicilio = $pa['tipo_domicilio'] ?? [];
                    if (is_array($tipoDomicilio) && !empty($tipoDomicilio)) {
                        $tdEl = $mkEl($dom, $paEl, $NS, 'tipo_domicilio');
                        $writeDomicilio($dom, $tdEl, $NS, $tipoDomicilio);
                    }
                    $telefono = $pa['telefono'] ?? [];
                    if (is_array($telefono) && (!empty($telefono['numero_telefono'] ?? ''))) {
                        $telEl = $mkEl($dom, $paEl, $NS, 'telefono');
                        $addEl($dom, $telEl, $NS, 'clave_pais', $telefono['clave_pais'] ?? null);
                        $addEl($dom, $telEl, $NS, 'numero_telefono', $telefono['numero_telefono'] ?? null);
                        $addEl($dom, $telEl, $NS, 'correo_electronico', $telefono['correo_electronico'] ?? null);
                    }
                }

                $duenoBenefList = $av['dueno_beneficiario'] ?? [];
                if (!is_array($duenoBenefList)) $duenoBenefList = [];
                foreach ($duenoBenefList as $db) {
                    if (!is_array($db)) continue;
                    $dbTp = $db['tipo_persona'] ?? [];
                    $dbHasData = false;
                    if (isset($dbTp['persona_fisica']) && is_array($dbTp['persona_fisica']) && !empty(trim($dbTp['persona_fisica']['nombre'] ?? ''))) $dbHasData = true;
                    elseif (isset($dbTp['persona_moral']) && is_array($dbTp['persona_moral']) && !empty(trim($dbTp['persona_moral']['denominacion_razon'] ?? ''))) $dbHasData = true;
                    elseif (isset($dbTp['fideicomiso']) && is_array($dbTp['fideicomiso']) && !empty(trim($dbTp['fideicomiso']['denominacion_razon'] ?? ''))) $dbHasData = true;
                    if ($dbHasData) {
                        $dbEl = $mkEl($dom, $avisoEl, $NS, 'dueno_beneficiario');
                        $dbTpEl = $mkEl($dom, $dbEl, $NS, 'tipo_persona');
                        $writePersonaSimple($dom, $dbTpEl, $NS, $dbTp);
                    }
                }

                $detalleOp = $av['detalle_operaciones'] ?? [];
                $detalleEl = $mkEl($dom, $avisoEl, $NS, 'detalle_operaciones');
                $datosOps = $detalleOp['datos_operacion'] ?? [];
                if (!is_array($datosOps)) $datosOps = [];

                foreach ($datosOps as $op) {
                    if (!is_array($op)) continue;
                    $opEl = $mkEl($dom, $detalleEl, $NS, 'datos_operacion');
                    $addEl($dom, $opEl, $NS, 'fecha_operacion', $op['fecha_operacion'] ?? null);

                    $tipoAct = $op['tipo_actividad'] ?? [];
                    if (isset($tipoAct['administracion_personas_morales']) && is_array($tipoAct['administracion_personas_morales'])) {
                        $adm = $tipoAct['administracion_personas_morales'];
                        $admEl = $mkEl($dom, $opEl, $NS, 'tipo_actividad');
                        $admInnerEl = $mkEl($dom, $admEl, $NS, 'administracion_personas_morales');
                        $addEl($dom, $admInnerEl, $NS, 'tipo_administracion', $adm['tipo_administracion'] ?? null);
                        $addEl($dom, $admInnerEl, $NS, 'tipo_operacion', $adm['tipo_operacion'] ?? null);
                        $addEl($dom, $admInnerEl, $NS, 'persona_moral_aviso', $adm['persona_moral_aviso'] ?? null);
                        if (isset($adm['tipo_persona']) && is_array($adm['tipo_persona']) && !empty($adm['tipo_persona'])) {
                            $admTpEl = $mkEl($dom, $admInnerEl, $NS, 'tipo_persona');
                            $writePersonaSimple($dom, $admTpEl, $NS, $adm['tipo_persona']);
                        }
                    } elseif (isset($tipoAct['administracion_recursos']) && is_array($tipoAct['administracion_recursos'])) {
                        $ar = $tipoAct['administracion_recursos'];
                        $arEl = $mkEl($dom, $opEl, $NS, 'tipo_actividad');
                        $arInnerEl = $mkEl($dom, $arEl, $NS, 'administracion_recursos');
                        foreach ($ar['tipo_activo'] ?? [] as $ta) {
                            if (!is_array($ta)) continue;
                            if (isset($ta['activo_inmobiliario']) && is_array($ta['activo_inmobiliario'])) {
                                $ai = $ta['activo_inmobiliario'];
                                $taEl = $mkEl($dom, $arInnerEl, $NS, 'tipo_activo');
                                $aiEl = $mkEl($dom, $taEl, $NS, 'activo_inmobiliario');
                                $addEl($dom, $aiEl, $NS, 'tipo_inmueble', $ai['tipo_inmueble'] ?? null);
                                $addEl($dom, $aiEl, $NS, 'valor_referencia', $ai['valor_referencia'] ?? null);
                                $addEl($dom, $aiEl, $NS, 'colonia', $ai['colonia'] ?? null);
                                $addEl($dom, $aiEl, $NS, 'calle', $ai['calle'] ?? null);
                                $addEl($dom, $aiEl, $NS, 'numero_exterior', $ai['numero_exterior'] ?? null);
                                if (!empty(trim($ai['numero_interior'] ?? ''))) $addEl($dom, $aiEl, $NS, 'numero_interior', $ai['numero_interior']);
                                $addEl($dom, $aiEl, $NS, 'codigo_postal', $ai['codigo_postal'] ?? null);
                                $addEl($dom, $aiEl, $NS, 'folio_real', $ai['folio_real'] ?? null);
                            }
                            if (isset($ta['activo_banco']) && is_array($ta['activo_banco'])) {
                                $ab = $ta['activo_banco'];
                                $taEl = $mkEl($dom, $arInnerEl, $NS, 'tipo_activo');
                                $abEl = $mkEl($dom, $taEl, $NS, 'activo_banco');
                                $addEl($dom, $abEl, $NS, 'estatus_manejo', $ab['estatus_manejo'] ?? null);
                                $addEl($dom, $abEl, $NS, 'clave_tipo_institucion', $ab['clave_tipo_institucion'] ?? null);
                                $addEl($dom, $abEl, $NS, 'nombre_institucion', $ab['nombre_institucion'] ?? null);
                                $addEl($dom, $abEl, $NS, 'numero_cuenta', $ab['numero_cuenta'] ?? null);
                            }
                            if (isset($ta['activo_outsourcing']) && is_array($ta['activo_outsourcing'])) {
                                $ao = $ta['activo_outsourcing'];
                                $taEl = $mkEl($dom, $arInnerEl, $NS, 'tipo_activo');
                                $aoEl = $mkEl($dom, $taEl, $NS, 'activo_outsourcing');
                                $as = $ao['area_servicio'] ?? [];
                                if (is_array($as)) {
                                    $asEl = $mkEl($dom, $aoEl, $NS, 'area_servicio');
                                    $addEl($dom, $asEl, $NS, 'tipo_area_servicio', $as['tipo_area_servicio'] ?? null);
                                    if (!empty(trim($as['descripcion_otro_area_servicio'] ?? ''))) $addEl($dom, $asEl, $NS, 'descripcion_otro_area_servicio', $as['descripcion_otro_area_servicio']);
                                }
                                $aa = $ao['activo_administrado'] ?? [];
                                if (is_array($aa)) {
                                    $aaEl = $mkEl($dom, $aoEl, $NS, 'activo_administrado');
                                    $addEl($dom, $aaEl, $NS, 'tipo_activo_administrado', $aa['tipo_activo_administrado'] ?? null);
                                    if (!empty(trim($aa['descripcion_otro_activo_administrado'] ?? ''))) $addEl($dom, $aaEl, $NS, 'descripcion_otro_activo_administrado', $aa['descripcion_otro_activo_administrado']);
                                }
                                $addEl($dom, $aoEl, $NS, 'numero_empleados', $ao['numero_empleados'] ?? null);
                            }
                            if (isset($ta['activo_otros']) && is_array($ta['activo_otros'])) {
                                $aot = $ta['activo_otros'];
                                $taEl = $mkEl($dom, $arInnerEl, $NS, 'tipo_activo');
                                $aotEl = $mkEl($dom, $taEl, $NS, 'activo_otros');
                                $addEl($dom, $aotEl, $NS, 'descripcion_activo_administrado', $aot['descripcion_activo_administrado'] ?? null);
                            }
                        }
                        $addEl($dom, $arInnerEl, $NS, 'numero_operaciones', $ar['numero_operaciones'] ?? null);
                    } elseif (isset($tipoAct['cesion_derechos_inmuebles']) && is_array($tipoAct['cesion_derechos_inmuebles'])) {
                        $cdi = $tipoAct['cesion_derechos_inmuebles'];
                        $cdiEl = $mkEl($dom, $opEl, $NS, 'tipo_actividad');
                        $cdiInnerEl = $mkEl($dom, $cdiEl, $NS, 'cesion_derechos_inmuebles');
                        $addEl($dom, $cdiInnerEl, $NS, 'figura_cliente', $cdi['figura_cliente'] ?? null);
                        $addEl($dom, $cdiInnerEl, $NS, 'tipo_cesion', $cdi['tipo_cesion'] ?? null);
                        foreach ($cdi['datos_contraparte'] ?? [] as $dc) {
                            if (!is_array($dc)) continue;
                            $dcEl = $mkEl($dom, $cdiInnerEl, $NS, 'datos_contraparte');
                            $dcTp = $dc['tipo_persona'] ?? [];
                            if (is_array($dcTp) && !empty($dcTp)) {
                                $dcTpEl = $mkEl($dom, $dcEl, $NS, 'tipo_persona');
                                $writePersonaSimple($dom, $dcTpEl, $NS, $dcTp);
                            }
                        }
                        foreach ($cdi['caracteristicas_inmueble'] ?? [] as $car) {
                            if (!is_array($car)) continue;
                            $carEl = $mkEl($dom, $cdiInnerEl, $NS, 'caracteristicas_inmueble');
                            $addEl($dom, $carEl, $NS, 'tipo_inmueble', $car['tipo_inmueble'] ?? null);
                            $addEl($dom, $carEl, $NS, 'valor_referencia', $car['valor_referencia'] ?? null);
                            $addEl($dom, $carEl, $NS, 'colonia', $car['colonia'] ?? null);
                            $addEl($dom, $carEl, $NS, 'calle', $car['calle'] ?? null);
                            $addEl($dom, $carEl, $NS, 'numero_exterior', $car['numero_exterior'] ?? null);
                            if (!empty(trim($car['numero_interior'] ?? ''))) $addEl($dom, $carEl, $NS, 'numero_interior', $car['numero_interior']);
                            $addEl($dom, $carEl, $NS, 'codigo_postal', $car['codigo_postal'] ?? null);
                            $addEl($dom, $carEl, $NS, 'dimension_terreno', $car['dimension_terreno'] ?? null);
                            $addEl($dom, $carEl, $NS, 'dimension_construido', $car['dimension_construido'] ?? null);
                            $addEl($dom, $carEl, $NS, 'folio_real', $car['folio_real'] ?? null);
                        }
                    } elseif (isset($tipoAct['constitucion_sociedades_mercantiles']) && is_array($tipoAct['constitucion_sociedades_mercantiles'])) {
                        $csm = $tipoAct['constitucion_sociedades_mercantiles'];
                        $csmEl = $mkEl($dom, $opEl, $NS, 'tipo_actividad');
                        $csmInnerEl = $mkEl($dom, $csmEl, $NS, 'constitucion_sociedades_mercantiles');
                        $addEl($dom, $csmInnerEl, $NS, 'tipo_persona_moral', $csm['tipo_persona_moral'] ?? null);
                        $addEl($dom, $csmInnerEl, $NS, 'denominacion_razon', $csm['denominacion_razon'] ?? null);
                        $addEl($dom, $csmInnerEl, $NS, 'giro_mercantil', $csm['giro_mercantil'] ?? null);
                        if (!empty(trim($csm['folio_mercantil'] ?? ''))) $addEl($dom, $csmInnerEl, $NS, 'folio_mercantil', $csm['folio_mercantil']);
                        $addEl($dom, $csmInnerEl, $NS, 'numero_total_acciones', $csm['numero_total_acciones'] ?? null);
                        $addEl($dom, $csmInnerEl, $NS, 'entidad_federativa', $csm['entidad_federativa'] ?? null);
                        $addEl($dom, $csmInnerEl, $NS, 'consejo_vigilancia', $csm['consejo_vigilancia'] ?? null);
                        $addEl($dom, $csmInnerEl, $NS, 'motivo_constitucion', $csm['motivo_constitucion'] ?? null);
                        $addEl($dom, $csmInnerEl, $NS, 'instrumento_publico', $csm['instrumento_publico'] ?? null);
                        foreach ($csm['datos_accionista'] ?? [] as $acc) {
                            if (!is_array($acc)) continue;
                            $accEl = $mkEl($dom, $csmInnerEl, $NS, 'datos_accionista');
                            $addEl($dom, $accEl, $NS, 'cargo_accionista', $acc['cargo_accionista'] ?? null);
                            if (isset($acc['tipo_persona']) && is_array($acc['tipo_persona']) && !empty($acc['tipo_persona'])) {
                                $accTpEl = $mkEl($dom, $accEl, $NS, 'tipo_persona');
                                $writePersonaSimple($dom, $accTpEl, $NS, $acc['tipo_persona']);
                            }
                            $addEl($dom, $accEl, $NS, 'numero_acciones', $acc['numero_acciones'] ?? null);
                        }
                        $cap = $csm['capital_social'] ?? [];
                        $capEl = $mkEl($dom, $csmInnerEl, $NS, 'capital_social');
                        $addEl($dom, $capEl, $NS, 'capital_fijo', $cap['capital_fijo'] ?? '0');
                        $addEl($dom, $capEl, $NS, 'capital_variable', $cap['capital_variable'] ?? '0');
                    } elseif (isset($tipoAct['compra_venta_inmuebles']) && is_array($tipoAct['compra_venta_inmuebles'])) {
                        $cvi = $tipoAct['compra_venta_inmuebles'];
                        $cviEl = $mkEl($dom, $opEl, $NS, 'tipo_actividad');
                        $cviInnerEl = $mkEl($dom, $cviEl, $NS, 'compra_venta_inmuebles');
                        $addEl($dom, $cviInnerEl, $NS, 'tipo_operacion', $cvi['tipo_operacion'] ?? null);
                        $addEl($dom, $cviInnerEl, $NS, 'valor_pactado', $cvi['valor_pactado'] ?? null);
                        foreach ($cvi['datos_contraparte'] ?? [] as $dc) {
                            if (!is_array($dc)) continue;
                            $dcEl = $mkEl($dom, $cviInnerEl, $NS, 'datos_contraparte');
                            $dcTp = $dc['tipo_persona'] ?? [];
                            if (is_array($dcTp) && !empty($dcTp)) {
                                $dcTpEl = $mkEl($dom, $dcEl, $NS, 'tipo_persona');
                                $writePersonaSimple($dom, $dcTpEl, $NS, $dcTp);
                            }
                        }
                        foreach ($cvi['caracteristicas_inmueble'] ?? [] as $car) {
                            if (!is_array($car)) continue;
                            $carEl = $mkEl($dom, $cviInnerEl, $NS, 'caracteristicas_inmueble');
                            $addEl($dom, $carEl, $NS, 'tipo_inmueble', $car['tipo_inmueble'] ?? null);
                            $addEl($dom, $carEl, $NS, 'colonia', $car['colonia'] ?? null);
                            $addEl($dom, $carEl, $NS, 'calle', $car['calle'] ?? null);
                            $addEl($dom, $carEl, $NS, 'numero_exterior', $car['numero_exterior'] ?? null);
                            if (!empty(trim($car['numero_interior'] ?? ''))) $addEl($dom, $carEl, $NS, 'numero_interior', $car['numero_interior']);
                            $addEl($dom, $carEl, $NS, 'codigo_postal', $car['codigo_postal'] ?? null);
                            $addEl($dom, $carEl, $NS, 'dimension_terreno', $car['dimension_terreno'] ?? null);
                            $addEl($dom, $carEl, $NS, 'dimension_construido', $car['dimension_construido'] ?? null);
                            $addEl($dom, $carEl, $NS, 'folio_real', $car['folio_real'] ?? null);
                            $contr = $car['contrato_instrumento_publico'] ?? [];
                            if (isset($contr['datos_instrumento_publico']) && is_array($contr['datos_instrumento_publico'])) {
                                $dip = $contr['datos_instrumento_publico'];
                                $contrEl = $mkEl($dom, $carEl, $NS, 'contrato_instrumento_publico');
                                $dipEl = $mkEl($dom, $contrEl, $NS, 'datos_instrumento_publico');
                                $addEl($dom, $dipEl, $NS, 'numero_instrumento_publico', $dip['numero_instrumento_publico'] ?? null);
                                $addEl($dom, $dipEl, $NS, 'fecha_instrumento_publico', $dip['fecha_instrumento_publico'] ?? null);
                                $addEl($dom, $dipEl, $NS, 'notario_instrumento_publico', $dip['notario_instrumento_publico'] ?? null);
                                $addEl($dom, $dipEl, $NS, 'entidad_instrumento_publico', $dip['entidad_instrumento_publico'] ?? null);
                                $addEl($dom, $dipEl, $NS, 'valor_referencia', $dip['valor_referencia'] ?? null);
                            } elseif (isset($contr['contrato']) && is_array($contr['contrato'])) {
                                $ct = $contr['contrato'];
                                $contrEl = $mkEl($dom, $carEl, $NS, 'contrato_instrumento_publico');
                                $ctEl = $mkEl($dom, $contrEl, $NS, 'contrato');
                                $addEl($dom, $ctEl, $NS, 'fecha_contrato', $ct['fecha_contrato'] ?? null);
                                $addEl($dom, $ctEl, $NS, 'valor_referencia', $ct['valor_referencia'] ?? null);
                            }
                        }
                    }

                    $datosFin = $op['datos_operacion_financiera'] ?? [];
                    if (!is_array($datosFin)) $datosFin = [$datosFin];
                    foreach ($datosFin as $df) {
                        if (!is_array($df)) continue;
                        $dfEl = $mkEl($dom, $opEl, $NS, 'datos_operacion_financiera');
                        if (!empty(trim($df['fecha_pago'] ?? ''))) $addEl($dom, $dfEl, $NS, 'fecha_pago', $df['fecha_pago']);
                        $addEl($dom, $dfEl, $NS, 'instrumento_monetario', $df['instrumento_monetario'] ?? null);
                        if (isset($df['activo_virtual']) && is_array($df['activo_virtual']) && !empty(trim($df['activo_virtual']['tipo_activo_virtual'] ?? ''))) {
                            $av = $df['activo_virtual'];
                            $avEl = $mkEl($dom, $dfEl, $NS, 'activo_virtual');
                            $addEl($dom, $avEl, $NS, 'tipo_activo_virtual', $av['tipo_activo_virtual'] ?? null);
                            if (!empty(trim($av['descripcion_activo_virtual'] ?? ''))) {
                                $addEl($dom, $avEl, $NS, 'descripcion_activo_virtual', $av['descripcion_activo_virtual']);
                            }
                            $addEl($dom, $avEl, $NS, 'cantidad_activo_virtual', $av['cantidad_activo_virtual'] ?? null);
                        }
                        if (!empty(trim($df['moneda'] ?? ''))) $addEl($dom, $dfEl, $NS, 'moneda', $df['moneda']);
                        $addEl($dom, $dfEl, $NS, 'monto_operacion', $df['monto_operacion'] ?? null);
                    }
                }
            }
        }

        $xml = $dom->saveXML();
        return ['xml' => $xml, 'errors' => []];
    }
}
