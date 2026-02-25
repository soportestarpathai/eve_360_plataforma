<?php
/**
 * Job/API: Generar corte mensual interno de avisos PLD (día 15/16).
 * - Consolida avisos umbral/acumulación del periodo.
 * - Arma detalle con XML generado/pendiente.
 * - Notifica a administradores que el corte está listo para presentar.
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/pld_cliente_kyc.php';
require_once __DIR__ . '/../config/pld_avisos.php';
header('Content-Type: application/json');

$isCli = php_sapi_name() === 'cli';
$idUsuarioActual = $_SESSION['user_id'] ?? null;

if (!$isCli && !$idUsuarioActual) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = [];

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (isset($_POST['mes']) ? (int)$_POST['mes'] : (int)($body['mes'] ?? 0));
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (isset($_POST['anio']) ? (int)$_POST['anio'] : (int)($body['anio'] ?? 0));
$force = (isset($_GET['force']) && $_GET['force'] === '1')
    || (isset($_POST['force']) && $_POST['force'] === '1')
    || (!empty($body['force']));

try {
    if (!pldTableExists($pdo, 'cortes_mensuales_pld') || !pldTableExists($pdo, 'corte_mensual_avisos_pld_detalle')) {
        throw new Exception('Faltan tablas de corte mensual. Ejecute db/migrations/add_corte_mensual_avisos_pld.sql');
    }

    $hoy = new DateTime();
    $dia = (int)$hoy->format('d');
    if (!$force && !in_array($dia, [15, 16], true)) {
        throw new Exception('Este corte interno está permitido en día 15 o 16. Use force=1 para ejecución manual.');
    }

    if ($mes < 1 || $mes > 12 || $anio < 2000) {
        // Por default usar periodo anterior al mes en curso.
        $periodoDefault = new DateTime('first day of last month');
        $mes = (int)$periodoDefault->format('m');
        $anio = (int)$periodoDefault->format('Y');
    }

    $periodoInicio = sprintf('%04d-%02d-01', $anio, $mes);
    $periodoFin = date('Y-m-t', strtotime($periodoInicio));
    $periodoYm = sprintf('%04d-%02d', $anio, $mes);
    $fechaLimiteEnvio = calcularDeadlineAviso($periodoFin);

    // Upsert cabecera de corte activo.
    $stmtCorte = $pdo->prepare("
        SELECT id_corte
        FROM cortes_mensuales_pld
        WHERE periodo_mes = ? AND periodo_anio = ? AND id_status = 1
        ORDER BY id_corte DESC
        LIMIT 1
    ");
    $stmtCorte->execute([$mes, $anio]);
    $corte = $stmtCorte->fetch(PDO::FETCH_ASSOC);

    if ($corte) {
        $idCorte = (int)$corte['id_corte'];
        $stmtReset = $pdo->prepare("
            UPDATE cortes_mensuales_pld
            SET fecha_corte = NOW(),
                fecha_limite_envio = ?,
                estatus = 'borrador',
                total_avisos = 0,
                total_xml_generados = 0,
                total_xml_pendientes = 0,
                observaciones = ?,
                id_usuario_genero = ?
            WHERE id_corte = ?
        ");
        $stmtReset->execute([
            $fechaLimiteEnvio,
            'Corte regenerado automáticamente',
            $idUsuarioActual,
            $idCorte
        ]);

        $pdo->prepare("DELETE FROM corte_mensual_avisos_pld_detalle WHERE id_corte = ?")->execute([$idCorte]);
    } else {
        $stmtInsCorte = $pdo->prepare("
            INSERT INTO cortes_mensuales_pld
            (periodo_mes, periodo_anio, fecha_limite_envio, estatus, observaciones, id_usuario_genero, id_status)
            VALUES (?, ?, ?, 'borrador', ?, ?, 1)
        ");
        $stmtInsCorte->execute([$mes, $anio, $fechaLimiteEnvio, 'Corte generado automáticamente', $idUsuarioActual]);
        $idCorte = (int)$pdo->lastInsertId();
    }

    $usaPuente = pldTableExists($pdo, 'aviso_transacciones');
    $tieneXml = pldColumnExists($pdo, 'operaciones_pld', 'xml_contenido');
    $colXmlNombre = pldColumnExists($pdo, 'operaciones_pld', 'xml_nombre_archivo');
    $exprXmlNombre = $colXmlNombre ? "o.xml_nombre_archivo" : "NULL";
    $exprXmlGenerado = $tieneXml ? "CASE WHEN o.xml_contenido IS NOT NULL AND LENGTH(o.xml_contenido) > 0 THEN 1 ELSE 0 END" : "0";

    if ($usaPuente) {
        $sql = "
            SELECT a.id_aviso, a.id_cliente, a.tipo_aviso, a.monto, a.fecha_operacion, a.fecha_deadline,
                   at.id_operacion,
                   {$exprXmlNombre} AS xml_nombre_archivo,
                   {$exprXmlGenerado} AS xml_generado
            FROM avisos_pld a
            LEFT JOIN aviso_transacciones at
                   ON a.id_aviso = at.id_aviso
                  AND at.id_status = 1
            LEFT JOIN operaciones_pld o
                   ON at.id_operacion = o.id_operacion
                  AND o.id_status = 1
            WHERE a.id_status = 1
              AND a.tipo_aviso IN ('umbral_individual', 'acumulacion')
              AND DATE_FORMAT(a.fecha_operacion, '%Y-%m') = ?
              AND a.estatus IN ('pendiente', 'generado', 'presentado', 'extemporaneo')
            ORDER BY a.id_aviso ASC, at.id_operacion ASC
        ";
    } else {
        $sql = "
            SELECT a.id_aviso, a.id_cliente, a.tipo_aviso, a.monto, a.fecha_operacion, a.fecha_deadline,
                   o.id_operacion,
                   {$exprXmlNombre} AS xml_nombre_archivo,
                   {$exprXmlGenerado} AS xml_generado
            FROM avisos_pld a
            LEFT JOIN operaciones_pld o
                   ON o.id_aviso_generado = a.id_aviso
                  AND o.id_status = 1
            WHERE a.id_status = 1
              AND a.tipo_aviso IN ('umbral_individual', 'acumulacion')
              AND DATE_FORMAT(a.fecha_operacion, '%Y-%m') = ?
              AND a.estatus IN ('pendiente', 'generado', 'presentado', 'extemporaneo')
            ORDER BY a.id_aviso ASC, o.id_operacion ASC
        ";
    }

    $stmtAvisos = $pdo->prepare($sql);
    $stmtAvisos->execute([$periodoYm]);
    $rows = $stmtAvisos->fetchAll(PDO::FETCH_ASSOC);

    $stmtInsDetalle = $pdo->prepare("
        INSERT IGNORE INTO corte_mensual_avisos_pld_detalle
        (id_corte, id_aviso, id_operacion, id_cliente, tipo_aviso, monto, fecha_operacion, fecha_deadline, xml_generado, xml_nombre_archivo, id_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $dedupe = [];
    $xmlGenerados = 0;
    $xmlPendientes = 0;
    $avisosSet = [];

    foreach ($rows as $row) {
        $idAviso = (int)$row['id_aviso'];
        $idOperacion = isset($row['id_operacion']) && $row['id_operacion'] !== null ? (int)$row['id_operacion'] : null;
        $key = $idAviso . '-' . ($idOperacion ?? 0);
        if (isset($dedupe[$key])) {
            continue;
        }
        $dedupe[$key] = true;
        $avisosSet[$idAviso] = true;

        $xml = (int)($row['xml_generado'] ?? 0) === 1 ? 1 : 0;
        if ($xml === 1) $xmlGenerados++;
        else $xmlPendientes++;

        $stmtInsDetalle->execute([
            $idCorte,
            $idAviso,
            $idOperacion,
            (int)$row['id_cliente'],
            $row['tipo_aviso'],
            $row['monto'] !== null ? (float)$row['monto'] : null,
            $row['fecha_operacion'] ?: null,
            $row['fecha_deadline'] ?: null,
            $xml,
            $row['xml_nombre_archivo'] ?: null
        ]);
    }

    $totalAvisos = count($avisosSet);

    $stmtUpdCorte = $pdo->prepare("
        UPDATE cortes_mensuales_pld
        SET estatus = 'listo_para_presentar',
            total_avisos = ?,
            total_xml_generados = ?,
            total_xml_pendientes = ?,
            observaciones = ?
        WHERE id_corte = ?
    ");
    $stmtUpdCorte->execute([
        $totalAvisos,
        $xmlGenerados,
        $xmlPendientes,
        "Corte interno del periodo {$periodoYm} listo para revisión y presentación.",
        $idCorte
    ]);

    // Notificar administradores sobre corte listo.
    if (pldTableExists($pdo, 'notificaciones')) {
        $admins = [];
        $stmtAdmins = $pdo->query("
            SELECT DISTINCT u.id_usuario
            FROM usuarios u
            INNER JOIN usuarios_permisos up ON u.id_usuario = up.id_usuario
            WHERE u.id_status_usuario = 1
              AND up.administracion > 0
        ");
        while ($r = $stmtAdmins->fetch(PDO::FETCH_ASSOC)) {
            $admins[] = (int)$r['id_usuario'];
        }

        $tipoNotif = 'corte_mensual_pld';
        $mensajeNotif = "Corte mensual PLD {$periodoYm} listo. Avisos: {$totalAvisos}, XML generados: {$xmlGenerados}, pendientes: {$xmlPendientes}.";
        foreach ($admins as $idAdmin) {
            $stmtEx = $pdo->prepare("
                SELECT 1
                FROM notificaciones
                WHERE id_usuario = ?
                  AND tipo = ?
                  AND mensaje = ?
                  AND estado != 'descartado'
                  AND fecha_generacion > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                LIMIT 1
            ");
            $stmtEx->execute([$idAdmin, $tipoNotif, $mensajeNotif]);
            if ($stmtEx->fetch()) continue;

            $stmtIn = $pdo->prepare("INSERT INTO notificaciones (id_usuario, id_cliente, tipo, mensaje) VALUES (?, NULL, ?, ?)");
            $stmtIn->execute([$idAdmin, $tipoNotif, $mensajeNotif]);
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Corte mensual PLD generado',
        'id_corte' => $idCorte,
        'periodo' => $periodoYm,
        'fecha_limite_envio' => $fechaLimiteEnvio,
        'total_avisos' => $totalAvisos,
        'total_xml_generados' => $xmlGenerados,
        'total_xml_pendientes' => $xmlPendientes,
        'items_detalle' => count($dedupe)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("generar_corte_mensual_avisos_pld: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
