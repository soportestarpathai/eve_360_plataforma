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
    require_once '../config/pld_expediente.php'; // VAL-PLD-005, VAL-PLD-006: Validación de expediente
    require_once '../config/pld_beneficiario_controlador.php'; // VAL-PLD-007: Beneficiario Controlador

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
$data['no_contrato'] = trim((string)($data['no_contrato'] ?? ''));
$data['moral_nombre_comercial'] = trim((string)($data['moral_nombre_comercial'] ?? ''));
$data['fisica_tax_id'] = strtoupper(trim((string)($data['fisica_tax_id'] ?? '')));
$data['fisica_curp'] = strtoupper(trim((string)($data['fisica_curp'] ?? '')));
$data['moral_tax_id'] = strtoupper(trim((string)($data['moral_tax_id'] ?? '')));
$data['kyc_empleo_actual'] = trim((string)($data['kyc_empleo_actual'] ?? ''));
$data['kyc_nombre_familiar_pep'] = trim((string)($data['kyc_nombre_familiar_pep'] ?? ''));
$data['kyc_parentesco_familiar_pep'] = trim((string)($data['kyc_parentesco_familiar_pep'] ?? ''));
$data['kyc_puesto_familiar_pep'] = trim((string)($data['kyc_puesto_familiar_pep'] ?? ''));
$data['kyc_fecha_ingreso_pep'] = trim((string)($data['kyc_fecha_ingreso_pep'] ?? ''));
$data['kyc_nivel_estudios'] = trim((string)($data['kyc_nivel_estudios'] ?? ''));

function isValidDateYmd($dateValue) {
    if (!is_string($dateValue) || $dateValue === '') return false;
    $dt = DateTime::createFromFormat('Y-m-d', $dateValue);
    return $dt && $dt->format('Y-m-d') === $dateValue;
}

function isAtLeastYearsOld($dateValue, $years) {
    if (!isValidDateYmd($dateValue)) return false;
    $date = new DateTime($dateValue);
    $limit = new DateTime('today');
    $limit->modify("-{$years} years");
    return $date <= $limit;
}

function isFutureDateYmd($dateValue) {
    if (!isValidDateYmd($dateValue)) return false;
    $date = new DateTime($dateValue);
    $today = new DateTime('today');
    return $date > $today;
}

function tableColumnExists(PDO $pdo, string $tableName, string $columnName): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$tableName, $columnName]);
    return (int)$stmt->fetchColumn() > 0;
}

function tableExists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$tableName]);
    return (int)$stmt->fetchColumn() > 0;
}

function catalogValueExists(PDO $pdo, string $tableName, string $keyColumn, int $idValue): bool {
    if ($idValue <= 0) return false;
    if (!tableExists($pdo, $tableName)) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM {$tableName} WHERE {$keyColumn} = ? LIMIT 1");
    $stmt->execute([$idValue]);
    return (bool)$stmt->fetchColumn();
}

// Validate required fields before starting transaction
if (empty($data['id_tipo_persona'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tipo de persona es requerido']);
    exit;
}

if ($data['no_contrato'] === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Número de contrato es requerido']);
    exit;
}

$type_stmt = $pdo->prepare("SELECT * FROM cat_tipo_persona WHERE id_tipo_persona = ?");
$type_stmt->execute([$data['id_tipo_persona']]);
$personaType = $type_stmt->fetch(PDO::FETCH_ASSOC);
if (!$personaType) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tipo de persona inválido']);
    exit;
}

$rfcFisicaRegex = '/^[A-ZÑ&]{4}[0-9]{6}[A-Z0-9]{3}$/u';
$rfcMoralRegex = '/^[A-ZÑ&]{3}[0-9]{6}[A-Z0-9]{3}$/u';
$curpRegex = '/^[A-Z][AEIOUX][A-Z]{2}[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9][0-9]$/';

if ((int)$personaType['es_fisica'] > 0) {
    $fisNombre = trim((string)($data['fisica_nombre'] ?? ''));
    $fisPaterno = trim((string)($data['fisica_ap_paterno'] ?? ''));
    $fisFecha = trim((string)($data['fisica_fecha_nacimiento'] ?? ''));
    $fisRfc = $data['fisica_tax_id'];
    $fisCurp = $data['fisica_curp'];

    if ($fisNombre === '' || $fisPaterno === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Nombre y Apellido Paterno son obligatorios para persona física']);
        exit;
    }
    if (!isValidDateYmd($fisFecha)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Fecha de nacimiento inválida']);
        exit;
    }
    if (!isAtLeastYearsOld($fisFecha, 18)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'La persona física debe ser mayor de 18 años']);
        exit;
    }
    if ($fisRfc === '' || !preg_match($rfcFisicaRegex, $fisRfc)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'RFC inválido para persona física']);
        exit;
    }
    if ($fisCurp !== '' && !preg_match($curpRegex, $fisCurp)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'CURP inválida']);
        exit;
    }
}

