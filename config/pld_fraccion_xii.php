<?php
/**
 * PLD Fraccion XII - Fe publica (FEP).
 */

if (!defined('PLD_FRACCION_XII_UMA_AVISO_INMUEBLES')) {
    define('PLD_FRACCION_XII_UMA_AVISO_INMUEBLES', 8000.0);
}
if (!defined('PLD_FRACCION_XII_UMA_AVISO_FIDEICOMISO')) {
    define('PLD_FRACCION_XII_UMA_AVISO_FIDEICOMISO', 4000.0);
}
if (!defined('PLD_FRACCION_XII_UMA_AVISO_AVALUO')) {
    define('PLD_FRACCION_XII_UMA_AVISO_AVALUO', 8025.0);
}

if (!function_exists('pldFraccionXIIUmbralAviso')) {
    function pldFraccionXIIUmbralAviso(string $subactividad): ?float
    {
        switch ($subactividad) {
            case 'constitucion_modificacion_fideicomiso':
                return PLD_FRACCION_XII_UMA_AVISO_FIDEICOMISO;
            case 'avaluo':
                return PLD_FRACCION_XII_UMA_AVISO_AVALUO;
            default:
                return null;
        }
    }
}

if (!function_exists('pldFraccionXIIAvisoSiempre')) {
    function pldFraccionXIIAvisoSiempre(string $subactividad): bool
    {
        return !in_array($subactividad, ['constitucion_modificacion_fideicomiso', 'avaluo'], true);
    }
}

if (!function_exists('getSubfraccionesXIIDefinition')) {
    function getSubfraccionesXIIDefinition(): array
    {
        global $FEP_CATALOGOS;
        require_once __DIR__ . '/fep_catalogos.php';
        return $FEP_CATALOGOS['subactividad'] ?? ($GLOBALS['FEP_CATALOGOS']['subactividad'] ?? []);
    }
}

if (!function_exists('getSubfraccionesXIIActivas')) {
    function getSubfraccionesXIIActivas($pdo, int $userId = 0): array
    {
        try {
            $subfracciones = null;
            $defs = array_keys(getSubfraccionesXIIDefinition());
            if ($userId > 0) {
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'subfracciones_xii'");
                    if ($chk && $chk->fetchColumn() > 0) {
                        $stmt = $pdo->prepare("SELECT subfracciones_xii FROM usuarios_permisos WHERE id_usuario = ?");
                        $stmt->execute([$userId]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row && !empty($row['subfracciones_xii'])) {
                            $dec = json_decode($row['subfracciones_xii'], true);
                            if (is_array($dec) && !empty($dec)) $subfracciones = $dec;
                        }
                    }
                } catch (Exception $e) { }
            }
            if ($subfracciones === null || !is_array($subfracciones)) {
                try {
                    $stmt = $pdo->query("SELECT subfracciones_xii FROM config_empresa WHERE id_config = 1");
                    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                    if ($row && !empty($row['subfracciones_xii'])) {
                        $dec = json_decode($row['subfracciones_xii'], true);
                        if (is_array($dec) && !empty($dec)) $subfracciones = $dec;
                    }
                } catch (Exception $e) { }
            }
            if (!is_array($subfracciones)) return [];
            return array_values(array_intersect(array_map('strval', $subfracciones), $defs));
        } catch (Exception $e) {
            error_log('getSubfraccionesXIIActivas: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getSubfraccionesXIIFESDefinition')) {
    function getSubfraccionesXIIFESDefinition(): array
    {
        global $FES_CATALOGOS;
        require_once __DIR__ . '/fes_catalogos.php';
        return $FES_CATALOGOS['subactividad'] ?? ($GLOBALS['FES_CATALOGOS']['subactividad'] ?? []);
    }
}

if (!function_exists('getSubfraccionesXIIFESActivas')) {
    function getSubfraccionesXIIFESActivas($pdo, int $userId = 0): array
    {
        try {
            $subfracciones = null;
            $defs = array_keys(getSubfraccionesXIIFESDefinition());
            $col = 'subfracciones_xii_fes';
            if ($userId > 0) {
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = '{$col}'");
                    if ($chk && $chk->fetchColumn() > 0) {
                        $stmt = $pdo->prepare("SELECT {$col} FROM usuarios_permisos WHERE id_usuario = ?");
                        $stmt->execute([$userId]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row && !empty($row[$col])) {
                            $dec = json_decode($row[$col], true);
                            if (is_array($dec) && !empty($dec)) $subfracciones = $dec;
                        }
                    }
                } catch (Exception $e) { }
            }
            if ($subfracciones === null || !is_array($subfracciones)) {
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = '{$col}'");
                    if ($chk && $chk->fetchColumn() > 0) {
                        $stmt = $pdo->query("SELECT {$col} FROM config_empresa WHERE id_config = 1");
                        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                        if ($row && !empty($row[$col])) {
                            $dec = json_decode($row[$col], true);
                            if (is_array($dec) && !empty($dec)) $subfracciones = $dec;
                        }
                    }
                } catch (Exception $e) { }
            }
            if (!is_array($subfracciones)) return [];
            return array_values(array_intersect(array_map('strval', $subfracciones), $defs));
        } catch (Exception $e) {
            error_log('getSubfraccionesXIIFESActivas: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('requireFraccionXIIActiva')) {
    function requireFraccionXIIActiva($pdo): array
    {
        if (!function_exists('validatePatronPLD')) {
            require_once __DIR__ . '/pld_validation.php';
        }
        return validatePatronPLD($pdo, 'XII');
    }
}

if (!function_exists('getIdVulnerableFraccionXIIFEP')) {
    function getIdVulnerableFraccionXIIFEP($pdo): ?int
    {
        try {
            try {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'XII'
                      AND (nombre LIKE '%Notario%' OR nombre LIKE '%Corredor%' OR nombre LIKE '%FEP%' OR nombre LIKE '%Fe p%blica%')
                      AND (id_status = 1 OR id_status IS NULL)
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            } catch (Exception $e) {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'XII'
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
                    VALUES ('Fe publica - Notarios y Corredores Publicos (FEP)', 'XII', 0.00, 0.00)
                ");
                $ins->execute();
                return (int)$pdo->lastInsertId();
            } catch (Exception $e) {
                return null;
            }
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionXIIFEP: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getIdVulnerableFraccionXIIFES')) {
    function getIdVulnerableFraccionXIIFES($pdo): ?int
    {
        try {
            try {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'XII'
                      AND (nombre LIKE '%Servidor%' OR nombre LIKE '%FES%')
                      AND (id_status = 1 OR id_status IS NULL)
                    ORDER BY id_vulnerable
                    LIMIT 1
                ");
                $stmt->execute();
            } catch (Exception $e) {
                $stmt = $pdo->prepare("
                    SELECT id_vulnerable
                    FROM cat_vulnerables
                    WHERE fraccion = 'XII'
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
                    VALUES ('Fe publica - Servidores Publicos (FES)', 'XII', 0.00, 0.00)
                ");
                $ins->execute();
                return (int)$pdo->lastInsertId();
            } catch (Exception $e) {
                return null;
            }
        } catch (Exception $e) {
            error_log('getIdVulnerableFraccionXIIFES: ' . $e->getMessage());
            return null;
        }
    }
}
