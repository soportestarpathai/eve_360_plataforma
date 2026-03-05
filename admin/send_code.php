<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Load PHPMailer (Adjust path if necessary, assuming libs is in root)
require '../libs/PHPMailer/Exception.php';
require '../libs/PHPMailer/PHPMailer.php';
require '../libs/PHPMailer/SMTP.php';
require_once '../config/db.php';

function buildAdminCodeEmail($code, $appName) {
    $codeEscaped = htmlspecialchars($code);
    return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Código de Acceso Administrativo</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f1f5f9;">
<tr><td style="padding:32px 16px;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
<tr>
<td style="background-color:#4361ee;background:linear-gradient(135deg,#4361ee 0%,#3a0ca3 100%);padding:28px 32px;text-align:center;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
<tr><td style="width:48px;height:48px;background:rgba(255,255,255,0.2);border-radius:12px;text-align:center;color:#ffffff;font-size:22px;line-height:48px;">&#128274;</td></tr>
</table>
<p style="margin:16px 0 0 0;color:#ffffff;font-size:18px;font-weight:700;letter-spacing:-0.02em;">' . htmlspecialchars($appName) . '</p>
<p style="margin:4px 0 0 0;color:rgba(255,255,255,0.9);font-size:14px;">Código de Acceso Administrativo</p>
</td>
</tr>
<tr>
<td style="padding:32px;">
<p style="margin:0 0 16px 0;color:#64748b;font-size:15px;line-height:1.5;">Solicitaste acceso al panel administrativo. Tu código temporal es:</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
<tr><td style="background:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;padding:20px;text-align:center;">
<p style="margin:0;font-family:\'Courier New\',Courier,monospace;font-size:24px;font-weight:700;letter-spacing:0.3em;color:#0f172a;">' . $codeEscaped . '</p>
</td></tr>
</table>
<p style="margin:20px 0 0 0;color:#94a3b8;font-size:13px;">
<span style="display:inline-block;background:#fef3c7;color:#92400e;padding:6px 12px;border-radius:8px;font-weight:600;">Expira en 15 minutos</span>
</p>
<p style="margin:24px 0 0 0;color:#64748b;font-size:13px;line-height:1.5;">Si no solicitaste este código, ignora este correo. No compartas este código con nadie.</p>
</td>
</tr>
<tr>
<td style="padding:20px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
<p style="margin:0;color:#94a3b8;font-size:12px;">&copy; ' . date('Y') . ' ' . htmlspecialchars($appName) . ' &middot; Acceso restringido</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>';
}

header('Content-Type: application/json');

try {
    $email = trim($_GET['email'] ?? $_POST['email'] ?? '');

    // Fallback: admin_access (id=1) si no hay admin_users o no se pasó email
    if (empty($email)) {
        $stmt = $pdo->query("SELECT email FROM admin_access WHERE id = 1");
        $email = $stmt->fetchColumn();
    }

    $appName = 'EVE 360';
    try {
        $cfg = $pdo->query("SELECT nombre_empresa FROM config_empresa WHERE id_config = 1")->fetch(PDO::FETCH_ASSOC);
        if (!empty($cfg['nombre_empresa'])) $appName = $cfg['nombre_empresa'];
    } catch (Exception $e) { /* fallback */ }

    if (!$email) {
        throw new Exception("Indique el email del administrador.");
    }

    $randomCode = bin2hex(random_bytes(4));
    $hash = password_hash($randomCode, PASSWORD_DEFAULT);
    $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // Actualizar admin_users si existe y el email está registrado; si no, admin_access (legacy)
    $hasAdminUsers = $pdo->query("SHOW TABLES LIKE 'admin_users'")->rowCount() > 0;
    if ($hasAdminUsers) {
        $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE LOWER(email) = LOWER(?) AND (id_status = 1 OR id_status IS NULL)");
        $stmt->execute([$email]);
        $adminRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($adminRow) {
            $pdo->prepare("UPDATE admin_users SET temp_password_hash = ?, expires_at = ? WHERE id = ?")
                ->execute([$hash, $expiry, $adminRow['id']]);
        } else {
            $legacyEmail = $pdo->query("SELECT email FROM admin_access WHERE id = 1")->fetchColumn();
            if ($legacyEmail && strcasecmp(trim($legacyEmail), $email) === 0) {
                $pdo->prepare("UPDATE admin_access SET temp_password_hash = ?, expires_at = ? WHERE id = 1")
                    ->execute([$hash, $expiry]);
            } else {
                throw new Exception("El email no está registrado como administrador.");
            }
        }
    } else {
        $pdo->prepare("UPDATE admin_access SET temp_password_hash = ?, expires_at = ? WHERE id = 1")
            ->execute([$hash, $expiry]);
    }

    // 4. Send Email
    $mail = new PHPMailer(true);
    
    // Server settings (Copy from your auth_login.php or config)
    $mail->isSMTP();
    $mail->CharSet    = 'UTF-8';
    $mail->Host       = 'smtp.ionos.mx'; 
    $mail->SMTPAuth   = true;
    $mail->Username   = 'no-reply@adsoft.mx'; // REPLACE WITH YOUR SENDER EMAIL
    $mail->Password   = 'Ex1t0@2026'; // REPLACE WITH YOUR APP PASSWORD
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('no-reply@adsoft.mx', $appName . ' - Seguridad');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = $appName . ' - Código de Acceso Administrativo';
    $mail->Body    = buildAdminCodeEmail($randomCode, $appName);
    $mail->AltBody = "Código de Acceso Administrativo\n\nTu código temporal es: $randomCode\n\nExpira en 15 minutos.\n\nSi no solicitaste este código, ignora este correo.";

    $mail->send();

    echo json_encode(['status' => 'success', 'message' => 'Código enviado a ' . $email]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>