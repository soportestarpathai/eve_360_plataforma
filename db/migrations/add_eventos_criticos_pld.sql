-- ============================================
-- Migration: Eventos Críticos PLD (VAL-PLD-014)
-- Cuando expedientes/evidencia no están disponibles en visita de verificación
-- ============================================

SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'eventos_criticos_pld'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `eventos_criticos_pld` (
        `id_evento` INT NOT NULL AUTO_INCREMENT,
        `id_visita` INT DEFAULT NULL COMMENT ''Visita de verificación que generó el evento'',
        `tipo` VARCHAR(64) NOT NULL DEFAULT ''expediente_no_disponible'' COMMENT ''expediente_no_disponible, evidencia_faltante, etc.'',
        `descripcion` TEXT,
        `id_cliente` INT DEFAULT NULL,
        `detalle_json` JSON DEFAULT NULL COMMENT ''Detalle de expedientes no disponibles'',
        `fecha_evento` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `id_usuario_registro` INT DEFAULT NULL,
        `id_status` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`id_evento`),
        INDEX `idx_visita` (`id_visita`),
        INDEX `idx_fecha` (`fecha_evento`),
        INDEX `idx_tipo` (`tipo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT=''Eventos críticos PLD - No disponible (VAL-PLD-014)''',
    'SELECT ''Table eventos_criticos_pld already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
