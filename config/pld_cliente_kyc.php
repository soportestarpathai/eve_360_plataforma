<?php
/**
 * Utilidades KYC para módulos PLD.
 * Centraliza la lectura de datos del cliente/KYC para prellenado y snapshot.
 */

if (!function_exists('pldTableExists')) {
    function pldTableExists(PDO $pdo, string $tableName): bool {
        static $cache = [];
        $key = 'tbl:' . strtolower($tableName);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$tableName]);
        $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
        return $cache[$key];
    }
}

if (!function_exists('pldColumnExists')) {
    function pldColumnExists(PDO $pdo, string $tableName, string $columnName): bool {
        static $cache = [];
        $key = 'col:' . strtolower($tableName) . ':' . strtolower($columnName);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$tableName, $columnName]);
        $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
        return $cache[$key];
    }
}

if (!function_exists('pldGetClienteKycData')) {
    /**
     * Obtiene datos KYC consolidados del cliente para PLD.
     */
    function pldGetClienteKycData(PDO $pdo, int $idCliente): ?array {
        if ($idCliente <= 0) {
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT c.id_cliente, c.id_tipo_persona, c.no_contrato, c.alias,
                   tp.nombre AS tipo_persona_nombre, tp.es_fisica, tp.es_moral, tp.es_fideicomiso
            FROM clientes c
            LEFT JOIN cat_tipo_persona tp ON c.id_tipo_persona = tp.id_tipo_persona
            WHERE c.id_cliente = ? AND c.id_status != 4
            LIMIT 1
        ");
        $stmt->execute([$idCliente]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cliente) {
            return null;
        }

        $kyc = [
            'id_cliente' => (int)$cliente['id_cliente'],
            'id_tipo_persona' => isset($cliente['id_tipo_persona']) ? (int)$cliente['id_tipo_persona'] : null,
            'no_contrato' => $cliente['no_contrato'] ?? null,
            'alias' => $cliente['alias'] ?? null,
            'tipo_persona' => $cliente['tipo_persona_nombre'] ?? null,
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
            'pais_nacionalidad_nombre' => null,
            'id_actividad' => null,
            'actividad_economica' => null,
            'empleo_actual' => null,
            'antiguedad_anios' => null,
            'id_origen_recursos' => null,
            'origen_recursos' => null,
            'id_ocupacion' => null,
            'ocupacion' => null,
            'id_profesion' => null,
            'profesion' => null,
            'nivel_estudios' => null,
            'tiene_familiar_pep' => 0,
            'nombre_familiar_pep' => null,
            'parentesco_familiar_pep' => null,
            'puesto_familiar_pep' => null,
            'fecha_ingreso_pep' => null
        ];

        if ($kyc['es_fisica'] === 1) {
            $stmt = $pdo->prepare("
                SELECT nombre, apellido_paterno, apellido_materno, fecha_nacimiento, tax_id, CURP
                FROM clientes_fisicas
                WHERE id_cliente = ?
                LIMIT 1
            ");
            $stmt->execute([$idCliente]);
            $pf = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($pf) {
                $kyc['nombre'] = $pf['nombre'] ?? null;
                $kyc['apellido_paterno'] = $pf['apellido_paterno'] ?? null;
                $kyc['apellido_materno'] = $pf['apellido_materno'] ?? null;
                $kyc['fecha_nacimiento'] = $pf['fecha_nacimiento'] ?? null;
                $kyc['rfc'] = $pf['tax_id'] ?? null;
                $kyc['curp'] = $pf['CURP'] ?? null;
                $kyc['denominacion_razon'] = trim(
                    ($pf['nombre'] ?? '') . ' ' .
                    ($pf['apellido_paterno'] ?? '') . ' ' .
                    ($pf['apellido_materno'] ?? '')
                );
            }
        } elseif ($kyc['es_moral'] === 1) {
            $stmt = $pdo->prepare("
                SELECT razon_social, fecha_constitucion, tax_id
                FROM clientes_morales
                WHERE id_cliente = ?
                LIMIT 1
            ");
            $stmt->execute([$idCliente]);
            $pm = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($pm) {
                $kyc['razon_social'] = $pm['razon_social'] ?? null;
                $kyc['denominacion_razon'] = $pm['razon_social'] ?? null;
                $kyc['fecha_constitucion'] = $pm['fecha_constitucion'] ?? null;
                $kyc['rfc'] = $pm['tax_id'] ?? null;
            }
        } elseif ($kyc['es_fideicomiso'] === 1) {
            $stmt = $pdo->prepare("
                SELECT denominacion, tax_id
                FROM clientes_fideicomisos
                WHERE id_cliente = ?
                LIMIT 1
            ");
            $stmt->execute([$idCliente]);
            $fi = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fi) {
                $kyc['denominacion_razon'] = $fi['denominacion'] ?? null;
                $kyc['rfc'] = $fi['tax_id'] ?? null;
            }
        }

        $codigoPaisCol = 'clave';
        if (pldColumnExists($pdo, 'cat_pais', 'codigo')) {
            $codigoPaisCol = 'codigo';
        } elseif (pldColumnExists($pdo, 'cat_pais', 'clave')) {
            $codigoPaisCol = 'clave';
        } elseif (pldColumnExists($pdo, 'cat_pais', 'codigo_iso')) {
            $codigoPaisCol = 'codigo_iso';
        }

        $stmt = $pdo->prepare("
            SELECT p.`{$codigoPaisCol}` AS codigo, p.nombre
            FROM clientes_nacionalidades cn
            LEFT JOIN cat_pais p ON cn.id_pais = p.id_pais
            WHERE cn.id_cliente = ?
            ORDER BY cn.id_cliente_nacionalidad ASC
            LIMIT 1
        ");
        $stmt->execute([$idCliente]);
        $nac = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($nac) {
            $kyc['pais_nacionalidad'] = !empty($nac['codigo']) ? strtoupper($nac['codigo']) : null;
            $kyc['pais_nacionalidad_nombre'] = $nac['nombre'] ?? null;
        }

        if (pldTableExists($pdo, 'clientes_kyc_info')) {
            $stmt = $pdo->prepare("
                SELECT k.id_actividad, a.nombre AS actividad_nombre, k.empleo_actual, k.antiguedad_anios,
                       k.id_origen_recursos, o.nombre AS origen_nombre, k.id_ocupacion, oc.nombre AS ocupacion_nombre,
                       k.id_profesion, p.nombre AS profesion_nombre, k.nivel_estudios, k.tiene_familiar_pep,
                       k.nombre_familiar_pep, k.parentesco_familiar_pep, k.puesto_familiar_pep, k.fecha_ingreso_pep
                FROM clientes_kyc_info k
                LEFT JOIN cat_actividades a ON k.id_actividad = a.id_actividad
                LEFT JOIN cat_origen_recursos o ON k.id_origen_recursos = o.id_origen_recursos
                LEFT JOIN cat_ocupacion oc ON k.id_ocupacion = oc.id_ocupacion
                LEFT JOIN cat_profesion p ON k.id_profesion = p.id_profesion
                WHERE k.id_cliente = ? AND (k.id_status = 1 OR k.id_status IS NULL)
                LIMIT 1
            ");
            $stmt->execute([$idCliente]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($info) {
                $kyc['id_actividad'] = $info['id_actividad'] !== null ? (int)$info['id_actividad'] : null;
                $kyc['actividad_economica'] = $info['actividad_nombre'] ?? null;
                $kyc['empleo_actual'] = $info['empleo_actual'] ?? null;
                $kyc['antiguedad_anios'] = $info['antiguedad_anios'] !== null ? (int)$info['antiguedad_anios'] : null;
                $kyc['id_origen_recursos'] = $info['id_origen_recursos'] !== null ? (int)$info['id_origen_recursos'] : null;
                $kyc['origen_recursos'] = $info['origen_nombre'] ?? null;
                $kyc['id_ocupacion'] = $info['id_ocupacion'] !== null ? (int)$info['id_ocupacion'] : null;
                $kyc['ocupacion'] = $info['ocupacion_nombre'] ?? null;
                $kyc['id_profesion'] = $info['id_profesion'] !== null ? (int)$info['id_profesion'] : null;
                $kyc['profesion'] = $info['profesion_nombre'] ?? null;
                $kyc['nivel_estudios'] = $info['nivel_estudios'] ?? null;
                $kyc['tiene_familiar_pep'] = (int)($info['tiene_familiar_pep'] ?? 0);
                $kyc['nombre_familiar_pep'] = $info['nombre_familiar_pep'] ?? null;
                $kyc['parentesco_familiar_pep'] = $info['parentesco_familiar_pep'] ?? null;
                $kyc['puesto_familiar_pep'] = $info['puesto_familiar_pep'] ?? null;
                $kyc['fecha_ingreso_pep'] = $info['fecha_ingreso_pep'] ?? null;
            }
        }

        return $kyc;
    }
}
