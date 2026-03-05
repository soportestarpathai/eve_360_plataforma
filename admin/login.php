<?php
session_start();
require_once '../config/db.php';

$error = "";
$adminEmail = "";
$showStep2 = false; // Step 2 = ingresar código (siempre inicia en Step 1)
$showPasswordMode = false;

// Obtener email por defecto (admin_access id=1)
try {
    $stmt = $pdo->query("SELECT email FROM admin_access WHERE id = 1");
    $adminEmail = $stmt->fetchColumn() ?: "";
} catch (Exception $e) {
    $adminEmail = "";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginMode = $_POST['login_mode'] ?? 'code';
    $email = trim($_POST['admin_email'] ?? '');
    $code = trim($_POST['access_code'] ?? '');
    $password = $_POST['admin_password'] ?? '';

    if (empty($email)) {
        $error = "Indique su correo electrónico.";
        $showStep2 = !empty($_POST['admin_email']) ? false : ($loginMode === 'code');
        $showPasswordMode = ($loginMode === 'password');
    } elseif ($loginMode === 'password') {
        // --- ACCESO POR CONTRASEÑA ---
        if (empty($password)) {
            $error = "Indique su contraseña.";
            $showPasswordMode = true;
        } else {
            $admin = null;
            $useAdminUsers = false;
            try {
                $hasPasswordCol = false;
                try {
                    $chk = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'password_hash'");
                    $hasPasswordCol = $chk->rowCount() > 0;
                } catch (Exception $e) { /* ignore */ }

                if ($hasPasswordCol && $pdo->query("SHOW TABLES LIKE 'admin_users'")->rowCount() > 0) {
                    $stmt = $pdo->prepare("SELECT id, password_hash FROM admin_users WHERE LOWER(email) = LOWER(?) AND (id_status = 1 OR id_status IS NULL)");
                    $stmt->execute([$email]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($admin) $useAdminUsers = true;
                }
                if (!$admin && $hasPasswordCol) {
                    $legacyEmail = $pdo->query("SELECT email FROM admin_access WHERE id = 1")->fetchColumn();
                    if ($legacyEmail && strcasecmp(trim($legacyEmail), $email) === 0) {
                        $stmt = $pdo->query("SELECT id, password_hash FROM admin_access WHERE id = 1");
                        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            } catch (Exception $e) { /* ignore */ }

            if (!$admin || empty($admin['password_hash'])) {
                $error = "No tiene contraseña configurada. Use el acceso por código.";
                $showPasswordMode = true;
            } elseif (password_verify($password, $admin['password_hash'])) {
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_email'] = $email;
                header("Location: index.php");
                exit;
            } else {
                $error = "Contraseña incorrecta.";
                $showPasswordMode = true;
            }
        }
    } else {
        // --- ACCESO POR CÓDIGO ---
        if (empty($code)) {
            $error = "Indique el código de acceso.";
            $showStep2 = true;
        } else {
            $admin = null;
            $useAdminUsers = false;
            try {
                if ($pdo->query("SHOW TABLES LIKE 'admin_users'")->rowCount() > 0) {
                    $stmt = $pdo->prepare("SELECT id, temp_password_hash, expires_at FROM admin_users WHERE LOWER(email) = LOWER(?) AND (id_status = 1 OR id_status IS NULL)");
                    $stmt->execute([$email]);
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($admin) $useAdminUsers = true;
                }
                if (!$admin) {
                    $legacyEmail = $pdo->query("SELECT email FROM admin_access WHERE id = 1")->fetchColumn();
                    if ($legacyEmail && strcasecmp(trim($legacyEmail), $email) === 0) {
                        $stmt = $pdo->query("SELECT id, temp_password_hash, expires_at FROM admin_access WHERE id = 1");
                        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            } catch (Exception $e) { /* ignorar */ }

            if (!$admin || !($admin['temp_password_hash'] ?? null)) {
                $error = "No hay código pendiente para este email. Solicite uno primero.";
                $showStep2 = true;
            } elseif (strtotime($admin['expires_at']) < time()) {
                $error = "El código ha expirado. Solicite uno nuevo.";
                $showStep2 = true;
            } elseif (password_verify($code, $admin['temp_password_hash'])) {
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_email'] = $email;
                if ($useAdminUsers) {
                    $pdo->prepare("UPDATE admin_users SET temp_password_hash = NULL, expires_at = NULL WHERE id = ?")->execute([$admin['id']]);
                } else {
                    $pdo->query("UPDATE admin_access SET temp_password_hash = NULL, expires_at = NULL WHERE id = 1");
                }
                header("Location: index.php");
                exit;
            } else {
                $error = "Código incorrecto.";
                $showStep2 = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Administrativo - EVE 360</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
:root {
    --admin-primary: #4361ee;
    --admin-primary-dark: #3a0ca3;
    --admin-dark: #0f172a;
    --admin-card-bg: #ffffff;
    --admin-border: #e2e8f0;
    --admin-success: #06d6a0;
    --admin-success-dark: #028a6e;
}
* { box-sizing: border-box; }
html { height: 100%; }
body {
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 1.5rem; margin: 0;
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
.admin-login-wrapper { position: relative; z-index: 1; width: 100%; max-width: 420px; }
.admin-login-card {
    background: var(--admin-card-bg);
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow:
        0 25px 50px -12px rgba(0,0,0,.4),
        0 0 0 1px rgba(255,255,255,.05),
        inset 0 1px 0 rgba(255,255,255,.8);
    animation: adminCardIn .5s cubic-bezier(.4,0,.2,1);
}
@keyframes adminCardIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.admin-logo {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.25rem; color: #fff; font-size: 1.75rem;
    box-shadow: 0 8px 24px rgba(67,97,238,.35);
}
.admin-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: .25rem; letter-spacing: -0.02em; }
.admin-subtitle { color: #64748b; font-size: .9rem; margin-bottom: 0; }
.admin-alert {
    padding: .875rem 1rem; border-radius: 12px; font-size: .875rem;
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    border: 1px solid #fecaca;
    color: #991b1b; margin-bottom: 1.5rem;
}
.admin-btn-primary {
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark)) !important;
    border: none !important; color: #fff !important; font-weight: 600; padding: .875rem 1.5rem;
    border-radius: 12px; font-size: 1rem;
    transition: transform .2s, box-shadow .2s;
    box-shadow: 0 4px 14px rgba(67,97,238,.35);
}
.admin-btn-primary:hover, .admin-btn-primary:focus {
    transform: translateY(-2px); box-shadow: 0 8px 24px rgba(67,97,238,.45);
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark)) !important;
    border: none !important; color: #fff !important;
}
.admin-btn-primary:disabled { opacity: .8; transform: none; }
.admin-btn-success {
    background: linear-gradient(135deg, var(--admin-success), var(--admin-success-dark)) !important;
    border: none !important; color: #fff !important; font-weight: 600; padding: .875rem 1.5rem;
    border-radius: 12px; font-size: 1rem;
    transition: transform .2s, box-shadow .2s;
    box-shadow: 0 4px 14px rgba(6,214,160,.35);
}
.admin-btn-success:hover, .admin-btn-success:focus {
    transform: translateY(-2px); box-shadow: 0 8px 24px rgba(6,214,160,.45);
    background: linear-gradient(135deg, var(--admin-success), var(--admin-success-dark)) !important;
    border: none !important; color: #fff !important;
}
.admin-input {
    border: 2px solid var(--admin-border); border-radius: 12px;
    padding: .875rem 1rem; font-size: 1.1rem; text-align: center;
    letter-spacing: .3em; font-weight: 600;
    transition: border-color .2s, box-shadow .2s;
}
.admin-input:focus {
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 4px rgba(67,97,238,.15);
    outline: none;
}
.admin-input-left { letter-spacing: normal; text-align: left; }
.admin-label { font-weight: 600; color: #334155; margin-bottom: .5rem; font-size: .9rem; }
.admin-link { color: #64748b; font-size: .875rem; text-decoration: none; transition: color .2s; cursor: pointer; background: none; border: none; padding: 0; }
.admin-link:hover { color: var(--admin-primary); }
.step-hidden { display: none !important; }
.admin-email-badge {
    display: inline-flex; align-items: center; gap: .5rem;
    padding: .5rem 1rem; border-radius: 10px;
    background: #f8fafc; border: 1px solid var(--admin-border);
    font-size: .85rem; color: #475569; margin-top: .75rem;
}
.admin-email-badge i { color: var(--admin-primary); }
@media (max-width: 480px) {
    .admin-login-card { padding: 1.75rem; }
    .admin-title { font-size: 1.25rem; }
}
    </style>
</head>
<body>

    <div class="admin-login-wrapper">
        <div class="admin-login-card">
            <div class="text-center mb-4">
                <div class="admin-logo"><i class="fa-solid fa-shield-halved"></i></div>
                <h1 class="admin-title">Panel Administrativo</h1>
                <p class="admin-subtitle">Acceso restringido</p>
            </div>

            <?php if($error): ?>
                <div class="admin-alert"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($showPasswordMode): ?>
            <!-- MODO CONTRASEÑA -->
            <form method="POST">
                <input type="hidden" name="login_mode" value="password">
                <div class="mb-4">
                    <label class="admin-label d-block">Correo electrónico</label>
                    <input type="email" name="admin_email" class="form-control admin-input admin-input-left" placeholder="admin@empresa.com"
                           value="<?= htmlspecialchars($_POST['admin_email'] ?? $adminEmail) ?>" autocomplete="email" required>
                </div>
                <div class="mb-4">
                    <label class="admin-label d-block">Contraseña</label>
                    <input type="password" name="admin_password" class="form-control admin-input admin-input-left" placeholder="••••••••"
                           autocomplete="current-password" required>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn admin-btn-primary btn-lg">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Entrar
                    </button>
                    <button type="button" class="admin-link py-2" onclick="showCodeMode()">
                        <i class="fa-solid fa-key me-2"></i>Acceder con código
                    </button>
                </div>
            </form>
            <?php else: ?>

            <!-- MODO CÓDIGO: Step 1 - Solicitar código -->
            <div id="step1" class="<?= $showStep2 ? 'step-hidden' : '' ?>">
                <div class="mb-4">
                    <label class="admin-label d-block">Correo electrónico</label>
                    <input type="email" id="inputAdminEmail" class="form-control admin-input admin-input-left" placeholder="admin@empresa.com"
                           value="<?= htmlspecialchars($_POST['admin_email'] ?? $adminEmail) ?>" autocomplete="email" required>
                </div>
                <div class="d-grid">
                    <button type="button" id="btnSendCode" class="btn admin-btn-primary btn-lg">
                        <i class="fa-regular fa-envelope me-2"></i>Enviar Código de Acceso
                    </button>
                </div>
                <div class="text-center mt-3">
                    <button type="button" class="admin-link" onclick="showPasswordMode()">
                        <i class="fa-solid fa-lock me-2"></i>Acceder con contraseña
                    </button>
                </div>
            </div>

            <!-- MODO CÓDIGO: Step 2 - Ingresar código -->
            <form id="step2" method="POST" class="<?= $showStep2 ? '' : 'step-hidden' ?>">
                <input type="hidden" name="login_mode" value="code">
                <input type="hidden" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>">
                <div class="mb-2">
                    <div class="admin-email-badge">
                        <i class="fa-solid fa-at"></i>
                        <span id="step2EmailLabel"><?= htmlspecialchars($_POST['admin_email'] ?? '') ?></span>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="admin-label d-block">Código de acceso</label>
                    <input type="text" name="access_code" class="form-control admin-input" placeholder="••••••••" autocomplete="one-time-code" required autofocus>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn admin-btn-success btn-lg">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Entrar
                    </button>
                    <a href="login.php" class="admin-link text-center py-2 d-block">Cancelar / Solicitar nuevo código</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showCodeMode() {
            window.location.href = 'login.php';
        }
        function showPasswordMode() {
            const form = document.createElement('form');
            form.method = 'POST';
            const mode = document.createElement('input');
            mode.type = 'hidden';
            mode.name = 'login_mode';
            mode.value = 'password';
            form.appendChild(mode);
            const email = document.getElementById('inputAdminEmail');
            if (email) {
                const hid = document.createElement('input');
                hid.type = 'hidden';
                hid.name = 'admin_email';
                hid.value = email.value.trim();
                form.appendChild(hid);
            }
            document.body.appendChild(form);
            form.submit();
        }

        document.getElementById('btnSendCode')?.addEventListener('click', async function() {
            const emailInput = document.getElementById('inputAdminEmail');
            const email = (emailInput?.value || '').trim();
            if (!email) {
                alert('Indique su correo electrónico primero.');
                emailInput?.focus();
                return;
            }

            const btn = this;
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Enviando...';

            try {
                const res = await fetch('send_code.php?email=' + encodeURIComponent(email));
                const json = await res.json();

                if (json.status === 'success') {
                    document.getElementById('step2').querySelector('input[name="admin_email"]').value = email;
                    const lbl = document.getElementById('step2EmailLabel');
                    if (lbl) lbl.textContent = 'Código enviado a: ' + email;
                    document.getElementById('step1').classList.add('step-hidden');
                    document.getElementById('step2').classList.remove('step-hidden');
                    document.querySelector('#step2 input[name="access_code"]')?.focus();
                } else {
                    alert("Error: " + (json.message || 'No se pudo enviar el código'));
                }
            } catch (err) {
                alert("Error de conexión. Verifique su red.");
            }
            btn.disabled = false;
            btn.innerHTML = origHtml;
        });
    </script>
</body>
</html>
