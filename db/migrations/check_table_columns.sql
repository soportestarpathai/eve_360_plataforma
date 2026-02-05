-- Script para verificar las columnas de las tablas relacionadas con expediente PLD
-- Ejecuta este script para ver qué columnas tienen las tablas

-- 1. Verificar clientes_identificaciones
SELECT 'clientes_identificaciones' as tabla, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes_identificaciones'
ORDER BY ORDINAL_POSITION;

-- 2. Verificar clientes_direcciones
SELECT 'clientes_direcciones' as tabla, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes_direcciones'
ORDER BY ORDINAL_POSITION;

-- 3. Verificar clientes_contactos
SELECT 'clientes_contactos' as tabla, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes_contactos'
ORDER BY ORDINAL_POSITION;

-- 4. Verificar clientes_documentos
SELECT 'clientes_documentos' as tabla, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes_documentos'
ORDER BY ORDINAL_POSITION;
