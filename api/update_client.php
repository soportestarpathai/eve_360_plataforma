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

    function _ucIsValidDateYmd($v) {
        if (!is_string($v) || $v === '') return false;
        $dt = DateTime::createFromFormat('Y-m-d', $v);
        return $dt && $dt->format('Y-m-d') === $v;
    }
    function _ucIsAtLeastYearsOld($v, $y) {
        if (!_ucIsValidDateYmd($v)) return false;
        $d = new DateTime($v);
        $limit = (new DateTime('today'))->modify("-{$y} years");
        return $d <= $limit;
    }
    function _ucIsFutureDateYmd($v) {
        if (!_ucIsValidDateYmd($v)) return false;
        return (new DateTime($v)) > (new DateTime('today'));
    }
    $curpRegex = '/^[A-Z][AEIOUX][A-Z]{2}[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9][0-9]$/';

    // Validación RFC / Tax ID (USA acepta EIN/SSN de 9 dígitos)
    $isTaxIdUSA = function ($v) {
        $d = preg_replace('/\D/', '', (string)$v);
        return strlen($d) === 9 && ctype_digit($d);
    };
    $hasUSA = false;
    if (!empty($data['nacionalidad_id']) && is_array($data['nacionalidad_id'])) {
        $stUSA = $pdo->query("SELECT id_pais FROM cat_pais WHERE clave = 'US' OR nombre LIKE '%Estados Unidos%' LIMIT 1");
        $usaRow = $stUSA ? $stUSA->fetch(PDO::FETCH_ASSOC) : null;
        if ($usaRow) {
            $usaId = (int)$usaRow['id_pais'];
            $nacIds = array_map('strval', $data['nacionalidad_id']);
            $hasUSA = in_array((string)$usaId, $nacIds, true);
        }
    }
    if ($personaType['es_fisica'] > 0) {
        $rfc = strtoupper(trim((string)($data['fisica_tax_id'] ?? '')));
        $curpFis = strtoupper(trim((string)($data['fisica_curp'] ?? '')));
        $fechaNac = trim((string)($data['fisica_fecha_nacimiento'] ?? ''));
        if (!_ucIsValidDateYmd($fechaNac)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Fecha de nacimiento inválida']);
            exit;
        }
        if (!_ucIsAtLeastYearsOld($fechaNac, 18)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'La persona física debe ser mayor de 18 años']);
            exit;
        }
        if ($rfc === '') {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'El RFC / Tax ID es obligatorio']);
            exit;
        }
        if ($hasUSA && !$isTaxIdUSA($rfc)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Tax ID inválido. Use EIN (9 dígitos) o SSN (XXX-XX-XXXX).']);
            exit;
        }
        if (!$hasUSA && !preg_match('/^[A-ZÑ&]{4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'RFC inválido para persona física']);
            exit;
        }
        if ($curpFis !== '' && !preg_match($curpRegex, $curpFis)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'CURP inválida']);
            exit;
        }
    } elseif ($personaType['es_moral'] > 0) {
        $rfcM = strtoupper(trim((string)($data['moral_tax_id'] ?? '')));
        $fechaConst = trim((string)($data['moral_fecha_constitucion'] ?? ''));
        if (!_ucIsValidDateYmd($fechaConst) || _ucIsFutureDateYmd($fechaConst)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Fecha de constitución inválida']);
            exit;
        }
        if ($rfcM === '') {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'El RFC / Tax ID es obligatorio']);
            exit;
        }
        if ($hasUSA && !$isTaxIdUSA($rfcM)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Tax ID inválido. Use EIN (9 dígitos) o formato XX-XXXXXXX.']);
            exit;
        }
        if (!$hasUSA && !preg_match('/^[A-ZÑ&]{3}[0-9]{6}[A-Z0-9]{3}$/u', $rfcM)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'RFC inválido para persona moral']);
            exit;
        }
    }

    if ($personaType['es_fisica'] > 0) {
        $oldDataFisica = getOldData($pdo, 'clientes_fisicas', $id_cliente);
        $stmt = $pdo->prepare(
            "UPDATE clientes_fisicas SET nombre = ?, apellido_paterno = ?, apellido_materno = ?, 
             fecha_nacimiento = ?, tax_id = ?, CURP = ?
             WHERE id_cliente = ?"
        );
        $stmt->execute([
            $data['fisica_nombre'], $data['fisica_ap_paterno'], $data['fisica_ap_materno'],
            $data['fisica_fecha_nacimiento'], $rfc, $curpFis,
            $id_cliente
        ]);
        $newDataFisica = [
            'nombre' => $data['fisica_nombre'], 'apellido_paterno' => $data['fisica_ap_paterno'], 'apellido_materno' => $data['fisica_ap_materno'],
            'fecha_nacimiento' => $data['fisica_fecha_nacimiento'], 'tax_id' => $rfc, 'CURP' => $curpFis
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
            $data['moral_razon_social'], $data['moral_fecha_constitucion'], $rfcM,
            $id_cliente
        ]);
        $newDataMoral = [
            'razon_social' => $data['moral_razon_social'], 'fecha_constitucion' => $data['moral_fecha_constitucion'], 'tax_id' => $rfcM
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
    $stmt_doc = $pdo->prepare("INSERT INTO clientes_documentos (id_cliente, descripcion, ruta, fecha_vencimiento, id_status) VALUES (?, ?, ?, ?, 1)");

    // Ensure folder exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $rfcCurpDescs = ['Constancia RFC - Persona Física', 'Documento CURP - Persona Física', 'Constancia RFC - Persona Moral'];

    if (isset($data['doc_tipo'])) {
        
        foreach($data['doc_tipo'] as $key => $tipo) {
            $tipoTrim = trim((string)$tipo);
            if (in_array($tipoTrim, $rfcCurpDescs, true)) continue; // RFC/CURP se manejan en campos dedicados

            $vencimiento = $data['doc_vencimiento'][$key] ?? null;
            $vencimiento = $vencimiento ?: null;
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

    // RFC / CURP: campos dedicados (persona física y moral)
    $saveRfcCurpDoc = function ($tmpName, $name, $desc) use ($id_cliente, $uploadDir, $stmt_doc, &$uploaded_files_this_request, &$newDocumentos) {
        $ext = pathinfo((string)$name, PATHINFO_EXTENSION);
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '', $desc) ?: 'doc';
        $target = $uploadDir . $clean . '_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? '.' . $ext : '');
        if (move_uploaded_file($tmpName, $target)) {
            $uploaded_files_this_request[] = $target;
            $stmt_doc->execute([$id_cliente, $desc, $target, null]);
            $newDocumentos[] = ['id_cliente' => $id_cliente, 'descripcion' => $desc, 'ruta' => $target, 'fecha_vencimiento' => null, 'id_status' => 1];
        }
    };

    if ($personaType['es_fisica'] > 0) {
        if (isset($_FILES['fisica_rfc_doc_file']) && ($_FILES['fisica_rfc_doc_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $saveRfcCurpDoc($_FILES['fisica_rfc_doc_file']['tmp_name'], $_FILES['fisica_rfc_doc_file']['name'], 'Constancia RFC - Persona Física');
        } elseif (isset($existingPaths['Constancia RFC - Persona Física'])) {
            $stmt_doc->execute([$id_cliente, 'Constancia RFC - Persona Física', $existingPaths['Constancia RFC - Persona Física'], null]);
            $newDocumentos[] = ['id_cliente' => $id_cliente, 'descripcion' => 'Constancia RFC - Persona Física', 'ruta' => $existingPaths['Constancia RFC - Persona Física'], 'fecha_vencimiento' => null, 'id_status' => 1];
        }
        if (isset($_FILES['fisica_curp_doc_file']) && ($_FILES['fisica_curp_doc_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $saveRfcCurpDoc($_FILES['fisica_curp_doc_file']['tmp_name'], $_FILES['fisica_curp_doc_file']['name'], 'Documento CURP - Persona Física');
        } elseif (isset($existingPaths['Documento CURP - Persona Física'])) {
            $stmt_doc->execute([$id_cliente, 'Documento CURP - Persona Física', $existingPaths['Documento CURP - Persona Física'], null]);
            $newDocumentos[] = ['id_cliente' => $id_cliente, 'descripcion' => 'Documento CURP - Persona Física', 'ruta' => $existingPaths['Documento CURP - Persona Física'], 'fecha_vencimiento' => null, 'id_status' => 1];
        }
    } elseif ($personaType['es_moral'] > 0) {
        if (isset($_FILES['moral_rfc_doc_file']) && ($_FILES['moral_rfc_doc_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $saveRfcCurpDoc($_FILES['moral_rfc_doc_file']['tmp_name'], $_FILES['moral_rfc_doc_file']['name'], 'Constancia RFC - Persona Moral');
        } elseif (isset($existingPaths['Constancia RFC - Persona Moral'])) {
            $stmt_doc->execute([$id_cliente, 'Constancia RFC - Persona Moral', $existingPaths['Constancia RFC - Persona Moral'], null]);
            $newDocumentos[] = ['id_cliente' => $id_cliente, 'descripcion' => 'Constancia RFC - Persona Moral', 'ruta' => $existingPaths['Constancia RFC - Persona Moral'], 'fecha_vencimiento' => null, 'id_status' => 1];
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