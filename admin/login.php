<?php
session_start();
require_once '../config/db.php';

$error = "";
$adminEmail = "";

// Get admin email from database
try {
    $stmt = $pdo->query("SELECT email FROM admin_access WHERE id = 1");
    $adminEmail = $stmt->fetchColumn() ?: "No configurado";
} catch (Exception $e) {
    $adminEmail = "Error al cargar";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['access_code'] ?? '';

    // Verify Code
    $stmt = $pdo->query("SELECT temp_password_hash, expires_at FROM admin_access WHERE id = 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && $admin['temp_password_hash']) {
        if (strtotime($admin['expires_at']) < time()) {
            $error = "El código ha expirado. Solicite uno nuevo.";
        } elseif (password_verify($code, $admin['temp_password_hash'])) {
            // Success!
            $_SESSION['is_admin'] = true;
            // Clear used code
            $pdo->query("UPDATE admin_access SET temp_password_hash = NULL, expires_at = NULL WHERE id = 1");
            header("Location: index.php");
            exit;
        } else {
            $error = "Código incorrecto.";
        }
    } else {
        $error = "No hay código pendiente. Solicite uno primero.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #1a1d20; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 400px; background: #fff; padding: 2.5rem; border-radius: 12px; }
        .step-hidden { display: none; }
    </style>
</head>
<body>

    <div class="login-card shadow-lg">
        <div class="text-center mb-4">
            <i class="fa-solid fa-user-shield fa-3x text-dark mb-3"></i>
            <h4>Panel Administrativo</h4>
            <p class="text-muted small">Acceso restringido</p>
        </div>

        <?php if($error): ?>
            <div class="alert alert-danger small text-center"><?= $error ?></div>
        <?php endif; ?>

        <!-- STEP 1: Request Code -->
        <div id="step1">
            <div class="d-grid gap-2">
                <button id="btnSendCode" class="btn btn-dark btn-lg">
                    <i class="fa-regular fa-envelope me-2"></i>Enviar Código de Acceso
                </button>
            </div>
            <p class="text-muted small text-center mt-3">
                Se enviará una clave temporal a<br><strong><?= htmlspecialchars($adminEmail) ?></strong>
            </p>
        </div>

        <!-- STEP 2: Enter Code -->
        <form id="step2" method="POST" class="step-hidden">
            <div class="mb-3">
                <label class="form-label fw-bold">Código de Acceso</label>
                <input type="text" name="access_code" class="form-control form-control-lg text-center letter-spacing-2" placeholder="XXXXXXXX" autocomplete="off" required>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-success btn-lg">Entrar</button>
                <button type="button" class="btn btn-link btn-sm text-decoration-none" onclick="location.reload()">Cancelar / Reenviar</button>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('btnSendCode').addEventListener('click', async function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Enviando...';

            try {
                const res = await fetch('send_code.php');
                const json = await res.json();

                if (json.status === 'success') {
                    document.getElementById('step1').classList.add('step-hidden');
                    document.getElementById('step2').classList.remove('step-hidden');
                } else {
                    alert("Error: " + json.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-regular fa-envelope me-2"></i>Reintentar';
                }
            } catch (err) {
                alert("Error de conexión");
                btn.disabled = false;
                btn.innerHTML = 'Error';
            }
        });
    </script>
</body>
</html>