-- ============================================
-- Migration: Soporte para múltiples usuarios admin
-- Permite gestionar varios administradores del panel admin/
-- ============================================

-- Crear tabla admin_users si no existe
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `temp_password_hash` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `id_status` tinyint(1) DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Migrar datos de admin_access (id=1) si admin_users está vacía
INSERT IGNORE INTO `admin_users` (`email`, `nombre`, `temp_password_hash`, `expires_at`, `id_status`)
SELECT a.email, COALESCE(a.email, 'Administrador'), a.temp_password_hash, a.expires_at, 1
FROM `admin_access` a
WHERE a.id = 1
  AND NOT EXISTS (SELECT 1 FROM admin_users LIMIT 1);

SELECT 'Migration add_admin_users_multiple completed' AS result;
