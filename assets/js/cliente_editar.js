/**
 * Cliente Editar Page JavaScript
 * Handles client editing form, dynamic lists, and form submission
 */

// Global Variables
const clientId = window.clientId || 0; // Get from PHP global variable
let catalogs = {}; // Holds all DB catalogs
let personaTypes = []; // Specific map for persona types
let apoderadoCounter = 0; // Counter for unique apoderado form fields

// --- ON PAGE LOAD ---
document.addEventListener('DOMContentLoaded', async function() {
    if (!clientId) {
        alert('ID de cliente no válido');
        return;
    }

    try {
        // Fetch catalogs and client details in parallel
        const [catalogsRes, clientRes] = await Promise.all([
            fetch('api/get_catalogos.php'),
            fetch(`api/get_client_details.php?id=${clientId}`)
        ]);

        // Check if responses are ok before parsing JSON
        if (!catalogsRes.ok) {
            throw new Error('Error al cargar catálogos: HTTP ' + catalogsRes.status);
        }
        if (!clientRes.ok) {
            throw new Error('Error al cargar datos del cliente: HTTP ' + clientRes.status);
        }

        // Get response text first to handle errors
        const catalogText = await catalogsRes.text();
        const clientText = await clientRes.text();

        let catalogJson, clientJson;
        try {
            catalogJson = JSON.parse(catalogText);
        } catch (e) {
            throw new Error('Error al parsear catálogos: ' + catalogText.substring(0, 100));
        }

        try {
            clientJson = JSON.parse(clientText);
        } catch (e) {
            throw new Error('Error al parsear datos del cliente: ' + clientText.substring(0, 100));
        }

        if (catalogJson.status !== 'success' || clientJson.status !== 'success') {
            throw new Error(catalogJson.message || clientJson.message || 'Error al cargar datos');
        }

        catalogs = catalogJson.data;
        personaTypes = catalogs.tipos_persona;
        
        // Populate the form with the fetched data
        populateForm(clientJson.data);

        // Setup dynamic list builders
        setupDynamicBuilders();
        
        // Status Change Logic
        const statusSelect = document.getElementById('id_status');
        if (statusSelect) {
            statusSelect.addEventListener('change', function(e) {
                const status = e.target.value;
                const bajaContainer = document.getElementById('fechaBajaContainer');
                if (!bajaContainer) return;
                
                const bajaInput = bajaContainer.querySelector('input');
                
                if (status == '3') { // '3' is Cancelado
                    bajaContainer.style.display = 'block';
                    if (bajaInput) bajaInput.required = true;
                } else {
                    bajaContainer.style.display = 'none';
                    if (bajaInput) bajaInput.required = false;
                    // Don't null the value on edit, in case they mis-clicked
                }
            });
        }

    } catch (error) {
        console.error('Error al cargar datos:', error);
        alert('Error al cargar datos: ' + error.message);
    }
});

