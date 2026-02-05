<?php
/**
 * PLD Validation - VAL-PLD-013, VAL-PLD-014
 * Conservación y Auditoría
 * 
 * VAL-PLD-013: Conservación de Información (10 años)
 * VAL-PLD-014: Atención a Visitas de Verificación
 */

if (!function_exists('validateConservacionInformacion')) {
    
    /**
     * Valida la conservación de información (10 años)
     * VAL-PLD-013
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int|null $id_cliente ID del cliente (opcional)
     * @param int|null $id_operacion ID de la operación (opcional)
     * @param int|null $id_aviso ID del aviso (opcional)
     * @return array Resultado de la validación
     */
    function validateConservacionInformacion($pdo, $id_cliente = null, $id_operacion = null, $id_aviso = null) {
        try {
            $sql = "SELECT * FROM conservacion_informacion_pld WHERE id_status = 1";
            $params = [];
            
            if ($id_cliente) {
                $sql .= " AND id_cliente = ?";
                $params[] = $id_cliente;
            }
            
            if ($id_operacion) {
                $sql .= " AND id_operacion = ?";
                $params[] = $id_operacion;
            }
            
            if ($id_aviso) {
                $sql .= " AND id_aviso = ?";
                $params[] = $id_aviso;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $evidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $faltantes = [];
            $vencidas = [];
            $disponibles = [];
            $cambios = [];
            $ediciones = [];
            
            foreach ($evidencias as $evidencia) {
                // Verificar que el archivo existe (resolver ruta relativa o absoluta)
                $rutaEvidencia = $evidencia['ruta_evidencia'];
                $rutaCompleta = $rutaEvidencia;
                
                // Intentar diferentes rutas
                if (!file_exists($rutaCompleta)) {
                    $rutaCompleta = __DIR__ . '/../' . $rutaEvidencia;
                }
                if (!file_exists($rutaCompleta)) {
                    $rutaCompleta = dirname(__DIR__) . '/' . $rutaEvidencia;
                }
                if (!file_exists($rutaCompleta)) {
                    $rutaCompleta = dirname(dirname(__DIR__)) . '/' . $rutaEvidencia;
                }
                
                if (!file_exists($rutaCompleta)) {
                    $faltantes[] = [
                        'id_conservacion' => $evidencia['id_conservacion'],
                        'ruta' => $evidencia['ruta_evidencia'],
                        'tipo' => $evidencia['tipo_evidencia'],
                        'fecha_creacion' => $evidencia['fecha_creacion']
                    ];
                }
                
                // Verificar vencimiento (plazo vigente)
                $fechaVencimiento = new DateTime($evidencia['fecha_vencimiento']);
                $hoy = new DateTime();
                
                if ($fechaVencimiento < $hoy) {
                    $vencidas[] = [
                        'id_conservacion' => $evidencia['id_conservacion'],
                        'fecha_vencimiento' => $evidencia['fecha_vencimiento'],
                        'tipo' => $evidencia['tipo_evidencia'],
                        'dias_vencido' => $hoy->diff($fechaVencimiento)->days
                    ];
                } else {
                    $disponibles[] = $evidencia;
                }
                
                // Verificar cambios y ediciones (si hay campo de fecha_modificacion)
                if (isset($evidencia['fecha_modificacion']) && $evidencia['fecha_modificacion'] > $evidencia['fecha_creacion']) {
                    $cambios[] = [
                        'id_conservacion' => $evidencia['id_conservacion'],
                        'fecha_modificacion' => $evidencia['fecha_modificacion'],
                        'tipo' => $evidencia['tipo_evidencia']
                    ];
                }
            }
            
            $expedienteIncompleto = !empty($faltantes) || !empty($vencidas);
            
            // Actualizar flag si hay problemas
            if ($expedienteIncompleto) {
                foreach (array_merge($faltantes, $vencidas) as $problema) {
                    $stmt = $pdo->prepare("UPDATE conservacion_informacion_pld 
                                           SET expediente_incompleto = 1 
                                           WHERE id_conservacion = ?");
                    $stmt->execute([$problema['id_conservacion']]);
                }
            }
            
            return [
                'valido' => empty($faltantes) && empty($vencidas),
                'expediente_incompleto' => $expedienteIncompleto,
                'faltantes' => $faltantes,
                'vencidas' => $vencidas,
                'disponibles' => count($disponibles),
                'total' => count($evidencias),
                'cambios' => $cambios,
                'ediciones' => $ediciones,
                'codigo' => $expedienteIncompleto ? 'EXPEDIENTE_INCOMPLETO' : null,
                'mensaje' => $expedienteIncompleto 
                    ? 'Falta evidencia o evidencia vencida para conservación' 
                    : 'Conservación de información válida'
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateConservacionInformacion: " . $e->getMessage());
            return [
                'valido' => false,
                'expediente_incompleto' => true,
                'error' => $e->getMessage(),
                'codigo' => 'EXPEDIENTE_INCOMPLETO'
            ];
        }
    }
}

if (!function_exists('registrarConservacionInformacion')) {
    
    /**
     * Registra evidencia para conservación (10 años)
     * VAL-PLD-013
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param array $data Datos de la evidencia
     * @return array Resultado de la operación
     */
    function registrarConservacionInformacion($pdo, $data) {
        try {
            $id_cliente = $data['id_cliente'] ?? null;
            $id_operacion = $data['id_operacion'] ?? null;
            $id_aviso = $data['id_aviso'] ?? null;
            $tipo_evidencia = $data['tipo_evidencia'] ?? null;
            $ruta_evidencia = $data['ruta_evidencia'] ?? null;
            
            if (!$tipo_evidencia || !$ruta_evidencia) {
                return [
                    'success' => false,
                    'message' => 'Datos incompletos: tipo_evidencia y ruta_evidencia son requeridos'
                ];
            }
            
            // Calcular fecha de vencimiento (10 años desde hoy)
            $fechaCreacion = new DateTime();
            $fechaVencimiento = clone $fechaCreacion;
            $fechaVencimiento->modify('+10 years');
            
            // La ruta viene del API como relativa (ej: "uploads/conservacion/archivo.pdf")
            // No necesitamos verificar existencia aquí porque el archivo acaba de subirse
            // La verificación se hará después de guardar en la BD
            
            $basePath = dirname(dirname(__DIR__)) . '/'; // Directorio raíz del proyecto
            
            // Normalizar ruta relativa (eliminar barras iniciales)
            $rutaRelativaBD = ltrim($ruta_evidencia, '/\\');
            
            // Verificar si ya existe un registro para esta evidencia
            $stmtCheck = $pdo->prepare("SELECT id_conservacion FROM conservacion_informacion_pld 
                                       WHERE id_cliente <=> ? AND id_operacion <=> ? AND id_aviso <=> ? 
                                       AND tipo_evidencia = ? AND id_status = 1");
            $stmtCheck->execute([$id_cliente, $id_operacion, $id_aviso, $tipo_evidencia]);
            $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($existente) {
                // Actualizar registro existente
                // Verificar si existe columna fecha_modificacion
                $stmtCol = $pdo->query("SHOW COLUMNS FROM conservacion_informacion_pld LIKE 'fecha_modificacion'");
                $tieneFechaModificacion = $stmtCol->rowCount() > 0;
                
                if ($tieneFechaModificacion) {
                    $stmt = $pdo->prepare("UPDATE conservacion_informacion_pld 
                                           SET ruta_evidencia = ?,
                                               fecha_vencimiento = ?,
                                               fecha_modificacion = NOW(),
                                               expediente_incompleto = 0
                                           WHERE id_conservacion = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE conservacion_informacion_pld 
                                           SET ruta_evidencia = ?,
                                               fecha_vencimiento = ?,
                                               expediente_incompleto = 0
                                           WHERE id_conservacion = ?");
                }
                $stmt->execute([$rutaRelativaBD, $fechaVencimiento->format('Y-m-d'), $existente['id_conservacion']]);
                $id_conservacion = $existente['id_conservacion'];
            } else {
                // Crear nuevo registro
                $stmt = $pdo->prepare("INSERT INTO conservacion_informacion_pld 
                                       (id_cliente, id_operacion, id_aviso, tipo_evidencia, 
                                        ruta_evidencia, fecha_creacion, fecha_vencimiento, 
                                        expediente_incompleto, id_status) 
                                       VALUES (?, ?, ?, ?, ?, NOW(), ?, 0, 1)");
                $stmt->execute([$id_cliente, $id_operacion, $id_aviso, $tipo_evidencia,
                               $rutaRelativaBD, $fechaVencimiento->format('Y-m-d')]);
                $id_conservacion = $pdo->lastInsertId();
            }
            
            // Verificar que el archivo existe después de guardar (para validación)
            $rutaVerificacion = $basePath . $rutaRelativaBD;
            if (!file_exists($rutaVerificacion)) {
                // Marcar como incompleto si el archivo no existe
                $stmt = $pdo->prepare("UPDATE conservacion_informacion_pld 
                                       SET expediente_incompleto = 1 
                                       WHERE id_conservacion = ?");
                $stmt->execute([$id_conservacion]);
                
                error_log("Advertencia: Archivo de evidencia no encontrado después de registro: " . $rutaVerificacion);
            }
            
            return [
                'success' => true,
                'message' => 'Evidencia registrada para conservación',
                'id_conservacion' => $id_conservacion,
                'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d')
            ];
            
        } catch (Exception $e) {
            error_log("Error en registrarConservacion: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al registrar conservación: ' . $e->getMessage(),
                'expediente_incompleto' => true
            ];
        }
    }
    
    /**
     * Valida que la evidencia esté disponible y no haya vencido
     * VAL-PLD-013
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int|null $id_cliente ID del cliente (opcional)
     * @param int|null $id_operacion ID de la operación (opcional)
     * @return array Resultado de la validación
     */
    function validateConservacion($pdo, $id_cliente = null, $id_operacion = null) {
        try {
            $sql = "SELECT * FROM conservacion_informacion_pld WHERE id_status = 1";
            $params = [];
            
            if ($id_cliente) {
                $sql .= " AND id_cliente = ?";
                $params[] = $id_cliente;
            }
            
            if ($id_operacion) {
                $sql .= " AND id_operacion = ?";
                $params[] = $id_operacion;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $evidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $faltantes = [];
            $vencidas = [];
            $disponibles = [];
            
            foreach ($evidencias as $evidencia) {
                // Verificar que el archivo existe
                if (!file_exists($evidencia['ruta_evidencia'])) {
                    $faltantes[] = [
                        'id_conservacion' => $evidencia['id_conservacion'],
                        'ruta' => $evidencia['ruta_evidencia'],
                        'tipo' => $evidencia['tipo_evidencia']
                    ];
                }
                
                // Verificar vencimiento
                $fechaVencimiento = new DateTime($evidencia['fecha_vencimiento']);
                $hoy = new DateTime();
                
                if ($fechaVencimiento < $hoy) {
                    $vencidas[] = [
                        'id_conservacion' => $evidencia['id_conservacion'],
                        'fecha_vencimiento' => $evidencia['fecha_vencimiento'],
                        'tipo' => $evidencia['tipo_evidencia']
                    ];
                } else {
                    $disponibles[] = $evidencia;
                }
            }
            
            $expedienteIncompleto = !empty($faltantes);
            
            // Actualizar flag si hay problemas
            if ($expedienteIncompleto) {
                foreach ($faltantes as $faltante) {
                    $stmt = $pdo->prepare("UPDATE conservacion_informacion_pld 
                                           SET expediente_incompleto = 1 
                                           WHERE id_conservacion = ?");
                    $stmt->execute([$faltante['id_conservacion']]);
                }
            }
            
            return [
                'valido' => empty($faltantes) && empty($vencidas),
                'expediente_incompleto' => $expedienteIncompleto,
                'faltantes' => $faltantes,
                'vencidas' => $vencidas,
                'disponibles' => count($disponibles),
                'total' => count($evidencias),
                'codigo' => $expedienteIncompleto ? 'EXPEDIENTE_INCOMPLETO' : null
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateConservacion: " . $e->getMessage());
            return [
                'valido' => false,
                'expediente_incompleto' => true,
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('registrarEventoCriticoPLD')) {
    /**
     * Registra un evento crítico PLD (ej. expediente/evidencia no disponible)
     * VAL-PLD-014 - No disponible → evento crítico
     *
     * @param PDO $pdo
     * @param array $data id_visita, tipo, descripcion, expedientes_solicitados, id_usuario_registro
     * @return array
     */
    function registrarEventoCriticoPLD($pdo, $data) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'eventos_criticos_pld'");
            if ($stmt->rowCount() === 0) {
                error_log("VAL-PLD-014: Tabla eventos_criticos_pld no existe. Ejecute add_eventos_criticos_pld.sql");
                return ['success' => false, 'message' => 'Tabla eventos_criticos_pld no existe'];
            }
            $id_visita = $data['id_visita'] ?? null;
            $tipo = $data['tipo'] ?? 'expediente_no_disponible';
            $descripcion = $data['descripcion'] ?? 'Evidencia o expedientes no disponibles';
            $id_usuario = $data['id_usuario_registro'] ?? null;
            $detalle = [];
            if (!empty($data['expedientes_solicitados'])) {
                $detalle['expedientes_solicitados'] = is_array($data['expedientes_solicitados'])
                    ? $data['expedientes_solicitados'] : json_decode($data['expedientes_solicitados'], true);
            }
            $detalleJson = empty($detalle) ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE);
            $stmt = $pdo->prepare("INSERT INTO eventos_criticos_pld 
                (id_visita, tipo, descripcion, detalle_json, id_usuario_registro, id_status) 
                VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$id_visita, $tipo, $descripcion, $detalleJson, $id_usuario]);
            $id_evento = $pdo->lastInsertId();
            if (function_exists('logChange')) {
                logChange($pdo, (int)$id_usuario, 'EVENTO_CRITICO_PLD', 'eventos_criticos_pld', (int)$id_evento, null, [
                    'id_visita' => $id_visita, 'tipo' => $tipo, 'descripcion' => $descripcion
                ]);
            }
            return ['success' => true, 'id_evento' => $id_evento];
        } catch (Exception $e) {
            error_log("Error en registrarEventoCriticoPLD: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('registrarVisitaVerificacion')) {
    
    /**
     * Registra una visita de verificación
     * VAL-PLD-014
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param array $data Datos de la visita
     * @return array Resultado de la operación
     */
    function registrarVisitaVerificacion($pdo, $data) {
        try {
            $fecha_visita = $data['fecha_visita'] ?? null;
            $autoridad = $data['autoridad'] ?? null;
            $tipo_requerimiento = $data['tipo_requerimiento'] ?? null;
            $expedientes_solicitados = $data['expedientes_solicitados'] ?? null;
            $observaciones = $data['observaciones'] ?? null;
            
            if (!$fecha_visita) {
                return [
                    'success' => false,
                    'message' => 'Fecha de visita es requerida'
                ];
            }
            
            // Validar que los expedientes solicitados estén disponibles
            $expedientesDisponibles = true;
            if (!empty($expedientes_solicitados)) {
                $expedientesArray = is_array($expedientes_solicitados) 
                    ? $expedientes_solicitados 
                    : json_decode($expedientes_solicitados, true);
                
                foreach ($expedientesArray as $id_cliente) {
                    $idCliente = is_array($id_cliente) ? ($id_cliente['id_cliente'] ?? $id_cliente) : (int) $id_cliente;
                    $result = validateConservacionInformacion($pdo, $idCliente);
                    if ($result['expediente_incompleto'] || !$result['valido']) {
                        $expedientesDisponibles = false;
                        break;
                    }
                }
            }
            
            $stmtCol = $pdo->query("SHOW COLUMNS FROM visitas_verificacion_pld LIKE 'observaciones'");
            $tieneObservaciones = $stmtCol->rowCount() > 0;
            if ($tieneObservaciones) {
                $stmt = $pdo->prepare("INSERT INTO visitas_verificacion_pld 
                                     (fecha_visita, autoridad, tipo_requerimiento, expedientes_solicitados, 
                                      expedientes_disponibles, observaciones, estatus, id_status) 
                                     VALUES (?, ?, ?, ?, ?, ?, 'programada', 1)");
            } else {
                $stmt = $pdo->prepare("INSERT INTO visitas_verificacion_pld 
                                     (fecha_visita, autoridad, tipo_requerimiento, 
                                      expedientes_solicitados, expedientes_disponibles, 
                                      estatus, id_status) 
                                     VALUES (?, ?, ?, ?, ?, 'programada', 1)");
            }
            
            $expedientesJson = is_array($expedientes_solicitados) 
                ? json_encode($expedientes_solicitados) 
                : $expedientes_solicitados;
            
            if ($tieneObservaciones) {
                $stmt->execute([$fecha_visita, $autoridad, $tipo_requerimiento,
                               $expedientesJson, $expedientesDisponibles ? 1 : 0, $observaciones]);
            } else {
                $stmt->execute([$fecha_visita, $autoridad, $tipo_requerimiento,
                               $expedientesJson, $expedientesDisponibles ? 1 : 0]);
            }
            
            $id_visita = $pdo->lastInsertId();
            
            // VAL-PLD-014: No disponible → evento crítico
            if (!$expedientesDisponibles) {
                $id_usuario = $data['id_usuario'] ?? null;
                registrarEventoCriticoPLD($pdo, [
                    'id_visita' => $id_visita,
                    'tipo' => 'expediente_no_disponible',
                    'descripcion' => 'Expedientes o evidencia solicitados no disponibles en visita de verificación',
                    'expedientes_solicitados' => $expedientes_solicitados,
                    'id_usuario_registro' => $id_usuario
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Visita de verificación registrada',
                'id_visita' => $id_visita,
                'expedientes_disponibles' => $expedientesDisponibles,
                'evento_critico' => !$expedientesDisponibles
            ];
            
        } catch (Exception $e) {
            error_log("Error en registrarVisitaVerificacion: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al registrar visita: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Valida que los expedientes estén disponibles para una visita
     * VAL-PLD-014
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param array $expedientesSolicitados Array de IDs de clientes
     * @return array Resultado de la validación
     */
    function validateExpedientesDisponibles($pdo, $expedientesSolicitados) {
        try {
            $disponibles = [];
            $no_disponibles = [];
            
            foreach ($expedientesSolicitados as $id_cliente) {
                $result = validateConservacionInformacion($pdo, $id_cliente);
                
                if ($result['valido'] && !$result['expediente_incompleto']) {
                    $disponibles[] = $id_cliente;
                } else {
                    $no_disponibles[] = [
                        'id_cliente' => $id_cliente,
                        'razon' => $result['expediente_incompleto'] ? 'Expediente incompleto' : 'Evidencia faltante',
                        'detalles' => [
                            'faltantes' => $result['faltantes'] ?? [],
                            'vencidas' => $result['vencidas'] ?? []
                        ]
                    ];
                }
            }
            
            return [
                'todos_disponibles' => empty($no_disponibles),
                'disponibles' => $disponibles,
                'no_disponibles' => $no_disponibles,
                'total_solicitados' => count($expedientesSolicitados),
                'total_disponibles' => count($disponibles)
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateExpedientesDisponibles: " . $e->getMessage());
            return [
                'todos_disponibles' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
