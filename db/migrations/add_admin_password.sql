-- Añadir columna password_hash para acceso por contraseña (además del código temporal)
-- Ejecutar solo si las columnas no existen. Si existe, ignorar el error.

ALTER TABLE `admin_users` ADD COLUMN `password_hash` varchar(255) DEFAULT NULL AFTER `temp_password_hash`;
ALTER TABLE `admin_access` ADD COLUMN `password_hash` varchar(255) DEFAULT NULL AFTER `temp_password_hash`;
