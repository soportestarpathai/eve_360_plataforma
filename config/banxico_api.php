<?php
/**
 * Cliente API Banxico
 * 
 * Maneja todas las llamadas a la API de Banxico con:
 * - Caché automático
 * - Validación de respuestas
 * - Manejo de errores
 * - Reintentos automáticos
 * - Logging
 */

require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/logger.php';

class BanxicoAPI {
    private $token;
    private $baseUrl = 'https://www.banxico.org.mx/SieAPIRest/service/v1/series';
    private $timeout;
    private $retryAttempts;
    private $cache;
    private $logger;
    
    public function __construct() {
        $config = require __DIR__ . '/env.php';
        $this->token = $config['BANXICO_TOKEN'];
        $this->timeout = $config['API_TIMEOUT'];
        $this->retryAttempts = $config['API_RETRY_ATTEMPTS'];
        $this->cache = Cache::getInstance();
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Obtiene datos oportunos de una o más series
     */
    public function getSeriesData($seriesIds, $cacheDuration = 1800) {
        // Validar formato de series IDs
        if (empty($seriesIds)) {
            $this->logger->error('BanxicoAPI: Series IDs vacío');
            return null;
        }
        
        // Normalizar series IDs (acepta string o array)
        if (is_array($seriesIds)) {
            $seriesIds = implode(',', $seriesIds);
        }
        
        // Validar formato (solo letras, números y comas)
        if (!preg_match('/^[A-Z0-9,]+$/', $seriesIds)) {
            $this->logger->error('BanxicoAPI: Formato inválido de Series IDs', ['seriesIds' => $seriesIds]);
            return null;
        }
        
        $cacheKey = 'banxico_' . md5($seriesIds);
        
        // Intentar obtener del caché
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            $this->logger->debug('BanxicoAPI: Datos obtenidos del caché', ['seriesIds' => $seriesIds]);
            return $cached;
        }
        
        // Hacer petición a la API
        $url = "{$this->baseUrl}/{$seriesIds}/datos/oportuno";
        $response = $this->makeRequest($url, $seriesIds);
        
        if ($response === null) {
            // Si falla, intentar devolver datos del caché aunque estén expirados
            $expiredCache = $this->getExpiredCache($cacheKey);
            if ($expiredCache !== null) {
                $this->logger->warning('BanxicoAPI: Usando caché expirado debido a error de API', ['seriesIds' => $seriesIds]);
                return $expiredCache;
            }
            return null;
        }
        
        // Validar y sanitizar respuesta
        $validatedData = $this->validateResponse($response, $seriesIds);
        
        if ($validatedData !== null) {
            // Guardar en caché
            $this->cache->set($cacheKey, $validatedData, $cacheDuration);
            return $validatedData;
        }
        
        return null;
    }
    
