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
if (!userCanAccessSPR($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_spr');
    exit;
}

$page_title = 'Aviso SPR - Servicios Profesionales';
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

require_once 'config/spr_catalogos.php';
require_once 'config/tsc_catalogos.php';
require_once 'config/pld_fraccion_xi.php';
$paisOptions = tscCatalogoOptions('pais', 'MX');
$subfraccionesXI = getSubfraccionesXIActivas($pdo, $userId);
$tipoActividadFilter = !empty($subfraccionesXI) ? $subfraccionesXI : null;
// Etiquetas de subfracciones activas para mostrar en el header
$tipoActividadLabels = $SPR_CATALOGOS['tipo_actividad'] ?? [];
$subfraccionesActivasLabels = [];
foreach ($subfraccionesXI as $clave) {
    if (isset($tipoActividadLabels[$clave])) {
        $subfraccionesActivasLabels[] = $tipoActividadLabels[$clave];
    }
}
$subfraccionesTexto = !empty($subfraccionesActivasLabels) ? implode(', ', $subfraccionesActivasLabels) : 'Todas las actividades';
?>
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/operaciones_pld.css">
<style>
:root {
    --spr-primary: #6366f1;
    --spr-primary-dark: #4f46e5;
    --spr-info: #0ea5e9;
    --spr-success: #10b981;
    --spr-warning: #f59e0b;
    --spr-dark: #3730a3;
    --spr-light: #eef2ff;
}
.spr-wrapper { max-width: 960px; margin: 0 auto; }
.spr-card { border: none; border-radius: 16px; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,.06); margin-bottom: 1.5rem; overflow: hidden; }
.spr-card-header { padding: 1rem 1.5rem; display: flex; align-items: center; gap: .75rem; cursor: pointer; user-select: none; border-bottom: 1px solid #e2e8f0; }
.spr-card-header:hover { background: rgba(0,0,0,.015); }
.spr-card-header .spr-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #fff; }
.spr-card-header h5 { margin: 0; font-size: 1rem; font-weight: 700; }
.spr-card-header .spr-chevron { margin-left: auto; font-size: .85rem; color: #94a3b8; transition: transform .25s; }
.spr-card-header.collapsed .spr-chevron { transform: rotate(-90deg); }
.spr-card-body { padding: 1.25rem 1.5rem; }
.icon-informe { background: linear-gradient(135deg, var(--spr-primary), var(--spr-primary-dark)); }
.icon-aviso { background: linear-gradient(135deg, var(--spr-warning), #d97706); }
.icon-persona { background: linear-gradient(135deg, var(--spr-success), #059669); }
.icon-detalle { background: linear-gradient(135deg, #7c3aed, #5b21b6); }
.persona-section, .domicilio-section, .db-persona-section { display: none; }
.persona-section.active, .domicilio-section.active, .db-persona-section.active { display: block; }
.spr-page-header { background: linear-gradient(135deg, var(--spr-primary) 0%, var(--spr-primary-dark) 100%); color: #fff; border-radius: 16px; padding: 1.75rem 2rem; margin-bottom: 1.75rem; }
.spr-submit-bar { position: sticky; bottom: 0; background: #fff; padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; box-shadow: 0 -4px 20px rgba(0,0,0,.06); z-index: 10; }
.badge-xsd { font-size: .58rem; vertical-align: middle; padding: .15em .4em; border-radius: 3px; }
.nested-card { border-left: 3px solid var(--spr-primary); background: var(--spr-light); border-radius: 0 10px 10px 0; padding: 1rem; }
</style>
</head>
<body>

<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="content-wrapper">
<div class="spr-wrapper">
    <div class="spr-page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2><i class="fa-solid fa-briefcase me-2"></i>Aviso SPR</h2>
                <p class="mb-0">Servicios Profesionales — Fracción XI
                    <a href="https://www.sat.gob.mx/consulta/44891/portal-de-prevencion-de-lavado-de-dinero" target="_blank" rel="noopener" class="ms-2 text-white text-decoration-underline">Portal PLD</a>
                </p>
                <p class="mb-0 mt-1 opacity-90" style="font-size: 0.875rem;">
                    <i class="fa-solid fa-layer-group me-1"></i><strong>Subfracción<?= count($subfraccionesActivasLabels) !== 1 ? 'es' : '' ?> activa<?= count($subfraccionesActivasLabels) !== 1 ? 's' : '' ?>:</strong> <?= htmlspecialchars($subfraccionesTexto) ?>
                </p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-light"><i class="fa-solid fa-arrow-left me-1"></i> Volver</a>
        </div>
    </div>

    <form id="formSPR" novalidate>

        <!-- Cliente KYC -->
        <div class="spr-card" id="sec-kyc">
            <div class="spr-card-header" onclick="toggleSprCard(this)">
                <div class="spr-icon icon-informe"><i class="fa-solid fa-user"></i></div>
                <div><h5>Cliente KYC</h5><small class="text-muted">Expediente de identificación</small></div>
                <i class="fa-solid fa-chevron-down spr-chevron"></i>
            </div>
            <div class="spr-card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cliente *</label>
                        <select class="form-select" id="id_cliente" required><option value="">-- Seleccione Cliente --</option></select>
                    </div>
                </div>
                <div id="kyc-preview" style="display:none;">
                    <div class="row g-2 text-muted small">
                        <div class="col-lg-4"><strong>RFC:</strong> <span id="kyc-rfc">-</span></div>
                        <div class="col-lg-4"><strong>Nombre:</strong> <span id="kyc-nombre">-</span></div>
                        <div class="col-lg-4"><strong>Nacionalidad:</strong> <span id="kyc-pais">-</span></div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="prefillPersonaFromKyc()"><i class="fa-solid fa-user-check me-1"></i>Prellenar Persona</button>
                </div>
            </div>
        </div>

        <!-- Informe y Sujeto Obligado -->
        <div class="spr-card" id="sec-informe">
            <div class="spr-card-header" onclick="toggleSprCard(this)">
                <div class="spr-icon icon-informe"><i class="fa-solid fa-file-alt"></i></div>
                <div><h5>Informe y Sujeto Obligado</h5><small class="text-muted">Mes, ocupación, clave</small></div>
                <i class="fa-solid fa-chevron-down spr-chevron"></i>
            </div>
            <div class="spr-card-body">
                <div class="row g-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Mes reportado (AAAAMM) *</label>
                        <input type="text" class="form-control" id="mes_reportado" pattern="\d{6}" maxlength="6" required placeholder="202602" value="<?= date('Ym') ?>">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Clave Sujeto Obligado *</label>
                        <input type="text" class="form-control" id="clave_sujeto_obligado" required maxlength="13" value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" placeholder="Ej: RFC empresa 12-13 caracteres">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Ocupación *</label>
                        <select class="form-select" id="tipo_ocupacion" required><?= sprCatalogoOptions('tipo_ocupacion', 'XI.Y 01') ?></select>
                    </div>
                    <div class="col-md-6 mb-2" id="wrap_descripcion_otra_ocupacion" style="display:none;">
                        <label class="form-label">Descripción otra ocupación *</label>
                        <input type="text" class="form-control text-uppercase" id="descripcion_otra_ocupacion" maxlength="100" placeholder="Ej: Consultor independiente, Asesor fiscal">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Clave Actividad *</label>
                        <input type="text" class="form-control" id="clave_actividad" value="SPR" readonly>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Entidad Colegiada</label>
                        <input type="text" class="form-control" id="clave_entidad_colegiada" maxlength="12" placeholder="Ej: LLAAMMDDXXX (opcional)">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Exento (Art. 27 Bis) *</label>
                        <select class="form-select" id="exento" required><?= sprCatalogoOptions('exento', '0') ?></select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aviso -->
        <div class="spr-card" id="sec-aviso">
            <div class="spr-card-header" onclick="toggleSprCard(this)">
                <div class="spr-icon icon-aviso"><i class="fa-solid fa-bell"></i></div>
                <div><h5>Aviso</h5><small class="text-muted">Referencia, prioridad, alerta</small></div>
                <i class="fa-solid fa-chevron-down spr-chevron"></i>
            </div>
            <div class="spr-card-body">
                <div class="row g-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Referencia Aviso *</label>
                        <input type="text" class="form-control text-uppercase" id="referencia_aviso" maxlength="14" required pattern="[A-ZÑ0-9]{1,14}" placeholder="Ej: REF202601001">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Prioridad *</label>
                        <select class="form-select" id="prioridad" required><?= sprCatalogoOptions('prioridad', '1') ?></select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Tipo Alerta *</label>
                        <select class="form-select" id="tipo_alerta" required><?= sprCatalogoOptions('tipo_alerta', '100') ?></select>
                    </div>
                    <div class="col-12 mb-2">
                        <label class="form-label">Descripción Alerta</label>
                        <textarea class="form-control" id="descripcion_alerta" maxlength="3000" rows="2" placeholder="Ej: Operación fuera del perfil transaccional del cliente"></textarea>
                    </div>
                </div>
                <hr class="my-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">¿Es aviso modificatorio?</label>
                        <select class="form-select" id="es_modificatorio"><option value="0">No</option><option value="1">Sí</option></select>
                    </div>
                </div>
                <div id="seccion_modificatorio" style="display:none;" class="mt-3 nested-card">
                    <div class="row g-3">
                        <div class="col-md-5"><label class="form-label">Folio Modificación *</label><input type="text" class="form-control" id="folio_modificacion" maxlength="14" placeholder="Ej: 2026-123456789"></div>
                        <div class="col-md-7"><label class="form-label">Descripción Modificación *</label><textarea class="form-control" id="descripcion_modificacion" maxlength="3000" rows="2" placeholder="Ej: Se corrige el monto de la operación y se actualiza el domicilio"></textarea></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Persona objeto del aviso -->
        <div class="spr-card" id="sec-persona">
            <div class="spr-card-header" onclick="toggleSprCard(this)">
                <div class="spr-icon icon-persona"><i class="fa-solid fa-id-card"></i></div>
                <div><h5>Persona objeto del aviso</h5><small class="text-muted">Persona física, moral o fideicomiso</small></div>
                <i class="fa-solid fa-chevron-down spr-chevron"></i>
            </div>
            <div class="spr-card-body">
                <div class="row g-3 mb-2">
                    <div class="col-md-6"><label class="form-label">Tipo Persona *</label>
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
                        <div class="col-md-6 mb-2"><label class="form-label">Fecha Nac. *</label><input type="date" class="form-control" id="pf_fecha_nacimiento" required title="AAAA-MM-DD"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">RFC (13 car.) *</label><input type="text" class="form-control" id="pf_rfc" maxlength="13" required placeholder="Ej: LOPG900115ABC"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">CURP (18 car.) *</label><input type="text" class="form-control" id="pf_curp" maxlength="18" required placeholder="Ej: LOPG900115HDFLRN01"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">País Nacionalidad *</label><select class="form-select" id="pf_pais_nacionalidad" required><?= $paisOptions ?></select></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Act. Económica (SCIAN) *</label><select class="form-select" id="pf_actividad_economica" required><?= tscCatalogoOptions('actividad_economica', '1000000') ?></select></div>
                    </div>
                </div>
                <div id="persona_moral_block" class="persona-section">
                    <div class="row g-3">
                        <div class="col-md-6 mb-2"><label class="form-label">Denominación/Razón Social *</label><input type="text" class="form-control text-uppercase" id="pm_denominacion" maxlength="254" required placeholder="Ej: EMPRESA EJEMPLO S.A. DE C.V."></div>
                        <div class="col-md-6 mb-2"><label class="form-label">RFC (12 car.)</label><input type="text" class="form-control" id="pm_rfc" maxlength="12" placeholder="Ej: EEE900101AAA"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Fecha Constitución *</label><input type="date" class="form-control" id="pm_fecha_constitucion" required placeholder="Ej: 2010-05-20"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">País Nacionalidad *</label><select class="form-select" id="pm_pais_nacionalidad" required><?= $paisOptions ?></select></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Giro Mercantil *</label><select class="form-select" id="pm_giro_mercantil" required><?= tscCatalogoOptions('giro_mercantil', '0000000') ?></select></div>
                    </div>
                    <div class="mt-3 p-2 rounded" style="background:#eef2ff;">
                        <label class="form-label fw-bold"><i class="fa-solid fa-user-tie me-1"></i>Representante/Apoderado</label>
                        <div class="row g-3">
                            <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control text-uppercase" id="pm_rep_nombre" maxlength="200" required placeholder="Ej: MARÍA FERNANDA"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Ap. Paterno *</label><input type="text" class="form-control text-uppercase" id="pm_rep_apellido_paterno" maxlength="200" required placeholder="Ej: MARTÍNEZ"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Ap. Materno *</label><input type="text" class="form-control text-uppercase" id="pm_rep_apellido_materno" maxlength="200" required placeholder="Ej: SÁNCHEZ"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Fecha Nac. *</label><input type="date" class="form-control" id="pm_rep_fecha_nacimiento" required placeholder="Ej: 1985-03-10"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">RFC *</label><input type="text" class="form-control" id="pm_rep_rfc" maxlength="13" required placeholder="Ej: MAMS850310ABC"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">CURP *</label><input type="text" class="form-control" id="pm_rep_curp" maxlength="18" required placeholder="Ej: MAMS850310MDFRNR01"></div>
                        </div>
                    </div>
                </div>
                <div id="fideicomiso_block" class="persona-section">
                    <div class="row g-3">
                        <div class="col-12 mb-2"><label class="form-label">Denominación *</label><input type="text" class="form-control text-uppercase" id="fid_denominacion" maxlength="254" required placeholder="Ej: FIDEICOMISO EJEMPLO S.A. DE C.V."></div>
                        <div class="col-md-6 mb-2"><label class="form-label">RFC (12 car.) *</label><input type="text" class="form-control" id="fid_rfc" maxlength="12" required placeholder="Ej: FDE900101AAA"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Identificador Fideicomiso *</label><input type="text" class="form-control" id="fid_identificador" maxlength="40" required placeholder="Ej: FID-001-2026"></div>
                    </div>
                    <div class="mt-3 p-2 rounded" style="background:#eef2ff;">
                        <label class="form-label fw-bold">Apoderado/Delegado</label>
                        <div class="row g-3">
                            <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control text-uppercase" id="fid_apod_nombre" maxlength="200" required placeholder="Ej: PEDRO ANTONIO"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Ap. Paterno *</label><input type="text" class="form-control text-uppercase" id="fid_apod_apellido_paterno" maxlength="200" required placeholder="Ej: HERNÁNDEZ"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Ap. Materno *</label><input type="text" class="form-control text-uppercase" id="fid_apod_apellido_materno" maxlength="200" required placeholder="Ej: RAMÍREZ"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Fecha Nac. *</label><input type="date" class="form-control" id="fid_apod_fecha_nacimiento" required placeholder="Ej: 1975-08-22"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">RFC *</label><input type="text" class="form-control" id="fid_apod_rfc" maxlength="13" required placeholder="Ej: HERP750822ABC"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">CURP *</label><input type="text" class="form-control" id="fid_apod_curp" maxlength="18" required placeholder="Ej: HERP750822HDFLRN01"></div>
                        </div>
                    </div>
                </div>

                <hr class="my-3"><h6 class="fw-bold mb-2">Domicilio</h6>
                <div class="row g-3 mb-2">
                    <div class="col-md-6"><label class="form-label">Tipo Domicilio *</label>
                        <select class="form-select" id="tipo_domicilio">
                            <option value="nacional">Nacional</option>
                            <option value="extranjero">Extranjero</option>
                        </select>
                    </div>
                </div>
                <div id="domicilio_nacional" class="domicilio-section active">
                    <div class="row g-3">
                        <div class="col-md-6 mb-2"><label class="form-label">Colonia *</label><input type="text" class="form-control text-uppercase" id="dom_colonia" maxlength="100" required placeholder="Ej: CENTRO"></div>
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
                        <div class="col-md-6 mb-2"><label class="form-label">Colonia *</label><input type="text" class="form-control text-uppercase" id="dom_ext_colonia" maxlength="100" required placeholder="Ej: DOWNTOWN"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Calle *</label><input type="text" class="form-control text-uppercase" id="dom_ext_calle" maxlength="100" required placeholder="Ej: MAIN STREET 456"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Núm. Exterior *</label><input type="text" class="form-control" id="dom_ext_numero" maxlength="56" required placeholder="Ej: 456"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Núm. Interior</label><input type="text" class="form-control" id="dom_ext_numero_int" maxlength="40" placeholder="Ej: 2 (opcional)"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">C.P. *</label><input type="text" class="form-control" id="dom_ext_cp" maxlength="12" required placeholder="Ej: 90001"></div>
                    </div>
                </div>

                <hr class="my-3"><h6 class="fw-bold mb-2">Teléfono</h6>
                <div class="row g-3">
                    <div class="col-md-4 mb-2"><label class="form-label">País *</label><select class="form-select" id="tel_clave_pais" required><?= $paisOptions ?></select></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Número *</label><input type="text" class="form-control" id="tel_numero" maxlength="12" pattern="\d{10,12}" required placeholder="Ej: 5512345678 (10-12 dígitos)"></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Correo</label><input type="email" class="form-control" id="tel_correo" maxlength="60" placeholder="Ej: correo@ejemplo.com"></div>
                </div>

                <hr class="my-3">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="incluir_dueno_beneficiario">
                    <label class="form-check-label" for="incluir_dueno_beneficiario"><strong>Incluir Dueño Beneficiario</strong> <small class="text-muted">(solo datos básicos, sin domicilio/teléfono)</small></label>
                </div>
                <div id="seccion_dueno_beneficiario" style="display:none;" class="nested-card">
                    <div class="row g-3 mb-2">
                        <div class="col-md-6"><label class="form-label">Tipo Persona *</label>
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
                            <div class="col-md-6 mb-2"><label class="form-label">Fecha Nac.</label><input type="date" class="form-control" id="db_pf_fecha_nacimiento" placeholder="Ej: 1990-01-15"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control" id="db_pf_rfc" maxlength="13" placeholder="Ej: LOPG900115ABC"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">CURP</label><input type="text" class="form-control" id="db_pf_curp" maxlength="18" placeholder="Ej: LOPG900115HDFLRN01"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">País Nacionalidad</label><select class="form-select" id="db_pf_pais_nacionalidad"><?= $paisOptions ?></select></div>
                        </div>
                    </div>
                    <div id="db_persona_moral_block" class="db-persona-section">
                        <div class="row g-3">
                            <div class="col-md-6 mb-2"><label class="form-label">Denominación/Razón Social *</label><input type="text" class="form-control text-uppercase" id="db_pm_denominacion" maxlength="254" placeholder="Ej: EMPRESA EJEMPLO S.A. DE C.V."></div>
                            <div class="col-md-6 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control" id="db_pm_rfc" maxlength="12" placeholder="Ej: EEE900101AAA"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Fecha Constitución</label><input type="date" class="form-control" id="db_pm_fecha_constitucion" placeholder="Ej: 2010-05-20"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">País Nacionalidad</label><select class="form-select" id="db_pm_pais_nacionalidad"><?= $paisOptions ?></select></div>
                        </div>
                    </div>
                    <div id="db_fideicomiso_block" class="db-persona-section">
                        <div class="row g-3">
                            <div class="col-12 mb-2"><label class="form-label">Denominación *</label><input type="text" class="form-control text-uppercase" id="db_fid_denominacion" maxlength="254" placeholder="Ej: FIDEICOMISO EJEMPLO"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control" id="db_fid_rfc" maxlength="12" placeholder="Ej: FDE900101AAA"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Identificador</label><input type="text" class="form-control" id="db_fid_identificador" maxlength="40" placeholder="Ej: FID-001-2026"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detalle operaciones SPR -->
        <div class="spr-card" id="sec-detalle">
            <div class="spr-card-header" onclick="toggleSprCard(this)">
                <div class="spr-icon icon-detalle"><i class="fa-solid fa-briefcase"></i></div>
                <div><h5>Detalle de la operación</h5><small class="text-muted">Tipo de actividad y datos financieros</small></div>
                <i class="fa-solid fa-chevron-down spr-chevron"></i>
            </div>
            <div class="spr-card-body">
                <div class="row g-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Fecha operación *</label>
                        <input type="date" class="form-control" id="fecha_operacion" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Tipo de actividad *</label>
                        <select class="form-select" id="tipo_actividad" required>
                            <?= sprCatalogoOptions('tipo_actividad', 'compra_venta_inmuebles', $tipoActividadFilter) ?>
                        </select>
                    </div>
                </div>
                <div id="cesion_derechos_inmuebles_section" style="display:none;">
                    <hr class="my-3">
                    <h6 class="fw-bold mb-2">Cesión de Derechos sobre Inmuebles</h6>
                    <div class="row g-3">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Figura del cliente *</label>
                            <select class="form-select" id="cdi_figura_cliente"><?= sprCatalogoOptions('figura_cliente_cesion', '2') ?></select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Tipo de cesión *</label>
                            <select class="form-select" id="cdi_tipo_cesion"><?= sprCatalogoOptions('tipo_cesion', '2') ?></select>
                        </div>
                    </div>
                    <h6 class="fw-bold mt-3 mb-2">Contraparte(s)</h6>
                    <div id="cdi_contrapartes_container">
                        <div class="nested-card mb-3 contraparte-item cdi-contraparte" data-idx="0">
                            <div class="row g-3 mb-2">
                                <div class="col-md-6"><label class="form-label">Tipo persona</label>
                                    <select class="form-select contraparte-tipo">
                                        <option value="persona_fisica">Persona Física</option>
                                        <option value="persona_moral">Persona Moral</option>
                                        <option value="fideicomiso">Fideicomiso</option>
                                    </select>
                                </div>
                            </div>
                            <div class="contraparte-pf">
                                <div class="row g-3">
                                    <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control text-uppercase contraparte-pf-nombre" maxlength="200"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Ap. Paterno *</label><input type="text" class="form-control text-uppercase contraparte-pf-ap" maxlength="200"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Ap. Materno *</label><input type="text" class="form-control text-uppercase contraparte-pf-am" maxlength="200"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Fecha Nac.</label><input type="date" class="form-control contraparte-pf-fnac"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control contraparte-pf-rfc" maxlength="13"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">CURP</label><input type="text" class="form-control contraparte-pf-curp" maxlength="18"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">País *</label><select class="form-select contraparte-pf-pais"><?= $paisOptions ?></select></div>
                                </div>
                            </div>
                            <div class="contraparte-pm" style="display:none;">
                                <div class="row g-3">
                                    <div class="col-md-6 mb-2"><label class="form-label">Denominación *</label><input type="text" class="form-control text-uppercase contraparte-pm-denom" maxlength="254"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control contraparte-pm-rfc" maxlength="12"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Fecha Const.</label><input type="date" class="form-control contraparte-pm-fconst"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">País *</label><select class="form-select contraparte-pm-pais"><?= $paisOptions ?></select></div>
                                </div>
                            </div>
                            <div class="contraparte-fid" style="display:none;">
                                <div class="row g-3">
                                    <div class="col-12 mb-2"><label class="form-label">Denominación *</label><input type="text" class="form-control text-uppercase contraparte-fid-denom" maxlength="254"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control contraparte-fid-rfc" maxlength="12"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Identificador</label><input type="text" class="form-control contraparte-fid-id" maxlength="40"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="agregarContraparteCesion()"><i class="fa-solid fa-plus me-1"></i>Agregar contraparte</button>
                    <h6 class="fw-bold mt-3 mb-2">Características del inmueble (con valor referencia)</h6>
                    <div id="cdi_inmuebles_container">
                        <div class="nested-card mb-3 inmueble-item cdi-inmueble" data-idx="0">
                            <div class="row g-3">
                                <div class="col-md-6 mb-2"><label class="form-label">Tipo inmueble *</label><select class="form-select inmueble-tipo"><?= sprCatalogoOptions('tipo_inmueble', '1') ?></select></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Valor referencia (MXN) *</label><input type="number" class="form-control inmueble-valorref" step="0.01" min="0" placeholder="0.00"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Colonia *</label><input type="text" class="form-control text-uppercase inmueble-colonia" maxlength="100"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Calle *</label><input type="text" class="form-control text-uppercase inmueble-calle" maxlength="100"></div>
                                <div class="col-md-3 mb-2"><label class="form-label">Núm. Ext. *</label><input type="text" class="form-control inmueble-numext" maxlength="56"></div>
                                <div class="col-md-3 mb-2"><label class="form-label">Núm. Int.</label><input type="text" class="form-control inmueble-numint" maxlength="40"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">C.P. *</label><input type="text" class="form-control inmueble-cp" maxlength="5" pattern="\d{5}"></div>
                                <div class="col-md-3 mb-2"><label class="form-label">Dimensión terreno (m²) *</label><input type="number" class="form-control inmueble-dimterr" step="0.01" min="0"></div>
                                <div class="col-md-3 mb-2"><label class="form-label">Dimensión construido (m²) *</label><input type="number" class="form-control inmueble-dimconst" step="0.01" min="0"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Folio real *</label><input type="text" class="form-control inmueble-folio" maxlength="200"></div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="agregarInmuebleCesion()"><i class="fa-solid fa-plus me-1"></i>Agregar inmueble</button>
                </div>
                <div id="administracion_recursos_section" style="display:none;">
                    <hr class="my-3">
                    <h6 class="fw-bold mb-2">Administración y manejo de recursos, valores, cuentas bancarias</h6>
                    <p class="small text-muted">Agregue al menos un tipo de activo administrado</p>

                    <h6 class="fw-bold mt-3 mb-2">Activos inmobiliarios</h6>
                    <div id="ar_inmuebles_container">
                        <div class="nested-card mb-3 ar-inmueble-item" data-idx="0">
                            <div class="row g-2">
                                <div class="col-md-4"><label class="form-label">Tipo inmueble</label><select class="form-select ar-inm-tipo"><?= sprCatalogoOptions('tipo_inmueble', '3') ?></select></div>
                                <div class="col-md-4"><label class="form-label">Valor ref. (MXN)</label><input type="number" class="form-control ar-inm-valorref" step="0.01" min="0" placeholder="0.00"></div>
                                <div class="col-md-4"><label class="form-label">Colonia</label><input type="text" class="form-control text-uppercase ar-inm-colonia" maxlength="100"></div>
                                <div class="col-md-6"><label class="form-label">Calle</label><input type="text" class="form-control text-uppercase ar-inm-calle" maxlength="100"></div>
                                <div class="col-md-2"><label class="form-label">Núm. Ext.</label><input type="text" class="form-control ar-inm-numext" maxlength="56"></div>
                                <div class="col-md-2"><label class="form-label">Núm. Int.</label><input type="text" class="form-control ar-inm-numint" maxlength="40"></div>
                                <div class="col-md-2"><label class="form-label">C.P.</label><input type="text" class="form-control ar-inm-cp" maxlength="5"></div>
                                <div class="col-md-6"><label class="form-label">Folio real</label><input type="text" class="form-control ar-inm-folio" maxlength="200"></div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="agregarARInmueble()"><i class="fa-solid fa-plus me-1"></i>Agregar inmueble</button>

                    <h6 class="fw-bold mt-3 mb-2">Activos banco / cuentas</h6>
                    <div id="ar_bancos_container">
                        <div class="nested-card mb-3 ar-banco-item" data-idx="0">
                            <div class="row g-2">
                                <div class="col-md-3"><label class="form-label">Estatus manejo</label><select class="form-select ar-banco-estatus"><?= sprCatalogoOptions('estatus_manejo', '1') ?></select></div>
                                <div class="col-md-3"><label class="form-label">Tipo institución</label><select class="form-select ar-banco-tipoinst"><?= sprCatalogoOptions('clave_tipo_institucion', '40') ?></select></div>
                                <div class="col-md-6"><label class="form-label">Nombre institución</label><input type="text" class="form-control text-uppercase ar-banco-nombre" maxlength="150"></div>
                                <div class="col-12"><label class="form-label">Número de cuenta</label><input type="text" class="form-control ar-banco-cuenta" maxlength="50"></div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="agregarARBanco()"><i class="fa-solid fa-plus me-1"></i>Agregar cuenta</button>

                    <h6 class="fw-bold mt-3 mb-2">Activos outsourcing (servicios especializados)</h6>
                    <div id="ar_outsourcing_container">
                        <div class="nested-card mb-3 ar-out-item" data-idx="0">
                            <div class="row g-2">
                                <div class="col-md-4"><label class="form-label">Área de servicio</label><select class="form-select ar-out-area"><?= sprCatalogoOptions('tipo_area_servicio', '1') ?></select></div>
                                <div class="col-md-4"><label class="form-label">Activo administrado</label><select class="form-select ar-out-activo"><?= sprCatalogoOptions('tipo_activo_administrado', '4') ?></select></div>
                                <div class="col-md-4"><label class="form-label">Núm. empleados</label><input type="number" class="form-control ar-out-empleados" min="0" placeholder="0"></div>
                                <div class="col-12 ar-out-desc-area" style="display:none;"><label class="form-label">Descripción otro área</label><input type="text" class="form-control ar-out-desc-area-txt" maxlength="500"></div>
                                <div class="col-12 ar-out-desc-activo" style="display:none;"><label class="form-label">Descripción otro activo</label><input type="text" class="form-control ar-out-desc-activo-txt" maxlength="500"></div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="agregarAROutsourcing()"><i class="fa-solid fa-plus me-1"></i>Agregar outsourcing</button>

                    <h6 class="fw-bold mt-3 mb-2">Otros activos</h6>
                    <div id="ar_otros_container">
                        <div class="nested-card mb-3 ar-otros-item" data-idx="0">
                            <div class="col-12"><label class="form-label">Descripción del activo administrado</label><textarea class="form-control ar-otros-desc" rows="2" maxlength="2000" placeholder="Descripción del activo que no aplica en las secciones anteriores"></textarea></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="agregarAROtros()"><i class="fa-solid fa-plus me-1"></i>Agregar otro activo</button>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Número de operaciones *</label>
                        <input type="text" class="form-control" id="ar_numero_operaciones" maxlength="15" placeholder="Ej: 9876543210" pattern="[0-9]+">
                    </div>
                </div>
                <div id="compra_venta_inmuebles_section">
                    <hr class="my-3">
                    <h6 class="fw-bold mb-2">Compraventa de Inmuebles</h6>
                    <div class="row g-3">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Tipo operación *</label>
                            <select class="form-select" id="cvi_tipo_operacion"><?= sprCatalogoOptions('tipo_operacion_compraventa', '2') ?></select>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Valor pactado (MXN) *</label>
                            <input type="number" class="form-control" id="cvi_valor_pactado" step="0.01" min="0" placeholder="Ej: 1500000.00 (monto en MXN)">
                        </div>
                    </div>
                    <h6 class="fw-bold mt-3 mb-2">Contraparte(s)</h6>
                    <div id="contrapartes_container">
                        <div class="nested-card mb-3 contraparte-item" data-idx="0">
                            <div class="row g-3 mb-2">
                                <div class="col-md-6"><label class="form-label">Tipo persona</label>
                                    <select class="form-select contraparte-tipo">
                                        <option value="persona_fisica">Persona Física</option>
                                        <option value="persona_moral">Persona Moral</option>
                                        <option value="fideicomiso">Fideicomiso</option>
                                    </select>
                                </div>
                            </div>
                            <div class="contraparte-pf">
                                <div class="row g-3">
                                    <div class="col-md-6 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control text-uppercase contraparte-pf-nombre" maxlength="200" placeholder="Ej: JUAN CARLOS"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Ap. Paterno *</label><input type="text" class="form-control text-uppercase contraparte-pf-ap" maxlength="200" placeholder="Ej: LÓPEZ"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Ap. Materno *</label><input type="text" class="form-control text-uppercase contraparte-pf-am" maxlength="200" placeholder="Ej: GARCÍA"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Fecha Nac.</label><input type="date" class="form-control contraparte-pf-fnac" placeholder="Ej: 1990-01-15"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control contraparte-pf-rfc" maxlength="13" placeholder="Ej: LOPG900115ABC"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">CURP</label><input type="text" class="form-control contraparte-pf-curp" maxlength="18" placeholder="Ej: LOPG900115HDFLRN01"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">País *</label><select class="form-select contraparte-pf-pais"><?= $paisOptions ?></select></div>
                                </div>
                            </div>
                            <div class="contraparte-pm" style="display:none;">
                                <div class="row g-3">
                                    <div class="col-md-6 mb-2"><label class="form-label">Denominación *</label><input type="text" class="form-control text-uppercase contraparte-pm-denom" maxlength="254" placeholder="Ej: EMPRESA EJEMPLO S.A. DE C.V."></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control contraparte-pm-rfc" maxlength="12" placeholder="Ej: EEE900101AAA"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Fecha Const.</label><input type="date" class="form-control contraparte-pm-fconst" placeholder="Ej: 2010-05-20"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">País *</label><select class="form-select contraparte-pm-pais"><?= $paisOptions ?></select></div>
                                </div>
                            </div>
                            <div class="contraparte-fid" style="display:none;">
                                <div class="row g-3">
                                    <div class="col-12 mb-2"><label class="form-label">Denominación *</label><input type="text" class="form-control text-uppercase contraparte-fid-denom" maxlength="254" placeholder="Ej: FIDEICOMISO EJEMPLO"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control contraparte-fid-rfc" maxlength="12" placeholder="Ej: FDE900101AAA"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Identificador</label><input type="text" class="form-control contraparte-fid-id" maxlength="40" placeholder="Ej: FID-001-2026"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="agregarContraparte()"><i class="fa-solid fa-plus me-1"></i>Agregar contraparte</button>
                    <h6 class="fw-bold mt-3 mb-2">Características del inmueble</h6>
                    <div id="inmuebles_container">
                        <div class="nested-card mb-3 inmueble-item" data-idx="0">
                            <div class="row g-3">
                                <div class="col-md-6 mb-2"><label class="form-label">Tipo inmueble *</label><select class="form-select inmueble-tipo"><?= sprCatalogoOptions('tipo_inmueble', '1') ?></select></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Colonia *</label><input type="text" class="form-control text-uppercase inmueble-colonia" maxlength="100" placeholder="Ej: CENTRO"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Calle *</label><input type="text" class="form-control text-uppercase inmueble-calle" maxlength="100" placeholder="Ej: AV. REFORMA 123"></div>
                                <div class="col-md-3 mb-2"><label class="form-label">Núm. Ext. *</label><input type="text" class="form-control inmueble-numext" maxlength="56" placeholder="Ej: 123"></div>
                                <div class="col-md-3 mb-2"><label class="form-label">Núm. Int.</label><input type="text" class="form-control inmueble-numint" maxlength="40" placeholder="Ej: 4 (opcional)"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">C.P. *</label><input type="text" class="form-control inmueble-cp" maxlength="5" pattern="\d{5}" placeholder="Ej: 06000"></div>
                                <div class="col-md-3 mb-2"><label class="form-label">Dimensión terreno (m²) *</label><input type="number" class="form-control inmueble-dimterr" step="0.01" min="0" placeholder="Ej: 250.00"></div>
                                <div class="col-md-3 mb-2"><label class="form-label">Dimensión construido (m²) *</label><input type="number" class="form-control inmueble-dimconst" step="0.01" min="0" placeholder="Ej: 120.00"></div>
                                <div class="col-md-6 mb-2"><label class="form-label">Folio real *</label><input type="text" class="form-control inmueble-folio" maxlength="200" placeholder="Ej: 123456"></div>
                                <div class="col-12 mb-2"><label class="form-label">¿Instrumento público o contrato privado? *</label>
                                    <select class="form-select inmueble-contrato-tipo">
                                        <option value="instrumento">Instrumento Público</option>
                                        <option value="contrato">Contrato Privado</option>
                                    </select>
                                </div>
                                <div class="inmueble-instrumento">
                                    <div class="row g-3">
                                        <div class="col-md-6 mb-2"><label class="form-label">Núm. instrumento *</label><input type="text" class="form-control inmueble-numinstr" maxlength="20" placeholder="Ej: 12345"></div>
                                        <div class="col-md-6 mb-2"><label class="form-label">Fecha instrumento *</label><input type="date" class="form-control inmueble-fechainstr" placeholder="Ej: 2025-01-15"></div>
                                        <div class="col-md-6 mb-2"><label class="form-label">Notario *</label><input type="text" class="form-control inmueble-notario" maxlength="8" placeholder="Ej: 123"></div>
                                        <div class="col-md-6 mb-2"><label class="form-label">Entidad fed. *</label><select class="form-select inmueble-entidad"><?= sprCatalogoOptions('entidad_federativa', '14') ?></select></div>
                                        <div class="col-md-6 mb-2"><label class="form-label">Valor referencia *</label><input type="number" class="form-control inmueble-valorref" step="0.01" min="0" placeholder="Ej: 1500000.00 (MXN)"></div>
                                    </div>
                                </div>
                                <div class="inmueble-contrato" style="display:none;">
                                    <div class="row g-3">
                                        <div class="col-md-6 mb-2"><label class="form-label">Fecha contrato *</label><input type="date" class="form-control inmueble-fechacto"></div>
                                        <div class="col-md-6 mb-2"><label class="form-label">Valor referencia *</label><input type="number" class="form-control inmueble-valorrefcto" step="0.01" min="0" placeholder="Ej: 1500000.00 (MXN)"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="agregarInmueble()"><i class="fa-solid fa-plus me-1"></i>Agregar inmueble</button>
                </div>
                <div id="admin_personas_morales_section" style="display:none;">
                    <hr class="my-3">
                    <h6 class="fw-bold mb-2">Administración de Personas Morales</h6>
                    <div class="row g-3">
                        <div class="col-12 mb-2">
                            <label class="form-label">Tipo de administración *</label>
                            <textarea class="form-control" id="tipo_administracion" maxlength="2000" rows="2" required placeholder="Descripción del tipo de administración (1-2000 car.)"></textarea>
                        </div>
                        <div class="col-12 mb-2">
                            <label class="form-label">Tipo de operación *</label>
                            <textarea class="form-control" id="tipo_operacion_text" maxlength="2000" rows="2" required placeholder="Descripción del tipo de operación (1-2000 car.)"></textarea>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">¿Persona moral objeto del aviso? *</label>
                            <select class="form-select" id="persona_moral_aviso" required>
                                <option value="SI">Sí</option>
                                <option value="NO">No</option>
                            </select>
                        </div>
                        <div class="col-12 mb-2" id="wrap_tipo_persona_admon" style="display:none;">
                            <label class="form-label">Datos persona moral (opcional)</label>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Tipo</label>
                                    <select class="form-select" id="admon_tipo_persona">
                                        <option value="persona_moral">Persona Moral</option>
                                        <option value="fideicomiso">Fideicomiso</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-2 admon-pm"><label class="form-label">Denominación</label><input type="text" class="form-control text-uppercase" id="admon_pm_denominacion" maxlength="254"></div>
                                <div class="col-md-6 mb-2 admon-pm"><label class="form-label">RFC</label><input type="text" class="form-control" id="admon_pm_rfc" maxlength="12"></div>
                                <div class="col-md-6 mb-2 admon-pm"><label class="form-label">Fecha Const.</label><input type="date" class="form-control" id="admon_pm_fecha_constitucion"></div>
                                <div class="col-md-6 mb-2 admon-pm"><label class="form-label">País</label><select class="form-select" id="admon_pm_pais"><?= $paisOptions ?></select></div>
                                <div class="col-md-6 mb-2 admon-fid"><label class="form-label">Denominación Fideicomiso</label><input type="text" class="form-control text-uppercase" id="admon_fid_denominacion" maxlength="254"></div>
                                <div class="col-md-6 mb-2 admon-fid"><label class="form-label">RFC Fideicomiso</label><input type="text" class="form-control" id="admon_fid_rfc" maxlength="12"></div>
                                <div class="col-md-6 mb-2 admon-fid"><label class="form-label">Identificador</label><input type="text" class="form-control" id="admon_fid_identificador" maxlength="40"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="constitucion_sociedades_section" style="display:none;">
                    <hr class="my-3">
                    <h6 class="fw-bold mb-2">Constitución de Personas Morales (sociedades mercantiles)</h6>
                    <div class="row g-3">
                        <div class="col-md-6 mb-2"><label class="form-label">Tipo persona moral *</label><select class="form-select" id="csm_tipo_persona_moral" required><?= sprCatalogoOptions('tipo_persona_moral', '6') ?></select></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Denominación/Razón social *</label><input type="text" class="form-control text-uppercase" id="csm_denominacion" maxlength="254" required placeholder="Ej: NUEVA EMPRESA S.A. DE C.V."></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Giro mercantil *</label><select class="form-select" id="csm_giro_mercantil" required><?= tscCatalogoOptions('giro_mercantil', '0000000') ?></select></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Folio mercantil</label><input type="text" class="form-control" id="csm_folio_mercantil" maxlength="50" placeholder="Ej: FM2058"></div>
                        <div class="col-md-4 mb-2"><label class="form-label">Núm. total acciones *</label><input type="number" class="form-control" id="csm_numero_total_acciones" step="0.01" min="0" required placeholder="1500.00"></div>
                        <div class="col-md-4 mb-2"><label class="form-label">Entidad federativa *</label><select class="form-select" id="csm_entidad_federativa" required><?= sprCatalogoOptions('entidad_federativa', '14') ?></select></div>
                        <div class="col-md-4 mb-2"><label class="form-label">Consejo de vigilancia *</label><select class="form-select" id="csm_consejo_vigilancia" required><?= sprCatalogoOptions('consejo_vigilancia', 'NO') ?></select></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Motivo constitución *</label><select class="form-select" id="csm_motivo_constitucion" required><?= sprCatalogoOptions('motivo_constitucion', '1') ?></select></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Instrumento público *</label><input type="text" class="form-control" id="csm_instrumento_publico" maxlength="50" required placeholder="Ej: N-2021-0156"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Capital fijo (MXN) *</label><input type="number" class="form-control" id="csm_capital_fijo" step="0.01" min="0" required placeholder="0.00"></div>
                        <div class="col-md-6 mb-2"><label class="form-label">Capital variable (MXN)</label><input type="number" class="form-control" id="csm_capital_variable" step="0.01" min="0" placeholder="0.00"></div>
                    </div>
                    <h6 class="fw-bold mt-3 mb-2">Accionistas/Socios</h6>
                    <div id="csm_accionistas_container">
                        <div class="nested-card mb-3 csm-accionista-item" data-idx="0">
                            <div class="row g-3 mb-2">
                                <div class="col-md-4"><label class="form-label">Cargo *</label><select class="form-select csm-acc-cargo"><?= sprCatalogoOptions('cargo_accionista', '4') ?></select></div>
                                <div class="col-md-4"><label class="form-label">Tipo persona</label><select class="form-select csm-acc-tipo"><option value="persona_fisica">Persona Física</option><option value="persona_moral">Persona Moral</option><option value="fideicomiso">Fideicomiso</option></select></div>
                                <div class="col-md-4"><label class="form-label">Núm. acciones *</label><input type="number" class="form-control csm-acc-num" step="0.01" min="0" placeholder="0.00"></div>
                            </div>
                            <div class="csm-acc-pf">
                                <div class="row g-3">
                                    <div class="col-md-4 mb-2"><label class="form-label">Nombre(s) *</label><input type="text" class="form-control text-uppercase csm-acc-pf-nombre" maxlength="200"></div>
                                    <div class="col-md-4 mb-2"><label class="form-label">Ap. Paterno *</label><input type="text" class="form-control text-uppercase csm-acc-pf-ap" maxlength="200"></div>
                                    <div class="col-md-4 mb-2"><label class="form-label">Ap. Materno *</label><input type="text" class="form-control text-uppercase csm-acc-pf-am" maxlength="200"></div>
                                    <div class="col-md-4 mb-2"><label class="form-label">Fecha Nac.</label><input type="date" class="form-control csm-acc-pf-fnac"></div>
                                    <div class="col-md-4 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control csm-acc-pf-rfc" maxlength="13"></div>
                                    <div class="col-md-4 mb-2"><label class="form-label">CURP</label><input type="text" class="form-control csm-acc-pf-curp" maxlength="18"></div>
                                    <div class="col-md-4 mb-2"><label class="form-label">País *</label><select class="form-select csm-acc-pf-pais"><?= $paisOptions ?></select></div>
                                </div>
                            </div>
                            <div class="csm-acc-pm" style="display:none;">
                                <div class="row g-3">
                                    <div class="col-md-6 mb-2"><label class="form-label">Denominación *</label><input type="text" class="form-control text-uppercase csm-acc-pm-denom" maxlength="254"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control csm-acc-pm-rfc" maxlength="12"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Fecha Const.</label><input type="date" class="form-control csm-acc-pm-fconst"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">País *</label><select class="form-select csm-acc-pm-pais"><?= $paisOptions ?></select></div>
                                </div>
                            </div>
                            <div class="csm-acc-fid" style="display:none;">
                                <div class="row g-3">
                                    <div class="col-md-6 mb-2"><label class="form-label">Denominación *</label><input type="text" class="form-control text-uppercase csm-acc-fid-denom" maxlength="254"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">RFC</label><input type="text" class="form-control csm-acc-fid-rfc" maxlength="12"></div>
                                    <div class="col-md-6 mb-2"><label class="form-label">Identificador</label><input type="text" class="form-control csm-acc-fid-id" maxlength="40"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="agregarAccionistaCSM()"><i class="fa-solid fa-plus me-1"></i>Agregar accionista/socio</button>
                </div>
                <hr class="my-3">
                <h6 class="fw-bold mb-2">Datos operación financiera *</h6>
                <div id="datos_fin_container">
                    <div class="nested-card mb-3 datos-fin-item" data-idx="0">
                        <div class="row g-3">
                            <div class="col-md-6 mb-2"><label class="form-label">Fecha pago</label><input type="date" class="form-control datos-fin-fechapago"></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Instrumento monetario *</label><select class="form-select datos-fin-instr"><?= sprCatalogoOptions('instrumento_monetario', '8') ?></select></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Moneda</label><select class="form-select datos-fin-moneda"><?= sprCatalogoOptions('moneda', '112') ?></select></div>
                            <div class="col-md-6 mb-2"><label class="form-label">Monto operación *</label><input type="number" class="form-control datos-fin-monto" step="0.01" min="0" placeholder="0.00"></div>
                            <div class="col-12 mb-2"><label class="form-check-label"><input type="checkbox" class="form-check-input datos-fin-activo-virt"> Incluir activo virtual</label></div>
                            <div class="datos-fin-av" style="display:none;">
                                <div class="row g-3">
                                    <div class="col-md-4 mb-2"><label class="form-label">Tipo activo virtual</label><input type="number" class="form-control datos-fin-av-tipo" min="1001" placeholder="1001"></div>
                                    <div class="col-md-4 mb-2"><label class="form-label">Descripción (si 999999)</label><input type="text" class="form-control datos-fin-av-desc" maxlength="100"></div>
                                    <div class="col-md-4 mb-2"><label class="form-label">Cantidad</label><input type="text" class="form-control datos-fin-av-cant" placeholder="0.00"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm mb-3" onclick="agregarDatosFinancieros()"><i class="fa-solid fa-plus me-1"></i>Agregar forma de pago</button>
            </div>
        </div>

        <div class="spr-submit-bar">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-2"></i>Registrar y Generar XML</button>
            <a href="operaciones_pld.php" class="btn btn-outline-secondary">Cancelar</a>
            <span class="text-muted ms-auto d-none d-md-inline small"><i class="fa-solid fa-info-circle me-1"></i>XML según instructivo SPR</span>
        </div>
    </form>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function toggleSprCard(header) {
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
    document.getElementById('tipo_ocupacion').addEventListener('change', function() {
        const wrap = document.getElementById('wrap_descripcion_otra_ocupacion');
        wrap.style.display = this.value === '99' ? 'block' : 'none';
        document.getElementById('descripcion_otra_ocupacion').required = this.value === '99';
    });
    document.getElementById('es_modificatorio').addEventListener('change', function() {
        const show = this.value === '1';
        document.getElementById('seccion_modificatorio').style.display = show ? 'block' : 'none';
        document.getElementById('folio_modificacion').required = show;
        document.getElementById('descripcion_modificacion').required = show;
    });
    document.getElementById('incluir_dueno_beneficiario').addEventListener('change', function() {
        const show = this.checked;
        document.getElementById('seccion_dueno_beneficiario').style.display = show ? 'block' : 'none';
        document.getElementById('db_tipo_persona').addEventListener('change', toggleDuenoTipoPersona);
        if (show) toggleDuenoTipoPersona();
    });
    document.getElementById('db_tipo_persona').addEventListener('change', toggleDuenoTipoPersona);
    document.getElementById('persona_moral_aviso').addEventListener('change', function() {
        const wrap = document.getElementById('wrap_tipo_persona_admon');
        wrap.style.display = this.value === 'SI' ? 'block' : 'none';
    });
    document.getElementById('admon_tipo_persona').addEventListener('change', function() {
        document.querySelectorAll('.admon-pm').forEach(e => e.style.display = this.value === 'persona_moral' ? '' : 'none');
        document.querySelectorAll('.admon-fid').forEach(e => e.style.display = this.value === 'fideicomiso' ? '' : 'none');
    });
    document.getElementById('tipo_actividad').addEventListener('change', toggleTipoActividad);
    toggleTipoActividad();
    document.getElementById('formSPR').addEventListener('submit', guardarAvisoSPR);
    document.getElementById('formSPR').addEventListener('change', function(e) {
        if (e.target.matches('.contraparte-tipo')) toggleContraparteTipo(e.target.closest('.contraparte-item, .cdi-contraparte'));
        if (e.target.matches('.csm-acc-tipo')) toggleCsmAccionistaTipo(e.target.closest('.csm-accionista-item'));
        if (e.target.matches('.ar-out-area') || e.target.matches('.ar-out-activo')) {
            const item = e.target.closest('.ar-out-item');
            if (item) {
                item.querySelector('.ar-out-desc-area').style.display = item.querySelector('.ar-out-area').value === '99' ? 'block' : 'none';
                item.querySelector('.ar-out-desc-activo').style.display = item.querySelector('.ar-out-activo').value === '99' ? 'block' : 'none';
            }
        }
        if (e.target.matches('.inmueble-contrato-tipo')) toggleInmuebleContrato(e.target.closest('.inmueble-item'));
        if (e.target.matches('.datos-fin-activo-virt')) toggleActivoVirtual(e.target.closest('.datos-fin-item'));
    });
});
function toggleTipoActividad() {
    const t = document.getElementById('tipo_actividad').value;
    document.getElementById('cesion_derechos_inmuebles_section').style.display = t === 'cesion_derechos_inmuebles' ? 'block' : 'none';
    document.getElementById('administracion_recursos_section').style.display = t === 'administracion_recursos' ? 'block' : 'none';
    document.getElementById('compra_venta_inmuebles_section').style.display = t === 'compra_venta_inmuebles' ? 'block' : 'none';
    document.getElementById('admin_personas_morales_section').style.display = t === 'administracion_personas_morales' ? 'block' : 'none';
    document.getElementById('constitucion_sociedades_section').style.display = t === 'constitucion_sociedades_mercantiles' ? 'block' : 'none';
    ['cvi_tipo_operacion','cvi_valor_pactado'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.required = t === 'compra_venta_inmuebles';
    });
    ['cdi_figura_cliente','cdi_tipo_cesion'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.required = t === 'cesion_derechos_inmuebles';
    });
    document.getElementById('ar_numero_operaciones').required = t === 'administracion_recursos';
    ['tipo_administracion','tipo_operacion_text','persona_moral_aviso'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.required = t === 'administracion_personas_morales';
    });
    ['csm_tipo_persona_moral','csm_denominacion','csm_giro_mercantil','csm_numero_total_acciones','csm_entidad_federativa','csm_consejo_vigilancia','csm_motivo_constitucion','csm_instrumento_publico','csm_capital_fijo'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.required = t === 'constitucion_sociedades_mercantiles';
    });
}
function toggleContraparteTipo(item) {
    if (!item) return;
    const t = item.querySelector('.contraparte-tipo').value;
    item.querySelector('.contraparte-pf').style.display = t === 'persona_fisica' ? 'block' : 'none';
    item.querySelector('.contraparte-pm').style.display = t === 'persona_moral' ? 'block' : 'none';
    item.querySelector('.contraparte-fid').style.display = t === 'fideicomiso' ? 'block' : 'none';
}
function toggleInmuebleContrato(item) {
    if (!item) return;
    const t = item.querySelector('.inmueble-contrato-tipo').value;
    item.querySelector('.inmueble-instrumento').style.display = t === 'instrumento' ? 'block' : 'none';
    item.querySelector('.inmueble-contrato').style.display = t === 'contrato' ? 'block' : 'none';
}
function toggleActivoVirtual(item) {
    if (!item) return;
    const chk = item.querySelector('.datos-fin-activo-virt');
    item.querySelector('.datos-fin-av').style.display = chk && chk.checked ? 'block' : 'none';
}
function toggleCsmAccionistaTipo(item) {
    if (!item) return;
    const t = item.querySelector('.csm-acc-tipo').value;
    item.querySelector('.csm-acc-pf').style.display = t === 'persona_fisica' ? 'block' : 'none';
    item.querySelector('.csm-acc-pm').style.display = t === 'persona_moral' ? 'block' : 'none';
    item.querySelector('.csm-acc-fid').style.display = t === 'fideicomiso' ? 'block' : 'none';
}
let csmAccionistaIdx = 1;
function agregarAccionistaCSM() {
    const tpl = document.querySelector('.csm-accionista-item').cloneNode(true);
    tpl.classList.add('csm-accionista-item');
    tpl.dataset.idx = csmAccionistaIdx++;
    tpl.querySelectorAll('input').forEach(el => { el.value = ''; });
    tpl.querySelector('.csm-acc-cargo').value = '4';
    tpl.querySelector('.csm-acc-tipo').value = 'persona_fisica';
    document.getElementById('csm_accionistas_container').appendChild(tpl);
    toggleCsmAccionistaTipo(tpl);
}
let contraparteIdx = 1, inmuebleIdx = 1, datosFinIdx = 1;
function agregarContraparte() {
    const tpl = document.querySelector('.contraparte-item').cloneNode(true);
    tpl.classList.remove('contraparte-item'); tpl.classList.add('contraparte-item');
    tpl.dataset.idx = contraparteIdx++;
    tpl.querySelectorAll('input, select').forEach(el => { el.value = ''; el.name = ''; el.id = ''; });
    document.getElementById('contrapartes_container').appendChild(tpl);
    toggleContraparteTipo(tpl);
}
let cdiContraparteIdx = 1, cdiInmuebleIdx = 1;
let arInmIdx = 1, arBancoIdx = 1, arOutIdx = 1, arOtrosIdx = 1;
function agregarARInmueble() {
    const tpl = document.querySelector('.ar-inmueble-item').cloneNode(true);
    tpl.dataset.idx = arInmIdx++;
    tpl.querySelectorAll('input, select').forEach(el => { el.value = ''; });
    document.getElementById('ar_inmuebles_container').appendChild(tpl);
}
function agregarARBanco() {
    const tpl = document.querySelector('.ar-banco-item').cloneNode(true);
    tpl.dataset.idx = arBancoIdx++;
    tpl.querySelectorAll('input, select').forEach(el => { el.value = ''; });
    document.getElementById('ar_bancos_container').appendChild(tpl);
}
function agregarAROutsourcing() {
    const tpl = document.querySelector('.ar-out-item').cloneNode(true);
    tpl.dataset.idx = arOutIdx++;
    tpl.querySelectorAll('input, select').forEach(el => { el.value = ''; });
    document.getElementById('ar_outsourcing_container').appendChild(tpl);
}
function agregarAROtros() {
    const tpl = document.querySelector('.ar-otros-item').cloneNode(true);
    tpl.dataset.idx = arOtrosIdx++;
    tpl.querySelectorAll('textarea').forEach(el => { el.value = ''; });
    document.getElementById('ar_otros_container').appendChild(tpl);
}
function agregarContraparteCesion() {
    const tpl = document.querySelector('.cdi-contraparte').cloneNode(true);
    tpl.classList.add('contraparte-item', 'cdi-contraparte');
    tpl.dataset.idx = cdiContraparteIdx++;
    tpl.querySelectorAll('input, select').forEach(el => { el.value = ''; el.name = ''; el.id = ''; });
    document.getElementById('cdi_contrapartes_container').appendChild(tpl);
    toggleContraparteTipo(tpl);
}
function agregarInmuebleCesion() {
    const tpl = document.querySelector('.cdi-inmueble').cloneNode(true);
    tpl.classList.add('inmueble-item', 'cdi-inmueble');
    tpl.dataset.idx = cdiInmuebleIdx++;
    tpl.querySelectorAll('input, select').forEach(el => { el.value = ''; el.name = ''; el.id = ''; });
    document.getElementById('cdi_inmuebles_container').appendChild(tpl);
}
function agregarInmueble() {
    const tpl = document.querySelector('#inmuebles_container .inmueble-item:not(.cdi-inmueble)').cloneNode(true);
    tpl.dataset.idx = inmuebleIdx++;
    tpl.querySelectorAll('input, select').forEach(el => { el.value = ''; el.name = ''; el.id = ''; });
    document.getElementById('inmuebles_container').appendChild(tpl);
    toggleInmuebleContrato(tpl);
}
function agregarDatosFinancieros() {
    const tpl = document.querySelector('.datos-fin-item').cloneNode(true);
    tpl.dataset.idx = datosFinIdx++;
    tpl.querySelectorAll('input, select').forEach(el => { el.value = ''; el.checked = false; });
    tpl.querySelector('.datos-fin-av').style.display = 'none';
    document.getElementById('datos_fin_container').appendChild(tpl);
}

function toggleTipoPersona() {
    const tipo = document.getElementById('tipo_persona').value;
    document.querySelectorAll('.persona-section').forEach(s => s.classList.remove('active'));
    document.getElementById('persona_fisica_block').classList.toggle('active', tipo === 'persona_fisica');
    document.getElementById('persona_moral_block').classList.toggle('active', tipo === 'persona_moral');
    document.getElementById('fideicomiso_block').classList.toggle('active', tipo === 'fideicomiso');
}
function toggleDuenoTipoPersona() {
    const tipo = document.getElementById('db_tipo_persona').value;
    document.querySelectorAll('.db-persona-section').forEach(s => { s.classList.remove('active'); s.style.display = 'none'; });
    const b = document.getElementById('db_persona_fisica_block');
    const m = document.getElementById('db_persona_moral_block');
    const f = document.getElementById('db_fideicomiso_block');
    if (tipo === 'persona_fisica') { b.classList.add('active'); b.style.display = 'block'; }
    else if (tipo === 'persona_moral') { m.classList.add('active'); m.style.display = 'block'; }
    else { f.classList.add('active'); f.style.display = 'block'; }
}

function cargarClientes() {
    fetch('api/get_clients.php').then(r => r.json()).then(data => {
        const sel = document.getElementById('id_cliente');
        sel.innerHTML = '<option value="">-- Seleccione Cliente --</option>';
        (Array.isArray(data) ? data : []).forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id_cliente;
            opt.textContent = c.nombre_cliente || 'Cliente #' + c.id_cliente;
            sel.appendChild(opt);
        });
    }).catch(e => console.error('Error clientes:', e));
}
function cargarKYC() {
    const id = document.getElementById('id_cliente').value;
    const preview = document.getElementById('kyc-preview');
    if (!id) { preview.style.display = 'none'; kycDataCache = null; return; }
    fetch('api/get_cliente_kyc_pld.php?id=' + id).then(r => r.json()).then(res => {
        if (res.status !== 'success') { preview.style.display = 'none'; kycDataCache = null; return; }
        const k = res.kyc;
        kycDataCache = k;
        document.getElementById('kyc-rfc').textContent = k.rfc || '-';
        document.getElementById('kyc-nombre').textContent = k.denominacion_razon || k.razon_social || k.nombre || '-';
        document.getElementById('kyc-pais').textContent = k.pais_nacionalidad || '-';
        preview.style.display = 'block';
    }).catch(e => { preview.style.display = 'none'; kycDataCache = null; });
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
        document.getElementById('pf_actividad_economica').value = (/^\d{7}$/.test(k.actividad_economica || '')) ? k.actividad_economica : '1000000';
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

function leerPersona() {
    const tipo = document.getElementById('tipo_persona').value;
    if (tipo === 'persona_fisica') {
        return { persona_fisica: {
            nombre: v('pf_nombre'), apellido_paterno: v('pf_apellido_paterno'), apellido_materno: v('pf_apellido_materno'),
            fecha_nacimiento: (v('pf_fecha_nacimiento') || '').replace(/-/g, ''), rfc: v('pf_rfc'), curp: v('pf_curp'),
            pais_nacionalidad: v('pf_pais_nacionalidad'), actividad_economica: v('pf_actividad_economica')
        }};
    } else if (tipo === 'persona_moral') {
        return { persona_moral: {
            denominacion_razon: v('pm_denominacion'), rfc: v('pm_rfc'), fecha_constitucion: (v('pm_fecha_constitucion') || '').replace(/-/g, ''),
            pais_nacionalidad: v('pm_pais_nacionalidad'), giro_mercantil: v('pm_giro_mercantil'),
            representante_apoderado: {
                nombre: v('pm_rep_nombre'), apellido_paterno: v('pm_rep_apellido_paterno'), apellido_materno: v('pm_rep_apellido_materno'),
                fecha_nacimiento: (v('pm_rep_fecha_nacimiento') || '').replace(/-/g, ''), rfc: v('pm_rep_rfc'), curp: v('pm_rep_curp')
            }
        }};
    } else {
        return { fideicomiso: {
            denominacion_razon: v('fid_denominacion'), rfc: v('fid_rfc'), identificador_fideicomiso: v('fid_identificador'),
            apoderado_delegado: {
                nombre: v('fid_apod_nombre'), apellido_paterno: v('fid_apod_apellido_paterno'), apellido_materno: v('fid_apod_apellido_materno'),
                fecha_nacimiento: (v('fid_apod_fecha_nacimiento') || '').replace(/-/g, ''), rfc: v('fid_apod_rfc'), curp: v('fid_apod_curp')
            }
        }};
    }
}
function leerDomicilio() {
    const t = document.getElementById('tipo_domicilio').value;
    if (t === 'nacional') return { nacional: { colonia: v('dom_colonia'), calle: v('dom_calle'), numero_exterior: v('dom_numero_exterior'), numero_interior: v('dom_numero_interior') || undefined, codigo_postal: v('dom_codigo_postal') }};
    return { extranjero: { pais: v('dom_pais'), estado_provincia: v('dom_estado'), ciudad_poblacion: v('dom_ciudad'), colonia: v('dom_ext_colonia'), calle: v('dom_ext_calle'), numero_exterior: v('dom_ext_numero'), numero_interior: v('dom_ext_numero_int') || undefined, codigo_postal: v('dom_ext_cp') }};
}
function leerDuenoBeneficiarioSimple() {
    const tipo = document.getElementById('db_tipo_persona').value;
    if (tipo === 'persona_fisica') return { persona_fisica: { nombre: v('db_pf_nombre'), apellido_paterno: v('db_pf_apellido_paterno'), apellido_materno: v('db_pf_apellido_materno'), fecha_nacimiento: (v('db_pf_fecha_nacimiento') || '').replace(/-/g, ''), rfc: v('db_pf_rfc'), curp: v('db_pf_curp'), pais_nacionalidad: v('db_pf_pais_nacionalidad') }};
    if (tipo === 'persona_moral') return { persona_moral: { denominacion_razon: v('db_pm_denominacion'), fecha_constitucion: (v('db_pm_fecha_constitucion') || '').replace(/-/g, ''), rfc: v('db_pm_rfc'), pais_nacionalidad: v('db_pm_pais_nacionalidad') }};
    return { fideicomiso: { denominacion_razon: v('db_fid_denominacion'), rfc: v('db_fid_rfc'), identificador_fideicomiso: v('db_fid_identificador') }};
}

function leerContrapartes(containerId = 'contrapartes_container') {
    const out = [];
    const container = document.getElementById(containerId);
    if (!container) return out;
    container.querySelectorAll('.contraparte-item, .cdi-contraparte').forEach(item => {
        const t = item.querySelector('.contraparte-tipo').value;
        let tp = {};
        if (t === 'persona_fisica') {
            const pf = item.querySelectorAll('.contraparte-pf input, .contraparte-pf select');
            tp = { persona_fisica: {
                nombre: item.querySelector('.contraparte-pf-nombre')?.value?.trim() || '',
                apellido_paterno: item.querySelector('.contraparte-pf-ap')?.value?.trim() || '',
                apellido_materno: item.querySelector('.contraparte-pf-am')?.value?.trim() || '',
                fecha_nacimiento: (item.querySelector('.contraparte-pf-fnac')?.value || '').replace(/-/g, ''),
                rfc: item.querySelector('.contraparte-pf-rfc')?.value?.trim() || '',
                curp: item.querySelector('.contraparte-pf-curp')?.value?.trim() || '',
                pais_nacionalidad: item.querySelector('.contraparte-pf-pais')?.value || ''
            }};
        } else if (t === 'persona_moral') {
            tp = { persona_moral: {
                denominacion_razon: item.querySelector('.contraparte-pm-denom')?.value?.trim() || '',
                fecha_constitucion: (item.querySelector('.contraparte-pm-fconst')?.value || '').replace(/-/g, ''),
                rfc: item.querySelector('.contraparte-pm-rfc')?.value?.trim() || '',
                pais_nacionalidad: item.querySelector('.contraparte-pm-pais')?.value || ''
            }};
        } else {
            tp = { fideicomiso: {
                denominacion_razon: item.querySelector('.contraparte-fid-denom')?.value?.trim() || '',
                rfc: item.querySelector('.contraparte-fid-rfc')?.value?.trim() || '',
                identificador_fideicomiso: item.querySelector('.contraparte-fid-id')?.value?.trim() || ''
            }};
        }
        if (Object.values(tp).some(o => Object.values(o).some(x => x))) out.push({ tipo_persona: tp });
    });
    return out;
}
function leerAccionistasCSM() {
    const out = [];
    document.querySelectorAll('#csm_accionistas_container .csm-accionista-item').forEach(item => {
        const cargo = item.querySelector('.csm-acc-cargo')?.value || '4';
        const tipo = item.querySelector('.csm-acc-tipo')?.value || 'persona_fisica';
        const numAcc = item.querySelector('.csm-acc-num')?.value || '0';
        let tp = {};
        if (tipo === 'persona_fisica') {
            const nom = item.querySelector('.csm-acc-pf-nombre')?.value?.trim() || '';
            if (!nom) return;
            tp = { persona_fisica: {
                nombre: nom,
                apellido_paterno: item.querySelector('.csm-acc-pf-ap')?.value?.trim() || '',
                apellido_materno: item.querySelector('.csm-acc-pf-am')?.value?.trim() || '',
                fecha_nacimiento: (item.querySelector('.csm-acc-pf-fnac')?.value || '').replace(/-/g, ''),
                rfc: item.querySelector('.csm-acc-pf-rfc')?.value?.trim() || '',
                curp: item.querySelector('.csm-acc-pf-curp')?.value?.trim() || '',
                pais_nacionalidad: item.querySelector('.csm-acc-pf-pais')?.value || ''
            }};
        } else if (tipo === 'persona_moral') {
            const denom = item.querySelector('.csm-acc-pm-denom')?.value?.trim() || '';
            if (!denom) return;
            tp = { persona_moral: {
                denominacion_razon: denom,
                fecha_constitucion: (item.querySelector('.csm-acc-pm-fconst')?.value || '').replace(/-/g, ''),
                rfc: item.querySelector('.csm-acc-pm-rfc')?.value?.trim() || '',
                pais_nacionalidad: item.querySelector('.csm-acc-pm-pais')?.value || ''
            }};
        } else {
            const denom = item.querySelector('.csm-acc-fid-denom')?.value?.trim() || '';
            if (!denom) return;
            tp = { fideicomiso: {
                denominacion_razon: denom,
                rfc: item.querySelector('.csm-acc-fid-rfc')?.value?.trim() || '',
                identificador_fideicomiso: item.querySelector('.csm-acc-fid-id')?.value?.trim() || ''
            }};
        }
        out.push({ cargo_accionista: cargo, tipo_persona: tp, numero_acciones: numAcc || '0' });
    });
    return out;
}
function leerAdministracionRecursos() {
    const tipoActivo = [];
    document.querySelectorAll('#ar_inmuebles_container .ar-inmueble-item').forEach(item => {
        const tipo = item.querySelector('.ar-inm-tipo')?.value || '';
        const valor = item.querySelector('.ar-inm-valorref')?.value || '0';
        const colonia = item.querySelector('.ar-inm-colonia')?.value?.trim() || '';
        if (!tipo && !colonia) return;
        tipoActivo.push({ activo_inmobiliario: {
            tipo_inmueble: tipo || '1',
            valor_referencia: valor,
            colonia, calle: item.querySelector('.ar-inm-calle')?.value?.trim() || '',
            numero_exterior: item.querySelector('.ar-inm-numext')?.value?.trim() || '',
            numero_interior: item.querySelector('.ar-inm-numint')?.value?.trim() || '',
            codigo_postal: item.querySelector('.ar-inm-cp')?.value?.trim() || '',
            folio_real: item.querySelector('.ar-inm-folio')?.value?.trim() || ''
        }});
    });
    document.querySelectorAll('#ar_bancos_container .ar-banco-item').forEach(item => {
        const nombre = item.querySelector('.ar-banco-nombre')?.value?.trim() || '';
        const cuenta = item.querySelector('.ar-banco-cuenta')?.value?.trim() || '';
        if (!nombre && !cuenta) return;
        tipoActivo.push({ activo_banco: {
            estatus_manejo: item.querySelector('.ar-banco-estatus')?.value || '1',
            clave_tipo_institucion: item.querySelector('.ar-banco-tipoinst')?.value || '40',
            nombre_institucion: nombre,
            numero_cuenta: cuenta
        }});
    });
    document.querySelectorAll('#ar_outsourcing_container .ar-out-item').forEach(item => {
        const area = item.querySelector('.ar-out-area')?.value || '';
        const activo = item.querySelector('.ar-out-activo')?.value || '';
        const emp = item.querySelector('.ar-out-empleados')?.value || '0';
        if (!area && !activo) return;
        const o = { area_servicio: { tipo_area_servicio: area } };
        if (area === '99') o.area_servicio.descripcion_otro_area_servicio = item.querySelector('.ar-out-desc-area-txt')?.value?.trim() || '';
        o.activo_administrado = { tipo_activo_administrado: activo };
        if (activo === '99') o.activo_administrado.descripcion_otro_activo_administrado = item.querySelector('.ar-out-desc-activo-txt')?.value?.trim() || '';
        o.numero_empleados = emp || '0';
        tipoActivo.push({ activo_outsourcing: o });
    });
    document.querySelectorAll('#ar_otros_container .ar-otros-item').forEach(item => {
        const desc = item.querySelector('.ar-otros-desc')?.value?.trim() || '';
        if (!desc) return;
        tipoActivo.push({ activo_otros: { descripcion_activo_administrado: desc } });
    });
    return { tipo_activo };
}
function leerInmueblesCesion() {
    const out = [];
    document.querySelectorAll('#cdi_inmuebles_container .cdi-inmueble, #cdi_inmuebles_container .inmueble-item').forEach(item => {
        const valorRef = item.querySelector('.inmueble-valorref');
        out.push({
            tipo_inmueble: item.querySelector('.inmueble-tipo')?.value || '1',
            valor_referencia: valorRef?.value || '0.00',
            colonia: item.querySelector('.inmueble-colonia')?.value?.trim() || '',
            calle: item.querySelector('.inmueble-calle')?.value?.trim() || '',
            numero_exterior: item.querySelector('.inmueble-numext')?.value?.trim() || '',
            numero_interior: item.querySelector('.inmueble-numint')?.value?.trim() || '',
            codigo_postal: item.querySelector('.inmueble-cp')?.value?.trim() || '',
            dimension_terreno: item.querySelector('.inmueble-dimterr')?.value || '0.00',
            dimension_construido: item.querySelector('.inmueble-dimconst')?.value || '0.00',
            folio_real: item.querySelector('.inmueble-folio')?.value?.trim() || ''
        });
    });
    return out;
}
function leerInmuebles() {
    const out = [];
    document.querySelectorAll('#inmuebles_container .inmueble-item:not(.cdi-inmueble)').forEach(item => {
        const tipoContrato = item.querySelector('.inmueble-contrato-tipo')?.value;
        let contrato = {};
        if (tipoContrato === 'instrumento') {
            contrato = { datos_instrumento_publico: {
                numero_instrumento_publico: item.querySelector('.inmueble-numinstr')?.value?.trim() || '',
                fecha_instrumento_publico: (item.querySelector('.inmueble-fechainstr')?.value || '').replace(/-/g, ''),
                notario_instrumento_publico: item.querySelector('.inmueble-notario')?.value?.trim() || '',
                entidad_instrumento_publico: item.querySelector('.inmueble-entidad')?.value || '',
                valor_referencia: item.querySelector('.inmueble-valorref')?.value || '0.00'
            }};
        } else {
            contrato = { contrato: {
                fecha_contrato: (item.querySelector('.inmueble-fechacto')?.value || '').replace(/-/g, ''),
                valor_referencia: item.querySelector('.inmueble-valorrefcto')?.value || '0.00'
            }};
        }
        out.push({
            tipo_inmueble: item.querySelector('.inmueble-tipo')?.value || '1',
            colonia: item.querySelector('.inmueble-colonia')?.value?.trim() || '',
            calle: item.querySelector('.inmueble-calle')?.value?.trim() || '',
            numero_exterior: item.querySelector('.inmueble-numext')?.value?.trim() || '',
            numero_interior: item.querySelector('.inmueble-numint')?.value?.trim() || '',
            codigo_postal: item.querySelector('.inmueble-cp')?.value?.trim() || '',
            dimension_terreno: item.querySelector('.inmueble-dimterr')?.value || '0.00',
            dimension_construido: item.querySelector('.inmueble-dimconst')?.value || '0.00',
            folio_real: item.querySelector('.inmueble-folio')?.value?.trim() || '',
            contrato_instrumento_publico: contrato
        });
    });
    return out;
}
function leerDatosFinancieros() {
    const out = [];
    document.querySelectorAll('.datos-fin-item').forEach(item => {
        const instr = item.querySelector('.datos-fin-instr')?.value || '';
        const monto = parseFloat(item.querySelector('.datos-fin-monto')?.value || 0);
        if (!instr || isNaN(monto)) return;
        const df = {
            fecha_pago: (item.querySelector('.datos-fin-fechapago')?.value || '').replace(/-/g, '') || undefined,
            instrumento_monetario: instr,
            moneda: item.querySelector('.datos-fin-moneda')?.value || '',
            monto_operacion: monto.toFixed(2)
        };
        const chk = item.querySelector('.datos-fin-activo-virt');
        if (chk && chk.checked) {
            const avTipo = item.querySelector('.datos-fin-av-tipo')?.value?.trim();
            const avCant = item.querySelector('.datos-fin-av-cant')?.value?.trim();
            if (avTipo && avCant) {
                df.activo_virtual = { tipo_activo_virtual: avTipo, cantidad_activo_virtual: avCant };
                const avDesc = item.querySelector('.datos-fin-av-desc')?.value?.trim();
                if (avDesc) df.activo_virtual.descripcion_activo_virtual = avDesc;
            }
        }
        out.push(df);
    });
    return out;
}

function guardarAvisoSPR(e) {
    e.preventDefault();
    const tipoAct = v('tipo_actividad');
    if (!v('id_cliente')) { Swal.fire('Error', 'Seleccione un cliente', 'error'); return; }
    if (!/^\d{6}$/.test(v('mes_reportado'))) { Swal.fire('Error', 'Mes reportado: 6 dígitos AAAAMM', 'error'); return; }
    if (!v('clave_sujeto_obligado')) { Swal.fire('Error', 'Clave Sujeto Obligado requerida', 'error'); return; }
    if (!v('referencia_aviso')) { Swal.fire('Error', 'Referencia del aviso requerida', 'error'); return; }

    if (tipoAct === 'compra_venta_inmuebles') {
        if (!v('cvi_valor_pactado') || parseFloat(v('cvi_valor_pactado')) < 0) { Swal.fire('Error', 'Valor pactado requerido', 'error'); return; }
        const contrapartes = leerContrapartes('contrapartes_container');
        if (contrapartes.length === 0) { Swal.fire('Error', 'Agregue al menos una contraparte', 'error'); return; }
        const inmuebles = leerInmuebles();
        if (inmuebles.length === 0) { Swal.fire('Error', 'Agregue al menos un inmueble', 'error'); return; }
    } else if (tipoAct === 'cesion_derechos_inmuebles') {
        const contrapartesCdi = leerContrapartes('cdi_contrapartes_container');
        if (contrapartesCdi.length === 0) { Swal.fire('Error', 'Agregue al menos una contraparte', 'error'); return; }
        const inmueblesCdi = leerInmueblesCesion();
        if (inmueblesCdi.length === 0) { Swal.fire('Error', 'Agregue al menos un inmueble', 'error'); return; }
    } else if (tipoAct === 'administracion_recursos') {
        const arData = leerAdministracionRecursos();
        if (arData.tipo_activo.length === 0) { Swal.fire('Error', 'Agregue al menos un tipo de activo (inmueble, banco, outsourcing u otro)', 'error'); return; }
        if (!v('ar_numero_operaciones') || !/^\d+$/.test(v('ar_numero_operaciones'))) { Swal.fire('Error', 'Número de operaciones requerido (solo dígitos)', 'error'); return; }
    } else if (tipoAct === 'administracion_personas_morales') {
        if (!v('tipo_administracion') || !v('tipo_operacion_text')) { Swal.fire('Error', 'Complete tipo de administración y operación', 'error'); return; }
    } else if (tipoAct === 'constitucion_sociedades_mercantiles') {
        const accs = leerAccionistasCSM();
        if (accs.length === 0) { Swal.fire('Error', 'Agregue al menos un accionista/socio', 'error'); return; }
        if (!v('csm_denominacion') || !v('csm_giro_mercantil') || !v('csm_instrumento_publico')) { Swal.fire('Error', 'Complete denominación, giro mercantil e instrumento público', 'error'); return; }
    }
    const datosFin = leerDatosFinancieros();
    if (datosFin.length === 0) { Swal.fire('Error', 'Agregue al menos un dato de operación financiera (instrumento y monto)', 'error'); return; }

    const btn = e.target.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Registrando...'; }

    let tipoActividadData = {};
    if (tipoAct === 'compra_venta_inmuebles') {
        tipoActividadData = { compra_venta_inmuebles: {
            tipo_operacion: v('cvi_tipo_operacion') || '2',
            valor_pactado: v('cvi_valor_pactado') || '0.00',
            datos_contraparte: leerContrapartes('contrapartes_container'),
            caracteristicas_inmueble: leerInmuebles()
        }};
    } else if (tipoAct === 'cesion_derechos_inmuebles') {
        tipoActividadData = { cesion_derechos_inmuebles: {
            figura_cliente: v('cdi_figura_cliente') || '2',
            tipo_cesion: v('cdi_tipo_cesion') || '2',
            datos_contraparte: leerContrapartes('cdi_contrapartes_container'),
            caracteristicas_inmueble: leerInmueblesCesion()
        }};
    } else if (tipoAct === 'administracion_recursos') {
        const ar = leerAdministracionRecursos();
        tipoActividadData = { administracion_recursos: {
            tipo_activo: ar.tipo_activo,
            numero_operaciones: v('ar_numero_operaciones') || '0'
        }};
    } else if (tipoAct === 'constitucion_sociedades_mercantiles') {
        tipoActividadData = { constitucion_sociedades_mercantiles: {
            tipo_persona_moral: v('csm_tipo_persona_moral') || '6',
            denominacion_razon: v('csm_denominacion'),
            giro_mercantil: v('csm_giro_mercantil') || '0000000',
            folio_mercantil: v('csm_folio_mercantil') || undefined,
            numero_total_acciones: v('csm_numero_total_acciones') || '0',
            entidad_federativa: v('csm_entidad_federativa') || '14',
            consejo_vigilancia: v('csm_consejo_vigilancia') || 'NO',
            motivo_constitucion: v('csm_motivo_constitucion') || '1',
            instrumento_publico: v('csm_instrumento_publico'),
            datos_accionista: leerAccionistasCSM(),
            capital_social: { capital_fijo: v('csm_capital_fijo') || '0', capital_variable: v('csm_capital_variable') || '0' }
        }};
    } else if (tipoAct === 'administracion_personas_morales') {
        tipoActividadData = { administracion_personas_morales: {
            tipo_administracion: v('tipo_administracion'),
            tipo_operacion: v('tipo_operacion_text'),
            persona_moral_aviso: v('persona_moral_aviso')
        }};
    } else {
        tipoActividadData = {};
    }

    const aviso = {
        referencia_aviso: v('referencia_aviso'),
        prioridad: v('prioridad'),
        alerta: { tipo_alerta: v('tipo_alerta'), descripcion_alerta: v('descripcion_alerta') || undefined },
        persona_aviso: [{
            tipo_persona: leerPersona(),
            tipo_domicilio: leerDomicilio(),
            telefono: { clave_pais: v('tel_clave_pais'), numero_telefono: v('tel_numero'), correo_electronico: v('tel_correo') || undefined }
        }],
        detalle_operaciones: {
            datos_operacion: [{
                fecha_operacion: (v('fecha_operacion') || '').replace(/-/g, ''),
                tipo_actividad: tipoActividadData,
                datos_operacion_financiera: leerDatosFinancieros()
            }]
        }
    };
    if (document.getElementById('incluir_dueno_beneficiario').checked) {
        aviso.dueno_beneficiario = [{ tipo_persona: leerDuenoBeneficiarioSimple() }];
    }
    if (v('es_modificatorio') === '1') aviso.modificatorio = { folio_modificacion: v('folio_modificacion'), descripcion_modificacion: v('descripcion_modificacion') };

    const sujetoObligado = {
        clave_sujeto_obligado: v('clave_sujeto_obligado'),
        ocupacion: { tipo_ocupacion: v('tipo_ocupacion'), descripcion_otra_ocupacion: v('tipo_ocupacion') === '99' ? v('descripcion_otra_ocupacion') : undefined },
        clave_actividad: v('clave_actividad'),
        exento: v('exento')
    };
    if (v('clave_entidad_colegiada')) sujetoObligado.clave_entidad_colegiada = v('clave_entidad_colegiada');

    const payload = {
        id_cliente: parseInt(v('id_cliente')),
        informe: [{ mes_reportado: v('mes_reportado'), sujeto_obligado: sujetoObligado, aviso: [aviso] }]
    };

    fetch('api/registrar_aviso_spr.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Registrar y Generar XML'; }
        if (data.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Aviso SPR registrado' }).then(() => { window.location.href = 'operaciones_pld.php'; });
        } else {
            Swal.fire('Error', data.message || 'Error al registrar', 'error');
        }
    })
    .catch(err => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Registrar y Generar XML'; }
        Swal.fire('Error', 'Error de conexión', 'error');
    });
}
</script>

<?php include 'templates/footer.php'; ?>
