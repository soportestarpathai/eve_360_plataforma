<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/pld_fraccion_xii.php';
require_once 'config/fes_catalogos.php';

requireModuleActive($pdo, 'pld');
if (!checkHabilitadoPLD($pdo)) { header('Location: index.php?error=pld_no_habilitado'); exit; }
$userId = $_SESSION['user_id'] ?? 0;
if (!function_exists('userCanAccessFES') || !userCanAccessFES($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_fes'); exit;
}

$clave_tribunal_dependencia = '';
try {
    $row = null;
    if ($userId > 0) {
        $stmtU = $pdo->prepare("SELECT folio_patron_pld FROM config_empresa_usuario WHERE id_usuario = ?");
        $stmtU->execute([$userId]);
        $row = $stmtU->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    if (empty($row['folio_patron_pld'])) $row = $pdo->query("SELECT folio_patron_pld FROM config_empresa WHERE id_config = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $clave_tribunal_dependencia = (string)($row['folio_patron_pld'] ?? '');
} catch (Exception $e) { }

$idFraccionXII = getIdVulnerableFraccionXIIFES($pdo);
$prioridadOptions = fesCatalogoOptions('prioridad', '1', null, false);
$tipoAlertaOptions = fesCatalogoOptions('tipo_alerta', '100', null, false);
$subactividadOptions = fesCatalogoOptions('subactividad', '', null, true);

$page_title = 'Aviso FES - Fraccion XII';
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
                <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-landmark me-2"></i>Aviso FES</h2>
                <p class="text-muted mb-0">Fraccion XII - Fe publica (Servidores Publicos)</p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        </div>
        <?php if (empty($idFraccionXII)): ?>
        <div class="alert alert-warning">No se encontro la Fraccion XII/FES en `cat_vulnerables`.</div>
        <?php endif; ?>
        <div class="alert alert-info mb-4">
            <strong>FES:</strong> captura el bloque <code>tipo_actividad</code> conforme al instructivo de Servidores Publicos.
            Derechos sobre inmuebles avisan desde 8,000 UMA; avaluos desde 8,025 UMA; el resto avisa siempre.
        </div>

        <form id="form-fes" class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Captura FES</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Cliente *</label><select id="id_cliente" class="form-select" required><option value="">-- Seleccione cliente --</option></select><small id="cliente_info_fes" class="text-muted">Seleccione un cliente.</small></div>
                    <div class="col-md-3"><label class="form-label">Mes reportado *</label><input id="mes_reportado" class="form-control" maxlength="6" pattern="\d{6}" value="<?= date('Ym') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Clave actividad</label><input class="form-control" value="FES" readonly></div>
                    <div class="col-md-6"><label class="form-label">Clave tribunal/dependencia *</label><input id="clave_tribunal_dependencia" class="form-control text-uppercase" maxlength="12" value="<?= htmlspecialchars($clave_tribunal_dependencia) ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Referencia aviso *</label><input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" placeholder="FES20260001" required></div>
                    <div class="col-md-3"><label class="form-label">Prioridad *</label><select id="prioridad" class="form-select" required><?= $prioridadOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Tipo alerta *</label><select id="tipo_alerta" class="form-select" required><?= $tipoAlertaOptions ?></select></div>
                    <div class="col-md-6"><label class="form-label">Descripcion alerta</label><input id="descripcion_alerta" class="form-control text-uppercase" maxlength="3000"></div>
                    <div class="col-md-3"><label class="form-label">Fecha operacion *</label><input id="fecha_operacion" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Subfraccion FES *</label><select id="subactividad" class="form-select" required><?= $subactividadOptions ?></select></div>
                    <div class="col-md-3"><label class="form-label">Monto para umbral *</label><input id="monto_operacion" type="number" min="0.01" step="0.01" class="form-control" required></div>

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
const templatesFes = {
  derechos_inmuebles:{derechos_inmuebles:{organo:"ORG JUR",tipo_juicio:"JUICIO",materia:"MATERIA",expediente:"EXPD123",tipo_acto:"9",tipo_acto_otro:"OTRO TIPO DE ACTO",datos_inmuebles:{caracteristicas_inmueble:[{tipo_inmueble:"2",valor_catastral:"159000.00",colonia:"TEZOYUCA",calle:"CALLE",numero_exterior:"500",numero_interior:"A",codigo_postal:"56000",dimension_terreno:"150.00",dimension_construido:"160.00",folio_real:"FOLIO123"},{tipo_inmueble:"4",valor_catastral:"650000.00",colonia:"CUAUHTEMOC",calle:"CALLE",numero_exterior:"100",numero_interior:"1",codigo_postal:"06500",dimension_terreno:"900.00",dimension_construido:"1000.00",folio_real:"FOLI987"}]},personas_acto:{datos_persona_acto:[{caracter:"1",tipo_persona:{persona_fisica:{nombre:"JUAN",apellido_paterno:"PEREZ",apellido_materno:"XXXX",fecha_nacimiento:"19990606",pais_nacionalidad:"GH"}},tipo_domicilio:{nacional:{colonia:"INDUSTRIAL VALLEJO",calle:"CALLE",numero_exterior:"6",codigo_postal:"02300"}},telefono:{numero_telefono:"5400000000"}},{caracter:"9",caracter_otro:"OTRO CARACTER",tipo_persona:{persona_moral:{denominacion_razon:"DENOMINACION",fecha_constitucion:"19930303",pais_nacionalidad:"AL",representante_apoderado:{nombre:"JOSE",apellido_paterno:"GARCIA",apellido_materno:"GARCIA",fecha_nacimiento:"19990606"}}},tipo_domicilio:{extranjero:{pais:"DE",estado_provincia:"ESTADO",ciudad_poblacion:"CIUDAD",colonia:"COLONIA",calle:"CALLE",numero_exterior:"9",codigo_postal:"540000"}},telefono:{numero_telefono:"871200000002"}}]}}},
  otorgamiento_poder:{otorgamiento_poder:{autoridad:{tipo_autoridad:{administrativo:{organo:"ORGANO",cargo:"CARGO",instrumento_publico:"1456000"}},domicilio_oficina:{nacional:{colonia:"CENTRO DE AZCAPOTZALCO",calle:"CALLE",numero_exterior:"41",numero_interior:"D",codigo_postal:"02000"}}},persona_solicita:{nombre:"JORGE",apellido_paterno:"XXXX",apellido_materno:"HERNANDEZ",fecha_nacimiento:"19930303",pais_nacionalidad:"AI"},datos_poderdante:[{tipo_persona:{persona_fisica:{nombre:"PEDRO",apellido_paterno:"PEREZ",apellido_materno:"PEREZ",rfc:"DRFA870604DE3",pais_nacionalidad:"CC"}}},{tipo_persona:{persona_moral:{denominacion_razon:"RAZON SOCIAL",rfc:"DER450512HH6",pais_nacionalidad:"TC"}}}],datos_apoderado:{tipo_poder:"3",tipo_persona:{persona_fisica:{nombre:"LAURA",apellido_paterno:"GONZALEZ",apellido_materno:"XXXX",fecha_nacimiento:"20030303",pais_nacionalidad:"NG"}}}}},
  contrato_mutuo_credito:{contrato_mutuo_credito:{autoridad:{tipo_autoridad:{administrativo:{organo:"ORGADMIN 1",cargo:"CAGO AUT",instrumento_publico:"INSTPUB123"}},domicilio_oficina:{nacional:{colonia:"CENTRO",calle:"CALLE",numero_exterior:"500",numero_interior:"B",codigo_postal:"59000"}}},tipo_otorgamiento:"2",persona_solicita:{nombre:"FERNANDO",apellido_paterno:"XXXX",apellido_materno:"PEREX",rfc:"DREF890505DE3",pais_nacionalidad:"GG"},datos_acreedor:[{tipo_persona:{persona_fisica:{nombre:"PABLO",apellido_paterno:"BELTRAN",apellido_materno:"XXXX",fecha_nacimiento:"19930606",pais_nacionalidad:"IL"}}},{tipo_persona:{persona_moral:{denominacion_razon:"EMPRESA",fecha_constitucion:"20101010",pais_nacionalidad:"DE"}}},{tipo_persona:{fideicomiso:{denominacion_razon:"EMRESA SA",rfc:"FRD890505DER"}}}],datos_deudor:[{tipo_persona:{fideicomiso:{denominacion_razon:"RAZON SOCIAL",rfc:"HJT870404DE1"}}},{tipo_persona:{persona_moral:{denominacion_razon:"PERSONA MORAL",fecha_constitucion:"20031006",pais_nacionalidad:"NP"}}},{tipo_persona:{persona_fisica:{nombre:"JORGE",apellido_paterno:"GONZALEZ",apellido_materno:"XXXX",fecha_nacimiento:"19200606",pais_nacionalidad:"BG"}}}],datos_garantia:[{tipo_garantia:"2",datos_bien_garantia:{datos_inmueble:{tipo_inmueble:"2",valor_referencia:"1800000.00",codigo_postal:"74000",folio_real:"56456456XC"}},tipo_persona:{persona_fisica:{nombre:"LORENA",apellido_paterno:"GALLARDO",apellido_materno:"XXXX",rfc:"ERDF780505DE3"}}},{tipo_garantia:"99",datos_bien_garantia:{datos_otro:{descripcion_garantia:"DESCRIPCION GARANTIA"}}},{tipo_garantia:"3",tipo_persona:{persona_moral:{denominacion_razon:"EMPRESA",rfc:"WED65020312D"}}}],datos_liquidacion:[{moneda:"2",monto_operacion:"195000.00"},{moneda:"1",monto_operacion:"29000000.00"}]}},
  avaluo:{avaluo:{organo:"ORGADMIN1",cargo:"CARGO AUTORIDAD",expediente_oficio:"OFIC. 1 / 2015",persona_solicita:{nombre:"JORGE",apellido_paterno:"XXXX",apellido_materno:"MARTINEZ",fecha_nacimiento:"19910101",pais_nacionalidad:"AO"},tipo_bien:"1",valor_avaluo:"1800000.00",datos_propietario:{propietario_solicita:"NO",dato_propietario:[{tipo_persona:{persona_moral:{denominacion_razon:"RAZON SOCIAL S.A. DE C.V.",fecha_constitucion:"19990101",pais_nacionalidad:"AO"}}},{tipo_persona:{fideicomiso:{denominacion_razon:"DENOMINACION",rfc:"WER780505DR5"}}},{tipo_persona:{persona_fisica:{nombre:"BAETRIZ",apellido_paterno:"HERNANDEZ",apellido_materno:"XXXXXX",curp:"DFRE890505HAGDRL09",pais_nacionalidad:"PH"}}}]}}},
  constitucion_personas_morales:{constitucion_personas_morales:{autoridad:{tipo_autoridad:{administrativo:{organo:"ORG ADMIN",cargo:"CARGO AUT",instrumento_publico_oficio:"INST PUB"}},domicilio_oficina:{nacional:{colonia:"EL PIPILA INFONAVIT",calle:"CALLE",numero_exterior:"1000",codigo_postal:"58000"}}},persona_solicita:{nombre:"JOSE",apellido_paterno:"TORRES",apellido_materno:"PEREZ",fecha_nacimiento:"19980808",pais_nacionalidad:"ES"},persona_moral_constitucion:{tipo_persona_moral:"99",tipo_persona_moral_otra:"OTRO TIPO PERSONA MORAL",denominacion_razon:"RAZON SOCIAL",giro_mercantil:"1000000",folio_mercantil:"FOLIOOO",numero_total_acciones:"15.00",entidad_federativa:"4",consejo_vigilancia:"SI",motivo_constitucion:"1",instrumento_publico:"INSTPUB12456",datos_accionista:[{cargo_accionista:"1",tipo_persona:{persona_fisica:{nombre:"MARTIN",apellido_paterno:"SALAS",apellido_materno:"XXXX",fecha_nacimiento:"19960505",pais_nacionalidad:"SB"}},numero_acciones:"85.00"},{cargo_accionista:"2",tipo_persona:{persona_moral:{denominacion_razon:"DENOMINACION SA",fecha_constitucion:"19880808",pais_nacionalidad:"AD"}},numero_acciones:"51.00"},{cargo_accionista:"3",tipo_persona:{fideicomiso:{denominacion_razon:"RAZON SA",rfc:"DRE850404DE3"}},numero_acciones:"67.00"}],capital_social:{capital_fijo:"18700000.00",capital_variable:"17000.00"}}}},
  modificacion_patrimonial:{modificacion_patrimonial:{autoridad:{tipo_autoridad:{jurisdiccional:{organo:"ORG JUR 1",tipo_juicio:"JUICIO 1",materia:"MATERIA 1",expediente:"EXP. 1"}}},persona_moral_modifica:{denominacion_razon:"DENOMINACION",fecha_constitucion:"20090909",pais_nacionalidad:"KY",giro_mercantil:"4850007",numero_total_acciones:"855.00",motivo_modificacion:"3"},datos_modificacion:{tipo_modificacion_capital_fijo:"1",inicial_capital_fijo:"18000.00",final_capital_fijo:"20000.00",tipo_modificacion_capital_variable:"3",inicial_capital_variable:"25000.00",final_capital_variable:"25000.00",datos_accionista:[{tipo_persona:{persona_fisica:{nombre:"JOSE",apellido_paterno:"HERNANDEZ",apellido_materno:"XXXX",fecha_nacimiento:"19990505",pais_nacionalidad:"GP"}},numero_acciones:"40.00"},{tipo_persona:{persona_moral:{denominacion_razon:"RAZON SOCIAL X",rfc:"RFE8905054ER",pais_nacionalidad:"JP"}},numero_acciones:"50.00"},{tipo_persona:{fideicomiso:{denominacion_razon:"RAZON SOCIAL S.A. DE C.V.",identificador_fideicomiso:"IDENT.FID."}},numero_acciones:"60.00"}]}}}
};
function refreshTemplate(){ const k=document.getElementById('subactividad').value; if(k&&templatesFes[k]) document.getElementById('tipo_actividad_json').value=JSON.stringify(templatesFes[k],null,2); }
async function loadClientesFES(){ const s=document.getElementById('id_cliente'); try{ const r=await fetch('api/get_clients.php'); const d=await r.json(); if(d.status!=='success'||!Array.isArray(d.data)) return; d.data.forEach(c=>{ const o=document.createElement('option'); o.value=c.id_cliente; const n=[c.nombre,c.apellido_paterno,c.apellido_materno].filter(Boolean).join(' ').trim(); o.textContent=`${c.id_cliente} - ${n||c.razon_social||'CLIENTE'}${c.rfc?' ('+c.rfc+')':''}`; s.appendChild(o); }); }catch(e){console.error(e);} }
document.getElementById('id_cliente').addEventListener('change', function(){ document.getElementById('cliente_info_fes').textContent=this.options[this.selectedIndex]?.textContent||'Seleccione un cliente.'; });
document.getElementById('subactividad').addEventListener('change', refreshTemplate);
document.getElementById('form-fes').addEventListener('submit', async function(e){
    e.preventDefault();
    let tipoActividad; try{ tipoActividad=JSON.parse(document.getElementById('tipo_actividad_json').value||'{}'); }catch(err){ return showMsg('Validacion','El JSON tipo_actividad no es valido.','warning'); }
    const payload={
        id_cliente:parseInt(document.getElementById('id_cliente').value||'0',10),
        mes_reportado:onlyDigits(document.getElementById('mes_reportado').value),
        clave_tribunal_dependencia:upper(document.getElementById('clave_tribunal_dependencia').value),
        referencia_aviso:upper(document.getElementById('referencia_aviso').value),
        prioridad:document.getElementById('prioridad').value,
        tipo_alerta:document.getElementById('tipo_alerta').value,
        descripcion_alerta:upper(document.getElementById('descripcion_alerta').value),
        fecha_operacion:ymd(document.getElementById('fecha_operacion').value),
        subactividad:document.getElementById('subactividad').value,
        monto_operacion:parseFloat(document.getElementById('monto_operacion').value||'0'),
        tipo_actividad:tipoActividad
    };
    if(!payload.id_cliente) return showMsg('Validacion','Seleccione un cliente.','warning');
    if(!payload.subactividad || !payload.tipo_actividad[payload.subactividad]) return showMsg('Validacion','El JSON debe coincidir con la subfraccion seleccionada.','warning');
    const btn=this.querySelector('button[type="submit"]'), old=btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-1"></i>Registrando...';
    try{ const r=await fetch('api/registrar_aviso_fes.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}); const d=await r.json(); if(d.status!=='success') throw new Error(d.message||'No fue posible registrar'); showMsg('Registro exitoso',`Operacion #${d.id_operacion||''}${d.id_aviso?' | Aviso #'+d.id_aviso:''}`,'success'); }catch(err){ showMsg('Error',err.message||'No fue posible registrar','error'); } finally { btn.disabled=false; btn.innerHTML=old; }
});
loadClientesFES();
</script>
</body>
</html>
