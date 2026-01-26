/**
 * Check PLD Page JavaScript
 * Handles PLD verification form, type toggle, and API validation
 */

/**
 * Toggle between Persona Física and Persona Moral fields
 */
function toggleType() {
    const isFisica = document.getElementById('typeFisica').checked;
    document.getElementById('fieldsFisica').style.display = isFisica ? 'block' : 'none';
    document.getElementById('fieldsMoral').style.display = isFisica ? 'none' : 'block';
    
    // Clear previous results
    document.getElementById('resultArea').style.display = 'none';
}

/**
 * Handle form submission for PLD verification
 */
document.addEventListener('DOMContentLoaded', function() {
    const pldForm = document.getElementById('pldForm');
    if (!pldForm) return;

    pldForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('btnCheck');
        const resultBox = document.getElementById('resultArea');
        const resultTitle = document.getElementById('resultTitle');
        const resultBody = document.getElementById('resultBody');
        const resultIcon = document.getElementById('resultIcon');
        const searchIdDisplay = document.getElementById('searchIdDisplay');
        
        // Gather Data
        let data = {};
        const isFisica = document.getElementById('typeFisica').checked;
        
        if (isFisica) {
            data.nombre = document.getElementById('fisica_nombre').value.trim();
            data.paterno = document.getElementById('fisica_paterno').value.trim();
            data.materno = document.getElementById('fisica_materno').value.trim();
            data.tipo_persona = 'fisica';
            if (!data.nombre || !data.paterno) { 
                alert("Nombre y Apellido Paterno son requeridos."); 
                return; 
            }
        } else {
            data.nombre = document.getElementById('moral_razon').value.trim();
            data.paterno = '';
            data.materno = '';
            data.tipo_persona = 'moral';
            if (!data.nombre) { 
                alert("La Razón Social es requerida."); 
                return; 
            }
        }

        // Enable history saving for this manual check
        data.save_history = true;

        // UI Loading State
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Consultando API...';
        resultBox.style.display = 'none';

        try {
            const res = await fetch('api/validate_person.php', {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            // Handle non-JSON response gracefully
            const text = await res.text();
            let json;
            try {
                json = JSON.parse(text);
            } catch (e) {
                throw new Error("Respuesta inválida del servidor: " + text.substring(0, 100));
            }

            resultBox.style.display = 'block';
            
            if (json.status === 'success') {
                // Display Search ID for reference
                if (json.id_busqueda) {
                    searchIdDisplay.innerText = `Folio de Búsqueda: #${json.id_busqueda}`;
                }

                if (json.found) {
                    // RISK FOUND
                    resultBox.className = 'result-box danger';
                    resultIcon.innerHTML = '<i class="fa-solid fa-triangle-exclamation fa-2x"></i>';
                    resultTitle.innerHTML = 'COINCIDENCIAS ENCONTRADAS';
                    
                    let html = '<p class="mb-3 fw-bold">Se encontraron posibles coincidencias en las siguientes listas:</p>';
                    
                    // Robust Parsing for Array of Arrays (PDF Structure)
                    const hits = Array.isArray(json.data) ? json.data : [json.data];
                    
                    hits.forEach(hit => {
                        // Mapping based on API PDF Structure: 
                        // [0]=ID, [1]=Name, [2]=Entity, [3]=Role, [4]=Date, [5]=ListType, [6]=Status, [7]=Score
                        // Also support named keys if the API mapping works
                        
                        const name = hit.nombreCompleto || hit[1] || 'Nombre desconocido';
                        const list = hit.lista || hit[5] || 'Lista desconocida';
                        const entity = hit.entidad || hit[2] || 'Sin entidad';
                        const role = hit.puesto || hit[3] || '';
                        const date = hit.fecha || hit[4] || '';
                        const status = hit.estatus || hit[6] || '';
                        const scoreVal = hit.porcentaje || hit[7] || 0;
                        
                        const score = parseFloat(scoreVal);
                        const badgeClass = score >= 90 ? 'bg-danger text-white' : 'bg-warning text-dark';
                        
                        html += `
                        <div class="hit-item">
                            <div class="hit-item-header">
                                <div class="hit-item-info">
                                    <div class="hit-item-name">${name}</div>
                                    <div class="hit-item-entity">${entity}${role ? ' - ' + role : ''}</div>
                                    <div class="hit-item-date"><i class="fa-regular fa-calendar me-1"></i>${date}</div>
                                </div>
                                <div class="hit-item-badge">
                                    <span class="badge ${badgeClass} fs-6">${score}% Coincidencia</span>
                                </div>
                            </div>
                            <div class="hit-item-footer">
                                <span class="hit-badge"><i class="fa-solid fa-list me-1"></i>${list}</span>
                                <span class="hit-status">${status}</span>
                            </div>
                        </div>`;
                    });
                    resultBody.innerHTML = html;
                } else {
                    // CLEAN
                    resultBox.className = 'result-box safe';
                    resultIcon.innerHTML = '<i class="fa-solid fa-check-circle fa-2x"></i>';
                    resultTitle.innerHTML = 'SIN COINCIDENCIAS';
                    resultBody.innerHTML = '<p class="mb-0">La persona consultada no aparece en ninguna lista de riesgo, PEPs o bloqueados.</p>';
                }
            } else {
                // API Error
                resultBox.className = 'result-box danger';
                resultIcon.innerHTML = '<i class="fa-solid fa-circle-xmark fa-2x"></i>';
                resultTitle.innerText = 'Error en el servicio';
                resultBody.innerText = json.message;
            }

        } catch (error) {
            resultBox.className = 'result-box danger';
            resultIcon.innerHTML = '<i class="fa-solid fa-wifi fa-2x"></i>';
            resultTitle.innerText = 'Error de conexión';
            resultBody.innerText = error.message;
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-2"></i> Consultar Listas';
        }
    });
});

// Make toggleType available globally
window.toggleType = toggleType;
