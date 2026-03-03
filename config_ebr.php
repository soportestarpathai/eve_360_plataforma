<?php 
include 'templates/header.php'; 
?>
<title>Configuración EBR - <?= htmlspecialchars($appConfig['nombre_empresa'] ?? 'EVE 360') ?></title>
<style>
:root {
    --ebr-primary: #4361ee;
    --ebr-primary-dark: #3a0ca3;
    --ebr-success: #06d6a0;
    --ebr-warning: #f77f00;
    --ebr-danger: #ef476f;
    --ebr-dark: #1d3557;
    --ebr-light: #f8f9fc;
    --ebr-border: #e2e8f0;
    --ebr-shadow: 0 4px 24px rgba(0,0,0,.06);
    --ebr-radius: 16px;
    --ebr-radius-sm: 10px;
    --ebr-transition: .25s cubic-bezier(.4,0,.2,1);
}
.ebr-wrapper { max-width: 1200px; margin: 0 auto; }

.ebr-page-header {
    background: linear-gradient(135deg, var(--ebr-primary) 0%, var(--ebr-primary-dark) 100%);
    color: #fff; border-radius: var(--ebr-radius); padding: 1.75rem 2rem; margin-bottom: 1.75rem;
    position: relative; overflow: hidden;
}
.ebr-page-header::before {
    content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px;
    background: rgba(255,255,255,.06); border-radius: 50%;
}
.ebr-page-header h2 { font-size: 1.5rem; font-weight: 800; margin: 0; }
.ebr-page-header p { opacity: .9; margin: .35rem 0 0; font-size: .9rem; }

