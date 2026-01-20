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
$id_busqueda = $data['id_busqueda'] ?? 0;
$seleccion = $data['seleccion'] ?? ''; 
$comentarios = $data['comentarios'] ?? '';
$riesgo_final = $data['riesgo_final'] ?? 0; 

if (!$id_busqueda) {
    echo json_encode(['status' => 'error', 'message' => 'ID Busqueda Missing']);
    exit;
}

try {
    // Fetch OLD state
    $stmtOld = $pdo->prepare("SELECT * FROM clientes_busquedas_listas WHERE id_busqueda = ?");
    $stmtOld->execute([$id_busqueda]);
    $oldSearch = $stmtOld->fetch(PDO::FETCH_ASSOC);

    if (is_array($seleccion) || is_object($seleccion)) {
        $seleccion = json_encode($seleccion, JSON_UNESCAPED_UNICODE);
    }

    $stmt = $pdo->prepare("UPDATE clientes_busquedas_listas SET coincidencia_seleccionada = ?, riesgo_detectado = ?, comentarios = ? WHERE id_busqueda = ?");
    $stmt->execute([$seleccion, $riesgo_final, $comentarios, $id_busqueda]);
    
    // Log Change
    $newSearch = $oldSearch;
    $newSearch['coincidencia_seleccionada'] = $seleccion;
    $newSearch['riesgo_detectado'] = $riesgo_final;
    $newSearch['comentarios'] = $comentarios;
    
    logChange($pdo, $id_usuario_actual, "CONFIRMAR_PLD", "clientes_busquedas_listas", $id_busqueda, $oldSearch, $newSearch);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>