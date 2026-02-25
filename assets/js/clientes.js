/**
 * Clientes Page JavaScript
 * Handles client list loading, analysis modal, and PLD threshold validation
 */

// --- 1. TABLE LOGIC: FILTERS, SEARCH, SORT, SOFT DELETE ---
const CLIENT_FILTER_KEYS = ['estatus', 'tipo_persona', 'nivel_riesgo', 'expediente', 'q'];
const SORT_KEYS = ['no_contrato', 'nombre_cliente', 'nivel_riesgo', 'rfc', 'fecha_apertura', 'expediente_pld', 'estatus_cliente'];
let clientsCache = [];
let sortState = { by: '', dir: 'asc' };

document.addEventListener('DOMContentLoaded', function() {
    initFiltersFromUrl();
    initSortFromUrl();
    bindFilterEvents();
    bindSortEvents();
    bindTableActionEvents();
    updateSortButtons();
    loadClients();
});

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getFiltersFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return {
        estatus: (params.get('estatus') || '').trim().toLowerCase(),
        tipo_persona: (params.get('tipo_persona') || '').trim().toLowerCase(),
        nivel_riesgo: (params.get('nivel_riesgo') || '').trim().toLowerCase(),
        expediente: (params.get('expediente') || '').trim().toLowerCase(),
        q: (params.get('q') || '').trim()
    };
}

function getFiltersFromControls() {
    return {
        estatus: (document.getElementById('estatusFilter')?.value || '').trim().toLowerCase(),
        tipo_persona: (document.getElementById('tipoPersonaFilter')?.value || '').trim().toLowerCase(),
        nivel_riesgo: (document.getElementById('riesgoFilter')?.value || '').trim().toLowerCase(),
        expediente: (document.getElementById('expedienteFilter')?.value || '').trim().toLowerCase(),
        q: (document.getElementById('searchInput')?.value || '').trim()
    };
}

function initFiltersFromUrl() {
    const filters = getFiltersFromUrl();
    const estatus = document.getElementById('estatusFilter');
    const tipoPersona = document.getElementById('tipoPersonaFilter');
    const riesgo = document.getElementById('riesgoFilter');
    const expediente = document.getElementById('expedienteFilter');
    const search = document.getElementById('searchInput');

    if (estatus) estatus.value = filters.estatus;
    if (tipoPersona) tipoPersona.value = filters.tipo_persona;
    if (riesgo) riesgo.value = filters.nivel_riesgo;
    if (expediente) expediente.value = filters.expediente;
    if (search) search.value = filters.q;
}

function initSortFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const sortBy = (params.get('sort_by') || '').trim();
    const sortDir = (params.get('sort_dir') || '').trim().toLowerCase();
    sortState.by = SORT_KEYS.includes(sortBy) ? sortBy : '';
    sortState.dir = sortDir === 'desc' ? 'desc' : 'asc';
}

function applyStateToUrl() {
    const params = new URLSearchParams();
    const filters = getFiltersFromControls();

    CLIENT_FILTER_KEYS.forEach((key) => {
        const value = filters[key];
        if (value) params.set(key, value);
    });
    if (sortState.by) {
        params.set('sort_by', sortState.by);
        params.set('sort_dir', sortState.dir);
    }

    const qs = params.toString();
    history.replaceState({}, '', qs ? `clientes.php?${qs}` : 'clientes.php');
}

function buildClientsApiUrl() {
    const params = new URLSearchParams();
    const filters = getFiltersFromControls();
    CLIENT_FILTER_KEYS.forEach((key) => {
        if (filters[key]) params.set(key, filters[key]);
    });
    params.set('_', String(Date.now()));
    return `api/get_clients.php?${params.toString()}`;
}

function bindFilterEvents() {
    ['estatusFilter', 'tipoPersonaFilter', 'riesgoFilter', 'expedienteFilter'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', () => {
                applyStateToUrl();
                loadClients();
            });
        }
    });

    const searchBtn = document.getElementById('searchBtn');
    if (searchBtn) {
        searchBtn.addEventListener('click', () => {
            applyStateToUrl();
            loadClients();
        });
    }

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyStateToUrl();
                loadClients();
            }
        });
    }

    const clearBtn = document.getElementById('clearFiltersBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            const estatus = document.getElementById('estatusFilter');
            const tipoPersona = document.getElementById('tipoPersonaFilter');
            const riesgo = document.getElementById('riesgoFilter');
            const expediente = document.getElementById('expedienteFilter');
            const search = document.getElementById('searchInput');

            if (estatus) estatus.value = '';
            if (tipoPersona) tipoPersona.value = '';
            if (riesgo) riesgo.value = '';
            if (expediente) expediente.value = '';
            if (search) search.value = '';

            sortState = { by: '', dir: 'asc' };
            updateSortButtons();
            applyStateToUrl();
            loadClients();
        });
    }
}

