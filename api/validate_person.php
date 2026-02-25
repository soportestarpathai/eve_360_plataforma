<?php
session_start();
require_once '../config/db.php';
require_once '../config/pld_middleware.php';
require_once '../config/pld_expediente.php'; // VAL-PLD-005, VAL-PLD-006
header('Content-Type: application/json');

function buildQuotaPayload($limit, $used, $yearMonth) {
    $limitInt = max(0, (int)$limit);
    $usedInt = max(0, (int)$used);
    return [
        'limit' => $limitInt,
        'used' => $usedInt,
        'available' => max(0, $limitInt - $usedInt),
        'year_month' => $yearMonth
    ];
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// VAL-PLD-001: Bloquear consultas PLD si no está habilitado
requirePLDHabilitado($pdo, true);

// --- 1. CHECK LIMIT (Read Only) ---
$currentMonth = date('Y-m');
$limit = 300;
$currentCount = 0;
try {
    $stmt_config = $pdo->query("SELECT max_busquedas_api FROM config_empresa WHERE id_config = 1");
    $config = $stmt_config->fetch(PDO::FETCH_ASSOC);
    $limit = $config ? (int)$config['max_busquedas_api'] : 300;
    if ($limit <= 0) {
        $limit = 300;
    }

    // Added backticks here too for safety
    $stmt_check = $pdo->prepare("SELECT search_count FROM `search_usage` WHERE `year_month` = ?");
    $stmt_check->execute([$currentMonth]);
    $usage = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    $currentCount = $usage ? (int)$usage['search_count'] : 0;
    
    if ($currentCount >= $limit) {
        echo json_encode([
            'status' => 'error',
            'message' => "Límite mensual ($limit) alcanzado.",
            'quota' => buildQuotaPayload($limit, $currentCount, $currentMonth)
        ]);
        exit;
    }
} catch (Exception $e) { 
    error_log("Limit check failed: " . $e->getMessage()); 
}

// Get POST data
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

// Validate JSON parsing
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid JSON data',
        'quota' => buildQuotaPayload($limit, $currentCount, $currentMonth)
    ]);
    exit;
}

$nombre = cleanString(trim($data['nombre'] ?? ''));
$paterno = cleanString(trim($data['paterno'] ?? ''));
$materno = cleanString(trim($data['materno'] ?? ''));
$tipo_persona_input = $data['tipo_persona'] ?? ''; 
$id_cliente = $data['id_cliente'] ?? null; 
$save_history = $data['save_history'] ?? false;
$use_cache = filter_var($data['use_cache'] ?? false, FILTER_VALIDATE_BOOLEAN);

// VAL-PLD-005 y VAL-PLD-006: Validar expediente si hay cliente asociado (solo lectura, sin actualizar flags)
// Las consultas PLD se permiten incluso si el expediente está incompleto.
// Se registra una advertencia para auditoría sin modificar la tabla clientes.
if ($id_cliente) {
    try {
        require_once __DIR__ . '/../config/pld_expediente.php';
        $resultCompleto = validateExpedienteCompleto($pdo, $id_cliente, false);
        $resultActualizacion = validateActualizacionExpediente($pdo, $id_cliente);
        
        // Si el expediente está incompleto o vencido, solo registramos una advertencia
        // pero NO bloqueamos la consulta PLD (puede ser necesaria para completar el expediente)
        if (!$resultCompleto['completo'] || !$resultActualizacion['actualizado']) {
            error_log("ADVERTENCIA PLD: Consulta PLD realizada para cliente $id_cliente con expediente incompleto o vencido. " .
                     "Completitud: " . ($resultCompleto['completo'] ? 'OK' : 'INCOMPLETO') . 
                     ", Actualización: " . ($resultActualizacion['actualizado'] ? 'OK' : 'VENCIDO'));
        }
    } catch (Exception $e) {
        // Si hay error en la validación, no bloqueamos la consulta
        error_log("Error al validar expediente en validate_person.php: " . $e->getMessage());
    }
}

// Validate required fields before API call
if ($tipo_persona_input === 'fisica') {
    if (empty($nombre) || empty($paterno)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Nombre y Apellido Paterno son requeridos para persona física',
            'quota' => buildQuotaPayload($limit, $currentCount, $currentMonth)
        ]);
        exit;
    }
} else if ($tipo_persona_input === 'moral') {
    if (empty($nombre) || strlen($nombre) < 3) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Razón Social es requerida (mínimo 3 caracteres) para persona moral',
            'quota' => buildQuotaPayload($limit, $currentCount, $currentMonth)
        ]);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Tipo de persona inválido o no especificado',
        'quota' => buildQuotaPayload($limit, $currentCount, $currentMonth)
    ]);
    exit;
} 

function cleanString($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $unwanted_array = ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n'];
    return strtr($str, $unwanted_array);
}

$tipo_persona = 'fisica'; 
if (in_array(strtolower($tipo_persona_input), ['moral', 'm'])) { $tipo_persona = 'moral'; }
$fullName = trim(preg_replace('/\s+/', ' ', "$nombre $paterno $materno"));

