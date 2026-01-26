-- Script: Insertar datos de padrón PLD para habilitar VAL-PLD-001
-- Fecha: 2025-01-21
-- Descripción: Actualiza config_empresa con datos del padrón PLD del SAT
-- IMPORTANTE: Reemplaza los valores de ejemplo con los datos reales de tu empresa

-- ============================================
-- OPCIÓN 1: UPDATE (si ya existe registro en config_empresa)
-- ============================================

UPDATE `config_empresa` 
SET 
    `folio_patron_pld` = 'FOLIO-123456789',  -- ⚠️ REEMPLAZAR: Folio real del padrón PLD del SAT
    `estatus_patron_pld` = 'vigente',        -- ⚠️ REEMPLAZAR: 'vigente', 'baja', 'suspendido'
    `fracciones_activas` = JSON_ARRAY('V', 'V Bis', 'VI'),  -- ⚠️ REEMPLAZAR: Fracciones reales (ej: 'V', 'V Bis', 'VI', 'XIII')
    `no_habilitado_pld` = 0,                 -- 0 = habilitado, 1 = NO habilitado
    `fecha_revalidacion_patron` = CURDATE()  -- Fecha actual
WHERE `id_config` = 1;

-- ============================================
-- OPCIÓN 2: INSERT (si no existe registro, aunque debería existir)
-- ============================================
-- NOTA: Normalmente config_empresa ya tiene un registro con id_config = 1
-- Solo usar este INSERT si realmente no existe

/*
INSERT INTO `config_empresa` (
    `id_config`,
    `nombre_empresa`,
    `id_tipo_empresa`,
    `logo_url`,
    `color_primario`,
    `max_usuarios`,
    `max_busquedas_api`,
    `id_vulnerable`,
    `folio_patron_pld`,
    `estatus_patron_pld`,
    `fecha_revalidacion_patron`,
    `no_habilitado_pld`,
    `fracciones_activas`
) VALUES (
    1,
    'Nombre de tu Empresa',  -- ⚠️ REEMPLAZAR
    1,                       -- ⚠️ REEMPLAZAR: id_tipo_empresa
    'assets/img/logo.png',   -- ⚠️ REEMPLAZAR
    '#1B8FEA',               -- ⚠️ REEMPLAZAR
    10,                      -- ⚠️ REEMPLAZAR
    500,                     -- ⚠️ REEMPLAZAR
    0,                       -- ⚠️ REEMPLAZAR: id_vulnerable
    'FOLIO-123456789',       -- ⚠️ REEMPLAZAR: Folio real del padrón PLD
    'vigente',               -- ⚠️ REEMPLAZAR: 'vigente', 'baja', 'suspendido'
    CURDATE(),               -- Fecha actual
    0,                       -- 0 = habilitado
    JSON_ARRAY('V', 'V Bis', 'VI')  -- ⚠️ REEMPLAZAR: Fracciones reales
) ON DUPLICATE KEY UPDATE
    `folio_patron_pld` = VALUES(`folio_patron_pld`),
    `estatus_patron_pld` = VALUES(`estatus_patron_pld`),
    `fecha_revalidacion_patron` = VALUES(`fecha_revalidacion_patron`),
    `no_habilitado_pld` = VALUES(`no_habilitado_pld`),
    `fracciones_activas` = VALUES(`fracciones_activas`);
*/

-- ============================================
-- VERIFICACIÓN: Consultar datos insertados
-- ============================================

-- Ver todos los datos de padrón PLD
SELECT 
    `id_config`,
    `nombre_empresa`,
    `folio_patron_pld`,
    `estatus_patron_pld`,
    `fracciones_activas`,
    `no_habilitado_pld`,
    `fecha_revalidacion_patron`
FROM `config_empresa`
WHERE `id_config` = 1;

-- ============================================
-- EJEMPLOS DE FRACCIONES COMUNES
-- ============================================
-- Fracción V: Actividades de intermediación
-- Fracción V Bis: Actividades de desarrollo
-- Fracción VI: Actividades de comercialización
-- Fracción XIII: Donativos

-- Ejemplo con una sola fracción:
-- `fracciones_activas` = JSON_ARRAY('V')

-- Ejemplo con múltiples fracciones:
-- `fracciones_activas` = JSON_ARRAY('V', 'V Bis', 'VI')

-- Ejemplo con fracción XIII (donativos):
-- `fracciones_activas` = JSON_ARRAY('XIII')

-- ============================================
-- NOTAS IMPORTANTES
-- ============================================
-- 1. El folio_patron_pld debe ser el folio real que te asignó el SAT en el Portal PLD
-- 2. El estatus_patron_pld debe ser exactamente 'vigente' (en minúsculas) para pasar la validación
-- 3. Las fracciones_activas deben coincidir con las que tienes registradas en el SAT
-- 4. Si no tienes datos reales aún, puedes usar valores de prueba, pero la validación fallará
-- 5. Para deshabilitar temporalmente, cambiar `no_habilitado_pld` = 1
