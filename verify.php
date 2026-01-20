<?php
require_once 'config/db.php';

$token = $_GET['token'] ?? '';
$message = '';
$status = 'error';

if ($token) {
    try {
        // 1. Find user with this token
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, login_user FROM usuarios WHERE verification_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // 2. Activate User and Clear Token
            $pdo->beginTransaction();
            
            $update = $pdo->prepare("UPDATE usuarios SET id_status_usuario = 1, verification_token = NULL WHERE id_usuario = ?");
            $update->execute([$user['id_usuario']]);

            // 3. Log the Activation
            $detalles = json_encode(['info' => 'Activación por correo verificada']);
            // Assuming system ID 0 for automated actions
            $log = $pdo->prepare("INSERT INTO bitacora (id_usuario, accion, tabla, id_registro, valor_anterior, valor_nuevo, fecha) VALUES (0, 'ACTIVAR', 'usuarios', ?, NULL, ?, NOW())");
            $log->execute([$user['id_usuario'], $detalles]);

            $pdo->commit();

            $status = 'success';
            $message = '¡Cuenta verificada exitosamente! Ya puede iniciar sesión.';
        } else {
            $message = 'El enlace de verificación es inválido o ya fue utilizado.';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error en el sistema: ' . $e->getMessage();
    }
} else {
    $message = 'Token no proporcionado.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Cuenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
    <div class="card shadow p-4 text-center" style="max-width: 400px;">
        <div class="mb-3">
            <?php if($status === 'success'): ?>
                <h1 class="text-success" style="font-size: 4rem;">&#10003;</h1>
                <h4 class="mb-3">¡Verificado!</h4>
            <?php else: ?>
                <h1 class="text-danger" style="font-size: 4rem;">&#10007;</h1>
                <h4 class="mb-3">Error</h4>
            <?php endif; ?>
        </div>
        <p class="text-muted"><?= htmlspecialchars($message) ?></p>
        <a href="login.php" class="btn btn-primary w-100 mt-3">Ir al Login</a>
    </div>
</body>
</html>