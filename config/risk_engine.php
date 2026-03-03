<?php
// config/risk_engine.php
// Motor EBR: calcula nivel de riesgo usando TODOS los factores configurados en config_ebr.php

if (!function_exists('calculateClientRisk')) {
    
    function calculateClientRisk($pdo, $id_cliente) {
        $stmt = $pdo->prepare("SELECT id_cliente, id_tipo_persona FROM clientes WHERE id_cliente = ?");
        $stmt->execute([$id_cliente]);
        $clientData = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$clientData) return 0;

        $factors = $pdo->query("SELECT * FROM config_factores_riesgo ORDER BY id_factor")->fetchAll(PDO::FETCH_ASSOC);
        $totalRiskScore = 0;
        $breakdown = []; 

        foreach ($factors as $factor) {
            $factorName = $factor['nombre_factor'];
            $weight = floatval($factor['peso_porcentaje']);
            $table = trim($factor['tabla_catalogo'] ?? '');
            $campoClave = trim($factor['campo_clave'] ?? '');
            $campoNombre = trim($factor['campo_nombre'] ?? 'nombre');
            $riskValue = 0;
            $foundValueName = "No asignado";

            if ($table === 'cat_tipo_persona') {
                $valId = $clientData['id_tipo_persona'] ?? null;
                $riskValue = getRiskValue($pdo, $factor['id_factor'], $valId);
                $foundValueName = getCatalogName($pdo, $table, $campoClave ?: 'id_tipo_persona', $campoNombre, $valId);
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
                            $bestName = getCatalogName($pdo, $table, 'id_pais', 'nombre', $idPais);
                        }
                    }
                }
                $riskValue = $maxRisk;
                $foundValueName = $bestName . (count($nacionalidades) > 1 ? " (Más riesgosa)" : "");
            }
            elseif ($table === 'cat_rango_edades') {
                $valId = getClientIdRangoEdad($pdo, $id_cliente);
                $riskValue = getRiskValue($pdo, $factor['id_factor'], $valId);
                $foundValueName = getCatalogName($pdo, $table, 'id_rango_edad', 'nombre', $valId);
            }
            else {
                // Detección dinámica: cualquier factor con tabla_catalogo + campo_clave se busca en tablas de cliente
                if ($campoClave !== '') {
                    $valId = getClientCatalogValueGeneric($pdo, $id_cliente, $table, $campoClave);
                    if ($valId !== null) {
                        $riskValue = getRiskValue($pdo, $factor['id_factor'], $valId);
                        $foundValueName = getCatalogName($pdo, $table, $campoClave, $campoNombre, $valId);
                    }
                }
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

        $stmtUpdate = $pdo->prepare("UPDATE clientes SET nivel_riesgo = ?, fecha_calculo_riesgo = NOW() WHERE id_cliente = ?");
        $stmtUpdate->execute([$totalRiskScore, $id_cliente]);

        $ranges = $pdo->query("SELECT * FROM config_riesgo_rangos ORDER BY min_valor ASC")->fetchAll(PDO::FETCH_ASSOC);
        $finalLabel = "Desconocido";
        $finalColor = "#6c757d";

        foreach ($ranges as $r) {
            if ($totalRiskScore >= floatval($r['min_valor']) && $totalRiskScore <= floatval($r['max_valor'])) {
                $finalLabel = $r['nivel'];
                $finalColor = $r['color_hex'] ?? $finalColor;
                break;
            }
        }

        return [
            'total' => $totalRiskScore,
            'label' => $finalLabel,
            'color' => $finalColor,
            'details' => $breakdown
        ];
    }

    function getRiskValue($pdo, $idFactor, $idValor) {
        if ($idValor === null || $idValor === '') return 0;
        $stmt = $pdo->prepare("SELECT nivel_riesgo FROM config_riesgo_valores WHERE id_factor = ? AND id_valor_catalogo = ?");
        $stmt->execute([$idFactor, (int)$idValor]);
        $res = $stmt->fetch(PDO::FETCH_COLUMN);
        return $res ? floatval($res) : 0; 
    }

    function getCatalogName($pdo, $table, $pk, $nameCol, $id) {
        if (!$id) return "-";
        // Dynamic: validate identifiers (prevent SQL injection) and verify table/columns exist
        if (!preg_match('/^[a-z][a-z0-9_]*$/i', $table) || !preg_match('/^[a-z][a-z0-9_]*$/i', $pk) || !preg_match('/^[a-z][a-z0-9_]*$/i', $nameCol)) {
            return "Desconocido";
        }
        try {
            $chk = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
            $chk->execute([$table, $pk]);
            if (!$chk->fetch()) return "Desconocido";
            $chk2 = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
            $chk2->execute([$table, $nameCol]);
            if (!$chk2->fetch()) return "Desconocido";
            $stmt = $pdo->prepare("SELECT `{$nameCol}` FROM `{$table}` WHERE `{$pk}` = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_COLUMN) ?: "Desconocido";
        } catch (Throwable $e) {
            return "Desconocido";
        }
    }

    function getClientIdRangoEdad($pdo, $id_cliente) {
        // cat_rango_edades applies to person age only; moral persons have no birth date
        $stmt = $pdo->prepare("SELECT fecha_nacimiento FROM clientes_fisicas WHERE id_cliente = ? LIMIT 1");
        $stmt->execute([$id_cliente]);
        $fecha = $stmt->fetch(PDO::FETCH_COLUMN);
        if (!$fecha) return null; // Moral person: no birth date, age range N/A
        try {
            $birth = DateTime::createFromFormat('Y-m-d', (string)$fecha);
            if (!$birth || $birth->format('Y-m-d') !== (string)$fecha) return null;
            $anios = (int)$birth->diff(new DateTime())->y;
        } catch (Throwable $e) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT id_rango_edad FROM cat_rango_edades WHERE ? BETWEEN min_valor AND max_valor LIMIT 1");
        $stmt->execute([$anios]);
        $val = $stmt->fetch(PDO::FETCH_COLUMN);
        return $val ? (int)$val : null;
    }

    function getClientCatalogValueGeneric($pdo, $id_cliente, $table, $campoClave) {
        $campoClave = trim((string)$campoClave);
        if ($campoClave === '' || !preg_match('/^[a-z][a-z0-9_]*$/i', $campoClave)) return null;
        // Orden preferido por campo (igual que funciones antiguas): actividad→moral/kyc, profesion→fisica/kyc, origen→kyc, tipo_persona→clientes
        $preferredOrder = [
            'id_tipo_persona' => ['clientes'],
            'id_actividad' => ['clientes_morales', 'clientes_kyc_info'],
            'id_profesion' => ['clientes_fisicas', 'clientes_kyc_info'],
            'id_ocupacion' => ['clientes_fisicas', 'clientes_kyc_info'],
            'id_origen_recursos' => ['clientes_kyc_info'],
        ];
        $sourceTables = $preferredOrder[$campoClave] ?? ['clientes_morales', 'clientes_fisicas', 'clientes_kyc_info', 'clientes'];
        $tablesWithColumn = [];
        foreach ($sourceTables as $src) {
            if (!_riskTableExists($pdo, $src)) continue;
            try {
                $stmt = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
                $stmt->execute([$src, $campoClave]);
                if ($stmt->fetch()) $tablesWithColumn[] = $src;
            } catch (Throwable $e) { /* skip */ }
        }
        foreach ($tablesWithColumn as $src) {
            try {
                $hasStatus = in_array($src, ['clientes_morales', 'clientes_kyc_info'], true);
                $sql = sprintf("SELECT `%s` FROM `%s` WHERE id_cliente = ?", $campoClave, $src);
                if ($hasStatus) $sql .= " AND id_status = 1";
                $sql .= " LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_cliente]);
                $val = $stmt->fetch(PDO::FETCH_COLUMN);
                if ($val !== null && $val !== false && $val !== '') return (int)$val;
            } catch (Throwable $e) { /* try next table */ }
        }
        return null;
    }

    function _riskTableExists($pdo, $name) {
        try {
            $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($name) . " LIMIT 1");
            return $stmt && $stmt->fetch();
        } catch (Throwable $e) {
            return false;
        }
    }
}
?>
