<?php
session_start();
require_once '../config/db.php';
require_once '../config/bitacora.php';
require_once '../config/ebr_usuario_helper.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id_usuario_actual = (int)$_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);
$id_factor = $data['id_factor'] ?? 0;
$items = $data['items'] ?? []; 

if (!$id_factor || empty($items)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();

    $usaUsuario = $id_usuario_actual > 0 && ebrTablaUsuarioExiste($pdo);

    if ($usaUsuario) {
        $stmtOld = $pdo->prepare("SELECT * FROM config_riesgo_valores_usuario WHERE id_usuario = ? AND id_factor = ?");
        $stmtOld->execute([$id_usuario_actual, $id_factor]);
        $oldValues = $stmtOld->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            INSERT INTO config_riesgo_valores_usuario (id_usuario, id_factor, id_valor_catalogo, nivel_riesgo)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE nivel_riesgo = VALUES(nivel_riesgo)
        ");
        foreach ($items as $item) {
            $stmt->execute([$id_usuario_actual, $id_factor, $item['id_item'], $item['risk']]);
        }

        $stmtNew = $pdo->prepare("SELECT * FROM config_riesgo_valores_usuario WHERE id_usuario = ? AND id_factor = ?");
        $stmtNew->execute([$id_usuario_actual, $id_factor]);
        $newValues = $stmtNew->fetchAll(PDO::FETCH_ASSOC);
        logChange($pdo, $id_usuario_actual, "ACTUALIZAR_VALORES_FACTOR", "config_riesgo_valores_usuario", $id_factor, $oldValues, $newValues);
    } else {
        $stmtOld = $pdo->prepare("SELECT * FROM config_riesgo_valores WHERE id_factor = ?");
        $stmtOld->execute([$id_factor]);
        $oldValues = $stmtOld->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            INSERT INTO config_riesgo_valores (id_factor, id_valor_catalogo, nivel_riesgo) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE nivel_riesgo = VALUES(nivel_riesgo)
        ");
        foreach ($items as $item) {
            $stmt->execute([$id_factor, $item['id_item'], $item['risk']]);
        }

        $stmtNew = $pdo->prepare("SELECT * FROM config_riesgo_valores WHERE id_factor = ?");
        $stmtNew->execute([$id_factor]);
        $newValues = $stmtNew->fetchAll(PDO::FETCH_ASSOC);
        logChange($pdo, $id_usuario_actual, "ACTUALIZAR_VALORES_FACTOR", "config_riesgo_valores", $id_factor, $oldValues, $newValues);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>