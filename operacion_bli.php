<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/pld_fraccion_ix.php';
require_once 'config/bli_catalogos.php';

requireModuleActive($pdo, 'pld');

if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if (!function_exists('userCanAccessBLI') || !userCanAccessBLI($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_bli');
    exit;
}

$clave_sujeto_obligado = '';
try {
    $row = null;
    if ($userId > 0) {
        $stmtU = $pdo->prepare("SELECT folio_patron_pld FROM config_empresa_usuario WHERE id_usuario = ?");
        $stmtU->execute([$userId]);
        $row = $stmtU->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    if (empty($row['folio_patron_pld'])) {
        $row = $pdo->query("SELECT folio_patron_pld FROM config_empresa WHERE id_config = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $clave_sujeto_obligado = (string)($row['folio_patron_pld'] ?? '');
} catch (Exception $e) { /* no-op */ }

$idFraccionIX = getIdVulnerableFraccionIX($pdo);
$umbralIdentUma = pldFraccionIXUmbralIdentificacion();
$umbralAvisoUma = pldFraccionIXUmbralAviso();

$prioridadOptions = function_exists('bliCatalogoOptions') ? bliCatalogoOptions('prioridad', '1', null, false) : '';
$tipoAlertaOptions = function_exists('bliCatalogoOptions') ? bliCatalogoOptions('tipo_alerta', '100', null, false) : '';
$tipoOperacionOptions = function_exists('bliCatalogoOptions') ? bliCatalogoOptions('tipo_operacion', '', null, true) : '';
$tipoBienOptions = function_exists('bliCatalogoOptions') ? bliCatalogoOptions('tipo_bien_blindado', '', null, true) : '';
$tipoInmuebleOptions = function_exists('bliCatalogoOptions') ? bliCatalogoOptions('tipo_inmueble', '', null, true) : '';
$parteBlindadaOptions = function_exists('bliCatalogoOptions') ? bliCatalogoOptions('parte_blindada', '', null, true) : '';
$estadoBienOptions = function_exists('bliCatalogoOptions') ? bliCatalogoOptions('estado_bien', '', null, true) : '';
$nivelBlindajeOptions = function_exists('bliCatalogoOptions') ? bliCatalogoOptions('nivel_blindaje', '', null, true) : '';
$instrumentoOptions = function_exists('bliCatalogoOptions') ? bliCatalogoOptions('instrumento_monetario', '', null, true) : '';
$monedaOptions = function_exists('bliCatalogoOptions')
    ? bliCatalogoOptions('moneda', '', function ($k, $v) { return $k . ' - ' . $v; }, true)
    : '';

$page_title = 'Aviso BLI - Fracción IX';
include 'templates/header.php';
?>
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/operaciones_pld.css">
</head>
<body>
<?php
$is_sub_page = true;
include 'templates/top_bar.php';
?>

<div class="content-wrapper">
    <div class="container-fluid" style="max-width: 1080px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-shield-halved me-2"></i>Aviso BLI</h2>
                <p class="text-muted mb-0">Fracción IX - Servicios de blindaje de vehículos terrestres e inmuebles</p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        </div>

        <?php if (empty($idFraccionIX)): ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            No se encontró la Fracción IX en `cat_vulnerables`. Pida a administración cargar el catálogo base.
        </div>
        <?php endif; ?>

        <div class="alert alert-info mb-4">
            <i class="fa-solid fa-circle-info me-1"></i>
            <strong>Regla BLI:</strong> Identificación desde <strong><?= number_format($umbralIdentUma, 0) ?> UMA</strong>.
            Aviso/acumulación desde <strong><?= number_format($umbralAvisoUma, 0) ?> UMA</strong>.
        </div>

        <form id="form-bli" class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3">
                <h5 class="mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Captura de operación BLI</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente *</label>
                        <select id="id_cliente" class="form-select" required>
                            <option value="">-- Seleccione cliente --</option>
                        </select>
                        <small id="cliente_info_bli" class="text-muted">Seleccione un cliente para continuar.</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mes reportado *</label>
                        <input id="mes_reportado" class="form-control" maxlength="6" pattern="\d{6}" value="<?= date('Ym') ?>" placeholder="Ej: 202604" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Clave actividad</label>
                        <input class="form-control" value="BLI" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Clave sujeto obligado *</label>
                        <input id="clave_sujeto_obligado" class="form-control text-uppercase" maxlength="13" value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" placeholder="Ej: ABC010203AB1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Referencia aviso *</label>
                        <input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" placeholder="Ej: BLI20260001" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Exento</label>
                        <select id="exento" class="form-select">
                            <option value="0" selected>0 - No</option>
                            <option value="1">1 - Sí</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Clave entidad colegiada</label>
                        <input id="clave_entidad_colegiada" class="form-control text-uppercase" maxlength="12" placeholder="Ej: ABC900101A1B">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha operación *</label>
                        <input id="fecha_operacion" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CP operación *</label>
                        <input id="codigo_postal" class="form-control" maxlength="5" inputmode="numeric" placeholder="Ej: 01020" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Tipo de operación *</label>
                        <select id="tipo_operacion" class="form-select" required>
                            <?= $tipoOperacionOptions ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo de bien blindado *</label>
                        <select id="tipo_bien_blindado" class="form-select" required>
                            <?= $tipoBienOptions ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado del bien *</label>
                        <select id="estado_bien" class="form-select" required>
                            <?= $estadoBienOptions ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Nivel blindaje *</label>
                        <select id="nivel_blindaje" class="form-select" required>
                            <?= $nivelBlindajeOptions ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Monto operación *</label>
                        <input id="monto" type="number" min="0.01" step="0.01" class="form-control" placeholder="Ej: 350000.00" required>
                    </div>
                    <div class="col-md-4 d-none" id="grp_tipo_inmueble">
                        <label class="form-label">Tipo de inmueble *</label>
                        <select id="tipo_inmueble" class="form-select">
                            <?= $tipoInmuebleOptions ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-none" id="grp_parte_blindada">
                        <label class="form-label">Parte del inmueble *</label>
                        <select id="parte_blindada" class="form-select">
                            <?= $parteBlindadaOptions ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Instrumento monetario *</label>
                        <select id="instrumento_monetario" class="form-select" required>
                            <?= $instrumentoOptions ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Moneda *</label>
                        <select id="moneda" class="form-select" required>
                            <?= $monedaOptions ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Prioridad *</label>
                        <select id="prioridad" class="form-select" required>
                            <?= $prioridadOptions ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tipo de alerta *</label>
                        <select id="tipo_alerta" class="form-select" required>
                            <?= $tipoAlertaOptions ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Descripción servicio</label>
                        <input id="descripcion_servicio" class="form-control text-uppercase" maxlength="3000" placeholder="Ej: BLINDAJE NIVEL 3 DE SUV MODELO 2024">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Descripción alerta</label>
                        <input id="descripcion_alerta" class="form-control text-uppercase" maxlength="3000" placeholder="Ej: OPERACIÓN INUSUAL POR MONTO Y FRECUENCIA">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-outline-secondary">Limpiar</button>
                <button type="submit" class="btn btn-primary" <?= empty($idFraccionIX) ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-floppy-disk me-1"></i>Registrar operación
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showMsg(title, text, icon) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({ title, text, icon });
    } else {
        alert(title + ': ' + text);
    }
}

function onlyDigits(v) {
    return (v || '').toString().replace(/\D+/g, '');
}

function upper(v) {
    return (v || '').toString().trim().toUpperCase();
}

function syncTipoInmueble() {
    const tipoBien = (document.getElementById('tipo_bien_blindado').value || '').trim();
    const grp = document.getElementById('grp_tipo_inmueble');
    const sel = document.getElementById('tipo_inmueble');
    const grpParte = document.getElementById('grp_parte_blindada');
    const selParte = document.getElementById('parte_blindada');
    const isInmueble = tipoBien === '2';
    grp.classList.toggle('d-none', !isInmueble);
    sel.required = isInmueble;
    grpParte.classList.toggle('d-none', !isInmueble);
    selParte.required = isInmueble;
    if (!isInmueble) sel.value = '';
    if (!isInmueble) selParte.value = '';
}

async function loadClientesBLI() {
    const select = document.getElementById('id_cliente');
    try {
        const res = await fetch('api/get_clients.php');
        const data = await res.json();
        if (data.status !== 'success' || !Array.isArray(data.data)) return;
        data.data.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id_cliente;
            const nombre = [c.nombre, c.apellido_paterno, c.apellido_materno].filter(Boolean).join(' ').trim();
            opt.textContent = `${c.id_cliente} - ${nombre || c.razon_social || 'CLIENTE'}${c.rfc ? ' (' + c.rfc + ')' : ''}`;
            select.appendChild(opt);
        });
    } catch (e) {
        console.error(e);
    }
}

