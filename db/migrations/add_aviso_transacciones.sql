-- ============================================
-- Migration: Tabla puente aviso_transacciones
-- Fecha: 2026-02-25
-- ============================================

SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'aviso_transacciones'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `aviso_transacciones` (
        `id_aviso_transaccion` INT NOT NULL AUTO_INCREMENT,
        `id_aviso` INT NOT NULL,
        `id_operacion` INT NOT NULL,
        `id_cliente` INT DEFAULT NULL,
        `tipo_relacion` ENUM(''operacion'', ''acumulacion'') DEFAULT ''operacion'',
        `fecha_alta` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `id_status` TINYINT DEFAULT 1,
        PRIMARY KEY (`id_aviso_transaccion`),
        UNIQUE KEY `uk_aviso_operacion` (`id_aviso`, `id_operacion`),
        KEY `idx_operacion` (`id_operacion`),
        KEY `idx_cliente` (`id_cliente`),
        KEY `idx_tipo_relacion` (`tipo_relacion`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
    'SELECT ''Table aviso_transacciones already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed: aviso_transacciones.' AS result;
