<?php include 'templates/header.php'; ?>
<title>Nuevo Cliente - Investor MLP</title>
<style>
    .wizard-card { max-width: 900px; margin: 2rem auto; }
    .step { display: none; }
    .step.active { display: block; }
    .form-section { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .section-title { font-size: 1.1rem; font-weight: 600; color: #0d6efd; border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 1.5rem; }
    .dynamic-list-item { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; background: #f9f9f9; padding: 10px; border-radius: 6px; }
    .dynamic-list-item .form-control, .dynamic-list-item .form-select { flex: 1; }
    .persona-specific { display: none; } /* Hidden by default */
    
    /* Status Badge for Validation */
    #validationStatus { font-weight: 600; margin-right: 15px; }
    .text-ok { color: #198754; }
    .text-risk { color: #dc3545; }
    .text-warning { color: #ffc107; }
    
    /* Modal Styles */
    .hit-row { border-bottom: 1px solid #eee; padding: 10px; cursor: pointer; transition: background 0.2s; }
    .hit-row:hover { background: #f8f9fa; }
</style>
</head>
<body>

<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="wizard-card">
    <form id="newClientForm">
        
        <!-- STEP 1 -->
        <div id="step-1" class="step active">
            <div class="form-section">
                <div class="section-title">Paso 1: Información General</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipo de Persona*</label>
                        <select id="tipoPersona" name="id_tipo_persona" class="form-select" required></select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">No. Contrato*</label>
                        <input type="text" class="form-control" name="no_contrato" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Alias</label>
                        <input type="text" class="form-control" name="alias">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha Apertura*</label>
                        <input type="date" class="form-control" name="fecha_apertura" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estatus*</label>
                        <select id="id_status" name="id_status" class="form-select" required>
                            <option value="1">Activo</option>
                            <option value="2" selected>Pendiente</option>
                            <option value="0">Inactivo</option>
                            <option value="3">Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3" id="fechaBajaContainer" style="display: none;">
                        <label class="form-label">Fecha de Cancelación*</label>
                        <input type="date" class="form-control" name="fecha_baja">
                    </div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button type="button" class="btn btn-primary" onclick="nextStep(2)">Siguiente <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>

        <!-- STEP 2: Validation & Details -->
        <div id="step-2" class="step">
            <div class="form-section">
                <div class="section-title">Paso 2: Detalles de Persona y PLD</div>

                <!-- VALIDATION STATUS ROW -->
                <div class="alert alert-light border d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <strong>Estatus PLD:</strong> 
                        <span id="validationStatus" class="text-muted">Pendiente de validación</span>
                    </div>
                    <button type="button" class="btn btn-warning btn-sm" onclick="validatePerson(false)">
                        <i class="fa-solid fa-shield-halved"></i> Validar en Listas
                    </button>
                </div>

                <!-- SECCIÓN FÍSICA (Added onblur events) -->
                <div id="persona-fisica" class="persona-specific">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Nombre*</label>
                            <input type="text" class="form-control" id="fisica_nombre" name="fisica_nombre" onblur="validatePerson(true)">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Apellido Paterno*</label>
                            <input type="text" class="form-control" id="fisica_ap_paterno" name="fisica_ap_paterno" onblur="validatePerson(true)">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Apellido Materno</label>
                            <input type="text" class="form-control" id="fisica_ap_materno" name="fisica_ap_materno" onblur="validatePerson(true)">
                        </div>
                        <div class="col-md-4 mb-3"><label>Fecha Nacimiento*</label><input type="date" class="form-control" name="fisica_fecha_nacimiento"></div>
                        <div class="col-md-4 mb-3"><label>RFC*</label><input type="text" class="form-control" name="fisica_tax_id"></div>
                        <div class="col-md-4 mb-3"><label>CURP</label><input type="text" class="form-control" name="fisica_curp"></div>
                    </div>
                </div>
                <!-- SECCIÓN MORAL (Added onblur events) -->
                <div id="persona-moral" class="persona-specific">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Razón Social*</label>
                            <input type="text" class="form-control" id="moral_razon_social" name="moral_razon_social" onblur="validatePerson(true)">
                        </div>
                        <div class="col-md-6 mb-3"><label>Fecha Constitución*</label><input type="date" class="form-control" name="moral_fecha_constitucion"></div>
                        <div class="col-md-6 mb-3"><label>RFC*</label><input type="text" class="form-control" name="moral_tax_id"></div>
                    </div>
                </div>
                <!-- SECCIÓN FIDEICOMISO -->
                <div id="persona-fideicomiso" class="persona-specific">
                     <div class="row">
                        <div class="col-md-6 mb-3"><label>Número Fideicomiso*</label><input type="text" class="form-control" name="fide_numero"></div>
                        <div class="col-md-6 mb-3"><label>Institución Fiduciaria*</label><input type="text" class="form-control" name="fide_institucion"></div>
                    </div>
                </div>
            </div>
            
            <!-- APODERADOS SECTION -->
            <div id="apoderados-section" class="form-section mt-4" style="display: none;">
                <div class="section-title">Apoderados / Representantes Legales</div>
                <div id="apoderados-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addApoderado">
                    <i class="fa-solid fa-plus"></i> Agregar Apoderado
                </button>
            </div>
            
            <div class="text-end mt-3">
                <button type="button" class="btn btn-secondary" onclick="prevStep(1)"><i class="fa-solid fa-arrow-left"></i> Atrás</button>
                <button type="button" class="btn btn-primary" id="btnStep2Next" onclick="nextStep(3)">Siguiente <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>

        <!-- STEP 3 -->
        <div id="step-3" class="step">
             <div class="form-section">
                <div class="section-title">Identificación y Contacto</div>
                <div class="section-title">Nacionalidades</div>
                <div id="nacionalidades-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addNacionalidad"><i class="fa-solid fa-plus"></i> Agregar Nacionalidad</button>
                <hr class="my-4">
                <div class="section-title">Identificaciones</div>
                <div id="identificaciones-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addIdentificacion"><i class="fa-solid fa-plus"></i> Agregar Identificación</button>
                <hr class="my-4">
                <div class="section-title">Direcciones</div>
                <div id="direcciones-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addDireccion"><i class="fa-solid fa-plus"></i> Agregar Dirección</button>
                <hr class="my-4">
                <div class="section-title">Contactos</div>
                <div id="contactos-list"></div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addContacto"><i class="fa-solid fa-plus"></i> Agregar Contacto</button>
            </div>
             <div class="text-end mt-3">
                <button type="button" class="btn btn-secondary" onclick="prevStep(2)"><i class="fa-solid fa-arrow-left"></i> Atrás</button>
                <button type="button" class="btn btn-primary" onclick="nextStep(4)">Siguiente <i class="fa-solid fa-arrow-right"></i></button>
            </div>
        </div>

        <!-- STEP 4 -->
        <div id="step-4" class="step">
            <div class="form-section">
                <div class="section-title">Paso 4: Documentos (KYC)</div>
                <div id="documentos-list"></div>
                <button type="button" class="btn btn-sm btn-outline-success" id="addDocumento"><i class="fa-solid fa-plus"></i> Agregar Documento</button>
            </div>
            <div class="text-end mt-3">
                <button type="button" class="btn btn-secondary" onclick="prevStep(3)"><i class="fa-solid fa-arrow-left"></i> Atrás</button>
                <button type="submit" class="btn btn-success" id="btnSaveClient"><i class="fa-solid fa-save"></i> Guardar Cliente</button>
            </div>
        </div>

    </form>
</div>

<!-- PLD SELECTION MODAL (Copied from cliente_detalle) -->
<div class="modal fade" id="pldModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Resultados de Búsqueda PLD</h5>
                <!-- No close button to force selection -->
            </div>
            <div class="modal-body">
                <div id="pldLoading" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2">Consultando listas...</p></div>
                <div id="pldResults" style="display:none;">
                    <div class="alert alert-warning small">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Se encontraron posibles coincidencias. Por favor, <strong>seleccione la coincidencia correcta</strong> o indique que no corresponde.
                    </div>
                    <div id="hitsContainer" class="border rounded mb-3" style="max-height: 300px; overflow-y: auto;"></div>
                    
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
<script>
    let catalogs = {};
    let personaTypes = [];
    let apoderadoCounter = 0;
    let currentStep = 1;
    let isValidatedPLD = false; // Flag to control submission
    let currentSearchId = null;

    // --- 1. VALIDATION LOGIC ---
    async function validatePerson(isAutomatic = false) {
        const statusSpan = document.getElementById('validationStatus');
        
        // Gather data based on visible section
        let data = {};
        const isFisica = document.getElementById('persona-fisica').style.display !== 'none';
        const isMoral = document.getElementById('persona-moral').style.display !== 'none';

        if (isFisica) {
            data.nombre = document.getElementById('fisica_nombre').value.trim();
            data.paterno = document.getElementById('fisica_ap_paterno').value.trim();
            data.materno = document.getElementById('fisica_ap_materno').value.trim();
            data.tipo_persona = 'fisica';
            if (isAutomatic && (!data.nombre || !data.paterno)) return; 
        } else if (isMoral) {
            data.nombre = document.getElementById('moral_razon_social').value.trim();
            data.paterno = '';
            data.materno = '';
            data.tipo_persona = 'moral';
            if (isAutomatic && data.nombre.length < 3) return;
        } else {
            return;
        }

        if (!isAutomatic) {
            // Open Modal immediately for manual check
            const modal = new bootstrap.Modal(document.getElementById('pldModal'));
            modal.show();
            document.getElementById('pldLoading').style.display = 'block';
            document.getElementById('pldResults').style.display = 'none';
            document.getElementById('pldClean').style.display = 'none';
            document.getElementById('btnConfirmPld').style.display = 'none';
            document.getElementById('btnCloseClean').style.display = 'none';
        } else {
            statusSpan.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Consultando...';
        }

        data.save_history = true; // Save this search

        try {
            const res = await fetch('api/validate_person.php', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            const result = await res.json();
            currentSearchId = result.id_busqueda;

            if (result.status === 'success') {
                if (result.found) {
                    // RISK FOUND
                    statusSpan.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> COINCIDENCIA EN LISTAS (Requiere Acción)';
                    statusSpan.className = 'text-risk';
                    isValidatedPLD = false; 

                    if (!isAutomatic) {
                        // Show Hits in Modal
                        document.getElementById('pldLoading').style.display = 'none';
                        document.getElementById('pldResults').style.display = 'block';
                        document.getElementById('btnConfirmPld').style.display = 'block';
                        renderHits(result.data);
                    } else {
                        // Auto-check just warns, doesn't open modal yet (user must click "Re-validar")
                        // But we mark it as invalid so they can't proceed
                    }
                } else {
                    // CLEAN
                    statusSpan.innerHTML = '<i class="fa-solid fa-check-circle"></i> Sin coincidencias (Limpio)';
                    statusSpan.className = 'text-ok';
                    isValidatedPLD = true;
                    
                    if (!isAutomatic) {
                        document.getElementById('pldLoading').style.display = 'none';
                        document.getElementById('pldClean').style.display = 'block';
                        document.getElementById('btnCloseClean').style.display = 'block';
                    }
                }
            } else {
                statusSpan.innerHTML = 'Error en consulta';
                if (!isAutomatic) {
                     document.getElementById('pldLoading').innerHTML = '<p class="text-danger">Error: ' + result.message + '</p>';
                }
            }
        } catch (err) {
            console.error(err);
            statusSpan.innerHTML = 'Error de conexión';
        }
    }

    function renderHits(hits) {
        const container = document.getElementById('hitsContainer');
        container.innerHTML = '';
        hits.forEach((hit, index) => {
            const div = document.createElement('div');
            div.className = 'hit-row';
            div.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="pldSelection" id="hit_${index}" value='${JSON.stringify(hit)}'>
                    <label class="form-check-label w-100" for="hit_${index}">
                        <div class="fw-bold">${hit.nombreCompleto}</div>
                        <div class="small text-muted">Lista: ${hit.lista} | Entidad: ${hit.entidad || 'N/A'} | Score: ${hit.porcentaje}%</div>
                    </label>
                </div>
            `;
            container.appendChild(div);
        });
    }

    function confirmPld() {
        const selected = document.querySelector('input[name="pldSelection"]:checked');
        if (!selected) { alert("Debe seleccionar un resultado o la opción 'Ninguna'."); return; }
        
        const comments = document.getElementById('pldComments').value;
        if (selected.value === 'none' && comments.trim().length < 5) {
            alert("Si selecciona 'Ninguna', es obligatorio agregar un comentario.");
            return;
        }

        const payload = {
            id_busqueda: currentSearchId,
            seleccion: selected.value,
            comentarios: comments,
            riesgo_final: (selected.value === 'none') ? 0 : 1
        };

        fetch('api/confirm_pld_selection.php', { method: 'POST', body: JSON.stringify(payload) })
        .then(res => res.json())
        .then(json => {
            if (json.status === 'success') {
                isValidatedPLD = true; // Now allowed to proceed (even if they selected a risk, they acknowledged it)
                document.getElementById('validationStatus').innerHTML = '<i class="fa-solid fa-check-double"></i> Validación Confirmada';
                document.getElementById('validationStatus').className = 'text-success';
                bootstrap.Modal.getInstance(document.getElementById('pldModal')).hide();
            } else {
                alert("Error al guardar selección: " + json.message);
            }
        });
    }

    // --- NAVIGATION CHECKS ---
    function nextStep(step) {
        // Check PLD on Step 2 exit
        if (currentStep === 2 && step === 3) {
            if (!isValidatedPLD) {
               alert("Debe validar al cliente en listas PLD y confirmar el resultado antes de continuar.");
               // Force open manual check if it failed auto-check
               if (document.getElementById('validationStatus').classList.contains('text-risk')) {
                   validatePerson(false);
               }
               return;
            }
        }
        
        document.getElementById(`step-${currentStep}`).classList.remove('active');
        document.getElementById(`step-${step}`).classList.add('active');
        currentStep = step;
        window.scrollTo(0,0);
    }
    
    function prevStep(step) {
        document.getElementById(`step-${currentStep}`).classList.remove('active');
        document.getElementById(`step-${step}`).classList.add('active');
        currentStep = step;
        window.scrollTo(0,0);
    }

    // ... (Rest of Catalog Loading and Dynamic Lists - Same as before) ...
    document.addEventListener('DOMContentLoaded', function() {
        fetch('api/get_catalogs.php').then(r=>r.json()).then(json=>{
            if(json.status==='success') {
                catalogs = json.data;
                personaTypes = catalogs.tipos_persona;
                populateSelect('tipoPersona', personaTypes, 'id_tipo_persona', 'nombre');
            }
        });

        document.getElementById('tipoPersona').addEventListener('change', function(e) {
            const selectedId = e.target.value;
            const persona = personaTypes.find(p => p.id_tipo_persona == selectedId);
            
            document.querySelectorAll('.persona-specific').forEach(el => el.style.display = 'none');
            // Reset validation display when type changes
            isValidatedPLD = false;
            document.getElementById('validationStatus').innerHTML = 'Pendiente';
            document.getElementById('validationStatus').className = 'text-muted';
            
            if (persona) {
                if (persona.es_fisica > 0) document.getElementById('persona-fisica').style.display = 'block';
                if (persona.es_moral > 0) {
                    document.getElementById('persona-moral').style.display = 'block';
                    document.getElementById('apoderados-section').style.display = 'block';
                } else {
                    document.getElementById('apoderados-section').style.display = 'none';
                }
                if (persona.es_fideicomiso > 0) {
                    document.getElementById('persona-fideicomiso').style.display = 'block';
                    document.getElementById('apoderados-section').style.display = 'block';
                } else if (persona.es_moral == 0) {
                    document.getElementById('apoderados-section').style.display = 'none';
                }
            } else {
                document.getElementById('apoderados-section').style.display = 'none';
            }
        });
        
        document.getElementById('id_status').addEventListener('change', function(e) {
            const status = e.target.value;
            const bajaContainer = document.getElementById('fechaBajaContainer');
            const bajaInput = bajaContainer.querySelector('input');
            if (status == '3') {
                bajaContainer.style.display = 'block';
                bajaInput.required = true;
            } else {
                bajaContainer.style.display = 'none';
                bajaInput.required = false;
                bajaInput.value = null;
            }
        });

        document.getElementById('addNacionalidad').addEventListener('click', () => addDynamicItem('nacionalidades-list', 'nacionalidad[]', catalogs.paises, 'id_pais', 'nombre'));
        document.getElementById('addIdentificacion').addEventListener('click', () => addDynamicItem('identificaciones-list', 'identificacion[]', catalogs.tipos_identificacion, 'id_tipo_identificacion', 'nombre'));
        document.getElementById('addDireccion').addEventListener('click', () => addDynamicItem('direcciones-list', 'direccion[]'));
        document.getElementById('addContacto').addEventListener('click', () => addDynamicItem('contactos-list', 'contacto[]'));
        document.getElementById('addDocumento').addEventListener('click', () => addDocumentoItem());
        document.getElementById('addApoderado').addEventListener('click', () => addApoderadoItem());
    });

    function populateSelect(elementId, data, valueField, labelField) {
        const select = document.getElementById(elementId);
        select.innerHTML = '<option value="">-- Seleccione --</option>';
        data.forEach(item => {
            select.innerHTML += `<option value="${item[valueField]}">${item[labelField]}</option>`;
        });
    }

    function addDynamicItem(listId, name, catalogData, catValue, catLabel) {
        const list = document.getElementById(listId);
        const item = document.createElement('div');
        item.className = 'dynamic-list-item';
        let html = '';
        if (listId === 'nacionalidades-list') {
            html = `<select class="form-select" name="nacionalidad_id[]">${catalogs.paises.map(p => `<option value="${p.id_pais}">${p.nombre}</option>`).join('')}</select>`;
        } else if (listId === 'identificaciones-list') {
            html = `<select class="form-select" name="ident_tipo[]">${catalogs.tipos_identificacion.map(t => `<option value="${t.id_tipo_identificacion}">${t.nombre}</option>`).join('')}</select>
                    <input type="text" class="form-control" name="ident_numero[]" placeholder="Número">
                    <input type="date" class="form-control" name="ident_vencimiento[]" placeholder="Vencimiento">`;
        } else if (listId === 'direcciones-list') {
            html = `<input type="text" class="form-control" name="dir_calle[]" placeholder="Calle y Número">
                    <input type="text" class="form-control" name="dir_colonia[]" placeholder="Colonia">
                    <input type="text" class="form-control" name="dir_cp[]" placeholder="C.P.">`;
        } else if (listId === 'contactos-list') {
            const options = catalogs.tipos_contacto.map(t => `<option value="${t.id_tipo_contacto}">${t.nombre}</option>`).join('');
            html = `<select class="form-select" name="contacto_id_tipo[]">${options}</select>
                    <input type="text" class="form-control" name="contacto_valor[]" placeholder="Dato de Contacto">`;
        }
        item.innerHTML = `<div class="row w-100 g-2">${html}</div><button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button>`;
        list.appendChild(item);
    }

    function addApoderadoItem() {
        const list = document.getElementById('apoderados-list');
        const item = document.createElement('div');
        const index = apoderadoCounter++;
        item.className = 'border p-3 rounded mb-3 bg-light';
        item.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Nuevo Apoderado</h6>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.border').remove()"><i class="fa-solid fa-trash"></i></button>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small">Tipo de Apoderado*</label>
                    <select class="form-select" name="apoderado[${index}][id_tipo_persona]" onchange="toggleApoderadoType(this, ${index})" required>
                        <option value="">-- Seleccione --</option>
                        ${catalogs.tipos_persona.filter(p => p.es_fisica > 0 || p.es_moral > 0).map(p => `<option value="${p.id_tipo_persona}">${p.nombre}</option>`).join('')}
                    </select>
                </div>
            </div>
            <div id="apoderado-fisica-${index}" class="apoderado-specific" style="display:none;">
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="small">Nombre*</label><input type="text" class="form-control" name="apoderado[${index}][fisica_nombre]"></div>
                    <div class="col-md-4 mb-3"><label class="small">Apellido Paterno*</label><input type="text" class="form-control" name="apoderado[${index}][fisica_ap_paterno]"></div>
                    <div class="col-md-4 mb-3"><label class="small">Apellido Materno</label><input type="text" class="form-control" name="apoderado[${index}][fisica_ap_materno]"></div>
                    <div class="col-md-6 mb-3"><label class="small">RFC*</label><input type="text" class="form-control" name="apoderado[${index}][fisica_tax_id]"></div>
                    <div class="col-md-6 mb-3"><label class="small">CURP</label><input type="text" class="form-control" name="apoderado[${index}][fisica_curp]"></div>
                </div>
            </div>
            <div id="apoderado-moral-${index}" class="apoderado-specific" style="display:none;">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="small">Razón Social*</label><input type="text" class="form-control" name="apoderado[${index}][moral_razon_social]"></div>
                    <div class="col-md-6 mb-3"><label class="small">RFC*</label><input type="text" class="form-control" name="apoderado[${index}][moral_tax_id]"></div>
                </div>
            </div>
            <hr>
            <div class="mb-2"><label class="form-label small fw-bold">Contactos del Apoderado</label></div>
            <div id="apoderado-contactos-list-${index}"></div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addApoderadoContactoItem(${index})"><i class="fa-solid fa-plus"></i> Agregar Contacto (Apoderado)</button>
        `;
        list.appendChild(item);
    }

    function addApoderadoContactoItem(apoderadoIndex) {
        const list = document.getElementById(`apoderado-contactos-list-${apoderadoIndex}`);
        const item = document.createElement('div');
        item.className = 'dynamic-list-item';
        const options = catalogs.tipos_contacto.map(t => `<option value="${t.id_tipo_contacto}">${t.nombre}</option>`).join('');
        item.innerHTML = `<select class="form-select" name="apoderado[${apoderadoIndex}][contactos][tipo][]">${options}</select>
                <input type="text" class="form-control" name="apoderado[${apoderadoIndex}][contactos][valor][]" placeholder="Dato de Contacto">
                <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button>`;
        list.appendChild(item);
    }

    function toggleApoderadoType(selectElement, index) {
        const selectedId = selectElement.value;
        const persona = catalogs.tipos_persona.find(p => p.id_tipo_persona == selectedId);
        document.getElementById(`apoderado-fisica-${index}`).style.display = 'none';
        document.getElementById(`apoderado-moral-${index}`).style.display = 'none';
        if (persona) {
            if (persona.es_fisica > 0) document.getElementById(`apoderado-fisica-${index}`).style.display = 'block';
            if (persona.es_moral > 0) document.getElementById(`apoderado-moral-${index}`).style.display = 'block';
        }
    }

    function addDocumentoItem() {
        const list = document.getElementById('documentos-list');
        const item = document.createElement('div');
        item.className = 'dynamic-list-item row g-2';
        item.innerHTML = `
            <div class="col-md-4"><input type="text" class="form-control" name="doc_tipo[]" placeholder="Tipo de Documento"></div>
            <div class="col-md-5"><input type="file" class="form-control" name="doc_file[]"></div>
            <div class="col-md-2"><input type="date" class="form-control" name="doc_vencimiento[]"></div>
            <div class="col-md-1"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.dynamic-list-item').remove()"><i class="fa-solid fa-trash"></i></button></div>
        `;
        list.appendChild(item);
    }

    document.getElementById('newClientForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        fetch('api/save_client.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Cliente guardado con éxito. ID: ' + data.id_cliente);
                window.location.href = 'clientes.php';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => { alert('Error de conexión. ' + err.message); });
    });
</script>

<?php include 'templates/footer.php'; ?>