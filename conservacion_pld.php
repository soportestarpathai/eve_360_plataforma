<?php
/**
 * Página: Conservación de Información PLD (VAL-PLD-013)
 * Gestiona la conservación de información por 10 años
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/pld_middleware.php';
require_once __DIR__ . '/config/pld_conservacion.php';

// VAL-PLD-001: Bloquear si no está habilitado
requirePLDHabilitado($pdo, false);

$page_title = "Conservación de Información PLD";
include 'templates/header.php';
?>

<link rel="stylesheet" href="assets/css/operaciones_pld.css">

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">
                <i class="fa-solid fa-archive me-2 text-primary"></i>
                Conservación y Verificación PLD
            </h2>
            <p class="text-muted mb-0">VAL-PLD-013 | Conservación 10 años — VAL-PLD-014 | Visitas de verificación</p>
        </div>
        <button class="btn btn-primary btn-conservacion-tab" onclick="abrirModalConservacion()">
            <i class="fa-solid fa-plus me-2"></i>Registrar Evidencia
        </button>
        <button class="btn btn-outline-primary btn-visitas-tab d-none" onclick="abrirModalVisita()">
            <i class="fa-solid fa-clipboard-check me-2"></i>Registrar Visita
        </button>
    </div>

    <ul class="nav nav-tabs mb-3" id="conservacionTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-conservacion-btn" data-bs-toggle="tab" data-bs-target="#tab-conservacion" type="button" role="tab">Conservación (VAL-PLD-013)</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-visitas-btn" data-bs-toggle="tab" data-bs-target="#tab-visitas" type="button" role="tab">Visitas de Verificación (VAL-PLD-014)</button>
        </li>
    </ul>

    <div class="tab-content" id="conservacionTabContent">
    <!-- Tab Conservación -->
    <div class="tab-pane fade show active" id="tab-conservacion" role="tabpanel">
    <!-- Información VAL-PLD-013 -->
    <div class="card mb-4 border-warning" style="background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%);">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-1 text-center">
                    <i class="fa-solid fa-archive fa-3x text-warning"></i>
                </div>
                <div class="col-md-11">
                    <h5 class="mb-2 text-warning">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        VAL-PLD-013 | Conservación de Información
                    </h5>
                    <p class="mb-2">
                        <strong>Generalidad:</strong> La información debe conservarse por al menos <strong>10 años</strong>. 
                        Esto incluye expedientes, documentos, avisos, operaciones y cualquier cambio o edición.
                    </p>
                    <div class="row mt-2">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fa-solid fa-check-circle me-1 text-success"></i>
                                <strong>Validaciones:</strong> Evidencia asociada | Plazo vigente (10 años) | Cambios | Ediciones
                            </small>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fa-solid fa-exclamation-triangle me-1 text-danger"></i>
                                <strong>Resultado:</strong> Falta de evidencia → <code>EXPEDIENTE_INCOMPLETO</code>
                            </small>
                        </div>
                    </div>
                    <p class="mb-1 small text-muted">Cambios y ediciones se registran con <strong>fecha de modificación</strong> en cada evidencia.</p>
                    <div class="alert alert-warning mt-2 mb-0">
                        <i class="fa-solid fa-lightbulb me-2"></i>
                        <strong>Nota:</strong> El sistema valida automáticamente que los archivos existan y que el plazo de conservación (10 años) esté vigente. 
                        Si falta evidencia o está vencida, se marca como <code>EXPEDIENTE_INCOMPLETO</code>.
                    </div>
    <!-- Alerta resultado EXPEDIENTE_INCOMPLETO cuando hay faltantes o vencidas -->
    <div id="alerta-expediente-incompleto" class="alert alert-danger mt-3 d-none" role="alert">
        <i class="fa-solid fa-exclamation-triangle me-2"></i>
        <strong>Resultado validación:</strong> <code>EXPEDIENTE_INCOMPLETO</code> — Falta evidencia o evidencia vencida. Corrija para cumplir VAL-PLD-013.
    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas Rápidas -->
    <div class="row mb-4" id="estadisticas-rapidas">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body">
                    <h3 class="mb-0 text-primary" id="total-evidencias">0</h3>
                    <small class="text-muted">Total Evidencias</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body">
                    <h3 class="mb-0 text-success" id="disponibles-count">0</h3>
                    <small class="text-muted">Disponibles</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-danger">
                <div class="card-body">
                    <h3 class="mb-0 text-danger" id="faltantes-count">0</h3>
                    <small class="text-muted">Faltantes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body">
                    <h3 class="mb-0 text-warning" id="vencidas-count">0</h3>
                    <small class="text-muted">Vencidas</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Cliente</label>
                    <select class="form-select" id="filtro-cliente">
                        <option value="">Todos los clientes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo de Evidencia</label>
                    <select class="form-select" id="filtro-tipo">
                        <option value="">Todos los tipos</option>
                        <option value="expediente">Expediente</option>
                        <option value="documento">Documento</option>
                        <option value="aviso">Aviso</option>
                        <option value="operacion">Operación</option>
                        <option value="cambio">Cambio</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" id="filtro-estado">
                        <option value="">Todos los estados</option>
                        <option value="disponible">Disponible</option>
                        <option value="faltante">Faltante</option>
                        <option value="vencida">Vencida</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-primary w-100" onclick="aplicarFiltros()">
                        <i class="fa-solid fa-filter me-2"></i>Aplicar Filtros
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Evidencias -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="tabla-conservacion">
                    <thead>
                        <tr>
                            <th>Fecha Creación</th>
                            <th>Cliente</th>
                            <th>Tipo Evidencia</th>
                            <th>Operación/Aviso</th>
                            <th>Archivo</th>
                            <th>Fecha Vencimiento</th>
                            <th>Días Restantes</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="conservacion-tbody">
                        <tr>
                            <td colspan="9" class="text-center">
                                <i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando evidencias...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Registrar Evidencia -->
<div class="modal fade" id="modalConservacion" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-archive me-2"></i>Registrar Evidencia para Conservación
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formConservacion" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cliente (Opcional)</label>
                            <select class="form-select" id="conservacion_id_cliente">
                                <option value="">-- Seleccione Cliente --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de Evidencia *</label>
                            <select class="form-select" id="conservacion_tipo_evidencia" required>
                                <option value="">-- Seleccione Tipo --</option>
                                <option value="expediente">Expediente</option>
                                <option value="documento">Documento</option>
                                <option value="aviso">Aviso</option>
                                <option value="operacion">Operación</option>
                                <option value="cambio">Cambio</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ID Operación (Opcional)</label>
                            <input type="number" class="form-control" id="conservacion_id_operacion" placeholder="ID de operación PLD">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ID Aviso (Opcional)</label>
                            <input type="number" class="form-control" id="conservacion_id_aviso" placeholder="ID de aviso PLD">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Archivo de Evidencia *</label>
                            <input type="file" class="form-control" id="conservacion_archivo" required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                            <small class="text-muted">Formatos permitidos: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG</small>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info mb-0">
                                <i class="fa-solid fa-info-circle me-2"></i>
                                <strong>Plazo de Conservación:</strong> El archivo será conservado por <strong>10 años</strong> desde la fecha de registro.
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarConservacion()">
                    <i class="fa-solid fa-save me-2"></i>Registrar Evidencia
                </button>
            </div>
        </div>
    </div>
</div>
    </div>
    <!-- Fin Tab Conservación -->

    <!-- Tab Visitas de Verificación (VAL-PLD-014) -->
    <div class="tab-pane fade" id="tab-visitas" role="tabpanel">
        <div class="card mb-4 border-info" style="background: linear-gradient(135deg, #e3f2fd 0%, #f0f7ff 100%);">
            <div class="card-body">
                <h5 class="mb-2 text-info"><i class="fa-solid fa-clipboard-check me-2"></i>VAL-PLD-014 | Atención a Visitas de Verificación</h5>
                <p class="mb-2"><strong>Generalidad:</strong> Debe existir capacidad de atención a requerimientos de autoridad.</p>
                <p class="mb-1 small"><strong>Validaciones:</strong> Acceso a expedientes | Evidencia disponible</p>
                <p class="mb-0 small text-danger"><strong>Resultado:</strong> No disponible → <code>evento crítico</code></p>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-6"><h5 class="mb-2">Visitas registradas</h5></div>
            <div class="col-md-6 text-end">
                <button class="btn btn-primary" onclick="abrirModalVisita()"><i class="fa-solid fa-plus me-2"></i>Registrar Visita</button>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr><th>Fecha</th><th>Autoridad</th><th>Tipo requerimiento</th><th>Expedientes disponibles</th><th>Estatus</th></tr>
                        </thead>
                        <tbody id="visitas-tbody">
                            <tr><td colspan="5" class="text-center text-muted">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <h5 class="mb-2 text-danger"><i class="fa-solid fa-exclamation-triangle me-2"></i>Eventos críticos</h5>
        <p class="small text-muted mb-2">Cuando expedientes o evidencia no están disponibles en una visita de verificación.</p>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Visita #</th></tr>
                        </thead>
                        <tbody id="eventos-criticos-tbody">
                            <tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Modal Registrar Visita (VAL-PLD-014) -->
<div class="modal fade" id="modalVisita" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-clipboard-check me-2"></i>Registrar Visita de Verificación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formVisita">
                    <div class="mb-3">
                        <label class="form-label">Fecha de visita *</label>
                        <input type="date" class="form-control" id="visita_fecha" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Autoridad</label>
                        <input type="text" class="form-control" id="visita_autoridad" placeholder="Ej. CNBV, UIF">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tipo de requerimiento</label>
                        <input type="text" class="form-control" id="visita_tipo_requerimiento" placeholder="Ej. Revisión de expedientes">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expedientes solicitados (IDs de cliente, separados por coma)</label>
                        <input type="text" class="form-control" id="visita_expedientes" placeholder="Ej. 1, 2, 5">
                        <small class="text-muted">Se validará que la evidencia esté disponible. Si no → evento crítico.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" id="visita_observaciones" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarVisita()"><i class="fa-solid fa-save me-2"></i>Registrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let clientesList = [];
let evidenciasList = [];

document.addEventListener('DOMContentLoaded', function() {
    cargarClientes();
    cargarEvidencias();
    var tabEl = document.getElementById('conservacionTabs');
    if (tabEl) {
        tabEl.addEventListener('shown.bs.tab', function(e) {
            var target = e.target.getAttribute('data-bs-target');
            var btnConservacion = document.querySelector('.btn-conservacion-tab');
            var btnVisitas = document.querySelector('.btn-visitas-tab');
            if (target === '#tab-visitas') {
                if (btnConservacion) btnConservacion.classList.add('d-none');
                if (btnVisitas) btnVisitas.classList.remove('d-none');
                cargarVisitas();
                cargarEventosCriticos();
            } else {
                if (btnConservacion) btnConservacion.classList.remove('d-none');
                if (btnVisitas) btnVisitas.classList.add('d-none');
            }
        });
    }
});

function cargarClientes() {
    fetch('api/get_clients.php')
        .then(res => res.json())
        .then(data => {
            clientesList = data;
            const select = document.getElementById('conservacion_id_cliente');
            const filtroSelect = document.getElementById('filtro-cliente');
            
            data.forEach(cliente => {
                const option = document.createElement('option');
                option.value = cliente.id_cliente;
                option.textContent = cliente.nombre_cliente || `Cliente #${cliente.id_cliente}`;
                select.appendChild(option);
                
                const filtroOption = option.cloneNode(true);
                filtroSelect.appendChild(filtroOption);
            });
        })
        .catch(err => console.error('Error al cargar clientes:', err));
}

function cargarEvidencias() {
    const id_cliente = document.getElementById('filtro-cliente')?.value || '';
    const tipo_evidencia = document.getElementById('filtro-tipo')?.value || '';
    const estado = document.getElementById('filtro-estado')?.value || '';
    
    let url = 'api/get_conservacion_info.php?';
    if (id_cliente) url += `id_cliente=${id_cliente}&`;
    if (tipo_evidencia) url += `tipo_evidencia=${tipo_evidencia}&`;
    if (estado === 'faltante') url += `expediente_incompleto=1&`;
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                evidenciasList = data.evidencias || [];
                renderEvidencias(evidenciasList, data.estadisticas);
                actualizarEstadisticas(data.estadisticas);
            } else {
                const tbody = document.getElementById('conservacion-tbody');
                if (tbody) {
                    tbody.innerHTML = 
                        '<tr><td colspan="9" class="text-center text-danger">Error al cargar evidencias</td></tr>';
                }
            }
        })
        .catch(err => {
            console.error('Error al cargar evidencias:', err);
            const tbody = document.getElementById('conservacion-tbody');
            if (tbody) {
                tbody.innerHTML = 
                    '<tr><td colspan="9" class="text-center text-danger">Error de conexión</td></tr>';
            }
        });
}

function renderEvidencias(evidencias, estadisticas) {
    const tbody = document.getElementById('conservacion-tbody');
    if (!tbody) return;
    
    if (evidencias.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center text-muted py-5">
                    <i class="fa-solid fa-archive fa-3x mb-3 d-block" style="opacity: 0.3;"></i>
                    <p class="mb-0">No hay evidencias registradas</p>
                    <small>Haz clic en "Registrar Evidencia" para comenzar</small>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = evidencias.map(ev => {
        const estado = ev.estado || 'disponible';
        const estadoBadge = {
            'disponible': '<span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i>Disponible</span>',
            'faltante': '<span class="badge bg-danger"><i class="fa-solid fa-exclamation-triangle me-1"></i>Faltante</span>',
            'vencida': '<span class="badge bg-warning"><i class="fa-solid fa-clock me-1"></i>Vencida</span>'
        }[estado] || '<span class="badge bg-secondary">Desconocido</span>';
        
        const diasRestantes = ev.dias_restantes || 0;
        const fechaVencimiento = new Date(ev.fecha_vencimiento);
        const hoy = new Date();
        const diasClass = diasRestantes <= 365 ? 'text-danger fw-bold' : diasRestantes <= 1095 ? 'text-warning' : 'text-success';
        
        const nombreArchivo = ev.ruta_evidencia ? ev.ruta_evidencia.split('/').pop().split('\\').pop() : '-';
        // La ruta ya es relativa desde el directorio raíz (ej: "uploads/conservacion/archivo.pdf")
        // conservacion_pld.php está en el directorio raíz, así que usamos la ruta directamente
        const rutaCompleta = ev.ruta_evidencia ? 
            (ev.ruta_evidencia.startsWith('http') ? ev.ruta_evidencia : 
             ev.ruta_evidencia.startsWith('/') ? ev.ruta_evidencia : 
             ev.ruta_evidencia) : '#';
        
        return `
            <tr class="${estado === 'faltante' || estado === 'vencida' ? 'table-danger' : ''}">
                <td><strong>${ev.fecha_creacion ? ev.fecha_creacion.split(' ')[0] : '-'}</strong></td>
                <td>
                    ${ev.cliente_nombre || '<span class="text-muted">-</span>'}
                </td>
                <td>
                    <span class="badge bg-info">${ev.tipo_evidencia || '-'}</span>
                </td>
                <td>
                    ${ev.id_operacion ? `<span class="badge bg-secondary">Op. #${ev.id_operacion}</span>` : ''}
                    ${ev.id_aviso ? `<span class="badge bg-secondary">Aviso #${ev.id_aviso}</span>` : ''}
                    ${!ev.id_operacion && !ev.id_aviso ? '<span class="text-muted">-</span>' : ''}
                </td>
                <td>
                    ${ev.archivo_existe ? 
                        `<a href="${rutaCompleta}" target="_blank" class="text-primary">
                            <i class="fa-solid fa-file me-1"></i>${nombreArchivo}
                        </a>` : 
                        `<span class="text-danger"><i class="fa-solid fa-exclamation-triangle me-1"></i>No encontrado</span>`
                    }
                </td>
                <td class="${diasClass}">
                    <strong>${ev.fecha_vencimiento || '-'}</strong>
                </td>
                <td class="${diasClass}">
                    ${diasRestantes > 0 ? `${diasRestantes} días` : '<span class="text-danger">Vencido</span>'}
                </td>
                <td>${estadoBadge}</td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="verDetalle(${ev.id_conservacion})" title="Ver Detalle">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    ${ev.archivo_existe ? 
                        `<a href="${rutaCompleta}" target="_blank" class="btn btn-sm btn-primary" title="Descargar">
                            <i class="fa-solid fa-download"></i>
                        </a>` : ''
                    }
                </td>
            </tr>
        `;
    }).join('');
}

function actualizarEstadisticas(estadisticas) {
    if (estadisticas) {
        document.getElementById('total-evidencias').textContent = estadisticas.total || 0;
        document.getElementById('disponibles-count').textContent = estadisticas.disponibles || 0;
        document.getElementById('faltantes-count').textContent = estadisticas.faltantes || 0;
        document.getElementById('vencidas-count').textContent = estadisticas.vencidas || 0;
        var alerta = document.getElementById('alerta-expediente-incompleto');
        if (alerta) {
            var incompleto = (estadisticas.faltantes || 0) > 0 || (estadisticas.vencidas || 0) > 0;
            alerta.classList.toggle('d-none', !incompleto);
        }
    }
}

function aplicarFiltros() {
    cargarEvidencias();
}

function abrirModalConservacion() {
    document.getElementById('formConservacion').reset();
    new bootstrap.Modal(document.getElementById('modalConservacion')).show();
}

function guardarConservacion() {
    const form = document.getElementById('formConservacion');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData();
    formData.append('tipo_evidencia', document.getElementById('conservacion_tipo_evidencia').value);
    formData.append('archivo_evidencia', document.getElementById('conservacion_archivo').files[0]);
    
    const id_cliente = document.getElementById('conservacion_id_cliente').value;
    const id_operacion = document.getElementById('conservacion_id_operacion').value;
    const id_aviso = document.getElementById('conservacion_id_aviso').value;
    
    if (id_cliente) formData.append('id_cliente', id_cliente);
    if (id_operacion) formData.append('id_operacion', id_operacion);
    if (id_aviso) formData.append('id_aviso', id_aviso);
    
    fetch('api/registrar_conservacion.php', {
        method: 'POST',
        body: formData
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(err => {
                throw new Error(err.message || `Error ${res.status}: ${res.statusText}`);
            });
        }
        return res.json();
    })
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Evidencia Registrada',
                html: `<p>${data.message}</p>
                       <p><strong>Fecha de Vencimiento:</strong> ${data.fecha_vencimiento}</p>
                       <p class="text-muted">La evidencia será conservada por 10 años</p>`,
                confirmButtonText: 'Aceptar'
            }).then(() => {
                bootstrap.Modal.getInstance(document.getElementById('modalConservacion')).hide();
                cargarEvidencias();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Error al registrar evidencia',
                footer: data.debug ? 'Revisa la consola para más detalles' : ''
            });
            if (data.debug) {
                console.error('Debug info:', data.debug);
            }
        }
    })
    .catch(err => {
        console.error('Error:', err);
        let errorMessage = 'Error al registrar evidencia';
        if (err.message) {
            errorMessage = err.message;
        }
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorMessage,
            footer: 'Revisa la consola para más detalles'
        });
    });
}

function verDetalle(idConservacion) {
    const evidencia = evidenciasList.find(e => e.id_conservacion == idConservacion);
    if (!evidencia) {
        Swal.fire('Error', 'Evidencia no encontrada', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Detalle de Evidencia',
        html: `
            <div class="text-start">
                <p><strong>Cliente:</strong> ${evidencia.cliente_nombre || '-'}</p>
                <p><strong>Tipo:</strong> ${evidencia.tipo_evidencia}</p>
                <p><strong>Fecha Creación:</strong> ${evidencia.fecha_creacion || '-'}</p>
                <p><strong>Fecha Vencimiento:</strong> ${evidencia.fecha_vencimiento || '-'}</p>
                <p><strong>Días Restantes:</strong> ${evidencia.dias_restantes || 0} días</p>
                <p><strong>Estado:</strong> ${evidencia.estado || '-'}</p>
                <p><strong>Archivo:</strong> ${evidencia.ruta_evidencia || '-'}</p>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Cerrar'
    });
}

// VAL-PLD-014: Visitas de verificación y eventos críticos
function cargarVisitas() {
    fetch('api/get_visitas_verificacion.php')
        .then(res => res.json())
        .then(data => {
            var tbody = document.getElementById('visitas-tbody');
            if (!tbody) return;
            if (data.status !== 'success' || !data.visitas || data.visitas.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No hay visitas registradas</td></tr>';
                return;
            }
            tbody.innerHTML = data.visitas.map(v => {
                var disp = v.expedientes_disponibles == 1 ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-danger">No (evento crítico)</span>';
                return '<tr><td>' + (v.fecha_visita || '-') + '</td><td>' + (v.autoridad || '-') + '</td><td>' + (v.tipo_requerimiento || '-') + '</td><td>' + disp + '</td><td><span class="badge bg-secondary">' + (v.estatus || '-') + '</span></td></tr>';
            }).join('');
        })
        .catch(err => {
            var tbody = document.getElementById('visitas-tbody');
            if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error al cargar</td></tr>';
        });
}

function cargarEventosCriticos() {
    fetch('api/get_eventos_criticos_pld.php')
        .then(res => res.json())
        .then(data => {
            var tbody = document.getElementById('eventos-criticos-tbody');
            if (!tbody) return;
            if (data.status !== 'success' || !data.eventos || data.eventos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay eventos críticos</td></tr>';
                return;
            }
            tbody.innerHTML = data.eventos.map(e => {
                var fecha = e.fecha_evento ? e.fecha_evento.replace(' ', '<br>') : '-';
                return '<tr><td>' + fecha + '</td><td><span class="badge bg-danger">' + (e.tipo || '-') + '</span></td><td>' + (e.descripcion || '-') + '</td><td>' + (e.id_visita ? '#' + e.id_visita : '-') + '</td></tr>';
            }).join('');
        })
        .catch(err => {
            var tbody = document.getElementById('eventos-criticos-tbody');
            if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error al cargar</td></tr>';
        });
}

function abrirModalVisita() {
    document.getElementById('formVisita').reset();
    document.getElementById('visita_fecha').value = new Date().toISOString().slice(0, 10);
    new bootstrap.Modal(document.getElementById('modalVisita')).show();
}

function guardarVisita() {
    var fecha = document.getElementById('visita_fecha').value;
    if (!fecha) {
        Swal.fire('Aviso', 'Indique la fecha de visita', 'warning');
        return;
    }
    var expedientes = document.getElementById('visita_expedientes').value;
    var arr = expedientes ? expedientes.split(',').map(function(x) { return parseInt(x.trim(), 10); }).filter(function(n) { return !isNaN(n); }) : null;
    var payload = {
        fecha_visita: fecha,
        autoridad: document.getElementById('visita_autoridad').value || null,
        tipo_requerimiento: document.getElementById('visita_tipo_requerimiento').value || null,
        expedientes_solicitados: arr,
        observaciones: document.getElementById('visita_observaciones').value || null
    };
    fetch('api/registrar_visita_verificacion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            var msg = data.message;
            if (data.evento_critico) msg += ' Se registró un <strong>evento crítico</strong> (expedientes no disponibles).';
            Swal.fire({ icon: 'success', title: 'Visita registrada', html: msg }).then(function() {
                bootstrap.Modal.getInstance(document.getElementById('modalVisita')).hide();
                cargarVisitas();
                cargarEventosCriticos();
            });
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Error al registrar' });
        }
    })
    .catch(err => {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Error de conexión' });
    });
}
</script>

<?php include 'templates/footer.php'; ?>
