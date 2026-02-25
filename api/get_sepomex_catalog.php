<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

function sepomexTableExists(PDO $pdo, string $tableName): bool
{
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tableName]);
    return (int)$stmt->fetchColumn() > 0;
}

function normalizeSepomexText(string $value): string
{
    return trim($value);
}

try {
    if (!sepomexTableExists($pdo, 'cat_sepomex')) {
        http_response_code(503);
        echo json_encode([
            'status' => 'error',
            'code' => 'CATALOGO_NO_DISPONIBLE',
            'message' => 'El catálogo SEPOMEX no está cargado. Ejecute la migración e importación.'
        ]);
        exit;
    }

    $mode = strtolower(trim((string)($_GET['mode'] ?? 'states')));
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
    if ($limit < 1) $limit = 1;
    if ($limit > 1000) $limit = 1000;

    switch ($mode) {
        case 'states': {
            $sql = "SELECT DISTINCT estado FROM cat_sepomex WHERE estado IS NOT NULL AND estado <> '' ORDER BY estado ASC LIMIT {$limit}";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['status' => 'success', 'data' => array_values($rows)]);
            exit;
        }

        case 'municipalities': {
            $state = normalizeSepomexText((string)($_GET['state'] ?? ''));
            if ($state === '') {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'El parámetro state es obligatorio.']);
                exit;
            }

            $sql = "SELECT DISTINCT municipio FROM cat_sepomex WHERE estado = :estado AND municipio IS NOT NULL AND municipio <> '' ORDER BY municipio ASC LIMIT {$limit}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['estado' => $state]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['status' => 'success', 'data' => array_values($rows)]);
            exit;
        }

        case 'postal_codes': {
            $state = normalizeSepomexText((string)($_GET['state'] ?? ''));
            $municipality = normalizeSepomexText((string)($_GET['municipality'] ?? ''));
            $prefix = preg_replace('/\D+/', '', (string)($_GET['q'] ?? ''));

            if ($state === '') {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'El parámetro state es obligatorio.']);
                exit;
            }
            if ($municipality === '') {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'El parámetro municipality es obligatorio.']);
                exit;
            }

            $sql = "SELECT DISTINCT codigo_postal 
                    FROM cat_sepomex 
                    WHERE estado = :estado 
                      AND municipio = :municipio 
                      AND codigo_postal IS NOT NULL
                      AND codigo_postal <> ''";

            $params = ['estado' => $state, 'municipio' => $municipality];
            if ($prefix !== '') {
                $sql .= " AND codigo_postal LIKE :prefix";
                $params['prefix'] = $prefix . '%';
            }
            $sql .= " ORDER BY codigo_postal ASC LIMIT {$limit}";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['status' => 'success', 'data' => array_values($rows)]);
            exit;
        }

        case 'postal_code_lookup': {
            $postalCode = preg_replace('/\D+/', '', (string)($_GET['postal_code'] ?? ''));
            $requestedMunicipality = normalizeSepomexText((string)($_GET['municipality'] ?? ''));
            if (!preg_match('/^\d{5}$/', $postalCode)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'El parámetro postal_code debe tener 5 dígitos.']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT estado, municipio, colonia, codigo_postal
                FROM cat_sepomex
                WHERE codigo_postal = :cp
                ORDER BY estado ASC, municipio ASC, colonia ASC
                LIMIT 2000
            ");
            $stmt->execute(['cp' => $postalCode]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                echo json_encode(['status' => 'success', 'data' => null]);
                exit;
            }

            $state = (string)($rows[0]['estado'] ?? '');
            $municipalities = [];
            $coloniasByMunicipality = [];

            foreach ($rows as $row) {
                $municipality = trim((string)($row['municipio'] ?? ''));
                $colonia = trim((string)($row['colonia'] ?? ''));
                if ($municipality === '') continue;
                if (!isset($coloniasByMunicipality[$municipality])) {
                    $coloniasByMunicipality[$municipality] = [];
                    $municipalities[] = $municipality;
                }
                if ($colonia !== '') {
                    $coloniasByMunicipality[$municipality][] = $colonia;
                }
            }

            foreach ($coloniasByMunicipality as $municipality => $items) {
                $coloniasByMunicipality[$municipality] = array_values(array_unique($items));
            }

            $selectedMunicipality = '';
            if ($requestedMunicipality !== '' && isset($coloniasByMunicipality[$requestedMunicipality])) {
                $selectedMunicipality = $requestedMunicipality;
            } elseif (!empty($municipalities)) {
                $selectedMunicipality = $municipalities[0];
            }

            $selectedColonias = $selectedMunicipality !== '' && isset($coloniasByMunicipality[$selectedMunicipality])
                ? $coloniasByMunicipality[$selectedMunicipality]
                : [];

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'postal_code' => $postalCode,
                    'state' => $state,
                    'municipality' => $selectedMunicipality,
                    'municipalities' => $municipalities,
                    'colonias' => $selectedColonias,
                    'colonias_by_municipality' => $selectedColonias,
                    'matches' => count($rows)
                ]
            ]);
            exit;
        }

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Modo de consulta no soportado.']);
            exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al consultar SEPOMEX: ' . $e->getMessage()
    ]);
}
?>
