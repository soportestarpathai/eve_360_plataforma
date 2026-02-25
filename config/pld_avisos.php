<?php
/**
 * PLD Validation - VAL-PLD-008 a VAL-PLD-012
 * Avisos y Umbrales PLD
 * 
 * VAL-PLD-008: Aviso por Umbral Individual
 * VAL-PLD-009: Aviso por Acumulación (6 meses)
 * VAL-PLD-010: Aviso por Transacción Sospechosa
 * VAL-PLD-011: Aviso por Listas Restringidas
 * VAL-PLD-012: Informe de No Transacciones
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/pld_cliente_kyc.php';

if (!function_exists('calcularDeadlineAviso')) {
    
    /**
     * Calcula la fecha deadline para un aviso (día 17 del mes siguiente)
     * 
     * @param DateTime|string $fechaOperacion Fecha de la operación
     * @return string Fecha deadline en formato Y-m-d
     */
    function calcularDeadlineAviso($fechaOperacion) {
        if (is_string($fechaOperacion)) {
            $fecha = new DateTime($fechaOperacion);
        } else {
            $fecha = $fechaOperacion;
        }
        
        // Mes siguiente, día 17
        $fecha->modify('first day of next month');
        $fecha->setDate($fecha->format('Y'), $fecha->format('m'), 17);
        
        return $fecha->format('Y-m-d');
    }
}

