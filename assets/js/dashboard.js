/**
 * Dashboard JavaScript - EVE 360
 * 
 * Maneja la lógica del menú circular y el gráfico de riesgo
 */

// --- MENU LOGIC ---
// Nota: menuData debe estar disponible globalmente desde index.php
let menuStack = [];

// Detectar si es móvil
const isMobile = window.innerWidth <= 768;
const radius = isMobile ? 0 : 200; // Adjusted radius for column layout

// Referencias a elementos del DOM
const container = document.getElementById('menuContainer');
const centerTitle = document.querySelector('#centerInfo h5');
const backBtn = document.getElementById('backBtn');

/**
 * Renderiza el menú circular
 */
function renderMenu(items) {
    if (!container) return;
    
    container.querySelectorAll('.menu-item').forEach(el => el.remove());
    if (!items || items.length === 0) return;

    const total = items.length;
    const startAngle = -90; 

    items.forEach((data, index) => {
        const el = document.createElement('a');
        el.className = 'menu-item';
        
        const hasSubmenu = (data.submenu && data.submenu.length > 0);
        el.href = hasSubmenu ? '#' : data.link; 

        el.innerHTML = `<i class="fa-solid ${data.icon}"></i><span>${data.label}</span>`;
        
        el.addEventListener('click', (e) => {
            if (hasSubmenu) {
                e.preventDefault();
                menuStack.push({ items: items, title: centerTitle ? centerTitle.textContent : '' });
                if (centerTitle) centerTitle.textContent = data.label;
                if (backBtn) backBtn.style.display = 'block';
                renderMenu(data.submenu);
            }
        });

        if (!isMobile) {
            const angleDeg = startAngle + (360 / total) * index;
            const angleRad = angleDeg * (Math.PI / 180);
            
            const x = Math.cos(angleRad) * radius;
            const y = Math.sin(angleRad) * radius;
            
            container.appendChild(el);

            requestAnimationFrame(() => {
                el.style.opacity = '1';
                el.style.transform = `translate(${x}px, ${y}px) scale(1)`;
            });
        } else {
            container.appendChild(el);
            el.style.opacity = '1';
            el.style.transform = 'scale(1)';
        }
    });
}

/**
 * Función para volver atrás en el menú
 */
function goBack() {
    if (menuStack.length === 0) return;
    const previousState = menuStack.pop();
    if (centerTitle) centerTitle.textContent = previousState.title;
    renderMenu(previousState.items);
    if (menuStack.length === 0 && backBtn) backBtn.style.display = 'none';
}

// Hacer goBack disponible globalmente para el onclick del botón
window.goBack = goBack;

/**
 * Inicializa el gráfico de riesgo
 * Nota: riskLabels, riskCounts, riskColors deben estar disponibles globalmente desde index.php
 */
function initChart() {
    const ctx = document.getElementById('riskChart');
    if (!ctx) return;

    // Verificar que los datos estén disponibles
    if (typeof riskLabels === 'undefined' || typeof riskCounts === 'undefined' || typeof riskColors === 'undefined') {
        console.warn('Dashboard: Datos del gráfico no disponibles');
        return;
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
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            let value = context.raw || 0;
                            let total = context.chart._metasets[context.datasetIndex].total;
                            let percentage = Math.round((value / total) * 100) + '%';
                            return `${label}: ${value} (${percentage})`;
                        }
                    }
                }
            },
            cutout: '60%' // Makes it a nice donut
        }
    });
}

/**
 * Inicialización cuando el DOM está listo
 */
document.addEventListener('DOMContentLoaded', () => {
    // Verificar que menuData esté disponible
    if (typeof menuData !== 'undefined') {
        renderMenu(menuData);
    } else {
        console.warn('Dashboard: menuData no disponible');
    }
    
    // Inicializar gráfico
    initChart();
});

/**
 * Manejar cambios de tamaño de ventana
 */
window.addEventListener('resize', () => {
    // Re-renderizar menú si cambia de móvil a desktop o viceversa
    const wasMobile = isMobile;
    const nowMobile = window.innerWidth <= 768;
    
    if (wasMobile !== nowMobile && typeof menuData !== 'undefined') {
        // Recargar página para re-renderizar correctamente
        // O podrías re-implementar renderMenu sin recargar
        location.reload();
    }
});