function bindSortEvents() {
    document.querySelectorAll('.sort-btn[data-sort]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.sort;
            if (!SORT_KEYS.includes(key)) return;

            if (sortState.by === key) {
                sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
            } else {
                sortState.by = key;
                sortState.dir = 'asc';
            }

            updateSortButtons();
            applyStateToUrl();
            renderClients();
        });
    });
}

function updateSortButtons() {
    document.querySelectorAll('.sort-btn[data-sort]').forEach((btn) => {
        const icon = btn.querySelector('i');
        if (!icon) return;

        btn.classList.remove('active');
        icon.className = 'fa-solid fa-sort ms-1';

        if (btn.dataset.sort === sortState.by) {
            btn.classList.add('active');
            icon.className = `fa-solid ${sortState.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down'} ms-1`;
        }
    });
}

function getSortValue(client, key) {
    switch (key) {
        case 'no_contrato':
            return (client.no_contrato || '').toString().toLowerCase();
        case 'nombre_cliente':
            return (client.nombre_cliente || '').toString().toLowerCase();
        case 'nivel_riesgo':
            return Number(client.nivel_riesgo || 0);
        case 'rfc':
            return (client.rfc || '').toString().toLowerCase();
        case 'fecha_apertura': {
            const date = client.fecha_apertura ? new Date(client.fecha_apertura).getTime() : 0;
            return Number.isFinite(date) ? date : 0;
        }
        case 'expediente_pld':
            return isExpedienteCompleto(client) ? 1 : 0;
        case 'estatus_cliente':
            return getStatusSortOrder(client);
        default:
            return '';
    }
}

function sortClients(data) {
    if (!sortState.by) return data;
    const dir = sortState.dir === 'desc' ? -1 : 1;

    return data.sort((a, b) => {
        const va = getSortValue(a, sortState.by);
        const vb = getSortValue(b, sortState.by);

        if (typeof va === 'number' && typeof vb === 'number') {
            return (va - vb) * dir;
        }
        return String(va).localeCompare(String(vb), 'es', { sensitivity: 'base' }) * dir;
    });
}

function getRiskBadge(client) {
    const score = Number(client.nivel_riesgo);
    if (!Number.isFinite(score) || score === 0) return '<span class="badge bg-secondary">Sin calcular</span>';
    if (score < 30) return '<span class="badge bg-success">Bajo</span>';
    if (score < 70) return '<span class="badge bg-warning text-dark">Medio</span>';
    return '<span class="badge bg-danger">Alto</span>';
}

function isExpedienteCompleto(client) {
    const incompleto = Number(client.identificacion_incompleta) === 1;
    const completo = Number(client.expediente_completo) === 1;
    return completo && !incompleto;
}

function getExpedienteBadge(client) {
    return isExpedienteCompleto(client)
        ? '<span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i>Completo</span>'
        : '<span class="badge bg-danger"><i class="fa-solid fa-times-circle me-1"></i>Incompleto</span>';
}

function getStatusBadge(client) {
    const normalized = normalizeClientStatus(client);
    if (normalized === 'activo') {
        return '<span class="status-active">Activo</span>';
    }
    if (normalized === 'inactivo') {
        return '<span class="status-inactive">Inactivo</span>';
    }
    if (normalized === 'cancelado') {
        return '<span class="status-cancelled">Cancelado</span>';
    }
    if (normalized === 'pendiente') {
        return '<span class="status-pending">Pendiente</span>';
    }
    return `<span class="status-pending">${escapeHtml(client.status_nombre || 'Desconocido')}</span>`;
}

