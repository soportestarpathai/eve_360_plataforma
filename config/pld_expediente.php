<?php
/**
 * PLD Validation - VAL-PLD-005, VAL-PLD-006, VAL-PLD-026
 * Integración y Actualización de Expediente de Identificación
 * 
 * VAL-PLD-005: Valida que el expediente esté completo
 * VAL-PLD-006: Valida que el expediente se actualice al menos 1 vez al año
 * VAL-PLD-026: Negativa de identificación del cliente → operación bloqueada
 * 
 * Validación por anexo (Art. 12 RCG): Anexo 3, 4, 4 Bis, 5, 6, 6 Bis, 7, 7 Bis, 8
 */

require_once __DIR__ . '/pld_anexo_helper.php';

if (!function_exists('_pld_col_exists')) {
    function _pld_col_exists(PDO $pdo, $table, $col) {
        static $cache = [];
        $key = $table . '.' . $col;
        if (!isset($cache[$key])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$table, $col]);
            $cache[$key] = $stmt->fetchColumn() > 0;
        }
        return $cache[$key];
    }
}

if (!function_exists('validateExpedienteCompleto')) {
    
    /**
     * Valida que el expediente de identificación esté completo
     * VAL-PLD-005
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param bool $updateFlags Si true (default), actualiza identificacion_incompleta y expediente_completo en clientes. Si false, solo devuelve el resultado sin modificar la BD.
     * @return array Resultado de la validación
     */
    function validateExpedienteCompleto($pdo, $id_cliente, $updateFlags = true) {
        try {
            // Obtener datos del cliente
            $stmt = $pdo->prepare("SELECT c.*, tp.nombre as tipo_persona_nombre, tp.es_fisica, tp.es_moral, tp.es_fideicomiso
                                   FROM clientes c
                                   LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
                                   WHERE c.id_cliente = ?");
            $stmt->execute([$id_cliente]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return [
                    'completo' => false,
                    'bloqueado' => true,
                    'razon' => 'Cliente no encontrado',
                    'faltantes' => []
                ];
            }
            
            $faltantes = [];

            // Obtener anexo aplicable (si existe cat_anexo_expediente)
            $anexo = ['id_anexo' => null, 'clave' => null, 'simplificado' => false];
            if (function_exists('getAnexoApplicable')) {
                $anexo = getAnexoApplicable($pdo, $id_cliente, false);
            }
            
            // Validar datos básicos según tipo de persona
            if ($cliente['es_fisica'] > 0) {
                $stmt = $pdo->prepare("SELECT * FROM clientes_fisicas WHERE id_cliente = ?");
                $stmt->execute([$id_cliente]);
                $fisica = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$fisica || empty($fisica['nombre']) || empty($fisica['apellido_paterno'])) {
                    $faltantes[] = 'Datos básicos de persona física (nombre, apellidos)';
                }
                // Anexo 5 (PF extranjera visitante): requiere fecha de ingreso al país
                if ($anexo['clave'] === 'ANEXO_5' && _pld_col_exists($pdo, 'clientes_fisicas', 'fecha_ingreso_pais')) {
                    if (empty($fisica['fecha_ingreso_pais'])) {
                        $faltantes[] = 'Fecha de ingreso al país (Anexo 5 - extranjero visitante)';
                    }
                }
            } elseif ($cliente['es_moral'] > 0) {
                $stmt = $pdo->prepare("SELECT * FROM clientes_morales WHERE id_cliente = ?");
                $stmt->execute([$id_cliente]);
                $moral = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$moral || empty($moral['razon_social'])) {
                    $faltantes[] = 'Datos básicos de persona moral (razón social)';
                }
            } elseif ($cliente['es_fideicomiso'] > 0) {
                $stmt = $pdo->prepare("SELECT * FROM clientes_fideicomisos WHERE id_cliente = ?");
                $stmt->execute([$id_cliente]);
                $fideicomiso = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$fideicomiso || empty($fideicomiso['numero_fideicomiso'])) {
                    $faltantes[] = 'Datos básicos de fideicomiso (número de fideicomiso)';
                }
            }
            
            // Validar identificaciones
            // Primero verificar si la columna id_status existe
            $stmt = $pdo->query("SELECT COUNT(*) as count 
                                FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'clientes_identificaciones' 
                                AND COLUMN_NAME = 'id_status'");
            $hasIdStatus = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if ($hasIdStatus) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_identificaciones WHERE id_cliente = ? AND id_status = 1");
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_identificaciones WHERE id_cliente = ?");
            }
            $stmt->execute([$id_cliente]);
            $identificaciones = $stmt->fetch(PDO::FETCH_ASSOC);
            $countIdentificaciones = (int)($identificaciones['count'] ?? 0);
            if ($countIdentificaciones == 0) {
                $faltantes[] = 'Identificaciones oficiales';
            }
            
            // Validar direcciones
            $stmt = $pdo->query("SELECT COUNT(*) as count 
                                FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'clientes_direcciones' 
                                AND COLUMN_NAME = 'id_status'");
            $hasIdStatus = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if ($hasIdStatus) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_direcciones WHERE id_cliente = ? AND id_status = 1");
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_direcciones WHERE id_cliente = ?");
            }
            $stmt->execute([$id_cliente]);
            $direcciones = $stmt->fetch(PDO::FETCH_ASSOC);
            $countDirecciones = (int)($direcciones['count'] ?? 0);
            if ($countDirecciones == 0) {
                $faltantes[] = 'Direcciones';
            }
            
            // Validar contactos
            $stmt = $pdo->query("SELECT COUNT(*) as count 
                                FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'clientes_contactos' 
                                AND COLUMN_NAME = 'id_status'");
            $hasIdStatus = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if ($hasIdStatus) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_contactos WHERE id_cliente = ? AND id_status = 1");
            } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_contactos WHERE id_cliente = ?");
            }
            $stmt->execute([$id_cliente]);
            $contactos = $stmt->fetch(PDO::FETCH_ASSOC);
            $countContactos = (int)($contactos['count'] ?? 0);
            if ($countContactos == 0) {
                $faltantes[] = 'Contactos (teléfono, email)';
            }
            
            // Validar documentos requeridos (ya sabemos que tiene id_status)
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_documentos 
                                   WHERE id_cliente = ? AND id_status = 1 AND ruta IS NOT NULL AND ruta != ''");
            $stmt->execute([$id_cliente]);
            $documentos = $stmt->fetch(PDO::FETCH_ASSOC);
            $countDocumentos = (int)($documentos['count'] ?? 0);
            if ($countDocumentos == 0) {
                $faltantes[] = 'Documentos de soporte';
            }

            // Check: Documentos vistos en original o copia certificada (Reglas - requisito previo)
            if (_pld_col_exists($pdo, 'clientes', 'documentos_vistos_original_certificado')) {
                $stmtDv = $pdo->prepare("SELECT documentos_vistos_original_certificado FROM clientes WHERE id_cliente = ?");
                $stmtDv->execute([$id_cliente]);
                $docVistos = $stmtDv->fetchColumn();
                if (empty($docVistos)) {
                    $faltantes[] = 'Confirmación: trabajador tuvo a la vista documentos originales o copia certificada';
                }
            }

            // Clasificación bajo riesgo sin Manual de Políticas vinculado
            if (!empty($cliente['clasificacion_bajo_riesgo']) && _pld_col_exists($pdo, 'clientes', 'id_manual_politicas_clasificacion')) {
                $stmtMp = $pdo->prepare("SELECT id_manual_politicas_clasificacion FROM clientes WHERE id_cliente = ?");
                $stmtMp->execute([$id_cliente]);
                $idManual = $stmtMp->fetchColumn();
                if (empty($idManual)) {
                    $faltantes[] = 'Vinculación al Manual de Políticas (clasificación bajo riesgo requiere criterios documentados Art. 37)';
                }
            }
            
            // Log para debug
            error_log("VALIDACION EXPEDIENTE Cliente $id_cliente: " .
                     "Identificaciones=$countIdentificaciones, " .
                     "Direcciones=$countDirecciones, " .
                     "Contactos=$countContactos, " .
                     "Documentos=$countDocumentos, " .
                     "Faltantes=" . count($faltantes));
            
            $completo = empty($faltantes);
            
            // Si está incompleto pero no hay faltantes específicos, verificar si hay datos inactivos
            if (!$completo && empty($faltantes)) {
                // Verificar si hay datos pero inactivos
                $stmt = $pdo->prepare("SELECT 
                    (SELECT COUNT(*) FROM clientes_identificaciones WHERE id_cliente = ?) as total_ids,
                    (SELECT COUNT(*) FROM clientes_direcciones WHERE id_cliente = ?) as total_dir,
                    (SELECT COUNT(*) FROM clientes_contactos WHERE id_cliente = ?) as total_cont,
                    (SELECT COUNT(*) FROM clientes_documentos WHERE id_cliente = ?) as total_docs");
                $stmt->execute([$id_cliente, $id_cliente, $id_cliente, $id_cliente]);
                $totales = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (($totales['total_ids'] ?? 0) > 0 || ($totales['total_dir'] ?? 0) > 0 || 
                    ($totales['total_cont'] ?? 0) > 0 || ($totales['total_docs'] ?? 0) > 0) {
                    $faltantes[] = 'Datos existentes pero inactivos (requiere activar id_status = 1)';
                } else {
                    $faltantes[] = 'Expediente incompleto - Verificar datos básicos del cliente';
                }
            }
            
            // Actualizar flag en la base de datos solo si se solicita (evita efectos secundarios en endpoints de solo lectura)
            if ($updateFlags) {
                $stmt = $pdo->prepare("UPDATE clientes 
                                       SET identificacion_incompleta = ?, expediente_completo = ? 
                                       WHERE id_cliente = ?");
                $stmt->execute([$completo ? 0 : 1, $completo ? 1 : 0, $id_cliente]);
            }
            
            return [
                'completo' => $completo,
                'bloqueado' => !$completo,
                'razon' => $completo ? 'Expediente completo' : 'Expediente incompleto',
                'faltantes' => $faltantes,
                'codigo' => $completo ? null : 'IDENTIFICACION_INCOMPLETA'
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateExpedienteCompleto: " . $e->getMessage());
            return [
                'completo' => false,
                'bloqueado' => true,
                'razon' => 'Error al validar expediente: ' . $e->getMessage(),
                'faltantes' => []
            ];
        }
    }
    
    /**
     * Valida que el expediente se haya actualizado en los últimos 12 meses
     * VAL-PLD-006
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @return array Resultado de la validación
     */
    function validateActualizacionExpediente($pdo, $id_cliente) {
        try {
            // Obtener fecha de última actualización
            $stmt = $pdo->prepare("SELECT fecha_ultima_actualizacion_expediente FROM clientes WHERE id_cliente = ?");
            $stmt->execute([$id_cliente]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente || empty($cliente['fecha_ultima_actualizacion_expediente'])) {
                return [
                    'actualizado' => false,
                    'bloqueado' => true,
                    'razon' => 'Expediente nunca actualizado',
                    'dias_vencido' => null
                ];
            }
            
            $fechaActualizacion = new DateTime($cliente['fecha_ultima_actualizacion_expediente']);
            $hoy = new DateTime();
            $diferencia = $hoy->diff($fechaActualizacion);
            $diasTranscurridos = $diferencia->days;
            
            // Debe actualizarse al menos una vez al año (365 días)
            $actualizado = $diasTranscurridos <= 365;
            
            return [
                'actualizado' => $actualizado,
                'bloqueado' => !$actualizado,
                'razon' => $actualizado ? 'Expediente actualizado' : 'Expediente vencido (requiere actualización anual)',
                'dias_vencido' => $actualizado ? 0 : ($diasTranscurridos - 365),
                'fecha_ultima_actualizacion' => $cliente['fecha_ultima_actualizacion_expediente']
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateActualizacionExpediente: " . $e->getMessage());
            return [
                'actualizado' => false,
                'bloqueado' => true,
                'razon' => 'Error al validar actualización: ' . $e->getMessage(),
                'dias_vencido' => null
            ];
        }
    }
    
    /**
     * Actualiza la fecha de última actualización del expediente
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @return bool True si se actualizó correctamente
     */
    function actualizarFechaExpediente($pdo, $id_cliente) {
        try {
            $stmt = $pdo->prepare("UPDATE clientes 
                                   SET fecha_ultima_actualizacion_expediente = CURDATE() 
                                   WHERE id_cliente = ?");
            $stmt->execute([$id_cliente]);
            return true;
        } catch (Exception $e) {
            error_log("Error en actualizarFechaExpediente: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Bloquea operación si el expediente no está completo o está vencido
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param bool $returnJson Si es true, retorna JSON. Si es false, lanza excepción
     * @return array|null Retorna array con error si returnJson es true
     * @throws Exception Si returnJson es false y hay problemas
     */
    function requireExpedienteCompleto($pdo, $id_cliente, $returnJson = true) {
        // Validar completitud (VAL-PLD-005)
        $resultCompleto = validateExpedienteCompleto($pdo, $id_cliente);
        
        if (!$resultCompleto['completo']) {
            if ($returnJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'code' => 'IDENTIFICACION_INCOMPLETA',
                    'message' => 'Expediente de identificación incompleto',
                    'razon' => $resultCompleto['razon'],
                    'faltantes' => $resultCompleto['faltantes']
                ]);
                exit;
            } else {
                throw new Exception('IDENTIFICACION_INCOMPLETA: ' . $resultCompleto['razon']);
            }
        }
        
        // Validar actualización (VAL-PLD-006)
        $resultActualizacion = validateActualizacionExpediente($pdo, $id_cliente);
        
        if (!$resultActualizacion['actualizado']) {
            if ($returnJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'code' => 'EXPEDIENTE_VENCIDO',
                    'message' => 'Expediente requiere actualización anual',
                    'razon' => $resultActualizacion['razon'],
                    'dias_vencido' => $resultActualizacion['dias_vencido']
                ]);
                exit;
            } else {
                throw new Exception('EXPEDIENTE_VENCIDO: ' . $resultActualizacion['razon']);
            }
        }
        
        return null;
    }
}

if (!function_exists('hasNegativaIdentificacion')) {
    /**
     * VAL-PLD-026 — Verifica si el cliente tiene registrada negativa a proporcionar información.
     * Si existe negativa, la operación no debe realizarse (OPERACION_RECHAZADA_PLD).
     *
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @return array ['negativa' => bool, 'fecha' => string|null, 'evidencia' => string|null]
     */
    function hasNegativaIdentificacion($pdo, $id_cliente) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes' AND COLUMN_NAME = 'negativa_identificacion_pld'");
            if ($stmt->fetch(PDO::FETCH_ASSOC)['c'] == 0) {
                return ['negativa' => false, 'fecha' => null, 'evidencia' => null];
            }
            $stmt = $pdo->prepare("SELECT negativa_identificacion_pld, fecha_negativa_identificacion_pld, evidencia_negativa_identificacion_pld FROM clientes WHERE id_cliente = ?");
            $stmt->execute([$id_cliente]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                return ['negativa' => false, 'fecha' => null, 'evidencia' => null];
            }
            $negativa = !empty($row['negativa_identificacion_pld']);
            return [
                'negativa' => (bool) $negativa,
                'fecha' => $row['fecha_negativa_identificacion_pld'] ?? null,
                'evidencia' => $row['evidencia_negativa_identificacion_pld'] ?? null
            ];
        } catch (Exception $e) {
            error_log('hasNegativaIdentificacion: ' . $e->getMessage());
            return ['negativa' => false, 'fecha' => null, 'evidencia' => null];
        }
    }
}

if (!function_exists('requireNoNegativaIdentificacion')) {
    /**
     * VAL-PLD-026 — Bloquea la acción si el cliente tiene negativa de identificación registrada.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param bool $returnJson Si true, envía JSON y exit; si false, lanza excepción
     * @return null
     * @throws Exception Si returnJson es false y hay negativa
     */
    function requireNoNegativaIdentificacion($pdo, $id_cliente, $returnJson = true) {
        $result = hasNegativaIdentificacion($pdo, $id_cliente);
        if (!$result['negativa']) {
            return null;
        }
        if ($returnJson) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'code' => 'OPERACION_RECHAZADA_PLD',
                'message' => 'No puede realizarse la transacción: el cliente registró negativa a proporcionar información de identificación'
            ]);
            exit;
        }
        throw new Exception('OPERACION_RECHAZADA_PLD: Cliente con negativa de identificación');
    }
}

