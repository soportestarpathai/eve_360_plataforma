<?php
/**
 * Helper XML ADU (Fraccion XIV).
 * Generador flexible inicial, pendiente de afinar contra XSD oficial completo.
 */

if (!function_exists('aduToUpper')) {
    function aduToUpper($val): string
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

if (!function_exists('aduMonto')) {
    function aduMonto($val): string
    {
        if ($val === null || $val === '') return '0.00';
        return number_format((float)$val, 2, '.', '');
    }
}

if (!function_exists('aduSanitizeScalar')) {
    function aduSanitizeScalar(string $key, $value): string
    {
        $v = trim((string)$value);
        if ($v === '') return '';
        $k = strtolower($key);
        if (in_array($k, ['monto_operacion', 'valor_mercancia', 'valor_aduana'], true)) return aduMonto($v);
        if (strpos($k, 'fecha') !== false) {
            $num = preg_replace('/\D+/', '', $v);
            return strlen($num) >= 8 ? substr($num, 0, 8) : $num;
        }
        if ($k === 'mes_reportado') return substr(preg_replace('/\D+/', '', $v), 0, 6);
        if ($k === 'clave_actividad') return 'ADU';
        if (in_array($k, ['clave_sujeto_obligado', 'clave_entidad_colegiada', 'referencia_aviso', 'rfc', 'curp', 'pedimento'], true)) {
            return preg_replace('/[^A-Z0-9Ñ&\-_]/u', '', aduToUpper($v));
        }
        if ($k === 'codigo_postal') return preg_replace('/[^0-9A-ZÑ]/u', '', aduToUpper($v));
        if (in_array($k, ['descripcion', 'descripcion_alerta', 'descripcion_mercancia'], true)) {
            return preg_replace('/[^A-ZÑ0-9 \-\.,:\/\'$]/u', '', aduToUpper($v));
        }
        return aduToUpper($v);
    }
}

if (!function_exists('generateADUXml')) {
    function generateADUXml(array $data): array
    {
        $NS = 'http://www.uif.shcp.gob.mx/recepcion/adu';
        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $archivo = $dom->createElementNS($NS, 'archivo');
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
            $txt = aduSanitizeScalar($name, $value);
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
