<?php 
$id_cliente = $_GET['id'] ?? 0;
if (!$id_cliente) { die("ID de cliente no válido."); }
include 'templates/header.php'; 
?>
<title>Detalle de Cliente</title>
<style>
    /* Page-specific styles */
    .detail-card { max-width: 1000px; margin: 2rem auto; }
    .form-section { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
    .section-title { font-size: 1.1rem; font-weight: 600; color: #0d6efd; border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 1.5rem; }
    .data-pair { margin-bottom: 1rem; }
    .data-label { font-size: 0.8rem; font-weight: 600; color: #6c757d; text-transform: uppercase; }
    .data-value { font-size: 1rem; color: #212529; word-break: break-word; }
    
    /* History Table Styles */
    .table-pld th { font-size: 0.85rem; background-color: #f8f9fa; white-space: nowrap; }
    .table-pld td { font-size: 0.9rem; vertical-align: middle; }
    .badge-risk { background-color: #f8d7da; color: #842029; }
    .badge-clean { background-color: #d1e7dd; color: #0f5132; }
    
    /* Modal Styles */
    .hit-row { border-bottom: 1px solid #eee; padding: 10px; cursor: pointer; transition: background 0.2s; }
    .hit-row:hover { background: #f8f9fa; }
    .hit-row.selected { background: #e8f0fe; border-left: 4px solid #0d6efd; }
</style>
</head>
<body>

<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<!-- MAIN CONTENT -->
<div class="detail-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 id="clientName">Cargando...</h2>
        <div>
            <button class="btn btn-warning me-2" onclick="openPldCheck()">
                <i class="fa-solid fa-shield-halved me-2"></i>Consultar Listas
            </button>
            <a href="cliente_editar.php?id=<?php echo $id_cliente; ?>" class="btn btn-primary">
                <i class="fa-solid fa-pen me-2"></i>Editar Cliente
            </a>
        </div>
    </div>

    <!-- General Info -->
    <div class="form-section">
        <div class="section-title d-flex justify-content-between">
            <span>Información General</span>
            <span id="riskHeaderBadge"></span>
        </div>
        <div class="row" id="general-info"></div>
    </div>

    <!-- Risk Breakdown -->
    <div class="form-section">
        <div class="section-title">Cálculo de Riesgo (EBR)</div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Factor</th>
                        <th>Peso</th>
                        <th>Valor del Cliente</th>
                        <th>Puntaje</th>
                        <th>Contribución</th>
                    </tr>
                </thead>
                <tbody id="risk-breakdown-list"></tbody>
                <tfoot class="bg-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">Riesgo Total Calculado:</td>
                        <td id="risk-total-score">0%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Persona-Specific Info -->
    <div class="form-section">
        <div class="section-title" id="persona-title">Detalles de Persona</div>
        <div class="row" id="persona-info"></div>
    </div>
    
    <!-- Apoderados Section -->
    <div class="form-section" id="apoderados-section-display" style="display: none;">
        <div class="section-title">Apoderados / Representantes Legales</div>
        <div id="apoderados-list-display"></div>
    </div>
    
    <!-- Related Data Sections -->
    <div class="form-section"><div class="section-title">Nacionalidades</div><ul class="list-group" id="nacionalidades-list"></ul></div>
    <div class="form-section"><div class="section-title">Identificaciones</div><ul class="list-group" id="identificaciones-list"></ul></div>
    <div class="form-section"><div class="section-title">Direcciones</div><ul class="list-group" id="direcciones-list"></ul></div>
    <div class="form-section"><div class="section-title">Contactos</div><ul class="list-group" id="contactos-list"></ul></div>
    <div class="form-section"><div class="section-title">Documentos</div><ul class="list-group" id="documentos-list"></ul></div>

    <!-- PLD History Section (Last) -->
    <div class="form-section">
        <div class="section-title">Historial de Consultas PLD</div>
        <div class="table-responsive">
            <table class="table table-pld table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Nombre Buscado</th>
                        <th>Resultado</th>
                        <th>Confirmación</th>
                        <th>Comentarios</th>
                    </tr>
                </thead>
                <tbody id="pld-history-list">
                    <tr><td colspan="6" class="text-center text-muted">Cargando historial...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- PLD SELECTION MODAL -->
<div class="modal fade" id="pldModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Resultados de Búsqueda PLD</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                        <div class="small text-muted mt-1">Advertencia: Al seleccionar esto, usted asume la responsabilidad de que el cliente no coincide con las listas mostradas.</div>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmPld" onclick="confirmPld()" style="display:none;">Confirmar Selección</button>
            </div>
        </div>
    </div>
</div>

<script>
    const clientId = <?php echo $id_cliente; ?>;
    let clientDataCache = null; 
    let currentSearchId = null; 

    document.addEventListener('DOMContentLoaded', function() {
        fetch(`api/get_client_details.php?id=${clientId}`).then(res => res.json()).then(json => {
            if (json.status === 'success') {
                clientDataCache = json.data;
                renderData(json.data);
            } else { alert(json.message); }
        });
    });

    function renderData(data) {
        // Helper to create data pairs
        const createPair = (label, value) => `<div class="col-md-4 data-pair"><div class="data-label">${label}</div><div class="data-value">${value || '-'}</div></div>`;
        
        let name = data.persona.nombre ? `${data.persona.nombre} ${data.persona.apellido_paterno}` : data.persona.razon_social;
        document.getElementById('clientName').textContent = name;

        // General Info
        const genInfo = document.getElementById('general-info');
        genInfo.innerHTML = createPair('No. Contrato', data.general.no_contrato) + createPair('Alias', data.general.alias) + createPair('Fecha Apertura', data.general.fecha_apertura) + createPair('Tipo Persona', data.general.tipo_persona_nombre);

        // Render Risk
        renderRisk(data.risk_breakdown);

        // Persona Info
        const perInfo = document.getElementById('persona-info');
        document.getElementById('persona-title').textContent = `Detalles (${data.general.tipo_persona_nombre})`;
        if (data.general.tipo_persona_nombre === 'Física') {
            perInfo.innerHTML = createPair('Nombre Completo', `${data.persona.nombre} ${data.persona.apellido_paterno} ${data.persona.apellido_materno}`) + createPair('Fecha Nacimiento', data.persona.fecha_nacimiento) + createPair('RFC', data.persona.tax_id) + createPair('CURP', data.persona.CURP);
        } else if (data.general.tipo_persona_nombre === 'Moral') {
            perInfo.innerHTML = createPair('Razón Social', data.persona.razon_social) + createPair('Fecha Constitución', data.persona.fecha_constitucion) + createPair('RFC', data.persona.tax_id);
        } else if (data.general.tipo_persona_nombre === 'Fideicomiso') {
            perInfo.innerHTML = createPair('Número', data.persona.numero_fideicomiso) + createPair('Institución', data.persona.institucion_fiduciaria);
        }
        
        // Apoderados Logic
        const typeName = data.general.tipo_persona_nombre ? data.general.tipo_persona_nombre.toLowerCase() : '';
        const apoderadosSection = document.getElementById('apoderados-section-display');
        if (typeName.includes('moral') || typeName.includes('fideicomiso')) {
            apoderadosSection.style.display = 'block';
            populateApoderadosList(data.apoderados);
        } else {
            apoderadosSection.style.display = 'none';
        }

        // Lists
        populateList('nacionalidades-list', data.nacionalidades, n => `País: ${n.pais_nombre}`);
        populateList('identificaciones-list', data.identificaciones, i => `Tipo ${i.tipo_identificacion_nombre}: ${i.numero_identificacion}`);
        populateList('direcciones-list', data.direcciones, d => `${d.calle}, ${d.colonia}, ${d.codigo_postal}`);
        populateList('contactos-list', data.contactos, c => `<strong>${c.tipo_nombre || 'Desconocido'}:</strong> ${c.dato_contacto}`);
        //populateList('documentos-list', data.documentos, d => `${d.descripcion} (Vence: ${d.fecha_vencimiento})`);

        // --- RENDER DOCUMENTOS ---
        const docList = document.getElementById('documentos-list'); // Ensure your UL or DIV has this ID
        docList.innerHTML = '';

        if (data.documentos && data.documentos.length > 0) {
            data.documentos.forEach(doc => {
                
                // 1. Clean the path (remove '../' so it works from root)
                let filePath = '#';
                let hasFile = false;
                
                if (doc.ruta && doc.ruta.trim() !== '') {
                    filePath = doc.ruta.replace('../', ''); // Fixes path from 'api/../uploads' to 'uploads'
                    hasFile = true;
                }

                // 2. Define Styles based on availability
                const btnClass = hasFile ? 'btn-outline-primary' : 'btn-outline-secondary disabled';
                const downloadClass = hasFile ? 'btn-outline-dark' : 'btn-outline-secondary disabled';
                const targetAttr = hasFile ? 'target="_blank"' : '';

                // 3. Build HTML
                const docItem = `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="me-3 text-primary">
                                <i class="fa-regular fa-file-pdf fa-lg"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark">${doc.descripcion}</div>
                                <small class="text-muted">
                                    <i class="fa-regular fa-calendar me-1"></i>
                                    Vence: ${doc.fecha_vencimiento ? doc.fecha_vencimiento : 'N/A'}
                                </small>
                            </div>
                        </div>
                        
                        <div class="btn-group" role="group">
                            <a href="${filePath}" ${targetAttr} class="btn btn-sm ${btnClass}" title="Ver documento">
                                <i class="fa-regular fa-eye"></i>
                            </a>
                            
                            <a href="${filePath}" download class="btn btn-sm ${downloadClass}" title="Descargar">
                                <i class="fa-solid fa-download"></i>
                            </a>
                        </div>
                    </li>
                `;
                docList.innerHTML += docItem;
            });
        } else {
            docList.innerHTML = '<li class="list-group-item text-muted text-center small">No hay documentos cargados.</li>';
        }
        
        // PLD History
        renderPldHistory(data.pld_history);
    }
    
    function renderRisk(riskData) {
        const list = document.getElementById('risk-breakdown-list');
        list.innerHTML = '';
        
        // Update Header Badge using Dynamic Data
        const total = parseFloat(riskData.total).toFixed(2);
        const label = riskData.label || 'Desconocido';
        const color = riskData.color || '#6c757d';
        
        const badgeSpan = document.getElementById('riskHeaderBadge');
        // Use inline style for dynamic DB color
        badgeSpan.innerHTML = `<span class="badge fs-6" style="background-color: ${color}; color: #fff;">Riesgo ${label}: ${total}%</span>`;
        
        document.getElementById('risk-total-score').textContent = total + '%';

        if (!riskData || !riskData.details || riskData.details.length === 0) {
            list.innerHTML = '<tr><td colspan="5" class="text-center">No hay factores configurados.</td></tr>';
            return;
        }

        riskData.details.forEach(d => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${d.factor}</td>
                <td class="text-muted">${d.weight}%</td>
                <td class="fw-medium">${d.value_name}</td>
                <td>${d.risk_score} / 100</td>
                <td class="fw-bold text-primary">+${parseFloat(d.contribution).toFixed(2)}</td>
            `;
            list.appendChild(tr);
        });
    }

    function renderPldHistory(history) {
        const list = document.getElementById('pld-history-list');
        list.innerHTML = '';
        if (!history || history.length === 0) {
            list.innerHTML = '<tr><td colspan="6" class="text-center text-muted small">Sin historial de búsquedas.</td></tr>';
            return;
        }
        
        history.forEach(h => {
            let badge = h.riesgo_detectado == 1 ? '<span class="badge badge-risk">COINCIDENCIA</span>' : '<span class="badge badge-clean">LIMPIO</span>';
            
            let confirmacion = '<span class="text-muted small">Pendiente</span>';
            if (h.coincidencia_seleccionada) {
                if (h.coincidencia_seleccionada === 'none') {
                    confirmacion = '<span class="text-success small fw-bold">Confirmado: Ninguna</span>';
                } else {
                    try {
                        const sel = JSON.parse(h.coincidencia_seleccionada);
                        // Try all possible name keys
                        const name = sel.nombreCompleto || sel.Nombre || sel[1] || 'Registro Seleccionado';
                        confirmacion = `<span class="text-danger small fw-bold">Confirmado: ${name}</span>`;
                    } catch(e) {
                        confirmacion = '<span class="text-danger small fw-bold">Confirmado: Registro</span>';
                    }
                }
            } else if (h.riesgo_detectado == 0) {
                confirmacion = '<span class="text-success small">Auto-validado (Sin Riesgo)</span>';
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${new Date(h.fecha_busqueda).toLocaleString()}</td>
                <td>${h.usuario_nombre || 'Usuario ID ' + h.id_usuario}</td>
                <td class="small text-truncate" style="max-width: 150px;">${h.nombre_buscado}</td>
                <td>${badge}</td>
                <td>${confirmacion}</td>
                <td class="small text-muted fst-italic text-truncate" style="max-width: 200px;">${h.comentarios || '-'}</td>
            `;
            list.appendChild(tr);
        });
    }
    
    function populateList(listId, items, formatter) {
        const list = document.getElementById(listId);
        list.innerHTML = '';
        if (!items || items.length === 0) { list.innerHTML = '<li class="list-group-item text-muted">No hay registros.</li>'; return; }
        items.forEach(item => { const li = document.createElement('li'); li.className = 'list-group-item'; li.innerHTML = formatter(item); list.appendChild(li); });
    }
    
    function populateApoderadosList(apoderados) {
        const list = document.getElementById('apoderados-list-display');
        list.innerHTML = '';
        if (!apoderados || apoderados.length === 0) { list.innerHTML = '<div class="text-muted text-center py-2">No hay apoderados registrados.</div>'; return; }
        apoderados.forEach(apo => {
            const card = document.createElement('div'); card.className = 'card mb-3';
            let html = `<div class="card-body"><h6 class="card-title">Apoderado</h6>`;
            if(apo.persona_data && apo.persona_data.nombre) html += `<div class="row"><div class="col-md-6"><strong>Nombre:</strong> ${apo.persona_data.nombre} ${apo.persona_data.apellido_paterno}</div></div>`;
            else if(apo.persona_data && apo.persona_data.razon_social) html += `<div class="row"><div class="col-md-6"><strong>Razón Social:</strong> ${apo.persona_data.razon_social}</div></div>`;
            
            let contactsHtml = '';
            if (apo.contactos && apo.contactos.length > 0) {
                contactsHtml = '<ul class="list-group list-group-flush mt-2">';
                apo.contactos.forEach(c => {
                    contactsHtml += `<li class="list-group-item small"><strong>${c.tipo_nombre || 'Contacto'}:</strong> ${c.dato_contacto}</li>`;
                });
                contactsHtml += '</ul>';
            } else {
                contactsHtml = '<ul class="list-group list-group-flush mt-2"><li class="list-group-item small text-muted">Sin contactos</li></ul>';
            }
            
            html += contactsHtml + `</div>`;
            card.innerHTML = html;
            list.appendChild(card);
        });
    }

    function openPldCheck() {
        if (!clientDataCache) return;
        const modal = new bootstrap.Modal(document.getElementById('pldModal'));
        modal.show();
        document.getElementById('pldLoading').style.display = 'block';
        document.getElementById('pldResults').style.display = 'none';
        document.getElementById('pldClean').style.display = 'none';
        document.getElementById('btnConfirmPld').style.display = 'none';
        
        let pldData = { save_history: true, id_cliente: clientId };
        if (clientDataCache.general.tipo_persona_nombre === 'Física') {
            pldData.nombre = clientDataCache.persona.nombre;
            pldData.paterno = clientDataCache.persona.apellido_paterno;
            pldData.materno = clientDataCache.persona.apellido_materno || '';
            pldData.tipo_persona = 'fisica';
        } else {
            pldData.nombre = clientDataCache.persona.razon_social;
            pldData.tipo_persona = 'moral';
        }

        fetch('api/validate_person.php', { method: 'POST', body: JSON.stringify(pldData) })
        .then(res => res.json())
        .then(json => {
            document.getElementById('pldLoading').style.display = 'none';
            if (json.status === 'success') {
                currentSearchId = json.id_busqueda; 
                if (json.found) {
                    document.getElementById('pldResults').style.display = 'block';
                    document.getElementById('btnConfirmPld').style.display = 'block';
                    renderHits(json.data);
                } else {
                    document.getElementById('pldClean').style.display = 'block';
                    setTimeout(() => location.reload(), 1500);
                }
            } else { alert("Error: " + json.message); }
        });
    }

    function renderHits(hits) {
        const container = document.getElementById('hitsContainer');
        container.innerHTML = '';
        hits.forEach((hit, index) => {
            const div = document.createElement('div');
            div.className = 'hit-row';
            const hitValue = encodeURIComponent(JSON.stringify(hit));
            div.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="pldSelection" id="hit_${index}" value="${hitValue}">
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
        if (!currentSearchId) { alert("Error: No se encontró ID de búsqueda."); return; }
        const selectedInput = document.querySelector('input[name="pldSelection"]:checked');
        if (!selectedInput) { alert("Debe seleccionar un resultado o la opción 'Ninguna'."); return; }
        const comments = document.getElementById('pldComments').value;
        if (selectedInput.id === 'selNone' && comments.trim().length < 5) { alert("Si selecciona 'Ninguna', es obligatorio agregar un comentario."); return; }

        let selectionData = selectedInput.value;
        if (selectedInput.id !== 'selNone') { selectionData = JSON.parse(decodeURIComponent(selectedInput.value)); }

        const payload = { id_busqueda: currentSearchId, seleccion: selectionData, comentarios: comments, riesgo_final: (selectedInput.id === 'selNone') ? 0 : 1 };

        fetch('api/confirm_pld_selection.php', { method: 'POST', body: JSON.stringify(payload) })
        .then(res => res.json())
        .then(json => {
            if (json.status === 'success') {
                alert("Validación registrada correctamente.");
                location.reload();
            } else { alert("Error al guardar selección: " + json.message); }
        })
        .catch(err => alert("Error de red: " + err.message));
    }
</script>

<?php include 'templates/footer.php'; ?>