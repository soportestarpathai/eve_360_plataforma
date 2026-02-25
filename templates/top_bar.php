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
                    <span class="d-none d-md-inline">...</span>
                </span>
                <img src="" id="navUserAvatar" class="user-avatar" alt="Avatar">
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
                
                <!-- System Config Section (Hidden by default, shown via JS permissions) -->
                <div id="adminConfigSection" class="restricted">
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Configuración del Sistema</h6></li>
                    
                    <!-- 1. Configuración EBR -->
                    <li>
                        <a class="dropdown-item" href="config_ebr.php">
                            <i class="fa-solid fa-sliders me-2"></i>Configuración EBR
                        </a>
                    </li>
                    
                    <!-- Future config items go here -->
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
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all top-bar functions
        initUserAndNav();
        initNotifications();
    });

    // --- 1. USER & PERMISSIONS ---
    function initUserAndNav() {
        fetch('api/get_current_user.php')
            .then(res => res.ok ? res.json() : Promise.reject('Auth failed'))
            .then(data => {
                if(data && data.status === 'success') {
                    // Populate user info
                    const userNameEl = document.getElementById('navUserName');
                    if (userNameEl) {
                        const nameSpan = userNameEl.querySelector('span');
                        if (nameSpan) {
                            nameSpan.textContent = data.user.name;
                        } else {
                            userNameEl.innerHTML = `<i class="fa-solid fa-user-circle"></i><span class="d-none d-md-inline">${data.user.name}</span>`;
                        }
                    }
                    const avatarEl = document.getElementById('navUserAvatar');
                    if (avatarEl) avatarEl.src = data.user.avatar;
                    
                    // Apply permissions
                    // If user has 'administracion' permission > 0, show the config section
                    if (data.permissions && data.permissions.administracion > 0) {
                        const adminSection = document.getElementById('adminConfigSection');
                        if (adminSection) adminSection.classList.remove('restricted');
                    }
                } else {
                    window.location.href = 'login.php';
                }
            }).catch(() => window.location.href = 'login.php');

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
        fetch('api/get_notifications.php')
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
                 notifications.forEach(n => {
                    let typeClass = 'bg-light text-dark';
                    const t = n.tipo.toLowerCase();
                    if(t.includes('pld')) typeClass = 'bg-danger-subtle text-danger';
                    else if(t.includes('pep') || t.includes('listas')) typeClass = 'bg-dark text-white';
                    else if(t.includes('vencida')) typeClass = 'bg-warning-subtle text-warning-emphasis';
                    else if(t.includes('kyc') || t.includes('incompleto')) typeClass = 'bg-warning-subtle text-warning-emphasis';
                    const date = new Date(n.fecha_generacion).toLocaleDateString('es-MX', { month: 'short', day: 'numeric', hour: '2-digit', minute:'2-digit' });
                    const itemHTML = `
                        <div class="p-3 border-bottom" id="notif-${n.id_notificacion}">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="badge ${typeClass}">${n.tipo}</span>
                                <small class="text-muted">${date}</small>
                            </div>
                            <div class="fw-bold small text-dark mb-1">${n.nombre_cliente || 'Cliente Desconocido'}</div>
                            <div class="small text-secondary mb-2">${n.mensaje}</div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;" onclick="window.location.href='cliente_detalle.php?id=${n.id_cliente}'"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Abrir</button>
                                <button class="btn btn-sm btn-outline-warning py-0 px-2 text-dark" style="font-size: 0.75rem;" onclick="handleAction(${n.id_notificacion}, 'snooze')"><i class="fa-regular fa-clock me-1"></i> Posponer</button>
                                <button class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;" onclick="handleAction(${n.id_notificacion}, 'dismiss')"><i class="fa-solid fa-xmark me-1"></i> Descartar</button>
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
        fetch('api/notification_action.php', { method: 'POST', body: JSON.stringify({ id, action }) })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                const el = document.getElementById(`notif-${id}`);
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
