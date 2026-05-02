<?php
/**
 * Helper XML INM (Inmuebles / V Bis independiente de DIN).
 */

if (!function_exists('formatMontoInm')) {
    function formatMontoInm($val): string
    {
        if ($val === null || $val === '') return '0.00';
        return number_format((float)$val, 2, '.', '');
    }
}

if (!function_exists('inmToUpper')) {
    function inmToUpper($val): string
    {
        $v = trim((string)$val);
        if ($v === '') return '';
        $v = mb_strtoupper($v, 'UTF-8');
        return strtr($v, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C'
        ]);
    }
}

if (!function_exists('inmSanitizeScalar')) {
    function inmSanitizeScalar(string $key, $value): string
    {
        $v = trim((string)$value);
        if ($v === '') return '';

        $k = strtolower($key);
        if (strpos($k, 'monto') !== false || strpos($k, 'valor') !== false || strpos($k, 'dimension') !== false) {
            return formatMontoInm($v);
        }
        if (strpos($k, 'fecha') !== false) {
            $num = preg_replace('/\D+/', '', $v);
            return strlen($num) >= 8 ? substr($num, 0, 8) : $num;
        }
        if ($k === 'mes_reportado') {
            return substr(preg_replace('/\D+/', '', $v), 0, 6);
        }
        if ($k === 'clave_actividad') {
            return 'INM';
        }
        if (in_array($k, ['clave_sujeto_obligado', 'clave_entidad_colegiada', 'rfc'], true)) {
            return preg_replace('/[^A-Z0-9Ñ&%+]/u', '', inmToUpper($v));
        }
        if ($k === 'referencia_aviso') {
            return substr(preg_replace('/[^A-Z0-9Ñ]/u', '', inmToUpper($v)), 0, 14);
        }
        if ($k === 'folio_modificacion') {
            return substr(preg_replace('/[^0-9\-]/', '', inmToUpper($v)), 0, 14);
        }
        if (in_array($k, ['folio_real'], true)) {
            return substr(preg_replace('/[^A-Z0-9\-_]/', '', inmToUpper($v)), 0, 200);
        }
        if (in_array($k, ['numero_instrumento_publico'], true)) {
            return substr(preg_replace('/[^A-Z0-9\-_]/', '', inmToUpper($v)), 0, 20);
        }
        if (in_array($k, ['notario_instrumento_publico'], true)) {
            return substr(preg_replace('/[^A-Z0-9\-_]/', '', inmToUpper($v)), 0, 8);
        }
        if ($k === 'curp') {
            return preg_replace('/[^A-Z0-9]/', '', inmToUpper($v));
        }
        if ($k === 'codigo_postal') {
            return preg_replace('/[^0-9A-Z]/', '', inmToUpper($v));
        }
        if (in_array($k, ['nombre', 'apellido_paterno', 'apellido_materno'], true)) {
            return preg_replace('/[^A-ZÑ \.,]/u', '', inmToUpper($v));
        }
        if (in_array($k, ['colonia', 'calle', 'numero_exterior', 'numero_interior', 'estado_provincia', 'ciudad_poblacion'], true)) {
            return preg_replace('/[^A-ZÑ0-9 \-\.,;:\/()\[\]"_\/+\'&#]/u', '', inmToUpper($v));
        }
        if (in_array($k, ['descripcion_alerta', 'descripcion_modificacion'], true)) {
            return preg_replace('/[^A-ZÑ0-9 \-_\.&,\'\:\/$;()"\[\]#@]/u', '', inmToUpper($v));
        }
        if (in_array($k, ['denominacion_razon'], true)) {
            return preg_replace('/[^A-ZÑ0-9 #\-\.&,_@\';:+\/()\[\]{}]/u', '', inmToUpper($v));
        }
        if ($k === 'identificador_fideicomiso') {
            return substr(preg_replace('/[^A-ZÑ0-9 \-_\.&,\'#@\:\/$;()"\[\]]/u', '', inmToUpper($v)), 0, 40);
        }
        return inmToUpper($v);
    }
}

if (!function_exists('generateINMXml')) {
    function generateINMXml(array $data): array
    {
        $NS = 'http://www.uif.shcp.gob.mx/recepcion/inm';
        $XSI = 'http://www.w3.org/2001/XMLSchema-instance';

        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $archivo = $dom->createElementNS($NS, 'archivo');
        $archivo->setAttributeNS($XSI, 'xsi:schemaLocation', $NS . ' inm.xsd');
        $dom->appendChild($archivo);

        $appendNode = function (DOMNode $parent, string $name, $value) use (&$appendNode, $dom, $NS) {
            if ($value === null) return;
            if (is_array($value)) {
                if ($value === []) return;
                $isSequential = array_keys($value) === range(0, count($value) - 1);
                if ($isSequential) {
                    foreach ($value as $item) $appendNode($parent, $name, $item);
                    return;
                }
                $node = $dom->createElementNS($NS, $name);
                $parent->appendChild($node);
                foreach ($value as $k => $v) $appendNode($node, (string)$k, $v);
                return;
            }
            $txt = inmSanitizeScalar($name, $value);
            if ($txt === '') return;
            $node = $dom->createElementNS($NS, $name);
            $node->appendChild($dom->createTextNode($txt));
            $parent->appendChild($node);
        };

        foreach ($data['informe'] as $inf) {
            if (!is_array($inf)) continue;
            $informe = $dom->createElementNS($NS, 'informe');
            $archivo->appendChild($informe);
            foreach ($inf as $k => $v) $appendNode($informe, (string)$k, $v);
        }

        return ['xml' => $dom->saveXML(), 'errors' => []];
    }
}
