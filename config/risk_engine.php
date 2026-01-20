<?php
// config/risk_engine.php

if (!function_exists('calculateClientRisk')) {
    
    function calculateClientRisk($pdo, $id_cliente) {
        // ... (Existing Fetch Logic) ...
        // 1. Fetch Client Data
        $stmt = $pdo->prepare("SELECT c.id_cliente, c.id_tipo_persona, '0' as placeholder FROM clientes c WHERE c.id_cliente = ?");
        $stmt->execute([$id_cliente]);
        $clientData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$clientData) return 0;

        // 2. Fetch Risk Factors
        $factors = $pdo->query("SELECT * FROM config_factores_riesgo")->fetchAll(PDO::FETCH_ASSOC);
        
        $totalRiskScore = 0;
        $breakdown = []; 

        foreach ($factors as $factor) {
            // ... (Existing Logic Matching code: CASE A, CASE B...) ...
            $factorName = $factor['nombre_factor'];
            $weight = floatval($factor['peso_porcentaje']);
            $table = $factor['tabla_catalogo'];
            $riskValue = 0;
            $foundValueName = "No asignado";

            if ($table === 'cat_tipo_persona') {
                $valId = $clientData['id_tipo_persona'];
                $riskValue = getRiskValue($pdo, $factor['id_factor'], $valId);
                $foundValueName = getCatalogName($pdo, $table, 'id_tipo_persona', $valId);
            }
            elseif ($table === 'cat_pais') {
                $stmtNac = $pdo->prepare("SELECT id_pais FROM clientes_nacionalidades WHERE id_cliente = ? AND id_status = 1");
                $stmtNac->execute([$id_cliente]);
                $nacionalidades = $stmtNac->fetchAll(PDO::FETCH_COLUMN);
                
                $maxRisk = 0;
                $bestName = "Sin Nacionalidad";
                if (!empty($nacionalidades)) {
                    foreach ($nacionalidades as $idPais) {
                        $r = getRiskValue($pdo, $factor['id_factor'], $idPais);
                        if ($r >= $maxRisk) {
                            $maxRisk = $r;
                            $bestName = getCatalogName($pdo, $table, 'id_pais', $idPais);
                        }
                    }
                }
                $riskValue = $maxRisk;
                $foundValueName = $bestName . (count($nacionalidades) > 1 ? " (Más riesgosa)" : "");
            }

            $contribution = ($weight * $riskValue) / 100;
            $totalRiskScore += $contribution;

            $breakdown[] = [
                'factor' => $factorName,
                'weight' => $weight,
                'value_name' => $foundValueName,
                'risk_score' => $riskValue,
                'contribution' => $contribution
            ];
        }

        // 3. Update Client Record
        $stmtUpdate = $pdo->prepare("UPDATE clientes SET nivel_riesgo = ?, fecha_calculo_riesgo = NOW() WHERE id_cliente = ?");
        $stmtUpdate->execute([$totalRiskScore, $id_cliente]);

        // --- NEW: Determine Label and Color Dynamically ---
        // Fetch ranges from DB
        $ranges = $pdo->query("SELECT * FROM config_riesgo_rangos ORDER BY min_valor ASC")->fetchAll(PDO::FETCH_ASSOC);
        $finalLabel = "Desconocido";
        $finalColor = "#6c757d"; // Grey default

        foreach ($ranges as $r) {
            if ($totalRiskScore >= $r['min_valor'] && $totalRiskScore <= $r['max_valor']) {
                $finalLabel = $r['nivel'];
                $finalColor = $r['color_hex'];
                break;
            }
        }
        // --- END NEW ---

        return [
            'total' => $totalRiskScore,
            'label' => $finalLabel,   // Return Label
            'color' => $finalColor,   // Return Color
            'details' => $breakdown
        ];
    }

    // (Keep existing helpers getRiskValue, getCatalogName)
    function getRiskValue($pdo, $idFactor, $idValor) {
        if (!$idValor) return 0;
        $stmt = $pdo->prepare("SELECT nivel_riesgo FROM config_riesgo_valores WHERE id_factor = ? AND id_valor_catalogo = ?");
        $stmt->execute([$idFactor, $idValor]);
        $res = $stmt->fetch(PDO::FETCH_COLUMN);
        return $res ? floatval($res) : 0; 
    }

    function getCatalogName($pdo, $table, $pk, $id) {
        if (!$id) return "-";
        $allowedTables = ['cat_tipo_persona', 'cat_pais', 'cat_actividad', 'cat_profesion']; 
        if (!in_array($table, $allowedTables)) return "Tabla desconocida";
        
        $stmt = $pdo->prepare("SELECT nombre FROM $table WHERE $pk = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_COLUMN) ?: "Desconocido";
    }
}
?>