<?php include 'templates/header.php'; ?>
<title>Clientes - Investor MLP</title>
<style>
    /* Page-specific styles */
    .content-wrapper { padding: 2rem; max-width: 1200px; margin: 0 auto; }
    .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; }
    .table-hover tbody tr:hover { background-color: #f8f9fa; }
    .status-active { color: #198754; font-weight: 600; background: #d1e7dd; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
    .status-inactive { color: #6c757d; font-weight: 600; background: #e9ecef; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
    .status-pending { color: #795548; font-weight: 600; background: #fff0c2; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
    .status-cancelled { color: #dc3545; font-weight: 600; background: #f8d7da; padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; }
</style>
</head>
<body>

<?php include 'templates/top_bar.php'; ?>

<div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-0">Cartera de Clientes</h2>
            <p class="text-muted">Gestión y seguimiento de expedientes</p>
        </div>
        <div>
            <a href="check_pld.php" class="btn btn-warning text-dark shadow-sm me-2">
                <i class="fa-solid fa-shield-halved me-2"></i>Buscar en Listas
            </a>
            
            <button onclick="initClientCreation()" class="btn btn-primary shadow-sm">
                <i class="fa-solid fa-user-plus me-2"></i>Nuevo Cliente
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Contrato</th>
                            <th>Cliente</th>
                            <th>Riesgo</th>
                            <th>RFC</th>
                            <th>Fecha Alta</th>
                            <th>Estatus</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="clientsTableBody">
                        <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando clientes...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="analysisModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa-solid fa-calculator me-2"></i>Análisis de Umbral PLD</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Ingrese los datos de la operación para determinar si es obligatorio identificar al cliente según las reglas de Actividad Vulnerable.
                </p>
                
                <form id="analysisForm">
                    <div class="mb-3" id="subactivityContainer" style="display:none;">
                        <label class="form-label fw-bold">Tipo de Servicio / Actividad</label>
                        <select class="form-select" id="ruleSelect">
                            </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Monto de la Operación (MXN)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="transactionAmount" placeholder="0.00" min="0" step="0.01">
                        </div>
                        <div class="form-text">Valor UMA actual: <span id="umaDisplay" class="fw-bold text-dark">-</span></div>
                    </div>

                    <div id="thresholdWarning" class="alert alert-warning border-warning d-none">
                        <div class="d-flex">
                            <i class="fa-solid fa-circle-exclamation fs-4 me-3"></i>
                            <div>
                                <strong>Atención:</strong> Para este cliente/transacción no se requiere la identificación del cliente.
                                <br><span class="small text-dark mt-1 d-block">¿Aún así quiere darlo de alta?</span>
                            </div>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">No, Cancelar</button>
                            <button type="button" class="btn btn-sm btn-warning fw-bold" onclick="proceedToCreate()">Sí, Continuar</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" id="modalFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="validateThreshold()">Analizar</button>
            </div>
        </div>
    </div>
</div>

<script>
    // --- 1. EXISTING LOAD CLIENTS LOGIC ---
    document.addEventListener('DOMContentLoaded', loadClients);

    function loadClients() {
        const tbody = document.getElementById('clientsTableBody');
        fetch('api/get_clients.php')
            .then(res => res.json())
            .then(data => {
                tbody.innerHTML = '';
                
                // Safety check for valid array
                if (!Array.isArray(data)) {
                    console.error("Data received is not an array:", data);
                    if (data.status === 'error' || data.error) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error: ' + (data.message || data.error) + '</td></tr>';
                    }
                    return;
                }

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No hay clientes registrados.</td></tr>';
                    return;
                }
                
                data.forEach(client => {
                    // Risk Badge Logic
                    let riskBadge = '<span class="badge bg-secondary">Sin Calcular</span>';
                    if (client.nivel_riesgo !== null) {
                        const score = parseFloat(client.nivel_riesgo);
                        if (score < 30) riskBadge = '<span class="badge bg-success">Bajo</span>';
                        else if (score < 70) riskBadge = '<span class="badge bg-warning text-dark">Medio</span>';
                        else riskBadge = '<span class="badge bg-danger">Alto</span>';
                    }

                    // Status Badge Logic
                    let statusClass = 'status-active';
                    if (client.status_nombre === 'Inactivo') statusClass = 'status-inactive';
                    else if (client.status_nombre === 'Pendiente') statusClass = 'status-pending';

                    const row = `
                        <tr>
                            <td class="ps-4 fw-bold text-primary">${client.no_contrato || 'S/N'}</td>
                            <td>
                                <div class="fw-medium">${client.nombre_cliente || 'Sin Nombre'}</div>
                                <small class="text-muted">ID: ${client.id_cliente}</small>
                            </td>
                            <td>${riskBadge}</td>
                            <td><span class="badge bg-light text-dark border">${client.rfc || 'N/A'}</span></td>
                            <td>${client.fecha_apertura || '-'}</td>
                            <td><span class="${statusClass}">${client.status_nombre || 'Activo'}</span></td>
                            <td class="text-end pe-4">
                                <a href="cliente_detalle.php?id=${client.id_cliente}" class="btn btn-sm btn-outline-secondary me-1" title="Ver Detalles">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <a href="cliente_editar.php?id=${client.id_cliente}" class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error al cargar clientes.</td></tr>';
            });
    }

    // --- 2. NEW LOGIC: ANALYSIS MODAL ---
    let globalUma = 0;
    let globalRules = [];
    // Initialize modal safely (check if element exists first)
    let modal; 
    
    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('analysisModal');
        if(modalEl) modal = new bootstrap.Modal(modalEl);
    });

    function initClientCreation() {
        // 1. Fetch Rules & Config
        fetch('api/get_transaction_rules.php')
            .then(res => res.json())
            .then(json => {
                if (json.status === 'success') {
                    const data = json.data;
                    
                    // CASE A: Not Vulnerable -> Go directly to form
                    if (!data.is_vulnerable) {
                        proceedToCreate();
                        return;
                    }

                    // CASE B: Vulnerable -> Show Modal
                    globalUma = parseFloat(data.uma_value);
                    globalRules = data.rules;
                    
                    document.getElementById('umaDisplay').textContent = '$ ' + globalUma.toFixed(2) + ' MXN';
                    
                    // Setup Subactivity Dropdown
                    const select = document.getElementById('ruleSelect');
                    const container = document.getElementById('subactivityContainer');
                    select.innerHTML = '';

                    if (globalRules.length > 1) {
                        // Multiple rules: Show dropdown
                        container.style.display = 'block';
                        globalRules.forEach((rule, index) => {
                            const opt = document.createElement('option');
                            opt.value = index; 
                            opt.text = rule.subactividad;
                            select.appendChild(opt);
                        });
                    } else if (globalRules.length === 1) {
                        // Single rule: Hide dropdown, auto-select
                        container.style.display = 'none';
                        const opt = document.createElement('option');
                        opt.value = 0; 
                        select.appendChild(opt);
                    } else {
                        // No rules defined? Safe to proceed.
                        proceedToCreate();
                        return;
                    }

                    // Reset UI
                    document.getElementById('transactionAmount').value = '';
                    document.getElementById('thresholdWarning').classList.add('d-none');
                    document.getElementById('modalFooter').style.display = 'flex'; 
                    
                    modal.show();

                } else {
                    alert('Error al verificar configuración: ' + json.message);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error de conexión.');
            });
    }

    function validateThreshold() {
        const amountInput = document.getElementById('transactionAmount').value;
        if (amountInput === '') {
            alert("Por favor ingrese el monto de la operación.");
            return;
        }

        const amount = parseFloat(amountInput);
        const ruleIndex = document.getElementById('ruleSelect').value;
        const rule = globalRules[ruleIndex];

        // LOGIC: Check Identification Requirement
        // 1. Is it ALWAYS required?
        if (parseInt(rule.es_siempre_identificacion) === 1) {
            proceedToCreate(); 
            return;
        }

        // 2. Does amount surpass threshold?
        const thresholdMXN = parseFloat(rule.monto_identificacion) * globalUma;

        if (amount >= thresholdMXN) {
            proceedToCreate(); 
        } else {
            // Requirement NOT Met -> Show Warning
            document.getElementById('thresholdWarning').classList.remove('d-none');
            document.getElementById('modalFooter').style.display = 'none'; 
        }
    }

    function proceedToCreate() {
        window.location.href = 'cliente_nuevo.php';
    }
</script>

<?php include 'templates/footer.php'; ?>