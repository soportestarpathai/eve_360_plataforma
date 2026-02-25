<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

function pickAddressField(array $address, array $keys): string
{
    foreach ($keys as $key) {
        if (!empty($address[$key])) {
            return trim((string)$address[$key]);
        }
    }
    return '';
}

try {
    $latRaw = $_GET['lat'] ?? $_POST['lat'] ?? null;
    $lngRaw = $_GET['lng'] ?? $_POST['lng'] ?? $_GET['lon'] ?? $_POST['lon'] ?? null;

    if ($latRaw === null || $lngRaw === null || !is_numeric($latRaw) || !is_numeric($lngRaw)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Parámetros lat/lng inválidos.']);
        exit;
    }

    $lat = (float)$latRaw;
    $lng = (float)$lngRaw;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Coordenadas fuera de rango.']);
        exit;
    }

    $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&accept-language=es'
        . '&lat=' . rawurlencode((string)$lat)
        . '&lon=' . rawurlencode((string)$lng);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'EVE360-PLD/1.0 (reverse-geocode)');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        http_response_code(502);
        $msg = $curlError !== '' ? $curlError : ('HTTP ' . $httpCode . ' al consultar geocodificación.');
        echo json_encode(['status' => 'error', 'message' => 'No se pudo consultar geocodificación: ' . $msg]);
        exit;
    }

    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        http_response_code(502);
        echo json_encode(['status' => 'error', 'message' => 'Respuesta inválida del servicio de geocodificación.']);
        exit;
    }

    $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];
    if (empty($address)) {
        echo json_encode(['status' => 'success', 'data' => null]);
        exit;
    }

    $state = pickAddressField($address, ['state', 'region', 'state_district']);
    $municipality = pickAddressField($address, ['county', 'city', 'town', 'municipality', 'city_district', 'district']);
    $colony = pickAddressField($address, ['suburb', 'neighbourhood', 'quarter', 'hamlet', 'village']);
    $postalCode = preg_replace('/\D+/', '', (string)pickAddressField($address, ['postcode']));
    if (strlen($postalCode) > 5) {
        $postalCode = substr($postalCode, 0, 5);
    }

    $streetName = pickAddressField($address, ['road', 'pedestrian', 'residential', 'footway']);
    $streetNumber = pickAddressField($address, ['house_number']);
    $street = trim($streetName . ($streetNumber !== '' ? ' ' . $streetNumber : ''));

    echo json_encode([
        'status' => 'success',
        'data' => [
            'state' => $state,
            'municipality' => $municipality,
            'colony' => $colony,
            'postal_code' => $postalCode,
            'street' => $street,
            'display_name' => (string)($payload['display_name'] ?? ''),
            'lat' => (string)($payload['lat'] ?? ''),
            'lng' => (string)($payload['lon'] ?? '')
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error en reverse geocoding: ' . $e->getMessage()
    ]);
}
?>
