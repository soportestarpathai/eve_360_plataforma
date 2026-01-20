<?php
// config/bitacora.php

if (!function_exists('logChange')) {
    /**
     * Logs a detailed change to the bitacora for undo capabilities.
     *
     * @param PDO $pdo The database connection.
     * @param int $id_usuario The ID of the user performing the action.
     * @param string $accion E.g., "CREAR", "ACTUALIZAR", "ELIMINAR".
     * @param string $tabla The database table affected.
     * @param int $id_afectado The primary key of the row that was changed.
     * @param mixed $valor_anterior The state of the data *before* the change (array or null).
     * @param mixed $valor_nuevo The state of the data *after* the change (array or null).
     */
    function logChange($pdo, $id_usuario, $accion, $tabla, $id_afectado, $valor_anterior, $valor_nuevo) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO bitacora (id_usuario, accion, tabla_afectada, id_afectado, valor_anterior, valor_nuevo) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            
            // Encode arrays/objects as JSON for storage in TEXT columns
            $json_anterior = is_null($valor_anterior) ? null : json_encode($valor_anterior, JSON_UNESCAPED_UNICODE);
            $json_nuevo = is_null($valor_nuevo) ? null : json_encode($valor_nuevo, JSON_UNESCAPED_UNICODE);

            $stmt->execute([
                $id_usuario,
                $accion,
                $tabla,
                $id_afectado,
                $json_anterior,
                $json_nuevo
            ]);
        } catch (Exception $e) {
            // Log this failure to a file, but don't stop the main transaction
            error_log("Failed to log to bitacora: " . $e->getMessage());
        }
    }
}
?>