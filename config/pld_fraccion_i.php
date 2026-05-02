<?php
/**
 * PLD Fracción I — Juegos con apuesta, concursos o sorteos (JYS)
 *
 * Umbrales LFPIORPI/RCG:
 * - Identificación: 325 UMA
 * - Aviso: 645 UMA
 */

if (!defined('PLD_FRACCION_I_UMA_IDENTIFICACION')) {
    define('PLD_FRACCION_I_UMA_IDENTIFICACION', 325.0);
}
if (!defined('PLD_FRACCION_I_UMA_AVISO')) {
    define('PLD_FRACCION_I_UMA_AVISO', 645.0);
}

if (!function_exists('getUmbralIdentificacionJYS')) {
    function getUmbralIdentificacionJYS(): float
    {
        return PLD_FRACCION_I_UMA_IDENTIFICACION;
    }
}

if (!function_exists('getUmbralAvisoJYS')) {
    function getUmbralAvisoJYS(): float
    {
        return PLD_FRACCION_I_UMA_AVISO;
    }
}

if (!function_exists('requireFraccionIActiva')) {
    /**
     * Valida que el sujeto obligado esté habilitado en padrón PLD con fracción I.
     */
    function requireFraccionIActiva(PDO $pdo): array
    {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'I');
    }
}

if (!function_exists('getIdVulnerableFraccionI')) {
    /**
     * Obtiene id_vulnerable de la fracción I (JYS) en cat_vulnerables.
     */
    function getIdVulnerableFraccionI(PDO $pdo): ?int
    {
        try {
            try {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'I'
                      AND (id_status = 1 OR id_status IS NULL)
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            } catch (Exception $e) {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'I'
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionI: ' . $e->getMessage());
            return null;
        }
    }
}
