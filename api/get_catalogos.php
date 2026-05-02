<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $catalogs = [];

    // Helper function to fetch a table
    // FIXED: Order by 'nombre' to avoid column name errors with IDs
    function fetchCatalog($pdo, $tableName) {
        // Ensure the table exists to prevent crashes
        try {
            $stmt = $pdo->query("SELECT * FROM $tableName ORDER BY nombre ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // If table doesn't exist or has no 'nombre' column, return empty array to prevent full crash
            return [];
        }
    }

    // Fetch all required catalogs
    $catalogs['tipos_persona'] = fetchCatalog($pdo, 'cat_tipo_persona');
    $catalogs['paises'] = fetchCatalog($pdo, 'cat_pais');
    $catalogs['tipos_identificacion'] = fetchCatalog($pdo, 'cat_tipo_identificaciones');
    $catalogs['tipos_contacto'] = fetchCatalog($pdo, 'cat_tipo_contacto');
    $catalogs['actividades'] = fetchCatalog($pdo, 'cat_actividades');
    $catalogs['ocupaciones'] = fetchCatalog($pdo, 'cat_ocupacion');
    $catalogs['profesiones'] = fetchCatalog($pdo, 'cat_profesion');
    $catalogs['origenes_recursos'] = fetchCatalog($pdo, 'cat_origen_recursos');

    // Catálogos KYC/Expediente por Anexo (Reglas Art. 12)
    try {
        $catalogs['anexos_expediente'] = fetchCatalog($pdo, 'cat_anexo_expediente');
    } catch (Exception $e) { $catalogs['anexos_expediente'] = []; }
    try {
        $catalogs['tipos_residencia'] = fetchCatalog($pdo, 'cat_tipo_residencia');
    } catch (Exception $e) { $catalogs['tipos_residencia'] = []; }
    try {
        $catalogs['anexo_7a'] = $pdo->query("SELECT * FROM cat_anexo_7a WHERE id_status = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $catalogs['anexo_7a'] = []; }
    try {
        $catalogs['anexo_7_bis_a'] = $pdo->query("SELECT * FROM cat_anexo_7_bis_a WHERE id_status = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $catalogs['anexo_7_bis_a'] = []; }
    try {
        $catalogs['manual_politicas'] = $pdo->query("SELECT id_manual, version, fecha_vigencia FROM pld_manual_politicas WHERE id_status = 1 ORDER BY fecha_vigencia DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $catalogs['manual_politicas'] = []; }

    // Plantillas de documentos requeridos por tipo (KYC - Art. 12 RCG)
    require_once __DIR__ . '/../config/expediente_documentos_por_anexo.php';
    $catalogs['documentos_template_fisica'] = function_exists('getDocumentosTemplatePorTipo') ? getDocumentosTemplatePorTipo('fisica') : [];
    $catalogs['documentos_template_moral'] = function_exists('getDocumentosTemplatePorTipo') ? getDocumentosTemplatePorTipo('moral') : [];
    $catalogs['documentos_template_fideicomiso'] = function_exists('getDocumentosTemplatePorTipo') ? getDocumentosTemplatePorTipo('fideicomiso') : [];
    
    // Fetch vulnerable activities filtered by user's assigned fracciones
    try {
        require_once __DIR__ . '/../config/pld_permisos.php';
        // Asegurar que exista Fracción I (JYS) en cat_vulnerables
        $chkI = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'I' AND (nombre LIKE '%JYS%' OR nombre LIKE '%Juegos%') LIMIT 1");
        if (!$chkI || !$chkI->fetch()) {
            $chkI2 = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'I' LIMIT 1");
            if (!$chkI2 || !$chkI2->fetch()) {
                try {
                    $pdo->exec("
                        INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                        VALUES ('Juegos con apuesta, concursos o sorteos (JYS)', 'I', 645.00, 645.00)
                    ");
                } catch (Exception $e) { /* ignorar si falla */ }
            }
        }

        // Asegurar que exista Fracción III (CHV) en cat_vulnerables
        $chkIII = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'III' AND (nombre LIKE '%CHV%' OR nombre LIKE '%Cheques%') LIMIT 1");
        if (!$chkIII || !$chkIII->fetch()) {
            $chkIII2 = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'III' LIMIT 1");
            if (!$chkIII2 || !$chkIII2->fetch()) {
                try {
                    $pdo->exec("
                        INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                        VALUES ('Cheques de viajero (CHV)', 'III', 645.00, 645.00)
                    ");
                } catch (Exception $e) { /* ignorar si falla */ }
            }
        }

        // Asegurar que exista Fracción VII (OBA) en cat_vulnerables
        $chkVII = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'VII' AND (nombre LIKE '%OBA%' OR nombre LIKE '%Arte%' OR nombre LIKE '%Obras%') LIMIT 1");
        if (!$chkVII || !$chkVII->fetch()) {
            $chkVII2 = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'VII' LIMIT 1");
            if (!$chkVII2 || !$chkVII2->fetch()) {
                try {
                    $pdo->exec("
                        INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                        VALUES ('Subasta o comercialización de obras de arte (OBA)', 'VII', 4815.00, 4815.00)
                    ");
                } catch (Exception $e) { /* ignorar si falla */ }
            }
        }

        // Asegurar que exista Fracción V Bis (INM) como actividad independiente de DIN
        $chkVBis = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'V Bis' AND (nombre LIKE '%INM%' OR nombre LIKE '%desarrollo inmobiliario%' OR nombre LIKE '%Recepci%n de recursos%') LIMIT 1");
        if (!$chkVBis || !$chkVBis->fetch()) {
            $chkVBis2 = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'V Bis' LIMIT 1");
            if (!$chkVBis2 || !$chkVBis2->fetch()) {
                try {
                    $pdo->exec("
                        INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                        VALUES ('Recepción de recursos para desarrollo inmobiliario (INM)', 'V Bis', 8025.00, 8025.00)
                    ");
                } catch (Exception $e) { /* ignorar si falla */ }
            }
        }

        // Asegurar que exista Fracción XI (SPR) en cat_vulnerables
        $chkXI = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'XI' AND (nombre LIKE '%SPR%' OR nombre LIKE '%Servicios Profesionales%') LIMIT 1");
        if (!$chkXI || !$chkXI->fetch()) {
            $chkXI2 = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'XI' LIMIT 1");
            if (!$chkXI2 || !$chkXI2->fetch()) {
                try {
                    $pdo->exec("
                        INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                        VALUES ('Servicios Profesionales (SPR)', 'XI', 1605.00, 1605.00)
                    ");
                } catch (Exception $e) { /* ignorar si falla */ }
            }
        }

        // Asegurar que exista Fracción IX (BLI) en cat_vulnerables
        $chkIX = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'IX' AND (nombre LIKE '%BLI%' OR nombre LIKE '%Blindaje%') LIMIT 1");
        if (!$chkIX || !$chkIX->fetch()) {
            $chkIX2 = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'IX' LIMIT 1");
            if (!$chkIX2 || !$chkIX2->fetch()) {
                try {
                    $pdo->exec("
                        INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                        VALUES ('Servicios de blindaje de vehículos e inmuebles (BLI)', 'IX', 4815.00, 4815.00)
                    ");
                } catch (Exception $e) { /* ignorar si falla */ }
            }
        }

        // Asegurar que exista Fracción X (TCV) en cat_vulnerables
        $chkX = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'X' AND (nombre LIKE '%TCV%' OR nombre LIKE '%Traslado%' OR nombre LIKE '%custodia%') LIMIT 1");
        if (!$chkX || !$chkX->fetch()) {
            $chkX2 = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'X' LIMIT 1");
            if (!$chkX2 || !$chkX2->fetch()) {
                try {
                    $pdo->exec("
                        INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                        VALUES ('Traslado o custodia de dinero o valores (TCV)', 'X', 3210.00, 3210.00)
                    ");
                } catch (Exception $e) { /* ignorar si falla */ }
            }
        }

        // Asegurar que exista Fracción XIV (ADU) en cat_vulnerables
        $chkXIV = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'XIV' AND (nombre LIKE '%ADU%' OR nombre LIKE '%Comercio exterior%' OR nombre LIKE '%aduanal%') LIMIT 1");
        if (!$chkXIV || !$chkXIV->fetch()) {
            $chkXIV2 = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'XIV' LIMIT 1");
            if (!$chkXIV2 || !$chkXIV2->fetch()) {
                try {
                    $pdo->exec("
                        INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                        VALUES ('Servicios de comercio exterior (ADU)', 'XIV', 0.00, 0.00)
                    ");
                } catch (Exception $e) { /* ignorar si falla */ }
            }
        }

        // Asegurar que exista Fracción XV (ARI) en cat_vulnerables
        $chkXV = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'XV' AND (nombre LIKE '%ARI%' OR nombre LIKE '%uso o goce%' OR nombre LIKE '%arrendamiento%') LIMIT 1");
        if (!$chkXV || !$chkXV->fetch()) {
            $chkXV2 = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'XV' LIMIT 1");
            if (!$chkXV2 || !$chkXV2->fetch()) {
                try {
                    $pdo->exec("
                        INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                        VALUES ('Derechos personales de uso o goce de bienes inmuebles (ARI)', 'XV', 3210.00, 3210.00)
                    ");
                } catch (Exception $e) { /* ignorar si falla */ }
            }
        }

        // Asegurar que exista Fracción XII (FEP) en cat_vulnerables
        $chkXII = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'XII' AND (nombre LIKE '%FEP%' OR nombre LIKE '%Notario%' OR nombre LIKE '%Corredor%' OR nombre LIKE '%Fe p%blica%') LIMIT 1");
        if (!$chkXII || !$chkXII->fetch()) {
            $chkXII2 = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'XII' LIMIT 1");
            if (!$chkXII2 || !$chkXII2->fetch()) {
                try {
                    $pdo->exec("
                        INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                        VALUES ('Fe publica - Notarios y Corredores Publicos (FEP)', 'XII', 0.00, 0.00)
                    ");
                } catch (Exception $e) { }
            }
        }
        // Asegurar que exista Fracción XII (FES) en cat_vulnerables
        $chkXIIFes = $pdo->query("SELECT 1 FROM cat_vulnerables WHERE fraccion = 'XII' AND (nombre LIKE '%FES%' OR nombre LIKE '%Servidor%') LIMIT 1");
        if (!$chkXIIFes || !$chkXIIFes->fetch()) {
            try {
                $pdo->exec("
                    INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                    VALUES ('Fe publica - Servidores Publicos (FES)', 'XII', 0.00, 0.00)
                ");
            } catch (Exception $e) { }
        }
        $userId = $_SESSION['user_id'] ?? null;
        $userFracciones = $userId ? getUserFraccionesPLD($pdo, $userId) : [];

        // Mostrar vulnerables solo según fracciones permitidas al usuario.
        $fraccionesParaFiltrar = $userFracciones;
        if (!empty($fraccionesParaFiltrar)) {
            $placeholders = implode(',', array_fill(0, count($fraccionesParaFiltrar), '?'));
            $stmtVuln = $pdo->prepare("SELECT * FROM cat_vulnerables WHERE fraccion IN ($placeholders) ORDER BY nombre ASC");
            $stmtVuln->execute($fraccionesParaFiltrar);
            $catalogs['vulnerables'] = $stmtVuln->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $catalogs['vulnerables'] = [];
        }
        $catalogs['user_fracciones'] = $userFracciones;
    } catch (PDOException $e) {
        $catalogs['vulnerables'] = [];
        $catalogs['user_fracciones'] = [];
    }

    echo json_encode(['status' => 'success', 'data' => $catalogs]);

} catch (Exception $e) {
    error_log('get_catalogos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al cargar catálogos.']);
}
?>
