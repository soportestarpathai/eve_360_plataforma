-- ============================================
-- Migration: Umbrales UMA para Fracción V Bis
-- Fuente: RCG PLD — Recepción de Recursos para Desarrollo Inmobiliario
-- ============================================
-- Aviso individual / acumulación: 8,025 UMA
-- Expediente: siempre obligatorio (umbral_expediente_uma = 0)
-- ============================================

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
    'ALTER TABLE `cat_vulnerables` ADD COLUMN `umbral_aviso_uma` DECIMAL(12,2) DEFAULT NULL COMMENT ''UMA aviso individual''',
    'SELECT ''umbral_aviso_uma exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@col_acum = 0,
    'ALTER TABLE `cat_vulnerables` ADD COLUMN `umbral_acumulacion_uma` DECIMAL(12,2) DEFAULT NULL COMMENT ''UMA acumulación 6 meses''',
    'SELECT ''umbral_acumulacion_uma exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@col_exp = 0,
    'ALTER TABLE `cat_vulnerables` ADD COLUMN `umbral_expediente_uma` DECIMAL(12,2) DEFAULT NULL COMMENT ''0 = siempre obligatorio''',
    'SELECT ''umbral_expediente_uma exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Valores para Fracción V Bis (Recepción de recursos para desarrollo inmobiliario)
-- Safe update mode: WHERE usa fraccion (no PK); se desactiva solo para este UPDATE
SET SQL_SAFE_UPDATES = 0;
UPDATE `cat_vulnerables`
SET
    `umbral_aviso_uma`       = 8025.00,
    `umbral_acumulacion_uma` = 8025.00,
    `umbral_expediente_uma`  = 0.00
WHERE `fraccion` = 'V Bis';
SET SQL_SAFE_UPDATES = 1;

SELECT 'Umbrales Fracción V Bis aplicados (8,025 UMA)' AS result;