// --- 2. TRY CACHE (Optional, no quota consumption) ---
if ($use_cache && $fullName !== '') {
    try {
        $stmtCache = $pdo->prepare("
            SELECT id_busqueda, resultado_json, riesgo_detectado
            FROM clientes_busquedas_listas
            WHERE id_usuario = ? AND nombre_buscado = ?
            ORDER BY fecha_busqueda DESC
            LIMIT 1
        ");
        $stmtCache->execute([$_SESSION['user_id'], $fullName]);
        $cached = $stmtCache->fetch(PDO::FETCH_ASSOC);

        if ($cached) {
            $cachedHits = json_decode($cached['resultado_json'] ?? '[]', true);
            if (!is_array($cachedHits)) {
                $cachedHits = [];
            }
            $cachedFound = !empty($cachedHits);

            echo json_encode([
                'status' => 'success',
                'found' => $cachedFound,
                'data' => $cachedHits,
                'id_busqueda' => (int)$cached['id_busqueda'],
                'cached' => true,
                'quota' => buildQuotaPayload($limit, $currentCount, $currentMonth)
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("PLD cache lookup failed: " . $e->getMessage());
    }
}

// --- 3. EXECUTE API REQUEST ---
$endpoint = "https://gt-servicios.com/prolistas/Busquedaapi/searchperson";
$apiKey = "KYC-ukJY0of8NoX40FS0po5odlM0n63wcgXvQq1H7mvaYpZTeLM4lCbUCyQjl3ieH4M=";

$postData = [
    "nombre" => $nombre, "apaterno" => $paterno, "amaterno" => $materno,
    "tipo_persona" => $tipo_persona, "tipo_busqueda" => "normal", "id_entidad" => "46000"
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $endpoint, CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_HTTPHEADER => ["x-api-key: $apiKey", "Content-Type: application/x-www-form-urlencoded"],
    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 30
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode < 200 || $httpCode >= 300 || $response === false) {
    echo json_encode([
        'status' => 'error',
        'message' => "API Error: HTTP $httpCode",
        'quota' => buildQuotaPayload($limit, $currentCount, $currentMonth)
    ]);
    exit;
}

// --- 4. INCREMENT COUNTER (Fixed Syntax) ---
try {
    // FIX: Added backticks (`) around table and column names to prevent SQL syntax errors
    $pdo->prepare("INSERT INTO `search_usage` (`year_month`, `search_count`) VALUES (?, 1) ON DUPLICATE KEY UPDATE `search_count` = `search_count` + 1")->execute([$currentMonth]);
    $currentCount++;
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'DB Error (Usage): ' . $e->getMessage(),
        'quota' => buildQuotaPayload($limit, $currentCount, $currentMonth)
    ]);
    exit;
}

// --- 5. PARSE RESPONSE ---
$jsonResponse = json_decode($response, true);
$mappedHits = [];
$found = false;

if (json_last_error() === JSON_ERROR_NONE) {
    $rawResult = $jsonResponse['parameters']['result'] ?? $jsonResponse['result'] ?? null;
    if ($rawResult !== null) {
        if (is_string($rawResult)) {
            $decoded = json_decode($rawResult, true);
            $resultData = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $rawResult;
        } else { $resultData = $rawResult; }

        if (is_array($resultData) && !empty($resultData)) {
             $hits = (isset($resultData[0]) && is_array($resultData[0])) ? $resultData : [$resultData];
             foreach ($hits as $hit) {
                 if (is_array($hit)) {
                     $mappedHits[] = [
                         'id' => $hit[0] ?? '',
                         'nombreCompleto' => $hit[1] ?? 'Desconocido',
                         'entidad' => $hit[2] ?? '',
                         'puesto' => $hit[3] ?? '',
                         'fecha' => $hit[4] ?? '',
                         'lista' => $hit[5] ?? 'Desconocida',
                         'estatus' => $hit[6] ?? '',
                         'porcentaje' => $hit[7] ?? 0
                     ];
                 }
             }
             if (count($mappedHits) > 0) $found = true;
        }
    }
}

// --- 6. SAVE HISTORY ---
$id_busqueda = null;
if ($save_history) {
    try {
        $stmt = $pdo->prepare("INSERT INTO clientes_busquedas_listas (id_cliente, id_usuario, nombre_buscado, resultado_json, riesgo_detectado) VALUES (?, ?, ?, ?, ?)");
        $jsonHits = json_encode($mappedHits, JSON_UNESCAPED_UNICODE);
        $riesgoVal = $found ? 1 : 0;
        $userId = $_SESSION['user_id'];
        
        $stmt->execute([$id_cliente, $userId, $fullName, $jsonHits, $riesgoVal]);
        $id_busqueda = $pdo->lastInsertId();

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'DB Error (History): ' . $e->getMessage(),
            'quota' => buildQuotaPayload($limit, $currentCount, $currentMonth)
        ]);
        exit;
    }
}

echo json_encode([
    'status' => 'success',
    'found' => $found,
    'data' => $mappedHits,
    'id_busqueda' => $id_busqueda,
    'cached' => false,
    'quota' => buildQuotaPayload($limit, $currentCount, $currentMonth)
]);
?>
