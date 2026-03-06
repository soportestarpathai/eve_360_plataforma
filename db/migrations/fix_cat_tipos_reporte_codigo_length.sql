-- Ampliar columna codigo para permitir nombres de archivo como reporte_transacciones.php
ALTER TABLE cat_tipos_reporte MODIFY COLUMN codigo VARCHAR(80) DEFAULT NULL;
