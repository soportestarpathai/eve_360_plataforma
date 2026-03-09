-- Script: subfracciones_xi en usuarios_permisos (por usuario)
-- Cuando un usuario tiene Fracción XI, puede tener subfracciones específicas asignadas

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'subfracciones_xi');

SET @sql = IF(@col = 0,
    'ALTER TABLE usuarios_permisos ADD COLUMN subfracciones_xi JSON DEFAULT NULL COMMENT ''Subfracciones XI asignadas al usuario''',
    'SELECT ''subfracciones_xi ya existe en usuarios_permisos'' AS msg');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
