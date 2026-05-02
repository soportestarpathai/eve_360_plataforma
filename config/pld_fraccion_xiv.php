<?php
/**
 * PLD Fraccion XIV - Comercio exterior como agente/apoderado/agencia aduanal (ADU).
 *
 * Umbrales por inciso:
 * - a) Vehiculos terrestres, aereos y maritimos: identificacion siempre, aviso siempre.
 * - b) Maquinas para juegos de apuesta y sorteos: identificacion siempre, aviso siempre.
 * - c) Equipos/materiales para elaboracion de tarjetas de pago: identificacion siempre, aviso siempre.
 * - d) Joyas, relojes, metales y piedras preciosas: 485 UMA identificacion/aviso.
 * - e) Obras de arte: 4,815 UMA identificacion/aviso.
 */

if (!defined('PLD_FRACCION_XIV_UMA_SIEMPRE')) {
    define('PLD_FRACCION_XIV_UMA_SIEMPRE', 0.0);
}
if (!defined('PLD_FRACCION_XIV_UMA_JOYAS')) {
    define('PLD_FRACCION_XIV_UMA_JOYAS', 485.0);
}
if (!defined('PLD_FRACCION_XIV_UMA_OBRAS_ARTE')) {
    define('PLD_FRACCION_XIV_UMA_OBRAS_ARTE', 4815.0);
}

if (!function_exists('pldFraccionXIVUmbralPorActividad')) {
    function pldFraccionXIVUmbralPorActividad(string $actividad): float
    {
        $actividad = strtoupper(trim($actividad));
        if ($actividad === 'MJR') return PLD_FRACCION_XIV_UMA_JOYAS;
        if ($actividad === 'OBA') return PLD_FRACCION_XIV_UMA_OBRAS_ARTE;
        return PLD_FRACCION_XIV_UMA_SIEMPRE;
    }
}

if (!function_exists('pldFraccionXIVUmbralIdentificacion')) {
    function pldFraccionXIVUmbralIdentificacion(string $actividad = 'VEH'): float
    {
        return pldFraccionXIVUmbralPorActividad($actividad);
    }
}

if (!function_exists('pldFraccionXIVUmbralAviso')) {
    function pldFraccionXIVUmbralAviso(string $actividad = 'VEH'): float
    {
        return pldFraccionXIVUmbralPorActividad($actividad);
    }
}

if (!function_exists('getSubfraccionesXIVDefinition')) {
    function getSubfraccionesXIVDefinition(): array
    {
        global $ADU_CATALOGOS;
        require_once __DIR__ . '/adu_catalogos.php';
        return $ADU_CATALOGOS['actividad_vulnerable'] ?? ($GLOBALS['ADU_CATALOGOS']['actividad_vulnerable'] ?? []);
    }
}

if (!function_exists('getSubfraccionesXIVActivas')) {
    function getSubfraccionesXIVActivas($pdo, int $userId = 0): array
    {
        try {
            $subfracciones = null;
            $defs = array_keys(getSubfraccionesXIVDefinition());
            if ($userId > 0) {
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'subfracciones_xiv'");
                    if ($chk && $chk->fetchColumn() > 0) {
                        $stmt = $pdo->prepare("SELECT subfracciones_xiv FROM usuarios_permisos WHERE id_usuario = ?");
                        $stmt->execute([$userId]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row && !empty($row['subfracciones_xiv'])) {
                            $dec = json_decode($row['subfracciones_xiv'], true);
                            if (is_array($dec) && !empty($dec)) $subfracciones = $dec;
                        }
                    }
                } catch (Exception $e) { }
            }
            if ($subfracciones === null || !is_array($subfracciones)) {
                try {
                    $stmt = $pdo->query("SELECT subfracciones_xiv FROM config_empresa WHERE id_config = 1");
                    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                    if ($row && !empty($row['subfracciones_xiv'])) {
                        $dec = json_decode($row['subfracciones_xiv'], true);
                        if (is_array($dec) && !empty($dec)) $subfracciones = $dec;
                    }
                } catch (Exception $e) { }
            }
            if (!is_array($subfracciones)) return [];
            return array_values(array_intersect(array_map('strval', $subfracciones), $defs));
        } catch (Exception $e) {
            error_log('getSubfraccionesXIVActivas: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('requireFraccionXIVActiva')) {
    function requireFraccionXIVActiva($pdo): array
    {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'XIV');
    }
}

if (!function_exists('getIdVulnerableFraccionXIV')) {
    function getIdVulnerableFraccionXIV($pdo): ?int
    {
        try {
            try {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'XIV'
                      AND (id_status = 1 OR id_status IS NULL)
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            } catch (Exception $e) {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'XIV'
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return (int)$row['id_vulnerable'];

            try {
                $ins = $pdo->prepare("
                    INSERT INTO cat_vulnerables (nombre, fraccion, umbral_aviso_uma, umbral_acumulacion_uma)
                    VALUES ('Servicios de comercio exterior (ADU)', 'XIV', 0.00, 0.00)
                ");
                $ins->execute();
                return (int)$pdo->lastInsertId();
            } catch (Exception $e) {
                return null;
            }
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionXIV: ' . $e->getMessage());
            return null;
        }
    }
}
