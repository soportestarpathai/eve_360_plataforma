<?php 
$id_cliente = $_GET['id'] ?? 0;
if (!$id_cliente) {
    die("ID de cliente no válido.");
}
include 'templates/header.php'; 
?>
<title>Editar Cliente - Investor MLP</title>
<style>
    .wizard-card { max-width: 900px; margin: 2rem auto; }
    .form-section { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .section-title { font-size: 1.1rem; font-weight: 600; color: #0d6efd; border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 1.5rem; }
        .dynamic-list-item { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; background: #f9f9f9; padding: 10px; border-radius: 6px; }
        .dynamic-list-item .form-control, .dynamic-list-item .form-select { flex: 1; }
    .persona-specific { display: none; } /* Hidden by default */
</style>
</head>
<body>

<?php $is_sub_page = true; // Show "Back" button
      include 'templates/top_bar.php'; ?>

<!-- WIZARD -->
<div class="wizard-card">
        <form id="editClientForm">
            <!-- Add the client ID as a hidden field -->
            <input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>">
            
            <!-- SECTION 1: General Info -->
            <div class="form-section">
                <div class="section-title">Información General</div>
                <!-- THIS ROW WAS INCOMPLETE -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipo de Persona</label>
                        <!-- Tipo Persona is disabled on edit -->
                        <select id="tipoPersona" name="id_tipo_persona" class="form-select" required disabled></select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">No. Contrato*</label>
                        <input type="text" class="form-control" id="no_contrato" name="no_contrato" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Alias</label>
                        <input type="text" class="form-control" id="alias" name="alias">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha Apertura*</label>
                        <input type="date" class="form-control" id="fecha_apertura" name="fecha_apertura" required>
                    </div>
                    
                    <!-- Estatus Fields (These were correct) -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estatus*</label>
                        <select id="id_status" name="id_status" class="form-select" required>
                            <option value="1">Activo</option>
                            <option value="2">Pendiente</option>
                            <option value="0">Inactivo</option>
                            <option value="3">Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3" id="fechaBajaContainer" style="display: none;">
                        <label class="form-label">Fecha de Cancelación*</label>
                        <input type="date" class="form-control" id="fecha_baja" name="fecha_baja">
                    </div>
                    <!-- End Estatus Fields -->
                </div>
            </div>

            <!-- SECTION 2: Detalle Persona (Dynamic) -->
            <div class="form-section">
                <div class="section-title">Detalles de Persona</div>
                <!-- SECCIÓN FÍSICA -->
                <div id="persona-fisica" class="persona-specific">
                    <div class="row">
                        <div class="col-md-4 mb-3"><label>Nombre*</label><input type="text" class="form-control" id="fisica_nombre" name="fisica_nombre"></div>
                        <div class="col-md-4 mb-3"><label>Apellido Paterno*</label><input type="text" class="form-control" id="fisica_ap_paterno" name="fisica_ap_paterno"></div>
                        <div class="col-md-4 mb-3"><label>Apellido Materno</label><input type="text" class="form-control" id="fisica_ap_materno" name="fisica_ap_materno"></div>
                        <div class="col-md-4 mb-3"><label>Fecha Nacimiento*</label><input type="date" class="form-control" id="fisica_fecha_nacimiento" name="fisica_fecha_nacimiento"></div>
                        <div class="col-md-4 mb-3"><label>RFC*</label><input type="text" class="form-control" id="fisica_tax_id" name="fisica_tax_id"></div>
                        <div class="col-md-4 mb-3"><label>CURP</label><input type="text" class="form-control" id="fisica_curp" name="fisica_curp"></div>
                    </div>
                </div>
                <!-- SECCIÓN MORAL -->
                <div id="persona-moral" class="persona-specific">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label>Razón Social*</label><input type="text" class="form-control" id="moral_razon_social" name="moral_razon_social"></div>
                        <div class="col-md-6 mb-3"><label>Fecha Constitución*</label><input type="date" class="form-control" id="moral_fecha_constitucion" name="moral_fecha_constitucion"></div>
                        <div class="col-md-6 mb-3"><label>RFC*</label><input type="text" class="form-control" id="moral_tax_id" name="moral_tax_id"></div>
                    </div>
                </div>
                <!-- SECCIÓN FIDEICOMISO -->
                <div id="persona-fideicomiso" class="persona-specific">
                     <div class="row">
                        <div class="col-md-6 mb-3"><label>Número Fideicomiso*</label><input type="text" class="form-control" id="fide_numero" name="fide_numero"></div>
                        <div class="col-md-6 mb-3"><label>Institución Fiduciaria*</label><input type="text" class="form-control" id="fide_institucion" name="fide_institucion"></div>
                    </div>
                </div>
            </div>
            
            <!-- NEW APODERADOS SECTION (Same as cliente_nuevo.php) -->
            <div id="apoderados-section" class="form-section mt-4" style="display: none;">
                <div class="section-title">Apoderados / Representantes Legales</div>
                <div id="apoderados-list">
                    <!-- Apoderados will be added here by JS -->
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addApoderado">
                    <i class="fa-solid fa-plus"></i> Agregar Apoderado
                </button>
            </div>

            <!-- SECTION 3: Identificación y Contacto -->
            <div class="form-section">
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

            <!-- SECTION 4: Documentos -->
            <div class="form-section">
                <div class="section-title">Documentos (KYC)</div>
                <div id="documentos-list"></div>
                <button type="button" class="btn btn-sm btn-outline-success" id="addDocumento"><i class="fa-solid fa-plus"></i> Agregar Documento</button>
            </div>
            
            <div class="text-end my-4">
                <a href="cliente_detalle.php?id=<?php echo $id_cliente; ?>" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-save"></i> Actualizar Cliente</button>
            </div>
        </form>
    </div>

    <!-- Page-specific JS -->
    <script>
        const clientId = <?php echo $id_cliente; ?>;
        let catalogs = {}; // Holds all DB catalogs
        let personaTypes = []; // Specific map for persona types
        let apoderadoCounter = 0; // Counter for unique apoderado form fields

        // (Hardcoded list removed here)

        // --- ON PAGE LOAD ---
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                // Fetch catalogs and client details in parallel
                const [catalogsRes, clientRes] = await Promise.all([
                    fetch('api/get_catalogs.php'),
                    fetch(`api/get_client_details.php?id=${clientId}`)
                ]);

                const catalogJson = await catalogsRes.json();
                const clientJson = await clientRes.json();

                if (catalogJson.status !== 'success' || clientJson.status !== 'success') {
                    throw new Error('Failed to load data.');
                }

                catalogs = catalogJson.data;
                personaTypes = catalogs.tipos_persona;
                
                // Populate the form with the fetched data
                populateForm(clientJson.data);

                // Setup dynamic list builders
                setupDynamicBuilders();
                
                // --- NEW: Status Change Logic ---
                document.getElementById('id_status').addEventListener('change', function(e) {
                    const status = e.target.value;
                    const bajaContainer = document.getElementById('fechaBajaContainer');
                    const bajaInput = bajaContainer.querySelector('input');
                    
                    if (status == '3') { // '3' is Cancelado
                        bajaContainer.style.display = 'block';
                        bajaInput.required = true;
                    } else {
                        bajaContainer.style.display = 'none';
                        bajaInput.required = false;
                        // Don't null the value on edit, in case they mis-clicked
                    }
                });

            } catch (error) {
                alert('Error al cargar datos: ' + error.message);
            }
        });
        
        function populateForm(data) {
            // 1. Populate General Info
            document.getElementById('no_contrato').value = data.general.no_contrato;
            document.getElementById('alias').value = data.general.alias;
            document.getElementById('fecha_apertura').value = data.general.fecha_apertura;

            // --- NEW: Populate Status ---
            document.getElementById('id_status').value = data.general.id_status;
            if (data.general.id_status == '3') {
                document.getElementById('fechaBajaContainer').style.display = 'block';
                document.getElementById('fecha_baja').value = data.general.fecha_baja;
            }
            // --- END NEW ---

            // 2. Populate and disable Tipo Persona
            populateSelect('tipoPersona', personaTypes, 'id_tipo_persona', 'nombre', data.general.id_tipo_persona);
            
            // 3. Show and Populate Persona Specific section
            const persona = personaTypes.find(p => p.id_tipo_persona == data.general.id_tipo_persona);
            if (persona) {
                if (persona.es_fisica > 0) {
                    document.getElementById('persona-fisica').style.display = 'block';
                    document.getElementById('fisica_nombre').value = data.persona.nombre;
                    document.getElementById('fisica_ap_paterno').value = data.persona.apellido_paterno;
                    document.getElementById('fisica_ap_materno').value = data.persona.apellido_materno;
                    document.getElementById('fisica_fecha_nacimiento').value = data.persona.fecha_nacimiento;
                    document.getElementById('fisica_tax_id').value = data.persona.tax_id;
                    document.getElementById('fisica_curp').value = data.persona.CURP;
                } else if (persona.es_moral > 0) {
                    document.getElementById('persona-moral').style.display = 'block';
                    document.getElementById('apoderados-section').style.display = 'block'; // SHOW
                    document.getElementById('moral_razon_social').value = data.persona.razon_social;
                    document.getElementById('moral_fecha_constitucion').value = data.persona.fecha_constitucion;
                    document.getElementById('moral_tax_id').value = data.persona.tax_id;
                } else if (persona.es_fideicomiso > 0) {
                    document.getElementById('persona-fideicomiso').style.display = 'block';
                    document.getElementById('apoderados-section').style.display = 'block'; // SHOW
                    document.getElementById('fide_numero').value = data.persona.numero_fideicomiso;
                    document.getElementById('fide_institucion').value = data.persona.institucion_fiduciaria;
                }
            }

            // 4. Re-build dynamic lists
            data.nacionalidades.forEach(n => addDynamicItem('nacionalidades-list', n));
            data.identificaciones.forEach(i => addDynamicItem('identificaciones-list', i));
            data.direcciones.forEach(d => addDynamicItem('direcciones-list', d));
            data.contactos.forEach(c => addDynamicItem('contactos-list', c));
            data.documentos.forEach(d => addDocumentoItem(d));
            
            // --- NEW: Re-build Apoderados list ---
            data.apoderados.forEach(a => addApoderadoItem(a));
        }
        
        function setupDynamicBuilders() {
            document.getElementById('addNacionalidad').addEventListener('click', () => addDynamicItem('nacionalidades-list'));
            document.getElementById('addIdentificacion').addEventListener('click', () => addDynamicItem('identificaciones-list'));
            document.getElementById('addDireccion').addEventListener('click', () => addDynamicItem('direcciones-list'));
            document.getElementById('addContacto').addEventListener('click', () => addDynamicItem('contactos-list'));
            document.getElementById('addDocumento').addEventListener('click', () => addDocumentoItem());
            
            // --- NEW: Apoderado Builder ---
            document.getElementById('addApoderado').addEventListener('click', () => addApoderadoItem());
        }

        // Helper to populate a <select>
        function populateSelect(elementId, data, valueField, labelField, selectedValue) {
            const select = document.getElementById(elementId);
            select.innerHTML = '<option value="">-- Seleccione --</option>';
            data.forEach(item => {
                const selected = (item[valueField] == selectedValue) ? 'selected' : '';
                select.innerHTML += `<option value="${item[valueField]}" ${selected}>${item[labelField]}</option>`;
            });
        }

        // --- DYNAMIC ITEM TEMPLATES (Now with pre-fill) ---
        function addDynamicItem(listId, data = {}) {
            const list = document.getElementById(listId);
            const item = document.createElement('div');
            item.className = 'dynamic-list-item'; 
            item.style.display = 'flex';
            item.style.alignItems = 'center';
            item.style.marginBottom = '10px';
            
            let html = '';
            
            // 1. BUILD HTML BASED ON LIST TYPE
            if (listId === 'nacionalidades-list') {
                // Safe check for the value (handle string vs number)
                const targetVal = data.id_pais || ''; 
                
                const options = catalogs.paises.map(p => {
                    // Loose comparison (==) handles string "1" vs number 1
                    const isSelected = (p.id_pais == targetVal) ? 'selected' : '';
                    return `<option value="${p.id_pais}" ${isSelected}>${p.nombre}</option>`;
                }).join('');
                
                html = `<select class="form-select" name="nacionalidad_id[]">
                            <option value="">Seleccione...</option>
                            ${options}
                        </select>`;

            } else if (listId === 'identificaciones-list') {
                const targetVal = data.id_tipo_identificacion || '';
                const options = catalogs.tipos_identificacion.map(t => {
                    const isSelected = (t.id_tipo_identificacion == targetVal) ? 'selected' : '';
                    return `<option value="${t.id_tipo_identificacion}" ${isSelected}>${t.nombre}</option>`;
                }).join('');
                
                html = `<select class="form-select" name="ident_tipo[]">${options}</select>
                        <input type="text" class="form-control" name="ident_numero[]" placeholder="Número" value="${data.numero_identificacion || ''}">
                        <input type="date" class="form-control" name="ident_vencimiento[]" placeholder="Vencimiento" value="${data.fecha_vencimiento || ''}">`;

            } else if (listId === 'direcciones-list') {
                html = `<input type="text" class="form-control" name="dir_calle[]" placeholder="Calle y Número" value="${data.calle || ''}">
                        <input type="text" class="form-control" name="dir_colonia[]" placeholder="Colonia" value="${data.colonia || ''}">
                        <input type="text" class="form-control" name="dir_cp[]" placeholder="C.P." value="${data.codigo_postal || ''}">`;

            } else if (listId === 'contactos-list') {
                const targetVal = data.id_tipo_contacto || '';
                const options = catalogs.tipos_contacto.map(t => {
                    const isSelected = (t.id_tipo_contacto == targetVal) ? 'selected' : '';
                    return `<option value="${t.id_tipo_contacto}" ${isSelected}>${t.nombre}</option>`;
                }).join('');
                
                html = `<select class="form-select" name="contacto_id_tipo[]">${options}</select>
                        <input type="text" class="form-control" name="contacto_valor[]" placeholder="Dato" value="${data.dato_contacto || ''}">`;
            }

            // 2. INSERT HTML
            item.innerHTML = `
                <div class="row w-100 g-2 align-items-center">${html}</div>
                <button type="button" class="btn btn-sm btn-danger ms-2" onclick="this.parentElement.remove()" style="width: auto;">
                    <i class="fa-solid fa-trash"></i>
                </button>
            `;
            list.appendChild(item);

            // 3. FORCE VALUE (Safety Net)
            // If the HTML 'selected' attribute failed for any reason, this forces the browser to select the right value.
            if (listId === 'nacionalidades-list' && data.id_pais) {
                item.querySelector('select').value = data.id_pais;
            }
            else if (listId === 'identificaciones-list' && data.id_tipo_identificacion) {
                item.querySelector('select').value = data.id_tipo_identificacion;
            }
            else if (listId === 'contactos-list' && data.id_tipo_contacto) {
                item.querySelector('select').value = data.id_tipo_contacto;
            }
        }
        // --- NEW/UPDATED: TEMPLATE FOR APODERADO ---
        function addApoderadoItem(data = {}) {
            const list = document.getElementById('apoderados-list');
            const item = document.createElement('div');
            const index = apoderadoCounter++; // Unique index for form names
            
            item.className = 'border p-3 rounded mb-3 bg-light';
            
            const tipoPersonaOptions = catalogs.tipos_persona
                .filter(p => p.es_fisica > 0 || p.es_moral > 0)
                .map(p => `<option value="${p.id_tipo_persona}" ${p.id_tipo_persona == data.id_tipo_persona ? 'selected' : ''}>${p.nombre}</option>`)
                .join('');
            
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Apoderado</h6>
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.border').remove()"><i class="fa-solid fa-trash"></i></button>
                </div>
                
                <!-- Apoderado Tipo Persona -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small">Tipo de Apoderado*</label>
                        <select class="form-select" name="apoderado[${index}][id_tipo_persona]" onchange="toggleApoderadoType(this, ${index})" required>
                            <option value="">-- Seleccione --</option>
                            ${tipoPersonaOptions}
                        </select>
                    </div>
                </div>

                <!-- Apoderado Física Fields -->
                <div id="apoderado-fisica-${index}" class="apoderado-specific" style="display:none;">
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="small">Nombre*</label><input type="text" class="form-control" name="apoderado[${index}][fisica_nombre]" value="${data.persona_data?.nombre || ''}"></div>
                        <div class="col-md-4 mb-3"><label class="small">Apellido Paterno*</label><input type="text" class="form-control" name="apoderado[${index}][fisica_ap_paterno]" value="${data.persona_data?.apellido_paterno || ''}"></div>
                        <div class="col-md-4 mb-3"><label class="small">Apellido Materno</label><input type="text" class="form-control" name="apoderado[${index}][fisica_ap_materno]" value="${data.persona_data?.apellido_materno || ''}"></div>
                        <div class="col-md-6 mb-3"><label class="small">RFC*</label><input type="text" class="form-control" name="apoderado[${index}][fisica_tax_id]" value="${data.persona_data?.tax_id || ''}"></div>
                        <div class="col-md-6 mb-3"><label class="small">CURP</label><input type="text" class="form-control" name="apoderado[${index}][fisica_curp]" value="${data.persona_data?.CURP || ''}"></div>
                    </div>
                </div>
                
                <!-- Apoderado Moral Fields -->
                <div id="apoderado-moral-${index}" class="apoderado-specific" style="display:none;">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="small">Razón Social*</label><input type="text" class="form-control" name="apoderado[${index}][moral_razon_social]" value="${data.persona_data?.razon_social || ''}"></div>
                        <div class="col-md-6 mb-3"><label class="small">RFC*</label><input type="text" class="form-control" name="apoderado[${index}][moral_tax_id]" value="${data.persona_data?.tax_id || ''}"></div>
                    </div>
                </div>

                <!-- Apoderado Contactos -->
                <hr>
                <div class="mb-2"><label class="form-label small fw-bold">Contactos del Apoderado</label></div>
                <div id="apoderado-contactos-list-${index}"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addApoderadoContactoItem(${index})">
                    <i class="fa-solid fa-plus"></i> Agregar Contacto (Apoderado)
                </button>
            `;
            list.appendChild(item);
            
            // Pre-fill contacts for this apoderado
            if (data.contactos) {
                data.contactos.forEach(c => addApoderadoContactoItem(index, c));
            }

            // Manually trigger the display logic
            toggleApoderadoType(item.querySelector('select'), index);
        }

        // --- NEW: TEMPLATE FOR APODERADO CONTACT ---
        function addApoderadoContactoItem(apoderadoIndex, data = {}) {
            const list = document.getElementById(`apoderado-contactos-list-${apoderadoIndex}`);
            const item = document.createElement('div');
            item.className = 'dynamic-list-item';
            
            // --- UPDATED to use DB Catalog ---
            const options = catalogs.tipos_contacto.map(t => 
                `<option value="${t.id_tipo_contacto}" ${t.id_tipo_contacto == data.id_tipo_contacto ? 'selected' : ''}>${t.nombre}</option>`
            ).join('');

            item.innerHTML = `
                <select class="form-select" name="apoderado[${apoderadoIndex}][contactos][tipo][]">${options}</select>
                <input type="text" class="form-control" name="apoderado[${apoderadoIndex}][contactos][valor][]" placeholder="Dato de Contacto" value="${data.dato_contacto || ''}">
                <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button>
            `;
            list.appendChild(item);
        }

        // --- NEW: TOGGLE LOGIC FOR APODERADO SUB-FORM ---
        function toggleApoderadoType(selectElement, index) {
            const selectedId = selectElement.value;
            const persona = catalogs.tipos_persona.find(p => p.id_tipo_persona == selectedId);
            
            // Hide all
            document.getElementById(`apoderado-fisica-${index}`).style.display = 'none';
            document.getElementById(`apoderado-moral-${index}`).style.display = 'none';

            if (persona) {
                if (persona.es_fisica > 0) document.getElementById(`apoderado-fisica-${index}`).style.display = 'block';
                if (persona.es_moral > 0) document.getElementById(`apoderado-moral-${index}`).style.display = 'block';
            }
        }
        
        function addDocumentoItem(data = {}) {
            const list = document.getElementById('documentos-list');
            const item = document.createElement('div');
            item.className = 'dynamic-list-item row g-2 align-items-center mb-2'; // Added align-items-center for vertical alignment

            let fileInput = '<input type="file" class="form-control" name="doc_file[]">';
            
            if (data.ruta && data.ruta.trim() !== '') {
                // --- FIX: Clean the path (remove '../') ---
                const cleanPath = data.ruta.replace('../', '');
                
                // Layout: Show a "View" button next to the file input (for replacing)
                fileInput = `
                    <div class="input-group">
                        <a href="${cleanPath}" target="_blank" class="btn btn-outline-primary" title="Ver Archivo actual">
                            <i class="fa-regular fa-eye"></i>
                        </a>
                        <input type="file" class="form-control" name="doc_file[]" title="Subir nuevo para reemplazar">
                    </div>
                `;
            }

            item.innerHTML = `
                <div class="col-md-4">
                    <input type="text" class="form-control" name="doc_tipo[]" placeholder="Tipo de Documento" value="${data.descripcion || ''}" readonly>
                </div>
                <div class="col-md-5">
                    ${fileInput}
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="doc_vencimiento[]" value="${data.fecha_vencimiento || ''}">
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-danger" onclick="this.closest('.dynamic-list-item').remove()">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            `;
            list.appendChild(item);
        }

        // --- FORM SUBMIT ---
        document.getElementById('editClientForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            // Re-enable disabled fields for submission
            document.getElementById('tipoPersona').disabled = false;
            formData.append('id_tipo_persona', document.getElementById('tipoPersona').value);
            document.getElementById('tipoPersona').disabled = true;

            fetch('api/update_client.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Cliente actualizado con éxito.');
                    window.location.href = `cliente_detalle.php?id=${clientId}`;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => {
                alert('Error de conexión. ' + err.message);
            });
        });

    </script>

<?php include 'templates/footer.php'; ?>