function populateForm(data) {
    // 1. Populate General Info
    const noContrato = document.getElementById('no_contrato');
    const alias = document.getElementById('alias');
    const fechaApertura = document.getElementById('fecha_apertura');
    
    if (noContrato) noContrato.value = data.general?.no_contrato || '';
    if (alias) alias.value = data.general?.alias || '';
    if (fechaApertura) fechaApertura.value = data.general?.fecha_apertura || '';

    // Populate Status
    const idStatus = document.getElementById('id_status');
    if (idStatus) {
        idStatus.value = data.general?.id_status || '1';
        if (data.general?.id_status == '3') {
            const fechaBajaContainer = document.getElementById('fechaBajaContainer');
            const fechaBaja = document.getElementById('fecha_baja');
            if (fechaBajaContainer) fechaBajaContainer.style.display = 'block';
            if (fechaBaja) fechaBaja.value = data.general?.fecha_baja || '';
        }
    }

    // 2. Populate and disable Tipo Persona
    if (data.general?.id_tipo_persona) {
        populateSelect('tipoPersona', personaTypes, 'id_tipo_persona', 'nombre', data.general.id_tipo_persona);
        const tipoPersonaSelect = document.getElementById('tipoPersona');
        if (tipoPersonaSelect) tipoPersonaSelect.disabled = true;
    }
    
    // 3. Show and Populate Persona Specific section
    const persona = personaTypes.find(p => p.id_tipo_persona == data.general?.id_tipo_persona);
    if (persona && data.persona) {
        if (persona.es_fisica > 0) {
            const fisicaSection = document.getElementById('persona-fisica');
            if (fisicaSection) {
                fisicaSection.style.display = 'block';
                const fisicaNombre = document.getElementById('fisica_nombre');
                const fisicaApPaterno = document.getElementById('fisica_ap_paterno');
                const fisicaApMaterno = document.getElementById('fisica_ap_materno');
                const fisicaFechaNacimiento = document.getElementById('fisica_fecha_nacimiento');
                const fisicaTaxId = document.getElementById('fisica_tax_id');
                const fisicaCurp = document.getElementById('fisica_curp');
                
                if (fisicaNombre) fisicaNombre.value = data.persona.nombre || '';
                if (fisicaApPaterno) fisicaApPaterno.value = data.persona.apellido_paterno || '';
                if (fisicaApMaterno) fisicaApMaterno.value = data.persona.apellido_materno || '';
                if (fisicaFechaNacimiento) fisicaFechaNacimiento.value = data.persona.fecha_nacimiento || '';
                if (fisicaTaxId) fisicaTaxId.value = data.persona.tax_id || '';
                if (fisicaCurp) fisicaCurp.value = data.persona.CURP || '';
            }
        } else if (persona.es_moral > 0) {
            const moralSection = document.getElementById('persona-moral');
            const apoderadosSection = document.getElementById('apoderados-section');
            if (moralSection) {
                moralSection.style.display = 'block';
                const moralRazonSocial = document.getElementById('moral_razon_social');
                const moralFechaConstitucion = document.getElementById('moral_fecha_constitucion');
                const moralTaxId = document.getElementById('moral_tax_id');
                
                if (moralRazonSocial) moralRazonSocial.value = data.persona.razon_social || '';
                if (moralFechaConstitucion) moralFechaConstitucion.value = data.persona.fecha_constitucion || '';
                if (moralTaxId) moralTaxId.value = data.persona.tax_id || '';
            }
            if (apoderadosSection) apoderadosSection.style.display = 'block';
        } else if (persona.es_fideicomiso > 0) {
            const fideicomisoSection = document.getElementById('persona-fideicomiso');
            const apoderadosSection = document.getElementById('apoderados-section');
            if (fideicomisoSection) {
                fideicomisoSection.style.display = 'block';
                const fideNumero = document.getElementById('fide_numero');
                const fideInstitucion = document.getElementById('fide_institucion');
                
                if (fideNumero) fideNumero.value = data.persona.numero_fideicomiso || '';
                if (fideInstitucion) fideInstitucion.value = data.persona.institucion_fiduciaria || '';
            }
            if (apoderadosSection) apoderadosSection.style.display = 'block';
        }
    }

    // 4. Re-build dynamic lists
    if (data.nacionalidades) data.nacionalidades.forEach(n => addDynamicItem('nacionalidades-list', n));
    if (data.identificaciones) data.identificaciones.forEach(i => addDynamicItem('identificaciones-list', i));
    if (data.direcciones) data.direcciones.forEach(d => addDynamicItem('direcciones-list', d));
    if (data.contactos) data.contactos.forEach(c => addDynamicItem('contactos-list', c));
    if (data.documentos) data.documentos.forEach(d => addDocumentoItem(d));
    
    // Re-build Apoderados list
    if (data.apoderados) data.apoderados.forEach(a => addApoderadoItem(a));
    
    // 5. Re-build Beneficiarios Controladores list
    if (data.beneficiarios_controladores) {
        const beneficiarioSection = document.getElementById('beneficiario-controlador-section');
        if (beneficiarioSection) {
            beneficiarioSection.style.display = 'block';
        }
        data.beneficiarios_controladores.forEach(b => addBeneficiarioItem(b));
    } else {
        // Mostrar sección si es persona moral o fideicomiso
        const persona = personaTypes.find(p => p.id_tipo_persona == data.general?.id_tipo_persona);
        if (persona && (persona.es_moral > 0 || persona.es_fideicomiso > 0)) {
            const beneficiarioSection = document.getElementById('beneficiario-controlador-section');
            if (beneficiarioSection) {
                beneficiarioSection.style.display = 'block';
            }
        }
    }
}

