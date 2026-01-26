-- Migration: Agregar campos para validación VAL-PLD-001 (Versión Manual)
-- Fecha: 2025-01-21
-- Descripción: Campos para validar padrón PLD del sujeto obligado
-- Ejecutar solo las columnas que faltan

-- Verificar qué columnas existen:
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' 
-- AND COLUMN_NAME IN ('estatus_patron_pld', 'fecha_revalidacion_patron', 'folio_patron_pld', 'no_habilitado_pld', 'fracciones_activas');

-- Si falta alguna, ejecutar solo esa:

-- 1. estatus_patron_pld (si no existe)
-- ALTER TABLE `config_empresa` 
-- ADD COLUMN `estatus_patron_pld` VARCHAR(50) DEFAULT NULL COMMENT 'Estatus en el padrón PLD (vigente, baja, suspendido)' AFTER `id_vulnerable`;

-- 2. fecha_revalidacion_patron (si no existe)
-- ALTER TABLE `config_empresa` 
-- ADD COLUMN `fecha_revalidacion_patron` DATE DEFAULT NULL COMMENT 'Fecha de última revalidación del padrón' AFTER `estatus_patron_pld`;

-- 3. folio_patron_pld (si no existe)
-- ALTER TABLE `config_empresa` 
-- ADD COLUMN `folio_patron_pld` VARCHAR(100) DEFAULT NULL COMMENT 'Folio de registro en el padrón PLD del SAT' AFTER `fecha_revalidacion_patron`;

-- 4. no_habilitado_pld (si no existe)
-- ALTER TABLE `config_empresa` 
-- ADD COLUMN `no_habilitado_pld` TINYINT(1) DEFAULT 0 COMMENT 'Flag: 1 = NO habilitado, 0 = habilitado' AFTER `folio_patron_pld`;

-- 5. fracciones_activas (si no existe)
-- ALTER TABLE `config_empresa` 
-- ADD COLUMN `fracciones_activas` JSON DEFAULT NULL COMMENT 'Fracciones activas registradas en el padrón' AFTER `no_habilitado_pld`;

-- Verificar si el índice existe:
-- SHOW INDEX FROM config_empresa WHERE Key_name = 'idx_config_empresa_pld_habilitado';

-- Si el índice no existe, crear:
-- CREATE INDEX `idx_config_empresa_pld_habilitado` ON `config_empresa`(`no_habilitado_pld`, `estatus_patron_pld`);
