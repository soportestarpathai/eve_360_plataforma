/**
 * Dashboard JavaScript - EVE 360
 * 
 * Maneja la lógica del menú circular y el gráfico de riesgo
 */

// --- MENU LOGIC ---
// Nota: menuData debe estar disponible globalmente desde index.php
let menuStack = [];
let container = null;
let centerTitle = null;
let backBtn = null;
let isMobile = false;
const RADIUS = 180; // Radio fijo para todos los items

/**
 * Inicializa las referencias del DOM del menú
 */
function initMenuRefs() {
    container = document.getElementById('menuContainer');
    centerTitle = document.querySelector('#centerInfo h5');
    backBtn = document.getElementById('backBtn');
    isMobile = window.innerWidth <= 768;
}

/**
 * Renderiza el menú circular - Versión simplificada y funcional
 */
function renderMenu(items) {
    // Inicializar referencias si no están disponibles
    if (!container) {
        initMenuRefs();
        if (!container) {
            console.error('Menu container not found');
            return;
        }
    }
    
    // Limpiar items existentes
    const existingItems = container.querySelectorAll('.menu-item');
    existingItems.forEach(el => el.remove());
    
    // Limpiar mensaje vacío si existe
    const emptyMsg = container.querySelector('.menu-empty');
    if (emptyMsg) emptyMsg.remove();
    
    if (!items || items.length === 0) {
        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'menu-empty';
        emptyMsg.innerHTML = '<p style="color: var(--eve-gray-light); text-align: center; padding: 2rem;">No hay opciones disponibles</p>';
        container.appendChild(emptyMsg);
        return;
    }

    const total = items.length;
    const angleStep = 360 / total; // Ángulo entre cada item
    const startAngle = -90; // Empezar desde arriba (12 o'clock)

    items.forEach((data, index) => {
        // Crear elemento del menú
        const el = document.createElement('a');
        el.className = 'menu-item';
        
        const hasSubmenu = (data.submenu && data.submenu.length > 0);
        
        // Determinar el link a usar
        let menuLink = data.link || '#';
        
        // Si no tiene submenú y el link está vacío o es '#', intentar inferir el link por defecto
        if (!hasSubmenu && (!menuLink || menuLink === '#')) {
            const labelLower = (data.label || '').toLowerCase();
            if (labelLower.includes('reporte')) {
                menuLink = 'admin/reports.php';
            }
        }
        
        el.href = hasSubmenu ? '#' : menuLink; 

        el.innerHTML = `<i class="fa-solid ${data.icon || 'fa-circle'}"></i><span>${data.label || 'Sin nombre'}</span>`;
        
        // Manejar click
        el.addEventListener('click', (e) => {
            if (hasSubmenu) {
                e.preventDefault();
                e.stopPropagation();
                if (centerTitle) {
                    menuStack.push({ items: items, title: centerTitle.textContent || 'Menu Principal' });
                    centerTitle.textContent = data.label || 'Submenú';
                }
                if (backBtn) backBtn.style.display = 'block';
                renderMenu(data.submenu);
            }
            // Si no tiene submenú y tiene un link válido, dejar que el navegador siga el link
            // No prevenir el comportamiento por defecto
        });

        if (!isMobile) {
            // Calcular posición circular
            const angleDeg = startAngle + (angleStep * index);
            const angleRad = (angleDeg * Math.PI) / 180;
            
            // Calcular coordenadas X e Y desde el centro del contenedor
            const x = Math.cos(angleRad) * RADIUS;
            const y = Math.sin(angleRad) * RADIUS;
            
            // Guardar coordenadas para hover
            el.dataset.x = x;
            el.dataset.y = y;
            
            // Agregar al contenedor ANTES de posicionar
            container.appendChild(el);

            // Aplicar posición usando el centro del contenedor como referencia
            // El contenedor tiene padding: 2rem, así que calculamos desde el centro real
            const containerWidth = container.offsetWidth;
            const containerHeight = container.offsetHeight;
            const centerX = containerWidth / 2;
            const centerY = containerHeight / 2;
            
            el.style.opacity = '1';
            el.style.left = `${centerX + x}px`;
            el.style.top = `${centerY + y}px`;
            el.style.transform = 'translate(-50%, -50%)';
            el.style.position = 'absolute';
        } else {
            // Para móvil: layout vertical simple
            container.appendChild(el);
            el.style.position = 'relative';
            el.style.opacity = '1';
            el.style.left = 'auto';
            el.style.top = 'auto';
            el.style.transform = 'none';
            el.style.margin = '0.5rem auto';
        }
    });
}

