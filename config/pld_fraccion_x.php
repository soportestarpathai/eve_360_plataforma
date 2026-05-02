<?php
/**
 * PLD Fracción X — Traslado o custodia de dinero o valores.
 *
 * - Identificación: siempre.
 * - Aviso: 3,210 UMA.
 * - Si no es posible determinar el monto trasladado/custodiado: aviso siempre.
 */

if (!defined('PLD_FRACCION_X_UMA_AVISO')) {
    define('PLD_FRACCION_X_UMA_AVISO', 3210.0);
}

if (!function_exists('pldFraccionXUmbralIdentificacion')) {
    function pldFraccionXUmbralIdentificacion(): float { return 0.0; }
}

if (!function_exists('pldFraccionXUmbralAviso')) {
    function pldFraccionXUmbralAviso(): float { return PLD_FRACCION_X_UMA_AVISO; }
}

if (!function_exists('requireFraccionXActiva')) {
    function requireFraccionXActiva($pdo) {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'X');
    }
}

if (!function_exists('getIdVulnerableFraccionX')) {
    function getIdVulnerableFraccionX($pdo): ?int {
        try {
            $hasStatus = false;
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM cat_vulnerables")->fetchAll(PDO::FETCH_COLUMN);
                $hasStatus = in_array('id_status', $cols, true);
            } catch (Exception $e) {
                $hasStatus = false;
            }
            $activeSql = $hasStatus ? " AND (id_status = 1 OR id_status IS NULL)" : "";
            $stmt = $pdo->prepare("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'X'{$activeSql} LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return (int)$row['id_vulnerable'];

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO cat_vulnerables (nombre, fraccion, ruta_template, ruta_instructivo, umbral_acumulacion_uma, umbral_aviso_uma, umbral_expediente_uma)
                    VALUES ('Traslado o custodia de dinero o valores (TCV)', 'X', 'instructivo_tcv.xlsx', 'instructivo_tcv.xlsx', 3210.00, 3210.00, 0.00)
                ");
                $stmt->execute();
                return (int)$pdo->lastInsertId();
            } catch (Exception $insertError) {
                error_log('getIdVulnerableFraccionX insert: ' . $insertError->getMessage());
            }

            $stmt = $pdo->prepare("
                SELECT id_vulnerable
                FROM cat_vulnerables
                WHERE 1=1 {$activeSql}
                  AND (nombre LIKE '%Traslado%' OR nombre LIKE '%custodia%' OR nombre LIKE '%TCV%')
                LIMIT 1
            ");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionX: ' . $e->getMessage());
            return null;
        }
    }
}
