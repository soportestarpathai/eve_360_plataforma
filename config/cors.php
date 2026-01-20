<?php
/**
 * Configuración CORS (Cross-Origin Resource Sharing)
 * 
 * Solo necesario si tu aplicación necesita aceptar peticiones desde otros dominios
 * Para APIs REST o aplicaciones SPA (Single Page Applications)
 */

require_once __DIR__ . '/env.php';

$config = require __DIR__ . '/env.php';

// Solo aplicar CORS si está habilitado
if ($config['CORS_ENABLED']) {
    // Obtener origen de la petición
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    
    // Verificar si el origen está permitido
    $allowedOrigins = explode(',', $config['CORS_ALLOWED_ORIGINS']);
    $allowedOrigins = array_map('trim', $allowedOrigins);
    
    if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
        // Permitir el origen
        header("Access-Control-Allow-Origin: {$origin}");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400"); // 24 horas
    }
    
    // Manejar preflight requests (OPTIONS)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
