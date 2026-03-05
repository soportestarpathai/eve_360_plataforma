<?php
/**
 * API: Registrar visita de verificación (VAL-PLD-014)
 * Si expedientes no disponibles → se registra evento crítico
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_conservacion.php';
require_once __DIR__ . '/../config/bitacora.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    $id_usuario = $_SESSION['user_id'] ?? null;
    
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $fecha_visita = $input['fecha_visita'] ?? null;
    $autoridad = $input['autoridad'] ?? null;
    $tipo_requerimiento = $input['tipo_requerimiento'] ?? null;
    $expedientes_solicitados = $input['expedientes_solicitados'] ?? null;
    $observaciones = $input['observaciones'] ?? null;
    
    if (!$fecha_visita) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Fecha de visita es requerida']);
        exit;
    }

    // Validar que expedientes solicitados pertenezcan al usuario (no admins)
    $expedientesArray = null;
    if (!empty($expedientes_solicitados)) {
        $expedientesArray = is_array($expedientes_solicitados) ? $expedientes_solicitados : json_decode($expedientes_solicitados, true);
        if (is_array($expedientesArray) && $id_usuario) {
            $stmtAdmin = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
            $stmtAdmin->execute([$id_usuario]);
            $perm = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
            $isAdmin = $perm && (int)($perm['administracion'] ?? 0) > 0;
            if (!$isAdmin) {
                foreach ($expedientesArray as $id_cli) {
                    $idCliente = is_array($id_cli) ? (int)($id_cli['id_cliente'] ?? $id_cli) : (int)$id_cli;
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
        }
    }
    
    $data = [
        'fecha_visita' => $fecha_visita,
        'autoridad' => $autoridad,
        'tipo_requerimiento' => $tipo_requerimiento,
        'expedientes_solicitados' => $expedientes_solicitados,
        'observaciones' => $observaciones,
        'id_usuario' => $id_usuario
    ];
    
    $result = registrarVisitaVerificacion($pdo, $data);
    
    if (!$result['success']) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $result['message'] ?? 'Error al registrar visita']);
        exit;
    }
    
    if (!empty($result['evento_critico']) && $id_usuario) {
        logChange($pdo, (int)$id_usuario, 'REGISTRAR_VISITA_VERIFICACION_EVENTO_CRITICO',
            'visitas_verificacion_pld', (int)$result['id_visita'], null,
            ['expedientes_disponibles' => 0, 'evento_critico' => true]);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => $result['message'],
        'id_visita' => $result['id_visita'],
        'expedientes_disponibles' => $result['expedientes_disponibles'] ?? true,
        'evento_critico' => $result['evento_critico'] ?? false
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    error_log("registrar_visita_verificacion.php: " . $e->getMessage());
}
