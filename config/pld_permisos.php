<?php
/**
 * Permisos PLD - Modificación y eliminación de operaciones/avisos
 * Solo administradores o responsables PLD pueden modificar/eliminar.
 * Todas las acciones se registran en bitácora para auditoría y deshacer.
 */

if (!function_exists('ensureFraccionesPLDColumn')) {

    function ensureFraccionesPLDColumn($pdo) {
        try {
            $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'fracciones_pld'");
            if ($chk && $chk->fetchColumn() == 0) {
                $pdo->exec("ALTER TABLE usuarios_permisos ADD COLUMN fracciones_pld JSON DEFAULT NULL");
            }
        } catch (Exception $e) {
            error_log("ensureFraccionesPLDColumn error: " . $e->getMessage());
        }
    }

    function ensurePermisoPldModificacionColumn($pdo) {
        try {
            $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'permiso_pld_modificacion'");
            if ($chk && $chk->fetchColumn() == 0) {
                $pdo->exec("ALTER TABLE usuarios_permisos ADD COLUMN permiso_pld_modificacion TINYINT(1) DEFAULT 0 COMMENT '1=Puede modificar/eliminar avisos y operaciones PLD' AFTER administracion");
            }
        } catch (Exception $e) {
            error_log("ensurePermisoPldModificacionColumn error: " . $e->getMessage());
        }
    }

    /**
     * Retorna las fracciones PLD que un usuario tiene permitidas,
     * intersectadas con las fracciones activas de la empresa.
     * @return array Ej: ["V", "V Bis"]
     */
    function getUserFraccionesPLD($pdo, $userId) {
        try {
            $row = null;
            if ($userId > 0) {
                $stmtU = $pdo->prepare("SELECT fracciones_activas FROM config_empresa_usuario WHERE id_usuario = ?");
                $stmtU->execute([$userId]);
                $row = $stmtU->fetch(PDO::FETCH_ASSOC);
            }
            if (!$row || empty($row['fracciones_activas'])) {
                $stmtConfig = $pdo->query("SELECT fracciones_activas FROM config_empresa WHERE id_config = 1");
                $row = $stmtConfig->fetch(PDO::FETCH_ASSOC);
            }
            $empresaFracciones = [];
            if ($row && !empty($row['fracciones_activas'])) {
                $decoded = json_decode($row['fracciones_activas'], true);
                if (is_array($decoded)) $empresaFracciones = $decoded;
            }
            $empresaFracciones = array_map(function ($f) {
                $s = trim((string)$f);
                return $s;
            }, $empresaFracciones);
            $empresaFracciones = array_values(array_filter($empresaFracciones, fn($f) => $f !== ''));
            $empresaFracciones = array_values(array_unique($empresaFracciones));

            ensureFraccionesPLDColumn($pdo);

            $stmtUser = $pdo->prepare("SELECT fracciones_pld FROM usuarios_permisos WHERE id_usuario = ?");
            $stmtUser->execute([$userId]);
            $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!$userRow || empty($userRow['fracciones_pld'])) return [];

            $userFracciones = json_decode($userRow['fracciones_pld'], true);
            if (!is_array($userFracciones)) return [];
            $userFracciones = array_map(function ($f) {
                $s = trim((string)$f);
                return $s;
            }, $userFracciones);
            $userFracciones = array_values(array_filter($userFracciones, fn($f) => $f !== ''));
            $userFracciones = array_values(array_unique($userFracciones));

            // Regla principal: solo fracciones asignadas al usuario y habilitadas en configuración.
            if (!empty($empresaFracciones)) {
                return array_values(array_intersect($userFracciones, $empresaFracciones));
            }

            // Fallback: si no hay configuración activa, usar las asignadas al usuario.
            return $userFracciones;
        } catch (Exception $e) {
            error_log("getUserFraccionesPLD error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica si el usuario tiene acceso a una fracción específica.
     */
    function userHasFraccion($pdo, $userId, $fraccion) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array($fraccion, $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario DIN (requiere V o V Bis).
     */
    function userCanAccessDIN($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('V', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario INM (requiere Fracción V Bis).
     * Recepción de recursos para desarrollo inmobiliario para venta o renta.
     */
    function userCanAccessINM($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('V Bis', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario TSC (requiere Fracción II).
     */
    function userCanAccessTSC($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('II', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario JYS (requiere Fracción I).
     * Juegos con apuesta, concursos o sorteos.
     */
    function userCanAccessJYS($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('I', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario CHV (requiere Fracción III).
     * Cheques de viajero.
     */
    function userCanAccessCHV($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('III', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario SPR (requiere Fracción XI).
     * Servicios Profesionales — compraventa inmuebles, cesión derechos, administración recursos, etc.
     */
    function userCanAccessSPR($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('XI', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario DON (requiere Fracción XIII).
     * Donativos.
     */
    function userCanAccessDON($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('XIII', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario AVI (requiere Fracción XVI).
     * Activos virtuales.
     */
    function userCanAccessAVI($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('XVI', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario VEH (requiere Fracción VIII).
     * Comercialización o distribución de vehículos.
     */
    function userCanAccessVEH($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('VIII', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario MJR (requiere Fracción VI).
     * Metales preciosos, piedras preciosas, joyas y relojes.
     */
    function userCanAccessMJR($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('VI', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario OBA (requiere Fracción VII).
     * Subasta o comercialización de obras de arte.
     */
    function userCanAccessOBA($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('VII', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario TCV (requiere Fracción X).
     * Traslado o custodia de dinero o valores.
     */
    function userCanAccessTCV($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('X', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario MPC (requiere Fracción IV).
     * Mutuo, garantía, préstamos o créditos.
     */
    function userCanAccessMPC($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('IV', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario BLI (requiere Fracción IX).
     * Servicios de blindaje de vehículos terrestres e inmuebles.
     */
    function userCanAccessBLI($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('IX', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario ADU (requiere Fracción XIV).
     * Servicios de comercio exterior.
     */
    function userCanAccessADU($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('XIV', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario ARI (requiere Fracción XV).
     * Derechos personales de uso o goce de bienes inmuebles.
     */
    function userCanAccessARI($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('XV', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario FEP (requiere Fraccion XII).
     * Fe publica - Notarios y Corredores Publicos.
     */
    function userCanAccessFEP($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('XII', $fracciones);
    }

    /**
     * Verifica si el usuario tiene acceso al formulario FES (requiere Fraccion XII).
     * Fe publica - Servidores Publicos.
     */
    function userCanAccessFES($pdo, $userId) {
        $fracciones = getUserFraccionesPLD($pdo, $userId);
        return in_array('XII', $fracciones);
    }
}

if (!function_exists('canModifyPLD')) {
    
    /**
     * Verifica si el usuario puede modificar o eliminar operaciones/avisos PLD.
     * Requiere: administrador (permiso administracion) O responsable PLD del cliente.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_usuario ID del usuario que intenta la acción
     * @param int|null $id_cliente ID del cliente asociado al aviso/operación (opcional)
     * @return bool True si puede modificar, false si no
     */
    function canModifyPLD($pdo, $id_usuario, $id_cliente = null) {
        try {
            static $adminCache = [];
            static $permisoColExists = null;
            static $permisoPldCache = [];
            static $responsableCache = [];

            // 1. Verificar permiso de administración (cacheado por usuario)
            if (!array_key_exists($id_usuario, $adminCache)) {
                $stmt = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
                $stmt->execute([$id_usuario]);
                $perm = $stmt->fetch(PDO::FETCH_ASSOC);
                $adminCache[$id_usuario] = (bool)($perm && !empty($perm['administracion']) && (int)$perm['administracion'] > 0);
            }
            if ($adminCache[$id_usuario]) {
                return true;
            }

            // 2. Verificar permiso_pld_modificacion (cachea existencia de columna y valor por usuario)
            if ($permisoColExists === null) {
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'permiso_pld_modificacion'");
                    $permisoColExists = ($chk && $chk->fetchColumn() > 0);
                } catch (Exception $e) {
                    $permisoColExists = false;
                }
            }
            if ($permisoColExists) {
                if (!array_key_exists($id_usuario, $permisoPldCache)) {
                    $stmt = $pdo->prepare("SELECT permiso_pld_modificacion FROM usuarios_permisos WHERE id_usuario = ?");
                    $stmt->execute([$id_usuario]);
                    $p = $stmt->fetch(PDO::FETCH_ASSOC);
                    $permisoPldCache[$id_usuario] = (bool)($p && !empty($p['permiso_pld_modificacion']) && (int)$p['permiso_pld_modificacion'] > 0);
                }
                if ($permisoPldCache[$id_usuario]) {
                    return true;
                }
            }

            // 3. Verificar si es responsable PLD del cliente (cacheado por usuario+cliente)
            if ($id_cliente) {
                $cacheKey = $id_usuario . ':' . $id_cliente;
                if (!array_key_exists($cacheKey, $responsableCache)) {
                    $stmt = $pdo->prepare("
                        SELECT 1 FROM clientes_responsable_pld
                        WHERE id_cliente = ? AND id_usuario_responsable = ?
                        AND activo = 1
                        AND (fecha_baja IS NULL OR fecha_baja > CURDATE())
                    ");
                    $stmt->execute([$id_cliente, $id_usuario]);
                    $responsableCache[$cacheKey] = (bool)$stmt->fetch();
                }
                return $responsableCache[$cacheKey];
            }

            return false;
        } catch (Exception $e) {
            error_log("canModifyPLD error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna mensaje cuando el usuario no tiene permiso
     */
    function mensajeSinPermisoPLD() {
        return 'No tiene permiso para esta acción. Solicite autorización a un administrador o al responsable PLD.';
    }
}
