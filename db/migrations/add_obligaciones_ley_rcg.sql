-- ============================================
-- Migration: Obligaciones Ley / Reglamento / RCG (documento EVE360)
-- - Art. 20 LFPIORPI y RCG Art. 10: Capacitación anual del REC
-- - Art. 17 y 12 RCG: Clasificación de bajo riesgo (expediente simplificado)
-- - Art. 12 RCG: Cotejo de copias contra originales
-- ============================================

-- 1) Capacitación anual del Representante Encargado del Cumplimiento (REC)
SET @col_cap = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes_responsable_pld' AND COLUMN_NAME = 'fecha_ultima_capacitacion'
);
SET @sql = IF(@col_cap = 0,
    'ALTER TABLE `clientes_responsable_pld`
     ADD COLUMN `fecha_ultima_capacitacion` DATE DEFAULT NULL COMMENT ''Última capacitación anual REC (Art. 20 Ley, RCG Art. 10)'' AFTER `observaciones`,
     ADD COLUMN `vigencia_capacitacion` DATE DEFAULT NULL COMMENT ''Vigencia de la capacitación (ej. +1 año)'' AFTER `fecha_ultima_capacitacion`',
    'SELECT ''Column fecha_ultima_capacitacion already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Clasificación de bajo riesgo (Art. 17 y 12 RCG; expediente simplificado)
SET @col_br = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'clasificacion_bajo_riesgo'
);
SET @sql = IF(@col_br = 0,
    'ALTER TABLE `clientes`
     ADD COLUMN `clasificacion_bajo_riesgo` TINYINT(1) DEFAULT 0 COMMENT ''1 = Cliente clasificado como de bajo riesgo (Art. 17 RCG); criterios en doc Art. 37'' AFTER `nivel_riesgo`,
     ADD COLUMN `fecha_clasificacion_bajo_riesgo` DATE DEFAULT NULL COMMENT ''Fecha de la clasificación'' AFTER `clasificacion_bajo_riesgo`',
    'SELECT ''Column clasificacion_bajo_riesgo already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Cotejo de copias contra originales (Art. 12 RCG)
SET @col_cot = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes_documentos' AND COLUMN_NAME = 'cotejado_contra_original'
);
SET @sql = IF(@col_cot = 0,
    'ALTER TABLE `clientes_documentos`
     ADD COLUMN `cotejado_contra_original` TINYINT(1) DEFAULT 0 COMMENT ''1 = Copia cotejada contra original o certificada (Art. 12 RCG)'' AFTER `id_status`,
     ADD COLUMN `fecha_cotejo` DATE DEFAULT NULL COMMENT ''Fecha del cotejo'' AFTER `cotejado_contra_original`',
    'SELECT ''Column cotejado_contra_original already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Obligaciones Ley/Reglamento/RCG aplicadas (capacitación REC, bajo riesgo, cotejo)' AS result;
