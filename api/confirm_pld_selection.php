<?php
session_start();
require_once '../config/db.php';
require_once '../config/bitacora.php'; // Include Logger
require_once '../config/pld_middleware.php'; // VAL-PLD-001: Bloqueo de operaciones PLD
require_once '../config/pld_expediente.php'; // VAL-PLD-005, VAL-PLD-006
require_once '../config/pld_beneficiario_controlador.php'; // VAL-PLD-007: Beneficiario Controlador
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// VAL-PLD-001: Bloquear confirmación PLD si no está habilitado
requirePLDHabilitado($pdo, true);

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
    // Fetch OLD state and get client ID
    $stmtOld = $pdo->prepare("SELECT * FROM clientes_busquedas_listas WHERE id_busqueda = ?");
    $stmtOld->execute([$id_busqueda]);
    $oldSearch = $stmtOld->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldSearch) {
        throw new Exception('Búsqueda no encontrada');
    }
    
    // VAL-PLD-005 y VAL-PLD-006: Validar expediente del cliente
    $id_cliente = $oldSearch['id_cliente'] ?? null;
    if ($id_cliente) {
        requireExpedienteCompleto($pdo, $id_cliente, true);
        
        // VAL-PLD-007: Validar beneficiario controlador (si aplica)
        requireBeneficiarioControlador($pdo, $id_cliente, true);
    }

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