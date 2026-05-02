<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/pld_fraccion_x.php';
require_once 'config/tcv_catalogos.php';

requireModuleActive($pdo, 'pld');
if (!checkHabilitadoPLD($pdo)) { header('Location: index.php?error=pld_no_habilitado'); exit; }
$userId = $_SESSION['user_id'] ?? 0;
if (!function_exists('userCanAccessTCV') || !userCanAccessTCV($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_tcv'); exit;
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

$idFraccionX = getIdVulnerableFraccionX($pdo);
$umbralAvisoUma = pldFraccionXUmbralAviso();
$prioridadOptions = tcvCatalogoOptions('prioridad', '1', null, false);
$tipoAlertaOptions = tcvCatalogoOptions('tipo_alerta', '100', null, false);
$tipoOperacionOptions = tcvCatalogoOptions('tipo_operacion', '', null, true);
$tipoServicioOptions = tcvCatalogoOptions('tipo_servicio', '1', null, false);
$instrumentoOptions = tcvCatalogoOptions('instrumento_monetario', '', null, true);
$monedaOptions = tcvCatalogoOptions('moneda', '', fn($k,$v) => $k . ' - ' . $v, true);
$tipoValorOptions = tcvCatalogoOptions('tipo_valor', '', null, true);

$page_title = 'Aviso TCV - Fracción X';
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
                <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-truck-ramp-box me-2"></i>Aviso TCV</h2>
                <p class="text-muted mb-0">Fracción X - Traslado o custodia de dinero o valores</p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        </div>
        <?php if (empty($idFraccionX)): ?>
        <div class="alert alert-warning">No se encontró la Fracción X en `cat_vulnerables`.</div>
        <?php endif; ?>
        <div class="alert alert-info mb-4">
            <strong>Regla TCV:</strong> Identificación <strong>siempre</strong>. Aviso desde <strong><?= number_format($umbralAvisoUma, 0) ?> UMA</strong> o siempre cuando no sea posible determinar el monto.
        </div>
        <form id="form-tcv" class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Captura de operación TCV</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Cliente *</label><select id="id_cliente" class="form-select" required><option value="">-- Seleccione cliente --</option></select><small id="cliente_info_tcv" class="text-muted">Seleccione un cliente.</small></div>
                    <div class="col-md-3"><label class="form-label">Mes reportado *</label><input id="mes_reportado" class="form-control" maxlength="6" pattern="\d{6}" value="<?= date('Ym') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Clave actividad</label><input class="form-control" value="TCV" readonly></div>
                    <div class="col-md-6"><label class="form-label">Clave sujeto obligado *</label><input id="clave_sujeto_obligado" class="form-control text-uppercase" maxlength="13" value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Referencia aviso *</label><input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" placeholder="TCV20260001" required></div>
                    <div class="col-md-3"><label class="form-label">Exento</label><select id="exento" class="form-select"><option value="0" selected>0 - No</option><option value="1">1 - Sí</option></select></div>
                    <div class="col-md-6"><label class="form-label">Clave entidad colegiada</label><input id="clave_entidad_colegiada" class="form-control text-uppercase" maxlength="12"></div>
                    <div class="col-md-3"><label class="form-label">Fecha operación *</label><input id="fecha_operacion" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Tipo operación *</label><select id="tipo_operacion" class="form-select" required><?= $tipoOperacionOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Prioridad *</label><select id="prioridad" class="form-select" required><?= $prioridadOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Tipo alerta *</label><select id="tipo_alerta" class="form-select" required><?= $tipoAlertaOptions ?></select></div>

                    <div class="col-12"><hr><h6 class="fw-bold">Bien trasladado o custodiado</h6></div>
                    <div class="col-md-3"><label class="form-label">Tipo de bien</label><select id="bien_tipo" class="form-select"><option value="efectivo">Efectivo / instrumento</option><option value="valores">Otros valores</option></select></div>
                    <div class="col-md-3 bien-efectivo"><label class="form-label">Instrumento *</label><select id="instrumento_monetario" class="form-select"><?= $instrumentoOptions ?></select></div>
                    <div class="col-md-3 bien-efectivo"><label class="form-label">Moneda *</label><select id="moneda" class="form-select"><?= $monedaOptions ?></select></div>
                    <div class="col-md-3 bien-valores d-none"><label class="form-label">Tipo valor *</label><select id="tipo_valor" class="form-select"><?= $tipoValorOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Monto / valor *</label><input id="monto_operacion" type="number" min="0.01" step="0.01" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">Monto no determinado</label><select id="monto_no_determinado" class="form-select"><option value="0" selected>No</option><option value="1">Sí</option></select></div>
                    <div class="col-md-6 bien-valores d-none"><label class="form-label">Descripción del valor</label><input id="descripcion_valor" class="form-control text-uppercase" maxlength="3000"></div>

                    <div class="col-12"><hr><h6 class="fw-bold">Recepción / custodia / entrega</h6></div>
                    <div class="col-md-3"><label class="form-label">Tipo servicio *</label><select id="tipo_servicio" class="form-select"><?= $tipoServicioOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Fecha recepción *</label><input id="fecha_recepcion" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">CP recepción *</label><input id="cp_recepcion" class="form-control" maxlength="5" required></div>
                    <div class="col-md-3"><label class="form-label">CP entrega</label><input id="cp_entrega" class="form-control" maxlength="5"></div>
                    <div class="col-md-3"><label class="form-label">Fecha entrega</label><input id="fecha_entrega" type="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Inicio custodia</label><input id="fecha_inicio" type="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Fin custodia</label><input id="fecha_fin" type="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-3"><label class="form-label">CP custodia</label><input id="cp_custodia" class="form-control" maxlength="5"></div>
                    <div class="col-12"><hr><h6 class="fw-bold">Destinatario</h6></div>
                    <div class="col-md-3"><label class="form-label">Es persona del aviso</label><select id="destinatario_persona_aviso" class="form-select"><option value="SI" selected>Sí</option><option value="NO">No</option></select></div>
                    <div class="col-md-3 destinatario-no d-none"><label class="form-label">Tipo persona</label><select id="destinatario_tipo_persona" class="form-select"><option value="fisica" selected>Persona física</option><option value="moral">Persona moral</option><option value="fideicomiso">Fideicomiso</option></select></div>
                    <div class="col-md-3 destinatario-fisica destinatario-no d-none"><label class="form-label">Nombre</label><input id="destinatario_nombre" class="form-control text-uppercase" maxlength="200"></div>
                    <div class="col-md-3 destinatario-fisica destinatario-no d-none"><label class="form-label">Apellido paterno</label><input id="destinatario_apellido_paterno" class="form-control text-uppercase" maxlength="200"></div>
                    <div class="col-md-3 destinatario-fisica destinatario-no d-none"><label class="form-label">Apellido materno</label><input id="destinatario_apellido_materno" class="form-control text-uppercase" maxlength="200"></div>
                    <div class="col-md-3 destinatario-fisica destinatario-no d-none"><label class="form-label">Fecha nacimiento</label><input id="destinatario_fecha_nacimiento" type="date" class="form-control"></div>
                    <div class="col-md-4 destinatario-moral destinatario-fideicomiso destinatario-no d-none"><label class="form-label">Denominación / razón</label><input id="destinatario_denominacion_razon" class="form-control text-uppercase" maxlength="254"></div>
                    <div class="col-md-3 destinatario-moral destinatario-no d-none"><label class="form-label">Fecha constitución</label><input id="destinatario_fecha_constitucion" type="date" class="form-control"></div>
                    <div class="col-md-3 destinatario-fideicomiso destinatario-no d-none"><label class="form-label">Identificador fideicomiso</label><input id="destinatario_identificador_fideicomiso" class="form-control text-uppercase" maxlength="40"></div>
                    <div class="col-md-3 destinatario-no d-none"><label class="form-label">RFC</label><input id="destinatario_rfc" class="form-control text-uppercase" maxlength="13"></div>
                    <div class="col-md-3 destinatario-fisica destinatario-no d-none"><label class="form-label">CURP</label><input id="destinatario_curp" class="form-control text-uppercase" maxlength="18"></div>
                    <div class="col-12"><hr><h6 class="fw-bold">Dueño beneficiario</h6></div>
                    <div class="col-md-3"><label class="form-label">Incluir dueño beneficiario</label><select id="dueno_beneficiario_incluir" class="form-select"><option value="0" selected>No</option><option value="1">Sí</option></select></div>
                    <div class="col-md-3 dueno-beneficiario d-none"><label class="form-label">Tipo persona</label><select id="dueno_beneficiario_tipo_persona" class="form-select"><option value="fisica" selected>Persona física</option><option value="moral">Persona moral</option><option value="fideicomiso">Fideicomiso</option></select></div>
                    <div class="col-md-3 db-fisica dueno-beneficiario d-none"><label class="form-label">Nombre</label><input id="dueno_beneficiario_nombre" class="form-control text-uppercase" maxlength="200"></div>
                    <div class="col-md-3 db-fisica dueno-beneficiario d-none"><label class="form-label">Apellido paterno</label><input id="dueno_beneficiario_apellido_paterno" class="form-control text-uppercase" maxlength="200"></div>
                    <div class="col-md-3 db-fisica dueno-beneficiario d-none"><label class="form-label">Apellido materno</label><input id="dueno_beneficiario_apellido_materno" class="form-control text-uppercase" maxlength="200"></div>
                    <div class="col-md-3 db-fisica dueno-beneficiario d-none"><label class="form-label">Fecha nacimiento</label><input id="dueno_beneficiario_fecha_nacimiento" type="date" class="form-control"></div>
                    <div class="col-md-4 db-moral db-fideicomiso dueno-beneficiario d-none"><label class="form-label">Denominación / razón</label><input id="dueno_beneficiario_denominacion_razon" class="form-control text-uppercase" maxlength="254"></div>
                    <div class="col-md-3 db-moral dueno-beneficiario d-none"><label class="form-label">Fecha constitución</label><input id="dueno_beneficiario_fecha_constitucion" type="date" class="form-control"></div>
                    <div class="col-md-3 db-fideicomiso dueno-beneficiario d-none"><label class="form-label">Identificador fideicomiso</label><input id="dueno_beneficiario_identificador_fideicomiso" class="form-control text-uppercase" maxlength="40"></div>
                    <div class="col-md-2 db-fisica db-moral dueno-beneficiario d-none"><label class="form-label">País</label><input id="dueno_beneficiario_pais_nacionalidad" class="form-control text-uppercase" maxlength="2" value="MX"></div>
                    <div class="col-md-3 dueno-beneficiario d-none"><label class="form-label">RFC</label><input id="dueno_beneficiario_rfc" class="form-control text-uppercase" maxlength="13"></div>
                    <div class="col-md-3 db-fisica dueno-beneficiario d-none"><label class="form-label">CURP</label><input id="dueno_beneficiario_curp" class="form-control text-uppercase" maxlength="18"></div>
                    <div class="col-md-6"><label class="form-label">Descripción alerta</label><input id="descripcion_alerta" class="form-control text-uppercase" maxlength="3000"></div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2"><button type="reset" class="btn btn-outline-secondary">Limpiar</button><button type="submit" class="btn btn-primary" <?= empty($idFraccionX) ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk me-1"></i>Registrar operación</button></div>
        </form>
    </div>
</div>
<script>
function showMsg(t,x,i){ if(typeof Swal!=='undefined') Swal.fire({title:t,text:x,icon:i}); else alert(t+': '+x); }
function onlyDigits(v){ return (v||'').toString().replace(/\D+/g,''); }
function upper(v){ return (v||'').toString().trim().toUpperCase(); }
function ymd(v){ return (v||'').replaceAll('-',''); }
async function loadClientesTCV(){ const s=document.getElementById('id_cliente'); try{ const r=await fetch('api/get_clients.php'); const d=await r.json(); if(d.status!=='success'||!Array.isArray(d.data)) return; d.data.forEach(c=>{ const o=document.createElement('option'); o.value=c.id_cliente; const n=[c.nombre,c.apellido_paterno,c.apellido_materno].filter(Boolean).join(' ').trim(); o.textContent=`${c.id_cliente} - ${n||c.razon_social||'CLIENTE'}${c.rfc?' ('+c.rfc+')':''}`; s.appendChild(o); }); }catch(e){console.error(e);} }
document.getElementById('id_cliente').addEventListener('change', function(){ document.getElementById('cliente_info_tcv').textContent=this.options[this.selectedIndex]?.textContent||'Seleccione un cliente.'; });
document.getElementById('bien_tipo').addEventListener('change', function(){
    const valores=this.value==='valores';
    document.querySelectorAll('.bien-valores').forEach(e=>e.classList.toggle('d-none',!valores));
    document.querySelectorAll('.bien-efectivo').forEach(e=>e.classList.toggle('d-none',valores));
});
function toggleDestinatarioTCV(){
    const no=document.getElementById('destinatario_persona_aviso').value==='NO';
    const tipo=document.getElementById('destinatario_tipo_persona').value;
    document.querySelectorAll('.destinatario-no').forEach(e=>e.classList.toggle('d-none',!no));
    document.querySelectorAll('.destinatario-fisica').forEach(e=>e.classList.toggle('d-none',!no||tipo!=='fisica'));
    document.querySelectorAll('.destinatario-moral').forEach(e=>e.classList.toggle('d-none',!no||tipo!=='moral'));
    document.querySelectorAll('.destinatario-fideicomiso').forEach(e=>e.classList.toggle('d-none',!no||tipo!=='fideicomiso'));
}
document.getElementById('destinatario_persona_aviso').addEventListener('change', toggleDestinatarioTCV);
document.getElementById('destinatario_tipo_persona').addEventListener('change', toggleDestinatarioTCV);
function toggleDuenoBeneficiarioTCV(){
    const on=document.getElementById('dueno_beneficiario_incluir').value==='1';
    const tipo=document.getElementById('dueno_beneficiario_tipo_persona').value;
    document.querySelectorAll('.dueno-beneficiario').forEach(e=>e.classList.toggle('d-none',!on));
    document.querySelectorAll('.db-fisica').forEach(e=>e.classList.toggle('d-none',!on||tipo!=='fisica'));
    document.querySelectorAll('.db-moral').forEach(e=>e.classList.toggle('d-none',!on||tipo!=='moral'));
    document.querySelectorAll('.db-fideicomiso').forEach(e=>e.classList.toggle('d-none',!on||tipo!=='fideicomiso'));
}
document.getElementById('dueno_beneficiario_incluir').addEventListener('change', toggleDuenoBeneficiarioTCV);
document.getElementById('dueno_beneficiario_tipo_persona').addEventListener('change', toggleDuenoBeneficiarioTCV);
document.getElementById('form-tcv').addEventListener('submit', async function(e){
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
        bien_tipo:document.getElementById('bien_tipo').value,
        instrumento_monetario:document.getElementById('instrumento_monetario').value,
        moneda:document.getElementById('moneda').value,
        tipo_valor:document.getElementById('tipo_valor').value,
        monto_operacion:parseFloat(document.getElementById('monto_operacion').value||'0'),
        valor_objeto:parseFloat(document.getElementById('monto_operacion').value||'0'),
        monto_no_determinado:document.getElementById('monto_no_determinado').value==='1',
        descripcion_valor:upper(document.getElementById('descripcion_valor').value),
        tipo_servicio:document.getElementById('tipo_servicio').value,
        fecha_recepcion:ymd(document.getElementById('fecha_recepcion').value),
        cp_recepcion:onlyDigits(document.getElementById('cp_recepcion').value),
        fecha_entrega:ymd(document.getElementById('fecha_entrega').value),
        cp_entrega:onlyDigits(document.getElementById('cp_entrega').value),
        fecha_inicio:ymd(document.getElementById('fecha_inicio').value),
        fecha_fin:ymd(document.getElementById('fecha_fin').value),
        cp_custodia:onlyDigits(document.getElementById('cp_custodia').value),
        destinatario_persona_aviso:document.getElementById('destinatario_persona_aviso').value,
        destinatario_tipo_persona:document.getElementById('destinatario_tipo_persona').value,
        destinatario_nombre:upper(document.getElementById('destinatario_nombre').value),
        destinatario_apellido_paterno:upper(document.getElementById('destinatario_apellido_paterno').value),
        destinatario_apellido_materno:upper(document.getElementById('destinatario_apellido_materno').value),
        destinatario_fecha_nacimiento:ymd(document.getElementById('destinatario_fecha_nacimiento').value),
        destinatario_denominacion_razon:upper(document.getElementById('destinatario_denominacion_razon').value),
        destinatario_fecha_constitucion:ymd(document.getElementById('destinatario_fecha_constitucion').value),
        destinatario_identificador_fideicomiso:upper(document.getElementById('destinatario_identificador_fideicomiso').value),
        destinatario_rfc:upper(document.getElementById('destinatario_rfc').value),
        destinatario_curp:upper(document.getElementById('destinatario_curp').value),
        dueno_beneficiario_incluir:document.getElementById('dueno_beneficiario_incluir').value==='1',
        dueno_beneficiario_tipo_persona:document.getElementById('dueno_beneficiario_tipo_persona').value,
        dueno_beneficiario_nombre:upper(document.getElementById('dueno_beneficiario_nombre').value),
        dueno_beneficiario_apellido_paterno:upper(document.getElementById('dueno_beneficiario_apellido_paterno').value),
        dueno_beneficiario_apellido_materno:upper(document.getElementById('dueno_beneficiario_apellido_materno').value),
        dueno_beneficiario_fecha_nacimiento:ymd(document.getElementById('dueno_beneficiario_fecha_nacimiento').value),
        dueno_beneficiario_denominacion_razon:upper(document.getElementById('dueno_beneficiario_denominacion_razon').value),
        dueno_beneficiario_fecha_constitucion:ymd(document.getElementById('dueno_beneficiario_fecha_constitucion').value),
        dueno_beneficiario_identificador_fideicomiso:upper(document.getElementById('dueno_beneficiario_identificador_fideicomiso').value),
        dueno_beneficiario_pais_nacionalidad:upper(document.getElementById('dueno_beneficiario_pais_nacionalidad').value),
        dueno_beneficiario_rfc:upper(document.getElementById('dueno_beneficiario_rfc').value),
        dueno_beneficiario_curp:upper(document.getElementById('dueno_beneficiario_curp').value)
    };
    if(!payload.id_cliente) return showMsg('Validación','Seleccione un cliente.','warning');
    if(payload.tipo_alerta==='9999'&&!payload.descripcion_alerta) return showMsg('Validación','Debe capturar descripción de alerta.','warning');
    const btn=this.querySelector('button[type="submit"]'), old=btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-1"></i>Registrando...';
    try{ const r=await fetch('api/registrar_aviso_tcv.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); const d=await r.json(); if(d.status!=='success') throw new Error(d.message||'No fue posible registrar'); showMsg('Registro exitoso', `Operación #${d.id_operacion||''}${d.id_aviso?' | Aviso #'+d.id_aviso:''}`, 'success'); this.reset(); document.getElementById('mes_reportado').value=new Date().toISOString().slice(0,7).replace('-',''); }catch(err){ showMsg('Error',err.message||'No fue posible registrar','error'); } finally { btn.disabled=false; btn.innerHTML=old; }
});
loadClientesTCV();
toggleDestinatarioTCV();
toggleDuenoBeneficiarioTCV();
</script>
</body>
</html>
