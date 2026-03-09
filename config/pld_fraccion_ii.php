<?php
/**
 * PLD Fracción II — Tarjetas de Servicio y de Crédito (TSC)
 * Constantes y helpers según LFPIORPI, RCG, SPPLD.
 *
 * Umbrales según RCG Fracción II:
 * - Identificación: 805 UMA
 * - Aviso: 1,285 UMA
 */

if (!defined('PLD_FRACCION_II_UMA_IDENTIFICACION')) {
    define('PLD_FRACCION_II_UMA_IDENTIFICACION', 805.0);
}

if (!defined('PLD_FRACCION_II_UMA_AVISO')) {
    define('PLD_FRACCION_II_UMA_AVISO', 1285.0);
}

if (!function_exists('getUmbralIdentificacionII')) {
    /**
     * Umbral en UMAs para identificación del cliente (Fracción II - TSC).
     *
     * @return float
     */
    function getUmbralIdentificacionII() {
        return PLD_FRACCION_II_UMA_IDENTIFICACION;
    }
}

if (!function_exists('getUmbralAvisoII')) {
    /**
     * Umbral en UMAs para aviso (Fracción II - TSC).
     *
     * @return float
     */
    function getUmbralAvisoII() {
        return PLD_FRACCION_II_UMA_AVISO;
    }
}

if (!function_exists('requireFraccionIIActiva')) {
    /**
     * Valida que el sujeto obligado esté habilitado en el padrón PLD con Fracción II activa.
     * VAL-PLD-001 (especializado para Tarjetas de Servicio y de Crédito).
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return array Mismo formato que validatePatronPLD
     */
    function requireFraccionIIActiva($pdo) {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'II');
    }
}

if (!function_exists('getIdVulnerableFraccionII')) {
    /**
     * Obtiene id_vulnerable de la fracción II en cat_vulnerables.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return int|null id_vulnerable o null
     */
    function getIdVulnerableFraccionII($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'II' AND (id_status = 1 OR id_status IS NULL) LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int) $row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionII: ' . $e->getMessage());
            return null;
        }
    }
}