if (!function_exists('validateAvisoUmbralIndividual')) {
    
    /**
     * Valida si una operación requiere aviso por umbral individual
     * VAL-PLD-008
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param float $monto Monto de la operación en MXN
     * @param int|null $id_fraccion ID de la fracción (opcional)
     * @param string|null $fechaOperacion Fecha de la transacción (Y-m-d). Se usa para deadline.
     * @return array Resultado de la validación
     */
    function validateAvisoUmbralIndividual($pdo, $id_cliente, $monto, $id_fraccion = null, $fechaOperacion = null) {
        try {
            // Obtener umbral configurado (en UMAs)
            $stmt = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1");
            $uma = $stmt->fetch(PDO::FETCH_ASSOC);
            $valorUMA = $uma ? floatval($uma['valor']) : 100.0; // Default si no hay UMA
            
            // Obtener umbral de la fracción si se especifica
            $umbralUMA = null;
            if ($id_fraccion) {
                $stmt = $pdo->prepare("SELECT umbral_aviso_uma FROM cat_vulnerables WHERE id_vulnerable = ?");
                $stmt->execute([$id_fraccion]);
                $fraccion = $stmt->fetch(PDO::FETCH_ASSOC);
                $umbralUMA = $fraccion ? floatval($fraccion['umbral_aviso_uma']) : null;
            }
            
            // Si no hay umbral específico, usar umbral general (configurable)
            if ($umbralUMA === null) {
                $stmt = $pdo->query("SELECT umbral_aviso_uma FROM config_empresa WHERE id_config = 1");
                $config = $stmt->fetch(PDO::FETCH_ASSOC);
                $umbralUMA = $config ? floatval($config['umbral_aviso_uma']) : 1000.0; // Default
            }
            
            $umbralMXN = $umbralUMA * $valorUMA;
            $montoUMA = $monto / $valorUMA;
            
            $requiereAviso = $monto >= $umbralMXN;
            
            if ($requiereAviso) {
                $fechaBase = $fechaOperacion ?: date('Y-m-d');
                $fechaDeadline = calcularDeadlineAviso($fechaBase);
                
                return [
                    'requiere_aviso' => true,
                    'tipo_aviso' => 'umbral_individual',
                    'monto' => $monto,
                    'monto_uma' => $montoUMA,
                    'umbral_uma' => $umbralUMA,
                    'umbral_mxn' => $umbralMXN,
                    'fecha_deadline' => $fechaDeadline,
                    'codigo' => 'AVISO_REQUERIDO'
                ];
            }
            
            return [
                'requiere_aviso' => false,
                'monto' => $monto,
                'monto_uma' => $montoUMA,
                'umbral_uma' => $umbralUMA,
                'umbral_mxn' => $umbralMXN
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateAvisoUmbralIndividual: " . $e->getMessage());
            return [
                'requiere_aviso' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('validateAvisoAcumulacion')) {
    
    /**
     * Valida si se requiere aviso por acumulación (ventana móvil 6 meses)
     * VAL-PLD-009
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param float $monto Monto de la nueva operación
     * @param string $fechaOperacion Fecha de la operación
     * @param int|null $id_fraccion ID de la fracción
     * @param string|null $tipoActo Tipo de acto
     * @return array Resultado de la validación
     */
    function validateAvisoAcumulacion($pdo, $id_cliente, $monto, $fechaOperacion, $id_fraccion = null, $tipoActo = null) {
        try {
            // Obtener valor UMA
            $stmt = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1");
            $uma = $stmt->fetch(PDO::FETCH_ASSOC);
            $valorUMA = $uma ? floatval($uma['valor']) : 100.0;
            
            // Obtener umbral de acumulación (configurable por fracción o general)
            $umbralUMA = null;
            if ($id_fraccion) {
                $stmt = $pdo->prepare("SELECT umbral_acumulacion_uma FROM cat_vulnerables WHERE id_vulnerable = ?");
                $stmt->execute([$id_fraccion]);
                $fraccion = $stmt->fetch(PDO::FETCH_ASSOC);
                $umbralUMA = $fraccion ? floatval($fraccion['umbral_acumulacion_uma']) : null;
            }
            
            // Si no hay umbral específico, usar umbral general (configurable)
            if ($umbralUMA === null) {
                $stmt = $pdo->query("SELECT umbral_acumulacion_uma FROM config_empresa WHERE id_config = 1");
                $config = $stmt->fetch(PDO::FETCH_ASSOC);
                $umbralUMA = $config ? floatval($config['umbral_acumulacion_uma']) : 1000.0; // Default: 1000 UMAs
            }
            
            $umbralMXN = $umbralUMA * $valorUMA;
            
            // Calcular ventana móvil de 6 meses desde la fecha de transacción actual
            $fechaOperacionObj = new DateTime($fechaOperacion);
            $fechaInicioVentana = clone $fechaOperacionObj;
            $fechaInicioVentana->modify('-6 months');
            
            // Obtener todas las operaciones en la ventana móvil (6 meses hacia atrás)
            // IMPORTANTE: La ventana móvil se calcula desde la fecha actual hacia atrás 6 meses
            $sql = "SELECT SUM(monto) as monto_acumulado, COUNT(*) as cantidad_operaciones,
                           MIN(fecha_operacion) as fecha_primera,
                           MAX(fecha_operacion) as fecha_ultima
                    FROM operaciones_pld
                    WHERE id_cliente = ? 
                    AND fecha_operacion >= ? 
                    AND fecha_operacion <= ?
                    AND id_status = 1";
            
            $params = [$id_cliente, $fechaInicioVentana->format('Y-m-d'), $fechaOperacion];
            
            // Filtrar por fracción si se especifica
            if ($id_fraccion) {
                $sql .= " AND id_fraccion = ?";
                $params[] = $id_fraccion;
            }
            
            // Filtrar por tipo de acto si se especifica
            if ($tipoActo) {
                $sql .= " AND tipo_operacion = ?";
                $params[] = $tipoActo;
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $acumulacion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Sumar el monto de la nueva operación
            $montoAcumulado = floatval($acumulacion['monto_acumulado'] ?? 0) + $monto;
            $cantidadOperaciones = intval($acumulacion['cantidad_operaciones'] ?? 0) + 1;
            $fechaPrimera = $acumulacion['fecha_primera'] ?? $fechaOperacion;
            
            // Validar si el monto acumulado rebasa el umbral
            $requiereAviso = $montoAcumulado >= $umbralMXN;
            
            if ($requiereAviso) {
                // Calcular deadline desde la primera operación de la ventana
                $fechaDeadline = calcularDeadlineAviso(new DateTime($fechaPrimera));
                
                return [
                    'requiere_aviso' => true,
                    'tipo_aviso' => 'acumulacion',
                    'monto_acumulado' => $montoAcumulado,
                    'monto_acumulado_uma' => $montoAcumulado / $valorUMA,
                    'cantidad_operaciones' => $cantidadOperaciones,
                    'fecha_primera_operacion' => $fechaPrimera,
                    'fecha_ultima_operacion' => $fechaOperacion,
                    'fecha_inicio_ventana' => $fechaInicioVentana->format('Y-m-d'),
                    'umbral_uma' => $umbralUMA,
                    'umbral_mxn' => $umbralMXN,
                    'fecha_deadline' => $fechaDeadline,
                    'codigo' => 'GENERAR_AVISO',
                    'mensaje' => "Acumulación de {$cantidadOperaciones} transacciones en 6 meses rebasa el umbral de " . number_format($umbralUMA, 2) . " UMAs"
                ];
            }
            
            return [
                'requiere_aviso' => false,
                'monto_acumulado' => $montoAcumulado,
                'monto_acumulado_uma' => $montoAcumulado / $valorUMA,
                'cantidad_operaciones' => $cantidadOperaciones,
                'fecha_primera_operacion' => $fechaPrimera,
                'fecha_ultima_operacion' => $fechaOperacion,
                'fecha_inicio_ventana' => $fechaInicioVentana->format('Y-m-d'),
                'umbral_uma' => $umbralUMA,
                'umbral_mxn' => $umbralMXN,
                'porcentaje_umbral' => ($montoAcumulado / $umbralMXN) * 100
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateAvisoAcumulacion: " . $e->getMessage());
            return [
                'requiere_aviso' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('validateAvisoSospechosa')) {
    
    /**
     * Valida si una transacción sospechosa requiere aviso 24H
     * VAL-PLD-010
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param string $fechaConocimiento Fecha de conocimiento de la sospecha
     * @return array Resultado de la validación
     */
    function validateAvisoSospechosa($pdo, $id_cliente, $fechaConocimiento = null) {
        try {
            if (empty($fechaConocimiento)) {
                $fechaConocimiento = date('Y-m-d H:i:s');
            }
            
            // Calcular deadline: 24 horas desde el conocimiento
            $fechaConocimientoObj = new DateTime($fechaConocimiento);
            $fechaDeadline = clone $fechaConocimientoObj;
            $fechaDeadline->modify('+24 hours');
            
            return [
                'requiere_aviso' => true,
                'tipo_aviso' => 'sospechosa_24h',
                'fecha_conocimiento' => $fechaConocimiento,
                'fecha_deadline' => $fechaDeadline->format('Y-m-d H:i:s'),
                'codigo' => 'AVISO_24H'
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateAvisoSospechosa: " . $e->getMessage());
            return [
                'requiere_aviso' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('validateAvisoListasRestringidas')) {
    
    /**
     * Valida si un match en listas restringidas requiere aviso 24H
     * VAL-PLD-011
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param string $fechaConocimiento Fecha de conocimiento del match
     * @return array Resultado de la validación
     */
    function validateAvisoListasRestringidas($pdo, $id_cliente, $fechaConocimiento = null) {
        try {
            // Verificar si hay match en listas restringidas
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes_busquedas_listas 
                                   WHERE id_cliente = ? AND match_encontrado = 1 AND id_status = 1");
            $stmt->execute([$id_cliente]);
            $match = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (intval($match['count']) == 0) {
                return [
                    'requiere_aviso' => false,
                    'razon' => 'No hay match en listas restringidas'
                ];
            }
            
            if (empty($fechaConocimiento)) {
                $fechaConocimiento = date('Y-m-d H:i:s');
            }
            
            // Calcular deadline: 24 horas desde el conocimiento
            $fechaConocimientoObj = new DateTime($fechaConocimiento);
            $fechaDeadline = clone $fechaConocimientoObj;
            $fechaDeadline->modify('+24 hours');
            
            return [
                'requiere_aviso' => true,
                'tipo_aviso' => 'listas_restringidas_24h',
                'fecha_conocimiento' => $fechaConocimiento,
                'fecha_deadline' => $fechaDeadline->format('Y-m-d H:i:s'),
                'codigo' => 'AVISO_24H'
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateAvisoListasRestringidas: " . $e->getMessage());
            return [
                'requiere_aviso' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('validateInformeNoOperaciones')) {
    
    /**
     * Valida si se requiere presentar informe de no operaciones
     * VAL-PLD-012
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $mes Mes a validar (1-12)
     * @param int $anio Año a validar
     * @return array Resultado de la validación
     */
    function validateInformeNoOperaciones($pdo, $mes, $anio) {
        try {
            // Verificar si hubo operaciones avisables en el periodo
            $fechaInicio = sprintf('%04d-%02d-01', $anio, $mes);
            $fechaFin = date('Y-m-t', strtotime($fechaInicio)); // Último día del mes
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM operaciones_pld 
                                   WHERE DATE_FORMAT(fecha_operacion, '%Y-%m') = ? 
                                   AND requiere_aviso = 1 AND id_status = 1");
            $stmt->execute([sprintf('%04d-%02d', $anio, $mes)]);
            $operaciones = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $huboOperaciones = intval($operaciones['count']) > 0;
            
            if ($huboOperaciones) {
                return [
                    'requiere_informe' => false,
                    'razon' => 'Hubo transacciones avisables en el periodo'
                ];
            }
            
            // Verificar si ya se presentó el informe
            $stmt = $pdo->prepare("SELECT * FROM informes_no_operaciones_pld 
                                   WHERE periodo_mes = ? AND periodo_anio = ? AND id_status = 1");
            $stmt->execute([$mes, $anio]);
            $informe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($informe && $informe['estatus'] === 'presentado') {
                return [
                    'requiere_informe' => false,
                    'razon' => 'Informe ya presentado',
                    'fecha_presentacion' => $informe['fecha_presentacion']
                ];
            }
            
            // Calcular fecha límite (día 17 del mes siguiente)
            $fechaLimite = calcularDeadlineAviso(new DateTime($fechaFin));
            
            return [
                'requiere_informe' => true,
                'periodo_mes' => $mes,
                'periodo_anio' => $anio,
                'fecha_limite' => $fechaLimite,
                'informe_existente' => $informe ? true : false,
                'estatus_informe' => $informe['estatus'] ?? null,
                'codigo' => 'INCUMPLIMIENTO_PLD'
            ];
            
        } catch (Exception $e) {
            error_log("Error en validateInformeNoOperaciones: " . $e->getMessage());
            return [
                'requiere_informe' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

if (!function_exists('pldObtenerUsuariosNotificacion')) {
    /**
     * Obtiene usuarios a notificar para eventos PLD:
     * - Administradores
     * - Responsables PLD del cliente (si tabla existe)
     */
    function pldObtenerUsuariosNotificacion($pdo, $id_cliente = null) {
        $usuarios = [];

        $stmtAdmin = $pdo->query("
            SELECT DISTINCT u.id_usuario
            FROM usuarios u
            INNER JOIN usuarios_permisos up ON u.id_usuario = up.id_usuario
            WHERE u.id_status_usuario = 1
              AND up.administracion > 0
        ");
        while ($r = $stmtAdmin->fetch(PDO::FETCH_ASSOC)) {
            $usuarios[(int)$r['id_usuario']] = true;
        }

        if ($id_cliente && function_exists('pldTableExists') && pldTableExists($pdo, 'clientes_responsable_pld')) {
            $stmtResp = $pdo->prepare("
                SELECT id_usuario_responsable
                FROM clientes_responsable_pld
                WHERE id_cliente = ?
                  AND activo = 1
                  AND (fecha_baja IS NULL OR fecha_baja > CURDATE())
            ");
            $stmtResp->execute([$id_cliente]);
            while ($r = $stmtResp->fetch(PDO::FETCH_ASSOC)) {
                $usuarios[(int)$r['id_usuario_responsable']] = true;
            }
        }

        return array_keys($usuarios);
    }
}

if (!function_exists('pldRegistrarNotificacionAvisoRequerido')) {
    /**
     * Crea notificación inmediata al detectar/generar aviso PLD.
     */
    function pldRegistrarNotificacionAvisoRequerido($pdo, $data) {
        if (!function_exists('pldTableExists') || !pldTableExists($pdo, 'notificaciones')) {
            return 0;
        }

        $id_cliente = isset($data['id_cliente']) ? (int)$data['id_cliente'] : 0;
        $id_aviso = isset($data['id_aviso']) ? (int)$data['id_aviso'] : 0;
        $id_operacion = isset($data['id_operacion']) ? (int)$data['id_operacion'] : 0;
        $tipo_aviso = $data['tipo_aviso'] ?? 'umbral_individual';
        $fecha_deadline = $data['fecha_deadline'] ?? null;
        $monto = isset($data['monto']) ? (float)$data['monto'] : 0.0;
        $xml_generado = !empty($data['xml_generado']) ? 1 : 0;

        if ($id_cliente <= 0 || $id_aviso <= 0) {
            return 0;
        }

        $tipoNotif = 'aviso_requerido_pld';
        $mensaje = sprintf(
            'Aviso PLD requerido (%s). Monto: $%s MXN. Deadline: %s. XML: %s.',
            $tipo_aviso,
            number_format($monto, 2, '.', ','),
            $fecha_deadline ?: 'N/D',
            $xml_generado ? 'GENERADO' : 'PENDIENTE'
        );

        $usuarios = pldObtenerUsuariosNotificacion($pdo, $id_cliente);
        if (empty($usuarios)) {
            return 0;
        }

        $tieneIdAviso = function_exists('pldColumnExists') && pldColumnExists($pdo, 'notificaciones', 'id_aviso');
        $tieneIdOperacion = function_exists('pldColumnExists') && pldColumnExists($pdo, 'notificaciones', 'id_operacion');

        $generadas = 0;
        foreach ($usuarios as $id_usuario) {
            $id_usuario = (int)$id_usuario;
            if ($id_usuario <= 0) continue;

            if ($tieneIdAviso) {
                $stmtEx = $pdo->prepare("
                    SELECT 1
                    FROM notificaciones
                    WHERE id_usuario = ?
                      AND id_aviso = ?
                      AND tipo = ?
                      AND estado != 'descartado'
                      AND fecha_generacion > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    LIMIT 1
                ");
                $stmtEx->execute([$id_usuario, $id_aviso, $tipoNotif]);
            } else {
                $stmtEx = $pdo->prepare("
                    SELECT 1
                    FROM notificaciones
                    WHERE id_usuario = ?
                      AND tipo = ?
                      AND mensaje = ?
                      AND estado != 'descartado'
                      AND fecha_generacion > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    LIMIT 1
                ");
                $stmtEx->execute([$id_usuario, $tipoNotif, $mensaje]);
            }
            if ($stmtEx->fetch()) {
                continue;
            }

            $cols = ['id_usuario', 'id_cliente', 'tipo', 'mensaje'];
            $vals = [$id_usuario, $id_cliente, $tipoNotif, $mensaje];
            if ($tieneIdAviso) {
                $cols[] = 'id_aviso';
                $vals[] = $id_aviso;
            }
            if ($tieneIdOperacion) {
                $cols[] = 'id_operacion';
                $vals[] = $id_operacion > 0 ? $id_operacion : null;
            }

            $sql = "INSERT INTO notificaciones (" . implode(', ', $cols) . ")
                    VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")";
            $stmtIns = $pdo->prepare($sql);
            $stmtIns->execute($vals);
            $generadas++;
        }

        return $generadas;
    }
}

if (!function_exists('pldBuscarAvisoAcumulacionExistente')) {
    /**
     * Busca aviso de acumulación existente para la misma ventana/fracción/tipo de acto.
     */
    function pldBuscarAvisoAcumulacionExistente($pdo, $id_cliente, $fecha_primera_operacion, $id_fraccion = null, $tipo_acto = null) {
        if (!$id_cliente || !$fecha_primera_operacion) {
            return null;
        }

        $sql = "SELECT id_aviso
                FROM avisos_pld
                WHERE id_cliente = ?
                  AND tipo_aviso = 'acumulacion'
                  AND id_status = 1
                  AND fecha_operacion = ?";
        $params = [(int)$id_cliente, $fecha_primera_operacion];

        $tieneDatos = function_exists('pldColumnExists') && pldColumnExists($pdo, 'avisos_pld', 'datos_adicionales');
        if ($tieneDatos) {
            if ($id_fraccion !== null && $id_fraccion !== '') {
                $sql .= " AND CAST(JSON_UNQUOTE(JSON_EXTRACT(datos_adicionales, '$.id_fraccion')) AS SIGNED) = ?";
                $params[] = (int)$id_fraccion;
            }
            if ($tipo_acto !== null && trim((string)$tipo_acto) !== '') {
                $sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(datos_adicionales, '$.tipo_acto')) = ?";
                $params[] = trim((string)$tipo_acto);
            }
        } elseif (($id_fraccion !== null && $id_fraccion !== '') || ($tipo_acto !== null && trim((string)$tipo_acto) !== '')) {
            // Sin metadatos no es posible diferenciar con seguridad fracción/tipo de acto.
            return null;
        }

        $sql .= " ORDER BY id_aviso DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['id_aviso'] : null;
    }
}

if (!function_exists('registrarAvisoAcumulacion')) {
    /**
     * Upsert de acumulación en tabla operaciones_pld_acumulacion.
     */
    function registrarAvisoAcumulacion($pdo, $data) {
        if (!function_exists('pldTableExists') || !pldTableExists($pdo, 'operaciones_pld_acumulacion')) {
            return null;
        }

        $id_cliente = isset($data['id_cliente']) ? (int)$data['id_cliente'] : 0;
        if ($id_cliente <= 0) return null;

        $id_fraccion = $data['id_fraccion'] ?? null;
        $tipo_acto = $data['tipo_acto'] ?? null;
        $fecha_primera = $data['fecha_primera_operacion'] ?? date('Y-m-d');
        $fecha_ultima = $data['fecha_ultima_operacion'] ?? $fecha_primera;
        $monto_acumulado = isset($data['monto_acumulado']) ? (float)$data['monto_acumulado'] : 0.0;
        $monto_acumulado_uma = isset($data['monto_acumulado_uma']) ? (float)$data['monto_acumulado_uma'] : null;
        $cantidad_operaciones = isset($data['cantidad_operaciones']) ? (int)$data['cantidad_operaciones'] : 1;
        $id_aviso_generado = isset($data['id_aviso_generado']) ? (int)$data['id_aviso_generado'] : null;
        $fecha_deadline_aviso = $data['fecha_deadline_aviso'] ?? calcularDeadlineAviso($fecha_primera);

        $stmtExist = $pdo->prepare("
            SELECT id_acumulacion
            FROM operaciones_pld_acumulacion
            WHERE id_cliente = ?
              AND (id_fraccion <=> ?)
              AND (tipo_acto <=> ?)
              AND fecha_primera_operacion = ?
              AND id_status = 1
            ORDER BY id_acumulacion DESC
            LIMIT 1
        ");
        $stmtExist->execute([$id_cliente, $id_fraccion, $tipo_acto, $fecha_primera]);
        $exist = $stmtExist->fetch(PDO::FETCH_ASSOC);

        if ($exist) {
            $id_acumulacion = (int)$exist['id_acumulacion'];
            $stmtUpd = $pdo->prepare("
                UPDATE operaciones_pld_acumulacion
                SET fecha_ultima_operacion = ?,
                    monto_acumulado = ?,
                    monto_acumulado_uma = ?,
                    cantidad_operaciones = ?,
                    requiere_aviso = 1,
                    fecha_deadline_aviso = ?,
                    id_aviso_generado = ?
                WHERE id_acumulacion = ?
            ");
            $stmtUpd->execute([
                $fecha_ultima, $monto_acumulado, $monto_acumulado_uma, $cantidad_operaciones,
                $fecha_deadline_aviso, $id_aviso_generado, $id_acumulacion
            ]);
            return $id_acumulacion;
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO operaciones_pld_acumulacion
            (id_cliente, id_fraccion, tipo_acto, fecha_primera_operacion, fecha_ultima_operacion,
             monto_acumulado, monto_acumulado_uma, cantidad_operaciones, requiere_aviso,
             fecha_deadline_aviso, id_aviso_generado, id_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 1)
        ");
        $stmtIns->execute([
            $id_cliente, $id_fraccion, $tipo_acto, $fecha_primera, $fecha_ultima,
            $monto_acumulado, $monto_acumulado_uma, $cantidad_operaciones,
            $fecha_deadline_aviso, $id_aviso_generado
        ]);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('vincularOperacionAvisoPLD')) {
    /**
     * Vincula aviso con operación para trazabilidad.
     */
    function vincularOperacionAvisoPLD($pdo, $id_aviso, $id_operacion, $id_cliente = null, $tipo_relacion = 'operacion') {
        if (!function_exists('pldTableExists') || !pldTableExists($pdo, 'aviso_transacciones')) {
            return false;
        }
        if ((int)$id_aviso <= 0 || (int)$id_operacion <= 0) {
            return false;
        }

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO aviso_transacciones
            (id_aviso, id_operacion, id_cliente, tipo_relacion, id_status)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([(int)$id_aviso, (int)$id_operacion, $id_cliente ? (int)$id_cliente : null, $tipo_relacion]);
        return true;
    }
}

if (!function_exists('vincularVentanaAcumulacionAvisoPLD')) {
    /**
     * Vincula todas las transacciones de la ventana de 6 meses al aviso de acumulación.
     */
    function vincularVentanaAcumulacionAvisoPLD($pdo, $id_aviso, $id_cliente, $fecha_inicio, $fecha_fin, $id_fraccion = null, $tipo_acto = null) {
        if (!function_exists('pldTableExists') || !pldTableExists($pdo, 'aviso_transacciones')) {
            return 0;
        }

        $sql = "SELECT id_operacion
                FROM operaciones_pld
                WHERE id_cliente = ?
                  AND fecha_operacion >= ?
                  AND fecha_operacion <= ?
                  AND id_status = 1";
        $params = [(int)$id_cliente, $fecha_inicio, $fecha_fin];
        if ($id_fraccion !== null && $id_fraccion !== '') {
            $sql .= " AND id_fraccion = ?";
            $params[] = (int)$id_fraccion;
        }
        if ($tipo_acto !== null && trim((string)$tipo_acto) !== '') {
            $sql .= " AND tipo_operacion = ?";
            $params[] = trim((string)$tipo_acto);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ops = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($ops as $op) {
            if (vincularOperacionAvisoPLD($pdo, $id_aviso, (int)$op['id_operacion'], (int)$id_cliente, 'acumulacion')) {
                $count++;
            }
        }
        return $count;
    }
}

if (!function_exists('registrarOperacionPLD')) {
    
    /**
     * Registra una transacción PLD y valida si requiere aviso
     * VAL-PLD-008, VAL-PLD-009
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param array $data Datos de la operación
     * @return array Resultado de la operación
     */
    function registrarOperacionPLD($pdo, $data) {
        try {
            $id_cliente = $data['id_cliente'] ?? null;
            $monto = floatval($data['monto'] ?? 0);
            $fecha_operacion = $data['fecha_operacion'] ?? date('Y-m-d');
            $id_fraccion = $data['id_fraccion'] ?? null;
            $tipo_operacion = $data['tipo_operacion'] ?? null;
            $es_sospechosa = $data['es_sospechosa'] ?? 0;
            $fecha_conocimiento_sospecha = $data['fecha_conocimiento_sospecha'] ?? null;
            $match_listas_restringidas = $data['match_listas_restringidas'] ?? 0;
            $fecha_conocimiento_match = $data['fecha_conocimiento_match'] ?? null;
            
            if (!$id_cliente || $monto <= 0) {
                return [
                    'success' => false,
                    'message' => 'Datos incompletos: id_cliente y monto son requeridos'
                ];
            }
            
            // Obtener valor UMA para calcular monto_uma
            $stmt = $pdo->query("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1");
            $uma = $stmt->fetch(PDO::FETCH_ASSOC);
            $valorUMA = $uma ? floatval($uma['valor']) : 100.0;
            $montoUMA = $monto / $valorUMA;
            
            // Validar umbral individual (VAL-PLD-008)
            $validacionUmbral = validateAvisoUmbralIndividual($pdo, $id_cliente, $monto, $id_fraccion, $fecha_operacion);
            $requiere_aviso = $validacionUmbral['requiere_aviso'] ?? false;
            $tipo_aviso = null;
            $fecha_deadline_aviso = null;
            
            if ($requiere_aviso) {
                $tipo_aviso = 'umbral_individual';
                $fecha_deadline_aviso = $validacionUmbral['fecha_deadline'] ?? null;
            }
            
            // Validar acumulación (VAL-PLD-009)
            // IMPORTANTE: La acumulación se valida independientemente del umbral individual
            // Puede haber acumulación aunque ninguna operación individual rebase el umbral
            $validacionAcumulacion = validateAvisoAcumulacion($pdo, $id_cliente, $monto, $fecha_operacion, $id_fraccion, $tipo_operacion);
            if ($validacionAcumulacion['requiere_aviso'] ?? false) {
                // Si ya hay un aviso por umbral individual, mantenerlo
                // Si no, crear aviso por acumulación
                if (!$requiere_aviso) {
                    $requiere_aviso = true;
                    $tipo_aviso = 'acumulacion';
                    $fecha_deadline_aviso = $validacionAcumulacion['fecha_deadline'] ?? null;
                } else {
                    // Si ya hay aviso por umbral individual, el acumulación tiene prioridad
                    // porque puede incluir más operaciones
                    $tipo_aviso = 'acumulacion';
                    $fecha_deadline_aviso = $validacionAcumulacion['fecha_deadline'] ?? null;
                }
            }
            
            // Validar transacción sospechosa (VAL-PLD-010)
            // IMPORTANTE: Los avisos 24H tienen prioridad sobre otros tipos
            if ($es_sospechosa) {
                $validacionSospechosa = validateAvisoSospechosa($pdo, $id_cliente, $fecha_conocimiento_sospecha);
                if ($validacionSospechosa['requiere_aviso'] ?? false) {
                    $requiere_aviso = true;
                    // Usar 'sospechosa' que existe en el ENUM (o actualizar el ENUM para incluir 'sospechosa_24h')
                    $tipo_aviso = 'sospechosa'; // Aviso 24H - Nota: El ENUM debe incluir 'sospechosa_24h' para mayor especificidad
                    $fecha_deadline_aviso = $validacionSospechosa['fecha_deadline'] ?? null;
                }
            }
            
            // Validar listas restringidas (VAL-PLD-011)
            // IMPORTANTE: Los avisos 24H tienen prioridad sobre otros tipos
            if ($match_listas_restringidas) {
                $validacionListas = validateAvisoListasRestringidas($pdo, $id_cliente, $fecha_conocimiento_match);
                if ($validacionListas['requiere_aviso'] ?? false) {
                    $requiere_aviso = true;
                    // Usar 'listas_restringidas' que existe en el ENUM (o actualizar el ENUM para incluir 'listas_restringidas_24h')
                    $tipo_aviso = 'listas_restringidas'; // Aviso 24H - Nota: El ENUM debe incluir 'listas_restringidas_24h' para mayor especificidad
                    $fecha_deadline_aviso = $validacionListas['fecha_deadline'] ?? null;
                }
            }
            
            $kycSnapshotJson = null;
            $snapshotDisponible = false;
            if (function_exists('pldColumnExists')) {
                $snapshotDisponible = pldColumnExists($pdo, 'operaciones_pld', 'kyc_snapshot_json');
            }
            if ($snapshotDisponible && function_exists('pldGetClienteKycData')) {
                $kycSnapshot = pldGetClienteKycData($pdo, (int)$id_cliente);
                if (is_array($kycSnapshot)) {
                    $kycSnapshot['capturado_en'] = date('Y-m-d H:i:s');
                    $kycSnapshotJson = json_encode($kycSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            // Insertar operación
            if ($snapshotDisponible) {
                $stmt = $pdo->prepare("INSERT INTO operaciones_pld
                                       (id_cliente, id_fraccion, tipo_operacion, monto, monto_uma, fecha_operacion,
                                        es_sospechosa, fecha_conocimiento_sospecha, match_listas_restringidas,
                                        fecha_conocimiento_match, requiere_aviso, tipo_aviso, fecha_deadline_aviso,
                                        kyc_snapshot_json, id_status)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([
                    $id_cliente, $id_fraccion, $tipo_operacion, $monto, $montoUMA, $fecha_operacion,
                    $es_sospechosa, $fecha_conocimiento_sospecha, $match_listas_restringidas,
                    $fecha_conocimiento_match, $requiere_aviso ? 1 : 0, $tipo_aviso, $fecha_deadline_aviso,
                    $kycSnapshotJson
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO operaciones_pld
                                       (id_cliente, id_fraccion, tipo_operacion, monto, monto_uma, fecha_operacion,
                                        es_sospechosa, fecha_conocimiento_sospecha, match_listas_restringidas,
                                        fecha_conocimiento_match, requiere_aviso, tipo_aviso, fecha_deadline_aviso, id_status)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([
                    $id_cliente, $id_fraccion, $tipo_operacion, $monto, $montoUMA, $fecha_operacion,
                    $es_sospechosa, $fecha_conocimiento_sospecha, $match_listas_restringidas,
                    $fecha_conocimiento_match, $requiere_aviso ? 1 : 0, $tipo_aviso, $fecha_deadline_aviso
                ]);
            }
            
            $id_operacion = $pdo->lastInsertId();
            
            // Si requiere aviso, generar/actualizar registro de aviso pendiente
            $id_aviso = null;
            if ($requiere_aviso) {
                // Para acumulación, usar monto acumulado en lugar del monto individual
                $montoAviso = ($tipo_aviso === 'acumulacion' && isset($validacionAcumulacion['monto_acumulado'])) 
                    ? $validacionAcumulacion['monto_acumulado'] 
                    : $monto;
                
                // Para avisos 24H, usar el tipo específico en avisos_pld (que sí tiene los valores 24h)
                $tipoAvisoParaRegistro = $tipo_aviso;
                if ($es_sospechosa && $tipo_aviso === 'sospechosa') {
                    $tipoAvisoParaRegistro = 'sospechosa_24h';
                } elseif ($match_listas_restringidas && $tipo_aviso === 'listas_restringidas') {
                    $tipoAvisoParaRegistro = 'listas_restringidas_24h';
                }

                $fechaOperacionAviso = $fecha_operacion;
                $datosAdicionalesAviso = null;

                if ($tipo_aviso === 'acumulacion') {
                    $fechaOperacionAviso = $validacionAcumulacion['fecha_primera_operacion'] ?? $fecha_operacion;
                    $datosAdicionalesAviso = [
                        'cantidad_operaciones' => $validacionAcumulacion['cantidad_operaciones'] ?? 1,
                        'fecha_primera_operacion' => $validacionAcumulacion['fecha_primera_operacion'] ?? $fecha_operacion,
                        'fecha_ultima_operacion' => $fecha_operacion,
                        'fecha_inicio_ventana' => $validacionAcumulacion['fecha_inicio_ventana'] ?? null,
                        'monto_acumulado_uma' => $validacionAcumulacion['monto_acumulado_uma'] ?? null,
                        'id_fraccion' => $id_fraccion ? (int)$id_fraccion : null,
                        'tipo_acto' => $tipo_operacion ?: null
                    ];

                    // Evitar duplicar avisos de acumulación para la misma ventana.
                    $id_aviso_existente = pldBuscarAvisoAcumulacionExistente(
                        $pdo,
                        $id_cliente,
                        $fechaOperacionAviso,
                        $id_fraccion,
                        $tipo_operacion
                    );

                    if ($id_aviso_existente) {
                        $id_aviso = $id_aviso_existente;
                        if (function_exists('pldColumnExists') && pldColumnExists($pdo, 'avisos_pld', 'datos_adicionales')) {
                            $stmtUpdAviso = $pdo->prepare("
                                UPDATE avisos_pld
                                SET monto = ?,
                                    fecha_deadline = ?,
                                    datos_adicionales = ?,
                                    estatus = CASE
                                        WHEN estatus IN ('cancelado', 'presentado') THEN estatus
                                        ELSE 'generado'
                                    END
                                WHERE id_aviso = ?
                            ");
                            $stmtUpdAviso->execute([
                                $montoAviso,
                                $fecha_deadline_aviso,
                                json_encode($datosAdicionalesAviso, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                $id_aviso
                            ]);
                        } else {
                            $stmtUpdAviso = $pdo->prepare("
                                UPDATE avisos_pld
                                SET monto = ?,
                                    fecha_deadline = ?,
                                    estatus = CASE
                                        WHEN estatus IN ('cancelado', 'presentado') THEN estatus
                                        ELSE 'generado'
                                    END
                                WHERE id_aviso = ?
                            ");
                            $stmtUpdAviso->execute([$montoAviso, $fecha_deadline_aviso, $id_aviso]);
                        }
                    }
                }

                if (!$id_aviso) {
                    $id_aviso = registrarAvisoPLD($pdo, [
                        'id_cliente' => $id_cliente,
                        'id_operacion' => $id_operacion,
                        'tipo_aviso' => $tipoAvisoParaRegistro,
                        'fecha_operacion' => $fechaOperacionAviso,
                        'fecha_conocimiento' => $es_sospechosa ? $fecha_conocimiento_sospecha : ($match_listas_restringidas ? $fecha_conocimiento_match : null),
                        'monto' => $montoAviso,
                        'fecha_deadline' => $fecha_deadline_aviso,
                        'datos_adicionales' => $datosAdicionalesAviso
                    ]);
                }

                // Actualizar transacción con id_aviso
                $stmt = $pdo->prepare("UPDATE operaciones_pld SET id_aviso_generado = ? WHERE id_operacion = ?");
                $stmt->execute([$id_aviso, $id_operacion]);

                // Trazabilidad aviso-transacción.
                vincularOperacionAvisoPLD($pdo, $id_aviso, $id_operacion, $id_cliente, $tipo_aviso === 'acumulacion' ? 'acumulacion' : 'operacion');
                
                // VAL-PLD-013: Registrar automáticamente para conservación
                if (function_exists('registrarConservacionInformacion')) {
                    require_once __DIR__ . '/pld_conservacion.php';
                    $rutaEvidencia = null;
                    // Si hay documentos asociados a la operación, usar la ruta del primero
                    // Por ahora, se puede registrar manualmente desde la UI
                }
                
                // Si es acumulación, registrar en tabla de acumulaciones
                if ($tipo_aviso === 'acumulacion' && isset($validacionAcumulacion)) {
                    registrarAvisoAcumulacion($pdo, [
                        'id_cliente' => $id_cliente,
                        'id_fraccion' => $id_fraccion,
                        'tipo_acto' => $tipo_operacion,
                        'fecha_primera_operacion' => $validacionAcumulacion['fecha_primera_operacion'] ?? $fecha_operacion,
                        'fecha_ultima_operacion' => $fecha_operacion,
                        'monto_acumulado' => $validacionAcumulacion['monto_acumulado'] ?? $monto,
                        'monto_acumulado_uma' => $validacionAcumulacion['monto_acumulado_uma'] ?? null,
                        'cantidad_operaciones' => $validacionAcumulacion['cantidad_operaciones'] ?? 1,
                        'id_aviso_generado' => $id_aviso,
                        'fecha_deadline_aviso' => $fecha_deadline_aviso
                    ]);

                    // En acumulación, vincular todas las transacciones de la ventana al aviso.
                    vincularVentanaAcumulacionAvisoPLD(
                        $pdo,
                        $id_aviso,
                        $id_cliente,
                        $validacionAcumulacion['fecha_inicio_ventana'] ?? $fecha_operacion,
                        $fecha_operacion,
                        $id_fraccion,
                        $tipo_operacion
                    );
                }

                // Notificación inmediata al detectar aviso.
                pldRegistrarNotificacionAvisoRequerido($pdo, [
                    'id_cliente' => $id_cliente,
                    'id_aviso' => $id_aviso,
                    'id_operacion' => $id_operacion,
                    'tipo_aviso' => $tipoAvisoParaRegistro,
                    'fecha_deadline' => $fecha_deadline_aviso,
                    'monto' => $montoAviso,
                    'xml_generado' => false
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Transacción registrada correctamente',
                'id_operacion' => $id_operacion,
                'id_aviso' => $id_aviso,
                'requiere_aviso' => $requiere_aviso,
                'tipo_aviso' => $tipo_aviso,
                'fecha_deadline' => $fecha_deadline_aviso,
                'validacion_umbral' => $validacionUmbral,
                'validacion_acumulacion' => $validacionAcumulacion
            ];
            
        } catch (Exception $e) {
            error_log("Error en registrarOperacionPLD: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al registrar transacción: ' . $e->getMessage()
            ];
        }
    }
}

if (!function_exists('registrarAvisoPLD')) {
    
    /**
     * Registra un aviso PLD pendiente
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param array $data Datos del aviso
     * @return int ID del aviso generado
     */
    function registrarAvisoPLD($pdo, $data) {
        try {
            $id_cliente = $data['id_cliente'] ?? null;
            $tipo_aviso = $data['tipo_aviso'] ?? null;
            $fecha_operacion = $data['fecha_operacion'] ?? date('Y-m-d');
            $fecha_conocimiento = $data['fecha_conocimiento'] ?? null;
            $monto = $data['monto'] ?? null;
            $fecha_deadline = $data['fecha_deadline'] ?? null;
            $id_operacion = $data['id_operacion'] ?? null;
            $datos_adicionales = $data['datos_adicionales'] ?? null;
            
            if (!$id_cliente || !$tipo_aviso) {
                throw new Exception('Datos incompletos: id_cliente y tipo_aviso son requeridos');
            }
            
            // Si no hay deadline, calcularlo
            if (!$fecha_deadline) {
                $fecha_deadline = calcularDeadlineAviso($fecha_operacion);
            }

            $hasDatosAdicionales = function_exists('pldColumnExists') && pldColumnExists($pdo, 'avisos_pld', 'datos_adicionales');
            $hasIdOperacion = function_exists('pldColumnExists') && pldColumnExists($pdo, 'avisos_pld', 'id_operacion');

            $columns = ['id_cliente', 'tipo_aviso', 'fecha_operacion', 'fecha_conocimiento', 'monto', 'fecha_deadline', 'estatus', 'id_status'];
            $params = [$id_cliente, $tipo_aviso, $fecha_operacion, $fecha_conocimiento, $monto, $fecha_deadline, 'pendiente', 1];

            if ($hasDatosAdicionales) {
                $columns[] = 'datos_adicionales';
                if (is_array($datos_adicionales)) {
                    $params[] = json_encode($datos_adicionales, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $params[] = $datos_adicionales;
                }
            }

            if ($hasIdOperacion) {
                $columns[] = 'id_operacion';
                $params[] = $id_operacion ? (int)$id_operacion : null;
            }

            $sql = "INSERT INTO avisos_pld (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $id_aviso = $pdo->lastInsertId();
            
            // VAL-PLD-013: Preparación para registro automático de conservación
            // Nota: El registro automático requiere archivo físico, por lo que se recomienda
            // registrar manualmente desde conservacion_pld.php cuando se tenga el archivo
            
            return $id_aviso;
            
        } catch (Exception $e) {
            error_log("Error en registrarAvisoPLD: " . $e->getMessage());
            throw $e;
        }
    }
}

if (!function_exists('requireAvisoUmbralIndividual')) {
    
    /**
     * Bloquea operación si requiere aviso por umbral individual y no está registrado
     * VAL-PLD-008
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param float $monto Monto de la operación
     * @param int|null $id_fraccion ID de la fracción
     * @param bool $returnJson Si es true, retorna JSON. Si es false, lanza excepción
     * @return array|null Retorna array con error si returnJson es true
     * @throws Exception Si returnJson es false y requiere aviso
     */
    function requireAvisoUmbralIndividual($pdo, $id_cliente, $monto, $id_fraccion = null, $returnJson = true) {
        $result = validateAvisoUmbralIndividual($pdo, $id_cliente, $monto, $id_fraccion);
        
        if ($result['requiere_aviso'] ?? false) {
            if ($returnJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'code' => 'AVISO_REQUERIDO',
                    'message' => 'Transacción requiere aviso por umbral individual',
                    'detalles' => $result
                ]);
                exit;
            } else {
                throw new Exception('AVISO_REQUERIDO: Transacción requiere aviso por umbral individual');
            }
        }
        
        return null;
    }
}

if (!function_exists('requireAvisoAcumulacion')) {
    
    /**
     * Bloquea operación si requiere aviso por acumulación y no está registrado
     * VAL-PLD-009
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param int $id_cliente ID del cliente
     * @param float $monto Monto de la nueva operación
     * @param string $fechaOperacion Fecha de la operación
     * @param int|null $id_fraccion ID de la fracción
     * @param string|null $tipoActo Tipo de acto
     * @param bool $returnJson Si es true, retorna JSON. Si es false, lanza excepción
     * @return array|null Retorna array con error si returnJson es true
     * @throws Exception Si returnJson es false y requiere aviso
     */
    function requireAvisoAcumulacion($pdo, $id_cliente, $monto, $fechaOperacion, $id_fraccion = null, $tipoActo = null, $returnJson = true) {
        $result = validateAvisoAcumulacion($pdo, $id_cliente, $monto, $fechaOperacion, $id_fraccion, $tipoActo);
        
        if ($result['requiere_aviso'] ?? false) {
            if ($returnJson) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'code' => 'GENERAR_AVISO',
                    'message' => 'Transacción requiere aviso por acumulación (ventana móvil 6 meses)',
                    'detalles' => $result
                ]);
                exit;
            } else {
                throw new Exception('GENERAR_AVISO: Transacción requiere aviso por acumulación');
            }
        }
        
        return null;
    }
}

