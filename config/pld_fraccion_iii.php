<?php
/**
 * PLD Fracción III — Cheques de viajero (CHV)
 *
 * Umbrales:
 * - Identificación: Siempre
 * - Aviso individual/acumulación: 645 UMA
 */

if (!defined('PLD_FRACCION_III_IDENTIFICACION_SIEMPRE')) {
    define('PLD_FRACCION_III_IDENTIFICACION_SIEMPRE', true);
}

if (!defined('PLD_FRACCION_III_UMA_AVISO')) {
    define('PLD_FRACCION_III_UMA_AVISO', 645.0);
}

if (!function_exists('pldFraccionIIIIdentificacionSiempre')) {
    function pldFraccionIIIIdentificacionSiempre(): bool
    {
        return PLD_FRACCION_III_IDENTIFICACION_SIEMPRE === true;
    }
}

if (!function_exists('pldFraccionIIIUmbralAviso')) {
    function pldFraccionIIIUmbralAviso(): float
    {
        return PLD_FRACCION_III_UMA_AVISO;
    }
}

if (!function_exists('requireFraccionIIIActiva')) {
    function requireFraccionIIIActiva($pdo): array
    {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'III');
    }
}

if (!function_exists('getIdVulnerableFraccionIII')) {
    function getIdVulnerableFraccionIII($pdo): ?int
    {
        try {
            try {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'III'
                      AND (id_status = 1 OR id_status IS NULL)
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            } catch (Exception $e) {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'III'
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionIII: ' . $e->getMessage());
            return null;
        }
    }
}
