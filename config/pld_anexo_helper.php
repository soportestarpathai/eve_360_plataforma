<?php
/**
 * Helper: Determina el anexo de expediente aplicable según tipo de persona,
 * nacionalidad, tipo de residencia y clasificación de bajo riesgo (Art. 12, 17 RCG)
 */

if (!function_exists('getAnexoApplicable')) {
    /**
     * Determina qué anexo aplica al cliente (3, 4, 4 Bis, 5, 6, 6 Bis, 7, 7 Bis, 8)
     *
     * @param PDO $pdo
     * @param int $id_cliente
     * @param bool $actualizar Si true, actualiza id_anexo_applicable y expediente_simplificado en clientes
     * @return array ['id_anexo' => int, 'clave' => string, 'nombre' => string, 'simplificado' => bool, 'razon' => string]
     */
    function getAnexoApplicable(PDO $pdo, $id_cliente, $actualizar = false) {
        $id_cliente = (int) $id_cliente;
        if ($id_cliente <= 0) {
            return ['id_anexo' => null, 'clave' => null, 'nombre' => null, 'simplificado' => false, 'razon' => 'Cliente inválido'];
        }

        $stmt = $pdo->prepare("
            SELECT c.id_cliente, c.id_tipo_persona, c.clasificacion_bajo_riesgo, c.expediente_simplificado,
                   tp.es_fisica, tp.es_moral, tp.es_fideicomiso,
                   (SELECT id_pais FROM clientes_nacionalidades WHERE id_cliente = c.id_cliente AND id_status = 1 LIMIT 1) as id_pais_nacionalidad
            FROM clientes c
            LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
            WHERE c.id_cliente = ?
        ");
        $stmt->execute([$id_cliente]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cliente && $cliente['es_moral'] > 0 && _colExists($pdo, 'clientes_morales', 'id_anexo_7a')) {
            $stmtM = $pdo->prepare("SELECT id_anexo_7a, id_anexo_7_bis_a, id_pais_nacionalidad FROM clientes_morales WHERE id_cliente = ? AND id_status = 1 LIMIT 1");
            $stmtM->execute([$id_cliente]);
            $moral = $stmtM->fetch(PDO::FETCH_ASSOC);
            if ($moral) {
                $cliente['id_anexo_7a'] = $moral['id_anexo_7a'];
                $cliente['id_anexo_7_bis_a'] = $moral['id_anexo_7_bis_a'];
                if (!empty($moral['id_pais_nacionalidad'])) $cliente['pm_id_pais_nacionalidad'] = $moral['id_pais_nacionalidad'];
            }
        }
        if (!$cliente) {
            return ['id_anexo' => null, 'clave' => null, 'nombre' => null, 'simplificado' => false, 'razon' => 'Cliente no encontrado'];
        }

        $bajoRiesgo = !empty($cliente['clasificacion_bajo_riesgo']);
        $idPaisNac = !empty($cliente['pm_id_pais_nacionalidad']) ? (int) $cliente['pm_id_pais_nacionalidad']
            : (!empty($cliente['id_pais_nacionalidad']) ? (int) $cliente['id_pais_nacionalidad'] : null);

        // México = 157 en cat_pais (código MX)
        $stmtPais = $pdo->query("SELECT id_pais FROM cat_pais WHERE clave = 'MX' OR nombre LIKE '%México%' LIMIT 1");
        $mexicoId = $stmtPais ? (int) ($stmtPais->fetch(PDO::FETCH_COLUMN) ?: 157) : 157;
        $esMexicano = ($idPaisNac === $mexicoId);

        $idTipoResidencia = null;
        if (_colExists($pdo, 'clientes', 'id_tipo_residencia')) {
            $stmtTR = $pdo->prepare("SELECT id_tipo_residencia FROM clientes WHERE id_cliente = ?");
            $stmtTR->execute([$id_cliente]);
            $idTipoResidencia = $stmtTR->fetchColumn();
        }

        $idAnexo = null;
        $simplificado = false;
        $razon = '';

        if (!_tablaExiste($pdo, 'cat_anexo_expediente')) {
            return ['id_anexo' => null, 'clave' => null, 'nombre' => null, 'simplificado' => false, 'razon' => 'Ejecute migración add_expediente_anexo_kyc_ebr.sql'];
        }

        if ($cliente['es_fisica'] > 0) {
            // PERSONA FÍSICA
            if ($esMexicano || ($idTipoResidencia && in_array($idTipoResidencia, [2, 3]))) {
                // Mexicana o extranjera residente temporal/permanente → Anexo 3
                $idAnexo = _getIdAnexoByClave($pdo, 'ANEXO_3');
                $razon = $esMexicano ? 'PF mexicana' : 'PF extranjera residente';
            } else {
                // Extranjera visitante → Anexo 5 (regla: expediente completo, no simplificado)
                $idAnexo = _getIdAnexoByClave($pdo, 'ANEXO_5');
                $razon = 'PF extranjera visitante';
            }
        } elseif ($cliente['es_moral'] > 0) {
            $idAnexo7a = !empty($cliente['id_anexo_7a']) ? (int) $cliente['id_anexo_7a'] : null;
            $idAnexo7BisA = !empty($cliente['id_anexo_7_bis_a']) ? (int) $cliente['id_anexo_7_bis_a'] : null;

            if ($idAnexo7BisA && $bajoRiesgo) {
                $idAnexo = _getIdAnexoByClave($pdo, 'ANEXO_7_BIS');
                $simplificado = true;
                $razon = 'PM derecho público en 7 Bis-A, bajo riesgo';
            } elseif ($idAnexo7a && $bajoRiesgo && $esMexicano) {
                $idAnexo = _getIdAnexoByClave($pdo, 'ANEXO_7');
                $simplificado = true;
                $razon = 'PM en Anexo 7-A, bajo riesgo';
            } elseif ($esMexicano) {
                $idAnexo = _getIdAnexoByClave($pdo, 'ANEXO_4');
                $razon = 'PM mexicana';
            } else {
                $idAnexo = _getIdAnexoByClave($pdo, 'ANEXO_6');
                $razon = 'PM extranjera';
            }
        } elseif ($cliente['es_fideicomiso'] > 0) {
            $idAnexo = _getIdAnexoByClave($pdo, 'ANEXO_8');
            $razon = 'Fideicomiso';
        } else {
            $idAnexo = _getIdAnexoByClave($pdo, 'ANEXO_3');
            $razon = 'Por defecto (tipo no determinado)';
        }

        $anexoInfo = _getAnexoInfo($pdo, $idAnexo);

        if ($actualizar && $idAnexo) {
            $stmtUp = $pdo->prepare("UPDATE clientes SET id_anexo_applicable = ?, expediente_simplificado = ? WHERE id_cliente = ?");
            $stmtUp->execute([$idAnexo, $simplificado ? 1 : 0, $id_cliente]);
        }

        return [
            'id_anexo' => $idAnexo,
            'clave' => $anexoInfo['clave'] ?? null,
            'nombre' => $anexoInfo['nombre'] ?? null,
            'simplificado' => $simplificado,
            'razon' => $razon
        ];
    }

    function _getIdAnexoByClave(PDO $pdo, $clave) {
        $stmt = $pdo->prepare("SELECT id_anexo FROM cat_anexo_expediente WHERE clave = ? LIMIT 1");
        $stmt->execute([$clave]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    function _getAnexoInfo(PDO $pdo, $id_anexo) {
        if (!$id_anexo) return ['clave' => null, 'nombre' => null];
        $stmt = $pdo->prepare("SELECT clave, nombre FROM cat_anexo_expediente WHERE id_anexo = ?");
        $stmt->execute([$id_anexo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['clave' => null, 'nombre' => null];
    }

    function _colExists(PDO $pdo, $table, $col) {
        static $cache = [];
        $key = $table . '.' . $col;
        if (!isset($cache[$key])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$table, $col]);
            $cache[$key] = $stmt->fetchColumn() > 0;
        }
        return $cache[$key];
    }

    if (!function_exists('_tablaExiste')) {
        function _tablaExiste(PDO $pdo, $table) {
            $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool) $stmt->fetch();
        }
    }
}
