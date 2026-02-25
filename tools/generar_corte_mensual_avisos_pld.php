<?php
/**
 * Wrapper CLI para generar corte mensual PLD.
 * Uso:
 *   php tools/generar_corte_mensual_avisos_pld.php --mes=2 --anio=2026 --force=1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(405);
    echo "Solo CLI\n";
    exit(1);
}

foreach ($argv as $arg) {
    if (preg_match('/^--mes=(\d{1,2})$/', $arg, $m)) {
        $_GET['mes'] = $m[1];
    } elseif (preg_match('/^--anio=(\d{4})$/', $arg, $m)) {
        $_GET['anio'] = $m[1];
    } elseif ($arg === '--force=1' || $arg === '--force') {
        $_GET['force'] = '1';
    }
}

require __DIR__ . '/../api/generar_corte_mensual_avisos_pld.php';

