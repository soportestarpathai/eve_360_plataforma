<?php
/**
 * PLD Fracción V — Desarrollo · Comercialización · Intermediación de Inmuebles
 * Constantes y helpers para el módulo inmobiliario (LFPIORPI, RCG, SPPLD).
 *
 * UMA según RCG Fracción V:
 * - Expediente: SIEMPRE obligatorio (sin importar monto)
 * - Aviso individual / acumulación: 8,025 UMA
 * - Prohibición efectivo/metales: operaciones ≥ 8,025 UMA
 */

if (!defined('PLD_FRACCION_V_UMA_AVISO')) {
    define('PLD_FRACCION_V_UMA_AVISO', 8025.0);
}
if (!defined('PLD_FRACCION_V_UMA_RESTRICCION_EFECTIVO')) {
    define('PLD_FRACCION_V_UMA_RESTRICCION_EFECTIVO', 8025.0);
}

/** Formas de pago que están prohibidas cuando monto ≥ umbral (VAL-PLD-027) */
if (!defined('PLD_FRACCION_V_FORMAS_PAGO_PROHIBIDAS')) {
    define('PLD_FRACCION_V_FORMAS_PAGO_PROHIBIDAS', ['efectivo', 'metales_preciosos', 'metales']);
}

if (!function_exists('requiereExpedienteInmobiliario')) {
    /**
     * En Fracción V la identificación es SIEMPRE obligatoria, sin importar monto.
     * VAL-PLD-005 Fracción V: Integración de Expediente del Cliente/Usuario.
     *
     * @return bool Siempre true para actos inmobiliarios
     */
    function requiereExpedienteInmobiliario() {
        return true;
    }
}

if (!function_exists('getUmbralAvisoInmobiliario')) {
    /**
     * Umbral en UMAs para aviso individual y acumulación (Fracción V).
     *
     * @return float
     */
    function getUmbralAvisoInmobiliario() {
        return PLD_FRACCION_V_UMA_AVISO;
    }
}

if (!function_exists('requireFraccionVActiva')) {
    /**
     * Valida que el sujeto obligado esté habilitado en el padrón PLD con Fracción V activa.
     * VAL-PLD-001 (especializado para Inmobiliario).
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return array Mismo formato que validatePatronPLD
     */
    function requireFraccionVActiva($pdo) {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'V');
    }
}

if (!function_exists('getIdVulnerableFraccionV')) {
    /**
     * Obtiene el primer id_vulnerable de la fracción V en cat_vulnerables.
     * Puede haber varios (Desarrollo, Comercialización, Intermediación).
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return int|null id_vulnerable o null
     */
    function getIdVulnerableFraccionV($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'V' AND (id_status = 1 OR id_status IS NULL) LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int) $row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionV: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('validateProhibicionEfectivoInmobiliario')) {
    /**
     * VAL-PLD-027 — Prohibición de operaciones en efectivo o metales preciosos.
     * Cuando monto_operacion ≥ 8,025 UMA, no se permite efectivo ni metales preciosos.
     *
     * @param float $monto_uma Monto de la operación en UMAs
     * @param string|null $forma_pago Forma de pago (ej: 'efectivo', 'transferencia', 'metales_preciosos')
     * @return array ['permitido' => bool, 'codigo' => string|null, 'mensaje' => string]
     */
    function validateProhibicionEfectivoInmobiliario($monto_uma, $forma_pago = null) {
        $umbral = PLD_FRACCION_V_UMA_RESTRICCION_EFECTIVO;
        if ($monto_uma < $umbral) {
            return [
                'permitido' => true,
                'codigo' => null,
                'mensaje' => 'Monto por debajo del umbral de restricción de efectivo'
            ];
        }
        $formasProhibidas = defined('PLD_FRACCION_V_FORMAS_PAGO_PROHIBIDAS')
            ? PLD_FRACCION_V_FORMAS_PAGO_PROHIBIDAS
            : ['efectivo', 'metales_preciosos', 'metales'];
        $formaNormalizada = $forma_pago ? strtolower(trim((string) $forma_pago)) : '';
        foreach ($formasProhibidas as $prohibida) {
            if ($formaNormalizada === $prohibida || strpos($formaNormalizada, $prohibida) !== false) {
                return [
                    'permitido' => false,
                    'codigo' => 'RESTRICCION_EFECTIVO',
                    'mensaje' => 'Transacciones inmobiliarias ≥ ' . number_format($umbral) . ' UMA no pueden realizarse en efectivo ni metales preciosos'
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
