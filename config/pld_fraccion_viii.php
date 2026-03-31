<?php
/**
 * PLD Fracción VIII — Comercialización / Distribución de Vehículos (VEH)
 *
 * Umbrales (UMAs):
 * - Identificación: 3,210
 * - Aviso individual / acumulación: 6,420
 */

if (!defined('PLD_FRACCION_VIII_UMA_IDENTIFICACION')) {
    define('PLD_FRACCION_VIII_UMA_IDENTIFICACION', 3210.0);
}

if (!defined('PLD_FRACCION_VIII_UMA_AVISO')) {
    define('PLD_FRACCION_VIII_UMA_AVISO', 6420.0);
}

if (!function_exists('getUmbralIdentificacionVeh')) {
    function getUmbralIdentificacionVeh(): float
    {
        return PLD_FRACCION_VIII_UMA_IDENTIFICACION;
    }
}

if (!function_exists('getUmbralAvisoVeh')) {
    function getUmbralAvisoVeh(): float
    {
        return PLD_FRACCION_VIII_UMA_AVISO;
    }
}

if (!function_exists('requireFraccionVIIIActiva')) {
    /**
     * Valida que el sujeto obligado esté habilitado con Fracción VIII activa.
     */
    function requireFraccionVIIIActiva($pdo)
    {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'VIII');
    }
}

if (!function_exists('getIdVulnerableFraccionVIII')) {
    /**
     * Obtiene id_vulnerable para fracción VIII desde cat_vulnerables.
     */
    function getIdVulnerableFraccionVIII($pdo): ?int
    {
        try {
            try {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'VIII'
                      AND (id_status = 1 OR id_status IS NULL)
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            } catch (Exception $e) {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'VIII'
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionVIII: ' . $e->getMessage());
            return null;
        }
    }
}

