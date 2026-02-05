-- Script para verificar qué tablas tienen id_status y cuáles no
-- Ejecuta este script para ver el estado de todas las tablas

-- 1. clientes_identificaciones
SELECT 'clientes_identificaciones' as tabla,
       CASE WHEN COUNT(*) > 0 THEN 'SÍ tiene id_status' ELSE 'NO tiene id_status' END as tiene_id_status,
       GROUP_CONCAT(COLUMN_NAME) as columnas
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes_identificaciones'
  AND COLUMN_NAME = 'id_status'

UNION ALL

-- 2. clientes_direcciones
SELECT 'clientes_direcciones' as tabla,
       CASE WHEN COUNT(*) > 0 THEN 'SÍ tiene id_status' ELSE 'NO tiene id_status' END as tiene_id_status,
       GROUP_CONCAT(COLUMN_NAME) as columnas
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes_direcciones'
  AND COLUMN_NAME = 'id_status'

UNION ALL

-- 3. clientes_contactos
SELECT 'clientes_contactos' as tabla,
       CASE WHEN COUNT(*) > 0 THEN 'SÍ tiene id_status' ELSE 'NO tiene id_status' END as tiene_id_status,
       GROUP_CONCAT(COLUMN_NAME) as columnas
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes_contactos'
  AND COLUMN_NAME = 'id_status'

UNION ALL

-- 4. clientes_documentos
SELECT 'clientes_documentos' as tabla,
       CASE WHEN COUNT(*) > 0 THEN 'SÍ tiene id_status' ELSE 'NO tiene id_status' END as tiene_id_status,
       GROUP_CONCAT(COLUMN_NAME) as columnas
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes_documentos'
  AND COLUMN_NAME = 'id_status';

-- Mostrar todas las columnas de cada tabla para referencia
SELECT '=== COLUMNAS DE clientes_identificaciones ===' as info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes_identificaciones'
ORDER BY ORDINAL_POSITION;

SELECT '=== COLUMNAS DE clientes_direcciones ===' as info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes_direcciones'
ORDER BY ORDINAL_POSITION;

SELECT '=== COLUMNAS DE clientes_contactos ===' as info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes_contactos'
ORDER BY ORDINAL_POSITION;
