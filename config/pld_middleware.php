<?php
/**
 * PLD Middleware
 * Bloquea transacciones PLD según todas las validaciones PLD
 * 
 * VAL-PLD-001: Validación de padrón PLD
 * VAL-PLD-002: Revalidación Periódica
 * VAL-PLD-003: Validación de responsable PLD
 * VAL-PLD-004: Representación Legal del Usuario
 * VAL-PLD-005: Integración de Expediente
 * VAL-PLD-006: Actualización Anual del Expediente
 * VAL-PLD-007: Identificación de Beneficiario Controlador
 * VAL-PLD-008 a VAL-PLD-012: Avisos y Umbrales
 * VAL-PLD-013: Conservación de Información
 * VAL-PLD-014: Atención a Visitas de Verificación
 * VAL-PLD-015: Registro del Beneficiario Controlador
 */

require_once __DIR__ . '/pld_validation.php';
require_once __DIR__ . '/pld_revalidation.php';
require_once __DIR__ . '/pld_responsable_validation.php';
require_once __DIR__ . '/pld_representacion_legal.php';
require_once __DIR__ . '/pld_expediente.php';
require_once __DIR__ . '/pld_beneficiario_controlador.php';
require_once __DIR__ . '/pld_conservacion.php';

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
    
    /**
     * Valida todas las reglas PLD antes de permitir una operación
     * Función centralizada para validar múltiples reglas
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int|null $id_cliente ID del cliente (si aplica)
     * @param int|null $id_usuario ID del usuario (si aplica)
     * @param array $validaciones Array de códigos de validación a ejecutar
     * @param bool $returnJson Si es true, retorna JSON. Si es false, lanza excepción
     * @return array|null Retorna array con error si returnJson es true
     * @throws Exception Si returnJson es false y hay problemas
     */
    function validatePLDOperation($pdo, $id_cliente = null, $id_usuario = null, $validaciones = [], $returnJson = true) {
        $errores = [];
        
        // VAL-PLD-001: Padrón PLD (siempre validar)
        if (in_array('VAL-PLD-001', $validaciones) || empty($validaciones)) {
            try {
                requirePLDHabilitado($pdo, false);
            } catch (Exception $e) {
                $errores[] = [
                    'codigo' => 'NO_HABILITADO_PLD',
                    'validacion' => 'VAL-PLD-001',
                    'mensaje' => $e->getMessage()
                ];
            }
        }
        
        // VAL-PLD-003: Responsable PLD (si hay cliente)
        if ($id_cliente && (in_array('VAL-PLD-003', $validaciones) || empty($validaciones))) {
            try {
                requireResponsablePLD($pdo, $id_cliente, false);
            } catch (Exception $e) {
                $errores[] = [
                    'codigo' => 'RESTRICCION_USUARIO',
                    'validacion' => 'VAL-PLD-003',
                    'mensaje' => $e->getMessage()
                ];
            }
        }
        
        // VAL-PLD-004: Representación Legal (si hay usuario)
        if ($id_usuario && (in_array('VAL-PLD-004', $validaciones) || empty($validaciones))) {
            $result = validateRepresentacionLegal($pdo, $id_usuario, $id_cliente);
            if (!$result['valido'] || $result['bloqueado']) {
                $errores[] = [
                    'codigo' => 'FALTA_REPRESENTACION_LEGAL',
                    'validacion' => 'VAL-PLD-004',
                    'mensaje' => $result['razon']
                ];
            }
        }
        
        // VAL-PLD-005 y VAL-PLD-006: Expediente (si hay cliente)
        if ($id_cliente && (in_array('VAL-PLD-005', $validaciones) || in_array('VAL-PLD-006', $validaciones) || empty($validaciones))) {
            try {
                requireExpedienteCompleto($pdo, $id_cliente, false);
            } catch (Exception $e) {
                $codigo = strpos($e->getMessage(), 'IDENTIFICACION_INCOMPLETA') !== false ? 'IDENTIFICACION_INCOMPLETA' : 'EXPEDIENTE_VENCIDO';
                $validacion = strpos($e->getMessage(), 'IDENTIFICACION_INCOMPLETA') !== false ? 'VAL-PLD-005' : 'VAL-PLD-006';
                $errores[] = [
                    'codigo' => $codigo,
                    'validacion' => $validacion,
                    'mensaje' => $e->getMessage()
                ];
            }
        }
        
        // VAL-PLD-007 y VAL-PLD-015: Beneficiario Controlador (si hay cliente)
        if ($id_cliente && (in_array('VAL-PLD-007', $validaciones) || in_array('VAL-PLD-015', $validaciones) || empty($validaciones))) {
            try {
                requireBeneficiarioControlador($pdo, $id_cliente, false);
            } catch (Exception $e) {
                $errores[] = [
                    'codigo' => 'BENEFICIARIO_CONTROLADOR_NO_IDENTIFICADO',
                    'validacion' => 'VAL-PLD-007/VAL-PLD-015',
                    'mensaje' => $e->getMessage()
                ];
            }
        }
        
        // Si hay errores, retornar o lanzar excepción
        if (!empty($errores)) {
            $primerError = $errores[0];
            
            if ($returnJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'code' => $primerError['codigo'],
                    'message' => 'Validación PLD fallida',
                    'validacion' => $primerError['validacion'],
                    'razon' => $primerError['mensaje'],
                    'todos_errores' => $errores
                ]);
                exit;
            } else {
                throw new Exception($primerError['codigo'] . ': ' . $primerError['mensaje']);
            }
        }
        
        return null;
    }
}

if (!function_exists('requireConservacionInformacion')) {
    
    /**
     * Valida que la conservación de información esté completa
     * VAL-PLD-013
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int|null $id_cliente ID del cliente
     * @param int|null $id_operacion ID de la operación
     * @param int|null $id_aviso ID del aviso
     * @param bool $returnJson Si es true, retorna JSON; si es false, lanza excepción
     * @throws Exception Si returnJson es false y hay problemas
     */
    function requireConservacionInformacion($pdo, $id_cliente = null, $id_operacion = null, $id_aviso = null, $returnJson = true) {
        $result = validateConservacionInformacion($pdo, $id_cliente, $id_operacion, $id_aviso);
        
        if ($result['expediente_incompleto']) {
            $mensaje = $result['mensaje'] ?? 'Falta evidencia para conservación (VAL-PLD-013)';
            
            if ($returnJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'code' => 'EXPEDIENTE_INCOMPLETO',
                    'validacion' => 'VAL-PLD-013',
                    'message' => 'Validación PLD fallida',
                    'razon' => $mensaje,
                    'detalles' => [
                        'faltantes' => $result['faltantes'] ?? [],
                        'vencidas' => $result['vencidas'] ?? []
                    ]
                ]);
                exit;
            } else {
                throw new Exception('EXPEDIENTE_INCOMPLETO: ' . $mensaje);
            }
        }
    }
}

