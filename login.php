<?php
session_start();
require_once 'config/db.php';

// Fetch Branding Config
$appConfig = [
    'nombre_empresa' => 'Investor MLP',
    'logo_url' => 'assets/img/logo_default.png',
    'color_primario' => '#0d6efd'
];

try {
    $stmt = $pdo->query("SELECT * FROM config_empresa WHERE id_config = 1");
    $dbConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dbConfig) {
        $appConfig = array_merge($appConfig, $dbConfig);
    }
} catch (Exception $e) {
    // Fallback defaults
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($appConfig['color_primario']) ?>;
        }
        body { 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background-color: #f8f9fa; 
        }
        .login-card { width: 100%; max-width: 400px; padding: 20px; border: none; }
        .btn-primary { 
            background-color: var(--primary-color) !important; 
            border-color: var(--primary-color) !important; 
        }
        .step-2 { display: none; }
        /* Hide forgot form by default */
        #forgotForm { display: none; }
        .logo-container img { max-height: 60px; max-width: 80%; }
    </style>
</head>
<body>

    <div class="card login-card shadow-sm">
        <div class="card-body">
            <div class="text-center mb-4 logo-container">
                <?php if(file_exists($appConfig['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($appConfig['logo_url']) ?>" alt="Logo">
                <?php else: ?>
                    <h3><?= htmlspecialchars($appConfig['nombre_empresa']) ?></h3>
                <?php endif; ?>
            </div>

            <!-- Step 1: Email & Password -->
            <form id="loginForm">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" id="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" id="password" class="form-control" required>
                    <div class="text-end mt-1">
                        <a href="#" id="showForgot" class="small text-decoration-none" style="color: var(--primary-color);">¿Olvidé mi contraseña?</a>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">Ingresar</button>
            </form>

            <!-- Forgot Password Form (Hidden by default) -->
            <form id="forgotForm">
                <h5 class="text-center mb-3">Recuperar Contraseña</h5>
                <p class="small text-muted text-center">Ingresa tu correo y te enviaremos un enlace para restablecer tu acceso.</p>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" id="forgotEmail" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-2">Enviar Enlace</button>
                <button type="button" id="cancelForgot" class="btn btn-link w-100 text-decoration-none text-muted">Cancelar</button>
            </form>

            <!-- Step 2: 2FA Code -->
            <form id="otpForm" class="step-2">
                <div class="alert alert-info">
                    Hemos enviado un código de 6 dígitos a tu correo.
                </div>
                <div class="mb-3">
                    <label class="form-label">Código de Verificación</label>
                    <input type="text" id="otpCode" class="form-control" maxlength="6" placeholder="123456" required>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="trustDevice">
                    <label class="form-check-label" for="trustDevice">Confiar en este equipo</label>
                </div>
                <button type="submit" class="btn btn-success w-100">Verificar</button>
            </form>
            
            <div id="message" class="mt-3 text-center text-danger"></div>
        </div>
    </div>

    <script>
        let tempUserId = null;

        // --- TOGGLE FORMS ---
        document.getElementById('showForgot').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('forgotForm').style.display = 'block';
            document.getElementById('message').innerText = ''; // Clear errors
        });

        document.getElementById('cancelForgot').addEventListener('click', function() {
            document.getElementById('forgotForm').style.display = 'none';
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('message').innerText = '';
        });

        // --- LOGIN LOGIC ---
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            const res = await fetch('api/auth_login.php', {
                method: 'POST',
                body: JSON.stringify({ email, password })
            });
            const data = await res.json();

            if (data.status === 'success') {
                window.location.href = 'index.php'; 
            } else if (data.status === '2fa_required') {
                tempUserId = data.temp_token;
                document.getElementById('loginForm').style.display = 'none';
                document.querySelector('.step-2').style.display = 'block';
            } else {
                document.getElementById('message').innerText = data.message;
            }
        });

        // --- OTP LOGIC ---
        document.getElementById('otpForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const code = document.getElementById('otpCode').value;
            const trust = document.getElementById('trustDevice').checked;

            const res = await fetch('api/auth_verify.php', {
                method: 'POST',
                body: JSON.stringify({ user_id: tempUserId, code: code, trust_device: trust })
            });
            const data = await res.json();

            if (data.status === 'success') {
                window.location.href = 'index.php';
            } else {
                document.getElementById('message').innerText = data.message;
            }
        });

        // --- FORGOT PASSWORD LOGIC ---
        document.getElementById('forgotForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const email = document.getElementById('forgotEmail').value;
            const btn = this.querySelector('button[type="submit"]');
            
            btn.disabled = true;
            btn.innerHTML = 'Enviando...';

            try {
                // Assuming you have api/auth_forgot.php created in a previous step
                const res = await fetch('api/auth_forgot.php', {
                    method: 'POST',
                    body: JSON.stringify({ email })
                });
                const data = await res.json();
                
                // Always show success message for security (or specific error if you prefer)
                document.getElementById('message').className = 'mt-3 text-center text-success';
                document.getElementById('message').innerText = data.message || 'Si el correo existe, se enviaron las instrucciones.';
                
                // Hide form after delay
                setTimeout(() => {
                    document.getElementById('forgotForm').style.display = 'none';
                    document.getElementById('loginForm').style.display = 'block';
                    document.getElementById('message').innerText = '';
                    btn.disabled = false;
                    btn.innerHTML = 'Enviar Enlace';
                }, 3000);

            } catch (err) {
                document.getElementById('message').className = 'mt-3 text-center text-danger';
                document.getElementById('message').innerText = "Error de conexión.";
                btn.disabled = false;
                btn.innerHTML = 'Enviar Enlace';
            }
        });
    </script>
</body>
</html>