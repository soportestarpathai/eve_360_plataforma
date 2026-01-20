<?php 
// Define base path for root files
$basePath = './';
include 'templates/header.php'; 
?>
<title>Verificación Preventiva PLD</title>
<style>
    .check-card { 
        max-width: 800px; 
        margin: 3rem auto; 
        background: #fff; 
        padding: 2rem; 
        border-radius: 12px; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
    }
    
    .result-box { 
        display: none; 
        margin-top: 25px; 
        padding: 20px; 
        border-radius: 8px; 
        animation: fadeIn 0.3s ease-in;
    }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .result-box.safe { 
        background-color: #d1e7dd; 
        color: #0f5132; 
        border: 1px solid #badbcc; 
    }
    
    .result-box.danger { 
        background-color: #fff5f5; 
        color: #b91c1c; 
        border: 1px solid #fecaca; 
    }
    
    .hit-item { 
        background: white; 
        padding: 15px; 
        border: 1px solid #e5e7eb; 
        margin-bottom: 10px; 
        border-radius: 8px;
        border-left: 4px solid #dc3545;
    }
    
    .hit-badge { 
        background: #fee2e2; 
        color: #991b1b; 
        font-size: 0.75rem; 
        padding: 3px 8px; 
        border-radius: 4px; 
        font-weight: bold; 
        text-transform: uppercase; 
    }
    
    .persona-specific { display: none; }
    
    /* Mobile adjustments */
    @media (max-width: 576px) {
        .check-card { margin: 1rem; padding: 1.5rem; }
    }
</style>
</head>
<body>

<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="container">
    <div class="check-card">
        <div class="d-flex align-items-center mb-4 text-primary">
            <i class="fa-solid fa-shield-halved fa-2x me-3"></i>
            <div>
                <h4 class="mb-0 fw-bold">Verificación Preventiva PLD</h4>
                <small class="text-muted">Consultar listas de riesgo y PEPs antes de vinculación.</small>
            </div>
        </div>

        <form id="pldForm">
            <div class="mb-4 p-3 bg-light rounded border">
                <label class="form-label fw-bold mb-2">Tipo de Persona a Consultar</label>
                <div class="d-flex gap-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tipo_persona" id="typeFisica" value="fisica" checked onchange="toggleType()">
                        <label class="form-check-label" for="typeFisica">Persona Física</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="tipo_persona" id="typeMoral" value="moral" onchange="toggleType()">
                        <label class="form-check-label" for="typeMoral">Persona Moral</label>
                    </div>
                </div>
            </div>

            <!-- Fisica Fields -->
            <div id="fieldsFisica" class="persona-specific" style="display: block;">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Nombre(s)*</label>
                        <input type="text" class="form-control" id="fisica_nombre" placeholder="Ej: Andres Manuel">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Apellido Paterno*</label>
                        <input type="text" class="form-control" id="fisica_paterno" placeholder="Ej: Lopez">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Apellido Materno</label>
                        <input type="text" class="form-control" id="fisica_materno" placeholder="Ej: Obrador">
                    </div>
                </div>
            </div>

            <!-- Moral Fields -->
            <div id="fieldsMoral" class="persona-specific">
                <div class="mb-3">
                    <label class="form-label">Razón Social*</label>
                    <input type="text" class="form-control" id="moral_razon" placeholder="Ej: Empresa Patito S.A. de C.V.">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2" id="btnCheck">
                <i class="fa-solid fa-magnifying-glass me-2"></i> Consultar Listas
            </button>
        </form>

        <!-- RESULTS AREA -->
        <div id="resultArea" class="result-box">
            <div class="d-flex align-items-center mb-3">
                <div id="resultIcon" class="me-3"></div>
                <h5 class="alert-heading fw-bold mb-0" id="resultTitle"></h5>
            </div>
            <hr>
            <div id="resultBody"></div>
            <div class="mt-3 text-end">
                <small class="text-muted fst-italic" id="searchIdDisplay"></small>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleType() {
        const isFisica = document.getElementById('typeFisica').checked;
        document.getElementById('fieldsFisica').style.display = isFisica ? 'block' : 'none';
        document.getElementById('fieldsMoral').style.display = isFisica ? 'none' : 'block';
        
        // Clear previous results
        document.getElementById('resultArea').style.display = 'none';
    }

    document.getElementById('pldForm').addEventListener('submit', async function(e) {
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
            if (!data.nombre || !data.paterno) { alert("Nombre y Apellido Paterno son requeridos."); return; }
        } else {
            data.nombre = document.getElementById('moral_razon').value.trim();
            data.paterno = '';
            data.materno = '';
            data.tipo_persona = 'moral';
            if (!data.nombre) { alert("La Razón Social es requerida."); return; }
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
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-dark" style="font-size:1.1rem;">${name}</div>
                                    <div class="small text-muted mb-1">${entity} ${role ? ' - ' + role : ''}</div>
                                    <div class="small text-secondary"><i class="fa-regular fa-calendar me-1"></i> ${date}</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge ${badgeClass} fs-6">${score}% Coincidencia</span>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="hit-badge"><i class="fa-solid fa-list me-1"></i> ${list}</span>
                                <span class="small text-danger fw-bold">${status}</span>
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
</script>

<?php include 'templates/footer.php'; ?>