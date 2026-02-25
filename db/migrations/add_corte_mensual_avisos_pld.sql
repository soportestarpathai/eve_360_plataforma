-- ============================================
-- Migration: Corte mensual interno de avisos PLD
-- Fecha: 2026-02-25
-- ============================================

SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cortes_mensuales_pld'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `cortes_mensuales_pld` (
        `id_corte` INT NOT NULL AUTO_INCREMENT,
        `periodo_mes` TINYINT NOT NULL,
        `periodo_anio` SMALLINT NOT NULL,
        `fecha_corte` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `fecha_limite_envio` DATE DEFAULT NULL,
        `estatus` ENUM(''borrador'', ''listo_para_presentar'', ''presentado'') DEFAULT ''listo_para_presentar'',
        `total_avisos` INT DEFAULT 0,
        `total_xml_generados` INT DEFAULT 0,
        `total_xml_pendientes` INT DEFAULT 0,
        `ruta_zip` VARCHAR(255) DEFAULT NULL,
        `observaciones` TEXT,
        `id_usuario_genero` INT DEFAULT NULL,
        `id_status` TINYINT DEFAULT 1,
        PRIMARY KEY (`id_corte`),
        UNIQUE KEY `uk_corte_periodo_activo` (`periodo_mes`, `periodo_anio`, `id_status`),
        KEY `idx_periodo` (`periodo_anio`, `periodo_mes`),
        KEY `idx_estatus` (`estatus`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
    'SELECT ''Table cortes_mensuales_pld already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'corte_mensual_avisos_pld_detalle'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `corte_mensual_avisos_pld_detalle` (
        `id_detalle` INT NOT NULL AUTO_INCREMENT,
        `id_corte` INT NOT NULL,
        `id_aviso` INT NOT NULL,
        `id_operacion` INT DEFAULT NULL,
        `id_cliente` INT NOT NULL,
        `tipo_aviso` VARCHAR(50) NOT NULL,
        `monto` DECIMAL(15,2) DEFAULT NULL,
        `fecha_operacion` DATE DEFAULT NULL,
        `fecha_deadline` DATE DEFAULT NULL,
        `xml_generado` TINYINT DEFAULT 0,
        `xml_nombre_archivo` VARCHAR(255) DEFAULT NULL,
        `id_status` TINYINT DEFAULT 1,
        `fecha_alta` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_detalle`),
        UNIQUE KEY `uk_corte_aviso_operacion` (`id_corte`, `id_aviso`, `id_operacion`),
        KEY `idx_corte` (`id_corte`),
        KEY `idx_aviso` (`id_aviso`),
        KEY `idx_operacion` (`id_operacion`),
        KEY `idx_cliente` (`id_cliente`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
    'SELECT ''Table corte_mensual_avisos_pld_detalle already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed: cortes_mensuales_pld + corte_mensual_avisos_pld_detalle.' AS result;
