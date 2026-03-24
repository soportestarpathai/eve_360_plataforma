<?php
// admin/users.php
include 'header.php';

// --- Asegurar tabla admin_users existe ---
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($chk->rowCount() == 0) {
        $pdo->exec("CREATE TABLE `admin_users` (
          `id` int NOT NULL AUTO_INCREMENT,
          `email` varchar(255) NOT NULL,
          `nombre` varchar(255) DEFAULT NULL,
          `temp_password_hash` varchar(255) DEFAULT NULL,
          `expires_at` datetime DEFAULT NULL,
          `id_status` tinyint(1) DEFAULT 1,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("INSERT INTO admin_users (email, nombre, id_status) SELECT email, COALESCE(email,'Administrador'), 1 FROM admin_access WHERE id = 1 LIMIT 1");
    }
} catch (Exception $e) { /* ignore */ }

function buildVerificationEmail($name, $link, $appName) {
    $nameEsc = htmlspecialchars($name);
    $linkEsc = htmlspecialchars($link);
    $appEsc = htmlspecialchars($appName);
    return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Verifique su cuenta</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f1f5f9;">
<tr><td style="padding:32px 16px;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
<tr>
<td style="background:linear-gradient(135deg,#4361ee 0%,#3a0ca3 100%);padding:28px 32px;text-align:center;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
<tr><td style="width:48px;height:48px;background:rgba(255,255,255,0.2);border-radius:12px;text-align:center;color:#ffffff;font-size:22px;line-height:48px;">&#128273;</td></tr>
</table>
<p style="margin:16px 0 0 0;color:#ffffff;font-size:18px;font-weight:700;letter-spacing:-0.02em;">' . $appEsc . '</p>
<p style="margin:4px 0 0 0;color:rgba(255,255,255,0.9);font-size:14px;">Verificación de cuenta</p>
</td>
</tr>
<tr>
<td style="padding:32px;">
<p style="margin:0 0 16px 0;color:#64748b;font-size:15px;line-height:1.5;">Hola <strong>' . $nameEsc . '</strong>,</p>
<p style="margin:0 0 20px 0;color:#64748b;font-size:15px;line-height:1.5;">Se ha creado o modificado su cuenta. Haga clic en el botón para activarla:</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
<tr><td style="text-align:center;padding:16px 0;">
<a href="' . $linkEsc . '" style="display:inline-block;background:linear-gradient(135deg,#4361ee,#3a0ca3);color:#ffffff!important;font-weight:600;padding:14px 32px;border-radius:12px;text-decoration:none;font-size:16px;box-shadow:0 4px 14px rgba(67,97,238,0.35);">Activar mi cuenta</a>
</td></tr>
</table>
<p style="margin:20px 0 0 0;color:#94a3b8;font-size:13px;line-height:1.5;">Si el botón no funciona, copie y pegue este enlace en su navegador:</p>
<p style="margin:8px 0 0 0;color:#64748b;font-size:12px;word-break:break-all;font-family:\'Courier New\',monospace;">' . $linkEsc . '</p>
<p style="margin:24px 0 0 0;color:#94a3b8;font-size:13px;">Si no solicitó esto, ignore este correo.</p>
</td>
</tr>
<tr>
<td style="padding:20px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
<p style="margin:0;color:#94a3b8;font-size:12px;">&copy; ' . date('Y') . ' ' . $appEsc . '</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>';
}

function sendVerificationEmail($email, $name, $token) {
    try {
        require __DIR__ . '/../libs/PHPMailer/Exception.php';
        require __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
        require __DIR__ . '/../libs/PHPMailer/SMTP.php';
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';
        $mail->Host = 'smtp.ionos.mx';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@adsoft.mx';
        $mail->Password = 'Ex1t0@2026';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $domain = $_SERVER['HTTP_HOST'];
        $basePath = '';
        $envConfig = @(include __DIR__ . '/../config/env.php');
        if (!empty($envConfig['APP_BASE_URL'])) {
            $basePath = rtrim($envConfig['APP_BASE_URL'], '/');
        } else {
            $basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
            if (strpos($basePath, '/admin') !== false) {
                $basePath = dirname($basePath);
            }
            $basePath = rtrim($basePath, '/');
        }
        $link = "http://$domain" . $basePath . "/verify.php?token=$token";
        $appName = 'EVE 360';
        try {
            global $pdo;
            if (isset($GLOBALS['pdo'])) {
                $cfg = $GLOBALS['pdo']->query("SELECT nombre_empresa FROM config_empresa WHERE id_config = 1")->fetch(PDO::FETCH_ASSOC);
                if (!empty($cfg['nombre_empresa'])) $appName = $cfg['nombre_empresa'];
            }
        } catch (Exception $e) { /* fallback */ }
        $mail->setFrom('no-reply@adsoft.mx', $appName);
        $mail->addAddress($email);
        $mail->Subject = $appName . " - Verifique su cuenta";
        $mail->isHTML(true);
        $mail->Body = buildVerificationEmail($name, $link, $appName);
        $mail->AltBody = "Hola $name,\n\nSe ha creado o modificado su cuenta. Por favor haga clic en el siguiente enlace para activarla:\n\n$link\n\nSi usted no solicitó esto, ignore este mensaje.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('sendVerificationEmail: ' . $e->getMessage());
        return false;
    }
}

