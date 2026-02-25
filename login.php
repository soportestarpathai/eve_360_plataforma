<?php
session_start();
require_once 'config/db.php';

// Fetch Branding Config
$appConfig = [
    'nombre_empresa' => 'Investor MLP',
    'logo_url' => 'assets/img/logo_default.png',
    'color_primario' => '#1B8FEA'
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- CSS Variables dinámicas desde PHP -->
    <style>
        :root {
            /* Colores EVE 360 - Se pueden sobrescribir desde DB */
            --eve-blue-dark: #0B486B;
            --eve-blue-deep: #0B3C8A;
            --eve-blue-medium: #1B8FEA;
            --eve-blue-light: #2ED1FF;
            --eve-white: #FFFFFF;
            --eve-gray-light: #C7CDD6;
            --eve-bg-dark: #020814;
            
            /* Color primario (puede venir de DB o usar EVE por defecto) */
            --primary-color: <?= !empty($appConfig['color_primario']) && $appConfig['color_primario'] !== '#0d6efd' ? htmlspecialchars($appConfig['color_primario']) : '#1B8FEA' ?>;
            --primary-dark: <?= !empty($appConfig['color_primario']) && $appConfig['color_primario'] !== '#0d6efd' ? htmlspecialchars($appConfig['color_primario']) : '#0B3C8A' ?>;
        }
    </style>
    <!-- CSS externo -->
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

    <div class="login-wrapper">
        <div class="card login-card shadow-lg">
            <div class="card-header-custom">
                <div class="logo-container">
                    <?php if(file_exists($appConfig['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($appConfig['logo_url']) ?>" alt="Logo">
                    <?php else: ?>
                        <i class="fa-solid fa-shield-halved fa-3x mb-3"></i>
                    <?php endif; ?>
                </div>
                <h3><?= htmlspecialchars($appConfig['nombre_empresa']) ?></h3>
                <p>Bienvenido</p>
            </div>

            <div class="card-body">
                <!-- Step 1: Email & Password -->
                <form id="loginForm">
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa-solid fa-envelope me-2"></i>Email
                        </label>
                        <div class="input-group-icon">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" id="email" class="form-control" placeholder="tu@email.com" required autocomplete="email">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa-solid fa-lock me-2"></i>Contraseña
                        </label>
                        <div class="input-group-icon">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="password" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                        </div>
                        <div class="text-end mt-2">
                            <a href="#" id="showForgot" class="forgot-link">
                                <i class="fa-solid fa-key"></i> ¿Olvidé mi contraseña?
                            </a>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">
                        <i class="fa-solid fa-sign-in-alt me-2"></i>Ingresar
                    </button>
                </form>

                <!-- Forgot Password Form (Hidden by default) -->
                <form id="forgotForm">
                    <div class="text-center mb-4">
                        <i class="fa-solid fa-key fa-3x text-primary mb-3"></i>
                        <h5 class="fw-bold">Recuperar Contraseña</h5>
                        <p class="small text-muted">Ingresa tu correo y te enviaremos un enlace para restablecer tu acceso.</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa-solid fa-envelope me-2"></i>Email
                        </label>
                        <div class="input-group-icon">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" id="forgotEmail" class="form-control" placeholder="tu@email.com" required autocomplete="email">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="fa-solid fa-paper-plane me-2"></i>Enviar Enlace
                    </button>
                    <button type="button" id="cancelForgot" class="btn btn-link w-100 text-decoration-none">
                        <i class="fa-solid fa-arrow-left me-2"></i>Volver al inicio de sesión
                    </button>
                </form>

                <!-- Step 2: 2FA Code -->
                <form id="otpForm" class="step-2">
                    <div class="alert alert-info">
                        <i class="fa-solid fa-envelope-circle-check me-2"></i>
                        <strong>Verificación requerida</strong><br>
                        <small>Hemos enviado un código de 6 dígitos a tu correo electrónico.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fa-solid fa-shield-halved me-2"></i>Código de Verificación
                        </label>
                        <input type="text" id="otpCode" class="form-control otp-input" maxlength="6" placeholder="000000" required autocomplete="one-time-code" pattern="[0-9]{6}">
                        <small class="text-muted d-block mt-2 text-center">Ingresa el código de 6 dígitos</small>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="trustDevice">
                        <label class="form-check-label" for="trustDevice">
                            <i class="fa-solid fa-device-desktop me-1"></i> Confiar en este equipo
                        </label>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fa-solid fa-check-circle me-2"></i>Verificar y Continuar
                    </button>
                </form>
                
                <div id="message" class="mt-3 text-center"></div>
                
                <!-- Footer con enlaces legales -->
                <div class="text-center mt-4 pt-3 border-top">
                    <small class="text-muted d-block mb-2">
                        Al ingresar, aceptas nuestros
                    </small>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="#" id="showPrivacy" class="text-decoration-none small text-muted" style="color: #1B8FEA !important;">
                            <i class="fa-solid fa-shield-halved me-1"></i>Aviso de Privacidad
                        </a>
                        <span class="text-muted">|</span>
                        <a href="#" id="showTerms" class="text-decoration-none small text-muted" style="color: #1B8FEA !important;">
                            <i class="fa-solid fa-file-contract me-1"></i>Términos y Condiciones
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/login.js"></script>
    <script>
        // Mostrar aviso de privacidad solo la primera vez
        document.addEventListener('DOMContentLoaded', function() {
            const privacyShown = localStorage.getItem('privacyAvisoShown');
            
            if (!privacyShown) {
                setTimeout(() => {
                    Swal.fire({
                        title: '<strong>Aviso de Privacidad</strong>',
                        html: `
                            <div style="text-align: left;">
                                <p><strong><?= htmlspecialchars($appConfig['nombre_empresa']) ?></strong> se compromete a proteger tu privacidad y datos personales.</p>
                                <p class="mb-2"><strong>Datos que recopilamos:</strong></p>
                                <ul style="margin-left: 20px; margin-bottom: 15px;">
                                    <li>Información de identificación (nombre, email)</li>
                                    <li>Datos de acceso y autenticación</li>
                                    <li>Información de dispositivo y sesión</li>
                                </ul>
                                <p class="mb-2"><strong>Uso de la información:</strong></p>
                                <ul style="margin-left: 20px; margin-bottom: 15px;">
                                    <li>Proporcionar y mejorar nuestros servicios</li>
                                    <li>Autenticación y seguridad de acceso</li>
                                    <li>Cumplimiento de obligaciones legales</li>
                                </ul>
                                <p><small>Al continuar, aceptas nuestro <a href="#" onclick="document.getElementById('showPrivacy').click(); return false;" style="color: #1B8FEA; text-decoration: underline;">Aviso de Privacidad completo</a>.</small></p>
                            </div>
                        `,
                        icon: 'info',
                        iconColor: '#1B8FEA',
                        width: 600,
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#1B8FEA',
                        confirmButtonTextStyle: { color: '#FFFFFF' },
                        background: '#ffffff',
                        showCloseButton: true,
                        allowOutsideClick: false
                    }).then(() => {
                        localStorage.setItem('privacyAvisoShown', 'true');
                    });
                }, 500);
            }
        });

        // Mostrar aviso de privacidad completo
        document.getElementById('showPrivacy').addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: '<strong>Aviso de Privacidad Completo</strong>',
                html: `
                    <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                        <p><strong>1. Responsable del tratamiento</strong></p>
                        <p><?= htmlspecialchars($appConfig['nombre_empresa']) ?> es responsable del tratamiento de sus datos personales.</p>
                        
                        <p class="mt-3"><strong>2. Datos personales que recopilamos</strong></p>
                        <ul style="margin-left: 20px;">
                            <li>Datos de identificación: nombre completo, correo electrónico</li>
                            <li>Datos de autenticación: contraseñas (encriptadas), códigos de verificación</li>
                            <li>Datos de navegación: dirección IP, tipo de dispositivo, cookies</li>
                            <li>Datos de sesión: fecha y hora de acceso, actividad en el sistema</li>
                        </ul>
                        
                        <p class="mt-3"><strong>3. Finalidad del tratamiento</strong></p>
                        <ul style="margin-left: 20px;">
                            <li>Autenticación y control de acceso al sistema</li>
                            <li>Gestión de cuentas de usuario</li>
                            <li>Mejora de nuestros servicios</li>
                            <li>Cumplimiento de obligaciones legales y regulatorias</li>
                            <li>Seguridad y prevención de fraudes</li>
                        </ul>
                        
                        <p class="mt-3"><strong>4. Base legal</strong></p>
                        <p>El tratamiento se basa en el consentimiento del titular, el cumplimiento de obligaciones legales y el interés legítimo del responsable.</p>
                        
                        <p class="mt-3"><strong>5. Transferencias</strong></p>
                        <p>Sus datos personales no serán transferidos a terceros, salvo en los casos previstos por la ley o cuando sea necesario para la prestación del servicio.</p>
                        
                        <p class="mt-3"><strong>6. Derechos ARCO</strong></p>
                        <p>Usted tiene derecho a: Acceder, Rectificar, Cancelar u Oponerse al tratamiento de sus datos personales (ARCO).</p>
                        
                        <p class="mt-3"><strong>7. Seguridad</strong></p>
                        <p>Implementamos medidas técnicas y administrativas para proteger sus datos personales contra daño, pérdida, alteración, destrucción o uso no autorizado.</p>
                        
                        <p class="mt-3"><strong>8. Contacto</strong></p>
                        <p>Para ejercer sus derechos o resolver dudas sobre el tratamiento de sus datos, puede contactarnos a través de nuestros canales oficiales.</p>
                    </div>
                `,
                icon: 'info',
                iconColor: '#1B8FEA',
                width: 700,
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#1B8FEA',
                background: '#ffffff',
                showCloseButton: true
            });
        });

        // Mostrar términos y condiciones
        document.getElementById('showTerms').addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: '<strong>Términos y Condiciones</strong>',
                html: `
                    <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                        <p><strong>1. Aceptación de los términos</strong></p>
                        <p>Al acceder y utilizar este sistema, usted acepta estos términos y condiciones en su totalidad.</p>
                        
                        <p class="mt-3"><strong>2. Uso del sistema</strong></p>
                        <ul style="margin-left: 20px;">
                            <li>El sistema está destinado exclusivamente para uso autorizado</li>
                            <li>Está prohibido compartir credenciales de acceso</li>
                            <li>Debe mantener la confidencialidad de su contraseña</li>
                            <li>Está prohibido intentar acceder a áreas no autorizadas</li>
                        </ul>
                        
                        <p class="mt-3"><strong>3. Responsabilidades del usuario</strong></p>
                        <ul style="margin-left: 20px;">
                            <li>Proporcionar información veraz y actualizada</li>
                            <li>Mantener la seguridad de sus credenciales</li>
                            <li>Notificar inmediatamente cualquier actividad sospechosa</li>
                            <li>Usar el sistema de acuerdo con las políticas establecidas</li>
                        </ul>
                        
                        <p class="mt-3"><strong>4. Seguridad</strong></p>
                        <p>El sistema implementa medidas de seguridad para proteger la información, sin embargo, el usuario es responsable de mantener la confidencialidad de sus credenciales.</p>
                        
                        <p class="mt-3"><strong>5. Modificaciones</strong></p>
                        <p>Nos reservamos el derecho de modificar estos términos en cualquier momento. Las modificaciones serán notificadas a través del sistema.</p>
                        
                        <p class="mt-3"><strong>6. Limitación de responsabilidad</strong></p>
                        <p>El sistema se proporciona "tal cual". No garantizamos disponibilidad ininterrumpida o libre de errores.</p>
                    </div>
                `,
                icon: 'info',
                iconColor: '#1B8FEA',
                width: 700,
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#1B8FEA',
                background: '#ffffff',
                showCloseButton: true
            });
        });
    </script>
</body>
</html>