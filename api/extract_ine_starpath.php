<?php
/**
 * API: Extracción de datos de INE mediante Starpath AI
 * Proxy que recibe el archivo, lo envía a Starpath y devuelve los datos extraídos.
 * Uso: POST con campo 'file' (PDF o imagen) y document_type=ine
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
$config = require __DIR__ . '/../config/env.php';
// URL exacta como en Postman (con trailing slash si está en config)
$apiUrl = $config['STARPATH_API_URL'] ?? 'https://www.starpathai.mx/api/documents/extract/';
$token = $config['STARPATH_API_TOKEN'] ?? '';

if (empty($token)) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Configuración de API Starpath no disponible']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'No se recibió archivo o hubo error en la carga (código: ' . ($_FILES['file']['error'] ?? 'N/A') . ')'
    ]);
    exit;
}

$file = $_FILES['file'];
if (!is_uploaded_file($file['tmp_name']) || !file_exists($file['tmp_name'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Archivo temporal no válido']);
    exit;
}
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Formato no válido. Use PDF o imagen (JPG, PNG, GIF, WebP)'
    ]);
    exit;
}

$documentType = $_POST['document_type'] ?? 'ine';

if (!class_exists('CURLFile')) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Extensión curl de PHP no disponible o incompatible']);
    exit;
}

$postData = [
    'file' => new CURLFile($file['tmp_name'], $mimeType, basename($file['name'])),
    'document_type' => $documentType
];

$ch = curl_init($apiUrl);
$curlOpts = [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
];
// En entornos locales (WAMP/XAMPP) suele faltar el CA bundle. Por defecto desactivar; en producción usar STARPATH_SSL_VERIFY=true
if (!($config['STARPATH_SSL_VERIFY'] ?? false)) {
    $curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
    $curlOpts[CURLOPT_SSL_VERIFYHOST] = 0;
}
curl_setopt_array($ch, $curlOpts);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    ob_end_clean();
    error_log("Starpath API curl error: $curlError");
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión con servicio de extracción: ' . $curlError]);
    exit;
}

$data = json_decode($response, true);
if (!is_array($data)) {
    $preview = substr((string)$response, 0, 500);
    $message = 'Respuesta inválida del servicio';
    if ($response === '') {
        $message = 'El servicio Starpath no devolvió respuesta';
    } elseif (stripos($response, 'RuntimeError') !== false || stripos($response, 'at /api/') !== false) {
        $message = 'El servicio Starpath reportó un error interno (RuntimeError). Posibles causas: token inválido o expirado, formato de petición no admitido, o fallo temporal. Revise la documentación de Starpath o contacte a soporte.';
    } elseif (preg_match('/<title>([^<]+)<\/title>/i', $response, $m)) {
        $message = 'Error del servicio Starpath: ' . trim(preg_replace('/\s+/', ' ', $m[1]));
    }
    $data = [
        'message' => $message,
        'raw_preview' => $preview
    ];
}

ob_end_clean();

// Starpath puede devolver { ok: true, data: {...} } o directamente { data: {...} }
$hasData = is_array($data) && !empty($data['data']);
if ($httpCode >= 200 && $httpCode < 300 && $hasData) {
    echo json_encode([
        'status' => 'success',
        'ok' => true,
        'data' => $data['data']
    ]);
} else {
    $message = $data['message'] ?? $data['error'] ?? 'Error al extraer datos del documento';
    $code = $httpCode >= 400 ? $httpCode : 422;
    http_response_code($code);
    $payload = [
        'status' => 'error',
        'message' => is_string($message) ? $message : json_encode($message),
        'raw' => $data
    ];
    // Siempre incluir vista previa cuando la respuesta del servicio no fue JSON válido
    if (!empty($data['raw_preview'])) {
        $payload['response_preview'] = $data['raw_preview'];
    }
    if (!empty($config['STARPATH_DEBUG'])) {
        $payload['_debug'] = [
            'http_code' => $httpCode,
            'response_preview' => substr((string)$response, 0, 500)
        ];
    }
    echo json_encode($payload);
}

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    error_log('extract_ine_starpath: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $errPayload = [
        'status' => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ];
    $cfg = @(include __DIR__ . '/../config/env.php') ?: [];
    if (!empty($cfg['STARPATH_DEBUG'])) {
        $errPayload['_debug'] = [
            'exception' => true,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    echo json_encode($errPayload);
}
