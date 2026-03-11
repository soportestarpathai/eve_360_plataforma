-- Columna subfraccion_xi en operaciones_pld para registrar subfracción SPR
-- Ej: compra_venta_inmuebles, cesion_derechos_inmuebles, administracion_recursos, etc.

SET @cnt = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'operaciones_pld' 
    AND COLUMN_NAME = 'subfraccion_xi');

SET @sql = IF(@cnt = 0,
    'ALTER TABLE operaciones_pld ADD COLUMN subfraccion_xi VARCHAR(80) DEFAULT NULL COMMENT ''Subfracción XI (SPR): compra_venta_inmuebles, administracion_recursos, etc.'' AFTER tipo_operacion',
    'SELECT ''subfraccion_xi ya existe'' AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'subfraccion_xi agregada a operaciones_pld' AS result;
