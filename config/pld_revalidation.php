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
    function checkRevalidationDue($pdo, $id_usuario = 0) {
        try {
            $config = null;
            if ($id_usuario > 0) {
                $stmtU = $pdo->prepare("SELECT fecha_revalidacion_patron FROM config_empresa_usuario WHERE id_usuario = ?");
                $stmtU->execute([$id_usuario]);
                $config = $stmtU->fetch(PDO::FETCH_ASSOC);
            }
            if (!$config) {
                $stmt = $pdo->query("SELECT fecha_revalidacion_patron FROM config_empresa WHERE id_config = 1");
                $config = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
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
    function comparePatronData($pdo, $nuevosDatos, $id_usuario = 0) {
        try {
            $datosActuales = null;
            $tieneColSubfXI = false;
            $tieneColSubfII = false;
            $tieneColSubfXII = false;
            $tieneColSubfXIIFES = false;
            $tieneColSubfXIV = false;
            $tabla = ($id_usuario > 0) ? 'config_empresa_usuario' : 'config_empresa';
            try {
                $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tabla}' AND COLUMN_NAME = 'subfracciones_xi'");
                $tieneColSubfXI = $chk && $chk->fetchColumn() > 0;
            } catch (Exception $e) { }
            try {
                $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tabla}' AND COLUMN_NAME = 'subfracciones_ii'");
                $tieneColSubfII = $chk && $chk->fetchColumn() > 0;
            } catch (Exception $e) { }
            try {
                $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tabla}' AND COLUMN_NAME = 'subfracciones_xii'");
                $tieneColSubfXII = $chk && $chk->fetchColumn() > 0;
            } catch (Exception $e) { }
            try {
                $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tabla}' AND COLUMN_NAME = 'subfracciones_xii_fes'");
                $tieneColSubfXIIFES = $chk && $chk->fetchColumn() > 0;
            } catch (Exception $e) { }
            try {
                $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tabla}' AND COLUMN_NAME = 'subfracciones_xiv'");
                $tieneColSubfXIV = $chk && $chk->fetchColumn() > 0;
            } catch (Exception $e) { }
            $cols = 'folio_patron_pld, estatus_patron_pld, fracciones_activas'
                . ($tieneColSubfXI ? ', subfracciones_xi' : '')
                . ($tieneColSubfII ? ', subfracciones_ii' : '')
                . ($tieneColSubfXII ? ', subfracciones_xii' : '')
                . ($tieneColSubfXIIFES ? ', subfracciones_xii_fes' : '')
                . ($tieneColSubfXIV ? ', subfracciones_xiv' : '');
            if ($id_usuario > 0) {
                $stmtU = $pdo->prepare("SELECT {$cols} FROM config_empresa_usuario WHERE id_usuario = ?");
                $stmtU->execute([$id_usuario]);
                $datosActuales = $stmtU->fetch(PDO::FETCH_ASSOC);
                if (!$datosActuales) {
                    $tabla = 'config_empresa';
                    try {
                        $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_xi'");
                        $tieneColSubfXI = $chk && $chk->fetchColumn() > 0;
                    } catch (Exception $e) { }
                    try {
                        $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_ii'");
                        $tieneColSubfII = $chk && $chk->fetchColumn() > 0;
                    } catch (Exception $e) { }
                    try {
                        $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_xii'");
                        $tieneColSubfXII = $chk && $chk->fetchColumn() > 0;
                    } catch (Exception $e) { }
                    try {
                        $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_xii_fes'");
                        $tieneColSubfXIIFES = $chk && $chk->fetchColumn() > 0;
                    } catch (Exception $e) { }
                    try {
                        $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_xiv'");
                        $tieneColSubfXIV = $chk && $chk->fetchColumn() > 0;
                    } catch (Exception $e) { }
                    $cols = 'folio_patron_pld, estatus_patron_pld, fracciones_activas'
                        . ($tieneColSubfXI ? ', subfracciones_xi' : '')
                        . ($tieneColSubfII ? ', subfracciones_ii' : '')
                        . ($tieneColSubfXII ? ', subfracciones_xii' : '')
                        . ($tieneColSubfXIIFES ? ', subfracciones_xii_fes' : '')
                        . ($tieneColSubfXIV ? ', subfracciones_xiv' : '');
                }
            }
            if (!$datosActuales) {
                $stmt = $pdo->query("SELECT {$cols} FROM config_empresa WHERE id_config = 1");
                $datosActuales = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
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

            // Comparar subfracciones XI (solo si existe la columna)
            if ($tieneColSubfXI && isset($nuevosDatos['subfracciones_xi'])) {
                $subfAnteriores = json_decode($datosActuales['subfracciones_xi'] ?? '[]', true);
                $subfNuevas = is_array($nuevosDatos['subfracciones_xi'])
                    ? $nuevosDatos['subfracciones_xi']
                    : json_decode($nuevosDatos['subfracciones_xi'] ?? '[]', true);
                $subfAnteriores = is_array($subfAnteriores) ? $subfAnteriores : [];
                $subfNuevas = is_array($subfNuevas) ? $subfNuevas : [];
                sort($subfAnteriores);
                sort($subfNuevas);
                if ($subfAnteriores !== $subfNuevas) {
                    $cambios[] = [
                        'campo' => 'subfracciones_xi',
                        'anterior' => $subfAnteriores,
                        'nuevo' => $subfNuevas,
                        'tipo' => 'MODIFICACION'
                    ];
                    $hayCambios = true;
                }
            }

            // Comparar subfracciones II (solo si existe la columna)
            if ($tieneColSubfII && isset($nuevosDatos['subfracciones_ii'])) {
                $subfIIAnteriores = json_decode($datosActuales['subfracciones_ii'] ?? '[]', true);
                $subfIINuevas = is_array($nuevosDatos['subfracciones_ii'])
                    ? $nuevosDatos['subfracciones_ii']
                    : json_decode($nuevosDatos['subfracciones_ii'] ?? '[]', true);
                $subfIIAnteriores = is_array($subfIIAnteriores) ? $subfIIAnteriores : [];
                $subfIINuevas = is_array($subfIINuevas) ? $subfIINuevas : [];
                sort($subfIIAnteriores);
                sort($subfIINuevas);
                if ($subfIIAnteriores !== $subfIINuevas) {
                    $cambios[] = [
                        'campo' => 'subfracciones_ii',
                        'anterior' => $subfIIAnteriores,
                        'nuevo' => $subfIINuevas,
                        'tipo' => 'MODIFICACION'
                    ];
                    $hayCambios = true;
                }
            }

            // Comparar subfracciones XII/FEP (solo si existe la columna)
            if ($tieneColSubfXII && isset($nuevosDatos['subfracciones_xii'])) {
                $subfXIIAnteriores = json_decode($datosActuales['subfracciones_xii'] ?? '[]', true);
                $subfXIINuevas = is_array($nuevosDatos['subfracciones_xii'])
                    ? $nuevosDatos['subfracciones_xii']
                    : json_decode($nuevosDatos['subfracciones_xii'] ?? '[]', true);
                $subfXIIAnteriores = is_array($subfXIIAnteriores) ? $subfXIIAnteriores : [];
                $subfXIINuevas = is_array($subfXIINuevas) ? $subfXIINuevas : [];
                sort($subfXIIAnteriores);
                sort($subfXIINuevas);
                if ($subfXIIAnteriores !== $subfXIINuevas) {
                    $cambios[] = [
                        'campo' => 'subfracciones_xii',
                        'anterior' => $subfXIIAnteriores,
                        'nuevo' => $subfXIINuevas,
                        'tipo' => 'MODIFICACION'
                    ];
                    $hayCambios = true;
                }
            }

            // Comparar subfracciones XII/FES (solo si existe la columna)
            if ($tieneColSubfXIIFES && isset($nuevosDatos['subfracciones_xii_fes'])) {
                $subfXIIFESAnteriores = json_decode($datosActuales['subfracciones_xii_fes'] ?? '[]', true);
                $subfXIIFESNuevas = is_array($nuevosDatos['subfracciones_xii_fes'])
                    ? $nuevosDatos['subfracciones_xii_fes']
                    : json_decode($nuevosDatos['subfracciones_xii_fes'] ?? '[]', true);
                $subfXIIFESAnteriores = is_array($subfXIIFESAnteriores) ? $subfXIIFESAnteriores : [];
                $subfXIIFESNuevas = is_array($subfXIIFESNuevas) ? $subfXIIFESNuevas : [];
                sort($subfXIIFESAnteriores);
                sort($subfXIIFESNuevas);
                if ($subfXIIFESAnteriores !== $subfXIIFESNuevas) {
                    $cambios[] = [
                        'campo' => 'subfracciones_xii_fes',
                        'anterior' => $subfXIIFESAnteriores,
                        'nuevo' => $subfXIIFESNuevas,
                        'tipo' => 'MODIFICACION'
                    ];
                    $hayCambios = true;
                }
            }

            // Comparar subfracciones XIV/ADU (solo si existe la columna)
            if ($tieneColSubfXIV && isset($nuevosDatos['subfracciones_xiv'])) {
                $subfXIVAnteriores = json_decode($datosActuales['subfracciones_xiv'] ?? '[]', true);
                $subfXIVNuevas = is_array($nuevosDatos['subfracciones_xiv'])
                    ? $nuevosDatos['subfracciones_xiv']
                    : json_decode($nuevosDatos['subfracciones_xiv'] ?? '[]', true);
                $subfXIVAnteriores = is_array($subfXIVAnteriores) ? $subfXIVAnteriores : [];
                $subfXIVNuevas = is_array($subfXIVNuevas) ? $subfXIVNuevas : [];
                sort($subfXIVAnteriores);
                sort($subfXIVNuevas);
                if ($subfXIVAnteriores !== $subfXIVNuevas) {
                    $cambios[] = [
                        'campo' => 'subfracciones_xiv',
                        'anterior' => $subfXIVAnteriores,
                        'nuevo' => $subfXIVNuevas,
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
    function processRevalidation($pdo, $nuevosDatos, $confirmarCambios = false, $id_usuario = 0) {
        try {
            $logger = Logger::getInstance();
            
            $comparacion = comparePatronData($pdo, $nuevosDatos, $id_usuario);
            
            if (!$comparacion['hay_cambios']) {
                if ($confirmarCambios) {
                    if ($id_usuario > 0) {
                        $stmt = $pdo->prepare("UPDATE config_empresa_usuario SET fecha_revalidacion_patron = CURDATE() WHERE id_usuario = ?");
                        $stmt->execute([$id_usuario]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE config_empresa SET fecha_revalidacion_patron = CURDATE() WHERE id_config = 1");
                        $stmt->execute();
                    }
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
                
                $subfraccionesXI = $nuevosDatos['subfracciones_xi'] ?? null;
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_xi'");
                    if ($chk && $chk->fetchColumn() > 0) {
                        $updates[] = "subfracciones_xi = ?";
                        $params[] = ($subfraccionesXI === null || $subfraccionesXI === '') ? null : (is_string($subfraccionesXI) ? $subfraccionesXI : json_encode($subfraccionesXI));
                    }
                } catch (Exception $e) { /* ignorar */ }

                $subfraccionesII = $nuevosDatos['subfracciones_ii'] ?? null;
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_ii'");
                    if ($chk && $chk->fetchColumn() > 0) {
                        $updates[] = "subfracciones_ii = ?";
                        $params[] = ($subfraccionesII === null || $subfraccionesII === '') ? null : (is_string($subfraccionesII) ? $subfraccionesII : json_encode($subfraccionesII));
                    }
                } catch (Exception $e) { /* ignorar */ }

                $subfraccionesXII = $nuevosDatos['subfracciones_xii'] ?? null;
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_xii'");
                    if ($chk && $chk->fetchColumn() > 0) {
                        $updates[] = "subfracciones_xii = ?";
                        $params[] = ($subfraccionesXII === null || $subfraccionesXII === '') ? null : (is_string($subfraccionesXII) ? $subfraccionesXII : json_encode($subfraccionesXII));
                    }
                } catch (Exception $e) { /* ignorar */ }

                $subfraccionesXIIFES = $nuevosDatos['subfracciones_xii_fes'] ?? null;
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_xii_fes'");
                    if ($chk && $chk->fetchColumn() > 0) {
                        $updates[] = "subfracciones_xii_fes = ?";
                        $params[] = ($subfraccionesXIIFES === null || $subfraccionesXIIFES === '') ? null : (is_string($subfraccionesXIIFES) ? $subfraccionesXIIFES : json_encode($subfraccionesXIIFES));
                    }
                } catch (Exception $e) { /* ignorar */ }

                $subfraccionesXIV = $nuevosDatos['subfracciones_xiv'] ?? null;
                try {
                    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa' AND COLUMN_NAME = 'subfracciones_xiv'");
                    if ($chk && $chk->fetchColumn() > 0) {
                        $updates[] = "subfracciones_xiv = ?";
                        $params[] = ($subfraccionesXIV === null || $subfraccionesXIV === '') ? null : (is_string($subfraccionesXIV) ? $subfraccionesXIV : json_encode($subfraccionesXIV));
                    }
                } catch (Exception $e) { /* ignorar */ }
                
                $updates[] = "fecha_revalidacion_patron = CURDATE()";
                
                if (!empty($updates)) {
                    if ($id_usuario > 0) {
                        // Cargar datos existentes para no sobrescribir con null
                        $existentes = null;
                        $stmtEx = $pdo->prepare("SELECT folio_patron_pld, estatus_patron_pld, fracciones_activas FROM config_empresa_usuario WHERE id_usuario = ?");
                        $stmtEx->execute([$id_usuario]);
                        $existentes = $stmtEx->fetch(PDO::FETCH_ASSOC);
                        if (!$existentes) {
                            $stmtG = $pdo->query("SELECT folio_patron_pld, estatus_patron_pld, fracciones_activas FROM config_empresa WHERE id_config = 1");
                            $existentes = $stmtG ? $stmtG->fetch(PDO::FETCH_ASSOC) : null;
                        }
                        $folioFin = $folio !== null && $folio !== '' ? $folio : ($existentes['folio_patron_pld'] ?? null);
                        $estatusFin = $estatus !== null && $estatus !== '' ? $estatus : ($existentes['estatus_patron_pld'] ?? null);
                        $fraccionesFin = $fracciones !== null && $fracciones !== '' ? $fracciones : ($existentes['fracciones_activas'] ?? null);
                        $subfXIVal = ($subfraccionesXI === null || $subfraccionesXI === '') ? null : (is_array($subfraccionesXI) ? json_encode($subfraccionesXI) : $subfraccionesXI);
                        $subfIIVal = ($subfraccionesII === null || $subfraccionesII === '') ? null : (is_array($subfraccionesII) ? json_encode($subfraccionesII) : $subfraccionesII);
                        $subfXIIVal = ($subfraccionesXII === null || $subfraccionesXII === '') ? null : (is_array($subfraccionesXII) ? json_encode($subfraccionesXII) : $subfraccionesXII);
                        $subfXIIFESVal = ($subfraccionesXIIFES === null || $subfraccionesXIIFES === '') ? null : (is_array($subfraccionesXIIFES) ? json_encode($subfraccionesXIIFES) : $subfraccionesXIIFES);
                        $subfXIVVal = ($subfraccionesXIV === null || $subfraccionesXIV === '') ? null : (is_array($subfraccionesXIV) ? json_encode($subfraccionesXIV) : $subfraccionesXIV);
                        $hasSubfXI = false;
                        $hasSubfII = false;
                        $hasSubfXII = false;
                        $hasSubfXIIFES = false;
                        $hasSubfXIV = false;
                        try {
                            $chkU = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa_usuario' AND COLUMN_NAME = 'subfracciones_xi'");
                            $hasSubfXI = $chkU && $chkU->fetchColumn() > 0;
                        } catch (Exception $e) { }
                        try {
                            $chkU = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa_usuario' AND COLUMN_NAME = 'subfracciones_ii'");
                            $hasSubfII = $chkU && $chkU->fetchColumn() > 0;
                        } catch (Exception $e) { }
                        try {
                            $chkU = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa_usuario' AND COLUMN_NAME = 'subfracciones_xii'");
                            $hasSubfXII = $chkU && $chkU->fetchColumn() > 0;
                        } catch (Exception $e) { }
                        try {
                            $chkU = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa_usuario' AND COLUMN_NAME = 'subfracciones_xii_fes'");
                            $hasSubfXIIFES = $chkU && $chkU->fetchColumn() > 0;
                        } catch (Exception $e) { }
                        try {
                            $chkU = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'config_empresa_usuario' AND COLUMN_NAME = 'subfracciones_xiv'");
                            $hasSubfXIV = $chkU && $chkU->fetchColumn() > 0;
                        } catch (Exception $e) { }
                        if ($hasSubfXI && $hasSubfII && $hasSubfXII && $hasSubfXIIFES && $hasSubfXIV) {
                            $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, folio_patron_pld, estatus_patron_pld, fracciones_activas, subfracciones_xi, subfracciones_ii, subfracciones_xii, subfracciones_xii_fes, subfracciones_xiv, fecha_revalidacion_patron) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE folio_patron_pld = VALUES(folio_patron_pld), estatus_patron_pld = VALUES(estatus_patron_pld), fracciones_activas = VALUES(fracciones_activas), subfracciones_xi = VALUES(subfracciones_xi), subfracciones_ii = VALUES(subfracciones_ii), subfracciones_xii = VALUES(subfracciones_xii), subfracciones_xii_fes = VALUES(subfracciones_xii_fes), subfracciones_xiv = VALUES(subfracciones_xiv), fecha_revalidacion_patron = VALUES(fecha_revalidacion_patron)");
                            $stmt->execute([$id_usuario, $folioFin, $estatusFin, $fraccionesFin, $subfXIVal, $subfIIVal, $subfXIIVal, $subfXIIFESVal, $subfXIVVal]);
                        } elseif ($hasSubfXI && $hasSubfII && $hasSubfXII && $hasSubfXIV) {
                            $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, folio_patron_pld, estatus_patron_pld, fracciones_activas, subfracciones_xi, subfracciones_ii, subfracciones_xii, subfracciones_xiv, fecha_revalidacion_patron) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE folio_patron_pld = VALUES(folio_patron_pld), estatus_patron_pld = VALUES(estatus_patron_pld), fracciones_activas = VALUES(fracciones_activas), subfracciones_xi = VALUES(subfracciones_xi), subfracciones_ii = VALUES(subfracciones_ii), subfracciones_xii = VALUES(subfracciones_xii), subfracciones_xiv = VALUES(subfracciones_xiv), fecha_revalidacion_patron = VALUES(fecha_revalidacion_patron)");
                            $stmt->execute([$id_usuario, $folioFin, $estatusFin, $fraccionesFin, $subfXIVal, $subfIIVal, $subfXIIVal, $subfXIVVal]);
                        } elseif ($hasSubfXII && $hasSubfXIIFES) {
                            $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, folio_patron_pld, estatus_patron_pld, fracciones_activas, subfracciones_xii, subfracciones_xii_fes, fecha_revalidacion_patron) VALUES (?, ?, ?, ?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE folio_patron_pld = VALUES(folio_patron_pld), estatus_patron_pld = VALUES(estatus_patron_pld), fracciones_activas = VALUES(fracciones_activas), subfracciones_xii = VALUES(subfracciones_xii), subfracciones_xii_fes = VALUES(subfracciones_xii_fes), fecha_revalidacion_patron = VALUES(fecha_revalidacion_patron)");
                            $stmt->execute([$id_usuario, $folioFin, $estatusFin, $fraccionesFin, $subfXIIVal, $subfXIIFESVal]);
                        } elseif ($hasSubfXI && $hasSubfII && $hasSubfXII) {
                            $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, folio_patron_pld, estatus_patron_pld, fracciones_activas, subfracciones_xi, subfracciones_ii, subfracciones_xii, fecha_revalidacion_patron) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE folio_patron_pld = VALUES(folio_patron_pld), estatus_patron_pld = VALUES(estatus_patron_pld), fracciones_activas = VALUES(fracciones_activas), subfracciones_xi = VALUES(subfracciones_xi), subfracciones_ii = VALUES(subfracciones_ii), subfracciones_xii = VALUES(subfracciones_xii), fecha_revalidacion_patron = VALUES(fecha_revalidacion_patron)");
                            $stmt->execute([$id_usuario, $folioFin, $estatusFin, $fraccionesFin, $subfXIVal, $subfIIVal, $subfXIIVal]);
                        } elseif ($hasSubfXI && $hasSubfII) {
                            $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, folio_patron_pld, estatus_patron_pld, fracciones_activas, subfracciones_xi, subfracciones_ii, fecha_revalidacion_patron) VALUES (?, ?, ?, ?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE folio_patron_pld = VALUES(folio_patron_pld), estatus_patron_pld = VALUES(estatus_patron_pld), fracciones_activas = VALUES(fracciones_activas), subfracciones_xi = VALUES(subfracciones_xi), subfracciones_ii = VALUES(subfracciones_ii), fecha_revalidacion_patron = VALUES(fecha_revalidacion_patron)");
                            $stmt->execute([$id_usuario, $folioFin, $estatusFin, $fraccionesFin, $subfXIVal, $subfIIVal]);
                        } elseif ($hasSubfXII) {
                            $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, folio_patron_pld, estatus_patron_pld, fracciones_activas, subfracciones_xii, fecha_revalidacion_patron) VALUES (?, ?, ?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE folio_patron_pld = VALUES(folio_patron_pld), estatus_patron_pld = VALUES(estatus_patron_pld), fracciones_activas = VALUES(fracciones_activas), subfracciones_xii = VALUES(subfracciones_xii), fecha_revalidacion_patron = VALUES(fecha_revalidacion_patron)");
                            $stmt->execute([$id_usuario, $folioFin, $estatusFin, $fraccionesFin, $subfXIIVal]);
                        } elseif ($hasSubfXIV) {
                            $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, folio_patron_pld, estatus_patron_pld, fracciones_activas, subfracciones_xiv, fecha_revalidacion_patron) VALUES (?, ?, ?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE folio_patron_pld = VALUES(folio_patron_pld), estatus_patron_pld = VALUES(estatus_patron_pld), fracciones_activas = VALUES(fracciones_activas), subfracciones_xiv = VALUES(subfracciones_xiv), fecha_revalidacion_patron = VALUES(fecha_revalidacion_patron)");
                            $stmt->execute([$id_usuario, $folioFin, $estatusFin, $fraccionesFin, $subfXIVVal]);
                        } elseif ($hasSubfXIIFES) {
                            $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, folio_patron_pld, estatus_patron_pld, fracciones_activas, subfracciones_xii_fes, fecha_revalidacion_patron) VALUES (?, ?, ?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE folio_patron_pld = VALUES(folio_patron_pld), estatus_patron_pld = VALUES(estatus_patron_pld), fracciones_activas = VALUES(fracciones_activas), subfracciones_xii_fes = VALUES(subfracciones_xii_fes), fecha_revalidacion_patron = VALUES(fecha_revalidacion_patron)");
                            $stmt->execute([$id_usuario, $folioFin, $estatusFin, $fraccionesFin, $subfXIIFESVal]);
                        } elseif ($hasSubfXI) {
                            $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, folio_patron_pld, estatus_patron_pld, fracciones_activas, subfracciones_xi, fecha_revalidacion_patron) VALUES (?, ?, ?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE folio_patron_pld = VALUES(folio_patron_pld), estatus_patron_pld = VALUES(estatus_patron_pld), fracciones_activas = VALUES(fracciones_activas), subfracciones_xi = VALUES(subfracciones_xi), fecha_revalidacion_patron = VALUES(fecha_revalidacion_patron)");
                            $stmt->execute([$id_usuario, $folioFin, $estatusFin, $fraccionesFin, $subfXIVal]);
                        } elseif ($hasSubfII) {
                            $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, folio_patron_pld, estatus_patron_pld, fracciones_activas, subfracciones_ii, fecha_revalidacion_patron) VALUES (?, ?, ?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE folio_patron_pld = VALUES(folio_patron_pld), estatus_patron_pld = VALUES(estatus_patron_pld), fracciones_activas = VALUES(fracciones_activas), subfracciones_ii = VALUES(subfracciones_ii), fecha_revalidacion_patron = VALUES(fecha_revalidacion_patron)");
                            $stmt->execute([$id_usuario, $folioFin, $estatusFin, $fraccionesFin, $subfIIVal]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO config_empresa_usuario (id_usuario, folio_patron_pld, estatus_patron_pld, fracciones_activas, fecha_revalidacion_patron) VALUES (?, ?, ?, ?, CURDATE()) ON DUPLICATE KEY UPDATE folio_patron_pld = VALUES(folio_patron_pld), estatus_patron_pld = VALUES(estatus_patron_pld), fracciones_activas = VALUES(fracciones_activas), fecha_revalidacion_patron = VALUES(fecha_revalidacion_patron)");
                            $stmt->execute([$id_usuario, $folioFin, $estatusFin, $fraccionesFin]);
                        }
                    } else {
                        $sql = "UPDATE config_empresa SET " . implode(", ", $updates) . " WHERE id_config = 1";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                    }
                }
                
                if ($tieneBaja) {
                    updateHabilitadoPLDFlag($pdo, true, $id_usuario);
                    $logger->warning('PLD Revalidation: Baja detectada y confirmada. Operaciones bloqueadas.');
                } else {
                    $result = validatePatronPLD($pdo, null, $id_usuario);
                    updateHabilitadoPLDFlag($pdo, !$result['habilitado'], $id_usuario);
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
