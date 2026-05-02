<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/pld_fraccion_iii.php';
require_once 'config/chv_catalogos.php';

requireModuleActive($pdo, 'pld');

if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if (!function_exists('userCanAccessCHV') || !userCanAccessCHV($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_chv');
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

$idFraccionIII = getIdVulnerableFraccionIII($pdo);
$umbralAvisoUma = pldFraccionIIIUmbralAviso();

$prioridadOptions = function_exists('chvCatalogoOptions') ? chvCatalogoOptions('prioridad', '1', null, false) : '';
$tipoAlertaOptions = function_exists('chvCatalogoOptions') ? chvCatalogoOptions('tipo_alerta', '100', null, false) : '';
$tipoOperacionOptions = function_exists('chvCatalogoOptions') ? chvCatalogoOptions('tipo_operacion', '', null, true) : '';
$monedaChequesOptions = function_exists('chvCatalogoOptions') ? chvCatalogoOptions('moneda_cheques', '', null, true) : '';
$instrumentoOptions = function_exists('chvCatalogoOptions') ? chvCatalogoOptions('instrumento_monetario', '', null, true) : '';
$monedaOptions = function_exists('chvCatalogoOptions')
    ? chvCatalogoOptions('moneda', '', function ($k, $v) { return $k . ' - ' . $v; }, true)
    : '';

$page_title = 'Aviso CHV - Fracción III';
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
                <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-money-check-dollar me-2"></i>Aviso CHV</h2>
                <p class="text-muted mb-0">Fracción III - Emisión y comercialización de cheques de viajero</p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        </div>

        <?php if (empty($idFraccionIII)): ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            No se encontró la Fracción III en `cat_vulnerables`. Pida a administración cargar el catálogo base.
        </div>
        <?php endif; ?>

        <div class="alert alert-info mb-4">
            <i class="fa-solid fa-circle-info me-1"></i>
            <strong>Regla CHV:</strong> Identificación <strong>siempre</strong>.
            Aviso/acumulación desde <strong><?= number_format($umbralAvisoUma, 0) ?> UMA</strong>.
        </div>

        <form id="form-chv" class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3">
                <h5 class="mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Captura de operación CHV</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente *</label>
                        <select id="id_cliente" class="form-select" required>
                            <option value="">-- Seleccione cliente --</option>
                        </select>
                        <small id="cliente_info_chv" class="text-muted">Seleccione un cliente para continuar.</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mes reportado *</label>
                        <input id="mes_reportado" class="form-control" maxlength="6" pattern="\d{6}" value="<?= date('Ym') ?>" placeholder="Ej: 202604" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Clave actividad</label>
                        <input class="form-control" value="CHV" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Clave sujeto obligado *</label>
                        <input id="clave_sujeto_obligado" class="form-control text-uppercase" maxlength="13" value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" placeholder="Ej: ABC010203AB1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Referencia aviso *</label>
                        <input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" placeholder="Ej: CHV20260001" required>
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
                        <label class="form-label">CP sucursal *</label>
                        <input id="codigo_postal" class="form-control" maxlength="5" inputmode="numeric" placeholder="Ej: 01020" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Tipo de operación *</label>
                        <select id="tipo_operacion" class="form-select" required>
                            <?= $tipoOperacionOptions ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Cheques de viajero *</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="add_cheque">
                                <i class="fa-solid fa-plus me-1"></i>Agregar cheque
                            </button>
                        </div>
                        <div id="cheques_container" class="vstack gap-2">
                            <div class="row g-2 chv-cheque-row">
                                <div class="col-md-5">
                                    <input type="number" min="1" step="1" class="form-control numero-cheques" placeholder="Número de cheques" required>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select moneda-cheques" required>
                                        <?= $monedaChequesOptions ?>
                                    </select>
                                </div>
                                <div class="col-md-1 d-grid">
                                    <button type="button" class="btn btn-outline-danger remove-cheque" title="Quitar">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Liquidación *</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="add_liquidacion">
                                <i class="fa-solid fa-plus me-1"></i>Agregar liquidación
                            </button>
                        </div>
                        <div id="liquidaciones_container" class="vstack gap-2">
                            <div class="row g-2 chv-liquidacion-row">
                                <div class="col-md-3">
                                    <input type="date" class="form-control fecha-pago" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select instrumento-monetario" required>
                                        <?= $instrumentoOptions ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select moneda-liquidacion" required>
                                        <?= $monedaOptions ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" min="0.01" step="0.01" class="form-control monto-liquidacion" placeholder="Monto" required>
                                </div>
                                <div class="col-md-1 d-grid">
                                    <button type="button" class="btn btn-outline-danger remove-liquidacion" title="Quitar">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Prioridad *</label>
                        <select id="prioridad" class="form-select" required>
                            <?= $prioridadOptions ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo de alerta *</label>
                        <select id="tipo_alerta" class="form-select" required>
                            <?= $tipoAlertaOptions ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Descripción alerta</label>
                        <input id="descripcion_alerta" class="form-control text-uppercase" maxlength="3000" placeholder="Ej: OPERACIÓN INUSUAL">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-outline-secondary">Limpiar</button>
                <button type="submit" class="btn btn-primary" <?= empty($idFraccionIII) ? 'disabled' : '' ?>>
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
function onlyDigits(v) { return (v || '').toString().replace(/\D+/g, ''); }
function upper(v) { return (v || '').toString().trim().toUpperCase(); }
function clearRow(row) {
    row.querySelectorAll('input, select').forEach(el => {
        if (el.type === 'date') el.value = new Date().toISOString().slice(0, 10);
        else el.value = '';
    });
}
function cloneRow(selector) {
    const row = document.querySelector(selector);
    const clone = row.cloneNode(true);
    clearRow(clone);
    return clone;
}
function removeOrClearRow(container, rowSelector, row) {
    if (container.querySelectorAll(rowSelector).length > 1) row.remove();
    else clearRow(row);
}

async function loadClientesCHV() {
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
    document.getElementById('cliente_info_chv').textContent = txt ? ('Cliente seleccionado: ' + txt) : 'Seleccione un cliente para continuar.';
});

document.getElementById('tipo_alerta').addEventListener('change', function () {
    if (this.value === '9999' && !document.getElementById('descripcion_alerta').value.trim()) {
        document.getElementById('descripcion_alerta').focus();
    }
});
document.getElementById('add_cheque').addEventListener('click', function () {
    document.getElementById('cheques_container').appendChild(cloneRow('.chv-cheque-row'));
});
document.getElementById('add_liquidacion').addEventListener('click', function () {
    document.getElementById('liquidaciones_container').appendChild(cloneRow('.chv-liquidacion-row'));
});
document.getElementById('cheques_container').addEventListener('click', function (e) {
    if (!e.target.closest('.remove-cheque')) return;
    removeOrClearRow(this, '.chv-cheque-row', e.target.closest('.chv-cheque-row'));
});
document.getElementById('liquidaciones_container').addEventListener('click', function (e) {
    if (!e.target.closest('.remove-liquidacion')) return;
    removeOrClearRow(this, '.chv-liquidacion-row', e.target.closest('.chv-liquidacion-row'));
});

document.getElementById('form-chv').addEventListener('submit', async function (e) {
    e.preventDefault();
    const datosCheque = Array.from(document.querySelectorAll('.chv-cheque-row')).map(row => ({
        numero_cheques: onlyDigits(row.querySelector('.numero-cheques')?.value || ''),
        moneda_cheques: (row.querySelector('.moneda-cheques')?.value || '').trim()
    })).filter(x => x.numero_cheques || x.moneda_cheques);
    const datosLiquidacion = Array.from(document.querySelectorAll('.chv-liquidacion-row')).map(row => ({
        fecha_pago: (row.querySelector('.fecha-pago')?.value || '').trim(),
        instrumento_monetario: (row.querySelector('.instrumento-monetario')?.value || '').trim(),
        moneda: (row.querySelector('.moneda-liquidacion')?.value || '').trim(),
        monto_operacion: parseFloat(row.querySelector('.monto-liquidacion')?.value || '0')
    })).filter(x => x.fecha_pago || x.instrumento_monetario || x.moneda || x.monto_operacion);
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
        datos_cheque: datosCheque,
        datos_liquidacion: datosLiquidacion
    };

    if (!payload.id_cliente) return showMsg('Validación', 'Seleccione un cliente.', 'warning');
    if (!payload.mes_reportado || payload.mes_reportado.length !== 6) return showMsg('Validación', 'Mes reportado inválido.', 'warning');
    if (!payload.datos_cheque.length) return showMsg('Validación', 'Capture al menos un cheque.', 'warning');
    if (payload.datos_cheque.some(x => !x.numero_cheques || Number(x.numero_cheques) <= 0 || !x.moneda_cheques)) return showMsg('Validación', 'Revise las partidas de cheques.', 'warning');
    if (!payload.datos_liquidacion.length) return showMsg('Validación', 'Capture al menos una liquidación.', 'warning');
    if (payload.datos_liquidacion.some(x => !x.fecha_pago || !x.instrumento_monetario || !x.moneda || !x.monto_operacion || x.monto_operacion <= 0)) return showMsg('Validación', 'Revise las partidas de liquidación.', 'warning');
    if (payload.tipo_alerta === '9999' && !payload.descripcion_alerta) return showMsg('Validación', 'Debe capturar descripción de alerta para tipo 9999.', 'warning');

    const btn = this.querySelector('button[type="submit"]');
    const old = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Registrando...';

    try {
        const res = await fetch('api/registrar_aviso_chv.php', {
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
        document.querySelectorAll('.chv-cheque-row:not(:first-child), .chv-liquidacion-row:not(:first-child)').forEach(row => row.remove());
        document.querySelectorAll('.fecha-pago').forEach(el => el.value = new Date().toISOString().slice(0, 10));
    } catch (err) {
        showMsg('Error', err.message || 'No fue posible registrar', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = old;
    }
});

loadClientesCHV();
</script>

</body>
</html>
