<?php
/**
 * Deshace un cambio registrado en bitácora (restaura valor_anterior).
 * Solo administradores o responsables PLD pueden deshacer.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/pld_permisos.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id_usuario_actual = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true) ?: $_GET;
$id_bitacora = (int)($data['id_bitacora'] ?? $data['id'] ?? 0);

if (!$id_bitacora) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID de bitácora requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM bitacora WHERE id_bitacora = ?");
    $stmt->execute([$id_bitacora]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reg) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Registro de bitácora no encontrado']);
        exit;
    }

    $tieneDeshacer = isset($reg['deshacer_aplicado']);
    if ($tieneDeshacer && !empty($reg['deshacer_aplicado'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Este cambio ya fue deshecho']);
        exit;
    }

    $valor_anterior = json_decode($reg['valor_anterior'], true);
    if (!$valor_anterior || !is_array($valor_anterior)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No se puede deshacer: valor anterior no disponible']);
        exit;
    }

    $tabla = $reg['tabla_afectada'];
    $id_afectado = (int)$reg['id_afectado'];
    $id_cliente = null;
    if ($tabla === 'avisos_pld' || $tabla === 'operaciones_pld' || $tabla === 'clientes') {
        $id_cliente = (int)($valor_anterior['id_cliente'] ?? $id_afectado);
    }

    $allowed = ['avisos_pld', 'operaciones_pld', 'clientes'];
    if (!in_array($tabla, $allowed)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Solo se puede deshacer en avisos_pld, operaciones_pld o clientes']);
        exit;
    }

    if ($tabla === 'clientes') {
        $stmtPerm = $pdo->prepare("
            SELECT COALESCE(administracion, 0) AS administracion
            FROM usuarios_permisos
            WHERE id_usuario = ?
            LIMIT 1
        ");
        $stmtPerm->execute([$id_usuario_actual]);
        $perm = $stmtPerm->fetch(PDO::FETCH_ASSOC);
        if (!$perm || (int)$perm['administracion'] <= 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Solo administradores pueden deshacer bajas de clientes']);
            exit;
        }
    } else {
        if (!canModifyPLD($pdo, $id_usuario_actual, $id_cliente ?: null)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => mensajeSinPermisoPLD()]);
            exit;
        }
    }

    $pk = 'id_operacion';
    if ($tabla === 'avisos_pld') $pk = 'id_aviso';
    if ($tabla === 'clientes') $pk = 'id_cliente';

    $allowedColumns = [
        'avisos_pld' => ['folio_sppld', 'fecha_presentacion', 'estatus', 'monto', 'fecha_operacion', 'tipo_operacion', 'id_status', 'id_aviso_generado'],
        'operaciones_pld' => ['monto', 'fecha_operacion', 'tipo_operacion', 'id_status', 'id_aviso_generado'],
        'clientes' => ['id_status', 'fecha_baja']
    ];
    $tableColumns = $allowedColumns[$tabla] ?? [];

    $cols = [];
    $vals = [];
    foreach ($valor_anterior as $k => $v) {
        if ($k === $pk) continue;
        if (!in_array($k, $tableColumns, true)) {
            continue;
        }
        $cols[] = "`$k` = ?";
        $vals[] = $v;
    }
    if (empty($cols)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No hay campos restaurables']);
        exit;
    }
    $vals[] = $id_afectado;

    $sql = "UPDATE $tabla SET " . implode(', ', $cols) . " WHERE $pk = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($vals);

    if ($tieneDeshacer) {
        $pdo->prepare("UPDATE bitacora SET deshacer_aplicado = 1 WHERE id_bitacora = ?")->execute([$id_bitacora]);
    }

    logChange($pdo, $id_usuario_actual, 'DESHACER_CAMBIO_PLD', 'bitacora', $id_bitacora,
        ['id_bitacora' => $id_bitacora, 'tabla' => $tabla, 'id_afectado' => $id_afectado],
        ['restaurado' => true, 'tabla' => $tabla, 'id_afectado' => $id_afectado]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Cambio deshecho correctamente',
        'tabla' => $tabla,
        'id_afectado' => $id_afectado
    ]);

} catch (Exception $e) {
    error_log("deshacer_cambio_pld: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al deshacer: ' . $e->getMessage()]);
}
