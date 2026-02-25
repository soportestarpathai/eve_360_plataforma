<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<?php include 'templates/header.php'; ?>
<title>Notificaciones - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/dashboard.css">
<link rel="stylesheet" href="assets/css/clientes.css">
<style>
.notif-page-card { background: var(--eve-white); border-radius: 12px; box-shadow: 0 4px 12px rgba(11,60,138,0.08); margin-bottom: 1rem; }
.notif-page-card .card-body { padding: 1rem 1.25rem; }
.notif-filtros .nav-link { border-radius: 8px; margin-right: 0.25rem; }
.notif-filtros .nav-link.active { background: var(--eve-blue-medium); color: #fff; }
.badge-estado-pendiente { background: #0d6efd; }
.badge-estado-pospuesto { background: #fd7e14; }
.badge-estado-descartado { background: #6c757d; }
</style>
</head>
<body>

<?php
$is_sub_page = true;
include 'templates/top_bar.php';
?>

<div class="content-wrapper">
    <div class="page-header">
        <div class="page-header-title">
            <h2 class="fw-bold text-primary mb-0">Centro de Notificaciones</h2>
            <p class="text-muted">Todas las notificaciones: pendientes, atendidas y pasadas</p>
        </div>
    </div>

    <ul class="nav notif-filtros mb-3" id="notifFiltros">
        <li class="nav-item"><a class="nav-link active" href="#" data-estado="">Todas</a></li>
        <li class="nav-item"><a class="nav-link" href="#" data-estado="pendiente">Pendientes</a></li>
        <li class="nav-item"><a class="nav-link" href="#" data-estado="pospuesto">Pospuestas</a></li>
        <li class="nav-item"><a class="nav-link" href="#" data-estado="descartado">Descartadas</a></li>
    </ul>

    <div id="notifListPage"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let allNotifs = [];
    let estadoFilter = '';

    function renderList() {
        const list = document.getElementById('notifListPage');
        const filtered = estadoFilter
            ? allNotifs.filter(n => n.estado === estadoFilter)
            : allNotifs;

        if (filtered.length === 0) {
            list.innerHTML = '<div class="alert alert-light border text-muted">No hay notificaciones con este filtro.</div>';
            return;
        }

        list.innerHTML = filtered.map(n => {
            let typeClass = 'bg-light text-dark';
            const t = (n.tipo || '').toLowerCase();
            if (t.includes('pld')) typeClass = 'bg-danger-subtle text-danger';
            else if (t.includes('pep') || t.includes('listas')) typeClass = 'bg-dark text-white';
            else if (t.includes('vencida')) typeClass = 'bg-warning-subtle text-warning-emphasis';
            else if (t.includes('kyc') || t.includes('incompleto')) typeClass = 'bg-warning-subtle text-warning-emphasis';

            let estadoBadge = 'pendiente';
            if (n.estado === 'pospuesto') estadoBadge = 'badge-estado-pospuesto';
            else if (n.estado === 'descartado') estadoBadge = 'badge-estado-descartado';
            else estadoBadge = 'badge-estado-pendiente';

            const date = n.fecha_generacion ? new Date(n.fecha_generacion).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : '-';
            const canAct = n.estado !== 'descartado';
            const actions = canAct
                ? `<button class="btn btn-sm btn-outline-warning text-dark" onclick="notifPageSnooze(${n.id_notificacion})"><i class="fa-regular fa-clock me-1"></i>Posponer</button>
                   <button class="btn btn-sm btn-outline-secondary" onclick="notifPageDismiss(${n.id_notificacion})"><i class="fa-solid fa-xmark me-1"></i>Descartar</button>`
                : '';

            return `
            <div class="notif-page-card card" id="notif-row-${n.id_notificacion}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
                        <span class="badge ${typeClass}">${n.tipo || 'Notificación'}</span>
                        <span class="badge ${estadoBadge}">${n.estado || 'pendiente'}</span>
                    </div>
                    <div class="fw-bold small text-dark mb-1">${n.nombre_cliente || 'Cliente desconocido'}</div>
                    <div class="small text-secondary mb-2">${n.mensaje || ''}</div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <small class="text-muted">${date}</small>
                        <div class="d-flex gap-2">
                            <a href="cliente_detalle.php?id=${n.id_cliente}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Abrir cliente</a>
                            ${actions}
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    document.getElementById('notifFiltros').addEventListener('click', function(e) {
        const link = e.target.closest('a[data-estado]');
        if (!link) return;
        e.preventDefault();
        document.querySelectorAll('#notifFiltros .nav-link').forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        estadoFilter = link.getAttribute('data-estado') || '';
        renderList();
    });

    fetch('api/get_notifications.php?todas=1')
        .then(res => res.json())
        .then(json => {
            if (json.status === 'success' && Array.isArray(json.data)) {
                allNotifs = json.data;
                renderList();
            } else {
                document.getElementById('notifListPage').innerHTML = '<div class="alert alert-warning">Error al cargar notificaciones.</div>';
            }
        })
        .catch(() => {
            document.getElementById('notifListPage').innerHTML = '<div class="alert alert-danger">Error de conexión.</div>';
        });

    window.notifPageSnooze = function(id) {
        fetch('api/notification_action.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, action: 'snooze' }) })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const idx = allNotifs.findIndex(n => n.id_notificacion == id);
                    if (idx >= 0) allNotifs[idx].estado = 'pospuesto';
                    renderList();
                }
            });
    };
    window.notifPageDismiss = function(id) {
        fetch('api/notification_action.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id, action: 'dismiss' }) })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    const idx = allNotifs.findIndex(n => n.id_notificacion == id);
                    if (idx >= 0) allNotifs[idx].estado = 'descartado';
                    renderList();
                }
            });
    };
});
</script>

<?php include 'templates/footer.php'; ?>
