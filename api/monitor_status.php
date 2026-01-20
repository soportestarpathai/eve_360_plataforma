<?php
/**
 * Endpoint para verificar el estado de las APIs externas
 * 
 * Uso: GET /api/monitor_status.php
 * 
 * Requiere autenticación de sesión (opcional, pero recomendado)
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/api_monitor.php';

try {
    $monitor = new APIMonitor();
    
    // Obtener estado (usar caché si está disponible, sino verificar)
    $cached = $monitor->getCachedStatus();
    
    if ($cached && (time() - strtotime($cached['timestamp'])) < 300) {
        // Usar datos del caché si tienen menos de 5 minutos
        echo json_encode([
            'status' => 'success',
            'data' => $cached,
            'source' => 'cache'
        ]);
    } else {
        // Verificar estado actual
        $status = $monitor->checkAll();
        echo json_encode([
            'status' => 'success',
            'data' => $status,
            'source' => 'live'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
