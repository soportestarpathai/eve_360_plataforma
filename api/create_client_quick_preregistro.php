<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_middleware.php';
require_once __DIR__ . '/../config/pld_expediente.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

try {
    requirePLDHabilitado($pdo, true);
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'PLD no habilitado']);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payload inválido']);
    exit;
}

function quickNormalizeText(string $value): string {
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return mb_strtoupper($value, 'UTF-8');
}

function quickTableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function quickColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function quickHasContractColumns(PDO $pdo, string $table): bool {
    if (!quickTableExists($pdo, $table)) {
        return false;
    }
    $required = [
        'contrato_prefijo',
        'contrato_siguiente',
        'contrato_longitud',
        'contrato_rellenar_ceros',
    ];
    foreach ($required as $column) {
        if (!quickColumnExists($pdo, $table, $column)) {
            return false;
        }
    }
    return true;
}

function quickGenerateContract(PDO $pdo, int $userId): string {
    $cfg = null;
    $useUserConfig = false;

    if ($userId > 0 && quickHasContractColumns($pdo, 'config_empresa_usuario')) {
        $stmtU = $pdo->prepare("
            SELECT contrato_prefijo, contrato_siguiente, contrato_longitud, contrato_rellenar_ceros
            FROM config_empresa_usuario
            WHERE id_usuario = ?
            FOR UPDATE
        ");
        $stmtU->execute([$userId]);
        $cfg = $stmtU->fetch(PDO::FETCH_ASSOC) ?: null;
        $useUserConfig = ($cfg !== null);
    }

    if (!$cfg) {
        if (!quickHasContractColumns($pdo, 'config_empresa')) {
            throw new RuntimeException('Falta configuración de folios de contrato. Ejecute la migración de esquema.');
        }
        $stmtC = $pdo->query("
            SELECT id_config, contrato_prefijo, contrato_siguiente, contrato_longitud, contrato_rellenar_ceros
            FROM config_empresa
            WHERE id_config = 1
            FOR UPDATE
        ");
        $cfg = $stmtC ? ($stmtC->fetch(PDO::FETCH_ASSOC) ?: null) : null;
    }

    if (!$cfg) {
        throw new RuntimeException('No existe configuración para generar contrato.');
    }

    $prefix = trim((string)($cfg['contrato_prefijo'] ?? ''));
    $next = max(1, (int)($cfg['contrato_siguiente'] ?? 1));
    $length = (int)($cfg['contrato_longitud'] ?? 6);
    $fillZeros = (int)($cfg['contrato_rellenar_ceros'] ?? 1) === 1;

    if ($length < 1) $length = 1;
    if ($length > 12) $length = 12;

    $stmtExists = $pdo->prepare("SELECT id_cliente FROM clientes WHERE no_contrato = ? LIMIT 1");
    $generated = null;
    $usedSequence = null;
    $maxAttempts = 5000;

    for ($offset = 0; $offset < $maxAttempts; $offset++) {
        $candidateSeq = $next + $offset;
        $sequence = $fillZeros
            ? str_pad((string)$candidateSeq, $length, '0', STR_PAD_LEFT)
            : (string)$candidateSeq;
        $candidate = $prefix . $sequence;
        $stmtExists->execute([$candidate]);
        if (!$stmtExists->fetch(PDO::FETCH_ASSOC)) {
            $generated = $candidate;
            $usedSequence = $candidateSeq;
            break;
        }
    }

    if ($generated === null || $usedSequence === null) {
        throw new RuntimeException('No fue posible generar un No. de contrato disponible.');
    }

    if ($useUserConfig) {
        $stmtUpdUser = $pdo->prepare("UPDATE config_empresa_usuario SET contrato_siguiente = ? WHERE id_usuario = ?");
        $stmtUpdUser->execute([$usedSequence + 1, $userId]);
        if ($stmtUpdUser->rowCount() < 1) {
            $pdo->prepare("UPDATE config_empresa SET contrato_siguiente = ? WHERE id_config = 1")
                ->execute([$usedSequence + 1]);
        }
    } else {
        $pdo->prepare("UPDATE config_empresa SET contrato_siguiente = ? WHERE id_config = 1")
            ->execute([$usedSequence + 1]);
    }

    return $generated;
}

function quickSplitFullName(string $fullName): array {
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];
    $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));

    if (count($parts) === 0) {
        return ['SIN NOMBRE', 'PENDIENTE', ''];
    }
    if (count($parts) === 1) {
        return [$parts[0], 'PENDIENTE', ''];
    }
    if (count($parts) === 2) {
        return [$parts[0], $parts[1], ''];
    }

    $nombre = array_shift($parts);
    $apellidoPaterno = array_shift($parts);
    $apellidoMaterno = implode(' ', $parts);
    return [$nombre, $apellidoPaterno, $apellidoMaterno];
}

