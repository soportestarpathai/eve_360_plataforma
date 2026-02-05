-- Migration: Agregar menú "Conservación PLD" (VAL-PLD-013)
-- Fecha: 2025-01-21
-- Descripción: Agrega el menú para acceder a la gestión de conservación de información

-- Verificar si ya existe
SET @menu_exists = (
    SELECT COUNT(*) 
    FROM menu_access 
    WHERE seccion = 'Conservación PLD' 
    AND file_path = 'conservacion_pld.php'
);

-- Si no existe, agregarlo para todos los tipos de empresa
SET @sql = IF(@menu_exists = 0,
    'INSERT INTO menu_access (id_tipo_empresa, seccion, icon, file_path, id_parent) 
     SELECT DISTINCT id_tipo_empresa, ''Conservación PLD'', ''fa-archive'', ''conservacion_pld.php'', 0
     FROM cat_tipo_empresa
     WHERE id_tipo_empresa IN (SELECT DISTINCT id_tipo_empresa FROM menu_access)',
    'SELECT ''Menu item Conservación PLD already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar inserción
SELECT * FROM menu_access WHERE seccion = 'Conservación PLD' AND file_path = 'conservacion_pld.php';
