<?php 
$id_cliente = $_GET['id'] ?? 0;
if (!$id_cliente) { die("ID de cliente no válido."); }
include 'templates/header.php'; 
?>
<title>Detalle de Cliente</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    
    <!-- Beneficiario Controlador Section (VAL-PLD-007) -->
    <div class="form-section" id="beneficiario-controlador-section" style="display: none;">
        <div class="section-title d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-users me-2"></i>Beneficiario Controlador (VAL-PLD-007 / VAL-PLD-015)</span>
            <button class="btn btn-sm btn-outline-primary" onclick="abrirModalBeneficiario()" title="Agregar Beneficiario">
                <i class="fa-solid fa-plus me-1"></i>Agregar
            </button>
        </div>
        <div id="beneficiario-controlador-status" class="alert alert-info">
            <i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando beneficiarios controladores...
        </div>
        <div id="beneficiarios-list" class="mt-3"></div>
    </div>

    <!-- Expediente PLD Section (VAL-PLD-005 y VAL-PLD-006) -->
    <div class="form-section" id="expediente-pld-section" style="display: block;">
        <div class="section-title d-flex justify-content-between align-items-center">
            <span><i class="fa-solid fa-folder-open me-2"></i>Estado del Expediente PLD (VAL-PLD-005 / VAL-PLD-006)</span>
            <div>
                <button class="btn btn-sm btn-outline-primary" onclick="validarExpedientePLD()" title="Validar Expediente">
                    <i class="fa-solid fa-check-circle me-1"></i>Validar
                </button>
                <button class="btn btn-sm btn-outline-success" onclick="updateFechaExpediente()" id="btnUpdateFecha" style="display:none;" title="Actualizar Fecha (VAL-PLD-006)">
                    <i class="fa-solid fa-calendar me-1"></i>Actualizar Fecha
                </button>
            </div>
        </div>
        <div id="expediente-pld-status" class="alert alert-info">
            <i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando estado del expediente...
        </div>
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
                
                // Mostrar sección de beneficiario controlador si es persona moral o fideicomiso
                const tipoPersona = json.data.general?.tipo_persona_nombre || '';
                if (tipoPersona.includes('Moral') || tipoPersona.includes('Fideicomiso')) {
                    document.getElementById('beneficiario-controlador-section').style.display = 'block';
                    loadBeneficiariosControladores();
                }
            } else { alert(json.message); }
        });
        
        // Cargar estado del expediente PLD
        loadExpedientePLD();
    });
    
    function loadExpedientePLD() {
        const statusDiv = document.getElementById('expediente-pld-status');
        statusDiv.innerHTML = '<div class="alert alert-info"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando estado del expediente...</div>';
        
        fetch(`api/validate_expediente_pld.php?id_cliente=${clientId}`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                }
                return res.json();
            })
            .then(data => {
                console.log('Datos recibidos del expediente:', data); // Debug
                if (data.status === 'success') {
                    // Debug completo
                    console.log('=== DEBUG EXPEDIENTE PLD ===');
                    console.log('Completo:', data.completitud?.completo);
                    console.log('Faltantes:', data.completitud?.faltantes);
                    console.log('Razón:', data.completitud?.razon);
                    console.log('Actualizado:', data.actualizacion?.actualizado);
                    console.log('Válido:', data.valido);
                    
                    // Mostrar faltantes en consola para debug
                    if (data.completitud && data.completitud.faltantes && data.completitud.faltantes.length > 0) {
                        console.log('Elementos faltantes detectados:', data.completitud.faltantes);
                    } else if (!data.completitud?.completo) {
                        console.warn('⚠️ Expediente marcado como incompleto pero NO hay faltantes en el array. Esto puede indicar que los datos existen pero están INACTIVOS (id_status = 0)');
                    }
                    renderExpedienteStatus(data);
                } else {
                    statusDiv.innerHTML = `<div class="alert alert-danger">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                        <strong>Error:</strong> ${data.message || 'Error desconocido'}
                        ${data.file ? `<br><small>Archivo: ${data.file}:${data.line}</small>` : ''}
                    </div>`;
                }
            })
            .catch(err => {
                console.error('Error al cargar expediente PLD:', err);
                statusDiv.innerHTML = `<div class="alert alert-danger">
                    <i class="fa-solid fa-exclamation-triangle me-2"></i>
                    <strong>Error de conexión:</strong> No se pudo conectar con el servidor.
                    <br><small>${err.message}</small>
                </div>`;
            });
    }
    
    function renderExpedienteStatus(data) {
        const statusDiv = document.getElementById('expediente-pld-status');
        const completitud = data.completitud || {};
        const actualizacion = data.actualizacion || {};
        const valido = data.valido;
        
        console.log('Renderizando estado:', { completitud, actualizacion, valido }); // Debug
        
        let html = '';
        
        if (valido) {
            html = `
                <div class="alert alert-success">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-check-circle fa-2x me-3"></i>
                        <div>
                            <h6 class="mb-1"><strong>Expediente Válido para Operaciones PLD</strong></h6>
                            <p class="mb-0 small">El expediente está completo y actualizado según VAL-PLD-005 y VAL-PLD-006</p>
                        </div>
                    </div>
                </div>
            `;
        } else {
            html = `
                <div class="alert alert-danger">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid fa-times-circle fa-2x me-3"></i>
                        <div>
                            <h6 class="mb-1"><strong>Expediente NO Válido - Bloquea Operaciones PLD</strong></h6>
                            <p class="mb-0 small">El expediente requiere atención antes de permitir operaciones PLD</p>
                        </div>
                    </div>
                </div>
            `;
        }
        
        html += '<div class="row mt-4">';
        
        // Completitud
        html += '<div class="col-md-6">';
        html += '<h6 class="fw-bold mb-3"><i class="fa-solid fa-clipboard-check me-2"></i>Completitud (VAL-PLD-005)</h6>';
        if (completitud.completo) {
            html += '<div class="alert alert-success mb-2"><i class="fa-solid fa-check me-2"></i>Expediente completo</div>';
        } else {
            html += '<div class="alert alert-danger mb-3"><i class="fa-solid fa-times me-2"></i>Expediente incompleto</div>';
            
            // Mostrar faltantes SIEMPRE que existan
            const faltantes = completitud.faltantes || [];
            console.log('Faltantes detectados:', faltantes); // Debug
            
            if (faltantes.length > 0) {
                html += '<div class="card border-danger mb-3 shadow-sm">';
                html += '<div class="card-header bg-danger text-white d-flex align-items-center">';
                html += '<i class="fa-solid fa-exclamation-triangle me-2"></i>';
                html += '<strong>Elementos Faltantes (' + faltantes.length + '):</strong>';
                html += '</div>';
                html += '<ul class="list-group list-group-flush">';
                faltantes.forEach((f, index) => {
                    html += `<li class="list-group-item d-flex align-items-start">
                        <span class="badge bg-danger me-2 mt-1">${index + 1}</span>
                        <span class="flex-grow-1">
                            <i class="fa-solid fa-arrow-right me-2 text-danger"></i>
                            <strong>${f}</strong>
                        </span>
                    </li>`;
                });
                html += '</ul>';
                html += '</div>';
                
                html += '<div class="alert alert-info mb-0">';
                html += '<i class="fa-solid fa-lightbulb me-2"></i>';
                html += '<small><strong>¿Cómo completar?</strong> Haz clic en "Editar Cliente" y agrega la información faltante, luego guarda los cambios.</small>';
                html += '</div>';
            } else {
                html += '<div class="alert alert-warning mb-0">';
                html += '<i class="fa-solid fa-info-circle me-2"></i>';
                html += '<small>No se detectaron elementos faltantes específicos. El expediente puede estar incompleto por otros motivos.</small>';
                html += '</div>';
            }
        }
        html += '</div>';
        
        // Actualización
        html += '<div class="col-md-6">';
        html += '<h6 class="fw-bold mb-3"><i class="fa-solid fa-calendar-check me-2"></i>Actualización (VAL-PLD-006)</h6>';
        if (actualizacion.actualizado) {
            html += '<div class="alert alert-success mb-2"><i class="fa-solid fa-check me-2"></i>Expediente actualizado</div>';
            if (actualizacion.fecha_ultima_actualizacion) {
                html += `<p class="small mb-0"><strong>Última actualización:</strong> ${actualizacion.fecha_ultima_actualizacion}</p>`;
            }
            if (document.getElementById('btnUpdateFecha')) {
                document.getElementById('btnUpdateFecha').style.display = 'none';
            }
        } else {
            html += '<div class="alert alert-danger mb-2"><i class="fa-solid fa-exclamation-triangle me-2"></i>Expediente vencido</div>';
            if (actualizacion.dias_vencido !== undefined && actualizacion.dias_vencido !== null) {
                html += `<p class="small mb-2"><strong>Días vencido:</strong> ${actualizacion.dias_vencido} días</p>`;
            }
            if (actualizacion.fecha_ultima_actualizacion) {
                html += `<p class="small mb-2"><strong>Última actualización:</strong> ${actualizacion.fecha_ultima_actualizacion}</p>`;
            }
            html += '<p class="small text-danger mb-0"><strong>⚠️ Bloquea nuevas operaciones PLD</strong></p>';
            if (document.getElementById('btnUpdateFecha')) {
                document.getElementById('btnUpdateFecha').style.display = 'inline-block';
            }
        }
        html += '</div>';
        
        html += '</div>';
        
        statusDiv.innerHTML = html;
    }
    
    function validarExpedientePLD() {
        const statusDiv = document.getElementById('expediente-pld-status');
        statusDiv.innerHTML = '<div class="alert alert-info"><i class="fa-solid fa-spinner fa-spin me-2"></i>Validando expediente...</div>';
        
        fetch(`api/validate_expediente_pld.php?id_cliente=${clientId}`, {
            method: 'POST'
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    renderExpedienteStatus(data);
                    Swal.fire({
                        icon: data.valido ? 'success' : 'error',
                        title: data.valido ? 'Expediente Válido' : 'Expediente NO Válido',
                        text: data.valido ? 'El expediente está completo y actualizado' : 'El expediente requiere atención',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error', 'Error al validar expediente', 'error');
            });
    }
    
    function updateFechaExpediente() {
        Swal.fire({
            title: '¿Actualizar fecha de expediente?',
            text: 'Esto marcará el expediente como actualizado hoy (VAL-PLD-006)',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, actualizar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('api/update_fecha_expediente.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id_cliente=' + clientId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Actualizado',
                            text: 'Fecha de expediente actualizada correctamente',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Recargar estado del expediente
                            loadExpedientePLD();
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Error al actualizar fecha', 'error');
                });
            }
        });
    }

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
    
    // ============================================
    // BENEFICIARIO CONTROLADOR (VAL-PLD-007)
    // ============================================
    
    function loadBeneficiariosControladores() {
        const statusDiv = document.getElementById('beneficiario-controlador-status');
        const listDiv = document.getElementById('beneficiarios-list');
        
        statusDiv.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando beneficiarios...';
        
        fetch(`api/beneficiario_controlador.php?id_cliente=${clientId}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    renderBeneficiarios(data);
                } else {
                    statusDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            })
            .catch(err => {
                console.error('Error al cargar beneficiarios:', err);
                statusDiv.innerHTML = `<div class="alert alert-danger">Error al cargar beneficiarios</div>`;
            });
    }
    
    function renderBeneficiarios(data) {
        const statusDiv = document.getElementById('beneficiario-controlador-status');
        const listDiv = document.getElementById('beneficiarios-list');
        const validacion = data.validacion || {};
        const beneficiarios = data.beneficiarios || [];
        
        // Mostrar estado de validación
        if (validacion.requerido) {
            if (validacion.identificado && !validacion.bloqueado) {
                statusDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fa-solid fa-check-circle me-2"></i>
                        <strong>Beneficiario Controlador Identificado</strong>
                        <br><small>Total: ${validacion.total_beneficiarios || 0} beneficiario(s)</small>
                    </div>
                `;
            } else {
                statusDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                        <strong>Beneficiario Controlador NO Identificado</strong>
                        <br><small>${validacion.razon || 'Requiere identificación'}</small>
                        ${validacion.incompletos ? '<br><small>Beneficiarios con documentación incompleta: ' + validacion.incompletos.length + '</small>' : ''}
                    </div>
                `;
            }
        } else {
            statusDiv.innerHTML = `
                <div class="alert alert-info">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    No aplica para este tipo de cliente (persona física)
                </div>
            `;
        }
        
        // Mostrar lista de beneficiarios
        if (beneficiarios.length === 0) {
            listDiv.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    No hay beneficiarios controladores registrados.
                    ${validacion.requerido ? ' <strong>Es obligatorio agregar al menos uno.</strong>' : ''}
                </div>
            `;
        } else {
            let html = '<div class="table-responsive"><table class="table table-hover">';
            html += '<thead><tr>';
            html += '<th>Tipo</th><th>Nombre</th><th>RFC</th><th>Participación</th><th>Documentación</th><th>Acciones</th>';
            html += '</tr></thead><tbody>';
            
            beneficiarios.forEach(benef => {
                const tieneDoc = benef.documento_identificacion && benef.documento_identificacion.trim() !== '';
                const tieneDeclaracion = benef.declaracion_jurada && benef.declaracion_jurada.trim() !== '';
                const docStatus = benef.tipo_persona === 'moral' ? (tieneDoc ? '✅' : '❌') : (tieneDeclaracion ? '✅' : '❌');
                
                html += `<tr>
                    <td>${benef.tipo_persona === 'moral' ? 'Moral' : 'Física'}</td>
                    <td>${benef.nombre_completo || 'N/A'}</td>
                    <td>${benef.rfc || 'N/A'}</td>
                    <td>${benef.porcentaje_participacion ? benef.porcentaje_participacion + '%' : 'N/A'}</td>
                    <td>${docStatus}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="editarBeneficiario(${benef.id_beneficiario})" title="Editar">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="eliminarBeneficiario(${benef.id_beneficiario})" title="Eliminar">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
            });
            
            html += '</tbody></table></div>';
            listDiv.innerHTML = html;
        }
    }
    
    function abrirModalBeneficiario(idBeneficiario = null) {
        Swal.fire({
            title: idBeneficiario ? 'Editar Beneficiario' : 'Agregar Beneficiario Controlador',
            html: `
                <form id="formBeneficiario">
                    <input type="hidden" id="benef_id_cliente" value="${clientId}">
                    <input type="hidden" id="benef_id_beneficiario" value="${idBeneficiario || ''}">
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Persona *</label>
                        <select class="form-select" id="benef_tipo_persona" required>
                            <option value="">-- Seleccione --</option>
                            <option value="fisica">Persona Física</option>
                            <option value="moral">Persona Moral</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" id="benef_nombre_completo" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">RFC</label>
                        <input type="text" class="form-control" id="benef_rfc" maxlength="13">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Porcentaje de Participación (%)</label>
                        <input type="number" class="form-control" id="benef_porcentaje" min="0" max="100" step="0.01">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Documento de Identificación</label>
                        <input type="file" class="form-control" id="benef_documento" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">Requerido para persona moral</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Declaración Jurada</label>
                        <input type="file" class="form-control" id="benef_declaracion" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">Requerido para persona física</small>
                    </div>
                </form>
            `,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            didOpen: () => {
                if (idBeneficiario) {
                    // Cargar datos del beneficiario para edición
                    fetch(`api/beneficiario_controlador.php?id_cliente=${clientId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success' && data.beneficiarios) {
                                const benef = data.beneficiarios.find(b => b.id_beneficiario == idBeneficiario);
                                if (benef) {
                                    document.getElementById('benef_tipo_persona').value = benef.tipo_persona || '';
                                    document.getElementById('benef_nombre_completo').value = benef.nombre_completo || '';
                                    document.getElementById('benef_rfc').value = benef.rfc || '';
                                    document.getElementById('benef_porcentaje').value = benef.porcentaje_participacion || '';
                                }
                            }
                        });
                }
            },
            preConfirm: () => {
                const formData = new FormData();
                formData.append('id_cliente', document.getElementById('benef_id_cliente').value);
                if (idBeneficiario) {
                    formData.append('id_beneficiario', idBeneficiario);
                }
                formData.append('tipo_persona', document.getElementById('benef_tipo_persona').value);
                formData.append('nombre_completo', document.getElementById('benef_nombre_completo').value);
                formData.append('rfc', document.getElementById('benef_rfc').value);
                formData.append('porcentaje_participacion', document.getElementById('benef_porcentaje').value);
                
                const docFile = document.getElementById('benef_documento').files[0];
                if (docFile) {
                    formData.append('documento_identificacion', docFile);
                }
                
                const declFile = document.getElementById('benef_declaracion').files[0];
                if (declFile) {
                    formData.append('declaracion_jurada', declFile);
                }
                
                return fetch('api/beneficiario_controlador.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        loadBeneficiariosControladores();
                        return data;
                    } else {
                        throw new Error(data.message);
                    }
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('Éxito', 'Beneficiario guardado correctamente', 'success');
            }
        });
    }
    
    function editarBeneficiario(idBeneficiario) {
        abrirModalBeneficiario(idBeneficiario);
    }
    
    function eliminarBeneficiario(idBeneficiario) {
        Swal.fire({
            title: '¿Eliminar beneficiario?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch(`api/beneficiario_controlador.php?id_beneficiario=${idBeneficiario}`, {
                    method: 'DELETE'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Eliminado', 'Beneficiario eliminado correctamente', 'success');
                        loadBeneficiariosControladores();
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'Error al eliminar beneficiario', 'error');
                });
            }
        });
    }
</script>

<?php include 'templates/footer.php'; ?>