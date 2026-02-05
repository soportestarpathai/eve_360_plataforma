-- ============================================
-- Migration: Umbrales UMA para Fracción XIII (Recepción de Donativos)
-- Fuente: RCG PLD, Fracción XIII — Recepción de Donativos
-- ============================================
-- Umbral identificación donante: 1,605 UMA
-- Umbral aviso individual / acumulación: 3,210 UMA
-- ============================================

-- Agregar columnas de umbral en cat_vulnerables si no existen
SET @col_aviso = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cat_vulnerables' AND COLUMN_NAME = 'umbral_aviso_uma'
);
SET @col_acum = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cat_vulnerables' AND COLUMN_NAME = 'umbral_acumulacion_uma'
);
SET @col_exp = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cat_vulnerables' AND COLUMN_NAME = 'umbral_expediente_uma'
);

SET @sql = IF(@col_aviso = 0,
    'ALTER TABLE `cat_vulnerables` ADD COLUMN `umbral_aviso_uma` DECIMAL(12,2) DEFAULT NULL COMMENT ''UMA para aviso individual (ej. 3210 Fracción XIII)''',
    'SELECT ''umbral_aviso_uma exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@col_acum = 0,
    'ALTER TABLE `cat_vulnerables` ADD COLUMN `umbral_acumulacion_uma` DECIMAL(12,2) DEFAULT NULL COMMENT ''UMA para aviso por acumulación 6 meses (ej. 3210 Fracción XIII)''',
    'SELECT ''umbral_acumulacion_uma exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@col_exp = 0,
    'ALTER TABLE `cat_vulnerables` ADD COLUMN `umbral_expediente_uma` DECIMAL(12,2) DEFAULT NULL COMMENT ''UMA desde la cual se exige expediente (ej. 1605 Fracción XIII)''',
    'SELECT ''umbral_expediente_uma exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Valores para Fracción XIII (Donativos)
UPDATE `cat_vulnerables`
SET
    `umbral_aviso_uma`       = 3210.00,
    `umbral_acumulacion_uma` = 3210.00,
    `umbral_expediente_uma`  = 1605.00
WHERE `fraccion` = 'XIII';

-- Opcional: fallback en config_empresa si no existen columnas
SET @ce_aviso = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'umbral_aviso_uma'
);
SET @ce_acum = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'umbral_acumulacion_uma'
);

SET @sql = IF(@ce_aviso = 0,
    'ALTER TABLE `config_empresa` ADD COLUMN `umbral_aviso_uma` DECIMAL(12,2) DEFAULT NULL COMMENT ''UMA aviso (fallback global)''',
    'SELECT ''config umbral_aviso_uma exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@ce_acum = 0,
    'ALTER TABLE `config_empresa` ADD COLUMN `umbral_acumulacion_uma` DECIMAL(12,2) DEFAULT NULL COMMENT ''UMA acumulación (fallback global)''',
    'SELECT ''config umbral_acumulacion_uma exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Umbrales Fracción XIII aplicados' AS result;
