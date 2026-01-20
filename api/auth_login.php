<?php
// 1. Namespaces go at the very top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
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

        // SEND EMAIL VIA SMTP
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->CharSet    = 'UTF-8';
            $mail->Host       = 'smtp.ionos.mx';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'no-reply@adsoft.mx'; // REPLACE THIS
            $mail->Password   = 'Ex1t0@2026';  // REPLACE WITH APP PASSWORD
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('no-reply@adsoft.mx', 'Investor Security');
            $mail->addAddress($email); 

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Investor App - Verification Code';
            $mail->Body    = "<h3>Tu código de acceso es:</h3><h1>$code</h1><p>Expira en 10 minutos.</p>";
            $mail->AltBody = "Tu código de acceso es: $code";

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