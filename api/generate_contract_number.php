<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

function ensureContractFolioColumns(PDO $pdo): void {
    $requiredColumns = [
        'contrato_prefijo' => "VARCHAR(20) NOT NULL DEFAULT ''",
        'contrato_siguiente' => "INT NOT NULL DEFAULT 1",
        'contrato_longitud' => "INT NOT NULL DEFAULT 6",
        'contrato_rellenar_ceros' => "TINYINT(1) NOT NULL DEFAULT 1"
    ];

    foreach ($requiredColumns as $column => $definition) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'config_empresa'
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$column]);
        $exists = (int)$stmt->fetchColumn() > 0;
        if (!$exists) {
            $pdo->exec("ALTER TABLE config_empresa ADD COLUMN {$column} {$definition}");
        }
    }
}

try {
    ensureContractFolioColumns($pdo);
    $pdo->beginTransaction();

    $stmtCfg = $pdo->query("
        SELECT id_config, contrato_prefijo, contrato_siguiente, contrato_longitud, contrato_rellenar_ceros
        FROM config_empresa
        WHERE id_config = 1
        FOR UPDATE
    ");
    $cfg = $stmtCfg->fetch(PDO::FETCH_ASSOC);

    if (!$cfg) {
        throw new RuntimeException('No existe configuración base de empresa (id_config = 1).');
    }

    $prefix = trim((string)($cfg['contrato_prefijo'] ?? ''));
    $next = max(1, (int)($cfg['contrato_siguiente'] ?? 1));
    $length = (int)($cfg['contrato_longitud'] ?? 6);
    $fillZeros = (int)($cfg['contrato_rellenar_ceros'] ?? 1) === 1;
    if ($length < 1) {
        $length = 1;
    } elseif ($length > 12) {
        $length = 12;
    }

    $generated = null;
    $usedSequence = null;
    $maxAttempts = 5000;

    $stmtExists = $pdo->prepare("SELECT id_cliente FROM clientes WHERE no_contrato = ? LIMIT 1");
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

    if ($generated === null) {
        throw new RuntimeException('No fue posible generar un No. de contrato disponible.');
    }

    $stmtUpd = $pdo->prepare("UPDATE config_empresa SET contrato_siguiente = ? WHERE id_config = 1");
    $stmtUpd->execute([$usedSequence + 1]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'no_contrato' => $generated,
        'config' => [
            'prefijo' => $prefix,
            'siguiente' => $usedSequence + 1,
            'longitud' => $length,
            'rellenar_ceros' => $fillZeros ? 1 : 0
        ]
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
