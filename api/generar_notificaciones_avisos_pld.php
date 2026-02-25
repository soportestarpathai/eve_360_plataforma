<?php
/**
 * Genera notificaciones para avisos PLD por vencer o vencidos sin folio SAT.
 * Se invoca al cargar operaciones_pld.php o por cron.
 * Reglas: aviso por vencer = deadline en 1-7 días sin folio; vencido = deadline pasado sin folio.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

// Puede llamarse sin sesión (cron) o con sesión (al cargar página)
$id_usuario_actual = $_SESSION['user_id'] ?? null;
if (!$id_usuario_actual && php_sapi_name() !== 'cli') {
    // Si es petición web sin sesión, retornar vacío
    echo json_encode(['status' => 'success', 'generadas' => 0, 'message' => 'Requiere sesión']);
    exit;
}

define('DIAS_AVISO_POR_VENCER', 7);

try {
    // Verificar si existe columna id_aviso en notificaciones
    $tieneIdAviso = false;
    try {
        $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificaciones' AND COLUMN_NAME = 'id_aviso'");
        $tieneIdAviso = $chk && $chk->fetchColumn() > 0;
    } catch (Exception $e) { /* ignorar */ }

    // Avisos pendientes sin folio: por vencer (1-7 días) o vencidos
    $sqlAvisos = "
        SELECT a.id_aviso, a.id_cliente, a.tipo_aviso, a.fecha_deadline, a.fecha_operacion, a.monto,
               COALESCE(cf.nombre, cm.razon_social, c.alias, 'Sin nombre') as cliente_nombre
        FROM avisos_pld a
        LEFT JOIN clientes c ON a.id_cliente = c.id_cliente
        LEFT JOIN clientes_fisicas cf ON c.id_cliente = cf.id_cliente
        LEFT JOIN clientes_morales cm ON c.id_cliente = cm.id_cliente
        WHERE a.id_status = 1
          AND COALESCE(c.id_status, 1) != 4
          AND (a.folio_sppld IS NULL OR TRIM(a.folio_sppld) = '')
          AND a.estatus IN ('pendiente', 'generado')
          AND (
              a.fecha_deadline < CURDATE()
              OR (a.fecha_deadline >= CURDATE() AND a.fecha_deadline <= DATE_ADD(CURDATE(), INTERVAL ? DAY))
          )
    ";
    $stmt = $pdo->prepare($sqlAvisos);
    $stmt->execute([DIAS_AVISO_POR_VENCER]);
    $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Usuarios a notificar: administracion=1 + responsables PLD por cliente
    $usuariosAdmin = [];
    $stmt = $pdo->query("
        SELECT DISTINCT u.id_usuario
        FROM usuarios u
        INNER JOIN usuarios_permisos up ON u.id_usuario = up.id_usuario
        WHERE up.administracion > 0 AND u.id_status_usuario = 1
    ");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $usuariosAdmin[$r['id_usuario']] = true;
    }

    $generadas = 0;
    foreach ($avisos as $aviso) {
        $id_aviso = (int)$aviso['id_aviso'];
        $id_cliente = (int)$aviso['id_cliente'];
        $vencido = (strtotime($aviso['fecha_deadline']) < strtotime('today'));
        $tipo = $vencido ? 'aviso_vencido' : 'aviso_por_vencer';
        $titulo = $vencido ? 'Aviso PLD vencido' : 'Aviso PLD por vencer';
        $mensaje = sprintf(
            '%s: Cliente %s, deadline %s. Capture el folio SAT en Actualizar Aviso.',
            $vencido ? 'Aviso vencido sin folio' : 'Aviso próximo a vencer sin folio',
            $aviso['cliente_nombre'],
            $aviso['fecha_deadline']
        );

        $usuariosParaNotificar = $usuariosAdmin;
        $stmtR = $pdo->prepare("
            SELECT id_usuario_responsable FROM clientes_responsable_pld
            WHERE id_cliente = ? AND activo = 1
            AND (fecha_baja IS NULL OR fecha_baja > CURDATE())
        ");
        $stmtR->execute([$id_cliente]);
        while ($r = $stmtR->fetch(PDO::FETCH_ASSOC)) {
            $usuariosParaNotificar[$r['id_usuario_responsable']] = true;
        }

        foreach (array_keys($usuariosParaNotificar) as $id_usuario) {
            // Evitar duplicados: misma notificación para este aviso+usuario+tipo en últimas 24h
            $sqlExiste = $tieneIdAviso
                ? "SELECT 1 FROM notificaciones WHERE id_aviso = ? AND id_usuario = ? AND tipo IN ('aviso_por_vencer','aviso_vencido') AND estado != 'descartado' AND fecha_generacion > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
                : "SELECT 1 FROM notificaciones WHERE id_usuario = ? AND tipo IN ('aviso_por_vencer','aviso_vencido') AND mensaje LIKE ? AND estado != 'descartado' AND fecha_generacion > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $stmtEx = $pdo->prepare($sqlExiste);
            if ($tieneIdAviso) {
                $stmtEx->execute([$id_aviso, $id_usuario]);
            } else {
                $stmtEx->execute([$id_usuario, '%' . $aviso['cliente_nombre'] . '%']);
            }
            if ($stmtEx->fetch()) {
                continue;
            }

            $cols = $tieneIdAviso
                ? 'id_usuario, id_cliente, id_aviso, tipo, mensaje'
                : 'id_usuario, id_cliente, tipo, mensaje';
            $placeholders = $tieneIdAviso
                ? '?, ?, ?, ?, ?'
                : '?, ?, ?, ?';
            $stmtIns = $pdo->prepare("INSERT INTO notificaciones ($cols) VALUES ($placeholders)");
            if ($tieneIdAviso) {
                $stmtIns->execute([$id_usuario, $id_cliente, $id_aviso, $tipo, $mensaje]);
            } else {
                $stmtIns->execute([$id_usuario, $id_cliente, $tipo, $mensaje]);
            }
            $generadas++;
        }
    }

    echo json_encode([
        'status' => 'success',
        'generadas' => $generadas,
        'avisos_revisados' => count($avisos)
    ]);

} catch (Exception $e) {
    error_log("generar_notificaciones_avisos_pld: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'generadas' => 0]);
}