function setupDynamicBuilders() {
    const addNacionalidad = document.getElementById('addNacionalidad');
    const addIdentificacion = document.getElementById('addIdentificacion');
    const addDireccion = document.getElementById('addDireccion');
    const addContacto = document.getElementById('addContacto');
    const addDocumento = document.getElementById('addDocumento');
    const addApoderado = document.getElementById('addApoderado');
    // Nota: addBeneficiario usa onclick directo en el HTML, no necesita event listener aquí
    
    if (addNacionalidad) addNacionalidad.addEventListener('click', () => addDynamicItem('nacionalidades-list'));
    if (addIdentificacion) addIdentificacion.addEventListener('click', () => addDynamicItem('identificaciones-list'));
    if (addDireccion) addDireccion.addEventListener('click', () => addDynamicItem('direcciones-list'));
    if (addContacto) addContacto.addEventListener('click', () => addDynamicItem('contactos-list'));
    if (addDocumento) addDocumento.addEventListener('click', () => addDocumentoItem());
    if (addApoderado) addApoderado.addEventListener('click', () => addApoderadoItem());
    // Removido: addBeneficiario - usa onclick directo
}

// Helper to populate a <select>
function populateSelect(elementId, data, valueField, labelField, selectedValue) {
    const select = document.getElementById(elementId);
    if (!select) return;
    
    select.innerHTML = '<option value="">-- Seleccione --</option>';
    data.forEach(item => {
        const selected = (item[valueField] == selectedValue) ? 'selected' : '';
        select.innerHTML += `<option value="${item[valueField]}" ${selected}>${item[labelField]}</option>`;
    });
}

// --- DYNAMIC ITEM TEMPLATES (Now with pre-fill) ---
function addDynamicItem(listId, data = {}) {
    const list = document.getElementById(listId);
    if (!list) return;
    
    const item = document.createElement('div');
    item.className = 'dynamic-list-item'; 
    item.style.display = 'flex';
    item.style.alignItems = 'center';
    item.style.marginBottom = '10px';
    
    let html = '';
    
    // BUILD HTML BASED ON LIST TYPE
    if (listId === 'nacionalidades-list') {
        const targetVal = data.id_pais || ''; 
        const options = catalogs.paises ? catalogs.paises.map(p => {
            const isSelected = (p.id_pais == targetVal) ? 'selected' : '';
            return `<option value="${p.id_pais}" ${isSelected}>${p.nombre}</option>`;
        }).join('') : '';
        
        html = `<select class="form-select" name="nacionalidad_id[]">
                    <option value="">Seleccione...</option>
                    ${options}
                </select>`;

    } else if (listId === 'identificaciones-list') {
        const targetVal = data.id_tipo_identificacion || '';
        const options = catalogs.tipos_identificacion ? catalogs.tipos_identificacion.map(t => {
            const isSelected = (t.id_tipo_identificacion == targetVal) ? 'selected' : '';
            return `<option value="${t.id_tipo_identificacion}" ${isSelected}>${t.nombre}</option>`;
        }).join('') : '';
        
        html = `<select class="form-select" name="ident_tipo[]">${options}</select>
                <input type="text" class="form-control" name="ident_numero[]" placeholder="Número" value="${(data.numero_identificacion || '').replace(/"/g, '&quot;')}">
                <input type="date" class="form-control" name="ident_vencimiento[]" placeholder="Vencimiento" value="${data.fecha_vencimiento || ''}">`;

    } else if (listId === 'direcciones-list') {
        html = `<input type="text" class="form-control" name="dir_calle[]" placeholder="Calle y Número" value="${(data.calle || '').replace(/"/g, '&quot;')}">
                <input type="text" class="form-control" name="dir_colonia[]" placeholder="Colonia" value="${(data.colonia || '').replace(/"/g, '&quot;')}">
                <input type="text" class="form-control" name="dir_cp[]" placeholder="C.P." value="${(data.codigo_postal || '').replace(/"/g, '&quot;')}">`;

    } else if (listId === 'contactos-list') {
        const targetVal = data.id_tipo_contacto || '';
        const options = catalogs.tipos_contacto ? catalogs.tipos_contacto.map(t => {
            const isSelected = (t.id_tipo_contacto == targetVal) ? 'selected' : '';
            return `<option value="${t.id_tipo_contacto}" ${isSelected}>${t.nombre}</option>`;
        }).join('') : '';
        
        html = `<select class="form-select" name="contacto_id_tipo[]">${options}</select>
                <input type="text" class="form-control" name="contacto_valor[]" placeholder="Dato" value="${(data.dato_contacto || '').replace(/"/g, '&quot;')}">`;
    }

    // INSERT HTML
    item.innerHTML = `
        <div class="row w-100 g-2 align-items-center">${html}</div>
        <button type="button" class="btn btn-sm btn-danger ms-2" onclick="this.parentElement.remove()" style="width: auto;">
            <i class="fa-solid fa-trash"></i>
        </button>
    `;
    list.appendChild(item);

    // FORCE VALUE (Safety Net)
    if (listId === 'nacionalidades-list' && data.id_pais) {
        const select = item.querySelector('select');
        if (select) select.value = data.id_pais;
    } else if (listId === 'identificaciones-list' && data.id_tipo_identificacion) {
        const select = item.querySelector('select');
        if (select) select.value = data.id_tipo_identificacion;
    } else if (listId === 'contactos-list' && data.id_tipo_contacto) {
        const select = item.querySelector('select');
        if (select) select.value = data.id_tipo_contacto;
    }
}

