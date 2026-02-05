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
    
    // Fetch vulnerable activities (fracciones PLD)
    try {
        $stmtVuln = $pdo->query("SELECT * FROM cat_vulnerables ORDER BY nombre ASC");
        $catalogs['vulnerables'] = $stmtVuln->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If table doesn't exist, return empty array
        $catalogs['vulnerables'] = [];
    }

    echo json_encode(['status' => 'success', 'data' => $catalogs]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>