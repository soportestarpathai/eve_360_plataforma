-- ============================================
-- Script: Insertar Ejemplos de Fracciones PLD
-- Fecha: 2025-01-21
-- Descripción: Inserta ejemplos de fracciones PLD según la LFPIORPI
-- ============================================
-- 
-- IMPORTANTE: Estas son fracciones de ejemplo según la Ley Federal para la 
-- Prevención e Identificación de Operaciones con Recursos de Procedencia Ilícita (LFPIORPI)
-- 
-- Las fracciones reales deben coincidir con las que tienes registradas en el SAT
-- ============================================

-- Verificar si la tabla existe
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'cat_vulnerables'
);

-- Si la tabla no existe, crearla
SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `cat_vulnerables` (
        `id_vulnerable` INT NOT NULL AUTO_INCREMENT,
        `fraccion` VARCHAR(20) NOT NULL COMMENT ''Fracción según LFPIORPI (ej: V, V Bis, VI, XIII)'',
        `nombre` VARCHAR(255) NOT NULL COMMENT ''Nombre descriptivo de la actividad vulnerable'',
        `descripcion` TEXT DEFAULT NULL COMMENT ''Descripción detallada de la actividad'',
        `id_status` TINYINT(1) DEFAULT 1 COMMENT ''1 = Activo, 0 = Inactivo'',
        `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `fecha_modificacion` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_vulnerable`),
        UNIQUE KEY `uk_fraccion` (`fraccion`),
        INDEX `idx_status` (`id_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT=''Catálogo de Actividades Vulnerables PLD''',
    'SELECT ''Table cat_vulnerables already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- INSERTAR FRACCIONES PLD DE EJEMPLO
-- ============================================
-- Nota: Usa INSERT IGNORE para evitar errores si ya existen

INSERT IGNORE INTO `cat_vulnerables` (`fraccion`, `nombre`, `descripcion`, `id_status`) VALUES
-- Fracción V: Actos de adquisición, enajenación o arrendamiento de bienes inmuebles
('V', 'Adquisición, Enajenación o Arrendamiento de Bienes Inmuebles', 
 'Actos de adquisición, enajenación o arrendamiento de bienes inmuebles, incluyendo lotes, casas, departamentos, terrenos, locales comerciales, etc.', 1),

-- Fracción V Bis: Actos de adquisición, enajenación o arrendamiento de bienes muebles
('V Bis', 'Adquisición, Enajenación o Arrendamiento de Bienes Muebles', 
 'Actos de adquisición, enajenación o arrendamiento de bienes muebles de alto valor, como vehículos, maquinaria, equipo industrial, etc.', 1),

-- Fracción VI: Actos de intermediación en operaciones con bienes inmuebles
('VI', 'Intermediación en Operaciones con Bienes Inmuebles', 
 'Actos de intermediación, promoción, gestión o realización de operaciones de adquisición, enajenación o arrendamiento de bienes inmuebles.', 1),

-- Fracción XIII: Actos de donativos
('XIII', 'Donativos', 
 'Actos de donativos, incluyendo donaciones en efectivo, bienes, servicios o cualquier otro tipo de donativo que pueda ser utilizado para lavado de dinero.', 1),

-- Fracción I: Actos de adquisición, enajenación o arrendamiento de bienes inmuebles (versión anterior)
('I', 'Adquisición de Bienes Inmuebles (Versión Anterior)', 
 'Actos de adquisición de bienes inmuebles (versión anterior de la fracción V).', 1),

-- Fracción II: Actos de enajenación de bienes inmuebles (versión anterior)
('II', 'Enajenación de Bienes Inmuebles (Versión Anterior)', 
 'Actos de enajenación de bienes inmuebles (versión anterior de la fracción V).', 1),

-- Fracción III: Actos de arrendamiento de bienes inmuebles (versión anterior)
('III', 'Arrendamiento de Bienes Inmuebles (Versión Anterior)', 
 'Actos de arrendamiento de bienes inmuebles (versión anterior de la fracción V).', 1),

-- Fracción IV: Actos de adquisición, enajenación o arrendamiento de bienes muebles (versión anterior)
('IV', 'Adquisición, Enajenación o Arrendamiento de Bienes Muebles (Versión Anterior)', 
 'Actos de adquisición, enajenación o arrendamiento de bienes muebles (versión anterior de la fracción V Bis).', 1),

-- Fracción VII: Actos de intermediación en operaciones con bienes muebles
('VII', 'Intermediación en Operaciones con Bienes Muebles', 
 'Actos de intermediación, promoción, gestión o realización de operaciones de adquisición, enajenación o arrendamiento de bienes muebles.', 1),

-- Fracción VIII: Actos de adquisición, enajenación o arrendamiento de bienes inmuebles en desarrollo
('VIII', 'Desarrollo de Bienes Inmuebles', 
 'Actos de desarrollo, construcción, promoción o comercialización de bienes inmuebles, incluyendo fraccionamientos, condominios, etc.', 1),

-- Fracción IX: Actos de adquisición, enajenación o arrendamiento de bienes inmuebles comerciales
('IX', 'Operaciones con Bienes Inmuebles Comerciales', 
 'Actos de adquisición, enajenación o arrendamiento de bienes inmuebles destinados a uso comercial, industrial o de servicios.', 1),

-- Fracción X: Actos de adquisición, enajenación o arrendamiento de bienes inmuebles residenciales
('X', 'Operaciones con Bienes Inmuebles Residenciales', 
 'Actos de adquisición, enajenación o arrendamiento de bienes inmuebles destinados a uso residencial.', 1),

-- Fracción XI: Actos de adquisición, enajenación o arrendamiento de bienes inmuebles mixtos
('XI', 'Operaciones con Bienes Inmuebles Mixtos', 
 'Actos de adquisición, enajenación o arrendamiento de bienes inmuebles con uso mixto (residencial y comercial).', 1),

-- Fracción XII: Actos de adquisición, enajenación o arrendamiento de bienes inmuebles rurales
('XII', 'Operaciones con Bienes Inmuebles Rurales', 
 'Actos de adquisición, enajenación o arrendamiento de bienes inmuebles destinados a uso agrícola, ganadero o forestal.', 1);

-- ============================================
-- VERIFICACIÓN: Consultar fracciones insertadas
-- ============================================

SELECT 
    `id_vulnerable`,
    `fraccion`,
    `nombre`,
    `descripcion`,
    `id_status`
FROM `cat_vulnerables`
ORDER BY `fraccion` ASC;

-- ============================================
-- NOTAS IMPORTANTES:
-- ============================================
-- 1. Las fracciones V, V Bis, VI y XIII son las más comunes en el sector inmobiliario
-- 2. Las fracciones I, II, III, IV son versiones anteriores que pueden estar en uso
-- 3. Las fracciones VII-XII son ejemplos adicionales que pueden aplicarse según el caso
-- 4. Debes verificar en el SAT qué fracciones están realmente registradas para tu empresa
-- 5. Solo las fracciones registradas en el padrón PLD del SAT deben estar activas (id_status = 1)
-- 6. Las fracciones no registradas deben estar inactivas (id_status = 0) o eliminadas
-- ============================================