    /**
     * Realiza una petición HTTP con reintentos
     */
    private function makeRequest($url, $seriesIds) {
        $attempt = 0;
        
        while ($attempt <= $this->retryAttempts) {
            try {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => $this->timeout,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_HTTPHEADER => [
                        "Bmx-Token: {$this->token}",
                        "Accept: application/json"
                    ],
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    throw new Exception("cURL Error: {$error}");
                }
                
                if ($httpCode !== 200) {
                    throw new Exception("HTTP Error: {$httpCode}");
                }
                
                if (empty($response)) {
                    throw new Exception("Respuesta vacía de la API");
                }
                
                // Si llegamos aquí, la petición fue exitosa
                $this->logger->info('BanxicoAPI: Petición exitosa', [
                    'seriesIds' => $seriesIds,
                    'httpCode' => $httpCode,
                    'attempt' => $attempt + 1
                ]);
                
                return $response;
                
            } catch (Exception $e) {
                $attempt++;
                $this->logger->warning("BanxicoAPI: Intento {$attempt} fallido", [
                    'error' => $e->getMessage(),
                    'url' => $url,
                    'seriesIds' => $seriesIds
                ]);
                
                if ($attempt <= $this->retryAttempts) {
                    // Esperar antes del siguiente intento (exponential backoff)
                    sleep(pow(2, $attempt - 1));
                } else {
                    $this->logger->error('BanxicoAPI: Todos los intentos fallaron', [
                        'url' => $url,
                        'seriesIds' => $seriesIds,
                        'lastError' => $e->getMessage()
                    ]);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Valida y sanitiza la respuesta de la API
     */
    private function validateResponse($response, $seriesIds) {
        // Decodificar JSON
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('BanxicoAPI: Error al decodificar JSON', [
                'error' => json_last_error_msg(),
                'seriesIds' => $seriesIds
            ]);
            return null;
        }
        
        // Validar estructura básica
        if (!isset($data['bmx']['series'])) {
            $this->logger->error('BanxicoAPI: Estructura de respuesta inválida', [
                'response' => substr($response, 0, 200),
                'seriesIds' => $seriesIds
            ]);
            return null;
        }
        
        $series = $data['bmx']['series'];
        
        // Validar que sea un array
        if (!is_array($series)) {
            $this->logger->error('BanxicoAPI: Series no es un array', ['seriesIds' => $seriesIds]);
            return null;
        }
        
        // Sanitizar y validar cada serie
        $sanitized = [];
        foreach ($series as $serie) {
            // Validar estructura mínima
            if (!isset($serie['idSerie']) || !isset($serie['datos'])) {
                $this->logger->warning('BanxicoAPI: Serie con estructura incompleta', [
                    'serie' => $serie['idSerie'] ?? 'unknown'
                ]);
                continue;
            }
            
            // Validar que datos sea un array
            if (!is_array($serie['datos'])) {
                continue;
            }
            
            // Validar que tenga al menos un dato
            if (empty($serie['datos'])) {
                continue;
            }
            
            $dato = $serie['datos'][0];
            
            // Validar estructura del dato
            if (!isset($dato['dato']) || !isset($dato['fecha'])) {
                continue;
            }
            
            // Sanitizar valor numérico
            $valor = $dato['dato'];
            if ($valor === 'N/E') {
                continue; // Saltar valores no disponibles
            }
            
            $valor = filter_var($valor, FILTER_VALIDATE_FLOAT);
            if ($valor === false) {
                $this->logger->warning('BanxicoAPI: Valor no numérico ignorado', [
                    'serie' => $serie['idSerie'],
                    'dato' => $dato['dato']
                ]);
                continue;
            }
            
            // Validar fecha
            $fecha = $dato['fecha'];
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                $this->logger->warning('BanxicoAPI: Formato de fecha inválido', [
                    'serie' => $serie['idSerie'],
                    'fecha' => $fecha
                ]);
                continue;
            }
            
            // Agregar serie validada
            $sanitized[] = [
                'idSerie' => htmlspecialchars($serie['idSerie'], ENT_QUOTES, 'UTF-8'),
                'dato' => (float)$valor,
                'fecha' => $fecha
            ];
        }
        
        if (empty($sanitized)) {
            $this->logger->warning('BanxicoAPI: No se pudieron validar series', ['seriesIds' => $seriesIds]);
            return null;
        }
        
        return $sanitized;
    }
    
    /**
     * Obtiene datos expirados del caché como fallback
     */
    private function getExpiredCache($cacheKey) {
        $file = __DIR__ . '/../cache/' . preg_replace('/[^a-zA-Z0-9_]/', '_', $cacheKey) . '.cache';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            return $data['value'] ?? null;
        }
        return null;
    }
    
    /**
     * Verifica disponibilidad de la API
     */
    public function checkHealth() {
        $testSeries = 'SP68257'; // UDIS como prueba
        $startTime = microtime(true);
        
        $url = "{$this->baseUrl}/{$testSeries}/datos/oportuno";
        $response = $this->makeRequest($url, $testSeries);
        
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        
        if ($response !== null) {
            $this->logger->info('BanxicoAPI: Health check exitoso', ['responseTime' => $responseTime . 'ms']);
            return [
                'status' => 'ok',
                'responseTime' => $responseTime
            ];
        }
        
        $this->logger->error('BanxicoAPI: Health check fallido', ['responseTime' => $responseTime . 'ms']);
        return [
            'status' => 'error',
            'responseTime' => $responseTime
        ];
    }
}
