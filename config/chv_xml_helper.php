<?php
/**
 * Helper XML CHV (Fracción III - Cheques de viajero).
 * Generador base flexible; se ajustará al XSD oficial cuando esté disponible.
 */

if (!function_exists('formatMontoChv')) {
    function formatMontoChv($val): string
    {
        if ($val === null || $val === '') return '0.00';
        return number_format((float)$val, 2, '.', '');
    }
}

if (!function_exists('chvToUpper')) {
    function chvToUpper($val): string
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
            'Ç' => 'C'
        ];
        return strtr($v, $map);
    }
}

if (!function_exists('chvSanitizeScalar')) {
    function chvSanitizeScalar(string $key, $value): string
    {
        $v = trim((string)$value);
        if ($v === '') return '';

        $k = strtolower($key);
        if (strpos($k, 'monto') !== false || strpos($k, 'valor') !== false) {
            return formatMontoChv($v);
        }
        if (strpos($k, 'fecha') !== false) {
            $num = preg_replace('/\D+/', '', $v);
            return strlen($num) >= 8 ? substr($num, 0, 8) : $num;
        }
        if ($k === 'mes_reportado') {
            return substr(preg_replace('/\D+/', '', $v), 0, 6);
        }
        if ($k === 'clave_actividad') {
            return 'CHV';
        }
        if (in_array($k, ['clave_sujeto_obligado', 'clave_entidad_colegiada', 'referencia_aviso', 'rfc', 'curp'], true)) {
            return preg_replace('/[^A-Z0-9Ñ&\-_]/u', '', chvToUpper($v));
        }
        if ($k === 'codigo_postal') {
            return preg_replace('/[^0-9A-Z]/', '', chvToUpper($v));
        }
        if ($k === 'numero_cheques') {
            return substr(preg_replace('/\D+/', '', $v), 0, 18);
        }
        return chvToUpper($v);
    }
}

if (!function_exists('generateCHVXml')) {
    function generateCHVXml(array $data): array
    {
        $NS = 'http://www.uif.shcp.gob.mx/recepcion/chv';
        $XSI = 'http://www.w3.org/2001/XMLSchema-instance';

        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $archivo = $dom->createElementNS($NS, 'archivo');
        $archivo->setAttributeNS($XSI, 'xsi:schemaLocation', $NS . ' chv.xsd');
        $dom->appendChild($archivo);

        $appendNode = function (DOMNode $parent, string $name, $value) use (&$appendNode, $dom, $NS) {
            if ($value === null) return;
            if (is_array($value)) {
                if ($value === []) return;
                $isSequential = array_keys($value) === range(0, count($value) - 1);
                if ($isSequential) {
                    foreach ($value as $item) {
                        $appendNode($parent, $name, $item);
                    }
                    return;
                }
                $node = $dom->createElementNS($NS, $name);
                $parent->appendChild($node);
                foreach ($value as $k => $v) {
                    $appendNode($node, (string)$k, $v);
                }
                return;
            }
            $txt = chvSanitizeScalar($name, $value);
            if ($txt === '') return;
            $node = $dom->createElementNS($NS, $name);
            $node->appendChild($dom->createTextNode($txt));
            $parent->appendChild($node);
        };

        foreach ($data['informe'] as $inf) {
            if (!is_array($inf)) continue;
            $informe = $dom->createElementNS($NS, 'informe');
            $archivo->appendChild($informe);
            foreach ($inf as $k => $v) {
                $appendNode($informe, (string)$k, $v);
            }
        }

        return ['xml' => $dom->saveXML(), 'errors' => []];
    }
}
