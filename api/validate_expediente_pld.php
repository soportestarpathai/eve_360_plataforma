<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../config/db.php';
require_once '../config/pld_expediente.php';
require_once '../config/expediente_documentos_por_anexo.php';
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

// Verificar conexión a la base de datos
if (!isset($pdo) || $pdo === null) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id_cliente = $_GET['id_cliente'] ?? $_POST['id_cliente'] ?? null;

if (!$id_cliente) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de cliente requerido']);
    exit;
}

try {
    // Validar completitud (VAL-PLD-005)
    $resultCompleto = validateExpedienteCompleto($pdo, $id_cliente);
    
    // Validar actualización (VAL-PLD-006)
    $resultActualizacion = validateActualizacionExpediente($pdo, $id_cliente);
    
    // Asegurar que faltantes sea un array
    if (!isset($resultCompleto['faltantes']) || !is_array($resultCompleto['faltantes'])) {
        $resultCompleto['faltantes'] = [];
    }

    // Anexo aplicable y check documentos vistos (Art. 12 RCG)
    $anexo = ['id_anexo' => null, 'clave' => null, 'nombre' => null, 'simplificado' => false, 'razon' => null];
    $documentosVistos = false;
    $fechaDocVistos = null;
    if (function_exists('getAnexoApplicable')) {
        $anexo = getAnexoApplicable($pdo, $id_cliente, false);
    }
    try {
        $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'documentos_vistos_original_certificado'");
        if ($chk && $chk->fetchColumn() > 0) {
            $stmtDv = $pdo->prepare("SELECT documentos_vistos_original_certificado, fecha_documentos_vistos FROM clientes WHERE id_cliente = ?");
            $stmtDv->execute([$id_cliente]);
            $rowDv = $stmtDv->fetch(PDO::FETCH_ASSOC);
            if ($rowDv) {
                $documentosVistos = !empty($rowDv['documentos_vistos_original_certificado']);
                $fechaDocVistos = $rowDv['fecha_documentos_vistos'] ?? null;
            }
        }
    } catch (Exception $e) { /* columnas no existen aún */ }
    
    // Documentos requeridos según anexo aplicable (Reglas KYC EVE360)
    $documentosRequeridos = [];
    if (function_exists('getDocumentosRequeridosPorAnexo') && !empty($anexo['clave'])) {
        $documentosRequeridos = getDocumentosRequeridosPorAnexo($anexo['clave']);
    }

    // Log para debug
    error_log("API validate_expediente_pld Cliente $id_cliente: " .
             "Completo=" . ($resultCompleto['completo'] ? 'SÍ' : 'NO') . ", " .
             "Faltantes=" . count($resultCompleto['faltantes']) . ", " .
             "Actualizado=" . ($resultActualizacion['actualizado'] ? 'SÍ' : 'NO'));
    
    echo json_encode([
        'status' => 'success',
        'completitud' => $resultCompleto,
        'actualizacion' => $resultActualizacion,
        'valido' => $resultCompleto['completo'] && $resultActualizacion['actualizado'],
        'anexo' => $anexo,
        'documentos_vistos' => $documentosVistos,
        'fecha_documentos_vistos' => $fechaDocVistos,
        'documentos_requeridos' => $documentosRequeridos
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en validate_expediente_pld.php: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno al validar expediente.'
    ]);
} catch (Error $e) {
    http_response_code(500);
    error_log("Error fatal en validate_expediente_pld.php: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno al validar expediente.'
    ]);
}
