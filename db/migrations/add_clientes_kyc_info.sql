-- ============================================
-- Migration: Perfil KYC complementario por cliente
-- Fecha: 2026-02-25
-- ============================================

SET @table_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clientes_kyc_info'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `clientes_kyc_info` (
        `id_kyc_info` INT NOT NULL AUTO_INCREMENT,
        `id_cliente` INT NOT NULL,
        `id_actividad` INT DEFAULT NULL,
        `empleo_actual` VARCHAR(255) DEFAULT NULL,
        `antiguedad_anios` INT DEFAULT NULL,
        `id_origen_recursos` INT DEFAULT NULL,
        `tiene_familiar_pep` TINYINT DEFAULT 0,
        `nombre_familiar_pep` VARCHAR(255) DEFAULT NULL,
        `parentesco_familiar_pep` VARCHAR(100) DEFAULT NULL,
        `puesto_familiar_pep` VARCHAR(255) DEFAULT NULL,
        `fecha_ingreso_pep` DATE DEFAULT NULL,
        `id_ocupacion` INT DEFAULT NULL,
        `id_profesion` INT DEFAULT NULL,
        `nivel_estudios` VARCHAR(100) DEFAULT NULL,
        `fecha_alta` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `fecha_modificacion` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        `id_status` TINYINT DEFAULT 1,
        PRIMARY KEY (`id_kyc_info`),
        UNIQUE KEY `uk_cliente` (`id_cliente`),
        KEY `idx_actividad` (`id_actividad`),
        KEY `idx_origen` (`id_origen_recursos`),
        KEY `idx_ocupacion` (`id_ocupacion`),
        KEY `idx_profesion` (`id_profesion`),
        CONSTRAINT `fk_kyc_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
    'SELECT ''Table clientes_kyc_info already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed: clientes_kyc_info.' AS result;