function normalizeClientStatus(client) {
    const statusName = (client.status_nombre || '').toString().trim().toLowerCase();
    if (statusName.includes('cancel')) return 'cancelado';
    if (statusName.includes('pend')) return 'pendiente';
    if (statusName.includes('inact')) return 'inactivo';
    if (statusName.includes('activ')) return 'activo';

    const statusId = Number(client.id_status);
    if (statusId === 1) return 'activo';
    if (statusId === 0 || statusId === 2) return 'inactivo';
    if (statusId === 3) return 'cancelado';
    return 'desconocido';
}

function getStatusSortOrder(client) {
    const status = normalizeClientStatus(client);
    const orderMap = {
        activo: 1,
        inactivo: 2,
        pendiente: 3,
        cancelado: 4,
        desconocido: 99
    };
    return orderMap[status] || 99;
}

function renderFilterLabel() {
    const filterLabelEl = document.getElementById('filterLabel');
    if (!filterLabelEl) return;

    const filters = getFiltersFromControls();
    const chips = [];

    if (filters.q) chips.push(`<span class="badge bg-primary-subtle text-primary border">Búsqueda: ${escapeHtml(filters.q)}</span>`);
    if (filters.tipo_persona) chips.push(`<span class="badge bg-light text-dark border">Tipo: ${escapeHtml(filters.tipo_persona)}</span>`);
    if (filters.nivel_riesgo) chips.push(`<span class="badge bg-light text-dark border">Riesgo: ${escapeHtml(filters.nivel_riesgo)}</span>`);
    if (filters.expediente) chips.push(`<span class="badge bg-light text-dark border">Expediente: ${escapeHtml(filters.expediente)}</span>`);
    if (filters.estatus) chips.push(`<span class="badge bg-light text-dark border">Estatus: ${escapeHtml(filters.estatus)}</span>`);

    if (chips.length === 0) {
        filterLabelEl.style.display = 'none';
        return;
    }

    filterLabelEl.style.display = 'block';
    filterLabelEl.innerHTML = `${chips.join(' ')} <a href="clientes.php" class="small ms-2">Ver todos</a>`;
}

function renderClients() {
    const tbody = document.getElementById('clientsTableBody');
    if (!tbody) return;

    const sortedData = sortClients([...clientsCache]);
    if (sortedData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No hay clientes con los filtros seleccionados.</td></tr>';
        return;
    }

    tbody.innerHTML = sortedData.map((client) => {
        const tipoPersona = escapeHtml(client.tipo_persona_nombre || 'Sin tipo');
        const clienteNombre = escapeHtml(client.nombre_cliente || 'Sin Nombre');
        const contrato = escapeHtml(client.no_contrato || 'S/N');
        const rfc = escapeHtml(client.rfc || 'N/A');
        const fechaApertura = escapeHtml(client.fecha_apertura || '-');
        const idCliente = Number(client.id_cliente) || 0;

        return `
            <tr>
                <td class="ps-4 fw-bold text-primary">${contrato}</td>
                <td>
                    <div class="fw-medium">${clienteNombre}</div>
                    <small class="text-muted">ID: ${idCliente} | Tipo: ${tipoPersona}</small>
                </td>
                <td>${getRiskBadge(client)}</td>
                <td><span class="badge bg-light text-dark border">${rfc}</span></td>
                <td>${fechaApertura}</td>
                <td>${getExpedienteBadge(client)}</td>
                <td>${getStatusBadge(client)}</td>
                <td class="text-end pe-4">
                    <div class="action-buttons">
                        <a href="cliente_detalle.php?id=${idCliente}" class="btn btn-sm btn-outline-secondary" title="Ver Detalles">
                            <i class="fa-regular fa-eye"></i>
                        </a>
                        <a href="cliente_editar.php?id=${idCliente}" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-client" data-id="${idCliente}" data-name="${clienteNombre}" title="Eliminar (lógico)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function loadClients() {
    const tbody = document.getElementById('clientsTableBody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Cargando clientes...</td></tr>';
    }

    renderFilterLabel();

    fetch(buildClientsApiUrl())
        .then((res) => res.json())
        .then((data) => {
            if (!Array.isArray(data)) {
                throw new Error(data?.message || data?.error || 'Respuesta inválida del servidor');
            }
            clientsCache = data;
            renderClients();
        })
        .catch((err) => {
            console.error(err);
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error al cargar clientes: ${escapeHtml(err.message || 'Error desconocido')}</td></tr>`;
            }
        });
}

