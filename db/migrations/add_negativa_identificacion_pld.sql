-- ============================================
-- Migration: VAL-PLD-026 — Negativa de Identificación del Cliente/Usuario
-- Si el cliente se niega a proporcionar información, no debe realizarse la operación.
-- ============================================

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'negativa_identificacion_pld'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `clientes`
     ADD COLUMN `negativa_identificacion_pld` TINYINT(1) DEFAULT 0 COMMENT ''1 = Cliente negó proporcionar información (VAL-PLD-026)'' AFTER `expediente_completo`,
     ADD COLUMN `fecha_negativa_identificacion_pld` DATE DEFAULT NULL COMMENT ''Fecha en que se registró la negativa'' AFTER `negativa_identificacion_pld`,
     ADD COLUMN `evidencia_negativa_identificacion_pld` TEXT DEFAULT NULL COMMENT ''Ruta o referencia a evidencia de solicitud'' AFTER `fecha_negativa_identificacion_pld`',
    'SELECT ''Column negativa_identificacion_pld already exists'' AS msg');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Negativa identificación PLD (VAL-PLD-026) aplicada' AS result;
