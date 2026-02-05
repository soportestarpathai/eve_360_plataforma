-- Script para verificar si los campos de expediente PLD existen
-- Ejecuta este script primero para verificar

SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'clientes'
  AND COLUMN_NAME IN (
    'fecha_ultima_actualizacion_expediente',
    'identificacion_incompleta',
    'expediente_completo'
  )
ORDER BY COLUMN_NAME;
