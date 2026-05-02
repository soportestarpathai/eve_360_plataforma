<?php
/**
 * Runner único E2E PLD
 *
 * Uso:
 *   php tools/qa_pld_suite_e2e.php
 *   php tools/qa_pld_suite_e2e.php --only=ii,don,avi,mjr,veh,spr5,sprall
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

date_default_timezone_set('America/Mexico_City');

$php = PHP_BINARY ?: 'php';
$baseDir = __DIR__;

$suite = [
    'jys' => 'qa_jys_e2e.php',
    'ii' => 'qa_fraccion_ii_e2e.php',
    'mpc' => 'qa_mpc_e2e.php',
    'don' => 'qa_don_e2e.php',
    'avi' => 'qa_avi_e2e.php',
    'mjr' => 'qa_mjr_e2e.php',
    'veh' => 'qa_veh_e2e.php',
    'bli' => 'qa_bli_e2e.php',
    'spr5' => 'qa_spr_subfraccion5_e2e.php',
    'sprall' => 'qa_spr_subfracciones_1_6_e2e.php',
];

$only = [];
foreach ($argv as $arg) {
    if (strpos((string)$arg, '--only=') === 0) {
        $raw = trim(substr((string)$arg, 7));
        if ($raw !== '') {
            $only = array_values(array_filter(array_map('trim', explode(',', strtolower($raw)))));
        }
    }
}

if (!empty($only)) {
    $filtered = [];
    foreach ($only as $key) {
        if (isset($suite[$key])) {
            $filtered[$key] = $suite[$key];
        } else {
            echo "[WARN] Clave desconocida en --only: {$key}\n";
        }
    }
    if (!empty($filtered)) {
        $suite = $filtered;
    } else {
        echo "[ERROR] --only no contiene claves válidas.\n";
        echo "Claves válidas: " . implode(', ', array_keys($suite)) . "\n";
        exit(1);
    }
}

echo "=== SUITE E2E PLD ===\n";
echo "PHP: {$php}\n";
echo "Inicio: " . date('Y-m-d H:i:s') . "\n";
echo "Pruebas: " . implode(', ', array_keys($suite)) . "\n";
echo "----------------------\n";

$results = [];
$start = microtime(true);

foreach ($suite as $key => $file) {
    $path = $baseDir . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        $results[$key] = ['code' => 99, 'ms' => 0, 'note' => 'script no encontrado'];
        echo "\n[{$key}] FAIL (script no encontrado: {$file})\n";
        continue;
    }

    echo "\n[{$key}] Ejecutando {$file}\n";
    $t0 = microtime(true);
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($path);
    passthru($cmd, $exitCode);
    $ms = (int)round((microtime(true) - $t0) * 1000);

    $results[$key] = ['code' => (int)$exitCode, 'ms' => $ms, 'note' => ''];
    echo "[{$key}] " . ($exitCode === 0 ? 'PASS' : 'FAIL') . " ({$ms} ms)\n";
}

$totalMs = (int)round((microtime(true) - $start) * 1000);
$passed = 0;
$failed = 0;
foreach ($results as $r) {
    if (($r['code'] ?? 1) === 0) $passed++; else $failed++;
}

echo "\n======================\n";
echo "Resumen:\n";
foreach ($results as $k => $r) {
    $status = (($r['code'] ?? 1) === 0) ? 'PASS' : 'FAIL';
    $extra = ($r['note'] ?? '') !== '' ? (' - ' . $r['note']) : '';
    echo "- {$k}: {$status} ({$r['ms']} ms){$extra}\n";
}
echo "----------------------\n";
echo "Total: " . count($results) . " | PASS: {$passed} | FAIL: {$failed}\n";
echo "Duración total: {$totalMs} ms\n";
echo "Fin: " . date('Y-m-d H:i:s') . "\n";

exit($failed > 0 ? 1 : 0);
