<?php
/**
 * Limpia clientes de QA creados para pruebas.
 * Uso:
 *   php tools/cleanup_qa_clients.php
 *   php tools/cleanup_qa_clients.php "QA-MTX-%"
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

$pattern = 'QA-%';
if (isset($argv[1]) && is_string($argv[1]) && trim($argv[1]) !== '') {
    $pattern = trim($argv[1]);
}

function tableExists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND TABLE_TYPE = 'BASE TABLE'
    ");
    $stmt->execute([$tableName]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    $stmtClients = $pdo->prepare("SELECT id_cliente, no_contrato FROM clientes WHERE no_contrato LIKE ? ORDER BY id_cliente");
    $stmtClients->execute([$pattern]);
    $rows = $stmtClients->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        fwrite(STDOUT, "No hay clientes QA para limpiar con patrón: {$pattern}\n");
        exit(0);
    }

    $clientIds = array_map(fn($r) => (int)$r['id_cliente'], $rows);
    $clientIds = array_values(array_filter($clientIds, fn($id) => $id > 0));
    if (empty($clientIds)) {
        fwrite(STDOUT, "No hay IDs válidos para limpiar.\n");
        exit(0);
    }

    $ph = implode(',', array_fill(0, count($clientIds), '?'));
    $summary = [];

    $pdo->beginTransaction();

    // 1) Dependencias por apoderado
    $apoderadoIds = [];
    if (tableExists($pdo, 'clientes_apoderados')) {
        $stmtApo = $pdo->prepare("SELECT id_cliente_apoderado FROM clientes_apoderados WHERE id_cliente IN ($ph)");
        $stmtApo->execute($clientIds);
        $apoderadoIds = array_map('intval', $stmtApo->fetchAll(PDO::FETCH_COLUMN));
    }

    if (!empty($apoderadoIds)) {
        $phA = implode(',', array_fill(0, count($apoderadoIds), '?'));
        foreach (['clientes_apoderados_contactos', 'clientes_apoderados_fisicas', 'clientes_apoderados_morales'] as $table) {
            if (!tableExists($pdo, $table)) {
                continue;
            }
            $stmtDel = $pdo->prepare("DELETE FROM {$table} WHERE id_cliente_apoderado IN ($phA)");
            $stmtDel->execute($apoderadoIds);
            $summary[$table] = ($summary[$table] ?? 0) + $stmtDel->rowCount();
        }
    }

    // 2) Tablas que tengan id_cliente (excepto clientes)
    $stmtTables = $pdo->query("
        SELECT DISTINCT c.TABLE_NAME
        FROM INFORMATION_SCHEMA.COLUMNS c
        INNER JOIN INFORMATION_SCHEMA.TABLES t
            ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
           AND t.TABLE_NAME = c.TABLE_NAME
        WHERE c.TABLE_SCHEMA = DATABASE()
          AND c.COLUMN_NAME = 'id_cliente'
          AND c.TABLE_NAME <> 'clientes'
          AND t.TABLE_TYPE = 'BASE TABLE'
        ORDER BY c.TABLE_NAME
    ");
    $tables = $stmtTables->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $stmtDel = $pdo->prepare("DELETE FROM {$table} WHERE id_cliente IN ($ph)");
        $stmtDel->execute($clientIds);
        $summary[$table] = ($summary[$table] ?? 0) + $stmtDel->rowCount();
    }

    // 3) Principal
    $stmtClient = $pdo->prepare("DELETE FROM clientes WHERE id_cliente IN ($ph)");
    $stmtClient->execute($clientIds);
    $summary['clientes'] = $stmtClient->rowCount();

    $pdo->commit();

    fwrite(STDOUT, "Limpieza completada.\n");
    fwrite(STDOUT, "Patrón: {$pattern}\n");
    fwrite(STDOUT, "Clientes objetivo: " . count($clientIds) . "\n");
    foreach ($summary as $table => $count) {
        fwrite(STDOUT, $table . ": " . $count . "\n");
    }
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Error en limpieza QA: " . $e->getMessage() . "\n");
    exit(1);
}
