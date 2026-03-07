-- Script: Agregar Fracción II (TSC) a fracciones_activas
-- Fecha: 2025-02-05
-- Descripción: Incluye Fracción II (Tarjetas de Servicios de Crédito) en config_empresa
--              para que aparezca en la sección Fracciones PLD al editar usuarios cliente.

-- OPCIÓN 1: Manual (recomendado)
-- En Admin > Configuración > Patrón PLD > Fracciones Activas, agregar "II" al valor.
-- Ejemplo: ["II", "V", "V Bis", "VI"] o bien: II, V, V Bis, VI

-- OPCIÓN 2: Si fracciones_activas está vacío o es NULL, inicializar con II
UPDATE `config_empresa`
SET `fracciones_activas` = JSON_ARRAY('II')
WHERE `id_config` = 1
  AND (`fracciones_activas` IS NULL OR `fracciones_activas` = '[]' OR `fracciones_activas` = '');

-- OPCIÓN 3: Agregar II al array existente (MySQL 5.7+)
-- Si ya tienes ["V","V Bis","VI"], esto lo convierte en ["V","V Bis","VI","II"]
-- Ajusta el id_config si corresponde
/*
UPDATE `config_empresa`
SET `fracciones_activas` = JSON_ARRAY_APPEND(COALESCE(`fracciones_activas`,'[]'), '$', 'II')
WHERE `id_config` = 1
  AND JSON_SEARCH(`fracciones_activas`, 'one', 'II', NULL, '$') IS NULL;
*/