.ebr-card {
    border: none; border-radius: var(--ebr-radius); overflow: hidden;
    box-shadow: var(--ebr-shadow); transition: var(--ebr-transition);
}
.ebr-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,.1); }
.ebr-card .card-header {
    background: linear-gradient(135deg, #1d3557 0%, #2d4a6f 100%);
    color: #fff; font-weight: 600; padding: 1rem 1.25rem; border: none;
}
.ebr-card .card-body { padding: 1.25rem; background: #fff; }
.ebr-card .card-footer {
    background: linear-gradient(180deg, #fafbff 0%, #f1f5f9 100%);
    border-top: 1px solid var(--ebr-border); padding: .75rem 1.25rem; font-size: .85rem;
}

.ebr-ranges-card .card-header { background: linear-gradient(135deg, var(--ebr-primary) 0%, var(--ebr-primary-dark) 100%); }
.ebr-btn-save {
    background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.4);
    color: #fff; font-weight: 600; padding: .4rem 1rem; border-radius: 8px;
    transition: var(--ebr-transition);
}
.ebr-btn-save:hover { background: rgba(255,255,255,.3); color: #fff; border-color: rgba(255,255,255,.5); }

.range-visual-bar {
    height: 28px; width: 100%; border-radius: 14px; display: flex; overflow: hidden;
    margin-bottom: 1.25rem; box-shadow: inset 0 2px 4px rgba(0,0,0,.08);
}
.range-segment {
    display: flex; align-items: center; justify-content: center; color: white;
    font-size: .75rem; font-weight: 700; transition: width var(--ebr-transition);
    text-shadow: 0 1px 2px rgba(0,0,0,.2);
}
.range-segment:first-child { background: linear-gradient(180deg, #06d6a0, #028a6e); }
.range-segment:nth-child(2) { background: linear-gradient(180deg, #f77f00, #e85d04); color: #fff; }
.range-segment:last-child { background: linear-gradient(180deg, #ef476f, #d62828); }

.form-range { accent-color: var(--ebr-primary); }
.form-range::-webkit-slider-thumb { box-shadow: 0 2px 8px rgba(67,97,238,.3); }

.ebr-factor-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: .85rem 1rem; border-radius: var(--ebr-radius-sm); margin-bottom: 4px;
    cursor: pointer; transition: var(--ebr-transition);
    border: 1px solid transparent; background: #fff;
}
.ebr-factor-item:hover { background: linear-gradient(90deg, rgba(67,97,238,.06) 0%, #fff 100%); border-color: var(--ebr-border); }
.ebr-factor-item.active {
    background: linear-gradient(135deg, var(--ebr-primary) 0%, var(--ebr-primary-dark) 100%);
    color: #fff; border-color: transparent; box-shadow: 0 4px 14px rgba(67,97,238,.35);
}
.ebr-factor-item .badge {
    padding: .35rem .65rem; border-radius: 50px; font-weight: 600; font-size: .75rem;
}
.ebr-factor-item:not(.active) .badge { background: #e2e8f0; color: #475569; }
.ebr-factor-item.active .badge { background: rgba(255,255,255,.25); color: #fff; }

.ebr-add-factor-btn {
    background: linear-gradient(135deg, var(--ebr-primary), var(--ebr-primary-dark));
    border: none; color: #fff; font-weight: 600; padding: .4rem .85rem; border-radius: 8px;
    transition: var(--ebr-transition);
}
.ebr-add-factor-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(67,97,238,.35); color: #fff; }

.ebr-editor-card .card-header {
    background: linear-gradient(180deg, #fafbff 0%, #fff 100%);
    border-bottom: 1px solid var(--ebr-border); color: var(--ebr-dark);
    padding: 1.25rem 1.5rem;
}
.ebr-editor-card .card-body {
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    min-height: 280px;
}
.scrollable-list { max-height: 520px; overflow-y: auto; }
.scrollable-list::-webkit-scrollbar { width: 8px; }
.scrollable-list::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
.scrollable-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

.risk-slider { width: 100%; }
.risk-value {
    width: 48px; display: inline-block; text-align: right; font-weight: 700;
    font-size: 1rem; min-width: 48px;
}
.ebr-value-row {
    padding: 1rem 0; border-bottom: 1px solid var(--ebr-border);
    transition: var(--ebr-transition);
}
.ebr-value-row:last-child { border-bottom: none; }

.ebr-empty-state {
    text-align: center; padding: 3rem 2rem; color: #94a3b8;
}
.ebr-empty-state i { font-size: 3rem; opacity: .4; margin-bottom: 1rem; display: block; }

#factorModal .modal-content { border: none; border-radius: var(--ebr-radius); box-shadow: 0 24px 48px rgba(0,0,0,.15); }
#factorModal .modal-header {
    background: linear-gradient(135deg, var(--ebr-primary) 0%, var(--ebr-primary-dark) 100%);
    color: #fff; border-radius: var(--ebr-radius) var(--ebr-radius) 0 0; padding: 1.25rem 1.5rem;
}
#factorModal .modal-header .btn-close { filter: brightness(0) invert(1); opacity: .9; }
#factorModal .modal-body { padding: 1.5rem; }
#factorModal .form-control, #factorModal .form-label { border-radius: 8px; }
#factorModal .form-control:focus { border-color: var(--ebr-primary); box-shadow: 0 0 0 3px rgba(67,97,238,.15); }
#factorModal .modal-footer { border-top: 1px solid var(--ebr-border); padding: 1rem 1.5rem; }
#factorModal .btn-primary {
    background: linear-gradient(135deg, var(--ebr-primary), var(--ebr-primary-dark));
    border: none; font-weight: 600; border-radius: 8px;
}
@media (max-width: 768px) {
    .ebr-page-header { padding: 1.25rem; }
    .ebr-page-header h2 { font-size: 1.2rem; }
}
</style>
</head>
<body>

<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="container-fluid px-4 pb-5 pt-3">
<div class="ebr-wrapper">
    <div class="ebr-page-header">
        <h2><i class="fa-solid fa-sliders me-2"></i>Configuración de Riesgo (EBR)</h2>
        <p>Defina los rangos y factores de riesgo para la matriz de evaluación</p>
    </div>

    <div class="row align-items-start g-4">
        <!-- Left Column: Factors & Ranges -->
        <div class="col-lg-4">
            <div class="ebr-card ebr-ranges-card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-chart-pie me-2"></i>Rangos Globales</span>
                    <button class="btn btn-sm ebr-btn-save" onclick="saveRanges()">
                        <i class="fa-solid fa-save me-1"></i>Guardar
                    </button>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Puntos de corte (0-100) para clasificar Bajo, Medio y Alto riesgo.</p>
                    
                    <div class="range-visual-bar">
                        <div id="visLow" class="range-segment" style="width: 30%;">Bajo</div>
                        <div id="visMed" class="range-segment" style="width: 40%;">Medio</div>
                        <div id="visHigh" class="range-segment" style="width: 30%;">Alto</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold" style="color: #028a6e;"><i class="fa-solid fa-arrow-down me-1"></i>Límite Bajo / Medio</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="range" class="form-range flex-grow-1" id="cutoff1" min="0" max="100" step="0.01" value="30" oninput="updateRangeUI()">
                            <span class="fw-bold text-success" style="min-width: 50px;" id="valCutoff1">30.00</span>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold" style="color: #d62828;"><i class="fa-solid fa-arrow-up me-1"></i>Límite Medio / Alto</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="range" class="form-range flex-grow-1" id="cutoff2" min="0" max="100" step="0.01" value="70" oninput="updateRangeUI()">
                            <span class="fw-bold text-danger" style="min-width: 50px;" id="valCutoff2">70.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ebr-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-layer-group me-2"></i>Factores de Riesgo</span>
                    <button class="btn btn-sm ebr-add-factor-btn" onclick="openAddFactorModal()">
                        <i class="fa-solid fa-plus me-1"></i>Agregar
                    </button>
                </div>
                <div class="list-group list-group-flush p-2" id="factorsList" style="max-height: 380px; overflow-y: auto;">
                    <div class="p-4 text-center text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando...</div>
                </div>
                <div class="card-footer">
                    Suma de pesos: <span id="totalWeight" class="fw-bold">0%</span>
                </div>
            </div>
        </div>

        <!-- Right Column: Values Editor -->
        <div class="col-lg-8">
            <div class="ebr-card ebr-editor-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div id="headerContent">
                        <span class="fw-bold fs-5" id="selectedFactorTitle">Seleccione un factor</span>
                        <div class="text-muted small mt-1" id="selectedFactorWeight"></div>
                    </div>
                    <div id="headerActions" class="d-none">
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="editCurrentFactor()">
                                <i class="fa-solid fa-pen me-1"></i>Editar
                            </button>
                            <button class="btn btn-sm btn-success" onclick="saveValues()">
                                <i class="fa-solid fa-save me-1"></i>Guardar Valores
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body scrollable-list px-4">
                    <div id="valuesContainer">
                        <div class="ebr-empty-state">
                            <i class="fa-solid fa-arrow-left"></i>
                            <p class="mb-0 fw-medium">Seleccione una categoría para configurar sus valores de riesgo</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<!-- MODAL: Add/Edit Factor -->
<div class="modal fade" id="factorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="factorModalTitle"><i class="fa-solid fa-cog me-2"></i>Nuevo Factor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="factorForm">
                    <input type="hidden" id="factorAction" value="add">
                    <input type="hidden" id="factorId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre del Factor</label>
                        <input type="text" class="form-control" id="f_nombre" placeholder="Ej: Nacionalidad" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Peso en la Matriz (%)</label>
                        <input type="number" class="form-control" id="f_peso" min="0" max="100" placeholder="0-100" required>
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="fw-semibold text-muted mb-3"><i class="fa-solid fa-database me-1"></i>Configuración de Catálogo</h6>
                    <div class="mb-3">
                        <label class="form-label">Tabla</label>
                        <input type="text" class="form-control" id="f_tabla" placeholder="Ej: cat_pais">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Campo ID</label>
                            <input type="text" class="form-control" id="f_clave" placeholder="Ej: id_pais">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Campo Nombre</label>
                            <input type="text" class="form-control" id="f_campo_nombre" placeholder="Ej: nombre">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" id="btnDeleteFactor" onclick="deleteFactor()" style="display:none;">
                    <i class="fa-solid fa-trash-can me-1"></i>Eliminar
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="submitFactor()">
                    <i class="fa-solid fa-check me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Global state
    window.currentFactor = null;

    document.addEventListener('DOMContentLoaded', function() {
        loadFactors();
        loadRanges();
    });

    // --- FACTORS LOGIC ---
    function loadFactors() {
        fetch('api/get_ebr_config.php?action=get_factors')
            .then(res => res.json())
            .then(json => {
                const list = document.getElementById('factorsList');
                list.innerHTML = '';
                let totalWeight = 0;
                
                if (json.status === 'success') {
                    if (json.data.length === 0) {
                        list.innerHTML = '<div class="p-4 text-center text-muted small">No hay factores configurados.</div>';
                    } else {
                        list.innerHTML = '';
                        json.data.forEach(f => {
                            totalWeight += parseFloat(f.peso_porcentaje);
                            const a = document.createElement('a');
                            a.href = '#';
                            a.className = 'ebr-factor-item list-group-item-action d-flex justify-content-between align-items-center text-decoration-none text-dark';
                            a.innerHTML = `<span>${f.nombre_factor}</span><span class="badge">${f.peso_porcentaje}%</span>`;
                            a.onclick = (e) => {
                                e.preventDefault();
                                selectFactor(f, a);
                            };
                            list.appendChild(a);
                        });
                    }
                    const totalSpan = document.getElementById('totalWeight');
                    totalSpan.textContent = totalWeight + '%';
                    totalSpan.className = 'fw-bold ' + (Math.abs(totalWeight - 100) < 0.1 ? 'text-success' : 'text-danger');
                }
            });
    }

    function selectFactor(factor, element) {
        window.currentFactor = factor; 
        
        document.querySelectorAll('.ebr-factor-item').forEach(el => el.classList.remove('active'));
        element.classList.add('active');

        document.getElementById('selectedFactorTitle').textContent = factor.nombre_factor;
        document.getElementById('selectedFactorWeight').textContent = `Peso en la matriz: ${factor.peso_porcentaje}%`;
        document.getElementById('headerActions').classList.remove('d-none');

        loadValues(factor.id_factor);
    }

    function loadValues(idFactor) {
        const container = document.getElementById('valuesContainer');
        container.innerHTML = '<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin text-primary"></i> Cargando valores...</div>';

        fetch(`api/get_ebr_config.php?action=get_values&id_factor=${idFactor}`)
            .then(res => res.json())
            .then(json => {
                container.innerHTML = '';
                if (json.status === 'success') {
                    if (json.data.length === 0) {
                        // --- UPDATED ERROR HANDLING UI ---
                        const table = window.currentFactor.tabla_catalogo || 'No definida';
                        let msg = 'No se encontraron elementos en el catálogo.';
                        if(json.message) msg = json.message;
                        
                        container.innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                                ${msg}
                                <br><small class="text-muted">Tabla configurada: <strong>${table}</strong></small>
                            </div>`;
                        return;
                    }

                    json.data.forEach(item => {
                        const row = document.createElement('div');
                        row.className = 'ebr-value-row';
                        row.innerHTML = `
                            <label class="form-label d-flex justify-content-between fw-medium">
                                <span>${item.nombre_item}</span>
                                <span class="risk-value" id="val_${item.id_item}">${item.nivel_riesgo}</span>
                            </label>
                            <input type="range" class="form-range risk-slider" 
                                   min="0" max="100" step="1" 
                                   value="${item.nivel_riesgo}" 
                                   data-item-id="${item.id_item}" 
                                   oninput="updateItemLabel(this, '${item.id_item}')">
                        `;
                        container.appendChild(row);
                        updateItemLabel(row.querySelector('input'), item.id_item); 
                    });
                } else {
                     container.innerHTML = `<div class="alert alert-danger">Error: ${json.message}</div>`;
                }
            })
            .catch(err => {
                container.innerHTML = `<div class="alert alert-danger">Error de conexión: ${err.message}</div>`;
            });
    }

    function updateItemLabel(slider, id) {
        const valSpan = document.getElementById(`val_${id}`);
        const val = parseFloat(slider.value);
        valSpan.textContent = val;
        
        const cut1 = parseFloat(document.getElementById('cutoff1').value || 30);
        const cut2 = parseFloat(document.getElementById('cutoff2').value || 70);
        
        if (val <= cut1) valSpan.className = 'risk-value text-success';
        else if (val <= cut2) valSpan.className = 'risk-value text-warning';
        else valSpan.className = 'risk-value text-danger';
    }

    function saveValues() {
        if (!window.currentFactor) return;
        const items = [];
        document.querySelectorAll('.risk-slider').forEach(slider => {
            const id = slider.getAttribute('data-item-id');
            if (id) items.push({ id_item: id, risk: slider.value });
        });

        fetch('api/save_ebr_config.php', {
            method: 'POST',
            body: JSON.stringify({ id_factor: window.currentFactor.id_factor, items: items })
        }).then(res => res.json()).then(json => {
            if (json.status === 'success') Swal.fire({ icon: 'success', title: 'Guardado', text: 'Valores actualizados correctamente' });
            else Swal.fire({ icon: 'error', title: 'Error', text: json.message });
        });
    }

    // --- RANGES LOGIC ---
    function loadRanges() {
        fetch('api/get_ebr_ranges.php')
            .then(res => res.json())
            .then(json => {
                if (json.status === 'success' && json.data.length > 0) {
                    const low = json.data.find(r => r.nivel === 'Bajo');
                    const high = json.data.find(r => r.nivel === 'Alto');
                    
                    if (low && high) {
                        document.getElementById('cutoff1').value = low.max_valor;
                        document.getElementById('cutoff2').value = high.min_valor;
                        updateRangeUI();
                    }
                }
            });
    }

    function updateRangeUI() {
        const c1 = document.getElementById('cutoff1');
        const c2 = document.getElementById('cutoff2');
        
        let val1 = parseFloat(c1.value);
        let val2 = parseFloat(c2.value);

        if (val1 > val2) {
            val1 = val2 - 0.01; 
            c1.value = val1.toFixed(2);
        }

        document.getElementById('valCutoff1').textContent = val1.toFixed(2);
        document.getElementById('valCutoff2').textContent = val2.toFixed(2);

        const widthLow = val1;
        const widthMed = val2 - val1;
        const widthHigh = 100 - val2;

        document.getElementById('visLow').style.width = widthLow + '%';
        document.getElementById('visMed').style.width = widthMed + '%';
        document.getElementById('visHigh').style.width = widthHigh + '%';
        
        document.getElementById('visLow').innerText = widthLow > 15 ? 'Bajo' : '';
        document.getElementById('visMed').innerText = widthMed > 15 ? 'Medio' : '';
        document.getElementById('visHigh').innerText = widthHigh > 15 ? 'Alto' : '';
    }

    function saveRanges() {
        const val1 = parseFloat(document.getElementById('cutoff1').value);
        const val2 = parseFloat(document.getElementById('cutoff2').value);

        const ranges = [
            { nivel: 'Bajo', min: 0, max: val1, color: '#198754' },
            { nivel: 'Medio', min: val1, max: val2, color: '#ffc107' },
            { nivel: 'Alto', min: val2, max: 100, color: '#dc3545' }
        ];

        fetch('api/save_ebr_ranges.php', { method: 'POST', body: JSON.stringify({ ranges }) })
        .then(res => res.json())
        .then(json => {
            if (json.status === 'success') Swal.fire({ icon: 'success', title: 'Guardado', text: 'Rangos actualizados correctamente' });
            else Swal.fire({ icon: 'error', title: 'Error', text: json.message });
        });
    }

    // --- MODAL LOGIC ---
    const modal = new bootstrap.Modal(document.getElementById('factorModal'));
    function openAddFactorModal() {
        document.getElementById('factorAction').value = 'add';
        document.getElementById('factorId').value = '';
        document.getElementById('factorModalTitle').textContent = 'Nuevo Factor';
        document.getElementById('f_nombre').value = '';
        document.getElementById('f_peso').value = '';
        document.getElementById('f_tabla').value = '';
        document.getElementById('f_clave').value = '';
        document.getElementById('f_campo_nombre').value = '';
        document.getElementById('btnDeleteFactor').style.display = 'none';
        modal.show();
    }
    function editCurrentFactor() {
        if (!window.currentFactor) return;
        const f = window.currentFactor;
        document.getElementById('factorAction').value = 'update';
        document.getElementById('factorId').value = f.id_factor;
        document.getElementById('factorModalTitle').textContent = 'Editando: ' + f.nombre_factor;
        document.getElementById('f_nombre').value = f.nombre_factor;
        document.getElementById('f_peso').value = f.peso_porcentaje;
        document.getElementById('f_tabla').value = f.tabla_catalogo || '';
        document.getElementById('f_clave').value = f.campo_clave || '';
        document.getElementById('f_campo_nombre').value = f.campo_nombre || '';
        document.getElementById('btnDeleteFactor').style.display = 'block';
        modal.show();
    }
    function submitFactor() {
        const payload = {
            action: document.getElementById('factorAction').value,
            id_factor: document.getElementById('factorId').value,
            nombre: document.getElementById('f_nombre').value,
            peso: document.getElementById('f_peso').value,
            tabla: document.getElementById('f_tabla').value,
            clave: document.getElementById('f_clave').value,
            campo_nombre: document.getElementById('f_campo_nombre').value
        };
        fetch('api/save_ebr_factor.php', { method: 'POST', body: JSON.stringify(payload) })
        .then(res => res.json())
        .then(json => {
            if (json.status === 'success') {
                modal.hide();
                const msg = payload.action === 'delete' ? 'Factor eliminado' : 'Factor guardado correctamente';
                Swal.fire({ icon: 'success', title: 'Listo', text: msg, timer: 1500, showConfirmButton: false });
                loadFactors();
                if (payload.action === 'delete') {
                    document.getElementById('valuesContainer').innerHTML = '<div class="ebr-empty-state"><i class="fa-solid fa-arrow-left"></i><p class="mb-0 fw-medium">Seleccione una categoría para configurar sus valores de riesgo</p></div>';
                    document.getElementById('headerActions').classList.add('d-none');
                    document.getElementById('selectedFactorTitle').textContent = 'Seleccione un factor';
                    document.getElementById('selectedFactorWeight').textContent = '';
                    window.currentFactor = null;
                }
            } else Swal.fire({ icon: 'error', title: 'Error', text: json.message });
        });
    }
    async function deleteFactor() {
        const result = await Swal.fire({
            title: '¿Eliminar factor?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef476f',
            cancelButtonText: 'Cancelar',
            confirmButtonText: 'Sí, eliminar'
        });
        if (!result.isConfirmed) return;
        document.getElementById('factorAction').value = 'delete';
        submitFactor();
    }
</script>

<?php include 'templates/footer.php'; ?>