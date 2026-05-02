<?php
/**
 * Helper XML FES (Fraccion XII - Fe publica, Servidores Publicos).
 */

require_once __DIR__ . '/fep_xml_helper.php';

if (!function_exists('fesToUpper')) {
    function fesToUpper($val): string
    {
        return fepToUpper($val);
    }
}

if (!function_exists('fesMonto')) {
    function fesMonto($val): string
    {
        return fepMonto($val);
    }
}

if (!function_exists('fesSanitizeScalar')) {
    function fesSanitizeScalar(string $key, $value): string
    {
        $v = trim((string)$value);
        if ($v === '') return '';
        $k = strtolower($key);
        if ($k === 'clave_actividad') return 'FES';
        if ($k === 'clave_tribunal_dependencia') return preg_replace('/[^A-Z0-9Ñ&]/u', '', fesToUpper($v));
        if (in_array($k, ['expediente', 'expediente_oficio', 'instrumento_publico', 'instrumento_publico_oficio'], true)) {
            return substr(preg_replace('/[^A-ZÑ0-9 \.,:\/\-_]/u', '', fesToUpper($v)), 0, 20);
        }
        if ($k === 'identificador_fideicomiso') {
            return substr(preg_replace('/[^A-ZÑ0-9 \-_\.&,\x27#@]/u', '', fesToUpper($v)), 0, 40);
        }
        if (in_array($k, ['organo', 'cargo', 'tipo_juicio', 'materia', 'tipo_acto_otro'], true)) {
            return substr(preg_replace('/[^A-ZÑ0-9 \.,:\/\-_]/u', '', fesToUpper($v)), 0, 100);
        }
        if (preg_match('/(monto|valor|capital|acciones|dimension|avaluo)/', $k)) return fesMonto($v);
        return fepSanitizeScalar($key, $value);
    }
}

if (!function_exists('generateFESXml')) {
    function generateFESXml(array $data): array
    {
        $NS = 'http://www.uif.shcp.gob.mx/recepcion/fes';
        $XSI = 'http://www.w3.org/2001/XMLSchema-instance';
        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $archivo = $dom->createElementNS($NS, 'archivo');
        $archivo->setAttributeNS($XSI, 'xsi:schemaLocation', $NS . ' fes.xsd');
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
            $txt = fesSanitizeScalar($name, $value);
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