document.getElementById('id_cliente').addEventListener('change', function () {
    const txt = this.options[this.selectedIndex]?.textContent || '';
    document.getElementById('cliente_info_bli').textContent = txt ? ('Cliente seleccionado: ' + txt) : 'Seleccione un cliente para continuar.';
});

document.getElementById('tipo_alerta').addEventListener('change', function () {
    if (this.value === '9999' && !document.getElementById('descripcion_alerta').value.trim()) {
        document.getElementById('descripcion_alerta').focus();
    }
});
document.getElementById('tipo_bien_blindado').addEventListener('change', syncTipoInmueble);

document.getElementById('form-bli').addEventListener('submit', async function (e) {
    e.preventDefault();
    const payload = {
        id_cliente: parseInt(document.getElementById('id_cliente').value || '0', 10),
        mes_reportado: onlyDigits(document.getElementById('mes_reportado').value),
        clave_sujeto_obligado: upper(document.getElementById('clave_sujeto_obligado').value),
        clave_entidad_colegiada: upper(document.getElementById('clave_entidad_colegiada').value),
        referencia_aviso: upper(document.getElementById('referencia_aviso').value),
        prioridad: (document.getElementById('prioridad').value || '').trim(),
        exento: (document.getElementById('exento').value || '0').trim(),
        tipo_alerta: (document.getElementById('tipo_alerta').value || '').trim(),
        descripcion_alerta: upper(document.getElementById('descripcion_alerta').value),
        fecha_operacion: (document.getElementById('fecha_operacion').value || '').trim(),
        codigo_postal: onlyDigits(document.getElementById('codigo_postal').value),
        tipo_operacion: (document.getElementById('tipo_operacion').value || '').trim(),
        tipo_bien_blindado: (document.getElementById('tipo_bien_blindado').value || '').trim(),
        tipo_inmueble: (document.getElementById('tipo_inmueble').value || '').trim(),
        parte_blindada: (document.getElementById('parte_blindada').value || '').trim(),
        estado_bien: (document.getElementById('estado_bien').value || '').trim(),
        nivel_blindaje: (document.getElementById('nivel_blindaje').value || '').trim(),
        descripcion_servicio: upper(document.getElementById('descripcion_servicio').value),
        instrumento_monetario: (document.getElementById('instrumento_monetario').value || '').trim(),
        moneda: (document.getElementById('moneda').value || '').trim(),
        monto: parseFloat(document.getElementById('monto').value || '0')
    };

    if (!payload.id_cliente) return showMsg('Validación', 'Seleccione un cliente.', 'warning');
    if (!payload.mes_reportado || payload.mes_reportado.length !== 6) return showMsg('Validación', 'Mes reportado inválido.', 'warning');
    if (!payload.monto || payload.monto <= 0) return showMsg('Validación', 'Monto inválido.', 'warning');
    if (payload.tipo_bien_blindado === '2' && !payload.tipo_inmueble) return showMsg('Validación', 'Debe seleccionar tipo de inmueble.', 'warning');
    if (payload.tipo_bien_blindado === '2' && !payload.parte_blindada) return showMsg('Validación', 'Debe seleccionar parte del inmueble.', 'warning');
    if (payload.tipo_alerta === '9999' && !payload.descripcion_alerta) return showMsg('Validación', 'Debe capturar descripción de alerta para tipo 9999.', 'warning');

    const btn = this.querySelector('button[type="submit"]');
    const old = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Registrando...';

    try {
        const res = await fetch('api/registrar_aviso_bli.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status !== 'success') {
            throw new Error(data.message || 'No fue posible registrar');
        }

        const parts = [];
        if (data.id_operacion) parts.push('Operación #' + data.id_operacion);
        if (data.requiere_aviso && data.id_aviso) parts.push('Aviso #' + data.id_aviso);
        showMsg('Registro exitoso', parts.join(' | ') || 'Operación registrada.', 'success');

        this.reset();
        document.getElementById('mes_reportado').value = new Date().toISOString().slice(0, 7).replace('-', '');
        document.getElementById('fecha_operacion').value = new Date().toISOString().slice(0, 10);
        syncTipoInmueble();
    } catch (err) {
        showMsg('Error', err.message || 'No fue posible registrar', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = old;
    }
});

loadClientesBLI();
syncTipoInmueble();
</script>

</body>
</html>
