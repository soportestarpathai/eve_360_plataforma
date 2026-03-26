<?php
$topbarUserName = trim((string)($_SESSION['user_name'] ?? 'Usuario'));
if ($topbarUserName === '') {
    $topbarUserName = 'Usuario';
}
$topbarAlertSessionKey = session_id();
$existingAlertNonce = trim((string)($_SESSION['pending_alert_nonce'] ?? ''));
if ($existingAlertNonce === '') {
    try {
        $_SESSION['pending_alert_nonce'] = bin2hex(random_bytes(8));
    } catch (Throwable $e) {
        $_SESSION['pending_alert_nonce'] = (string)time();
    }
}
$topbarAlertNonce = trim((string)($_SESSION['pending_alert_nonce'] ?? ''));
$topbarAlertUserId = (int)($_SESSION['user_id'] ?? 0);
if ($topbarAlertNonce !== '') {
    $topbarAlertSessionKey .= ':' . $topbarAlertUserId . ':' . $topbarAlertNonce;
}

if (!function_exists('buildTopbarAvatarDataUri')) {
    function buildTopbarAvatarDataUri(string $name): string {
        $name = trim($name);
        $initials = '';
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name);
            foreach ($parts as $part) {
                if ($part === '') continue;
                $initials .= strtoupper(substr($part, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }
        if ($initials === '') $initials = 'U';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">'
             . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
             . '<stop offset="0%" stop-color="#0D8ABC"/><stop offset="100%" stop-color="#0B3C8A"/></linearGradient></defs>'
             . '<rect width="96" height="96" rx="48" fill="url(#g)"/>'
             . '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" '
             . 'font-family="Segoe UI, Arial, sans-serif" font-size="34" font-weight="700" fill="#ffffff">'
             . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
             . '</text></svg>';

        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}

$topbarAvatarFallback = buildTopbarAvatarDataUri($topbarUserName);
?>

<!-- TOP BANNER -->
<div class="top-banner">
    <div class="top-bar-left">
        
        <?php if (isset($is_sub_page)): // This variable is set by the parent page ?>
            <!-- 1. The new "Back" button (uses JS history) -->
            <a href="#" onclick="history.back(); return false;" class="back-button">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Atrás</span>
            </a>
        <?php endif; ?>

        <!-- 2. The "Home" link (always present) -->
        <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
            <img src="<?= htmlspecialchars($appConfig['logo_url']) ?>" 
                    alt="Logo" 
                    height="45" 
                    class="d-inline-block align-text-top"
                    style="object-fit: contain;">
            
            <span class="ms-2 d-none d-sm-inline">
                <?= htmlspecialchars($appConfig['nombre_empresa']) ?>
            </span>
        </a>
    </div>

    <div class="user-actions">
        <div class="notif-icon" title="Notificaciones">
            <i class="fa-solid fa-bell"></i>
            <span class="notif-badge" id="notifCount">0</span>
        </div>
        <div class="dropdown">
            <div class="user-profile" data-bs-toggle="dropdown" title="Mi Perfil">
                <span class="user-name" id="navUserName">
                    <i class="fa-solid fa-user-circle"></i>
                    <span class="d-none d-md-inline"><?= htmlspecialchars($topbarUserName) ?></span>
                </span>
                <img src="<?= htmlspecialchars($topbarAvatarFallback) ?>" id="navUserAvatar" class="user-avatar" alt="Avatar" data-fallback="<?= htmlspecialchars($topbarAvatarFallback) ?>">
                <i class="fa-solid fa-chevron-down ms-1" style="font-size: 0.75rem; color: rgba(255,255,255,0.8);"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 12px; margin-top: 10px; min-width: 220px;">
                <!-- Account Section -->
                <li><h6 class="dropdown-header text-uppercase small fw-bold" style="color: #6c757d; letter-spacing: 1px;">
                    <i class="fa-solid fa-user-circle me-2"></i>Mi Cuenta
                </h6></li>
                <li>
                    <a class="dropdown-item" href="mi_cuenta.php">
                        <i class="fa-solid fa-user-gear me-2" style="color: var(--primary-color);"></i>Administrar cuenta
                    </a>
                </li>
                
                <!-- Configuración EBR: visible para cualquier usuario con módulo risk (admin o no) -->
                <div id="topbarEbrSection" class="restricted">
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="config_ebr.php">
                            <i class="fa-solid fa-sliders me-2"></i>Configuración EBR
                        </a>
                    </li>
                </div>

                <!-- System Config Section (solo para usuarios con administracion > 0) -->
                <div id="adminConfigSection" class="restricted">
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Configuración del Sistema</h6></li>
                    <!-- Future admin-only config items go here -->
                    <!-- <li><a class="dropdown-item" href="config_users.php"><i class="fa-solid fa-users-gear me-2"></i>Usuarios</a></li> -->
                </div>

                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger fw-semibold" href="#" id="btnLogout">
                        <i class="fa-solid fa-right-from-bracket me-2"></i>Cerrar sesión
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- NOTIFICATION PANEL (Hidden) -->
<div class="notification-dropdown" id="notifPanel">
    <div class="p-3 border-bottom fw-bold d-flex justify-content-between">
        <span>Centro de Notificaciones</span>
        <button class="btn-close small" onclick="toggleNotifPanel()"></button>
    </div>
    <div id="notifList"></div>
</div>

<!-- All JS logic for the Top Bar -->
<script>
    window.EVE_PENDING_ALERT_SESSION_KEY = <?= json_encode($topbarAlertSessionKey) ?>;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all top-bar functions
        initUserAndNav();
        initNotifications();
    });

    // --- 1. USER & PERMISSIONS ---
    function initUserAndNav() {
        const applyUserPayload = (data) => {
            if (!data || data.status !== 'success') return;

            const userNameEl = document.getElementById('navUserName');
            if (userNameEl) {
                const nameSpan = userNameEl.querySelector('span');
                if (nameSpan) {
                    nameSpan.textContent = data.user && data.user.name ? data.user.name : '';
                } else {
                    userNameEl.innerHTML = '<i class="fa-solid fa-user-circle"></i><span class="d-none d-md-inline"></span>';
                    const sp = userNameEl.querySelector('span');
                    if (sp) sp.textContent = data.user && data.user.name ? data.user.name : '';
                }
            }

            const avatarEl = document.getElementById('navUserAvatar');
            if (avatarEl && data.user) {
                const fallback = avatarEl.dataset.fallback || '';
                const nextAvatar = data.user.avatar || fallback;
                if (nextAvatar) avatarEl.src = nextAvatar;
                avatarEl.onerror = () => {
                    if (fallback && avatarEl.src !== fallback) avatarEl.src = fallback;
                };
            }

            const riskActive = data.sys_modules && data.sys_modules.risk !== 0;
            const ebrSection = document.getElementById('topbarEbrSection');
            if (ebrSection) ebrSection.classList.toggle('restricted', !riskActive);

            const adminSection = document.getElementById('adminConfigSection');
            if (adminSection) {
                const isAdmin = !!(data.permissions && Number(data.permissions.administracion) > 0);
                adminSection.classList.toggle('restricted', !isAdmin);
            }
        };

        try {
            const cachedRaw = sessionStorage.getItem('topbar_user_cache_v1');
            if (cachedRaw) {
                const cached = JSON.parse(cachedRaw);
                const ageMs = Date.now() - Number(cached.ts || 0);
                if (cached.payload && ageMs >= 0 && ageMs < 120000) {
                    applyUserPayload(cached.payload);
                }
            }
        } catch (e) { /* sin cache */ }

        const abortController = new AbortController();
        const timeoutId = setTimeout(() => abortController.abort(), 2500);

        fetch('api/get_current_user.php', {
            cache: 'no-store',
            credentials: 'same-origin',
            signal: abortController.signal
        })
            .then(async (res) => {
                clearTimeout(timeoutId);
                let data = null;
                try {
                    data = await res.json();
                } catch (e) {
                    data = null;
                }

                // Solo redirigir a login cuando realmente no hay sesión.
                if (res.status === 401) {
                    window.location.href = 'login.php';
                    return;
                }

                // Para errores temporales (500, JSON inválido, etc.), no expulsar al usuario.
                if (!res.ok || !data || data.status !== 'success') {
                    console.warn('TopBar: no se pudo cargar perfil de usuario', {
                        status: res.status,
                        payload: data
                    });
                    return;
                }
                applyUserPayload(data);
                try {
                    sessionStorage.setItem('topbar_user_cache_v1', JSON.stringify({ ts: Date.now(), payload: data }));
                } catch (e) { /* storage full o no disponible */ }
            })
            .catch((err) => {
                clearTimeout(timeoutId);
                console.warn('TopBar: error de red al obtener usuario actual', err);
            });

        // Logout
        const btnLogout = document.getElementById('btnLogout');
        if (btnLogout) {
            btnLogout.addEventListener('click', (e) => {
                e.preventDefault();
                fetch('api/auth_logout.php').then(() => window.location.href = 'login.php');
            });
        }
    }

    // --- 2. NOTIFICATIONS ---
    function initNotifications() {
        const panel = document.getElementById('notifPanel');
        const bell = document.querySelector('.notif-icon');
        
        if (!panel || !bell) return;

        const setPanelVisibility = (visible) => {
            panel.style.display = visible ? 'block' : 'none';
        };

        bell.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = panel.style.display === 'block';
            setPanelVisibility(!isVisible);
            if (!isVisible) loadNotifs();
        });
        
        document.addEventListener('click', (e) => {
            if (!panel.contains(e.target) && !bell.contains(e.target)) {
                setPanelVisibility(false);
            }
        });

        window.toggleNotifPanel = () => {
            const isVisible = panel.style.display === 'block';
            setPanelVisibility(!isVisible);
            if (!isVisible) loadNotifs();
        };

        window.openNotifPanel = () => {
            setPanelVisibility(true);
            loadNotifs();
        };

        window.closeNotifPanel = () => setPanelVisibility(false);
        window.loadNotifs = loadNotifs;
        loadNotifs(); // Initial load
    }

    function loadNotifs() {
        fetch('api/get_notifications.php', { cache: 'no-store', credentials: 'same-origin' })
        .then(res => res.json())
        .then(json => {
             const list = document.getElementById('notifList');
             if (!list) return;
             list.innerHTML = '';

             const notifications = Array.isArray(json.data) ? json.data : [];
             const count = notifications.length;
             document.getElementById('notifCount').textContent = count;
             const badge = document.querySelector('.notif-badge');
             if(badge) badge.style.display = count > 0 ? 'block' : 'none';

             document.dispatchEvent(new CustomEvent('notifications:updated', {
                 detail: { data: notifications }
             }));
             
             if(count > 0) {
                 const escapeHtml = (s) => { if (s == null || s === undefined) return ''; const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; };
                 notifications.forEach(n => {
                    const notifId = parseInt(n.id_notificacion, 10) || 0;
                    const clienteId = parseInt(n.id_cliente, 10) || 0;
                    const openUrl = clienteId > 0 ? `cliente_detalle.php?id=${clienteId}` : 'notificaciones.php';
                    let typeClass = 'bg-light text-dark';
                    const t = (n.tipo || '').toLowerCase();
                    if(t.includes('pld')) typeClass = 'bg-danger-subtle text-danger';
                    else if(t.includes('pep') || t.includes('listas')) typeClass = 'bg-dark text-white';
                    else if(t.includes('vencida')) typeClass = 'bg-warning-subtle text-warning-emphasis';
                    else if(t.includes('kyc') || t.includes('incompleto')) typeClass = 'bg-warning-subtle text-warning-emphasis';
                    const date = new Date(n.fecha_generacion || 0).toLocaleDateString('es-MX', { month: 'short', day: 'numeric', hour: '2-digit', minute:'2-digit' });
                    const itemHTML = `
                        <div class="p-3 border-bottom" id="notif-${notifId}">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="badge ${typeClass}">${escapeHtml(n.tipo)}</span>
                                <small class="text-muted">${escapeHtml(date)}</small>
                            </div>
                            <div class="fw-bold small text-dark mb-1">${escapeHtml(n.nombre_cliente || 'Cliente Desconocido')}</div>
                            <div class="small text-secondary mb-2">${escapeHtml(n.mensaje)}</div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;" onclick="window.location.href='${openUrl}'"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Abrir</button>
                                <button class="btn btn-sm btn-outline-warning py-0 px-2 text-dark" style="font-size: 0.75rem;" onclick="handleAction(${notifId}, 'snooze')"><i class="fa-regular fa-clock me-1"></i> Posponer</button>
                                <button class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="handleAction(${notifId}, 'dismiss')"><i class="fa-solid fa-xmark me-1"></i> Descartar</button>
                            </div>
                        </div>`;
                     list.innerHTML += itemHTML;
                 });
             } else {
                 list.innerHTML = '<div class="text-center p-4 text-muted small">No hay notificaciones pendientes.</div>';
             }
        })
        .catch(() => {
            document.dispatchEvent(new CustomEvent('notifications:updated', {
                detail: { data: [] }
            }));
        });
    }

    window.handleAction = function(id, action) {
        fetch('api/notification_action.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, action })
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                const el = document.getElementById(`notif-${id}`);
                if (!el) {
                    loadNotifs();
                    return;
                }
                el.style.transition = "opacity 0.3s";
                el.style.opacity = '0';
                setTimeout(() => {
                    el.remove();
                    loadNotifs(); // Reload to update count
                }, 300);
            }
        });
    };
</script>
