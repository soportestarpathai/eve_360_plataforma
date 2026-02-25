<?php
/**
 * Importador de catálogo SEPOMEX.
 *
 * Uso:
 *   php tools/import_sepomex_csv.php "C:\ruta\CPdescarga.txt" [--truncate] [--delimiter=|]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse desde CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

function usage(): void
{
    $msg = <<<TXT
Uso:
  php tools/import_sepomex_csv.php <archivo_sep> [--truncate] [--delimiter=|]

Parámetros:
  <archivo_sep>   Ruta del archivo SEPOMEX (CPdescarga o CSV equivalente).
  --truncate      Limpia cat_sepomex antes de importar.
  --delimiter     Fuerza delimitador (, ; |). Si no se indica, se autodetecta.

TXT;
    fwrite(STDOUT, $msg);
}

function normalizeTextValue($value): string
{
    $text = trim((string)$value);
    if ($text === '') return '';

    if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding')) {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1,Windows-1252,UTF-8');
        }
    } elseif (function_exists('iconv')) {
        $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $text);
        if (is_string($converted) && $converted !== '') {
            $text = $converted;
        }
    }

    return trim($text);
}

function detectDelimiter(string $line): string
{
    $candidates = ['|', ';', ','];
    $best = ',';
    $bestCount = -1;
    foreach ($candidates as $delimiter) {
        $count = substr_count($line, $delimiter);
        if ($count > $bestCount) {
            $bestCount = $count;
            $best = $delimiter;
        }
    }
    return $best;
}

try {
    $args = $argv;
    array_shift($args);
    if (empty($args)) {
        usage();
        exit(1);
    }

    $filePath = null;
    $truncate = false;
    $forcedDelimiter = null;

    foreach ($args as $arg) {
        if ($arg === '--truncate') {
            $truncate = true;
            continue;
        }
        if (strpos($arg, '--delimiter=') === 0) {
            $forcedDelimiter = substr($arg, 12);
            continue;
        }
        if ($filePath === null) {
            $filePath = $arg;
        }
    }

    if ($filePath === null || !is_file($filePath)) {
        fwrite(STDERR, "Archivo no encontrado: {$filePath}\n");
        exit(1);
    }

    $existsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cat_sepomex'
    ");
    $existsStmt->execute();
    if ((int)$existsStmt->fetchColumn() === 0) {
        fwrite(STDERR, "No existe la tabla cat_sepomex. Ejecute la migración add_sepomex_catalog_and_geo_fields.sql.\n");
        exit(1);
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        fwrite(STDERR, "No fue posible abrir el archivo.\n");
        exit(1);
    }

    $sampleLine = fgets($handle);
    if ($sampleLine === false) {
        fclose($handle);
        fwrite(STDERR, "Archivo vacío.\n");
        exit(1);
    }
    rewind($handle);

    $delimiter = $forcedDelimiter !== null && $forcedDelimiter !== '' ? $forcedDelimiter : detectDelimiter($sampleLine);
    $header = fgetcsv($handle, 0, $delimiter);
    if (!is_array($header) || empty($header)) {
        fclose($handle);
        fwrite(STDERR, "No se pudo leer el encabezado del archivo.\n");
        exit(1);
    }

    $normalizedHeader = [];
    foreach ($header as $index => $name) {
        $cleanName = strtolower(normalizeTextValue((string)$name));
        $normalizedHeader[$cleanName] = $index;
    }

    $requiredCols = ['d_codigo', 'd_asenta', 'd_mnpio', 'd_estado'];
    $missing = [];
    foreach ($requiredCols as $column) {
        if (!array_key_exists($column, $normalizedHeader)) {
            $missing[] = $column;
        }
    }
    if (!empty($missing)) {
        fclose($handle);
        fwrite(STDERR, 'Faltan columnas requeridas: ' . implode(', ', $missing) . "\n");
        exit(1);
    }

    if ($truncate) {
        $pdo->exec('TRUNCATE TABLE cat_sepomex');
        fwrite(STDOUT, "Tabla cat_sepomex truncada.\n");
    }

    $insertSql = "
        INSERT IGNORE INTO cat_sepomex
        (codigo_postal, estado, municipio, colonia, tipo_asentamiento, ciudad, zona, c_estado, c_mnpio, c_oficina)
        VALUES
        (:codigo_postal, :estado, :municipio, :colonia, :tipo_asentamiento, :ciudad, :zona, :c_estado, :c_mnpio, :c_oficina)
    ";
    $insertStmt = $pdo->prepare($insertSql);

    $pdo->beginTransaction();

    $total = 0;
    $inserted = 0;
    $skipped = 0;
    $batch = 0;

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $total++;

        $cpRaw = normalizeTextValue($row[$normalizedHeader['d_codigo']] ?? '');
        $digits = preg_replace('/\D+/', '', $cpRaw);
        if ($digits === '') {
            $skipped++;
            continue;
        }
        $codigoPostal = str_pad(substr($digits, 0, 5), 5, '0', STR_PAD_LEFT);
        if (!preg_match('/^\d{5}$/', $codigoPostal)) {
            $skipped++;
            continue;
        }

        $estado = normalizeTextValue($row[$normalizedHeader['d_estado']] ?? '');
        $municipio = normalizeTextValue($row[$normalizedHeader['d_mnpio']] ?? '');
        $colonia = normalizeTextValue($row[$normalizedHeader['d_asenta']] ?? '');

        if ($estado === '' || $municipio === '' || $colonia === '') {
            $skipped++;
            continue;
        }

        $tipoAsentamiento = normalizeTextValue($row[$normalizedHeader['d_tipo_asenta']] ?? '');
        $ciudad = normalizeTextValue($row[$normalizedHeader['d_ciudad']] ?? '');
        $zona = normalizeTextValue($row[$normalizedHeader['d_zona']] ?? '');
        $cEstado = normalizeTextValue($row[$normalizedHeader['c_estado']] ?? '');
        $cMnpio = normalizeTextValue($row[$normalizedHeader['c_mnpio']] ?? '');
        $cOficina = normalizeTextValue($row[$normalizedHeader['c_oficina']] ?? '');

        $insertStmt->execute([
            'codigo_postal' => $codigoPostal,
            'estado' => $estado,
            'municipio' => $municipio,
            'colonia' => $colonia,
            'tipo_asentamiento' => $tipoAsentamiento !== '' ? $tipoAsentamiento : null,
            'ciudad' => $ciudad !== '' ? $ciudad : null,
            'zona' => $zona !== '' ? $zona : null,
            'c_estado' => $cEstado !== '' ? $cEstado : null,
            'c_mnpio' => $cMnpio !== '' ? $cMnpio : null,
            'c_oficina' => $cOficina !== '' ? $cOficina : null
        ]);

        if ($insertStmt->rowCount() > 0) {
            $inserted++;
        }

        $batch++;
        if ($batch >= 5000) {
            $pdo->commit();
            $pdo->beginTransaction();
            $batch = 0;
        }
    }

    fclose($handle);
    $pdo->commit();

    fwrite(STDOUT, "Importación completada.\n");
    fwrite(STDOUT, "Total leídos: {$total}\n");
    fwrite(STDOUT, "Insertados: {$inserted}\n");
    fwrite(STDOUT, "Omitidos: {$skipped}\n");
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Error en importación SEPOMEX: " . $e->getMessage() . "\n");
    exit(1);
}
?>
