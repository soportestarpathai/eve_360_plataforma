<?php
/**
 * Helper para generar XML MPC (Fracción IV - Mutuo, garantía, préstamos o créditos).
 * Estructura basada en XSD oficial UIF.
 */

if (!function_exists('formatMontoMpc')) {
    function formatMontoMpc($val): string
    {
        if ($val === null || $val === '') return '0.00';
        return number_format((float)$val, 2, '.', '');
    }
}

if (!function_exists('mpcToUpper')) {
    function mpcToUpper($val): string
    {
        $v = trim((string)$val);
        if ($v === '') return '';
        $v = mb_strtoupper($v, 'UTF-8');
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

if (!function_exists('mpcSanitizeRef')) {
    function mpcSanitizeRef($val): string
    {
        $v = mpcToUpper($val);
        $v = preg_replace('/[^A-Z\x{00D1}0-9]/u', '', $v);
        return substr($v, 0, 14);
    }
}

if (!function_exists('mpcSanitizeDesc')) {
    function mpcSanitizeDesc($val, int $len = 3000): string
    {
        $v = mpcToUpper(trim((string)$val));
        $v = preg_replace('/[^A-Z\x{00D1}0-9 \-\.,\':\/$]/u', '', $v);
        return substr($v, 0, $len);
    }
}

if (!function_exists('mpcSanitizeClaveSO')) {
    function mpcSanitizeClaveSO($val): string
    {
        $v = mpcToUpper(trim((string)$val));
        $v = preg_replace('/[^A-Z\x{00D1}&0-9]/u', '', $v);
        return substr($v, 0, 13);
    }
}

if (!function_exists('mpcValidarClaveSO')) {
    function mpcValidarClaveSO($val): bool
    {
        $v = trim((string)$val);
        if ($v === '') return false;
        return (bool)preg_match('/^[A-Z\x{00D1}&]{3,4}\d{6}[A-Z0-9]{3}$/u', mpcToUpper($v));
    }
}

if (!function_exists('generateMPCXml')) {
    function generateMPCXml(array $data): array
    {
        $NS  = 'http://www.uif.shcp.gob.mx/recepcion/mpc';
        $XSI = 'http://www.w3.org/2001/XMLSchema-instance';

        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $addEl = function (DOMDocument $dom, DOMElement $parent, string $ns, string $name, $value) {
            if ($value === null) return null;
            $value = trim((string)$value);
            if ($value === '') return null;

            if ($name === 'mes_reportado') {
                $value = substr(preg_replace('/[^0-9]/', '', $value), 0, 6);
            } elseif ($name === 'clave_actividad') {
                $value = 'MPC';
            } elseif ($name === 'clave_entidad_colegiada') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}&0-9]/u', '', mpcToUpper($value)), 0, 12);
            } elseif ($name === 'clave_sujeto_obligado') {
                $value = mpcSanitizeClaveSO($value);
            } elseif ($name === 'exento') {
                $value = ((string)$value === '1') ? '1' : '';
            } elseif ($name === 'referencia_aviso') {
                $value = mpcSanitizeRef($value);
            } elseif ($name === 'folio_modificacion') {
                $tmp = preg_replace('/[^0-9\-]/', '', $value);
                if (preg_match('/^(\d{4})\-(\d{1,9})$/', $tmp, $m)) {
                    $value = $m[1] . '-' . ltrim($m[2], '0');
                    if (substr($value, -1) === '-') $value .= '1';
                } else {
                    $value = '';
                }
            } elseif (in_array($name, ['descripcion_alerta', 'descripcion_modificacion', 'descripcion_garantia'], true)) {
                $value = mpcSanitizeDesc($value, 3000);
            } elseif ($name === 'identificador_fideicomiso') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \-_\.&,\#@\']/u', '', mpcToUpper($value)), 0, 40);
            } elseif ($name === 'denominacion_razon') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \#\-\.\&,_@\'\(\)]/u', '', mpcToUpper($value)), 0, 254);
            } elseif (in_array($name, ['nombre', 'apellido_paterno', 'apellido_materno'], true)) {
                $value = substr(preg_replace('/[^A-Z\x{00D1} ]/u', '', mpcToUpper($value)), 0, 200);
            } elseif ($name === 'correo_electronico') {
                $value = substr(preg_replace('/[^A-Z0-9\._\'\-@]/', '', mpcToUpper($value)), 0, 60);
            } elseif ($name === 'numero_telefono') {
                $value = substr(preg_replace('/[^0-9]/', '', $value), 0, 12);
            } elseif (in_array($name, ['pais', 'pais_nacionalidad', 'clave_pais'], true)) {
                $value = substr(preg_replace('/[^A-Z]/', '', mpcToUpper($value)), 0, 2);
            } elseif (in_array($name, ['actividad_economica', 'giro_mercantil'], true)) {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = str_pad(substr($value, 0, 7), 7, '0', STR_PAD_LEFT);
            } elseif (in_array($name, ['prioridad'], true)) {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = in_array($value, ['1', '2'], true) ? $value : '1';
            } elseif (in_array($name, ['tipo_alerta', 'tipo_operacion'], true)) {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = (strlen($value) >= 3) ? substr($value, 0, 4) : '';
            } elseif (in_array($name, ['tipo_garantia', 'tipo_inmueble', 'instrumento_monetario'], true)) {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = $value === '' ? '' : substr($value, 0, 2);
            } elseif ($name === 'moneda') {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = $value === '' ? '' : substr($value, 0, 3);
            } elseif (in_array($name, ['monto_operacion', 'valor_referencia'], true)) {
                $value = formatMontoMpc($value);
            } elseif (in_array($name, ['fecha_nacimiento', 'fecha_constitucion', 'fecha_operacion', 'fecha_disposicion'], true)) {
                $value = preg_replace('/[^0-9]/', '', $value);
                $value = strlen($value) === 8 ? $value : '';
            } elseif ($name === 'rfc') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}&0-9]/u', '', mpcToUpper($value)), 0, 13);
            } elseif ($name === 'curp') {
                $value = substr(preg_replace('/[^A-Z0-9]/u', '', mpcToUpper($value)), 0, 18);
            } elseif ($name === 'folio_real') {
                $value = substr(preg_replace('/[^A-Z0-9\-_]/', '', mpcToUpper($value)), 0, 200);
            } elseif ($name === 'colonia') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \-\.,:\/\(\)]/u', '', mpcToUpper($value)), 0, 50);
            } elseif (in_array($name, ['calle', 'estado_provincia', 'ciudad_poblacion'], true)) {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \-\.,:\/]/u', '', mpcToUpper($value)), 0, 100);
            } elseif ($name === 'numero_exterior') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \-\.,:\/]/u', '', mpcToUpper($value)), 0, 56);
            } elseif ($name === 'numero_interior') {
                $value = substr(preg_replace('/[^A-Z\x{00D1}0-9 \-\.,:\/]/u', '', mpcToUpper($value)), 0, 40);
            } elseif ($name === 'codigo_postal') {
                $value = substr(preg_replace('/[^0-9A-Z]/', '', mpcToUpper($value)), 0, 12);
            }

            if ($value === '') return null;
            $el = $dom->createElementNS($ns, $name);
            $el->appendChild($dom->createTextNode($value));
            $parent->appendChild($el);
            return $el;
        };

        $mkEl = function (DOMDocument $dom, DOMElement $parent, string $ns, string $name) {
            $el = $dom->createElementNS($ns, $name);
            $parent->appendChild($el);
            return $el;
        };

        $normalizeList = function ($raw, string $marker): array {
            if (!is_array($raw)) return [];
            return isset($raw[$marker]) ? [$raw] : $raw;
        };

        $writePersona = function (DOMDocument $dom, DOMElement $parent, string $ns, array $pData) use ($addEl, $mkEl) {
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
                if (array_key_exists('actividad_economica', $pf)) {
                    $addEl($dom, $pfEl, $ns, 'actividad_economica', $pf['actividad_economica'] ?? null);
                }
                return;
            }

            if (isset($pData['persona_moral']) && is_array($pData['persona_moral'])) {
                $pm = $pData['persona_moral'];
                $pmEl = $mkEl($dom, $parent, $ns, 'persona_moral');
                $addEl($dom, $pmEl, $ns, 'denominacion_razon', $pm['denominacion_razon'] ?? null);
                $addEl($dom, $pmEl, $ns, 'fecha_constitucion', $pm['fecha_constitucion'] ?? null);
                $addEl($dom, $pmEl, $ns, 'rfc', $pm['rfc'] ?? null);
                $addEl($dom, $pmEl, $ns, 'pais_nacionalidad', $pm['pais_nacionalidad'] ?? null);
                if (array_key_exists('giro_mercantil', $pm)) {
                    $addEl($dom, $pmEl, $ns, 'giro_mercantil', $pm['giro_mercantil'] ?? null);
                }
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
                return;
            }

            if (isset($pData['fideicomiso']) && is_array($pData['fideicomiso'])) {
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

        $writePersonaSimple = function (DOMDocument $dom, DOMElement $parent, string $ns, array $pData) use ($addEl, $mkEl) {
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
                return;
            }

            if (isset($pData['persona_moral']) && is_array($pData['persona_moral'])) {
                $pm = $pData['persona_moral'];
                $pmEl = $mkEl($dom, $parent, $ns, 'persona_moral');
                $addEl($dom, $pmEl, $ns, 'denominacion_razon', $pm['denominacion_razon'] ?? null);
                $addEl($dom, $pmEl, $ns, 'fecha_constitucion', $pm['fecha_constitucion'] ?? null);
                $addEl($dom, $pmEl, $ns, 'rfc', $pm['rfc'] ?? null);
                $addEl($dom, $pmEl, $ns, 'pais_nacionalidad', $pm['pais_nacionalidad'] ?? null);
                return;
            }

            if (isset($pData['fideicomiso']) && is_array($pData['fideicomiso'])) {
                $fi = $pData['fideicomiso'];
                $fiEl = $mkEl($dom, $parent, $ns, 'fideicomiso');
                $addEl($dom, $fiEl, $ns, 'denominacion_razon', $fi['denominacion_razon'] ?? null);
                $addEl($dom, $fiEl, $ns, 'rfc', $fi['rfc'] ?? null);
                $addEl($dom, $fiEl, $ns, 'identificador_fideicomiso', $fi['identificador_fideicomiso'] ?? null);
            }
        };

        $writeDomicilio = function (DOMDocument $dom, DOMElement $parent, string $ns, array $dData) use ($addEl, $mkEl) {
            if (isset($dData['nacional']) && is_array($dData['nacional'])) {
                $n = $dData['nacional'];
                $nEl = $mkEl($dom, $parent, $ns, 'nacional');
                $addEl($dom, $nEl, $ns, 'colonia', $n['colonia'] ?? null);
                $addEl($dom, $nEl, $ns, 'calle', $n['calle'] ?? null);
                $addEl($dom, $nEl, $ns, 'numero_exterior', $n['numero_exterior'] ?? null);
                if (trim((string)($n['numero_interior'] ?? '')) !== '') {
                    $addEl($dom, $nEl, $ns, 'numero_interior', $n['numero_interior']);
                }
                $addEl($dom, $nEl, $ns, 'codigo_postal', $n['codigo_postal'] ?? null);
                return;
            }

            if (isset($dData['extranjero']) && is_array($dData['extranjero'])) {
                $x = $dData['extranjero'];
                $xEl = $mkEl($dom, $parent, $ns, 'extranjero');
                $addEl($dom, $xEl, $ns, 'pais', $x['pais'] ?? null);
                $addEl($dom, $xEl, $ns, 'estado_provincia', $x['estado_provincia'] ?? null);
                $addEl($dom, $xEl, $ns, 'ciudad_poblacion', $x['ciudad_poblacion'] ?? null);
                $addEl($dom, $xEl, $ns, 'colonia', $x['colonia'] ?? null);
                $addEl($dom, $xEl, $ns, 'calle', $x['calle'] ?? null);
                $addEl($dom, $xEl, $ns, 'numero_exterior', $x['numero_exterior'] ?? null);
                if (trim((string)($x['numero_interior'] ?? '')) !== '') {
                    $addEl($dom, $xEl, $ns, 'numero_interior', $x['numero_interior']);
                }
                $addEl($dom, $xEl, $ns, 'codigo_postal', $x['codigo_postal'] ?? null);
            }
        };

        $writeGarante = function (DOMDocument $dom, DOMElement $parent, string $ns, array $pData) use ($addEl, $mkEl) {
            if (isset($pData['persona_fisica']) && is_array($pData['persona_fisica'])) {
                $pf = $pData['persona_fisica'];
                $pfEl = $mkEl($dom, $parent, $ns, 'persona_fisica');
                $addEl($dom, $pfEl, $ns, 'nombre', $pf['nombre'] ?? null);
                $addEl($dom, $pfEl, $ns, 'apellido_paterno', $pf['apellido_paterno'] ?? null);
                $addEl($dom, $pfEl, $ns, 'apellido_materno', $pf['apellido_materno'] ?? null);
                $addEl($dom, $pfEl, $ns, 'fecha_nacimiento', $pf['fecha_nacimiento'] ?? null);
                $addEl($dom, $pfEl, $ns, 'rfc', $pf['rfc'] ?? null);
                $addEl($dom, $pfEl, $ns, 'curp', $pf['curp'] ?? null);
                return;
            }
            if (isset($pData['persona_moral']) && is_array($pData['persona_moral'])) {
                $pm = $pData['persona_moral'];
                $pmEl = $mkEl($dom, $parent, $ns, 'persona_moral');
                $addEl($dom, $pmEl, $ns, 'denominacion_razon', $pm['denominacion_razon'] ?? null);
                $addEl($dom, $pmEl, $ns, 'fecha_constitucion', $pm['fecha_constitucion'] ?? null);
                $addEl($dom, $pmEl, $ns, 'rfc', $pm['rfc'] ?? null);
                return;
            }
            if (isset($pData['fideicomiso']) && is_array($pData['fideicomiso'])) {
                $fi = $pData['fideicomiso'];
                $fiEl = $mkEl($dom, $parent, $ns, 'fideicomiso');
                $addEl($dom, $fiEl, $ns, 'denominacion_razon', $fi['denominacion_razon'] ?? null);
                $addEl($dom, $fiEl, $ns, 'rfc', $fi['rfc'] ?? null);
                $addEl($dom, $fiEl, $ns, 'identificador_fideicomiso', $fi['identificador_fideicomiso'] ?? null);
            }
        };

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $archivo = $dom->createElementNS($NS, 'archivo');
        $archivo->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $XSI);
        $archivo->setAttributeNS($XSI, 'xsi:schemaLocation', $NS . ' mpc.xsd');
        $dom->appendChild($archivo);

        foreach ($data['informe'] as $inf) {
            if (!is_array($inf)) continue;
            $informeEl = $mkEl($dom, $archivo, $NS, 'informe');
            $addEl($dom, $informeEl, $NS, 'mes_reportado', $inf['mes_reportado'] ?? null);

            $so = is_array($inf['sujeto_obligado'] ?? null) ? $inf['sujeto_obligado'] : [];
            $soEl = $mkEl($dom, $informeEl, $NS, 'sujeto_obligado');
            if (trim((string)($so['clave_entidad_colegiada'] ?? '')) !== '') {
                $addEl($dom, $soEl, $NS, 'clave_entidad_colegiada', $so['clave_entidad_colegiada']);
            }
            $addEl($dom, $soEl, $NS, 'clave_sujeto_obligado', $so['clave_sujeto_obligado'] ?? null);
            $addEl($dom, $soEl, $NS, 'clave_actividad', $so['clave_actividad'] ?? 'MPC');
            if ((string)($so['exento'] ?? '') === '1') {
                $addEl($dom, $soEl, $NS, 'exento', '1');
            }

            $avisos = $normalizeList($inf['aviso'] ?? [], 'referencia_aviso');
            foreach ($avisos as $av) {
                if (!is_array($av)) continue;
                $avEl = $mkEl($dom, $informeEl, $NS, 'aviso');
                $addEl($dom, $avEl, $NS, 'referencia_aviso', $av['referencia_aviso'] ?? null);

                if (isset($av['modificatorio']) && is_array($av['modificatorio'])) {
                    $m = $av['modificatorio'];
                    $mEl = $mkEl($dom, $avEl, $NS, 'modificatorio');
                    $addEl($dom, $mEl, $NS, 'folio_modificacion', $m['folio_modificacion'] ?? null);
                    $addEl($dom, $mEl, $NS, 'descripcion_modificacion', $m['descripcion_modificacion'] ?? null);
                }

                $addEl($dom, $avEl, $NS, 'prioridad', $av['prioridad'] ?? null);
                $alerta = is_array($av['alerta'] ?? null) ? $av['alerta'] : [];
                $alertaEl = $mkEl($dom, $avEl, $NS, 'alerta');
                $addEl($dom, $alertaEl, $NS, 'tipo_alerta', $alerta['tipo_alerta'] ?? null);
                if (trim((string)($alerta['descripcion_alerta'] ?? '')) !== '') {
                    $addEl($dom, $alertaEl, $NS, 'descripcion_alerta', $alerta['descripcion_alerta']);
                }

                $personas = $normalizeList($av['persona_aviso'] ?? [], 'tipo_persona');
                foreach ($personas as $pa) {
                    if (!is_array($pa)) continue;
                    $paEl = $mkEl($dom, $avEl, $NS, 'persona_aviso');

                    $tpEl = $mkEl($dom, $paEl, $NS, 'tipo_persona');
                    $writePersona($dom, $tpEl, $NS, (array)($pa['tipo_persona'] ?? []));

                    if (isset($pa['tipo_domicilio']) && is_array($pa['tipo_domicilio'])) {
                        $tdEl = $mkEl($dom, $paEl, $NS, 'tipo_domicilio');
                        $writeDomicilio($dom, $tdEl, $NS, $pa['tipo_domicilio']);
                    }
                    if (isset($pa['telefono']) && is_array($pa['telefono'])) {
                        $t = $pa['telefono'];
                        $telEl = $mkEl($dom, $paEl, $NS, 'telefono');
                        $addEl($dom, $telEl, $NS, 'clave_pais', $t['clave_pais'] ?? null);
                        $addEl($dom, $telEl, $NS, 'numero_telefono', $t['numero_telefono'] ?? null);
                        $addEl($dom, $telEl, $NS, 'correo_electronico', $t['correo_electronico'] ?? null);
                    }
                }

                $duenos = $normalizeList($av['dueno_beneficiario'] ?? [], 'tipo_persona');
                foreach ($duenos as $db) {
                    if (!is_array($db)) continue;
                    $dbEl = $mkEl($dom, $avEl, $NS, 'dueno_beneficiario');
                    $tpEl = $mkEl($dom, $dbEl, $NS, 'tipo_persona');
                    $writePersonaSimple($dom, $tpEl, $NS, (array)($db['tipo_persona'] ?? []));
                }

                $det = $normalizeList($av['detalle_operaciones'] ?? [], 'datos_operacion');
                foreach ($det as $detItem) {
                    if (!is_array($detItem)) continue;
                    $detEl = $mkEl($dom, $avEl, $NS, 'detalle_operaciones');

                    $ops = $normalizeList($detItem['datos_operacion'] ?? [], 'fecha_operacion');
                    foreach ($ops as $op) {
                        if (!is_array($op)) continue;
                        $opEl = $mkEl($dom, $detEl, $NS, 'datos_operacion');
                        $addEl($dom, $opEl, $NS, 'fecha_operacion', $op['fecha_operacion'] ?? null);
                        $addEl($dom, $opEl, $NS, 'codigo_postal', $op['codigo_postal'] ?? null);
                        $addEl($dom, $opEl, $NS, 'tipo_operacion', $op['tipo_operacion'] ?? null);

                        $garantias = $normalizeList($op['datos_garantia'] ?? [], 'tipo_garantia');
                        foreach ($garantias as $g) {
                            if (!is_array($g)) continue;
                            $gEl = $mkEl($dom, $opEl, $NS, 'datos_garantia');
                            $addEl($dom, $gEl, $NS, 'tipo_garantia', $g['tipo_garantia'] ?? null);

                            if (isset($g['datos_bien_mutuo']) && is_array($g['datos_bien_mutuo'])) {
                                $dbm = $g['datos_bien_mutuo'];
                                $dbmEl = $mkEl($dom, $gEl, $NS, 'datos_bien_mutuo');
                                if (isset($dbm['datos_inmueble']) && is_array($dbm['datos_inmueble'])) {
                                    $di = $dbm['datos_inmueble'];
                                    $diEl = $mkEl($dom, $dbmEl, $NS, 'datos_inmueble');
                                    $addEl($dom, $diEl, $NS, 'tipo_inmueble', $di['tipo_inmueble'] ?? null);
                                    $addEl($dom, $diEl, $NS, 'valor_referencia', $di['valor_referencia'] ?? null);
                                    $addEl($dom, $diEl, $NS, 'codigo_postal', $di['codigo_postal'] ?? null);
                                    $addEl($dom, $diEl, $NS, 'folio_real', $di['folio_real'] ?? null);
                                } elseif (isset($dbm['datos_otro']) && is_array($dbm['datos_otro'])) {
                                    $do = $dbm['datos_otro'];
                                    $doEl = $mkEl($dom, $dbmEl, $NS, 'datos_otro');
                                    $addEl($dom, $doEl, $NS, 'descripcion_garantia', $do['descripcion_garantia'] ?? null);
                                }
                            }

                            if (isset($g['tipo_persona']) && is_array($g['tipo_persona'])) {
                                $tgEl = $mkEl($dom, $gEl, $NS, 'tipo_persona');
                                $writeGarante($dom, $tgEl, $NS, $g['tipo_persona']);
                            }
                        }

                        $liqs = $normalizeList($op['datos_liquidacion'] ?? [], 'monto_operacion');
                        foreach ($liqs as $liq) {
                            if (!is_array($liq)) continue;
                            $lEl = $mkEl($dom, $opEl, $NS, 'datos_liquidacion');
                            $addEl($dom, $lEl, $NS, 'fecha_disposicion', $liq['fecha_disposicion'] ?? null);
                            $addEl($dom, $lEl, $NS, 'instrumento_monetario', $liq['instrumento_monetario'] ?? null);
                            $addEl($dom, $lEl, $NS, 'moneda', $liq['moneda'] ?? null);
                            $addEl($dom, $lEl, $NS, 'monto_operacion', $liq['monto_operacion'] ?? null);
                        }
                    }
                }
            }
        }

        return ['xml' => $dom->saveXML(), 'errors' => []];
    }
}
