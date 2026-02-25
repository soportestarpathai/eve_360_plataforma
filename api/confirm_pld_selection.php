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

function getRequestData(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'multipart/form-data') !== false) {
        return $_POST ?? [];
    }
    $raw = file_get_contents("php://input");
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function hasColumn(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function saveSupportFile(int $idBusqueda): ?string {
    if (!isset($_FILES['documento_soporte']) || !is_array($_FILES['documento_soporte'])) {
        return null;
    }

    $file = $_FILES['documento_soporte'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir documento de soporte (código: ' . $file['error'] . ').');
    }

    $maxBytes = 10 * 1024 * 1024; // 10 MB
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new Exception('El documento de soporte excede 10 MB.');
    }

    $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed, true)) {
        throw new Exception('Tipo de archivo no permitido para documento de soporte.');
    }

    $baseName = pathinfo($file['name'] ?? 'soporte', PATHINFO_FILENAME);
    $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
    $safeBase = trim($safeBase, '_');
    if ($safeBase === '') {
        $safeBase = 'soporte';
    }

    $targetDir = dirname(__DIR__) . '/uploads/pld_soportes/';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
        throw new Exception('No fue posible crear la carpeta de soportes PLD.');
    }

    $fileName = date('Ymd_His') . '_busq_' . $idBusqueda . '_' . $safeBase . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $targetDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('No fue posible guardar el documento de soporte.');
    }

    return 'uploads/pld_soportes/' . $fileName;
}

$data = getRequestData();
$id_busqueda = (int)($data['id_busqueda'] ?? 0);
$seleccion = $data['seleccion'] ?? '';
$comentarios = trim((string)($data['comentarios'] ?? ''));
$riesgo_final = (int)($data['riesgo_final'] ?? 0);

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

    $documento_soporte = saveSupportFile($id_busqueda);
    $hasDocumentoSoporte = hasColumn($pdo, 'clientes_busquedas_listas', 'documento_soporte');
    $comentariosGuardar = $comentarios;
    if ($documento_soporte && !$hasDocumentoSoporte) {
        $comentariosGuardar = trim($comentariosGuardar . "\n[Documento de soporte: " . $documento_soporte . "]");
    }

    if ($hasDocumentoSoporte) {
        $stmt = $pdo->prepare("UPDATE clientes_busquedas_listas SET coincidencia_seleccionada = ?, riesgo_detectado = ?, comentarios = ?, documento_soporte = ? WHERE id_busqueda = ?");
        $stmt->execute([$seleccion, $riesgo_final, $comentariosGuardar, $documento_soporte, $id_busqueda]);
    } else {
        $stmt = $pdo->prepare("UPDATE clientes_busquedas_listas SET coincidencia_seleccionada = ?, riesgo_detectado = ?, comentarios = ? WHERE id_busqueda = ?");
        $stmt->execute([$seleccion, $riesgo_final, $comentariosGuardar, $id_busqueda]);
    }
    
    // Log Change
    $newSearch = $oldSearch;
    $newSearch['coincidencia_seleccionada'] = $seleccion;
    $newSearch['riesgo_detectado'] = $riesgo_final;
    $newSearch['comentarios'] = $comentariosGuardar;
    if ($hasDocumentoSoporte) {
        $newSearch['documento_soporte'] = $documento_soporte;
    } elseif ($documento_soporte) {
        $newSearch['documento_soporte'] = $documento_soporte;
    }
    
    logChange($pdo, $id_usuario_actual, "CONFIRMAR_PLD", "clientes_busquedas_listas", $id_busqueda, $oldSearch, $newSearch);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
