<?php
/**
 * PLD Revalidation - VAL-PLD-002
 * Revalidación Periódica de Alta en el Padrón PLD
 * 
 * Verifica cada 3 meses:
 * - Cambios de estatus
 * - Cambios de fracciones
 * - Necesidad de modificación
 * - Baja confirmada → bloqueo operativo
 */

require_once __DIR__ . '/pld_validation.php';
require_once __DIR__ . '/logger.php';

if (!function_exists('checkRevalidationDue')) {
    
    /**
     * Verifica si la revalidación está vencida o próxima a vencer
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @return array Información sobre el estado de revalidación
     */
    function checkRevalidationDue($pdo) {
        try {
            $stmt = $pdo->query("SELECT fecha_revalidacion_patron FROM config_empresa WHERE id_config = 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config || empty($config['fecha_revalidacion_patron'])) {
                return [
                    'vencida' => true,
                    'dias_restantes' => -1,
                    'mensaje' => 'Nunca se ha realizado una revalidación',
                    'requiere_revalidacion' => true
                ];
            }
            
            $fechaRevalidacion = new DateTime($config['fecha_revalidacion_patron']);
            $fechaActual = new DateTime();
            $diferencia = $fechaActual->diff($fechaRevalidacion);
            $diasTranscurridos = (int)$fechaActual->diff($fechaRevalidacion)->format('%a');
            
            // Si la fecha de revalidación es futura, calcular días restantes
            if ($fechaRevalidacion > $fechaActual) {
                $diasRestantes = $diasTranscurridos;
            } else {
                $diasRestantes = -$diasTranscurridos;
            }
            
            // 3 meses = 90 días (aproximado)
            $periodoRevalidacion = 90;
            $diasVencidos = $diasRestantes < 0 ? abs($diasRestantes) : 0;
            $vencida = $diasVencidos >= $periodoRevalidacion;
            $proxima = $diasRestantes <= 15 && $diasRestantes >= 0; // Próxima a vencer en 15 días
            
            return [
                'vencida' => $vencida,
                'dias_restantes' => $diasRestantes,
                'dias_vencidos' => $diasVencidos,
                'proxima_vencer' => $proxima,
                'requiere_revalidacion' => $vencida || $proxima,
                'mensaje' => $vencida 
                    ? "Revalidación vencida hace {$diasVencidos} días" 
                    : ($proxima 
                        ? "Revalidación próxima a vencer en {$diasRestantes} días"
                        : "Revalidación vigente, vence en {$diasRestantes} días")
            ];
            
        } catch (Exception $e) {
            return [
                'vencida' => true,
                'dias_restantes' => -1,
                'mensaje' => 'Error al verificar revalidación: ' . $e->getMessage(),
                'requiere_revalidacion' => true
            ];
        }
    }
    
    /**
     * Compara el estado actual del padrón con el almacenado
     * Detecta cambios de estatus o fracciones
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param array $nuevosDatos Datos nuevos del padrón (folio, estatus, fracciones)
     * @return array Resultado de la comparación
     */
    function comparePatronData($pdo, $nuevosDatos) {
        try {
            $stmt = $pdo->query("SELECT folio_patron_pld, estatus_patron_pld, fracciones_activas FROM config_empresa WHERE id_config = 1");
            $datosActuales = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$datosActuales) {
                return [
                    'hay_cambios' => true,
                    'cambios' => [],
                    'mensaje' => 'No hay datos actuales para comparar'
                ];
            }
            
            $cambios = [];
            $hayCambios = false;
            
            // Comparar folio
            if (isset($nuevosDatos['folio']) && $nuevosDatos['folio'] !== $datosActuales['folio_patron_pld']) {
                $cambios[] = [
                    'campo' => 'folio',
                    'anterior' => $datosActuales['folio_patron_pld'],
                    'nuevo' => $nuevosDatos['folio'],
                    'tipo' => 'MODIFICACION'
                ];
                $hayCambios = true;
            }
            
            // Comparar estatus
            if (isset($nuevosDatos['estatus'])) {
                $estatusAnterior = strtolower($datosActuales['estatus_patron_pld'] ?? '');
                $estatusNuevo = strtolower($nuevosDatos['estatus']);
                
                if ($estatusAnterior !== $estatusNuevo) {
                    $cambios[] = [
                        'campo' => 'estatus',
                        'anterior' => $datosActuales['estatus_patron_pld'],
                        'nuevo' => $nuevosDatos['estatus'],
                        'tipo' => $estatusNuevo === 'baja' ? 'BAJA' : 'MODIFICACION'
                    ];
                    $hayCambios = true;
                }
            }
            
            // Comparar fracciones
            if (isset($nuevosDatos['fracciones'])) {
                $fraccionesAnteriores = json_decode($datosActuales['fracciones_activas'] ?? '[]', true);
                $fraccionesNuevas = is_array($nuevosDatos['fracciones']) 
                    ? $nuevosDatos['fracciones'] 
                    : json_decode($nuevosDatos['fracciones'], true);
                
                sort($fraccionesAnteriores);
                sort($fraccionesNuevas);
                
                if ($fraccionesAnteriores !== $fraccionesNuevas) {
                    $cambios[] = [
                        'campo' => 'fracciones',
                        'anterior' => $fraccionesAnteriores,
                        'nuevo' => $fraccionesNuevas,
                        'tipo' => 'MODIFICACION'
                    ];
                    $hayCambios = true;
                }
            }
            
            return [
                'hay_cambios' => $hayCambios,
                'cambios' => $cambios,
                'mensaje' => $hayCambios 
                    ? 'Se detectaron cambios en el padrón' 
                    : 'No hay cambios detectados'
            ];
            
        } catch (Exception $e) {
            return [
                'hay_cambios' => true,
                'cambios' => [],
                'mensaje' => 'Error al comparar datos: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Procesa la revalidación con confirmación de cambios
     * 
     * @param PDO $pdo Conexión a la base de datos
     * @param array $nuevosDatos Datos nuevos del padrón
     * @param bool $confirmarCambios Si true, aplica los cambios
     * @return array Resultado de la revalidación
     */
    function processRevalidation($pdo, $nuevosDatos, $confirmarCambios = false) {
        try {
            $logger = Logger::getInstance();
            
            // Comparar datos
            $comparacion = comparePatronData($pdo, $nuevosDatos);
            
            if (!$comparacion['hay_cambios']) {
                // No hay cambios, solo actualizar fecha de revalidación
                if ($confirmarCambios) {
                    $stmt = $pdo->prepare("UPDATE config_empresa SET fecha_revalidacion_patron = CURDATE() WHERE id_config = 1");
                    $stmt->execute();
                }
                
                return [
                    'status' => 'success',
                    'mensaje' => 'Revalidación completada. No se detectaron cambios.',
                    'cambios' => []
                ];
            }
            
            // Hay cambios detectados
            $tieneBaja = false;
            foreach ($comparacion['cambios'] as $cambio) {
                if ($cambio['tipo'] === 'BAJA') {
                    $tieneBaja = true;
                    break;
                }
            }
            
            if ($confirmarCambios) {
                // Aplicar cambios
                $folio = $nuevosDatos['folio'] ?? null;
                $estatus = $nuevosDatos['estatus'] ?? null;
                $fracciones = isset($nuevosDatos['fracciones']) 
                    ? (is_array($nuevosDatos['fracciones']) 
                        ? json_encode($nuevosDatos['fracciones']) 
                        : $nuevosDatos['fracciones'])
                    : null;
                
                $updates = [];
                $params = [];
                
                if ($folio !== null) {
                    $updates[] = "folio_patron_pld = ?";
                    $params[] = $folio;
                }
                
                if ($estatus !== null) {
                    $updates[] = "estatus_patron_pld = ?";
                    $params[] = $estatus;
                }
                
                if ($fracciones !== null) {
                    $updates[] = "fracciones_activas = ?";
                    $params[] = $fracciones;
                }
                
                $updates[] = "fecha_revalidacion_patron = CURDATE()";
                
                if (!empty($updates)) {
                    $sql = "UPDATE config_empresa SET " . implode(", ", $updates) . " WHERE id_config = 1";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }
                
                // Si hay baja, bloquear operaciones
                if ($tieneBaja) {
                    updateHabilitadoPLDFlag($pdo, true); // true = NO habilitado
                    $logger->warning('PLD Revalidation: Baja detectada y confirmada. Operaciones bloqueadas.');
                } else {
                    // Revalidar habilitación
                    $result = validatePatronPLD($pdo);
                    updateHabilitadoPLDFlag($pdo, !$result['habilitado']);
                }
                
                $logger->info('PLD Revalidation: Cambios aplicados', ['cambios' => $comparacion['cambios']]);
                
                return [
                    'status' => 'success',
                    'mensaje' => $tieneBaja 
                        ? 'Revalidación completada. Baja confirmada. Operaciones bloqueadas.' 
                        : 'Revalidación completada. Cambios aplicados.',
                    'cambios' => $comparacion['cambios'],
                    'bloqueado' => $tieneBaja
                ];
            } else {
                // Solo mostrar cambios, no aplicar
                return [
                    'status' => 'pending_confirmation',
                    'mensaje' => 'Se detectaron cambios. Requiere confirmación para aplicar.',
                    'cambios' => $comparacion['cambios'],
                    'requiere_confirmacion' => true
                ];
            }
            
        } catch (Exception $e) {
            $logger = Logger::getInstance();
            $logger->error('PLD Revalidation Error', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'error',
                'mensaje' => 'Error al procesar revalidación: ' . $e->getMessage(),
                'cambios' => []
            ];
        }
    }
}
