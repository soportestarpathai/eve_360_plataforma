-- ============================================
-- Script para corregir la validación de expediente PLD
-- Fecha: 2025-01-29
-- Descripción: Agrega columna id_status si no existe o ajusta la validación
-- ============================================

-- Verificar y agregar id_status a clientes_identificaciones si no existe
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'clientes_identificaciones' 
    AND COLUMN_NAME = 'id_status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `clientes_identificaciones` 
     ADD COLUMN `id_status` TINYINT(1) DEFAULT 1 COMMENT ''Estado: 1=Activo, 0=Inactivo''',
    'SELECT ''Columna id_status ya existe en clientes_identificaciones'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar id_status a clientes_direcciones si no existe
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'clientes_direcciones' 
    AND COLUMN_NAME = 'id_status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `clientes_direcciones` 
     ADD COLUMN `id_status` TINYINT(1) DEFAULT 1 COMMENT ''Estado: 1=Activo, 0=Inactivo''',
    'SELECT ''Columna id_status ya existe en clientes_direcciones'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar id_status a clientes_contactos si no existe
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'clientes_contactos' 
    AND COLUMN_NAME = 'id_status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `clientes_contactos` 
     ADD COLUMN `id_status` TINYINT(1) DEFAULT 1 COMMENT ''Estado: 1=Activo, 0=Inactivo''',
    'SELECT ''Columna id_status ya existe en clientes_contactos'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar id_status a clientes_documentos si no existe
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'clientes_documentos' 
    AND COLUMN_NAME = 'id_status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `clientes_documentos` 
     ADD COLUMN `id_status` TINYINT(1) DEFAULT 1 COMMENT ''Estado: 1=Activo, 0=Inactivo''',
    'SELECT ''Columna id_status ya existe en clientes_documentos'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Inicializar id_status = 1 para registros existentes que tengan NULL
UPDATE `clientes_identificaciones` SET `id_status` = 1 WHERE `id_status` IS NULL;
UPDATE `clientes_direcciones` SET `id_status` = 1 WHERE `id_status` IS NULL;
UPDATE `clientes_contactos` SET `id_status` = 1 WHERE `id_status` IS NULL;
UPDATE `clientes_documentos` SET `id_status` = 1 WHERE `id_status` IS NULL;

SELECT 'Migración completada. Verifica las columnas con: db/migrations/check_table_columns.sql' AS resultado;
