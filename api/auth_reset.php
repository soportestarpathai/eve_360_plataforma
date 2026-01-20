<?php
// api/auth_reset.php
require_once '../config/db.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $token = $data['token'] ?? '';
    $password = $data['password'] ?? '';

    if (!$token || !$password) {
        throw new Exception("Datos incompletos.");
    }

    // 1. Verify Token and Expiry
    $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE password_reset_token = ? AND password_reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("El enlace ha expirado o no es válido.");
    }

    // 2. Update Password and Clear Token
    // Hash password using bcrypt (PASSWORD_DEFAULT)
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE usuarios SET login_password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id_usuario = ?");
    $update->execute([$hash, $user['id_usuario']]);

    echo json_encode(['status' => 'success', 'message' => 'Contraseña actualizada']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>