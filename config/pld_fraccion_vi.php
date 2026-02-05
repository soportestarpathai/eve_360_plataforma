<?php
/**
 * PLD Fracción VI — Metales preciosos, piedras preciosas, joyas o relojes
 * Constantes y helpers (LFPIORPI, RCG, SPPLD).
 *
 * UMA según RCG Fracción VI:
 * - Expediente: obligatorio cuando monto_operacion ≥ 805 UMA
 * - Aviso individual / acumulación: 1,605 UMA
 * - Restricción efectivo/metales: operaciones ≥ 3,210 UMA (VAL-PLD-027)
 */

if (!defined('PLD_FRACCION_VI_UMA_IDENTIFICACION')) {
    define('PLD_FRACCION_VI_UMA_IDENTIFICACION', 805.0);
}
if (!defined('PLD_FRACCION_VI_UMA_AVISO')) {
    define('PLD_FRACCION_VI_UMA_AVISO', 1605.0);
}
if (!defined('PLD_FRACCION_VI_UMA_RESTRICCION_EFECTIVO')) {
    define('PLD_FRACCION_VI_UMA_RESTRICCION_EFECTIVO', 3210.0);
}

/** Formas de pago prohibidas cuando monto ≥ umbral (VAL-PLD-027) */
if (!defined('PLD_FRACCION_VI_FORMAS_PAGO_PROHIBIDAS')) {
    define('PLD_FRACCION_VI_FORMAS_PAGO_PROHIBIDAS', ['efectivo', 'metales_preciosos', 'metales']);
}

if (!function_exists('requiereExpedienteMetalesJoyas')) {
    /**
     * Indica si por monto (en UMAs) se requiere expediente en Fracción VI.
     * VAL-PLD-005: identificación obligatoria cuando monto_operacion ≥ 805 UMA.
     *
     * @param float $monto_uma Monto de la operación en UMAs
     * @return bool
     */
    function requiereExpedienteMetalesJoyas($monto_uma) {
        return $monto_uma >= PLD_FRACCION_VI_UMA_IDENTIFICACION;
    }
}

if (!function_exists('getUmbralIdentificacionMetalesJoyas')) {
    /** @return float Umbral UMA para exigir expediente (805). */
    function getUmbralIdentificacionMetalesJoyas() {
        return PLD_FRACCION_VI_UMA_IDENTIFICACION;
    }
}

if (!function_exists('getUmbralAvisoMetalesJoyas')) {
    /** @return float Umbral UMA para aviso individual/acumulación (1,605). */
    function getUmbralAvisoMetalesJoyas() {
        return PLD_FRACCION_VI_UMA_AVISO;
    }
}

if (!function_exists('getUmbralRestriccionEfectivoMetalesJoyas')) {
    /** @return float Umbral UMA desde el cual está prohibido efectivo/metales (3,210). */
    function getUmbralRestriccionEfectivoMetalesJoyas() {
        return PLD_FRACCION_VI_UMA_RESTRICCION_EFECTIVO;
    }
}

if (!function_exists('requireFraccionVIActiva')) {
    /**
     * Valida que el sujeto obligado esté habilitado en el padrón PLD con Fracción VI activa.
     * VAL-PLD-001 (Metales preciosos, piedras preciosas, joyas o relojes).
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return array Mismo formato que validatePatronPLD
     */
    function requireFraccionVIActiva($pdo) {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'VI');
    }
}

if (!function_exists('getIdVulnerableFraccionVI')) {
    /**
     * Obtiene id_vulnerable de la fracción VI en cat_vulnerables.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return int|null id_vulnerable o null
     */
    function getIdVulnerableFraccionVI($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'VI' AND (id_status = 1 OR id_status IS NULL) LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int) $row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionVI: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('validateProhibicionEfectivoMetalesJoyas')) {
    /**
     * VAL-PLD-027 — Prohibición de efectivo o metales preciosos en operaciones de alto monto.
     * Fracción VI: monto_operacion ≥ 3,210 UMA → no se permite efectivo ni metales preciosos.
     *
     * @param float $monto_uma Monto de la operación en UMAs
     * @param string|null $forma_pago Forma de pago (ej: 'efectivo', 'transferencia', 'metales_preciosos')
     * @return array ['permitido' => bool, 'codigo' => string|null, 'mensaje' => string]
     */
    function validateProhibicionEfectivoMetalesJoyas($monto_uma, $forma_pago = null) {
        $umbral = PLD_FRACCION_VI_UMA_RESTRICCION_EFECTIVO;
        if ($monto_uma < $umbral) {
            return [
                'permitido' => true,
                'codigo' => null,
                'mensaje' => 'Monto por debajo del umbral de restricción de efectivo'
            ];
        }
        $formasProhibidas = defined('PLD_FRACCION_VI_FORMAS_PAGO_PROHIBIDAS')
            ? PLD_FRACCION_VI_FORMAS_PAGO_PROHIBIDAS
            : ['efectivo', 'metales_preciosos', 'metales'];
        $formaNormalizada = $forma_pago ? strtolower(trim((string) $forma_pago)) : '';
        foreach ($formasProhibidas as $prohibida) {
            if ($formaNormalizada === $prohibida || strpos($formaNormalizada, $prohibida) !== false) {
                return [
                    'permitido' => false,
                    'codigo' => 'RESTRICCION_EFECTIVO',
                    'mensaje' => 'Operaciones ≥ ' . number_format($umbral) . ' UMA no pueden realizarse en efectivo ni metales preciosos (Fracción VI)'
                ];
            }
        }
        return [
            'permitido' => true,
            'codigo' => null,
            'mensaje' => 'Forma de pago permitida'
        ];
    }
}

if (!function_exists('registrarRiesgoSancionableVI')) {
    /**
     * VAL-PLD-028 — Marca riesgo sancionable cuando se detectan triggers.
     * Triggers: omisión de avisos, aviso extemporáneo, aviso sin formalidades,
     * incumplimiento art. 33, 33 Bis, 33 Ter, operaciones prohibidas (art. 32 LFPIORPI).
     * Resultado: RIESGO_SANCION_ADMINISTRATIVA; escalamiento interno; posible suspensión temporal.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @param string $trigger Descripción del trigger detectado
     * @param string|null $detalle Descripción opcional
     * @return bool True si se actualizó
     */
    function registrarRiesgoSancionableVI($pdo, $trigger, $detalle = null) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'riesgo_sancion_administrativa'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false || (int) ($row['c'] ?? 0) == 0) {
                return false;
            }
            $stmt = $pdo->prepare("UPDATE config_empresa SET riesgo_sancion_administrativa = 1 WHERE id_config = 1");
            $stmt->execute();
            if (function_exists('logChange')) {
                logChange($pdo, 0, 'RIESGO_SANCION_ADMINISTRATIVA', 'config_empresa', 1, null, [
                    'trigger' => $trigger,
                    'detalle' => $detalle,
                    'fraccion' => 'VI'
                ]);
            }
            return true;
        } catch (Exception $e) {
            error_log('registrarRiesgoSancionableVI: ' . $e->getMessage());
            return false;
        }
    }
}
