<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$input = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (json_decode(file_get_contents('php://input'), true) ?: $_POST)
    : $_GET;

$noContrato = trim((string)($input['no_contrato'] ?? ''));
$excludeId = (int)($input['exclude_id'] ?? 0);

if ($noContrato === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No. de contrato requerido']);
    exit;
}

try {
    if ($excludeId > 0) {
        $stmt = $pdo->prepare("
            SELECT id_cliente, id_status
            FROM clientes
            WHERE no_contrato = ? AND id_cliente <> ?
            LIMIT 1
        ");
        $stmt->execute([$noContrato, $excludeId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id_cliente, id_status
            FROM clientes
            WHERE no_contrato = ?
            LIMIT 1
        ");
        $stmt->execute([$noContrato]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $exists = (bool)$row;

    echo json_encode([
        'status' => 'success',
        'exists' => $exists,
        'no_contrato' => $noContrato,
        'id_cliente' => $exists ? (int)$row['id_cliente'] : null,
        'id_status' => $exists ? (int)$row['id_status'] : null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
