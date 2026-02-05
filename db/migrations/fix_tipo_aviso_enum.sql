-- Migration: Actualizar ENUM de tipo_aviso en operaciones_pld
-- Fecha: 2025-01-21
-- Descripción: Agregar valores 'sospechosa_24h' y 'listas_restringidas_24h' al ENUM de tipo_aviso

-- Verificar si la columna existe
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'operaciones_pld'
    AND COLUMN_NAME = 'tipo_aviso'
);

-- Si la columna existe, actualizar el ENUM
SET @sql = IF(@column_exists > 0,
    'ALTER TABLE `operaciones_pld` 
     MODIFY COLUMN `tipo_aviso` ENUM(
         ''umbral_individual'', 
         ''acumulacion'', 
         ''sospechosa'', 
         ''sospechosa_24h'',
         ''listas_restringidas'',
         ''listas_restringidas_24h''
     ) DEFAULT NULL',
    'SELECT ''Column tipo_aviso does not exist'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar el cambio
SELECT COLUMN_NAME, COLUMN_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'operaciones_pld'
AND COLUMN_NAME = 'tipo_aviso';
