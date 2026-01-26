-- Script: Datos de PRUEBA para habilitar PLD (solo para desarrollo/testing)
-- Fecha: 2025-01-21
-- ADVERTENCIA: Estos son datos de PRUEBA. NO usar en producción.
-- En producción, usar los datos reales del padrón PLD del SAT

-- Datos de prueba para desarrollo
UPDATE `config_empresa` 
SET 
    `folio_patron_pld` = 'FOLIO-TEST-12345',
    `estatus_patron_pld` = 'vigente',
    `fracciones_activas` = JSON_ARRAY('V', 'V Bis'),
    `no_habilitado_pld` = 0,
    `fecha_revalidacion_patron` = CURDATE()
WHERE `id_config` = 1;

-- Verificar que se insertó correctamente
SELECT 
    `folio_patron_pld`,
    `estatus_patron_pld`,
    `fracciones_activas`,
    `no_habilitado_pld`,
    CASE 
        WHEN `no_habilitado_pld` = 0 THEN 'HABILITADO'
        ELSE 'NO HABILITADO'
    END AS estado_habilitacion
FROM `config_empresa`
WHERE `id_config` = 1;
