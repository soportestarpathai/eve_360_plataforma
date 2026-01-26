/**
 * Cliente Nuevo Page JavaScript
 * Handles client creation wizard, PLD validation, and form submission
 */

// Global Variables
let catalogs = {};
let personaTypes = [];
let apoderadoCounter = 0;
let currentStep = 1;
let isValidatedPLD = false; // Flag to control submission
let currentSearchId = null;

// --- 1. VALIDATION LOGIC ---
async function validatePerson(isAutomatic = false) {
    const statusSpan = document.getElementById('validationStatus');
    if (!statusSpan) return;
    
    // Gather data based on visible section
    let data = {};
    const personaFisica = document.getElementById('persona-fisica');
    const personaMoral = document.getElementById('persona-moral');
    
    if (!personaFisica || !personaMoral) {
        console.error('Elements not found');
        return;
    }
    
    const isFisica = personaFisica.style.display !== 'none';
    const isMoral = personaMoral.style.display !== 'none';

    if (isFisica) {
        data.nombre = document.getElementById('fisica_nombre')?.value.trim() || '';
        data.paterno = document.getElementById('fisica_ap_paterno')?.value.trim() || '';
        data.materno = document.getElementById('fisica_ap_materno')?.value.trim() || '';
        data.tipo_persona = 'fisica';
        
        // Validation: For manual checks, require nombre and paterno
        if (!isAutomatic && (!data.nombre || !data.paterno)) {
            alert('Por favor complete al menos el Nombre y Apellido Paterno para realizar la búsqueda.');
            return;
        }
        // For automatic checks, skip if fields are empty
        if (isAutomatic && (!data.nombre || !data.paterno)) {
            return;
        }
    } else if (isMoral) {
        data.nombre = document.getElementById('moral_razon_social')?.value.trim() || '';
        data.paterno = '';
        data.materno = '';
        data.tipo_persona = 'moral';
        
        // Validation: For manual checks, require nombre
        if (!isAutomatic && data.nombre.length < 3) {
            alert('Por favor ingrese la Razón Social (mínimo 3 caracteres) para realizar la búsqueda.');
            return;
        }
        // For automatic checks, skip if nombre is too short
        if (isAutomatic && data.nombre.length < 3) {
            return;
        }
    } else {
        // No persona type selected
        if (!isAutomatic) {
            alert('Por favor seleccione el tipo de persona primero.');
        }
        return;
    }

    if (!isAutomatic) {
        // Open Modal immediately for manual check
        const modalEl = document.getElementById('pldModal');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
            document.getElementById('pldLoading').style.display = 'block';
            document.getElementById('pldResults').style.display = 'none';
            document.getElementById('pldClean').style.display = 'none';
            document.getElementById('btnConfirmPld').style.display = 'none';
            document.getElementById('btnCloseClean').style.display = 'none';
        }
    } else {
        if (statusSpan) {
            statusSpan.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Consultando...';
        }
    }

    data.save_history = true; // Save this search

    try {
        const res = await fetch('api/validate_person.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        // Check if response is ok
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        }

        // Parse JSON response
        const text = await res.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (parseErr) {
            throw new Error('Respuesta inválida del servidor: ' + text.substring(0, 100));
        }

        // Check if result has status
        if (result.status === 'success') {
            // Store search ID if available
            if (result.id_busqueda) {
                currentSearchId = result.id_busqueda;
            }

            if (result.found) {
                // RISK FOUND
                if (statusSpan) {
                    statusSpan.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> COINCIDENCIA EN LISTAS (Requiere Acción)';
                    statusSpan.className = 'text-risk';
                }
                isValidatedPLD = false; 

                if (!isAutomatic) {
                    // Show Hits in Modal
                    const pldLoading = document.getElementById('pldLoading');
                    const pldResults = document.getElementById('pldResults');
                    const btnConfirmPld = document.getElementById('btnConfirmPld');
                    
                    if (pldLoading) pldLoading.style.display = 'none';
                    if (pldResults) pldResults.style.display = 'block';
                    if (btnConfirmPld) btnConfirmPld.style.display = 'block';
                    
                    if (result.data && Array.isArray(result.data)) {
                        renderHits(result.data);
                    }
                }
            } else {
                // CLEAN
                if (statusSpan) {
                    statusSpan.innerHTML = '<i class="fa-solid fa-check-circle"></i> Sin coincidencias (Limpio)';
                    statusSpan.className = 'text-ok';
                }
                isValidatedPLD = true;
                
                if (!isAutomatic) {
                    const pldLoading = document.getElementById('pldLoading');
                    const pldClean = document.getElementById('pldClean');
                    const btnCloseClean = document.getElementById('btnCloseClean');
                    
                    if (pldLoading) pldLoading.style.display = 'none';
                    if (pldClean) pldClean.style.display = 'block';
                    if (btnCloseClean) btnCloseClean.style.display = 'block';
                }
            }
        } else {
            // API returned an error
            const errorMsg = result.message || 'Error desconocido';
            if (statusSpan) {
                statusSpan.innerHTML = 'Error en consulta';
            }
            if (!isAutomatic) {
                const pldLoading = document.getElementById('pldLoading');
                if (pldLoading) {
                    pldLoading.innerHTML = '<p class="text-danger"><i class="fa-solid fa-circle-exclamation me-2"></i>Error: ' + errorMsg + '</p>';
                }
            } else {
                console.error('PLD Validation Error:', errorMsg);
            }
        }
    } catch (err) {
        console.error('PLD Validation Error:', err);
        if (statusSpan) {
            statusSpan.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Error de conexión';
            statusSpan.className = 'text-risk';
        }
        if (!isAutomatic) {
            const pldLoading = document.getElementById('pldLoading');
            if (pldLoading) {
                pldLoading.innerHTML = '<p class="text-danger"><i class="fa-solid fa-wifi me-2"></i>Error de conexión: ' + err.message + '</p>';
            }
        }
    }
}

