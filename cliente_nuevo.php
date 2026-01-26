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
            <h4 class="alert-heading"><i class="fa-solid fa-ban me-2"></i>Operación Bloqueada</h4>
            <p><strong>NO HABILITADO PARA OPERAR PLD</strong></p>
            <p>El sujeto obligado no está habilitado para realizar operaciones PLD.</p>
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
    <form id="newClientForm">
        
        <!-- STEP 1 -->
        <div id="step-1" class="step active">
            <div class="form-section">
                <div class="section-title">
                    <i class="fa-solid fa-info-circle"></i>
                    Paso 1: Información General
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
                        <input type="text" class="form-control" name="no_contrato" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <i class="fa-solid fa-tag"></i>
                            Alias
                        </label>
                        <input type="text" class="form-control" name="alias">
                    </div>
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
                    <div class="col-md-6 mb-3" id="fechaBajaContainer" style="display: none;">
                        <label class="form-label">
                            <i class="fa-solid fa-calendar-times"></i>
                            Fecha de Cancelación*
                        </label>
                        <input type="date" class="form-control" name="fecha_baja">
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

                <!-- SECCIÓN FÍSICA (Added onblur events) -->
                <div id="persona-fisica" class="persona-specific">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-user"></i>
                                Nombre*
                            </label>
                            <input type="text" class="form-control" id="fisica_nombre" name="fisica_nombre" onblur="validatePerson(true)" placeholder="Ej: Juan">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-user"></i>
                                Apellido Paterno*
                            </label>
                            <input type="text" class="form-control" id="fisica_ap_paterno" name="fisica_ap_paterno" onblur="validatePerson(true)" placeholder="Ej: Pérez">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-user"></i>
                                Apellido Materno
                            </label>
                            <input type="text" class="form-control" id="fisica_ap_materno" name="fisica_ap_materno" onblur="validatePerson(true)" placeholder="Ej: González">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-calendar-days"></i>
                                Fecha Nacimiento*
                            </label>
                            <input type="date" class="form-control" name="fisica_fecha_nacimiento">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-id-card"></i>
                                RFC*
                            </label>
                            <input type="text" class="form-control" name="fisica_tax_id" placeholder="Ej: PERG800101ABC">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-id-badge"></i>
                                CURP
                            </label>
                            <input type="text" class="form-control" name="fisica_curp" placeholder="Ej: PERG800101HDFRNS01">
                        </div>
                    </div>
                </div>
                <!-- SECCIÓN MORAL (Added onblur events) -->
                <div id="persona-moral" class="persona-specific">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-building"></i>
                                Razón Social*
                            </label>
                            <input type="text" class="form-control" id="moral_razon_social" name="moral_razon_social" onblur="validatePerson(true)" placeholder="Ej: Empresa S.A. de C.V.">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-calendar-days"></i>
                                Fecha Constitución*
                            </label>
                            <input type="date" class="form-control" name="moral_fecha_constitucion">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-id-card"></i>
                                RFC*
                            </label>
                            <input type="text" class="form-control" name="moral_tax_id" placeholder="Ej: ABC123456DEF">
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
                            <input type="text" class="form-control" name="fide_numero" placeholder="Ej: FID-2024-001">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-building-columns"></i>
                                Institución Fiduciaria*
                            </label>
                            <input type="text" class="form-control" name="fide_institucion" placeholder="Ej: Banco Fiduciario S.A.">
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
             <div class="form-section">
                <div class="section-title">
                    <i class="fa-solid fa-address-card"></i>
                    Paso 3: Identificación y Contacto
                </div>
                
                <div class="subsection-title">
                    <i class="fa-solid fa-globe"></i>
                    Nacionalidades
                </div>
                <div id="nacionalidades-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addNacionalidad">
                    <i class="fa-solid fa-plus me-2"></i>Agregar Nacionalidad
                </button>
                
                <hr class="my-4">
                
                <div class="subsection-title">
                    <i class="fa-solid fa-id-card"></i>
                    Identificaciones
                </div>
                <div id="identificaciones-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addIdentificacion">
                    <i class="fa-solid fa-plus me-2"></i>Agregar Identificación
                </button>
                
                <hr class="my-4">
                
                <div class="subsection-title">
                    <i class="fa-solid fa-location-dot"></i>
                    Direcciones
                </div>
                <div id="direcciones-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addDireccion">
                    <i class="fa-solid fa-plus me-2"></i>Agregar Dirección
                </button>
                
                <hr class="my-4">
                
                <div class="subsection-title">
                    <i class="fa-solid fa-phone"></i>
                    Contactos
                </div>
                <div id="contactos-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addContacto">
                    <i class="fa-solid fa-plus me-2"></i>Agregar Contacto
                </button>
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
                    Paso 4: Documentos (KYC)
                </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/cliente_nuevo.js"></script>

<?php include 'templates/footer.php'; ?>