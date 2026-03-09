-- Script: Asegurar Fracción XI - Servicios Profesionales (SPR) en cat_vulnerables
-- Fecha: 2025-02-05
-- Nota: Si ya existe registro con fraccion XI para SPR, no hace nada.

INSERT INTO `cat_vulnerables` (`nombre`, `fraccion`, `ruta_template`, `ruta_instructivo`, `umbral_acumulacion_uma`, `umbral_aviso_uma`, `umbral_expediente_uma`)
SELECT 'Servicios Profesionales (SPR)', 'XI', 'instructivos/instructivo_spr2.xlsx', 'instructivos/Fraccion XI.pdf', NULL, NULL, NULL
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `cat_vulnerables` WHERE `fraccion` = 'XI' AND (`nombre` LIKE '%Servicios Profesionales%' OR `nombre` LIKE '%SPR%') LIMIT 1);

SELECT 'Fracción XI SPR verificada' AS result;
