-- Migration: Agregar tabla para Responsable PLD - VAL-PLD-003
-- Fecha: 2025-01-21
-- Descripción: Tabla para almacenar la designación de Responsable PLD para personas morales y fideicomisos
-- NOTA: Este script verifica la existencia antes de crear para evitar errores

-- 1. Verificar y crear tabla clientes_responsable_pld
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'clientes_responsable_pld'
);

SET @sql_create_table = IF(@table_exists = 0,
    'CREATE TABLE `clientes_responsable_pld` (
      `id_responsable_pld` INT NOT NULL AUTO_INCREMENT,
      `id_cliente` INT NOT NULL COMMENT ''ID del cliente (persona moral o fideicomiso)'',
      `id_usuario_responsable` INT NOT NULL COMMENT ''ID del usuario designado como responsable PLD'',
      `fecha_designacion` DATE NOT NULL DEFAULT (CURDATE()) COMMENT ''Fecha de designación'',
      `fecha_baja` DATE DEFAULT NULL COMMENT ''Fecha de baja/remoción del responsable'',
      `activo` TINYINT(1) DEFAULT 1 COMMENT ''1 = activo, 0 = inactivo'',
      `observaciones` TEXT DEFAULT NULL COMMENT ''Observaciones sobre la designación'',
      `fecha_modificacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id_responsable_pld`),
      KEY `idx_cliente_activo` (`id_cliente`, `activo`),
      KEY `idx_usuario_responsable` (`id_usuario_responsable`),
      CONSTRAINT `fk_responsable_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE CASCADE,
      CONSTRAINT `fk_responsable_usuario` FOREIGN KEY (`id_usuario_responsable`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
    COMMENT=''Tabla para almacenar responsables PLD designados por cliente (personas morales y fideicomisos)''',
    'SELECT ''Tabla clientes_responsable_pld ya existe'' AS message'
);

PREPARE stmt FROM @sql_create_table;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Verificar y agregar campo restriccion_usuario a la tabla clientes
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'clientes' 
    AND COLUMN_NAME = 'restriccion_usuario'
);

SET @sql_add_column = IF(@column_exists = 0,
    'ALTER TABLE `clientes` 
     ADD COLUMN `restriccion_usuario` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = RESTRICCION_USUARIO (falta responsable PLD), 0 = sin restricción'' AFTER `nivel_riesgo`',
    'SELECT ''Columna restriccion_usuario ya existe en clientes'' AS message'
);

PREPARE stmt FROM @sql_add_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Verificar y crear índice idx_clientes_restriccion_usuario
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'clientes' 
    AND INDEX_NAME = 'idx_clientes_restriccion_usuario'
);

SET @sql_create_index = IF(@index_exists = 0,
    'CREATE INDEX `idx_clientes_restriccion_usuario` ON `clientes`(`restriccion_usuario`, `id_tipo_persona`)',
    'SELECT ''Índice idx_clientes_restriccion_usuario ya existe'' AS message'
);

PREPARE stmt FROM @sql_create_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
