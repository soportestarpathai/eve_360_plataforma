<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/avi_catalogos.php';

requireModuleActive($pdo, 'pld');

if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if (!function_exists('userCanAccessAVI') || !userCanAccessAVI($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_avi');
    exit;
}

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
        $config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $clave_sujeto_obligado = $config['folio_patron_pld'] ?? '';
} catch (Exception $e) { /* fallback vacío */ }

$page_title = 'Aviso AVI - Activos Virtuales';
include 'templates/header.php';
?>
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/operaciones_pld.css">
</head>
<body>
<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="content-wrapper">
    <div class="container" style="max-width: 980px;">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2 class="fw-bold text-primary mb-1"><i class="fa-solid fa-coins me-2"></i>Aviso AVI</h2>
                    <p class="text-muted mb-0">Fracción XVI - Activos Virtuales</p>
                </div>
                <a href="operaciones_pld.php" class="btn btn-outline-primary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Volver
                </a>
            </div>
        </div>

        <div class="alert alert-info border-info">
            <strong>Reglas Fracción XVI:</strong>
            Identificación siempre. Aviso cuando la operación sea <strong>&gt;= 210 UMA</strong> o cuando la contraprestación sea <strong>&gt;= 4 UMA</strong>.
        </div>

        <form id="formAVI" class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="text-primary mb-3"><i class="fa-solid fa-user-check me-2"></i>Cliente / KYC</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente *</label>
                        <select id="id_cliente" class="form-select" required>
                            <option value="">-- Seleccione cliente --</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tipo persona (KYC)</label>
                        <input id="kyc_tipo_persona" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nombre / Razón (KYC)</label>
                        <input id="kyc_nombre" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">RFC (KYC)</label>
                        <input id="kyc_rfc" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">CURP (KYC)</label>
                        <input id="kyc_curp" class="form-control" readonly>
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="text-primary mb-3"><i class="fa-solid fa-file-lines me-2"></i>Informe / Sujeto Obligado</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Mes reportado (AAAAMM) *</label>
                        <input id="mes_reportado" class="form-control" maxlength="6" value="<?= date('Ym') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Clave sujeto obligado *</label>
                        <input id="clave_sujeto_obligado" class="form-control text-uppercase" maxlength="13" value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Clave actividad *</label>
                        <input id="clave_actividad" class="form-control" value="AVI" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Entidad colegiada</label>
                        <input id="clave_entidad_colegiada" class="form-control text-uppercase" maxlength="12">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Exento (Art. 27 Bis)</label>
                        <select id="exento" class="form-select"><?= aviCatalogoOptions('exento', '0') ?></select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Dominio plataforma *</label>
                        <input id="dominio_plataforma" class="form-control text-uppercase" maxlength="100" required placeholder="EJEMPLOCOM">
                        <small class="text-muted">Formato XSD AVI: solo letras, números y guion medio.</small>
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="text-primary mb-3"><i class="fa-solid fa-bell me-2"></i>Aviso</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Referencia aviso *</label>
                        <input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" required placeholder="AVI202603001">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Prioridad *</label>
                        <select id="prioridad" class="form-select" required><?= aviCatalogoOptions('prioridad', '1') ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo alerta *</label>
                        <select id="tipo_alerta" class="form-select" required><?= aviCatalogoOptions('tipo_alerta', '100') ?></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción alerta</label>
                        <textarea id="descripcion_alerta" class="form-control" rows="2" maxlength="3000"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">¿Aviso modificatorio?</label>
                        <select id="es_modificatorio" class="form-select">
                            <option value="0" selected>No</option>
                            <option value="1">Sí</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-none" id="box_folio_modificacion">
                        <label class="form-label">Folio modificación *</label>
                        <input id="folio_modificacion" class="form-control text-uppercase" maxlength="14" placeholder="2026-123456">
                    </div>
                    <div class="col-md-12 d-none" id="box_descripcion_modificacion">
                        <label class="form-label">Descripción modificación *</label>
                        <textarea id="descripcion_modificacion" class="form-control" rows="2" maxlength="3000"></textarea>
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="text-primary mb-3"><i class="fa-solid fa-wallet me-2"></i>Cuenta de Plataforma</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">ID usuario *</label>
                        <input id="id_usuario_plataforma" class="form-control text-uppercase" maxlength="30" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cuenta relacionada *</label>
                        <input id="cuenta_relacionada" class="form-control text-uppercase" maxlength="100" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Moneda cuenta *</label>
                        <select id="moneda_cuenta" class="form-select" required><?= aviCatalogoOptions('moneda', '1') ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CLABE interbancaria</label>
                        <input id="clabe_interbancaria" class="form-control" maxlength="18">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo identificación persona *</label>
                        <select id="doc_tipo_identificacion" class="form-select" required><?= aviCatalogoOptions('tipo_identificacion', '1') ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Número identificación persona *</label>
                        <input id="doc_numero_identificacion" class="form-control text-uppercase" maxlength="30" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Act. económica PF *</label>
                        <select id="actividad_economica_pf" class="form-select"><?= aviCatalogoOptions('actividad_economica', '4330100') ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Giro mercantil PM *</label>
                        <select id="giro_mercantil_pm" class="form-select"><?= aviCatalogoOptions('giro_mercantil', '1100001') ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Identificador fideicomiso</label>
                        <input id="identificador_fideicomiso" class="form-control text-uppercase" maxlength="40">
                    </div>
                </div>

                <div class="mt-3 p-3 border rounded bg-light">
                    <h6 class="text-primary mb-3">Representante / Apoderado (obligatorio para PM/Fideicomiso)</h6>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Nombre</label><input id="rep_nombre" class="form-control text-uppercase" maxlength="200"></div>
                        <div class="col-md-4"><label class="form-label">Apellido paterno</label><input id="rep_apellido_paterno" class="form-control text-uppercase" maxlength="200"></div>
                        <div class="col-md-4"><label class="form-label">Apellido materno</label><input id="rep_apellido_materno" class="form-control text-uppercase" maxlength="200"></div>
                        <div class="col-md-3"><label class="form-label">Fecha nacimiento</label><input id="rep_fecha_nacimiento" type="date" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">RFC</label><input id="rep_rfc" class="form-control text-uppercase" maxlength="13"></div>
                        <div class="col-md-3"><label class="form-label">CURP</label><input id="rep_curp" class="form-control text-uppercase" maxlength="18"></div>
                        <div class="col-md-2"><label class="form-label">Tipo ID</label><select id="rep_doc_tipo_identificacion" class="form-select"><?= aviCatalogoOptions('tipo_identificacion', '1') ?></select></div>
                        <div class="col-md-2"><label class="form-label">Número ID</label><input id="rep_doc_numero_identificacion" class="form-control text-uppercase" maxlength="30"></div>
                    </div>
                </div>

                <div class="mt-3 p-3 border rounded bg-light">
                    <h6 class="text-primary mb-3">Domicilio y Contacto (PF/PM)</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tipo domicilio</label>
                            <select id="tipo_domicilio_persona" class="form-select">
                                <option value="nacional" selected>Nacional</option>
                                <option value="extranjero">Extranjero</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-1" id="domicilio_nacional_box">
                        <div class="col-md-4"><label class="form-label">Colonia</label><input id="dom_n_colonia" class="form-control text-uppercase" maxlength="100"></div>
                        <div class="col-md-4"><label class="form-label">Calle</label><input id="dom_n_calle" class="form-control text-uppercase" maxlength="100"></div>
                        <div class="col-md-2"><label class="form-label">No. exterior</label><input id="dom_n_num_ext" class="form-control text-uppercase" maxlength="56"></div>
                        <div class="col-md-2"><label class="form-label">No. interior</label><input id="dom_n_num_int" class="form-control text-uppercase" maxlength="40"></div>
                        <div class="col-md-3"><label class="form-label">C.P.</label><input id="dom_n_cp" class="form-control" maxlength="5"></div>
                    </div>
                    <div class="row g-3 mt-1 d-none" id="domicilio_extranjero_box">
                        <div class="col-md-2"><label class="form-label">País</label><input id="dom_e_pais" class="form-control text-uppercase" maxlength="2" placeholder="US"></div>
                        <div class="col-md-4"><label class="form-label">Estado/Provincia</label><input id="dom_e_estado" class="form-control text-uppercase" maxlength="100"></div>
                        <div class="col-md-4"><label class="form-label">Ciudad/Población</label><input id="dom_e_ciudad" class="form-control text-uppercase" maxlength="100"></div>
                        <div class="col-md-4"><label class="form-label">Colonia</label><input id="dom_e_colonia" class="form-control text-uppercase" maxlength="100"></div>
                        <div class="col-md-4"><label class="form-label">Calle</label><input id="dom_e_calle" class="form-control text-uppercase" maxlength="100"></div>
                        <div class="col-md-2"><label class="form-label">No. exterior</label><input id="dom_e_num_ext" class="form-control text-uppercase" maxlength="56"></div>
                        <div class="col-md-2"><label class="form-label">No. interior</label><input id="dom_e_num_int" class="form-control text-uppercase" maxlength="40"></div>
                        <div class="col-md-3"><label class="form-label">C.P.</label><input id="dom_e_cp" class="form-control text-uppercase" maxlength="12"></div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-2"><label class="form-label">Clave país tel.</label><input id="tel_clave_pais" class="form-control text-uppercase" maxlength="2" value="MX"></div>
                        <div class="col-md-4"><label class="form-label">Número teléfono</label><input id="tel_numero" class="form-control" maxlength="12" placeholder="10 o 12 dígitos"></div>
                        <div class="col-md-6"><label class="form-label">Correo electrónico</label><input id="tel_correo" class="form-control text-uppercase" maxlength="60"></div>
                    </div>
                </div>

                <div class="mt-3 p-3 border rounded bg-light">
                    <h6 class="text-primary mb-3">Dueño Beneficiario (opcional)</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">¿Agregar?</label>
                            <select id="db_habilitado" class="form-select">
                                <option value="0" selected>No</option>
                                <option value="1">Sí</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-none" id="db_tipo_box">
                            <label class="form-label">Tipo persona</label>
                            <select id="db_tipo_persona" class="form-select">
                                <option value="fisica" selected>Física</option>
                                <option value="moral">Moral</option>
                                <option value="fideicomiso">Fideicomiso</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3 mt-1 d-none" id="db_fisica_box">
                        <div class="col-md-4"><label class="form-label">Nombre</label><input id="db_f_nombre" class="form-control text-uppercase" maxlength="200"></div>
                        <div class="col-md-4"><label class="form-label">Apellido paterno</label><input id="db_f_ap_paterno" class="form-control text-uppercase" maxlength="200"></div>
                        <div class="col-md-4"><label class="form-label">Apellido materno</label><input id="db_f_ap_materno" class="form-control text-uppercase" maxlength="200"></div>
                        <div class="col-md-3"><label class="form-label">Fecha nac.</label><input id="db_f_fecha" type="date" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">RFC</label><input id="db_f_rfc" class="form-control text-uppercase" maxlength="13"></div>
                        <div class="col-md-3"><label class="form-label">CURP</label><input id="db_f_curp" class="form-control text-uppercase" maxlength="18"></div>
                        <div class="col-md-3"><label class="form-label">País</label><input id="db_f_pais" class="form-control text-uppercase" maxlength="2" value="MX"></div>
                    </div>
                    <div class="row g-3 mt-1 d-none" id="db_moral_box">
                        <div class="col-md-6"><label class="form-label">Denominación/Razón</label><input id="db_m_razon" class="form-control text-uppercase" maxlength="254"></div>
                        <div class="col-md-3"><label class="form-label">Fecha const.</label><input id="db_m_fecha" type="date" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">RFC</label><input id="db_m_rfc" class="form-control text-uppercase" maxlength="12"></div>
                        <div class="col-md-3"><label class="form-label">País</label><input id="db_m_pais" class="form-control text-uppercase" maxlength="2" value="MX"></div>
                    </div>
                    <div class="row g-3 mt-1 d-none" id="db_fide_box">
                        <div class="col-md-6"><label class="form-label">Denominación/Razón</label><input id="db_t_razon" class="form-control text-uppercase" maxlength="254"></div>
                        <div class="col-md-3"><label class="form-label">RFC</label><input id="db_t_rfc" class="form-control text-uppercase" maxlength="12"></div>
                        <div class="col-md-3"><label class="form-label">Identificador fideicomiso</label><input id="db_t_ident" class="form-control text-uppercase" maxlength="40"></div>
                    </div>
                    <div class="row g-3 mt-2 d-none" id="db_actions_box">
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="button" id="btnAgregarDuenoBeneficiario" class="btn btn-outline-primary">
                                <i class="fa-solid fa-user-plus me-2"></i>Agregar dueño beneficiario
                            </button>
                            <button type="button" id="btnLimpiarDuenosBeneficiarios" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-trash me-2"></i>Limpiar dueños beneficiarios
                            </button>
                        </div>
                    </div>
                    <div class="row g-3 mt-1 d-none" id="db_tabla_box">
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0" id="tablaDuenosBeneficiarios">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Tipo</th>
                                            <th>Resumen</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="4" class="text-muted text-center">Sin dueños beneficiarios capturados</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 p-3 border rounded bg-light" id="fondos-extra-box">
                    <h6 class="text-primary mb-3">Datos ordenante / beneficiario (solo fondos)</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nacionalidad cuenta</label>
                            <select id="fondos_nacionalidad_cuenta" class="form-select">
                                <option value="nacional">Nacional</option>
                                <option value="extranjero">Extranjero</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CLABE destino</label>
                            <input id="clabe_destino" class="form-control" maxlength="18">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Clave institución financiera</label>
                            <select id="clave_institucion_financiera" class="form-select">
                                <?= aviCatalogoOptions('clave_institucion_financiera', '101') ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Número cuenta extranjera</label>
                            <input id="numero_cuenta_extranjera" class="form-control text-uppercase" maxlength="30">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre banco extranjero</label>
                            <input id="nombre_banco_extranjero" class="form-control text-uppercase" maxlength="100">
                        </div>
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="text-primary mb-3"><i class="fa-solid fa-chart-line me-2"></i>Detalle Operación AVI</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo operación *</label>
                        <select id="tipo_operacion_avi" class="form-select" required><?= aviCatalogoOptions('tipo_operacion', 'compra') ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha y hora operación *</label>
                        <input id="fecha_hora_operacion" type="datetime-local" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Monto operación (MXN) *</label>
                        <input id="monto_operacion" type="number" step="0.01" min="0" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Contraprestación servicio (MXN)</label>
                        <input id="monto_contraprestacion_servicio" type="number" step="0.01" min="0" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Moneda operación</label>
                        <select id="moneda_operacion" class="form-select"><?= aviCatalogoOptions('moneda', '1') ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Instrumento monetario</label>
                        <select id="instrumento_monetario" class="form-select"><?= aviCatalogoOptions('instrumento_monetario', '1') ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Activo virtual operado *</label>
                        <select id="activo_virtual_operado" class="form-select" required><?= aviCatalogoOptions('activo_virtual_operado', '1001') ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Descripción activo virtual (si es otro)</label>
                        <input id="descripcion_activo_virtual" class="form-control text-uppercase" maxlength="100">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo cambio MN</label>
                        <input id="tipo_cambio_mn" type="number" step="0.000001" min="0" class="form-control" value="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cantidad activo virtual</label>
                        <input id="cantidad_activo_virtual" type="number" step="0.00000001" min="0" class="form-control" value="1">
                    </div>
                    <div class="col-md-4 d-none" id="box_activo_recibido_tipo">
                        <label class="form-label">Activo virtual recibido (intercambio) *</label>
                        <select id="activo_virtual_operado_recibido" class="form-select"><?= aviCatalogoOptions('activo_virtual_operado', '1001') ?></select>
                    </div>
                    <div class="col-md-4 d-none" id="box_activo_recibido_desc">
                        <label class="form-label">Descripción recibido (si es otro)</label>
                        <input id="descripcion_activo_virtual_recibido" class="form-control text-uppercase" maxlength="100">
                    </div>
                    <div class="col-md-2 d-none" id="box_activo_recibido_tc">
                        <label class="form-label">Tipo cambio recibido</label>
                        <input id="tipo_cambio_mn_recibido" type="number" step="0.000001" min="0" class="form-control" value="1">
                    </div>
                    <div class="col-md-2 d-none" id="box_activo_recibido_cant">
                        <label class="form-label">Cantidad recibida</label>
                        <input id="cantidad_activo_virtual_recibido" type="number" step="0.00000001" min="0" class="form-control" value="1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Hash operación</label>
                        <input id="hash_operacion" class="form-control text-uppercase" maxlength="2000" placeholder="HASH123ABC">
                        <small class="text-muted">Obligatorio para compra, venta, intercambio y transferencias.</small>
                    </div>
                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="button" id="btnAgregarOperacion" class="btn btn-outline-primary">
                            <i class="fa-solid fa-plus me-2"></i>Agregar operación al aviso
                        </button>
                        <button type="button" id="btnLimpiarOperaciones" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-trash me-2"></i>Limpiar operaciones
                        </button>
                    </div>
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle" id="tablaOperacionesAVI">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tipo</th>
                                        <th>Fecha/Hora</th>
                                        <th>Monto MXN</th>
                                        <th>Hash</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="6" class="text-muted text-center">Sin operaciones capturadas</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane me-2"></i>Registrar y Generar XML AVI
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const AVI_CATALOGOS_JS = <?= aviCatalogosJson() ?>;
let kycCache = null;
let operacionesDraft = [];
let duenosBeneficiariosDraft = [];

