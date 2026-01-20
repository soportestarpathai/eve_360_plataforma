-- ============================================
-- ÍNDICES SUGERIDOS PARA OPTIMIZACIÓN
-- ============================================
-- Ejecutar estos índices para mejorar el rendimiento de las consultas
-- en index.php y otras partes del sistema
-- 
-- IMPORTANTE: Revisar el impacto antes de crear índices en producción
-- ============================================

-- 1. Indicadores: Optimizar búsqueda de UMA por nombre y fecha
-- Uso: SELECT valor, fecha FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1
CREATE INDEX IF NOT EXISTS idx_indicadores_nombre_fecha 
ON indicadores(nombre, fecha DESC);

-- 2. Config Empresa: Ya debería tener PRIMARY KEY, pero asegurar índice
-- Uso: SELECT * FROM config_empresa WHERE id_config = 1
-- (Normalmente ya tiene PRIMARY KEY, pero verificarlo)
ALTER TABLE config_empresa ADD PRIMARY KEY IF NOT EXISTS (id_config);

-- 3. Cat Vulnerables: Optimizar búsqueda por ID
-- Uso: SELECT fraccion FROM cat_vulnerables WHERE id_vulnerable = ?
CREATE INDEX IF NOT EXISTS idx_vulnerables_id 
ON cat_vulnerables(id_vulnerable);

-- 4. Clientes: Optimizar búsqueda por estado y nivel de riesgo
-- Uso: SELECT nivel_riesgo FROM clientes WHERE id_status = 1
CREATE INDEX IF NOT EXISTS idx_clientes_status_riesgo 
ON clientes(id_status, nivel_riesgo);

-- 5. Config Riesgo Rangos: Optimizar ordenamiento por min_valor
-- Uso: SELECT * FROM config_riesgo_rangos ORDER BY min_valor ASC
CREATE INDEX IF NOT EXISTS idx_riesgo_min_max 
ON config_riesgo_rangos(min_valor, max_valor);

-- 6. Menu Access: Optimizar búsqueda por tipo de empresa y parent
-- Uso: SELECT * FROM menu_access WHERE id_tipo_empresa = ? ORDER BY id_menu_access ASC
CREATE INDEX IF NOT EXISTS idx_menu_tipo_parent 
ON menu_access(id_tipo_empresa, id_parent, id_menu_access);

-- 7. Notificaciones: Optimizar búsqueda por usuario, estado y snooze
-- Uso: SELECT n.* FROM notificaciones n WHERE n.id_usuario = ? AND n.estado != 'descartado' AND (n.snooze_until IS NULL OR n.snooze_until <= NOW())
CREATE INDEX IF NOT EXISTS idx_notificaciones_usuario_estado 
ON notificaciones(id_usuario, estado, snooze_until);

-- 8. Usuarios: Optimizar búsqueda por login y estado
-- Uso: SELECT id_usuario, nombre, login_password FROM usuarios WHERE login_user = ? AND id_status_usuario = 1
CREATE INDEX IF NOT EXISTS idx_usuarios_login_status 
ON usuarios(login_user, id_status_usuario);

-- 9. Usuarios Permisos: Optimizar JOIN con usuarios
-- Uso: SELECT u.nombre, p.* FROM usuarios u LEFT JOIN usuarios_permisos p ON u.id_usuario = p.id_usuario WHERE u.id_usuario = ?
CREATE INDEX IF NOT EXISTS idx_usuarios_permisos_usuario 
ON usuarios_permisos(id_usuario);

-- 10. Clientes Físicas/Morales: Optimizar JOIN con clientes
-- Uso: LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
--      LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
CREATE INDEX IF NOT EXISTS idx_clientes_fisicas_cliente 
ON clientes_fisicas(id_cliente);

CREATE INDEX IF NOT EXISTS idx_clientes_morales_cliente 
ON clientes_morales(id_cliente);

-- ============================================
-- VERIFICAR ÍNDICES EXISTENTES
-- ============================================
-- Para verificar los índices creados en una tabla:
-- SHOW INDEX FROM nombre_tabla;
--
-- Para ver todas las tablas y sus índices:
-- SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME 
-- FROM INFORMATION_SCHEMA.STATISTICS 
-- WHERE TABLE_SCHEMA = 'investor'
-- ORDER BY TABLE_NAME, INDEX_NAME;
-- ============================================
