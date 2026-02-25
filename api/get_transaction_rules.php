<?php
// api/get_transaction_rules.php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

function parseFraccionesActivas($raw) {
    if ($raw === null || $raw === '') {
        return [];
    }

    if (is_array($raw)) {
        $values = $raw;
    } else {
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            $values = $decoded;
        } else {
            $values = array_map('trim', explode(',', (string)$raw));
        }
    }

    $out = [];
    foreach ($values as $v) {
        $s = trim((string)$v);
        if ($s !== '') {
            $out[] = $s;
        }
    }

    return array_values(array_unique($out));
}

try {
    $response = [
        'is_vulnerable' => false,
        'uma_value' => 0,
        'activities' => [],
        'rules' => [],
        'has_multiple_activities' => false
    ];

    $stmtConfig = $pdo->query("
        SELECT id_tipo_empresa, id_vulnerable, fracciones_activas
        FROM config_empresa
        WHERE id_config = 1
    ");
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);

    if (!$config || (int)$config['id_tipo_empresa'] !== 1) {
        echo json_encode(['status' => 'success', 'data' => $response]);
        exit;
    }

    $response['is_vulnerable'] = true;

    $stmtUMA = $pdo->prepare("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1");
    $stmtUMA->execute();
    $uma = $stmtUMA->fetchColumn();
    $response['uma_value'] = (float)$uma;

    $fraccionesActivas = parseFraccionesActivas($config['fracciones_activas'] ?? null);
    $activities = [];

    if (!empty($fraccionesActivas)) {
        $placeholders = implode(',', array_fill(0, count($fraccionesActivas), '?'));
        $stmtActivities = $pdo->prepare("
            SELECT id_vulnerable, nombre, fraccion
            FROM cat_vulnerables
            WHERE fraccion IN ($placeholders)
            ORDER BY fraccion ASC, nombre ASC
        ");
        $stmtActivities->execute($fraccionesActivas);
        $activities = $stmtActivities->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($activities) && (int)$config['id_vulnerable'] > 0) {
        $stmtActivity = $pdo->prepare("
            SELECT id_vulnerable, nombre, fraccion
            FROM cat_vulnerables
            WHERE id_vulnerable = ?
            LIMIT 1
        ");
        $stmtActivity->execute([(int)$config['id_vulnerable']]);
        $single = $stmtActivity->fetch(PDO::FETCH_ASSOC);
        if ($single) {
            $activities[] = $single;
        }
    }

    if (empty($activities)) {
        echo json_encode(['status' => 'success', 'data' => $response]);
        exit;
    }

    $ids = array_values(array_unique(array_map(static function($a) {
        return (int)($a['id_vulnerable'] ?? 0);
    }, $activities)));
    $ids = array_values(array_filter($ids, static function($id) {
        return $id > 0;
    }));

    $rulesByVulnerable = [];
    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmtRules = $pdo->prepare("
            SELECT
                id_vulnerable_regla,
                id_vulnerable,
                subactividad,
                monto_identificacion,
                comentarios_identificacion,
                es_siempre_identificacion,
                monto_aviso,
                comentarios_aviso,
                es_siempre_aviso,
                ruta_aviso
            FROM vulnerables_reglas
            WHERE id_vulnerable IN ($in)
            ORDER BY id_vulnerable ASC, id_vulnerable_regla ASC
        ");
        $stmtRules->execute($ids);
        $allRules = $stmtRules->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allRules as $rule) {
            $idV = (int)$rule['id_vulnerable'];
            if (!isset($rulesByVulnerable[$idV])) {
                $rulesByVulnerable[$idV] = [];
            }
            $rulesByVulnerable[$idV][] = $rule;
        }
    }

    $normalizedActivities = [];
    foreach ($activities as $activity) {
        $idV = (int)$activity['id_vulnerable'];
        $rules = $rulesByVulnerable[$idV] ?? [];
        $allAlways = !empty($rules);
        foreach ($rules as $r) {
            if ((int)($r['es_siempre_identificacion'] ?? 0) !== 1) {
                $allAlways = false;
                break;
            }
        }

        $normalizedActivities[] = [
            'id_vulnerable' => $idV,
            'nombre' => (string)($activity['nombre'] ?? ''),
            'fraccion' => (string)($activity['fraccion'] ?? ''),
            'rules' => $rules,
            'all_rules_always_identification' => $allAlways
        ];
    }

    $response['activities'] = $normalizedActivities;
    $response['has_multiple_activities'] = count($normalizedActivities) > 1;
    $response['rules'] = $normalizedActivities[0]['rules'] ?? [];

    echo json_encode(['status' => 'success', 'data' => $response]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
