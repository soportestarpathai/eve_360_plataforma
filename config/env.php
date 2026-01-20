<?php
/**
 * Configuración de Variables de Entorno
 * 
 * Para producción, crear un archivo .env o configurar variables de entorno del servidor
 * Ejemplo .env:
 * BANXICO_TOKEN=tu_token_aqui
 * CACHE_ENABLED=true
 * CACHE_DURATION=1800
 * LOG_LEVEL=ERROR
 */

// Intentar cargar desde .env si existe
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Configuración por defecto (desarrollo)
return [
    // API Externa: Banxico
    'BANXICO_TOKEN' => $_ENV['BANXICO_TOKEN'] ?? '6210a4bfb2eaae222f81f1fada3b951732d371b30d72984fcd67c5d6d4b4fd0f',
    
    // Sistema de Caché
    'CACHE_ENABLED' => filter_var($_ENV['CACHE_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'CACHE_DURATION' => (int)($_ENV['CACHE_DURATION'] ?? 1800), // 30 minutos por defecto
    'CACHE_DIR' => __DIR__ . '/../cache/',
    
    // Sistema de Logging
    'LOG_LEVEL' => $_ENV['LOG_LEVEL'] ?? 'ERROR', // DEBUG, INFO, WARNING, ERROR
    'LOG_DIR' => __DIR__ . '/../logs/',
    'LOG_FILE' => 'app.log',
    'LOG_MAX_SIZE' => 10485760, // 10MB
    
    // Monitoreo de APIs
    'API_TIMEOUT' => (int)($_ENV['API_TIMEOUT'] ?? 5), // segundos
    'API_RETRY_ATTEMPTS' => (int)($_ENV['API_RETRY_ATTEMPTS'] ?? 2),
    
    // CORS (si se necesita en el futuro)
    'CORS_ENABLED' => filter_var($_ENV['CORS_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'CORS_ALLOWED_ORIGINS' => $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*',
];
