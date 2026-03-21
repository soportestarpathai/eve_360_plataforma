<?php
/**
 * Helper XML AVI (Activos Virtuales) — Fracción XVI
 * Generador flexible basado en estructura de payload (informe/aviso/operaciones_persona).
 */

if (!function_exists('aviValidarClaveSO')) {
    /**
     * RFC 12-13 (PF/PM): 3-4 letras + 6 dígitos + 3 alfanum.
     */
    function aviValidarClaveSO($rfc): bool {
        $rfc = strtoupper(trim((string)$rfc));
        return (bool)preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc);
    }
}

if (!function_exists('aviIsAssocArray')) {
    function aviIsAssocArray(array $arr): bool {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

if (!function_exists('aviAppendNode')) {
    /**
     * Inserta nodos recursivamente:
     * - array asociativo -> nodo único con hijos
     * - array indexado -> nodos repetidos con el mismo nombre
     */
    function aviAppendNode(DOMDocument $dom, DOMElement $parent, string $name, $value, string $ns): void {
        if (is_array($value)) {
            if (aviIsAssocArray($value)) {
                $el = $dom->createElementNS($ns, $name);
                $parent->appendChild($el);
                foreach ($value as $childName => $childValue) {
                    if ($childValue === null || $childValue === '') {
                        continue;
                    }
                    if (!is_string($childName) || $childName === '') {
                        continue;
                    }
                    aviAppendNode($dom, $el, $childName, $childValue, $ns);
                }
                return;
            }

            foreach ($value as $item) {
                if ($item === null || $item === '') {
                    continue;
                }
                aviAppendNode($dom, $parent, $name, $item, $ns);
            }
            return;
        }

        $el = $dom->createElementNS($ns, $name);
        $el->appendChild($dom->createTextNode((string)$value));
        $parent->appendChild($el);
    }
}

if (!function_exists('generateAVIXml')) {
    /**
     * @param array $data Debe incluir clave informe[].
     * @param string|null $xsdPath Ruta opcional de XSD para validación.
     * @return array ['xml'=>string, 'errors'=>array]
     */
    function generateAVIXml(array $data, ?string $xsdPath = null): array {
        $errors = [];
        $NS = 'http://www.uif.shcp.gob.mx/recepcion/avi';
        $XSI = 'http://www.w3.org/2001/XMLSchema-instance';

        try {
            $informes = $data['informe'] ?? null;
            if (!is_array($informes) || empty($informes)) {
                return ['xml' => '', 'errors' => ['Estructura inválida: informe[] requerido']];
            }

            $dom = new DOMDocument('1.0', 'UTF-8');
            $dom->formatOutput = true;

            $root = $dom->createElementNS($NS, 'archivo');
            $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $XSI);
            $root->setAttributeNS($XSI, 'xsi:schemaLocation', $NS . ' avi.xsd');
            $dom->appendChild($root);

            foreach ($informes as $informe) {
                if (!is_array($informe)) {
                    continue;
                }
                $informeEl = $dom->createElementNS($NS, 'informe');
                $root->appendChild($informeEl);

                foreach ($informe as $key => $value) {
                    if ($value === null || $value === '' || !is_string($key) || $key === '') {
                        continue;
                    }
                    aviAppendNode($dom, $informeEl, $key, $value, $NS);
                }
            }

            $xml = $dom->saveXML();
            if (!$xml) {
                return ['xml' => '', 'errors' => ['No se pudo serializar XML AVI']];
            }

            if ($xsdPath && is_file($xsdPath)) {
                libxml_use_internal_errors(true);
                $ok = $dom->schemaValidate($xsdPath);
                if (!$ok) {
                    foreach (libxml_get_errors() as $err) {
                        $errors[] = trim($err->message);
                    }
                }
                libxml_clear_errors();
            }

            return ['xml' => $xml, 'errors' => $errors];
        } catch (Throwable $e) {
            return ['xml' => '', 'errors' => ['Error al generar XML AVI: ' . $e->getMessage()]];
        }
    }
}
