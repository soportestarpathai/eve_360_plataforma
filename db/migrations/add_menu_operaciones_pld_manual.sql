-- Migration Manual: Agregar "Operaciones PLD" al menú
-- Fecha: 2025-01-21
-- Descripción: Agrega el elemento de menú para Operaciones PLD (VAL-PLD-008)
-- 
-- INSTRUCCIONES:
-- 1. Ejecuta este script en tu base de datos
-- 2. O ejecuta los INSERT individuales según tu id_tipo_empresa
-- 3. Verifica que aparezca en el menú del dashboard

-- Opción 1: Insertar para tipo de empresa 1 (si es tu caso)
INSERT INTO menu_access (id_tipo_empresa, seccion, icon, file_path, id_parent) 
VALUES (1, 'Operaciones PLD', 'fa-file-invoice-dollar', 'operaciones_pld.php', 0)
ON DUPLICATE KEY UPDATE seccion = seccion;

-- Opción 2: Insertar para tipo de empresa 3 (si es tu caso)
INSERT INTO menu_access (id_tipo_empresa, seccion, icon, file_path, id_parent) 
VALUES (3, 'Operaciones PLD', 'fa-file-invoice-dollar', 'operaciones_pld.php', 0)
ON DUPLICATE KEY UPDATE seccion = seccion;

-- Opción 3: Insertar para TODOS los tipos de empresa que tienen menú
-- (Descomenta y ejecuta si quieres agregarlo a todos)
/*
INSERT INTO menu_access (id_tipo_empresa, seccion, icon, file_path, id_parent) 
SELECT DISTINCT id_tipo_empresa, 'Operaciones PLD', 'fa-file-invoice-dollar', 'operaciones_pld.php', 0
FROM menu_access
WHERE id_tipo_empresa NOT IN (
    SELECT id_tipo_empresa 
    FROM menu_access 
    WHERE seccion = 'Operaciones PLD' 
    AND file_path = 'operaciones_pld.php'
);
*/

-- Verificar inserción
SELECT * FROM menu_access WHERE seccion = 'Operaciones PLD' AND file_path = 'operaciones_pld.php';
