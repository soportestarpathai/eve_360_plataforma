<?php
// Start output buffering to catch any unwanted output
ob_start();

// Suppress any warnings/notices that might break JSON output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

try {
    session_start();
    require_once '../config/db.php'; // Ensure DB connection is available
    require_once '../config/bitacora.php'; 
    require_once '../config/risk_engine.php'; // INCLUDE ENGINE
    require_once '../config/pld_middleware.php'; // VAL-PLD-001: Bloqueo de operaciones PLD

    if (!isset($_SESSION['user_id'])) {
        ob_end_clean(); // Clear any output
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    // VAL-PLD-001: Bloquear creación de clientes si no está habilitado
    requirePLDHabilitado($pdo, true);
} catch (Exception $e) {
    ob_end_clean(); // Clear any output
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al inicializar: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    error_log('Init Error in save_client.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    exit;
} catch (Error $e) {
    ob_end_clean(); // Clear any output
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error fatal al inicializar: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    error_log('Fatal Init Error in save_client.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    exit;
}

// Clear any buffered output before starting
ob_end_clean();

// POST data comes from FormData
$data = $_POST;
$id_usuario_actual = $_SESSION['user_id'];

// Validate required fields before starting transaction
if (empty($data['id_tipo_persona'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tipo de persona es requerido']);
    exit;
}

if (empty($data['no_contrato'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Número de contrato es requerido']);
    exit;
}

// Start Transaction
try {
    $pdo->beginTransaction();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al iniciar transacción: ' . $e->getMessage()
    ]);
    exit;
}

try {
    // 1. INSERT into `clientes` (Main Table)
    $stmt = $pdo->prepare(
        "INSERT INTO clientes (id_tipo_persona, no_contrato, alias, fecha_apertura, id_usuario, id_status, fecha_baja)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $fecha_baja = ($data['id_status'] == '3') ? $data['fecha_baja'] : null;
    
    $stmt->execute([
        $data['id_tipo_persona'],
        $data['no_contrato'],
        $data['alias'],
        $data['fecha_apertura'],
        $id_usuario_actual,
        $data['id_status'],
        $fecha_baja
    ]);
    $id_cliente = $pdo->lastInsertId();

    // --- Log Change ---
    $newDataClientes = [
        'id_cliente' => $id_cliente, 'id_tipo_persona' => $data['id_tipo_persona'], 'no_contrato' => $data['no_contrato'],
        'alias' => $data['alias'], 'fecha_apertura' => $data['fecha_apertura'], 'id_usuario' => $id_usuario_actual,
        'id_status' => $data['id_status'], 'fecha_baja' => $fecha_baja
    ];
    logChange($pdo, $id_usuario_actual, "CREAR", "clientes", $id_cliente, null, $newDataClientes);
    // --- End Log ---

    // 2. INSERT into `clientes_fisicas`, `morales`, or `fideicomisos`
    // We need to fetch the persona type from DB to trust its flags
    $type_stmt = $pdo->prepare("SELECT * FROM cat_tipo_persona WHERE id_tipo_persona = ?");
    $type_stmt->execute([$data['id_tipo_persona']]);
    $personaType = $type_stmt->fetch();

    if ($personaType['es_fisica'] > 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO clientes_fisicas (id_cliente, nombre, apellido_paterno, apellido_materno, fecha_nacimiento, tax_id, CURP, id_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([
            $id_cliente, $data['fisica_nombre'], $data['fisica_ap_paterno'], $data['fisica_ap_materno'],
            $data['fisica_fecha_nacimiento'], $data['fisica_tax_id'], $data['fisica_curp']
        ]);
        // --- Log Change ---
        $newDataFisica = [
            'id_cliente' => $id_cliente, 'nombre' => $data['fisica_nombre'], 'apellido_paterno' => $data['fisica_ap_paterno'],
            'apellido_materno' => $data['fisica_ap_materno'], 'fecha_nacimiento' => $data['fisica_fecha_nacimiento'],
            'tax_id' => $data['fisica_tax_id'], 'CURP' => $data['fisica_curp']
        ];
        logChange($pdo, $id_usuario_actual, "CREAR", "clientes_fisicas", $id_cliente, null, $newDataFisica);
        // --- End Log ---
    } 
    elseif ($personaType['es_moral'] > 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO clientes_morales (id_cliente, razon_social, fecha_constitucion, tax_id, id_status)
             VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->execute([
            $id_cliente, $data['moral_razon_social'], $data['moral_fecha_constitucion'], $data['moral_tax_id']
        ]);
        // --- Log Change ---
        logChange($pdo, $id_usuario_actual, "CREAR", "clientes_morales", $id_cliente, null, [
            'id_cliente' => $id_cliente, 'razon_social' => $data['moral_razon_social'], 
            'fecha_constitucion' => $data['moral_fecha_constitucion'], 'tax_id' => $data['moral_tax_id']
        ]);
        // --- End Log ---
    }
    elseif ($personaType['es_fideicomiso'] > 0) {
         $stmt = $pdo->prepare(
            "INSERT INTO clientes_fideicomisos (id_cliente, numero_fideicomiso, institucion_fiduciaria, id_status)
             VALUES (?, ?, ?, 1)"
        );
        $stmt->execute([
            $id_cliente, $data['fide_numero'], $data['fide_institucion']
        ]);
        // --- Log Change ---
        logChange($pdo, $id_usuario_actual, "CREAR", "clientes_fideicomisos", $id_cliente, null, [
            'id_cliente' => $id_cliente, 'numero_fideicomiso' => $data['fide_numero'], 'institucion_fiduciaria' => $data['fide_institucion']
        ]);
        // --- End Log ---
    }

    // 3. Loop and INSERT `clientes_nacionalidades`
    if (isset($data['nacionalidad_id'])) {
        $stmt_nac = $pdo->prepare("INSERT INTO clientes_nacionalidades (id_cliente, id_pais, id_status) VALUES (?, ?, 1)");
        $newDataNac = [];
        foreach ($data['nacionalidad_id'] as $id_pais) {
            $stmt_nac->execute([$id_cliente, $id_pais]);
            $newDataNac[] = ['id_cliente' => $id_cliente, 'id_pais' => $id_pais];
        }
        logChange($pdo, $id_usuario_actual, "CREAR_LISTA", "clientes_nacionalidades", $id_cliente, null, $newDataNac);
    }
    
    // 4. Loop and INSERT `clientes_identificaciones`
    if (isset($data['ident_tipo'])) {
        $stmt_id = $pdo->prepare("INSERT INTO clientes_identificaciones (id_cliente, id_tipo_identificacion, numero_identificacion, fecha_vencimiento, id_status) VALUES (?, ?, ?, ?, 1)");
        $newDataIdent = [];
        foreach ($data['ident_tipo'] as $key => $tipo) {
            $vencimiento = $data['ident_vencimiento'][$key] ?: null;
            $numero = $data['ident_numero'][$key];
            $stmt_id->execute([$id_cliente, $tipo, $numero, $vencimiento]);
            $newDataIdent[] = ['id_cliente' => $id_cliente, 'id_tipo_identificacion' => $tipo, 'numero_identificacion' => $numero, 'fecha_vencimiento' => $vencimiento];
        }
        logChange($pdo, $id_usuario_actual, "CREAR_LISTA", "clientes_identificaciones", $id_cliente, null, $newDataIdent);
    }
    
    // 5. Loop and INSERT `clientes_direcciones`
    if (isset($data['dir_calle'])) {
        $stmt_dir = $pdo->prepare("INSERT INTO clientes_direcciones (id_cliente, calle, colonia, codigo_postal) VALUES (?, ?, ?, ?)");
        $newDataDir = [];
        foreach ($data['dir_calle'] as $key => $calle) {
            $colonia = $data['dir_colonia'][$key];
            $cp = $data['dir_cp'][$key];
            $stmt_dir->execute([$id_cliente, $calle, $colonia, $cp]);
            $newDataDir[] = ['id_cliente' => $id_cliente, 'calle' => $calle, 'colonia' => $colonia, 'codigo_postal' => $cp];
        }
        logChange($pdo, $id_usuario_actual, "CREAR_LISTA", "clientes_direcciones", $id_cliente, null, $newDataDir);
    }

    // 6. Loop and INSERT `clientes_contactos`
    if (isset($data['contacto_id_tipo'])) { // <-- UPDATED variable name
         $stmt_con = $pdo->prepare("INSERT INTO clientes_contactos (id_cliente, id_tipo_contacto, dato_contacto, id_status) VALUES (?, ?, ?, 1)");
         $newDataCon = [];
         foreach ($data['contacto_id_tipo'] as $key => $id_tipo_contacto) { // <-- UPDATED variable name
             $dato = $data['contacto_valor'][$key];
             $stmt_con->execute([ $id_cliente, $id_tipo_contacto, $dato ]);
             $newDataCon[] = ['id_cliente' => $id_cliente, 'id_tipo_contacto' => $id_tipo_contacto, 'dato_contacto' => $dato];
         }
         logChange($pdo, $id_usuario_actual, "CREAR_LISTA", "clientes_contactos", $id_cliente, null, $newDataCon);
    }
    
    // 7. Loop and INSERT `clientes_documentos` AND Handle Uploads
    if (isset($data['doc_tipo'])) {
        // UPDATED: Now inserting into 'ruta' instead of 'archivo_path'
        $stmt_doc = $pdo->prepare("INSERT INTO clientes_documentos (id_cliente, descripcion, ruta, fecha_vencimiento, id_status) VALUES (?, ?, ?, ?, 1)");
        
        // Create specific folder for this client
        $uploadDir = '../uploads/clientes/' . $id_cliente . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newDataDoc = [];
        foreach($data['doc_tipo'] as $key => $tipo) {
            $vencimiento = $data['doc_vencimiento'][$key] ?: null;
            $rutaDB = null; // Default if no file uploaded

            // Handle File Upload
            if (isset($_FILES['doc_file']['name'][$key]) && $_FILES['doc_file']['error'][$key] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['doc_file']['tmp_name'][$key];
                // Sanitizing filename to avoid issues
                $extension = pathinfo($_FILES['doc_file']['name'][$key], PATHINFO_EXTENSION);
                $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '', $tipo) . '_' . time() . '.' . $extension;
                
                $targetPath = $uploadDir . $cleanName;
                
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $rutaDB = $targetPath; // Save the path
                }
            }

            $stmt_doc->execute([ $id_cliente, $tipo, $rutaDB, $vencimiento ]);
            
            $newDataDoc[] = [
                'id_cliente' => $id_cliente, 
                'descripcion' => $tipo, 
                'ruta' => $rutaDB, 
                'fecha_vencimiento' => $vencimiento
            ];
        }
        logChange($pdo, $id_usuario_actual, "CREAR_LISTA", "clientes_documentos", $id_cliente, null, $newDataDoc);
    }

    // --- NEW: 8. Loop and INSERT `clientes_apoderados` ---
    if (isset($data['apoderado'])) {
        foreach ($data['apoderado'] as $apoData) {
            // 8.1 Insert into `clientes_apoderados`
            $stmt_apo = $pdo->prepare("INSERT INTO clientes_apoderados (id_cliente, id_tipo_persona, fecha_alta) VALUES (?, ?, CURDATE())");
            $stmt_apo->execute([$id_cliente, $apoData['id_tipo_persona']]);
            $id_cliente_apoderado = $pdo->lastInsertId();
            
            // Log this change
            logChange($pdo, $id_usuario_actual, "CREAR", "clientes_apoderados", $id_cliente_apoderado, null, ['id_cliente' => $id_cliente, 'id_tipo_persona' => $apoData['id_tipo_persona']]);

            // 8.2 Insert into `fisicas` or `morales` for apoderado
            $type_stmt_apo = $pdo->prepare("SELECT * FROM cat_tipo_persona WHERE id_tipo_persona = ?");
            $type_stmt_apo->execute([$apoData['id_tipo_persona']]);
            $apoPersonaType = $type_stmt_apo->fetch();

            if ($apoPersonaType['es_fisica'] > 0) {
                $stmt_apo_fis = $pdo->prepare("INSERT INTO clientes_apoderados_fisicas (id_cliente_apoderado, nombre, apellido_paterno, apellido_materno, tax_id, CURP) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_apo_fis->execute([$id_cliente_apoderado, $apoData['fisica_nombre'], $apoData['fisica_ap_paterno'], $apoData['fisica_ap_materno'], $apoData['fisica_tax_id'], $apoData['fisica_curp']]);
                
                logChange($pdo, $id_usuario_actual, "CREAR", "clientes_apoderados_fisicas", $id_cliente_apoderado, null, ['id_cliente_apoderado' => $id_cliente_apoderado, 'nombre' => $apoData['fisica_nombre']]);

            } elseif ($apoPersonaType['es_moral'] > 0) {
                $stmt_apo_mor = $pdo->prepare("INSERT INTO clientes_apoderados_morales (id_cliente_apoderado, razon_social, tax_id) VALUES (?, ?, ?)");
                $stmt_apo_mor->execute([$id_cliente_apoderado, $apoData['moral_razon_social'], $apoData['moral_tax_id']]);
                
                logChange($pdo, $id_usuario_actual, "CREAR", "clientes_apoderados_morales", $id_cliente_apoderado, null, ['id_cliente_apoderado' => $id_cliente_apoderado, 'razon_social' => $apoData['moral_razon_social']]);
            }
            
            // 8.3 Insert `clientes_apoderados_contactos`
            if (isset($apoData['contactos'])) {
                $stmt_apo_con = $pdo->prepare("INSERT INTO clientes_apoderados_contactos (id_cliente_apoderado, id_tipo_contacto, dato_contacto, id_status) VALUES (?, ?, ?, 1)");
                $newDataApoCon = [];
                foreach ($apoData['contactos']['tipo'] as $key => $tipo) {
                    $valor = $apoData['contactos']['valor'][$key];
                    $stmt_apo_con->execute([$id_cliente_apoderado, $tipo, $valor]);
                    $newDataApoCon[] = ['id_cliente_apoderado' => $id_cliente_apoderado, 'id_tipo_contacto' => $tipo, 'dato_contacto' => $valor];
                }
                logChange($pdo, $id_usuario_actual, "CREAR_LISTA", "clientes_apoderados_contactos", $id_cliente_apoderado, null, $newDataApoCon);
            }
        }
    }
    // --- END NEW ---

    // If all inserts were successful, commit the transaction
    $pdo->commit();

    // --- NEW: Calculate Initial Risk ---
    calculateClientRisk($pdo, $id_cliente);
    // -----------------------------------

    // --- VAL-PLD-003: Validar y actualizar flag RESTRICCION_USUARIO ---
    require_once '../config/pld_responsable_validation.php';
    validateAndUpdateResponsablePLD($pdo, $id_cliente);
    // -------------------------------------------------------------------

    echo json_encode(['status' => 'success', 'id_cliente' => $id_cliente]);
    exit;

} catch (PDOException $e) {
    // If any database operation fails, roll back the entire operation
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error de base de datos: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    error_log('PDO Error in save_client.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    exit;
} catch (Exception $e) {
    // If any other error occurs, roll back the entire operation
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error al guardar cliente: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    error_log('Error in save_client.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    exit;
} catch (Error $e) {
    // Catch fatal errors in PHP 7+
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error fatal: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    error_log('Fatal Error in save_client.php: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    exit;
}
?>