<?php
/**
 * PLD Fracción IX — Servicios de blindaje de vehículos terrestres e inmuebles (BLI)
 *
 * Umbrales:
 * - Identificación: 2,410 UMA
 * - Aviso individual/acumulación: 4,815 UMA
 */

if (!defined('PLD_FRACCION_IX_UMA_IDENTIFICACION')) {
    define('PLD_FRACCION_IX_UMA_IDENTIFICACION', 2410.0);
}

if (!defined('PLD_FRACCION_IX_UMA_AVISO')) {
    define('PLD_FRACCION_IX_UMA_AVISO', 4815.0);
}

if (!function_exists('pldFraccionIXUmbralIdentificacion')) {
    function pldFraccionIXUmbralIdentificacion(): float
    {
        return PLD_FRACCION_IX_UMA_IDENTIFICACION;
    }
}

if (!function_exists('pldFraccionIXUmbralAviso')) {
    function pldFraccionIXUmbralAviso(): float
    {
        return PLD_FRACCION_IX_UMA_AVISO;
    }
}

if (!function_exists('requireFraccionIXActiva')) {
    /**
     * Valida que el sujeto obligado tenga activa la Fracción IX.
     */
    function requireFraccionIXActiva($pdo): array
    {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'IX');
    }
}

if (!function_exists('getIdVulnerableFraccionIX')) {
    /**
     * Obtiene id_vulnerable de Fracción IX desde cat_vulnerables.
     */
    function getIdVulnerableFraccionIX($pdo): ?int
    {
        try {
            try {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'IX'
                      AND (id_status = 1 OR id_status IS NULL)
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            } catch (Exception $e) {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'IX'
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionIX: ' . $e->getMessage());
            return null;
        }
    }
}

