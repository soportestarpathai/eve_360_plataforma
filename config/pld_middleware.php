<?php
/**
 * PLD Middleware
 * Bloquea operaciones PLD si el sujeto obligado no está habilitado
 * VAL-PLD-001: Validación de padrón PLD
 * VAL-PLD-003: Validación de responsable PLD
 */

require_once __DIR__ . '/pld_validation.php';
require_once __DIR__ . '/pld_responsable_validation.php';

if (!function_exists('requirePLDHabilitado')) {
    
    /**
     * Requiere que el sujeto obligado esté habilitado para operar PLD
     * Si no está habilitado, bloquea la operación y retorna error
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param bool $returnJson Si es true, retorna JSON. Si es false, lanza excepción
     * @return array|null Retorna array con error si returnJson es true, null si lanza excepción
     * @throws Exception Si returnJson es false y no está habilitado
     */
    function requirePLDHabilitado($pdo, $returnJson = true) {
        $habilitado = checkHabilitadoPLD($pdo);
        
        if (!$habilitado) {
            $result = validatePatronPLD($pdo);
            
            if ($returnJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'code' => 'NO_HABILITADO_PLD',
                    'message' => 'El sujeto obligado no está habilitado para operar PLD',
                    'razon' => $result['razon'] ?? 'Validación de padrón PLD fallida',
                    'detalles' => $result['detalles'] ?? []
                ]);
                exit;
            } else {
                throw new Exception('NO_HABILITADO_PLD: ' . ($result['razon'] ?? 'Validación de padrón PLD fallida'));
            }
        }
        
        return null;
    }
    
    /**
     * Verifica si debe bloquear operaciones por baja en padrón
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @return bool True si debe bloquear, false si no
     */
    function shouldBlockOperations($pdo) {
        try {
            $stmt = $pdo->query("SELECT estatus_patron_pld, no_habilitado_pld FROM config_empresa WHERE id_config = 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config) {
                return true; // Bloquear si no hay configuración
            }
            
            // Bloquear si el flag está activo
            if (!empty($config['no_habilitado_pld']) && $config['no_habilitado_pld'] == 1) {
                return true;
            }
            
            // Bloquear si el estatus es "baja"
            $estatus = strtolower($config['estatus_patron_pld'] ?? '');
            if ($estatus === 'baja' || $estatus === 'BAJA') {
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error checking block status: " . $e->getMessage());
            return true; // Bloquear por seguridad si hay error
        }
    }
    
    /**
     * Requiere que un cliente tenga responsable PLD designado (si aplica)
     * VAL-PLD-003: Designación de Responsable PLD
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente a validar
     * @param bool $returnJson Si es true, retorna JSON. Si es false, lanza excepción
     * @return array|null Retorna array con error si returnJson es true, null si no hay restricción
     * @throws Exception Si returnJson es false y hay restricción
     */
    function requireResponsablePLD($pdo, $id_cliente, $returnJson = true) {
        $validation = validateResponsablePLD($pdo, $id_cliente);
        
        if ($validation['restriccion'] === true) {
            if ($returnJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'code' => 'RESTRICCION_USUARIO',
                    'message' => 'El cliente requiere responsable PLD designado',
                    'razon' => $validation['razon'] ?? 'No hay responsable PLD designado',
                    'detalles' => $validation['detalles'] ?? []
                ]);
                exit;
            } else {
                throw new Exception('RESTRICCION_USUARIO: ' . ($validation['razon'] ?? 'No hay responsable PLD designado'));
            }
        }
        
        return null;
    }
}
