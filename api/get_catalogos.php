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
    
    // Fetch vulnerable activities filtered by user's assigned fracciones
    try {
        require_once __DIR__ . '/../config/pld_permisos.php';
        $userId = $_SESSION['user_id'] ?? null;
        $userFracciones = $userId ? getUserFraccionesPLD($pdo, $userId) : [];

        if (!empty($userFracciones)) {
            $placeholders = implode(',', array_fill(0, count($userFracciones), '?'));
            $stmtVuln = $pdo->prepare("SELECT * FROM cat_vulnerables WHERE fraccion IN ($placeholders) ORDER BY nombre ASC");
            $stmtVuln->execute($userFracciones);
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
