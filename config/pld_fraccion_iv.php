<?php
/**
 * PLD Fracción IV — Mutuo, garantía, préstamos o créditos (MPC)
 *
 * Umbrales:
 * - Identificación: Siempre
 * - Aviso individual/acumulación: 1,605 UMA
 */

if (!defined('PLD_FRACCION_IV_IDENTIFICACION_SIEMPRE')) {
    define('PLD_FRACCION_IV_IDENTIFICACION_SIEMPRE', true);
}

if (!defined('PLD_FRACCION_IV_UMA_AVISO')) {
    define('PLD_FRACCION_IV_UMA_AVISO', 1605.0);
}

if (!function_exists('pldFraccionIVIdentificacionSiempre')) {
    function pldFraccionIVIdentificacionSiempre(): bool
    {
        return PLD_FRACCION_IV_IDENTIFICACION_SIEMPRE === true;
    }
}

if (!function_exists('pldFraccionIVUmbralAviso')) {
    function pldFraccionIVUmbralAviso(): float
    {
        return PLD_FRACCION_IV_UMA_AVISO;
    }
}

if (!function_exists('requireFraccionIVActiva')) {
    /**
     * Valida que el sujeto obligado tenga activa la Fracción IV.
     */
    function requireFraccionIVActiva($pdo): array
    {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'IV');
    }
}

if (!function_exists('getIdVulnerableFraccionIV')) {
    /**
     * Obtiene id_vulnerable de Fracción IV desde cat_vulnerables.
     */
    function getIdVulnerableFraccionIV($pdo): ?int
    {
        try {
            try {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'IV'
                      AND (id_status = 1 OR id_status IS NULL)
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            } catch (Exception $e) {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'IV'
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionIV: ' . $e->getMessage());
            return null;
        }
    }
}

