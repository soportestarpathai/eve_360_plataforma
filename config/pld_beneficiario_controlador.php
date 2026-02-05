<?php
/**
 * PLD Validation - VAL-PLD-007, VAL-PLD-015
 * Identificación de Beneficiario Controlador
 * 
 * VAL-PLD-007: Valida identificación de beneficiario controlador
 * VAL-PLD-015: Valida registro completo del beneficiario controlador
 */

if (!function_exists('validateBeneficiarioControlador')) {
    
    /**
     * Valida que el beneficiario controlador esté identificado
     * VAL-PLD-007, VAL-PLD-015
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @return array Resultado de la validación
     */
    function validateBeneficiarioControlador($pdo, $id_cliente) {
        try {
            // Obtener tipo de persona del cliente
            $stmt = $pdo->prepare("SELECT c.id_tipo_persona, tp.es_moral, tp.es_fideicomiso, tp.nombre as tipo_nombre
                                   FROM clientes c
                                   LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
                                   WHERE c.id_cliente = ?");
            $stmt->execute([$id_cliente]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return [
                    'identificado' => false,
                    'bloqueado' => true,
                    'razon' => 'Cliente no encontrado',
                    'requerido' => false
                ];
            }
            
            // Solo aplica para personas morales y fideicomisos
            $requerido = ($cliente['es_moral'] > 0 || $cliente['es_fideicomiso'] > 0);
            
            if (!$requerido) {
                // Para personas físicas, puede requerir declaración pero no es obligatorio
                return [
                    'identificado' => true,
                    'bloqueado' => false,
                    'razon' => 'No aplica (persona física)',
                    'requerido' => false
                ];
            }
            
            // Verificar si existe beneficiario controlador registrado
            $stmt = $pdo->prepare("SELECT * FROM clientes_beneficiario_controlador 
                                   WHERE id_cliente = ? AND id_status = 1");
            $stmt->execute([$id_cliente]);
            $beneficiarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($beneficiarios)) {
                return [
                    'identificado' => false,
                    'bloqueado' => true,
                    'razon' => 'Beneficiario controlador no identificado (requerido para ' . $cliente['tipo_nombre'] . ')',
                    'requerido' => true,
                    'tipo_cliente' => $cliente['tipo_nombre']
                ];
            }
            
            // Validar que cada beneficiario tenga documentación completa
            $incompletos = [];
            foreach ($beneficiarios as $benef) {
                $faltantes = [];
                
                // Validar datos básicos
                if (empty($benef['nombre_completo'])) {
                    $faltantes[] = 'Nombre completo';
                }
                
                // Para personas morales, RFC es importante
                if ($benef['tipo_persona'] === 'moral' && empty($benef['rfc'])) {
                    $faltantes[] = 'RFC';
                }
                
                // Validar documentación según tipo
                if ($benef['tipo_persona'] === 'moral') {
                    // Persona moral requiere documentación obligatoria
                    if (empty($benef['documento_identificacion'])) {
                        $faltantes[] = 'Documento de identificación';
                    }
                } else {
                    // Persona física requiere declaración jurada
                    if (empty($benef['declaracion_jurada'])) {
                        $faltantes[] = 'Declaración jurada';
                    }
                }
                
                if (!empty($faltantes)) {
                    $incompletos[] = [
                        'id_beneficiario' => $benef['id_beneficiario'],
                        'nombre' => $benef['nombre_completo'] ?? 'Sin nombre',
                        'faltantes' => $faltantes
                    ];
                }
            }
            
            if (!empty($incompletos)) {
                return [
                    'identificado' => false,
                    'bloqueado' => true,
                    'razon' => 'Beneficiario controlador con documentación incompleta',
                    'requerido' => true,
                    'incompletos' => $incompletos,
                    'total_beneficiarios' => count($beneficiarios),
                    'completos' => count($beneficiarios) - count($incompletos)
                ];
            }
            
            return [
                'identificado' => true,
                'bloqueado' => false,
                'razon' => 'Beneficiario controlador identificado correctamente',
                'requerido' => true,
                'total_beneficiarios' => count($beneficiarios),
                'beneficiarios' => $beneficiarios
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateBeneficiarioControlador: " . $e->getMessage());
            return [
                'identificado' => false,
                'bloqueado' => true,
                'razon' => 'Error al validar beneficiario controlador: ' . $e->getMessage(),
                'requerido' => false
            ];
        }
    }
    
    /**
     * Registra o actualiza un beneficiario controlador
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param array $data Datos del beneficiario
     * @return array Resultado de la operación
     */
    function registrarBeneficiarioControlador($pdo, $data) {
        try {
            $id_cliente = $data['id_cliente'] ?? null;
            $tipo_persona = $data['tipo_persona'] ?? null;
            $nombre_completo = $data['nombre_completo'] ?? null;
            $rfc = $data['rfc'] ?? null;
            $porcentaje_participacion = $data['porcentaje_participacion'] ?? null;
            $documento_identificacion = $data['documento_identificacion'] ?? null;
            $declaracion_jurada = $data['declaracion_jurada'] ?? null;
            $id_beneficiario = $data['id_beneficiario'] ?? null;
            
            if (!$id_cliente || !$tipo_persona || !$nombre_completo) {
                return [
                    'success' => false,
                    'message' => 'Datos incompletos: id_cliente, tipo_persona y nombre_completo son requeridos'
                ];
            }
            
            if ($id_beneficiario) {
                // Actualizar existente
                $stmt = $pdo->prepare("UPDATE clientes_beneficiario_controlador 
                                       SET tipo_persona = ?, nombre_completo = ?, rfc = ?, 
                                           porcentaje_participacion = ?, documento_identificacion = ?, 
                                           declaracion_jurada = ?, fecha_ultima_actualizacion = CURDATE()
                                       WHERE id_beneficiario = ?");
                $stmt->execute([$tipo_persona, $nombre_completo, $rfc, $porcentaje_participacion,
                              $documento_identificacion, $declaracion_jurada, $id_beneficiario]);
            } else {
                // Crear nuevo
                $stmt = $pdo->prepare("INSERT INTO clientes_beneficiario_controlador 
                                       (id_cliente, tipo_persona, nombre_completo, rfc, 
                                        porcentaje_participacion, documento_identificacion, 
                                        declaracion_jurada, fecha_registro, fecha_ultima_actualizacion, id_status) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), CURDATE(), 1)");
                $stmt->execute([$id_cliente, $tipo_persona, $nombre_completo, $rfc,
                              $porcentaje_participacion, $documento_identificacion, $declaracion_jurada]);
                $id_beneficiario = $pdo->lastInsertId();
            }
            
            return [
                'success' => true,
                'message' => 'Beneficiario controlador registrado correctamente',
                'id_beneficiario' => $id_beneficiario
            ];
            
        } catch (Exception $e) {
            error_log("Error en registrarBeneficiarioControlador: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al registrar beneficiario controlador: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Bloquea operación si el beneficiario controlador no está identificado
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param bool $returnJson Si es true, retorna JSON. Si es false, lanza excepción
     * @return array|null Retorna array con error si returnJson es true
     * @throws Exception Si returnJson es false y no está identificado
     */
    function requireBeneficiarioControlador($pdo, $id_cliente, $returnJson = true) {
        $result = validateBeneficiarioControlador($pdo, $id_cliente);
        
        if ($result['requerido'] && (!$result['identificado'] || $result['bloqueado'])) {
            if ($returnJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'code' => 'BENEFICIARIO_CONTROLADOR_NO_IDENTIFICADO',
                    'message' => 'Beneficiario controlador no identificado',
                    'razon' => $result['razon'],
                    'detalles' => $result
                ]);
                exit;
            } else {
                throw new Exception('BENEFICIARIO_CONTROLADOR_NO_IDENTIFICADO: ' . $result['razon']);
            }
        }
        
        return null;
    }
}
