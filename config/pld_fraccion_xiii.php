<?php
/**
 * PLD Fracción XIII — Recepción de Donativos
 * Constantes y helpers para el módulo de donativos (LFPIORPI, RCG, SPPLD).
 *
 * UMA según RCG:
 * - Expediente donante: monto_donativo ≥ 1,605 UMA (o relación de negocios)
 * - Aviso individual / acumulación: 3,210 UMA
 */

if (!defined('PLD_FRACCION_XIII_UMA_IDENTIFICACION')) {
    define('PLD_FRACCION_XIII_UMA_IDENTIFICACION', 1605.0);
}
if (!defined('PLD_FRACCION_XIII_UMA_AVISO')) {
    define('PLD_FRACCION_XIII_UMA_AVISO', 3210.0);
}

if (!function_exists('requiereExpedienteDonante')) {
    /**
     * Indica si por monto (en UMAs) se requiere expediente de identificación del donante.
     * VAL-PLD-005: obligatorio cuando monto_donativo ≥ 1,605 UMA o exista relación de negocios.
     *
     * @param float $monto_uma Monto del donativo en UMAs
     * @param bool $tiene_relacion_negocios Si existe relación de negocios (si true, siempre true)
     * @return bool
     */
    function requiereExpedienteDonante($monto_uma, $tiene_relacion_negocios = false) {
        if ($tiene_relacion_negocios) {
            return true;
        }
        return $monto_uma >= PLD_FRACCION_XIII_UMA_IDENTIFICACION;
    }
}

if (!function_exists('getUmbralIdentificacionDonativo')) {
    /**
     * Devuelve el umbral en UMAs para exigir expediente del donante (Fracción XIII).
     *
     * @return float
     */
    function getUmbralIdentificacionDonativo() {
        return PLD_FRACCION_XIII_UMA_IDENTIFICACION;
    }
}

if (!function_exists('getUmbralAvisoDonativo')) {
    /**
     * Devuelve el umbral en UMAs para aviso individual/acumulación (Fracción XIII).
     *
     * @return float
     */
    function getUmbralAvisoDonativo() {
        return PLD_FRACCION_XIII_UMA_AVISO;
    }
}

if (!function_exists('requireFraccionXIIIActiva')) {
    /**
     * Valida que el sujeto obligado esté habilitado en el padrón PLD con Fracción XIII activa.
     * Usar antes de registrar donativos o enviar avisos de donativos.
     * VAL-PLD-001 (especializado para Recepción de Donativos).
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return array Mismo formato que validatePatronPLD; si falla por fracción: estatus NO_HABILITADO_PLD, razon indicando Fracción XIII
     */
    function requireFraccionXIIIActiva($pdo) {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'XIII');
    }
}

if (!function_exists('getIdVulnerableFraccionXIII')) {
    /**
     * Obtiene id_vulnerable de la fracción XIII (Donativos) en cat_vulnerables.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return int|null id_vulnerable o null si no existe
     */
    function getIdVulnerableFraccionXIII($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'XIII' AND (id_status = 1 OR id_status IS NULL) LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int) $row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionXIII: ' . $e->getMessage());
            return null;
        }
    }
}
