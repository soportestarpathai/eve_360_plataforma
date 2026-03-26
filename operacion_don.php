<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/don_catalogos.php';

requireModuleActive($pdo, 'pld');
if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!function_exists('userCanAccessDON') || !userCanAccessDON($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_don');
    exit;
}

$page_title = 'Aviso DON - Fraccion XIII';
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
  --don-primary:#16a34a;
  --don-primary-dark:#15803d;
  --don-info:#0891b2;
  --don-warning:#d97706;
  --don-success:#059669;
  --don-dark:#14532d;
  --don-light:#f0fdf4;
  --don-border:#bbf7d0;
  --don-shadow:0 4px 24px rgba(0,0,0,.06);
  --don-radius:16px;
  --don-radius-sm:10px;
  --don-transition:.25s cubic-bezier(.4,0,.2,1);
  --don-max-width:960px;
}
.don-wrapper{max-width:var(--don-max-width);margin:0 auto}
.don-page-header{
  background:linear-gradient(135deg,var(--don-primary),var(--don-primary-dark));
  color:#fff;border-radius:var(--don-radius);padding:1.75rem 2rem;margin-bottom:1.75rem;
}
.don-page-header h2{font-size:1.5rem;font-weight:800;margin-bottom:.25rem}
.don-page-header p{opacity:.9;margin:0}
.don-page-header .btn-outline-light{border:1.5px solid rgba(255,255,255,.55);color:#fff}
.don-page-header .btn-outline-light:hover{background:rgba(255,255,255,.15);border-color:#fff}
.don-progress{display:flex;gap:0;margin-bottom:1.8rem;overflow-x:auto;padding-bottom:4px}
.don-step{flex:1;min-width:100px;text-align:center;position:relative;padding:.75rem .5rem;font-size:.78rem;font-weight:600;color:#94a3b8;transition:var(--don-transition)}
.don-step::after{content:'';position:absolute;bottom:0;left:0;width:100%;height:3px;background:#e2e8f0;border-radius:3px;transition:var(--don-transition)}
.don-step.active{color:var(--don-primary)}
.don-step.active::after{background:var(--don-primary)}
.don-step-num{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-size:.75rem;font-weight:700;background:#e2e8f0;color:#64748b;margin-bottom:4px;transition:var(--don-transition)}
.don-step.active .don-step-num{background:var(--don-primary);color:#fff}
.don-card{border:none;border-radius:var(--don-radius);background:#fff;box-shadow:var(--don-shadow);margin-bottom:1.5rem;overflow:hidden;transition:var(--don-transition)}
.don-card:hover{box-shadow:0 8px 32px rgba(0,0,0,.09)}
.don-card-header{padding:1rem 1.5rem;display:flex;align-items:center;gap:.75rem;cursor:pointer;user-select:none;border-bottom:1px solid transparent;transition:var(--don-transition)}
.don-card-header:hover{background:rgba(0,0,0,.015)}
.don-card-body{padding:1.25rem 1.5rem}
.don-icon{width:40px;height:40px;border-radius:var(--don-radius-sm);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;flex-shrink:0}
.don-icon-cliente{background:linear-gradient(135deg,var(--don-primary),var(--don-primary-dark))}
.don-icon-aviso{background:linear-gradient(135deg,var(--don-warning),#b45309)}
.don-icon-persona{background:linear-gradient(135deg,var(--don-success),#047857)}
.don-icon-benef{background:linear-gradient(135deg,var(--don-info),#0e7490)}
.don-icon-detalle{background:linear-gradient(135deg,#7c3aed,#5b21b6)}
.don-card-header h5{margin:0;font-size:1rem;font-weight:700;color:var(--don-dark)}
.don-card-header small{color:#94a3b8;font-size:.78rem;font-weight:400;display:block}
.don-chevron{margin-left:auto;font-size:.85rem;color:#94a3b8;transition:var(--don-transition)}
.don-card-header.collapsed .don-chevron{transform:rotate(-90deg)}
.don-subcard{border-left:3px solid var(--don-primary);background:var(--don-light);border-radius:0 var(--don-radius-sm) var(--don-radius-sm) 0;padding:1rem 1rem .25rem;margin-top:1rem}
.don-submit-bar{position:sticky;bottom:0;background:#fff;padding:1rem 1.5rem;border-top:1px solid #e2e8f0;border-radius:var(--don-radius) var(--don-radius) 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.06);z-index:10;display:flex;justify-content:flex-end;align-items:center;gap:.75rem;flex-wrap:wrap}
.don-submit-bar .btn-primary{background:linear-gradient(135deg,var(--don-primary),var(--don-primary-dark));border:none;padding:.7rem 1.6rem;font-weight:700;border-radius:var(--don-radius-sm);box-shadow:0 4px 14px rgba(22,163,74,.25)}
.don-submit-bar .btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(22,163,74,.35)}
.don-hidden{display:none!important}
.don-note{font-size:.8rem;color:#64748b}
.don-person-item{border:1px solid #d1fae5;background:#f0fdf4;border-radius:10px;padding:10px 12px;margin-bottom:8px}
.don-card .form-control::placeholder,
.don-card .form-select::placeholder{color:#94a3b8;opacity:1}
</style>
</head>
<body>
<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="content-wrapper">
<div class="don-wrapper">
  <div class="don-page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div>
        <h2><i class="fa-solid fa-hand-holding-heart me-2"></i>Aviso DON</h2>
        <p>Fracción XIII - Donativos</p>
      </div>
      <a class="btn btn-outline-light btn-sm" href="operaciones_pld.php"><i class="fa-solid fa-arrow-left me-1"></i> Volver</a>
    </div>
  </div>

  <div class="don-progress" aria-label="Progreso DON">
    <div class="don-step active"><div class="don-step-num">1</div><div>Cliente</div></div>
    <div class="don-step active"><div class="don-step-num">2</div><div>Aviso</div></div>
    <div class="don-step active"><div class="don-step-num">3</div><div>Persona</div></div>
    <div class="don-step active"><div class="don-step-num">4</div><div>Beneficiario</div></div>
    <div class="don-step active"><div class="don-step-num">5</div><div>Detalle DON</div></div>
  </div>

  <div class="alert alert-info">Umbral de identificacion: <code>1,605 UMA</code>. Umbral de aviso/acumulacion: <code>3,210 UMA</code>.</div>

<form id="formDON" novalidate>
  <div class="don-card" id="sec-cliente">
    <div class="don-card-header" onclick="toggleDonCard(this)">
      <div class="don-icon don-icon-cliente"><i class="fa-solid fa-user"></i></div>
      <div><h5>Cliente e informe</h5><small>Datos generales del sujeto obligado</small></div>
      <i class="fa-solid fa-chevron-down don-chevron"></i>
    </div>
    <div class="don-card-body">
    <div class="row g-3">
      <div class="col-xl-5 col-lg-6 col-md-12"><label class="form-label">Cliente *</label><select id="id_cliente" class="form-select" required><option value="">-- Seleccione cliente --</option></select></div>
      <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Mes reportado *</label><input id="mes_reportado" class="form-control" maxlength="6" value="<?= date('Ym') ?>" placeholder="202603" inputmode="numeric" required></div>
      <div class="col-xl-3 col-lg-5 col-md-8"><label class="form-label">Clave sujeto obligado *</label><input id="clave_sujeto_obligado" class="form-control text-uppercase" maxlength="13" value="<?= htmlspecialchars($claveSO) ?>" placeholder="ABCD900101XY1" required></div>
      <div class="col-xl-2 col-lg-2 col-md-4"><label class="form-label">Actividad</label><input class="form-control" value="DON" readonly></div>
      <div class="col-xl-2 col-lg-3 col-md-4"><label class="form-label">Exento</label><select id="exento" class="form-select"><?= donCatalogoOptions('exento', '0', null, false) ?></select></div>
      <div class="col-xl-4 col-lg-5 col-md-8"><label class="form-label">Clave entidad colegiada</label><input id="clave_entidad_colegiada" class="form-control text-uppercase" maxlength="12" placeholder="ABC900101A1B"></div>
    </div>
    <div id="kyc_info" class="don-note mt-2"></div>
    </div>
  </div>

  <div class="don-card" id="sec-aviso">
    <div class="don-card-header" onclick="toggleDonCard(this)">
      <div class="don-icon don-icon-aviso"><i class="fa-solid fa-bell"></i></div>
      <div><h5>Datos del aviso</h5><small>Referencia, prioridad y alerta</small></div>
      <i class="fa-solid fa-chevron-down don-chevron"></i>
    </div>
    <div class="don-card-body">
    <div class="row g-3">
      <div class="col-lg-3 col-md-6"><label class="form-label">Referencia aviso *</label><input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" placeholder="REF20260001" required></div>
      <div class="col-lg-2 col-md-3"><label class="form-label">Prioridad *</label><select id="prioridad" class="form-select" required><?= donCatalogoOptions('prioridad', '1', null, false) ?></select></div>
      <div class="col-lg-3 col-md-6"><label class="form-label">Tipo alerta *</label><select id="tipo_alerta" class="form-select" required><?= donCatalogoOptions('tipo_alerta', '100') ?></select></div>
      <div class="col-lg-4 col-md-12"><label class="form-label">Descripcion alerta</label><input id="descripcion_alerta" class="form-control text-uppercase" maxlength="3000" placeholder="Ej. MOVIMIENTOS INUSUALES DEL DONANTE"></div>
    </div>
    <div class="form-check mt-3"><input id="es_modificatorio" class="form-check-input" type="checkbox"><label class="form-check-label" for="es_modificatorio">Aviso modificatorio</label></div>
    <div id="modif_wrap" class="row g-3 mt-1 don-hidden">
      <div class="col-lg-4 col-md-6"><label class="form-label">Folio modificacion *</label><input id="folio_modificacion" class="form-control text-uppercase" maxlength="14" placeholder="2026-12345"></div>
      <div class="col-lg-8 col-md-12"><label class="form-label">Descripcion modificacion *</label><input id="descripcion_modificacion" class="form-control text-uppercase" maxlength="3000" placeholder="Ej. CORRECCION DE DATOS DEL DONANTE"></div>
    </div>
    </div>
  </div>

  <div class="don-card" id="sec-persona">
    <div class="don-card-header" onclick="toggleDonCard(this)">
      <div class="don-icon don-icon-persona"><i class="fa-solid fa-users"></i></div>
      <div><h5>Persona aviso, domicilio y contacto</h5><small>Captura de una o varias personas del aviso</small></div>
      <i class="fa-solid fa-chevron-down don-chevron"></i>
    </div>
    <div class="don-card-body">
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
        <div class="col-lg-3 col-md-4"><label class="form-label">Pais *</label><select id="pf_pais" class="form-select"><?= donCatalogoOptions('pais', 'MX') ?></select></div>
        <div class="col-lg-6 col-md-8"><label class="form-label">Actividad economica *</label><select id="pf_act" class="form-select"><?= donCatalogoOptions('actividad_economica', '1000000') ?></select></div>
      </div>
    </div>

    <div id="pm_wrap" class="mt-3 don-hidden">
      <div class="row g-3">
        <div class="col-lg-6 col-md-8"><label class="form-label">Denominacion / Razon *</label><input id="pm_den" class="form-control text-uppercase" placeholder="ASOCIACION DONA VIDA A.C."></div>
        <div class="col-lg-3 col-md-4"><label class="form-label">Fecha constitucion</label><input id="pm_fc" type="date" class="form-control" placeholder="2018-05-16"></div>
        <div class="col-lg-3 col-md-4"><label class="form-label">RFC</label><input id="pm_rfc" class="form-control text-uppercase" maxlength="12" placeholder="ASF950516ABC"></div>
        <div class="col-lg-3 col-md-4"><label class="form-label">Pais *</label><select id="pm_pais" class="form-select"><?= donCatalogoOptions('pais', 'MX') ?></select></div>
        <div class="col-lg-6 col-md-8"><label class="form-label">Giro mercantil *</label><select id="pm_giro" class="form-select"><?= donCatalogoOptions('giro_mercantil', '1000000') ?></select></div>
      </div>
      <div class="don-subcard">
        <h6 class="mb-2">Representante / Apoderado</h6>
        <div class="row g-3">
          <div class="col-lg-4 col-md-6"><label class="form-label">Nombre *</label><input id="pm_rn" class="form-control text-uppercase" placeholder="JUAN MANUEL"></div>
          <div class="col-lg-4 col-md-6"><label class="form-label">Apellido paterno *</label><input id="pm_rap" class="form-control text-uppercase" placeholder="LOPEZ"></div>
          <div class="col-lg-4 col-md-6"><label class="form-label">Apellido materno *</label><input id="pm_ram" class="form-control text-uppercase" placeholder="MARTINEZ"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">Fecha nacimiento</label><input id="pm_rfn" type="date" class="form-control" placeholder="1988-03-22"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">RFC</label><input id="pm_rrfc" class="form-control text-uppercase" maxlength="13" placeholder="LOMJ880322AB1"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">CURP</label><input id="pm_rcurp" class="form-control text-uppercase" maxlength="18" placeholder="LOMJ880322HDFPRN01"></div>
        </div>
      </div>
    </div>

    <div id="fi_wrap" class="mt-3 don-hidden">
      <div class="row g-3">
        <div class="col-lg-6 col-md-8"><label class="form-label">Denominacion fiduciario *</label><input id="fi_den" class="form-control text-uppercase" placeholder="FIDEICOMISO APOYO SOCIAL"></div>
        <div class="col-lg-3 col-md-4"><label class="form-label">RFC fideicomiso</label><input id="fi_rfc" class="form-control text-uppercase" maxlength="12" placeholder="FID901010ABC"></div>
        <div class="col-lg-3 col-md-4"><label class="form-label">Identificador fideicomiso</label><input id="fi_id" class="form-control text-uppercase" maxlength="40" placeholder="FID-2026-001"></div>
      </div>
      <div class="don-subcard">
        <h6 class="mb-2">Apoderado / Delegado fiduciario</h6>
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
        <div class="col-lg-4 col-md-6"><label class="form-label">Colonia *</label><input id="dn_col" class="form-control text-uppercase" placeholder="AXOTLA"></div>
        <div class="col-lg-4 col-md-6"><label class="form-label">Calle *</label><input id="dn_calle" class="form-control text-uppercase" placeholder="INSURGENTES SUR"></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Numero exterior *</label><input id="dn_ne" class="form-control text-uppercase" placeholder="785"></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Numero interior</label><input id="dn_ni" class="form-control text-uppercase" placeholder="B45"></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Codigo postal *</label><input id="dn_cp" class="form-control" maxlength="5" placeholder="01030" inputmode="numeric"></div>
      </div>
    </div>

    <div id="dom_ext" class="don-hidden">
      <div class="row g-3">
        <div class="col-lg-3 col-md-6"><label class="form-label">Pais *</label><select id="de_pais" class="form-select"><?= donCatalogoOptions('pais', 'US') ?></select></div>
        <div class="col-lg-3 col-md-6"><label class="form-label">Estado / Provincia *</label><input id="de_est" class="form-control text-uppercase" placeholder="MUNICH"></div>
        <div class="col-lg-3 col-md-6"><label class="form-label">Ciudad / Poblacion *</label><input id="de_cd" class="form-control text-uppercase" placeholder="MUNICH"></div>
        <div class="col-lg-3 col-md-6"><label class="form-label">Colonia *</label><input id="de_col" class="form-control text-uppercase" placeholder="CENTRO"></div>
        <div class="col-lg-4 col-md-6"><label class="form-label">Calle *</label><input id="de_calle" class="form-control text-uppercase" placeholder="STURGENSSEN"></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Numero exterior *</label><input id="de_ne" class="form-control text-uppercase" placeholder="45"></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Numero interior</label><input id="de_ni" class="form-control text-uppercase" placeholder="A"></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Codigo postal *</label><input id="de_cp" class="form-control text-uppercase" maxlength="12" placeholder="115501"></div>
      </div>
    </div>

    <div class="row g-3 mt-1">
      <div class="col-md-3"><label class="form-label">Clave pais telefono</label><select id="tel_pais" class="form-select"><?= donCatalogoOptions('pais', 'MX') ?></select></div>
      <div class="col-md-3"><label class="form-label">Telefono</label><input id="tel_num" class="form-control" maxlength="12" placeholder="5512345678" inputmode="numeric"></div>
      <div class="col-md-6"><label class="form-label">Correo electronico</label><input id="tel_mail" class="form-control text-uppercase" maxlength="60" placeholder="DONANTE@MAIL.COM"></div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
      <button type="button" id="btn_add_persona" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-user-plus me-1"></i>Agregar persona al aviso</button>
      <button type="button" id="btn_clear_persona" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-eraser me-1"></i>Limpiar editor</button>
      <span class="don-note">Puedes registrar varias personas en el mismo aviso DON.</span>
    </div>
    <div id="personas_list" class="mt-3"></div>
    </div>
  </div>

  <div class="don-card" id="sec-beneficiario">
    <div class="don-card-header" onclick="toggleDonCard(this)">
      <div class="don-icon don-icon-benef"><i class="fa-solid fa-user-shield"></i></div>
      <div><h5>Dueño beneficiario (opcional)</h5><small>Información de beneficiario final del aviso</small></div>
      <i class="fa-solid fa-chevron-down don-chevron"></i>
    </div>
    <div class="don-card-body">
    <div class="form-check mb-2"><input id="db_on" class="form-check-input" type="checkbox"><label class="form-check-label" for="db_on">Capturar dueno beneficiario</label></div>
    <div id="db_wrap" class="don-hidden">
      <div class="row g-3"><div class="col-md-3"><label class="form-label">Tipo</label><select id="db_tipo" class="form-select"><option value="fisica">Fisica</option><option value="moral">Moral</option><option value="fideicomiso">Fideicomiso</option></select></div></div>
      <div id="db_fisica" class="mt-2">
        <div class="row g-3">
          <div class="col-lg-4 col-md-6"><label class="form-label">Nombre *</label><input id="db_f_nom" class="form-control text-uppercase" placeholder="RODRIGO"></div>
          <div class="col-lg-4 col-md-6"><label class="form-label">Apellido paterno *</label><input id="db_f_ap" class="form-control text-uppercase" placeholder="SUAREZ"></div>
          <div class="col-lg-4 col-md-6"><label class="form-label">Apellido materno *</label><input id="db_f_am" class="form-control text-uppercase" placeholder="MORALES"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">Fecha nacimiento</label><input id="db_f_fecha" type="date" class="form-control" placeholder="1992-11-08"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">RFC</label><input id="db_f_rfc" class="form-control text-uppercase" maxlength="13" placeholder="SUMR921108AA1"></div>
          <div class="col-lg-3 col-md-4"><label class="form-label">CURP</label><input id="db_f_curp" class="form-control text-uppercase" maxlength="18" placeholder="SUMR921108HDFRDL06"></div>
          <div class="col-md-3"><label class="form-label">Pais</label><select id="db_f_pais" class="form-select"><?= donCatalogoOptions('pais', 'MX') ?></select></div>
        </div>
      </div>
      <div id="db_moral" class="mt-2 don-hidden">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Denominacion / Razon *</label><input id="db_m_den" class="form-control text-uppercase" placeholder="FUNDACION AYUDA TOTAL"></div>
          <div class="col-md-3"><label class="form-label">Fecha constitucion</label><input id="db_m_fc" type="date" class="form-control" placeholder="2010-01-15"></div>
          <div class="col-md-3"><label class="form-label">RFC</label><input id="db_m_rfc" class="form-control text-uppercase" maxlength="12" placeholder="FAT100115AB1"></div>
          <div class="col-md-3"><label class="form-label">Pais</label><select id="db_m_pais" class="form-select"><?= donCatalogoOptions('pais', 'MX') ?></select></div>
        </div>
      </div>
      <div id="db_fide" class="mt-2 don-hidden">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Denominacion / Razon *</label><input id="db_t_den" class="form-control text-uppercase" placeholder="FIDEICOMISO BENEFICIARIO"></div>
          <div class="col-md-3"><label class="form-label">RFC</label><input id="db_t_rfc" class="form-control text-uppercase" maxlength="12" placeholder="FIB901201ABC"></div>
          <div class="col-md-3"><label class="form-label">Identificador *</label><input id="db_t_id" class="form-control text-uppercase" maxlength="40" placeholder="FDB-2026-009"></div>
        </div>
      </div>
    </div>
    </div>
  </div>

  <div class="don-card" id="sec-detalle">
    <div class="don-card-header" onclick="toggleDonCard(this)">
      <div class="don-icon don-icon-detalle"><i class="fa-solid fa-file-invoice-dollar"></i></div>
      <div><h5>Detalle de operación DON</h5><small>Datos de la operación y tipo de donativo</small></div>
      <i class="fa-solid fa-chevron-down don-chevron"></i>
    </div>
    <div class="don-card-body">
    <div class="row g-3">
      <div class="col-lg-3 col-md-4"><label class="form-label">Fecha operacion *</label><input id="op_fecha" type="date" class="form-control" value="<?= date('Y-m-d') ?>" placeholder="2026-03-24"></div>
      <div class="col-lg-3 col-md-4"><label class="form-label">CP sucursal *</label><input id="op_cp" class="form-control" maxlength="5" placeholder="01030" inputmode="numeric"></div>
      <div class="col-lg-4 col-md-6"><label class="form-label">Tipo operacion *</label><select id="op_tipo" class="form-select"><?= donCatalogoOptions('tipo_operacion', '1301') ?></select></div>
    </div>
    <div class="row g-3 mt-1"><div class="col-lg-3 col-md-4"><label class="form-label">Clase donativo *</label><select id="don_clase" class="form-select"><option value="numerario">Numerario</option><option value="especie">Especie</option></select></div></div>
    <div id="don_num" class="mt-2">
      <div class="row g-3">
        <div class="col-lg-3 col-md-4"><label class="form-label">Fecha pago *</label><input id="num_fp" type="date" class="form-control" value="<?= date('Y-m-d') ?>" placeholder="2026-03-24"></div>
        <div class="col-lg-3 col-md-4"><label class="form-label">Instrumento monetario *</label><select id="num_inst" class="form-select"><?= donCatalogoOptions('instrumento_monetario') ?></select></div>
        <div class="col-lg-3 col-md-4"><label class="form-label">Moneda *</label><select id="num_moneda" class="form-select"><?= donCatalogoOptions('moneda', '147') ?></select></div>
        <div class="col-lg-3 col-md-4"><label class="form-label">Monto *</label><input id="num_monto" type="number" class="form-control" step="0.01" min="0.01" value="0.00" placeholder="175847.00"></div>
      </div>
    </div>
    <div id="don_esp" class="mt-2 don-hidden">
      <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Monto especie *</label><input id="esp_monto" type="number" class="form-control" step="0.01" min="0.01" value="0.00" placeholder="14500.00"></div>
        <div class="col-md-3"><label class="form-label">Moneda *</label><select id="esp_moneda" class="form-select"><?= donCatalogoOptions('moneda', '147') ?></select></div>
        <div class="col-md-3"><label class="form-label">Bien donado *</label><select id="esp_bien" class="form-select"><?= donCatalogoOptions('bien_donado') ?></select></div>
      </div>
      <div id="inm_wrap" class="row g-3 mt-1 don-hidden">
        <div class="col-md-3"><label class="form-label">Tipo inmueble *</label><select id="inm_tipo" class="form-select"><?= donCatalogoOptions('tipo_inmueble') ?></select></div>
        <div class="col-md-3"><label class="form-label">CP inmueble *</label><input id="inm_cp" class="form-control" maxlength="5" placeholder="01030" inputmode="numeric"></div>
        <div class="col-md-6"><label class="form-label">Folio real / antecedentes *</label><input id="inm_folio" class="form-control text-uppercase" maxlength="200" placeholder="Si no existe, capture XXXX"></div>
      </div>
      <div id="otro_wrap" class="mt-2 don-hidden"><label class="form-label">Descripcion del bien donado *</label><textarea id="otro_desc" class="form-control text-uppercase" rows="2" maxlength="3000" placeholder="EJ. MONUMENTO DE BRONCE"></textarea></div>
    </div>
    </div>
  </div>

  <div class="don-submit-bar mb-4">
    <a href="operaciones_pld.php" class="btn btn-outline-secondary">Cancelar</a>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Registrar aviso DON</button>
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
const isPhone = x => /^\d{10}(\d{2})?$/.test(String(x || '').replace(/\D+/g, ''));
const isMonth6 = x => /^[2-9]\d{3}(0[1-9]|1[0-2])$/.test(x);

let personasAviso = [];
let personaEditIndex = null;

function toggleDonCard(header) {
  const card = header?.closest('.don-card');
  const body = card?.querySelector('.don-card-body');
  if (!body) return;
  const collapsed = header.classList.toggle('collapsed');
  body.style.display = collapsed ? 'none' : '';
}

function setPersonaActionButton() {
  const btn = q('btn_add_persona');
  if (!btn) return;
  if (personaEditIndex === null) {
    btn.innerHTML = '<i class="fa-solid fa-user-plus me-1"></i>Agregar persona al aviso';
    btn.classList.remove('btn-warning');
    btn.classList.add('btn-outline-success');
  } else {
    btn.innerHTML = '<i class="fa-solid fa-pen-to-square me-1"></i>Actualizar persona';
    btn.classList.remove('btn-outline-success');
    btn.classList.add('btn-warning');
  }
}

function setPersonaEditMode(index = null) {
  personaEditIndex = Number.isInteger(index) && index >= 0 ? index : null;
  setPersonaActionButton();
}

function date8ToInput(value) {
  const raw = String(value || '').replace(/\D+/g, '');
  if (raw.length !== 8) return '';
  return `${raw.slice(0, 4)}-${raw.slice(4, 6)}-${raw.slice(6, 8)}`;
}

function deepClone(obj) {
  return JSON.parse(JSON.stringify(obj));
}

function bindInputMask(id, formatter) {
  const el = q(id);
  if (!el || typeof formatter !== 'function') return;
  const apply = () => {
    const old = el.value;
    const next = formatter(old);
    if (old !== next) el.value = next;
  };
  el.addEventListener('input', apply);
  el.addEventListener('blur', apply);
  apply();
}

function setupInputMasks() {
  const digits = max => value => String(value || '').replace(/\D+/g, '').slice(0, max);
  const upperAllowed = (max, regex) => value => up(value).replace(regex, '').slice(0, max);

  bindInputMask('mes_reportado', digits(6));
  bindInputMask('op_cp', digits(5));
  bindInputMask('dn_cp', digits(5));
  bindInputMask('inm_cp', digits(5));
  bindInputMask('tel_num', digits(12));

  bindInputMask('de_cp', upperAllowed(12, /[^A-Z0-9Ñ]/g));
  bindInputMask('folio_modificacion', upperAllowed(14, /[^0-9-]/g));

  bindInputMask('clave_sujeto_obligado', upperAllowed(13, /[^A-Z0-9Ñ&]/g));
  bindInputMask('referencia_aviso', upperAllowed(14, /[^A-Z0-9Ñ]/g));
  bindInputMask('clave_entidad_colegiada', upperAllowed(12, /[^A-Z0-9Ñ&]/g));

  ['pf_rfc', 'pm_rrfc', 'fi_arfc', 'db_f_rfc'].forEach(id => bindInputMask(id, upperAllowed(13, /[^A-Z0-9Ñ&]/g)));
  ['pm_rfc', 'fi_rfc', 'db_m_rfc', 'db_t_rfc'].forEach(id => bindInputMask(id, upperAllowed(12, /[^A-Z0-9Ñ&]/g)));
  ['pf_curp', 'pm_rcurp', 'fi_acurp', 'db_f_curp'].forEach(id => bindInputMask(id, upperAllowed(18, /[^A-Z0-9]/g)));

  ['pf_nombre', 'pf_ap', 'pf_am', 'pm_den', 'pm_rn', 'pm_rap', 'pm_ram', 'fi_den', 'fi_an', 'fi_aap', 'fi_aam',
   'dn_col', 'dn_calle', 'dn_ne', 'dn_ni', 'de_est', 'de_cd', 'de_col', 'de_calle', 'de_ne', 'de_ni',
   'db_f_nom', 'db_f_ap', 'db_f_am', 'db_m_den', 'db_t_den', 'db_t_id', 'inm_folio']
    .forEach(id => bindInputMask(id, value => up(value)));

  bindInputMask('tel_mail', upperAllowed(60, /[^A-Z0-9@._'\-]/g));
  bindInputMask('descripcion_alerta', value => up(value).slice(0, 3000));
  bindInputMask('descripcion_modificacion', value => up(value).slice(0, 3000));
  bindInputMask('otro_desc', value => up(value).slice(0, 3000));
}

function tgPersona() {
  const t = v('persona_tipo');
  q('pf_wrap').classList.toggle('don-hidden', t !== 'fisica');
  q('pm_wrap').classList.toggle('don-hidden', t !== 'moral');
  q('fi_wrap').classList.toggle('don-hidden', t !== 'fideicomiso');
}
function tgDom() {
  const t = v('domicilio_tipo');
  q('dom_nac').classList.toggle('don-hidden', t !== 'nacional');
  q('dom_ext').classList.toggle('don-hidden', t !== 'extranjero');
}
function tgMod() { q('modif_wrap').classList.toggle('don-hidden', !q('es_modificatorio').checked); }
function tgClase() {
  const t = v('don_clase');
  q('don_num').classList.toggle('don-hidden', t !== 'numerario');
  q('don_esp').classList.toggle('don-hidden', t !== 'especie');
}
function tgBien() {
  const b = v('esp_bien');
  q('inm_wrap').classList.toggle('don-hidden', b !== '1');
  q('otro_wrap').classList.toggle('don-hidden', b !== '99');
}
function tgDb() { q('db_wrap').classList.toggle('don-hidden', !q('db_on').checked); }
function tgDbTipo() {
  const t = v('db_tipo');
  q('db_fisica').classList.toggle('don-hidden', t !== 'fisica');
  q('db_moral').classList.toggle('don-hidden', t !== 'moral');
  q('db_fide').classList.toggle('don-hidden', t !== 'fideicomiso');
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
  if (!id) {
    q('kyc_info').textContent = '';
    return;
  }
  const r = await fetch('api/get_cliente_kyc_pld.php?id=' + encodeURIComponent(id));
  const d = await r.json();
  if (d?.status !== 'success') {
    q('kyc_info').textContent = '';
    return;
  }
  const k = d.kyc || {};
  const nombre = up(k.denominacion_razon || k.razon_social || [k.nombre, k.apellido_paterno, k.apellido_materno].filter(Boolean).join(' '));
  q('kyc_info').innerHTML = `<strong>KYC:</strong> ${up(k.tipo_persona || '')} | ${nombre || '-'} | RFC: ${up(k.rfc || '-')}`;
  fillFromKyc(k);
}

function personaObjFromEditor() {
  const t = v('persona_tipo');
  if (t === 'fisica') {
    return {
      persona_fisica: {
        nombre: up(v('pf_nombre')),
        apellido_paterno: up(v('pf_ap')),
        apellido_materno: up(v('pf_am')),
        fecha_nacimiento: d8(v('pf_fn')),
        rfc: up(v('pf_rfc')),
        curp: up(v('pf_curp')),
        pais_nacionalidad: up(v('pf_pais')),
        actividad_economica: v('pf_act')
      }
    };
  }
  if (t === 'moral') {
    return {
      persona_moral: {
        denominacion_razon: up(v('pm_den')),
        fecha_constitucion: d8(v('pm_fc')),
        rfc: up(v('pm_rfc')),
        pais_nacionalidad: up(v('pm_pais')),
        giro_mercantil: v('pm_giro'),
        representante_apoderado: {
          nombre: up(v('pm_rn')),
          apellido_paterno: up(v('pm_rap')),
          apellido_materno: up(v('pm_ram')),
          fecha_nacimiento: d8(v('pm_rfn')),
          rfc: up(v('pm_rrfc')),
          curp: up(v('pm_rcurp'))
        }
      }
    };
  }
  return {
    fideicomiso: {
      denominacion_razon: up(v('fi_den')),
      rfc: up(v('fi_rfc')),
      identificador_fideicomiso: up(v('fi_id')),
      apoderado_delegado: {
        nombre: up(v('fi_an')),
        apellido_paterno: up(v('fi_aap')),
        apellido_materno: up(v('fi_aam')),
        fecha_nacimiento: d8(v('fi_afn')),
        rfc: up(v('fi_arfc')),
        curp: up(v('fi_acurp'))
      }
    }
  };
}

function domObjFromEditor() {
  if (v('domicilio_tipo') === 'extranjero') {
    return {
      extranjero: {
        pais: up(v('de_pais')),
        estado_provincia: up(v('de_est')),
        ciudad_poblacion: up(v('de_cd')),
        colonia: up(v('de_col')),
        calle: up(v('de_calle')),
        numero_exterior: up(v('de_ne')),
        numero_interior: up(v('de_ni')),
        codigo_postal: up(v('de_cp'))
      }
    };
  }
  return {
    nacional: {
      colonia: up(v('dn_col')),
      calle: up(v('dn_calle')),
      numero_exterior: up(v('dn_ne')),
      numero_interior: up(v('dn_ni')),
      codigo_postal: v('dn_cp')
    }
  };
}

function buildPersonaAvisoFromEditor() {
  const pa = { tipo_persona: personaObjFromEditor(), tipo_domicilio: domObjFromEditor() };
  const telNum = v('tel_num');
  const telMail = up(v('tel_mail'));
  if (telNum || telMail) {
    pa.telefono = { clave_pais: up(v('tel_pais')), numero_telefono: telNum, correo_electronico: telMail };
  }
  return pa;
}

function personaEditorHasData() {
  const ids = [
    'pf_nombre','pf_ap','pf_am','pf_rfc','pf_curp','pm_den','pm_rfc','pm_rn','pm_rap','pm_ram',
    'fi_den','fi_rfc','fi_id','fi_an','fi_aap','fi_aam','dn_col','dn_calle','dn_ne','dn_cp',
    'de_est','de_cd','de_col','de_calle','de_ne','de_cp','tel_num','tel_mail'
  ];
  return ids.some(id => v(id) !== '');
}

function validatePersonaEditor() {
  if (v('persona_tipo') === 'fisica') {
    if (!v('pf_nombre') || !v('pf_ap') || !v('pf_am')) throw new Error('Persona física incompleta.');
    if (!v('pf_pais') || !v('pf_act')) throw new Error('Persona física requiere país y actividad económica.');
  } else if (v('persona_tipo') === 'moral') {
    if (!v('pm_den') || !v('pm_pais') || !v('pm_giro')) throw new Error('Persona moral incompleta.');
    if (!v('pm_rn') || !v('pm_rap') || !v('pm_ram')) throw new Error('Capture representante de persona moral.');
  } else {
    if (!v('fi_den')) throw new Error('Fideicomiso requiere denominación.');
    if (!v('fi_an') || !v('fi_aap') || !v('fi_aam')) throw new Error('Capture apoderado/delegado del fideicomiso.');
  }

  if (v('domicilio_tipo') === 'nacional') {
    if (!v('dn_col') || !v('dn_calle') || !v('dn_ne') || !isCP(v('dn_cp'))) throw new Error('Domicilio nacional incompleto o CP inválido.');
  } else {
    if (!v('de_pais') || !v('de_est') || !v('de_cd') || !v('de_col') || !v('de_calle') || !v('de_ne')) throw new Error('Domicilio extranjero incompleto.');
    if (!/^[A-Z0-9]{4,12}$/.test(up(v('de_cp')))) throw new Error('Código postal extranjero inválido.');
  }

  if (v('tel_num') || v('tel_mail')) {
    if (!v('tel_pais') || !isPhone(v('tel_num'))) throw new Error('Teléfono inválido (10 o 12 dígitos).');
  }
}

function clearPersonaEditor() {
  const textIds = [
    'pf_nombre','pf_ap','pf_am','pf_fn','pf_rfc','pf_curp',
    'pm_den','pm_fc','pm_rfc','pm_rn','pm_rap','pm_ram','pm_rfn','pm_rrfc','pm_rcurp',
    'fi_den','fi_rfc','fi_id','fi_an','fi_aap','fi_aam','fi_afn','fi_arfc','fi_acurp',
    'dn_col','dn_calle','dn_ne','dn_ni','dn_cp',
    'de_est','de_cd','de_col','de_calle','de_ne','de_ni','de_cp',
    'tel_num','tel_mail'
  ];
  textIds.forEach(id => { if (q(id)) q(id).value = ''; });

  q('persona_tipo').value = 'fisica';
  q('domicilio_tipo').value = 'nacional';
  q('pf_pais').value = 'MX';
  q('pm_pais').value = 'MX';
  q('de_pais').value = 'US';
  q('tel_pais').value = 'MX';
  tgPersona();
  tgDom();
  setPersonaEditMode(null);
}

function loadPersonaToEditor(pa) {
  if (!pa || typeof pa !== 'object') return;
  clearPersonaEditor();

  const tp = pa.tipo_persona || {};
  if (tp.persona_fisica) {
    const p = tp.persona_fisica;
    q('persona_tipo').value = 'fisica';
    q('pf_nombre').value = p.nombre || '';
    q('pf_ap').value = p.apellido_paterno || '';
    q('pf_am').value = p.apellido_materno || '';
    q('pf_fn').value = date8ToInput(p.fecha_nacimiento);
    q('pf_rfc').value = p.rfc || '';
    q('pf_curp').value = p.curp || '';
    if (p.pais_nacionalidad) q('pf_pais').value = p.pais_nacionalidad;
    if (p.actividad_economica) q('pf_act').value = p.actividad_economica;
  } else if (tp.persona_moral) {
    const p = tp.persona_moral;
    q('persona_tipo').value = 'moral';
    q('pm_den').value = p.denominacion_razon || '';
    q('pm_fc').value = date8ToInput(p.fecha_constitucion);
    q('pm_rfc').value = p.rfc || '';
    if (p.pais_nacionalidad) q('pm_pais').value = p.pais_nacionalidad;
    if (p.giro_mercantil) q('pm_giro').value = p.giro_mercantil;
    const rep = p.representante_apoderado || {};
    q('pm_rn').value = rep.nombre || '';
    q('pm_rap').value = rep.apellido_paterno || '';
    q('pm_ram').value = rep.apellido_materno || '';
    q('pm_rfn').value = date8ToInput(rep.fecha_nacimiento);
    q('pm_rrfc').value = rep.rfc || '';
    q('pm_rcurp').value = rep.curp || '';
  } else if (tp.fideicomiso) {
    const p = tp.fideicomiso;
    q('persona_tipo').value = 'fideicomiso';
    q('fi_den').value = p.denominacion_razon || '';
    q('fi_rfc').value = p.rfc || '';
    q('fi_id').value = p.identificador_fideicomiso || '';
    const ap = p.apoderado_delegado || {};
    q('fi_an').value = ap.nombre || '';
    q('fi_aap').value = ap.apellido_paterno || '';
    q('fi_aam').value = ap.apellido_materno || '';
    q('fi_afn').value = date8ToInput(ap.fecha_nacimiento);
    q('fi_arfc').value = ap.rfc || '';
    q('fi_acurp').value = ap.curp || '';
  }

  const td = pa.tipo_domicilio || {};
  if (td.extranjero) {
    const d = td.extranjero;
    q('domicilio_tipo').value = 'extranjero';
    if (d.pais) q('de_pais').value = d.pais;
    q('de_est').value = d.estado_provincia || '';
    q('de_cd').value = d.ciudad_poblacion || '';
    q('de_col').value = d.colonia || '';
    q('de_calle').value = d.calle || '';
    q('de_ne').value = d.numero_exterior || '';
    q('de_ni').value = d.numero_interior || '';
    q('de_cp').value = d.codigo_postal || '';
  } else if (td.nacional) {
    const d = td.nacional;
    q('domicilio_tipo').value = 'nacional';
    q('dn_col').value = d.colonia || '';
    q('dn_calle').value = d.calle || '';
    q('dn_ne').value = d.numero_exterior || '';
    q('dn_ni').value = d.numero_interior || '';
    q('dn_cp').value = d.codigo_postal || '';
  }

  const tel = pa.telefono || {};
  if (tel.clave_pais) q('tel_pais').value = tel.clave_pais;
  q('tel_num').value = tel.numero_telefono || '';
  q('tel_mail').value = tel.correo_electronico || '';

  tgPersona();
  tgDom();
}

function resumenPersona(pa, index) {
  const tp = pa.tipo_persona || {};
  if (tp.persona_fisica) {
    const p = tp.persona_fisica;
    return `#${index + 1} Física: ${p.nombre} ${p.apellido_paterno} ${p.apellido_materno}`.trim();
  }
  if (tp.persona_moral) {
    return `#${index + 1} Moral: ${tp.persona_moral.denominacion_razon}`;
  }
  if (tp.fideicomiso) {
    return `#${index + 1} Fideicomiso: ${tp.fideicomiso.denominacion_razon}`;
  }
  return `#${index + 1} Persona`;
}

function renderPersonasList() {
  const cont = q('personas_list');
  if (!cont) return;
  if (personasAviso.length === 0) {
    cont.innerHTML = '<div class="don-note">No hay personas agregadas todavía. Puedes enviar solo la del editor actual o agregar varias.</div>';
    return;
  }
  cont.innerHTML = personasAviso.map((pa, i) => (
    `<div class="don-person-item d-flex justify-content-between align-items-center ${personaEditIndex === i ? 'border border-warning' : ''}">
      <div>${resumenPersona(pa, i)} ${personaEditIndex === i ? '<span class="badge bg-warning text-dark ms-2">Editando</span>' : ''}</div>
      <div class="d-flex gap-1">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-move-up="${i}" ${i === 0 ? 'disabled' : ''}>
          <i class="fa-solid fa-arrow-up"></i>
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-move-down="${i}" ${i === personasAviso.length - 1 ? 'disabled' : ''}>
          <i class="fa-solid fa-arrow-down"></i>
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary" data-edit-persona="${i}">
          <i class="fa-solid fa-pen me-1"></i>Editar
        </button>
        <button type="button" class="btn btn-sm btn-outline-info" data-dup-persona="${i}">
          <i class="fa-solid fa-copy me-1"></i>Duplicar
        </button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-persona="${i}">
          <i class="fa-solid fa-trash me-1"></i>Quitar
        </button>
      </div>
    </div>`
  )).join('');
}

function getPersonasAvisoFinal() {
  const finalList = [...personasAviso];
  if (personaEditorHasData()) {
    validatePersonaEditor();
    if (personaEditIndex !== null && finalList[personaEditIndex]) {
      finalList[personaEditIndex] = buildPersonaAvisoFromEditor();
    } else {
      finalList.push(buildPersonaAvisoFromEditor());
    }
  }
  if (finalList.length === 0) {
    throw new Error('Capture al menos una persona para el aviso.');
  }
  return finalList;
}

function dbObj() {
  if (!q('db_on').checked) return null;
  const t = v('db_tipo');
  if (t === 'fisica') return { tipo_persona: { persona_fisica: { nombre: up(v('db_f_nom')), apellido_paterno: up(v('db_f_ap')), apellido_materno: up(v('db_f_am')), fecha_nacimiento: d8(v('db_f_fecha')), rfc: up(v('db_f_rfc')), curp: up(v('db_f_curp')), pais_nacionalidad: up(v('db_f_pais')) } } };
  if (t === 'moral') return { tipo_persona: { persona_moral: { denominacion_razon: up(v('db_m_den')), fecha_constitucion: d8(v('db_m_fc')), rfc: up(v('db_m_rfc')), pais_nacionalidad: up(v('db_m_pais')) } } };
  return { tipo_persona: { fideicomiso: { denominacion_razon: up(v('db_t_den')), rfc: up(v('db_t_rfc')), identificador_fideicomiso: up(v('db_t_id')) } } };
}

function operacionObj() {
  const op = { fecha_operacion: d8(v('op_fecha')), codigo_postal: v('op_cp'), tipo_operacion: v('op_tipo'), datos_donativo: [{ tipo_donativo: [] }] };
  if (v('don_clase') === 'numerario') {
    op.datos_donativo[0].tipo_donativo.push({ liquidacion_numerario: { fecha_pago: d8(v('num_fp')), instrumento_monetario: v('num_inst'), moneda: v('num_moneda'), monto_operacion: v('num_monto') } });
  } else {
    const e = { monto_operacion: v('esp_monto'), moneda: v('esp_moneda'), bien_donado: v('esp_bien') };
    if (v('esp_bien') === '1') e.datos_bien_donado = { datos_inmueble: { tipo_inmueble: v('inm_tipo'), codigo_postal: v('inm_cp'), folio_real: up(v('inm_folio')) } };
    if (v('esp_bien') === '99') e.datos_bien_donado = { datos_otro: { descripcion_bien_donado: up(v('otro_desc')) } };
    op.datos_donativo[0].tipo_donativo.push({ liquidacion_especie: e });
  }
  return op;
}

function payload() {
  const personasFinal = getPersonasAvisoFinal();
  const sujetoObligado = {
    clave_entidad_colegiada: up(v('clave_entidad_colegiada')),
    clave_sujeto_obligado: up(v('clave_sujeto_obligado')),
    clave_actividad: 'DON'
  };
  if (v('exento') === '1') {
    sujetoObligado.exento = '1';
  }
  const av = {
    referencia_aviso: up(v('referencia_aviso')),
    prioridad: v('prioridad'),
    alerta: { tipo_alerta: v('tipo_alerta'), descripcion_alerta: up(v('descripcion_alerta')) },
    persona_aviso: personasFinal,
    detalle_operaciones: [{ datos_operacion: [operacionObj()] }]
  };
  if (q('es_modificatorio').checked) av.modificatorio = { folio_modificacion: up(v('folio_modificacion')), descripcion_modificacion: up(v('descripcion_modificacion')) };
  const db = dbObj();
  if (db) av.dueno_beneficiario = [db];
  return {
    id_cliente: Number(v('id_cliente')),
    informe: [{
      mes_reportado: v('mes_reportado'),
      sujeto_obligado: sujetoObligado,
      aviso: [av]
    }]
  };
}

function validateGeneral() {
  const currYm = new Date().toISOString().slice(0, 7).replace('-', '');
  if (!v('id_cliente')) throw new Error('Seleccione un cliente.');
  if (!isMonth6(v('mes_reportado')) || v('mes_reportado') < '201309' || v('mes_reportado') > currYm) throw new Error('mes_reportado inválido.');
  if (!/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/.test(up(v('clave_sujeto_obligado')))) throw new Error('Clave sujeto obligado inválida.');
  if (v('clave_entidad_colegiada') && !/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/.test(up(v('clave_entidad_colegiada')))) throw new Error('Clave entidad colegiada inválida.');
  if (!/^[A-ZÑ0-9]{1,14}$/.test(up(v('referencia_aviso')))) throw new Error('Referencia de aviso inválida.');
  if (v('prioridad') === '2' && v('tipo_alerta') === '100') throw new Error('Prioridad 2 no permite alerta 100.');
  if (v('tipo_alerta') === '9999' && !v('descripcion_alerta')) throw new Error('Capture descripción de alerta para 9999.');
  if (q('es_modificatorio').checked) {
    if (!/^[2-9]\d{3}-[1-9]\d{0,8}$/.test(up(v('folio_modificacion')))) throw new Error('Folio de modificación inválido.');
    if (!v('descripcion_modificacion')) throw new Error('Capture descripción de modificación.');
  }

  if (q('db_on').checked) {
    if (v('db_tipo') === 'fisica') {
      if (!v('db_f_nom') || !v('db_f_ap') || !v('db_f_am')) throw new Error('Dueño beneficiario físico incompleto.');
    } else if (v('db_tipo') === 'moral') {
      if (!v('db_m_den')) throw new Error('Dueño beneficiario moral requiere denominación.');
    } else {
      if (!v('db_t_den') || !v('db_t_id')) throw new Error('Dueño beneficiario fideicomiso requiere denominación e identificador.');
    }
  }

  if (!isDate8(d8(v('op_fecha'))) || !isCP(v('op_cp')) || !v('op_tipo')) throw new Error('Datos de operación inválidos.');
  if (v('don_clase') === 'numerario') {
    if (!isDate8(d8(v('num_fp')))) throw new Error('Fecha de pago inválida.');
    if (!v('num_inst') || !v('num_moneda') || Number(v('num_monto')) <= 0) throw new Error('Numerario requiere instrumento, moneda y monto > 0.');
    if ((v('num_inst') === '13' || v('num_inst') === '14') && !(Number(v('num_moneda')) >= 159 && Number(v('num_moneda')) <= 179)) throw new Error('Para instrumento 13/14 la moneda debe estar entre 159 y 179.');
    if (v('num_inst') !== '13' && v('num_inst') !== '14' && Number(v('num_moneda')) >= 159 && Number(v('num_moneda')) <= 179) throw new Error('Monedas 159-179 solo aplican a instrumento 13/14.');
  } else {
    if (!v('esp_moneda') || !v('esp_bien') || Number(v('esp_monto')) <= 0) throw new Error('Especie requiere moneda, bien donado y monto > 0.');
    if (v('esp_bien') === '1' && (!v('inm_tipo') || !isCP(v('inm_cp')) || !v('inm_folio'))) throw new Error('Inmueble requiere tipo, CP y folio real.');
    if (v('esp_bien') === '99' && !v('otro_desc')) throw new Error('Capture descripción del bien donado.');
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  setupInputMasks();
  await loadClientes();
  q('id_cliente').addEventListener('change', loadKyc);
  q('persona_tipo').addEventListener('change', tgPersona);
  q('domicilio_tipo').addEventListener('change', tgDom);
  q('es_modificatorio').addEventListener('change', tgMod);
  q('don_clase').addEventListener('change', tgClase);
  q('esp_bien').addEventListener('change', tgBien);
  q('db_on').addEventListener('change', tgDb);
  q('db_tipo').addEventListener('change', tgDbTipo);

  q('btn_add_persona').addEventListener('click', () => {
    try {
      const isUpdate = personaEditIndex !== null && !!personasAviso[personaEditIndex];
      validatePersonaEditor();
      if (isUpdate) {
        personasAviso[personaEditIndex] = buildPersonaAvisoFromEditor();
      } else {
        personasAviso.push(buildPersonaAvisoFromEditor());
      }
      renderPersonasList();
      clearPersonaEditor();
      Swal.fire({ icon: 'success', title: isUpdate ? 'Persona actualizada' : 'Persona agregada', timer: 1100, showConfirmButton: false });
    } catch (e) {
      Swal.fire({ icon: 'error', title: 'No fue posible agregar', text: e.message || 'Error inesperado' });
    }
  });

  q('btn_clear_persona').addEventListener('click', clearPersonaEditor);

  q('personas_list').addEventListener('click', ev => {
    const btnUp = ev.target.closest('[data-move-up]');
    if (btnUp) {
      const index = Number(btnUp.getAttribute('data-move-up'));
      if (!Number.isInteger(index) || index <= 0 || !personasAviso[index]) return;
      const tmp = personasAviso[index - 1];
      personasAviso[index - 1] = personasAviso[index];
      personasAviso[index] = tmp;
      if (personaEditIndex === index) setPersonaEditMode(index - 1);
      else if (personaEditIndex === index - 1) setPersonaEditMode(index);
      renderPersonasList();
      return;
    }

    const btnDown = ev.target.closest('[data-move-down]');
    if (btnDown) {
      const index = Number(btnDown.getAttribute('data-move-down'));
      if (!Number.isInteger(index) || index < 0 || index >= personasAviso.length - 1 || !personasAviso[index]) return;
      const tmp = personasAviso[index + 1];
      personasAviso[index + 1] = personasAviso[index];
      personasAviso[index] = tmp;
      if (personaEditIndex === index) setPersonaEditMode(index + 1);
      else if (personaEditIndex === index + 1) setPersonaEditMode(index);
      renderPersonasList();
      return;
    }

    const btnEdit = ev.target.closest('[data-edit-persona]');
    if (btnEdit) {
      const index = Number(btnEdit.getAttribute('data-edit-persona'));
      if (!Number.isInteger(index) || index < 0 || !personasAviso[index]) return;
      loadPersonaToEditor(personasAviso[index]);
      setPersonaEditMode(index);
      renderPersonasList();
      return;
    }

    const btnDup = ev.target.closest('[data-dup-persona]');
    if (btnDup) {
      const index = Number(btnDup.getAttribute('data-dup-persona'));
      if (!Number.isInteger(index) || index < 0 || !personasAviso[index]) return;
      const clone = deepClone(personasAviso[index]);
      personasAviso.splice(index + 1, 0, clone);
      if (personaEditIndex !== null && personaEditIndex > index) {
        setPersonaEditMode(personaEditIndex + 1);
      }
      renderPersonasList();
      Swal.fire({ icon: 'success', title: 'Persona duplicada', timer: 900, showConfirmButton: false });
      return;
    }

    const btnRemove = ev.target.closest('[data-remove-persona]');
    if (!btnRemove) return;
    const index = Number(btnRemove.getAttribute('data-remove-persona'));
    if (!Number.isInteger(index) || index < 0) return;
    personasAviso = personasAviso.filter((_, i) => i !== index);
    if (personaEditIndex !== null) {
      if (personaEditIndex === index) {
        clearPersonaEditor();
      } else if (personaEditIndex > index) {
        setPersonaEditMode(personaEditIndex - 1);
      }
    }
    renderPersonasList();
  });

  tgPersona();
  tgDom();
  tgMod();
  tgClase();
  tgBien();
  tgDb();
  tgDbTipo();
  setPersonaActionButton();
  renderPersonasList();

  q('formDON').addEventListener('submit', async ev => {
    ev.preventDefault();
    try {
      validateGeneral();
      const p = payload();
      Swal.fire({ title: 'Registrando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
      const r = await fetch('api/registrar_aviso_don.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(p) });
      const j = await r.json();
      if (!r.ok || j.status !== 'success') throw new Error(j.message || 'No fue posible registrar aviso DON.');
      await Swal.fire({ icon: 'success', title: 'Aviso DON registrado', text: `Operacion #${j.id_operacion}` });
      window.location = 'operaciones_pld.php';
    } catch (e) {
      Swal.fire({ icon: 'error', title: 'No fue posible registrar', text: e.message || 'Error inesperado' });
    }
  });
});
</script>
<?php include 'templates/footer.php'; ?>
