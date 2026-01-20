let tempUserId = null;

// Helper function to show message
function showMessage(text, type = 'danger') {
    const messageEl = document.getElementById('message');
    messageEl.className = `mt-3 text-center text-${type}`;
    messageEl.innerHTML = `<i class="fa-solid fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${text}`;
    messageEl.style.display = 'block';
}

function clearMessage() {
    const messageEl = document.getElementById('message');
    messageEl.innerText = '';
    messageEl.className = 'mt-3 text-center';
    messageEl.style.display = 'none';
}

// Helper function to toggle loading state
function setLoading(button, isLoading) {
    if (isLoading) {
        button.classList.add('btn-loading');
        button.disabled = true;
    } else {
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
}

// --- TOGGLE FORMS ---
document.getElementById('showForgot').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('loginForm').style.display = 'none';
    document.getElementById('forgotForm').style.display = 'block';
    clearMessage();
});

document.getElementById('cancelForgot').addEventListener('click', function() {
    document.getElementById('forgotForm').style.display = 'none';
    document.getElementById('loginForm').style.display = 'block';
    clearMessage();
});

// --- LOGIN LOGIC ---
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    if (!email || !password) {
        showMessage('Por favor completa todos los campos.');
        return;
    }

    clearMessage();
    setLoading(submitBtn, true);
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Verificando...';

    try {
        const res = await fetch('api/auth_login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email, password })
        });
        
        const data = await res.json();

        if (data.status === 'success') {
            showMessage('¡Bienvenido! Redirigiendo...', 'success');
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 500);
        } else if (data.status === '2fa_required') {
            tempUserId = data.temp_token;
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('otpForm').style.display = 'block';
            document.getElementById('otpCode').focus();
        } else {
            showMessage(data.message || 'Credenciales incorrectas. Por favor intenta de nuevo.');
            setLoading(submitBtn, false);
            submitBtn.innerHTML = originalText;
        }
    } catch (error) {
        showMessage('Error de conexión. Por favor verifica tu conexión a internet.');
        setLoading(submitBtn, false);
        submitBtn.innerHTML = originalText;
    }
});

// --- OTP LOGIC ---
document.getElementById('otpForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const code = document.getElementById('otpCode').value.trim();
    const trust = document.getElementById('trustDevice').checked;
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    if (!code || code.length !== 6) {
        showMessage('Por favor ingresa el código de 6 dígitos.');
        return;
    }

    clearMessage();
    setLoading(submitBtn, true);
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Verificando código...';

    try {
        const res = await fetch('api/auth_verify.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ user_id: tempUserId, code: code, trust_device: trust })
        });
        
        const data = await res.json();

        if (data.status === 'success') {
            showMessage('¡Verificación exitosa! Redirigiendo...', 'success');
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 500);
        } else {
            showMessage(data.message || 'Código incorrecto. Por favor intenta de nuevo.');
            setLoading(submitBtn, false);
            submitBtn.innerHTML = originalText;
            document.getElementById('otpCode').value = '';
            document.getElementById('otpCode').focus();
        }
    } catch (error) {
        showMessage('Error de conexión. Por favor verifica tu conexión a internet.');
        setLoading(submitBtn, false);
        submitBtn.innerHTML = originalText;
    }
});

// Auto-format OTP input (only numbers, max 6 digits)
document.getElementById('otpCode')?.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    if (this.value.length === 6) {
        // Auto-submit when 6 digits are entered (optional)
        // this.form.dispatchEvent(new Event('submit'));
    }
});

// --- FORGOT PASSWORD LOGIC ---
document.getElementById('forgotForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const email = document.getElementById('forgotEmail').value.trim();
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    if (!email) {
        showMessage('Por favor ingresa tu correo electrónico.');
        return;
    }

    clearMessage();
    setLoading(submitBtn, true);
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Enviando...';

    try {
        const res = await fetch('api/auth_forgot.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email })
        });
        
        const data = await res.json();
        
        // Always show success message for security
        showMessage(data.message || 'Si el correo existe, se enviaron las instrucciones.', 'success');
        
        // Hide form after delay
        setTimeout(() => {
            document.getElementById('forgotForm').style.display = 'none';
            document.getElementById('loginForm').style.display = 'block';
            clearMessage();
            setLoading(submitBtn, false);
            submitBtn.innerHTML = originalText;
        }, 3000);

    } catch (err) {
        showMessage('Error de conexión. Por favor verifica tu conexión a internet.');
        setLoading(submitBtn, false);
        submitBtn.innerHTML = originalText;
    }
});

// Focus first input on load
document.addEventListener('DOMContentLoaded', function() {
    const emailInput = document.getElementById('email');
    if (emailInput && !emailInput.value) {
        emailInput.focus();
    }
});

// Enter key handling for OTP input
document.getElementById('otpCode')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && this.value.length === 6) {
        e.preventDefault();
        document.getElementById('otpForm').dispatchEvent(new Event('submit'));
    }
});
