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
        $mail->Subject = 'Restablecer contrasena - Investor';
        $mail->Body    = "
            <h3>Solicitud para restablecer contraseña</h3>
            <p>Haz clic en el siguiente enlace para crear una nueva contraseña:</p>
            <p><a href='$link'>$link</a></p>
            <p>Este enlace expira en 1 hora.</p>
        ";
        
        $mail->send();
    }

    // Always say success to prevent email enumeration (security best practice)
    echo json_encode(['status' => 'success', 'message' => 'Si el correo existe, se han enviado las instrucciones.']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>