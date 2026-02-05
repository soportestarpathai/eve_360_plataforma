-- ============================================
-- Migration: Campos para Validaciones PLD (VAL-PLD-004 a VAL-PLD-015)
-- Fecha: 2025-01-21
-- Descripción: Agrega campos necesarios para todas las validaciones PLD
-- ============================================

-- ============================================
-- CATEGORÍA B - REPRESENTACIÓN LEGAL (VAL-PLD-004)
-- ============================================

-- Tabla para representación legal de usuarios
-- Verificar si la tabla existe antes de crearla
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'usuarios_representacion_legal'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `usuarios_representacion_legal` (
        `id_representacion` INT NOT NULL AUTO_INCREMENT,
        `id_usuario` INT NOT NULL,
        `id_cliente` INT DEFAULT NULL COMMENT ''Cliente asociado si aplica'',
        `tipo_representacion` ENUM(''representante_legal'', ''apoderado'', ''usuario_autorizado'') NOT NULL,
        `documento_facultades` TEXT COMMENT ''Ruta al documento de facultades'',
        `fecha_alta` DATE DEFAULT NULL,
        `fecha_vencimiento` DATE DEFAULT NULL,
        `id_status` TINYINT(1) DEFAULT 1,
        `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_representacion`),
        INDEX `idx_usuario` (`id_usuario`),
        INDEX `idx_cliente` (`id_cliente`),
        INDEX `idx_tipo` (`tipo_representacion`),
        INDEX `idx_status` (`id_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT=''Representación legal de usuarios (VAL-PLD-004)''',
    'SELECT ''Table usuarios_representacion_legal already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- CATEGORÍA C - EXPEDIENTES (VAL-PLD-005, VAL-PLD-006)
-- ============================================

-- Agregar campos a tabla clientes para expediente
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'clientes' 
    AND COLUMN_NAME = 'fecha_ultima_actualizacion_expediente'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `clientes` 
     ADD COLUMN `fecha_ultima_actualizacion_expediente` DATE DEFAULT NULL COMMENT ''Fecha última actualización del expediente (VAL-PLD-006)'' AFTER `fecha_calculo_riesgo`,
     ADD COLUMN `identificacion_incompleta` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Expediente incompleto (VAL-PLD-005)'' AFTER `fecha_ultima_actualizacion_expediente`,
     ADD COLUMN `expediente_completo` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Expediente completo'' AFTER `identificacion_incompleta`',
    'SELECT ''Columns already exist in clientes'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tabla para beneficiario controlador
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'clientes_beneficiario_controlador'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `clientes_beneficiario_controlador` (
        `id_beneficiario` INT NOT NULL AUTO_INCREMENT,
        `id_cliente` INT NOT NULL,
        `tipo_persona` ENUM(''fisica'', ''moral'') NOT NULL,
        `nombre_completo` VARCHAR(255) DEFAULT NULL,
        `rfc` VARCHAR(13) DEFAULT NULL,
        `porcentaje_participacion` DECIMAL(5,2) DEFAULT NULL,
        `documento_identificacion` TEXT COMMENT ''Ruta al documento de identificación'',
        `declaracion_jurada` TEXT COMMENT ''Ruta a declaración jurada si aplica'',
        `fecha_registro` DATE DEFAULT NULL,
        `fecha_ultima_actualizacion` DATE DEFAULT NULL,
        `id_status` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`id_beneficiario`),
        INDEX `idx_cliente` (`id_cliente`),
        INDEX `idx_status` (`id_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT=''Beneficiario controlador (VAL-PLD-007, VAL-PLD-015)''',
    'SELECT ''Table clientes_beneficiario_controlador already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- CATEGORÍA D - AVISOS Y UMBRALES (VAL-PLD-008 a VAL-PLD-012)
-- ============================================

-- Tabla para operaciones/transacciones PLD
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'operaciones_pld'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `operaciones_pld` (
        `id_operacion` INT NOT NULL AUTO_INCREMENT,
        `id_cliente` INT NOT NULL,
        `id_fraccion` INT DEFAULT NULL COMMENT ''Fracción de actividad vulnerable'',
        `tipo_operacion` VARCHAR(100) DEFAULT NULL,
        `monto` DECIMAL(15,2) NOT NULL,
        `monto_uma` DECIMAL(15,2) DEFAULT NULL COMMENT ''Monto en UMAs'',
        `fecha_operacion` DATE NOT NULL,
        `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `es_sospechosa` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Operación sospechosa (VAL-PLD-010)'',
        `fecha_conocimiento_sospecha` DATETIME DEFAULT NULL,
        `match_listas_restringidas` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Match en listas (VAL-PLD-011)'',
        `fecha_conocimiento_match` DATETIME DEFAULT NULL,
        `requiere_aviso` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Requiere aviso (VAL-PLD-008, VAL-PLD-009)'',
        `tipo_aviso` ENUM(''umbral_individual'', ''acumulacion'', ''sospechosa'', ''listas_restringidas'') DEFAULT NULL,
        `fecha_deadline_aviso` DATE DEFAULT NULL COMMENT ''Deadline día 17 del mes siguiente'',
        `id_aviso_generado` INT DEFAULT NULL COMMENT ''ID del aviso generado si aplica'',
        `id_status` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`id_operacion`),
        INDEX `idx_cliente` (`id_cliente`),
        INDEX `idx_fecha_operacion` (`fecha_operacion`),
        INDEX `idx_requiere_aviso` (`requiere_aviso`),
        INDEX `idx_es_sospechosa` (`es_sospechosa`),
        INDEX `idx_match_listas` (`match_listas_restringidas`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT=''Operaciones PLD (VAL-PLD-008 a VAL-PLD-011)''',
    'SELECT ''Table operaciones_pld already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tabla para acumulación de operaciones (ventana móvil 6 meses)
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'operaciones_pld_acumulacion'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `operaciones_pld_acumulacion` (
        `id_acumulacion` INT NOT NULL AUTO_INCREMENT,
        `id_cliente` INT NOT NULL,
        `id_fraccion` INT DEFAULT NULL,
        `tipo_acto` VARCHAR(100) DEFAULT NULL,
        `fecha_primera_operacion` DATE NOT NULL COMMENT ''Primera operación de la ventana'',
        `fecha_ultima_operacion` DATE NOT NULL,
        `monto_acumulado` DECIMAL(15,2) NOT NULL,
        `monto_acumulado_uma` DECIMAL(15,2) DEFAULT NULL,
        `cantidad_operaciones` INT DEFAULT 1,
        `requiere_aviso` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Rebase umbral (VAL-PLD-009)'',
        `fecha_deadline_aviso` DATE DEFAULT NULL,
        `id_aviso_generado` INT DEFAULT NULL,
        `fecha_cierre` DATE DEFAULT NULL COMMENT ''Fecha de cierre de la ventana'',
        `id_status` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`id_acumulacion`),
        INDEX `idx_cliente` (`id_cliente`),
        INDEX `idx_fecha_primera` (`fecha_primera_operacion`),
        INDEX `idx_requiere_aviso` (`requiere_aviso`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT=''Acumulación de operaciones 6 meses (VAL-PLD-009)''',
    'SELECT ''Table operaciones_pld_acumulacion already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tabla para avisos PLD
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'avisos_pld'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `avisos_pld` (
        `id_aviso` INT NOT NULL AUTO_INCREMENT,
        `id_cliente` INT NOT NULL,
        `tipo_aviso` ENUM(''umbral_individual'', ''acumulacion'', ''sospechosa_24h'', ''listas_restringidas_24h'', ''informe_no_operaciones'') NOT NULL,
        `fecha_operacion` DATE DEFAULT NULL,
        `fecha_conocimiento` DATETIME DEFAULT NULL COMMENT ''Fecha de conocimiento para avisos 24H'',
        `monto` DECIMAL(15,2) DEFAULT NULL,
        `folio_sppld` VARCHAR(100) DEFAULT NULL COMMENT ''Folio del aviso en SPPLD'',
        `fecha_presentacion` DATE DEFAULT NULL,
        `fecha_deadline` DATE DEFAULT NULL COMMENT ''Deadline día 17 del mes siguiente'',
        `estatus` ENUM(''pendiente'', ''generado'', ''presentado'', ''extemporaneo'', ''cancelado'') DEFAULT ''pendiente'',
        `observaciones` TEXT,
        `id_status` TINYINT(1) DEFAULT 1,
        `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_aviso`),
        INDEX `idx_cliente` (`id_cliente`),
        INDEX `idx_tipo` (`tipo_aviso`),
        INDEX `idx_estatus` (`estatus`),
        INDEX `idx_fecha_deadline` (`fecha_deadline`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT=''Avisos PLD (VAL-PLD-008 a VAL-PLD-012)''',
    'SELECT ''Table avisos_pld already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tabla para informes de no operaciones
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'informes_no_operaciones_pld'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `informes_no_operaciones_pld` (
        `id_informe` INT NOT NULL AUTO_INCREMENT,
        `periodo_mes` INT NOT NULL COMMENT ''Mes (1-12)'',
        `periodo_anio` INT NOT NULL COMMENT ''Año'',
        `fecha_limite` DATE NOT NULL COMMENT ''Fecha límite día 17 del mes siguiente'',
        `fecha_presentacion` DATE DEFAULT NULL,
        `folio_sppld` VARCHAR(100) DEFAULT NULL,
        `estatus` ENUM(''pendiente'', ''presentado'', ''extemporaneo'') DEFAULT ''pendiente'',
        `observaciones` TEXT,
        `id_status` TINYINT(1) DEFAULT 1,
        `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_informe`),
        UNIQUE KEY `idx_periodo` (`periodo_mes`, `periodo_anio`),
        INDEX `idx_fecha_limite` (`fecha_limite`),
        INDEX `idx_estatus` (`estatus`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT=''Informes de no operaciones (VAL-PLD-012)''',
    'SELECT ''Table informes_no_operaciones_pld already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- CATEGORÍA E - CONSERVACIÓN Y AUDITORÍA (VAL-PLD-013, VAL-PLD-014)
-- ============================================

-- Tabla para conservación de información
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'conservacion_informacion_pld'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `conservacion_informacion_pld` (
        `id_conservacion` INT NOT NULL AUTO_INCREMENT,
        `id_cliente` INT DEFAULT NULL,
        `id_operacion` INT DEFAULT NULL,
        `id_aviso` INT DEFAULT NULL,
        `tipo_evidencia` ENUM(''expediente'', ''documento'', ''aviso'', ''operacion'', ''cambio'') NOT NULL,
        `ruta_evidencia` TEXT NOT NULL,
        `fecha_creacion` DATETIME NOT NULL,
        `fecha_vencimiento` DATE NOT NULL COMMENT ''Fecha de vencimiento (10 años desde creación)'',
        `expediente_incompleto` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Falta evidencia (VAL-PLD-013)'',
        `id_status` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`id_conservacion`),
        INDEX `idx_cliente` (`id_cliente`),
        INDEX `idx_fecha_vencimiento` (`fecha_vencimiento`),
        INDEX `idx_expediente_incompleto` (`expediente_incompleto`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT=''Conservación de información 10 años (VAL-PLD-013)''',
    'SELECT ''Table conservacion_informacion_pld already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tabla para visitas de verificación
SET @table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'visitas_verificacion_pld'
);

SET @sql = IF(@table_exists = 0,
    'CREATE TABLE `visitas_verificacion_pld` (
        `id_visita` INT NOT NULL AUTO_INCREMENT,
        `fecha_visita` DATE NOT NULL,
        `autoridad` VARCHAR(255) DEFAULT NULL,
        `tipo_requerimiento` VARCHAR(255) DEFAULT NULL,
        `expedientes_solicitados` TEXT COMMENT ''IDs de expedientes solicitados'',
        `expedientes_disponibles` TINYINT(1) DEFAULT 1 COMMENT ''Flag: 1 = Disponibles (VAL-PLD-014)'',
        `observaciones` TEXT,
        `estatus` ENUM(''programada'', ''en_proceso'', ''concluida'', ''con_observaciones'') DEFAULT ''programada'',
        `id_status` TINYINT(1) DEFAULT 1,
        `fecha_creacion` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_visita`),
        INDEX `idx_fecha_visita` (`fecha_visita`),
        INDEX `idx_estatus` (`estatus`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT=''Visitas de verificación (VAL-PLD-014)''',
    'SELECT ''Table visitas_verificacion_pld already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Campos adicionales en config_empresa para flags globales
-- ============================================

SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'config_empresa' 
    AND COLUMN_NAME = 'incumplimiento_pld'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `config_empresa` 
     ADD COLUMN `incumplimiento_pld` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Incumplimiento PLD (VAL-PLD-012)'' AFTER `no_habilitado_pld`,
     ADD COLUMN `aviso_requerido` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Aviso requerido (VAL-PLD-008, VAL-PLD-009)'' AFTER `incumplimiento_pld`,
     ADD COLUMN `aviso_24h` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Aviso 24H pendiente (VAL-PLD-010, VAL-PLD-011)'' AFTER `aviso_requerido`,
     ADD COLUMN `operacion_rechazada_pld` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Operación rechazada'' AFTER `aviso_24h`,
     ADD COLUMN `restriccion_efectivo` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Restricción por pago en efectivo'' AFTER `operacion_rechazada_pld`,
     ADD COLUMN `riesgo_sancion_administrativa` TINYINT(1) DEFAULT 0 COMMENT ''Flag: 1 = Riesgo sancionable'' AFTER `restriccion_efectivo`',
    'SELECT ''Columns already exist in config_empresa'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- Índices adicionales para optimización
-- ============================================

-- Índice para búsquedas de operaciones por cliente y fecha
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'operaciones_pld' 
    AND INDEX_NAME = 'idx_cliente_fecha'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `operaciones_pld` ADD INDEX `idx_cliente_fecha` (`id_cliente`, `fecha_operacion`)',
    'SELECT ''Index idx_cliente_fecha already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed successfully' AS result;