function renderHits(hits) {
    const container = document.getElementById('hitsContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (!hits || hits.length === 0) {
        container.innerHTML = '<div class="text-center p-4 text-muted"><i class="fa-solid fa-info-circle me-2"></i>No se encontraron detalles de coincidencias.</div>';
        return;
    }
    
    hits.forEach((hit, index) => {
        const div = document.createElement('div');
        div.className = 'hit-row';
        
        // Determine badge class based on score
        const score = parseFloat(hit.porcentaje || 0);
        const badgeClass = score >= 90 ? 'bg-danger text-white' : 'bg-warning text-dark';
        const badgeText = score >= 90 ? 'ALTO RIESGO' : 'RIESGO MEDIO';
        
        // Escape HTML to prevent XSS
        const nombre = (hit.nombreCompleto || 'Nombre desconocido').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const lista = (hit.lista || 'Lista desconocida').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const entidad = (hit.entidad || 'N/A').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const puesto = (hit.puesto || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const fecha = (hit.fecha || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const estatus = (hit.estatus || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        
        div.innerHTML = `
            <div class="form-check">
                <input class="form-check-input" type="radio" name="pldSelection" id="hit_${index}" value='${JSON.stringify(hit).replace(/'/g, "&apos;")}'>
                <label class="form-check-label w-100" for="hit_${index}">
                    <div class="fw-bold">
                        <i class="fa-solid fa-user-xmark"></i>
                        ${nombre}
                        <span class="badge ${badgeClass} ms-2">${badgeText} ${score}%</span>
                    </div>
                    <div class="small mt-2">
                        <span><i class="fa-solid fa-list me-1"></i><strong>Lista:</strong> ${lista}</span>
                        ${entidad !== 'N/A' ? `<span><i class="fa-solid fa-building me-1"></i><strong>Entidad:</strong> ${entidad}</span>` : ''}
                        ${puesto ? `<span><i class="fa-solid fa-briefcase me-1"></i><strong>Puesto:</strong> ${puesto}</span>` : ''}
                        ${fecha ? `<span><i class="fa-solid fa-calendar me-1"></i><strong>Fecha:</strong> ${fecha}</span>` : ''}
                        ${estatus ? `<span><i class="fa-solid fa-flag me-1"></i><strong>Estatus:</strong> ${estatus}</span>` : ''}
                    </div>
                </label>
            </div>
        `;
        container.appendChild(div);
    });
}

function confirmPld() {
    const selected = document.querySelector('input[name="pldSelection"]:checked');
    if (!selected) { 
        alert("Debe seleccionar un resultado o la opción 'Ninguna'."); 
        return; 
    }
    
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

// --- 2. NAVIGATION FUNCTIONS ---
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
    window.scrollTo(0, 0);
}

function prevStep(step) {
    document.getElementById(`step-${currentStep}`).classList.remove('active');
    document.getElementById(`step-${step}`).classList.add('active');
    currentStep = step;
    window.scrollTo(0, 0);
}

// --- 3. UTILITY FUNCTIONS ---
function populateSelect(elementId, data, valueField, labelField) {
    const select = document.getElementById(elementId);
    if (!select) return;
    
    select.innerHTML = '<option value="">-- Seleccione --</option>';
    data.forEach(item => {
        select.innerHTML += `<option value="${item[valueField]}">${item[labelField]}</option>`;
    });
}

function addDynamicItem(listId, name, catalogData, catValue, catLabel) {
    const list = document.getElementById(listId);
    if (!list) return;
    
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
    if (!list) return;
    
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
    if (!list) return;
    
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
    
    const fisicaEl = document.getElementById(`apoderado-fisica-${index}`);
    const moralEl = document.getElementById(`apoderado-moral-${index}`);
    
    if (fisicaEl) fisicaEl.style.display = 'none';
    if (moralEl) moralEl.style.display = 'none';
    
    if (persona) {
        if (persona.es_fisica > 0 && fisicaEl) fisicaEl.style.display = 'block';
        if (persona.es_moral > 0 && moralEl) moralEl.style.display = 'block';
    }
}

function addDocumentoItem() {
    const list = document.getElementById('documentos-list');
    if (!list) return;
    
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

// --- 4. INITIALIZATION ---
document.addEventListener('DOMContentLoaded', function() {
    // Load catalogs
    fetch('api/get_catalogos.php')
        .then(r => {
            if (!r.ok) {
                throw new Error('Error al cargar catálogos: ' + r.status);
            }
            return r.json();
        })
        .then(json => {
            if (json.status === 'success') {
                catalogs = json.data;
                personaTypes = catalogs.tipos_persona;
                populateSelect('tipoPersona', personaTypes, 'id_tipo_persona', 'nombre');
            } else {
                console.error('Error al cargar catálogos:', json.message);
            }
        })
        .catch(err => {
            console.error('Error de conexión al cargar catálogos:', err);
        });

    // Tipo Persona Change Handler
    const tipoPersonaSelect = document.getElementById('tipoPersona');
    if (tipoPersonaSelect) {
        tipoPersonaSelect.addEventListener('change', function(e) {
            const selectedId = e.target.value;
            const persona = personaTypes.find(p => p.id_tipo_persona == selectedId);
            
            document.querySelectorAll('.persona-specific').forEach(el => el.style.display = 'none');
            // Reset validation display when type changes
            isValidatedPLD = false;
            const statusSpan = document.getElementById('validationStatus');
            if (statusSpan) {
                statusSpan.innerHTML = 'Pendiente';
                statusSpan.className = 'text-muted';
            }
            
            if (persona) {
                if (persona.es_fisica > 0) {
                    const fisicaEl = document.getElementById('persona-fisica');
                    if (fisicaEl) fisicaEl.style.display = 'block';
                }
                if (persona.es_moral > 0) {
                    const moralEl = document.getElementById('persona-moral');
                    const apoderadosEl = document.getElementById('apoderados-section');
                    if (moralEl) moralEl.style.display = 'block';
                    if (apoderadosEl) apoderadosEl.style.display = 'block';
                } else {
                    const apoderadosEl = document.getElementById('apoderados-section');
                    if (apoderadosEl) apoderadosEl.style.display = 'none';
                }
                if (persona.es_fideicomiso > 0) {
                    const fideicomisoEl = document.getElementById('persona-fideicomiso');
                    const apoderadosEl = document.getElementById('apoderados-section');
                    if (fideicomisoEl) fideicomisoEl.style.display = 'block';
                    if (apoderadosEl) apoderadosEl.style.display = 'block';
                } else if (persona.es_moral == 0) {
                    const apoderadosEl = document.getElementById('apoderados-section');
                    if (apoderadosEl) apoderadosEl.style.display = 'none';
                }
            } else {
                const apoderadosEl = document.getElementById('apoderados-section');
                if (apoderadosEl) apoderadosEl.style.display = 'none';
            }
        });
    }
    
    // Status Change Handler
    const idStatusSelect = document.getElementById('id_status');
    if (idStatusSelect) {
        idStatusSelect.addEventListener('change', function(e) {
            const status = e.target.value;
            const bajaContainer = document.getElementById('fechaBajaContainer');
            if (bajaContainer) {
                const bajaInput = bajaContainer.querySelector('input');
                if (status == '3') {
                    bajaContainer.style.display = 'block';
                    if (bajaInput) bajaInput.required = true;
                } else {
                    bajaContainer.style.display = 'none';
                    if (bajaInput) {
                        bajaInput.required = false;
                        bajaInput.value = null;
                    }
                }
            }
        });
    }

    // Event Listeners for Dynamic Items
    const addNacionalidad = document.getElementById('addNacionalidad');
    if (addNacionalidad) {
        addNacionalidad.addEventListener('click', () => addDynamicItem('nacionalidades-list', 'nacionalidad[]', catalogs.paises, 'id_pais', 'nombre'));
    }
    
    const addIdentificacion = document.getElementById('addIdentificacion');
    if (addIdentificacion) {
        addIdentificacion.addEventListener('click', () => addDynamicItem('identificaciones-list', 'identificacion[]', catalogs.tipos_identificacion, 'id_tipo_identificacion', 'nombre'));
    }
    
    const addDireccion = document.getElementById('addDireccion');
    if (addDireccion) {
        addDireccion.addEventListener('click', () => addDynamicItem('direcciones-list', 'direccion[]'));
    }
    
    const addContacto = document.getElementById('addContacto');
    if (addContacto) {
        addContacto.addEventListener('click', () => addDynamicItem('contactos-list', 'contacto[]'));
    }
    
    const addDocumento = document.getElementById('addDocumento');
    if (addDocumento) {
        addDocumento.addEventListener('click', () => addDocumentoItem());
    }
    
    const addApoderado = document.getElementById('addApoderado');
    if (addApoderado) {
        addApoderado.addEventListener('click', () => addApoderadoItem());
    }

    // Form Submit Handler
    const newClientForm = document.getElementById('newClientForm');
    if (newClientForm) {
        newClientForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Disable submit button to prevent double submission
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Guardando...';
            }
            
            const formData = new FormData(this);
            
            fetch('api/save_client.php', { 
                method: 'POST', 
                body: formData 
            })
            .then(res => {
                // Check if response is ok
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                
                // Get response as text first to check if it's valid JSON
                return res.text();
            })
            .then(text => {
                // Try to parse JSON
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseErr) {
                    // If parsing fails, show the raw response (helpful for debugging)
                    console.error('Error parsing JSON:', text);
                    throw new Error('Respuesta inválida del servidor. Por favor, verifique los logs del servidor. Respuesta: ' + text.substring(0, 200));
                }
                
                // Check if data has status
                if (data.status === 'success') {
                    alert('Cliente guardado con éxito. ID: ' + data.id_cliente);
                    window.location.href = 'clientes.php';
                } else {
                    alert('Error: ' + (data.message || 'Error desconocido'));
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnText;
                    }
                }
            })
            .catch(err => {
                console.error('Error al guardar cliente:', err);
                alert('Error de conexión: ' + err.message);
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            });
        });
    }
});

// Make functions available globally for inline handlers
window.validatePerson = validatePerson;
window.confirmPld = confirmPld;
window.nextStep = nextStep;
window.prevStep = prevStep;
window.addApoderadoContactoItem = addApoderadoContactoItem;
window.toggleApoderadoType = toggleApoderadoType;
