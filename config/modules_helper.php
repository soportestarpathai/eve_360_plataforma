<?php
/**
 * Helper para módulos del sistema (config_modulos / config_modulos_usuario)
 * Usado para filtrar menú, acciones rápidas y acceso a páginas por usuario
 */
if (!function_exists('getSysModulesForUser')) {
    /**
     * Obtiene los módulos activos para el usuario actual
     * @param PDO $pdo
     * @param int|null $userId Si null, usa $_SESSION['user_id']
     * @return array ['pld'=>1, 'risk'=>1, 'reports'=>1, 'investments'=>1]
     */
    function getSysModulesForUser(PDO $pdo, $userId = null) {
        $userId = $userId ?? ($_SESSION['user_id'] ?? 0);
        $sysModules = [];
        try {
            $modStmt = $pdo->prepare("SELECT m.nombre_clave, COALESCE(u.activo, m.activo) as activo 
                FROM config_modulos m 
                LEFT JOIN config_modulos_usuario u ON u.id_modulo = m.id_modulo AND u.id_usuario = ?");
            $modStmt->execute([$userId]);
            $rows = $modStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $sysModules[$r['nombre_clave']] = (int)$r['activo'];
            }
            if (empty($sysModules)) {
                $fallback = $pdo->query("SELECT nombre_clave, activo FROM config_modulos");
                if ($fallback) {
                    $fm = $fallback->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($fm as $k => $v) $sysModules[$k] = (int)$v;
                }
            }
        } catch (Exception $e) {
            $sysModules = [];
        }
        return $sysModules;
    }
}

if (!function_exists('isModuleActive')) {
    /**
     * Verifica si un módulo está activo para el usuario
     * @param array $sysModules resultado de getSysModulesForUser
     * @param string $moduleKey pld|risk|reports|investments
     * @return bool
     */
    function isModuleActive($sysModules, $moduleKey) {
        return !isset($sysModules[$moduleKey]) || (int)($sysModules[$moduleKey] ?? 1) === 1;
    }
}

// Mapeo: file_path o label -> nombre_clave del módulo
if (!defined('MODULE_PAGE_MAP')) {
    define('MODULE_PAGE_MAP', [
        'pld' => ['operaciones_pld.php', 'conservacion_pld.php', 'pld_avisos.php', 'operacion_din.php', 'operacion_tsc.php', 'operacion_spr.php', 'operacion_avi.php'],
        'risk' => ['reporte_riesgos.php', 'config_ebr.php'],
        'reports' => ['reporte_transacciones.php', 'bitacora_actividad.php'],
        'investments' => ['valuacion.php', 'rebalanceo.php', 'portafolios.php'],
    ]);
}

// Archivos de reportes controlables por usuario (reportes_usuario)
if (!defined('REPORTE_FILES')) {
    define('REPORTE_FILES', ['conservacion_pld.php', 'reporte_riesgos.php', 'reporte_transacciones.php', 'bitacora_actividad.php']);
}

if (!function_exists('getReportesActivosForUser')) {
    /**
     * Obtiene qué reportes están activos para el usuario (reportes_usuario)
     * @param PDO $pdo
     * @param int|null $userId
     * @return array ['conservacion_pld.php'=>1, ...] (1=activo, 0=oculto)
     */
    function getReportesActivosForUser(PDO $pdo, $userId = null) {
        $userId = $userId ?? ($_SESSION['user_id'] ?? 0);
        $result = [];
        foreach (REPORTE_FILES as $file) {
            $result[$file] = 1; // default activo
        }
        if ($userId <= 0) return $result;
        try {
            $stmt = $pdo->prepare("SELECT t.codigo, COALESCE(u.activo, 1) as activo 
                FROM cat_tipos_reporte t 
                LEFT JOIN reportes_usuario u ON u.id_tipo_reporte = t.id_tipo_reporte AND u.id_usuario = ?
                WHERE t.codigo IN ('conservacion_pld.php','reporte_riesgos.php','reporte_transacciones.php','bitacora_actividad.php')");
            $stmt->execute([$userId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result[$row['codigo']] = (int)$row['activo'];
            }
        } catch (Exception $e) {
            // tablas pueden no existir
        }
        return $result;
    }
}

if (!function_exists('isReporteActivo')) {
    function isReporteActivo($reportesActivos, $filePath) {
        return !isset($reportesActivos[$filePath]) || (int)($reportesActivos[$filePath] ?? 1) === 1;
    }
}

if (!function_exists('requireReporteActivo')) {
    /**
     * Redirige a index si el reporte no está activo para el usuario (reportes_usuario)
     */
    function requireReporteActivo(PDO $pdo, $filePath) {
        if (empty($_SESSION['user_id'])) return;
        if (!in_array($filePath, REPORTE_FILES, true)) return;
        $reportes = getReportesActivosForUser($pdo);
        if (!isReporteActivo($reportes, $filePath)) {
            header('Location: index.php?error=reporte_no_disponible');
            exit;
        }
    }
}

if (!function_exists('requireModuleActive')) {
    /**
     * Redirige a index si el módulo no está activo para el usuario
     * @param PDO $pdo
     * @param string $moduleKey pld|risk|reports|investments
     * @return void (redirects and exits if inactive)
     */
    function requireModuleActive(PDO $pdo, $moduleKey) {
        if (empty($_SESSION['user_id'])) return;
        $sysModules = getSysModulesForUser($pdo);
        if (!isModuleActive($sysModules, $moduleKey)) {
            header('Location: index.php?error=modulo_no_disponible');
            exit;
        }
    }
}
