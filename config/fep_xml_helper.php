<?php
/**
 * Helper XML FEP (Fraccion XII).
 */

if (!function_exists('fepToUpper')) {
    function fepToUpper($val): string
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

if (!function_exists('fepMonto')) {
    function fepMonto($val): string
    {
        if ($val === null || $val === '') return '0.00';
        return number_format((float)$val, 2, '.', '');
    }
}

if (!function_exists('fepSanitizeScalar')) {
    function fepSanitizeScalar(string $key, $value): string
    {
        $v = trim((string)$value);
        if ($v === '') return '';
        $k = strtolower($key);
        if ($k === 'tipo_persona_moral_otra') {
            return preg_replace('/[^A-ZÑ0-9 \-_\.&,\x27#@]/u', '', fepToUpper($v));
        }
        if (strpos($k, 'tipo_') === 0) {
            return preg_replace('/\D+/', '', $v);
        }
        if (preg_match('/(monto|valor|capital|acciones)/', $k) || in_array($k, [
            'valor_nominal', 'valor_avaluo', 'valor_referencia', 'valor_bien',
            'monto_operacion', 'monto_patrimonio', 'monto_cesion'
        ], true)) return fepMonto($v);
        if (strpos($k, 'fecha') !== false) {
            $num = preg_replace('/\D+/', '', $v);
            return strlen($num) >= 8 ? substr($num, 0, 8) : $num;
        }
        if ($k === 'mes_reportado') return substr(preg_replace('/\D+/', '', $v), 0, 6);
        if ($k === 'clave_actividad') return 'FEP';
        if ($k === 'referencia_aviso') return preg_replace('/[^A-Z0-9Ñ]/u', '', fepToUpper($v));
        if (in_array($k, ['instrumento_publico', 'folio_mercantil', 'folio_real', 'identificador_fideicomiso'], true)) {
            return substr(preg_replace('/[^A-Z0-9\-_]/u', '', fepToUpper($v)), 0, 200);
        }
        if (in_array($k, ['clave_sujeto_obligado', 'clave_entidad_colegiada', 'rfc', 'curp'], true)) {
            return preg_replace('/[^A-Z0-9Ñ&]/u', '', fepToUpper($v));
        }
        if ($k === 'codigo_postal') return preg_replace('/[^0-9A-ZÑ]/u', '', fepToUpper($v));
        if (in_array($k, ['nombre', 'apellido_paterno', 'apellido_materno'], true)) {
            return preg_replace('/[^A-ZÑ ]/u', '', fepToUpper($v));
        }
        if ($k === 'denominacion_razon') {
            return preg_replace('/[^A-ZÑ0-9 #\-\.&,_@\'()]/u', '', fepToUpper($v));
        }
        if (in_array($k, ['descripcion', 'descripcion_alerta', 'descripcion_garantia'], true)) {
            return preg_replace('/[^A-ZÑ0-9 \-\.,:\/\'$]/u', '', fepToUpper($v));
        }
        return fepToUpper($v);
    }
}

if (!function_exists('generateFEPXml')) {
    function generateFEPXml(array $data): array
    {
        $NS = 'http://www.uif.shcp.gob.mx/recepcion/fep';
        $XSI = 'http://www.w3.org/2001/XMLSchema-instance';
        if (!isset($data['informe']) || !is_array($data['informe'])) {
            return ['xml' => '', 'errors' => ['informe[] requerido']];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $archivo = $dom->createElementNS($NS, 'archivo');
        $archivo->setAttributeNS($XSI, 'xsi:schemaLocation', $NS . ' fep.xsd');
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
            $txt = fepSanitizeScalar($name, $value);
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
