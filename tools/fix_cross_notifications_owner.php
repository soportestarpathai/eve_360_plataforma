<?php
/**
 * Reasigna notificaciones históricas al dueño real del cliente.
 *
 * Regla:
 * - Si una notificación tiene id_cliente y su id_usuario no coincide con clientes.id_usuario,
 *   se corrige para que apunte al dueño del cliente.
 *
 * Uso:
 *   php tools/fix_cross_notifications_owner.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

try {
    $sqlCount = "
        SELECT COUNT(*)
        FROM notificaciones n
        INNER JOIN clientes c ON c.id_cliente = n.id_cliente
        WHERE n.id_cliente IS NOT NULL
          AND c.id_usuario IS NOT NULL
          AND c.id_usuario > 0
          AND n.id_usuario <> c.id_usuario
    ";
    $before = (int)$pdo->query($sqlCount)->fetchColumn();

    if ($before <= 0) {
        fwrite(STDOUT, "No hay notificaciones cruzadas por corregir.\n");
        exit(0);
    }

    fwrite(STDOUT, "Notificaciones cruzadas detectadas: {$before}\n");

    $pdo->beginTransaction();

    $sqlFix = "
        UPDATE notificaciones n
        INNER JOIN clientes c ON c.id_cliente = n.id_cliente
        SET n.id_usuario = c.id_usuario
        WHERE n.id_cliente IS NOT NULL
          AND c.id_usuario IS NOT NULL
          AND c.id_usuario > 0
          AND n.id_usuario <> c.id_usuario
    ";
    $stmtFix = $pdo->prepare($sqlFix);
    $stmtFix->execute();
    $updated = $stmtFix->rowCount();

    $after = (int)$pdo->query($sqlCount)->fetchColumn();

    $pdo->commit();

    fwrite(STDOUT, "Notificaciones reasignadas: {$updated}\n");
    fwrite(STDOUT, "Cruces restantes: {$after}\n");
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Error al corregir notificaciones cruzadas: " . $e->getMessage() . "\n");
    exit(1);
}