function v(id) { return (document.getElementById(id)?.value || '').trim(); }
function up(s) { return (s || '').toString().trim().toUpperCase(); }
function asText(value, fallback = '') {
    const x = (value === null || value === undefined) ? '' : String(value);
    return x.trim() || fallback;
}
function stripAccents(s) {
    return (s || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}
function cleanByRegex(s, regex, fallback = '') {
    const t = stripAccents(up(s)).replace(regex, '');
    return t || fallback;
}
function onlyDigits(s) { return (s || '').toString().replace(/\D+/g, ''); }
function ymdFromAnyDate(s) {
    if (!s) return '';
    const d = onlyDigits(s);
    if (d.length >= 8) return d.substring(0, 8);
    return '';
}
function ymdhmFromDatetimeLocal(s) {
    if (!s) return '';
    const d = onlyDigits(s);
    if (d.length >= 12) return d.substring(0, 12) + '00';
    return '';
}
function fmtMonto2(n) {
    const x = Number(n || 0);
    return x.toFixed(2);
}
function fmtCantidad10(n) {
    const x = Number(n || 0);
    return x.toFixed(10).replace(/0+$/, '').replace(/\.$/, '.00');
}
function intOrDefault(raw, fallback) {
    const n = parseInt(String(raw ?? '').trim(), 10);
    return Number.isFinite(n) ? n : fallback;
}
function isRFC(s) { return /^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/.test(up(s)); }
function isCURP(s) { return /^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]{2}$/.test(up(s)); }
function requireTrue(cond, message) { if (!cond) throw new Error(message); }
function validateMesReportado(mes) {
    requireTrue(/^\d{6}$/.test(mes), 'Mes reportado debe tener formato AAAAMM.');
    const minMes = '202004';
    const now = new Date();
    const maxMes = String(now.getFullYear()) + String(now.getMonth() + 1).padStart(2, '0');
    requireTrue(mes >= minMes && mes <= maxMes, `Mes reportado fuera de rango (${minMes}-${maxMes}).`);
}
function normalizeDominio(d) {
    let x = stripAccents(up(d)).replace(/[^A-Z0-9\-]/g, '');
    x = x.replace(/^-+/, '').replace(/-+$/, '');
    requireTrue(x.length >= 2 && x.length <= 100, 'Dominio plataforma inválido.');
    return x;
}