function bindTableActionEvents() {
    const tbody = document.getElementById('clientsTableBody');
    if (!tbody) return;

    tbody.addEventListener('click', (event) => {
        const deleteBtn = event.target.closest('.btn-delete-client');
        if (!deleteBtn) return;

        event.preventDefault();
        const idCliente = Number(deleteBtn.dataset.id || 0);
        const nombre = deleteBtn.dataset.name || 'cliente';
        if (!idCliente) return;

        const confirmed = window.confirm(`Se eliminará de forma lógica a "${nombre}".\n\nEl cliente se ocultará de consultas, reportes y transacciones.\n¿Deseas continuar?`);
        if (!confirmed) return;

        deleteBtn.disabled = true;
        fetch('api/delete_client.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_cliente: idCliente })
        })
        .then((res) => res.json())
        .then((json) => {
            if (json.status !== 'success') {
                throw new Error(json.message || 'No se pudo eliminar el cliente');
            }
            loadClients();
        })
        .catch((err) => {
            console.error(err);
            alert('Error al eliminar cliente: ' + (err.message || 'Error desconocido'));
        })
        .finally(() => {
            deleteBtn.disabled = false;
        });
    });
}

// --- 2. NEW LOGIC: ANALYSIS MODAL ---
let globalUma = 0;
let globalRules = [];
let globalActivities = [];
let globalSelectedActivityId = 0;
// Initialize modal safely (check if element exists first)
let modal; 

document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('analysisModal');
    if (modalEl) modal = new bootstrap.Modal(modalEl);

    const activitySelect = document.getElementById('activitySelect');
    if (activitySelect) {
        activitySelect.addEventListener('change', () => {
            const selectedId = Number(activitySelect.value || 0);
            setCurrentActivity(selectedId);
            resetThresholdUI();
        });
    }

    const ruleSelect = document.getElementById('ruleSelect');
    if (ruleSelect) {
        ruleSelect.addEventListener('change', resetThresholdUI);
    }

    const amountInput = document.getElementById('transactionAmount');
    if (amountInput) {
        amountInput.addEventListener('input', resetThresholdUI);
    }
});

function resetThresholdUI() {
    const warning = document.getElementById('thresholdWarning');
    const footer = document.getElementById('modalFooter');
    if (warning) warning.classList.add('d-none');
    if (footer) footer.style.display = 'flex';
}

function isAlwaysIdentificationRule(rule) {
    return Number(rule?.es_siempre_identificacion || 0) === 1;
}

function normalizeActivities(data) {
    if (Array.isArray(data?.activities) && data.activities.length > 0) {
        return data.activities.map((activity) => {
            const rules = Array.isArray(activity.rules) ? activity.rules : [];
            const allAlways = rules.length > 0 && rules.every(isAlwaysIdentificationRule);
            return {
                id_vulnerable: Number(activity.id_vulnerable || 0),
                nombre: String(activity.nombre || 'Actividad vulnerable'),
                fraccion: String(activity.fraccion || ''),
                rules,
                all_rules_always_identification: Boolean(activity.all_rules_always_identification ?? allAlways)
            };
        });
    }

    if (Array.isArray(data?.rules) && data.rules.length > 0) {
        const rules = data.rules;
        return [{
            id_vulnerable: Number(data.id_vulnerable || 0),
            nombre: String(data.nombre || 'Actividad vulnerable'),
            fraccion: String(data.fraccion || ''),
            rules,
            all_rules_always_identification: rules.every(isAlwaysIdentificationRule)
        }];
    }

    return [];
}

function shouldSkipAnalysisModal(activities) {
    if (!Array.isArray(activities) || activities.length === 0) return true;
    return activities.every((activity) => {
        const rules = Array.isArray(activity.rules) ? activity.rules : [];
        return rules.length > 0 && rules.every(isAlwaysIdentificationRule);
    });
}

function renderActivitySelect() {
    const container = document.getElementById('activityContainer');
    const select = document.getElementById('activitySelect');
    if (!container || !select) return;

    select.innerHTML = '';
    globalActivities.forEach((activity) => {
        const option = document.createElement('option');
        option.value = String(activity.id_vulnerable || 0);
        const suffix = activity.fraccion ? ` (Fracción ${activity.fraccion})` : '';
        option.textContent = `${activity.nombre}${suffix}`;
        select.appendChild(option);
    });

    container.style.display = globalActivities.length > 1 ? 'block' : 'none';
}