if ((int)$personaType['es_moral'] > 0) {
    $morRazon = trim((string)($data['moral_razon_social'] ?? ''));
    $morFecha = trim((string)($data['moral_fecha_constitucion'] ?? ''));
    $morRfc = $data['moral_tax_id'];

    if ($morRazon === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Razón social obligatoria para persona moral']);
        exit;
    }
    if (!isValidDateYmd($morFecha) || isFutureDateYmd($morFecha)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Fecha de constitución inválida']);
        exit;
    }
    if ($morRfc === '' || !preg_match($rfcMoralRegex, $morRfc)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'RFC inválido para persona moral']);
        exit;
    }
}

$kycActividadId = isset($data['kyc_id_actividad']) && $data['kyc_id_actividad'] !== '' ? (int)$data['kyc_id_actividad'] : 0;
$kycAntiguedadAnios = isset($data['kyc_antiguedad_anios']) && $data['kyc_antiguedad_anios'] !== '' ? (int)$data['kyc_antiguedad_anios'] : -1;
$kycOrigenRecursosId = isset($data['kyc_id_origen_recursos']) && $data['kyc_id_origen_recursos'] !== '' ? (int)$data['kyc_id_origen_recursos'] : 0;
$kycOcupacionId = isset($data['kyc_id_ocupacion']) && $data['kyc_id_ocupacion'] !== '' ? (int)$data['kyc_id_ocupacion'] : null;
$kycProfesionId = isset($data['kyc_id_profesion']) && $data['kyc_id_profesion'] !== '' ? (int)$data['kyc_id_profesion'] : null;
$kycTieneFamiliarPepRaw = (string)($data['kyc_tiene_familiar_pep'] ?? '');
$kycTieneFamiliarPep = $kycTieneFamiliarPepRaw === '1' ? 1 : 0;

if ($kycActividadId <= 0 || !catalogValueExists($pdo, 'cat_actividades', 'id_actividad', $kycActividadId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar una actividad válida (KYC).']);
    exit;
}
if ($kycOrigenRecursosId <= 0 || !catalogValueExists($pdo, 'cat_origen_recursos', 'id_origen_recursos', $kycOrigenRecursosId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un origen de recursos válido (KYC).']);
    exit;
}
if ($kycAntiguedadAnios < 0 || $kycAntiguedadAnios > 120) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'La antigüedad (años) debe estar entre 0 y 120.']);
    exit;
}

if ((int)$personaType['es_fisica'] > 0) {
    if ($data['kyc_empleo_actual'] === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'El empleo actual es obligatorio para persona física.']);
        exit;
    }
    if (!$kycOcupacionId || !catalogValueExists($pdo, 'cat_ocupacion', 'id_ocupacion', $kycOcupacionId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar una ocupación válida para persona física.']);
        exit;
    }
    if ($data['kyc_nivel_estudios'] === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'El nivel de estudios es obligatorio para persona física.']);
        exit;
    }
    if ($kycTieneFamiliarPepRaw !== '0' && $kycTieneFamiliarPepRaw !== '1') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Indique si existe familiar directo políticamente expuesto.']);
        exit;
    }
    if ($kycTieneFamiliarPep === 1) {
        if ($data['kyc_parentesco_familiar_pep'] === '' || $data['kyc_nombre_familiar_pep'] === '' || $data['kyc_puesto_familiar_pep'] === '' || !isValidDateYmd($data['kyc_fecha_ingreso_pep']) || isFutureDateYmd($data['kyc_fecha_ingreso_pep'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Complete parentesco, nombre, puesto y fecha válida de ingreso del familiar PEP.']);
            exit;
        }
    }
} else {
    $kycOcupacionId = null;
    $data['kyc_empleo_actual'] = '';
    $data['kyc_nivel_estudios'] = '';
    $kycTieneFamiliarPep = 0;
    $data['kyc_nombre_familiar_pep'] = '';
    $data['kyc_parentesco_familiar_pep'] = '';
    $data['kyc_puesto_familiar_pep'] = '';
    $data['kyc_fecha_ingreso_pep'] = '';
}

if ($kycProfesionId !== null && !catalogValueExists($pdo, 'cat_profesion', 'id_profesion', $kycProfesionId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'La profesión seleccionada no es válida.']);
    exit;
}

