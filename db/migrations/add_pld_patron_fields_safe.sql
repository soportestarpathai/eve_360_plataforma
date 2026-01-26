-- Migration: Agregar campos para validación VAL-PLD-001 (Versión Segura)
-- Fecha: 2025-01-21
-- Descripción: Campos para validar padrón PLD del sujeto obligado
-- Este script verifica si las columnas existen antes de agregarlas

-- Verificar y agregar estatus_patron_pld
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'config_empresa' 
    AND COLUMN_NAME = 'estatus_patron_pld'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `config_empresa` ADD COLUMN `estatus_patron_pld` VARCHAR(50) DEFAULT NULL COMMENT ''Estatus en el padrón PLD (vigente, baja, suspendido)'' AFTER `id_vulnerable`',
    'SELECT ''Columna estatus_patron_pld ya existe'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar fecha_revalidacion_patron
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'config_empresa' 
    AND COLUMN_NAME = 'fecha_revalidacion_patron'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `config_empresa` ADD COLUMN `fecha_revalidacion_patron` DATE DEFAULT NULL COMMENT ''Fecha de última revalidación del padrón'' AFTER `estatus_patron_pld`',
    'SELECT ''Columna fecha_revalidacion_patron ya existe'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar folio_patron_pld
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'config_empresa' 
    AND COLUMN_NAME = 'folio_patron_pld'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `config_empresa` ADD COLUMN `folio_patron_pld` VARCHAR(100) DEFAULT NULL COMMENT ''Folio de registro en el padrón PLD del SAT'' AFTER `fecha_revalidacion_patron`',
    'SELECT ''Columna folio_patron_pld ya existe'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar no_habilitado_pld
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'config_empresa' 
    AND COLUMN_NAME = 'no_habilitado_pld'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `config_empresa` ADD COLUMN `no_habilitado_pld` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = NO habilitado, 0 = habilitado'' AFTER `folio_patron_pld`',
    'SELECT ''Columna no_habilitado_pld ya existe'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar fracciones_activas
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'config_empresa' 
    AND COLUMN_NAME = 'fracciones_activas'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `config_empresa` ADD COLUMN `fracciones_activas` JSON DEFAULT NULL COMMENT ''Fracciones activas registradas en el padrón'' AFTER `no_habilitado_pld`',
    'SELECT ''Columna fracciones_activas ya existe'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y crear índice
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'config_empresa' 
    AND INDEX_NAME = 'idx_config_empresa_pld_habilitado'
);

SET @sql = IF(@idx_exists = 0,
    'CREATE INDEX `idx_config_empresa_pld_habilitado` ON `config_empresa`(`no_habilitado_pld`, `estatus_patron_pld`)',
    'SELECT ''Índice idx_config_empresa_pld_habilitado ya existe'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
