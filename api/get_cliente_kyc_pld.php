<?php
/**
 * API: Datos KYC del cliente para formulario PLD (prellenado, solo lectura)
 * Usado en operacion_din.php para mostrar datos del cliente sin permitir edición
 */
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id_cliente = (int)($_GET['id'] ?? 0);
if (!$id_cliente) {
    echo json_encode(['status' => 'error', 'message' => 'id_cliente requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT c.id_cliente, c.id_tipo_persona, tp.nombre as tipo_persona_nombre,
               tp.es_fisica, tp.es_moral, tp.es_fideicomiso
        FROM clientes c
        LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
        WHERE c.id_cliente = ? AND c.id_status != 0
    ");
    $stmt->execute([$id_cliente]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado']);
        exit;
    }

    $kyc = [
        'id_cliente' => $cliente['id_cliente'],
        'tipo_persona' => $cliente['tipo_persona_nombre'],
        'es_fisica' => (int)($cliente['es_fisica'] ?? 0),
        'es_moral' => (int)($cliente['es_moral'] ?? 0),
        'es_fideicomiso' => (int)($cliente['es_fideicomiso'] ?? 0),
        'rfc' => null,
        'curp' => null,
        'nombre' => null,
        'apellido_paterno' => null,
        'apellido_materno' => null,
        'razon_social' => null,
        'denominacion_razon' => null,
        'fecha_nacimiento' => null,
        'fecha_constitucion' => null,
        'pais_nacionalidad' => null,
        'actividad_economica' => null,
        'giro_mercantil' => null,
    ];

    if ($cliente['es_fisica']) {
        $stmt = $pdo->prepare("SELECT nombre, apellido_paterno, apellido_materno, fecha_nacimiento, tax_id, CURP FROM clientes_fisicas WHERE id_cliente = ?");
        $stmt->execute([$id_cliente]);
        $pf = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($pf) {
            $kyc['nombre'] = $pf['nombre'] ?? null;
            $kyc['apellido_paterno'] = $pf['apellido_paterno'] ?? null;
            $kyc['apellido_materno'] = $pf['apellido_materno'] ?? null;
            $kyc['fecha_nacimiento'] = $pf['fecha_nacimiento'] ?? null;
            $kyc['rfc'] = $pf['tax_id'] ?? null;
            $kyc['curp'] = $pf['CURP'] ?? null;
            $kyc['denominacion_razon'] = trim(($pf['nombre'] ?? '') . ' ' . ($pf['apellido_paterno'] ?? '') . ' ' . ($pf['apellido_materno'] ?? ''));
        }
    } elseif ($cliente['es_moral']) {
        $stmt = $pdo->prepare("SELECT razon_social, fecha_constitucion, tax_id FROM clientes_morales WHERE id_cliente = ?");
        $stmt->execute([$id_cliente]);
        $pm = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($pm) {
            $kyc['razon_social'] = $pm['razon_social'] ?? null;
            $kyc['denominacion_razon'] = $pm['razon_social'] ?? null;
            $kyc['fecha_constitucion'] = $pm['fecha_constitucion'] ?? null;
            $kyc['rfc'] = $pm['tax_id'] ?? null;
        }
    } elseif ($cliente['es_fideicomiso']) {
        $stmt = $pdo->prepare("SELECT denominacion, tax_id FROM clientes_fideicomisos WHERE id_cliente = ?");
        $stmt->execute([$id_cliente]);
        $fi = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fi) {
            $kyc['denominacion_razon'] = $fi['denominacion'] ?? null;
            $kyc['rfc'] = $fi['tax_id'] ?? null;
        }
    }

    // Nacionalidad (primer registro)
    $stmt = $pdo->prepare("SELECT p.codigo FROM clientes_nacionalidades cn LEFT JOIN cat_pais p ON cn.id_pais = p.id_pais WHERE cn.id_cliente = ? LIMIT 1");
    $stmt->execute([$id_cliente]);
    $nac = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($nac && !empty($nac['codigo'])) {
        $kyc['pais_nacionalidad'] = strtoupper($nac['codigo']);
    }

    echo json_encode(['status' => 'success', 'kyc' => $kyc]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
