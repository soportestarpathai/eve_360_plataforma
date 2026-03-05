-- Versión para MySQL 5.7 / MariaDB < 10.5.2 (sin ADD COLUMN IF NOT EXISTS)
-- Ejecutar SOLO SI add_config_patron_menu_usuario.sql falla por sintaxis.
-- Si alguna columna ya existe, omitir esa línea manualmente.

ALTER TABLE config_empresa_usuario ADD COLUMN folio_patron_pld VARCHAR(100) DEFAULT NULL;
ALTER TABLE config_empresa_usuario ADD COLUMN estatus_patron_pld VARCHAR(50) DEFAULT NULL;
ALTER TABLE config_empresa_usuario ADD COLUMN fecha_revalidacion_patron DATE DEFAULT NULL;
ALTER TABLE config_empresa_usuario ADD COLUMN fracciones_activas JSON DEFAULT NULL;
ALTER TABLE config_empresa_usuario ADD COLUMN no_habilitado_pld TINYINT(1) DEFAULT 0;

CREATE TABLE IF NOT EXISTS `menu_access_usuario` (
  `id_usuario` int NOT NULL,
  `id_menu_access` int NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int DEFAULT NULL,
  PRIMARY KEY (`id_usuario`, `id_menu_access`),
  KEY `fk_menu_usuario` (`id_usuario`),
  KEY `fk_menu_access` (`id_menu_access`),
  CONSTRAINT `fk_menu_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_menu_access` FOREIGN KEY (`id_menu_access`) REFERENCES `menu_access` (`id_menu_access`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
