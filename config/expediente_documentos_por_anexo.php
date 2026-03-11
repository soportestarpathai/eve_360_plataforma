<?php
/**
 * Documentos requeridos por anexo - Reglas para Expedientes KYC EVE360
 * Fuente: REGLAS para Expedientes de Clientes y Usuarios KYC EVE360.pdf
 * Art. 12 RCG - Anexos 3, 4, 4 Bis, 5, 6, 6 Bis, 7, 7 Bis, 8
 */

if (!function_exists('getDocumentosRequeridosPorAnexo')) {
    /**
     * Devuelve la lista de documentos requeridos según el anexo aplicable
     * @param string|null $claveAnexo Clave del anexo (ANEXO_3, ANEXO_4, etc.)
     * @return array Lista de documentos requeridos con descripción
     */
    function getDocumentosRequeridosPorAnexo($claveAnexo) {
        $map = [
            'ANEXO_3' => [
                ['desc' => 'Identificación oficial vigente (credencial para votar, pasaporte, cédula profesional o documento migratorio)', 'nota' => 'Al menos uno'],
                ['desc' => 'Documento que acredite legal estancia en el país', 'nota' => 'Solo para extranjeros con residencia'],
            ],
            'ANEXO_4' => [
                ['desc' => 'Acta constitutiva inscrita en el Registro Público de Comercio (y modificaciones)', 'nota' => null],
                ['desc' => 'Poder notarial vigente del representante legal o apoderado', 'nota' => null],
                ['desc' => 'Identificación oficial vigente del representante legal o apoderado', 'nota' => 'Conforme Anexo 3'],
                ['desc' => 'Cédula de identificación fiscal o constancia de inscripción en el RFC', 'nota' => null],
                ['desc' => 'Comprobante de domicilio del domicilio social', 'nota' => null],
            ],
            'ANEXO_4_BIS' => [
                ['desc' => 'Documento oficial que acredite la creación, constitución o existencia de la persona moral de derecho público', 'nota' => null],
                ['desc' => 'Documento oficial que acredite las facultades del servidor público, funcionario o representante', 'nota' => null],
                ['desc' => 'Identificación oficial vigente del servidor público, funcionario o representante', 'nota' => null],
                ['desc' => 'Comprobante de domicilio', 'nota' => null],
            ],
            'ANEXO_5' => [
                ['desc' => 'Pasaporte vigente expedido por la autoridad competente del país de origen', 'nota' => null],
                ['desc' => 'Documento migratorio vigente que acredite legal estancia en el país', 'nota' => 'Cuando corresponda'],
            ],
            'ANEXO_6' => [
                ['desc' => 'Documento oficial que acredite constitución, existencia y personalidad jurídica (legalizado o apostillado)', 'nota' => null],
                ['desc' => 'Documento oficial que acredite facultades del representante legal o apoderado (legalizado o apostillado)', 'nota' => null],
                ['desc' => 'Identificación oficial vigente del representante legal o apoderado', 'nota' => 'Conforme Anexo 5'],
                ['desc' => 'Comprobante de domicilio', 'nota' => null],
            ],
            'ANEXO_6_BIS' => [
                ['desc' => 'Documento oficial que acredite existencia, representación y acreditación ante el Estado Mexicano', 'nota' => null],
                ['desc' => 'Documento oficial que acredite facultades del funcionario o representante', 'nota' => null],
                ['desc' => 'Identificación oficial vigente del funcionario o representante', 'nota' => null],
                ['desc' => 'Comprobante de domicilio', 'nota' => null],
            ],
            'ANEXO_7' => [
                ['desc' => 'Documento oficial que acredite constitución, creación o existencia de la persona moral o entidad', 'nota' => null],
                ['desc' => 'Documento oficial que acredite facultades del representante, servidor público o funcionario', 'nota' => null],
                ['desc' => 'Identificación oficial vigente del representante, servidor público o funcionario', 'nota' => null],
                ['desc' => 'Comprobante de domicilio', 'nota' => null],
            ],
            'ANEXO_7_BIS' => [
                ['desc' => 'Documento oficial que acredite existencia y representación del ente', 'nota' => null],
                ['desc' => 'Documento oficial que acredite facultades del servidor público o funcionario', 'nota' => null],
                ['desc' => 'Identificación oficial vigente del funcionario o servidor público que celebre el acto', 'nota' => null],
            ],
            'ANEXO_8' => [
                ['desc' => 'Contrato de fideicomiso y modificaciones', 'nota' => null],
                ['desc' => 'Documento que acredite existencia y personalidad jurídica del fiduciario', 'nota' => null],
                ['desc' => 'Documento que acredite facultades del delegado fiduciario', 'nota' => null],
                ['desc' => 'Identificación oficial vigente del delegado fiduciario', 'nota' => null],
                ['desc' => 'Comprobante de domicilio', 'nota' => null],
            ],
        ];

        if (!$claveAnexo || !isset($map[$claveAnexo])) {
            return [];
        }
        return $map[$claveAnexo];
    }
}

