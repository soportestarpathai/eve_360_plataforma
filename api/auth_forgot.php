<?php
// api/auth_forgot.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require '../libs/PHPMailer/Exception.php';
require '../libs/PHPMailer/PHPMailer.php';
require '../libs/PHPMailer/SMTP.php';
require_once '../config/db.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Correo inválido.");
    }

    // 1. Check if user exists
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE login_user = ? AND id_status_usuario = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // 2. Generate Token (32 bytes hex)
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Valid for 1 hour

        // 3. Save to DB
        $update = $pdo->prepare("UPDATE usuarios SET password_reset_token = ?, password_reset_expires = ? WHERE id_usuario = ?");
        $update->execute([$token, $expires, $user['id_usuario']]);

        // 4. Build Link (Detects your current domain/folder automatically)
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname(dirname($_SERVER['PHP_SELF'])); // Go up one level from /api/
        $link = "$protocol://$host$path/reset_password.html?token=$token";

        // 5. Send Email
        $mail = new PHPMailer(true);
        
        // --- COPY YOUR SMTP SETTINGS HERE ---
        $mail->isSMTP();
        $mail->CharSet    = 'UTF-8';
        $mail->Host       = 'smtp.ionos.mx';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'no-reply@adsoft.mx'; // <--- EDIT
        $mail->Password   = 'Ex1t0@2026'; // <--- EDIT
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        // ------------------------------------

        $mail->setFrom('no-reply@adsoft.mx', 'Investor Security');
        $mail->addAddress($email); 

        $mail->isHTML(true);
        $mail->Subject = 'Restablecer contraseña - Investor Security';
        $mail->Body    = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0; padding:0; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f0f2f5; padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 520px; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow: hidden;">
                            <tr>
                                <td style="background: linear-gradient(135deg, #0B3C8A 0%, #0B486B 100%); padding: 32px 40px; text-align: center;">
                                    <h1 style="margin:0; font-size: 22px; font-weight: 600; color: #ffffff; letter-spacing: 0.5px;">Investor Security</h1>
                                    <p style="margin: 8px 0 0 0; font-size: 13px; color: rgba(255,255,255,0.85); font-weight: 400;">Plataforma de cumplimiento</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 40px 40px 32px 40px;">
                                    <h2 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #1a1a2e;">Solicitud para restablecer contraseña</h2>
                                    <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.6; color: #4a5568;">Recibimos una solicitud para restablecer la contraseña de tu cuenta. Haz clic en el botón de abajo para crear una nueva contraseña.</p>
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                        <tr>
                                            <td style="border-radius: 10px; background: linear-gradient(135deg, #0B3C8A 0%, #0B486B 100%); box-shadow: 0 4px 14px rgba(11, 60, 138, 0.35);">
                                                <a href="' . $link . '" target="_blank" style="display: inline-block; padding: 16px 32px; font-size: 15px; font-weight: 600; color: #ffffff !important; text-decoration: none; letter-spacing: 0.3px;">Restablecer contraseña</a>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style="margin: 28px 0 0 0; font-size: 12px; line-height: 1.6; color: #718096;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                                    <p style="margin: 8px 0 0 0; font-size: 12px; word-break: break-all; color: #0B3C8A;"><a href="' . $link . '" style="color:#0B3C8A; text-decoration:underline;">' . $link . '</a></p>
                                    <div style="margin-top: 32px; padding: 16px; background-color: #fffbeb; border-radius: 10px; border-left: 4px solid #f59e0b;">
                                        <p style="margin: 0; font-size: 13px; color: #92400e; font-weight: 500;">⏱ Este enlace expira en <strong>1 hora</strong>. Si no solicitaste este cambio, ignora este correo.</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 24px 40px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center;">
                                    <p style="margin: 0; font-size: 12px; color: #64748b;">Investor Security · <a href="#" style="color:#0B3C8A; text-decoration:none;">EVE 360</a></p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
        
        $mail->send();
    }

    // Always say success to prevent email enumeration (security best practice)
    echo json_encode(['status' => 'success', 'message' => 'Si el correo existe, se han enviado las instrucciones.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>