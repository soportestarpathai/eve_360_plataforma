-- Umbrales Fracción II (TSC) según RCG
-- Identificación: 805 UMA | Aviso: 1,285 UMA

SET @chk = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cat_vulnerables' AND COLUMN_NAME = 'umbral_aviso_uma');
SET @sql = IF(@chk = 0, 
    'ALTER TABLE `cat_vulnerables` ADD COLUMN `umbral_aviso_uma` DECIMAL(12,2) DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @chk2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cat_vulnerables' AND COLUMN_NAME = 'umbral_acumulacion_uma');
SET @sql2 = IF(@chk2 = 0, 
    'ALTER TABLE `cat_vulnerables` ADD COLUMN `umbral_acumulacion_uma` DECIMAL(12,2) DEFAULT NULL',
    'SELECT 1');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

UPDATE `cat_vulnerables`
SET `umbral_aviso_uma` = 1285.00,
    `umbral_acumulacion_uma` = 1285.00
WHERE `fraccion` = 'II';

SELECT 'Umbrales Fracción II actualizados' AS result;
