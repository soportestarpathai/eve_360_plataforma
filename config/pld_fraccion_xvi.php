<?php
/**
 * PLD Fracción XVI — Activos Virtuales (AVI)
 *
 * Reglas de umbral (según instructivo recibido):
 * - Identificación: SIEMPRE obligatoria.
 * - Aviso:
 *   a) Monto de operación >= 210 UMA
 *   b) Contraprestación por servicio >= 4 UMA
 */

if (!defined('PLD_FRACCION_XVI_UMA_IDENTIFICACION_SIEMPRE')) {
    define('PLD_FRACCION_XVI_UMA_IDENTIFICACION_SIEMPRE', true);
}

if (!defined('PLD_FRACCION_XVI_UMA_AVISO_OPERACION')) {
    define('PLD_FRACCION_XVI_UMA_AVISO_OPERACION', 210.0);
}

if (!defined('PLD_FRACCION_XVI_UMA_AVISO_CONTRAPRESTACION')) {
    define('PLD_FRACCION_XVI_UMA_AVISO_CONTRAPRESTACION', 4.0);
}

if (!function_exists('pldFraccionXVIIdentificacionSiempre')) {
    function pldFraccionXVIIdentificacionSiempre(): bool {
        return PLD_FRACCION_XVI_UMA_IDENTIFICACION_SIEMPRE === true;
    }
}

if (!function_exists('pldFraccionXVIUmbralAvisoOperacion')) {
    function pldFraccionXVIUmbralAvisoOperacion(): float {
        return PLD_FRACCION_XVI_UMA_AVISO_OPERACION;
    }
}

if (!function_exists('pldFraccionXVIUmbralAvisoContraprestacion')) {
    function pldFraccionXVIUmbralAvisoContraprestacion(): float {
        return PLD_FRACCION_XVI_UMA_AVISO_CONTRAPRESTACION;
    }
}

if (!function_exists('pldFraccionXVIEvaluaUmbralAviso')) {
    /**
     * Evalúa los dos disparadores de aviso de Fracción XVI.
     *
     * @param float $valorUma Valor UMA diario vigente.
     * @param float $montoOperacion Monto de la operación en MXN.
     * @param float $montoContraprestacion Monto de contraprestación en MXN.
     * @return array
     */
    function pldFraccionXVIEvaluaUmbralAviso(float $valorUma, float $montoOperacion, float $montoContraprestacion = 0.0): array {
        $valorUma = $valorUma > 0 ? $valorUma : 100.0;
        $montoOperacion = max(0.0, $montoOperacion);
        $montoContraprestacion = max(0.0, $montoContraprestacion);

        $umbralOperacionUma = pldFraccionXVIUmbralAvisoOperacion();
        $umbralContrapUma = pldFraccionXVIUmbralAvisoContraprestacion();

        $montoOperacionUma = $montoOperacion / $valorUma;
        $montoContrapUma = $montoContraprestacion / $valorUma;

        $requierePorMonto = $montoOperacionUma >= $umbralOperacionUma;
        $requierePorContraprestacion = $montoContrapUma >= $umbralContrapUma;
        $requiereAviso = $requierePorMonto || $requierePorContraprestacion;

        return [
            'requiere_aviso' => $requiereAviso,
            'requiere_aviso_por_monto' => $requierePorMonto,
            'requiere_aviso_por_contraprestacion' => $requierePorContraprestacion,
            'valor_uma' => $valorUma,
            'monto_operacion_mxn' => $montoOperacion,
            'monto_operacion_uma' => $montoOperacionUma,
            'monto_contraprestacion_mxn' => $montoContraprestacion,
            'monto_contraprestacion_uma' => $montoContrapUma,
            'umbral_operacion_uma' => $umbralOperacionUma,
            'umbral_operacion_mxn' => $umbralOperacionUma * $valorUma,
            'umbral_contraprestacion_uma' => $umbralContrapUma,
            'umbral_contraprestacion_mxn' => $umbralContrapUma * $valorUma
        ];
    }
}

if (!function_exists('requireFraccionXVIActiva')) {
    /**
     * Valida que el sujeto obligado esté habilitado en padrón PLD con Fracción XVI activa.
     */
    function requireFraccionXVIActiva($pdo) {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'XVI');
    }
}

if (!function_exists('getIdVulnerableFraccionXVI')) {
    /**
     * Obtiene id_vulnerable para fracción XVI desde cat_vulnerables.
     */
    function getIdVulnerableFraccionXVI($pdo): ?int {
        try {
            // Compatibilidad de esquema: algunas BD no tienen cat_vulnerables.id_status
            try {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'XVI'
                      AND (id_status = 1 OR id_status IS NULL)
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            } catch (Exception $e) {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'XVI'
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionXVI: ' . $e->getMessage());
            return null;
        }
    }
}
