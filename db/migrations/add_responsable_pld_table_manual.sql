-- Migration: Agregar tabla para Responsable PLD - VAL-PLD-003 (Versión Manual)
-- Fecha: 2025-01-21
-- Descripción: Script para ejecutar manualmente paso a paso
-- NOTA: Ejecutar solo los comandos que correspondan según lo que ya existe

-- ============================================
-- PASO 1: Verificar si la tabla existe
-- ============================================
-- SELECT COUNT(*) 
-- FROM INFORMATION_SCHEMA.TABLES 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'clientes_responsable_pld';

-- Si no existe (COUNT = 0), ejecutar:
CREATE TABLE `clientes_responsable_pld` (
  `id_responsable_pld` INT NOT NULL AUTO_INCREMENT,
  `id_cliente` INT NOT NULL COMMENT 'ID del cliente (persona moral o fideicomiso)',
  `id_usuario_responsable` INT NOT NULL COMMENT 'ID del usuario designado como responsable PLD',
  `fecha_designacion` DATE NOT NULL DEFAULT (CURDATE()) COMMENT 'Fecha de designación',
  `fecha_baja` DATE DEFAULT NULL COMMENT 'Fecha de baja/remoción del responsable',
  `activo` TINYINT(1) DEFAULT 1 COMMENT '1 = activo, 0 = inactivo',
  `observaciones` TEXT DEFAULT NULL COMMENT 'Observaciones sobre la designación',
  `fecha_modificacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_responsable_pld`),
  KEY `idx_cliente_activo` (`id_cliente`, `activo`),
  KEY `idx_usuario_responsable` (`id_usuario_responsable`),
  CONSTRAINT `fk_responsable_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE CASCADE,
  CONSTRAINT `fk_responsable_usuario` FOREIGN KEY (`id_usuario_responsable`) REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
COMMENT='Tabla para almacenar responsables PLD designados por cliente (personas morales y fideicomisos)';

-- ============================================
-- PASO 2: Verificar si la columna existe
-- ============================================
-- SELECT COUNT(*) 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'clientes' 
-- AND COLUMN_NAME = 'restriccion_usuario';

-- Si no existe (COUNT = 0), ejecutar:
ALTER TABLE `clientes` 
ADD COLUMN `restriccion_usuario` TINYINT(1) DEFAULT 0 COMMENT 'Flag: 1 = RESTRICCION_USUARIO (falta responsable PLD), 0 = sin restricción' AFTER `nivel_riesgo`;

-- ============================================
-- PASO 3: Verificar si el índice existe
-- ============================================
-- SELECT COUNT(*) 
-- FROM INFORMATION_SCHEMA.STATISTICS 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'clientes' 
-- AND INDEX_NAME = 'idx_clientes_restriccion_usuario';

-- Si no existe (COUNT = 0), ejecutar:
CREATE INDEX `idx_clientes_restriccion_usuario` ON `clientes`(`restriccion_usuario`, `id_tipo_persona`);