/**
 * Plantilla de documentos para formularios de registro/edición
 * Según tipo de persona (fisica, moral, fideicomiso) → anexo típico
 * @param string $tipo 'fisica'|'moral'|'fideicomiso'
 * @return array [['key'=>'...','label'=>'...','field'=>'...','required'=>bool], ...]
 */
if (!function_exists('getDocumentosTemplatePorTipo')) {
    function getDocumentosTemplatePorTipo($tipo) {
        $tipo = strtolower(trim($tipo ?: ''));
        $templates = [
            'fisica' => [
                ['key' => 'soporte_nacionalidad', 'label' => 'Soporte de nacionalidad', 'field' => 'nac_doc', 'required' => true],
                ['key' => 'soporte_identificacion', 'label' => 'Identificación oficial vigente (INE, pasaporte, cédula profesional)', 'field' => 'ident_doc', 'required' => true],
                ['key' => 'comprobante_domicilio', 'label' => 'Comprobante de domicilio', 'field' => 'dir_doc', 'required' => true],
                ['key' => 'constancia_rfc', 'label' => 'Constancia RFC / Tax ID', 'field' => 'fisica_rfc_doc', 'required' => true],
                ['key' => 'documento_curp', 'label' => 'Documento CURP', 'field' => 'fisica_curp_doc', 'required' => false],
            ],
            'moral' => [
                ['key' => 'acta_constitutiva', 'label' => 'Acta constitutiva inscrita en RPC (y modificaciones)', 'field' => 'moral_acta_constitutiva', 'required' => true],
                ['key' => 'poder_notarial', 'label' => 'Poder notarial vigente del representante legal', 'field' => 'moral_poder_notarial', 'required' => true],
                ['key' => 'identificacion_representante', 'label' => 'Identificación oficial del representante legal', 'field' => 'ident_doc', 'required' => true],
                ['key' => 'constancia_rfc', 'label' => 'Cédula fiscal / Constancia RFC', 'field' => 'moral_rfc_doc', 'required' => true],
                ['key' => 'comprobante_domicilio', 'label' => 'Comprobante de domicilio del domicilio social', 'field' => 'dir_doc', 'required' => true],
            ],
            'fideicomiso' => [
                ['key' => 'contrato_fideicomiso', 'label' => 'Contrato de fideicomiso y modificaciones', 'field' => 'fide_contrato', 'required' => true],
                ['key' => 'documento_fiduciario', 'label' => 'Documento que acredite existencia y personalidad del fiduciario', 'field' => 'fide_doc_fiduciario', 'required' => true],
                ['key' => 'facultades_delegado', 'label' => 'Documento que acredite facultades del delegado fiduciario', 'field' => 'fide_facultades', 'required' => true],
                ['key' => 'identificacion_delegado', 'label' => 'Identificación oficial del delegado fiduciario', 'field' => 'fide_ident_delegado', 'required' => true],
                ['key' => 'comprobante_domicilio', 'label' => 'Comprobante de domicilio', 'field' => 'dir_doc', 'required' => true],
            ],
        ];
        return $templates[$tipo] ?? [];
    }
}
