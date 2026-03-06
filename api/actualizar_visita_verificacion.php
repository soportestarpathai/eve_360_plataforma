<?php
/**
 * API: Actualizar visita de verificación (VAL-PLD-014)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/bitacora.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    $id_usuario = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id_visita = (int)($input['id_visita'] ?? 0);

    if ($id_visita <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'ID de visita inválido']);
        exit;
    }

    // Verificar que la visita existe y pertenece al usuario (no admins)
    $stmtAdmin = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
    $stmtAdmin->execute([$id_usuario]);
    $perm = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    $isAdmin = $perm && (int)($perm['administracion'] ?? 0) > 0;

    $stmtCol = $pdo->query("SHOW COLUMNS FROM visitas_verificacion_pld LIKE 'id_usuario'");
    $tieneIdUsuario = $stmtCol->rowCount() > 0;

    if (!$isAdmin) {
        if (!$tieneIdUsuario) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No se puede verificar la propiedad de la visita. Contacte al administrador.']);
            exit;
        }
        $chk = $pdo->prepare("SELECT id_visita FROM visitas_verificacion_pld WHERE id_visita = ? AND id_usuario = ? AND id_status = 1");
        $chk->execute([$id_visita, $id_usuario]);
        if (!$chk->fetch()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No puede editar esta visita']);
            exit;
        }
    } else {
        $chk = $pdo->prepare("SELECT id_visita FROM visitas_verificacion_pld WHERE id_visita = ? AND id_status = 1");
        $chk->execute([$id_visita]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Visita no encontrada']);
            exit;
        }
    }

    $fecha_visita = $input['fecha_visita'] ?? null;
    if (!$fecha_visita) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Fecha de visita es requerida']);
        exit;
    }

    $autoridad = $input['autoridad'] ?? null;
    $tipo_requerimiento = $input['tipo_requerimiento'] ?? null;
    $expedientes_solicitados = $input['expedientes_solicitados'] ?? null;
    $observaciones = $input['observaciones'] ?? null;

    $expedientesJson = null;
    if (!empty($expedientes_solicitados)) {
        $arr = is_array($expedientes_solicitados) ? $expedientes_solicitados : (is_string($expedientes_solicitados) ? json_decode($expedientes_solicitados, true) : []);
        if (!is_array($arr)) {
            $arr = array_filter(array_map('intval', array_map('trim', explode(',', (string)$expedientes_solicitados))));
        }
        $arr = array_values(array_filter(array_map('intval', $arr)));

        // Validar que expedientes solicitados pertenezcan al usuario (no admins)
        if (!empty($arr) && !$isAdmin) {
            foreach ($arr as $id_cli) {
                $idCliente = (int)$id_cli;
                if ($idCliente > 0) {
                    $chk = $pdo->prepare("SELECT 1 FROM clientes WHERE id_cliente = ? AND id_usuario = ?");
                    $chk->execute([$idCliente, $id_usuario]);
                    if (!$chk->fetch()) {
                        http_response_code(403);
                        echo json_encode(['status' => 'error', 'message' => 'Uno o más expedientes (clientes) no pertenecen a su cartera']);
                        exit;
                    }
                }
            }
        }

        $expedientesJson = !empty($arr) ? json_encode($arr) : null;
    }

    $stmtColObs = $pdo->query("SHOW COLUMNS FROM visitas_verificacion_pld LIKE 'observaciones'");
    $tieneObservaciones = $stmtColObs->rowCount() > 0;

    if ($tieneObservaciones) {
        $stmt = $pdo->prepare("UPDATE visitas_verificacion_pld SET fecha_visita = ?, autoridad = ?, tipo_requerimiento = ?, expedientes_solicitados = ?, observaciones = ? WHERE id_visita = ?");
        $stmt->execute([$fecha_visita, $autoridad, $tipo_requerimiento, $expedientesJson, $observaciones, $id_visita]);
    } else {
        $stmt = $pdo->prepare("UPDATE visitas_verificacion_pld SET fecha_visita = ?, autoridad = ?, tipo_requerimiento = ?, expedientes_solicitados = ? WHERE id_visita = ?");
        $stmt->execute([$fecha_visita, $autoridad, $tipo_requerimiento, $expedientesJson, $id_visita]);
    }

    logChange($pdo, (int)$id_usuario, 'ACTUALIZAR_VISITA_VERIFICACION', 'visitas_verificacion_pld', $id_visita, null, null);

    echo json_encode(['status' => 'success', 'message' => 'Visita actualizada', 'id_visita' => $id_visita]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    error_log("actualizar_visita_verificacion.php: " . $e->getMessage());
}
