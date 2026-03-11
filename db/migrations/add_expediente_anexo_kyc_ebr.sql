-- ============================================
-- Migration: Expediente por Anexo KYC + EBR
-- Fecha: 2026-02-05
-- Puntos 1-5: Anexo aplicable, catálogos, validación por anexo, check documentos, Manual Políticas
-- ============================================

-- 1. CAT_ANEXO_EXPEDIENTE: Catálogo de anexos según Reglas
CREATE TABLE IF NOT EXISTS `cat_anexo_expediente` (
  `id_anexo` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(20) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text,
  `requiere_tipo_residencia` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id_anexo`),
  UNIQUE KEY `uk_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO `cat_anexo_expediente` (`id_anexo`, `clave`, `nombre`, `descripcion`, `requiere_tipo_residencia`) VALUES
(1, 'ANEXO_3', 'Personas físicas (mexicana o extranjera residente)', 'PF mexicana o extranjera con residente temporal/permanente', 1),
(2, 'ANEXO_4', 'Personas morales mexicanas', 'PM mexicana - expediente completo', 0),
(3, 'ANEXO_4_BIS', 'Personas morales mexicanas derecho público', 'PM mexicana de derecho público', 0),
(4, 'ANEXO_5', 'Personas físicas extranjeras (visitante)', 'PF extranjera visitante - expediente completo', 1),
(5, 'ANEXO_6', 'Personas morales extranjeras', 'PM extranjera - expediente completo', 0),
(6, 'ANEXO_6_BIS', 'Embajadas, consulados, organismos internacionales', 'Entes diplomáticos acreditados en México', 0),
(7, 'ANEXO_7', 'Personas morales/dependencias Anexo 7-A (bajo riesgo)', 'Régimen simplificado para entes listados en 7-A', 0),
(8, 'ANEXO_7_BIS', 'Personas morales derecho público Anexo 7 Bis-A', 'Régimen simplificado entes 7 Bis-A (secretarías, SAT, etc.)', 0),
(9, 'ANEXO_8', 'Fideicomisos', 'Expediente completo fideicomisos', 0);

-- 2. CAT_TIPO_RESIDENCIA: Tipo de residencia (para PF)
CREATE TABLE IF NOT EXISTS `cat_tipo_residencia` (
  `id_tipo_residencia` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(30) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `aplica_extranjero` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id_tipo_residencia`),
  UNIQUE KEY `uk_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO `cat_tipo_residencia` (`id_tipo_residencia`, `clave`, `nombre`, `aplica_extranjero`) VALUES
(1, 'MEXICANA', 'Mexicana', 0),
(2, 'RESIDENTE_TEMPORAL', 'Residente temporal', 1),
(3, 'RESIDENTE_PERMANENTE', 'Residente permanente', 1),
(4, 'VISITANTE', 'Visitante (no residente)', 1);

-- 3. CAT_ANEXO_7A: Instituciones/dependencias Anexo 7-A (elegibles régimen simplificado)
CREATE TABLE IF NOT EXISTS `cat_anexo_7a` (
  `id_anexo_7a` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(80) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `id_status` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_anexo_7a`),
  UNIQUE KEY `uk_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO `cat_anexo_7a` (`id_anexo_7a`, `clave`, `nombre`, `id_status`) VALUES
(1, 'SOCIEDADES_CONTROLADORAS', 'Sociedades Controladoras de Grupos Financieros', 1),
(2, 'SOCIEDADES_INVERSION', 'Sociedades de Inversión', 1),
(3, 'SIEFORE', 'Sociedades de Inversión Especializadas en Fondos para el Retiro', 1),
(4, 'OPERADORAS_SI', 'Sociedades Operadoras de Sociedades de Inversión', 1),
(5, 'DISTRIBUIDORAS_ACCIONES', 'Sociedades Distribuidoras de Acciones de Sociedades de Inversión', 1),
(6, 'INSTITUCIONES_CREDITO', 'Instituciones de Crédito', 1),
(7, 'CASAS_BOLSA', 'Casas de Bolsa', 1),
(8, 'CASAS_CAMBIO', 'Casas de Cambio', 1),
(9, 'AFORES', 'Administradoras de Fondos para el Retiro', 1),
(10, 'INSTITUCIONES_SEGUROS', 'Instituciones de Seguros', 1),
(11, 'MUTUALISTAS_SEGUROS', 'Sociedades Mutualistas de Seguros', 1),
(12, 'INSTITUCIONES_FIANZAS', 'Instituciones de Fianzas', 1),
(13, 'ALMACENES_GENERALES', 'Almacenes Generales de Depósito', 1),
(14, 'ARRENDADORAS', 'Arrendadoras Financieras', 1),
(15, 'COOP_AHORRO', 'Sociedades Cooperativas de Ahorro y Préstamo', 1),
(16, 'SOFIPOS', 'Sociedades Financieras Populares', 1),
(17, 'SOFIRES', 'Sociedades Financieras Rurales', 1),
(18, 'SOFOLES', 'Sociedades Financieras de Objeto Limitado', 1),
(19, 'SOFOMES', 'Sociedades Financieras de Objeto Múltiple', 1),
(20, 'UNIONES_CREDITO', 'Uniones de Crédito', 1),
(21, 'EMPRESAS_FACTORAJE', 'Empresas de Factoraje Financiero', 1),
(22, 'EMISORAS_VALORES', 'Sociedades Emisoras de Valores', 1),
(23, 'ENTIDADES_FIN_EXTERIOR', 'Entidades Financieras del Exterior', 1),
(24, 'DEPENDENCIAS_PUBLICAS', 'Dependencias y Entidades públicas federales, estatales y municipales', 1),
(25, 'BOLSAS_VALORES', 'Bolsas de Valores', 1),
(26, 'INDVAL', 'Instituciones para el Depósito de Valores', 1),
(27, 'MECANISMOS_TRANSACCIONES', 'Empresas que administren mecanismos para facilitar transacciones con valores', 1),
(28, 'CONTRAPARTES_CENTRALES', 'Contrapartes Centrales', 1);

-- 4. CAT_ANEXO_7_BIS_A: Entidades Anexo 7 Bis-A (personas morales derecho público)
CREATE TABLE IF NOT EXISTS `cat_anexo_7_bis_a` (
  `id_anexo_7_bis_a` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(60) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `id_status` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_anexo_7_bis_a`),
  UNIQUE KEY `uk_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO `cat_anexo_7_bis_a` (`id_anexo_7_bis_a`, `clave`, `nombre`, `id_status`) VALUES
(1, 'SEGOB', 'Secretaría de Gobernación', 1),
(2, 'SRE', 'Secretaría de Relaciones Exteriores', 1),
(3, 'SEDENA', 'Secretaría de la Defensa Nacional', 1),
(4, 'SEMAR', 'Secretaría de Marina', 1),
(5, 'SHCP', 'Secretaría de Hacienda y Crédito Público', 1),
(6, 'SCT', 'Secretaría de Comunicaciones y Transportes', 1),
(7, 'SFP', 'Secretaría de la Función Pública', 1),
(8, 'CISEN', 'Centro de Investigación y Seguridad Nacional', 1),
(9, 'INM', 'Instituto Nacional de Migración', 1),
(10, 'SEJUSTICIA', 'Secretaría Técnica del Consejo de Coordinación para la Implementación del Sistema de Justicia Penal', 1),
(11, 'SAT', 'Servicio de Administración Tributaria', 1);

-- 5. AGREGAR COLUMNAS A clientes (MySQL: ADD COLUMN IF NOT EXISTS no existe, usamos procedimiento)
SET @dbname = DATABASE();
SET @tablename = 'clientes';

-- id_anexo_applicable
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'id_anexo_applicable');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes ADD COLUMN id_anexo_applicable int DEFAULT NULL COMMENT ''Anexo aplicable''', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- expediente_simplificado
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'expediente_simplificado');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes ADD COLUMN expediente_simplificado tinyint(1) DEFAULT 0 COMMENT ''1=Simplificado''', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- documentos_vistos_original_certificado
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'documentos_vistos_original_certificado');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes ADD COLUMN documentos_vistos_original_certificado tinyint(1) DEFAULT 0 COMMENT ''Documentos vistos original/certificado''', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fecha_documentos_vistos
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'fecha_documentos_vistos');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes ADD COLUMN fecha_documentos_vistos date DEFAULT NULL', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- id_usuario_documentos_vistos
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'id_usuario_documentos_vistos');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes ADD COLUMN id_usuario_documentos_vistos int DEFAULT NULL', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- id_manual_politicas_clasificacion
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'id_manual_politicas_clasificacion');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes ADD COLUMN id_manual_politicas_clasificacion int DEFAULT NULL COMMENT ''Manual Políticas para clasificación''', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. AGREGAR COLUMNAS A clientes_fisicas (país nacimiento, fecha ingreso para extranjeros)
SET @tablename = 'clientes_fisicas';
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'id_pais_nacimiento');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes_fisicas ADD COLUMN id_pais_nacimiento int DEFAULT NULL COMMENT ''País de nacimiento (Anexo 3, 5)''', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'fecha_ingreso_pais');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes_fisicas ADD COLUMN fecha_ingreso_pais date DEFAULT NULL COMMENT ''Fecha ingreso a México (Anexo 5 extranjeros)''', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7. AGREGAR id_tipo_residencia A clientes (para PF)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'id_tipo_residencia');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes ADD COLUMN id_tipo_residencia int DEFAULT NULL COMMENT ''Tipo residencia (mexicana, residente, visitante)''', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 8. AGREGAR id_pais_nacionalidad, id_anexo_7a, id_anexo_7_bis_a A clientes_morales
SET @tablename = 'clientes_morales';
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'id_pais_nacionalidad');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes_morales ADD COLUMN id_pais_nacionalidad int DEFAULT NULL COMMENT ''País nacionalidad PM (Anexo 4, 6)''', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'id_anexo_7a');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes_morales ADD COLUMN id_anexo_7a int DEFAULT NULL COMMENT ''Si PM en Anexo 7-A (régimen simplificado)''', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'id_anexo_7_bis_a');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE clientes_morales ADD COLUMN id_anexo_7_bis_a int DEFAULT NULL COMMENT ''Si PM en Anexo 7 Bis-A (ente público)''', 'SELECT 1 AS noop');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 9. TABLA pld_manual_politicas: Versiones del Manual de Políticas (Art. 37)
CREATE TABLE IF NOT EXISTS `pld_manual_politicas` (
  `id_manual` int NOT NULL AUTO_INCREMENT,
  `version` varchar(30) NOT NULL,
  `fecha_vigencia` date NOT NULL,
  `ruta_documento` varchar(500) DEFAULT NULL COMMENT 'Ruta al PDF del manual',
  `criterios_bajo_riesgo_resumen` text COMMENT 'Resumen de criterios documentados',
  `id_status` tinyint(1) DEFAULT 1,
  `fecha_alta` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_manual`),
  UNIQUE KEY `uk_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insertar versión inicial
INSERT IGNORE INTO `pld_manual_politicas` (`id_manual`, `version`, `fecha_vigencia`, `criterios_bajo_riesgo_resumen`, `id_status`) VALUES
(1, 'v1.0', CURDATE(), 'Criterios de clasificación de bajo riesgo según Art. 17 y 37 de las Reglas. Documentar en el Manual las condiciones específicas.', 1);

SELECT 'Migration completed: add_expediente_anexo_kyc_ebr.' AS result;
