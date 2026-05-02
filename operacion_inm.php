<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/pld_fraccion_v_bis.php';
require_once 'config/inm_catalogos.php';

requireModuleActive($pdo, 'pld');
if (!checkHabilitadoPLD($pdo)) { header('Location: index.php?error=pld_no_habilitado'); exit; }
$userId = $_SESSION['user_id'] ?? 0;
if (!function_exists('userCanAccessINM') || !userCanAccessINM($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_inm'); exit;
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

$idFraccionVBis = getIdVulnerableFraccionVBis($pdo);
$umbralAvisoUma = getUmbralAvisoVBis();
$prioridadOptions = inmCatalogoOptions('prioridad', '1', null, false);
$tipoAlertaOptions = inmCatalogoOptions('tipo_alerta', '100', null, false);
$tipoOperacionOptions = inmCatalogoOptions('tipo_operacion', '', null, true);
$figuraClienteOptions = inmCatalogoOptions('figura_cliente', '', null, true);
$figuraSoOptions = inmCatalogoOptions('figura_so', '', null, true);
$tipoInmuebleOptions = inmCatalogoOptions('tipo_inmueble', '', null, true);
$formaPagoOptions = inmCatalogoOptions('forma_pago', '', null, true);
$instrumentoOptions = inmCatalogoOptions('instrumento_monetario', '', null, true);
$monedaOptions = inmCatalogoOptions('moneda', '', fn($k,$v) => $k . ' - ' . $v, true);

$page_title = 'Aviso INM - Fracción V Bis';
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
                <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-house-chimney me-2"></i>Aviso INM</h2>
                <p class="text-muted mb-0">Fracción V Bis - Inmuebles</p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        </div>
        <?php if (empty($idFraccionVBis)): ?>
        <div class="alert alert-warning">No se encontró la Fracción V Bis en `cat_vulnerables`.</div>
        <?php endif; ?>
        <div class="alert alert-info mb-4">
            <strong>Regla INM:</strong> Identificación <strong>siempre</strong>. Aviso/acumulación desde <strong><?= number_format($umbralAvisoUma, 0) ?> UMA</strong>.
        </div>
        <form id="form-inm" class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Captura de operación INM</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Cliente *</label><select id="id_cliente" class="form-select" required><option value="">-- Seleccione cliente --</option></select><small id="cliente_info_inm" class="text-muted">Seleccione un cliente.</small></div>
                    <div class="col-md-3"><label class="form-label">Mes reportado *</label><input id="mes_reportado" class="form-control" maxlength="6" pattern="\d{6}" value="<?= date('Ym') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Clave actividad</label><input class="form-control" value="INM" readonly></div>
                    <div class="col-md-6"><label class="form-label">Clave sujeto obligado *</label><input id="clave_sujeto_obligado" class="form-control text-uppercase" maxlength="13" value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Referencia aviso *</label><input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" placeholder="INM20260001" required></div>
                    <div class="col-md-3"><label class="form-label">Exento</label><select id="exento" class="form-select"><option value="0" selected>0 - No</option><option value="1">1 - Sí</option></select></div>
                    <div class="col-md-6"><label class="form-label">Clave entidad colegiada</label><input id="clave_entidad_colegiada" class="form-control text-uppercase" maxlength="12"></div>
                    <div class="col-md-3"><label class="form-label">Fecha operación *</label><input id="fecha_operacion" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Tipo operación *</label><select id="tipo_operacion" class="form-select" required><?= $tipoOperacionOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Figura cliente *</label><select id="figura_cliente" class="form-select" required><?= $figuraClienteOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Figura SO *</label><select id="figura_so" class="form-select" required><?= $figuraSoOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Prioridad *</label><select id="prioridad" class="form-select" required><?= $prioridadOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Tipo alerta *</label><select id="tipo_alerta" class="form-select" required><?= $tipoAlertaOptions ?></select></div>

                    <div class="col-12"><hr><h6 class="fw-bold">Características del inmueble</h6></div>
                    <div class="col-md-3"><label class="form-label">Tipo inmueble *</label><select id="tipo_inmueble" class="form-select" required><?= $tipoInmuebleOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Valor pactado *</label><input id="valor_pactado" type="number" min="0.01" step="0.01" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">CP inmueble *</label><input id="cp_inmueble" class="form-control" maxlength="5" required></div>
                    <div class="col-md-3"><label class="form-label">Folio real *</label><input id="folio_real" class="form-control text-uppercase" maxlength="200" required></div>
                    <div class="col-md-3"><label class="form-label">Colonia *</label><input id="colonia" class="form-control text-uppercase" maxlength="50" required></div>
                    <div class="col-md-3"><label class="form-label">Calle *</label><input id="calle" class="form-control text-uppercase" maxlength="100" required></div>
                    <div class="col-md-2"><label class="form-label">Núm. exterior *</label><input id="numero_exterior" class="form-control text-uppercase" maxlength="56" required></div>
                    <div class="col-md-2"><label class="form-label">Terreno m2 *</label><input id="dimension_terreno" type="number" min="0.01" max="9999999.99" step="0.01" class="form-control" required></div>
                    <div class="col-md-2"><label class="form-label">Construido m2 *</label><input id="dimension_construido" type="number" min="0.01" max="9999999.99" step="0.01" class="form-control" required></div>

                    <div class="col-12"><hr><h6 class="fw-bold">Contrato / liquidación</h6></div>
                    <div class="col-md-3"><label class="form-label">Documento</label><select id="instrumento_o_contrato" class="form-select"><option value="contrato">Contrato privado</option><option value="instrumento">Instrumento público</option></select></div>
                    <div class="col-md-3"><label class="form-label">Forma pago *</label><select id="forma_pago" class="form-select" required><?= $formaPagoOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Instrumento monetario</label><select id="instrumento_monetario" class="form-select"><?= $instrumentoOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Moneda *</label><select id="moneda" class="form-select" required><?= $monedaOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Fecha pago *</label><input id="fecha_pago" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Monto operación *</label><input id="monto" type="number" min="0.01" step="0.01" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Descripción alerta</label><input id="descripcion_alerta" class="form-control text-uppercase" maxlength="3000"></div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2"><button type="reset" class="btn btn-outline-secondary">Limpiar</button><button type="submit" class="btn btn-primary" <?= empty($idFraccionVBis) ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk me-1"></i>Registrar operación</button></div>
        </form>
    </div>
</div>
<script>
function showMsg(t,x,i){ if(typeof Swal!=='undefined') Swal.fire({title:t,text:x,icon:i}); else alert(t+': '+x); }
function onlyDigits(v){ return (v||'').toString().replace(/\D+/g,''); }
function upper(v){ return (v||'').toString().trim().toUpperCase(); }
async function loadClientesINM(){ const s=document.getElementById('id_cliente'); try{ const r=await fetch('api/get_clients.php'); const d=await r.json(); if(d.status!=='success'||!Array.isArray(d.data)) return; d.data.forEach(c=>{ const o=document.createElement('option'); o.value=c.id_cliente; const n=[c.nombre,c.apellido_paterno,c.apellido_materno].filter(Boolean).join(' ').trim(); o.textContent=`${c.id_cliente} - ${n||c.razon_social||'CLIENTE'}${c.rfc?' ('+c.rfc+')':''}`; s.appendChild(o); }); }catch(e){console.error(e);} }
document.getElementById('id_cliente').addEventListener('change', function(){ document.getElementById('cliente_info_inm').textContent=this.options[this.selectedIndex]?.textContent||'Seleccione un cliente.'; });
document.getElementById('form-inm').addEventListener('submit', async function(e){
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
        fecha_operacion:document.getElementById('fecha_operacion').value,
        tipo_operacion:document.getElementById('tipo_operacion').value,
        figura_cliente:document.getElementById('figura_cliente').value,
        figura_so:document.getElementById('figura_so').value,
        caracteristicas_inmueble:{
            tipo_inmueble:document.getElementById('tipo_inmueble').value,
            valor_pactado:parseFloat(document.getElementById('valor_pactado').value||'0'),
            codigo_postal:onlyDigits(document.getElementById('cp_inmueble').value),
            folio_real:upper(document.getElementById('folio_real').value),
            colonia:upper(document.getElementById('colonia').value),
            calle:upper(document.getElementById('calle').value),
            numero_exterior:upper(document.getElementById('numero_exterior').value),
            dimension_terreno:parseFloat(document.getElementById('dimension_terreno').value||'0'),
            dimension_construido:parseFloat(document.getElementById('dimension_construido').value||'0')
        },
        instrumento_o_contrato:document.getElementById('instrumento_o_contrato').value,
        datos_liquidacion:[{
            fecha_pago:document.getElementById('fecha_pago').value,
            forma_pago:document.getElementById('forma_pago').value,
            instrumento_monetario:document.getElementById('instrumento_monetario').value,
            moneda:document.getElementById('moneda').value,
            monto_operacion:parseFloat(document.getElementById('monto').value||'0')
        }]
    };
    if(!payload.id_cliente) return showMsg('Validación','Seleccione un cliente.','warning');
    if(payload.tipo_alerta==='9999'&&!payload.descripcion_alerta) return showMsg('Validación','Debe capturar descripción de alerta.','warning');
    const btn=this.querySelector('button[type="submit"]'), old=btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-1"></i>Registrando...';
    try{ const r=await fetch('api/registrar_aviso_inm.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); const d=await r.json(); if(d.status!=='success') throw new Error(d.message||'No fue posible registrar'); showMsg('Registro exitoso', `Operación #${d.id_operacion||''}${d.id_aviso?' | Aviso #'+d.id_aviso:''}`, 'success'); this.reset(); document.getElementById('mes_reportado').value=new Date().toISOString().slice(0,7).replace('-',''); }catch(err){ showMsg('Error',err.message||'No fue posible registrar','error'); } finally { btn.disabled=false; btn.innerHTML=old; }
});
loadClientesINM();
</script>
</body>
</html>