function logUserAction($pdo, $action, $id, $oldVal, $newVal) {
    $userId = $_SESSION['user_id'] ?? 0;
    $stmt = $pdo->prepare("INSERT INTO bitacora (id_usuario, accion, tabla_afectada, id_afectado, valor_anterior, valor_nuevo, fecha) VALUES (?, ?, 'usuarios', ?, ?, ?, NOW())");
    $stmt->execute([$userId, $action, $id, json_encode($oldVal), json_encode($newVal)]);
}
?>
<title>Administración de Usuarios - EVE 360</title>
<style>
    body { background: linear-gradient(135deg, #f0f4f8 0%, #e8f0f5 50%, #f5f9fc 100%); }
    .users-page .users-welcome {
        background: linear-gradient(135deg, #0B3C8A 0%, #0B486B 100%);
        border-radius: 20px; padding: 1.5rem 2rem; margin-bottom: 1.5rem; color: #fff;
        box-shadow: 0 12px 40px rgba(11, 60, 138, 0.25);
    }
    .users-page .users-welcome h1 { font-size: 1.5rem; font-weight: 800; margin: 0; letter-spacing: -0.02em; }
    .users-page .users-welcome p { margin: 0.3rem 0 0; opacity: 0.9; font-size: 0.9rem; }
    .users-page .nav-tabs { border-bottom: 2px solid #e2e8f0; gap: 0.5rem; }
    .users-page .nav-tabs .nav-link {
        border: none; border-radius: 12px 12px 0 0; padding: 0.75rem 1.5rem;
        color: #64748b; font-weight: 600; background: transparent; transition: all 0.2s;
    }
    .users-page .nav-tabs .nav-link:hover { color: #0B3C8A; background: rgba(11, 60, 138, 0.08); }
    .users-page .nav-tabs .nav-link.active {
        background: linear-gradient(135deg, #0B3C8A 0%, #0B486B 100%); color: #fff;
    }
    .users-page .users-card {
        border-radius: 16px; overflow: hidden; border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.04);
    }
    .users-page .users-card .card-header {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #fff; border: none; padding: 1rem 1.25rem;
        font-weight: 600; font-size: 0.95rem;
    }
    .users-page .users-card .card-header .btn-light { border-radius: 10px; font-weight: 600; }
    .users-page .users-card .table { font-size: 0.875rem; }
    .users-page .users-card .table thead { background: #f8fafc; color: #475569; }
    .users-page .users-card .table thead th { border: none; padding: 0.875rem 1rem; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .users-page .users-card .table tbody tr { transition: background 0.2s; }
    .users-page .users-card .table tbody tr:hover { background: #f8fafc; }
    .users-page .users-card .table tbody td { padding: 0.875rem 1rem; vertical-align: middle; }
    .users-page .users-card .table-responsive { max-height: 420px; overflow-y: auto; }
    .users-page .users-card .table thead th { position: sticky; top: 0; z-index: 5; background: #f8fafc; }
    #adminModal .modal-content, #userModal .modal-content { border-radius: 16px; border: none; box-shadow: 0 24px 48px rgba(0,0,0,0.15); }
    #adminModal .modal-header, #userModal .modal-header { background: linear-gradient(135deg, #0B3C8A 0%, #0B486B 100%); color: #fff; border: none; padding: 1rem 1.25rem; border-radius: 16px 16px 0 0; }
    #adminModal .modal-header .btn-close, #userModal .modal-header .btn-close { filter: brightness(0) invert(1); }
    #userModal .modal-body { padding: 1.5rem 1.5rem; }
    #userModal .form-control, #userModal .form-select { border-radius: 10px; border: 2px solid #e2e8f0; padding: 0.5rem 1rem; }
    #userModal .form-control:focus, #userModal .form-select:focus { border-color: #0B3C8A; box-shadow: 0 0 0 3px rgba(11,60,138,0.15); }
    #userModal .form-label { font-weight: 600; color: #334155; margin-bottom: 0.35rem; }
    #userModal .input-group-password { border-radius: 10px; border: 2px solid #e2e8f0; overflow: hidden; }
    #userModal .input-group-password:focus-within { border-color: #0B3C8A; box-shadow: 0 0 0 3px rgba(11,60,138,0.15); }
    #userModal .input-group-password .form-control { border: none; border-radius: 0; }
    #userModal .input-group-password .form-control:focus { box-shadow: none; }
    #userModal .input-group-password .btn-toggle-pass { border: none; background: #f8fafc; color: #64748b; padding: 0 1rem; cursor: pointer; }
    #userModal .input-group-password .btn-toggle-pass:hover { background: #e2e8f0; color: #0B3C8A; }
    #userModal .perm-section { background: #f8fafc; border-radius: 12px; padding: 1rem; border: 1px solid #e2e8f0; }
    #userModal .perm-section small { color: #64748b; font-weight: 600; letter-spacing: 0.5px; }
    #userModal .form-check-input:checked { background-color: #0B3C8A; border-color: #0B3C8A; }
    #adminModal .modal-body { padding: 1.5rem; }
    #adminModal .form-control, #adminModal .form-select { border-radius: 10px; border: 2px solid #e2e8f0; padding: 0.5rem 1rem; }
    #adminModal .form-control:focus, #adminModal .form-select:focus { border-color: #0B3C8A; box-shadow: 0 0 0 3px rgba(11,60,138,0.15); }
    #adminModal .form-label { font-weight: 600; color: #334155; }
</style>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ─── ADMIN USERS ─────────────────────────────────────────────────────
    if (isset($_POST['action']) && $_POST['action'] === 'save_admin') {
        try {
            $id_admin = $_POST['id_admin'] ?? '';
            $nombre = trim($_POST['admin_nombre'] ?? '');
            $email = trim($_POST['admin_email'] ?? '');
            $id_status = (int)($_POST['admin_status'] ?? 1);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Email inválido.");
            }

            if ($id_admin) {
                $stmt = $pdo->prepare("UPDATE admin_users SET nombre = ?, email = ?, id_status = ? WHERE id = ?");
                $stmt->execute([$nombre, $email, $id_status, $id_admin]);
                echo '<div class="alert alert-success mt-3"><i class="fa-solid fa-check me-2"></i>Administrador actualizado.</div>';
            } else {
                $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn()) {
                    throw new Exception("El email ya está registrado como administrador.");
                }
                $stmt = $pdo->prepare("INSERT INTO admin_users (nombre, email, id_status) VALUES (?, ?, ?)");
                $stmt->execute([$nombre, $email, $id_status]);
                echo '<div class="alert alert-success mt-3"><i class="fa-solid fa-check me-2"></i>Administrador creado. Deberá solicitar código de acceso para ingresar.</div>';
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger mt-3">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
        try {
            $idDel = (int)$_POST['id_admin_delete'];
            if ($idDel < 1) throw new Exception("ID inválido");
            $pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$idDel]);
            echo '<div class="alert alert-warning mt-3">Administrador eliminado.</div>';
        } catch (Exception $e) {
            echo '<div class="alert alert-danger mt-3">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // ─── CLIENT USERS (usuarios con validaciones PLD) ─────────────────────
    if (isset($_POST['action']) && $_POST['action'] === 'save_user') {
        try {
            $pdo->beginTransaction();
            $id_usuario = $_POST['id_usuario'] ?? '';
            $nombre = $_POST['nombre'];
            $email = trim($_POST['login_user']);
            $id_grupo = $_POST['id_grupo'] ?? 1;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("El campo 'Usuario (Login)' debe ser un correo electrónico válido.");
            }

            $sendEmail = false;
            $newToken = null;
            $statusToSave = (int)($_POST['id_status_usuario'] ?? 1);

            if ($id_usuario) {
                $stmtOld = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
                $stmtOld->execute([$id_usuario]);
                $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

                if ($oldData['login_user'] !== $email) {
                    $statusToSave = 0;
                    $newToken = bin2hex(random_bytes(32));
                    $sendEmail = true;
                }

                $sql = "UPDATE usuarios SET nombre = ?, login_user = ?, id_status_usuario = ?, id_grupo = ?, verification_token = ? WHERE id_usuario = ?";
                $params = [$nombre, $email, $statusToSave, $id_grupo, ($newToken ?: $oldData['verification_token']), $id_usuario];

                if (!empty($_POST['login_password'])) {
                    $hash = password_hash($_POST['login_password'], PASSWORD_DEFAULT);
                    $sql = "UPDATE usuarios SET nombre = ?, login_user = ?, id_status_usuario = ?, id_grupo = ?, verification_token = ?, login_password = ? WHERE id_usuario = ?";
                    $params = [$nombre, $email, $statusToSave, $id_grupo, ($newToken ?: $oldData['verification_token']), $hash, $id_usuario];
                }
                $pdo->prepare($sql)->execute($params);
                logUserAction($pdo, 'ACTUALIZAR', $id_usuario, $oldData, ['nombre' => $nombre, 'login_user' => $email]);
            } else {
                $stmtCheck = $pdo->prepare("SELECT count(*) FROM usuarios WHERE login_user = ?");
                $stmtCheck->execute([$email]);
                if ($stmtCheck->fetchColumn() > 0) {
                    throw new Exception("El correo electrónico ya está registrado.");
                }
                $statusToSave = 0;
                $newToken = bin2hex(random_bytes(32));
                $sendEmail = true;
                $hash = password_hash($_POST['login_password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, login_user, login_password, id_status_usuario, id_grupo, verification_token) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $email, $hash, $statusToSave, $id_grupo, $newToken]);
                $id_usuario = $pdo->lastInsertId();
                logUserAction($pdo, 'CREAR', $id_usuario, null, ['nombre' => $nombre, 'login_user' => $email]);
            }

            $permCols = ['catalogo_instituciones', 'catalogo_emisoras', 'catalogo_clientes', 'captura', 'administracion', 'reportes', 'valuacion', 'correcciones', 'rebalanceo', 'permiso_pld_modificacion'];
            $permValues = [];
            foreach ($permCols as $col) { $permValues[$col] = isset($_POST['perm_' . $col]) ? 1 : 0; }
            $selectedFracciones = $_POST['fracciones_pld'] ?? [];
            $fraccionesPldJson = !empty($selectedFracciones) ? json_encode(array_values($selectedFracciones)) : null;
            $selectedSubfracciones = (in_array('XI', $selectedFracciones)) ? ($_POST['subfracciones_xi'] ?? []) : [];
            $subfraccionesXiJson = !empty($selectedSubfracciones) ? json_encode(array_values($selectedSubfracciones)) : null;

            require_once __DIR__ . '/../config/pld_permisos.php';
            ensureFraccionesPLDColumn($pdo);
            ensurePermisoPldModificacionColumn($pdo);

            $stmtCheckPerm = $pdo->prepare("SELECT id_permiso FROM usuarios_permisos WHERE id_usuario = ?");
            $stmtCheckPerm->execute([$id_usuario]);
            $hasSubfCol = false;
            try {
                $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'subfracciones_xi'");
                $hasSubfCol = $chk && $chk->fetchColumn() > 0;
            } catch (Exception $e) { }
            if ($stmtCheckPerm->fetchColumn()) {
                $setParts = [];
                $execParams = [];
                foreach ($permValues as $col => $val) { $setParts[] = "$col = ?"; $execParams[] = $val; }
                $setParts[] = "fracciones_pld = ?";
                $execParams[] = $fraccionesPldJson;
                if ($hasSubfCol) { $setParts[] = "subfracciones_xi = ?"; $execParams[] = $subfraccionesXiJson; }
                $execParams[] = $id_usuario;
                $pdo->prepare("UPDATE usuarios_permisos SET " . implode(', ', $setParts) . " WHERE id_usuario = ?")->execute($execParams);
            } else {
                $cols = "id_usuario, " . implode(', ', array_keys($permValues)) . ", fracciones_pld" . ($hasSubfCol ? ", subfracciones_xi" : "");
                $placeholders = "?, " . str_repeat('?, ', count($permValues)) . "?" . ($hasSubfCol ? ", ?" : "");
                $execParams = array_merge([$id_usuario], array_values($permValues), [$fraccionesPldJson]);
                if ($hasSubfCol) $execParams[] = $subfraccionesXiJson;
                $pdo->prepare("INSERT INTO usuarios_permisos ($cols) VALUES ($placeholders)")->execute($execParams);
            }

            if ($sendEmail && $newToken) {
                if (sendVerificationEmail($email, $nombre, $newToken)) {
                    echo '<div class="alert alert-success mt-3"><i class="fa-solid fa-envelope me-2"></i>Usuario guardado. Correo de verificación enviado.</div>';
                } else {
                    echo '<div class="alert alert-warning mt-3">Usuario guardado, pero falló el envío del correo.</div>';
                }
            } else {
                echo '<div class="alert alert-success mt-3"><i class="fa-solid fa-check me-2"></i>Usuario actualizado correctamente.</div>';
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo '<div class="alert alert-danger mt-3">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        try {
            $idDel = $_POST['id_user_delete'];
            $stmtOld = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
            $stmtOld->execute([$idDel]);
            $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM usuarios_permisos WHERE id_usuario = ?")->execute([$idDel]);
            $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$idDel]);
            logUserAction($pdo, 'ELIMINAR', $idDel, $oldData, null);
            $pdo->commit();
            echo '<div class="alert alert-warning mt-3">Usuario eliminado.</div>';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo '<div class="alert alert-danger mt-3">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

require_once __DIR__ . '/../config/pld_permisos.php';
ensureFraccionesPLDColumn($pdo);
ensurePermisoPldModificacionColumn($pdo);
try {
    $chk = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_permisos' AND COLUMN_NAME = 'subfracciones_xi'");
    if ($chk && $chk->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE usuarios_permisos ADD COLUMN subfracciones_xi JSON DEFAULT NULL");
    }
} catch (Exception $e) { }

$stmtConfig = $pdo->query("SELECT fracciones_activas FROM config_empresa WHERE id_config = 1");
$configRow = $stmtConfig->fetch(PDO::FETCH_ASSOC);
$fraccionesActivas = [];
if ($configRow && !empty($configRow['fracciones_activas'])) {
    $decoded = json_decode($configRow['fracciones_activas'], true);
    if (is_array($decoded)) $fraccionesActivas = $decoded;
}
// Asegurar fracciones base siempre disponibles; reemplazar XII por XI
$fraccionesActivas = array_values(array_filter($fraccionesActivas, fn($f) => $f !== 'XII'));
if (!in_array('II', $fraccionesActivas, true)) array_unshift($fraccionesActivas, 'II');
if (!in_array('XI', $fraccionesActivas, true)) $fraccionesActivas[] = 'XI';
if (!in_array('XIII', $fraccionesActivas, true)) $fraccionesActivas[] = 'XIII';
if (!in_array('XVI', $fraccionesActivas, true)) $fraccionesActivas[] = 'XVI';
$fraccionesActivas = array_values(array_unique($fraccionesActivas));

$adminUsers = [];
try {
    $adminUsers = $pdo->query("SELECT * FROM admin_users ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }

$stmt = $pdo->query("
    SELECT u.*, up.catalogo_instituciones, up.catalogo_emisoras, up.catalogo_clientes,
           up.captura, up.administracion, up.reportes, up.valuacion, up.correcciones, up.rebalanceo,
           up.fracciones_pld, up.subfracciones_xi, COALESCE(up.permiso_pld_modificacion, 0) AS permiso_pld_modificacion
    FROM usuarios u
    LEFT JOIN usuarios_permisos up ON u.id_usuario = up.id_usuario
    ORDER BY u.nombre ASC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="users-page container mt-4 mb-5">
    <div class="users-welcome">
        <h1><i class="fa-solid fa-users-gear me-2"></i>Administración de Usuarios</h1>
        <p>Gestión de administradores del panel y usuarios de la plataforma</p>
    </div>

    <ul class="nav nav-tabs mb-4" id="usersTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-admin" data-bs-toggle="tab" data-bs-target="#panel-admin" type="button">
                <i class="fa-solid fa-shield-halved me-1"></i>Usuarios Admin
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-clientes" data-bs-toggle="tab" data-bs-target="#panel-clientes" type="button">
                <i class="fa-solid fa-users me-1"></i>Usuarios Clientes
            </button>
        </li>
    </ul>

    <div class="tab-content" id="usersTabContent">
        <!-- TAB ADMIN -->
        <div class="tab-pane fade show active" id="panel-admin">
            <div class="card users-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fa-solid fa-shield-halved me-2"></i>Administradores del Panel</h6>
                    <button class="btn btn-light btn-sm" onclick="openAdminModal()"><i class="fa-solid fa-plus me-1"></i>Nuevo Admin</button>
                </div>
                <div class="card-body p-0">
                    <p class="small text-muted px-4 pt-3 mb-2">Acceso exclusivo al panel de administración (configuración, usuarios). Sin validaciones PLD.</p>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Nombre</th><th>Email</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminUsers as $a): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($a['nombre'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($a['email']) ?></td>
                                    <td><?= ($a['id_status'] ?? 1) == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>' ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary border-0" onclick='editAdmin(<?= json_encode($a) ?>)' title="Editar"><i class="fa-solid fa-pen"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este administrador?');">
                                            <input type="hidden" name="action" value="delete_admin">
                                            <input type="hidden" name="id_admin_delete" value="<?= $a['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger border-0" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($adminUsers)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No hay administradores. <a href="#" onclick="openAdminModal(); return false;">Crear uno</a></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB CLIENTES -->
        <div class="tab-pane fade" id="panel-clientes">
            <div class="card users-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fa-solid fa-users me-2"></i>Usuarios de la Plataforma</h6>
                    <button class="btn btn-light btn-sm" onclick="openUserModal()"><i class="fa-solid fa-plus me-1"></i>Nuevo Usuario</button>
                </div>
                <div class="card-body p-0">
                    <p class="small text-muted px-4 pt-3 mb-2">Usuarios con acceso a clientes, operaciones PLD, reportes. Requieren validaciones (representación legal, expediente, fracciones PLD).</p>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre</th><th>Email (Login)</th><th>Status</th><th class="text-center">Permisos</th><th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($u['nombre']) ?></td>
                                    <td><?= htmlspecialchars($u['login_user']) ?></td>
                                    <td><?= $u['id_status_usuario'] == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-warning text-dark">Inactivo</span>' ?></td>
                                    <td class="text-center">
                                        <?php
                                        $countP = 0;
                                        foreach (['catalogo_instituciones','catalogo_emisoras','catalogo_clientes','captura','administracion','reportes','valuacion','correcciones','rebalanceo','permiso_pld_modificacion'] as $p) {
                                            if (!empty($u[$p]) && $u[$p] == 1) $countP++;
                                        }
                                        echo $countP > 0 ? '<span class="badge bg-info">' . $countP . ' roles</span>' : '<span class="text-muted">-</span>';
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary border-0" onclick='editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)'><i class="fa-solid fa-pen"></i></button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="id_user_delete" value="<?= $u['id_usuario'] ?>">
                                            <button class="btn btn-sm btn-outline-danger border-0"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($users)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No hay usuarios. <a href="#" onclick="openUserModal(); return false;">Crear uno</a></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Admin -->
<div class="modal fade" id="adminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="adminModalTitle">Nuevo Administrador</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_admin">
                    <input type="hidden" name="id_admin" id="adminId">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nombre</label>
                        <input type="text" name="admin_nombre" id="adminNombre" class="form-control" placeholder="Nombre del administrador">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="admin_email" id="adminEmail" class="form-control" placeholder="admin@ejemplo.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Estado</label>
                        <select name="admin_status" id="adminStatus" class="form-select">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                    <p class="small text-muted mb-0"><i class="fa-solid fa-envelope me-1"></i>Los administradores acceden con código temporal enviado por email.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Usuario Cliente -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="id_usuario" id="userId">
                    <div class="alert alert-info small mb-4"><i class="fa-solid fa-circle-info me-2"></i>Al crear o cambiar email, la cuenta quedará <strong>Inactiva</strong> hasta verificación.</div>

                    <h6 class="mb-3 fw-bold" style="color:#0B3C8A;"><i class="fa-solid fa-user me-2"></i>Datos del usuario</h6>
                    <div class="row mb-4 g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="userName" class="form-control" placeholder="Nombre completo" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email (Login)</label>
                            <input type="email" name="login_user" id="userLogin" class="form-control" placeholder="usuario@ejemplo.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña</label>
                            <div class="input-group input-group-password">
                                <input type="password" name="login_password" id="userPass" class="form-control" placeholder="Mín. 6 caracteres" autocomplete="new-password">
                                <button type="button" class="btn-toggle-pass" onclick="togglePassVisibility('userPass', this)" title="Mostrar/Ocultar contraseña">
                                    <i class="fa-solid fa-eye" id="userPassIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estatus</label>
                            <select name="id_status_usuario" id="userStatus" class="form-select">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Grupo</label>
                            <input type="number" name="id_grupo" id="userGroup" class="form-control" value="1" min="1">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="mb-3 fw-bold" style="color:#0B3C8A;"><i class="fa-solid fa-key me-2"></i>Permisos</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="perm-section h-100">
                                <small class="d-block mb-2">Catálogos</small>
                                <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="perm_catalogo_instituciones" id="perm_inst"><label class="form-check-label" for="perm_inst">Instituciones</label></div>
                                <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="perm_catalogo_emisoras" id="perm_emi"><label class="form-check-label" for="perm_emi">Emisoras</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_catalogo_clientes" id="perm_cli"><label class="form-check-label" for="perm_cli">Clientes</label></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="perm-section h-100">
                                <small class="d-block mb-2">Transacción</small>
                                <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="perm_captura" id="perm_cap"><label class="form-check-label" for="perm_cap">Captura</label></div>
                                <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="perm_valuacion" id="perm_val"><label class="form-check-label" for="perm_val">Valuación</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_rebalanceo" id="perm_reb"><label class="form-check-label" for="perm_reb">Rebalanceo</label></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="perm-section h-100">
                                <small class="d-block mb-2">Control</small>
                                <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="perm_correcciones" id="perm_corr"><label class="form-check-label" for="perm_corr">Correcciones</label></div>
                                <div class="form-check mb-1"><input class="form-check-input" type="checkbox" name="perm_reportes" id="perm_rep"><label class="form-check-label" for="perm_rep">Reportes</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="perm_permiso_pld_modificacion" id="perm_pld_mod"><label class="form-check-label" for="perm_pld_mod">Modificar avisos PLD</label></div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($fraccionesActivas)): ?>
                    <hr class="my-4">
                    <h6 class="mb-3 fw-bold" style="color:#0B3C8A;"><i class="fa-solid fa-shield-halved me-2"></i>Fracciones PLD</h6>
                    <div class="perm-section mb-0">
                        <div class="row g-2">
                            <?php foreach ($fraccionesActivas as $frac): $fracId = str_replace(' ', '_', $frac); ?>
                            <div class="col-md-4"><div class="form-check">
                                <input class="form-check-input pld-fraccion-check" type="checkbox" name="fracciones_pld[]" value="<?= htmlspecialchars($frac) ?>" id="pld_frac_<?= htmlspecialchars($fracId) ?>" data-fraccion="<?= htmlspecialchars($frac) ?>" onchange="toggleUserSubfraccionesXI()">
                                <label class="form-check-label" for="pld_frac_<?= htmlspecialchars($fracId) ?>"><?= htmlspecialchars($frac) ?></label>
                            </div></div>
                            <?php endforeach; ?>
                        </div>
                        <div id="userSubfraccionesXiSection" class="mt-3 p-3 rounded" style="display:none; background:#eef2ff; border-left:3px solid #6366f1;">
                            <label class="form-label fw-bold"><i class="fa-solid fa-layer-group me-2"></i>Subfracciones XI (SPR)</label>
                            <p class="small text-muted mb-2">Seleccione las actividades de Servicios Profesionales para este usuario:</p>
                            <div class="row g-2">
                                <?php
                                require_once __DIR__ . '/../config/spr_catalogos.php';
                                foreach (($SPR_CATALOGOS['tipo_actividad'] ?? []) as $clave => $etiqueta):
                                ?>
                                <div class="col-md-6"><div class="form-check">
                                    <input class="form-check-input user-subfraccion-xi" type="checkbox" name="subfracciones_xi[]" value="<?= htmlspecialchars($clave) ?>" id="user_subf_<?= htmlspecialchars($clave) ?>">
                                    <label class="form-check-label" for="user_subf_<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($etiqueta) ?></label>
                                </div></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="form-text text-muted d-block mt-2">Solo fracciones habilitadas en configuración.</small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        btn.title = 'Ocultar contraseña';
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        btn.title = 'Mostrar contraseña';
    }
}

const adminModal = new bootstrap.Modal(document.getElementById('adminModal'));
const userModal = new bootstrap.Modal(document.getElementById('userModal'));

function openAdminModal() {
    document.getElementById('adminModalTitle').textContent = 'Nuevo Administrador';
    document.getElementById('adminId').value = '';
    document.getElementById('adminNombre').value = '';
    document.getElementById('adminEmail').value = '';
    document.getElementById('adminEmail').readOnly = false;
    document.getElementById('adminStatus').value = '1';
    adminModal.show();
}
function editAdmin(a) {
    document.getElementById('adminModalTitle').textContent = 'Editar Administrador';
    document.getElementById('adminId').value = a.id;
    document.getElementById('adminNombre').value = a.nombre || '';
    document.getElementById('adminEmail').value = a.email || '';
    document.getElementById('adminEmail').readOnly = true;
    document.getElementById('adminStatus').value = String(a.id_status ?? 1);
    adminModal.show();
}
function openUserModal() {
    document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
    document.getElementById('userId').value = '';
    document.getElementById('userName').value = '';
    document.getElementById('userLogin').value = '';
    const passInput = document.getElementById('userPass');
    passInput.value = '';
    passInput.type = 'password';
    passInput.required = true;
    const passIcon = document.querySelector('#userModal .btn-toggle-pass i');
    if (passIcon) { passIcon.classList.remove('fa-eye-slash'); passIcon.classList.add('fa-eye'); }
    document.getElementById('userStatus').value = '1';
    document.getElementById('userStatus').disabled = true;
    document.querySelectorAll('#userModal .form-check-input').forEach(el => el.checked = false);
    const subfEl = document.getElementById('userSubfraccionesXiSection'); if (subfEl) subfEl.style.display = 'none';
    userModal.show();
}
function editUser(u) {
    document.getElementById('modalTitle').textContent = 'Editar Usuario';
    document.getElementById('userId').value = u.id_usuario;
    document.getElementById('userName').value = u.nombre || '';
    document.getElementById('userLogin').value = u.login_user || '';
    const passInput = document.getElementById('userPass');
    passInput.value = '';
    passInput.type = 'password';
    passInput.required = false;
    const passIcon = document.querySelector('#userModal .btn-toggle-pass i');
    if (passIcon) { passIcon.classList.remove('fa-eye-slash'); passIcon.classList.add('fa-eye'); }
    document.getElementById('userStatus').value = u.id_status_usuario;
    document.getElementById('userStatus').disabled = false;
    document.getElementById('userGroup').value = u.id_grupo || 1;
    const setP = (id, v) => { const el = document.getElementById(id); if (el) el.checked = (v == 1); };
    setP('perm_inst', u.catalogo_instituciones); setP('perm_emi', u.catalogo_emisoras); setP('perm_cli', u.catalogo_clientes);
    setP('perm_cap', u.captura); setP('perm_val', u.valuacion); setP('perm_reb', u.rebalanceo);
    setP('perm_corr', u.correcciones); setP('perm_rep', u.reportes); setP('perm_pld_mod', u.permiso_pld_modificacion);
    document.querySelectorAll('#userModal .pld-fraccion-check').forEach(el => el.checked = false);
    document.querySelectorAll('#userModal .user-subfraccion-xi').forEach(el => el.checked = false);
    const subfSec = document.getElementById('userSubfraccionesXiSection');
    if (u.fracciones_pld) {
        let f = u.fracciones_pld;
        if (typeof f === 'string') { try { f = JSON.parse(f); } catch(e) { f = []; } }
        if (Array.isArray(f)) f.forEach(x => { const e = document.getElementById('pld_frac_' + String(x).replace(/ /g, '_')); if (e) { e.checked = true; if (x === 'XI' && subfSec) subfSec.style.display = 'block'; } });
    }
    if (u.subfracciones_xi) {
        let sf = u.subfracciones_xi;
        if (typeof sf === 'string') { try { sf = JSON.parse(sf); } catch(e) { sf = []; } }
        if (Array.isArray(sf)) sf.forEach(x => { const e = document.getElementById('user_subf_' + x); if (e) e.checked = true; });
    }
    userModal.show();
}

function toggleUserSubfraccionesXI() {
    const xiCb = document.getElementById('pld_frac_XI');
    const sec = document.getElementById('userSubfraccionesXiSection');
    if (xiCb && sec) sec.style.display = xiCb.checked ? 'block' : 'none';
}
</script>
<?php include '../templates/footer.php'; ?>
