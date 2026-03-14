<?php 
include 'templates/header.php'; 

// VAL-PLD-001: Verificar habilitación PLD antes de permitir onboarding
require_once __DIR__ . '/config/pld_validation.php';
require_once __DIR__ . '/config/pld_middleware.php';

$isPLDHabilitado = checkHabilitadoPLD($pdo);
if (!$isPLDHabilitado) {
    $validationResult = validatePatronPLD($pdo);
    ?>
    <title>Acceso Bloqueado - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
    </head>
    <body>
    <?php $is_sub_page = true; include 'templates/top_bar.php'; ?>
    <div class="container mt-5">
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading"><i class="fa-solid fa-ban me-2"></i>Transacción Bloqueada</h4>
            <p><strong>NO HABILITADO PARA OPERAR PLD</strong></p>
            <p>El sujeto obligado no está habilitado para realizar transacciones PLD.</p>
            <hr>
            <p class="mb-0">
                <strong>Razón:</strong> <?= htmlspecialchars($validationResult['razon'] ?? 'Validación de padrón PLD fallida') ?><br>
                <strong>Estatus:</strong> <?= htmlspecialchars($validationResult['estatus'] ?? 'NO_HABILITADO_PLD') ?>
            </p>
            <div class="mt-3">
                <a href="index.php" class="btn btn-primary">Volver al Dashboard</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                    // Verificar permisos de administración
                    try {
                        $stmt = $pdo->prepare("SELECT administracion FROM usuarios_permisos WHERE id_usuario = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $perm = $stmt->fetchColumn();
                        if (!empty($perm) && $perm > 0):
                    ?>
                        <a href="admin/config.php#pld" class="btn btn-warning ms-2">Configurar Padrón PLD</a>
                    <?php endif; } catch (Exception $e) {} ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include 'templates/footer.php'; ?>
    <?php exit; }
?>
<title>Nuevo Cliente - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/cliente_nuevo.css">
</head>
<body>

<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="wizard-card">
    <div class="wizard-progress" id="wizardProgress">
        <div class="wizard-progress-track">
            <span id="wizardProgressBar"></span>
        </div>
        <div class="wizard-progress-steps">
            <div class="wizard-step-pill active" data-step="1">
                <span class="wizard-step-num">1</span>
                <span>General</span>
            </div>
            <div class="wizard-step-pill" data-step="2">
                <span class="wizard-step-num">2</span>
                <span>Persona y PLD</span>
            </div>
            <div class="wizard-step-pill" data-step="3">
                <span class="wizard-step-num">3</span>
                <span>Listas y KYC</span>
            </div>
            <div class="wizard-step-pill" data-step="4">
                <span class="wizard-step-num">4</span>
                <span>Documentos</span>
            </div>
        </div>
    </div>

    <form id="newClientForm">
        
        <!-- STEP 1 -->
        <div id="step-1" class="step active">
            <div class="form-section step1-general step1-compact">
                <div class="section-title step1-title">
                    <i class="fa-solid fa-info-circle"></i>
                    Paso 1: Información General
                </div>
                <div class="step1-intro-band">
                    <span class="step1-chip">
                        <i class="fa-solid fa-bolt"></i>Alta inicial
                    </span>
                    <span class="step1-chip">
                        <i class="fa-solid fa-shield-halved"></i>Base KYC
                    </span>
                    <span class="step1-chip">
                        <i class="fa-solid fa-hourglass-start"></i>2 minutos
                    </span>
                </div>

                <div class="step1-panel">
                    <div class="step1-panel-title">
                        <i class="fa-solid fa-id-card-clip"></i>
                        Identidad base del cliente
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-user-tag"></i>
                                Tipo de Persona*
                            </label>
                            <select id="tipoPersona" name="id_tipo_persona" class="form-select" required></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-file-contract"></i>
                                No. Contrato*
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="noContratoInput" name="no_contrato" required autocomplete="off" placeholder="Ej: EVE-000123">
                                <button type="button" class="btn btn-outline-primary" id="btnGenerateContract" title="Generar folio automático">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Generar
                                </button>
                            </div>
                            <div class="form-text" id="contractHint">Puedes configurarlo en Admin > Configuración > General.</div>
                            <div class="small mt-1" id="contractValidationMessage"></div>
                            <input type="hidden" name="auto_generated_contract" id="autoGeneratedContract" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-tag"></i>
                                Alias
                            </label>
                            <input type="text" class="form-control" name="alias" placeholder="Ej: Cliente preferente">
                        </div>
                        <div class="col-12" id="generalPersonaFields" style="display:none;">
                            <div class="step1-persona-box">
                                <div class="small text-primary fw-bold mb-2">
                                    <i class="fa-solid fa-address-card me-1"></i>Datos principales para registro inicial
                                </div>
                                <div id="general-persona-fisica" class="row" style="display:none;">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fa-solid fa-user"></i>Nombre(s)*</label>
                                        <input type="text" class="form-control" id="general_fisica_nombre" placeholder="Ej: Juan">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fa-solid fa-user"></i>Apellido Paterno*</label>
                                        <input type="text" class="form-control" id="general_fisica_ap_paterno" placeholder="Ej: Pérez">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fa-solid fa-user"></i>Apellido Materno</label>
                                        <input type="text" class="form-control" id="general_fisica_ap_materno" placeholder="Ej: González">
                                    </div>
                                </div>
                                <div id="general-persona-moral" class="row" style="display:none;">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fa-solid fa-store"></i>Nombre Comercial*</label>
                                        <input type="text" class="form-control" id="general_moral_nombre_comercial" placeholder="Ej: EVE Consultores">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fa-solid fa-building"></i>Razón Social*</label>
                                        <input type="text" class="form-control" id="general_moral_razon_social" placeholder="Ej: Empresa S.A. de C.V.">
                                    </div>
                                </div>
                                <div id="general-persona-fideicomiso" class="row" style="display:none;">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><i class="fa-solid fa-file-signature"></i>Número de Fideicomiso*</label>
                                        <input type="text" class="form-control" id="general_fide_numero" placeholder="Ej: FID-2024-001">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="step1-panel step1-panel-secondary mt-3">
                    <div class="step1-panel-title">
                        <i class="fa-solid fa-sliders"></i>
                        Control de alta y seguimiento
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-calendar-plus"></i>
                                Fecha Apertura*
                            </label>
                            <input type="date" class="form-control" name="fecha_apertura" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-circle-check"></i>
                                Estatus*
                            </label>
                            <select id="id_status" name="id_status" class="form-select" required>
                                <option value="1">Activo</option>
                                <option value="2" selected>Pendiente</option>
                                <option value="0">Inactivo</option>
                                <option value="3">Cancelado</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">
                                <i class="fa-solid fa-list-check me-1"></i>Modalidad de Alta
                            </label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="es_preregistro" name="es_preregistro" value="1">
                                <label class="form-check-label" for="es_preregistro">
                                    Pre-registro obligatorio mínimo (cliente aún no obligado a expediente completo)
                                </label>
                            </div>
                            <div class="form-text small">
                                Permite guardar con datos parciales. Las operaciones PLD se bloquean hasta completar expediente.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3" id="fechaBajaContainer" style="display: none;">
                            <label class="form-label">
                                <i class="fa-solid fa-calendar-times"></i>
                                Fecha de Cancelación*
                            </label>
                            <input type="date" class="form-control" name="fecha_baja">
                        </div>
                    </div>
                </div>
            </div>
            <div class="step-navigation">
                <div></div>
                <button type="button" class="btn btn-primary" onclick="nextStep(2)">
                    Siguiente <i class="fa-solid fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>

        <!-- STEP 2: Validation & Details -->
        <div id="step-2" class="step">
            <div class="form-section">
                <div class="section-title">
                    <i class="fa-solid fa-user-shield"></i>
                    Paso 2: Detalles de Persona y PLD
                </div>

                <!-- VALIDATION STATUS ROW -->
                <div class="validation-status-alert d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <strong>
                            <i class="fa-solid fa-shield-halved"></i>
                            Estatus PLD:
                        </strong>
                        <span id="validationStatus" class="text-muted">Pendiente de validación</span>
                    </div>
                    <button type="button" class="btn btn-warning btn-sm" onclick="validatePerson(false)">
                        <i class="fa-solid fa-shield-halved me-2"></i>Validar en Listas
                    </button>
                </div>

                <!-- SECCIÓN FÍSICA -->
                <div id="persona-fisica" class="persona-specific">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-user"></i>
                                Nombre*
                            </label>
                            <input type="text" class="form-control" id="fisica_nombre" name="fisica_nombre" placeholder="Ej: Juan">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-user"></i>
                                Apellido Paterno*
                            </label>
                            <input type="text" class="form-control" id="fisica_ap_paterno" name="fisica_ap_paterno" placeholder="Ej: Pérez">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-user"></i>
                                Apellido Materno
                            </label>
                            <input type="text" class="form-control" id="fisica_ap_materno" name="fisica_ap_materno" placeholder="Ej: González">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-calendar-days"></i>
                                Fecha Nacimiento*
                            </label>
                            <input type="date" class="form-control" id="fisica_fecha_nacimiento" name="fisica_fecha_nacimiento">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-id-card"></i>
                                RFC / Tax ID*
                            </label>
                            <input type="text" class="form-control" id="fisica_tax_id" name="fisica_tax_id" placeholder="Ej: PERG800101ABC">
                            <input type="file" class="form-control form-control-sm mt-1" name="fisica_rfc_doc_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Constancia de Situación Fiscal / RFC / Tax ID">
                            <div class="form-text"><i class="fa-solid fa-paperclip me-1"></i>Adjuntar constancia RFC / Tax ID</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-id-badge"></i>
                                CURP
                            </label>
                            <input type="text" class="form-control" id="fisica_curp" name="fisica_curp" placeholder="Ej: PERG800101HDFRNS01">
                            <input type="file" class="form-control form-control-sm mt-1" name="fisica_curp_doc_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Documento CURP">
                            <div class="form-text"><i class="fa-solid fa-paperclip me-1"></i>Adjuntar documento CURP</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fa-solid fa-passport me-1"></i>Tipo de residencia</label>
                            <select class="form-select" id="id_tipo_residencia" name="id_tipo_residencia">
                                <option value="">-- Seleccione --</option>
                            </select>
                            <div class="form-text small">Mexicana, residente o visitante (KYC)</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fa-solid fa-globe me-1"></i>País de nacimiento</label>
                            <select class="form-select" id="fisica_id_pais_nacimiento" name="fisica_id_pais_nacimiento">
                                <option value="">-- Seleccione --</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="fisica_fecha_ingreso_container" style="display:none;">
                            <label class="form-label"><i class="fa-solid fa-plane-arrival me-1"></i>Fecha ingreso a México</label>
                            <input type="date" class="form-control" id="fisica_fecha_ingreso_pais" name="fisica_fecha_ingreso_pais">
                            <div class="form-text small">Requerido para extranjeros visitantes (Anexo 5)</div>
                        </div>
                    </div>
                </div>
                <!-- SECCIÓN MORAL -->
                <div id="persona-moral" class="persona-specific">
                    <div class="row">
                        <input type="hidden" id="moral_nombre_comercial" name="moral_nombre_comercial">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-building"></i>
                                Razón Social*
                            </label>
                            <input type="text" class="form-control" id="moral_razon_social" name="moral_razon_social" placeholder="Ej: Empresa S.A. de C.V.">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-calendar-days"></i>
                                Fecha Constitución*
                            </label>
                            <input type="date" class="form-control" id="moral_fecha_constitucion" name="moral_fecha_constitucion">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-id-card"></i>
                                RFC / Tax ID*
                            </label>
                            <input type="text" class="form-control" id="moral_tax_id" name="moral_tax_id" placeholder="Ej: ABC123456DEF">
                            <input type="file" class="form-control form-control-sm mt-1" name="moral_rfc_doc_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Constancia de Situación Fiscal / RFC / Tax ID">
                            <div class="form-text"><i class="fa-solid fa-paperclip me-1"></i>Adjuntar constancia RFC / Tax ID</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fa-solid fa-globe me-1"></i>País de nacionalidad (PM)</label>
                            <select class="form-select" id="moral_id_pais_nacionalidad" name="moral_id_pais_nacionalidad">
                                <option value="">-- Seleccione --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fa-solid fa-building me-1"></i>Incluido en Anexo 7-A</label>
                            <select class="form-select" id="moral_id_anexo_7a" name="moral_id_anexo_7a">
                                <option value="">-- No aplica --</option>
                            </select>
                            <div class="form-text small">Si la PM está en el listado (régimen simplificado)</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fa-solid fa-landmark me-1"></i>Incluido en Anexo 7 Bis-A</label>
                            <select class="form-select" id="moral_id_anexo_7_bis_a" name="moral_id_anexo_7_bis_a">
                                <option value="">-- No aplica --</option>
                            </select>
                            <div class="form-text small">Entes de derecho público (SAT, SRE, etc.)</div>
                        </div>
                        <div class="col-12 mt-3">
                            <div class="small text-primary fw-bold mb-2"><i class="fa-solid fa-file-signature me-1"></i>Documentos KYC (Persona Moral)</div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small">Acta constitutiva (RPC)*</label>
                                    <input type="file" class="form-control form-control-sm" name="moral_acta_constitutiva_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Acta constitutiva">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small">Poder notarial vigente*</label>
                                    <input type="file" class="form-control form-control-sm" name="moral_poder_notarial_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Poder notarial">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- SECCIÓN FIDEICOMISO -->
                <div id="persona-fideicomiso" class="persona-specific">
                     <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-file-contract"></i>
                                Número Fideicomiso*
                            </label>
                            <input type="text" class="form-control" id="fide_numero" name="fide_numero" placeholder="Ej: FID-2024-001">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-building-columns"></i>
                                Institución Fiduciaria*
                            </label>
                            <input type="text" class="form-control" id="fide_institucion" name="fide_institucion" placeholder="Ej: Banco Fiduciario S.A.">
                        </div>
                        <div class="col-12 mt-3">
                            <div class="small text-primary fw-bold mb-2"><i class="fa-solid fa-file-signature me-1"></i>Documentos KYC (Fideicomiso)</div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small">Contrato de fideicomiso*</label>
                                    <input type="file" class="form-control form-control-sm" name="fide_contrato_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Contrato fideicomiso">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small">Documento fiduciario*</label>
                                    <input type="file" class="form-control form-control-sm" name="fide_doc_fiduciario_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Existencia fiduciario">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small">Facultades delegado fiduciario*</label>
                                    <input type="file" class="form-control form-control-sm" name="fide_facultades_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Facultades delegado">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label small">Identificación delegado fiduciario*</label>
                                    <input type="file" class="form-control form-control-sm" name="fide_ident_delegado_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Identificación delegado">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- APODERADOS SECTION -->
            <div id="apoderados-section" class="form-section mt-4" style="display: none;">
                <div class="section-title">
                    <i class="fa-solid fa-user-tie"></i>
                    Apoderados / Representantes Legales
                </div>
                <div id="apoderados-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addApoderado">
                    <i class="fa-solid fa-plus"></i> Agregar Apoderado
                </button>
            </div>
            
            <div class="step-navigation">
                <button type="button" class="btn btn-secondary" onclick="prevStep(1)">
                    <i class="fa-solid fa-arrow-left me-2"></i>Atrás
                </button>
                <button type="button" class="btn btn-primary" id="btnStep2Next" onclick="nextStep(3)">
                    Siguiente <i class="fa-solid fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>

        <!-- STEP 3 -->
        <div id="step-3" class="step">
             <div class="form-section step3-modern">
                <div class="section-title">
                    <i class="fa-solid fa-address-card"></i>
                    Paso 3: Identificación y Contacto
                </div>
                
                <div id="ai-extraction-badge" class="alert alert-info py-2 px-3 mb-3 d-none" role="alert">
                    <i class="fa-solid fa-robot me-2"></i><strong>Datos completados por IA</strong> — extraídos automáticamente del documento INE
                </div>

                <div class="step3-group">
                    <div class="subsection-title">
                        <i class="fa-solid fa-globe"></i>
                        Nacionalidades
                    </div>
                    <div id="nacionalidades-list"></div>
                    <button type="button" class="btn btn-sm btn-outline-primary step3-add-btn" id="addNacionalidad">
                        <i class="fa-solid fa-plus me-2"></i>Agregar Nacionalidad
                    </button>
                </div>

                <div class="step3-group">
                    <div class="subsection-title">
                        <i class="fa-solid fa-id-card"></i>
                        Identificaciones
                    </div>
                    <div id="identificaciones-list"></div>
                    <button type="button" class="btn btn-sm btn-outline-primary step3-add-btn" id="addIdentificacion">
                        <i class="fa-solid fa-plus me-2"></i>Agregar Identificación
                    </button>
                </div>

                <div class="step3-group">
                    <div class="subsection-title">
                        <i class="fa-solid fa-location-dot"></i>
                        Direcciones
                    </div>
                    <div id="direcciones-list"></div>
                    <button type="button" class="btn btn-sm btn-outline-primary step3-add-btn" id="addDireccion">
                        <i class="fa-solid fa-plus me-2"></i>Agregar Dirección
                    </button>
                </div>

                <div class="step3-group">
                    <div class="subsection-title">
                        <i class="fa-solid fa-phone"></i>
                        Contactos
                    </div>
                    <div id="contactos-list"></div>
                    <button type="button" class="btn btn-sm btn-outline-primary step3-add-btn" id="addContacto">
                        <i class="fa-solid fa-plus me-2"></i>Agregar Contacto
                    </button>
                </div>
            </div>
             <div class="step-navigation">
                <button type="button" class="btn btn-secondary" onclick="prevStep(2)">
                    <i class="fa-solid fa-arrow-left me-2"></i>Atrás
                </button>
                <button type="button" class="btn btn-primary" onclick="nextStep(4)">
                    Siguiente <i class="fa-solid fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>

        <!-- STEP 4 -->
        <div id="step-4" class="step">
            <div class="form-section">
                <div class="section-title">
                    <i class="fa-solid fa-file-check"></i>
                    Paso 4: Perfil KYC y Documentos Adicionales
                </div>
                <div class="alert alert-info py-2 px-3 mb-3">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    Los documentos de soporte deben cargarse en cada sección previa (datos, PLD, identificación, domicilio). Aquí solo se agregan documentos adicionales.
                </div>

                <div class="subsection-title">
                    <i class="fa-solid fa-shield-halved me-1"></i>
                    Clasificación de Riesgo (Art. 17 RCG)
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="clasificacion_bajo_riesgo" name="clasificacion_bajo_riesgo" value="1">
                            <label class="form-check-label" for="clasificacion_bajo_riesgo">Cliente clasificado como de bajo riesgo</label>
                        </div>
                        <div class="form-text small">Requiere criterios documentados en Manual de Políticas (Art. 37)</div>
                    </div>
                    <div class="col-md-6 mb-2" id="manual_politicas_container" style="display:none;">
                        <label class="form-label"><i class="fa-solid fa-book me-1"></i>Manual de Políticas (versión)</label>
                        <select class="form-select" id="id_manual_politicas_clasificacion" name="id_manual_politicas_clasificacion">
                            <option value="">-- Seleccione versión --</option>
                        </select>
                    </div>
                </div>

                <div class="subsection-title">
                    <i class="fa-solid fa-user-check"></i>
                    Perfil KYC / Cumplimiento
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fa-solid fa-briefcase"></i>Actividad*</label>
                        <select class="form-select" id="kyc_id_actividad" name="kyc_id_actividad">
                            <option value="">-- Seleccione actividad --</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><i class="fa-solid fa-hourglass-half"></i>Antigüedad (años)*</label>
                        <input type="number" class="form-control" id="kyc_antiguedad_anios" name="kyc_antiguedad_anios" min="0" max="120" step="1" placeholder="Ej: 3">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><i class="fa-solid fa-wallet"></i>Origen de recursos*</label>
                        <select class="form-select" id="kyc_id_origen_recursos" name="kyc_id_origen_recursos">
                            <option value="">-- Seleccione origen --</option>
                        </select>
                    </div>
                </div>

                <div id="kyc-fisica-only" style="display:none;">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fa-solid fa-building-user"></i>Empleo actual*</label>
                            <input type="text" class="form-control" id="kyc_empleo_actual" name="kyc_empleo_actual" placeholder="Ej: Analista financiero en Empresa XYZ">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><i class="fa-solid fa-user-tie"></i>Ocupación*</label>
                            <select class="form-select" id="kyc_id_ocupacion" name="kyc_id_ocupacion">
                                <option value="">-- Seleccione ocupación --</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><i class="fa-solid fa-user-graduate"></i>Nivel de estudios*</label>
                            <select class="form-select" id="kyc_nivel_estudios" name="kyc_nivel_estudios">
                                <option value="">-- Seleccione nivel --</option>
                                <option value="Primaria">Primaria</option>
                                <option value="Secundaria">Secundaria</option>
                                <option value="Bachillerato">Bachillerato</option>
                                <option value="Técnico">Técnico</option>
                                <option value="Licenciatura">Licenciatura</option>
                                <option value="Especialidad">Especialidad</option>
                                <option value="Maestría">Maestría</option>
                                <option value="Doctorado">Doctorado</option>
                                <option value="No especificado">No especificado</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fa-solid fa-user-doctor"></i>Profesión (opcional)</label>
                        <select class="form-select" id="kyc_id_profesion" name="kyc_id_profesion">
                            <option value="">-- Seleccione profesión --</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fa-solid fa-landmark-flag"></i>¿Tiene familiar directo políticamente expuesto?*</label>
                        <select class="form-select" id="kyc_tiene_familiar_pep" name="kyc_tiene_familiar_pep">
                            <option value="">-- Seleccione --</option>
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>
                    </div>
                </div>

                <div id="kyc-pep-extra" style="display:none;">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Parentesco*</label>
                            <select class="form-select" id="kyc_parentesco_familiar_pep" name="kyc_parentesco_familiar_pep">
                                <option value="">-- Seleccione parentesco --</option>
                                <option value="Padre">Padre</option>
                                <option value="Madre">Madre</option>
                                <option value="Hermano/Hermana">Hermano/Hermana</option>
                                <option value="Cónyuge">Cónyuge</option>
                                <option value="Hijo/Hija">Hijo/Hija</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Nombre del familiar*</label>
                            <input type="text" class="form-control" id="kyc_nombre_familiar_pep" name="kyc_nombre_familiar_pep" placeholder="Nombre completo">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Puesto*</label>
                            <input type="text" class="form-control" id="kyc_puesto_familiar_pep" name="kyc_puesto_familiar_pep" placeholder="Ej: Diputado local">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Fecha de ingreso*</label>
                            <input type="date" class="form-control" id="kyc_fecha_ingreso_pep" name="kyc_fecha_ingreso_pep">
                        </div>
                    </div>
                </div>

                <hr class="my-4">
                <div id="documentos-requeridos-hint" class="alert alert-info mb-3" style="display:none;">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    <strong>Documentos requeridos según tu perfil (KYC - Art. 12 RCG):</strong>
                    <ul id="documentos-requeridos-list" class="mb-0 mt-2 small"></ul>
                </div>
                <div class="subsection-title">
                    <i class="fa-solid fa-folder-open"></i>
                    Documentos requeridos (KYC)
                </div>
                <p class="small text-muted">Complete los documentos solicitados en nacionalidades, identificaciones y direcciones arriba. Los documentos adicionales se agregan abajo.</p>
                <div id="documentos-list"></div>
                <button type="button" class="btn btn-sm btn-outline-success" id="addDocumento">
                    <i class="fa-solid fa-plus me-2"></i>Agregar Documento
                </button>
            </div>
            <div class="step-navigation">
                <button type="button" class="btn btn-secondary" onclick="prevStep(3)">
                    <i class="fa-solid fa-arrow-left me-2"></i>Atrás
                </button>
                <button type="submit" class="btn btn-success" id="btnSaveClient">
                    <i class="fa-solid fa-save me-2"></i>Guardar Cliente
                </button>
            </div>
        </div>

    </form>
</div>

<!-- PLD SELECTION MODAL (Copied from cliente_detalle) -->
<div class="modal fade" id="pldModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    Resultados de Búsqueda PLD
                </h5>
                <!-- No close button to force selection -->
            </div>
            <div class="modal-body">
                <div id="pldLoading" class="text-center py-4">
                    <i class="fa-solid fa-spinner fa-spin fa-3x"></i>
                    <p class="mt-3">Consultando listas...</p>
                </div>
                <div id="pldResults" style="display:none;">
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <strong>⚠️ COINCIDENCIAS ENCONTRADAS</strong><br>
                        Se encontraron posibles coincidencias en listas de riesgo. Por favor, <strong>seleccione la coincidencia correcta</strong> o indique que no corresponde.
                    </div>
                    <div id="hitsContainer"></div>
                    
                    <div class="form-check border p-3 rounded bg-light text-danger">
                        <input class="form-check-input" type="radio" name="pldSelection" id="selNone" value="none">
                        <label class="form-check-label fw-bold" for="selNone">
                            Ninguna de las anteriores corresponde (Forzar "No Encontrado")
                        </label>
                        <div class="small text-muted mt-1">Advertencia: Usted asume la responsabilidad de esta decisión.</div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="form-label">Comentarios / Justificación:</label>
                        <textarea class="form-control" id="pldComments" rows="2" placeholder="Ej: Homónimo, fecha de nacimiento no coincide..."></textarea>
                    </div>
                    <div class="mt-3">
                        <label for="pldSupportFile" class="form-label">Documento de soporte (opcional):</label>
                        <input type="file" class="form-control" id="pldSupportFile" accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
                        <div class="form-text">Adjunte evidencia para sustentar la selección en caso de homónimos o descartes.</div>
                    </div>
                </div>
                <div id="pldClean" style="display:none;" class="text-center py-4">
                    <i class="fa-solid fa-check-circle fa-3x text-success mb-3"></i>
                    <h5>Sin Coincidencias</h5>
                    <p class="text-muted">El cliente no aparece en listas de riesgo.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnConfirmPld" onclick="confirmPld()" style="display:none;">Confirmar Selección</button>
                <button type="button" class="btn btn-success" id="btnCloseClean" data-bs-dismiss="modal" style="display:none;">Continuar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/cliente_nuevo.js"></script>

<?php include 'templates/footer.php'; ?>