// TEMPLATE FOR APODERADO
function addApoderadoItem(data = {}) {
    const list = document.getElementById('apoderados-list');
    if (!list) return;
    
    const item = document.createElement('div');
    const index = apoderadoCounter++;
    
    item.className = 'border p-3 rounded mb-3 bg-light';
    
    const tipoPersonaOptions = catalogs.tipos_persona
        ? catalogs.tipos_persona
            .filter(p => p.es_fisica > 0 || p.es_moral > 0)
            .map(p => `<option value="${p.id_tipo_persona}" ${p.id_tipo_persona == data.id_tipo_persona ? 'selected' : ''}>${p.nombre}</option>`)
            .join('')
        : '';
    
    const personaData = data.persona_data || {};
    
    item.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Apoderado</h6>
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.border').remove()"><i class="fa-solid fa-trash"></i></button>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label small">Tipo de Apoderado*</label>
                <select class="form-select" name="apoderado[${index}][id_tipo_persona]" onchange="toggleApoderadoType(this, ${index})" required>
                    <option value="">-- Seleccione --</option>
                    ${tipoPersonaOptions}
                </select>
            </div>
        </div>

        <div id="apoderado-fisica-${index}" class="apoderado-specific" style="display:none;">
            <div class="row">
                <div class="col-md-4 mb-3"><label class="small">Nombre*</label><input type="text" class="form-control" name="apoderado[${index}][fisica_nombre]" value="${(personaData.nombre || '').replace(/"/g, '&quot;')}"></div>
                <div class="col-md-4 mb-3"><label class="small">Apellido Paterno*</label><input type="text" class="form-control" name="apoderado[${index}][fisica_ap_paterno]" value="${(personaData.apellido_paterno || '').replace(/"/g, '&quot;')}"></div>
                <div class="col-md-4 mb-3"><label class="small">Apellido Materno</label><input type="text" class="form-control" name="apoderado[${index}][fisica_ap_materno]" value="${(personaData.apellido_materno || '').replace(/"/g, '&quot;')}"></div>
                <div class="col-md-6 mb-3"><label class="small">RFC*</label><input type="text" class="form-control" name="apoderado[${index}][fisica_tax_id]" value="${(personaData.tax_id || '').replace(/"/g, '&quot;')}"></div>
                <div class="col-md-6 mb-3"><label class="small">CURP</label><input type="text" class="form-control" name="apoderado[${index}][fisica_curp]" value="${(personaData.CURP || '').replace(/"/g, '&quot;')}"></div>
            </div>
        </div>
        
        <div id="apoderado-moral-${index}" class="apoderado-specific" style="display:none;">
            <div class="row">
                <div class="col-md-6 mb-3"><label class="small">Razón Social*</label><input type="text" class="form-control" name="apoderado[${index}][moral_razon_social]" value="${(personaData.razon_social || '').replace(/"/g, '&quot;')}"></div>
                <div class="col-md-6 mb-3"><label class="small">RFC*</label><input type="text" class="form-control" name="apoderado[${index}][moral_tax_id]" value="${(personaData.tax_id || '').replace(/"/g, '&quot;')}"></div>
            </div>
        </div>

        <hr>
        <div class="mb-2"><label class="form-label small fw-bold">Contactos del Apoderado</label></div>
        <div id="apoderado-contactos-list-${index}"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addApoderadoContactoItem(${index})">
            <i class="fa-solid fa-plus"></i> Agregar Contacto (Apoderado)
        </button>
    `;
    list.appendChild(item);
    
    // Pre-fill contacts for this apoderado
    if (data.contactos && Array.isArray(data.contactos)) {
        data.contactos.forEach(c => addApoderadoContactoItem(index, c));
    }

    // Manually trigger the display logic
    const select = item.querySelector('select');
    if (select) toggleApoderadoType(select, index);
}

// TEMPLATE FOR APODERADO CONTACT
function addApoderadoContactoItem(apoderadoIndex, data = {}) {
    const list = document.getElementById(`apoderado-contactos-list-${apoderadoIndex}`);
    if (!list) return;
    
    const item = document.createElement('div');
    item.className = 'dynamic-list-item';
    
    const options = catalogs.tipos_contacto
        ? catalogs.tipos_contacto.map(t => 
            `<option value="${t.id_tipo_contacto}" ${t.id_tipo_contacto == data.id_tipo_contacto ? 'selected' : ''}>${t.nombre}</option>`
        ).join('')
        : '';

    item.innerHTML = `
        <select class="form-select" name="apoderado[${apoderadoIndex}][contactos][tipo][]">${options}</select>
        <input type="text" class="form-control" name="apoderado[${apoderadoIndex}][contactos][valor][]" placeholder="Dato de Contacto" value="${(data.dato_contacto || '').replace(/"/g, '&quot;')}">
        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button>
    `;
    list.appendChild(item);
}

// TOGGLE LOGIC FOR APODERADO SUB-FORM
function toggleApoderadoType(selectElement, index) {
    const selectedId = selectElement.value;
    const persona = catalogs.tipos_persona ? catalogs.tipos_persona.find(p => p.id_tipo_persona == selectedId) : null;
    
    const fisicaEl = document.getElementById(`apoderado-fisica-${index}`);
    const moralEl = document.getElementById(`apoderado-moral-${index}`);
    
    if (fisicaEl) fisicaEl.style.display = 'none';
    if (moralEl) moralEl.style.display = 'none';

    if (persona) {
        if (persona.es_fisica > 0 && fisicaEl) fisicaEl.style.display = 'block';
        if (persona.es_moral > 0 && moralEl) moralEl.style.display = 'block';
    }
}

function addDocumentoItem(data = {}) {
    const list = document.getElementById('documentos-list');
    if (!list) return;
    
    const item = document.createElement('div');
    item.className = 'dynamic-list-item row g-2 align-items-center mb-2';

    let fileInput = '<input type="file" class="form-control" name="doc_file[]">';
    
    if (data.ruta && data.ruta.trim() !== '') {
        // Clean the path (remove '../')
        const cleanPath = data.ruta.replace('../', '');
        
        fileInput = `
            <div class="input-group">
                <a href="${cleanPath}" target="_blank" class="btn btn-outline-primary" title="Ver Archivo actual">
                    <i class="fa-regular fa-eye"></i>
                </a>
                <input type="file" class="form-control" name="doc_file[]" title="Subir nuevo para reemplazar">
            </div>
        `;
    }

    // Si hay datos existentes (ruta o descripcion), el tipo es readonly
    // Si es un documento nuevo, el tipo es editable
    const isExisting = (data.ruta && data.ruta.trim() !== '') || (data.descripcion && data.descripcion.trim() !== '');
    const readonlyAttr = isExisting ? 'readonly' : '';
    
    item.innerHTML = `
        <div class="col-md-4">
            <input type="text" class="form-control" name="doc_tipo[]" placeholder="Tipo de Documento (ej: KYC, Identificación)" value="${(data.descripcion || '').replace(/"/g, '&quot;')}" ${readonlyAttr}>
        </div>
        <div class="col-md-5">
            ${fileInput}
        </div>
        <div class="col-md-2">
            <input type="date" class="form-control" name="doc_vencimiento[]" value="${data.fecha_vencimiento || ''}" placeholder="Vencimiento">
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-danger" onclick="this.closest('.dynamic-list-item').remove()">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    `;
    list.appendChild(item);
}

