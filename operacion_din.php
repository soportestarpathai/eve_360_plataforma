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
$userFracciones = getUserFraccionesPLD($pdo, $userId);
if (!userCanAccessDIN($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_din');
    exit;
}

$id_fraccion = (int)($_GET['id_fraccion'] ?? 0);
$page_title = 'Transacción DIN - Desarrollo Inmobiliario';
include 'templates/header.php';

$clave_sujeto_obligado = '';
try {
    if ($userId > 0) {
        $stmtU = $pdo->prepare("SELECT folio_patron_pld FROM config_empresa_usuario WHERE id_usuario = ?");
        $stmtU->execute([$userId]);
        $config = $stmtU->fetch(PDO::FETCH_ASSOC);
    }
    if (empty($config['folio_patron_pld'])) {
        $stmt = $pdo->query("SELECT folio_patron_pld FROM config_empresa WHERE id_config = 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $clave_sujeto_obligado = $config['folio_patron_pld'] ?? '';
} catch (Exception $e) { /* fallback vacío */ }

require_once 'config/din_catalogos.php';

try {
    $stmtPaises = $pdo->query("SELECT clave, nombre FROM cat_pais ORDER BY nombre");
    $paisesDB = $stmtPaises->fetchAll(PDO::FETCH_ASSOC);
    $DIN_CATALOGOS['pais'] = [];
    foreach ($paisesDB as $p) {
        if (!empty($p['clave'])) {
            $DIN_CATALOGOS['pais'][$p['clave']] = $p['nombre'];
        }
    }
} catch (Exception $e) {
    $DIN_CATALOGOS['pais'] = ['MX' => 'México', 'US' => 'Estados Unidos'];
}
?>
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/operaciones_pld.css">
<style>
:root {
    --din-primary: #4361ee;
    --din-primary-dark: #3a0ca3;
    --din-info: #4cc9f0;
    --din-success: #06d6a0;
    --din-warning: #f77f00;
    --din-danger: #ef476f;
    --din-dark: #1d3557;
    --din-light: #f8f9fc;
    --din-border: #e2e8f0;
    --din-shadow: 0 4px 24px rgba(0,0,0,.06);
    --din-radius: 16px;
    --din-radius-sm: 10px;
    --din-transition: .25s cubic-bezier(.4,0,.2,1);
    --din-max-width: 960px;
}

/* ─── Contenedor angosto centrado ─── */
.din-wrapper {
    max-width: var(--din-max-width);
    margin: 0 auto;
}

/* ─── Progress Steps ─── */
.din-progress { display:flex; gap:0; margin-bottom:2rem; overflow-x:auto; padding-bottom:4px; }
.din-step {
    flex:1; min-width:120px; text-align:center; position:relative; padding:.75rem .5rem;
    font-size:.78rem; font-weight:600; color:#94a3b8; cursor:pointer;
    transition:var(--din-transition);
}
.din-step::after {
    content:''; position:absolute; bottom:0; left:0; width:100%; height:3px;
    background:#e2e8f0; border-radius:3px; transition:var(--din-transition);
}
.din-step.active { color:var(--din-primary); }
.din-step.active::after { background:var(--din-primary); }
.din-step.done { color:var(--din-success); }
.din-step.done::after { background:var(--din-success); }
.din-step-num {
    display:inline-flex; align-items:center; justify-content:center;
    width:28px; height:28px; border-radius:50%; font-size:.75rem; font-weight:700;
    background:#e2e8f0; color:#64748b; margin-bottom:4px; transition:var(--din-transition);
}
.din-step.active .din-step-num { background:var(--din-primary); color:#fff; }
.din-step.done .din-step-num { background:var(--din-success); color:#fff; }

/* ─── Section Cards ─── */
.din-card {
    border:none; border-radius:var(--din-radius); background:#fff;
    box-shadow:var(--din-shadow); margin-bottom:1.5rem;
    overflow:hidden; transition:var(--din-transition);
}
.din-card:hover { box-shadow:0 8px 32px rgba(0,0,0,.09); }
.din-card-header {
    padding:1rem 1.5rem; display:flex; align-items:center; gap:.75rem;
    cursor:pointer; user-select:none; transition:var(--din-transition);
    border-bottom:1px solid transparent;
}
.din-card-header:hover { background:rgba(0,0,0,.015); }
.din-card-header .din-icon {
    width:40px; height:40px; border-radius:var(--din-radius-sm);
    display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#fff;
    flex-shrink:0;
}
.din-card-header h5 { margin:0; font-size:1rem; font-weight:700; color:var(--din-dark); }
.din-card-header small { color:#94a3b8; font-size:.78rem; font-weight:400; display:block; }
.din-card-header .din-chevron {
    margin-left:auto; font-size:.85rem; color:#94a3b8; transition:var(--din-transition);
}
.din-card-header.collapsed .din-chevron { transform:rotate(-90deg); }
.din-card-body { padding:1.25rem 1.5rem; }

.icon-kyc { background:linear-gradient(135deg,var(--din-primary),var(--din-primary-dark)); }
.icon-informe { background:linear-gradient(135deg,#6366f1,#8b5cf6); }
.icon-aviso { background:linear-gradient(135deg,var(--din-warning),#fb8500); }
.icon-desarrollo { background:linear-gradient(135deg,var(--din-info),#0077b6); }
.icon-aportacion { background:linear-gradient(135deg,var(--din-success),#028a6e); }
.icon-pld { background:linear-gradient(135deg,var(--din-danger),#d62828); }

/* ─── Section toggling ─── */
.din-section { display:none; }
.din-section.active { display:block; animation:dinFadeIn .3s ease; }
@keyframes dinFadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

.persona-section, .domicilio-section { display:none; }
.persona-section.active, .domicilio-section.active { display:block; animation:dinFadeIn .25s ease; }
.text-uppercase { text-transform: uppercase; }

/* ─── Nested cards ─── */
.nested-card {
    border-left:3px solid var(--din-primary); background:var(--din-light);
    border-radius:0 var(--din-radius-sm) var(--din-radius-sm) 0;
}

/* ─── Dynamic items ─── */
.socio-item, .tercero-item, .acreedor-item {
    border:1px solid var(--din-border); border-radius:var(--din-radius-sm);
    padding:1.25rem; margin-bottom:1rem; background:#fff;
    transition:var(--din-transition); position:relative;
}
.socio-item:hover, .tercero-item:hover, .acreedor-item:hover {
    border-color:var(--din-primary); box-shadow:0 2px 12px rgba(67,97,238,.1);
}

/* ─── Labels & Helpers ─── */
.din-card .form-label {
    font-size:.8rem; font-weight:600; color:#475569;
    text-transform:none; letter-spacing:0; margin-bottom:.35rem;
    display:flex; align-items:center; flex-wrap:wrap; gap:.3rem;
}
.section-help { font-size:.7rem; color:#a0aec0; margin-top:.15rem; line-height:1.25; }
.badge-xsd { font-size:.58rem; vertical-align:middle; padding:.15em .4em; border-radius:3px; font-weight:600; white-space:nowrap; }

/* ─── Form Controls inside DIN ─── */
.din-card .form-control, .din-card .form-select {
    font-size:.875rem; padding:.55rem .85rem; border-radius:8px;
    border:1.5px solid var(--din-border); transition:var(--din-transition);
}
.din-card .form-control:focus, .din-card .form-select:focus {
    border-color:var(--din-primary); box-shadow:0 0 0 3px rgba(67,97,238,.12);
}
.din-card .form-control[readonly] { background:#f1f5f9; color:#64748b; }

/* ─── Submit bar ─── */
.din-submit-bar {
    position:sticky; bottom:0; background:#fff; padding:1rem 1.5rem;
    border-top:1px solid var(--din-border); border-radius:var(--din-radius) var(--din-radius) 0 0;
    box-shadow:0 -4px 20px rgba(0,0,0,.06); z-index:10;
    display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
}
.din-submit-bar .btn-primary {
    background:linear-gradient(135deg,var(--din-primary),var(--din-primary-dark));
    border:none; padding:.7rem 2rem; font-weight:700; border-radius:var(--din-radius-sm);
    box-shadow:0 4px 14px rgba(67,97,238,.3); transition:var(--din-transition);
}
.din-submit-bar .btn-primary:hover {
    transform:translateY(-2px); box-shadow:0 6px 20px rgba(67,97,238,.4);
}

/* ─── Page Header ─── */
.din-page-header {
    background:linear-gradient(135deg,var(--din-primary) 0%,var(--din-primary-dark) 100%);
    color:#fff; border-radius:var(--din-radius); padding:1.75rem 2rem; margin-bottom:1.75rem;
    position:relative; overflow:hidden;
}
.din-page-header::before {
    content:''; position:absolute; top:-50%; right:-10%; width:300px; height:300px;
    background:rgba(255,255,255,.06); border-radius:50%;
}
.din-page-header h2 { font-size:1.5rem; font-weight:800; margin-bottom:.25rem; }
.din-page-header p { opacity:.8; margin:0; font-size:.9rem; }
.din-page-header a { color:#fff; text-decoration:underline; opacity:.85; }
.din-page-header a:hover { opacity:1; }
.din-page-header .btn-outline-light {
    border:1.5px solid rgba(255,255,255,.5); color:#fff; border-radius:8px;
    font-weight:600; backdrop-filter:blur(4px); transition:var(--din-transition);
}
.din-page-header .btn-outline-light:hover { background:rgba(255,255,255,.15); border-color:#fff; }

/* ─── KYC preview ─── */
#kyc-preview {
    background:linear-gradient(135deg,#f0f4ff,#f8f9fc); border:1px solid #dbeafe;
    border-radius:var(--din-radius-sm); padding:1rem 1.25rem;
}
#kyc-preview strong { color:var(--din-dark); font-size:.82rem; }
#kyc-preview span { color:#475569; font-size:.85rem; }

/* ─── Responsive ─── */
@media(max-width:992px) {
    .din-page-header { padding:1.25rem; }
    .din-page-header h2 { font-size:1.2rem; }
    .din-card-body { padding:1rem; }
    .din-progress { gap:0; }
    .din-step { min-width:80px; font-size:.7rem; padding:.5rem .25rem; }
    .din-step-num { width:24px; height:24px; font-size:.65rem; }
}
@media(max-width:576px) {
    .din-page-header { border-radius:0; margin:-1rem -1rem 1rem; padding:1.25rem 1rem; }
    .din-submit-bar { flex-direction:column; }
    .din-submit-bar .btn { width:100%; }
    .din-card { border-radius:var(--din-radius-sm); }
    .din-card-header { padding:.85rem 1rem; }
    .din-card-body { padding:.85rem 1rem; }
    .socio-item, .tercero-item, .acreedor-item { padding:.85rem; }
    .nested-card { padding:.85rem !important; }
}
</style>
</head>
<body>

<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="content-wrapper">
<div class="din-wrapper">
    <div class="din-page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2><i class="fa-solid fa-building me-2"></i>Formulario DIN</h2>
                <p>Desarrollo Inmobiliario — Fracción V / V Bis
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

    <nav class="din-progress" aria-label="Progreso del formulario">
        <div class="din-step active" data-target="sec-kyc"><div class="din-step-num">1</div><div>Cliente</div></div>
        <div class="din-step" data-target="sec-informe"><div class="din-step-num">2</div><div>Informe</div></div>
        <div class="din-step" data-target="sec-aviso"><div class="din-step-num">3</div><div>Aviso</div></div>
        <div class="din-step" data-target="sec-desarrollo"><div class="din-step-num">4</div><div>Desarrollo</div></div>
        <div class="din-step" data-target="sec-aportaciones"><div class="din-step-num">5</div><div>Aportaciones</div></div>
        <div class="din-step" data-target="sec-pld"><div class="din-step-num">6</div><div>PLD</div></div>
    </nav>

    <form id="formDIN" novalidate>

        <!-- SECCIÓN 0: Cliente (KYC) -->
        <div class="din-card" id="sec-kyc">
            <div class="din-card-header" onclick="toggleDinCard(this)">
                <div class="din-icon icon-kyc"><i class="fa-solid fa-user"></i></div>
                <div><h5>Cliente KYC</h5><small>Datos prellenados desde el expediente de identificación</small></div>
                <i class="fa-solid fa-chevron-down din-chevron"></i>
            </div>
            <div class="din-card-body">
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
                        <small class="text-muted d-block mb-1">Prellenar con estos datos:</small>
                        <button type="button" class="btn btn-outline-primary btn-sm me-1 mb-1" onclick="prefillFirstSocio()" id="btn-prefill-socio" title="Al agregar Socio, se prellenará automáticamente. O use este botón si ya hay socios.">
                            <i class="fa-solid fa-user-check me-1"></i>1er Socio
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm me-1 mb-1" onclick="prefillFirstTercero()" id="btn-prefill-tercero" title="Prellenar el primer Tercero con los datos del cliente.">
                            <i class="fa-solid fa-user-check me-1"></i>1er Tercero
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm mb-1" onclick="prefillFirstAcreedor()" id="btn-prefill-acreedor" title="Prellenar el primer Acreedor con los datos del cliente.">
                            <i class="fa-solid fa-user-check me-1"></i>1er Acreedor
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 1-2: Informe / Sujeto Obligado -->
        <div class="din-card" id="sec-informe">
            <div class="din-card-header" onclick="toggleDinCard(this)">
                <div class="din-icon icon-informe"><i class="fa-solid fa-file-alt"></i></div>
                <div><h5>Informe y Sujeto Obligado</h5><small>Mes reportado, clave del obligado y actividad</small></div>
                <i class="fa-solid fa-chevron-down din-chevron"></i>
            </div>
            <div class="din-card-body">
                <div class="row g-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Mes reportado (AAAAMM) * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control" id="mes_reportado" pattern="\d{6}" maxlength="6" required
                               placeholder="Ej: 202602" value="<?= date('Ym') ?>">
                        <div class="section-help">6 dígitos numéricos AAAAMM</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Clave Sujeto Obligado * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control" id="clave_sujeto_obligado" required maxlength="13"
                               value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" placeholder="RFC empresa 12-13 car.">
                        <div class="section-help">RFC de la empresa, 12-13 caracteres</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Clave Actividad * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control" id="clave_actividad" value="DIN" readonly maxlength="3">
                        <div class="section-help">Fijo "DIN" para Desarrollo Inmobiliario</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Entidad Colegiada <span class="badge bg-warning text-dark badge-xsd">Opc.</span></label>
                        <input type="text" class="form-control" id="clave_entidad_colegiada" maxlength="12"
                               placeholder="LLLAAMMDDXXX">
                        <div class="section-help">Solo si está registrado con entidad</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Exento (Art. 27 Bis) <span class="badge bg-warning text-dark badge-xsd">Cond.</span></label>
                        <select class="form-select" id="exento">
                            <option value="">-- No aplica --</option>
                            <option value="1">1 - Sí</option>
                            <option value="0">0 - No</option>
                        </select>
                        <div class="section-help">Obligatorio si aplica Art. 27 Bis</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 3: Aviso -->
        <div class="din-card" id="sec-aviso">
            <div class="din-card-header" onclick="toggleDinCard(this)">
                <div class="din-icon icon-aviso"><i class="fa-solid fa-bell"></i></div>
                <div><h5>Aviso</h5><small>Referencia, prioridad, alerta y tipo de operación</small></div>
                <i class="fa-solid fa-chevron-down din-chevron"></i>
            </div>
            <div class="din-card-body">
                <div class="row g-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Referencia Aviso * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control" id="referencia_aviso" maxlength="14" required placeholder="Ej: REF202601001">
                        <div class="section-help">1-14 caracteres alfanuméricos</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Prioridad * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="prioridad" required>
                            <?= dinCatalogoOptions('prioridad', '1') ?>
                        </select>
                        <div class="section-help">Catálogo UIF</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Tipo Alerta * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="tipo_alerta" required>
                            <?= dinCatalogoOptions('tipo_alerta', '100') ?>
                        </select>
                        <div class="section-help">Catálogo UIF de alertas DIN</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Tipo Operación * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="tipo_operacion" required>
                            <?= dinCatalogoOptions('tipo_operacion', '1601') ?>
                        </select>
                        <div class="section-help">Catálogo UIF de operaciones</div>
                    </div>
                    <div class="col-12 mb-2">
                        <label class="form-label">Descripción Alerta <span class="badge bg-warning text-dark badge-xsd">Opc.</span></label>
                        <textarea class="form-control" id="descripcion_alerta" maxlength="3000" rows="2" placeholder="Descripción de la alerta..."></textarea>
                        <div class="section-help">Hasta 3,000 caracteres</div>
                    </div>
                </div>

                <hr class="my-3" style="border-color:var(--din-border);">

                <div class="row g-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">¿Es aviso modificatorio?</label>
                        <select class="form-select" id="es_modificatorio">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>
                    </div>
                </div>
                <div id="seccion_modificatorio" class="din-section">
                    <div class="nested-card p-3 rounded mb-3 mt-3">
                        <h6 class="fw-bold" style="color:var(--din-primary)"><i class="fa-solid fa-pen me-1"></i>Datos Modificatorio</h6>
                        <div class="row g-3">
                            <div class="col-md-5 mb-2">
                                <label class="form-label">Folio Modificación * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <input type="text" class="form-control" id="folio_modificacion" maxlength="14" placeholder="Ej: 2026-123456789">
                                <div class="section-help">Patrón AAAA-999999999, 6-14 car.</div>
                            </div>
                            <div class="col-md-7 mb-2">
                                <label class="form-label">Descripción Modificación * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <textarea class="form-control" id="descripcion_modificacion" maxlength="3000" rows="2" placeholder="Ej: Describa los cambios realizados al aviso original"></textarea>
                                <div class="section-help">Hasta 3,000 caracteres</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 3.5.1.2: Desarrollo Inmobiliario -->
        <div class="din-card" id="sec-desarrollo">
            <div class="din-card-header" onclick="toggleDinCard(this)">
                <div class="din-icon icon-desarrollo"><i class="fa-solid fa-city"></i></div>
                <div><h5>Desarrollo Inmobiliario</h5><small>Ubicación, características y montos del desarrollo</small></div>
                <i class="fa-solid fa-chevron-down din-chevron"></i>
            </div>
            <div class="din-card-body">
                <div class="row g-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Objeto Aviso Anterior * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="objeto_aviso_anterior" required>
                            <option value="NO" selected>NO</option>
                            <option value="SI">SI</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Modificación * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="modificacion_desarrollo" required>
                            <option value="NO" selected>NO</option>
                            <option value="SI">SI</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Entidad Federativa * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="entidad_federativa" required>
                            <?= dinCatalogoOptions('entidad_federativa', '9') ?>
                        </select>
                        <div class="section-help">Catálogo INEGI</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Registro/Licencia * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control" id="registro_licencia" maxlength="200" required placeholder="Ej: REG-001-2025">
                        <div class="section-help">1-200 caracteres alfanuméricos</div>
                    </div>
                </div>

                <hr class="my-3" style="border-color:var(--din-border);">
                <h6 class="fw-bold mb-3" style="color:var(--din-info);"><i class="fa-solid fa-list me-1"></i>Características del Desarrollo</h6>
                <div class="row g-3">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Código Postal * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control" id="codigo_postal" pattern="\d{5}" maxlength="5" required placeholder="Ej: 02000" inputmode="numeric" autocomplete="postal-code">
                        <div class="section-help">5 dígitos</div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Colonia * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control text-uppercase" id="colonia" maxlength="50" required placeholder="Ej: CENTRO">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Calle * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="text" class="form-control text-uppercase" id="calle" maxlength="100" required placeholder="Ej: AV. REFORMA 123">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Tipo Desarrollo * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="tipo_desarrollo" required>
                            <?= dinCatalogoOptions('tipo_desarrollo', '5') ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Descripción Desarrollo <span class="badge bg-warning text-dark badge-xsd">Obl. si tipo=99</span></label>
                        <input type="text" class="form-control" id="descripcion_desarrollo" maxlength="3000" placeholder="Ej: Desarrollo mixto residencial y comercial (obligatorio si tipo = 99)">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Monto Desarrollo (MXN) * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="number" class="form-control" id="monto_desarrollo" step="0.01" min="0" required placeholder="Ej: 5000000.00 (MXN)">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Unidades Comercializadas * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="number" class="form-control" id="unidades_comercializadas" step="0.01" min="0" value="1" required placeholder="Ej: 1">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Costo por Unidad * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="number" class="form-control" id="costo_unidad" step="0.01" min="0" required placeholder="Ej: 250000.00 (MXN)">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Otras Empresas * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="otras_empresas" required>
                            <option value="NO" selected>NO</option>
                            <option value="SI">SI</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 3.5.1.3: Aportaciones -->
        <div class="din-card" id="sec-aportaciones">
            <div class="din-card-header" onclick="toggleDinCard(this)">
                <div class="din-icon icon-aportacion"><i class="fa-solid fa-money-bill-wave"></i></div>
                <div><h5>Aportaciones</h5><small>Recursos propios, socios, terceros o financiamiento</small></div>
                <i class="fa-solid fa-chevron-down din-chevron"></i>
            </div>
            <div class="din-card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Fecha Aportación * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <input type="date" class="form-control" id="fecha_aportacion" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tipo de Aportación * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                        <select class="form-select" id="tipo_aportacion_selector" required>
                            <option value="recursos_propios" selected>Recursos Propios</option>
                            <option value="socios">Socios</option>
                            <option value="terceros">Terceros</option>
                            <option value="prestamo_financiero">Préstamo Financiero</option>
                            <option value="prestamo_no_financiero">Préstamo No Financiero</option>
                            <option value="financiamiento_bursatil">Financiamiento Bursátil</option>
                        </select>
                        <div class="section-help">Solo un tipo por registro</div>
                    </div>
                </div>

                <div id="sec_recursos_propios" class="din-section active">
                    <div class="nested-card p-3 rounded mb-3">
                        <h6 class="fw-bold" style="color:var(--din-success)"><i class="fa-solid fa-wallet me-1"></i>Recursos Propios</h6>
                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label class="form-label">Tipo de Dato *</label>
                                <select class="form-select" id="rp_tipo_dato">
                                    <option value="numerario" selected>Numerario (efectivo/transf.)</option>
                                    <option value="especie">En Especie (bienes)</option>
                                </select>
                            </div>
                        </div>
                        <div id="rp_sec_numerario">
                            <div class="row g-3">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Instrumento Monetario * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                    <select class="form-select" id="instrumento_monetario">
                                        <?= dinCatalogoOptions('instrumento_monetario', '1') ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Moneda * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                    <select class="form-select" id="moneda">
                                        <?= dinCatalogoOptions('moneda', '1') ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">Monto Aportación * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                    <input type="number" class="form-control" id="monto_aportacion" step="0.01" min="0" required placeholder="Ej: 500000.00 (MXN)">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label">¿Fideicomiso? * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                    <select class="form-select" id="aportacion_fideicomiso">
                                        <option value="NO" selected>NO</option>
                                        <option value="SI">SI</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2" id="rp_nombre_inst_div">
                                    <label class="form-label">Nombre Institución <span class="badge bg-warning text-dark badge-xsd">Cond.</span></label>
                                    <input type="text" class="form-control" id="nombre_institucion" maxlength="254" placeholder="Ej: BANCO XYZ S.A. (si fideicomiso = SI)">
                                </div>
                            </div>
                        </div>
                        <div id="rp_sec_especie" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-7 mb-2">
                                    <label class="form-label">Descripción del Bien * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                    <textarea class="form-control" id="rp_descripcion_bien" maxlength="3000" rows="2" placeholder="Ej: Terreno urbano con edificio comercial"></textarea>
                                </div>
                                <div class="col-md-5 mb-2">
                                    <label class="form-label">Monto Estimado (MXN) * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                    <input type="number" class="form-control" id="rp_monto_estimado" step="0.01" min="0" placeholder="Ej: 1500000.00 (MXN)">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="sec_socios" class="din-section">
                    <div class="nested-card p-3 rounded mb-3">
                        <h6 class="fw-bold" style="color:var(--din-success)"><i class="fa-solid fa-users me-1"></i>Socios</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Número de Socios * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <input type="number" class="form-control" id="numero_socios" min="1" max="99999999" value="1">
                            </div>
                            <div class="col-md-6 d-flex align-items-end mb-2">
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="addSocio()">
                                    <i class="fa-solid fa-plus me-1"></i>Agregar Socio
                                </button>
                            </div>
                        </div>
                        <div id="socios_container">
                            <!-- Socios dinámicos se agregan aquí -->
                        </div>
                    </div>
                </div>

                <div id="sec_terceros" class="din-section">
                    <div class="nested-card p-3 rounded mb-3">
                        <h6 class="fw-bold" style="color:var(--din-success)"><i class="fa-solid fa-people-arrows me-1"></i>Terceros</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Número de Terceros * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <input type="number" class="form-control" id="numero_terceros" min="1" max="99999999" value="1">
                            </div>
                            <div class="col-md-6 d-flex align-items-end mb-2">
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="addTercero()">
                                    <i class="fa-solid fa-plus me-1"></i>Agregar Tercero
                                </button>
                            </div>
                        </div>
                        <div id="terceros_container">
                            <!-- Terceros dinámicos se agregan aquí -->
                        </div>
                    </div>
                </div>

                <div id="sec_prestamo_financiero" class="din-section">
                    <div class="nested-card p-3 rounded mb-3">
                        <h6 class="fw-bold" style="color:var(--din-success)"><i class="fa-solid fa-building-columns me-1"></i>Préstamo Financiero</h6>
                        <div class="row g-3">
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Tipo Institución * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <select class="form-select" id="pf_tipo_institucion" required>
                                    <?= dinCatalogoOptions('tipo_institucion') ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Institución * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <input type="text" class="form-control" id="pf_institucion" maxlength="254" required placeholder="Ej: BANCO XYZ S.A. DE C.V.">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Tipo Crédito * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <select class="form-select" id="pf_tipo_credito" required>
                                    <?= dinCatalogoOptions('tipo_credito') ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Moneda * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <select class="form-select" id="pf_moneda" required>
                                    <?= dinCatalogoOptions('moneda', '1') ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Monto Préstamo * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <input type="number" class="form-control" id="pf_monto_prestamo" step="0.01" min="0" placeholder="Ej: 500000.00 (MXN)" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label">Plazo (meses) * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <input type="number" class="form-control" id="pf_plazo_meses" min="1" max="99999999" required placeholder="Ej: 12">
                            </div>
                        </div>
                    </div>
                </div>

                <div id="sec_prestamo_no_financiero" class="din-section">
                    <div class="nested-card p-3 rounded mb-3">
                        <h6 class="fw-bold" style="color:var(--din-success)"><i class="fa-solid fa-handshake me-1"></i>Préstamo No Financiero</h6>
                        <div class="row g-3">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Monto Préstamo * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <input type="number" class="form-control" id="pnf_monto_prestamo" step="0.01" min="0" placeholder="Ej: 300000.00 (MXN)" required>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Moneda * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <select class="form-select" id="pnf_moneda" required>
                                    <?= dinCatalogoOptions('moneda', '1') ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Plazo (meses) * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <input type="number" class="form-control" id="pnf_plazo_meses" min="1" max="99999999" required placeholder="Ej: 24">
                            </div>
                            <div class="col-12 mb-2">
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="addAcreedor()">
                                    <i class="fa-solid fa-plus me-1"></i>Agregar Acreedor
                                </button>
                            </div>
                        </div>
                        <div id="acreedores_container"></div>
                    </div>
                </div>

                <div id="sec_financiamiento_bursatil" class="din-section">
                    <div class="nested-card p-3 rounded mb-3">
                        <h6 class="fw-bold" style="color:var(--din-success)"><i class="fa-solid fa-chart-line me-1"></i>Financiamiento Bursátil</h6>
                        <div class="row g-3">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Fecha Emisión * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <input type="date" class="form-control" id="fb_fecha_emision" required>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Monto Solicitado * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <input type="number" class="form-control" id="fb_monto_solicitado" step="0.01" min="0" placeholder="Ej: 1000000.00 (MXN)" required>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Monto Recibido * <span class="badge bg-danger badge-xsd">Obl.</span></label>
                                <input type="number" class="form-control" id="fb_monto_recibido" step="0.01" min="0" placeholder="Ej: 950000.00 (MXN)" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Opcionales PLD -->
        <div class="din-card" id="sec-pld">
            <div class="din-card-header" onclick="toggleDinCard(this)">
                <div class="din-icon icon-pld"><i class="fa-solid fa-shield-halved"></i></div>
                <div><h5>Controles PLD</h5><small>Sospecha, listas restringidas y fracción</small></div>
                <i class="fa-solid fa-chevron-down din-chevron"></i>
            </div>
            <div class="din-card-body">
                <div class="row align-items-center">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label class="form-label">Transacción Sospechosa</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="es_sospechosa">
                            <label class="form-check-label" for="es_sospechosa">Aviso 24H</label>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3" id="fecha_sospecha_div" style="display:none;">
                        <label class="form-label">Fecha conocimiento sospecha</label>
                        <input type="datetime-local" class="form-control" id="fecha_conocimiento_sospecha" placeholder="Ej: 2026-02-05 14:30">
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label class="form-label">Match listas restringidas</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="match_listas">
                            <label class="form-check-label" for="match_listas">Aviso 24H</label>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label class="form-label">Fracción (umbrales PLD)</label>
                        <select class="form-select" id="id_fraccion">
                            <option value="">-- Seleccione --</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="din-submit-bar">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-paper-plane me-2"></i>Registrar y Generar XML
            </button>
            <a href="operaciones_pld.php" class="btn btn-outline-secondary">Cancelar</a>
            <span class="text-muted ms-auto d-none d-md-inline" style="font-size:.82rem;">
                <i class="fa-solid fa-info-circle me-1"></i>Se generará el XML automáticamente al guardar
            </span>
        </div>
    </form>
</div><!-- /din-wrapper -->
</div>

<!-- Templates para entidades dinámicas (Socio / Tercero / Acreedor) -->
<template id="tpl_persona_block">
    <div class="mt-3">
        <div class="row g-3 mb-2">
            <div class="col-md-6">
                <label class="form-label">Tipo Persona *</label>
                <select class="form-select persona-type-select">
                    <option value="persona_fisica">Persona Física</option>
                    <option value="persona_moral">Persona Moral</option>
                    <option value="fideicomiso">Fideicomiso</option>
                </select>
            </div>
        </div>
        <div class="persona-section pf-section active">
            <div class="row g-3">
                <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control pf-nombre" maxlength="200" required placeholder="Ej: JUAN CARLOS"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Apellido Paterno *</label><input type="text" class="form-control pf-apellido-paterno" maxlength="200" required placeholder="Ej: LÓPEZ"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Apellido Materno *</label><input type="text" class="form-control pf-apellido-materno" maxlength="200" required placeholder="Ej: GARCÍA"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Fecha Nacimiento *</label><input type="date" class="form-control pf-fecha-nacimiento" required title="AAAA-MM-DD"></div>
                <div class="col-md-6 mb-2"><label class="form-label">RFC (13 car.) *</label><input type="text" class="form-control pf-rfc" maxlength="13" required placeholder="Ej: LOPG900101ABC"></div>
                <div class="col-md-6 mb-2"><label class="form-label">CURP (18 car.) *</label><input type="text" class="form-control pf-curp" maxlength="18" required placeholder="Ej: LOPG900101HDFLRN01"></div>
                <div class="col-md-6 mb-2"><label class="form-label">País Nacionalidad *</label><select class="form-select pf-pais-nacionalidad" required><?= dinCatalogoOptions('pais', 'MX') ?></select></div>
                <div class="col-md-6 mb-2"><label class="form-label">Act. Económica (SCIAN) *</label><input type="text" class="form-control pf-actividad-economica" maxlength="7" pattern="\d{7}" placeholder="7 dígitos" required></div>
            </div>
        </div>
        <div class="persona-section pm-section">
            <div class="row g-3">
                <div class="col-md-6 mb-2"><label class="form-label">Denominación/Razón Social *</label><input type="text" class="form-control pm-denominacion" maxlength="254" required placeholder="Ej: EMPRESA EJEMPLO S.A. DE C.V."></div>
                <div class="col-md-6 mb-2"><label class="form-label">RFC (12 car.)</label><input type="text" class="form-control pm-rfc" maxlength="12" placeholder="Ej: EEE900101AAA (opcional)"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Fecha Constitución *</label><input type="date" class="form-control pm-fecha-constitucion" required title="AAAA-MM-DD"></div>
                <div class="col-md-6 mb-2"><label class="form-label">País Nacionalidad *</label><select class="form-select pm-pais-nacionalidad" required><?= dinCatalogoOptions('pais', 'MX') ?></select></div>
                <div class="col-md-6 mb-2"><label class="form-label">Giro Mercantil (7 díg.) *</label><input type="text" class="form-control pm-giro-mercantil" maxlength="7" pattern="\d{7}" required placeholder="Ej: 5311111"></div>
            </div>
            <div class="mt-3 p-2 rounded" style="background:var(--din-light);">
                <label class="form-label fw-bold" style="color:var(--din-primary)"><i class="fa-solid fa-user-tie me-1"></i>Representante/Apoderado Legal</label>
                <div class="row g-3">
                    <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control pm-rep-nombre" maxlength="200" required placeholder="Ej: MARÍA FERNANDA"></div>
                    <div class="col-md-6 mb-2"><label class="form-label">Ap. Paterno *</label><input type="text" class="form-control pm-rep-apellido-paterno" maxlength="200" required placeholder="Ej: MARTÍNEZ"></div>
                    <div class="col-md-6 mb-2"><label class="form-label">Ap. Materno *</label><input type="text" class="form-control pm-rep-apellido-materno" maxlength="200" required placeholder="Ej: SÁNCHEZ"></div>
                    <div class="col-md-6 mb-2"><label class="form-label">Fecha Nac. *</label><input type="date" class="form-control pm-rep-fecha-nacimiento" required title="AAAA-MM-DD"></div>
                    <div class="col-md-6 mb-2"><label class="form-label">RFC (13 car.) *</label><input type="text" class="form-control pm-rep-rfc" maxlength="13" required placeholder="Ej: MAMS850310ABC"></div>
                    <div class="col-md-6 mb-2"><label class="form-label">CURP (18 car.) *</label><input type="text" class="form-control pm-rep-curp" maxlength="18" required placeholder="Ej: MAMS850310MDFRNR01"></div>
                </div>
            </div>
        </div>
        <div class="persona-section fid-section">
            <div class="row g-3">
                <div class="col-12 mb-2"><label class="form-label">Denominación/Razón Social Fiduciario *</label><input type="text" class="form-control fid-denominacion" maxlength="254" required placeholder="Ej: FIDEICOMISO EJEMPLO S.A. DE C.V."></div>
                <div class="col-md-6 mb-2"><label class="form-label">RFC Fideicomiso (12 car.) *</label><input type="text" class="form-control fid-rfc" maxlength="12" required placeholder="Ej: FDE900101AAA"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Identificador Fideicomiso *</label><input type="text" class="form-control fid-identificador" maxlength="40" required placeholder="Ej: FID-001-2026"></div>
            </div>
        </div>
    </div>
</template>

<template id="tpl_domicilio_block">
    <div class="mt-2">
        <div class="row g-3 mb-2">
            <div class="col-md-6">
                <label class="form-label">Tipo Domicilio *</label>
                <select class="form-select domicilio-type-select">
                    <option value="nacional">Nacional</option>
                    <option value="extranjero">Extranjero</option>
                </select>
            </div>
        </div>
        <div class="domicilio-section dom-nacional active">
            <div class="row g-3">
                <div class="col-md-6 mb-2"><label class="form-label">Colonia *</label><input type="text" class="form-control text-uppercase dn-colonia" maxlength="50" required placeholder="Ej: CENTRO"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Calle *</label><input type="text" class="form-control text-uppercase dn-calle" maxlength="100" required placeholder="Ej: AV. REFORMA 123"></div>
                <div class="col-md-4 mb-2"><label class="form-label">Núm. Exterior *</label><input type="text" class="form-control dn-numero-exterior" maxlength="56" required placeholder="Ej: 123"></div>
                <div class="col-md-4 mb-2"><label class="form-label">Núm. Interior</label><input type="text" class="form-control dn-numero-interior" maxlength="40" placeholder="Ej: 4 (opcional)"></div>
                <div class="col-md-4 mb-2"><label class="form-label">C.P. *</label><input type="text" class="form-control dn-codigo-postal" maxlength="5" pattern="\d{5}" required placeholder="Ej: 06000"></div>
            </div>
        </div>
        <div class="domicilio-section dom-extranjero">
            <div class="row g-3">
                <div class="col-md-6 mb-2"><label class="form-label">País *</label><select class="form-select de-pais" required><?= dinCatalogoOptions('pais', 'US') ?></select></div>
                <div class="col-md-6 mb-2"><label class="form-label">Estado/Provincia *</label><input type="text" class="form-control de-estado-provincia" maxlength="100" required placeholder="Ej: CALIFORNIA"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Ciudad/Población *</label><input type="text" class="form-control de-ciudad-poblacion" maxlength="100" required placeholder="Ej: LOS ANGELES"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Colonia *</label><input type="text" class="form-control text-uppercase de-colonia" maxlength="50" required placeholder="Ej: DOWNTOWN"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Calle *</label><input type="text" class="form-control text-uppercase de-calle" maxlength="100" required placeholder="Ej: MAIN STREET 456"></div>
                <div class="col-md-6 mb-2"><label class="form-label">C.P. *</label><input type="text" class="form-control de-codigo-postal" maxlength="12" required placeholder="Ej: 90001"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Núm. Exterior *</label><input type="text" class="form-control de-numero-exterior" maxlength="56" required placeholder="Ej: 456"></div>
                <div class="col-md-6 mb-2"><label class="form-label">Núm. Interior</label><input type="text" class="form-control de-numero-interior" maxlength="40" placeholder="Ej: 2 (opcional)"></div>
            </div>
        </div>
    </div>
</template>

<template id="tpl_telefono_block">
    <div class="mt-2">
        <div class="row g-3">
            <div class="col-md-4 mb-2"><label class="form-label">País Tel. *</label><select class="form-select tel-clave-pais" required><?= dinCatalogoOptions('pais', 'MX') ?></select></div>
            <div class="col-md-4 mb-2"><label class="form-label">Teléfono *</label><input type="text" class="form-control tel-numero" maxlength="12" pattern="\d{10,12}" required placeholder="Ej: 5512345678"></div>
            <div class="col-md-4 mb-2"><label class="form-label">Correo Electrónico</label><input type="email" class="form-control tel-correo" maxlength="60" placeholder="Ej: correo@ejemplo.com"></div>
        </div>
    </div>
</template>

<template id="tpl_aportacion_entidad_block">
    <div class="mt-2">
        <div class="row g-3 mb-2">
            <div class="col-md-6">
                <label class="form-label">Tipo Dato Aportación *</label>
                <select class="form-select entidad-aport-tipo-select">
                    <option value="numerario">Numerario</option>
                    <option value="especie">En Especie</option>
                </select>
            </div>
        </div>
        <div class="entidad-aport-numerario">
            <div class="row g-3">
                <div class="col-md-6 mb-2"><label class="form-label">Instr. Monetario *</label><select class="form-select ea-instrumento" required><?= dinCatalogoOptions('instrumento_monetario', '1') ?></select></div>
                <div class="col-md-6 mb-2"><label class="form-label">Moneda *</label><select class="form-select ea-moneda" required><?= dinCatalogoOptions('moneda', '1') ?></select></div>
                <div class="col-md-4 mb-2"><label class="form-label">Monto *</label><input type="number" class="form-control ea-monto" step="0.01" min="0" placeholder="Ej: 250000.00 (MXN)" required></div>
                <div class="col-md-4 mb-2"><label class="form-label">¿Fideicomiso? *</label><select class="form-select ea-fideicomiso" required><option value="NO">NO</option><option value="SI">SI</option></select></div>
                <div class="col-md-4 mb-2"><label class="form-label">Nombre Institución</label><input type="text" class="form-control ea-nombre-inst" maxlength="254" placeholder="Ej: BANCO ABC S.A."></div>
            </div>
        </div>
        <div class="entidad-aport-especie" style="display:none;">
            <div class="row g-3">
                <div class="col-md-7 mb-2"><label class="form-label">Descripción del Bien *</label><textarea class="form-control ea-desc-bien" maxlength="3000" rows="1" required placeholder="Ej: Terreno urbano con edificio comercial"></textarea></div>
                <div class="col-md-5 mb-2"><label class="form-label">Monto Estimado *</label><input type="number" class="form-control ea-monto-estimado" step="0.01" min="0" required placeholder="Ej: 1500000.00 (MXN)"></div>
            </div>
        </div>
    </div>
</template>

<script>
const DIN_CATALOGOS = <?= dinCatalogosJson() ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function toggleDinCard(header) {
    const body = header.nextElementSibling;
    header.classList.toggle('collapsed');
    body.style.display = header.classList.contains('collapsed') ? 'none' : '';
}

function updateProgressSteps() {
    const steps = document.querySelectorAll('.din-step');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                steps.forEach(s => s.classList.remove('active'));
                const step = document.querySelector(`.din-step[data-target="${entry.target.id}"]`);
                if (step) {
                    step.classList.add('active');
                    let prev = step.previousElementSibling;
                    while (prev) { prev.classList.add('done'); prev = prev.previousElementSibling; }
                    let next = step.nextElementSibling;
                    while (next) { next.classList.remove('done'); next = next.nextElementSibling; }
                }
            }
        });
    }, { threshold: 0.3 });
    document.querySelectorAll('.din-card[id]').forEach(card => observer.observe(card));

    steps.forEach(step => {
        step.addEventListener('click', () => {
            const target = document.getElementById(step.dataset.target);
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    cargarClientes();
    cargarFracciones();
    updateProgressSteps();
    document.getElementById('id_cliente').addEventListener('change', cargarKYC);
    document.getElementById('es_sospechosa').addEventListener('change', function() {
        document.getElementById('fecha_sospecha_div').style.display = this.checked ? 'block' : 'none';
        if (this.checked && !document.getElementById('fecha_conocimiento_sospecha').value) {
            const n = new Date();
            n.setMinutes(n.getMinutes() - n.getTimezoneOffset());
            document.getElementById('fecha_conocimiento_sospecha').value = n.toISOString().slice(0, 16);
        }
    });

    document.getElementById('es_modificatorio').addEventListener('change', function() {
        const show = this.value === '1';
        toggleSection('seccion_modificatorio', show);
        document.getElementById('folio_modificacion').required = show;
        document.getElementById('descripcion_modificacion').required = show;
    });
    document.getElementById('folio_modificacion').required = (document.getElementById('es_modificatorio').value === '1');
    document.getElementById('descripcion_modificacion').required = (document.getElementById('es_modificatorio').value === '1');

    document.getElementById('tipo_aportacion_selector').addEventListener('change', function() {
        const sections = ['sec_recursos_propios','sec_socios','sec_terceros','sec_prestamo_financiero','sec_prestamo_no_financiero','sec_financiamiento_bursatil'];
        sections.forEach(s => document.getElementById(s).classList.remove('active'));
        document.getElementById('sec_' + this.value).classList.add('active');
    });

    const toggleRecursosPropios = function() {
        const isNumerario = document.getElementById('rp_tipo_dato').value === 'numerario';
        document.getElementById('rp_sec_numerario').style.display = isNumerario ? '' : 'none';
        document.getElementById('rp_sec_especie').style.display = isNumerario ? 'none' : '';
        document.getElementById('monto_aportacion').required = isNumerario;
        document.getElementById('instrumento_monetario').required = isNumerario;
        document.getElementById('moneda').required = isNumerario;
        document.getElementById('aportacion_fideicomiso').required = isNumerario;
        document.getElementById('rp_descripcion_bien').required = !isNumerario;
        document.getElementById('rp_monto_estimado').required = !isNumerario;
    };
    document.getElementById('rp_tipo_dato').addEventListener('change', toggleRecursosPropios);
    toggleRecursosPropios();

    document.getElementById('aportacion_fideicomiso').addEventListener('change', function() {
        document.getElementById('rp_nombre_inst_div').style.display = this.value === 'SI' ? '' : 'none';
    });

    document.getElementById('tipo_desarrollo').addEventListener('change', function() {
        document.getElementById('descripcion_desarrollo').required = (this.value === '99');
    });
    document.getElementById('descripcion_desarrollo').required = (document.getElementById('tipo_desarrollo').value === '99');

    document.getElementById('formDIN').addEventListener('submit', guardarOperacionDIN);

    setupCpLookupMainForm();
    addSocio();
    addTercero();
    addAcreedor();
});

function toggleSection(id, show) {
    const el = document.getElementById(id);
    if (show) el.classList.add('active');
    else el.classList.remove('active');
}

/* Código Postal: solo dígitos. Colonia y Calle: solo mayúsculas */
function enforceDigitsOnly(el, maxLen) {
    if (!el) return;
    const limit = (maxLen != null && maxLen > 0) ? maxLen : 5;
    const apply = function() {
        const v = this.value.replace(/\D/g, '').slice(0, limit);
        if (this.value !== v) this.value = v;
    };
    el.addEventListener('input', apply);
    apply.call(el);
}

function enforceUppercase(el) {
    if (!el) return;
    const apply = function() {
        const v = this.value.toUpperCase();
        if (this.value !== v) this.value = v;
    };
    el.addEventListener('input', apply);
    apply.call(el);
}

function setupCpLookupMainForm() {
    const cp = document.getElementById('codigo_postal');
    const colonia = document.getElementById('colonia');
    const calle = document.getElementById('calle');
    if (!cp || !colonia || !calle) return;
    enforceDigitsOnly(cp);
    enforceUppercase(colonia);
    enforceUppercase(calle);
}

function v(id) {
    const el = document.getElementById(id);
    return el ? String(el.value || '').trim() : '';
}

function cargarFracciones() {
    fetch('api/get_catalogos.php')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('id_fraccion');
            sel.innerHTML = '<option value="">-- Seleccione --</option>';
            (data.data?.vulnerables || []).forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.id_vulnerable;
                opt.textContent = f.nombre + ' (' + f.fraccion + ')';
                sel.appendChild(opt);
            });
        })
        .catch(e => console.error('Error fracciones:', e));
}

function cargarClientes() {
    fetch('api/get_clients.php')
        .then(r => r.json())
        .then(data => {
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

let kycDataCache = null;

function cargarKYC() {
    const id = document.getElementById('id_cliente').value;
    const preview = document.getElementById('kyc-preview');
    if (!id) { preview.style.display = 'none'; kycDataCache = null; return; }
    const requestedId = id;
    fetch('api/get_cliente_kyc_pld.php?id=' + id)
        .then(r => r.json())
        .then(res => {
            if (document.getElementById('id_cliente').value !== requestedId) return;
            if (res.status !== 'success') { preview.style.display = 'none'; kycDataCache = null; return; }
            const k = res.kyc;
            kycDataCache = k;
            document.getElementById('kyc-rfc').textContent = k.rfc || '-';
            document.getElementById('kyc-curp').textContent = k.curp || '-';
            document.getElementById('kyc-tipo').textContent = k.tipo_persona || '-';
            document.getElementById('kyc-nombre').textContent = k.denominacion_razon || k.razon_social || '-';
            document.getElementById('kyc-fecha').textContent = k.fecha_nacimiento || k.fecha_constitucion || '-';
            document.getElementById('kyc-pais').textContent = k.pais_nacionalidad || '-';
            preview.style.display = 'block';
        })
        .catch(e => {
            if (document.getElementById('id_cliente').value !== requestedId) return;
            console.error('Error KYC:', e); preview.style.display = 'none'; kycDataCache = null;
        });
}

function prefillPersonaFromKyc(container) {
    const k = kycDataCache;
    if (!k || !container) return;
    const tipoSel = container.querySelector('.persona-type-select');
    if (!tipoSel) return;
    if ((k.es_fisica || 0) === 1) {
        tipoSel.value = 'persona_fisica';
        container.querySelectorAll('.persona-section').forEach(s => s.classList.remove('active'));
        const pf = container.querySelector('.pf-section');
        if (pf) {
            pf.classList.add('active');
            const set = (sel, val) => { const e = container.querySelector(sel); if (e) e.value = (val != null && val !== '') ? val : ''; };
            set('.pf-nombre', k.nombre);
            set('.pf-apellido-paterno', k.apellido_paterno);
            set('.pf-apellido-materno', k.apellido_materno);
            set('.pf-fecha-nacimiento', (k.fecha_nacimiento || '').toString().substring(0, 10));
            set('.pf-rfc', k.rfc);
            set('.pf-curp', k.curp);
            set('.pf-pais-nacionalidad', k.pais_nacionalidad);
        }
    } else if ((k.es_moral || 0) === 1) {
        tipoSel.value = 'persona_moral';
        container.querySelectorAll('.persona-section').forEach(s => s.classList.remove('active'));
        const pm = container.querySelector('.pm-section');
        if (pm) {
            pm.classList.add('active');
            const set = (sel, val) => { const e = container.querySelector(sel); if (e) e.value = (val != null && val !== '') ? val : ''; };
            set('.pm-denominacion', k.denominacion_razon || k.razon_social);
            set('.pm-rfc', k.rfc);
            set('.pm-fecha-constitucion', (k.fecha_constitucion || '').toString().substring(0, 10));
            set('.pm-pais-nacionalidad', k.pais_nacionalidad);
        }
    } else if ((k.es_fideicomiso || 0) === 1) {
        tipoSel.value = 'fideicomiso';
        container.querySelectorAll('.persona-section').forEach(s => s.classList.remove('active'));
        const fid = container.querySelector('.fid-section');
        if (fid) {
            fid.classList.add('active');
            const set = (sel, val) => { const e = container.querySelector(sel); if (e) e.value = (val != null && val !== '') ? val : ''; };
            set('.fid-denominacion', k.denominacion_razon);
            set('.fid-rfc', k.rfc);
        }
    } else {
        // Fallback: id_tipo_persona inválido o datos corruptos — usar persona_fisica como predeterminado
        tipoSel.value = 'persona_fisica';
        container.querySelectorAll('.persona-section').forEach(s => s.classList.remove('active'));
        const pf = container.querySelector('.pf-section');
        if (pf) {
            pf.classList.add('active');
            const set = (sel, val) => { const e = container.querySelector(sel); if (e) e.value = (val != null && val !== '') ? val : ''; };
            set('.pf-nombre', k.nombre);
            set('.pf-apellido-paterno', k.apellido_paterno);
            set('.pf-apellido-materno', k.apellido_materno);
            set('.pf-fecha-nacimiento', (k.fecha_nacimiento || '').toString().substring(0, 10));
            set('.pf-rfc', k.rfc);
            set('.pf-curp', k.curp);
            set('.pf-pais-nacionalidad', k.pais_nacionalidad);
        }
    }
    // RFC Socio: existe en todos los Socio items; aplicar para cualquier tipo de persona
    const socioRfc = container.querySelector('.socio-rfc');
    if (socioRfc) socioRfc.value = (k.rfc != null && k.rfc !== '') ? k.rfc : '';
}

/* ═══════════════════════════════════════════ */
/* Dynamic entity helpers (Socio/Tercero/Acreedor) */
/* ═══════════════════════════════════════════ */
let socioCount = 0, terceroCount = 0, acreedorCount = 0;

function buildCatOptions(catName, selectedVal) {
    const cat = DIN_CATALOGOS[catName] || {};
    let html = '<option value="">-- Seleccione --</option>';
    for (const [k, desc] of Object.entries(cat)) {
        const sel = (selectedVal && String(k) === String(selectedVal)) ? ' selected' : '';
        html += '<option value="' + k + '"' + sel + '>' + k + ' - ' + desc + '</option>';
    }
    return html;
}

function cloneTemplate(tplId) {
    return document.getElementById(tplId).content.cloneNode(true);
}

function setupPersonaToggle(container) {
    const sel = container.querySelector('.persona-type-select');
    if (!sel) return;
    sel.addEventListener('change', function() {
        container.querySelectorAll('.persona-section').forEach(s => s.classList.remove('active'));
        const map = { persona_fisica: '.pf-section', persona_moral: '.pm-section', fideicomiso: '.fid-section' };
        const target = container.querySelector(map[this.value]);
        if (target) target.classList.add('active');
    });
}

function setupDomicilioToggle(container) {
    const sel = container.querySelector('.domicilio-type-select');
    if (!sel) return;
    sel.addEventListener('change', function() {
        container.querySelectorAll('.domicilio-section').forEach(s => s.classList.remove('active'));
        const map = { nacional: '.dom-nacional', extranjero: '.dom-extranjero' };
        const target = container.querySelector(map[this.value]);
        if (target) target.classList.add('active');
    });
}

function setupDomicilioUppercaseAndDigits(container) {
    const dnCp = container.querySelector('.dn-codigo-postal');
    const deCp = container.querySelector('.de-codigo-postal');
    const dnColonia = container.querySelector('.dn-colonia');
    const dnCalle = container.querySelector('.dn-calle');
    const deColonia = container.querySelector('.de-colonia');
    const deCalle = container.querySelector('.de-calle');
    if (dnCp) enforceDigitsOnly(dnCp, 5);
    if (deCp) enforceDigitsOnly(deCp, 12);
    [dnColonia, dnCalle, deColonia, deCalle].forEach(el => { if (el) enforceUppercase(el); });
}

function setupAportTipoToggle(container) {
    const sel = container.querySelector('.entidad-aport-tipo-select');
    if (!sel) return;
    sel.addEventListener('change', function() {
        const num = container.querySelector('.entidad-aport-numerario');
        const esp = container.querySelector('.entidad-aport-especie');
        num.style.display = this.value === 'numerario' ? '' : 'none';
        esp.style.display = this.value === 'especie' ? '' : 'none';
    });
}

function addSocio() {
    socioCount++;
    const idx = socioCount;
    const div = document.createElement('div');
    div.className = 'socio-item';
    div.dataset.idx = idx;
    div.innerHTML = '<div class="d-flex justify-content-between align-items-center mb-2">'
        + '<strong class="text-primary">Socio #' + idx + '</strong>'
        + '<button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest(\'.socio-item\').remove()"><i class="fa-solid fa-trash"></i></button>'
        + '</div>'
        + '<div class="row mb-2"><div class="col-md-3"><label class="form-label">Aportación anterior (SI/NO) *</label>'
        + '<select class="form-select socio-aport-anterior" required><option value="NO">NO</option><option value="SI">SI</option></select></div>'
        + '<div class="col-md-4"><label class="form-label">RFC Socio (13 car.) *</label><input type="text" class="form-control socio-rfc" maxlength="13" required></div></div>';

    const personaBlock = cloneTemplate('tpl_persona_block');
    const domBlock = cloneTemplate('tpl_domicilio_block');
    const telBlock = cloneTemplate('tpl_telefono_block');
    const aportBlock = cloneTemplate('tpl_aportacion_entidad_block');

    const wrapper = document.createElement('div');
    wrapper.appendChild(personaBlock);

    const domLabel = document.createElement('h6');
    domLabel.className = 'mt-3 text-secondary';
    domLabel.innerHTML = '<i class="fa-solid fa-map-marker-alt me-1"></i>Domicilio del Socio';
    wrapper.appendChild(domLabel);
    wrapper.appendChild(domBlock);

    const telLabel = document.createElement('h6');
    telLabel.className = 'mt-3 text-secondary';
    telLabel.innerHTML = '<i class="fa-solid fa-phone me-1"></i>Teléfono del Socio';
    wrapper.appendChild(telLabel);
    wrapper.appendChild(telBlock);

    const aportLabel = document.createElement('h6');
    aportLabel.className = 'mt-3 text-secondary';
    aportLabel.innerHTML = '<i class="fa-solid fa-coins me-1"></i>Aportación del Socio';
    wrapper.appendChild(aportLabel);
    wrapper.appendChild(aportBlock);

    div.appendChild(wrapper);
    document.getElementById('socios_container').appendChild(div);

    setupPersonaToggle(div);
    setupDomicilioToggle(div);
    setupDomicilioUppercaseAndDigits(div);
    setupAportTipoToggle(div);
    addKycPrefillButton(div, idx === 1);
}

function addTercero() {
    terceroCount++;
    const idx = terceroCount;
    const div = document.createElement('div');
    div.className = 'tercero-item';
    div.dataset.idx = idx;
    div.innerHTML = '<div class="d-flex justify-content-between align-items-center mb-2">'
        + '<strong class="text-primary">Tercero #' + idx + '</strong>'
        + '<button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest(\'.tercero-item\').remove()"><i class="fa-solid fa-trash"></i></button>'
        + '</div>'
        + '<div class="row mb-2">'
        + '<div class="col-md-3"><label class="form-label">Tipo Tercero *</label><select class="form-select tercero-tipo" required>' + buildCatOptions('tipo_tercero') + '</select></div>'
        + '<div class="col-md-5"><label class="form-label">Descripción (si tipo=99)</label><textarea class="form-control tercero-descripcion" maxlength="3000" rows="1"></textarea></div>'
        + '</div>';

    const personaBlock = cloneTemplate('tpl_persona_block');
    const aportBlock = cloneTemplate('tpl_aportacion_entidad_block');

    const wrapper = document.createElement('div');
    wrapper.appendChild(personaBlock);

    const aportLabel = document.createElement('h6');
    aportLabel.className = 'mt-3 text-secondary';
    aportLabel.innerHTML = '<i class="fa-solid fa-coins me-1"></i>Aportación del Tercero';
    wrapper.appendChild(aportLabel);

    const valInmLabel = document.createElement('div');
    valInmLabel.className = 'row mb-2';
    valInmLabel.innerHTML = '<div class="col-md-4"><label class="form-label">Valor Inmueble Preventa (MXN)</label>'
        + '<input type="number" class="form-control tercero-valor-inmueble" step="0.01" min="0" placeholder="0.00">'
        + '<div class="section-help">3.5.1.3.2.3.2.1.4.1.1.6 — Solo aplica en numerario</div></div>';
    wrapper.appendChild(aportBlock);
    wrapper.appendChild(valInmLabel);

    div.appendChild(wrapper);
    document.getElementById('terceros_container').appendChild(div);

    setupPersonaToggle(div);
    setupAportTipoToggle(div);
    addKycPrefillButton(div, idx === 1);
    const tipoSel = div.querySelector('.tercero-tipo');
    const descText = div.querySelector('.tercero-descripcion');
    if (tipoSel && descText) {
        tipoSel.addEventListener('change', function() { descText.required = (this.value === '99'); });
        descText.required = (tipoSel.value === '99');
    }
}

function addAcreedor() {
    acreedorCount++;
    const idx = acreedorCount;
    const div = document.createElement('div');
    div.className = 'acreedor-item';
    div.dataset.idx = idx;
    div.innerHTML = '<div class="d-flex justify-content-between align-items-center mb-2">'
        + '<strong class="text-primary">Acreedor #' + idx + '</strong>'
        + '<button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest(\'.acreedor-item\').remove()"><i class="fa-solid fa-trash"></i></button>'
        + '</div>';

    const personaBlock = cloneTemplate('tpl_persona_block');
    div.appendChild(personaBlock);
    document.getElementById('acreedores_container').appendChild(div);

    setupPersonaToggle(div);
    addKycPrefillButton(div, idx === 1);
}

function prefillFirstSocio() {
    if (!kycDataCache) { Swal.fire('Info', 'Seleccione un cliente y espere a que carguen los datos del expediente.', 'info'); return; }
    const first = document.querySelector('#socios_container .socio-item');
    if (first) prefillPersonaFromKyc(first);
    else Swal.fire('Info', 'Agregue un Socio primero y luego use este botón.', 'info');
}
function prefillFirstTercero() {
    if (!kycDataCache) { Swal.fire('Info', 'Seleccione un cliente y espere a que carguen los datos del expediente.', 'info'); return; }
    const first = document.querySelector('#terceros_container .tercero-item');
    if (first) prefillPersonaFromKyc(first);
    else Swal.fire('Info', 'Agregue un Tercero primero y luego use este botón.', 'info');
}
function prefillFirstAcreedor() {
    if (!kycDataCache) { Swal.fire('Info', 'Seleccione un cliente y espere a que carguen los datos del expediente.', 'info'); return; }
    const first = document.querySelector('#acreedores_container .acreedor-item');
    if (first) prefillPersonaFromKyc(first);
    else Swal.fire('Info', 'Agregue un Acreedor primero y luego use este botón.', 'info');
}

function addKycPrefillButton(container, autoPrefill) {
    const header = container.querySelector('.d-flex.justify-content-between') || container.querySelector('.d-flex');
    if (!header) return;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline-primary btn-sm ms-1';
    btn.innerHTML = '<i class="fa-solid fa-user-check me-1"></i>Usar datos del cliente';
    btn.title = 'Prellenar con RFC, CURP, nombre, fecha y nacionalidad del expediente';
    btn.onclick = function() {
        if (!kycDataCache) {
            Swal.fire('Info', 'Seleccione un cliente y espere a que carguen los datos del expediente.', 'info');
            return;
        }
        prefillPersonaFromKyc(container);
    };
    header.appendChild(btn);
    if (autoPrefill && kycDataCache) prefillPersonaFromKyc(container);
}

/* ═══════════════════════════════════════════ */
/* Read entity data helpers */
/* ═══════════════════════════════════════════ */
function readPersona(container) {
    const tipo = container.querySelector('.persona-type-select').value;
    if (tipo === 'persona_fisica') {
        return { persona_fisica: {
            nombre: container.querySelector('.pf-nombre').value.trim(),
            apellido_paterno: container.querySelector('.pf-apellido-paterno').value.trim(),
            apellido_materno: container.querySelector('.pf-apellido-materno').value.trim(),
            fecha_nacimiento: (container.querySelector('.pf-fecha-nacimiento').value || '').replace(/-/g, ''),
            rfc: (container.querySelector('.pf-rfc')?.value || '').trim() || undefined,
            curp: container.querySelector('.pf-curp').value.trim(),
            pais_nacionalidad: container.querySelector('.pf-pais-nacionalidad').value.trim(),
            actividad_economica: container.querySelector('.pf-actividad-economica').value.trim()
        }};
    } else if (tipo === 'persona_moral') {
        return { persona_moral: {
            denominacion_razon: container.querySelector('.pm-denominacion').value.trim(),
            rfc: (container.querySelector('.pm-rfc')?.value || '').trim() || undefined,
            fecha_constitucion: (container.querySelector('.pm-fecha-constitucion').value || '').replace(/-/g, ''),
            pais_nacionalidad: container.querySelector('.pm-pais-nacionalidad').value.trim(),
            giro_mercantil: container.querySelector('.pm-giro-mercantil').value.trim(),
            representante_apoderado: {
                nombre: container.querySelector('.pm-rep-nombre').value.trim(),
                apellido_paterno: container.querySelector('.pm-rep-apellido-paterno').value.trim(),
                apellido_materno: container.querySelector('.pm-rep-apellido-materno').value.trim(),
                fecha_nacimiento: (container.querySelector('.pm-rep-fecha-nacimiento').value || '').replace(/-/g, ''),
                rfc: container.querySelector('.pm-rep-rfc').value.trim(),
                curp: container.querySelector('.pm-rep-curp').value.trim()
            }
        }};
    } else {
        return { fideicomiso: {
            denominacion_razon: container.querySelector('.fid-denominacion').value.trim(),
            rfc: container.querySelector('.fid-rfc').value.trim(),
            identificador_fideicomiso: container.querySelector('.fid-identificador').value.trim()
        }};
    }
}

function readDomicilio(container) {
    const tipo = container.querySelector('.domicilio-type-select').value;
    if (tipo === 'nacional') {
        return { nacional: {
            colonia: container.querySelector('.dn-colonia').value.trim(),
            calle: container.querySelector('.dn-calle').value.trim(),
            numero_exterior: container.querySelector('.dn-numero-exterior').value.trim(),
            numero_interior: container.querySelector('.dn-numero-interior').value.trim() || undefined,
            codigo_postal: container.querySelector('.dn-codigo-postal').value.trim()
        }};
    } else {
        return { extranjero: {
            pais: container.querySelector('.de-pais').value.trim(),
            estado_provincia: container.querySelector('.de-estado-provincia').value.trim(),
            ciudad_poblacion: container.querySelector('.de-ciudad-poblacion').value.trim(),
            colonia: container.querySelector('.de-colonia').value.trim(),
            calle: container.querySelector('.de-calle').value.trim(),
            numero_exterior: container.querySelector('.de-numero-exterior').value.trim(),
            numero_interior: container.querySelector('.de-numero-interior').value.trim() || undefined,
            codigo_postal: container.querySelector('.de-codigo-postal').value.trim()
        }};
    }
}

function readTelefono(container) {
    return {
        clave_pais: container.querySelector('.tel-clave-pais').value.trim(),
        numero_telefono: container.querySelector('.tel-numero').value.trim(),
        correo_electronico: container.querySelector('.tel-correo').value.trim() || undefined
    };
}

function readAportacionEntidad(container) {
    const tipo = container.querySelector('.entidad-aport-tipo-select').value;
    if (tipo === 'numerario') {
        return { aportacion_numerario: {
            instrumento_monetario: container.querySelector('.ea-instrumento').value.trim(),
            moneda: container.querySelector('.ea-moneda').value.trim(),
            monto_aportacion: parseFloat(container.querySelector('.ea-monto').value) || 0,
            aportacion_fideicomiso: container.querySelector('.ea-fideicomiso').value,
            nombre_institucion: container.querySelector('.ea-nombre-inst').value.trim() || undefined
        }};
    } else {
        return { aportacion_especie: {
            descripcion_bien: container.querySelector('.ea-desc-bien').value.trim(),
            monto_estimado: parseFloat(container.querySelector('.ea-monto-estimado').value) || 0
        }};
    }
}

/* ═══════════════════════════════════════════ */
/* Build and submit payload */
/* ═══════════════════════════════════════════ */
function expandCardForField(field) {
    const card = field.closest('.din-card');
    if (!card) return;
    const header = card.querySelector('.din-card-header');
    const body = card.querySelector('.din-card-body');
    if (header && header.classList.contains('collapsed')) {
        header.classList.remove('collapsed');
        if (body) body.style.display = '';
    }
    const section = field.closest('.din-section, .persona-section, .domicilio-section');
    if (section && !section.classList.contains('active')) {
        section.classList.add('active');
    }
}

function validateDINForm() {
    const form = document.getElementById('formDIN');
    const fields = form.querySelectorAll('[required]');
    for (const field of fields) {
        if (field.offsetParent === null && field.closest('.din-section:not(.active), .persona-section:not(.active), .domicilio-section:not(.active)')) {
            continue;
        }
        const aportNum = field.closest('.entidad-aport-numerario');
        const aportEsp = field.closest('.entidad-aport-especie');
        if (aportNum && aportNum.style.display === 'none') continue;
        if (aportEsp && aportEsp.style.display === 'none') continue;
        if (!field.checkValidity()) {
            expandCardForField(field);
            setTimeout(() => {
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                field.reportValidity();
            }, 350);
            return false;
        }
    }
    return true;
}

function guardarOperacionDIN(e) {
    e.preventDefault();
    if (!validateDINForm()) return;
    const idCliente = v('id_cliente');
    if (!idCliente) { Swal.fire('Error', 'Seleccione un cliente', 'error'); return; }

    const fechaAport = document.getElementById('fecha_aportacion').value;
    const fechaAportFmt = fechaAport ? fechaAport.replace(/-/g, '') : '';
    const tipoAportSel = v('tipo_aportacion_selector');

    const tipoAportacion = {};

    if (tipoAportSel === 'recursos_propios') {
        const rpTipo = v('rp_tipo_dato');
        const datosAportacion = {};
        if (rpTipo === 'numerario') {
            datosAportacion.aportacion_numerario = [{
                instrumento_monetario: v('instrumento_monetario') || '1',
                moneda: v('moneda') || '1',
                monto_aportacion: parseFloat(v('monto_aportacion')) || 0,
                aportacion_fideicomiso: v('aportacion_fideicomiso') || 'NO',
                nombre_institucion: v('nombre_institucion') || undefined
            }];
        } else {
            datosAportacion.aportacion_especie = [{
                descripcion_bien: v('rp_descripcion_bien'),
                monto_estimado: parseFloat(v('rp_monto_estimado')) || 0
            }];
        }
        tipoAportacion.recursos_propios = [{ datos_aportacion: [datosAportacion] }];

    } else if (tipoAportSel === 'socios') {
        const items = document.querySelectorAll('#socios_container .socio-item');
        tipoAportacion.numero_socios = parseInt(v('numero_socios')) || items.length;
        const detalleSocios = [];
        items.forEach(item => {
            const socio = {
                aportacion_anterior_socio: item.querySelector('.socio-aport-anterior').value,
                rfc_socio: item.querySelector('.socio-rfc').value.trim(),
                tipo_persona_socio: readPersona(item),
                tipo_domicilio_socio: readDomicilio(item),
                telefono: readTelefono(item),
                detalle_aportaciones: [{ datos_aportacion: readAportacionEntidad(item) }]
            };
            detalleSocios.push(socio);
        });
        tipoAportacion.socios = { numero_socios: tipoAportacion.numero_socios, detalle_socios: detalleSocios };

    } else if (tipoAportSel === 'terceros') {
        const items = document.querySelectorAll('#terceros_container .tercero-item');
        tipoAportacion.numero_terceros = parseInt(v('numero_terceros')) || items.length;
        const detalleTerceros = [];
        items.forEach(item => {
            const tercero = {
                tipo_tercero: item.querySelector('.tercero-tipo').value.trim(),
                descripcion_tercero: item.querySelector('.tercero-descripcion').value.trim() || undefined,
                tipo_persona_tercero: readPersona(item),
                detalle_aportaciones: [{ datos_aportacion: readAportacionEntidad(item) }]
            };
            const valInm = item.querySelector('.tercero-valor-inmueble');
            if (valInm && valInm.value) {
                tercero.valor_inmueble_preventa = parseFloat(valInm.value) || undefined;
            }
            detalleTerceros.push(tercero);
        });
        tipoAportacion.terceros = { numero_terceros: tipoAportacion.numero_terceros, detalle_terceros: detalleTerceros };

    } else if (tipoAportSel === 'prestamo_financiero') {
        tipoAportacion.prestamo_financiero = {
            datos_prestamo: {
                tipo_institucion: v('pf_tipo_institucion'),
                institucion: v('pf_institucion'),
                tipo_credito: v('pf_tipo_credito'),
                monto_prestamo: parseFloat(v('pf_monto_prestamo')) || 0,
                moneda: v('pf_moneda') || '1',
                plazo_meses: parseInt(v('pf_plazo_meses')) || 0
            }
        };

    } else if (tipoAportSel === 'prestamo_no_financiero') {
        const acreedores = [];
        document.querySelectorAll('#acreedores_container .acreedor-item').forEach(item => {
            acreedores.push({ tipo_persona_acreedor: readPersona(item) });
        });
        tipoAportacion.prestamo_no_financiero = {
            datos_prestamo: {
                monto_prestamo: parseFloat(v('pnf_monto_prestamo')) || 0,
                moneda: v('pnf_moneda') || '1',
                plazo_meses: parseInt(v('pnf_plazo_meses')) || 0,
                detalle_acreedores: acreedores
            }
        };

    } else if (tipoAportSel === 'financiamiento_bursatil') {
        const fbFecha = document.getElementById('fb_fecha_emision').value;
        tipoAportacion.financiamiento_bursatil = {
            fecha_emision: fbFecha ? fbFecha.replace(/-/g, '') : '',
            monto_solicitado: parseFloat(v('fb_monto_solicitado')) || 0,
            monto_recibido: parseFloat(v('fb_monto_recibido')) || 0
        };
    }

    const avisoObj = {
        referencia_aviso: v('referencia_aviso'),
        prioridad: v('prioridad'),
        alerta: {
            tipo_alerta: v('tipo_alerta'),
            descripcion_alerta: v('descripcion_alerta') || undefined
        },
        detalle_operaciones: [{
            datos_operacion: [{
                tipo_operacion: v('tipo_operacion'),
                desarrollos_inmobiliarios: [{
                    datos_desarrollo: [{
                        objeto_aviso_anterior: v('objeto_aviso_anterior'),
                        modificacion: v('modificacion_desarrollo'),
                        entidad_federativa: v('entidad_federativa'),
                        registro_licencia: v('registro_licencia'),
                        caracteristicas_desarrollo: [{
                            codigo_postal: v('codigo_postal'),
                            colonia: v('colonia'),
                            calle: v('calle'),
                            tipo_desarrollo: v('tipo_desarrollo'),
                            descripcion_desarrollo: v('descripcion_desarrollo') || undefined,
                            monto_desarrollo: parseFloat(v('monto_desarrollo')) || 0,
                            unidades_comercializadas: parseFloat(v('unidades_comercializadas')) || 1,
                            costo_unidad: parseFloat(v('costo_unidad')) || 0,
                            otras_empresas: v('otras_empresas')
                        }]
                    }]
                }],
                aportaciones: [{
                    fecha_aportacion: fechaAportFmt,
                    tipo_aportacion: [tipoAportacion]
                }]
            }]
        }]
    };

    if (v('es_modificatorio') === '1') {
        avisoObj.modificatorio = {
            folio_modificacion: v('folio_modificacion'),
            descripcion_modificacion: v('descripcion_modificacion')
        };
    }

    const sujetoObligado = {
        clave_sujeto_obligado: v('clave_sujeto_obligado'),
        clave_actividad: v('clave_actividad')
    };
    if (v('clave_entidad_colegiada')) sujetoObligado.clave_entidad_colegiada = v('clave_entidad_colegiada');
    if (v('exento')) sujetoObligado.exento = v('exento');

    const payload = {
        id_cliente: parseInt(idCliente),
        id_fraccion: v('id_fraccion') ? parseInt(v('id_fraccion')) : null,
        es_sospechosa: document.getElementById('es_sospechosa').checked ? 1 : 0,
        fecha_conocimiento_sospecha: document.getElementById('es_sospechosa').checked ? v('fecha_conocimiento_sospecha') : null,
        match_listas_restringidas: document.getElementById('match_listas').checked ? 1 : 0,
        fecha_conocimiento_match: null,
        informe: [{
            mes_reportado: v('mes_reportado'),
            sujeto_obligado: sujetoObligado,
            aviso: [avisoObj]
        }]
    };

    fetch('api/registrar_operacion_din.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Transacción registrada',
                html: (data.requiere_aviso ? '<p><strong>Requiere aviso.</strong> Deadline: ' + (data.fecha_deadline || '') + '</p>' : '<p>Transacción registrada sin aviso.</p>') +
                      '<p>XML almacenado correctamente.</p>'
            }).then(() => {
                window.location.href = 'operaciones_pld.php';
            });
        } else {
            Swal.fire('Error', data.message || 'Error al registrar', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Error de conexión', 'error');
    });
}
</script>

<?php include 'templates/footer.php'; ?>
