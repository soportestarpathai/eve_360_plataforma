-- ============================================
-- Migration: XML DIN en operaciones PLD
-- Almacena el XML generado por fracción (ej. DIN para V/V Bis)
-- ============================================

SELECT COUNT(*) INTO @col_xml FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'operaciones_pld' AND COLUMN_NAME = 'xml_contenido';

SET @sql = IF(@col_xml = 0,
    'ALTER TABLE `operaciones_pld`
     ADD COLUMN `xml_contenido` LONGTEXT DEFAULT NULL COMMENT ''XML del aviso (ej. DIN según XSD)'' AFTER `id_aviso_generado`,
     ADD COLUMN `xml_nombre_archivo` VARCHAR(255) DEFAULT NULL COMMENT ''Nombre del archivo XML generado'' AFTER `xml_contenido`',
    'SELECT ''Column xml_contenido already exists'' AS msg');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'XML columns added to operaciones_pld' AS result;
