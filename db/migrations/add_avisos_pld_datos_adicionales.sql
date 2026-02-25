-- ============================================
-- Migration: datos_adicionales en avisos_pld
-- Fecha: 2026-02-25
-- ============================================

SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'avisos_pld'
      AND COLUMN_NAME = 'datos_adicionales'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `avisos_pld`
     ADD COLUMN `datos_adicionales` JSON DEFAULT NULL COMMENT ''Metadatos del aviso (acumulación, trazabilidad)'' AFTER `observaciones`',
    'SELECT ''Column datos_adicionales already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed: datos_adicionales on avisos_pld.' AS result;

