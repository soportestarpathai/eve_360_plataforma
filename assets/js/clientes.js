/**
 * Clientes Page JavaScript
 * Handles client list loading, analysis modal, and PLD threshold validation
 */

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
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error: ' + (data.message || data.error) + '</td></tr>';
                }
                return;
            }

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No hay clientes registrados.</td></tr>';
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

                // Expediente PLD Badge Logic (VAL-PLD-005)
                let expedienteBadge = '<span class="badge bg-secondary" title="Estado no verificado"><i class="fa-solid fa-question me-1"></i>No verificado</span>';
                if (client.identificacion_incompleta === 1 || client.identificacion_incompleta === '1') {
                    expedienteBadge = '<span class="badge bg-danger" title="Expediente incompleto - Bloquea operaciones PLD"><i class="fa-solid fa-times-circle me-1"></i>Incompleto</span>';
                } else if (client.expediente_completo === 1 || client.expediente_completo === '1') {
                    // Verificar si está vencido (VAL-PLD-006)
                    let fechaActualizacion = client.fecha_ultima_actualizacion_expediente;
                    if (fechaActualizacion) {
                        const fecha = new Date(fechaActualizacion);
                        const hoy = new Date();
                        const diffTime = Math.abs(hoy - fecha);
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        if (diffDays > 365) {
                            expedienteBadge = '<span class="badge bg-warning text-dark" title="Expediente vencido - Requiere actualización"><i class="fa-solid fa-exclamation-triangle me-1"></i>Vencido</span>';
                        } else {
                            expedienteBadge = '<span class="badge bg-success" title="Expediente completo y actualizado"><i class="fa-solid fa-check-circle me-1"></i>Completo</span>';
                        }
                    } else {
                        expedienteBadge = '<span class="badge bg-success" title="Expediente completo"><i class="fa-solid fa-check-circle me-1"></i>Completo</span>';
                    }
                }

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
                        <td>${expedienteBadge}</td>
                        <td><span class="${statusClass}">${client.status_nombre || 'Activo'}</span></td>
                        <td class="text-end pe-4">
                            <div class="action-buttons">
                                <a href="cliente_detalle.php?id=${client.id_cliente}" class="btn btn-sm btn-outline-secondary" title="Ver Detalles">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <a href="cliente_editar.php?id=${client.id_cliente}" class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        })
        .catch(err => {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error al cargar clientes.</td></tr>';
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
