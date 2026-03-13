<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();
require_once __DIR__ . '/../config/banxico_api.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $banxicoAPI = new BanxicoAPI();
    $seriesIds = ['SP68257', 'SF43718', 'SF46410', 'SP74660'];
    $banxicoData = $banxicoAPI->getSeriesData($seriesIds, 1800);

    $items = [];
    if (is_array($banxicoData)) {
        foreach ($banxicoData as $serie) {
            $val = number_format((float)($serie['dato'] ?? 0), 2);
            switch ($serie['idSerie'] ?? '') {
                case 'SP68257':
                    $items[] = "<i class='fa-solid fa-coins me-2 text-info'></i>UDIS: <strong>$ {$val}</strong>";
                    break;
                case 'SF43718':
                    $items[] = "<i class='fa-solid fa-dollar-sign me-2 text-success'></i>Dólar: <strong>$ {$val} MXN</strong>";
                    break;
                case 'SF46410':
                    $items[] = "<i class='fa-solid fa-euro-sign me-2 text-primary'></i>Euro: <strong>$ {$val} MXN</strong>";
                    break;
                case 'SP74660':
                    $items[] = "<i class='fa-solid fa-chart-line me-2 text-danger'></i>Inflación: <strong>{$val}%</strong>";
                    break;
            }
        }
    }

    echo json_encode([
        'status' => 'success',
        'items' => $items
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudieron cargar indicadores Banxico'
    ], JSON_UNESCAPED_UNICODE);
}

