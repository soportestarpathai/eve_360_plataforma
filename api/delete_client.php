<?php
/**
 * Baja lógica de cliente.
 * No elimina registros: asigna estatus "Eliminado" (id_status = 4) y registra bitácora.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/bitacora.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$idUsuario = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$idCliente = (int)($input['id_cliente'] ?? $input['id'] ?? 0);

if ($idCliente <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de cliente inválido']);
    exit;
}

try {
    // Validar permisos de catálogo de clientes o administración
    $stmtPerm = $pdo->prepare("
        SELECT COALESCE(catalogo_clientes, 0) AS catalogo_clientes, COALESCE(administracion, 0) AS administracion
        FROM usuarios_permisos
        WHERE id_usuario = ?
        LIMIT 1
    ");
    $stmtPerm->execute([$idUsuario]);
    $perm = $stmtPerm->fetch(PDO::FETCH_ASSOC);
    $canDelete = $perm && (((int)$perm['catalogo_clientes'] > 0) || ((int)$perm['administracion'] > 0));

    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Sin permisos para eliminar clientes']);
        exit;
    }

    $pdo->beginTransaction();

    // Asegurar existencia de estatus "Eliminado" (id_status = 4)
    $stmtStatus = $pdo->prepare("SELECT id_status FROM cat_status WHERE id_status = 4 LIMIT 1");
    $stmtStatus->execute();
    $status4 = $stmtStatus->fetch(PDO::FETCH_ASSOC);

    if (!$status4) {
        $stmtInsertStatus = $pdo->prepare("
            INSERT INTO cat_status (id_status, nombre, es_visible, es_modificable, es_activo)
            VALUES (4, 'Eliminado', 0, 0, 0)
        ");
        $stmtInsertStatus->execute();
    }

    $stmtClient = $pdo->prepare("SELECT * FROM clientes WHERE id_cliente = ? LIMIT 1");
    $stmtClient->execute([$idCliente]);
    $cliente = $stmtClient->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado']);
        exit;
    }

    if ((int)$cliente['id_status'] === 4) {
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'El cliente ya estaba eliminado']);
        exit;
    }

    $before = $cliente;
    $after = $cliente;
    $after['id_status'] = 4;
    $after['fecha_baja'] = date('Y-m-d');

    $stmtDelete = $pdo->prepare("UPDATE clientes SET id_status = 4, fecha_baja = CURDATE() WHERE id_cliente = ?");
    $stmtDelete->execute([$idCliente]);

    logChange($pdo, $idUsuario, 'ELIMINAR_CLIENTE_LOGICO', 'clientes', $idCliente, $before, $after);

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'message' => 'Cliente eliminado de forma lógica correctamente',
        'id_cliente' => $idCliente
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
