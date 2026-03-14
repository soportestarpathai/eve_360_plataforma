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
        $userId = $_SESSION['user_id'] ?? null;
        $userFracciones = $userId ? getUserFraccionesPLD($pdo, $userId) : [];

        // Mostrar vulnerables según fracciones del usuario; si vacío, usar fracciones de la empresa (DIN, TSC, SPR)
        $fraccionesParaFiltrar = $userFracciones;
        if (empty($fraccionesParaFiltrar)) {
            $stmtCfg = $pdo->query("SELECT fracciones_activas FROM config_empresa WHERE id_config = 1");
            $rowCfg = $stmtCfg ? $stmtCfg->fetch(PDO::FETCH_ASSOC) : null;
            if ($rowCfg && !empty($rowCfg['fracciones_activas'])) {
                $dec = json_decode($rowCfg['fracciones_activas'], true);
                $fraccionesParaFiltrar = is_array($dec) ? $dec : [];
            }
            if (empty($fraccionesParaFiltrar)) {
                // Todas las fracciones implementadas (umbrales/config): II, V, V Bis, VI, XI, XIII
                $fraccionesParaFiltrar = ['II', 'V', 'V Bis', 'VI', 'XI', 'XIII'];
            }
        }
        if (!empty($fraccionesParaFiltrar)) {
            $placeholders = implode(',', array_fill(0, count($fraccionesParaFiltrar), '?'));
            $stmtVuln = $pdo->prepare("SELECT * FROM cat_vulnerables WHERE fraccion IN ($placeholders) ORDER BY nombre ASC");
            $stmtVuln->execute($fraccionesParaFiltrar);
        } else {
            $stmtVuln = $pdo->query("SELECT * FROM cat_vulnerables ORDER BY nombre ASC");
        }
        $catalogs['vulnerables'] = $stmtVuln->fetchAll(PDO::FETCH_ASSOC);
        $catalogs['user_fracciones'] = $userFracciones;
    } catch (PDOException $e) {
        $catalogs['vulnerables'] = [];
        $catalogs['user_fracciones'] = [];
    }

    echo json_encode(['status' => 'success', 'data' => $catalogs]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
