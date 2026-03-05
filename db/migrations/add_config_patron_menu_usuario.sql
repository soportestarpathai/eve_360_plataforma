-- Extender config_empresa_usuario con campos Padrón PLD
-- y crear menu_access_usuario para menú por usuario
-- Ejecutar después de add_config_per_usuario.sql
-- MySQL 8.0.12+ o MariaDB 10.5.2+ para ADD COLUMN IF NOT EXISTS

-- 1. Agregar columnas Padrón PLD a config_empresa_usuario (idempotente)
ALTER TABLE config_empresa_usuario ADD COLUMN IF NOT EXISTS folio_patron_pld VARCHAR(100) DEFAULT NULL;
ALTER TABLE config_empresa_usuario ADD COLUMN IF NOT EXISTS estatus_patron_pld VARCHAR(50) DEFAULT NULL;
ALTER TABLE config_empresa_usuario ADD COLUMN IF NOT EXISTS fecha_revalidacion_patron DATE DEFAULT NULL;
ALTER TABLE config_empresa_usuario ADD COLUMN IF NOT EXISTS fracciones_activas JSON DEFAULT NULL;
ALTER TABLE config_empresa_usuario ADD COLUMN IF NOT EXISTS no_habilitado_pld TINYINT(1) DEFAULT 0;

-- 2. Tabla menu_access_usuario: visibilidad de ítems de menú por usuario
CREATE TABLE IF NOT EXISTS `menu_access_usuario` (
  `id_usuario` int NOT NULL,
  `id_menu_access` int NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=visible, 0=oculto',
  `orden` int DEFAULT NULL COMMENT 'Orden opcional',
  PRIMARY KEY (`id_usuario`, `id_menu_access`),
  KEY `fk_menu_usuario` (`id_usuario`),
  KEY `fk_menu_access` (`id_menu_access`),
  CONSTRAINT `fk_menu_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_menu_access` FOREIGN KEY (`id_menu_access`) REFERENCES `menu_access` (`id_menu_access`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
