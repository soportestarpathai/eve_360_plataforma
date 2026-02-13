-- ============================================
-- Migration: Mejoras Aviso PLD
-- Reglas plazo, notificaciones, bitácora deshacer, histórico
-- ============================================

-- 1) Notificaciones: agregar id_aviso e id_operacion para vincular alertas PLD
SELECT COUNT(*) INTO @col_aviso FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificaciones' AND COLUMN_NAME = 'id_aviso';

SET @sql1 = IF(@col_aviso = 0,
    'ALTER TABLE `notificaciones`
     ADD COLUMN `id_aviso` INT DEFAULT NULL COMMENT ''Aviso PLD relacionado'' AFTER `id_cliente`,
     ADD COLUMN `id_operacion` INT DEFAULT NULL COMMENT ''Operación PLD relacionada'' AFTER `id_aviso`,
     ADD KEY `idx_notif_aviso` (`id_aviso`),
     ADD KEY `idx_notif_operacion` (`id_operacion`)',
    'SELECT ''Column id_aviso already exists'' AS msg');

PREPARE stmt FROM @sql1;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Bitácora: agregar deshacer_aplicado para evitar deshacer dos veces
SELECT COUNT(*) INTO @col_deshacer FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bitacora' AND COLUMN_NAME = 'deshacer_aplicado';

SET @sql2 = IF(@col_deshacer = 0,
    'ALTER TABLE `bitacora`
     ADD COLUMN `deshacer_aplicado` TINYINT(1) DEFAULT 0 COMMENT ''1=ya se aplicó deshacer'' AFTER `valor_nuevo`',
    'SELECT ''Column deshacer_aplicado already exists'' AS msg');

PREPARE stmt FROM @sql2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Tabla solicitudes_permiso_pld (para flujo de aprobación cuando usuario no es admin/responsable)
CREATE TABLE IF NOT EXISTS `solicitudes_permiso_pld` (
  `id_solicitud` INT NOT NULL AUTO_INCREMENT,
  `id_usuario_solicitante` INT NOT NULL,
  `tipo_accion` ENUM('actualizar_aviso','baja_operacion','baja_aviso','modificar_operacion') NOT NULL,
  `tabla_afectada` VARCHAR(50) NOT NULL,
  `id_registro` INT NOT NULL,
  `datos_solicitados` JSON DEFAULT NULL COMMENT 'Datos a aplicar si se aprueba',
  `estado` ENUM('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `id_usuario_aprobador` INT DEFAULT NULL,
  `fecha_solicitud` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` DATETIME DEFAULT NULL,
  `observaciones` TEXT,
  PRIMARY KEY (`id_solicitud`),
  KEY `idx_solicitud_estado` (`estado`),
  KEY `idx_solicitud_usuario` (`id_usuario_solicitante`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Solicitudes de permiso para modificar/eliminar en PLD';

-- 4) usuarios_permisos: agregar permiso_pld_modificacion (opcional, si no existe)
SELECT COUNT(*) INTO @col_pld FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'permiso_pld_modificacion';

SET @sql3 = IF(@col_pld = 0,
    'ALTER TABLE `usuarios_permisos`
     ADD COLUMN `permiso_pld_modificacion` TINYINT(1) DEFAULT 0 COMMENT ''1=Puede modificar/eliminar avisos y operaciones PLD'' AFTER `administracion`',
    'SELECT ''Column permiso_pld_modificacion already exists'' AS msg');

PREPARE stmt FROM @sql3;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration aviso_pld_mejoras completed' AS result;
