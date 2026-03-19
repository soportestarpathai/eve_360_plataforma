<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();
require_once '../config/db.php';
require_once '../config/risk_engine.php'; // Include engine
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id_cliente = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_cliente) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No client ID provided']);
    exit;
}

try {
    $details = [];

    // Helper to fetch related data (Generic fallback)
    function fetchRelated($pdo, $table, $id_cliente) {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE id_cliente = ?");
        $stmt->execute([$id_cliente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 1. Get main `clientes` data WITH Status Name
    // JOIN cat_status para obtener status_nombre
    $stmt = $pdo->prepare("
        SELECT c.*, s.nombre as status_nombre 
        FROM clientes c
        LEFT JOIN cat_status s ON c.id_status = s.id_status
        WHERE c.id_cliente = ? AND c.id_status != 4
    ");
    $stmt->execute([$id_cliente]);
    $details['general'] = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$details['general']) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado.']);
        exit;
    }

    // Verificar ownership: usuario no admin solo ve sus clientes
    $userId = $_SESSION['user_id'] ?? 0;
    $isAdmin = false;
    if ($userId > 0) {
        $stmtPerm = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
        $stmtPerm->execute([$userId]);
        $perm = $stmtPerm->fetch(PDO::FETCH_ASSOC);
        $isAdmin = $perm && (int)($perm['administracion'] ?? 0) > 0;
    }
    if (!$isAdmin && $userId > 0 && (int)($details['general']['id_usuario'] ?? 0) !== (int)$userId) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Acceso denegado. Este cliente no pertenece a tu cuenta.']);
        exit;
    }

    // 2. Get specific persona data (Fisica, Moral, Fideicomiso)
    $tipo_persona_id = $details['general']['id_tipo_persona'];
    $type_stmt = $pdo->prepare("SELECT * FROM cat_tipo_persona WHERE id_tipo_persona = ?");
    $type_stmt->execute([$tipo_persona_id]);
    $personaType = $type_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$personaType) {
        throw new Exception("Tipo de persona no encontrado.");
    }

    $personaRows = [];
    if ($personaType['es_fisica'] > 0) {
        $personaRows = fetchRelated($pdo, 'clientes_fisicas', $id_cliente);
    } elseif ($personaType['es_moral'] > 0) {
        $personaRows = fetchRelated($pdo, 'clientes_morales', $id_cliente);
    } elseif ($personaType['es_fideicomiso'] > 0) {
        $personaRows = fetchRelated($pdo, 'clientes_fideicomisos', $id_cliente);
    }
    $details['persona'] = (!empty($personaRows)) ? $personaRows[0] : null;
    $details['general']['tipo_persona_nombre'] = $personaType['nombre'];


    // 3. Get all related data from other tables WITH JOINS

    // A. Nacionalidades -> Join cat_pais [UPDATED]
    $stmt_nac = $pdo->prepare("
        SELECT cn.*, p.nombre as pais_nombre
        FROM clientes_nacionalidades cn
        LEFT JOIN cat_pais p ON cn.id_pais = p.id_pais
        WHERE cn.id_cliente = ?
    ");
    $stmt_nac->execute([$id_cliente]);
    $details['nacionalidades'] = $stmt_nac->fetchAll(PDO::FETCH_ASSOC);

    // B. Identificaciones -> Join cat_tipo_identificacion [UPDATED]
    $stmt_id = $pdo->prepare("
        SELECT ci.*, ti.nombre as tipo_identificacion_nombre
        FROM clientes_identificaciones ci
        LEFT JOIN cat_tipo_identificaciones ti ON ci.id_tipo_identificacion = ti.id_tipo_identificacion
        WHERE ci.id_cliente = ?
    ");
    $stmt_id->execute([$id_cliente]);
    $details['identificaciones'] = $stmt_id->fetchAll(PDO::FETCH_ASSOC);

    // C. Direcciones & Documentos (Usually text based, standard fetch is fine)
    $details['direcciones'] = fetchRelated($pdo, 'clientes_direcciones', $id_cliente);
    $details['documentos'] = fetchRelated($pdo, 'clientes_documentos', $id_cliente);
    
    // D. Contacts -> Join cat_tipo_contacto (Already present)
    $stmt_con = $pdo->prepare("
        SELECT cc.*, ctc.nombre as tipo_nombre 
        FROM clientes_contactos cc
        LEFT JOIN cat_tipo_contacto ctc ON cc.id_tipo_contacto = ctc.id_tipo_contacto
        WHERE cc.id_cliente = ?
    ");
    $stmt_con->execute([$id_cliente]);
    $details['contactos'] = $stmt_con->fetchAll(PDO::FETCH_ASSOC);

    
    // 4. Fetch Apoderados
    $details['apoderados'] = [];
    $apoderados = fetchRelated($pdo, 'clientes_apoderados', $id_cliente);
    
    foreach ($apoderados as $apo) {
        $id_apoderado = $apo['id_cliente_apoderado'];
        
        // UPDATED: Select 'nombre' to get the type name (e.g. "Persona Física")
        $stmt_apo_type = $pdo->prepare("SELECT nombre, es_fisica, es_moral FROM cat_tipo_persona WHERE id_tipo_persona = ?");
        $stmt_apo_type->execute([$apo['id_tipo_persona']]);
        $apoType = $stmt_apo_type->fetch(PDO::FETCH_ASSOC);
        if (!$apoType) {
            $apo['tipo_persona_nombre'] = 'N/A';
            $details['apoderados'][] = $apo;
            continue;
        }

        $apo['tipo_persona_nombre'] = $apoType['nombre']; // Add name to response

        if ($apoType['es_fisica'] > 0) {
            $stmt_apo_details = $pdo->prepare("SELECT * FROM clientes_apoderados_fisicas WHERE id_cliente_apoderado = ?");
            $stmt_apo_details->execute([$id_apoderado]);
            $apo['persona_data'] = $stmt_apo_details->fetch(PDO::FETCH_ASSOC);
        } elseif ($apoType['es_moral'] > 0) {
            $stmt_apo_details = $pdo->prepare("SELECT * FROM clientes_apoderados_morales WHERE id_cliente_apoderado = ?");
            $stmt_apo_details->execute([$id_apoderado]);
            $apo['persona_data'] = $stmt_apo_details->fetch(PDO::FETCH_ASSOC);
        }

        $stmt_apo_contacts = $pdo->prepare("
            SELECT cac.*, ctc.nombre as tipo_nombre 
            FROM clientes_apoderados_contactos cac
            LEFT JOIN cat_tipo_contacto ctc ON cac.id_tipo_contacto = ctc.id_tipo_contacto
            WHERE cac.id_cliente_apoderado = ?
        ");
        $stmt_apo_contacts->execute([$id_apoderado]);
        $apo['contactos'] = $stmt_apo_contacts->fetchAll(PDO::FETCH_ASSOC);
        
        $details['apoderados'][] = $apo;
    }
    
    // 5. Fetch Beneficiarios Controladores (VAL-PLD-007)
    $stmt_benef = $pdo->prepare("
        SELECT * FROM clientes_beneficiario_controlador 
        WHERE id_cliente = ? AND id_status = 1
        ORDER BY fecha_registro DESC
    ");
    $stmt_benef->execute([$id_cliente]);
    $details['beneficiarios_controladores'] = $stmt_benef->fetchAll(PDO::FETCH_ASSOC);

    // --- NEW: Fetch PLD Search History ---
    $stmt_pld = $pdo->prepare("
        SELECT 
            cbl.*, 
            u.nombre as usuario_nombre 
        FROM clientes_busquedas_listas cbl
        LEFT JOIN usuarios u ON cbl.id_usuario = u.id_usuario
        WHERE cbl.id_cliente = ?
        ORDER BY cbl.fecha_busqueda DESC
    ");
    $stmt_pld->execute([$id_cliente]);
    $details['pld_history'] = $stmt_pld->fetchAll(PDO::FETCH_ASSOC);
    
    // --- Get Real-time Risk Breakdown ---
    $riskData = calculateClientRisk($pdo, $id_cliente); 
    $details['risk_breakdown'] = $riskData;

    echo json_encode(['status' => 'success', 'data' => $details]);

} catch (Exception $e) {
    error_log('get_client_details: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno al obtener el detalle del cliente.']);
}
?>
