<?php 
// Inicializar conexión a la base de datos primero
require_once __DIR__ . '/config/db.php';

$id_cliente = $_GET['id'] ?? 0;
if (!$id_cliente) {
    die("ID de cliente no válido.");
}

// VAL-PLD-001: Verificar habilitación PLD antes de permitir edición
require_once __DIR__ . '/config/pld_validation.php';
require_once __DIR__ . '/config/pld_middleware.php';

$isPLDHabilitado = checkHabilitadoPLD($pdo);
if (!$isPLDHabilitado) {
    $validationResult = validatePatronPLD($pdo);
    include 'templates/header.php';
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
            </div>
        </div>
    </div>
    <?php include 'templates/footer.php'; ?>
    <?php exit; }

include 'templates/header.php'; 
?>
<title>Editar Cliente - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/cliente_editar.css">
</head>
<body>

<?php $is_sub_page = true; // Show "Back" button
      include 'templates/top_bar.php'; ?>

<!-- WIZARD -->
<div class="wizard-card">
        <form id="editClientForm" enctype="multipart/form-data">
            <!-- Add the client ID as a hidden field -->
            <input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>">
            
            <!-- SECTION 1: General Info -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fa-solid fa-info-circle"></i>
                    Información General
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <i class="fa-solid fa-user-tag"></i>
                            Tipo de Persona
                        </label>
                        <!-- Tipo Persona is disabled on edit -->
                        <select id="tipoPersona" name="id_tipo_persona" class="form-select" required disabled></select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <i class="fa-solid fa-file-contract"></i>
                            No. Contrato*
                        </label>
                        <input type="text" class="form-control" id="no_contrato" name="no_contrato" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <i class="fa-solid fa-tag"></i>
                            Alias
                        </label>
                        <input type="text" class="form-control" id="alias" name="alias">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <i class="fa-solid fa-calendar-plus"></i>
                            Fecha Apertura*
                        </label>
                        <input type="date" class="form-control" id="fecha_apertura" name="fecha_apertura" required>
                    </div>
                    
                    <!-- Estatus Fields -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">
                            <i class="fa-solid fa-circle-check"></i>
                            Estatus*
                        </label>
                        <select id="id_status" name="id_status" class="form-select" required>
                            <option value="1">Activo</option>
                            <option value="2">Pendiente</option>
                            <option value="0">Inactivo</option>
                            <option value="3">Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3" id="fechaBajaContainer" style="display: none;">
                        <label class="form-label">
                            <i class="fa-solid fa-calendar-times"></i>
                            Fecha de Cancelación*
                        </label>
                        <input type="date" class="form-control" id="fecha_baja" name="fecha_baja">
                    </div>
                    <!-- End Estatus Fields -->
                </div>
            </div>

            <!-- SECTION 2: Detalle Persona (Dynamic) -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fa-solid fa-user-shield"></i>
                    Detalles de Persona
                </div>
                <!-- SECCIÓN FÍSICA -->
                <div id="persona-fisica" class="persona-specific">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-user"></i>
                                Nombre*
                            </label>
                            <input type="text" class="form-control" id="fisica_nombre" name="fisica_nombre">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-user"></i>
                                Apellido Paterno*
                            </label>
                            <input type="text" class="form-control" id="fisica_ap_paterno" name="fisica_ap_paterno">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-user"></i>
                                Apellido Materno
                            </label>
                            <input type="text" class="form-control" id="fisica_ap_materno" name="fisica_ap_materno">
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
                            <input type="text" class="form-control" id="fisica_tax_id" name="fisica_tax_id">
                            <input type="file" class="form-control form-control-sm mt-1" name="fisica_rfc_doc_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Constancia RFC / Tax ID">
                            <div class="form-text"><i class="fa-solid fa-paperclip me-1"></i>Adjuntar constancia RFC / Tax ID</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-id-badge"></i>
                                CURP
                            </label>
                            <input type="text" class="form-control" id="fisica_curp" name="fisica_curp">
                            <input type="file" class="form-control form-control-sm mt-1" name="fisica_curp_doc_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Documento CURP">
                            <div class="form-text"><i class="fa-solid fa-paperclip me-1"></i>Adjuntar documento CURP</div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><i class="fa-solid fa-passport me-1"></i>Tipo de residencia</label>
                            <select class="form-select" id="id_tipo_residencia" name="id_tipo_residencia">
                                <option value="">-- Seleccione --</option>
                            </select>
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-building"></i>
                                Razón Social*
                            </label>
                            <input type="text" class="form-control" id="moral_razon_social" name="moral_razon_social">
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
                            <input type="text" class="form-control" id="moral_tax_id" name="moral_tax_id">
                            <input type="file" class="form-control form-control-sm mt-1" name="moral_rfc_doc_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" title="Constancia RFC / Tax ID">
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
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fa-solid fa-landmark me-1"></i>Incluido en Anexo 7 Bis-A</label>
                            <select class="form-select" id="moral_id_anexo_7_bis_a" name="moral_id_anexo_7_bis_a">
                                <option value="">-- No aplica --</option>
                            </select>
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
                            <input type="text" class="form-control" id="fide_numero" name="fide_numero">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fa-solid fa-building-columns"></i>
                                Institución Fiduciaria*
                            </label>
                            <input type="text" class="form-control" id="fide_institucion" name="fide_institucion">
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
                <div id="apoderados-list">
                    <!-- Apoderados will be added here by JS -->
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addApoderado">
                    <i class="fa-solid fa-plus me-2"></i>Agregar Apoderado
                </button>
            </div>

            <!-- SECTION 3: Identificación y Contacto -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fa-solid fa-address-card"></i>
                    Identificación y Contacto
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

            <!-- SECTION: Clasificación de Riesgo PLD -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fa-solid fa-shield-halved me-1"></i>
                    Clasificación de Riesgo (Art. 17 RCG)
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="clasificacion_bajo_riesgo" name="clasificacion_bajo_riesgo" value="1">
                            <label class="form-check-label" for="clasificacion_bajo_riesgo">Cliente clasificado como de bajo riesgo</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2" id="manual_politicas_container" style="display:none;">
                        <label class="form-label"><i class="fa-solid fa-book me-1"></i>Manual de Políticas (versión)</label>
                        <select class="form-select" id="id_manual_politicas_clasificacion" name="id_manual_politicas_clasificacion">
                            <option value="">-- Seleccione versión --</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Documentos -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fa-solid fa-file-check"></i>
                    Documentos (KYC)
                </div>
                <div id="documentos-requeridos-hint" class="alert alert-info mb-3" style="display:none;">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    <strong>Documentos requeridos según tu perfil (KYC - Art. 12 RCG):</strong>
                    <ul id="documentos-requeridos-list" class="mb-0 mt-2 small"></ul>
                </div>
                <div id="documentos-list"></div>
                <button type="button" class="btn btn-sm btn-outline-success" id="addDocumento">
                    <i class="fa-solid fa-plus me-2"></i>Agregar Documento
                </button>
            </div>

            <!-- SECTION 5: Beneficiario Controlador (VAL-PLD-007) -->
            <div class="form-section" id="beneficiario-controlador-section" style="display: none;">
                <div class="section-title">
                    <i class="fa-solid fa-users me-2"></i>
                    Beneficiario Controlador (VAL-PLD-007 / VAL-PLD-015)
                </div>
                <div class="alert alert-info">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    <small>Obligatorio para Personas Morales y Fideicomisos. Permite identificar a los beneficiarios controladores del cliente.</small>
                </div>
                <div id="beneficiarios-controladores-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addBeneficiario" onclick="addBeneficiarioItem()">
                    <i class="fa-solid fa-plus me-2"></i>Agregar Beneficiario Controlador
                </button>
            </div>
            
            <div class="text-end my-4">
                <a href="cliente_detalle.php?id=<?php echo $id_cliente; ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-times me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-save me-2"></i>Actualizar Cliente
                </button>
            </div>
        </form>
    </div>

    <!-- Page-specific JS -->
    <script>
        // Pass client ID from PHP to JavaScript
        window.clientId = <?php echo $id_cliente; ?>;
    </script>
    <script src="assets/js/cliente_editar.js"></script>

<?php include 'templates/footer.php'; ?>