/**
 * Función para volver atrás en el menú
 */
function goBack() {
    if (!container || !centerTitle) {
        initMenuRefs();
    }
    
    if (menuStack.length === 0) {
        if (backBtn) backBtn.style.display = 'none';
        return;
    }
    
    const previousState = menuStack.pop();
    if (centerTitle) {
        centerTitle.textContent = previousState.title || 'Menu Principal';
    }
    
    renderMenu(previousState.items);
    
    if (menuStack.length === 0 && backBtn) {
        backBtn.style.display = 'none';
    }
}

// Hacer goBack disponible globalmente
window.goBack = goBack;

/**
 * Construye URL de reporte con filtros (query string)
 */
function buildReportUrl(baseUrl, filters) {
    const params = new URLSearchParams();
    Object.keys(filters || {}).forEach((key) => {
        const value = filters[key];
        if (value !== undefined && value !== null && value !== '') {
            params.set(key, String(value));
        }
    });

    const query = params.toString();
    if (!query) return baseUrl;
    return `${baseUrl}${baseUrl.includes('?') ? '&' : '?'}${query}`;
}

/**
 * Redirige a reporte con filtros
 */
function navigateToReport(baseUrl, filters) {
    window.location.href = buildReportUrl(baseUrl, filters);
}

/**
 * Hace clicable la tarjeta de una gráfica para ir a reporte
 */
function makeReportCardClickable(cardId, reportUrl, defaultFilters) {
    const card = document.getElementById(cardId);
    if (!card || card.dataset.reportBound === '1') return;

    card.dataset.reportBound = '1';
    card.setAttribute('role', 'link');
    card.setAttribute('tabindex', '0');

    card.addEventListener('click', (event) => {
        if (event.target.closest('canvas')) return; // El canvas gestiona su propio click con filtros
        if (event.target.closest('a, button, input, select, textarea')) return;
        navigateToReport(reportUrl, defaultFilters);
    });

    card.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            navigateToReport(reportUrl, defaultFilters);
        }
    });
}

/**
 * Inicializa el gráfico de riesgo
 * Nota: riskLabels, riskCounts, riskColors deben estar disponibles globalmente desde index.php
 * Hover: muestra % y cantidad; el segmento se separa (hoverOffset).
 * Clic: lleva al Reporte de riesgos.
 */
function initChart() {
    const ctx = document.getElementById('riskChart');
    if (!ctx) return;
    const chartLink = document.getElementById('riskChartLink');
    const reportUrl = 'reporte_riesgos.php';

    if (typeof riskLabels === 'undefined' || typeof riskCounts === 'undefined' || typeof riskColors === 'undefined') {
        console.warn('Dashboard: Datos del gráfico no disponibles');
        return;
    }

    const navigateToRiskReport = () => {
        window.location.href = reportUrl;
    };

    if (chartLink) {
        chartLink.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                navigateToRiskReport();
            }
        });
    }

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: riskLabels,
            datasets: [{
                data: riskCounts,
                backgroundColor: riskColors,
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverBorderWidth: 4,
                hoverBorderColor: '#ffffff',
                hoverOffset: 20
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            onClick: function(evt, elements) {
                if (elements.length > 0) {
                    navigateToRiskReport();
                }
            },
            onHover: function(evt, elements) {
                ctx.style.cursor = elements.length > 0 ? 'pointer' : 'default';
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: { size: 12 }
                    }
                },
                tooltip: {
                    enabled: true,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = Number(context.raw) || 0;
                            const datasetValues = context.dataset.data || [];
                            const total = datasetValues.reduce((sum, item) => sum + (Number(item) || 0), 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                            const clientes = value === 1 ? '1 cliente' : value + ' clientes';
                            return label + ': ' + clientes + ' (' + percentage + '%)';
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });

    ctx.style.cursor = 'default';
}

/**
 * Inicializa el gráfico de barras: Clientes por mes
 */
