<?php
// tools/recalc_all_risk.php
// Run this once in your browser to update all old clients
require_once '../config/db.php';
require_once '../config/risk_engine.php';

$stmt = $pdo->query("SELECT id_cliente FROM clientes");
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Starting recalculation for " . count($ids) . " clients...<br>";

foreach ($ids as $id) {
    $res = calculateClientRisk($pdo, $id);
    echo "Client ID $id: Risk " . $res['total'] . "%<br>";
}

echo "Done.";
?>