<?php
/**
 * PLD Fracción V Bis — Recepción de Recursos para Desarrollo Inmobiliario
 * Constantes y helpers (LFPIORPI, RCG, SPPLD).
 *
 * UMA según RCG Fracción V Bis:
 * - Expediente: SIEMPRE obligatorio (sin importar monto)
 * - Aviso individual / acumulación: 8,025 UMA
 */

if (!defined('PLD_FRACCION_V_BIS_UMA_AVISO')) {
    define('PLD_FRACCION_V_BIS_UMA_AVISO', 8025.0);
}

if (!function_exists('requiereExpedienteVBis')) {
    /**
     * En Fracción V Bis la identificación es SIEMPRE obligatoria.
     * VAL-PLD-005 Fracción V Bis: Expediente del cliente/usuario que aporta recursos.
     *
     * @return bool Siempre true
     */
    function requiereExpedienteVBis() {
        return true;
    }
}

if (!function_exists('getUmbralAvisoVBis')) {
    /**
     * Umbral en UMAs para aviso individual y acumulación (Fracción V Bis).
     *
     * @return float
     */
    function getUmbralAvisoVBis() {
        return PLD_FRACCION_V_BIS_UMA_AVISO;
    }
}

if (!function_exists('requireFraccionVBisActiva')) {
    /**
     * Valida que el sujeto obligado esté habilitado en el padrón PLD con Fracción V Bis activa.
     * VAL-PLD-001 (especializado para Recepción de Recursos para Desarrollo Inmobiliario).
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return array Mismo formato que validatePatronPLD
     */
    function requireFraccionVBisActiva($pdo) {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'V Bis');
    }
}

if (!function_exists('getIdVulnerableFraccionVBis')) {
    /**
     * Obtiene id_vulnerable de la fracción V Bis en cat_vulnerables.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return int|null id_vulnerable o null
     */
    function getIdVulnerableFraccionVBis($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'V Bis' AND (id_status = 1 OR id_status IS NULL) LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int) $row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionVBis: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('registrarRiesgoSancionableVBis')) {
    /**
     * VAL-PLD-028 — Marca riesgo sancionable cuando se detectan triggers.
     * Triggers: omisión de avisos, aviso extemporáneo, aviso sin formalidades, incumplimiento art. 33 Bis/33 Ter.
     * Actualiza config_empresa.riesgo_sancion_administrativa = 1 y opcionalmente registra en bitácora.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @param string $trigger Omisión de avisos | Aviso extemporáneo | Aviso sin formalidades | Incumplimiento art. 33 Bis/33 Ter
     * @param string|null $detalle Descripción opcional
     * @return bool True si se actualizó
     */
    function registrarRiesgoSancionableVBis($pdo, $trigger, $detalle = null) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'riesgo_sancion_administrativa'");
            if ($stmt->fetch(PDO::FETCH_ASSOC)['c'] == 0) {
                return false;
            }
            $stmt = $pdo->prepare("UPDATE config_empresa SET riesgo_sancion_administrativa = 1 WHERE id_config = 1");
            $stmt->execute();
            if (function_exists('logChange')) {
                logChange($pdo, 0, 'RIESGO_SANCION_ADMINISTRATIVA', 'config_empresa', 1, null, [
                    'trigger' => $trigger,
                    'detalle' => $detalle,
                    'fraccion' => 'V Bis'
                ]);
            }
            return true;
        } catch (Exception $e) {
            error_log('registrarRiesgoSancionableVBis: ' . $e->getMessage());
            return false;
        }
    }
}
