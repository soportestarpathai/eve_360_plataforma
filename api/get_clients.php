<?php
// 1. Prevent "Invisible Whitespace" errors
ob_start();
session_start();
require_once '../config/db.php';
ob_end_clean(); // Discard any prior output (spaces, newlines)

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $estatus = isset($_GET['estatus']) ? trim(strtolower($_GET['estatus'])) : '';
    $tipoPersona = isset($_GET['tipo_persona']) ? trim(strtolower($_GET['tipo_persona'])) : '';
    $nivelRiesgo = isset($_GET['nivel_riesgo']) ? trim(strtolower($_GET['nivel_riesgo'])) : '';
    $expediente = isset($_GET['expediente']) ? trim(strtolower($_GET['expediente'])) : '';
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';

    $conditions = ['c.id_status != 4']; // 4 = Eliminado (baja lógica)
    $params = [];

    // Filtro por estatus de cliente (tolerante a esquemas legacy/actuales)
    $statusExpr = "LOWER(TRIM(COALESCE(s.nombre, '')))";
    if ($estatus === 'activo' || $estatus === 'activos') {
        $conditions[] = "($statusExpr LIKE 'activo%' OR c.id_status = 1)";
    } elseif ($estatus === 'inactivo' || $estatus === 'inactivos') {
        $conditions[] = "(
            $statusExpr LIKE 'inactivo%'
            OR c.id_status = 0
            OR (c.id_status = 2 AND $statusExpr NOT LIKE 'pendiente%')
        )";
    } elseif ($estatus === 'pendiente' || $estatus === 'pendientes') {
        $conditions[] = "($statusExpr LIKE 'pendiente%')";
    } elseif ($estatus === 'cancelado' || $estatus === 'cancelados') {
        $conditions[] = "($statusExpr LIKE 'cancelado%' OR c.id_status = 3)";
    }

    // Filtro por tipo de persona
    if ($tipoPersona !== '') {
        if (is_numeric($tipoPersona)) {
            $conditions[] = 'c.id_tipo_persona = ?';
            $params[] = (int)$tipoPersona;
        } elseif ($tipoPersona === 'fisica') {
            $conditions[] = 'COALESCE(tp.es_fisica, 0) = 1';
        } elseif ($tipoPersona === 'moral') {
            $conditions[] = 'COALESCE(tp.es_moral, 0) = 1';
        } elseif ($tipoPersona === 'fideicomiso') {
            $conditions[] = 'COALESCE(tp.es_fideicomiso, 0) = 1';
        }
    }

    // Filtro por nivel de riesgo
    if ($nivelRiesgo !== '') {
        if ($nivelRiesgo === 'bajo') {
            $conditions[] = 'c.nivel_riesgo IS NOT NULL AND c.nivel_riesgo < 30';
        } elseif ($nivelRiesgo === 'medio') {
            $conditions[] = 'c.nivel_riesgo IS NOT NULL AND c.nivel_riesgo >= 30 AND c.nivel_riesgo < 70';
        } elseif ($nivelRiesgo === 'alto') {
            $conditions[] = 'c.nivel_riesgo IS NOT NULL AND c.nivel_riesgo >= 70';
        } elseif ($nivelRiesgo === 'sin_calcular') {
            $conditions[] = '(c.nivel_riesgo IS NULL OR c.nivel_riesgo = 0)';
        }
    }

    // Filtro por expediente PLD
    if ($expediente !== '') {
        if ($expediente === 'completo') {
            $conditions[] = 'COALESCE(c.expediente_completo, 0) = 1 AND COALESCE(c.identificacion_incompleta, 0) = 0';
        } elseif ($expediente === 'incompleto') {
            $conditions[] = '(COALESCE(c.identificacion_incompleta, 0) = 1 OR COALESCE(c.expediente_completo, 0) = 0)';
        }
    }

    // Búsqueda por texto libre
    if ($search !== '') {
        $conditions[] = "(
            c.no_contrato LIKE ?
            OR c.alias LIKE ?
            OR CONCAT(COALESCE(cf.nombre, ''), ' ', COALESCE(cf.apellido_paterno, ''), ' ', COALESCE(cf.apellido_materno, '')) LIKE ?
            OR cm.razon_social LIKE ?
            OR cf.tax_id LIKE ?
            OR cm.tax_id LIKE ?
        )";
        $searchLike = '%' . $search . '%';
        array_push($params, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
    }

    $sql = "
        SELECT 
            c.id_cliente,
            c.id_tipo_persona,
            c.no_contrato,
            c.fecha_apertura,
            c.nivel_riesgo,
            c.id_status,
            c.identificacion_incompleta,
            c.expediente_completo,
            c.fecha_ultima_actualizacion_expediente,
            COALESCE(tp.nombre, 'Sin Tipo') AS tipo_persona_nombre,
            COALESCE(s.nombre, 'Desconocido') AS status_nombre,
            
            -- Client Name Logic
            CASE 
                WHEN c.alias IS NOT NULL AND c.alias != '' THEN c.alias
                WHEN cf.nombre IS NOT NULL THEN CONCAT(cf.nombre, ' ', cf.apellido_paterno, ' ', COALESCE(cf.apellido_materno, ''))
                WHEN cm.razon_social IS NOT NULL THEN cm.razon_social
                ELSE 'Sin Nombre'
            END as nombre_cliente,

            -- RFC Logic
            CASE 
                WHEN cf.tax_id IS NOT NULL THEN cf.tax_id
                WHEN cm.tax_id IS NOT NULL THEN cm.tax_id
                ELSE 'N/A'
            END as rfc

        FROM clientes c
        LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
        LEFT JOIN cat_status s ON c.id_status = s.id_status
        LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
        LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY c.id_cliente DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($clients);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
