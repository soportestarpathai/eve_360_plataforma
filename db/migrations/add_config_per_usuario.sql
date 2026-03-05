-- Configuración por usuario: logo, color, nombre, módulos y reportes individuales
-- Ejecutar después de add_admin_password si aplica

-- 1. config_empresa_usuario: configuración visual y límites por usuario
CREATE TABLE IF NOT EXISTS `config_empresa_usuario` (
  `id_usuario` int NOT NULL,
  `nombre_empresa` varchar(255) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `color_primario` varchar(7) DEFAULT NULL,
  `max_usuarios` int DEFAULT NULL,
  `max_busquedas_api` int DEFAULT NULL,
  `id_tipo_empresa` int DEFAULT NULL,
  `id_vulnerable` int DEFAULT NULL,
  `contrato_prefijo` varchar(20) DEFAULT NULL,
  `contrato_siguiente` int DEFAULT NULL,
  `contrato_longitud` int DEFAULT NULL,
  `contrato_rellenar_ceros` tinyint(1) DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`),
  KEY `fk_config_empresa_usuario` (`id_usuario`),
  CONSTRAINT `fk_config_empresa_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. config_modulos_usuario: módulos activos/inactivos por usuario
CREATE TABLE IF NOT EXISTS `config_modulos_usuario` (
  `id_usuario` int NOT NULL,
  `id_modulo` int NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_usuario`, `id_modulo`),
  KEY `fk_mod_usuario` (`id_usuario`),
  KEY `fk_mod_modulo` (`id_modulo`),
  CONSTRAINT `fk_mod_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_mod_modulo` FOREIGN KEY (`id_modulo`) REFERENCES `config_modulos` (`id_modulo`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3. reportes_usuario: reportes disponibles por usuario (1=visible, 0=oculto)
CREATE TABLE IF NOT EXISTS `reportes_usuario` (
  `id_usuario` int NOT NULL,
  `id_tipo_reporte` int NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_usuario`, `id_tipo_reporte`),
  KEY `fk_rep_usuario` (`id_usuario`),
  KEY `fk_rep_tipo` (`id_tipo_reporte`),
  CONSTRAINT `fk_rep_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `fk_rep_tipo` FOREIGN KEY (`id_tipo_reporte`) REFERENCES `cat_tipos_reporte` (`id_tipo_reporte`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
