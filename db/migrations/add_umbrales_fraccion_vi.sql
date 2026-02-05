-- ============================================
-- Migration: Umbrales UMA para Fracción VI
-- Fuente: RCG PLD — Metales preciosos, piedras preciosas, joyas o relojes
-- ============================================
-- Expediente: monto ≥ 805 UMA
-- Aviso individual / acumulación: 1,605 UMA
-- Restricción efectivo (VAL-PLD-027): 3,210 UMA (solo en código pld_fraccion_vi.php)
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
    'ALTER TABLE `cat_vulnerables` ADD COLUMN `umbral_expediente_uma` DECIMAL(12,2) DEFAULT NULL COMMENT ''UMA desde la cual se exige expediente''',
    'SELECT ''umbral_expediente_uma exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Valores para Fracción VI (Metales, piedras preciosas, joyas, relojes)
-- Safe update mode: WHERE usa fraccion (no PK); se desactiva solo para este UPDATE
SET SQL_SAFE_UPDATES = 0;
UPDATE `cat_vulnerables`
SET
    `umbral_aviso_uma`       = 1605.00,
    `umbral_acumulacion_uma` = 1605.00,
    `umbral_expediente_uma`  = 805.00
WHERE `fraccion` = 'VI';
SET SQL_SAFE_UPDATES = 1;

SELECT 'Umbrales Fracción VI aplicados (expediente 805, aviso 1605 UMA)' AS result;
