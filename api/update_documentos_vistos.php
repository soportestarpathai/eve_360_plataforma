<?php
/**
 * API: Marca que el trabajador tuvo a la vista documentos originales o copia certificada
 * Requisito Reglas KYC - check obligatorio antes de celebrar operación
 */
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    require_once __DIR__ . '/../config/db.php';
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }

    $id_cliente = isset($_POST['id_cliente']) ? (int) $_POST['id_cliente'] : 0;
    if ($id_cliente <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'id_cliente requerido']);
        exit;
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'documentos_vistos_original_certificado'");
    if (!$stmt->fetchColumn()) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Ejecute la migración add_expediente_anexo_kyc_ebr.sql']);
        exit;
    }

    $id_usuario = (int) $_SESSION['user_id'];
    $stmtUpdate = $pdo->prepare("
        UPDATE clientes 
        SET documentos_vistos_original_certificado = 1, 
            fecha_documentos_vistos = CURDATE(), 
            id_usuario_documentos_vistos = ?
        WHERE id_cliente = ?
    ");
    $stmtUpdate->execute([$id_usuario, $id_cliente]);

    if ($stmtUpdate->rowCount() === 0) {
        // Puede ser que ya estuviera marcado; confirmar existencia para no devolver falso 404.
        $stmtExiste = $pdo->prepare("SELECT id_cliente FROM clientes WHERE id_cliente = ? LIMIT 1");
        $stmtExiste->execute([$id_cliente]);
        if (!$stmtExiste->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado']);
            exit;
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => $stmtUpdate->rowCount() > 0
            ? 'Documentos marcados como verificados'
            : 'Documentos ya estaban verificados'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
