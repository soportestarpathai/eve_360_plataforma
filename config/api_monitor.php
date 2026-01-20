<?php
/**
 * Monitor de Disponibilidad de APIs Externas
 * 
 * Verifica el estado y rendimiento de las APIs externas
 * Útil para monitoreo proactivo y alertas
 */

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/banxico_api.php';

class APIMonitor {
    private $logger;
    private $banxicoAPI;
    private $cache;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->banxicoAPI = new BanxicoAPI();
        require_once __DIR__ . '/cache.php';
        $this->cache = Cache::getInstance();
    }
    
    /**
     * Verifica el estado de todas las APIs externas
     */
    public function checkAll() {
        $results = [
            'banxico' => $this->checkBanxico(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Guardar resultado en caché para consulta rápida
        $this->cache->set('api_monitor_status', $results, 300); // 5 minutos
        
        return $results;
    }
    
    /**
     * Verifica el estado de la API de Banxico
     */
    public function checkBanxico() {
        try {
            $health = $this->banxicoAPI->checkHealth();
            
            if ($health['status'] === 'ok') {
                $this->logger->info('APIMonitor: Banxico API operativa', [
                    'responseTime' => $health['responseTime']
                ]);
                
                return [
                    'status' => 'operational',
                    'responseTime' => $health['responseTime'],
                    'lastCheck' => date('Y-m-d H:i:s')
                ];
            } else {
                $this->logger->error('APIMonitor: Banxico API no disponible', [
                    'responseTime' => $health['responseTime']
                ]);
                
                return [
                    'status' => 'down',
                    'responseTime' => $health['responseTime'],
                    'lastCheck' => date('Y-m-d H:i:s'),
                    'error' => 'API no responde correctamente'
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('APIMonitor: Error al verificar Banxico', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'status' => 'error',
                'lastCheck' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtiene el estado desde caché
     */
    public function getCachedStatus() {
        return $this->cache->get('api_monitor_status');
    }
    
    /**
     * Obtiene estadísticas históricas (requiere tabla adicional)
     * Puedes crear una tabla para almacenar historial si lo necesitas
     */
    public function getStatistics($days = 7) {
        // Esto requeriría una tabla para almacenar historial
        // Por ahora retornamos datos del caché
        $cached = $this->getCachedStatus();
        
        return [
            'current' => $cached,
            'note' => 'Para estadísticas históricas, implementar tabla de logs de monitoreo'
        ];
    }
    
    /**
     * Verifica si hay alertas que notificar
     */
    public function checkAlerts() {
        $status = $this->checkAll();
        $alerts = [];
        
        // Alerta si Banxico está caído
        if (isset($status['banxico']) && $status['banxico']['status'] === 'down') {
            $alerts[] = [
                'type' => 'error',
                'service' => 'Banxico API',
                'message' => 'La API de Banxico no está disponible',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Alerta si el tiempo de respuesta es muy alto
        if (isset($status['banxico']) && 
            $status['banxico']['status'] === 'operational' && 
            $status['banxico']['responseTime'] > 5000) { // Más de 5 segundos
            $alerts[] = [
                'type' => 'warning',
                'service' => 'Banxico API',
                'message' => "Tiempo de respuesta alto: {$status['banxico']['responseTime']}ms",
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        if (!empty($alerts)) {
            foreach ($alerts as $alert) {
                if ($alert['type'] === 'error') {
                    $this->logger->error('APIMonitor: Alerta crítica', $alert);
                } else {
                    $this->logger->warning('APIMonitor: Alerta de rendimiento', $alert);
                }
            }
        }
        
        return $alerts;
    }
}

// Endpoint opcional para verificar estado vía web
// Puedes llamar: api/monitor_status.php
if (php_sapi_name() === 'cli' || (isset($_GET['check']) && $_GET['check'] === 'status')) {
    $monitor = new APIMonitor();
    $status = $monitor->checkAll();
    
    if (php_sapi_name() === 'cli') {
        print_r($status);
    } else {
        header('Content-Type: application/json');
        echo json_encode($status);
    }
}
