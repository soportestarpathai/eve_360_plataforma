<?php
/**
 * PLD Fracción XI — Servicios Profesionales (SPR)
 * Actos u operaciones de compraventa, cesión de derechos, administración de recursos,
 * constitución de sociedades, fusión, escisión, fideicomiso, etc.
 * clave_actividad: SPR
 */

if (!function_exists('requireFraccionXIActiva')) {
    /**
     * Valida que el sujeto obligado esté habilitado en el padrón PLD con Fracción XI activa.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return array Mismo formato que validatePatronPLD
     */
    function requireFraccionXIActiva($pdo) {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'XI');
    }
}

if (!function_exists('getIdVulnerableFraccionXI')) {
    /**
     * Obtiene id_vulnerable de la fracción XI (Servicios Profesionales) en cat_vulnerables.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @return int|null id_vulnerable o null
     */
    function getIdVulnerableFraccionXI($pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT id_vulnerable FROM cat_vulnerables 
                WHERE fraccion = 'XI' AND (nombre LIKE '%Servicios Profesionales%' OR nombre LIKE '%SPR%')
                ORDER BY id_vulnerable 
                LIMIT 1
            ");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $stmt = $pdo->prepare("SELECT id_vulnerable FROM cat_vulnerables WHERE fraccion = 'XI' LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return $row ? (int) $row['id_vulnerable'] : null;
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionXI: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getSubfraccionesXIActivas')) {
    /**
     * Obtiene las subfracciones XI (SPR) activas según config.
     * Si vacío = todas las del catálogo.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @param int $userId 0 = config empresa, >0 = config usuario si existe
     * @return array Claves de tipo_actividad activas
     */
    function getSubfraccionesXIActivas($pdo, $userId = 0) {
        try {
            $subfracciones = null;
            // 1. Por usuario (usuarios_permisos): subfracciones asignadas al editar usuario
            if ($userId > 0) {
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'subfracciones_xi'");
                    if ($chk && $chk->fetchColumn() > 0) {
                        $stmt = $pdo->prepare("SELECT subfracciones_xi FROM usuarios_permisos WHERE id_usuario = ?");
                        $stmt->execute([$userId]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row && !empty($row['subfracciones_xi'])) {
                            $dec = json_decode($row['subfracciones_xi'], true);
                            if (is_array($dec) && !empty($dec)) $subfracciones = $dec;
                        }
                    }
                } catch (Exception $e) { }
            }
            // 2. Config empresa (Padrón PLD > Subfracciones XI)
            if ($subfracciones === null || !is_array($subfracciones)) {
                $stmt = $pdo->query("SELECT subfracciones_xi FROM config_empresa WHERE id_config = 1");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['subfracciones_xi'])) {
                    $subfracciones = json_decode($row['subfracciones_xi'], true);
                }
            }
            return is_array($subfracciones) ? $subfracciones : [];
        } catch (Exception $e) {
            return [];
        }
    }
}
