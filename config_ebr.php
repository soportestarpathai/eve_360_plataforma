<?php 
// Explicitly set base path for root-level file
include 'templates/header.php'; 
?>
<title>Configuración EBR</title>
<style>
    .config-container { max-width: 1200px; margin: 2rem auto; }
    .list-group-item.active { background-color: #0d6efd; border-color: #0d6efd; }
    .risk-slider { width: 100%; }
    .risk-value { width: 60px; display: inline-block; text-align: right; font-weight: bold; }
    .scrollable-list { max-height: 600px; overflow-y: auto; }
    .range-visual-bar { height: 20px; width: 100%; border-radius: 10px; display: flex; overflow: hidden; margin-bottom: 10px; }
    .range-segment { display: flex; align-items: center; justify-content: center; color: white; font-size: 0.75rem; font-weight: bold; transition: width 0.2s; }
</style>
</head>
<body>

<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="config-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fa-solid fa-sliders me-2"></i>Configuración de Riesgo (EBR)</h3>
    </div>

    <div class="row align-items-start">
        <!-- Left Column: Factors & Ranges -->
        <div class="col-md-4">
            
            <!-- RISK RANGES CARD -->
            <div class="card mb-4 shadow-sm border-primary">
                <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-chart-simple me-2"></i>Rangos Globales</span>
                    <button class="btn btn-sm btn-light text-primary fw-bold" onclick="saveRanges()">
                        <i class="fa-solid fa-save me-1"></i>Guardar
                    </button>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Defina los puntos de corte (0-100) para clasificar el riesgo.</p>
                    
                    <div class="range-visual-bar bg-light border">
                        <div id="visLow" class="range-segment bg-success" style="width: 30%;">Bajo</div>
                        <div id="visMed" class="range-segment bg-warning text-dark" style="width: 40%;">Medio</div>
                        <div id="visHigh" class="range-segment bg-danger" style="width: 30%;">Alto</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-success">Límite Bajo / Medio</label>
                        <div class="d-flex align-items-center">
                            <input type="range" class="form-range" id="cutoff1" min="0" max="100" step="0.01" value="30" oninput="updateRangeUI()">
                            <span class="ms-2 fw-bold text-success" id="valCutoff1">30.00</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-danger">Límite Medio / Alto</label>
                        <div class="d-flex align-items-center">
                            <input type="range" class="form-range" id="cutoff2" min="0" max="100" step="0.01" value="70" oninput="updateRangeUI()">
                            <span class="ms-2 fw-bold text-danger" id="valCutoff2">70.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FACTORS LIST -->
            <div class="card">
                <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                    <span>Factores de Riesgo</span>
                    <button class="btn btn-sm btn-outline-primary" onclick="openAddFactorModal()">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
                <div class="list-group list-group-flush" id="factorsList">
                    <div class="p-3 text-center"><i class="fa-solid fa-spinner fa-spin"></i> Cargando...</div>
                </div>
                <div class="card-footer text-muted small">
                    Suma de pesos: <span id="totalWeight" class="fw-bold">0%</span>
                </div>
            </div>
        </div>

        <!-- Right Column: Values Editor -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div id="headerContent">
                        <span class="fw-bold fs-5" id="selectedFactorTitle">Seleccione un factor</span>
                        <div class="text-muted small" id="selectedFactorWeight"></div>
                    </div>
                    <div id="headerActions" style="display:none;">
                        <button class="btn btn-sm btn-outline-secondary me-1" onclick="editCurrentFactor()">
                            <i class="fa-solid fa-pen"></i> Editar
                        </button>
                        <button class="btn btn-sm btn-success" onclick="saveValues()">
                            <i class="fa-solid fa-save"></i> Guardar Valores
                        </button>
                    </div>
                </div>
                <div class="card-body scrollable-list" style="min-height: 200px;">
                    <div id="valuesContainer">
                        <div class="text-center text-muted py-5">
                            <i class="fa-solid fa-arrow-left me-2"></i>Seleccione una categoría para configurar sus valores.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Add/Edit Factor -->
<div class="modal fade" id="factorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="factorModalTitle">Nuevo Factor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="factorForm">
                    <input type="hidden" id="factorAction" value="add">
                    <input type="hidden" id="factorId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre del Factor</label>
                        <input type="text" class="form-control" id="f_nombre" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Peso en la Matriz (%)</label>
                        <input type="number" class="form-control" id="f_peso" min="0" max="100" required>
                    </div>
                    
                    <hr>
                    <h6>Configuración de Catálogo</h6>
                    <div class="mb-3">
                        <label class="form-label">Tabla</label>
                        <input type="text" class="form-control" id="f_tabla" placeholder="Ej: cat_pais">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Campo ID</label>
                            <input type="text" class="form-control" id="f_clave" placeholder="Ej: id_pais">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Campo Nombre</label>
                            <input type="text" class="form-control" id="f_campo_nombre" placeholder="Ej: nombre">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger me-auto" id="btnDeleteFactor" onclick="deleteFactor()" style="display:none;">Eliminar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="submitFactor()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
                        list.innerHTML = '<div class="p-3 text-center text-muted small">No hay factores.</div>';
                    }
                    json.data.forEach(f => {
                        totalWeight += parseFloat(f.peso_porcentaje);
                        const a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                        
                        a.innerHTML = `
                            <span>${f.nombre_factor}</span>
                            <span class="badge bg-light text-dark border">${f.peso_porcentaje}%</span>
                        `;
                        a.onclick = (e) => {
                            e.preventDefault();
                            selectFactor(f, a);
                        };
                        list.appendChild(a);
                    });
                    const totalSpan = document.getElementById('totalWeight');
                    totalSpan.textContent = totalWeight + '%';
                    totalSpan.className = Math.abs(totalWeight - 100) < 0.1 ? 'fw-bold text-success' : 'fw-bold text-danger';
                }
            });
    }

    function selectFactor(factor, element) {
        window.currentFactor = factor; 
        
        document.querySelectorAll('.list-group-item').forEach(el => el.classList.remove('active', 'text-white'));
        document.querySelectorAll('.list-group-item .badge').forEach(el => { 
            el.classList.remove('bg-white', 'text-primary'); 
            el.classList.add('bg-light', 'text-dark'); 
        });
        element.classList.add('active', 'text-white');
        const badge = element.querySelector('.badge');
        badge.classList.remove('bg-light', 'text-dark');
        badge.classList.add('bg-white', 'text-primary');

        document.getElementById('selectedFactorTitle').textContent = factor.nombre_factor;
        document.getElementById('selectedFactorWeight').textContent = `Peso en la matriz: ${factor.peso_porcentaje}%`;
        document.getElementById('headerActions').style.display = 'block';

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
                        row.className = 'mb-3 border-bottom pb-2';
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
            if (json.status === 'success') alert('Valores actualizados.');
            else alert('Error: ' + json.message);
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
            if (json.status === 'success') alert('Rangos actualizados.');
            else alert('Error: ' + json.message);
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
                loadFactors();
                if (payload.action === 'delete') {
                    document.getElementById('valuesContainer').innerHTML = '';
                    document.getElementById('headerActions').style.display = 'none';
                    document.getElementById('selectedFactorTitle').textContent = 'Seleccione un factor';
                    window.currentFactor = null;
                }
            } else alert('Error: ' + json.message);
        });
    }
    function deleteFactor() {
        if(!confirm('¿Eliminar factor?')) return;
        document.getElementById('factorAction').value = 'delete';
        submitFactor();
    }
</script>

<?php include 'templates/footer.php'; ?>