function quickFormatMissing(array $faltantes): string {
    $faltantes = array_values(array_filter(array_map('trim', $faltantes)));
    if (empty($faltantes)) {
        return 'completar expediente KYC';
    }
    return implode('; ', array_slice($faltantes, 0, 4));
}

$tipoPersonaInput = strtolower(trim((string)($payload['tipo_persona'] ?? 'fisica')));
$nombreCompleto = quickNormalizeText((string)($payload['nombre_completo'] ?? ''));
$curp = quickNormalizeText((string)($payload['curp'] ?? ''));
$folioIne = quickNormalizeText((string)($payload['folio_ine'] ?? ''));
$razonSocial = quickNormalizeText((string)($payload['razon_social'] ?? ''));
$rfcTaxId = quickNormalizeText((string)($payload['rfc_tax_id'] ?? ''));
$numeroFideicomiso = quickNormalizeText((string)($payload['numero_fideicomiso'] ?? ''));
$institucionFiduciaria = quickNormalizeText((string)($payload['institucion_fiduciaria'] ?? ''));
$idUsuario = (int)$_SESSION['user_id'];

if (!in_array($tipoPersonaInput, ['fisica', 'moral', 'fideicomiso'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tipo de persona inválido']);
    exit;
}

$curpRegex = '/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/';
$folioRegex = '/^[A-Z0-9\-]{6,40}$/';
$rfcTaxRegex = '/^[A-Z0-9Ñ&\-]{9,20}$/';
if (!preg_match($folioRegex, $folioIne)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Folio de identificación inválido']);
    exit;
}

if ($idUsuario <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesión inválida']);
    exit;
}

if ($tipoPersonaInput === 'fisica') {
    if ($nombreCompleto === '' || mb_strlen($nombreCompleto, 'UTF-8') < 5) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Nombre completo inválido']);
        exit;
    }
    if (!preg_match($curpRegex, $curp)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'CURP inválida']);
        exit;
    }
}

if ($tipoPersonaInput === 'moral') {
    if ($razonSocial === '' || mb_strlen($razonSocial, 'UTF-8') < 3) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Razón social inválida']);
        exit;
    }
    if (!preg_match($rfcTaxRegex, $rfcTaxId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'RFC / Tax ID inválido']);
        exit;
    }
}

if ($tipoPersonaInput === 'fideicomiso') {
    if ($numeroFideicomiso === '' || mb_strlen($numeroFideicomiso, 'UTF-8') < 3) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Número/identificador de fideicomiso inválido']);
        exit;
    }
    if ($rfcTaxId !== '' && !preg_match($rfcTaxRegex, $rfcTaxId)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'RFC / Tax ID inválido']);
        exit;
    }
}