function loadClientes() {
    fetch('api/get_clients.php')
        .then(r => r.json())
        .then(rows => {
            const sel = document.getElementById('id_cliente');
            sel.innerHTML = '<option value="">-- Seleccione cliente --</option>';
            (Array.isArray(rows) ? rows : []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_cliente;
                opt.textContent = `${c.nombre_cliente || 'Sin Nombre'} (${c.rfc || 'N/A'})`;
                sel.appendChild(opt);
            });
        })
        .catch(() => Swal.fire('Error', 'No se pudieron cargar clientes', 'error'));
}

function loadKyc(idCliente) {
    if (!idCliente) return;
    fetch('api/get_cliente_kyc_pld.php?id=' + encodeURIComponent(idCliente))
        .then(r => r.json())
        .then(resp => {
            if (resp.status !== 'success') throw new Error(resp.message || 'Error KYC');
            kycCache = resp.kyc || {};
            document.getElementById('kyc_tipo_persona').value = kycCache.tipo_persona || '';
            document.getElementById('kyc_nombre').value =
                (kycCache.denominacion_razon || '') ||
                [kycCache.nombre, kycCache.apellido_paterno, kycCache.apellido_materno].filter(Boolean).join(' ');
            document.getElementById('kyc_rfc').value = kycCache.rfc || '';
            document.getElementById('kyc_curp').value = kycCache.curp || '';
        })
        .catch(err => Swal.fire('Error', err.message || 'No se pudo cargar KYC', 'error'));
}

