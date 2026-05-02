<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/pld_fraccion_xv.php';
require_once 'config/ari_catalogos.php';

requireModuleActive($pdo, 'pld');
if (!checkHabilitadoPLD($pdo)) { header('Location: index.php?error=pld_no_habilitado'); exit; }
$userId = $_SESSION['user_id'] ?? 0;
if (!function_exists('userCanAccessARI') || !userCanAccessARI($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_ari'); exit;
}

$clave_sujeto_obligado = '';
try {
    $row = null;
    if ($userId > 0) {
        $stmtU = $pdo->prepare("SELECT folio_patron_pld FROM config_empresa_usuario WHERE id_usuario = ?");
        $stmtU->execute([$userId]);
        $row = $stmtU->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    if (empty($row['folio_patron_pld'])) $row = $pdo->query("SELECT folio_patron_pld FROM config_empresa WHERE id_config = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $clave_sujeto_obligado = (string)($row['folio_patron_pld'] ?? '');
} catch (Exception $e) { }

$idFraccionXV = getIdVulnerableFraccionXV($pdo);
$prioridadOptions = ariCatalogoOptions('prioridad', '1', null, false);
$tipoAlertaOptions = ariCatalogoOptions('tipo_alerta', '100', null, false);
$tipoOperacionOptions = ariCatalogoOptions('tipo_operacion', '', null, true);
$tipoInmuebleOptions = ariCatalogoOptions('tipo_inmueble', '', null, true);
$formaPagoOptions = ariCatalogoOptions('forma_pago', '', null, true);
$instrumentoOptions = ariCatalogoOptions('instrumento_monetario', '', null, true);
$monedaOptions = ariCatalogoOptions('moneda', '', fn($k,$v) => $k . ' - ' . $v, true);

$page_title = 'Aviso ARI - Fraccion XV';
include 'templates/header.php';
?>
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/operaciones_pld.css">
</head>
<body>
<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>
<div class="content-wrapper">
    <div class="container-fluid" style="max-width:1080px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-key me-2"></i>Aviso ARI</h2>
                <p class="text-muted mb-0">Fraccion XV - Derechos personales de uso o goce de bienes inmuebles</p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        </div>
        <?php if (empty($idFraccionXV)): ?>
        <div class="alert alert-warning">No se encontro la Fraccion XV en `cat_vulnerables`.</div>
        <?php endif; ?>
        <div class="alert alert-info mb-4">
            <strong>Regla ARI:</strong> Identificacion desde <strong><?= number_format(pldFraccionXVUmbralIdentificacion(), 0) ?> UMA</strong>.
            Aviso/acumulacion desde <strong><?= number_format(pldFraccionXVUmbralAviso(), 0) ?> UMA</strong>.
        </div>

        <form id="form-ari" class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Captura de operacion ARI</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Cliente *</label><select id="id_cliente" class="form-select" required><option value="">-- Seleccione cliente --</option></select><small id="cliente_info_ari" class="text-muted">Seleccione un cliente.</small></div>
                    <div class="col-md-3"><label class="form-label">Mes reportado *</label><input id="mes_reportado" class="form-control" maxlength="6" pattern="\d{6}" value="<?= date('Ym') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Clave actividad</label><input class="form-control" value="ARI" readonly></div>
                    <div class="col-md-6"><label class="form-label">Clave sujeto obligado *</label><input id="clave_sujeto_obligado" class="form-control text-uppercase" maxlength="13" value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Referencia aviso *</label><input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" placeholder="ARI20260001" required></div>
                    <div class="col-md-3"><label class="form-label">Exento</label><select id="exento" class="form-select"><option value="0" selected>0 - No</option><option value="1">1 - Si</option></select></div>
                    <div class="col-md-6"><label class="form-label">Clave entidad colegiada</label><input id="clave_entidad_colegiada" class="form-control text-uppercase" maxlength="12"></div>
                    <div class="col-md-3"><label class="form-label">Fecha operacion *</label><input id="fecha_operacion" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Tipo operacion *</label><select id="tipo_operacion" class="form-select" required><?= $tipoOperacionOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Prioridad *</label><select id="prioridad" class="form-select" required><?= $prioridadOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Tipo alerta *</label><select id="tipo_alerta" class="form-select" required><?= $tipoAlertaOptions ?></select></div>

                    <div class="col-12"><hr><h6 class="fw-bold">Inmueble</h6></div>
                    <div class="col-md-3"><label class="form-label">Tipo inmueble *</label><select id="tipo_inmueble" class="form-select" required><?= $tipoInmuebleOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">CP inmueble *</label><input id="codigo_postal" class="form-control" maxlength="5" required></div>
                    <div class="col-md-3"><label class="form-label">Colonia *</label><input id="colonia" class="form-control text-uppercase" maxlength="50" required></div>
                    <div class="col-md-3"><label class="form-label">Calle *</label><input id="calle" class="form-control text-uppercase" maxlength="100" required></div>
                    <div class="col-md-3"><label class="form-label">Numero exterior *</label><input id="numero_exterior" class="form-control text-uppercase" maxlength="56" required></div>
                    <div class="col-md-3"><label class="form-label">Numero interior</label><input id="numero_interior" class="form-control text-uppercase" maxlength="40"></div>
                    <div class="col-md-3"><label class="form-label">Inicio *</label><input id="fecha_inicio" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Termino *</label><input id="fecha_termino" type="date" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Valor referencia *</label><input id="valor_referencia" type="number" min="0.01" step="0.01" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Folio real *</label><input id="folio_real" class="form-control text-uppercase" maxlength="200" required></div>

                    <div class="col-12"><hr><h6 class="fw-bold">Pago</h6></div>
                    <div class="col-md-3"><label class="form-label">Forma pago *</label><select id="forma_pago" class="form-select" required><?= $formaPagoOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Instrumento monetario *</label><select id="instrumento_monetario" class="form-select" required><?= $instrumentoOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Moneda *</label><select id="moneda" class="form-select" required><?= $monedaOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Monto operacion *</label><input id="monto_operacion" type="number" min="0.01" step="0.01" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Descripcion alerta</label><input id="descripcion_alerta" class="form-control text-uppercase" maxlength="3000"></div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2"><button type="reset" class="btn btn-outline-secondary">Limpiar</button><button type="submit" class="btn btn-primary" <?= empty($idFraccionXV) ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk me-1"></i>Registrar operacion</button></div>
        </form>
    </div>
</div>
<script>
function showMsg(t,x,i){ if(typeof Swal!=='undefined') Swal.fire({title:t,text:x,icon:i}); else alert(t+': '+x); }
function onlyDigits(v){ return (v||'').toString().replace(/\D+/g,''); }
function upper(v){ return (v||'').toString().trim().toUpperCase(); }
function ymd(v){ return (v||'').replaceAll('-',''); }
async function loadClientesARI(){ const s=document.getElementById('id_cliente'); try{ const r=await fetch('api/get_clients.php'); const d=await r.json(); if(d.status!=='success'||!Array.isArray(d.data)) return; d.data.forEach(c=>{ const o=document.createElement('option'); o.value=c.id_cliente; const n=[c.nombre,c.apellido_paterno,c.apellido_materno].filter(Boolean).join(' ').trim(); o.textContent=`${c.id_cliente} - ${n||c.razon_social||'CLIENTE'}${c.rfc?' ('+c.rfc+')':''}`; s.appendChild(o); }); }catch(e){console.error(e);} }
document.getElementById('id_cliente').addEventListener('change', function(){ document.getElementById('cliente_info_ari').textContent=this.options[this.selectedIndex]?.textContent||'Seleccione un cliente.'; });
document.getElementById('form-ari').addEventListener('submit', async function(e){
    e.preventDefault();
    const payload={
        id_cliente:parseInt(document.getElementById('id_cliente').value||'0',10),
        mes_reportado:onlyDigits(document.getElementById('mes_reportado').value),
        clave_sujeto_obligado:upper(document.getElementById('clave_sujeto_obligado').value),
        clave_entidad_colegiada:upper(document.getElementById('clave_entidad_colegiada').value),
        referencia_aviso:upper(document.getElementById('referencia_aviso').value),
        prioridad:document.getElementById('prioridad').value,
        exento:document.getElementById('exento').value,
        tipo_alerta:document.getElementById('tipo_alerta').value,
        descripcion_alerta:upper(document.getElementById('descripcion_alerta').value),
        fecha_operacion:ymd(document.getElementById('fecha_operacion').value),
        tipo_operacion:document.getElementById('tipo_operacion').value,
        tipo_inmueble:document.getElementById('tipo_inmueble').value,
        codigo_postal:onlyDigits(document.getElementById('codigo_postal').value),
        colonia:upper(document.getElementById('colonia').value),
        calle:upper(document.getElementById('calle').value),
        numero_exterior:upper(document.getElementById('numero_exterior').value),
        numero_interior:upper(document.getElementById('numero_interior').value),
        fecha_inicio:ymd(document.getElementById('fecha_inicio').value),
        fecha_termino:ymd(document.getElementById('fecha_termino').value),
        valor_referencia:parseFloat(document.getElementById('valor_referencia').value||'0'),
        folio_real:upper(document.getElementById('folio_real').value),
        forma_pago:document.getElementById('forma_pago').value,
        instrumento_monetario:document.getElementById('instrumento_monetario').value,
        moneda:document.getElementById('moneda').value,
        monto_operacion:parseFloat(document.getElementById('monto_operacion').value||'0')
    };
    if(!payload.id_cliente) return showMsg('Validacion','Seleccione un cliente.','warning');
    if(payload.tipo_alerta==='9999'&&!payload.descripcion_alerta) return showMsg('Validacion','Debe capturar descripcion de alerta.','warning');
    const btn=this.querySelector('button[type="submit"]'), old=btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-1"></i>Registrando...';
    try{ const r=await fetch('api/registrar_aviso_ari.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); const d=await r.json(); if(d.status!=='success') throw new Error(d.message||'No fue posible registrar'); showMsg('Registro exitoso', `Operacion #${d.id_operacion||''}${d.id_aviso?' | Aviso #'+d.id_aviso:''}`, 'success'); this.reset(); document.getElementById('mes_reportado').value=new Date().toISOString().slice(0,7).replace('-',''); }catch(err){ showMsg('Error',err.message||'No fue posible registrar','error'); } finally { btn.disabled=false; btn.innerHTML=old; }
});
loadClientesARI();
</script>
</body>
</html>