// FORM SUBMIT
document.addEventListener('DOMContentLoaded', function() {
    const editClientForm = document.getElementById('editClientForm');
    if (!editClientForm) return;
    
    editClientForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Disable submit button to prevent double submission
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Actualizando...';
        }
        
        const formData = new FormData(this);
        
        // Re-enable disabled fields for submission
        const tipoPersonaSelect = document.getElementById('tipoPersona');
        if (tipoPersonaSelect) {
            tipoPersonaSelect.disabled = false;
            formData.append('id_tipo_persona', tipoPersonaSelect.value);
            tipoPersonaSelect.disabled = true;
        }

        fetch('api/update_client.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }
            return res.text();
        })
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseErr) {
                console.error('Error parsing JSON:', text);
                throw new Error('Respuesta inválida del servidor: ' + text.substring(0, 200));
            }
            
            if (data.status === 'success') {
                alert('Cliente actualizado con éxito.');
                window.location.href = `cliente_detalle.php?id=${clientId}`;
            } else {
                alert('Error: ' + (data.message || 'Error desconocido'));
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }
        })
        .catch(err => {
            console.error('Error al actualizar cliente:', err);
            alert('Error de conexión: ' + err.message);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        });
    });
});

// ============================================
// BENEFICIARIO CONTROLADOR (VAL-PLD-007)
// ============================================

