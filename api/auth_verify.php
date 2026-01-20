<?php
session_start();
header('Content-Type: application/json');
require_once '../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);
$userId = $data['user_id'];
$code = $data['code'];
$trustDevice = $data['trust_device'] ?? false;

try {
    // 1. Verify Code
    $stmt = $pdo->prepare("SELECT id_usuario, nombre FROM usuarios WHERE id_usuario = ? AND two_factor_code = ? AND two_factor_expires > NOW()");
    $stmt->execute([$userId, $code]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Código inválido o expirado']);
        exit;
    }

    // 2. Clear Code
    $pdo->prepare("UPDATE usuarios SET two_factor_code = NULL, two_factor_expires = NULL WHERE id_usuario = ?")->execute([$userId]);

    // 3. Handle Trusted Device
    if ($trustDevice) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Save to DB
        $stmt = $pdo->prepare("INSERT INTO usuarios_trusted_devices (id_usuario, device_token, user_agent, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $token, $_SERVER['HTTP_USER_AGENT'], $expires]);

        // Set Cookie (30 days)
        setcookie('trusted_device', $token, time() + (86400 * 30), "/", "", false, true);
    }

    // 4. Start Session
    $_SESSION['user_id'] = $user['id_usuario'];
    $_SESSION['user_name'] = $user['nombre'];

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>