function renderServiceSelect() {
    const container = document.getElementById('subactivityContainer');
    const select = document.getElementById('ruleSelect');
    if (!container || !select) return;

    select.innerHTML = '';
    if (!Array.isArray(globalRules) || globalRules.length === 0) {
        container.style.display = 'none';
        return;
    }

    globalRules.forEach((rule, index) => {
        const option = document.createElement('option');
        option.value = String(index);
        option.text = rule.subactividad || `Servicio ${index + 1}`;
        select.appendChild(option);
    });

    container.style.display = globalRules.length > 1 ? 'block' : 'none';
}

function setCurrentActivity(activityId) {
    let selected = globalActivities.find((activity) => Number(activity.id_vulnerable) === Number(activityId));
    if (!selected) {
        selected = globalActivities[0] || null;
    }

    if (!selected) {
        globalSelectedActivityId = 0;
        globalRules = [];
        renderServiceSelect();
        return;
    }

    globalSelectedActivityId = Number(selected.id_vulnerable || 0);
    globalRules = Array.isArray(selected.rules) ? selected.rules : [];

    const activitySelect = document.getElementById('activitySelect');
    if (activitySelect && globalSelectedActivityId > 0) {
        activitySelect.value = String(globalSelectedActivityId);
    }

    renderServiceSelect();
}

function getCurrentRule() {
    if (!Array.isArray(globalRules) || globalRules.length === 0) return null;
    const select = document.getElementById('ruleSelect');
    const index = Number(select?.value ?? 0);
    return globalRules[index] || globalRules[0] || null;
}

function initClientCreation() {
    fetch('api/get_transaction_rules.php')
        .then((res) => res.json())
        .then((json) => {
            if (json.status === 'success') {
                const data = json.data;

                if (!data.is_vulnerable) {
                    proceedToCreate();
                    return;
                }

                globalUma = Number.parseFloat(data.uma_value || 0) || 0;
                globalActivities = normalizeActivities(data);

                if (globalActivities.length === 0) {
                    proceedToCreate();
                    return;
                }

                // Si todas las reglas aplicables son de identificación siempre obligatoria,
                // se omite la ventana de análisis y se continúa directo.
                if (shouldSkipAnalysisModal(globalActivities)) {
                    proceedToCreate();
                    return;
                }

                const umaDisplay = document.getElementById('umaDisplay');
                if (umaDisplay) {
                    umaDisplay.textContent = '$ ' + globalUma.toFixed(2) + ' MXN';
                }

                renderActivitySelect();
                setCurrentActivity(globalActivities[0]?.id_vulnerable || 0);

                const amountInput = document.getElementById('transactionAmount');
                if (amountInput) amountInput.value = '';
                resetThresholdUI();

                modal.show();
            } else {
                alert('Error al verificar configuración: ' + json.message);
            }
        })
        .catch((err) => {
            console.error(err);
            alert('Error de conexión.');
        });
}

function validateThreshold() {
    const rule = getCurrentRule();
    if (!rule) {
        proceedToCreate();
        return;
    }

    if (isAlwaysIdentificationRule(rule)) {
        proceedToCreate();
        return;
    }

    const amountInput = (document.getElementById('transactionAmount')?.value || '').trim();
    // Restricción removida: se puede dar de alta sin monto para capturas futuras.
    if (amountInput === '') {
        proceedToCreate();
        return;
    }

    const amount = Number.parseFloat(amountInput);
    if (!Number.isFinite(amount) || amount < 0) {
        proceedToCreate();
        return;
    }

    const thresholdUma = Number.parseFloat(rule.monto_identificacion || 0);
    const thresholdMXN = thresholdUma * globalUma;
    if (!Number.isFinite(thresholdMXN) || thresholdMXN <= 0) {
        proceedToCreate();
        return;
    }

    // Si supera umbral, continúa; si no, mostrar advertencia.
    if (amount >= thresholdMXN) {
        proceedToCreate();
    } else {
        const warning = document.getElementById('thresholdWarning');
        const footer = document.getElementById('modalFooter');
        if (warning) warning.classList.remove('d-none');
        if (footer) footer.style.display = 'none';
    }
}

function proceedToCreate() {
    window.location.href = 'cliente_nuevo.php';
}
