<?php
/**
 * API Endpoint: Registrar Informe de No Operaciones (VAL-PLD-012)
 * Registra un informe cuando no hubo operaciones avisables en un periodo
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_avisos.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/pld_middleware.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// VAL-PLD-001: Bloquear si no está habilitado
requirePLDHabilitado($pdo, true);

$id_usuario_actual = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            $data = $_POST;
        }
        
        // Validar datos requeridos
        $periodo_mes = intval($data['periodo_mes'] ?? 0);
        $periodo_anio = intval($data['periodo_anio'] ?? 0);
        
        if ($periodo_mes < 1 || $periodo_mes > 12 || $periodo_anio < 2020) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Periodo inválido: mes debe ser 1-12 y año debe ser válido'
            ]);
            exit;
        }
        
        // Validar si requiere informe
        $validacion = validateInformeNoOperaciones($pdo, $periodo_mes, $periodo_anio);
        
        if (!$validacion['requiere_informe'] ?? false) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $validacion['razon'] ?? 'No se requiere informe para este periodo',
                'validacion' => $validacion
            ]);
            exit;
        }
        
        // Verificar si ya existe un informe para este periodo
        $stmt = $pdo->prepare("SELECT * FROM informes_no_operaciones_pld 
                               WHERE periodo_mes = ? AND periodo_anio = ? AND id_status = 1");
        $stmt->execute([$periodo_mes, $periodo_anio]);
        $informe_existente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($informe_existente) {
            // Actualizar informe existente
            $stmt = $pdo->prepare("UPDATE informes_no_operaciones_pld 
                                   SET fecha_presentacion = ?,
                                       folio_sppld = ?,
                                       estatus = 'presentado',
                                       observaciones = ?,
                                       fecha_modificacion = NOW()
                                   WHERE id_informe = ?");
            $stmt->execute([
                $data['fecha_presentacion'] ?? date('Y-m-d'),
                $data['folio_sppld'] ?? null,
                $data['observaciones'] ?? null,
                $informe_existente['id_informe']
            ]);
            
            $id_informe = $informe_existente['id_informe'];
        } else {
            // Crear nuevo informe
            $stmt = $pdo->prepare("INSERT INTO informes_no_operaciones_pld 
                                   (periodo_mes, periodo_anio, fecha_limite, fecha_presentacion, 
                                    folio_sppld, estatus, observaciones, id_status)
                                   VALUES (?, ?, ?, ?, ?, 'presentado', ?, 1)");
            $stmt->execute([
                $periodo_mes,
                $periodo_anio,
                $validacion['fecha_limite'] ?? null,
                $data['fecha_presentacion'] ?? date('Y-m-d'),
                $data['folio_sppld'] ?? null,
                $data['observaciones'] ?? null
            ]);
            
            $id_informe = $pdo->lastInsertId();
        }
        
        // Log
        logChange($pdo, $id_usuario_actual, 'REGISTRAR_INFORME_NO_OPERACIONES', 
                 'informes_no_operaciones_pld', $id_informe, null, $data);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Informe registrado correctamente',
            'id_informe' => $id_informe,
            'validacion' => $validacion
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en registrar_informe_no_operaciones.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al procesar solicitud: ' . $e->getMessage()
    ]);
}
