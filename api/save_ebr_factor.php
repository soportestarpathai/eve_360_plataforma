<?php
session_start();
require_once '../config/db.php';
require_once '../config/bitacora.php'; // Include Logger
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id_usuario_actual = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? ''; 

try {
    $pdo->beginTransaction();

    if ($action === 'add') {
        $nombre = $data['nombre'];
        $peso = $data['peso'];
        $tabla = $data['tabla'] ?? null;
        $clave = $data['clave'] ?? null;
        $campo_nombre = $data['campo_nombre'] ?? null;

        $stmt = $pdo->prepare("INSERT INTO config_factores_riesgo (nombre_factor, peso_porcentaje, tabla_catalogo, campo_clave, campo_nombre) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $peso, $tabla, $clave, $campo_nombre]);
        $id_factor = $pdo->lastInsertId();

        // Log Creation
        $newFactor = ['id_factor' => $id_factor, 'nombre_factor' => $nombre, 'peso_porcentaje' => $peso, 'tabla_catalogo' => $tabla];
        logChange($pdo, $id_usuario_actual, "CREAR_FACTOR", "config_factores_riesgo", $id_factor, null, $newFactor);

        echo json_encode(['status' => 'success', 'message' => 'Factor creado']);
    } 
    elseif ($action === 'update') {
        $id = $data['id_factor'];
        $nombre = $data['nombre'];
        $peso = $data['peso'];
        // New fields for update
        $tabla = $data['tabla'] ?? null;
        $clave = $data['clave'] ?? null;
        $campo_nombre = $data['campo_nombre'] ?? null;

        // Fetch Old Data
        $stmtOld = $pdo->prepare("SELECT * FROM config_factores_riesgo WHERE id_factor = ?");
        $stmtOld->execute([$id]);
        $oldFactor = $stmtOld->fetch(PDO::FETCH_ASSOC);

        // --- FIXED UPDATE QUERY ---
        $stmt = $pdo->prepare("UPDATE config_factores_riesgo SET nombre_factor = ?, peso_porcentaje = ?, tabla_catalogo = ?, campo_clave = ?, campo_nombre = ? WHERE id_factor = ?");
        $stmt->execute([$nombre, $peso, $tabla, $clave, $campo_nombre, $id]);
        
        // Log Update
        $newFactor = $oldFactor; // Copy structure
        $newFactor['nombre_factor'] = $nombre;
        $newFactor['peso_porcentaje'] = $peso;
        $newFactor['tabla_catalogo'] = $tabla;
        $newFactor['campo_clave'] = $clave;
        $newFactor['campo_nombre'] = $campo_nombre;
        
        logChange($pdo, $id_usuario_actual, "ACTUALIZAR_FACTOR", "config_factores_riesgo", $id, $oldFactor, $newFactor);

        echo json_encode(['status' => 'success', 'message' => 'Factor actualizado']);
    }
    elseif ($action === 'delete') {
        $id = $data['id_factor'];
        
        // Fetch Old Data
        $stmtOld = $pdo->prepare("SELECT * FROM config_factores_riesgo WHERE id_factor = ?");
        $stmtOld->execute([$id]);
        $oldFactor = $stmtOld->fetch(PDO::FETCH_ASSOC);
        
        // Fetch Old Values (Cascade delete simulation for logging)
        $stmtOldVals = $pdo->prepare("SELECT * FROM config_riesgo_valores WHERE id_factor = ?");
        $stmtOldVals->execute([$id]);
        $oldValues = $stmtOldVals->fetchAll(PDO::FETCH_ASSOC);

        // Delete
        $pdo->prepare("DELETE FROM config_riesgo_valores WHERE id_factor = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM config_factores_riesgo WHERE id_factor = ?")->execute([$id]);
        
        // Log Deletion (Log factor and values separately or together)
        logChange($pdo, $id_usuario_actual, "ELIMINAR_FACTOR", "config_factores_riesgo", $id, $oldFactor, null);
        if(!empty($oldValues)) {
            logChange($pdo, $id_usuario_actual, "ELIMINAR_VALORES_FACTOR", "config_riesgo_valores", $id, $oldValues, null);
        }

        echo json_encode(['status' => 'success', 'message' => 'Factor eliminado']);
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>