function buildTipoPersonaFromKyc() {
    if (!kycCache || !kycCache.id_cliente) {
        throw new Error('Seleccione un cliente y cargue su KYC.');
    }
    const pais = cleanByRegex(kycCache.pais_nacionalidad || 'MX', /[^A-Z]/g, 'MX').substring(0, 2);
    const docNumero = cleanByRegex(v('doc_numero_identificacion'), /[^A-Z0-9 \-]/g, '');
    requireTrue(docNumero.length > 0, 'Número de identificación requerido.');
    const doc = {
        tipo_identificacion: parseInt(v('doc_tipo_identificacion') || '1', 10),
        numero_identificacion: docNumero
    };

    const repNombre = cleanByRegex(v('rep_nombre'), /[^A-ZÑ ]/g, '');
    const repAp = cleanByRegex(v('rep_apellido_paterno'), /[^A-ZÑ ]/g, '');
    const repAm = cleanByRegex(v('rep_apellido_materno'), /[^A-ZÑ ]/g, '');
    const repFecha = ymdFromAnyDate(v('rep_fecha_nacimiento'));
    const repRfc = cleanByRegex(v('rep_rfc'), /[^A-Z0-9Ñ&]/g, '');
    const repCurp = cleanByRegex(v('rep_curp'), /[^A-Z0-9]/g, '');
    const repDocNum = cleanByRegex(v('rep_doc_numero_identificacion'), /[^A-Z0-9 \-]/g, '');
    const repDoc = {
        tipo_identificacion: parseInt(v('rep_doc_tipo_identificacion') || '1', 10),
        numero_identificacion: repDocNum
    };

    requireTrue(repNombre && repAp && repAm, 'Representante/apoderado incompleto.');
    requireTrue(repDocNum.length > 0, 'Documento de identificación del representante/apoderado requerido.');
    requireTrue(repFecha || repRfc || repCurp, 'Representante/apoderado: capture fecha de nacimiento, RFC o CURP.');
    if (repRfc) requireTrue(isRFC(repRfc), 'RFC del representante/apoderado inválido.');
    if (repCurp) requireTrue(isCURP(repCurp), 'CURP del representante/apoderado inválido.');

    const representante = {
        nombre: repNombre,
        apellido_paterno: repAp,
        apellido_materno: repAm,
        fecha_nacimiento: repFecha || undefined,
        rfc: repRfc || undefined,
        curp: repCurp || undefined,
        documento_identificacion: repDoc
    };

    if ((kycCache.es_fisica || 0) > 0) {
        const nombre = cleanByRegex(kycCache.nombre, /[^A-ZÑ ]/g, '');
        const ap = cleanByRegex(kycCache.apellido_paterno, /[^A-ZÑ ]/g, '');
        const am = cleanByRegex(kycCache.apellido_materno, /[^A-ZÑ ]/g, '');
        const fechaNac = ymdFromAnyDate(kycCache.fecha_nacimiento || '');
        const rfc = cleanByRegex(kycCache.rfc || '', /[^A-Z0-9Ñ&]/g, '');
        const curp = cleanByRegex(kycCache.curp || '', /[^A-Z0-9]/g, '');
        const actEcon = cleanByRegex(v('actividad_economica_pf'), /[^0-9]/g, '');
        requireTrue(nombre && ap && am, 'KYC persona física incompleto.');
        requireTrue(fechaNac || rfc || curp, 'Persona física: capture fecha de nacimiento, RFC o CURP en KYC.');
        requireTrue(actEcon.length === 7, 'Actividad económica de persona física inválida.');
        if (rfc) requireTrue(isRFC(rfc), 'RFC de persona física inválido.');
        if (curp) requireTrue(isCURP(curp), 'CURP de persona física inválido.');
        return {
            persona_fisica: {
                nombre: nombre,
                apellido_paterno: ap,
                apellido_materno: am,
                fecha_nacimiento: fechaNac || undefined,
                rfc: rfc || undefined,
                curp: curp || undefined,
                pais_nacionalidad: pais,
                actividad_economica: actEcon,
                documento_identificacion: doc
            }
        };
    }
    if ((kycCache.es_moral || 0) > 0) {
        const razon = cleanByRegex(kycCache.razon_social || kycCache.denominacion_razon, /[^A-ZÑ0-9#\-\.&,_@' ]/g, '');
        const fechaConst = ymdFromAnyDate(kycCache.fecha_constitucion || '');
        const rfc = cleanByRegex(kycCache.rfc || '', /[^A-Z0-9Ñ&]/g, '');
        const giro = cleanByRegex(v('giro_mercantil_pm'), /[^0-9]/g, '');
        requireTrue(razon.length > 0, 'KYC persona moral incompleto.');
        requireTrue(fechaConst || rfc, 'Persona moral: capture fecha de constitución o RFC en KYC.');
        requireTrue(giro.length === 7, 'Giro mercantil de persona moral inválido.');
        if (rfc) requireTrue(isRFC(rfc), 'RFC de persona moral inválido.');
        return {
            persona_moral: {
                denominacion_razon: razon,
                fecha_constitucion: fechaConst || undefined,
                rfc: rfc || undefined,
                pais_nacionalidad: pais,
                giro_mercantil: giro,
                representante_apoderado: representante
            }
        };
    }
    const razonFid = cleanByRegex(kycCache.denominacion_razon || kycCache.razon_social, /[^A-ZÑ0-9#\-\.&,_@' ]/g, '');
    const rfcFid = cleanByRegex(kycCache.rfc || '', /[^A-Z0-9Ñ&]/g, '');
    const identFid = cleanByRegex(v('identificador_fideicomiso'), /[^A-ZÑ0-9#\-\.&,_@' ]/g, '');
    requireTrue(razonFid.length > 0, 'KYC fideicomiso incompleto.');
    requireTrue(rfcFid || identFid, 'Fideicomiso: capture RFC o identificador.');
    if (rfcFid) requireTrue(isRFC(rfcFid), 'RFC de fideicomiso inválido.');
    return {
        fideicomiso: {
            denominacion_razon: razonFid,
            rfc: rfcFid || undefined,
            identificador_fideicomiso: identFid || undefined,
            apoderado_delegado: representante
        }
    };
}

function buildActivoVirtualNode(suffix = '') {
    const activoOperado = parseInt(v(`activo_virtual_operado${suffix}`) || '0', 10);
    const tipoCambio = parseFloat(v(`tipo_cambio_mn${suffix}`) || '0');
    const cantidad = parseFloat(v(`cantidad_activo_virtual${suffix}`) || '0');
    const desc = cleanByRegex(v(`descripcion_activo_virtual${suffix}`), /[^A-ZÑ0-9 ,\.:\/'\$\-]/g, '');
    requireTrue(activoOperado > 0, 'Activo virtual operado inválido.');
    requireTrue(tipoCambio > 0, 'Tipo de cambio debe ser mayor a 0.');
    requireTrue(cantidad > 0, 'Cantidad de activo virtual debe ser mayor a 0.');
    if (activoOperado === 999999) {
        requireTrue(desc.length > 0, 'Descripción de activo virtual requerida para opción OTRO.');
    }
    const activo = {
        activo_virtual_operado: activoOperado,
        tipo_cambio_mn: fmtMonto2(tipoCambio),
        cantidad_activo_virtual: fmtCantidad10(cantidad)
    };
    if (desc) {
        activo.descripcion_activo_virtual = desc;
    }
    return activo;
}

function buildPersonaCuentaBasica() {
    requireTrue(!!kycCache, 'KYC no cargado para datos de cuenta.');
    const nacTipo = v('fondos_nacionalidad_cuenta') || 'nacional';
    const tipoPersonaBasico = ((kycCache?.es_fisica || 0) > 0)
        ? {
            persona_fisica: {
                nombre: cleanByRegex(kycCache?.nombre, /[^A-ZÑ ]/g, ''),
                apellido_paterno: cleanByRegex(kycCache?.apellido_paterno, /[^A-ZÑ ]/g, ''),
                apellido_materno: cleanByRegex(kycCache?.apellido_materno, /[^A-ZÑ ]/g, '')
            }
        }
        : {
            persona_moral: {
                denominacion_razon: cleanByRegex(kycCache?.razon_social || kycCache?.denominacion_razon, /[^A-ZÑ0-9#\-\.&,_@' ]/g, '')
            }
        };
    if ((kycCache?.es_fisica || 0) > 0) {
        requireTrue(!!tipoPersonaBasico.persona_fisica.nombre && !!tipoPersonaBasico.persona_fisica.apellido_paterno && !!tipoPersonaBasico.persona_fisica.apellido_materno,
            'KYC persona física incompleto para fondos.');
    } else {
        requireTrue(!!tipoPersonaBasico.persona_moral.denominacion_razon, 'KYC persona moral incompleto para fondos.');
    }

    let nacionalidadCuenta = null;
    if (nacTipo === 'extranjero') {
        const numeroCuenta = cleanByRegex(v('numero_cuenta_extranjera'), /[^A-Z0-9]/g, '');
        const nombreBanco = cleanByRegex(v('nombre_banco_extranjero'), /[^A-ZÑ0-9#\-\.&,_@' ]/g, '');
        requireTrue(numeroCuenta.length >= 1 && numeroCuenta.length <= 30, 'Número de cuenta extranjera inválido.');
        requireTrue(nombreBanco.length >= 1, 'Nombre de banco extranjero requerido.');
        nacionalidadCuenta = {
            extranjero: {
                numero_cuenta: numeroCuenta,
                nombre_banco: nombreBanco
            }
        };
    } else {
        const clabeDestino = onlyDigits(v('clabe_destino') || v('clabe_interbancaria'));
        const claveInst = onlyDigits(v('clave_institucion_financiera'));
        const catInstitucion = AVI_CATALOGOS_JS?.clave_institucion_financiera || {};
        requireTrue(clabeDestino.length === 18, 'CLABE destino debe tener 18 dígitos.');
        requireTrue(claveInst.length >= 3 && claveInst.length <= 5, 'Clave institución financiera debe tener 3-5 dígitos.');
        requireTrue(Object.prototype.hasOwnProperty.call(catInstitucion, claveInst), 'Clave institución financiera fuera de catálogo AVI.');
        nacionalidadCuenta = {
            nacional: {
                clabe_destino: clabeDestino,
                clave_institucion_financiera: parseInt(claveInst, 10)
            }
        };
    }

    return {
        tipo_persona: tipoPersonaBasico,
        nacionalidad_cuenta: nacionalidadCuenta
    };
}

function buildTipoDomicilioNode() {
    const esFide = !((kycCache?.es_fisica || 0) > 0) && !((kycCache?.es_moral || 0) > 0);
    if (esFide) return undefined;
    const tipo = v('tipo_domicilio_persona') || 'nacional';
    if (tipo === 'extranjero') {
        const pais = cleanByRegex(v('dom_e_pais'), /[^A-Z]/g, '');
        const estado = cleanByRegex(v('dom_e_estado'), /[^A-ZÑ0-9 ,\.:\/]/g, '');
        const ciudad = cleanByRegex(v('dom_e_ciudad'), /[^A-ZÑ0-9 ,\.:\/]/g, '');
        const colonia = cleanByRegex(v('dom_e_colonia'), /[^A-ZÑ0-9 ,\.:\/\-\(\)]/g, '');
        const calle = cleanByRegex(v('dom_e_calle'), /[^A-ZÑ0-9 ,\.:\/]/g, '');
        const numExt = cleanByRegex(v('dom_e_num_ext'), /[^A-ZÑ0-9 ,\.:\/\-]/g, '');
        const cpExt = cleanByRegex(v('dom_e_cp'), /[^A-Z0-9Ñ]/g, '');
        requireTrue(pais.length === 2, 'Domicilio extranjero: país inválido.');
        requireTrue(estado && ciudad && colonia && calle && numExt && cpExt, 'Domicilio extranjero incompleto.');
        return {
            extranjero: {
                pais: pais,
                estado_provincia: estado,
                ciudad_poblacion: ciudad,
                colonia: colonia,
                calle: calle,
                numero_exterior: numExt,
                numero_interior: cleanByRegex(v('dom_e_num_int'), /[^A-ZÑ0-9 ,\.:\/\-]/g, '') || undefined,
                codigo_postal: cpExt
            }
        };
    }
    const cp = onlyDigits(v('dom_n_cp'));
    const colonia = cleanByRegex(v('dom_n_colonia'), /[^A-ZÑ0-9 ,\.:\/\-\(\)]/g, '');
    const calle = cleanByRegex(v('dom_n_calle'), /[^A-ZÑ0-9 ,\.:\/]/g, '');
    const numExt = cleanByRegex(v('dom_n_num_ext'), /[^A-ZÑ0-9 ,\.:\/\-]/g, '');
    requireTrue(cp.length === 5, 'Domicilio nacional: C.P. inválido.');
    requireTrue(colonia && calle && numExt, 'Domicilio nacional incompleto.');
    return {
        nacional: {
            colonia: colonia,
            calle: calle,
            numero_exterior: numExt,
            numero_interior: cleanByRegex(v('dom_n_num_int'), /[^A-ZÑ0-9 ,\.:\/\-]/g, '') || undefined,
            codigo_postal: cp
        }
    };
}

function buildTelefonoNode() {
    const esFide = !((kycCache?.es_fisica || 0) > 0) && !((kycCache?.es_moral || 0) > 0);
    if (esFide) return undefined;
    const clave = cleanByRegex(v('tel_clave_pais'), /[^A-Z]/g, '').substring(0, 2);
    const numero = onlyDigits(v('tel_numero'));
    const correo = cleanByRegex(v('tel_correo'), /[^A-Z0-9\._'\-@]/g, '');
    requireTrue(clave.length === 2, 'Teléfono: clave país inválida.');
    requireTrue(numero.length === 10 || numero.length === 12, 'Teléfono: número inválido.');
    requireTrue(/^[A-Z0-9\._'\-]+@[A-Z0-9_'\-]+\.[A-Z0-9\._'\-]+$/.test(correo), 'Teléfono: correo inválido.');
    return { clave_pais: clave, numero_telefono: numero, correo_electronico: correo };
}

function buildDuenoBeneficiarioNode(strictMode = true) {
    if (v('db_habilitado') !== '1') return undefined;
    const tipo = v('db_tipo_persona') || 'fisica';
    if (tipo === 'fisica') {
        const nombre = cleanByRegex(v('db_f_nombre'), /[^A-ZÑ ]/g, '');
        const ap = cleanByRegex(v('db_f_ap_paterno'), /[^A-ZÑ ]/g, '');
        const am = cleanByRegex(v('db_f_ap_materno'), /[^A-ZÑ ]/g, '');
        const fecha = ymdFromAnyDate(v('db_f_fecha'));
        const rfc = cleanByRegex(v('db_f_rfc'), /[^A-Z0-9Ñ&]/g, '');
        const curp = cleanByRegex(v('db_f_curp'), /[^A-Z0-9]/g, '');
        const pais = cleanByRegex(v('db_f_pais') || 'MX', /[^A-Z]/g, '').substring(0, 2);
        const isBlank = !nombre && !ap && !am && !fecha && !rfc && !curp;
        if (isBlank && !strictMode) return undefined;
        requireTrue(nombre && ap && am, 'Dueño beneficiario PF incompleto.');
        requireTrue(fecha || rfc || curp, 'Dueño beneficiario PF: capture fecha, RFC o CURP.');
        return { tipo_persona: { persona_fisica: { nombre, apellido_paterno: ap, apellido_materno: am, fecha_nacimiento: fecha || undefined, rfc: rfc || undefined, curp: curp || undefined, pais_nacionalidad: pais } } };
    }
    if (tipo === 'moral') {
        const razon = cleanByRegex(v('db_m_razon'), /[^A-ZÑ0-9#\-\.&,_@' ]/g, '');
        const fecha = ymdFromAnyDate(v('db_m_fecha'));
        const rfc = cleanByRegex(v('db_m_rfc'), /[^A-Z0-9Ñ&]/g, '');
        const pais = cleanByRegex(v('db_m_pais') || 'MX', /[^A-Z]/g, '').substring(0, 2);
        const isBlank = !razon && !fecha && !rfc;
        if (isBlank && !strictMode) return undefined;
        requireTrue(razon.length > 0, 'Dueño beneficiario PM incompleto.');
        requireTrue(fecha || rfc, 'Dueño beneficiario PM: capture fecha de constitución o RFC.');
        return { tipo_persona: { persona_moral: { denominacion_razon: razon, fecha_constitucion: fecha || undefined, rfc: rfc || undefined, pais_nacionalidad: pais } } };
    }
    const razonF = cleanByRegex(v('db_t_razon'), /[^A-ZÑ0-9#\-\.&,_@' ]/g, '');
    const rfcF = cleanByRegex(v('db_t_rfc'), /[^A-Z0-9Ñ&]/g, '');
    const ident = cleanByRegex(v('db_t_ident'), /[^A-ZÑ0-9#\-\.&,_@' ]/g, '');
    const isBlank = !razonF && !rfcF && !ident;
    if (isBlank && !strictMode) return undefined;
    requireTrue(razonF.length > 0, 'Dueño beneficiario fideicomiso incompleto.');
    requireTrue(rfcF || ident, 'Dueño beneficiario fideicomiso: capture RFC o identificador.');
    return { tipo_persona: { fideicomiso: { denominacion_razon: razonF, rfc: rfcF || undefined, identificador_fideicomiso: ident || undefined } } };
}

function getDuenoBeneficiarioSummary(node) {
    const tp = node?.tipo_persona || {};
    if (tp.persona_fisica) {
        const pf = tp.persona_fisica;
        return {
            tipo: 'Física',
            resumen: [pf.nombre, pf.apellido_paterno, pf.apellido_materno].filter(Boolean).join(' ') + (pf.rfc ? ` | RFC: ${pf.rfc}` : '')
        };
    }
    if (tp.persona_moral) {
        const pm = tp.persona_moral;
        return {
            tipo: 'Moral',
            resumen: `${pm.denominacion_razon || ''}${pm.rfc ? ` | RFC: ${pm.rfc}` : ''}`
        };
    }
    const fi = tp.fideicomiso || {};
    return {
        tipo: 'Fideicomiso',
        resumen: `${fi.denominacion_razon || ''}${fi.rfc ? ` | RFC: ${fi.rfc}` : ''}${fi.identificador_fideicomiso ? ` | ID: ${fi.identificador_fideicomiso}` : ''}`
    };
}

function renderDuenosBeneficiariosDraft() {
    const tbody = document.querySelector('#tablaDuenosBeneficiarios tbody');
    if (!tbody) return;
    if (!duenosBeneficiariosDraft.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center">Sin dueños beneficiarios capturados</td></tr>';
        return;
    }
    tbody.innerHTML = duenosBeneficiariosDraft.map((item, idx) => {
        const meta = getDuenoBeneficiarioSummary(item);
        return `
        <tr>
            <td>${idx + 1}</td>
            <td>${meta.tipo}</td>
            <td>${meta.resumen || '-'}</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" data-del-db="${idx}"><i class="fa-solid fa-xmark"></i></button></td>
        </tr>`;
    }).join('');
    tbody.querySelectorAll('button[data-del-db]').forEach(btn => {
        btn.addEventListener('click', () => {
            const i = parseInt(btn.getAttribute('data-del-db') || '-1', 10);
            if (i >= 0) {
                duenosBeneficiariosDraft.splice(i, 1);
                renderDuenosBeneficiariosDraft();
            }
        });
    });
}

function clearDuenoBeneficiarioFormByTipo(tipo) {
    if (tipo === 'fisica') {
        ['db_f_nombre', 'db_f_ap_paterno', 'db_f_ap_materno', 'db_f_fecha', 'db_f_rfc', 'db_f_curp'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        return;
    }
    if (tipo === 'moral') {
        ['db_m_razon', 'db_m_fecha', 'db_m_rfc'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        return;
    }
    ['db_t_razon', 'db_t_rfc', 'db_t_ident'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
}

function addDuenoBeneficiarioDraft() {
    requireTrue(v('db_habilitado') === '1', 'Habilite "Agregar" para dueños beneficiarios.');
    const tipo = v('db_tipo_persona') || 'fisica';
    const node = buildDuenoBeneficiarioNode(true);
    requireTrue(!!node, 'Capture un dueño beneficiario válido.');
    duenosBeneficiariosDraft.push(node);
    clearDuenoBeneficiarioFormByTipo(tipo);
    renderDuenosBeneficiariosDraft();
}

function toggleOperacionSections() {
    const tipo = v('tipo_operacion_avi');
    const isInter = tipo === 'intercambio';
    const isFondos = tipo === 'fondos_retiro' || tipo === 'fondos_deposito';
    document.getElementById('fondos-extra-box')?.classList.toggle('d-none', !isFondos);
    document.getElementById('box_activo_recibido_tipo')?.classList.toggle('d-none', !isInter);
    document.getElementById('box_activo_recibido_desc')?.classList.toggle('d-none', !isInter);
    document.getElementById('box_activo_recibido_tc')?.classList.toggle('d-none', !isInter);
    document.getElementById('box_activo_recibido_cant')?.classList.toggle('d-none', !isInter);
}

function renderOperacionesDraft() {
    const tbody = document.querySelector('#tablaOperacionesAVI tbody');
    if (!tbody) return;
    if (!operacionesDraft.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center">Sin operaciones capturadas</td></tr>';
        return;
    }
    tbody.innerHTML = operacionesDraft.map((op, idx) => `
        <tr>
            <td>${idx + 1}</td>
            <td>${op.tipo}</td>
            <td>${op.fecha}</td>
            <td>${fmtMonto2(op.monto)}</td>
            <td>${op.hash || '-'}</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" data-del-op="${idx}"><i class="fa-solid fa-xmark"></i></button></td>
        </tr>
    `).join('');
    tbody.querySelectorAll('button[data-del-op]').forEach(btn => {
        btn.addEventListener('click', () => {
            const i = parseInt(btn.getAttribute('data-del-op') || '-1', 10);
            if (i >= 0) {
                operacionesDraft.splice(i, 1);
                renderOperacionesDraft();
            }
        });
    });
}

function addOperacionDraftFromForm() {
    const tipo = v('tipo_operacion_avi');
    const fechaHora = ymdhmFromDatetimeLocal(v('fecha_hora_operacion'));
    const monto = parseFloat(v('monto_operacion') || '0') || 0;
    const hashOperacion = cleanByRegex(v('hash_operacion'), /[^A-Z0-9]/g, '');
    requireTrue(fechaHora.length === 14, 'Fecha/hora de operación inválida.');
    requireTrue(monto > 0, 'Monto de operación inválido.');
    if (tipo !== 'fondos_retiro' && tipo !== 'fondos_deposito') {
        requireTrue(hashOperacion.length > 0, 'Hash de operación requerido.');
    }
    operacionesDraft.push({
        tipo,
        fecha: fechaHora,
        monto,
        hash: hashOperacion,
        moneda: parseInt(v('moneda_operacion') || '1', 10),
        instrumento: parseInt(v('instrumento_monetario') || '1', 10),
        activo: buildActivoVirtualNode(''),
        activoRecibido: tipo === 'intercambio' ? buildActivoVirtualNode('_recibido') : null,
        personaCuenta: (tipo === 'fondos_retiro' || tipo === 'fondos_deposito') ? buildPersonaCuentaBasica() : null
    });
    renderOperacionesDraft();
}

function buildDetalleOperacion() {
    if (!operacionesDraft.length) {
        addOperacionDraftFromForm();
    }
    requireTrue(operacionesDraft.length > 0, 'Capture al menos una operación.');
    const detalle = {};
    operacionesDraft.forEach(op => {
        const montoMN = fmtMonto2(op.monto);
        if (op.tipo === 'compra') {
            if (!detalle.operaciones_compra) detalle.operaciones_compra = { compra: [] };
            detalle.operaciones_compra.compra.push({
                fecha_hora_operacion: op.fecha,
                moneda_operacion: op.moneda,
                monto_operacion: montoMN,
                activo_virtual: op.activo,
                hash_operacion: op.hash
            });
            return;
        }
        if (op.tipo === 'venta') {
            if (!detalle.operaciones_venta) detalle.operaciones_venta = { venta: [] };
            detalle.operaciones_venta.venta.push({
                fecha_hora_operacion: op.fecha,
                moneda_operacion: op.moneda,
                monto_operacion: montoMN,
                activo_virtual: op.activo,
                hash_operacion: op.hash
            });
            return;
        }
        if (op.tipo === 'intercambio') {
            if (!detalle.operaciones_intercambio) detalle.operaciones_intercambio = { intercambio: [] };
            detalle.operaciones_intercambio.intercambio.push({
                fecha_hora_operacion: op.fecha,
                activo_virtual_enviado: { activo_virtual: op.activo, monto_operacion_mn: montoMN },
                activo_virtual_recibido: { activo_virtual: op.activoRecibido || op.activo, monto_operacion_mn: montoMN },
                hash_operacion: op.hash
            });
            return;
        }
        if (op.tipo === 'transferencia_envio') {
            if (!detalle.operaciones_transferencia) detalle.operaciones_transferencia = {};
            if (!detalle.operaciones_transferencia.transferencias_enviadas) detalle.operaciones_transferencia.transferencias_enviadas = { envio: [] };
            detalle.operaciones_transferencia.transferencias_enviadas.envio.push({
                fecha_hora_operacion: op.fecha,
                monto_operacion_mn: montoMN,
                activo_virtual: op.activo,
                hash_operacion: op.hash
            });
            return;
        }
        if (op.tipo === 'transferencia_recepcion') {
            if (!detalle.operaciones_transferencia) detalle.operaciones_transferencia = {};
            if (!detalle.operaciones_transferencia.transferencias_recibidas) detalle.operaciones_transferencia.transferencias_recibidas = { recepcion: [] };
            detalle.operaciones_transferencia.transferencias_recibidas.recepcion.push({
                fecha_hora_operacion: op.fecha,
                monto_operacion_mn: montoMN,
                activo_virtual: op.activo,
                hash_operacion: op.hash
            });
            return;
        }
        if (op.tipo === 'fondos_retiro') {
            if (!detalle.operaciones_fondos) detalle.operaciones_fondos = {};
            if (!detalle.operaciones_fondos.fondos_retirados) detalle.operaciones_fondos.fondos_retirados = { retiro: [] };
            detalle.operaciones_fondos.fondos_retirados.retiro.push({
                fecha_hora_operacion: op.fecha,
                instrumento_monetario: op.instrumento,
                moneda_operacion: op.moneda,
                monto_operacion: montoMN,
                datos_beneficiario: op.personaCuenta
            });
            return;
        }
        if (!detalle.operaciones_fondos) detalle.operaciones_fondos = {};
        if (!detalle.operaciones_fondos.fondos_depositados) detalle.operaciones_fondos.fondos_depositados = { deposito: [] };
        detalle.operaciones_fondos.fondos_depositados.deposito.push({
            fecha_hora_operacion: op.fecha,
            instrumento_monetario: op.instrumento,
            moneda_operacion: op.moneda,
            monto_operacion: montoMN,
            datos_ordenante: op.personaCuenta
        });
    });
    return detalle;
}

function buildPayload() {
    const idCliente = parseInt(v('id_cliente'), 10);
    if (!idCliente) throw new Error('Seleccione un cliente.');
    validateMesReportado(v('mes_reportado'));
    const tipoPersona = buildTipoPersonaFromKyc();
    const detalleOperacion = buildDetalleOperacion();
    const montoContrap = parseFloat(v('monto_contraprestacion_servicio') || '0') || 0;
    const montoOperacionControl = operacionesDraft.length
        ? operacionesDraft.reduce((acc, it) => acc + (parseFloat(it.monto) || 0), 0)
        : (parseFloat(v('monto_operacion') || '0') || 0);
    const exento = v('exento') === '1' ? '1' : undefined;
    const clabe = onlyDigits(v('clabe_interbancaria'));
    if (clabe) requireTrue(clabe.length === 18, 'CLABE interbancaria inválida.');
    const idUsuario = cleanByRegex(v('id_usuario_plataforma'), /[^A-Z0-9\-_]/g, '').substring(0, 30);
    const cuentaRelacionada = cleanByRegex(v('cuenta_relacionada'), /[^A-Z0-9]/g, '');
    const referenciaAviso = cleanByRegex(v('referencia_aviso'), /[^A-Z0-9Ñ]/g, '').substring(0, 14);
    const prioridad = intOrDefault(v('prioridad') || '1', 1);
    const tipoAlerta = intOrDefault(v('tipo_alerta') || '100', 100);
    const descripcionAlerta = cleanByRegex(v('descripcion_alerta') || '', /[^A-ZÑ0-9 ,\.:\/'\$\-]/g, '');
    requireTrue(idUsuario.length > 0, 'ID usuario de plataforma requerido.');
    requireTrue(cuentaRelacionada.length > 0, 'Cuenta relacionada requerida.');
    requireTrue(referenciaAviso.length > 0, 'Referencia aviso requerida.');
    if (prioridad === 2) requireTrue(tipoAlerta !== 100, 'Si prioridad es 2, tipo de alerta no puede ser 100.');
    if (tipoAlerta === 9999) requireTrue(descripcionAlerta.length > 0, 'Descripción alerta requerida para tipo alerta 9999.');
    const esModificatorio = v('es_modificatorio') === '1';
    const folioMod = cleanByRegex(v('folio_modificacion') || '', /[^A-Z0-9\-]/g, '');
    const descMod = cleanByRegex(v('descripcion_modificacion') || '', /[^A-ZÑ0-9 ,\.:\/'\$\-]/g, '');
    if (esModificatorio) {
        requireTrue(folioMod.length >= 6 && folioMod.length <= 14, 'Folio de modificación inválido.');
        requireTrue(descMod.length > 0, 'Descripción de modificación requerida.');
    }
    const tipoDomicilio = buildTipoDomicilioNode();
    const telefono = buildTelefonoNode();
    const duenos = [...duenosBeneficiariosDraft];
    if (v('db_habilitado') === '1') {
        if (duenos.length === 0) {
            const maybeDueno = buildDuenoBeneficiarioNode(false);
            if (maybeDueno) duenos.push(maybeDueno);
        }
        requireTrue(duenos.length > 0, 'Agregue al menos un dueño beneficiario o seleccione "No".');
    }
    const sujetoObligado = {
        clave_entidad_colegiada: up(v('clave_entidad_colegiada')) || undefined,
        clave_sujeto_obligado: up(v('clave_sujeto_obligado')),
        clave_actividad: 'AVI',
        exento: exento,
        dominio_plataforma: normalizeDominio(v('dominio_plataforma'))
    };
    if (sujetoObligado.clave_entidad_colegiada) requireTrue(sujetoObligado.clave_entidad_colegiada.length === 12, 'Clave entidad colegiada inválida.');
    requireTrue(isRFC(sujetoObligado.clave_sujeto_obligado), 'Clave sujeto obligado (RFC) inválida.');

    const personaAviso = {
        datos_cuenta_plataforma: {
            id_usuario: idUsuario,
            cuenta_relacionada: cuentaRelacionada,
            clabe_interbancaria: clabe.length === 18 ? clabe : undefined,
            moneda_cuenta: intOrDefault(v('moneda_cuenta') || '1', 1)
        },
        tipo_persona: tipoPersona
    };
    if (tipoDomicilio) personaAviso.tipo_domicilio = tipoDomicilio;
    if (telefono) personaAviso.telefono = telefono;

    const operacionesPersona = { persona_aviso: personaAviso };
    if (duenos.length > 0) operacionesPersona.dueno_beneficiario = duenos;
    operacionesPersona.detalle_operaciones = detalleOperacion;

    const aviso = {
        referencia_aviso: referenciaAviso,
        prioridad: prioridad,
        alerta: { tipo_alerta: tipoAlerta },
        operaciones_persona: operacionesPersona
    };
    if (tipoAlerta === 9999 && descripcionAlerta) aviso.alerta.descripcion_alerta = descripcionAlerta;
    if (esModificatorio) {
        aviso.modificatorio = {
            folio_modificacion: folioMod,
            descripcion_modificacion: descMod
        };
    }

    return {
        id_cliente: idCliente,
        monto_operacion_control: montoOperacionControl,
        monto_contraprestacion_servicio: montoContrap,
        informe: [{
            mes_reportado: v('mes_reportado'),
            sujeto_obligado: sujetoObligado,
            aviso: [aviso]
        }]
    };
}

function toggleModificatorioSections() {
    const isMod = v('es_modificatorio') === '1';
    document.getElementById('box_folio_modificacion')?.classList.toggle('d-none', !isMod);
    document.getElementById('box_descripcion_modificacion')?.classList.toggle('d-none', !isMod);
}

function toggleDomicilioSections() {
    const tipo = v('tipo_domicilio_persona') || 'nacional';
    document.getElementById('domicilio_nacional_box')?.classList.toggle('d-none', tipo === 'extranjero');
    document.getElementById('domicilio_extranjero_box')?.classList.toggle('d-none', tipo !== 'extranjero');
}

function toggleDuenoSections() {
    const on = v('db_habilitado') === '1';
    const tipo = v('db_tipo_persona') || 'fisica';
    document.getElementById('db_tipo_box')?.classList.toggle('d-none', !on);
    document.getElementById('db_fisica_box')?.classList.toggle('d-none', !(on && tipo === 'fisica'));
    document.getElementById('db_moral_box')?.classList.toggle('d-none', !(on && tipo === 'moral'));
    document.getElementById('db_fide_box')?.classList.toggle('d-none', !(on && tipo === 'fideicomiso'));
    document.getElementById('db_actions_box')?.classList.toggle('d-none', !on);
    document.getElementById('db_tabla_box')?.classList.toggle('d-none', !on);
}

function submitAVI(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    if (btn && btn.disabled) return;

    let payload;
    try {
        payload = buildPayload();
    } catch (err) {
        Swal.fire('Error', err.message || 'Formulario incompleto', 'error');
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Registrando...';
    }

    fetch('api/registrar_aviso_avi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Registrar y Generar XML AVI';
        }
        if (data.status !== 'success') {
            throw new Error(data.message || 'Error al registrar AVI');
        }
        const ev = data.evaluacion_xvi || {};
        const txt = [
            `Operación registrada (ID ${data.id_operacion || '-'})`,
            `Aviso requerido: ${data.requiere_aviso ? 'Sí' : 'No'}`,
            `Umbral 210 UMA por monto: ${ev.requiere_aviso_por_monto ? 'Sí' : 'No'}`,
            `Umbral 4 UMA por contraprestación: ${ev.requiere_aviso_por_contraprestacion ? 'Sí' : 'No'}`
        ].join('<br>');
        Swal.fire({
            icon: 'success',
            title: 'Aviso AVI registrado',
            html: txt
        }).then(() => window.location.href = 'operaciones_pld.php');
    })
    .catch(err => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane me-2"></i>Registrar y Generar XML AVI';
        }
        Swal.fire('Error', err.message || 'Error de conexión', 'error');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadClientes();
    document.getElementById('id_cliente').addEventListener('change', (e) => loadKyc(e.target.value));
    document.getElementById('tipo_operacion_avi')?.addEventListener('change', toggleOperacionSections);
    document.getElementById('es_modificatorio')?.addEventListener('change', toggleModificatorioSections);
    document.getElementById('tipo_domicilio_persona')?.addEventListener('change', toggleDomicilioSections);
    document.getElementById('db_habilitado')?.addEventListener('change', toggleDuenoSections);
    document.getElementById('db_tipo_persona')?.addEventListener('change', toggleDuenoSections);
    document.getElementById('btnAgregarDuenoBeneficiario')?.addEventListener('click', () => {
        try {
            addDuenoBeneficiarioDraft();
        } catch (err) {
            Swal.fire('Error', err.message || 'No se pudo agregar dueño beneficiario', 'error');
        }
    });
    document.getElementById('btnLimpiarDuenosBeneficiarios')?.addEventListener('click', () => {
        duenosBeneficiariosDraft = [];
        renderDuenosBeneficiariosDraft();
    });
    document.getElementById('btnAgregarOperacion')?.addEventListener('click', () => {
        try {
            addOperacionDraftFromForm();
        } catch (err) {
            Swal.fire('Error', err.message || 'No se pudo agregar operación', 'error');
        }
    });
    document.getElementById('btnLimpiarOperaciones')?.addEventListener('click', () => {
        operacionesDraft = [];
        renderOperacionesDraft();
    });
    document.getElementById('formAVI').addEventListener('submit', submitAVI);
    toggleOperacionSections();
    toggleModificatorioSections();
    toggleDomicilioSections();
    toggleDuenoSections();
    renderOperacionesDraft();
    renderDuenosBeneficiariosDraft();
});
</script>

<?php include 'templates/footer.php'; ?>
