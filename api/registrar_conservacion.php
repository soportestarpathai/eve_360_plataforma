<?php
/**
 * API Endpoint: Registrar evidencia para conservación (VAL-PLD-013)
 * Registra o actualiza evidencia para conservación por 10 años
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_conservacion.php';
require_once __DIR__ . '/../config/bitacora.php';
require_once __DIR__ . '/../config/pld_middleware.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// VAL-PLD-001: Bloquear si no está habilitado
requirePLDHabilitado($pdo, true);

$id_usuario_actual = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $data = [];
        
        // Debug: Log datos recibidos
        error_log("Datos POST: " . print_r($_POST, true));
        error_log("Archivos: " . print_r($_FILES, true));
        
        // Manejar FormData (archivos)
        if (!empty($_FILES)) {
            $data = $_POST;
            
            // Procesar archivo subido
            $tipo_evidencia = $data['tipo_evidencia'] ?? null;
            $id_cliente = $data['id_cliente'] ?? null;
            $id_operacion = $data['id_operacion'] ?? null;
            $id_aviso = $data['id_aviso'] ?? null;
            
            if (!isset($_FILES['archivo_evidencia'])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Archivo no proporcionado',
                    'debug' => [
                        'files_keys' => array_keys($_FILES),
                        'post_data' => $_POST
                    ]
                ]);
                exit;
            }
            
            $archivoError = $_FILES['archivo_evidencia']['error'];
            if ($archivoError !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido',
                    UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
                    UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                    UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
                    UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
                    UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
                    UPLOAD_ERR_EXTENSION => 'Una extensión PHP detuvo la subida del archivo'
                ];
                
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Error al subir archivo: ' . ($errorMessages[$archivoError] ?? "Error desconocido ($archivoError)")
                ]);
                exit;
            }
            
            // Crear directorio si no existe
            $uploadDir = __DIR__ . '/../uploads/conservacion/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    http_response_code(500);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Error al crear directorio de uploads'
                    ]);
                    exit;
                }
            }
            
            // Validar extensión
            $extension = strtolower(pathinfo($_FILES['archivo_evidencia']['name'], PATHINFO_EXTENSION));
            $extensionesPermitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
            
            if (!in_array($extension, $extensionesPermitidas)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Tipo de archivo no permitido. Formatos permitidos: ' . implode(', ', $extensionesPermitidas)
                ]);
                exit;
            }
            
            // Generar nombre único
            $nombreArchivo = date('Ymd_His') . '_' . uniqid() . '.' . $extension;
            $rutaArchivo = $uploadDir . $nombreArchivo;
            
            // Mover archivo
            if (!move_uploaded_file($_FILES['archivo_evidencia']['tmp_name'], $rutaArchivo)) {
                http_response_code(500);
                error_log("Error al mover archivo: tmp_name=" . $_FILES['archivo_evidencia']['tmp_name'] . ", destino=" . $rutaArchivo);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Error al guardar archivo en el servidor'
                ]);
                exit;
            }
            
            // Ruta relativa para guardar en BD
            $data['ruta_evidencia'] = 'uploads/conservacion/' . $nombreArchivo;
            
        } else {
            // JSON data
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            
            if (!$data) {
                $data = $_POST;
            }
        }
        
        // Validar datos requeridos
        $tipo_evidencia = $data['tipo_evidencia'] ?? null;
        $ruta_evidencia = $data['ruta_evidencia'] ?? null;
        
        // Normalizar valores vacíos
        if ($tipo_evidencia === '') {
            $tipo_evidencia = null;
        }
        
        if (!$tipo_evidencia) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'tipo_evidencia es requerido',
                'debug' => [
                    'data_recibida' => $data,
                    'post' => $_POST,
                    'files' => !empty($_FILES) ? 'presente' : 'vacio'
                ]
            ]);
            exit;
        }
        
        if (!$ruta_evidencia && empty($_FILES)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'ruta_evidencia o archivo es requerido',
                'debug' => [
                    'ruta_evidencia' => $ruta_evidencia,
                    'files_empty' => empty($_FILES)
                ]
            ]);
            exit;
        }
        
        // Normalizar valores opcionales
        if (isset($data['id_cliente']) && $data['id_cliente'] === '') {
            $data['id_cliente'] = null;
        }
        if (isset($data['id_operacion']) && $data['id_operacion'] === '') {
            $data['id_operacion'] = null;
        }
        if (isset($data['id_aviso']) && $data['id_aviso'] === '') {
            $data['id_aviso'] = null;
        }
        
        // Registrar conservación
        $result = registrarConservacionInformacion($pdo, $data);
        
        if ($result['success']) {
            // Log
            logChange($pdo, $id_usuario_actual, 'REGISTRAR_CONSERVACION', 
                     'conservacion_informacion_pld', $result['id_conservacion'], null, $data);
            
            echo json_encode([
                'status' => 'success',
                'message' => $result['message'],
                'id_conservacion' => $result['id_conservacion'],
                'fecha_vencimiento' => $result['fecha_vencimiento']
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $result['message'] ?? 'Error desconocido al registrar conservación',
                'expediente_incompleto' => $result['expediente_incompleto'] ?? false,
                'debug' => $result
            ]);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en registrar_conservacion.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al procesar solicitud: ' . $e->getMessage()
    ]);
}
