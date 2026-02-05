<?php
/**
 * PLD Validation - VAL-PLD-004
 * Representación Legal del Usuario
 * 
 * Valida que quien actúa en nombre de la entidad tenga facultades documentadas
 */

if (!function_exists('validateRepresentacionLegal')) {
    
    /**
     * Valida la representación legal de un usuario
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_usuario ID del usuario a validar
     * @param int|null $id_cliente ID del cliente (opcional, si aplica)
     * @return array Resultado de la validación
     */
    function validateRepresentacionLegal($pdo, $id_usuario, $id_cliente = null) {
        try {
            // Verificar si el usuario tiene representación legal registrada
            $sql = "SELECT * FROM usuarios_representacion_legal 
                    WHERE id_usuario = ? AND id_status = 1";
            $params = [$id_usuario];
            
            if ($id_cliente !== null) {
                $sql .= " AND (id_cliente = ? OR id_cliente IS NULL)";
                $params[] = $id_cliente;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $representaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($representaciones)) {
                return [
                    'valido' => false,
                    'bloqueado' => true,
                    'razon' => 'No existe representación legal registrada para el usuario',
                    'tipo_requerido' => 'representante_legal',
                    'detalles' => [
                        'id_usuario' => $id_usuario,
                        'id_cliente' => $id_cliente,
                        'representaciones_encontradas' => 0
                    ]
                ];
            }
            
            // Verificar que al menos una representación tenga documento de facultades
            $tieneDocumento = false;
            $representacionesValidas = [];
            
            foreach ($representaciones as $rep) {
                if (!empty($rep['documento_facultades']) && file_exists($rep['documento_facultades'])) {
                    $tieneDocumento = true;
                    $representacionesValidas[] = $rep;
                }
            }
            
            if (!$tieneDocumento) {
                return [
                    'valido' => false,
                    'bloqueado' => true,
                    'razon' => 'Falta evidencia documental de facultades',
                    'tipo_requerido' => 'documento_facultades',
                    'detalles' => [
                        'id_usuario' => $id_usuario,
                        'representaciones_sin_documento' => count($representaciones),
                        'representaciones' => $representaciones
                    ]
                ];
            }
            
            // Verificar vencimiento si aplica
            $vencidas = [];
            foreach ($representacionesValidas as $rep) {
                if (!empty($rep['fecha_vencimiento'])) {
                    $fechaVencimiento = new DateTime($rep['fecha_vencimiento']);
                    $hoy = new DateTime();
                    if ($fechaVencimiento < $hoy) {
                        $vencidas[] = $rep;
                    }
                }
            }
            
            if (!empty($vencidas)) {
                return [
                    'valido' => false,
                    'bloqueado' => true,
                    'razon' => 'Documento de facultades vencido',
                    'tipo_requerido' => 'documento_renovado',
                    'detalles' => [
                        'id_usuario' => $id_usuario,
                        'representaciones_vencidas' => $vencidas
                    ]
                ];
            }
            
            return [
                'valido' => true,
                'bloqueado' => false,
                'razon' => 'Representación legal válida',
                'detalles' => [
                    'id_usuario' => $id_usuario,
                    'representaciones_validas' => count($representacionesValidas),
                    'tipos' => array_column($representacionesValidas, 'tipo_representacion')
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateRepresentacionLegal: " . $e->getMessage());
            return [
                'valido' => false,
                'bloqueado' => true,
                'razon' => 'Error al validar representación legal: ' . $e->getMessage(),
                'detalles' => []
            ];
        }
    }
    
    /**
     * Registra o actualiza la representación legal de un usuario
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param array $data Datos de la representación
     * @return array Resultado de la operación
     */
    function registrarRepresentacionLegal($pdo, $data) {
        try {
            $id_usuario = $data['id_usuario'] ?? null;
            $id_cliente = $data['id_cliente'] ?? null;
            $tipo_representacion = $data['tipo_representacion'] ?? null;
            $documento_facultades = $data['documento_facultades'] ?? null;
            $fecha_vencimiento = $data['fecha_vencimiento'] ?? null;
            
            if (!$id_usuario || !$tipo_representacion) {
                return [
                    'success' => false,
                    'message' => 'Datos incompletos: id_usuario y tipo_representacion son requeridos'
                ];
            }
            
            // Verificar si ya existe una representación del mismo tipo
            $stmt = $pdo->prepare("SELECT id_representacion FROM usuarios_representacion_legal 
                                   WHERE id_usuario = ? AND tipo_representacion = ? 
                                   AND (id_cliente = ? OR (id_cliente IS NULL AND ? IS NULL)) 
                                   AND id_status = 1");
            $stmt->execute([$id_usuario, $tipo_representacion, $id_cliente, $id_cliente]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existente) {
                // Actualizar existente
                $stmt = $pdo->prepare("UPDATE usuarios_representacion_legal 
                                       SET documento_facultades = ?, fecha_vencimiento = ?, 
                                           fecha_alta = COALESCE(fecha_alta, CURDATE())
                                       WHERE id_representacion = ?");
                $stmt->execute([$documento_facultades, $fecha_vencimiento, $existente['id_representacion']]);
                $id_representacion = $existente['id_representacion'];
            } else {
                // Crear nueva
                $stmt = $pdo->prepare("INSERT INTO usuarios_representacion_legal 
                                       (id_usuario, id_cliente, tipo_representacion, documento_facultades, 
                                        fecha_vencimiento, fecha_alta, id_status) 
                                       VALUES (?, ?, ?, ?, ?, CURDATE(), 1)");
                $stmt->execute([$id_usuario, $id_cliente, $tipo_representacion, 
                              $documento_facultades, $fecha_vencimiento]);
                $id_representacion = $pdo->lastInsertId();
            }
            
            return [
                'success' => true,
                'message' => 'Representación legal registrada correctamente',
                'id_representacion' => $id_representacion
            ];
            
        } catch (Exception $e) {
            error_log("Error en registrarRepresentacionLegal: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al registrar representación legal: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Bloquea acción si no hay representación legal válida
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_usuario ID del usuario
     * @param int|null $id_cliente ID del cliente (opcional)
     * @param bool $returnJson Si es true, retorna JSON. Si es false, lanza excepción
     * @return array|null Retorna array con error si returnJson es true
     * @throws Exception Si returnJson es false y no hay representación válida
     */
    function requireRepresentacionLegal($pdo, $id_usuario, $id_cliente = null, $returnJson = true) {
        $result = validateRepresentacionLegal($pdo, $id_usuario, $id_cliente);
        
        if (!$result['valido'] || $result['bloqueado']) {
            if ($returnJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'code' => 'FALTA_REPRESENTACION_LEGAL',
                    'message' => 'Falta representación legal documentada',
                    'razon' => $result['razon'],
                    'detalles' => $result['detalles']
                ]);
                exit;
            } else {
                throw new Exception('FALTA_REPRESENTACION_LEGAL: ' . $result['razon']);
            }
        }
        
        return null;
    }
}
