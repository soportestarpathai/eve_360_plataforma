<?php
session_start();
require_once '../config/db.php'; // Change 1: Removed require bitacora/risk to check path
require_once '../config/bitacora.php'; 
require_once '../config/risk_engine.php';
require_once '../config/pld_middleware.php'; // VAL-PLD-001: Bloqueo de operaciones PLD
require_once '../config/pld_expediente.php'; // VAL-PLD-005, VAL-PLD-006: Validación de expediente
require_once '../config/pld_beneficiario_controlador.php'; // VAL-PLD-007: Beneficiario Controlador
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// VAL-PLD-001: Bloquear actualización de clientes si no está habilitado
requirePLDHabilitado($pdo, true);

$data = $_POST;
$id_cliente = $data['id_cliente'] ?? 0;
$id_usuario_actual = $_SESSION['user_id']; 

if (!$id_cliente) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cliente no válido.']);
    exit;
}

// Start Transaction
$pdo->beginTransaction();

/** Archivos subidos en esta petición; si hay rollback se eliminan para no dejar huérfanos */
$uploaded_files_this_request = [];

try {
    // Helper function (Ensure this exists or is included)
    if (!function_exists('getOldData')) {
        function getOldData($pdo, $table, $id_cliente) {
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE id_cliente = ?");
            $stmt->execute([$id_cliente]);
            if (strpos($table, '_nacionalidades') || strpos($table, '_identificaciones') || strpos($table, '_direcciones') || strpos($table, '_contactos') || strpos($table, '_documentos')) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // 1. UPDATE `clientes` (Main Table)
    $oldData = getOldData($pdo, 'clientes', $id_cliente); 
    
    // FIXED QUERY: Ensure columns match placeholders (?)
    // Columns: no_contrato, alias, fecha_apertura, id_status, fecha_baja
    // Count: 5 placeholders
    // Where: id_cliente (1 placeholder)
    // Total: 6 placeholders
    $stmt = $pdo->prepare(
        "UPDATE clientes SET no_contrato = ?, alias = ?, fecha_apertura = ?, id_status = ?, fecha_baja = ?
         WHERE id_cliente = ?"
    );
    
    $fecha_baja = ($data['id_status'] == '3') ? $data['fecha_baja'] : null;
    
    // FIXED PARAMS: Ensure 6 values are passed
    $stmt->execute([
        $data['no_contrato'],
        $data['alias'],
        $data['fecha_apertura'],
        $data['id_status'],
        $fecha_baja,
        $id_cliente
    ]);

    // --- Log Change ---
    $newData = [
        'no_contrato' => $data['no_contrato'], 'alias' => $data['alias'], 'fecha_apertura' => $data['fecha_apertura'],
        'id_status' => $data['id_status'], 'fecha_baja' => $fecha_baja
    ];
    logChange($pdo, $id_usuario_actual, "ACTUALIZAR", "clientes", $id_cliente, $oldData, $newData);
    // --- End Log ---

    // 2. UPDATE `clientes_fisicas`, `morales`, or `fideicomisos`
    // Check which type currently exists for this client (trust DB over form ID for safety, or use form ID)
    // Using form ID since we enable it briefly before submit
    $type_stmt = $pdo->prepare("SELECT * FROM cat_tipo_persona WHERE id_tipo_persona = ?");
    $type_stmt->execute([$data['id_tipo_persona']]);
    $personaType = $type_stmt->fetch();

    if ($personaType['es_fisica'] > 0) {
        $oldDataFisica = getOldData($pdo, 'clientes_fisicas', $id_cliente);
        $stmt = $pdo->prepare(
            "UPDATE clientes_fisicas SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, 
             fecha_nacimiento = ?, tax_id = ?, CURP = ?
             WHERE id_cliente = ?"
        );
        $stmt->execute([
            $data['fisica_nombre'], $data['fisica_ap_paterno'], $data['fisica_ap_materno'],
            $data['fisica_fecha_nacimiento'], $data['fisica_tax_id'], $data['fisica_curp'],
            $id_cliente
        ]);
        $newDataFisica = [
            'nombre' => $data['fisica_nombre'], 'apellido_paterno' => $data['fisica_ap_paterno'], 'apellido_materno' => $data['fisica_ap_materno'],
            'fecha_nacimiento' => $data['fisica_fecha_nacimiento'], 'tax_id' => $data['fisica_tax_id'], 'CURP' => $data['fisica_curp']
        ];
        logChange($pdo, $id_usuario_actual, "ACTUALIZAR", "clientes_fisicas", $id_cliente, $oldDataFisica, $newDataFisica);
    } 
    elseif ($personaType['es_moral'] > 0) {
        $oldDataMoral = getOldData($pdo, 'clientes_morales', $id_cliente);
        $stmt = $pdo->prepare(
            "UPDATE clientes_morales SET razon_social = ?, fecha_constitucion = ?, tax_id = ?
             WHERE id_cliente = ?"
        );
        $stmt->execute([
            $data['moral_razon_social'], $data['moral_fecha_constitucion'], $data['moral_tax_id'],
            $id_cliente
        ]);
        $newDataMoral = [
            'razon_social' => $data['moral_razon_social'], 'fecha_constitucion' => $data['moral_fecha_constitucion'], 'tax_id' => $data['moral_tax_id']
        ];
        logChange($pdo, $id_usuario_actual, "ACTUALIZAR", "clientes_morales", $id_cliente, $oldDataMoral, $newDataMoral);
    }
    elseif ($personaType['es_fideicomiso'] > 0) {
        $oldDataFide = getOldData($pdo, 'clientes_fideicomisos', $id_cliente);
        $stmt = $pdo->prepare(
            "UPDATE clientes_fideicomisos SET numero_fideicomiso = ?, institucion_fiduciaria = ?
             WHERE id_cliente = ?"
        );
        $stmt->execute([
            $data['fide_numero'], $data['fide_institucion'],
            $id_cliente
        ]);
        $newDataFide = [
            'numero_fideicomiso' => $data['fide_numero'], 'institucion_fiduciaria' => $data['fide_institucion']
        ];
        logChange($pdo, $id_usuario_actual, "ACTUALIZAR", "clientes_fideicomisos", $id_cliente, $oldDataFide, $newDataFide);
    }

    // --- 3. Handle Dynamic Lists (Delete and Re-insert) ---

    // Nacionalidades
    $oldNacionalidades = getOldData($pdo, 'clientes_nacionalidades', $id_cliente);
    $pdo->prepare("DELETE FROM clientes_nacionalidades WHERE id_cliente = ?")->execute([$id_cliente]);
    $newNacionalidades = [];
    if (isset($data['nacionalidad_id'])) {
        $stmt_nac = $pdo->prepare("INSERT INTO clientes_nacionalidades (id_cliente, id_pais, id_status) VALUES (?, ?, 1)");
        foreach ($data['nacionalidad_id'] as $id_pais) {
            $stmt_nac->execute([$id_cliente, $id_pais]);
            $newNacionalidades[] = ['id_cliente' => $id_cliente, 'id_pais' => $id_pais, 'id_status' => 1];
        }
    }
    logChange($pdo, $id_usuario_actual, "ACTUALIZAR_LISTA", "clientes_nacionalidades", $id_cliente, $oldNacionalidades, $newNacionalidades);
    
    // Identificaciones
    $oldIdentificaciones = getOldData($pdo, 'clientes_identificaciones', $id_cliente);
    $pdo->prepare("DELETE FROM clientes_identificaciones WHERE id_cliente = ?")->execute([$id_cliente]);
    $newIdentificaciones = [];
    if (isset($data['ident_tipo'])) {
        $stmt_id = $pdo->prepare("INSERT INTO clientes_identificaciones (id_cliente, id_tipo_identificacion, numero_identificacion, fecha_vencimiento, id_status) VALUES (?, ?, ?, ?, 1)");
        foreach ($data['ident_tipo'] as $key => $tipo) {
            $numero = $data['ident_numero'][$key];
            $vencimiento = $data['ident_vencimiento'][$key] ?: null;
            $stmt_id->execute([ $id_cliente, $tipo, $numero, $vencimiento ]);
            $newIdentificaciones[] = ['id_cliente' => $id_cliente, 'id_tipo_identificacion' => $tipo, 'numero_identificacion' => $numero, 'fecha_vencimiento' => $vencimiento, 'id_status' => 1];
        }
    }
    logChange($pdo, $id_usuario_actual, "ACTUALIZAR_LISTA", "clientes_identificaciones", $id_cliente, $oldIdentificaciones, $newIdentificaciones);
    
    // Direcciones
    $oldDirecciones = getOldData($pdo, 'clientes_direcciones', $id_cliente);
    $pdo->prepare("DELETE FROM clientes_direcciones WHERE id_cliente = ?")->execute([$id_cliente]);
    $newDirecciones = [];
    if (isset($data['dir_calle'])) {
        $stmt_dir = $pdo->prepare("INSERT INTO clientes_direcciones (id_cliente, calle, colonia, codigo_postal) VALUES (?, ?, ?, ?)");
        foreach ($data['dir_calle'] as $key => $calle) {
            $colonia = $data['dir_colonia'][$key];
            $cp = $data['dir_cp'][$key];
            $stmt_dir->execute([ $id_cliente, $calle, $colonia, $cp ]);
            $newDirecciones[] = ['id_cliente' => $id_cliente, 'calle' => $calle, 'colonia' => $colonia, 'codigo_postal' => $cp];
        }
    }
    logChange($pdo, $id_usuario_actual, "ACTUALIZAR_LISTA", "clientes_direcciones", $id_cliente, $oldDirecciones, $newDirecciones);

    // Contactos
    $oldContactos = getOldData($pdo, 'clientes_contactos', $id_cliente);
    $pdo->prepare("DELETE FROM clientes_contactos WHERE id_cliente = ?")->execute([$id_cliente]);
    $newContactos = [];
    if (isset($data['contacto_id_tipo'])) {
         $stmt_con = $pdo->prepare("INSERT INTO clientes_contactos (id_cliente, id_tipo_contacto, dato_contacto, id_status) VALUES (?, ?, ?, 1)");
         foreach ($data['contacto_id_tipo'] as $key => $id_tipo_contacto) {
             $dato = $data['contacto_valor'][$key];
             $stmt_con->execute([ $id_cliente, $id_tipo_contacto, $dato ]);
             $newContactos[] = ['id_cliente' => $id_cliente, 'id_tipo_contacto' => $id_tipo_contacto, 'dato_contacto' => $dato, 'id_status' => 1];
         }
    }
    logChange($pdo, $id_usuario_actual, "ACTUALIZAR_LISTA", "clientes_contactos", $id_cliente, $oldContactos, $newContactos);
    
    // --- Documentos (Smart Update) ---
    $oldDocumentos = getOldData($pdo, 'clientes_documentos', $id_cliente);
    
    // 1. Cache existing paths before deletion
    // We store them to "recycle" the path if the user didn't upload a new file
    $existingPaths = [];
    $stmtCache = $pdo->prepare("SELECT descripcion, ruta FROM clientes_documentos WHERE id_cliente = ?");
    $stmtCache->execute([$id_cliente]);
    while ($row = $stmtCache->fetch(PDO::FETCH_ASSOC)) {
        // We use the description as a key to find it later
        // Note: If you have duplicates, this picks the last one, which is acceptable for this fix.
        $existingPaths[$row['descripcion']] = $row['ruta'];
    }

    // 2. Delete (Clean slate for DB rows)
    $pdo->prepare("DELETE FROM clientes_documentos WHERE id_cliente = ?")->execute([$id_cliente]);

    // 3. Re-insert with File Handling
    $newDocumentos = [];
    $uploadDir = '../uploads/clientes/' . $id_cliente . '/';
    
    // Ensure folder exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (isset($data['doc_tipo'])) {
        $stmt_doc = $pdo->prepare("INSERT INTO clientes_documentos (id_cliente, descripcion, ruta, fecha_vencimiento, id_status) VALUES (?, ?, ?, ?, 1)");
        
        foreach($data['doc_tipo'] as $key => $tipo) {
            $vencimiento = $data['doc_vencimiento'][$key] ?: null;
            $rutaToSave = null;

            // CHECK: Is there a NEW file uploaded?
            if (isset($_FILES['doc_file']['name'][$key]) && $_FILES['doc_file']['error'][$key] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['doc_file']['tmp_name'][$key];
                $extension = pathinfo($_FILES['doc_file']['name'][$key], PATHINFO_EXTENSION);
                // Sanitize filename
                $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '', $tipo) . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                $targetPath = $uploadDir . $cleanName;
                
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $uploaded_files_this_request[] = $targetPath;
                    $rutaToSave = $targetPath; // Use NEW path
                }
            } 
            // CHECK: If no new file, do we have an EXISTING one?
            elseif (isset($existingPaths[$tipo])) {
                $rutaToSave = $existingPaths[$tipo]; // Recycle OLD path
            }

            $stmt_doc->execute([ $id_cliente, $tipo, $rutaToSave, $vencimiento ]);
            
            $newDocumentos[] = [
                'id_cliente' => $id_cliente, 
                'descripcion' => $tipo, 
                'ruta' => $rutaToSave, 
                'fecha_vencimiento' => $vencimiento, 
                'id_status' => 1
            ];
        }
    }
    logChange($pdo, $id_usuario_actual, "ACTUALIZAR_LISTA", "clientes_documentos", $id_cliente, $oldDocumentos, $newDocumentos);

    // --- NEW: 4. Handle Apoderados (Delete All and Re-insert) ---
    
    // 4.1 Get all old apoderado IDs for this client
    $stmt_old_apos = $pdo->prepare("SELECT id_cliente_apoderado FROM clientes_apoderados WHERE id_cliente = ?");
    $stmt_old_apos->execute([$id_cliente]);
    $old_apo_ids = $stmt_old_apos->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($old_apo_ids)) {
        $in_clause = str_repeat('?,', count($old_apo_ids) - 1) . '?';
        
        // Delete in reverse order (child tables first)
        $pdo->prepare("DELETE FROM clientes_apoderados_contactos WHERE id_cliente_apoderado IN ($in_clause)")->execute($old_apo_ids);
        $pdo->prepare("DELETE FROM clientes_apoderados_fisicas WHERE id_cliente_apoderado IN ($in_clause)")->execute($old_apo_ids);
        $pdo->prepare("DELETE FROM clientes_apoderados_morales WHERE id_cliente_apoderado IN ($in_clause)")->execute($old_apo_ids);
        $pdo->prepare("DELETE FROM clientes_apoderados WHERE id_cliente_apoderado IN ($in_clause)")->execute($old_apo_ids);
    }
    
    // 4.2 Re-insert apoderados
    if (isset($data['apoderado'])) {
        foreach ($data['apoderado'] as $apoData) {
            $stmt_apo = $pdo->prepare("INSERT INTO clientes_apoderados (id_cliente, id_tipo_persona, fecha_alta) VALUES (?, ?, CURDATE())");
            $stmt_apo->execute([$id_cliente, $apoData['id_tipo_persona']]);
            $id_cliente_apoderado = $pdo->lastInsertId();
            
            $type_stmt_apo = $pdo->prepare("SELECT * FROM cat_tipo_persona WHERE id_tipo_persona = ?");
            $type_stmt_apo->execute([$apoData['id_tipo_persona']]);
            $apoPersonaType = $type_stmt_apo->fetch();

            if ($apoPersonaType['es_fisica'] > 0) {
                $stmt_apo_fis = $pdo->prepare("INSERT INTO clientes_apoderados_fisicas (id_cliente_apoderado, nombre, apellido_paterno, apellido_materno, tax_id, CURP) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_apo_fis->execute([$id_cliente_apoderado, $apoData['fisica_nombre'], $apoData['fisica_ap_paterno'], $apoData['fisica_ap_materno'], $apoData['fisica_tax_id'], $apoData['fisica_curp']]);
            } elseif ($apoPersonaType['es_moral'] > 0) {
                $stmt_apo_mor = $pdo->prepare("INSERT INTO clientes_apoderados_morales (id_cliente_apoderado, razon_social, tax_id) VALUES (?, ?, ?)");
                $stmt_apo_mor->execute([$id_cliente_apoderado, $apoData['moral_razon_social'], $apoData['moral_tax_id']]);
            }
            
            if (isset($apoData['contactos'])) {
                $stmt_apo_con = $pdo->prepare("INSERT INTO clientes_apoderados_contactos (id_cliente_apoderado, id_tipo_contacto, dato_contacto, id_status) VALUES (?, ?, ?, 1)");
                foreach ($apoData['contactos']['tipo'] as $key => $tipo) {
                    $valor = $apoData['contactos']['valor'][$key];
                    $stmt_apo_con->execute([$id_cliente_apoderado, $tipo, $valor]);
                }
            }
        }
    }

    // Recalculate Risk (still inside transaction)
    calculateClientRisk($pdo, $id_cliente);
    
    // --- VAL-PLD-005 y VAL-PLD-006: Validar expediente y actualizar fecha ---
    validateExpedienteCompleto($pdo, $id_cliente); // Actualiza flags
    actualizarFechaExpediente($pdo, $id_cliente); // Actualiza fecha de última actualización (VAL-PLD-006)
    // -------------------------------------------------------------------------

    // --- VAL-PLD-005/006: Bloquear actualización si expediente incompleto o vencido ---
    requireExpedienteCompleto($pdo, $id_cliente, false);
    // ---------------------------------------------------------------------------------

    // --- VAL-PLD-007: Procesar Beneficiarios Controladores ---
    if (isset($data['beneficiario']) && is_array($data['beneficiario'])) {
        $uploadDir = __DIR__ . '/../uploads/beneficiarios/' . $id_cliente . '/';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                throw new Exception('No se pudo crear el directorio para documentos de beneficiarios. Compruebe permisos en uploads/beneficiarios.');
            }
        }

        // Obtener beneficiarios existentes para mantener los que no se están editando
        $stmt_existing = $pdo->prepare("SELECT id_beneficiario FROM clientes_beneficiario_controlador WHERE id_cliente = ? AND id_status = 1");
        $stmt_existing->execute([$id_cliente]);
        $existing_ids = $stmt_existing->fetchAll(PDO::FETCH_COLUMN);
        $submitted_ids = [];
        
        foreach ($data['beneficiario'] as $key => $benefData) {
            $id_beneficiario = $benefData['id_beneficiario'] ?? null;
            $tipo_persona = $benefData['tipo_persona'] ?? null;
            $nombre_completo = $benefData['nombre_completo'] ?? null;
            $rfc = $benefData['rfc'] ?? null;
            $porcentaje_participacion = $benefData['porcentaje_participacion'] ?? null;
            
            if (!$tipo_persona || !$nombre_completo) {
                continue; // Skip invalid entries
            }
            
            if ($id_beneficiario) {
                $submitted_ids[] = $id_beneficiario;
            }
            
            // Handle file uploads
            $documento_identificacion = null;
            $declaracion_jurada = null;
            
            // Obtener rutas existentes si hay id_beneficiario
            if ($id_beneficiario) {
                $stmt_old = $pdo->prepare("SELECT documento_identificacion, declaracion_jurada FROM clientes_beneficiario_controlador WHERE id_beneficiario = ?");
                $stmt_old->execute([$id_beneficiario]);
                $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
                if ($old_data) {
                    $documento_identificacion = $old_data['documento_identificacion'];
                    $declaracion_jurada = $old_data['declaracion_jurada'];
                }
            }
            
            // Process documento_identificacion file (incluir $key para evitar sobrescritura entre beneficiarios)
            if (isset($_FILES['beneficiario']['name'][$key]['documento_identificacion']) && 
                $_FILES['beneficiario']['error'][$key]['documento_identificacion'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['beneficiario']['tmp_name'][$key]['documento_identificacion'];
                $baseName = basename($_FILES['beneficiario']['name'][$key]['documento_identificacion']);
                $fileName = time() . '_' . $key . '_' . bin2hex(random_bytes(4)) . '_' . $baseName;
                $filePath = $uploadDir . $fileName;
                if (move_uploaded_file($tmpName, $filePath)) {
                    $uploaded_files_this_request[] = $filePath;
                    $documento_identificacion = '../uploads/beneficiarios/' . $id_cliente . '/' . $fileName;
                }
            }
            
            // Process declaracion_jurada file (incluir $key para evitar sobrescritura entre beneficiarios)
            if (isset($_FILES['beneficiario']['name'][$key]['declaracion_jurada']) && 
                $_FILES['beneficiario']['error'][$key]['declaracion_jurada'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['beneficiario']['tmp_name'][$key]['declaracion_jurada'];
                $baseName = basename($_FILES['beneficiario']['name'][$key]['declaracion_jurada']);
                $fileName = time() . '_' . $key . '_' . bin2hex(random_bytes(4)) . '_' . $baseName;
                $filePath = $uploadDir . $fileName;
                if (move_uploaded_file($tmpName, $filePath)) {
                    $uploaded_files_this_request[] = $filePath;
                    $declaracion_jurada = '../uploads/beneficiarios/' . $id_cliente . '/' . $fileName;
                }
            }
            
            $benefPayload = [
                'id_cliente' => $id_cliente,
                'tipo_persona' => $tipo_persona,
                'nombre_completo' => $nombre_completo,
                'rfc' => $rfc,
                'porcentaje_participacion' => $porcentaje_participacion,
                'documento_identificacion' => $documento_identificacion,
                'declaracion_jurada' => $declaracion_jurada
            ];
            
            if ($id_beneficiario) {
                $benefPayload['id_beneficiario'] = $id_beneficiario;
            }
            
            $result = registrarBeneficiarioControlador($pdo, $benefPayload);
            if (empty($result['success'])) {
                throw new Exception($result['message'] ?? 'Error al registrar beneficiario controlador');
            }
            // Nuevos beneficiarios: añadir el ID devuelto a submitted_ids para que no se desactiven después
            if (empty($id_beneficiario) && !empty($result['id_beneficiario'])) {
                $submitted_ids[] = (int) $result['id_beneficiario'];
            }
        }
        
        // Desactivar beneficiarios que no fueron incluidos en el formulario
        $to_deactivate = array_diff($existing_ids, $submitted_ids);
        if (!empty($to_deactivate)) {
            $in_clause = str_repeat('?,', count($to_deactivate) - 1) . '?';
            $stmt_deactivate = $pdo->prepare("UPDATE clientes_beneficiario_controlador SET id_status = 0 WHERE id_beneficiario IN ($in_clause)");
            $stmt_deactivate->execute($to_deactivate);
        }
    }
    // -------------------------------------------------------------------------

    // If all successful (including beneficiarios and PLD), commit
    $pdo->commit();

    echo json_encode(['status' => 'success', 'id_cliente' => $id_cliente]);

} catch (Exception $e) {
    // Eliminar archivos subidos en esta petición para no dejar huérfanos al hacer rollback
    foreach ($uploaded_files_this_request as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>