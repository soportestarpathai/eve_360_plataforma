-- ============================================
-- Migration: Campos para Expediente PLD (VAL-PLD-005 y VAL-PLD-006)
-- Fecha: 2025-01-29
-- Descripción: Agrega solo los campos necesarios para VAL-PLD-005 y VAL-PLD-006
-- ============================================

-- Verificar y agregar campo fecha_ultima_actualizacion_expediente
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'clientes' 
    AND COLUMN_NAME = 'fecha_ultima_actualizacion_expediente'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `clientes` 
     ADD COLUMN `fecha_ultima_actualizacion_expediente` DATE DEFAULT NULL 
     COMMENT ''Fecha última actualización del expediente (VAL-PLD-006)''',
    'SELECT ''Campo fecha_ultima_actualizacion_expediente ya existe'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar campo identificacion_incompleta
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'clientes' 
    AND COLUMN_NAME = 'identificacion_incompleta'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `clientes` 
     ADD COLUMN `identificacion_incompleta` TINYINT(1) DEFAULT 0 
     COMMENT ''Flag: 1 = Expediente incompleto (VAL-PLD-005)''',
    'SELECT ''Campo identificacion_incompleta ya existe'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar campo expediente_completo
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'clientes' 
    AND COLUMN_NAME = 'expediente_completo'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `clientes` 
     ADD COLUMN `expediente_completo` TINYINT(1) DEFAULT 0 
     COMMENT ''Flag: 1 = Expediente completo''',
    'SELECT ''Campo expediente_completo ya existe'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mensaje final
SELECT 'Migración completada. Verifica los campos con: SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ''clientes'' AND COLUMN_NAME LIKE ''%expediente%'' OR COLUMN_NAME LIKE ''%identificacion%''' AS resultado;
