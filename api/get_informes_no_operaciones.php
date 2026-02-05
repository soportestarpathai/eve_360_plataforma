<?php
/**
 * API Endpoint: Obtener Informes de No Operaciones (VAL-PLD-012)
 * Lista informes de no operaciones registrados
 */

session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    $periodo_mes = $_GET['periodo_mes'] ?? null;
    $periodo_anio = $_GET['periodo_anio'] ?? null;
    $estatus = $_GET['estatus'] ?? null;
    
    $sql = "SELECT * FROM informes_no_operaciones_pld WHERE id_status = 1";
    $params = [];
    
    if ($periodo_mes) {
        $sql .= " AND periodo_mes = ?";
        $params[] = $periodo_mes;
    }
    
    if ($periodo_anio) {
        $sql .= " AND periodo_anio = ?";
        $params[] = $periodo_anio;
    }
    
    if ($estatus) {
        $sql .= " AND estatus = ?";
        $params[] = $estatus;
    }
    
    $sql .= " ORDER BY periodo_anio DESC, periodo_mes DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $informes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular si hay periodos pendientes
    $periodosPendientes = [];
    $mesActual = intval(date('m'));
    $anioActual = intval(date('Y'));
    
    // Verificar últimos 6 meses
    for ($i = 0; $i < 6; $i++) {
        $mes = $mesActual - $i;
        $anio = $anioActual;
        
        if ($mes <= 0) {
            $mes += 12;
            $anio--;
        }
        
        // Verificar si hay informe para este periodo
        $stmt = $pdo->prepare("SELECT * FROM informes_no_operaciones_pld 
                               WHERE periodo_mes = ? AND periodo_anio = ? AND id_status = 1");
        $stmt->execute([$mes, $anio]);
        $informe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar si hubo operaciones avisables
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM operaciones_pld 
                               WHERE DATE_FORMAT(fecha_operacion, '%Y-%m') = ? 
                               AND requiere_aviso = 1 AND id_status = 1");
        $stmt->execute([sprintf('%04d-%02d', $anio, $mes)]);
        $operaciones = $stmt->fetch(PDO::FETCH_ASSOC);
        $huboOperaciones = intval($operaciones['count']) > 0;
        
        if (!$huboOperaciones && (!$informe || $informe['estatus'] !== 'presentado')) {
            // Calcular fecha límite (día 17 del mes siguiente)
            $fechaLimite = date('Y-m-17', strtotime("+1 month", strtotime("$anio-$mes-01")));
            
            $periodosPendientes[] = [
                'periodo_mes' => $mes,
                'periodo_anio' => $anio,
                'periodo_nombre' => date('F Y', strtotime("$anio-$mes-01")),
                'fecha_limite' => $fechaLimite,
                'dias_restantes' => max(0, (strtotime($fechaLimite) - time()) / 86400),
                'estatus' => $informe ? $informe['estatus'] : 'pendiente'
            ];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'informes' => $informes,
        'periodos_pendientes' => $periodosPendientes,
        'total' => count($informes)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_informes_no_operaciones.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener informes: ' . $e->getMessage()
    ]);
}
