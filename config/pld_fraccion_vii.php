<?php
/**
 * PLD Fracción VII - Obras de arte (OBA)
 *
 * Umbrales:
 * - Identificación: 2,410 UMA
 * - Aviso individual/acumulación: 4,815 UMA
 */

if (!defined('PLD_FRACCION_VII_UMA_IDENTIFICACION')) {
    define('PLD_FRACCION_VII_UMA_IDENTIFICACION', 2410.0);
}

if (!defined('PLD_FRACCION_VII_UMA_AVISO')) {
    define('PLD_FRACCION_VII_UMA_AVISO', 4815.0);
}

if (!function_exists('pldFraccionVIIUmbralIdentificacion')) {
    function pldFraccionVIIUmbralIdentificacion(): float
    {
        return PLD_FRACCION_VII_UMA_IDENTIFICACION;
    }
}

if (!function_exists('pldFraccionVIIUmbralAviso')) {
    function pldFraccionVIIUmbralAviso(): float
    {
        return PLD_FRACCION_VII_UMA_AVISO;
    }
}

if (!function_exists('requireFraccionVIIActiva')) {
    function requireFraccionVIIActiva($pdo): array
    {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'VII');
    }
}

if (!function_exists('getIdVulnerableFraccionVII')) {
    function getIdVulnerableFraccionVII($pdo): ?int
    {
        try {
            try {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'VII'
                      AND (id_status = 1 OR id_status IS NULL)
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            } catch (Exception $e) {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'VII'
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionVII: ' . $e->getMessage());
            return null;
        }
    }
}
