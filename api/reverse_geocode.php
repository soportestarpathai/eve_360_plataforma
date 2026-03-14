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

function requestJson(string $url, string $userAgent = ''): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    if ($userAgent !== '') {
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => $curlError !== '' ? $curlError : ('HTTP ' . $httpCode),
            'payload' => null
        ];
    }

    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'error' => 'Respuesta JSON inválida',
            'payload' => null
        ];
    }

    return [
        'ok' => true,
        'http_code' => $httpCode,
        'error' => '',
        'payload' => $payload
    ];
}

function normalizeAddressResult(array $payload): ?array
{
    $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];
    $displayName = trim((string)($payload['display_name'] ?? $payload['formatted'] ?? ''));

    $state = pickAddressField($address, ['state', 'region', 'state_district', 'principalSubdivision']);
    $municipality = pickAddressField($address, ['county', 'city', 'town', 'municipality', 'city_district', 'district', 'locality']);
    $colony = pickAddressField($address, ['suburb', 'neighbourhood', 'quarter', 'hamlet', 'village']);
    $postalCode = preg_replace('/\D+/', '', (string)pickAddressField($address, ['postcode', 'postal_code']));
    if (strlen($postalCode) > 5) {
        $postalCode = substr($postalCode, 0, 5);
    }

    $streetName = pickAddressField($address, ['road', 'pedestrian', 'residential', 'footway', 'street']);
    $streetNumber = pickAddressField($address, ['house_number', 'houseNumber']);
    $street = trim($streetName . ($streetNumber !== '' ? ' ' . $streetNumber : ''));

    // BigDataCloud fallback shape
    if ($state === '') {
        $state = trim((string)($payload['principalSubdivision'] ?? ''));
    }
    if ($municipality === '') {
        $municipality = trim((string)($payload['city'] ?? $payload['locality'] ?? ''));
    }
    if ($postalCode === '') {
        $postalCode = preg_replace('/\D+/', '', (string)($payload['postcode'] ?? $payload['postalCode'] ?? ''));
        if (strlen($postalCode) > 5) {
            $postalCode = substr($postalCode, 0, 5);
        }
    }
    if ($street === '') {
        $street = trim((string)($payload['localityInfo']['administrative'][0]['name'] ?? ''));
    }

    // Si address está vacío pero hay display_name, intentar extraer algo (zonas rurales/remotas)
    if (empty($address) && $displayName !== '') {
        $parts = array_map('trim', explode(',', $displayName));
        $parts = array_values(array_filter($parts));
        $n = count($parts);
        if ($n >= 1 && $street === '') {
            $street = $parts[0];
        }
        if ($n >= 2 && $colony === '') {
            $colony = $parts[1];
        }
        foreach ($parts as $p) {
            if (preg_match('/^\d{5}$/', $p) && $postalCode === '') {
                $postalCode = $p;
                break;
            }
        }
        if ($n >= 4 && $municipality === '') {
            $municipality = $parts[$n - 4] ?? $parts[$n - 3] ?? '';
        }
        if ($n >= 3 && $state === '') {
            $state = $parts[$n - 3] ?? $parts[$n - 2] ?? '';
        }
    }

    $hasAny = ($state !== '' || $municipality !== '' || $colony !== '' || $postalCode !== '' || $street !== '' || $displayName !== '');
    if (!$hasAny) {
        return null;
    }

    return [
        'state' => $state,
        'municipality' => $municipality,
        'colony' => $colony,
        'postal_code' => $postalCode,
        'street' => $street,
        'display_name' => $displayName
    ];
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

    $nominatimUrl = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&accept-language=es&countrycodes=mx&zoom=18'
        . '&lat=' . rawurlencode((string)$lat)
        . '&lon=' . rawurlencode((string)$lng);
    $mapsCoUrl = 'https://geocode.maps.co/reverse?lat=' . rawurlencode((string)$lat)
        . '&lon=' . rawurlencode((string)$lng);
    $bigDataCloudUrl = 'https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=' . rawurlencode((string)$lat)
        . '&longitude=' . rawurlencode((string)$lng)
        . '&localityLanguage=es';

    $providers = [
        ['name' => 'nominatim', 'url' => $nominatimUrl, 'ua' => 'EVE360-PLD/1.0 (reverse-geocode; cumplimiento PLD)'],
        ['name' => 'mapsco', 'url' => $mapsCoUrl, 'ua' => 'EVE360-PLD/1.0 (reverse-geocode fallback)'],
        ['name' => 'bigdatacloud', 'url' => $bigDataCloudUrl, 'ua' => 'EVE360-PLD/1.0 (reverse-geocode fallback)']
    ];

    $providerErrors = [];
    $normalized = null;
    $usedProvider = '';
    $providerPayload = null;

    foreach ($providers as $provider) {
        $result = requestJson($provider['url'], $provider['ua']);
        if (!$result['ok']) {
            $providerErrors[] = $provider['name'] . ': ' . $result['error'];
            continue;
        }

        $candidate = normalizeAddressResult($result['payload']);
        if ($candidate !== null) {
            $normalized = $candidate;
            $usedProvider = $provider['name'];
            $providerPayload = $result['payload'];
            break;
        }

        $providerErrors[] = $provider['name'] . ': sin datos útiles';
    }

    if ($normalized === null) {
        http_response_code(502);
        $errorText = implode(' | ', $providerErrors);
        if (strlen($errorText) > 350) {
            $errorText = substr($errorText, 0, 350) . '...';
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'No se pudo consultar geocodificación en este momento.',
            'details' => $errorText
        ]);
        exit;
    }

    $normalized['lat'] = (string)($providerPayload['lat'] ?? $providerPayload['latitude'] ?? $lat);
    $normalized['lng'] = (string)($providerPayload['lon'] ?? $providerPayload['longitude'] ?? $lng);
    $normalized['provider'] = $usedProvider;

    echo json_encode([
        'status' => 'success',
        'data' => $normalized
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error en reverse geocoding: ' . $e->getMessage()
    ]);
}
?>
