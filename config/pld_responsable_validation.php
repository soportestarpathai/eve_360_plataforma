<?php
/**
 * PLD Responsable Validation - VAL-PLD-003
 * Validación de Designación de Responsable PLD
 * 
 * Las personas morales y fideicomisos deben designar responsable PLD.
 * Validaciones:
 * - Responsable registrado
 * - Asociación activa con la entidad
 * 
 * Resultado: No registrado → RESTRICCION_USUARIO
 */

if (!function_exists('validateResponsablePLD')) {
    
    /**
     * Valida si un cliente (persona moral o fideicomiso) tiene responsable PLD designado
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente a validar
     * @return array Resultado de la validación
     */
    function validateResponsablePLD($pdo, $id_cliente) {
        try {
            // 1. Verificar que el cliente existe y obtener su tipo
            $stmt = $pdo->prepare("
                SELECT c.id_cliente, c.id_tipo_persona, tp.es_moral, tp.es_fideicomiso
                FROM clientes c
                LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
                WHERE c.id_cliente = ?
            ");
            $stmt->execute([$id_cliente]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                return [
                    'requiere_responsable' => false,
                    'tiene_responsable' => false,
                    'restriccion' => false,
                    'razon' => 'Cliente no encontrado',
                    'detalles' => []
                ];
            }
            
            // 2. Verificar si requiere responsable PLD (solo personas morales y fideicomisos)
            $requiereResponsable = ($cliente['es_moral'] == 1 || $cliente['es_fideicomiso'] == 1);
            
            if (!$requiereResponsable) {
                // Personas físicas no requieren responsable PLD
                return [
                    'requiere_responsable' => false,
                    'tiene_responsable' => false,
                    'restriccion' => false,
                    'razon' => 'No aplica: Persona física no requiere responsable PLD',
                    'detalles' => [
                        'tipo_persona' => 'fisica'
                    ]
                ];
            }
            
            // 3. Buscar responsable PLD activo para este cliente
            $stmt = $pdo->prepare("
                SELECT 
                    crp.*,
                    u.nombre as responsable_nombre,
                    u.login_user as responsable_email,
                    u.id_status_usuario
                FROM clientes_responsable_pld crp
                INNER JOIN usuarios u ON crp.id_usuario_responsable = u.id_usuario
                WHERE crp.id_cliente = ?
                  AND crp.activo = 1
                  AND (crp.fecha_baja IS NULL OR crp.fecha_baja > CURDATE())
                  AND u.id_status_usuario = 1
                ORDER BY crp.fecha_designacion DESC
                LIMIT 1
            ");
            $stmt->execute([$id_cliente]);
            $responsable = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$responsable) {
                // No hay responsable PLD designado o está inactivo
                return [
                    'requiere_responsable' => true,
                    'tiene_responsable' => false,
                    'restriccion' => true,
                    'estatus' => 'RESTRICCION_USUARIO',
                    'razon' => 'No hay responsable PLD designado o el responsable está inactivo',
                    'detalles' => [
                        'tipo_persona' => $cliente['es_moral'] == 1 ? 'moral' : 'fideicomiso',
                        'responsable_designado' => false,
                        'responsable_activo' => false
                    ]
                ];
            }
            
            // 4. Verificar que el usuario responsable está activo
            if ($responsable['id_status_usuario'] != 1) {
                return [
                    'requiere_responsable' => true,
                    'tiene_responsable' => false,
                    'restriccion' => true,
                    'estatus' => 'RESTRICCION_USUARIO',
                    'razon' => 'El responsable PLD designado está inactivo',
                    'detalles' => [
                        'tipo_persona' => $cliente['es_moral'] == 1 ? 'moral' : 'fideicomiso',
                        'responsable_designado' => true,
                        'responsable_activo' => false,
                        'id_responsable' => $responsable['id_responsable_pld']
                    ]
                ];
            }
            
            // 5. Capacitación anual REC (Art. 20 LFPIORPI, RCG Art. 10)
            $capacitacionVigente = true;
            $razonCapacitacion = null; // mensaje específico si falla por capacitación
            $tieneColumnaCapacitacion = false;
            try {
                $stmtCol = $pdo->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes_responsable_pld' AND COLUMN_NAME = 'fecha_ultima_capacitacion'");
                $rowCol = $stmtCol ? $stmtCol->fetch(PDO::FETCH_ASSOC) : false;
                $tieneColumnaCapacitacion = $rowCol && (int)($rowCol['c'] ?? 0) > 0;
            } catch (Exception $e) { /* conservar $tieneColumnaCapacitacion = false */ }
            if (!$tieneColumnaCapacitacion) {
                $capacitacionVigente = false;
                $razonCapacitacion = 'Ejecute la migración add_obligaciones_ley_rcg.sql para validar capacitación REC';
            }
            $fechaCapacitacion = $responsable['fecha_ultima_capacitacion'] ?? null;
            $vigenciaCapacitacion = $responsable['vigencia_capacitacion'] ?? null;
            if ($tieneColumnaCapacitacion && ($fechaCapacitacion !== null || $vigenciaCapacitacion !== null)) {
                $hoy = new DateTime('today');
                $vigencia = $vigenciaCapacitacion ? new DateTime($vigenciaCapacitacion) : null;
                if (!$vigencia && $fechaCapacitacion) {
                    $vigencia = new DateTime($fechaCapacitacion);
                    $vigencia->modify('+1 year');
                }
                // Vigencia = fecha de expiración: vence el mismo día (>=), no al día siguiente
                if ($vigencia && $hoy >= $vigencia) {
                    $capacitacionVigente = false;
                }
            }
            // Si nunca se ha registrado capacitación (columnas existen pero están vacías), restricción por capacitación pendiente
            if ($tieneColumnaCapacitacion && $fechaCapacitacion === null && $vigenciaCapacitacion === null) {
                $capacitacionVigente = false; // Capacitación nunca registrada → pendiente
                if ($razonCapacitacion === null) {
                    $razonCapacitacion = 'El responsable PLD debe contar con capacitación anual vigente (Art. 20 Ley, RCG Art. 10)';
                }
            }
            
            if (!$capacitacionVigente) {
                return [
                    'requiere_responsable' => true,
                    'tiene_responsable' => true,
                    'restriccion' => true,
                    'estatus' => 'RESTRICCION_USUARIO',
                    'razon' => $razonCapacitacion ?: 'El responsable PLD debe contar con capacitación anual vigente (Art. 20 Ley, RCG Art. 10)',
                    'detalles' => [
                        'tipo_persona' => $cliente['es_moral'] == 1 ? 'moral' : 'fideicomiso',
                        'responsable_designado' => true,
                        'responsable_activo' => true,
                        'capacitacion_vigente' => false,
                        'fecha_ultima_capacitacion' => $fechaCapacitacion,
                        'vigencia_capacitacion' => $vigenciaCapacitacion,
                        'id_responsable' => $responsable['id_responsable_pld']
                    ]
                ];
            }
            
            // Todas las validaciones pasaron
            return [
                'requiere_responsable' => true,
                'tiene_responsable' => true,
                'restriccion' => false,
                'estatus' => 'SIN_RESTRICCION',
                'razon' => 'Responsable PLD designado, activo y con capacitación vigente',
                'detalles' => [
                    'tipo_persona' => $cliente['es_moral'] == 1 ? 'moral' : 'fideicomiso',
                    'responsable_designado' => true,
                    'responsable_activo' => true,
                    'capacitacion_vigente' => true,
                    'fecha_ultima_capacitacion' => $fechaCapacitacion,
                    'vigencia_capacitacion' => $vigenciaCapacitacion,
                    'id_responsable' => $responsable['id_responsable_pld'],
                    'responsable_nombre' => $responsable['responsable_nombre'],
                    'responsable_email' => $responsable['responsable_email'],
                    'fecha_designacion' => $responsable['fecha_designacion']
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'requiere_responsable' => false,
                'tiene_responsable' => false,
                'restriccion' => false,
                'razon' => 'Error al validar responsable PLD: ' . $e->getMessage(),
                'detalles' => []
            ];
        }
    }
    
    /**
     * Actualiza el flag RESTRICCION_USUARIO en la tabla clientes
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param bool $tieneRestriccion True si tiene restricción, false si no
     * @return bool True si se actualizó correctamente
     */
    function updateRestriccionUsuarioFlag($pdo, $id_cliente, $tieneRestriccion) {
        try {
            $flag = $tieneRestriccion ? 1 : 0; // 1 = RESTRICCION_USUARIO, 0 = sin restricción
            $stmt = $pdo->prepare("UPDATE clientes SET restriccion_usuario = ? WHERE id_cliente = ?");
            $stmt->execute([$flag, $id_cliente]);
            return true;
        } catch (Exception $e) {
            error_log("Error updating RESTRICCION_USUARIO flag: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Valida y actualiza el flag RESTRICCION_USUARIO para un cliente
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @return array Resultado de la validación
     */
    function validateAndUpdateResponsablePLD($pdo, $id_cliente) {
        $result = validateResponsablePLD($pdo, $id_cliente);
        updateRestriccionUsuarioFlag($pdo, $id_cliente, $result['restriccion']);
        return $result;
    }
    
    /**
     * Registra o actualiza la fecha de capacitación anual del REC (Art. 20 Ley, RCG Art. 10).
     * Vigencia por defecto: fecha + 1 año.
     *
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_responsable_pld ID en clientes_responsable_pld
     * @param string|null $fecha_capacitacion Fecha (Y-m-d); si null usa hoy
     * @param string|null $vigencia Fecha vigencia (Y-m-d); si null se calcula +1 año
     * @return array ['success' => bool, 'message' => string]
     */
    function registrarCapacitacionRec($pdo, $id_responsable_pld, $fecha_capacitacion = null, $vigencia = null) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clientes_responsable_pld' AND COLUMN_NAME = 'fecha_ultima_capacitacion'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false || (int)($row['c'] ?? 0) === 0) {
                return ['success' => false, 'message' => 'Ejecute la migración add_obligaciones_ley_rcg.sql'];
            }
            $fecha = $fecha_capacitacion ?: date('Y-m-d');
            if ($vigencia === null) {
                $d = new DateTime($fecha);
                $d->modify('+1 year');
                $vigencia = $d->format('Y-m-d');
            }
            $stmt = $pdo->prepare("UPDATE clientes_responsable_pld SET fecha_ultima_capacitacion = ?, vigencia_capacitacion = ? WHERE id_responsable_pld = ?");
            $stmt->execute([$fecha, $vigencia, $id_responsable_pld]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'No existe el responsable PLD con el ID indicado (id_responsable_pld no encontrado)'];
            }
            return ['success' => true, 'message' => 'Capacitación anual REC registrada', 'fecha_ultima_capacitacion' => $fecha, 'vigencia_capacitacion' => $vigencia];
        } catch (Exception $e) {
            error_log('registrarCapacitacionRec: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Verifica si un cliente tiene restricción por falta de responsable PLD
     *
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @return bool True si tiene restricción, false si no
     */
    function hasRestriccionUsuario($pdo, $id_cliente) {
        $result = validateResponsablePLD($pdo, $id_cliente);
        return $result['restriccion'] === true;
    }
    
    /**
     * Designa un responsable PLD para un cliente
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param int $id_usuario_responsable ID del usuario a designar como responsable
     * @param string $observaciones Observaciones opcionales
     * @return array Resultado de la operación
     */
    function designarResponsablePLD($pdo, $id_cliente, $id_usuario_responsable, $observaciones = null) {
        try {
            // Verificar que el cliente requiere responsable (moral o fideicomiso)
            $validation = validateResponsablePLD($pdo, $id_cliente);
            if (!$validation['requiere_responsable']) {
                return [
                    'status' => 'error',
                    'message' => 'Este tipo de cliente no requiere responsable PLD',
                    'razon' => $validation['razon']
                ];
            }
            
            // Verificar que el usuario existe y está activo
            $stmt = $pdo->prepare("SELECT id_usuario, nombre, id_status_usuario FROM usuarios WHERE id_usuario = ?");
            $stmt->execute([$id_usuario_responsable]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuario) {
                return [
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ];
            }
            
            if ($usuario['id_status_usuario'] != 1) {
                return [
                    'status' => 'error',
                    'message' => 'El usuario seleccionado está inactivo'
                ];
            }
            
            // Desactivar responsables anteriores para este cliente
            $stmt = $pdo->prepare("
                UPDATE clientes_responsable_pld 
                SET activo = 0, fecha_baja = CURDATE()
                WHERE id_cliente = ? AND activo = 1
            ");
            $stmt->execute([$id_cliente]);
            
            // Insertar nuevo responsable
            $stmt = $pdo->prepare("
                INSERT INTO clientes_responsable_pld 
                (id_cliente, id_usuario_responsable, fecha_designacion, activo, observaciones)
                VALUES (?, ?, CURDATE(), 1, ?)
            ");
            $stmt->execute([$id_cliente, $id_usuario_responsable, $observaciones]);
            
            // Actualizar flag de restricción
            updateRestriccionUsuarioFlag($pdo, $id_cliente, false);
            
            return [
                'status' => 'success',
                'message' => 'Responsable PLD designado correctamente',
                'id_responsable' => $pdo->lastInsertId(),
                'responsable_nombre' => $usuario['nombre']
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Error al designar responsable PLD: ' . $e->getMessage()
            ];
        }
    }
}
