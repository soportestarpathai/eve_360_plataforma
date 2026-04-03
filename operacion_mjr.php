<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/mjr_catalogos.php';

requireModuleActive($pdo, 'pld');
if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!function_exists('userCanAccessMJR') || !userCanAccessMJR($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_mjr');
    exit;
}

$page_title = 'Aviso MJR - Fraccion VI';
$claveSO = '';
try {
    $row = [];
    if ($userId > 0) {
        $stmtU = $pdo->prepare("SELECT folio_patron_pld FROM config_empresa_usuario WHERE id_usuario = ?");
        $stmtU->execute([$userId]);
        $row = $stmtU->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    if (empty($row['folio_patron_pld'])) {
        $row = $pdo->query("SELECT folio_patron_pld FROM config_empresa WHERE id_config = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $claveSO = (string)($row['folio_patron_pld'] ?? '');
} catch (Throwable $e) {
    $claveSO = '';
}

include 'templates/header.php';
?>
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/operaciones_pld.css">
<style>
:root{
  --veh-primary:#2563eb;
  --veh-primary-dark:#1d4ed8;
  --veh-info:#0ea5e9;
  --veh-warning:#d97706;
  --veh-success:#059669;
  --veh-dark:#1e3a8a;
  --veh-light:#eff6ff;
  --veh-shadow:0 4px 24px rgba(0,0,0,.06);
  --veh-radius:16px;
  --veh-radius-sm:10px;
  --veh-transition:.25s cubic-bezier(.4,0,.2,1);
  --veh-max-width:1080px;
}
.veh-wrapper{max-width:var(--veh-max-width);margin:0 auto}
.veh-page-header{background:linear-gradient(135deg,var(--veh-primary),var(--veh-primary-dark));color:#fff;border-radius:var(--veh-radius);padding:1.75rem 2rem;margin-bottom:1.75rem}
.veh-page-header h2{font-size:1.5rem;font-weight:800;margin-bottom:.25rem}
.veh-page-header p{opacity:.9;margin:0}
.veh-page-header .btn-outline-light{border:1.5px solid rgba(255,255,255,.55);color:#fff}
.veh-page-header .btn-outline-light:hover{background:rgba(255,255,255,.15);border-color:#fff}
.veh-progress{display:flex;gap:0;margin-bottom:1.8rem;overflow-x:auto;padding-bottom:4px}
.veh-step{flex:1;min-width:100px;text-align:center;position:relative;padding:.75rem .5rem;font-size:.78rem;font-weight:600;color:#94a3b8;transition:var(--veh-transition)}
.veh-step::after{content:'';position:absolute;bottom:0;left:0;width:100%;height:3px;background:#e2e8f0;border-radius:3px;transition:var(--veh-transition)}
.veh-step.active{color:var(--veh-primary)}
.veh-step.active::after{background:var(--veh-primary)}
.veh-step-num{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-size:.75rem;font-weight:700;background:#e2e8f0;color:#64748b;margin-bottom:4px;transition:var(--veh-transition)}
.veh-step.active .veh-step-num{background:var(--veh-primary);color:#fff}
.veh-card{border:none;border-radius:var(--veh-radius);background:#fff;box-shadow:0 4px 24px rgba(0,0,0,.06);margin-bottom:1.5rem;overflow:hidden;transition:var(--veh-transition)}
.veh-card:hover{box-shadow:0 8px 32px rgba(0,0,0,.09)}
.veh-card-header{padding:1rem 1.5rem;display:flex;align-items:center;gap:.75rem;cursor:pointer;user-select:none;border-bottom:1px solid transparent;transition:var(--veh-transition)}
.veh-card-header:hover{background:rgba(0,0,0,.015)}
.veh-card-body{padding:1.25rem 1.5rem}
.veh-icon{width:40px;height:40px;border-radius:var(--veh-radius-sm);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;flex-shrink:0}
.veh-icon-cliente{background:linear-gradient(135deg,var(--veh-primary),var(--veh-primary-dark))}
.veh-icon-aviso{background:linear-gradient(135deg,var(--veh-warning),#b45309)}
.veh-icon-persona{background:linear-gradient(135deg,var(--veh-success),#047857)}
.veh-icon-benef{background:linear-gradient(135deg,var(--veh-info),#0369a1)}
.veh-icon-detalle{background:linear-gradient(135deg,#7c3aed,#5b21b6)}
.veh-card-header h5{margin:0;font-size:1rem;font-weight:700;color:var(--veh-dark)}
.veh-card-header small{color:#94a3b8;font-size:.78rem;font-weight:400;display:block}
.veh-chevron{margin-left:auto;font-size:.85rem;color:#94a3b8;transition:var(--veh-transition)}
.veh-card-header.collapsed .veh-chevron{transform:rotate(-90deg)}
.veh-subcard{border-left:3px solid var(--veh-primary);background:var(--veh-light);border-radius:0 var(--veh-radius-sm) var(--veh-radius-sm) 0;padding:1rem 1rem .25rem;margin-top:1rem}
.veh-submit-bar{position:sticky;bottom:0;background:#fff;padding:1rem 1.5rem;border-top:1px solid #e2e8f0;border-radius:var(--veh-radius) var(--veh-radius) 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.06);z-index:10;display:flex;justify-content:flex-end;align-items:center;gap:.75rem;flex-wrap:wrap}
.veh-submit-bar .btn-primary{background:linear-gradient(135deg,var(--veh-primary),var(--veh-primary-dark));border:none;padding:.7rem 1.6rem;font-weight:700;border-radius:var(--veh-radius-sm)}
.veh-submit-bar .btn-primary:hover{transform:translateY(-1px)}
.veh-hidden{display:none!important}
.veh-note{font-size:.8rem;color:#64748b}
.veh-item{border:1px solid #dbeafe;background:#eff6ff;border-radius:10px;padding:10px 12px;margin-bottom:8px}
.veh-card .form-control::placeholder,.veh-card .form-select::placeholder{color:#94a3b8;opacity:1}
</style>
</head>
<body>
<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="content-wrapper">
<div class="veh-wrapper">
  <div class="veh-page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div>
        <h2><i class="fa-solid fa-gem me-2"></i>Aviso MJR</h2>
        <p>Fraccion VI - Metales preciosos, piedras preciosas, joyas y relojes</p>
      </div>
      <a class="btn btn-outline-light btn-sm" href="operaciones_pld.php"><i class="fa-solid fa-arrow-left me-1"></i> Volver</a>
    </div>
  </div>

  <div class="veh-progress" aria-label="Progreso MJR">
    <div class="veh-step active"><div class="veh-step-num">1</div><div>Cliente</div></div>
    <div class="veh-step active"><div class="veh-step-num">2</div><div>Aviso</div></div>
    <div class="veh-step active"><div class="veh-step-num">3</div><div>Persona</div></div>
    <div class="veh-step active"><div class="veh-step-num">4</div><div>Beneficiario</div></div>
    <div class="veh-step active"><div class="veh-step-num">5</div><div>Detalle MJR</div></div>
  </div>

  <div class="alert alert-info">Umbral de identificacion: <code>805 UMA</code>. Umbral de aviso/acumulacion: <code>1,605 UMA</code>.</div>

<form id="formMJR" novalidate>
  <div class="veh-card" id="sec-cliente">
    <div class="veh-card-header" onclick="toggleVehCard(this)">
      <div class="veh-icon veh-icon-cliente"><i class="fa-solid fa-user"></i></div>
      <div><h5>Cliente e informe</h5><small>Datos generales del sujeto obligado</small></div>
      <i class="fa-solid fa-chevron-down veh-chevron"></i>
    </div>
    <div class="veh-card-body">
      <div class="row g-3">
        <div class="col-xl-5 col-lg-6 col-md-12"><label class="form-label">Cliente *</label><select id="id_cliente" class="form-select" required><option value="">-- Seleccione cliente --</option></select></div>
        <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Mes reportado *</label><input id="mes_reportado" class="form-control" maxlength="6" value="<?= date('Ym') ?>" placeholder="202603" inputmode="numeric" required></div>
        <div class="col-xl-3 col-lg-5 col-md-8"><label class="form-label">Clave sujeto obligado *</label><input id="clave_sujeto_obligado" class="form-control text-uppercase" maxlength="13" value="<?= htmlspecialchars($claveSO) ?>" placeholder="ABCD900101XY1" required></div>
        <div class="col-xl-2 col-lg-2 col-md-4"><label class="form-label">Actividad</label><input class="form-control" value="MJR" readonly></div>
        <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Exento</label><select id="exento" class="form-select"><?= mjrCatalogoOptions('exento', '0', null, false) ?></select></div>
        <div class="col-xl-4 col-lg-5 col-md-8"><label class="form-label">Clave entidad colegiada</label><input id="clave_entidad_colegiada" class="form-control text-uppercase" maxlength="12" placeholder="ABC900101A1B"></div>
      </div>
      <div id="kyc_info" class="veh-note mt-2"></div>
    </div>
  </div>

  <div class="veh-card" id="sec-aviso">
    <div class="veh-card-header" onclick="toggleVehCard(this)">
      <div class="veh-icon veh-icon-aviso"><i class="fa-solid fa-bell"></i></div>
      <div><h5>Datos del aviso</h5><small>Referencia, prioridad y alerta</small></div>
      <i class="fa-solid fa-chevron-down veh-chevron"></i>
    </div>
    <div class="veh-card-body">
      <div class="row g-3">
        <div class="col-lg-3 col-md-6"><label class="form-label">Referencia aviso *</label><input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" placeholder="REF20260001" required></div>
        <div class="col-lg-2 col-md-3"><label class="form-label">Prioridad *</label><select id="prioridad" class="form-select" required><?= mjrCatalogoOptions('prioridad', '1', null, false) ?></select></div>
        <div class="col-lg-3 col-md-6"><label class="form-label">Tipo alerta *</label><input id="tipo_alerta" class="form-control" maxlength="4" inputmode="numeric" placeholder="602" value="100" required></div>
        <div class="col-lg-4 col-md-12"><label class="form-label">Descripcion alerta</label><input id="descripcion_alerta" class="form-control text-uppercase" maxlength="3000" placeholder="Ej. OPERACION INUSUAL EN COMERCIALIZACION DE JOYAS"></div>
      </div>
      <div class="form-check mt-3"><input id="es_modificatorio" class="form-check-input" type="checkbox"><label class="form-check-label" for="es_modificatorio">Aviso modificatorio</label></div>
      <div id="modif_wrap" class="row g-3 mt-1 veh-hidden">
        <div class="col-lg-4 col-md-6"><label class="form-label">Folio modificacion *</label><input id="folio_modificacion" class="form-control text-uppercase" maxlength="14" placeholder="2026-12345"></div>
        <div class="col-lg-8 col-md-12"><label class="form-label">Descripcion modificacion *</label><input id="descripcion_modificacion" class="form-control text-uppercase" maxlength="3000" placeholder="Ej. CORRECCION DE DATOS DEL COMPRADOR"></div>
      </div>
    </div>
  </div>
  <div class="veh-card" id="sec-persona">
    <div class="veh-card-header" onclick="toggleVehCard(this)">
      <div class="veh-icon veh-icon-persona"><i class="fa-solid fa-users"></i></div>
      <div><h5>Persona aviso, domicilio y contacto</h5><small>Captura de una o varias personas del aviso</small></div>
      <i class="fa-solid fa-chevron-down veh-chevron"></i>
    </div>
    <div class="veh-card-body">
      <div class="row g-3">
        <div class="col-lg-3 col-md-6"><label class="form-label">Tipo persona *</label><select id="persona_tipo" class="form-select"><option value="fisica">Fisica</option><option value="moral">Moral</option><option value="fideicomiso">Fideicomiso</option></select></div>
        <div class="col-lg-3 col-md-6"><label class="form-label">Tipo domicilio *</label><select id="domicilio_tipo" class="form-select"><option value="nacional">Nacional</option><option value="extranjero">Extranjero</option></select></div>
      </div>

      <div id="pf_wrap" class="mt-3">
        <div class="row g-3">
          <div class="col-lg-4 col-md-6"><label class="form-label">Nombre *</label><input id="pf_nombre" class="form-control text-uppercase" placeholder="JUAN CARLOS"></div>
          <div class="col-lg-4 col-md-6"><label class="form-label">Apellido paterno *</label><input id="pf_ap" class="form-control text-uppercase" placeholder="PEREZ"></div>
          <div class="col-lg-4 col-md-6"><label class="form-label">Apellido materno *</label><input id="pf_am" class="form-control text-uppercase" placeholder="GARCIA"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">Fecha nacimiento</label><input id="pf_fn" type="date" class="form-control" placeholder="1990-05-16"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">RFC</label><input id="pf_rfc" class="form-control text-uppercase" maxlength="13" placeholder="PEGJ780219R56"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">CURP</label><input id="pf_curp" class="form-control text-uppercase" maxlength="18" placeholder="PEGJ780219HDFRRS09"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">Pais *</label><select id="pf_pais" class="form-select"><?= mjrCatalogoOptions('pais', 'MX') ?></select></div>
          <div class="col-lg-6 col-md-8"><label class="form-label">Actividad economica *</label><select id="pf_act" class="form-select"><?= mjrCatalogoOptions('actividad_economica', '1000000') ?></select></div>
        </div>
      </div>

      <div id="pm_wrap" class="mt-3 veh-hidden">
        <div class="row g-3">
          <div class="col-lg-6 col-md-8"><label class="form-label">Denominacion / Razon *</label><input id="pm_den" class="form-control text-uppercase" placeholder="AUTOS DEL CENTRO SA DE CV"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">Fecha constitucion</label><input id="pm_fc" type="date" class="form-control" placeholder="2018-05-16"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">RFC</label><input id="pm_rfc" class="form-control text-uppercase" maxlength="12" placeholder="ADC180516ABC"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">Pais *</label><select id="pm_pais" class="form-select"><?= mjrCatalogoOptions('pais', 'MX') ?></select></div>
          <div class="col-lg-6 col-md-8"><label class="form-label">Giro mercantil *</label><select id="pm_giro" class="form-select"><?= mjrCatalogoOptions('giro_mercantil', '1000000') ?></select></div>
        </div>
        <div class="veh-subcard">
          <h6 class="mb-2">Representante / Apoderado</h6>
          <div class="row g-3">
            <div class="col-lg-4 col-md-6"><label class="form-label">Nombre *</label><input id="pm_rn" class="form-control text-uppercase" placeholder="LUIS ALBERTO"></div>
            <div class="col-lg-4 col-md-6"><label class="form-label">Apellido paterno *</label><input id="pm_rap" class="form-control text-uppercase" placeholder="LOPEZ"></div>
            <div class="col-lg-4 col-md-6"><label class="form-label">Apellido materno *</label><input id="pm_ram" class="form-control text-uppercase" placeholder="MARTINEZ"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">Fecha nacimiento</label><input id="pm_rfn" type="date" class="form-control" placeholder="1988-03-22"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">RFC</label><input id="pm_rrfc" class="form-control text-uppercase" maxlength="13" placeholder="LOMJ880322AB1"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">CURP</label><input id="pm_rcurp" class="form-control text-uppercase" maxlength="18" placeholder="LOMJ880322HDFPRN01"></div>
          </div>
        </div>
      </div>

      <div id="fi_wrap" class="mt-3 veh-hidden">
        <div class="row g-3">
          <div class="col-lg-6 col-md-8"><label class="form-label">Denominacion fideicomiso *</label><input id="fi_den" class="form-control text-uppercase" placeholder="FIDEICOMISO COMERCIAL"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">RFC fideicomiso</label><input id="fi_rfc" class="form-control text-uppercase" maxlength="12" placeholder="FDV901010ABC"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">Identificador fideicomiso</label><input id="fi_id" class="form-control text-uppercase" maxlength="40" placeholder="FIDEI-2026-001"></div>
        </div>
        <div class="veh-subcard">
          <h6 class="mb-2">Apoderado / Delegado</h6>
          <div class="row g-3">
            <div class="col-lg-4 col-md-6"><label class="form-label">Nombre *</label><input id="fi_an" class="form-control text-uppercase" placeholder="MARIA FERNANDA"></div>
            <div class="col-lg-4 col-md-6"><label class="form-label">Apellido paterno *</label><input id="fi_aap" class="form-control text-uppercase" placeholder="HERNANDEZ"></div>
            <div class="col-lg-4 col-md-6"><label class="form-label">Apellido materno *</label><input id="fi_aam" class="form-control text-uppercase" placeholder="SILVA"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">Fecha nacimiento</label><input id="fi_afn" type="date" class="form-control" placeholder="1991-10-09"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">RFC</label><input id="fi_arfc" class="form-control text-uppercase" maxlength="13" placeholder="HESM9110099A1"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">CURP</label><input id="fi_acurp" class="form-control text-uppercase" maxlength="18" placeholder="HESM911009MDFRRL08"></div>
          </div>
        </div>
      </div>

      <hr>
      <div id="dom_nac">
        <div class="row g-3">
          <div class="col-lg-4 col-md-6"><label class="form-label">Colonia *</label><input id="dn_col" class="form-control text-uppercase" placeholder="CENTRO"></div>
          <div class="col-lg-4 col-md-6"><label class="form-label">Calle *</label><input id="dn_calle" class="form-control text-uppercase" placeholder="AV INSURGENTES"></div>
          <div class="col-lg-2 col-md-4"><label class="form-label">Numero exterior *</label><input id="dn_ne" class="form-control text-uppercase" placeholder="120"></div>
          <div class="col-lg-2 col-md-4"><label class="form-label">Numero interior</label><input id="dn_ni" class="form-control text-uppercase" placeholder="B"></div>
          <div class="col-lg-2 col-md-4"><label class="form-label">Codigo postal *</label><input id="dn_cp" class="form-control" maxlength="5" placeholder="01030" inputmode="numeric"></div>
        </div>
      </div>

      <div id="dom_ext" class="veh-hidden">
        <div class="row g-3">
          <div class="col-lg-3 col-md-6"><label class="form-label">Pais *</label><select id="de_pais" class="form-select"><?= mjrCatalogoOptions('pais', 'US') ?></select></div>
          <div class="col-lg-3 col-md-6"><label class="form-label">Estado / Provincia *</label><input id="de_est" class="form-control text-uppercase" placeholder="CALIFORNIA"></div>
          <div class="col-lg-3 col-md-6"><label class="form-label">Ciudad / Poblacion *</label><input id="de_cd" class="form-control text-uppercase" placeholder="LOS ANGELES"></div>
          <div class="col-lg-3 col-md-6"><label class="form-label">Colonia *</label><input id="de_col" class="form-control text-uppercase" placeholder="WEST"></div>
          <div class="col-lg-4 col-md-6"><label class="form-label">Calle *</label><input id="de_calle" class="form-control text-uppercase" placeholder="MAIN STREET"></div>
          <div class="col-lg-2 col-md-4"><label class="form-label">Numero exterior *</label><input id="de_ne" class="form-control text-uppercase" placeholder="45"></div>
          <div class="col-lg-2 col-md-4"><label class="form-label">Numero interior</label><input id="de_ni" class="form-control text-uppercase" placeholder="A"></div>
          <div class="col-lg-2 col-md-4"><label class="form-label">Codigo postal *</label><input id="de_cp" class="form-control text-uppercase" maxlength="12" placeholder="90001"></div>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-md-3"><label class="form-label">Clave pais telefono</label><select id="tel_pais" class="form-select"><?= mjrCatalogoOptions('pais', 'MX') ?></select></div>
        <div class="col-md-3"><label class="form-label">Telefono</label><input id="tel_num" class="form-control" maxlength="12" placeholder="5512345678" inputmode="numeric"></div>
        <div class="col-md-6"><label class="form-label">Correo electronico</label><input id="tel_mail" class="form-control text-uppercase" maxlength="60" placeholder="CONTACTO@MAIL.COM"></div>
      </div>

      <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
        <button type="button" id="btn_add_persona" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-user-plus me-1"></i>Agregar persona al aviso</button>
        <button type="button" id="btn_clear_persona" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-eraser me-1"></i>Limpiar editor</button>
        <span class="veh-note">Puedes registrar varias personas en el mismo Aviso MJR.</span>
      </div>
      <div id="personas_list" class="mt-3"></div>
    </div>
  </div>

  <div class="veh-card" id="sec-beneficiario">
    <div class="veh-card-header" onclick="toggleVehCard(this)">
      <div class="veh-icon veh-icon-benef"><i class="fa-solid fa-user-shield"></i></div>
      <div><h5>Dueno beneficiario (opcional)</h5><small>Informacion del beneficiario final del aviso</small></div>
      <i class="fa-solid fa-chevron-down veh-chevron"></i>
    </div>
    <div class="veh-card-body">
      <div class="form-check mb-2"><input id="db_on" class="form-check-input" type="checkbox"><label class="form-check-label" for="db_on">Capturar dueno beneficiario</label></div>
      <div id="db_wrap" class="veh-hidden">
        <div class="row g-3"><div class="col-md-3"><label class="form-label">Tipo</label><select id="db_tipo" class="form-select"><option value="fisica">Fisica</option><option value="moral">Moral</option><option value="fideicomiso">Fideicomiso</option></select></div></div>
        <div id="db_fisica" class="mt-2">
          <div class="row g-3">
            <div class="col-lg-4 col-md-6"><label class="form-label">Nombre *</label><input id="db_f_nom" class="form-control text-uppercase" placeholder="RODRIGO"></div>
            <div class="col-lg-4 col-md-6"><label class="form-label">Apellido paterno *</label><input id="db_f_ap" class="form-control text-uppercase" placeholder="SUAREZ"></div>
            <div class="col-lg-4 col-md-6"><label class="form-label">Apellido materno *</label><input id="db_f_am" class="form-control text-uppercase" placeholder="MORALES"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">Fecha nacimiento</label><input id="db_f_fecha" type="date" class="form-control" placeholder="1992-11-08"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">RFC</label><input id="db_f_rfc" class="form-control text-uppercase" maxlength="13" placeholder="SUMR921108AA1"></div>
            <div class="col-lg-3 col-md-4"><label class="form-label">CURP</label><input id="db_f_curp" class="form-control text-uppercase" maxlength="18" placeholder="SUMR921108HDFRDL06"></div>
            <div class="col-md-3"><label class="form-label">Pais *</label><select id="db_f_pais" class="form-select"><?= mjrCatalogoOptions('pais', 'MX') ?></select></div>
          </div>
        </div>
        <div id="db_moral" class="mt-2 veh-hidden">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Denominacion / Razon *</label><input id="db_m_den" class="form-control text-uppercase" placeholder="HOLDING COMERCIAL SA"></div>
            <div class="col-md-3"><label class="form-label">Fecha constitucion</label><input id="db_m_fc" type="date" class="form-control" placeholder="2010-01-15"></div>
            <div class="col-md-3"><label class="form-label">RFC</label><input id="db_m_rfc" class="form-control text-uppercase" maxlength="12" placeholder="HOV100115AB1"></div>
            <div class="col-md-3"><label class="form-label">Pais *</label><select id="db_m_pais" class="form-select"><?= mjrCatalogoOptions('pais', 'MX') ?></select></div>
          </div>
        </div>
        <div id="db_fide" class="mt-2 veh-hidden">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Denominacion / Razon *</label><input id="db_t_den" class="form-control text-uppercase" placeholder="FIDEICOMISO BENEFICIARIO"></div>
            <div class="col-md-3"><label class="form-label">RFC</label><input id="db_t_rfc" class="form-control text-uppercase" maxlength="12" placeholder="FIB901201ABC"></div>
            <div class="col-md-3"><label class="form-label">Identificador</label><input id="db_t_id" class="form-control text-uppercase" maxlength="40" placeholder="FDB-2026-009"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="veh-card" id="sec-detalle">
    <div class="veh-card-header" onclick="toggleVehCard(this)">
      <div class="veh-icon veh-icon-detalle"><i class="fa-solid fa-file-invoice-dollar"></i></div>
      <div><h5>Detalle de operación MJR</h5><small>Datos del bien comercializado y liquidación</small></div>
      <i class="fa-solid fa-chevron-down veh-chevron"></i>
    </div>
    <div class="veh-card-body">
      <div class="row g-3">
        <div class="col-lg-3 col-md-4"><label class="form-label">Fecha operación *</label><input id="op_fecha" type="date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" placeholder="2026-03-24"></div>
        <div class="col-lg-3 col-md-4"><label class="form-label">CP operación *</label><input id="op_cp" class="form-control" maxlength="5" placeholder="56140" inputmode="numeric"></div>
        <div class="col-lg-4 col-md-6"><label class="form-label">Tipo operación *</label><select id="op_tipo" class="form-select"><?= mjrCatalogoOptions('tipo_operacion', '601') ?></select></div>
      </div>

      <hr>
      <h6 class="mb-2">Datos del bien</h6>
      <div class="row g-3">
        <div class="col-lg-4 col-md-6"><label class="form-label">Tipo de bien *</label><select id="bien_tipo" class="form-select"><?= mjrCatalogoOptions('tipo_bien', '1') ?></select></div>
        <div class="col-lg-3 col-md-4"><label class="form-label">Unidad comercializada *</label><select id="bien_unidad" class="form-select"><?= mjrCatalogoOptions('unidad_comercializada', '2') ?></select></div>
        <div class="col-lg-3 col-md-4"><label class="form-label">Cantidad comercializada *</label><input id="bien_cantidad" type="number" class="form-control" step="0.01" min="0.01" value="1.00" placeholder="10.00"></div>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
        <button type="button" id="btn_add_bien" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Agregar bien</button>
        <button type="button" id="btn_clear_bien" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-eraser me-1"></i>Limpiar bien</button>
        <span class="veh-note">Puedes agregar varios bienes por operación.</span>
      </div>
      <div id="bien_list" class="mt-3"></div>

      <hr>
      <h6 class="mb-2">Liquidación</h6>
      <div class="row g-3">
        <div class="col-lg-2 col-md-4"><label class="form-label">Fecha pago *</label><input id="liq_fecha_pago" type="date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" placeholder="2026-03-24"></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Forma pago *</label><select id="liq_forma_pago" class="form-select"><?= mjrCatalogoOptions('forma_pago', '1') ?></select></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Instrumento *</label><select id="liq_instrumento" class="form-select"><?= mjrCatalogoOptions('instrumento_monetario') ?></select></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Moneda *</label><select id="liq_moneda" class="form-select"><?= mjrCatalogoOptions('moneda', '1') ?></select></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Monto *</label><input id="liq_monto" type="number" class="form-control" step="0.01" min="0.01" value="0.00" placeholder="350000.00"></div>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
        <button type="button" id="btn_add_liq" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Agregar liquidacion</button>
        <button type="button" id="btn_clear_liq" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-eraser me-1"></i>Limpiar liquidacion</button>
        <span class="veh-note">Puedes agregar varias liquidaciones por operacion.</span>
      </div>
      <div id="liq_list" class="mt-3"></div>
    </div>
  </div>

  <div class="veh-submit-bar mb-4">
    <a href="operaciones_pld.php" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Registrar Aviso MJR</button>
  </div>
</form>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const q = id => document.getElementById(id);
const v = id => (q(id)?.value || '').trim();
const up = x => String(x || '').trim().toUpperCase();
const d8 = x => String(x || '').replaceAll('-', '');
const isDate8 = x => /^\d{8}$/.test(x);
const isCP = x => /^\d{5}$/.test(x);
const isMonth6 = x => /^[2-9]\d{3}(0[1-9]|1[0-2])$/.test(x);
const todayYmd = () => new Date().toISOString().slice(0, 10);
const today8 = () => todayYmd().replaceAll('-', '');

let personasAviso = [];
let personaEditIndex = null;
let bienesOperacion = [];
let bienEditIndex = null;
let liquidaciones = [];
let liqEditIndex = null;

function toggleVehCard(header) {
  const card = header?.closest('.veh-card');
  const body = card?.querySelector('.veh-card-body');
  if (!body) return;
  const collapsed = header.classList.toggle('collapsed');
  body.style.display = collapsed ? 'none' : '';
}

function bindUpper(id, max = null, pattern = null) {
  const el = q(id);
  if (!el) return;
  const apply = () => {
    let value = up(el.value || '');
    if (pattern) value = value.replace(pattern, '');
    if (max !== null) value = value.slice(0, max);
    if (el.value !== value) el.value = value;
  };
  el.addEventListener('input', apply);
  el.addEventListener('blur', apply);
  apply();
}

function bindDigits(id, max = null) {
  const el = q(id);
  if (!el) return;
  const apply = () => {
    let value = String(el.value || '').replace(/\D+/g, '');
    if (max !== null) value = value.slice(0, max);
    if (el.value !== value) el.value = value;
  };
  el.addEventListener('input', apply);
  el.addEventListener('blur', apply);
  apply();
}

function setupMasks() {
  bindDigits('mes_reportado', 6);
  bindDigits('op_cp', 5);
  bindDigits('dn_cp', 5);
  bindDigits('tel_num', 12);
  bindDigits('de_cp', 12);
  bindUpper('clave_sujeto_obligado', 13, /[^A-Z0-9Ñ&]/g);
  bindUpper('clave_entidad_colegiada', 12, /[^A-Z0-9Ñ&]/g);
  bindUpper('referencia_aviso', 14, /[^A-Z0-9Ñ]/g);
  bindUpper('folio_modificacion', 14, /[^0-9-]/g);
  ['pf_rfc','pm_rrfc','fi_arfc','db_f_rfc'].forEach(id => bindUpper(id, 13, /[^A-Z0-9Ñ&]/g));
  ['pm_rfc','fi_rfc','db_m_rfc','db_t_rfc'].forEach(id => bindUpper(id, 12, /[^A-Z0-9Ñ&]/g));
  ['pf_curp','pm_rcurp','fi_acurp','db_f_curp'].forEach(id => bindUpper(id, 18, /[^A-Z0-9]/g));
  ['pf_nombre','pf_ap','pf_am','pm_den','pm_rn','pm_rap','pm_ram','fi_den','fi_an','fi_aap','fi_aam','dn_col','dn_calle','dn_ne','dn_ni','de_est','de_cd','de_col','de_calle','de_ne','de_ni','db_f_nom','db_f_ap','db_f_am','db_m_den','db_t_den','db_t_id','descripcion_alerta','descripcion_modificacion','tel_mail']
    .forEach(id => bindUpper(id));
}

function setPersonaActionButton() {
  const btn = q('btn_add_persona');
  if (!btn) return;
  if (personaEditIndex === null) {
    btn.innerHTML = '<i class="fa-solid fa-user-plus me-1"></i>Agregar persona al aviso';
    btn.classList.remove('btn-warning');
    btn.classList.add('btn-outline-primary');
  } else {
    btn.innerHTML = '<i class="fa-solid fa-pen-to-square me-1"></i>Actualizar persona';
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-warning');
  }
}

function setLiqActionButton() {
  const btn = q('btn_add_liq');
  if (!btn) return;
  if (liqEditIndex === null) {
    btn.innerHTML = '<i class="fa-solid fa-plus me-1"></i>Agregar liquidacion';
    btn.classList.remove('btn-warning');
    btn.classList.add('btn-outline-primary');
  } else {
    btn.innerHTML = '<i class="fa-solid fa-pen-to-square me-1"></i>Actualizar liquidacion';
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-warning');
  }
}

function setBienActionButton() {
  const btn = q('btn_add_bien');
  if (!btn) return;
  if (bienEditIndex === null) {
    btn.innerHTML = '<i class="fa-solid fa-plus me-1"></i>Agregar bien';
    btn.classList.remove('btn-warning');
    btn.classList.add('btn-outline-primary');
  } else {
    btn.innerHTML = '<i class="fa-solid fa-pen-to-square me-1"></i>Actualizar bien';
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-warning');
  }
}

function tgPersona() {
  const t = v('persona_tipo');
  q('pf_wrap').classList.toggle('veh-hidden', t !== 'fisica');
  q('pm_wrap').classList.toggle('veh-hidden', t !== 'moral');
  q('fi_wrap').classList.toggle('veh-hidden', t !== 'fideicomiso');
}
function tgDom() {
  const t = v('domicilio_tipo');
  q('dom_nac').classList.toggle('veh-hidden', t !== 'nacional');
  q('dom_ext').classList.toggle('veh-hidden', t !== 'extranjero');
}
function tgMod() { q('modif_wrap').classList.toggle('veh-hidden', !q('es_modificatorio').checked); }
function tgDb() { q('db_wrap').classList.toggle('veh-hidden', !q('db_on').checked); }
function tgDbTipo() {
  const t = v('db_tipo');
  q('db_fisica').classList.toggle('veh-hidden', t !== 'fisica');
  q('db_moral').classList.toggle('veh-hidden', t !== 'moral');
  q('db_fide').classList.toggle('veh-hidden', t !== 'fideicomiso');
}
async function loadClientes() {
  const r = await fetch('api/get_clients.php');
  const d = await r.json();
  const s = q('id_cliente');
  (Array.isArray(d) ? d : []).forEach(c => {
    const o = document.createElement('option');
    o.value = c.id_cliente;
    o.textContent = `${c.id_cliente} - ${c.nombre_cliente || 'Sin nombre'} (${c.rfc || 'N/A'})`;
    s.appendChild(o);
  });
}

function fillFromKyc(k) {
  if (!k || typeof k !== 'object') return;
  const tipo = Number(k.id_tipo_persona || 0);
  if (tipo === 1 || k.es_fisica === 1) {
    q('persona_tipo').value = 'fisica';
    q('pf_nombre').value = up(k.nombre || '');
    q('pf_ap').value = up(k.apellido_paterno || '');
    q('pf_am').value = up(k.apellido_materno || '');
    if (k.fecha_nacimiento) q('pf_fn').value = String(k.fecha_nacimiento).slice(0, 10);
    q('pf_rfc').value = up(k.rfc || '');
    q('pf_curp').value = up(k.curp || '');
  } else if (tipo === 2 || k.es_moral === 1) {
    q('persona_tipo').value = 'moral';
    q('pm_den').value = up(k.denominacion_razon || k.razon_social || '');
    if (k.fecha_constitucion) q('pm_fc').value = String(k.fecha_constitucion).slice(0, 10);
    q('pm_rfc').value = up(k.rfc || '');
  } else if (tipo === 3 || k.es_fideicomiso === 1) {
    q('persona_tipo').value = 'fideicomiso';
    q('fi_den').value = up(k.denominacion_razon || '');
    q('fi_rfc').value = up(k.rfc || '');
  }
  tgPersona();
}

async function loadKyc() {
  const id = v('id_cliente');
  if (!id) { q('kyc_info').textContent = ''; return; }
  const r = await fetch('api/get_cliente_kyc_pld.php?id=' + encodeURIComponent(id));
  const d = await r.json();
  if (d?.status !== 'success') { q('kyc_info').textContent = ''; return; }
  const k = d.kyc || {};
  const nombre = up(k.denominacion_razon || k.razon_social || [k.nombre, k.apellido_paterno, k.apellido_materno].filter(Boolean).join(' '));
  q('kyc_info').innerHTML = `<strong>KYC:</strong> ${up(k.tipo_persona || '')} | ${nombre || '-'} | RFC: ${up(k.rfc || '-')}`;
  fillFromKyc(k);
}

function personaObjFromEditor() {
  const t = v('persona_tipo');
  if (t === 'fisica') {
    return { persona_fisica: { nombre: up(v('pf_nombre')), apellido_paterno: up(v('pf_ap')), apellido_materno: up(v('pf_am')), fecha_nacimiento: d8(v('pf_fn')), rfc: up(v('pf_rfc')), curp: up(v('pf_curp')), pais_nacionalidad: up(v('pf_pais')), actividad_economica: v('pf_act') } };
  }
  if (t === 'moral') {
    return { persona_moral: { denominacion_razon: up(v('pm_den')), fecha_constitucion: d8(v('pm_fc')), rfc: up(v('pm_rfc')), pais_nacionalidad: up(v('pm_pais')), giro_mercantil: v('pm_giro'), representante_apoderado: { nombre: up(v('pm_rn')), apellido_paterno: up(v('pm_rap')), apellido_materno: up(v('pm_ram')), fecha_nacimiento: d8(v('pm_rfn')), rfc: up(v('pm_rrfc')), curp: up(v('pm_rcurp')) } } };
  }
  return { fideicomiso: { denominacion_razon: up(v('fi_den')), rfc: up(v('fi_rfc')), identificador_fideicomiso: up(v('fi_id')), apoderado_delegado: { nombre: up(v('fi_an')), apellido_paterno: up(v('fi_aap')), apellido_materno: up(v('fi_aam')), fecha_nacimiento: d8(v('fi_afn')), rfc: up(v('fi_arfc')), curp: up(v('fi_acurp')) } } };
}
function domicilioObjFromEditor() {
  if (v('domicilio_tipo') === 'nacional') {
    return { nacional: { colonia: up(v('dn_col')), calle: up(v('dn_calle')), numero_exterior: up(v('dn_ne')), numero_interior: up(v('dn_ni')), codigo_postal: v('dn_cp') } };
  }
  return { extranjero: { pais: up(v('de_pais')), estado_provincia: up(v('de_est')), ciudad_poblacion: up(v('de_cd')), colonia: up(v('de_col')), calle: up(v('de_calle')), numero_exterior: up(v('de_ne')), numero_interior: up(v('de_ni')), codigo_postal: up(v('de_cp')) } };
}
function telefonoObjFromEditor() {
  const tel = { clave_pais: up(v('tel_pais')), numero_telefono: String(v('tel_num')).replace(/\D+/g, ''), correo_electronico: up(v('tel_mail')) };
  if (!tel.clave_pais && !tel.numero_telefono && !tel.correo_electronico) return null;
  return tel;
}
function personaEditorHasData() { return ['pf_nombre','pf_ap','pf_am','pm_den','fi_den','dn_col','de_est'].some(id => v(id) !== ''); }

function validatePersonaEditor() {
  const t = v('persona_tipo');
  if (!['fisica','moral','fideicomiso'].includes(t)) throw new Error('Tipo de persona invalido.');
  if (t === 'fisica') {
    if (!v('pf_nombre') || !v('pf_ap') || !v('pf_am')) throw new Error('Persona fisica requiere nombre y apellidos.');
    if (!v('pf_pais')) throw new Error('Persona fisica requiere pais.');
    if (!/^\d{7}$/.test(v('pf_act'))) throw new Error('Persona fisica requiere actividad economica de 7 digitos.');
  } else if (t === 'moral') {
    if (!v('pm_den')) throw new Error('Persona moral requiere denominacion.');
    if (!v('pm_pais')) throw new Error('Persona moral requiere pais.');
    if (!/^\d{7}$/.test(v('pm_giro'))) throw new Error('Persona moral requiere giro mercantil de 7 digitos.');
    if (!v('pm_rn') || !v('pm_rap') || !v('pm_ram')) throw new Error('Persona moral requiere representante/apoderado.');
  } else {
    if (!v('fi_den')) throw new Error('Fideicomiso requiere denominacion.');
    if (!v('fi_an') || !v('fi_aap') || !v('fi_aam')) throw new Error('Fideicomiso requiere apoderado/delegado.');
  }
  if (v('domicilio_tipo') === 'nacional') {
    if (!v('dn_col') || !v('dn_calle') || !v('dn_ne') || !isCP(v('dn_cp'))) throw new Error('Domicilio nacional incompleto o codigo postal invalido.');
  } else {
    if (!v('de_pais') || !v('de_est') || !v('de_cd') || !v('de_col') || !v('de_calle') || !v('de_ne') || !v('de_cp')) throw new Error('Domicilio extranjero incompleto.');
    if (!/^[A-Z0-9]{4,12}$/.test(up(v('de_cp')))) throw new Error('Codigo postal extranjero invalido.');
  }
}

function buildPersonaAvisoFromEditor() {
  const obj = { tipo_persona: personaObjFromEditor(), tipo_domicilio: domicilioObjFromEditor() };
  const tel = telefonoObjFromEditor();
  if (tel) obj.telefono = tel;
  return obj;
}
function clearPersonaEditor() {
  ['pf_nombre','pf_ap','pf_am','pf_fn','pf_rfc','pf_curp','pm_den','pm_fc','pm_rfc','pm_rn','pm_rap','pm_ram','pm_rfn','pm_rrfc','pm_rcurp','fi_den','fi_rfc','fi_id','fi_an','fi_aap','fi_aam','fi_afn','fi_arfc','fi_acurp','dn_col','dn_calle','dn_ne','dn_ni','dn_cp','de_est','de_cd','de_col','de_calle','de_ne','de_ni','de_cp','tel_num','tel_mail'].forEach(id => { if (q(id)) q(id).value = ''; });
  q('persona_tipo').value = 'fisica';
  q('domicilio_tipo').value = 'nacional';
  q('pf_pais').value = 'MX'; q('pm_pais').value = 'MX'; q('de_pais').value = 'US'; q('tel_pais').value = 'MX';
  tgPersona(); tgDom(); personaEditIndex = null; setPersonaActionButton(); renderPersonasList();
}
function loadPersonaToEditor(pa) {
  clearPersonaEditor();
  if (!pa || !pa.tipo_persona) return;
  const tp = pa.tipo_persona;
  if (tp.persona_fisica) {
    const p = tp.persona_fisica;
    q('persona_tipo').value = 'fisica'; q('pf_nombre').value = p.nombre || ''; q('pf_ap').value = p.apellido_paterno || ''; q('pf_am').value = p.apellido_materno || '';
    if (p.fecha_nacimiento && String(p.fecha_nacimiento).length === 8) q('pf_fn').value = `${p.fecha_nacimiento.slice(0,4)}-${p.fecha_nacimiento.slice(4,6)}-${p.fecha_nacimiento.slice(6,8)}`;
    q('pf_rfc').value = p.rfc || ''; q('pf_curp').value = p.curp || ''; if (p.pais_nacionalidad) q('pf_pais').value = p.pais_nacionalidad; if (p.actividad_economica) q('pf_act').value = p.actividad_economica;
  } else if (tp.persona_moral) {
    const p = tp.persona_moral;
    q('persona_tipo').value = 'moral'; q('pm_den').value = p.denominacion_razon || '';
    if (p.fecha_constitucion && String(p.fecha_constitucion).length === 8) q('pm_fc').value = `${p.fecha_constitucion.slice(0,4)}-${p.fecha_constitucion.slice(4,6)}-${p.fecha_constitucion.slice(6,8)}`;
    q('pm_rfc').value = p.rfc || ''; if (p.pais_nacionalidad) q('pm_pais').value = p.pais_nacionalidad; if (p.giro_mercantil) q('pm_giro').value = p.giro_mercantil;
    const r = p.representante_apoderado || {}; q('pm_rn').value = r.nombre || ''; q('pm_rap').value = r.apellido_paterno || ''; q('pm_ram').value = r.apellido_materno || '';
    if (r.fecha_nacimiento && String(r.fecha_nacimiento).length === 8) q('pm_rfn').value = `${r.fecha_nacimiento.slice(0,4)}-${r.fecha_nacimiento.slice(4,6)}-${r.fecha_nacimiento.slice(6,8)}`;
    q('pm_rrfc').value = r.rfc || ''; q('pm_rcurp').value = r.curp || '';
  } else if (tp.fideicomiso) {
    const p = tp.fideicomiso;
    q('persona_tipo').value = 'fideicomiso'; q('fi_den').value = p.denominacion_razon || ''; q('fi_rfc').value = p.rfc || ''; q('fi_id').value = p.identificador_fideicomiso || '';
    const a = p.apoderado_delegado || {}; q('fi_an').value = a.nombre || ''; q('fi_aap').value = a.apellido_paterno || ''; q('fi_aam').value = a.apellido_materno || '';
    if (a.fecha_nacimiento && String(a.fecha_nacimiento).length === 8) q('fi_afn').value = `${a.fecha_nacimiento.slice(0,4)}-${a.fecha_nacimiento.slice(4,6)}-${a.fecha_nacimiento.slice(6,8)}`;
    q('fi_arfc').value = a.rfc || ''; q('fi_acurp').value = a.curp || '';
  }
  const td = pa.tipo_domicilio || {};
  if (td.extranjero) {
    const d = td.extranjero;
    q('domicilio_tipo').value = 'extranjero'; if (d.pais) q('de_pais').value = d.pais;
    q('de_est').value = d.estado_provincia || ''; q('de_cd').value = d.ciudad_poblacion || ''; q('de_col').value = d.colonia || ''; q('de_calle').value = d.calle || ''; q('de_ne').value = d.numero_exterior || ''; q('de_ni').value = d.numero_interior || ''; q('de_cp').value = d.codigo_postal || '';
  } else if (td.nacional) {
    const d = td.nacional;
    q('domicilio_tipo').value = 'nacional'; q('dn_col').value = d.colonia || ''; q('dn_calle').value = d.calle || ''; q('dn_ne').value = d.numero_exterior || ''; q('dn_ni').value = d.numero_interior || ''; q('dn_cp').value = d.codigo_postal || '';
  }
  const tel = pa.telefono || {}; if (tel.clave_pais) q('tel_pais').value = tel.clave_pais; q('tel_num').value = tel.numero_telefono || ''; q('tel_mail').value = tel.correo_electronico || '';
  tgPersona(); tgDom();
}
function resumenPersona(pa, index) {
  const tp = pa.tipo_persona || {};
  if (tp.persona_fisica) { const p = tp.persona_fisica; return `#${index + 1} Fisica: ${p.nombre} ${p.apellido_paterno} ${p.apellido_materno}`.trim(); }
  if (tp.persona_moral) return `#${index + 1} Moral: ${tp.persona_moral.denominacion_razon}`;
  if (tp.fideicomiso) return `#${index + 1} Fideicomiso: ${tp.fideicomiso.denominacion_razon}`;
  return `#${index + 1} Persona`;
}
function renderPersonasList() {
  const cont = q('personas_list');
  if (!cont) return;
  if (personasAviso.length === 0) { cont.innerHTML = '<div class="veh-note">No hay personas agregadas todavia.</div>'; return; }
  cont.innerHTML = personasAviso.map((pa, i) => `<div class="veh-item d-flex justify-content-between align-items-center ${personaEditIndex === i ? 'border border-warning' : ''}"><div>${resumenPersona(pa, i)} ${personaEditIndex === i ? '<span class="badge bg-warning text-dark ms-2">Editando</span>' : ''}</div><div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary" data-edit-persona="${i}"><i class="fa-solid fa-pen me-1"></i>Editar</button><button type="button" class="btn btn-sm btn-outline-danger" data-remove-persona="${i}"><i class="fa-solid fa-trash me-1"></i>Quitar</button></div></div>`).join('');
}
function getPersonasAvisoFinal() {
  const finalList = [...personasAviso];
  if (personaEditorHasData()) {
    validatePersonaEditor();
    if (personaEditIndex !== null && finalList[personaEditIndex]) finalList[personaEditIndex] = buildPersonaAvisoFromEditor();
    else finalList.push(buildPersonaAvisoFromEditor());
  }
  if (finalList.length === 0) throw new Error('Capture al menos una persona para el aviso.');
  return finalList;
}

function dbObj() {
  if (!q('db_on').checked) return null;
  const t = v('db_tipo');
  if (t === 'fisica') return { tipo_persona: { persona_fisica: { nombre: up(v('db_f_nom')), apellido_paterno: up(v('db_f_ap')), apellido_materno: up(v('db_f_am')), fecha_nacimiento: d8(v('db_f_fecha')), rfc: up(v('db_f_rfc')), curp: up(v('db_f_curp')), pais_nacionalidad: up(v('db_f_pais')) } } };
  if (t === 'moral') return { tipo_persona: { persona_moral: { denominacion_razon: up(v('db_m_den')), fecha_constitucion: d8(v('db_m_fc')), rfc: up(v('db_m_rfc')), pais_nacionalidad: up(v('db_m_pais')) } } };
  return { tipo_persona: { fideicomiso: { denominacion_razon: up(v('db_t_den')), rfc: up(v('db_t_rfc')), identificador_fideicomiso: up(v('db_t_id')) } } };
}
function validateDb() {
  if (!q('db_on').checked) return;
  const t = v('db_tipo');
  if (t === 'fisica') { if (!v('db_f_nom') || !v('db_f_ap') || !v('db_f_am') || !v('db_f_pais')) throw new Error('Dueno beneficiario fisico incompleto.'); }
  else if (t === 'moral') { if (!v('db_m_den') || !v('db_m_pais')) throw new Error('Dueno beneficiario moral incompleto.'); }
  else if (!v('db_t_den')) throw new Error('Dueno beneficiario fideicomiso requiere denominacion.');
}

function bienEditorHasData() {
  return ['bien_tipo', 'bien_unidad', 'bien_cantidad'].some(id => v(id) !== '');
}

function validateBienEditor() {
  if (!/^\d{1,2}$/.test(v('bien_tipo'))) throw new Error('Tipo de bien inválido.');
  if (!/^\d{1}$/.test(v('bien_unidad'))) throw new Error('Unidad de comercialización inválida.');
  if (Number(v('bien_cantidad')) <= 0) throw new Error('Cantidad comercializada debe ser mayor a 0.');
}

function buildBienFromEditor() {
  return {
    tipo_bien: v('bien_tipo'),
    unidad_comercializada: v('bien_unidad'),
    cantidad_comercializada: Number(v('bien_cantidad')).toFixed(2)
  };
}

function clearBienEditor() {
  q('bien_tipo').value = '1';
  q('bien_unidad').value = '1';
  q('bien_cantidad').value = '1.00';
  bienEditIndex = null;
  setBienActionButton();
  renderBienesList();
}

function renderBienesList() {
  const cont = q('bien_list');
  if (!cont) return;
  if (bienesOperacion.length === 0) {
    cont.innerHTML = '<div class="veh-note">No hay bienes agregados todavía.</div>';
    return;
  }
  cont.innerHTML = bienesOperacion.map((b, i) => `<div class="veh-item d-flex justify-content-between align-items-center ${bienEditIndex === i ? 'border border-warning' : ''}"><div>#${i + 1} Tipo ${b.tipo_bien} | Unidad ${b.unidad_comercializada} | Cantidad ${b.cantidad_comercializada}</div><div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary" data-edit-bien="${i}"><i class="fa-solid fa-pen me-1"></i>Editar</button><button type="button" class="btn btn-sm btn-outline-danger" data-remove-bien="${i}"><i class="fa-solid fa-trash me-1"></i>Quitar</button></div></div>`).join('');
}

function getBienesFinal() {
  const finalList = [...bienesOperacion];
  if (bienEditorHasData()) {
    validateBienEditor();
    if (bienEditIndex !== null && finalList[bienEditIndex]) finalList[bienEditIndex] = buildBienFromEditor();
    else finalList.push(buildBienFromEditor());
  }
  if (finalList.length === 0) throw new Error('Capture al menos un bien comercializado.');
  return finalList;
}

function liqEditorHasData() { return ['liq_fecha_pago','liq_forma_pago','liq_instrumento','liq_moneda'].some(id => v(id) !== '') || Number(v('liq_monto')) > 0; }
function validateLiqEditor() {
  if (!isDate8(d8(v('liq_fecha_pago')))) throw new Error('Fecha de pago invalida.');
  if (!/^\d{1,1}$/.test(v('liq_forma_pago'))) throw new Error('Forma de pago invalida.');
  const instrumento = v('liq_instrumento');
  if (!/^\d{1,2}$/.test(instrumento)) throw new Error('Instrumento monetario invalido.');
  if (!/^\d{1,3}$/.test(v('liq_moneda'))) throw new Error('Moneda invalida.');
  if (Number(v('liq_monto')) <= 0) throw new Error('Monto de liquidacion debe ser mayor a 0.');
}
function buildLiqFromEditor() { return { fecha_pago: d8(v('liq_fecha_pago')), forma_pago: v('liq_forma_pago'), instrumento_monetario: v('liq_instrumento'), moneda: v('liq_moneda'), monto_operacion: Number(v('liq_monto')).toFixed(2) }; }
function clearLiqEditor() { q('liq_fecha_pago').value = todayYmd(); q('liq_forma_pago').value = '1'; q('liq_instrumento').selectedIndex = 0; q('liq_moneda').value = '1'; q('liq_monto').value = '0.00'; liqEditIndex = null; setLiqActionButton(); renderLiqList(); }
function renderLiqList() {
  const cont = q('liq_list');
  if (!cont) return;
  if (liquidaciones.length === 0) { cont.innerHTML = '<div class="veh-note">No hay liquidaciones agregadas todavia.</div>'; return; }
  cont.innerHTML = liquidaciones.map((l, i) => `<div class="veh-item d-flex justify-content-between align-items-center ${liqEditIndex === i ? 'border border-warning' : ''}"><div>#${i + 1} ${l.fecha_pago} | Forma ${l.forma_pago} | Inst ${l.instrumento_monetario} | Mon ${l.moneda} | Monto ${l.monto_operacion}</div><div class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-primary" data-edit-liq="${i}"><i class="fa-solid fa-pen me-1"></i>Editar</button><button type="button" class="btn btn-sm btn-outline-danger" data-remove-liq="${i}"><i class="fa-solid fa-trash me-1"></i>Quitar</button></div></div>`).join('');
}
function getLiquidacionesFinal() {
  const finalList = [...liquidaciones];
  if (liqEditorHasData()) {
    validateLiqEditor();
    if (liqEditIndex !== null && finalList[liqEditIndex]) finalList[liqEditIndex] = buildLiqFromEditor();
    else finalList.push(buildLiqFromEditor());
  }
  if (finalList.length === 0) throw new Error('Capture al menos una liquidacion.');
  return finalList;
}
function operacionObj() {
  return {
    fecha_operacion: d8(v('op_fecha')),
    codigo_postal: v('op_cp'),
    tipo_operacion: v('op_tipo'),
    datos_bien: getBienesFinal(),
    datos_liquidacion: getLiquidacionesFinal()
  };
}

function payload() {
  const personasFinal = getPersonasAvisoFinal();
  const sujetoObligado = { clave_entidad_colegiada: up(v('clave_entidad_colegiada')), clave_sujeto_obligado: up(v('clave_sujeto_obligado')), clave_actividad: 'MJR', exento: v('exento') };
  const av = { referencia_aviso: up(v('referencia_aviso')), prioridad: v('prioridad'), alerta: { tipo_alerta: v('tipo_alerta'), descripcion_alerta: up(v('descripcion_alerta')) }, persona_aviso: personasFinal, detalle_operaciones: [{ datos_operacion: [operacionObj()] }] };
  if (q('es_modificatorio').checked) av.modificatorio = { folio_modificacion: up(v('folio_modificacion')), descripcion_modificacion: up(v('descripcion_modificacion')) };
  const db = dbObj();
  if (db) av.dueno_beneficiario = [db];
  return { id_cliente: Number(v('id_cliente')), informe: [{ mes_reportado: v('mes_reportado'), sujeto_obligado: sujetoObligado, aviso: [av] }] };
}

function validateGeneral() {
  const currYm = new Date().toISOString().slice(0, 7).replace('-', '');
  if (!v('id_cliente')) throw new Error('Seleccione un cliente.');
  if (!isMonth6(v('mes_reportado')) || v('mes_reportado') < '201309' || v('mes_reportado') > currYm) throw new Error('mes_reportado invalido.');
  if (!/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/.test(up(v('clave_sujeto_obligado')))) throw new Error('Clave sujeto obligado invalida.');
  if (v('clave_entidad_colegiada') && !/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/.test(up(v('clave_entidad_colegiada')))) throw new Error('Clave entidad colegiada invalida.');
  if (!/^[A-ZÑ0-9]{1,14}$/.test(up(v('referencia_aviso')))) throw new Error('Referencia de aviso invalida.');
  if (!/^\d{3,4}$/.test(v('tipo_alerta'))) throw new Error('Tipo de alerta invalido (3-4 dígitos).');
  if (q('es_modificatorio').checked) {
    if (!/^\d{4}-\d{1,9}$/.test(up(v('folio_modificacion')))) throw new Error('Folio de modificacion invalido.');
    if (!v('descripcion_modificacion')) throw new Error('Capture descripcion de modificacion.');
  }
  validateDb();
  if (!isDate8(d8(v('op_fecha'))) || d8(v('op_fecha')) > today8() || d8(v('op_fecha')) < '20130901') throw new Error('Fecha de operacion invalida.');
  if (!isCP(v('op_cp'))) throw new Error('Codigo postal de operacion invalido.');
  if (!/^\d{3,4}$/.test(v('op_tipo'))) throw new Error('Tipo de operacion invalido.');
}

document.addEventListener('DOMContentLoaded', async () => {
  setupMasks();
  bindDigits('tipo_alerta', 4);
  await loadClientes();
  q('id_cliente').addEventListener('change', loadKyc);
  q('persona_tipo').addEventListener('change', tgPersona);
  q('domicilio_tipo').addEventListener('change', tgDom);
  q('es_modificatorio').addEventListener('change', tgMod);
  q('db_on').addEventListener('change', tgDb);
  q('db_tipo').addEventListener('change', tgDbTipo);

  q('btn_add_persona').addEventListener('click', () => {
    try {
      const isUpdate = personaEditIndex !== null && !!personasAviso[personaEditIndex];
      validatePersonaEditor();
      if (isUpdate) personasAviso[personaEditIndex] = buildPersonaAvisoFromEditor();
      else personasAviso.push(buildPersonaAvisoFromEditor());
      renderPersonasList();
      clearPersonaEditor();
      Swal.fire({ icon: 'success', title: isUpdate ? 'Persona actualizada' : 'Persona agregada', timer: 1100, showConfirmButton: false });
    } catch (e) {
      Swal.fire({ icon: 'error', title: 'No fue posible agregar', text: e.message || 'Error inesperado' });
    }
  });

  q('btn_clear_persona').addEventListener('click', clearPersonaEditor);
  q('personas_list').addEventListener('click', ev => {
    const btnEdit = ev.target.closest('[data-edit-persona]');
    if (btnEdit) {
      const index = Number(btnEdit.getAttribute('data-edit-persona'));
      if (!Number.isInteger(index) || index < 0 || !personasAviso[index]) return;
      loadPersonaToEditor(personasAviso[index]);
      personaEditIndex = index;
      setPersonaActionButton();
      renderPersonasList();
      return;
    }
    const btnRemove = ev.target.closest('[data-remove-persona]');
    if (!btnRemove) return;
    const index = Number(btnRemove.getAttribute('data-remove-persona'));
    if (!Number.isInteger(index) || index < 0) return;
    personasAviso = personasAviso.filter((_, i) => i !== index);
    if (personaEditIndex === index) personaEditIndex = null;
    else if (personaEditIndex !== null && personaEditIndex > index) personaEditIndex--;
    setPersonaActionButton();
    renderPersonasList();
  });

  q('btn_add_bien').addEventListener('click', () => {
    try {
      const isUpdate = bienEditIndex !== null && !!bienesOperacion[bienEditIndex];
      validateBienEditor();
      if (isUpdate) bienesOperacion[bienEditIndex] = buildBienFromEditor();
      else bienesOperacion.push(buildBienFromEditor());
      renderBienesList();
      clearBienEditor();
      Swal.fire({ icon: 'success', title: isUpdate ? 'Bien actualizado' : 'Bien agregado', timer: 900, showConfirmButton: false });
    } catch (e) {
      Swal.fire({ icon: 'error', title: 'No fue posible agregar bien', text: e.message || 'Error inesperado' });
    }
  });

  q('btn_clear_bien').addEventListener('click', clearBienEditor);
  q('bien_list').addEventListener('click', ev => {
    const btnEdit = ev.target.closest('[data-edit-bien]');
    if (btnEdit) {
      const index = Number(btnEdit.getAttribute('data-edit-bien'));
      if (!Number.isInteger(index) || index < 0 || !bienesOperacion[index]) return;
      const b = bienesOperacion[index];
      q('bien_tipo').value = String(b.tipo_bien || '1');
      q('bien_unidad').value = String(b.unidad_comercializada || '1');
      q('bien_cantidad').value = String(b.cantidad_comercializada || '1.00');
      bienEditIndex = index;
      setBienActionButton();
      renderBienesList();
      return;
    }
    const btnRemove = ev.target.closest('[data-remove-bien]');
    if (!btnRemove) return;
    const index = Number(btnRemove.getAttribute('data-remove-bien'));
    if (!Number.isInteger(index) || index < 0) return;
    bienesOperacion = bienesOperacion.filter((_, i) => i !== index);
    if (bienEditIndex === index) bienEditIndex = null;
    else if (bienEditIndex !== null && bienEditIndex > index) bienEditIndex--;
    setBienActionButton();
    renderBienesList();
  });

  q('btn_add_liq').addEventListener('click', () => {
    try {
      const isUpdate = liqEditIndex !== null && !!liquidaciones[liqEditIndex];
      validateLiqEditor();
      if (isUpdate) liquidaciones[liqEditIndex] = buildLiqFromEditor();
      else liquidaciones.push(buildLiqFromEditor());
      renderLiqList();
      clearLiqEditor();
      Swal.fire({ icon: 'success', title: isUpdate ? 'Liquidacion actualizada' : 'Liquidacion agregada', timer: 900, showConfirmButton: false });
    } catch (e) {
      Swal.fire({ icon: 'error', title: 'No fue posible agregar liquidacion', text: e.message || 'Error inesperado' });
    }
  });

  q('btn_clear_liq').addEventListener('click', clearLiqEditor);
  q('liq_list').addEventListener('click', ev => {
    const btnEdit = ev.target.closest('[data-edit-liq]');
    if (btnEdit) {
      const index = Number(btnEdit.getAttribute('data-edit-liq'));
      if (!Number.isInteger(index) || index < 0 || !liquidaciones[index]) return;
      const l = liquidaciones[index];
      if (l.fecha_pago && String(l.fecha_pago).length === 8) q('liq_fecha_pago').value = `${l.fecha_pago.slice(0,4)}-${l.fecha_pago.slice(4,6)}-${l.fecha_pago.slice(6,8)}`;
      q('liq_forma_pago').value = String(l.forma_pago || '');
      q('liq_instrumento').value = String(l.instrumento_monetario || '');
      q('liq_moneda').value = String(l.moneda || '');
      q('liq_monto').value = String(l.monto_operacion || '0.00');
      liqEditIndex = index;
      setLiqActionButton();
      renderLiqList();
      return;
    }
    const btnRemove = ev.target.closest('[data-remove-liq]');
    if (!btnRemove) return;
    const index = Number(btnRemove.getAttribute('data-remove-liq'));
    if (!Number.isInteger(index) || index < 0) return;
    liquidaciones = liquidaciones.filter((_, i) => i !== index);
    if (liqEditIndex === index) liqEditIndex = null;
    else if (liqEditIndex !== null && liqEditIndex > index) liqEditIndex--;
    setLiqActionButton();
    renderLiqList();
  });

  tgPersona(); tgDom(); tgMod(); tgDb(); tgDbTipo();
  setPersonaActionButton(); setBienActionButton(); setLiqActionButton();
  renderPersonasList(); renderBienesList(); renderLiqList();
  q('liq_fecha_pago').value = todayYmd(); q('op_fecha').value = todayYmd();

  q('formMJR').addEventListener('submit', async ev => {
    ev.preventDefault();
    try {
      validateGeneral();
      const p = payload();
      Swal.fire({ title: 'Registrando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
      const r = await fetch('api/registrar_aviso_mjr.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(p) });
      const j = await r.json();
      if (!r.ok || j.status !== 'success') throw new Error(j.message || 'No fue posible registrar Aviso MJR.');
      await Swal.fire({ icon: 'success', title: 'Aviso MJR registrado', text: `Operacion #${j.id_operacion}` });
      window.location = 'operaciones_pld.php';
    } catch (e) {
      Swal.fire({ icon: 'error', title: 'No fue posible registrar', text: e.message || 'Error inesperado' });
    }
  });
});
</script>
<?php include 'templates/footer.php'; ?>

