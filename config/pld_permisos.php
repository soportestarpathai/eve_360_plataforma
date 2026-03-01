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

    /**
     * Retorna las fracciones PLD que un usuario tiene permitidas,
     * intersectadas con las fracciones activas de la empresa.
     * @return array Ej: ["V", "V Bis"]
     */
    function getUserFraccionesPLD($pdo, $userId) {
        try {
            $stmtConfig = $pdo->query("SELECT fracciones_activas FROM config_empresa WHERE id_config = 1");
            $row = $stmtConfig->fetch(PDO::FETCH_ASSOC);
            $empresaFracciones = [];
            if ($row && !empty($row['fracciones_activas'])) {
                $decoded = json_decode($row['fracciones_activas'], true);
                if (is_array($decoded)) $empresaFracciones = $decoded;
            }

            if (empty($empresaFracciones)) return [];

            ensureFraccionesPLDColumn($pdo);

            $stmtUser = $pdo->prepare("SELECT fracciones_pld FROM usuarios_permisos WHERE id_usuario = ?");
            $stmtUser->execute([$userId]);
            $userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!$userRow || empty($userRow['fracciones_pld'])) return [];

            $userFracciones = json_decode($userRow['fracciones_pld'], true);
            if (!is_array($userFracciones)) return [];

            return array_values(array_intersect($userFracciones, $empresaFracciones));
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
        return in_array('V', $fracciones) || in_array('V Bis', $fracciones);
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
            // 1. Verificar permiso de administración
            $stmt = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
            $stmt->execute([$id_usuario]);
            $perm = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($perm && !empty($perm['administracion']) && (int)$perm['administracion'] > 0) {
                return true;
            }

            // 2. Verificar permiso_pld_modificacion (si existe la columna)
            try {
                $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'permiso_pld_modificacion'");
                if ($chk && $chk->fetchColumn() > 0) {
                    $stmt = $pdo->prepare("SELECT permiso_pld_modificacion FROM usuarios_permisos WHERE id_usuario = ?");
                    $stmt->execute([$id_usuario]);
                    $p = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($p && !empty($p['permiso_pld_modificacion']) && (int)$p['permiso_pld_modificacion'] > 0) {
                        return true;
                    }
                }
            } catch (Exception $e) { /* ignorar */ }

            // 3. Verificar si es responsable PLD del cliente (si se proporciona id_cliente)
            if ($id_cliente) {
                $stmt = $pdo->prepare("
                    SELECT 1 FROM clientes_responsable_pld
                    WHERE id_cliente = ? AND id_usuario_responsable = ?
                    AND activo = 1
                    AND (fecha_baja IS NULL OR fecha_baja > CURDATE())
                ");
                $stmt->execute([$id_cliente, $id_usuario]);
                if ($stmt->fetch()) {
                    return true;
                }
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
