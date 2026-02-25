-- ============================================
-- Migration: Catálogo SEPOMEX + geolocalización en direcciones
-- Fecha: 2026-02-24
-- ============================================

-- 1) Crear tabla cat_sepomex si no existe
SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cat_sepomex'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `cat_sepomex` (
        `id_sepomex` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `codigo_postal` CHAR(5) NOT NULL,
        `estado` VARCHAR(120) NOT NULL,
        `municipio` VARCHAR(120) NOT NULL,
        `colonia` VARCHAR(180) NOT NULL,
        `tipo_asentamiento` VARCHAR(80) DEFAULT NULL,
        `ciudad` VARCHAR(120) DEFAULT NULL,
        `zona` VARCHAR(40) DEFAULT NULL,
        `c_estado` VARCHAR(10) DEFAULT NULL,
        `c_mnpio` VARCHAR(10) DEFAULT NULL,
        `c_oficina` VARCHAR(10) DEFAULT NULL,
        `fecha_alta` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_sepomex`),
        UNIQUE KEY `uk_cp_estado_municipio_colonia` (`codigo_postal`, `estado`, `municipio`, `colonia`),
        KEY `idx_cp` (`codigo_postal`),
        KEY `idx_estado_municipio` (`estado`, `municipio`),
        KEY `idx_estado_municipio_cp` (`estado`, `municipio`, `codigo_postal`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
    'SELECT ''Table cat_sepomex already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Agregar latitud/longitud a clientes_direcciones (si faltan)
SET @col_lat = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes_direcciones'
      AND COLUMN_NAME = 'latitud'
);

SET @sql = IF(@col_lat = 0,
    'ALTER TABLE `clientes_direcciones` ADD COLUMN `latitud` DECIMAL(10,7) NULL AFTER `codigo_postal`',
    'SELECT ''Column latitud already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_lng = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes_direcciones'
      AND COLUMN_NAME = 'longitud'
);

SET @sql = IF(@col_lng = 0,
    'ALTER TABLE `clientes_direcciones` ADD COLUMN `longitud` DECIMAL(10,7) NULL AFTER `latitud`',
    'SELECT ''Column longitud already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed: cat_sepomex + geolocalización.' AS result;
