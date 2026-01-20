<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Load PHPMailer (Adjust path if necessary, assuming libs is in root)
require '../libs/PHPMailer/Exception.php';
require '../libs/PHPMailer/PHPMailer.php';
require '../libs/PHPMailer/SMTP.php';
require_once '../config/db.php';

header('Content-Type: application/json');

try {
    // 1. Generate Random Code
    $randomCode = bin2hex(random_bytes(4)); // 8 chars
    $hash = password_hash($randomCode, PASSWORD_DEFAULT);
    $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    // 2. Get Admin Email
    $stmt = $pdo->query("SELECT email FROM admin_access WHERE id = 1");
    $email = $stmt->fetchColumn();

    if (!$email) {
        throw new Exception("No admin email configured.");
    }

    // 3. Update DB
    $update = $pdo->prepare("UPDATE admin_access SET temp_password_hash = ?, expires_at = ? WHERE id = 1");
    $update->execute([$hash, $expiry]);

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

    $mail->setFrom('no-reply@adsoft.mx', 'Investor Admin Security');
    $mail->addAddress($email); 

    $mail->isHTML(true);
    $mail->Subject = 'Investor App - Admin Access Code';
    $mail->Body    = "
        <h3>Código de Acceso Administrativo</h3>
        <p>Tu código temporal es:</p>
        <h1 style='letter-spacing: 5px; background: #f0f0f0; padding: 10px; display: inline-block;'>$randomCode</h1>
        <p>Expira en 15 minutos.</p>
    ";

    $mail->send();

    echo json_encode(['status' => 'success', 'message' => 'Código enviado a ' . $email]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>