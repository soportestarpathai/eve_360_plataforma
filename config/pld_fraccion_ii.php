<?php
/**
 * PLD Fracción II — Tarjetas de Servicio y de Crédito (TSC)
 * Constantes y helpers según LFPIORPI, RCG, SPPLD.
 *
 * Subfracciones operativas:
 * 1) Tarjetas de Servicio y Crédito (TSC)         805 / 1285 UMA
 * 2) Tarjetas de Prepago y Cupones (TPP)          645 /  645 UMA
 * 3) Tarjetas de Devolución y Recompensas (TDR)   645 /  645 UMA
 */

if (!defined('PLD_FRACCION_II_UMA_IDENTIFICACION')) {
    define('PLD_FRACCION_II_UMA_IDENTIFICACION', 805.0);
}

if (!defined('PLD_FRACCION_II_UMA_AVISO')) {
    define('PLD_FRACCION_II_UMA_AVISO', 1285.0);
}

if (!defined('PLD_FRACCION_II_SUBFRACCIONES')) {
    define('PLD_FRACCION_II_SUBFRACCIONES', [
        'servicio_credito' => [
            'clave' => 'TSC',
            'nombre' => 'Tarjetas de Servicio y Crédito',
            'detalle_tipo' => 'tsc',
            'umbral_identificacion_uma' => 805.0,
            'umbral_aviso_uma' => 1285.0,
        ],
        'prepago_cupones' => [
            'clave' => 'TPP',
            'nombre' => 'Tarjetas de Prepago y Cupones',
            'detalle_tipo' => 'tpp',
            'umbral_identificacion_uma' => 645.0,
            'umbral_aviso_uma' => 645.0,
        ],
        'devolucion_recompensas' => [
            'clave' => 'TDR',
            'nombre' => 'Tarjetas de Devolución y Recompensas',
            'detalle_tipo' => 'tdr',
            'umbral_identificacion_uma' => 645.0,
            'umbral_aviso_uma' => 645.0,
        ],
    ]);
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

if (!function_exists('getSubfraccionesIIDefinition')) {
    /**
     * Definición completa de subfracciones II.
     */
    function getSubfraccionesIIDefinition(): array {
        $defs = PLD_FRACCION_II_SUBFRACCIONES;
        return is_array($defs) ? $defs : [];
    }
}

if (!function_exists('getSubfraccionIIData')) {
    /**
     * Datos de una subfracción II por clave interna.
     */
    function getSubfraccionIIData(?string $subfraccion): ?array {
        $defs = getSubfraccionesIIDefinition();
        $key = trim((string)$subfraccion);
        if ($key === '' || !isset($defs[$key]) || !is_array($defs[$key])) {
            return null;
        }
        return $defs[$key];
    }
}

if (!function_exists('getSubfraccionIIClaveActividad')) {
    /**
     * Clave UIF actividad vulnerable para subfracción II (TSC/TPP/TDR).
     */
    function getSubfraccionIIClaveActividad(?string $subfraccion): string {
        $data = getSubfraccionIIData($subfraccion);
        if (!is_array($data)) return 'TSC';
        return trim((string)($data['clave'] ?? 'TSC')) ?: 'TSC';
    }
}

if (!function_exists('getSubfraccionIIDetalleTipo')) {
    /**
     * Tipo de detalle XML/formulario esperado por subfracción II.
     * Valores conocidos: tsc, tpp, tdr.
     */
    function getSubfraccionIIDetalleTipo(?string $subfraccion): string {
        $data = getSubfraccionIIData($subfraccion);
        if (!is_array($data)) return 'tsc';
        $tipo = trim((string)($data['detalle_tipo'] ?? 'tsc'));
        return $tipo !== '' ? $tipo : 'tsc';
    }
}

if (!function_exists('getUmbralIdentificacionIIPorSubfraccion')) {
    /**
     * Umbral identificación UMA para subfracción II.
     */
    function getUmbralIdentificacionIIPorSubfraccion(?string $subfraccion): float {
        $data = getSubfraccionIIData($subfraccion);
        if (!is_array($data)) return getUmbralIdentificacionII();
        $v = (float)($data['umbral_identificacion_uma'] ?? getUmbralIdentificacionII());
        return $v > 0 ? $v : getUmbralIdentificacionII();
    }
}

if (!function_exists('getUmbralAvisoIIPorSubfraccion')) {
    /**
     * Umbral aviso UMA para subfracción II.
     */
    function getUmbralAvisoIIPorSubfraccion(?string $subfraccion): float {
        $data = getSubfraccionIIData($subfraccion);
        if (!is_array($data)) return getUmbralAvisoII();
        $v = (float)($data['umbral_aviso_uma'] ?? getUmbralAvisoII());
        return $v > 0 ? $v : getUmbralAvisoII();
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

if (!function_exists('getSubfraccionesIIActivas')) {
    /**
     * Obtiene subfracciones II activas por usuario/config.
     * Si retorna [] => sin restricción explícita (permitir todas).
     */
    function getSubfraccionesIIActivas($pdo, int $userId = 0): array {
        try {
            $subfracciones = null;
            $defs = array_keys(getSubfraccionesIIDefinition());

            if ($userId > 0) {
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'subfracciones_ii'");
                    if ($chk && $chk->fetchColumn() > 0) {
                        $stmt = $pdo->prepare("SELECT subfracciones_ii FROM usuarios_permisos WHERE id_usuario = ?");
                        $stmt->execute([$userId]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row && !empty($row['subfracciones_ii'])) {
                            $dec = json_decode($row['subfracciones_ii'], true);
                            if (is_array($dec) && !empty($dec)) $subfracciones = $dec;
                        }
                    }
                } catch (Exception $e) { /* noop */ }
            }

            if ($subfracciones === null || !is_array($subfracciones)) {
                try {
                    $stmt = $pdo->query("SELECT subfracciones_ii FROM config_empresa WHERE id_config = 1");
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row && !empty($row['subfracciones_ii'])) {
                        $dec = json_decode($row['subfracciones_ii'], true);
                        if (is_array($dec) && !empty($dec)) $subfracciones = $dec;
                    }
                } catch (Exception $e) { /* noop */ }
            }

            if (!is_array($subfracciones)) return [];
            $out = [];
            foreach ($subfracciones as $s) {
                $k = trim((string)$s);
                if ($k !== '' && in_array($k, $defs, true)) $out[$k] = true;
            }
            return array_keys($out);
        } catch (Exception $e) {
            return [];
        }
    }
}