try {
    $existingId = 0;
    if ($tipoPersonaInput === 'fisica') {
        $stmtDup = $pdo->prepare("
            SELECT c.id_cliente
            FROM clientes_fisicas cf
            INNER JOIN clientes c ON c.id_cliente = cf.id_cliente
            WHERE UPPER(TRIM(COALESCE(cf.CURP, ''))) = ?
              AND COALESCE(c.id_status, 1) != 4
            LIMIT 1
        ");
        $stmtDup->execute([$curp]);
        $existingId = (int)$stmtDup->fetchColumn();
    } elseif ($tipoPersonaInput === 'moral') {
        $stmtDup = $pdo->prepare("
            SELECT c.id_cliente
            FROM clientes_morales cm
            INNER JOIN clientes c ON c.id_cliente = cm.id_cliente
            WHERE UPPER(TRIM(COALESCE(cm.tax_id, ''))) = ?
              AND COALESCE(c.id_status, 1) != 4
            LIMIT 1
        ");
        $stmtDup->execute([$rfcTaxId]);
        $existingId = (int)$stmtDup->fetchColumn();
    } else {
        $stmtDup = $pdo->prepare("
            SELECT c.id_cliente
            FROM clientes_fideicomisos cf
            INNER JOIN clientes c ON c.id_cliente = cf.id_cliente
            WHERE UPPER(TRIM(COALESCE(cf.numero_fideicomiso, ''))) = ?
              AND COALESCE(c.id_status, 1) != 4
            LIMIT 1
        ");
        $stmtDup->execute([$numeroFideicomiso]);
        $existingId = (int)$stmtDup->fetchColumn();
    }

    if ($existingId > 0) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => 'Ya existe un cliente con ese dato identificador',
            'id_cliente' => $existingId
        ]);
        exit;
    }

    $stmtTipo = null;
    if ($tipoPersonaInput === 'fisica') {
        $stmtTipo = $pdo->query("SELECT id_tipo_persona FROM cat_tipo_persona WHERE es_fisica = 1 LIMIT 1");
    } elseif ($tipoPersonaInput === 'moral') {
        $stmtTipo = $pdo->query("SELECT id_tipo_persona FROM cat_tipo_persona WHERE es_moral = 1 LIMIT 1");
    } else {
        $stmtTipo = $pdo->query("SELECT id_tipo_persona FROM cat_tipo_persona WHERE es_fideicomiso = 1 LIMIT 1");
    }
    $idTipoPersona = $stmtTipo ? (int)$stmtTipo->fetchColumn() : 0;
    if ($idTipoPersona <= 0) {
        throw new RuntimeException('No se encontró el tipo de persona requerido.');
    }

    $stmtTipoId = $pdo->query("
        SELECT id_tipo_identificacion
        FROM cat_tipo_identificaciones
        WHERE UPPER(nombre) LIKE '%INE%'
           OR UPPER(nombre) LIKE '%CREDENCIAL%'
        ORDER BY id_tipo_identificacion ASC
        LIMIT 1
    ");
    $idTipoIdentificacion = (int)$stmtTipoId->fetchColumn();
    if ($idTipoIdentificacion <= 0) {
        $idTipoIdentificacion = 1;
    }

    $stmtPaisMx = $pdo->query("
        SELECT id_pais
        FROM cat_pais
        WHERE UPPER(clave) = 'MX'
           OR UPPER(nombre) LIKE '%MEXICO%'
           OR UPPER(nombre) LIKE '%MÉXICO%'
        ORDER BY CASE WHEN UPPER(clave) = 'MX' THEN 0 ELSE 1 END, id_pais
        LIMIT 1
    ");
    $idPaisMx = (int)$stmtPaisMx->fetchColumn();

    [$nombre, $apellidoPaterno, $apellidoMaterno] = quickSplitFullName($nombreCompleto);

    $pdo->beginTransaction();

    $noContrato = quickGenerateContract($pdo, $idUsuario);
    $hoy = (new DateTimeImmutable('today'))->format('Y-m-d');

    $alias = $nombreCompleto;
    if ($tipoPersonaInput === 'moral') {
        $alias = $razonSocial;
    } elseif ($tipoPersonaInput === 'fideicomiso') {
        $alias = $numeroFideicomiso;
    }

    $clientColumns = ['id_tipo_persona', 'no_contrato', 'alias', 'fecha_apertura', 'id_usuario', 'id_status', 'fecha_baja'];
    $clientValues = [$idTipoPersona, $noContrato, $alias, $hoy, $idUsuario, 2, null];

    if (quickColumnExists($pdo, 'clientes', 'identificacion_incompleta')) {
        $clientColumns[] = 'identificacion_incompleta';
        $clientValues[] = 1;
    }
    if (quickColumnExists($pdo, 'clientes', 'expediente_completo')) {
        $clientColumns[] = 'expediente_completo';
        $clientValues[] = 0;
    }

    $sqlInsertCliente = "INSERT INTO clientes (" . implode(', ', $clientColumns) . ")
                         VALUES (" . implode(', ', array_fill(0, count($clientColumns), '?')) . ")";
    $stmtCliente = $pdo->prepare($sqlInsertCliente);
    $stmtCliente->execute($clientValues);
    $idCliente = (int)$pdo->lastInsertId();

    if ($tipoPersonaInput === 'fisica') {
        $fisColumns = ['id_cliente', 'nombre', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento', 'tax_id', 'CURP', 'id_status'];
        $fisValues = [$idCliente, $nombre, $apellidoPaterno, $apellidoMaterno, null, null, $curp, 1];
        if ($idPaisMx > 0 && quickColumnExists($pdo, 'clientes_fisicas', 'id_pais_nacimiento')) {
            $fisColumns[] = 'id_pais_nacimiento';
            $fisValues[] = $idPaisMx;
        }
        $sqlInsertFis = "INSERT INTO clientes_fisicas (" . implode(', ', $fisColumns) . ")
                        VALUES (" . implode(', ', array_fill(0, count($fisColumns), '?')) . ")";
        $stmtFis = $pdo->prepare($sqlInsertFis);
        $stmtFis->execute($fisValues);
    } elseif ($tipoPersonaInput === 'moral') {
        $morColumns = ['id_cliente', 'razon_social', 'nombre_comercial', 'fecha_constitucion', 'id_actividad', 'tax_id', 'id_status'];
        $morValues = [$idCliente, $razonSocial, $razonSocial, null, null, $rfcTaxId, 1];
        if ($idPaisMx > 0 && quickColumnExists($pdo, 'clientes_morales', 'id_pais_nacionalidad')) {
            $morColumns[] = 'id_pais_nacionalidad';
            $morValues[] = $idPaisMx;
        }
        $sqlInsertMor = "INSERT INTO clientes_morales (" . implode(', ', $morColumns) . ")
                        VALUES (" . implode(', ', array_fill(0, count($morColumns), '?')) . ")";
        $stmtMor = $pdo->prepare($sqlInsertMor);
        $stmtMor->execute($morValues);
    } else {
        $fidColumns = ['id_cliente', 'numero_fideicomiso', 'institucion_fiduciaria', 'tax_id', 'id_status'];
        $fidValues = [$idCliente, $numeroFideicomiso, $institucionFiduciaria !== '' ? $institucionFiduciaria : null, $rfcTaxId !== '' ? $rfcTaxId : null, 1];
        $sqlInsertFid = "INSERT INTO clientes_fideicomisos (" . implode(', ', $fidColumns) . ")
                        VALUES (" . implode(', ', array_fill(0, count($fidColumns), '?')) . ")";
        $stmtFid = $pdo->prepare($sqlInsertFid);
        $stmtFid->execute($fidValues);
    }

    $idColumns = ['id_cliente', 'id_tipo_identificacion', 'numero_identificacion', 'fecha_vencimiento'];
    $idValues = [$idCliente, $idTipoIdentificacion, $folioIne, null];
    if (quickColumnExists($pdo, 'clientes_identificaciones', 'id_status')) {
        $idColumns[] = 'id_status';
        $idValues[] = 1;
    }
    $sqlInsertIdent = "INSERT INTO clientes_identificaciones (" . implode(', ', $idColumns) . ")
                       VALUES (" . implode(', ', array_fill(0, count($idColumns), '?')) . ")";
    $stmtIdent = $pdo->prepare($sqlInsertIdent);
    $stmtIdent->execute($idValues);

    if ($idPaisMx > 0 && quickTableExists($pdo, 'clientes_nacionalidades')) {
        $natColumns = ['id_cliente', 'id_pais'];
        $natValues = [$idCliente, $idPaisMx];
        if (quickColumnExists($pdo, 'clientes_nacionalidades', 'id_status')) {
            $natColumns[] = 'id_status';
            $natValues[] = 1;
        }
        $sqlNat = "INSERT INTO clientes_nacionalidades (" . implode(', ', $natColumns) . ")
                   VALUES (" . implode(', ', array_fill(0, count($natColumns), '?')) . ")";
        $stmtNat = $pdo->prepare($sqlNat);
        $stmtNat->execute($natValues);
    }

    $resultadoExpediente = validateExpedienteCompleto($pdo, $idCliente, true);
    $faltantes = is_array($resultadoExpediente['faltantes'] ?? null) ? $resultadoExpediente['faltantes'] : [];
    $faltantesTexto = quickFormatMissing($faltantes);

    if (quickTableExists($pdo, 'notificaciones')) {
        $tipoNotif = 'kyc_incompleto';
        $labelPersona = $tipoPersonaInput === 'fisica' ? $nombreCompleto : ($tipoPersonaInput === 'moral' ? $razonSocial : $numeroFideicomiso);
        $mensajeNotif = "Cliente preregistro incompleto {$noContrato} ({$labelPersona}). Faltantes: {$faltantesTexto}.";

        $stmtExisteNotif = $pdo->prepare("
            SELECT 1
            FROM notificaciones
            WHERE id_usuario = ?
              AND id_cliente = ?
              AND tipo = ?
              AND estado != 'descartado'
              AND fecha_generacion > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            LIMIT 1
        ");
        $stmtExisteNotif->execute([$idUsuario, $idCliente, $tipoNotif]);
        if (!$stmtExisteNotif->fetchColumn()) {
            $stmtNotif = $pdo->prepare("
                INSERT INTO notificaciones (id_usuario, id_cliente, tipo, mensaje)
                VALUES (?, ?, ?, ?)
            ");
            $stmtNotif->execute([$idUsuario, $idCliente, $tipoNotif, $mensajeNotif]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Cliente preregistrado correctamente.',
        'cliente' => [
            'id_cliente' => $idCliente,
            'id_tipo_persona' => $idTipoPersona,
            'tipo_persona' => $tipoPersonaInput,
            'no_contrato' => $noContrato,
            'nombre_cliente' => $alias,
            'curp' => $tipoPersonaInput === 'fisica' ? $curp : '',
            'rfc' => $tipoPersonaInput === 'fisica' ? '' : $rfcTaxId,
            'identificador' => $tipoPersonaInput === 'fisica'
                ? $curp
                : ($tipoPersonaInput === 'moral' ? $rfcTaxId : $numeroFideicomiso),
            'folio_ine' => $folioIne,
            'id_status' => 2,
            'identificacion_incompleta' => 1,
            'expediente_completo' => 0
        ],
        'faltantes' => $faltantes
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'No fue posible crear el preregistro rápido.'
    ]);
    error_log('create_client_quick_preregistro.php: ' . $e->getMessage());
}
