-- Migration: Agregar campos para validación VAL-PLD-001
-- Fecha: 2025-01-21
-- Descripción: Campos para validar padrón PLD del sujeto obligado

-- Agregar columnas (si alguna ya existe, se ignorará con error, pero las demás se crearán)
ALTER TABLE `config_empresa` 
ADD COLUMN `estatus_patron_pld` VARCHAR(50) DEFAULT NULL COMMENT 'Estatus en el padrón PLD (vigente, baja, suspendido)' AFTER `id_vulnerable`,
ADD COLUMN `fecha_revalidacion_patron` DATE DEFAULT NULL COMMENT 'Fecha de última revalidación del padrón' AFTER `estatus_patron_pld`,
ADD COLUMN `folio_patron_pld` VARCHAR(100) DEFAULT NULL COMMENT 'Folio de registro en el padrón PLD del SAT' AFTER `fecha_revalidacion_patron`,
ADD COLUMN `no_habilitado_pld` TINYINT(1) DEFAULT 0 COMMENT 'Flag: 1 = NO habilitado, 0 = habilitado' AFTER `folio_patron_pld`,
ADD COLUMN `fracciones_activas` JSON DEFAULT NULL COMMENT 'Fracciones activas registradas en el padrón' AFTER `no_habilitado_pld`;

-- Índice para búsquedas rápidas
-- Nota: Si el índice ya existe, este comando fallará. 
-- Para verificar si existe: SHOW INDEX FROM config_empresa WHERE Key_name = 'idx_config_empresa_pld_habilitado';
-- Si ya existe, comentar o eliminar esta línea antes de ejecutar
CREATE INDEX `idx_config_empresa_pld_habilitado` ON `config_empresa`(`no_habilitado_pld`, `estatus_patron_pld`);
