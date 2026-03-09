-- Configuración EBR por usuario (cada usuario que registra clientes tiene su propia config)
-- Las tablas globales config_riesgo_rangos y config_riesgo_valores se conservan como plantilla/fallback

-- 1. config_riesgo_rangos_usuario
CREATE TABLE IF NOT EXISTS `config_riesgo_rangos_usuario` (
  `id_usuario` int NOT NULL,
  `nivel` varchar(50) NOT NULL,
  `min_valor` decimal(5,2) NOT NULL,
  `max_valor` decimal(5,2) NOT NULL,
  `color_hex` varchar(10) DEFAULT '#000000',
  PRIMARY KEY (`id_usuario`, `nivel`),
  KEY `fk_ebr_rangos_usuario` (`id_usuario`),
  CONSTRAINT `fk_ebr_rangos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 2. config_riesgo_valores_usuario
CREATE TABLE IF NOT EXISTS `config_riesgo_valores_usuario` (
  `id_usuario` int NOT NULL,
  `id_factor` int NOT NULL,
  `id_valor_catalogo` int NOT NULL,
  `nivel_riesgo` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id_usuario`, `id_factor`, `id_valor_catalogo`),
  KEY `fk_ebr_valores_usuario` (`id_usuario`),
  KEY `idx_ebr_valores_factor` (`id_factor`, `id_valor_catalogo`),
  CONSTRAINT `fk_ebr_valores_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- 3. Copiar datos globales a usuarios que tienen clientes (seed inicial)
INSERT IGNORE INTO config_riesgo_rangos_usuario (id_usuario, nivel, min_valor, max_valor, color_hex)
SELECT DISTINCT c.id_usuario, r.nivel, r.min_valor, r.max_valor, r.color_hex
FROM clientes c
CROSS JOIN config_riesgo_rangos r
WHERE c.id_usuario IS NOT NULL AND c.id_usuario > 0;

INSERT IGNORE INTO config_riesgo_valores_usuario (id_usuario, id_factor, id_valor_catalogo, nivel_riesgo)
SELECT DISTINCT c.id_usuario, v.id_factor, v.id_valor_catalogo, v.nivel_riesgo
FROM clientes c
CROSS JOIN config_riesgo_valores v
WHERE c.id_usuario IS NOT NULL AND c.id_usuario > 0;
