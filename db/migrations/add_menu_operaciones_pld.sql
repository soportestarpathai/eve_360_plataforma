-- Migration: Agregar "Operaciones PLD" al menú
-- Fecha: 2025-01-21
-- Descripción: Agrega el elemento de menú para Operaciones PLD (VAL-PLD-008)

-- Verificar si ya existe el elemento de menú
SET @menu_exists = (
    SELECT COUNT(*) 
    FROM menu_access 
    WHERE seccion = 'Operaciones PLD' 
    AND file_path = 'operaciones_pld.php'
);

-- Si no existe, insertarlo para todos los tipos de empresa
SET @sql = IF(@menu_exists = 0,
    'INSERT INTO menu_access (id_tipo_empresa, seccion, icon, file_path, id_parent) 
     SELECT DISTINCT id_tipo_empresa, ''Operaciones PLD'', ''fa-file-invoice-dollar'', ''operaciones_pld.php'', 0
     FROM cat_tipo_empresa
     WHERE id_tipo_empresa IN (SELECT DISTINCT id_tipo_empresa FROM menu_access)',
    'SELECT ''Menu item Operaciones PLD already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar inserción
SELECT * FROM menu_access WHERE seccion = 'Operaciones PLD' AND file_path = 'operaciones_pld.php';
