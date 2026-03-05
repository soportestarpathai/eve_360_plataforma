<?php
/**
 * PLD Validation - VAL-PLD-001
 * Validación de Alta en el Padrón PLD del SAT
 * 
 * Verifica que el sujeto obligado esté:
 * - Dado de alta en el Portal PLD del SAT
 * - Con estatus vigente
 * - Con fracciones activas registradas
 */

if (!function_exists('validatePatronPLD')) {
    
    /**
     * Valida el padrón PLD del sujeto obligado
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param string|null $fraccionRequerida Si se indica (ej: 'XIII' para Donativos), valida además que esa fracción esté activa
     * @return array Resultado de la validación
     */
    function validatePatronPLD($pdo, $fraccionRequerida = null, $id_usuario = 0) {
        try {
            $config = null;
            if ($id_usuario > 0) {
                $stmtU = $pdo->prepare("SELECT folio_patron_pld, estatus_patron_pld, fracciones_activas, fecha_revalidacion_patron, no_habilitado_pld FROM config_empresa_usuario WHERE id_usuario = ?");
                $stmtU->execute([$id_usuario]);
                $configU = $stmtU->fetch(PDO::FETCH_ASSOC);
                if ($configU) {
                    $stmtG = $pdo->query("SELECT * FROM config_empresa WHERE id_config = 1");
                    $config = $stmtG->fetch(PDO::FETCH_ASSOC) ?: [];
                    foreach (['folio_patron_pld','estatus_patron_pld','fracciones_activas','fecha_revalidacion_patron','no_habilitado_pld'] as $k) {
                        if (isset($configU[$k])) $config[$k] = $configU[$k];
                    }
                }
            }
            if (!$config) {
                $stmt = $pdo->query("SELECT * FROM config_empresa WHERE id_config = 1");
                $config = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if (!$config) {
                return [
                    'habilitado' => false,
                    'estatus' => 'NO_HABILITADO_PLD',
                    'razon' => 'Configuración del sujeto obligado no encontrada',
                    'detalles' => []
                ];
            }
            
            $folioPatron = $config['folio_patron_pld'] ?? null;
            $estatusPatron = $config['estatus_patron_pld'] ?? null;
            $fraccionesActivas = $config['fracciones_activas'] ?? null;
            
            // Validación 1: Existe registro en el padrón (folio)
            if (empty($folioPatron)) {
                return [
                    'habilitado' => false,
                    'estatus' => 'NO_HABILITADO_PLD',
                    'razon' => 'No existe folio de registro en el padrón PLD',
                    'detalles' => [
                        'folio_registrado' => false,
                        'estatus_vigente' => false,
                        'fracciones_activas' => false
                    ]
                ];
            }
            
            // Validación 2: Estatus vigente
            $estatusVigente = ($estatusPatron === 'vigente' || $estatusPatron === 'VIGENTE');
            
            if (!$estatusVigente) {
                return [
                    'habilitado' => false,
                    'estatus' => 'NO_HABILITADO_PLD',
                    'razon' => 'El sujeto obligado no tiene estatus vigente en el padrón PLD',
                    'detalles' => [
                        'folio_registrado' => true,
                        'estatus_vigente' => false,
                        'estatus_actual' => $estatusPatron,
                        'fracciones_activas' => false
                    ]
                ];
            }
            
            // Validación 3: Fracciones activas
            $fraccionesArray = [];
            if (!empty($fraccionesActivas)) {
                $fraccionesArray = json_decode($fraccionesActivas, true);
                if (!is_array($fraccionesArray)) {
                    $fraccionesArray = [];
                }
            }
            
            if (empty($fraccionesArray)) {
                return [
                    'habilitado' => false,
                    'estatus' => 'NO_HABILITADO_PLD',
                    'razon' => 'No hay fracciones activas registradas en el padrón PLD',
                    'detalles' => [
                        'folio_registrado' => true,
                        'estatus_vigente' => true,
                        'fracciones_activas' => false,
                        'fracciones' => []
                    ]
                ];
            }
            
            // Validación opcional: fracción específica activa (ej. Fracción XIII para Donativos)
            if ($fraccionRequerida !== null && $fraccionRequerida !== '') {
                $fraccionNormalizada = strtoupper(trim($fraccionRequerida));
                $tieneFraccion = false;
                foreach ($fraccionesArray as $f) {
                    if (strtoupper(trim((string) $f)) === $fraccionNormalizada) {
                        $tieneFraccion = true;
                        break;
                    }
                }
                if (!$tieneFraccion) {
                    return [
                        'habilitado' => false,
                        'estatus' => 'NO_HABILITADO_PLD',
                        'razon' => 'La fracción ' . $fraccionRequerida . ' no está activa en el padrón PLD',
                        'detalles' => [
                            'folio_registrado' => true,
                            'estatus_vigente' => true,
                            'fracciones_activas' => true,
                            'fraccion_requerida' => $fraccionRequerida,
                            'fraccion_activa' => false,
                            'fracciones' => $fraccionesArray
                        ]
                    ];
                }
            }
            
            // Todas las validaciones pasaron
            return [
                'habilitado' => true,
                'estatus' => 'HABILITADO_PLD',
                'razon' => 'Sujeto obligado habilitado para operar PLD',
                'detalles' => [
                    'folio_registrado' => true,
                    'estatus_vigente' => true,
                    'fracciones_activas' => true,
                    'folio' => $folioPatron,
                    'estatus' => $estatusPatron,
                    'fracciones' => $fraccionesArray,
                    'fecha_revalidacion' => $config['fecha_revalidacion_patron'] ?? null
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'habilitado' => false,
                'estatus' => 'NO_HABILITADO_PLD',
                'razon' => 'Error al validar padrón PLD: ' . $e->getMessage(),
                'detalles' => []
            ];
        }
    }
    
    /**
     * Verifica si el sujeto obligado está habilitado para operar PLD
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @return bool True si está habilitado, false en caso contrario
     */
    function checkHabilitadoPLD($pdo, $id_usuario = null) {
        if ($id_usuario === null && !empty($_SESSION['user_id'])) {
            $id_usuario = (int)$_SESSION['user_id'];
        }
        $id_usuario = $id_usuario ?? 0;
        $result = validatePatronPLD($pdo, null, $id_usuario);
        return $result['habilitado'] === true;
    }
    
    /**
     * Actualiza el flag NO_HABILITADO_PLD en la base de datos
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param bool $habilitado Estado de habilitación
     * @return bool True si se actualizó correctamente
     */
    function updateHabilitadoPLDFlag($pdo, $habilitado, $id_usuario = 0) {
        try {
            $flag = $habilitado ? 0 : 1; // 0 = habilitado, 1 = NO habilitado
            if ($id_usuario > 0) {
                $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, no_habilitado_pld) VALUES (?, ?) ON DUPLICATE KEY UPDATE no_habilitado_pld = VALUES(no_habilitado_pld)");
                $stmt->execute([$id_usuario, $flag]);
            } else {
                $stmt = $pdo->prepare("UPDATE config_empresa SET no_habilitado_pld = ? WHERE id_config = 1");
                $stmt->execute([$flag]);
            }
            return true;
        } catch (Exception $e) {
            error_log("Error updating NO_HABILITADO_PLD flag: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Revalida el padrón PLD y actualiza el flag
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @return array Resultado de la revalidación
     */
    function revalidatePatronPLD($pdo, $id_usuario = 0) {
        $result = validatePatronPLD($pdo, null, $id_usuario);
        updateHabilitadoPLDFlag($pdo, $result['habilitado'], $id_usuario);
        
        try {
            if ($id_usuario > 0) {
                $stmt = $pdo->prepare("UPDATE config_empresa_usuario SET fecha_revalidacion_patron = CURDATE() WHERE id_usuario = ?");
                $stmt->execute([$id_usuario]);
            } else {
                $stmt = $pdo->prepare("UPDATE config_empresa SET fecha_revalidacion_patron = CURDATE() WHERE id_config = 1");
                $stmt->execute();
            }
        } catch (Exception $e) {
            error_log("Error updating revalidation date: " . $e->getMessage());
        }
        
        return $result;
    }
}
