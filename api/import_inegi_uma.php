<?php
require_once '../config/db.php'; 

header('Content-Type: application/json');

// 1. CONFIGURATION
// ID Provided by user: 539260 (UMA)
$indicatorID = "539260"; 
$token = "fbc251b6-02a1-f46a-fbea-6e8b891b4f67"; 

// We request the historical series (false) in JSON format
$apiUrl = "https://www.inegi.org.mx/app/api/indicadores/desarrolladores/jsonxml/INDICATOR/$indicatorID/es/00/false/BISE/2.0/$token?type=json";

try {
    // 2. FETCH DATA
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; InvestorMLP/1.0)");
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception('Error cURL: ' . curl_error($ch));
    }
    curl_close($ch);

    // 3. PARSE JSON
    $data = json_decode($response, true);

    if (!$data) {
        throw new Exception('Error decoding JSON or empty response from INEGI.');
    }

    // 4. PROCESS OBSERVATIONS
    // We check if the path Series -> OBSERVATIONS exists
    if (isset($data['Series'][0]['OBSERVATIONS'])) {
        
        $nombreIndicador = "UMA (Valor Diario)"; 

        // Optional: Clear previous UMA values to avoid duplicates or use ON DUPLICATE KEY UPDATE
        // For now, we just insert.
        $stmt = $pdo->prepare("INSERT INTO indicadores (nombre, fecha, valor) VALUES (?, ?, ?)");
        
        $count = 0;
        $pdo->beginTransaction();

        foreach ($data['Series'][0]['OBSERVATIONS'] as $obs) {
            $rawDate = $obs['TIME_PERIOD']; // Example: "2016/02/01" or "2024"
            $valor = $obs['OBS_VALUE'];
            
            // Date Logic:
            // UMA is usually annual. If INEGI returns just "2024", we assume 2024-02-01 (UMA standard start)
            // or 2024-01-01 for sorting.
            if (strlen($rawDate) == 4) {
                // If year only, set to February 1st (Official UMA change date)
                $fecha = $rawDate . "-02-01"; 
            } else {
                // If full date "YYYY/MM/DD", format it for MySQL
                $fecha = date('Y-m-d', strtotime(str_replace('/', '-', $rawDate)));
            }

            $stmt->execute([$nombreIndicador, $fecha, $valor]);
            $count++;
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success', 
            'message' => "Importación exitosa. Se guardaron $count registros de UMA (ID: $indicatorID)."
        ]);

    } else {
        // Log the raw response for debugging if it fails again
        error_log("INEGI Response Error: " . print_r($data, true));
        throw new Exception('La respuesta de INEGI es válida pero no contiene datos (OBSERVATIONS). Verifique si el ID ' . $indicatorID . ' es correcto para el Banco de Indicadores (BISE).');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>