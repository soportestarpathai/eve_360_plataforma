<?php
/**
 * PLD Fraccion XV - Derechos personales de uso o goce de bienes inmuebles (ARI).
 *
 * Umbrales:
 * - Identificacion: 1,605 UMA
 * - Aviso individual/acumulacion: 3,210 UMA
 */

if (!defined('PLD_FRACCION_XV_UMA_IDENTIFICACION')) {
    define('PLD_FRACCION_XV_UMA_IDENTIFICACION', 1605.0);
}
if (!defined('PLD_FRACCION_XV_UMA_AVISO')) {
    define('PLD_FRACCION_XV_UMA_AVISO', 3210.0);
}

if (!function_exists('pldFraccionXVUmbralIdentificacion')) {
    function pldFraccionXVUmbralIdentificacion(): float
    {
        return PLD_FRACCION_XV_UMA_IDENTIFICACION;
    }
}

if (!function_exists('pldFraccionXVUmbralAviso')) {
    function pldFraccionXVUmbralAviso(): float
    {
        return PLD_FRACCION_XV_UMA_AVISO;
    }
}

if (!function_exists('requireFraccionXVActiva')) {
    function requireFraccionXVActiva($pdo): array
    {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'XV');
    }
}

if (!function_exists('getIdVulnerableFraccionXV')) {
    function getIdVulnerableFraccionXV($pdo): ?int
    {
        try {
            try {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'XV'
                      AND (id_status = 1 OR id_status IS NULL)
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            } catch (Exception $e) {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'XV'
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return (int)$row['id_vulnerable'];

            try {
                $ins = $pdo->prepare("
                    INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                    VALUES ('Derechos personales de uso o goce de bienes inmuebles (ARI)', 'XV', 3210.00, 3210.00)
                ");
                $ins->execute();
                return (int)$pdo->lastInsertId();
            } catch (Exception $e) {
                return null;
            }
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionXV: ' . $e->getMessage());
            return null;
        }
    }
}
