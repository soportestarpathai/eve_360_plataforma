<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) && php_sapi_name() !== 'cli') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$indicatorID = '539260';
$token = 'fbc251b6-02a1-f46a-fbea-6e8b891b4f67';
$apiUrl = "https://www.inegi.org.mx/app/api/indicadores/desarrolladores/jsonxml/INDICATOR/$indicatorID/es/00/false/BISE/2.0/$token?type=json";

try {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('La extensión cURL no está disponible en PHP.');
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; EVE360/1.0)');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlErr !== '') {
        throw new RuntimeException('No se pudo consultar INEGI: ' . $curlErr);
    }
    if ($httpCode >= 400) {
        throw new RuntimeException('INEGI respondió con HTTP ' . $httpCode);
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Respuesta inválida de INEGI.');
    }

    $observaciones = $data['Series'][0]['OBSERVATIONS'] ?? null;
    if (!is_array($observaciones) || empty($observaciones)) {
        throw new RuntimeException('La respuesta de INEGI no contiene observaciones de UMA.');
    }

    $nombreIndicador = 'UMA (Valor Diario)';
    $stmtFind = $pdo->prepare('SELECT id_indicador FROM indicadores WHERE nombre = ? AND DATE(fecha) = ? LIMIT 1');
    $stmtInsert = $pdo->prepare('INSERT INTO indicadores (nombre, fecha, valor) VALUES (?, ?, ?)');
    $stmtUpdate = $pdo->prepare('UPDATE indicadores SET valor = ? WHERE id_indicador = ?');

    $insertados = 0;
    $actualizados = 0;

    $pdo->beginTransaction();
    foreach ($observaciones as $obs) {
        $rawDate = trim((string)($obs['TIME_PERIOD'] ?? ''));
        $rawValor = trim((string)($obs['OBS_VALUE'] ?? ''));
        if ($rawDate === '' || $rawValor === '') {
            continue;
        }

        if (strlen($rawDate) === 4 && ctype_digit($rawDate)) {
            $fecha = $rawDate . '-02-01';
        } else {
            $timestamp = strtotime(str_replace('/', '-', $rawDate));
            if ($timestamp === false) {
                continue;
            }
            $fecha = date('Y-m-d', $timestamp);
        }

        if (!is_numeric($rawValor)) {
            continue;
        }
        $valor = (float)$rawValor;

        $stmtFind->execute([$nombreIndicador, $fecha]);
        $idExistente = (int)$stmtFind->fetchColumn();

        if ($idExistente > 0) {
            $stmtUpdate->execute([$valor, $idExistente]);
            $actualizados++;
        } else {
            $stmtInsert->execute([$nombreIndicador, $fecha, $valor]);
            $insertados++;
        }
    }
    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Importación de UMA completada.',
        'insertados' => $insertados,
        'actualizados' => $actualizados,
        'total_procesados' => $insertados + $actualizados
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('import_inegi_uma: ' . $e->getMessage());

    $msg = $e->getMessage();
    if (stripos($msg, 'INEGI') !== false || stripos($msg, 'cURL') !== false) {
        http_response_code(502);
    } else {
        http_response_code(500);
    }

    echo json_encode(['status' => 'error', 'message' => $msg]);
}
?>
