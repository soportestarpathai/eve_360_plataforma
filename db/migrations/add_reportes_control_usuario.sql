-- Reportes controlables por usuario (Conservación PLD, Riesgos, Transacciones, Bitácora)
-- El codigo = file_path para filtrar en el menú

INSERT INTO cat_tipos_reporte (nombre, codigo, descripcion)
SELECT 'Conservación y Verificación PLD', 'conservacion_pld.php', 'Reporte de conservación PLD (VAL-PLD-013, VAL-PLD-014)'
WHERE NOT EXISTS (SELECT 1 FROM cat_tipos_reporte WHERE codigo = 'conservacion_pld.php');

INSERT INTO cat_tipos_reporte (nombre, codigo, descripcion)
SELECT 'Reporte de riesgos', 'reporte_riesgos.php', 'Reporte de niveles de riesgo por cliente'
WHERE NOT EXISTS (SELECT 1 FROM cat_tipos_reporte WHERE codigo = 'reporte_riesgos.php');

INSERT INTO cat_tipos_reporte (nombre, codigo, descripcion)
SELECT 'Reporte de transacciones', 'reporte_transacciones.php', 'Reporte de transacciones PLD'
WHERE NOT EXISTS (SELECT 1 FROM cat_tipos_reporte WHERE codigo = 'reporte_transacciones.php');

INSERT INTO cat_tipos_reporte (nombre, codigo, descripcion)
SELECT 'Bitácora de actividad de usuarios (SAT)', 'bitacora_actividad.php', 'Registro de actividad de usuarios'
WHERE NOT EXISTS (SELECT 1 FROM cat_tipos_reporte WHERE codigo = 'bitacora_actividad.php');
