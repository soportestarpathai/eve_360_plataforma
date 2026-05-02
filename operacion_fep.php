<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/pld_fraccion_xii.php';
require_once 'config/fep_catalogos.php';

requireModuleActive($pdo, 'pld');
if (!checkHabilitadoPLD($pdo)) { header('Location: index.php?error=pld_no_habilitado'); exit; }
$userId = $_SESSION['user_id'] ?? 0;
if (!function_exists('userCanAccessFEP') || !userCanAccessFEP($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_fep'); exit;
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

$idFraccionXII = getIdVulnerableFraccionXIIFEP($pdo);
$prioridadOptions = fepCatalogoOptions('prioridad', '1', null, false);
$tipoAlertaOptions = fepCatalogoOptions('tipo_alerta', '100', null, false);
$subactividadOptions = fepCatalogoOptions('subactividad', '', null, true);

$page_title = 'Aviso FEP - Fraccion XII';
include 'templates/header.php';
?>
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/operaciones_pld.css">
</head>
<body>
<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>
<div class="content-wrapper">
    <div class="container-fluid" style="max-width:1120px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-scale-balanced me-2"></i>Aviso FEP</h2>
                <p class="text-muted mb-0">Fraccion XII - Fe publica (Notarios y Corredores Publicos)</p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        </div>
        <?php if (empty($idFraccionXII)): ?>
        <div class="alert alert-warning">No se encontro la Fraccion XII/FEP en `cat_vulnerables`.</div>
        <?php endif; ?>
        <div class="alert alert-info mb-4">
            <strong>FEP:</strong> las subfracciones capturan el bloque <code>tipo_actividad</code> conforme al XML oficial.
            Fideicomisos avisan desde 4,000 UMA; avaluos desde 8,025 UMA; el resto avisa siempre.
        </div>

        <form id="form-fep" class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Captura FEP</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Cliente *</label><select id="id_cliente" class="form-select" required><option value="">-- Seleccione cliente --</option></select><small id="cliente_info_fep" class="text-muted">Seleccione un cliente.</small></div>
                    <div class="col-md-3"><label class="form-label">Mes reportado *</label><input id="mes_reportado" class="form-control" maxlength="6" pattern="\d{6}" value="<?= date('Ym') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Clave actividad</label><input class="form-control" value="FEP" readonly></div>
                    <div class="col-md-6"><label class="form-label">Clave sujeto obligado *</label><input id="clave_sujeto_obligado" class="form-control text-uppercase" maxlength="13" value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Referencia aviso *</label><input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" placeholder="FEP20260001" required></div>
                    <div class="col-md-3"><label class="form-label">Clave entidad colegiada</label><input id="clave_entidad_colegiada" class="form-control text-uppercase" maxlength="12"></div>
                    <div class="col-md-3"><label class="form-label">Prioridad *</label><select id="prioridad" class="form-select" required><?= $prioridadOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Tipo alerta *</label><select id="tipo_alerta" class="form-select" required><?= $tipoAlertaOptions ?></select></div>
                    <div class="col-md-6"><label class="form-label">Descripcion alerta</label><input id="descripcion_alerta" class="form-control text-uppercase" maxlength="3000"></div>
                    <div class="col-md-3"><label class="form-label">Fecha operacion *</label><input id="fecha_operacion" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Instrumento publico *</label><input id="instrumento_publico" class="form-control text-uppercase" maxlength="20" required></div>
                    <div class="col-md-6"><label class="form-label">Subfraccion FEP *</label><select id="subactividad" class="form-select" required><?= $subactividadOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Monto para umbral *</label><input id="monto_operacion" type="number" min="0.01" step="0.01" class="form-control" required></div>

                    <div class="col-12"><hr><h6 class="fw-bold">Persona aviso</h6></div>
                    <div class="col-md-3"><label class="form-label">Nombre *</label><input id="pa_nombre" class="form-control text-uppercase" required></div>
                    <div class="col-md-3"><label class="form-label">Apellido paterno *</label><input id="pa_apellido_paterno" class="form-control text-uppercase" required></div>
                    <div class="col-md-3"><label class="form-label">Apellido materno *</label><input id="pa_apellido_materno" class="form-control text-uppercase" required></div>
                    <div class="col-md-3"><label class="form-label">Fecha nacimiento *</label><input id="pa_fecha_nacimiento" type="date" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">RFC</label><input id="pa_rfc" class="form-control text-uppercase" maxlength="13"></div>
                    <div class="col-md-3"><label class="form-label">CURP</label><input id="pa_curp" class="form-control text-uppercase" maxlength="18"></div>

                    <div class="col-12"><hr><h6 class="fw-bold">Bloque tipo_actividad</h6></div>
                    <div class="col-12">
                        <textarea id="tipo_actividad_json" class="form-control font-monospace" rows="18" required></textarea>
                        <small class="text-muted">El JSON debe tener como llave principal la subfraccion seleccionada. Ejemplo: <code>{"avaluo":{...}}</code></small>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2"><button type="reset" class="btn btn-outline-secondary">Limpiar</button><button type="submit" class="btn btn-primary" <?= empty($idFraccionXII) ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk me-1"></i>Registrar operacion</button></div>
        </form>
    </div>
</div>
<script>
function showMsg(t,x,i){ if(typeof Swal!=='undefined') Swal.fire({title:t,text:x,icon:i}); else alert(t+': '+x); }
function onlyDigits(v){ return (v||'').toString().replace(/\D+/g,''); }
function upper(v){ return (v||'').toString().trim().toUpperCase(); }
function ymd(v){ return (v||'').replaceAll('-',''); }
const templatesFep = {
  otorgamiento_poder:{otorgamiento_poder:{datos_poderdante:{tipo_persona:{persona_moral:{denominacion_razon:"AZTECA",fecha_constitucion:"19800202",pais_nacionalidad:"MX",giro_mercantil:"5313110"}}},datos_apoderado:[{tipo_poder:"2",tipo_persona:{persona_fisica:{nombre:"ANDREA",apellido_paterno:"ROBLES",apellido_materno:"GONZALES",fecha_nacimiento:"19921120",pais_nacionalidad:"MX"}}}]}},
  constitucion_personas_morales:{constitucion_personas_morales:{tipo_persona_moral:"2",denominacion_razon:"LAAZTECA",giro_mercantil:"4640000",folio_mercantil:"231GH",numero_total_acciones:"12334.00",entidad_federativa:"07",consejo_vigilancia:"NO",motivo_constitucion:"3",instrumento_publico:"12549655",datos_accionista:[{cargo_accionista:"1",tipo_persona:{persona_fisica:{nombre:"MARIBEL",apellido_paterno:"FLORES",apellido_materno:"RIO",fecha_nacimiento:"19770317",pais_nacionalidad:"MX"}},numero_acciones:"30.00"}],capital_social:{capital_fijo:"12810.00",capital_variable:"1541800.44"}}},
  modificacion_patrimonial:{modificacion_patrimonial:{persona_moral_modifica:{denominacion_razon:"CHOCOLATEAZTECA",fecha_constitucion:"20000811",rfc:"CHO000811KJL",pais_nacionalidad:"MX",giro_mercantil:"5231100",numero_total_acciones:"12334.55",motivo_modificacion:"3",instrumento_publico:"4500"},datos_modificacion:{tipo_modificacion_capital_fijo:"2",inicial_capital_fijo:"12300.55",final_capital_fijo:"11560.25",tipo_modificacion_capital_variable:"2",inicial_capital_variable:"10056.00",final_capital_variable:"100567.34",datos_accionista:[{tipo_persona:{persona_fisica:{nombre:"ALMA",apellido_paterno:"SALAS",apellido_materno:"VILLA",fecha_nacimiento:"19771015",pais_nacionalidad:"MX"}},numero_acciones:"50.00"}]}}},
  fusion:{fusion:{tipo_fusion:"1",datos_fusionadas:{datos_fusionada:[{denominacion_razon:"SAISOLUCIONES",fecha_constitucion:"20001102",rfc:"SAI001102HJK",pais_nacionalidad:"MX",giro_mercantil:"5222200",capital_social_fijo:"2200.00",capital_social_variable:"1250033.00",folio_mercantil:"718183FG"}]},datos_fusionante:{fusionante_determinadas:"SI",fusionante:{denominacion_razon:"AVANTE",fecha_constitucion:"19780713",rfc:"AVA780713IIO",pais_nacionalidad:"MX",giro_mercantil:"5411100",capital_social_fijo:"12000036.00",capital_social_variable:"566000333.00",folio_mercantil:"YUT678",numero_total_acciones:"1236685.00",datos_accionista:[{tipo_persona:{persona_fisica:{nombre:"ANTONIO",apellido_paterno:"SALAZAR",apellido_materno:"PRADO",fecha_nacimiento:"19800309",pais_nacionalidad:"MX"}},numero_acciones:"20336900.00"}]}}}},
  escision:{escision:{datos_escindente:{denominacion_razon:"AVANTE",fecha_constitucion:"19780713",rfc:"AVA780713IIO",pais_nacionalidad:"MX",giro_mercantil:"5411100",capital_social_fijo:"12000036.00",capital_social_variable:"566000333.00",folio_mercantil:"YUT678",escindente_subsiste:"SI"},datos_escindidas:{escindidas_determinadas:"SI",dato_escindida:[{denominacion_razon:"UFIO",fecha_constitucion:"19900201",rfc:"UFI900201KKK",pais_nacionalidad:"MX",giro_mercantil:"4810000",capital_social_fijo:"1200365.00",capital_social_variable:"23555066.00",folio_mercantil:"RUTU8473",numero_total_acciones:"21.00"}]}}},
  compra_venta_acciones:{compra_venta_acciones:{tipo_operacion:"1",persona_moral_acciones:{denominacion_razon:"PEDRO",fecha_constitucion:"18000101",pais_nacionalidad:"AL",valor_nominal:"112223215158.00",numero_acciones:"45.00",datos_vendedor:[{numero_acciones_vendidas:"45.00",tipo_persona:{persona_moral:{denominacion_razon:"AZTECA",fecha_constitucion:"19800202",pais_nacionalidad:"MX"}}}],datos_comprador:[{numero_acciones_compradas:"40.00",tipo_persona:{persona_fisica:{nombre:"ANDREA",apellido_paterno:"ROBLES",apellido_materno:"GONZALES",fecha_nacimiento:"19921120",pais_nacionalidad:"MX"}}}]},datos_liquidacion:{fecha_pago:"20140701",instrumento_monetario:"1",moneda:"1",monto_operacion:"1800000.12"}}},
  constitucion_modificacion_fideicomiso:{constitucion_modificacion_fideicomiso:{tipo_movimiento:"4",tipo_fideicomiso:"1",descripcion:"DESCRIPCION CONSTITUCION MODIFICACION FIDEICOMISO",identificador_fideicomiso:"1A",denominacion_razon:"FID",monto_patrimonio:"5900000.40",datos_fideicomitente:[{tipo_movimiento_fideicomitente:"1",tipo_persona:{persona_fisica:{nombre:"C",apellido_paterno:"C",apellido_materno:"C",fecha_nacimiento:"19901212",pais_nacionalidad:"AF",actividad_economica:"0300004"}},datos_tipo_patrimonio:[{patrimonio_monetario:{moneda:"1",monto_operacion:"10000000.00"}}]}],datos_fideicomisarios:{datos_fideicomisarios_determinados:"NO"},datos_miembro_comite_tecnico:{comite_tecnico:"SI",modificacion_comite_tecnico:"NO"}}},
  cesion_derechos_fideicomitente_fideicomisario:{cesion_derechos_fideicomitente_fideicomisario:{identificador_fideicomiso:"44564",rfc:"ROS7805225M4",denominacion_razon:"ASDASDASDASDD",tipo_cesion:"1",datos_cedente:{tipo_persona:{persona_fisica:{nombre:"C",apellido_paterno:"C",apellido_materno:"C",fecha_nacimiento:"19901212",pais_nacionalidad:"AF",actividad_economica:"0300004"}}},datos_cesionario:{tipo_persona:{persona_fisica:{nombre:"C",apellido_paterno:"C",apellido_materno:"C",fecha_nacimiento:"19981212",pais_nacionalidad:"AD",actividad_economica:"1300003"}}},datos_cesion:{monto_cesion:"115621261.00"}}},
  contrato_mutuo_credito:{contrato_mutuo_credito:{tipo_otorgamiento:"2",datos_acreedor:{tipo_persona:{persona_fisica:{nombre:"C",apellido_paterno:"C",apellido_materno:"C",fecha_nacimiento:"19801212",pais_nacionalidad:"AQ",actividad_economica:"1300003"}}},datos_deudor:{tipo_persona:{persona_fisica:{nombre:"C",apellido_paterno:"C",apellido_materno:"C",fecha_nacimiento:"19801212",pais_nacionalidad:"AQ",actividad_economica:"1300003"}}},datos_garantia:[{tipo_garantia:"2",datos_bien_mutuo:{datos_inmueble:{tipo_inmueble:"1",valor_referencia:"1000000.00",codigo_postal:"06000",folio_real:"1111111"}},tipo_persona:{persona_fisica:{nombre:"C",apellido_paterno:"C",apellido_materno:"C",fecha_nacimiento:"19801212"}}}],datos_liquidacion:{moneda:"1",monto_operacion:"10000000.00"}}},
  avaluo:{avaluo:{tipo_bien:"1",valor_avaluo:"580000.98",datos_propietario:{propietario_solicita:"NO",tipo_persona:{persona_fisica:{nombre:"MAR",apellido_paterno:"CAS",apellido_materno:"OLI",fecha_nacimiento:"19820903",pais_nacionalidad:"AF"}}}}}
};
function refreshTemplate(){ const k=document.getElementById('subactividad').value; if(k&&templatesFep[k]) document.getElementById('tipo_actividad_json').value=JSON.stringify(templatesFep[k],null,2); }
async function loadClientesFEP(){ const s=document.getElementById('id_cliente'); try{ const r=await fetch('api/get_clients.php'); const d=await r.json(); if(d.status!=='success'||!Array.isArray(d.data)) return; d.data.forEach(c=>{ const o=document.createElement('option'); o.value=c.id_cliente; const n=[c.nombre,c.apellido_paterno,c.apellido_materno].filter(Boolean).join(' ').trim(); o.textContent=`${c.id_cliente} - ${n||c.razon_social||'CLIENTE'}${c.rfc?' ('+c.rfc+')':''}`; s.appendChild(o); }); }catch(e){console.error(e);} }
document.getElementById('id_cliente').addEventListener('change', function(){ document.getElementById('cliente_info_fep').textContent=this.options[this.selectedIndex]?.textContent||'Seleccione un cliente.'; });
document.getElementById('subactividad').addEventListener('change', refreshTemplate);
document.getElementById('form-fep').addEventListener('submit', async function(e){
    e.preventDefault();
    let tipoActividad; try{ tipoActividad=JSON.parse(document.getElementById('tipo_actividad_json').value||'{}'); }catch(err){ return showMsg('Validacion','El JSON tipo_actividad no es valido.','warning'); }
    const payload={
        id_cliente:parseInt(document.getElementById('id_cliente').value||'0',10),
        mes_reportado:onlyDigits(document.getElementById('mes_reportado').value),
        clave_sujeto_obligado:upper(document.getElementById('clave_sujeto_obligado').value),
        clave_entidad_colegiada:upper(document.getElementById('clave_entidad_colegiada').value),
        referencia_aviso:upper(document.getElementById('referencia_aviso').value),
        prioridad:document.getElementById('prioridad').value,
        tipo_alerta:document.getElementById('tipo_alerta').value,
        descripcion_alerta:upper(document.getElementById('descripcion_alerta').value),
        fecha_operacion:ymd(document.getElementById('fecha_operacion').value),
        instrumento_publico:upper(document.getElementById('instrumento_publico').value),
        subactividad:document.getElementById('subactividad').value,
        monto_operacion:parseFloat(document.getElementById('monto_operacion').value||'0'),
        persona_aviso:{nombre:upper(document.getElementById('pa_nombre').value),apellido_paterno:upper(document.getElementById('pa_apellido_paterno').value),apellido_materno:upper(document.getElementById('pa_apellido_materno').value),fecha_nacimiento:ymd(document.getElementById('pa_fecha_nacimiento').value),rfc:upper(document.getElementById('pa_rfc').value),curp:upper(document.getElementById('pa_curp').value)},
        tipo_actividad:tipoActividad
    };
    if(!payload.id_cliente) return showMsg('Validacion','Seleccione un cliente.','warning');
    if(!payload.subactividad || !payload.tipo_actividad[payload.subactividad]) return showMsg('Validacion','El JSON debe coincidir con la subfraccion seleccionada.','warning');
    const btn=this.querySelector('button[type="submit"]'), old=btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-1"></i>Registrando...';
    try{ const r=await fetch('api/registrar_aviso_fep.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); const d=await r.json(); if(d.status!=='success') throw new Error(d.message||'No fue posible registrar'); showMsg('Registro exitoso',`Operacion #${d.id_operacion||''}${d.id_aviso?' | Aviso #'+d.id_aviso:''}`,'success'); }catch(err){ showMsg('Error',err.message||'No fue posible registrar','error'); } finally { btn.disabled=false; btn.innerHTML=old; }
});
loadClientesFEP();
</script>
</body>
</html>