let _beneficiarioFormIndex = 0;

function addBeneficiarioItem(data = {}) {
    const list = document.getElementById('beneficiarios-controladores-list');
    if (!list) return;
    
    _beneficiarioFormIndex += 1;
    const index = _beneficiarioFormIndex;

    const item = document.createElement('div');
    item.className = 'border p-3 rounded mb-3 bg-light';
    
    const tipoPersonaSelected = data.tipo_persona || '';
    const tipoPersonaOptions = `
        <option value="">-- Seleccione --</option>
        <option value="fisica" ${tipoPersonaSelected === 'fisica' ? 'selected' : ''}>Persona Física</option>
        <option value="moral" ${tipoPersonaSelected === 'moral' ? 'selected' : ''}>Persona Moral</option>
    `;
    
    item.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><i class="fa-solid fa-user-tie me-2"></i>Beneficiario Controlador</h6>
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.border').remove()">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label small">Tipo de Persona *</label>
                <select class="form-select" name="beneficiario[${index}][tipo_persona]" required>
                    ${tipoPersonaOptions}
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label small">Nombre Completo *</label>
                <input type="text" class="form-control" name="beneficiario[${index}][nombre_completo]" 
                       value="${(data.nombre_completo || '').replace(/"/g, '&quot;')}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label small">RFC</label>
                <input type="text" class="form-control" name="beneficiario[${index}][rfc]" 
                       value="${(data.rfc || '').replace(/"/g, '&quot;')}" maxlength="13">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label small">Porcentaje de Participación (%)</label>
                <input type="number" class="form-control" name="beneficiario[${index}][porcentaje_participacion]" 
                       value="${data.porcentaje_participacion || ''}" min="0" max="100" step="0.01">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label small">Documento de Identificación</label>
                <input type="file" class="form-control" name="beneficiario[${index}][documento_identificacion]" 
                       accept=".pdf,.jpg,.jpeg,.png">
                <small class="text-muted">Requerido para persona moral</small>
                ${data.documento_identificacion ? `<div class="mt-1"><a href="${data.documento_identificacion.replace('../', '')}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye me-1"></i>Ver actual</a></div>` : ''}
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label small">Declaración Jurada</label>
                <input type="file" class="form-control" name="beneficiario[${index}][declaracion_jurada]" 
                       accept=".pdf,.jpg,.jpeg,.png">
                <small class="text-muted">Requerido para persona física</small>
                ${data.declaracion_jurada ? `<div class="mt-1"><a href="${data.declaracion_jurada.replace('../', '')}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye me-1"></i>Ver actual</a></div>` : ''}
            </div>
        </div>
        ${data.id_beneficiario ? `<input type="hidden" name="beneficiario[${index}][id_beneficiario]" value="${data.id_beneficiario}">` : ''}
    `;
    
    list.appendChild(item);
}

// Make functions available globally for inline handlers
window.addApoderadoContactoItem = addApoderadoContactoItem;
window.toggleApoderadoType = toggleApoderadoType;
window.addBeneficiarioItem = addBeneficiarioItem;