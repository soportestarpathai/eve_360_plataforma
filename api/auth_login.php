<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

function build2FACodeEmail($code, $appName) {
    $codeEsc = htmlspecialchars($code);
    $appEsc = htmlspecialchars($appName);
    return '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Código de verificación</title></head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f1f5f9;">
<tr><td style="padding:32px 16px;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
<tr>
<td style="background:linear-gradient(135deg,#4361ee 0%,#3a0ca3 100%);padding:28px 32px;text-align:center;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
<tr><td style="width:48px;height:48px;background:rgba(255,255,255,0.2);border-radius:12px;text-align:center;color:#fff;font-size:22px;line-height:48px;">&#128274;</td></tr>
</table>
<p style="margin:16px 0 0 0;color:#fff;font-size:18px;font-weight:700;">' . $appEsc . '</p>
<p style="margin:4px 0 0 0;color:rgba(255,255,255,0.9);font-size:14px;">Código de verificación</p>
</td>
</tr>
<tr>
<td style="padding:32px;">
<p style="margin:0 0 16px 0;color:#64748b;font-size:15px;line-height:1.5;">Solicitaste iniciar sesión. Tu código temporal es:</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
<tr><td style="background:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;padding:24px;text-align:center;">
<p style="margin:0;font-family:\'Courier New\',monospace;font-size:32px;font-weight:700;letter-spacing:0.4em;color:#0f172a;">' . $codeEsc . '</p>
</td></tr>
</table>
<p style="margin:20px 0 0 0;color:#94a3b8;font-size:13px;">
<span style="display:inline-block;background:#fef3c7;color:#92400e;padding:6px 12px;border-radius:8px;font-weight:600;">Expira en 10 minutos</span>
</p>
<p style="margin:24px 0 0 0;color:#64748b;font-size:13px;">Si no solicitaste este código, ignora este correo.</p>
</td>
</tr>
<tr>
<td style="padding:20px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
<p style="margin:0;color:#94a3b8;font-size:12px;">&copy; ' . date('Y') . ' ' . $appEsc . '</p>
</td>
</tr>
</table>
</td></tr></table>
</body>
</html>';
}
header('Content-Type: application/json');

// 1. Load PHPMailer Manually
require '../libs/PHPMailer/Exception.php';
require '../libs/PHPMailer/PHPMailer.php';
require '../libs/PHPMailer/SMTP.php';

try {
    // DB Connection
    if (!file_exists('../config/db.php')) throw new Exception("Missing db.php");
    require_once '../config/db.php';
    
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $deviceToken = $_COOKIE['trusted_device'] ?? '';

    // 2. Authenticate User
    $stmt = $pdo->prepare("SELECT id_usuario, nombre, login_password FROM usuarios WHERE login_user = ? AND id_status_usuario = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas']);
        exit;
    }
    
    // Verify Password (Assuming plain text for now based on previous steps, update to password_verify if hashed)
    if (password_verify($password, $user['login_password']) === false) {
        echo json_encode(['status' => 'error', 'message' => 'Credenciales incorrectas']); 
        exit; 
    }

    // 3. Check Trusted Device
    $isTrusted = false;
    if ($deviceToken) {
        $stmt = $pdo->prepare("SELECT id_trusted_device FROM usuarios_trusted_devices WHERE id_usuario = ? AND device_token = ? AND expires_at > NOW()");
        $stmt->execute([$user['id_usuario'], $deviceToken]);
        if ($stmt->fetch()) $isTrusted = true;
    }

    if ($isTrusted) {
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['user_name'] = $user['nombre'];
        echo json_encode(['status' => 'success', 'message' => 'Login exitoso']);
    } else {
        // 4. Generate & Send 2FA Code
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Save to DB
        $update = $pdo->prepare("UPDATE usuarios SET two_factor_code = ?, two_factor_expires = ? WHERE id_usuario = ?");
        $update->execute([$code, $expires, $user['id_usuario']]);

        // Obtener nombre de la app
        $appName = 'EVE 360';
        try {
            $cfg = $pdo->query("SELECT nombre_empresa FROM config_empresa WHERE id_config = 1")->fetch(PDO::FETCH_ASSOC);
            if (!empty($cfg['nombre_empresa'])) $appName = $cfg['nombre_empresa'];
        } catch (Exception $e) { /* fallback */ }

        // SEND EMAIL VIA SMTP
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->CharSet = 'UTF-8';
            $mail->Host = 'smtp.ionos.mx';
            $mail->SMTPAuth = true;
            $mail->Username = 'no-reply@adsoft.mx';
            $mail->Password = 'Ex1t0@2026';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('no-reply@adsoft.mx', $appName . ' - Seguridad');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = $appName . ' - Código de verificación';
            $mail->Body = build2FACodeEmail($code, $appName);
            $mail->AltBody = "Código de verificación\n\nTu código de acceso es: $code\n\nExpira en 10 minutos.\n\nSi no solicitaste este código, ignora este correo.";

            $mail->send();
        } catch (Exception $e) {
            // If mail fails, we log it but maybe still allow logic to proceed for DEV testing?
            // For now, we return error so you know it failed.
            throw new Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
        
        // Return response (Debug code removed)
        echo json_encode([
            'status' => '2fa_required', 
            'temp_token' => $user['id_usuario']
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>