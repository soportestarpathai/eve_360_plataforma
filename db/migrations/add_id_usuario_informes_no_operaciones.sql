-- Permite informes de no operaciones por usuario (cada usuario ve solo los suyos)
-- Ejecutar cada sentencia por separado. Si id_usuario ya existe, omitir la línea 1.

-- 1. Agregar columna id_usuario (omitir si ya existe)
ALTER TABLE informes_no_operaciones_pld ADD COLUMN id_usuario INT NULL;

-- 2. Cambiar índice único para permitir un informe por (periodo, usuario)
ALTER TABLE informes_no_operaciones_pld DROP INDEX idx_periodo;
ALTER TABLE informes_no_operaciones_pld ADD UNIQUE KEY idx_periodo_usuario (periodo_mes, periodo_anio, id_usuario);
