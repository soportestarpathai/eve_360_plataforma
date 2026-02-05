-- ============================================
-- Migration: Umbrales UMA para Fracción V (Inmobiliario)
-- Fuente: RCG PLD, Fracción V — Desarrollo · Comercialización · Intermediación de Inmuebles
-- ============================================
-- Aviso individual / acumulación: 8,025 UMA
-- Expediente: siempre obligatorio (umbral_expediente_uma = 0 indica "siempre")
-- Restricción efectivo: operaciones ≥ 8,025 UMA
-- ============================================

-- Asegurar columnas en cat_vulnerables (por si no se ejecutó add_umbrales_fraccion_xiii.sql)
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

-- Valores para Fracción V (Inmobiliario): 8,025 UMA aviso/acumulación; 0 = expediente siempre
UPDATE `cat_vulnerables`
SET
    `umbral_aviso_uma`       = 8025.00,
    `umbral_acumulacion_uma` = 8025.00,
    `umbral_expediente_uma`  = 0.00
WHERE `fraccion` = 'V';

SELECT 'Umbrales Fracción V aplicados (8,025 UMA)' AS result;
