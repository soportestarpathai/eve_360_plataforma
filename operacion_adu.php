<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/pld_fraccion_xiv.php';
require_once 'config/adu_catalogos.php';

requireModuleActive($pdo, 'pld');

if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if (!function_exists('userCanAccessADU') || !userCanAccessADU($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_adu');
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

$idFraccionXIV = getIdVulnerableFraccionXIV($pdo);
$prioridadOptions = aduCatalogoOptions('prioridad', '1', null, false);
$tipoAlertaOptions = aduCatalogoOptions('tipo_alerta', '100', null, false);
$actividadVulnerableCatalogo = $ADU_CATALOGOS['actividad_vulnerable'] ?? [];
$subfraccionesXIVActivas = function_exists('getSubfraccionesXIVActivas') ? getSubfraccionesXIVActivas($pdo, (int)$userId) : [];
if (!empty($subfraccionesXIVActivas)) {
    $actividadVulnerableCatalogo = array_intersect_key($actividadVulnerableCatalogo, array_flip($subfraccionesXIVActivas));
}
$actividadVulnerableOptions = '<option value="">-- Seleccione --</option>';
foreach ($actividadVulnerableCatalogo as $k => $v) {
    $actividadVulnerableOptions .= '<option value="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars((string)$k . ' - ' . (string)$v, ENT_QUOTES, 'UTF-8') . '</option>';
}
$tipoOperacionOptions = aduCatalogoOptions('tipo_operacion', '', null, true);
$instrumentoOptions = aduCatalogoOptions('instrumento_monetario', '', null, true);
$monedaOptions = aduCatalogoOptions('moneda', '', fn($k, $v) => $k . ' - ' . $v, true);
$paisOptions = aduCatalogoOptions('pais', 'MX', fn($k, $v) => $k . ' - ' . $v, false);

$page_title = 'Aviso ADU - Fraccion XIV';
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
                <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-plane-departure me-2"></i>Aviso ADU</h2>
                <p class="text-muted mb-0">Fraccion XIV - Servicios de comercio exterior</p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        </div>

        <?php if (empty($idFraccionXIV)): ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            No se encontro la Fraccion XIV en `cat_vulnerables`.
        </div>
        <?php endif; ?>

        <div class="alert alert-info mb-4">
            <i class="fa-solid fa-circle-info me-1"></i>
            <strong>Regla ADU:</strong> VEH/JYS/TSC/TPP/TDR generan identificacion/aviso siempre;
            MJR desde <strong>485 UMA</strong>; OBA desde <strong>4,815 UMA</strong>.
        </div>

        <form id="form-adu" class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3">
                <h5 class="mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Captura de operacion ADU</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente *</label>
                        <select id="id_cliente" class="form-select" required>
                            <option value="">-- Seleccione cliente --</option>
                        </select>
                        <small id="cliente_info_adu" class="text-muted">Seleccione un cliente.</small>
                    </div>
                    <div class="col-md-3"><label class="form-label">Mes reportado *</label><input id="mes_reportado" class="form-control" maxlength="6" pattern="\d{6}" value="<?= date('Ym') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Clave actividad</label><input class="form-control" value="ADU" readonly></div>
                    <div class="col-md-6"><label class="form-label">Clave sujeto obligado *</label><input id="clave_sujeto_obligado" class="form-control text-uppercase" maxlength="13" value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Referencia aviso *</label><input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" placeholder="ADU20260001" required></div>
                    <div class="col-md-3"><label class="form-label">Exento</label><select id="exento" class="form-select"><option value="0" selected>0 - No</option><option value="1">1 - Si</option></select></div>
                    <div class="col-md-6"><label class="form-label">Clave entidad colegiada</label><input id="clave_entidad_colegiada" class="form-control text-uppercase" maxlength="12"></div>
                    <div class="col-md-3"><label class="form-label">Fecha operacion *</label><input id="fecha_operacion" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">CP operacion *</label><input id="codigo_postal" class="form-control" maxlength="5" inputmode="numeric" required></div>

                    <div class="col-12"><hr><h6 class="fw-bold">Operacion de comercio exterior</h6></div>
                    <div class="col-md-6"><label class="form-label">Actividad vulnerable *</label><select id="actividad_vulnerable" class="form-select" required><?= $actividadVulnerableOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Tipo operacion *</label><select id="tipo_operacion" class="form-select" required><?= $tipoOperacionOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Pedimento</label><input id="pedimento" class="form-control text-uppercase" maxlength="30"></div>
                    <div class="col-md-6"><label class="form-label">Descripcion mercancia</label><input id="descripcion_mercancia" class="form-control text-uppercase" maxlength="3000"></div>
                    <div class="col-md-3"><label class="form-label">Pais origen *</label><select id="pais_origen" class="form-select"><?= $paisOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Pais destino *</label><select id="pais_destino" class="form-select"><?= $paisOptions ?></select></div>

                    <div class="col-12"><hr><h6 class="fw-bold">Liquidacion</h6></div>
                    <div class="col-md-4"><label class="form-label">Instrumento monetario *</label><select id="instrumento_monetario" class="form-select" required><?= $instrumentoOptions ?></select></div>
                    <div class="col-md-4"><label class="form-label">Moneda *</label><select id="moneda" class="form-select" required><?= $monedaOptions ?></select></div>
                    <div class="col-md-4"><label class="form-label">Monto operacion *</label><input id="monto_operacion" type="number" min="0.01" step="0.01" class="form-control" required></div>

                    <div class="col-md-3"><label class="form-label">Prioridad *</label><select id="prioridad" class="form-select" required><?= $prioridadOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Tipo alerta *</label><select id="tipo_alerta" class="form-select" required><?= $tipoAlertaOptions ?></select></div>
                    <div class="col-md-6"><label class="form-label">Descripcion alerta</label><input id="descripcion_alerta" class="form-control text-uppercase" maxlength="3000"></div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-outline-secondary">Limpiar</button>
                <button type="submit" class="btn btn-primary" <?= empty($idFraccionXIV) ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk me-1"></i>Registrar operacion</button>
            </div>
        </form>
    </div>
</div>

<script>
function showMsg(t,x,i){ if(typeof Swal!=='undefined') Swal.fire({title:t,text:x,icon:i}); else alert(t+': '+x); }
function onlyDigits(v){ return (v||'').toString().replace(/\D+/g,''); }
function upper(v){ return (v||'').toString().trim().toUpperCase(); }
function ymd(v){ return (v||'').replaceAll('-',''); }
async function loadClientesADU(){ const s=document.getElementById('id_cliente'); try{ const r=await fetch('api/get_clients.php'); const d=await r.json(); if(d.status!=='success'||!Array.isArray(d.data)) return; d.data.forEach(c=>{ const o=document.createElement('option'); o.value=c.id_cliente; const n=[c.nombre,c.apellido_paterno,c.apellido_materno].filter(Boolean).join(' ').trim(); o.textContent=`${c.id_cliente} - ${n||c.razon_social||'CLIENTE'}${c.rfc?' ('+c.rfc+')':''}`; s.appendChild(o); }); }catch(e){console.error(e);} }
document.getElementById('id_cliente').addEventListener('change', function(){ document.getElementById('cliente_info_adu').textContent=this.options[this.selectedIndex]?.textContent||'Seleccione un cliente.'; });
document.getElementById('form-adu').addEventListener('submit', async function(e){
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
        codigo_postal:onlyDigits(document.getElementById('codigo_postal').value),
        actividad_vulnerable:document.getElementById('actividad_vulnerable').value,
        tipo_operacion:document.getElementById('tipo_operacion').value,
        pedimento:upper(document.getElementById('pedimento').value),
        descripcion_mercancia:upper(document.getElementById('descripcion_mercancia').value),
        pais_origen:document.getElementById('pais_origen').value,
        pais_destino:document.getElementById('pais_destino').value,
        instrumento_monetario:document.getElementById('instrumento_monetario').value,
        moneda:document.getElementById('moneda').value,
        monto_operacion:parseFloat(document.getElementById('monto_operacion').value||'0')
    };
    if(!payload.id_cliente) return showMsg('Validacion','Seleccione un cliente.','warning');
    if(payload.tipo_alerta==='9999'&&!payload.descripcion_alerta) return showMsg('Validacion','Debe capturar descripcion de alerta.','warning');
    const btn=this.querySelector('button[type="submit"]'), old=btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-1"></i>Registrando...';
    try{ const r=await fetch('api/registrar_aviso_adu.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); const d=await r.json(); if(d.status!=='success') throw new Error(d.message||'No fue posible registrar'); showMsg('Registro exitoso', `Operacion #${d.id_operacion||''}${d.id_aviso?' | Aviso #'+d.id_aviso:''}`, 'success'); this.reset(); document.getElementById('mes_reportado').value=new Date().toISOString().slice(0,7).replace('-',''); }catch(err){ showMsg('Error',err.message||'No fue posible registrar','error'); } finally { btn.disabled=false; btn.innerHTML=old; }
});
loadClientesADU();
</script>
</body>
</html>
