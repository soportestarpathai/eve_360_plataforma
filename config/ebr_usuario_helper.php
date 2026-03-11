<?php
/**
 * Helper: EBR config por usuario. Devuelve rangos/valores del usuario, con fallback a config global.
 * Si las tablas *_usuario no existen, usa solo global.
 */

if (!function_exists('getRangosRiesgoUsuario')) {
    function getRangosRiesgoUsuario($pdo, $id_usuario) {
        $id_usuario = (int)$id_usuario;
        if ($id_usuario <= 0) {
            return _getRangosGlobales($pdo);
        }
        if (_tablaExiste($pdo, 'config_riesgo_rangos_usuario')) {
            $stmt = $pdo->prepare("SELECT nivel, min_valor, max_valor, color_hex FROM config_riesgo_rangos_usuario WHERE id_usuario = ? ORDER BY min_valor ASC");
            $stmt->execute([$id_usuario]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                return $rows;
            }
        }
        return _getRangosGlobales($pdo);
    }

    function _getRangosGlobales($pdo) {
        $stmt = $pdo->query("SELECT nivel, min_valor, max_valor, color_hex FROM config_riesgo_rangos ORDER BY min_valor ASC");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    function getRiskValorUsuario($pdo, $id_factor, $id_valor_catalogo, $id_usuario) {
        $id_valor_catalogo = (int)$id_valor_catalogo;
        if ($id_valor_catalogo <= 0 && $id_valor_catalogo !== 0) return 0;
        $id_usuario = (int)$id_usuario;
        if ($id_usuario > 0 && _tablaExiste($pdo, 'config_riesgo_valores_usuario')) {
            $stmt = $pdo->prepare("SELECT nivel_riesgo FROM config_riesgo_valores_usuario WHERE id_usuario = ? AND id_factor = ? AND id_valor_catalogo = ?");
            $stmt->execute([$id_usuario, $id_factor, $id_valor_catalogo]);
            $res = $stmt->fetch(PDO::FETCH_COLUMN);
            if ($res !== false && $res !== null) {
                return floatval($res);
            }
        }
        $stmt = $pdo->prepare("SELECT nivel_riesgo FROM config_riesgo_valores WHERE id_factor = ? AND id_valor_catalogo = ?");
        $stmt->execute([$id_factor, $id_valor_catalogo]);
        $res = $stmt->fetch(PDO::FETCH_COLUMN);
        return $res ? floatval($res) : 0;
    }

    function ebrTablaUsuarioExiste($pdo, $nombre = 'config_riesgo_valores_usuario') {
        return _tablaExiste($pdo, $nombre);
    }

    if (!function_exists('_tablaExiste')) {
        function _tablaExiste($pdo, $nombre) {
            static $cache = [];
            if (!isset($cache[$nombre])) {
                try {
                    $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($nombre) . " LIMIT 1");
                    $cache[$nombre] = $stmt && $stmt->fetch();
                } catch (Throwable $e) {
                    $cache[$nombre] = false;
                }
            }
            return $cache[$nombre];
        }
    }
}