// Paso 3 obligatorio: al menos una nacionalidad, identificación y dirección
if (empty($data['nacionalidad_id']) || !is_array($data['nacionalidad_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Debe capturar al menos una nacionalidad']);
    exit;
}
if (empty($data['ident_tipo']) || !is_array($data['ident_tipo']) || empty($data['ident_numero']) || !is_array($data['ident_numero'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Debe capturar al menos una identificación']);
    exit;
}
if (empty($data['dir_calle']) || !is_array($data['dir_calle']) || empty($data['dir_colonia']) || !is_array($data['dir_colonia']) || empty($data['dir_cp']) || !is_array($data['dir_cp'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Debe capturar al menos una dirección']);
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

/** Archivos subidos en esta petición; si hay rollback se eliminan para no dejar huérfanos */
$uploaded_files_this_request = [];

try {
    // Validación de duplicidad de contrato (defensa en servidor)
    $stmtDup = $pdo->prepare("SELECT id_cliente FROM clientes WHERE no_contrato = ? LIMIT 1");
    $stmtDup->execute([$data['no_contrato']]);
    $dup = $stmtDup->fetch(PDO::FETCH_ASSOC);
    if ($dup) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'code' => 'CONTRATO_DUPLICADO',
            'message' => 'El No. de contrato ya está registrado',
            'id_cliente' => (int)$dup['id_cliente']
        ]);
        exit;
    }

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
    if ($personaType['es_fisica'] > 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO clientes_fisicas (id_cliente, nombre, apellido_paterno, apellido_materno, fecha_nacimiento, id_ocupacion, id_profesion, tax_id, CURP, id_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([
            $id_cliente, $data['fisica_nombre'], $data['fisica_ap_paterno'], $data['fisica_ap_materno'],
            $data['fisica_fecha_nacimiento'], $kycOcupacionId, $kycProfesionId, $data['fisica_tax_id'], $data['fisica_curp']
        ]);
        // --- Log Change ---
        $newDataFisica = [
            'id_cliente' => $id_cliente, 'nombre' => $data['fisica_nombre'], 'apellido_paterno' => $data['fisica_ap_paterno'],
            'apellido_materno' => $data['fisica_ap_materno'], 'fecha_nacimiento' => $data['fisica_fecha_nacimiento'],
            'id_ocupacion' => $kycOcupacionId, 'id_profesion' => $kycProfesionId,
            'tax_id' => $data['fisica_tax_id'], 'CURP' => $data['fisica_curp']
        ];
        logChange($pdo, $id_usuario_actual, "CREAR", "clientes_fisicas", $id_cliente, null, $newDataFisica);
        // --- End Log ---
    } 
    elseif ($personaType['es_moral'] > 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO clientes_morales (id_cliente, razon_social, nombre_comercial, fecha_constitucion, id_actividad, tax_id, id_status)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->execute([
            $id_cliente, $data['moral_razon_social'], $data['moral_nombre_comercial'], $data['moral_fecha_constitucion'], $kycActividadId, $data['moral_tax_id']
        ]);
        // --- Log Change ---
        logChange($pdo, $id_usuario_actual, "CREAR", "clientes_morales", $id_cliente, null, [
            'id_cliente' => $id_cliente, 'razon_social' => $data['moral_razon_social'], 'nombre_comercial' => $data['moral_nombre_comercial'],
            'fecha_constitucion' => $data['moral_fecha_constitucion'], 'id_actividad' => $kycActividadId, 'tax_id' => $data['moral_tax_id']
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
            $numero = trim((string)($data['ident_numero'][$key] ?? ''));
            if ($tipo === '' || $numero === '') {
                throw new Exception('Cada identificación requiere tipo y número.');
            }
            $stmt_id->execute([$id_cliente, $tipo, $numero, $vencimiento]);
            $newDataIdent[] = ['id_cliente' => $id_cliente, 'id_tipo_identificacion' => $tipo, 'numero_identificacion' => $numero, 'fecha_vencimiento' => $vencimiento];
        }
        logChange($pdo, $id_usuario_actual, "CREAR_LISTA", "clientes_identificaciones", $id_cliente, null, $newDataIdent);
    }
    
    // 5. Loop and INSERT `clientes_direcciones`
    if (isset($data['dir_calle'])) {
        $hasLatitudColumn = tableColumnExists($pdo, 'clientes_direcciones', 'latitud');
        $hasLongitudColumn = tableColumnExists($pdo, 'clientes_direcciones', 'longitud');
        $supportsGeoColumns = $hasLatitudColumn && $hasLongitudColumn;

        if ($supportsGeoColumns) {
            $stmt_dir = $pdo->prepare("INSERT INTO clientes_direcciones (id_cliente, id_tipo_direccion, calle, colonia, delegacion, estado, id_pais, codigo_postal, latitud, longitud) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        } else {
            $stmt_dir = $pdo->prepare("INSERT INTO clientes_direcciones (id_cliente, id_tipo_direccion, calle, colonia, delegacion, estado, id_pais, codigo_postal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        }
        $newDataDir = [];
        foreach ($data['dir_calle'] as $key => $calle) {
            $tipoDireccion = isset($data['dir_tipo'][$key]) && $data['dir_tipo'][$key] !== '' ? (int)$data['dir_tipo'][$key] : null;
            $calleValue = trim((string)$calle);
            $colonia = trim((string)($data['dir_colonia'][$key] ?? ''));
            $municipio = trim((string)($data['dir_municipio'][$key] ?? ''));
            $estado = trim((string)($data['dir_estado'][$key] ?? ''));
            $idPais = isset($data['dir_pais'][$key]) && $data['dir_pais'][$key] !== '' ? (int)$data['dir_pais'][$key] : 157;
            $cp = trim((string)($data['dir_cp'][$key] ?? ''));
            $latRaw = trim((string)($data['dir_latitud'][$key] ?? ''));
            $lngRaw = trim((string)($data['dir_longitud'][$key] ?? ''));
            $latitud = (is_numeric($latRaw) && (float)$latRaw >= -90 && (float)$latRaw <= 90) ? (float)$latRaw : null;
            $longitud = (is_numeric($lngRaw) && (float)$lngRaw >= -180 && (float)$lngRaw <= 180) ? (float)$lngRaw : null;

            if (!$tipoDireccion || $calleValue === '' || $colonia === '' || $municipio === '' || $estado === '' || !preg_match('/^\d{5}$/', $cp)) {
                throw new Exception('Cada dirección requiere tipo, estado, municipio, calle, colonia y C.P. válido de 5 dígitos.');
            }

            if ($supportsGeoColumns) {
                $stmt_dir->execute([$id_cliente, $tipoDireccion, $calleValue, $colonia, $municipio, $estado, $idPais, $cp, $latitud, $longitud]);
            } else {
                $stmt_dir->execute([$id_cliente, $tipoDireccion, $calleValue, $colonia, $municipio, $estado, $idPais, $cp]);
            }

            $rowDir = [
                'id_cliente' => $id_cliente,
                'id_tipo_direccion' => $tipoDireccion,
                'calle' => $calleValue,
                'colonia' => $colonia,
                'delegacion' => $municipio,
                'estado' => $estado,
                'id_pais' => $idPais,
                'codigo_postal' => $cp
            ];
            if ($supportsGeoColumns) {
                $rowDir['latitud'] = $latitud;
                $rowDir['longitud'] = $longitud;
            }
            $newDataDir[] = $rowDir;
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

    // 7. INSERT perfil KYC complementario
    if (!tableExists($pdo, 'clientes_kyc_info')) {
        throw new Exception('Falta la tabla clientes_kyc_info. Ejecute la migración db/migrations/add_clientes_kyc_info.sql');
    }

    $stmt_kyc = $pdo->prepare("
        INSERT INTO clientes_kyc_info (
            id_cliente, id_actividad, empleo_actual, antiguedad_anios, id_origen_recursos,
            tiene_familiar_pep, nombre_familiar_pep, parentesco_familiar_pep, puesto_familiar_pep, fecha_ingreso_pep,
            id_ocupacion, id_profesion, nivel_estudios, id_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt_kyc->execute([
        $id_cliente,
        $kycActividadId,
        $data['kyc_empleo_actual'] !== '' ? $data['kyc_empleo_actual'] : null,
        $kycAntiguedadAnios,
        $kycOrigenRecursosId,
        $kycTieneFamiliarPep,
        $data['kyc_nombre_familiar_pep'] !== '' ? $data['kyc_nombre_familiar_pep'] : null,
        $data['kyc_parentesco_familiar_pep'] !== '' ? $data['kyc_parentesco_familiar_pep'] : null,
        $data['kyc_puesto_familiar_pep'] !== '' ? $data['kyc_puesto_familiar_pep'] : null,
        $data['kyc_fecha_ingreso_pep'] !== '' ? $data['kyc_fecha_ingreso_pep'] : null,
        $kycOcupacionId,
        $kycProfesionId,
        $data['kyc_nivel_estudios'] !== '' ? $data['kyc_nivel_estudios'] : null
    ]);
    logChange($pdo, $id_usuario_actual, "CREAR", "clientes_kyc_info", $id_cliente, null, [
        'id_cliente' => $id_cliente,
        'id_actividad' => $kycActividadId,
        'empleo_actual' => $data['kyc_empleo_actual'],
        'antiguedad_anios' => $kycAntiguedadAnios,
        'id_origen_recursos' => $kycOrigenRecursosId,
        'tiene_familiar_pep' => $kycTieneFamiliarPep,
        'nombre_familiar_pep' => $data['kyc_nombre_familiar_pep'],
        'parentesco_familiar_pep' => $data['kyc_parentesco_familiar_pep'],
        'puesto_familiar_pep' => $data['kyc_puesto_familiar_pep'],
        'fecha_ingreso_pep' => $data['kyc_fecha_ingreso_pep'],
        'id_ocupacion' => $kycOcupacionId,
        'id_profesion' => $kycProfesionId,
        'nivel_estudios' => $data['kyc_nivel_estudios']
    ]);
    
    // 8. INSERT `clientes_documentos` (Paso 4 + soportes de Paso 3)
    $hasAnyDocuments = (isset($data['doc_tipo']) && is_array($data['doc_tipo']))
        || isset($_FILES['nac_doc_file'])
        || isset($_FILES['ident_doc_file'])
        || isset($_FILES['dir_doc_file']);

    if ($hasAnyDocuments) {
        $stmt_doc = $pdo->prepare("INSERT INTO clientes_documentos (id_cliente, descripcion, ruta, fecha_vencimiento, id_status) VALUES (?, ?, ?, ?, 1)");
        $uploadDir = '../uploads/clientes/' . $id_cliente . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newDataDoc = [];
        $saveUploadedDocument = function ($tmpName, $originalName, $descripcion, $vencimiento = null) use ($stmt_doc, $id_cliente, $uploadDir, &$uploaded_files_this_request, &$newDataDoc) {
            $extension = pathinfo((string)$originalName, PATHINFO_EXTENSION);
            $cleanDesc = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$descripcion);
            if ($cleanDesc === '') {
                $cleanDesc = 'documento';
            }
            $cleanName = $cleanDesc . '_' . time() . '_' . bin2hex(random_bytes(4)) . ($extension ? '.' . $extension : '');
            $targetPath = $uploadDir . $cleanName;
            if (!move_uploaded_file($tmpName, $targetPath)) {
                throw new Exception('No fue posible guardar un documento de soporte.');
            }
            $uploaded_files_this_request[] = $targetPath;
            $stmt_doc->execute([$id_cliente, $descripcion, $targetPath, $vencimiento]);
            $newDataDoc[] = [
                'id_cliente' => $id_cliente,
                'descripcion' => $descripcion,
                'ruta' => $targetPath,
                'fecha_vencimiento' => $vencimiento
            ];
        };

        // Paso 4: Documentos generales (se mantiene comportamiento actual)
        if (isset($data['doc_tipo']) && is_array($data['doc_tipo'])) {
            foreach ($data['doc_tipo'] as $key => $tipo) {
                $descripcion = trim((string)$tipo);
                $vencimiento = $data['doc_vencimiento'][$key] ?: null;
                $rutaDB = null;

                if (isset($_FILES['doc_file']['name'][$key]) && $_FILES['doc_file']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['doc_file']['tmp_name'][$key];
                    $originalName = $_FILES['doc_file']['name'][$key];
                    $saveUploadedDocument($tmpName, $originalName, $descripcion !== '' ? $descripcion : 'Documento', $vencimiento);
                    continue;
                }

                $stmt_doc->execute([$id_cliente, $descripcion, $rutaDB, $vencimiento]);
                $newDataDoc[] = [
                    'id_cliente' => $id_cliente,
                    'descripcion' => $descripcion,
                    'ruta' => $rutaDB,
                    'fecha_vencimiento' => $vencimiento
                ];
            }
        }

        // Paso 3: soportes por nacionalidad
        if (isset($_FILES['nac_doc_file']['name']) && is_array($_FILES['nac_doc_file']['name'])) {
            foreach ($_FILES['nac_doc_file']['name'] as $key => $name) {
                if (($_FILES['nac_doc_file']['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $descripcion = 'Soporte Nacionalidad #' . ($key + 1);
                $saveUploadedDocument($_FILES['nac_doc_file']['tmp_name'][$key], $name, $descripcion, null);
            }
        }

        // Paso 3: soportes por identificación
        if (isset($_FILES['ident_doc_file']['name']) && is_array($_FILES['ident_doc_file']['name'])) {
            foreach ($_FILES['ident_doc_file']['name'] as $key => $name) {
                if (($_FILES['ident_doc_file']['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $descripcion = 'Soporte Identificación #' . ($key + 1);
                $saveUploadedDocument($_FILES['ident_doc_file']['tmp_name'][$key], $name, $descripcion, null);
            }
        }

        // Paso 3: soportes por dirección
        if (isset($_FILES['dir_doc_file']['name']) && is_array($_FILES['dir_doc_file']['name'])) {
            foreach ($_FILES['dir_doc_file']['name'] as $key => $name) {
                if (($_FILES['dir_doc_file']['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $descripcion = 'Comprobante Domicilio #' . ($key + 1);
                $saveUploadedDocument($_FILES['dir_doc_file']['tmp_name'][$key], $name, $descripcion, null);
            }
        }

        if (!empty($newDataDoc)) {
            logChange($pdo, $id_usuario_actual, "CREAR_LISTA", "clientes_documentos", $id_cliente, null, $newDataDoc);
        }
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

    // Calcular riesgo, responsable PLD y expediente DENTRO de la transacción
    calculateClientRisk($pdo, $id_cliente);

    require_once '../config/pld_responsable_validation.php';
    validateAndUpdateResponsablePLD($pdo, $id_cliente);

    validateExpedienteCompleto($pdo, $id_cliente);
    actualizarFechaExpediente($pdo, $id_cliente);

    // VAL-PLD-005/006: Bloquear guardado si expediente incompleto o vencido (retorna JSON estructurado)
    requireExpedienteCompleto($pdo, $id_cliente, true);

    // Si todo fue correcto (incluyendo validaciones), confirmar la transacción
    $pdo->commit();

    echo json_encode(['status' => 'success', 'id_cliente' => $id_cliente]);
    exit;

} catch (PDOException $e) {
    // Eliminar archivos subidos en esta petición para no dejar huérfanos al hacer rollback
    foreach ($uploaded_files_this_request as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
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
    foreach ($uploaded_files_this_request as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
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
    foreach ($uploaded_files_this_request as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
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
