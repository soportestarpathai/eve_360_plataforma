-- Script: Agregar Fracciones II (TSC) y XI (SPR) a fracciones_activas
-- Fecha: 2025-02-05
-- II = Tarjetas de Servicio y Crédito (TSC)
-- XI = Servicios Profesionales (SPR)

-- Si fracciones_activas está vacío, inicializar con II y XI
UPDATE `config_empresa`
SET `fracciones_activas` = JSON_ARRAY('II', 'XI')
WHERE (`fracciones_activas` IS NULL OR `fracciones_activas` = '[]' OR `fracciones_activas` = '')
  AND `id_config` = 1;

-- Si no incluye II, agregarlo
UPDATE `config_empresa`
SET `fracciones_activas` = JSON_ARRAY_APPEND(COALESCE(`fracciones_activas`,'[]'), '$', 'II')
WHERE `id_config` = 1
  AND JSON_SEARCH(`fracciones_activas`, 'one', 'II', NULL, '$') IS NULL;

-- Si no incluye XI, agregarlo
UPDATE `config_empresa`
SET `fracciones_activas` = JSON_ARRAY_APPEND(COALESCE(`fracciones_activas`,'[]'), '$', 'XI')
WHERE `id_config` = 1
  AND JSON_SEARCH(`fracciones_activas`, 'one', 'XI', NULL, '$') IS NULL;

SELECT 'Fracciones II y XI agregadas a config' AS result;
