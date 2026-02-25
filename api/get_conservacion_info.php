<?php
/**
 * API Endpoint: Obtener información de conservación (VAL-PLD-013)
 * Lista evidencias registradas para conservación
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_conservacion.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    $id_cliente = $_GET['id_cliente'] ?? null;
    $id_operacion = $_GET['id_operacion'] ?? null;
    $id_aviso = $_GET['id_aviso'] ?? null;
    $tipo_evidencia = $_GET['tipo_evidencia'] ?? null;
    $expediente_incompleto = $_GET['expediente_incompleto'] ?? null;
    
    $sql = "SELECT c.*, 
                   COALESCE(cf.nombre, cm.razon_social, 'Sin nombre') as cliente_nombre,
                   op.fecha_operacion,
                   a.tipo_aviso as aviso_tipo
            FROM conservacion_informacion_pld c
            LEFT JOIN clientes cl ON c.id_cliente = cl.id_cliente
            LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
            LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
            LEFT JOIN operaciones_pld op ON c.id_operacion = op.id_operacion
            LEFT JOIN avisos_pld a ON c.id_aviso = a.id_aviso
            WHERE c.id_status = 1
              AND COALESCE(cl.id_status, 1) != 4";
    $params = [];
    
    if ($id_cliente) {
        $sql .= " AND c.id_cliente = ?";
        $params[] = $id_cliente;
    }
    
    if ($id_operacion) {
        $sql .= " AND c.id_operacion = ?";
        $params[] = $id_operacion;
    }
    
    if ($id_aviso) {
        $sql .= " AND c.id_aviso = ?";
        $params[] = $id_aviso;
    }
    
    if ($tipo_evidencia) {
        $sql .= " AND c.tipo_evidencia = ?";
        $params[] = $tipo_evidencia;
    }
    
    if ($expediente_incompleto !== null) {
        $sql .= " AND c.expediente_incompleto = ?";
        $params[] = $expediente_incompleto;
    }
    
    $sql .= " ORDER BY c.fecha_creacion DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $evidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Validar cada evidencia
    $resultado = [];
    $faltantes = 0;
    $vencidas = 0;
    $disponibles = 0;
    
    $basePath = dirname(__DIR__) . '/'; // Directorio raíz del proyecto
    
    foreach ($evidencias as $evidencia) {
        $hoy = new DateTime();
        $fechaVencimiento = new DateTime($evidencia['fecha_vencimiento']);
        
        // Resolver ruta del archivo (puede ser relativa o absoluta)
        $rutaEvidencia = $evidencia['ruta_evidencia'];
        $rutaCompleta = $rutaEvidencia;
        
        // Si no existe como ruta absoluta, intentar como relativa desde el directorio raíz
        if (!file_exists($rutaCompleta)) {
            $rutaCompleta = $basePath . ltrim($rutaEvidencia, '/\\');
        }
        
        // Si aún no existe, intentar desde el directorio de config
        if (!file_exists($rutaCompleta)) {
            $rutaCompleta = dirname($basePath) . '/' . ltrim($rutaEvidencia, '/\\');
        }
        
        $archivoExiste = file_exists($rutaCompleta);
        $estaVencida = $fechaVencimiento < $hoy;
        
        $estado = 'disponible';
        if (!$archivoExiste) {
            $estado = 'faltante';
            $faltantes++;
        } elseif ($estaVencida) {
            $estado = 'vencida';
            $vencidas++;
        } else {
            $disponibles++;
        }
        
        $resultado[] = array_merge($evidencia, [
            'estado' => $estado,
            'archivo_existe' => $archivoExiste,
            'esta_vencida' => $estaVencida,
            'dias_restantes' => $estaVencida ? 0 : $hoy->diff($fechaVencimiento)->days,
            'ruta_completa' => $rutaCompleta // Para debugging
        ]);
    }
    
    // Validación general
    $validacion = validateConservacionInformacion($pdo, $id_cliente, $id_operacion, $id_aviso);
    
    echo json_encode([
        'status' => 'success',
        'evidencias' => $resultado,
        'validacion' => $validacion,
        'estadisticas' => [
            'total' => count($evidencias),
            'disponibles' => $disponibles,
            'faltantes' => $faltantes,
            'vencidas' => $vencidas
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_conservacion_info.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener información de conservación: ' . $e->getMessage()
    ]);
}
