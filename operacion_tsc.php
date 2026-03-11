<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';

requireModuleActive($pdo, 'pld');

if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if (!userCanAccessTSC($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_tsc');
    exit;
}

$page_title = 'Aviso TSC - Tarjetas de Servicio y de Crédito';
include 'templates/header.php';

$clave_sujeto_obligado = '';
$config = [];
try {
    if ($userId > 0) {
        $stmtU = $pdo->prepare("SELECT folio_patron_pld FROM config_empresa_usuario WHERE id_usuario = ?");
        $stmtU->execute([$userId]);
        $config = $stmtU->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    if (empty($config['folio_patron_pld'])) {
        $stmt = $pdo->query("SELECT folio_patron_pld FROM config_empresa WHERE id_config = 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $clave_sujeto_obligado = $config['folio_patron_pld'] ?? '';
} catch (Exception $e) { /* fallback vacío */ }

require_once 'config/tsc_catalogos.php';
$paisOptions = tscCatalogoOptions('pais', 'MX');
?>
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/operaciones_pld.css">
<style>
:root {
    --tsc-primary: #0d9488;
    --tsc-primary-dark: #0f766e;
    --tsc-info: #0891b2;
    --tsc-success: #059669;
    --tsc-warning: #d97706;
    --tsc-dark: #134e4a;
    --tsc-light: #f0fdfa;
    --tsc-border: #99f6e4;
    --tsc-shadow: 0 4px 24px rgba(0,0,0,.06);
    --tsc-radius: 16px;
    --tsc-radius-sm: 10px;
    --tsc-transition: .25s cubic-bezier(.4,0,.2,1);
    --tsc-max-width: 960px;
}
.tsc-wrapper { max-width: var(--tsc-max-width); margin: 0 auto; }
.tsc-progress { display:flex; gap:0; margin-bottom:2rem; overflow-x:auto; padding-bottom:4px; }
.tsc-step { flex:1; min-width:100px; text-align:center; position:relative; padding:.75rem .5rem; font-size:.78rem; font-weight:600; color:#94a3b8; cursor:pointer; transition:var(--tsc-transition); }
.tsc-step::after { content:''; position:absolute; bottom:0; left:0; width:100%; height:3px; background:#e2e8f0; border-radius:3px; transition:var(--tsc-transition); }
.tsc-step.active { color:var(--tsc-primary); }
.tsc-step.active::after { background:var(--tsc-primary); }
.tsc-step.done { color:var(--tsc-success); }
.tsc-step.done::after { background:var(--tsc-success); }
.tsc-step-num { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; font-size:.75rem; font-weight:700; background:#e2e8f0; color:#64748b; margin-bottom:4px; transition:var(--tsc-transition); }
.tsc-step.active .tsc-step-num { background:var(--tsc-primary); color:#fff; }
.tsc-step.done .tsc-step-num { background:var(--tsc-success); color:#fff; }
.tsc-card { border:none; border-radius:var(--tsc-radius); background:#fff; box-shadow:var(--tsc-shadow); margin-bottom:1.5rem; overflow:hidden; transition:var(--tsc-transition); }
.tsc-card:hover { box-shadow:0 8px 32px rgba(0,0,0,.09); }
.tsc-card-header { padding:1rem 1.5rem; display:flex; align-items:center; gap:.75rem; cursor:pointer; user-select:none; transition:var(--tsc-transition); border-bottom:1px solid transparent; }
.tsc-card-header:hover { background:rgba(0,0,0,.015); }
.tsc-card-header .tsc-icon { width:40px; height:40px; border-radius:var(--tsc-radius-sm); display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#fff; flex-shrink:0; }
.tsc-card-header h5 { margin:0; font-size:1rem; font-weight:700; color:var(--tsc-dark); }
.tsc-card-header small { color:#94a3b8; font-size:.78rem; font-weight:400; display:block; }
.tsc-card-header .tsc-chevron { margin-left:auto; font-size:.85rem; color:#94a3b8; transition:var(--tsc-transition); }
.tsc-card-header.collapsed .tsc-chevron { transform:rotate(-90deg); }
.tsc-card-body { padding:1.25rem 1.5rem; }
.icon-kyc { background:linear-gradient(135deg,var(--tsc-primary),var(--tsc-primary-dark)); }
.icon-informe { background:linear-gradient(135deg,#0891b2,#0e7490); }
.icon-aviso { background:linear-gradient(135deg,var(--tsc-warning),#b45309); }
.icon-persona { background:linear-gradient(135deg,var(--tsc-success),#047857); }
.icon-detalle { background:linear-gradient(135deg,#7c3aed,#5b21b6); }
.persona-section, .domicilio-section { display:none; }
.persona-section.active, .domicilio-section.active { display:block; animation:tscFadeIn .25s ease; }
.db-persona-section, .db-domicilio-section { display:none; }
.db-persona-section.active, .db-domicilio-section.active { display:block !important; animation:tscFadeIn .25s ease; }
@keyframes tscFadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
.nested-card { border-left:3px solid var(--tsc-primary); background:var(--tsc-light); border-radius:0 var(--tsc-radius-sm) var(--tsc-radius-sm) 0; }
.tsc-card .form-label { font-size:.8rem; font-weight:600; color:#475569; margin-bottom:.35rem; display:flex; align-items:center; flex-wrap:wrap; gap:.3rem; }
.section-help { font-size:.7rem; color:#a0aec0; margin-top:.15rem; line-height:1.25; }
.badge-xsd { font-size:.58rem; vertical-align:middle; padding:.15em .4em; border-radius:3px; font-weight:600; white-space:nowrap; }
.tsc-card .form-control, .tsc-card .form-select { font-size:.875rem; padding:.55rem .85rem; border-radius:8px; border:1.5px solid #e2e8f0; transition:var(--tsc-transition); }
.tsc-card .form-control:focus, .tsc-card .form-select:focus { border-color:var(--tsc-primary); box-shadow:0 0 0 3px rgba(13,148,136,.12); }
.tsc-page-header { background:linear-gradient(135deg,var(--tsc-primary) 0%,var(--tsc-primary-dark) 100%); color:#fff; border-radius:var(--tsc-radius); padding:1.75rem 2rem; margin-bottom:1.75rem; position:relative; overflow:hidden; }
.tsc-page-header h2 { font-size:1.5rem; font-weight:800; margin-bottom:.25rem; }
.tsc-page-header p { opacity:.8; margin:0; font-size:.9rem; }
.tsc-page-header a { color:#fff; text-decoration:underline; opacity:.85; }
.tsc-page-header a:hover { opacity:1; }
.tsc-page-header .btn-outline-light { border:1.5px solid rgba(255,255,255,.5); color:#fff; border-radius:8px; font-weight:600; transition:var(--tsc-transition); }
.tsc-page-header .btn-outline-light:hover { background:rgba(255,255,255,.15); border-color:#fff; }
.tsc-submit-bar { position:sticky; bottom:0; background:#fff; padding:1rem 1.5rem; border-top:1px solid #e2e8f0; border-radius:var(--tsc-radius) var(--tsc-radius) 0 0; box-shadow:0 -4px 20px rgba(0,0,0,.06); z-index:10; display:flex; align-items:center; gap:1rem; flex-wrap:wrap; }
.tsc-submit-bar .btn-primary { background:linear-gradient(135deg,var(--tsc-primary),var(--tsc-primary-dark)); border:none; padding:.7rem 2rem; font-weight:700; border-radius:var(--tsc-radius-sm); box-shadow:0 4px 14px rgba(13,148,136,.3); transition:var(--tsc-transition); }
.tsc-submit-bar .btn-primary:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(13,148,136,.4); }
#kyc-preview { background:linear-gradient(135deg,#ccfbf1,#f0fdfa); border:1px solid #5eead4; border-radius:var(--tsc-radius-sm); padding:1rem 1.25rem; }
.text-uppercase { text-transform: uppercase; }
</style>
</head>
<body>

<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="content-wrapper">
<div class="tsc-wrapper">
    <div class="tsc-page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2><i class="fa-solid fa-credit-card me-2"></i>Aviso TSC</h2>
                <p>Tarjetas de Servicio y de Crédito — Fracción II
                    <a href="https://www.sat.gob.mx/consulta/44891/portal-de-prevencion-de-lavado-de-dinero" target="_blank" rel="noopener" class="ms-2">
                        <i class="fa-solid fa-external-link-alt"></i> Portal PLD
                    </a>
                </p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-light">
                <i class="fa-solid fa-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>

    <nav class="tsc-progress" aria-label="Progreso del formulario">
        <div class="tsc-step active" data-target="sec-kyc"><div class="tsc-step-num">1</div><div>Cliente</div></div>
        <div class="tsc-step" data-target="sec-informe"><div class="tsc-step-num">2</div><div>Informe</div></div>
        <div class="tsc-step" data-target="sec-aviso"><div class="tsc-step-num">3</div><div>Aviso</div></div>
        <div class="tsc-step" data-target="sec-persona"><div class="tsc-step-num">4</div><div>Persona</div></div>
        <div class="tsc-step" data-target="sec-detalle"><div class="tsc-step-num">5</div><div>Detalle TSC</div></div>
    </nav>

    <form id="formTSC" novalidate>

        <!-- SECCIÓN 1: Cliente KYC -->
        <div class="tsc-card" id="sec-kyc">
            <div class="tsc-card-header" onclick="toggleTscCard(this)">
                <div class="tsc-icon icon-kyc"><i class="fa-solid fa-user"></i></div>
                <div><h5>Cliente KYC</h5><small>Datos del expediente de identificación</small></div>
                <i class="fa-solid fa-chevron-down tsc-chevron"></i>
            </div>
            <div class="tsc-card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cliente *</label>
                        <select class="form-select" id="id_cliente" required>
                            <option value="">-- Seleccione Cliente --</option>
                        </select>
                    </div>
                </div>
                <div id="kyc-preview" style="display:none;">
                    <small class="text-muted d-block mb-2"><i class="fa-solid fa-lock me-1"></i>Solo lectura — datos del expediente</small>
                    <div class="row g-2">
                        <div class="col-lg-4 col-md-6"><strong>RFC:</strong> <span id="kyc-rfc">-</span></div>
                        <div class="col-lg-4 col-md-6"><strong>CURP:</strong> <span id="kyc-curp">-</span></div>
                        <div class="col-lg-4 col-md-6"><strong>Tipo:</strong> <span id="kyc-tipo">-</span></div>
                        <div class="col-lg-6 col-md-6"><strong>Nombre/Razón:</strong> <span id="kyc-nombre">-</span></div>
                        <div class="col-lg-3 col-md-6"><strong>Fecha Nac/Const:</strong> <span id="kyc-fecha">-</span></div>
                        <div class="col-lg-3 col-md-6"><strong>Nacionalidad:</strong> <span id="kyc-pais">-</span></div>
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="prefillPersonaFromKyc()" id="btn-prefill-persona" title="Prellenar persona objeto del aviso con datos del expediente">
                            <i class="fa-solid fa-user-check me-1"></i>Prellenar Persona
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 2: Informe -->
        <div class="tsc-card" id="sec-informe">
            <div class="tsc-card-header" onclick="toggleTscCard(this)">
                <div class="tsc-icon icon-informe"><i class="fa-solid fa-file-alt"></i></div>
                <div><h5>Informe y Sujeto Obligado</h5><small>Mes reportado, clave del obligado</small></div>
                <i class="fa-solid fa-chevron-down tsc-chevron"></i>
            </div>
            <div class="tsc-card-body">
                <div class="row g-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Mes reportado (AAAAMM) * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control" id="mes_reportado" pattern="\d{6}" maxlength="6" required placeholder="Ej: 202602" value="<?= date('Ym') ?>">
                        <div class="section-help">6 dígitos AAAAMM</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Clave Sujeto Obligado * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control text-uppercase" id="clave_sujeto_obligado" required maxlength="13" value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" placeholder="Ej: ABC010203AB1 o ABCD010203AB1" pattern="[A-Za-zÑ&]{3,4}\d{6}[A-Za-z0-9]{3}" title="Formato RFC: 3-4 letras + 6 dígitos + 3 caracteres (ej: ABC010203AB1)">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Clave Actividad * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control" id="clave_actividad" value="TSC" readonly maxlength="3">
                        <div class="section-help">Fijo "TSC" para Tarjetas de Servicio y de Crédito</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Entidad Colegiada <span class="badge bg-warning text-dark badge-xsd">Opc.</span></label>
                        <input type="text" class="form-control" id="clave_entidad_colegiada" maxlength="12" placeholder="LLLAAMMDDXXX">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Exento (Art. 27 Bis) * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="exento" required>
                            <?= tscCatalogoOptions('exento', '0') ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 3: Aviso -->
        <div class="tsc-card" id="sec-aviso">
            <div class="tsc-card-header" onclick="toggleTscCard(this)">
                <div class="tsc-icon icon-aviso"><i class="fa-solid fa-bell"></i></div>
                <div><h5>Aviso</h5><small>Referencia, prioridad y alerta</small></div>
                <i class="fa-solid fa-chevron-down tsc-chevron"></i>
            </div>
            <div class="tsc-card-body">
                <div class="row g-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Referencia Aviso * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control text-uppercase" id="referencia_aviso" maxlength="14" required placeholder="Ej: REF202601001 o AVI-001" pattern="[A-ZÑ0-9]{1,14}" title="Solo A-Z, Ñ, 0-9, máx 14 caracteres">
                        <div class="section-help">XSD: [A-ZÑ0-9]{1,14}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Prioridad * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="prioridad" required>
                            <?= tscCatalogoOptions('prioridad', '1') ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Tipo Alerta * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="tipo_alerta" required>
                            <?= tscCatalogoOptions('tipo_alerta', '100') ?>
                        </select>
                    </div>
                    <div class="col-12 mb-2">
                        <label class="form-label">Descripción Alerta <span class="badge bg-warning text-dark badge-xsd">Opc.</span></label>
                        <textarea class="form-control" id="descripcion_alerta" maxlength="3000" rows="2" placeholder="Descripción de la alerta (opcional, hasta 3,000 caracteres)"></textarea>
                        <div class="section-help">Opcional. Solo A-Z, Ñ, 0-9, espacios y - . , ' : / $</div>
                    </div>
                </div>
                <hr class="my-3" style="border-color:#e2e8f0;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">¿Es aviso modificatorio?</label>
                        <select class="form-select" id="es_modificatorio">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>
                    </div>
                </div>
                <div id="seccion_modificatorio" style="display:none;" class="mt-3">
                    <div class="nested-card p-3 rounded mb-3">
                        <h6 class="fw-bold" style="color:var(--tsc-primary)"><i class="fa-solid fa-pen me-1"></i>Datos Modificatorio</h6>
                        <div class="row g-3">
                            <div class="col-md-5 mb-2">
                                <label class="form-label">Folio Modificación *</label>
                                <input type="text" class="form-control" id="folio_modificacion" maxlength="14" placeholder="Ej: 2026-123456789 (formato AAAA-N)" pattern="\d{4}-\d{1,9}" title="Formato: AAAA-N (6-14 car.)">
                                <div class="section-help">XSD: \d{4}-\d{1,9} ej. 2026-123456789</div>
                            </div>
                            <div class="col-md-7 mb-2">
                                <label class="form-label">Descripción Modificación *</label>
                                <textarea class="form-control" id="descripcion_modificacion" maxlength="3000" rows="2" placeholder="Describa los cambios realizados al aviso original"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 4: Persona objeto del aviso -->
        <div class="tsc-card" id="sec-persona">
            <div class="tsc-card-header" onclick="toggleTscCard(this)">
                <div class="tsc-icon icon-persona"><i class="fa-solid fa-id-card"></i></div>
                <div><h5>Persona objeto del aviso</h5><small>Persona física, moral o fideicomiso</small></div>
                <i class="fa-solid fa-chevron-down tsc-chevron"></i>
            </div>
            <div class="tsc-card-body">
                <div class="row g-3 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Tipo Persona *</label>
                        <select class="form-select" id="tipo_persona">
                            <option value="persona_fisica">Persona Física</option>
                            <option value="persona_moral">Persona Moral</option>
                            <option value="fideicomiso">Fideicomiso</option>
                        </select>
                    </div>
                </div>
                <div id="persona_fisica_block" class="persona-section active">
                        <div class="row g-3">
                        <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control text-uppercase" id="pf_nombre" maxlength="200" required placeholder="Ej: JUAN CARLOS"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Apellido Paterno *</label><input type="text" class="form-control text-uppercase" id="pf_apellido_paterno" maxlength="200" required placeholder="Ej: LÓPEZ"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Apellido Materno *</label><input type="text" class="form-control text-uppercase" id="pf_apellido_materno" maxlength="200" required placeholder="Ej: GARCÍA"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Fecha Nacimiento *</label><input type="date" class="form-control" id="pf_fecha_nacimiento" required title="Formato: AAAA-MM-DD"><div class="section-help">Ej: 15/01/1990 → 1990-01-15</div></div>
                        <div class="col-md-6 mb-2"><label class="form-label">RFC (13 car.) *</label><input type="text" class="form-control" id="pf_rfc" maxlength="13" required placeholder="Ej: LOPG900101ABC"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">CURP (18 car.) *</label><input type="text" class="form-control" id="pf_curp" maxlength="18" required placeholder="Ej: LOPG900101HDFLRN01"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">País Nacionalidad *</label><select class="form-select" id="pf_pais_nacionalidad" required><?= $paisOptions ?></select></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Act. Económica (SCIAN) *</label><select class="form-select" id="pf_actividad_economica" required><?= tscCatalogoOptions('actividad_economica', '1000000') ?></select></div>
                    </div>
                </div>
                <div id="persona_moral_block" class="persona-section">
                    <div class="row g-3">
                        <div class="col-md-6 mb-2"><label class="form-label">Denominación/Razón Social *</label><input type="text" class="form-control text-uppercase" id="pm_denominacion" maxlength="254" required placeholder="Ej: EMPRESA EJEMPLO S.A. DE C.V."></div>
                        <div class="col-md-6 mb-2"><label class="form-label">RFC (12 car.)</label><input type="text" class="form-control" id="pm_rfc" maxlength="12" placeholder="Ej: EEE900101AAA"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Fecha Constitución *</label><input type="date" class="form-control" id="pm_fecha_constitucion" required title="AAAA-MM-DD"><div class="section-help">Ej: 20/05/2010 → 2010-05-20</div></div>
                        <div class="col-md-6 mb-2"><label class="form-label">País Nacionalidad *</label><select class="form-select" id="pm_pais_nacionalidad" required><?= $paisOptions ?></select></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Giro Mercantil *</label><select class="form-select" id="pm_giro_mercantil" required><?= tscCatalogoOptions('giro_mercantil', '0000000') ?></select></div>
                    </div>
                    <div class="mt-3 p-2 rounded" style="background:#f0fdfa;">
                        <label class="form-label fw-bold" style="color:var(--tsc-primary)"><i class="fa-solid fa-user-tie me-1"></i>Representante/Apoderado Legal</label>
                        <div class="row g-3">
                            <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control text-uppercase" id="pm_rep_nombre" maxlength="200" required placeholder="Ej: MARÍA FERNANDA"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Ap. Paterno *</label><input type="text" class="form-control text-uppercase" id="pm_rep_apellido_paterno" maxlength="200" required placeholder="Ej: MARTÍNEZ"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Ap. Materno *</label><input type="text" class="form-control text-uppercase" id="pm_rep_apellido_materno" maxlength="200" required placeholder="Ej: SÁNCHEZ"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Fecha Nac. *</label><input type="date" class="form-control" id="pm_rep_fecha_nacimiento" required placeholder="AAAA-MM-DD"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">RFC (13 car.) *</label><input type="text" class="form-control" id="pm_rep_rfc" maxlength="13" required placeholder="Ej: MAMS800101ABC"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">CURP (18 car.) *</label><input type="text" class="form-control" id="pm_rep_curp" maxlength="18" required placeholder="Ej: MAMS800101MDFRNR01"></div>
                        </div>
                    </div>
                </div>
                <div id="fideicomiso_block" class="persona-section">
                    <div class="row g-3">
                        <div class="col-12 mb-2"><label class="form-label">Denominación Fiduciario *</label><input type="text" class="form-control text-uppercase" id="fid_denominacion" maxlength="254" required placeholder="Ej: FIDEICOMISO EJEMPLO S.A. DE C.V."></div>
                        <div class="col-md-6 mb-2"><label class="form-label">RFC Fideicomiso (12 car.) *</label><input type="text" class="form-control" id="fid_rfc" maxlength="12" required placeholder="Ej: FDE900101AAA"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Identificador Fideicomiso *</label><input type="text" class="form-control" id="fid_identificador" maxlength="40" required placeholder="Ej: FID-001-2026"></div>
                    </div>
                    <div class="mt-3 p-2 rounded" style="background:#f0fdfa;">
                        <label class="form-label fw-bold"><i class="fa-solid fa-user-tie me-1"></i>Apoderado/Delegado</label>
                        <div class="row g-3">
                            <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control text-uppercase" id="fid_apod_nombre" maxlength="200" required placeholder="Ej: PEDRO ANTONIO"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Ap. Paterno *</label><input type="text" class="form-control text-uppercase" id="fid_apod_apellido_paterno" maxlength="200" required placeholder="Ej: HERNÁNDEZ"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Ap. Materno *</label><input type="text" class="form-control text-uppercase" id="fid_apod_apellido_materno" maxlength="200" required placeholder="Ej: RAMÍREZ"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Fecha Nac. *</label><input type="date" class="form-control" id="fid_apod_fecha_nacimiento" required placeholder="AAAA-MM-DD"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">RFC *</label><input type="text" class="form-control" id="fid_apod_rfc" maxlength="13" required placeholder="Ej: HERP700101ABC"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">CURP *</label><input type="text" class="form-control" id="fid_apod_curp" maxlength="18" required placeholder="Ej: HERP700101HDFLRN01"></div>
                        </div>
                    </div>
                </div>

                <hr class="my-3">
                <h6 class="fw-bold mb-2" style="color:var(--tsc-info)"><i class="fa-solid fa-map-marker-alt me-1"></i>Domicilio</h6>
                <div class="row g-3 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Tipo Domicilio *</label>
                        <select class="form-select" id="tipo_domicilio">
                            <option value="nacional">Nacional</option>
                            <option value="extranjero">Extranjero</option>
                        </select>
                    </div>
                </div>
                <div id="domicilio_nacional" class="domicilio-section active">
                    <div class="row g-3">
                        <div class="col-md-6 mb-2"><label class="form-label">Colonia *</label><input type="text" class="form-control text-uppercase" id="dom_colonia" maxlength="50" required placeholder="Ej: CENTRO"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Calle *</label><input type="text" class="form-control text-uppercase" id="dom_calle" maxlength="100" required placeholder="Ej: AV. REFORMA 123"></div>
                        <div class="col-md-4 mb-2"><label class="form-label">Núm. Exterior *</label><input type="text" class="form-control" id="dom_numero_exterior" maxlength="56" required placeholder="Ej: 123"></div>
                        <div class="col-md-4 mb-2"><label class="form-label">Núm. Interior</label><input type="text" class="form-control" id="dom_numero_interior" maxlength="40" placeholder="Ej: 4 (opcional)"></div>
                        <div class="col-md-4 mb-2"><label class="form-label">C.P. *</label><input type="text" class="form-control" id="dom_codigo_postal" maxlength="5" pattern="\d{5}" required placeholder="Ej: 06000"></div>
                    </div>
                </div>
                <div id="domicilio_extranjero" class="domicilio-section">
                    <div class="row g-3">
                        <div class="col-md-6 mb-2"><label class="form-label">País *</label><select class="form-select" id="dom_pais" required><?= $paisOptions ?></select></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Estado/Provincia *</label><input type="text" class="form-control" id="dom_estado" maxlength="100" required placeholder="Ej: CALIFORNIA"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Ciudad *</label><input type="text" class="form-control" id="dom_ciudad" maxlength="100" required placeholder="Ej: LOS ANGELES"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Colonia *</label><input type="text" class="form-control text-uppercase" id="dom_ext_colonia" maxlength="50" required placeholder="Ej: DOWNTOWN"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Calle *</label><input type="text" class="form-control text-uppercase" id="dom_ext_calle" maxlength="100" required placeholder="Ej: MAIN STREET 456"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Núm. Exterior *</label><input type="text" class="form-control" id="dom_ext_numero" maxlength="56" required placeholder="Ej: 456"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Núm. Interior</label><input type="text" class="form-control" id="dom_ext_numero_int" maxlength="40" placeholder="Ej: 2 (opcional)"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">C.P. *</label><input type="text" class="form-control" id="dom_ext_cp" maxlength="12" required placeholder="Ej: 90001"></div>
                    </div>
                </div>

                <hr class="my-3">
                <h6 class="fw-bold mb-2"><i class="fa-solid fa-phone me-1"></i>Teléfono</h6>
                <div class="row g-3">
                    <div class="col-md-4 mb-2"><label class="form-label">País Tel. *</label><select class="form-select" id="tel_clave_pais" required><?= $paisOptions ?></select></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Número *</label><input type="text" class="form-control" id="tel_numero" maxlength="12" pattern="\d{10,12}" required placeholder="Ej: 5512345678 (10-12 dígitos)"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Correo Electrónico</label><input type="email" class="form-control" id="tel_correo" maxlength="60" placeholder="Ej: correo@ejemplo.com (opcional)"><div class="section-help">En el XML se enviará en mayúsculas (requisito XSD)</div></div>
                </div>

                <hr class="my-3">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="incluir_dueno_beneficiario">
                    <label class="form-check-label" for="incluir_dueno_beneficiario"><strong>Incluir Dueño Beneficiario / Controlador</strong> <span class="badge bg-warning text-dark badge-xsd">Opcional</span></label>
                </div>
                <div id="seccion_dueno_beneficiario" style="display:none;">
                    <div class="nested-card p-3 rounded">
                        <h6 class="fw-bold mb-3" style="color:var(--tsc-primary)"><i class="fa-solid fa-user-shield me-1"></i>3.6 Dueño Beneficiario / Controlador</h6>
                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label class="form-label">Tipo Persona *</label>
                                <select class="form-select" id="db_tipo_persona">
                                    <option value="persona_fisica">Persona Física</option>
                                    <option value="persona_moral">Persona Moral</option>
                                    <option value="fideicomiso">Fideicomiso</option>
                                </select>
                            </div>
                        </div>
                        <div id="db_persona_fisica_block" class="db-persona-section active">
                            <div class="row g-3">
                                <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control text-uppercase" id="db_pf_nombre" maxlength="200" placeholder="Ej: JUAN CARLOS"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Apellido Paterno *</label><input type="text" class="form-control text-uppercase" id="db_pf_apellido_paterno" maxlength="200" placeholder="Ej: LÓPEZ"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Apellido Materno *</label><input type="text" class="form-control text-uppercase" id="db_pf_apellido_materno" maxlength="200" placeholder="Ej: GARCÍA"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Fecha Nacimiento *</label><input type="date" class="form-control" id="db_pf_fecha_nacimiento" placeholder="AAAA-MM-DD"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">RFC (13 car.) *</label><input type="text" class="form-control" id="db_pf_rfc" maxlength="13" placeholder="Ej: LOPG900101ABC"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">CURP (18 car.) *</label><input type="text" class="form-control" id="db_pf_curp" maxlength="18" placeholder="Ej: LOPG900101HDFLRN01"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">País Nacionalidad *</label><select class="form-select" id="db_pf_pais_nacionalidad"><?= $paisOptions ?></select></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Act. Económica (SCIAN) *</label><select class="form-select" id="db_pf_actividad_economica"><?= tscCatalogoOptions('actividad_economica', '1000000') ?></select></div>
                            </div>
                        </div>
                        <div id="db_persona_moral_block" class="db-persona-section" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-6 mb-2"><label class="form-label">Denominación/Razón Social *</label><input type="text" class="form-control text-uppercase" id="db_pm_denominacion" maxlength="254" placeholder="Ej: EMPRESA EJEMPLO S.A. DE C.V."></div>
                                <div class="col-md-6 mb-2"><label class="form-label">RFC (12 car.)</label><input type="text" class="form-control" id="db_pm_rfc" maxlength="12" placeholder="Ej: EEE900101AAA"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Fecha Constitución *</label><input type="date" class="form-control" id="db_pm_fecha_constitucion" placeholder="AAAA-MM-DD"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">País Nacionalidad *</label><select class="form-select" id="db_pm_pais_nacionalidad"><?= $paisOptions ?></select></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Giro Mercantil *</label><select class="form-select" id="db_pm_giro_mercantil"><?= tscCatalogoOptions('giro_mercantil', '0000000') ?></select></div>
                            </div>
                            <div class="mt-2 p-2 rounded" style="background:#e0f2fe;">
                                <label class="form-label fw-bold" style="color:#0369a1"><i class="fa-solid fa-user-tie me-1"></i>Representante/Apoderado Legal</label>
                                <div class="row g-3">
                                    <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control text-uppercase" id="db_pm_rep_nombre" maxlength="200" placeholder="Ej: MARÍA FERNANDA"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Ap. Paterno *</label><input type="text" class="form-control text-uppercase" id="db_pm_rep_apellido_paterno" maxlength="200" placeholder="Ej: MARTÍNEZ"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Ap. Materno *</label><input type="text" class="form-control text-uppercase" id="db_pm_rep_apellido_materno" maxlength="200" placeholder="Ej: SÁNCHEZ"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Fecha Nac. *</label><input type="date" class="form-control" id="db_pm_rep_fecha_nacimiento" placeholder="AAAA-MM-DD"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">RFC (13 car.) *</label><input type="text" class="form-control" id="db_pm_rep_rfc" maxlength="13" placeholder="Ej: MAMS800101ABC"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">CURP (18 car.) *</label><input type="text" class="form-control" id="db_pm_rep_curp" maxlength="18" placeholder="Ej: MAMS800101MDFRNR01"></div>
                                </div>
                            </div>
                        </div>
                        <div id="db_fideicomiso_block" class="db-persona-section" style="display:none;">
                            <div class="row g-3">
                                <div class="col-12 mb-2"><label class="form-label">Denominación Fiduciario *</label><input type="text" class="form-control text-uppercase" id="db_fid_denominacion" maxlength="254" placeholder="Ej: FIDEICOMISO EJEMPLO"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">RFC Fideicomiso (12 car.) *</label><input type="text" class="form-control" id="db_fid_rfc" maxlength="12" placeholder="Ej: FDE900101AAA"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Identificador Fideicomiso *</label><input type="text" class="form-control" id="db_fid_identificador" maxlength="40" placeholder="Ej: FID-001-2026"></div>
                            </div>
                            <div class="mt-2 p-2 rounded" style="background:#e0f2fe;">
                                <label class="form-label fw-bold"><i class="fa-solid fa-user-tie me-1"></i>Apoderado/Delegado</label>
                                <div class="row g-3">
                                    <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control text-uppercase" id="db_fid_apod_nombre" maxlength="200" placeholder="Ej: PEDRO ANTONIO"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Ap. Paterno *</label><input type="text" class="form-control text-uppercase" id="db_fid_apod_apellido_paterno" maxlength="200" placeholder="Ej: HERNÁNDEZ"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Ap. Materno *</label><input type="text" class="form-control text-uppercase" id="db_fid_apod_apellido_materno" maxlength="200" placeholder="Ej: RAMÍREZ"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Fecha Nac. *</label><input type="date" class="form-control" id="db_fid_apod_fecha_nacimiento" placeholder="AAAA-MM-DD"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">RFC *</label><input type="text" class="form-control" id="db_fid_apod_rfc" maxlength="13" placeholder="Ej: HERP700101ABC"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">CURP *</label><input type="text" class="form-control" id="db_fid_apod_curp" maxlength="18" placeholder="Ej: HERP700101HDFLRN01"></div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <h6 class="fw-bold mb-2" style="color:var(--tsc-info)"><i class="fa-solid fa-map-marker-alt me-1"></i>Domicilio Dueño Beneficiario</h6>
                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label class="form-label">Tipo Domicilio *</label>
                                <select class="form-select" id="db_tipo_domicilio">
                                    <option value="nacional">Nacional</option>
                                    <option value="extranjero">Extranjero</option>
                                </select>
                            </div>
                        </div>
                        <div id="db_domicilio_nacional" class="db-domicilio-section active">
                            <div class="row g-3">
                                <div class="col-md-6 mb-2"><label class="form-label">Colonia *</label><input type="text" class="form-control text-uppercase" id="db_dom_colonia" maxlength="50" placeholder="Ej: CENTRO"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Calle *</label><input type="text" class="form-control text-uppercase" id="db_dom_calle" maxlength="100" placeholder="Ej: AV. REFORMA 123"></div>
                                <div class="col-md-4 mb-2"><label class="form-label">Núm. Exterior *</label><input type="text" class="form-control" id="db_dom_numero_exterior" maxlength="56" placeholder="Ej: 123"></div>
                                <div class="col-md-4 mb-2"><label class="form-label">Núm. Interior</label><input type="text" class="form-control" id="db_dom_numero_interior" maxlength="40" placeholder="Ej: 4 (opcional)"></div>
                                <div class="col-md-4 mb-2"><label class="form-label">C.P. *</label><input type="text" class="form-control" id="db_dom_codigo_postal" maxlength="5" pattern="\d{5}" placeholder="Ej: 06000"></div>
                            </div>
                        </div>
                        <div id="db_domicilio_extranjero" class="db-domicilio-section" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-6 mb-2"><label class="form-label">País *</label><select class="form-select" id="db_dom_pais"><?= $paisOptions ?></select></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Estado/Provincia *</label><input type="text" class="form-control" id="db_dom_estado" maxlength="100" placeholder="Ej: CALIFORNIA"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Ciudad *</label><input type="text" class="form-control" id="db_dom_ciudad" maxlength="100" placeholder="Ej: LOS ANGELES"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Colonia *</label><input type="text" class="form-control text-uppercase" id="db_dom_ext_colonia" maxlength="50" placeholder="Ej: DOWNTOWN"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Calle *</label><input type="text" class="form-control text-uppercase" id="db_dom_ext_calle" maxlength="100" placeholder="Ej: MAIN STREET 456"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Núm. Exterior *</label><input type="text" class="form-control" id="db_dom_ext_numero" maxlength="56" placeholder="Ej: 456"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Núm. Interior</label><input type="text" class="form-control" id="db_dom_ext_numero_int" maxlength="40" placeholder="Ej: 2 (opcional)"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">C.P. *</label><input type="text" class="form-control" id="db_dom_ext_cp" maxlength="12" placeholder="Ej: 90001"></div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <h6 class="fw-bold mb-2"><i class="fa-solid fa-phone me-1"></i>Teléfono Dueño Beneficiario</h6>
                        <div class="row g-3">
                            <div class="col-md-4 mb-2"><label class="form-label">País Tel. *</label><select class="form-select" id="db_tel_clave_pais"><?= $paisOptions ?></select></div>
                            <div class="col-md-4 mb-2"><label class="form-label">Número *</label><input type="text" class="form-control" id="db_tel_numero" maxlength="12" pattern="\d{10,12}" placeholder="Ej: 5512345678"></div>
                            <div class="col-md-4 mb-2"><label class="form-label">Correo Electrónico</label><input type="email" class="form-control" id="db_tel_correo" maxlength="60" placeholder="Ej: correo@ejemplo.com"></div>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="prefillDuenoBeneficiarioFromKyc()" title="Prellenar con datos del expediente del cliente">
                                <i class="fa-solid fa-user-check me-1"></i>Prellenar desde Cliente KYC
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 5: Detalle operaciones TSC -->
        <div class="tsc-card" id="sec-detalle">
            <div class="tsc-card-header" onclick="toggleTscCard(this)">
                <div class="tsc-icon icon-detalle"><i class="fa-solid fa-credit-card"></i></div>
                <div><h5>Detalle de la operación</h5><small>Periodo, tipo, tarjeta y monto</small></div>
                <i class="fa-solid fa-chevron-down tsc-chevron"></i>
            </div>
            <div class="tsc-card-body">
                <div class="row g-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Periodo a reportar (AAAAMM) * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control" id="fecha_periodo" pattern="\d{6}" maxlength="6" required placeholder="202602" value="<?= date('Ym') ?>">
                        <div class="section-help">6 dígitos numéricos</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Tipo de Operación * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="tipo_operacion" required>
                            <?= tscCatalogoOptions('tipo_operacion', '1701') ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Tipo de Tarjeta * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="tipo_tarjeta" required>
                            <?= tscCatalogoOptions('tipo_tarjeta', '1') ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Número Tarjeta/Cuenta/Identificador * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control text-uppercase" id="numero_identificador" maxlength="18" required placeholder="Solo A-Z, 0-9 (1-18 car.)" pattern="[A-Za-z0-9]{1,18}" title="Solo letras y números, máx 18 caracteres">
                        <div class="section-help">XSD: referencia_1-18 [A-Z0-9]{1,18}</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Monto Total gasto acumulado (periodo) * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="number" class="form-control" id="monto_gasto" step="0.01" min="0" required placeholder="Ej: 15000.50 (monto en MXN)">
                        <div class="section-help">4-17 dígitos, formato decimal</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tsc-submit-bar">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-paper-plane me-2"></i>Registrar y Generar XML
            </button>
            <a href="operaciones_pld.php" class="btn btn-outline-secondary">Cancelar</a>
            <span class="text-muted ms-auto d-none d-md-inline" style="font-size:.82rem;">
                <i class="fa-solid fa-info-circle me-1"></i>Se generará el XML según instructivo Tarjetas de Servicio y de Crédito
            </span>
        </div>
    </form>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const TSC_CATALOGOS = <?= tscCatalogosJson() ?>;

/** Mapa nombre actividad (KYC/cat_actividades) -> codigo SCIAN TSC */
function buildActividadNombreToCodigo() {
    const cat = TSC_CATALOGOS.actividad_economica || {};
    const exact = {};
    for (const [code, name] of Object.entries(cat)) {
        const n = String(name || '').trim().toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[,;]+/g, ' ').replace(/\s+/g, ' ');
        exact[n] = code;
    }
    return { exact };
}

const ACT_ECON_MAP = buildActividadNombreToCodigo();

/** Convierte actividad_nombre (KYC/cat_actividades) a codigo SCIAN TSC. Si no encuentra, usa 1000000. */
function actividadNombreToCodigoSCIAN(nombre) {
    if (!nombre || typeof nombre !== 'string') return '1000000';
    const norm = s => String(s).trim().toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[,;]+/g, ' ').replace(/\s+/g, ' ');
    const n = norm(nombre);
    for (const [catName, code] of Object.entries(ACT_ECON_MAP.exact)) {
        const cn = norm(catName);
        if (cn === n || cn.includes(n) || n.includes(cn)) return code;
    }
    return '1000000';
}

function toggleTscCard(header) {
    const body = header.nextElementSibling;
    header.classList.toggle('collapsed');
    body.style.display = header.classList.contains('collapsed') ? 'none' : '';
}

function v(id) {
    const el = document.getElementById(id);
    return el ? String(el.value || '').trim() : '';
}

let kycDataCache = null;

document.addEventListener('DOMContentLoaded', function() {
    cargarClientes();
    document.getElementById('id_cliente').addEventListener('change', cargarKYC);
    document.getElementById('tipo_persona').addEventListener('change', toggleTipoPersona);
    document.getElementById('tipo_domicilio').addEventListener('change', toggleTipoDomicilio);
    document.getElementById('es_modificatorio').addEventListener('change', function() {
        const show = this.value === '1';
        document.getElementById('seccion_modificatorio').style.display = show ? 'block' : 'none';
        document.getElementById('folio_modificacion').required = show;
        document.getElementById('descripcion_modificacion').required = show;
    });
    document.getElementById('incluir_dueno_beneficiario').addEventListener('change', function() {
        const show = this.checked;
        document.getElementById('seccion_dueno_beneficiario').style.display = show ? 'block' : 'none';
        setDuenoBeneficiarioRequired(show);
    });
    document.getElementById('db_tipo_persona').addEventListener('change', toggleDuenoTipoPersona);
    document.getElementById('db_tipo_domicilio').addEventListener('change', toggleDuenoTipoDomicilio);

    document.getElementById('formTSC').addEventListener('submit', guardarAvisoTSC);

    // Progress steps
    const steps = document.querySelectorAll('.tsc-step');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                steps.forEach(s => s.classList.remove('active'));
                const step = document.querySelector(`.tsc-step[data-target="${entry.target.id}"]`);
                if (step) {
                    step.classList.add('active');
                    let prev = step.previousElementSibling;
                    while (prev) { prev.classList.add('done'); prev = prev.previousElementSibling; }
                }
            }
        });
    }, { threshold: 0.3 });
    document.querySelectorAll('.tsc-card[id]').forEach(card => observer.observe(card));
    steps.forEach(step => {
        step.addEventListener('click', () => {
            const target = document.getElementById(step.dataset.target);
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
});

function toggleTipoPersona() {
    const tipo = document.getElementById('tipo_persona').value;
    document.querySelectorAll('.persona-section').forEach(s => s.classList.remove('active'));
    document.getElementById('persona_fisica_block').classList.toggle('active', tipo === 'persona_fisica');
    document.getElementById('persona_moral_block').classList.toggle('active', tipo === 'persona_moral');
    document.getElementById('fideicomiso_block').classList.toggle('active', tipo === 'fideicomiso');
    const pfIds = ['pf_nombre','pf_apellido_paterno','pf_apellido_materno','pf_fecha_nacimiento','pf_rfc','pf_curp','pf_pais_nacionalidad','pf_actividad_economica'];
    const pmIds = ['pm_denominacion','pm_fecha_constitucion','pm_pais_nacionalidad','pm_giro_mercantil','pm_rep_nombre','pm_rep_apellido_paterno','pm_rep_apellido_materno','pm_rep_fecha_nacimiento','pm_rep_rfc','pm_rep_curp'];
    const fidIds = ['fid_denominacion','fid_rfc','fid_identificador','fid_apod_nombre','fid_apod_apellido_paterno','fid_apod_apellido_materno','fid_apod_fecha_nacimiento','fid_apod_rfc','fid_apod_curp'];
    [pfIds, pmIds, fidIds].forEach((ids, i) => {
        const isActive = (i === 0 && tipo === 'persona_fisica') || (i === 1 && tipo === 'persona_moral') || (i === 2 && tipo === 'fideicomiso');
        ids.forEach(id => { const el = document.getElementById(id); if (el) el.required = isActive; });
    });
}

function toggleDuenoTipoPersona() {
    const tipo = document.getElementById('db_tipo_persona').value;
    document.querySelectorAll('.db-persona-section').forEach(s => { s.classList.remove('active'); s.style.display = 'none'; });
    const block = document.getElementById('db_persona_fisica_block');
    const blockM = document.getElementById('db_persona_moral_block');
    const blockF = document.getElementById('db_fideicomiso_block');
    if (tipo === 'persona_fisica') { block.classList.add('active'); block.style.display = 'block'; }
    else if (tipo === 'persona_moral') { blockM.classList.add('active'); blockM.style.display = 'block'; }
    else { blockF.classList.add('active'); blockF.style.display = 'block'; }
    setDuenoBeneficiarioRequired(document.getElementById('incluir_dueno_beneficiario').checked);
}

function toggleDuenoTipoDomicilio() {
    const tipo = document.getElementById('db_tipo_domicilio').value;
    document.querySelectorAll('.db-domicilio-section').forEach(s => { s.classList.remove('active'); s.style.display = 'none'; });
    const nat = document.getElementById('db_domicilio_nacional');
    const ext = document.getElementById('db_domicilio_extranjero');
    if (tipo === 'nacional') { nat.classList.add('active'); nat.style.display = 'block'; }
    else { ext.classList.add('active'); ext.style.display = 'block'; }
    setDuenoBeneficiarioRequired(document.getElementById('incluir_dueno_beneficiario').checked);
}

function setDuenoBeneficiarioRequired(required) {
    const tipo = document.getElementById('db_tipo_persona').value;
    const domTipo = document.getElementById('db_tipo_domicilio').value;
    const pfIds = ['db_pf_nombre','db_pf_apellido_paterno','db_pf_apellido_materno','db_pf_fecha_nacimiento','db_pf_rfc','db_pf_curp','db_pf_pais_nacionalidad','db_pf_actividad_economica'];
    const pmIds = ['db_pm_denominacion','db_pm_fecha_constitucion','db_pm_pais_nacionalidad','db_pm_giro_mercantil','db_pm_rep_nombre','db_pm_rep_apellido_paterno','db_pm_rep_apellido_materno','db_pm_rep_fecha_nacimiento','db_pm_rep_rfc','db_pm_rep_curp'];
    const fidIds = ['db_fid_denominacion','db_fid_rfc','db_fid_identificador','db_fid_apod_nombre','db_fid_apod_apellido_paterno','db_fid_apod_apellido_materno','db_fid_apod_fecha_nacimiento','db_fid_apod_rfc','db_fid_apod_curp'];
    const natIds = ['db_dom_colonia','db_dom_calle','db_dom_numero_exterior','db_dom_codigo_postal'];
    const extIds = ['db_dom_pais','db_dom_estado','db_dom_ciudad','db_dom_ext_colonia','db_dom_ext_calle','db_dom_ext_numero','db_dom_ext_cp'];
    const telIds = ['db_tel_clave_pais','db_tel_numero'];
    const allDb = [...pfIds, ...pmIds, ...fidIds, ...natIds, ...extIds, ...telIds];
    allDb.forEach(id => { const el = document.getElementById(id); if (el) el.required = false; });
    if (!required) return;
    const activePf = tipo === 'persona_fisica' ? pfIds : (tipo === 'persona_moral' ? pmIds : fidIds);
    const activeDom = domTipo === 'nacional' ? natIds : extIds;
    [...activePf, ...activeDom, ...telIds].forEach(id => { const el = document.getElementById(id); if (el) el.required = true; });
}

function toggleTipoDomicilio() {
    const tipo = document.getElementById('tipo_domicilio').value;
    document.querySelectorAll('.domicilio-section').forEach(s => s.classList.remove('active'));
    document.getElementById('domicilio_nacional').classList.toggle('active', tipo === 'nacional');
    document.getElementById('domicilio_extranjero').classList.toggle('active', tipo === 'extranjero');
    const nat = document.querySelectorAll('#domicilio_nacional [required]');
    const ext = document.querySelectorAll('#domicilio_extranjero [required]');
    nat.forEach(el => { el.required = tipo === 'nacional'; });
    ext.forEach(el => { el.required = tipo === 'extranjero'; });
}

function cargarClientes() {
    fetch('api/get_clients.php')
        .then(r => r.text())
        .then(text => {
            let data;
            try { data = JSON.parse(text); } catch (e) {
                console.error('get_clients: respuesta inválida', text.substring(0, 200));
                data = [];
            }
            const sel = document.getElementById('id_cliente');
            sel.innerHTML = '<option value="">-- Seleccione Cliente --</option>';
            (Array.isArray(data) ? data : []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_cliente;
                opt.textContent = c.nombre_cliente || `Cliente #${c.id_cliente}`;
                sel.appendChild(opt);
            });
        })
        .catch(e => console.error('Error clientes:', e));
}

function cargarKYC() {
    const id = document.getElementById('id_cliente').value;
    const preview = document.getElementById('kyc-preview');
    if (!id) { preview.style.display = 'none'; kycDataCache = null; return; }
    fetch('api/get_cliente_kyc_pld.php?id=' + id)
        .then(r => r.text())
        .then(text => {
            let res;
            try { res = JSON.parse(text); } catch (e) {
                console.error('get_cliente_kyc_pld: respuesta inválida', text.substring(0, 200));
                preview.style.display = 'none'; kycDataCache = null; return;
            }
            if (res.status !== 'success') { preview.style.display = 'none'; kycDataCache = null; return; }
            const k = res.kyc;
            kycDataCache = k;
            document.getElementById('kyc-rfc').textContent = k.rfc || '-';
            document.getElementById('kyc-curp').textContent = k.curp || '-';
            document.getElementById('kyc-tipo').textContent = k.tipo_persona || '-';
            document.getElementById('kyc-nombre').textContent = k.denominacion_razon || k.razon_social || k.nombre || '-';
            document.getElementById('kyc-fecha').textContent = k.fecha_nacimiento || k.fecha_constitucion || '-';
            document.getElementById('kyc-pais').textContent = k.pais_nacionalidad || '-';
            preview.style.display = 'block';
        })
        .catch(e => { console.error('Error KYC:', e); preview.style.display = 'none'; kycDataCache = null; });
}

function prefillPersonaFromKyc() {
    const k = kycDataCache;
    if (!k) { Swal.fire('Info', 'Seleccione un cliente y espere a que carguen los datos.', 'info'); return; }
    const tipo = document.getElementById('tipo_persona');
    if ((k.es_fisica || 0) === 1) {
        tipo.value = 'persona_fisica';
        document.getElementById('pf_nombre').value = k.nombre || '';
        document.getElementById('pf_apellido_paterno').value = k.apellido_paterno || '';
        document.getElementById('pf_apellido_materno').value = k.apellido_materno || '';
        document.getElementById('pf_fecha_nacimiento').value = (k.fecha_nacimiento || '').toString().substring(0, 10);
        document.getElementById('pf_rfc').value = k.rfc || '';
        document.getElementById('pf_curp').value = k.curp || '';
        document.getElementById('pf_pais_nacionalidad').value = k.pais_nacionalidad || 'MX';
        document.getElementById('pf_actividad_economica').value = actividadNombreToCodigoSCIAN(k.actividad_economica);
    } else if ((k.es_moral || 0) === 1) {
        tipo.value = 'persona_moral';
        document.getElementById('pm_denominacion').value = k.denominacion_razon || k.razon_social || '';
        document.getElementById('pm_rfc').value = k.rfc || '';
        document.getElementById('pm_fecha_constitucion').value = (k.fecha_constitucion || '').toString().substring(0, 10);
        document.getElementById('pm_pais_nacionalidad').value = k.pais_nacionalidad || 'MX';
        document.getElementById('pm_giro_mercantil').value = k.giro_mercantil || '0000000';
    } else if ((k.es_fideicomiso || 0) === 1) {
        tipo.value = 'fideicomiso';
        document.getElementById('fid_denominacion').value = k.denominacion_razon || '';
        document.getElementById('fid_rfc').value = k.rfc || '';
    }
    toggleTipoPersona();
}

function prefillDuenoBeneficiarioFromKyc() {
    const k = kycDataCache;
    if (!k) { Swal.fire('Info', 'Seleccione un cliente y espere a que carguen los datos.', 'info'); return; }
    const tipo = document.getElementById('db_tipo_persona');
    if ((k.es_fisica || 0) === 1) {
        tipo.value = 'persona_fisica';
        document.getElementById('db_pf_nombre').value = k.nombre || '';
        document.getElementById('db_pf_apellido_paterno').value = k.apellido_paterno || '';
        document.getElementById('db_pf_apellido_materno').value = k.apellido_materno || '';
        document.getElementById('db_pf_fecha_nacimiento').value = (k.fecha_nacimiento || '').toString().substring(0, 10);
        document.getElementById('db_pf_rfc').value = k.rfc || '';
        document.getElementById('db_pf_curp').value = k.curp || '';
        document.getElementById('db_pf_pais_nacionalidad').value = k.pais_nacionalidad || 'MX';
        document.getElementById('db_pf_actividad_economica').value = actividadNombreToCodigoSCIAN(k.actividad_economica);
    } else if ((k.es_moral || 0) === 1) {
        tipo.value = 'persona_moral';
        document.getElementById('db_pm_denominacion').value = k.denominacion_razon || k.razon_social || '';
        document.getElementById('db_pm_rfc').value = k.rfc || '';
        document.getElementById('db_pm_fecha_constitucion').value = (k.fecha_constitucion || '').toString().substring(0, 10);
        document.getElementById('db_pm_pais_nacionalidad').value = k.pais_nacionalidad || 'MX';
        document.getElementById('db_pm_giro_mercantil').value = k.giro_mercantil || '0000000';
    } else if ((k.es_fideicomiso || 0) === 1) {
        tipo.value = 'fideicomiso';
        document.getElementById('db_fid_denominacion').value = k.denominacion_razon || '';
        document.getElementById('db_fid_rfc').value = k.rfc || '';
    }
    toggleDuenoTipoPersona();
}

function leerPersona() {
    const tipo = document.getElementById('tipo_persona').value;
    if (tipo === 'persona_fisica') {
        return { persona_fisica: {
            nombre: v('pf_nombre'),
            apellido_paterno: v('pf_apellido_paterno'),
            apellido_materno: v('pf_apellido_materno'),
            fecha_nacimiento: (v('pf_fecha_nacimiento') || '').replace(/-/g, ''),
            rfc: v('pf_rfc'),
            curp: v('pf_curp'),
            pais_nacionalidad: v('pf_pais_nacionalidad'),
            actividad_economica: v('pf_actividad_economica')
        }};
    } else if (tipo === 'persona_moral') {
        return { persona_moral: {
            denominacion_razon: v('pm_denominacion'),
            rfc: v('pm_rfc'),
            fecha_constitucion: (v('pm_fecha_constitucion') || '').replace(/-/g, ''),
            pais_nacionalidad: v('pm_pais_nacionalidad'),
            giro_mercantil: v('pm_giro_mercantil'),
            representante_apoderado: {
                nombre: v('pm_rep_nombre'),
                apellido_paterno: v('pm_rep_apellido_paterno'),
                apellido_materno: v('pm_rep_apellido_materno'),
                fecha_nacimiento: (v('pm_rep_fecha_nacimiento') || '').replace(/-/g, ''),
                rfc: v('pm_rep_rfc'),
                curp: v('pm_rep_curp')
            }
        }};
    } else {
        return { fideicomiso: {
            denominacion_razon: v('fid_denominacion'),
            rfc: v('fid_rfc'),
            identificador_fideicomiso: v('fid_identificador'),
            apoderado_delegado: {
                nombre: v('fid_apod_nombre'),
                apellido_paterno: v('fid_apod_apellido_paterno'),
                apellido_materno: v('fid_apod_apellido_materno'),
                fecha_nacimiento: (v('fid_apod_fecha_nacimiento') || '').replace(/-/g, ''),
                rfc: v('fid_apod_rfc'),
                curp: v('fid_apod_curp')
            }
        }};
    }
}

function leerDuenoBeneficiario() {
    const tipo = document.getElementById('db_tipo_persona').value;
    let tipoPersona = {};
    if (tipo === 'persona_fisica') {
        tipoPersona = { persona_fisica: {
            nombre: v('db_pf_nombre'),
            apellido_paterno: v('db_pf_apellido_paterno'),
            apellido_materno: v('db_pf_apellido_materno'),
            fecha_nacimiento: (v('db_pf_fecha_nacimiento') || '').replace(/-/g, ''),
            rfc: v('db_pf_rfc'),
            curp: v('db_pf_curp'),
            pais_nacionalidad: v('db_pf_pais_nacionalidad'),
            actividad_economica: v('db_pf_actividad_economica')
        }};
    } else if (tipo === 'persona_moral') {
        tipoPersona = { persona_moral: {
            denominacion_razon: v('db_pm_denominacion'),
            rfc: v('db_pm_rfc'),
            fecha_constitucion: (v('db_pm_fecha_constitucion') || '').replace(/-/g, ''),
            pais_nacionalidad: v('db_pm_pais_nacionalidad'),
            giro_mercantil: v('db_pm_giro_mercantil'),
            representante_apoderado: {
                nombre: v('db_pm_rep_nombre'),
                apellido_paterno: v('db_pm_rep_apellido_paterno'),
                apellido_materno: v('db_pm_rep_apellido_materno'),
                fecha_nacimiento: (v('db_pm_rep_fecha_nacimiento') || '').replace(/-/g, ''),
                rfc: v('db_pm_rep_rfc'),
                curp: v('db_pm_rep_curp')
            }
        }};
    } else {
        tipoPersona = { fideicomiso: {
            denominacion_razon: v('db_fid_denominacion'),
            rfc: v('db_fid_rfc'),
            identificador_fideicomiso: v('db_fid_identificador'),
            apoderado_delegado: {
                nombre: v('db_fid_apod_nombre'),
                apellido_paterno: v('db_fid_apod_apellido_paterno'),
                apellido_materno: v('db_fid_apod_apellido_materno'),
                fecha_nacimiento: (v('db_fid_apod_fecha_nacimiento') || '').replace(/-/g, ''),
                rfc: v('db_fid_apod_rfc'),
                curp: v('db_fid_apod_curp')
            }
        }};
    }
    const domTipo = document.getElementById('db_tipo_domicilio').value;
    let tipoDomicilio = {};
    if (domTipo === 'nacional') {
        tipoDomicilio = { nacional: {
            colonia: v('db_dom_colonia'),
            calle: v('db_dom_calle'),
            numero_exterior: v('db_dom_numero_exterior'),
            numero_interior: v('db_dom_numero_interior') || undefined,
            codigo_postal: v('db_dom_codigo_postal')
        }};
    } else {
        tipoDomicilio = { extranjero: {
            pais: v('db_dom_pais'),
            estado_provincia: v('db_dom_estado'),
            ciudad_poblacion: v('db_dom_ciudad'),
            colonia: v('db_dom_ext_colonia'),
            calle: v('db_dom_ext_calle'),
            numero_exterior: v('db_dom_ext_numero'),
            numero_interior: v('db_dom_ext_numero_int') || undefined,
            codigo_postal: v('db_dom_ext_cp')
        }};
    }
    return {
        tipo_persona: tipoPersona,
        tipo_domicilio: tipoDomicilio,
        telefono: {
            clave_pais: v('db_tel_clave_pais'),
            numero_telefono: v('db_tel_numero'),
            correo_electronico: (v('db_tel_correo') || '').trim() ? (v('db_tel_correo') || '').toUpperCase() : undefined
        }
    };
}

function leerDomicilio() {
    const tipo = document.getElementById('tipo_domicilio').value;
    if (tipo === 'nacional') {
        return { nacional: {
            colonia: v('dom_colonia'),
            calle: v('dom_calle'),
            numero_exterior: v('dom_numero_exterior'),
            numero_interior: v('dom_numero_interior') || undefined,
            codigo_postal: v('dom_codigo_postal')
        }};
    } else {
        return { extranjero: {
            pais: v('dom_pais'),
            estado_provincia: v('dom_estado'),
            ciudad_poblacion: v('dom_ciudad'),
            colonia: v('dom_ext_colonia'),
            calle: v('dom_ext_calle'),
            numero_exterior: v('dom_ext_numero'),
            numero_interior: v('dom_ext_numero_int') || undefined,
            codigo_postal: v('dom_ext_cp')
        }};
    }
}

function validarFormularioTSC() {
    const idCliente = v('id_cliente');
    if (!idCliente) { Swal.fire('Error', 'Seleccione un cliente', 'error'); return false; }
    const mesRep = v('mes_reportado');
    if (!mesRep || !/^\d{6}$/.test(mesRep)) { Swal.fire('Error', 'Mes reportado debe ser 6 dígitos (AAAAMM)', 'error'); return false; }
    const mm = parseInt(mesRep.substring(4, 6), 10);
    if (mm < 1 || mm > 12) { Swal.fire('Error', 'Mes reportado: mes inválido (01-12)', 'error'); return false; }
    const claveSO = (v('clave_sujeto_obligado') || '').replace(/[^A-Za-z0-9Ñ&]/g, '').toUpperCase();
    if (!claveSO) { Swal.fire('Error', 'Clave Sujeto Obligado es obligatoria', 'error'); return false; }
    if (!/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/.test(claveSO)) {
        Swal.fire('Error', 'Clave Sujeto Obligado debe ser RFC: 3-4 letras + 6 dígitos + 3 caracteres (ej: ABC010203AB1). No usar folio o texto libre.', 'error');
        return false;
    }
    const refAviso = v('referencia_aviso');
    if (!refAviso) { Swal.fire('Error', 'Referencia del aviso es obligatoria', 'error'); return false; }
    if (!/^[A-ZÑ0-9]{1,14}$/i.test(refAviso)) { Swal.fire('Error', 'Referencia: solo A-Z, Ñ, 0-9, máx 14 caracteres', 'error'); return false; }
    if (!v('prioridad')) { Swal.fire('Error', 'Prioridad es obligatoria', 'error'); return false; }
    if (!v('tipo_alerta')) { Swal.fire('Error', 'Tipo de alerta es obligatorio', 'error'); return false; }
    const fechaPer = v('fecha_periodo');
    if (!fechaPer || !/^\d{6}$/.test(fechaPer)) { Swal.fire('Error', 'Periodo a reportar debe ser 6 dígitos (AAAAMM)', 'error'); return false; }
    const fpMm = parseInt(fechaPer.substring(4, 6), 10);
    if (fpMm < 1 || fpMm > 12) { Swal.fire('Error', 'Periodo a reportar: mes inválido (01-12)', 'error'); return false; }
    if (!v('tipo_operacion')) { Swal.fire('Error', 'Tipo de operación es obligatorio', 'error'); return false; }
    if (!v('tipo_tarjeta')) { Swal.fire('Error', 'Tipo de tarjeta es obligatorio', 'error'); return false; }
    const numId = v('numero_identificador');
    if (!numId) { Swal.fire('Error', 'Número identificador es obligatorio', 'error'); return false; }
    if (!/^[A-Z0-9]{1,18}$/i.test(numId)) { Swal.fire('Error', 'Número identificador: solo A-Z, 0-9, máx 18 caracteres', 'error'); return false; }
    const monto = parseFloat(v('monto_gasto'));
    if (isNaN(monto) || monto < 0) { Swal.fire('Error', 'Monto gasto es obligatorio y debe ser mayor o igual a 0', 'error'); return false; }

    if (v('es_modificatorio') === '1') {
        const folio = v('folio_modificacion');
        if (!folio) { Swal.fire('Error', 'Folio modificación es obligatorio en aviso modificatorio', 'error'); return false; }
        if (!/^\d{4}-\d{1,9}$/.test(folio)) { Swal.fire('Error', 'Folio modificación: formato AAAA-N (ej. 2026-123456789)', 'error'); return false; }
        if (!v('descripcion_modificacion')) { Swal.fire('Error', 'Descripción modificación es obligatoria', 'error'); return false; }
    }

    const telNum = v('tel_numero');
    if (!telNum) { Swal.fire('Error', 'Número de teléfono es obligatorio', 'error'); return false; }
    if (!/^\d{10,12}$/.test(telNum)) { Swal.fire('Error', 'Teléfono: 10 a 12 dígitos', 'error'); return false; }

    const tipoPers = document.getElementById('tipo_persona').value;
    const tipoDom = document.getElementById('tipo_domicilio').value;

    if (tipoDom === 'nacional') {
        const cp = v('dom_codigo_postal');
        if (!/^\d{5}$/.test(cp)) { Swal.fire('Error', 'Código postal nacional: 5 dígitos', 'error'); return false; }
    }
    const pfReqs = ['pf_nombre','pf_apellido_paterno','pf_apellido_materno','pf_fecha_nacimiento','pf_rfc','pf_curp','pf_pais_nacionalidad','pf_actividad_economica'];
    const pmReqs = ['pm_denominacion','pm_fecha_constitucion','pm_pais_nacionalidad','pm_giro_mercantil','pm_rep_nombre','pm_rep_apellido_paterno','pm_rep_apellido_materno','pm_rep_fecha_nacimiento','pm_rep_rfc','pm_rep_curp'];
    const fidReqs = ['fid_denominacion','fid_rfc','fid_identificador','fid_apod_nombre','fid_apod_apellido_paterno','fid_apod_apellido_materno','fid_apod_fecha_nacimiento','fid_apod_rfc','fid_apod_curp'];
    const domNatReqs = ['dom_colonia','dom_calle','dom_numero_exterior','dom_codigo_postal'];
    const domExtReqs = ['dom_pais','dom_estado','dom_ciudad','dom_ext_colonia','dom_ext_calle','dom_ext_numero','dom_ext_cp'];
    const persReqs = tipoPers === 'persona_fisica' ? pfReqs : (tipoPers === 'persona_moral' ? pmReqs : fidReqs);
    const domReqs = tipoDom === 'nacional' ? domNatReqs : domExtReqs;
    for (const id of [...persReqs, ...domReqs, 'tel_clave_pais', 'tel_numero']) {
        if (!v(id)) {
            Swal.fire('Error', 'Complete todos los datos de la Persona objeto del aviso', 'error');
            const el = document.getElementById(id);
            if (el) { el.focus(); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
            return false;
        }
    }
    // Validación de formato RFC, CURP, fechas YYYYMMDD
    const rfc13 = /^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/i;
    const rfc12 = /^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/i;
    const curp18 = /^[A-Z]{4}\d{6}[HM][A-Z]{5}[0-9A]\d$/i;
    const fecha8 = /^\d{8}$/;
    const validarRfc = (val, len) => (len === 12 ? rfc12 : rfc13).test((val || '').replace(/\s/g, ''));
    const validarCurp = (val) => curp18.test((val || '').replace(/\s/g, ''));
    const validarFecha8 = (val) => fecha8.test((val || '').replace(/-/g, ''));
    if (tipoPers === 'persona_fisica') {
        if (!validarRfc(v('pf_rfc'), 13)) { Swal.fire('Error', 'RFC Persona Física: formato inválido (13 car. ej: LOPG900101ABC)', 'error'); return false; }
        if (!validarCurp(v('pf_curp'))) { Swal.fire('Error', 'CURP: formato inválido (18 car. ej: LOPG900101HDFLRN01)', 'error'); return false; }
        if (!validarFecha8(v('pf_fecha_nacimiento'))) { Swal.fire('Error', 'Fecha nacimiento: formato YYYYMMDD (ej: 19900101)', 'error'); return false; }
    } else if (tipoPers === 'persona_moral') {
        if (v('pm_rfc') && !validarRfc(v('pm_rfc'), 12)) { Swal.fire('Error', 'RFC Persona Moral: formato inválido (12 car.)', 'error'); return false; }
        if (!validarFecha8(v('pm_fecha_constitucion'))) { Swal.fire('Error', 'Fecha constitución: formato YYYYMMDD', 'error'); return false; }
        if (!validarRfc(v('pm_rep_rfc'), 13)) { Swal.fire('Error', 'RFC Representante: formato inválido (13 car.)', 'error'); return false; }
        if (!validarCurp(v('pm_rep_curp'))) { Swal.fire('Error', 'CURP Representante: formato inválido (18 car.)', 'error'); return false; }
        if (!validarFecha8(v('pm_rep_fecha_nacimiento'))) { Swal.fire('Error', 'Fecha nacimiento representante: formato YYYYMMDD', 'error'); return false; }
    } else {
        if (!validarRfc(v('fid_rfc'), 12)) { Swal.fire('Error', 'RFC Fideicomiso: formato inválido (12 car.)', 'error'); return false; }
        if (!validarRfc(v('fid_apod_rfc'), 13)) { Swal.fire('Error', 'RFC Apoderado: formato inválido (13 car.)', 'error'); return false; }
        if (!validarCurp(v('fid_apod_curp'))) { Swal.fire('Error', 'CURP Apoderado: formato inválido (18 car.)', 'error'); return false; }
        if (!validarFecha8(v('fid_apod_fecha_nacimiento'))) { Swal.fire('Error', 'Fecha nacimiento apoderado: formato YYYYMMDD', 'error'); return false; }
    }
    const correoVal = (v('tel_correo') || '').trim();
    if (correoVal && !/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/.test(correoVal)) {
        Swal.fire('Error', 'Correo electrónico: formato inválido', 'error'); return false;
    }

    const incluirDb = document.getElementById('incluir_dueno_beneficiario').checked;
    if (incluirDb) {
        const tipo = document.getElementById('db_tipo_persona').value;
        const domTipo = document.getElementById('db_tipo_domicilio').value;
        const pfReqs = ['db_pf_nombre','db_pf_apellido_paterno','db_pf_apellido_materno','db_pf_fecha_nacimiento','db_pf_rfc','db_pf_curp','db_pf_pais_nacionalidad','db_pf_actividad_economica'];
        const pmReqs = ['db_pm_denominacion','db_pm_fecha_constitucion','db_pm_pais_nacionalidad','db_pm_giro_mercantil','db_pm_rep_nombre','db_pm_rep_apellido_paterno','db_pm_rep_apellido_materno','db_pm_rep_fecha_nacimiento','db_pm_rep_rfc','db_pm_rep_curp'];
        const fidReqs = ['db_fid_denominacion','db_fid_rfc','db_fid_identificador','db_fid_apod_nombre','db_fid_apod_apellido_paterno','db_fid_apod_apellido_materno','db_fid_apod_fecha_nacimiento','db_fid_apod_rfc','db_fid_apod_curp'];
        const natReqs = ['db_dom_colonia','db_dom_calle','db_dom_numero_exterior','db_dom_codigo_postal'];
        const extReqs = ['db_dom_pais','db_dom_estado','db_dom_ciudad','db_dom_ext_colonia','db_dom_ext_calle','db_dom_ext_numero','db_dom_ext_cp'];
        const telReqs = ['db_tel_clave_pais','db_tel_numero'];
        const activePersona = tipo === 'persona_fisica' ? pfReqs : (tipo === 'persona_moral' ? pmReqs : fidReqs);
        const activeDom = domTipo === 'nacional' ? natReqs : extReqs;
        const todos = [...activePersona, ...activeDom, ...telReqs];
        for (const id of todos) {
            if (!v(id)) {
                Swal.fire('Error', 'Complete todos los datos obligatorios del Dueño Beneficiario', 'error');
                const el = document.getElementById(id);
                if (el) { el.focus(); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                return false;
            }
        }
        if (domTipo === 'nacional') {
            const dbCp = v('db_dom_codigo_postal');
            if (!/^\d{5}$/.test(dbCp)) { Swal.fire('Error', 'Código postal Dueño Beneficiario: 5 dígitos', 'error'); return false; }
        }
        if (tipo === 'persona_fisica') {
            if (v('db_pf_rfc') && !validarRfc(v('db_pf_rfc'), 13)) { Swal.fire('Error', 'RFC Dueño Beneficiario PF: formato inválido', 'error'); return false; }
            if (v('db_pf_curp') && !validarCurp(v('db_pf_curp'))) { Swal.fire('Error', 'CURP Dueño Beneficiario: formato inválido', 'error'); return false; }
            if (v('db_pf_fecha_nacimiento') && !validarFecha8(v('db_pf_fecha_nacimiento'))) { Swal.fire('Error', 'Fecha nacimiento DB: formato YYYYMMDD', 'error'); return false; }
        } else if (tipo === 'persona_moral') {
            if (v('db_pm_rfc') && !validarRfc(v('db_pm_rfc'), 12)) { Swal.fire('Error', 'RFC DB Moral: formato inválido', 'error'); return false; }
            if (v('db_pm_fecha_constitucion') && !validarFecha8(v('db_pm_fecha_constitucion'))) { Swal.fire('Error', 'Fecha constitución DB: formato YYYYMMDD', 'error'); return false; }
            if (v('db_pm_rep_rfc') && !validarRfc(v('db_pm_rep_rfc'), 13)) { Swal.fire('Error', 'RFC Representante DB: formato inválido', 'error'); return false; }
            if (v('db_pm_rep_curp') && !validarCurp(v('db_pm_rep_curp'))) { Swal.fire('Error', 'CURP Representante DB: formato inválido', 'error'); return false; }
        } else {
            if (v('db_fid_rfc') && !validarRfc(v('db_fid_rfc'), 12)) { Swal.fire('Error', 'RFC Fideicomiso DB: formato inválido', 'error'); return false; }
            if (v('db_fid_apod_rfc') && !validarRfc(v('db_fid_apod_rfc'), 13)) { Swal.fire('Error', 'RFC Apoderado DB: formato inválido', 'error'); return false; }
            if (v('db_fid_apod_curp') && !validarCurp(v('db_fid_apod_curp'))) { Swal.fire('Error', 'CURP Apoderado DB: formato inválido', 'error'); return false; }
        }
        const dbCorreo = (v('db_tel_correo') || '').trim();
        if (dbCorreo && !/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/.test(dbCorreo)) {
            Swal.fire('Error', 'Correo Dueño Beneficiario: formato inválido', 'error'); return false;
        }
    }
    return true;
}

function guardarAvisoTSC(e) {
    e.preventDefault();
    if (!validarFormularioTSC()) return;

    const idCliente = v('id_cliente');
    const btn = e.target.querySelector('button[type="submit"]');
    if (btn && btn.disabled) return;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Registrando...';
    }

    const aviso = {
        referencia_aviso: v('referencia_aviso'),
        prioridad: v('prioridad'),
        alerta: {
            tipo_alerta: v('tipo_alerta'),
            descripcion_alerta: v('descripcion_alerta')
        },
        persona_aviso: (() => {
            const pa = {
                tipo_persona: leerPersona(),
                tipo_domicilio: leerDomicilio(),
                telefono: {
                    clave_pais: v('tel_clave_pais'),
                    numero_telefono: v('tel_numero'),
                    correo_electronico: (v('tel_correo') || '').trim() ? (v('tel_correo') || '').toUpperCase() : undefined
                }
            };
            if (document.getElementById('incluir_dueno_beneficiario').checked) {
                pa.dueno_beneficiario = leerDuenoBeneficiario();
            }
            return pa;
        })(),
        detalle_operaciones: [{
            datos_operacion: [{
                fecha_periodo: v('fecha_periodo'),
                tipo_operacion: v('tipo_operacion'),
                tipo_tarjeta: v('tipo_tarjeta'),
                numero_identificador: v('numero_identificador'),
                monto_gasto: parseFloat(v('monto_gasto')) || 0
            }]
        }]
    };

    if (v('es_modificatorio') === '1') {
        aviso.modificatorio = {
            folio_modificacion: v('folio_modificacion'),
            descripcion_modificacion: v('descripcion_modificacion')
        };
    }

    const sujetoObligado = {
        clave_sujeto_obligado: (v('clave_sujeto_obligado') || '').replace(/[^A-Za-z0-9Ñ&]/g, '').toUpperCase(),
        clave_actividad: v('clave_actividad'),
        exento: v('exento')
    };
    if (v('clave_entidad_colegiada')) sujetoObligado.clave_entidad_colegiada = v('clave_entidad_colegiada');

    const payload = {
        id_cliente: parseInt(idCliente),
        informe: [{
            mes_reportado: v('mes_reportado'),
            sujeto_obligado: sujetoObligado,
            aviso: [aviso]
        }]
    };

    fetch('api/registrar_aviso_tsc.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.text().then(t => ({ ok: r.ok, status: r.status, text: t })))
    .then(({ ok, status, text }) => {
        let data;
        try { data = JSON.parse(text); } catch (e) {
            console.error('registrar_aviso_tsc: respuesta inválida (HTTP ' + status + ')', text.substring(0, 300));
            const hint = (text.trim().indexOf('<') === 0) 
                ? ' El servidor devolvió HTML (posible Xdebug). Desactive Xdebug en php.ini (xdebug.mode=off) para APIs.' 
                : '';
            throw new Error(!ok ? 'Error del servidor (HTTP ' + status + ').' + hint : 'Respuesta inválida.');
        }
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Registrar y Generar XML';
        }
        if (data.status === 'success') {
            let html = '<p>Operación registrada correctamente.</p>';
            if (data.requiere_aviso) html += '<p><strong>Requiere aviso.</strong> Deadline: ' + (data.fecha_deadline || '') + '</p>';
            html += data.xml_generado ? '<p>XML almacenado.</p>' : '<p class="text-warning">XML no generado.</p>';
            if (data.xml_advertencia) html += '<p class="text-warning small">' + (data.xml_advertencia.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')) + '</p>';
            Swal.fire({ icon: 'success', title: 'Aviso TSC registrado', html }).then(() => {
                window.location.href = 'operaciones_pld.php';
            });
        } else {
            Swal.fire('Error', data.message || 'Error al registrar', 'error');
        }
    })
    .catch(err => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Registrar y Generar XML';
        }
        console.error(err);
        Swal.fire('Error', err.message || 'Error de conexión', 'error');
    });
}
</script>

<?php include 'templates/footer.php'; ?>