function initMonthlyChart() {
    const ctx = document.getElementById('monthlyChart');
    if (!ctx) return;

    const reportUrl = 'clientes.php';
    const defaultFilters = {};
    makeReportCardClickable('monthlyChartCard', reportUrl, defaultFilters);

    if (typeof monthlyClients === 'undefined') return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthlyClients.labels,
            datasets: [{
                label: 'Clientes Registrados',
                data: monthlyClients.data,
                backgroundColor: 'rgba(27, 143, 234, 0.8)',
                borderColor: 'rgba(27, 143, 234, 1)',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            onClick: function(evt, elements) {
                if (evt && evt.native && typeof evt.native.stopPropagation === 'function') {
                    evt.native.stopPropagation();
                }

                if (elements.length > 0) {
                    navigateToReport(reportUrl, defaultFilters);
                    return;
                }

                navigateToReport(reportUrl, defaultFilters);
            },
            onHover: function() {
                ctx.style.cursor = 'pointer';
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    enabled: true,
                    callbacks: {
                        label: function(context) {
                            const value = Number(context.parsed.y) || 0;
                            const total = (monthlyClients.data || []).reduce((sum, item) => sum + (Number(item) || 0), 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                            return `Clientes: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    ctx.style.cursor = 'pointer';
}

/**
 * Inicializa el gráfico de líneas: Activos vs Inactivos
 */
function initStatusChart() {
    const ctx = document.getElementById('statusChart');
    if (!ctx) return;

    const reportUrl = 'clientes.php';
    const defaultFilters = {};
    makeReportCardClickable('statusChartCard', reportUrl, defaultFilters);

    if (typeof statusComparison === 'undefined') return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: statusComparison.labels,
            datasets: [
                {
                    label: 'Clientes Activos',
                    data: statusComparison.activos,
                    borderColor: 'rgba(46, 209, 255, 1)',
                    backgroundColor: 'rgba(46, 209, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                },
                {
                    label: 'Clientes Inactivos',
                    data: statusComparison.inactivos,
                    borderColor: 'rgba(199, 205, 214, 1)',
                    backgroundColor: 'rgba(199, 205, 214, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            onClick: function(evt, elements) {
                if (evt && evt.native && typeof evt.native.stopPropagation === 'function') {
                    evt.native.stopPropagation();
                }

                if (elements.length > 0) {
                    const point = elements[0];
                    const estatus = point.datasetIndex === 0 ? 'activos' : 'inactivos';
                    navigateToReport(reportUrl, Object.assign({}, defaultFilters, { estatus: estatus }));
                    return;
                }

                navigateToReport(reportUrl, defaultFilters);
            },
            onHover: function() {
                ctx.style.cursor = 'pointer';
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    enabled: true,
                    callbacks: {
                        label: function(context) {
                            const value = Number(context.parsed.y) || 0;
                            const idx = context.dataIndex;
                            const activos = Number(statusComparison.activos[idx]) || 0;
                            const inactivos = Number(statusComparison.inactivos[idx]) || 0;
                            const total = activos + inactivos;
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                            return `${context.dataset.label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    ctx.style.cursor = 'pointer';
}

/**
 * Inicializa el gráfico de barras horizontal: Top niveles de riesgo
 */
function initTopRiskChart() {
    const ctx = document.getElementById('topRiskChart');
    if (!ctx) return;

    const reportUrl = 'reporte_riesgos.php';
    const defaultFilters = { origen: 'dashboard', grafica: 'top_niveles_riesgo', top: '5' };
    makeReportCardClickable('topRiskChartCard', reportUrl, defaultFilters);

    if (typeof topRiskLevels === 'undefined' || topRiskLevels.labels.length === 0) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: topRiskLevels.labels,
            datasets: [{
                label: 'Número de Clientes',
                data: topRiskLevels.data,
                backgroundColor: topRiskLevels.colors,
                borderColor: topRiskLevels.colors.map(c => c.replace('0.8', '1')),
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'nearest',
                intersect: false
            },
            onClick: function(evt, elements) {
                if (evt && evt.native && typeof evt.native.stopPropagation === 'function') {
                    evt.native.stopPropagation();
                }

                if (elements.length > 0) {
                    const point = elements[0];
                    const riskLevel = topRiskLevels.labels[point.index] || '';
                    navigateToReport(reportUrl, Object.assign({}, defaultFilters, { nivel_riesgo: riskLevel }));
                    return;
                }

                navigateToReport(reportUrl, defaultFilters);
            },
            onHover: function() {
                ctx.style.cursor = 'pointer';
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: true,
                    callbacks: {
                        label: function(context) {
                            const value = Number(context.parsed.x) || 0;
                            const total = (topRiskLevels.data || []).reduce((sum, item) => sum + (Number(item) || 0), 0);
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                            return `Clientes: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    ctx.style.cursor = 'pointer';
}

/**
 * Inicializa el gráfico de área: Distribución acumulada
 */
function initAreaChart() {
    const ctx = document.getElementById('areaChart');
    if (!ctx) return;

    const reportUrl = 'reporte_riesgos.php';
    const defaultFilters = { origen: 'dashboard', grafica: 'distribucion_acumulada', periodo: '6m' };
    makeReportCardClickable('areaChartCard', reportUrl, defaultFilters);

    if (typeof monthlyClients === 'undefined') return;

    // Calcular datos acumulados
    let cumulative = 0;
    const cumulativeData = monthlyClients.data.map(value => {
        cumulative += value;
        return cumulative;
    });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: monthlyClients.labels,
            datasets: [{
                label: 'Total Acumulado de Clientes',
                data: cumulativeData,
                borderColor: 'rgba(11, 60, 138, 1)',
                backgroundColor: 'rgba(11, 60, 138, 0.2)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointBackgroundColor: 'rgba(11, 60, 138, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            onClick: function(evt, elements) {
                if (evt && evt.native && typeof evt.native.stopPropagation === 'function') {
                    evt.native.stopPropagation();
                }

                if (elements.length > 0) {
                    const point = elements[0];
                    const monthLabel = monthlyClients.labels[point.index] || '';
                    navigateToReport(reportUrl, Object.assign({}, defaultFilters, { mes: monthLabel }));
                    return;
                }

                navigateToReport(reportUrl, defaultFilters);
            },
            onHover: function() {
                ctx.style.cursor = 'pointer';
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    enabled: true,
                    callbacks: {
                        label: function(context) {
                            const value = Number(context.parsed.y) || 0;
                            const total = cumulativeData.length > 0 ? Number(cumulativeData[cumulativeData.length - 1]) || 0 : 0;
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                            return `Total acumulado: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    ctx.style.cursor = 'pointer';
}

/**
 * Inicialización cuando el DOM está listo
 */
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar referencias del menú
    initMenuRefs();
    
    // Verificar que menuData esté disponible y renderizar menú
    if (typeof menuData !== 'undefined' && menuData && menuData.length > 0) {
        renderMenu(menuData);
    } else {
        console.warn('Dashboard: menuData no disponible o vacío');
        // Mostrar mensaje si no hay menú
        if (container) {
            const emptyMsg = document.createElement('div');
            emptyMsg.className = 'menu-empty';
            emptyMsg.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: var(--eve-gray-light); padding: 2rem;';
            emptyMsg.innerHTML = '<i class="fa-solid fa-exclamation-triangle fa-3x mb-3"></i><p>No hay opciones de menú disponibles</p>';
            container.appendChild(emptyMsg);
        }
    }
    
    // Inicializar gráficos con delay para mejorar carga inicial
    // Solo el gráfico principal se carga inmediatamente
    initChart();
    
    // Cargar gráficos adicionales después de que la página se haya renderizado
    requestIdleCallback(() => {
        initMonthlyChart();
        initStatusChart();
        initTopRiskChart();
        initAreaChart();
    }, { timeout: 2000 }); // Fallback después de 2 segundos si el navegador no soporta requestIdleCallback
    
    // Fallback para navegadores que no soportan requestIdleCallback
    if (!window.requestIdleCallback) {
        // Cargar inmediatamente sin delay
        initMonthlyChart();
        initStatusChart();
        initTopRiskChart();
        initAreaChart();
    }
    
    // Recalcular isMobile en resize
    window.addEventListener('resize', () => {
        const wasMobile = isMobile;
        isMobile = window.innerWidth <= 768;
        
        // Si cambió de móvil a desktop o viceversa, re-renderizar menú
        if (wasMobile !== isMobile && typeof menuData !== 'undefined') {
            if (menuStack.length > 0) {
                // Si estamos en un submenú, volver al inicio
                menuStack = [];
                if (centerTitle) centerTitle.textContent = 'Menu Principal';
                if (backBtn) backBtn.style.display = 'none';
            }
            renderMenu(menuData);
        }
    });
});

