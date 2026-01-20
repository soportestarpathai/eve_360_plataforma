<?php
// api/get_transaction_rules.php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $response = [
        'is_vulnerable' => false,
        'uma_value' => 0,
        'rules' => []
    ];

    // 1. Check Company Configuration
    $stmtConfig = $pdo->query("SELECT id_tipo_empresa, id_vulnerable FROM config_empresa WHERE id_config = 1");
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);

    // Assuming ID 1 is "Actividad Vulnerable" based on previous context
    if ($config && $config['id_tipo_empresa'] == 1 && $config['id_vulnerable'] > 0) {
        $response['is_vulnerable'] = true;
        
        // 2. Get Latest UMA
        $stmtUMA = $pdo->prepare("SELECT valor FROM indicadores WHERE nombre LIKE '%UMA%' ORDER BY fecha DESC LIMIT 1");
        $stmtUMA->execute();
        $uma = $stmtUMA->fetchColumn();
        $response['uma_value'] = floatval($uma);

        // 3. Get Rules for this Activity
        $stmtRules = $pdo->prepare("SELECT * FROM vulnerables_reglas WHERE id_vulnerable = ?");
        $stmtRules->execute([$config['id_vulnerable']]);
        $response['rules'] = $stmtRules->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['status' => 'success', 'data' => $response]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>