-- Script: Agregar subfracciones XI (SPR) a config_empresa
-- Fecha: 2025-02-05
-- Descripción: Columna JSON para subfracciones activas de Fracción XI (Servicios Profesionales)
-- Valores: array de tipo_actividad ej. ["compra_venta_inmuebles","administracion_personas_morales"]
-- Si vacío o NULL = todas las subfracciones activas

SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_xi'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE config_empresa ADD COLUMN subfracciones_xi JSON DEFAULT NULL COMMENT ''Subfracciones activas para XI (SPR): compra_venta_inmuebles, administracion_personas_morales, etc.''',
    'SELECT ''subfracciones_xi ya existe'' AS msg'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Opcional: también en config_empresa_usuario para override por usuario
SET @col_u = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa_usuario' AND COLUMN_NAME = 'subfracciones_xi'
);

SET @sql2 = IF(@col_u = 0,
    'ALTER TABLE config_empresa_usuario ADD COLUMN subfracciones_xi JSON DEFAULT NULL COMMENT ''Override subfracciones XI por usuario''',
    'SELECT ''subfracciones_xi usuario ya existe'' AS msg'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SELECT 'subfracciones_xi agregado' AS result;
