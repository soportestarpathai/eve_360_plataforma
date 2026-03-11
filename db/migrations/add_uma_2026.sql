-- Migration: Agregar UMA 2026 a indicadores
-- Fecha: 2026-02-05
-- Valor oficial UMA 2026 (vigente 1 feb 2026): Diario 117.31 MXN
-- Fuente: INEGI / DOF

INSERT INTO `indicadores` (`nombre`, `fecha`, `valor`)
SELECT 'UMA (Valor Diario)', '2026-02-01 00:00:00', 117.31
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `indicadores`
    WHERE `nombre` LIKE '%UMA%' AND YEAR(`fecha`) = 2026
);
