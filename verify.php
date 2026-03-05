<?php
require_once 'config/db.php';

$token = $_GET['token'] ?? '';
$message = '';
$status = 'error';
$appName = 'EVE 360';
try {
    $cfg = $pdo->query("SELECT nombre_empresa FROM config_empresa WHERE id_config = 1")->fetch(PDO::FETCH_ASSOC);
    if (!empty($cfg['nombre_empresa'])) $appName = $cfg['nombre_empresa'];
} catch (Exception $e) { /* fallback */ }

if ($token) {
    try {
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, login_user FROM usuarios WHERE verification_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $pdo->beginTransaction();
            $update = $pdo->prepare("UPDATE usuarios SET id_status_usuario = 1, verification_token = NULL WHERE id_usuario = ?");
            $update->execute([$user['id_usuario']]);
            $detalles = json_encode(['info' => 'Activación por correo verificada']);
            $log = $pdo->prepare("INSERT INTO bitacora (id_usuario, accion, tabla_afectada, id_afectado, valor_anterior, valor_nuevo, fecha) VALUES (0, 'ACTIVAR', 'usuarios', ?, NULL, ?, NOW())");
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
    <title>Verificación - <?= htmlspecialchars($appName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
:root {
    --v-primary: #4361ee;
    --v-primary-dark: #3a0ca3;
    --v-success: #06d6a0;
    --v-success-dark: #028a6e;
    --v-danger: #ef4444;
    --v-danger-dark: #dc2626;
    --v-card-bg: #ffffff;
    --v-text: #0f172a;
    --v-text-muted: #64748b;
}
* { box-sizing: border-box; }
html { height: 100%; }
body {
    min-height: 100vh; margin: 0; padding: 1.5rem;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    background-attachment: fixed;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif;
    position: relative; overflow-x: hidden;
}
body::before {
    content: ''; position: fixed; inset: 0; z-index: 0;
    background:
        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(67,97,238,.15), transparent),
        radial-gradient(ellipse 60% 40% at 100% 100%, rgba(58,12,163,.1), transparent),
        radial-gradient(ellipse 50% 30% at 0% 80%, rgba(67,97,238,.08), transparent);
    pointer-events: none;
}
.verify-wrapper { position: relative; z-index: 1; width: 100%; max-width: 420px; }
.verify-card {
    background: var(--v-card-bg);
    border-radius: 24px;
    padding: 2.5rem;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,.4), 0 0 0 1px rgba(255,255,255,.05);
    text-align: center;
    animation: verifyIn .5s cubic-bezier(.4,0,.2,1);
}
@keyframes verifyIn {
    from { opacity: 0; transform: translateY(20px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.verify-logo {
    width: 72px; height: 72px; margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, var(--v-primary), var(--v-primary-dark));
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 2rem;
    box-shadow: 0 12px 28px rgba(67,97,238,.35);
}
.verify-icon {
    width: 88px; height: 88px; margin: 0 auto 1.25rem;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.75rem;
}
.verify-icon.success {
    background: linear-gradient(135deg, rgba(6,214,160,.15), rgba(2,138,110,.15));
    color: var(--v-success);
    border: 3px solid rgba(6,214,160,.4);
}
.verify-icon.error {
    background: linear-gradient(135deg, rgba(239,68,68,.12), rgba(220,38,38,.12));
    color: var(--v-danger);
    border: 3px solid rgba(239,68,68,.35);
}
.verify-title {
    font-size: 1.5rem; font-weight: 800; margin-bottom: .5rem;
    color: var(--v-text); letter-spacing: -0.02em;
}
.verify-title.success { color: var(--v-success-dark); }
.verify-title.error { color: var(--v-danger); }
.verify-message {
    color: var(--v-text-muted);
    font-size: 1rem; line-height: 1.6;
    margin-bottom: 1.75rem;
}
.verify-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
    background: linear-gradient(135deg, var(--v-primary), var(--v-primary-dark));
    color: #fff !important; font-weight: 600; font-size: 1rem;
    padding: .9rem 1.75rem; border-radius: 14px; text-decoration: none;
    border: none; cursor: pointer; transition: transform .2s, box-shadow .2s;
    box-shadow: 0 6px 20px rgba(67,97,238,.4);
}
.verify-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(67,97,238,.5); color: #fff !important; }
.verify-footer { margin-top: 1.5rem; font-size: .8rem; color: var(--v-text-muted); }
@media (max-width: 480px) {
    .verify-card { padding: 1.75rem; }
    .verify-title { font-size: 1.25rem; }
}
    </style>
</head>
<body>
    <div class="verify-wrapper">
        <div class="verify-card">
            <div class="verify-logo"><i class="fa-solid fa-shield-halved"></i></div>
            <?php if ($status === 'success'): ?>
                <div class="verify-icon success"><i class="fa-solid fa-circle-check"></i></div>
                <h2 class="verify-title success">¡Verificado!</h2>
            <?php else: ?>
                <div class="verify-icon error"><i class="fa-solid fa-circle-xmark"></i></div>
                <h2 class="verify-title error">Verificación</h2>
            <?php endif; ?>
            <p class="verify-message"><?= htmlspecialchars($message) ?></p>
            <a href="login.php" class="verify-btn">
                <i class="fa-solid fa-right-to-bracket"></i>
                Ir al Login
            </a>
            <p class="verify-footer"><?= htmlspecialchars($appName) ?></p>
        </div>
    </div>
</body>
</html>