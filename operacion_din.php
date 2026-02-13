<?php
session_start();
require_once 'config/db.php';
require_once 'config/pld_middleware.php';

if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

$id_fraccion = (int)($_GET['id_fraccion'] ?? 0);
$page_title = 'Operación DIN - Desarrollo Inmobiliario';
include 'templates/header.php';

$stmt = $pdo->query("SELECT folio_patron_pld FROM config_empresa WHERE id_config = 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);
$clave_sujeto_obligado = $config['folio_patron_pld'] ?? '';
?>
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/operaciones_pld.css">
</head>
<body>

<?php $is_sub_page = true; include 'templates/top_bar.php'; ?>

<div class="content-wrapper">
    <div class="page-header mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 class="fw-bold text-primary mb-1">
                    <i class="fa-solid fa-building me-2"></i>Formulario DIN - Desarrollo Inmobiliario
                </h2>
                <p class="text-muted mb-0">
                    Fracción V / V Bis — Portal de Prevención de Lavado de Dinero
                    <a href="https://www.sat.gob.mx/consulta/44891/portal-de-prevencion-de-lavado-de-dinero" target="_blank" rel="noopener" class="ms-2">
                        <i class="fa-solid fa-external-link-alt"></i> Descargar XSD
                    </a>
                </p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Volver a Operaciones PLD
            </a>
        </div>
    </div>

    <form id="formDIN">
        <!-- Cliente (KYC) -->
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fa-solid fa-user me-2"></i>Cliente (datos del KYC - solo lectura)</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cliente *</label>
                        <select class="form-select" id="id_cliente" required>
                            <option value="">-- Seleccione Cliente --</option>
                        </select>
                    </div>
                </div>
                <div id="kyc-preview" class="border rounded p-3 bg-light" style="display:none;">
                    <small class="text-muted d-block mb-2">Datos prellenados desde el expediente de identificación (no editables)</small>
                    <div class="row">
                        <div class="col-md-4"><strong>RFC:</strong> <span id="kyc-rfc">-</span></div>
                        <div class="col-md-4"><strong>CURP:</strong> <span id="kyc-curp">-</span></div>
                        <div class="col-md-4"><strong>Tipo:</strong> <span id="kyc-tipo">-</span></div>
                        <div class="col-md-6 mt-2"><strong>Nombre/Razón:</strong> <span id="kyc-nombre">-</span></div>
                        <div class="col-md-3 mt-2"><strong>Fecha Nac/Const:</strong> <span id="kyc-fecha">-</span></div>
                        <div class="col-md-3 mt-2"><strong>Nacionalidad:</strong> <span id="kyc-pais">-</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informe / Sujeto Obligado -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fa-solid fa-file-alt me-2"></i>Informe y Sujeto Obligado</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">mes_reportado (YYYYMM) *</label>
                        <input type="text" class="form-control" id="mes_reportado" pattern="\d{6}" maxlength="6" required
                               placeholder="Ej: 202602" value="<?= date('Ym') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">clave_sujeto_obligado (RFC empresa) *</label>
                        <input type="text" class="form-control" id="clave_sujeto_obligado" required
                               value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" placeholder="XXXXXXXXXXXXX">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">clave_actividad</label>
                        <input type="text" class="form-control" id="clave_actividad" value="DIN" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aviso -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fa-solid fa-bell me-2"></i>Aviso</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">referencia_aviso *</label>
                        <input type="text" class="form-control" id="referencia_aviso" maxlength="14" required placeholder="REF001">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">prioridad (1 o 2) *</label>
                        <select class="form-select" id="prioridad" required>
                            <option value="1">1</option>
                            <option value="2">2</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">tipo_alerta (3-4 dígitos) *</label>
                        <input type="text" class="form-control" id="tipo_alerta" pattern="\d{3,4}" maxlength="4" required value="100">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">tipo_operacion (3-4 dígitos) *</label>
                        <input type="text" class="form-control" id="tipo_operacion" pattern="\d{3,4}" maxlength="4" required value="1601" placeholder="1601">
                    </div>
                </div>
            </div>
        </div>

        <!-- Desarrollo Inmobiliario -->
        <div class="card mb-4 border-info">
            <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fa-solid fa-building me-2"></i>Desarrollo Inmobiliario</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">objeto_aviso_anterior (SI/NO) *</label>
                        <select class="form-select" id="objeto_aviso_anterior" required>
                            <option value="SI">SI</option>
                            <option value="NO" selected>NO</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">modificacion (SI/NO) *</label>
                        <select class="form-select" id="modificacion" required>
                            <option value="SI">SI</option>
                            <option value="NO" selected>NO</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">entidad_federativa (1-2 dígitos) *</label>
                        <input type="text" class="form-control" id="entidad_federativa" pattern="\d{1,2}" maxlength="2" required value="9">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">registro_licencia *</label>
                        <input type="text" class="form-control" id="registro_licencia" maxlength="200" required placeholder="REG-001">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">codigo_postal *</label>
                        <input type="text" class="form-control" id="codigo_postal" pattern="\d{5}" maxlength="5" required placeholder="02000">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">tipo_desarrollo (1-2 dígitos) *</label>
                        <input type="text" class="form-control" id="tipo_desarrollo" pattern="\d{1,2}" maxlength="2" value="5">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">colonia *</label>
                        <input type="text" class="form-control" id="colonia" maxlength="50" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">calle *</label>
                        <input type="text" class="form-control" id="calle" maxlength="100" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">descripcion_desarrollo</label>
                        <input type="text" class="form-control" id="descripcion_desarrollo" maxlength="3000">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">monto_desarrollo (MXN) *</label>
                        <input type="number" class="form-control" id="monto_desarrollo" step="0.01" min="0" required placeholder="0.00">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">unidades_comercializadas *</label>
                        <input type="number" class="form-control" id="unidades_comercializadas" step="0.01" min="0" value="1" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">costo_unidad *</label>
                        <input type="number" class="form-control" id="costo_unidad" step="0.01" min="0" required placeholder="0.00">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">otras_empresas (SI/NO) *</label>
                        <select class="form-select" id="otras_empresas" required>
                            <option value="SI">SI</option>
                            <option value="NO" selected>NO</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aportación (recursos_propios → aportacion_numerario) -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fa-solid fa-money-bill me-2"></i>Aportación</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">fecha_aportacion (YYYYMMDD) *</label>
                        <input type="date" class="form-control" id="fecha_aportacion" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">instrumento_monetario</label>
                        <input type="text" class="form-control" id="instrumento_monetario" value="1" maxlength="2">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">moneda</label>
                        <input type="text" class="form-control" id="moneda" value="1" maxlength="3">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">monto_aportacion (MXN) *</label>
                        <input type="number" class="form-control" id="monto_aportacion" step="0.01" min="0" required placeholder="0.00">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">aportacion_fideicomiso (SI/NO)</label>
                        <select class="form-select" id="aportacion_fideicomiso">
                            <option value="SI">SI</option>
                            <option value="NO" selected>NO</option>
                        </select>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">nombre_institucion</label>
                        <input type="text" class="form-control" id="nombre_institucion" maxlength="254">
                    </div>
                </div>
            </div>
        </div>

        <!-- Opcionales PLD -->
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning"><h5 class="mb-0"><i class="fa-solid fa-exclamation-triangle me-2"></i>Opcionales PLD</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Operación Sospechosa</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="es_sospechosa">
                            <label class="form-check-label" for="es_sospechosa">Aviso 24H</label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3" id="fecha_sospecha_div" style="display:none;">
                        <label class="form-label">Fecha conocimiento sospecha</label>
                        <input type="datetime-local" class="form-control" id="fecha_conocimiento_sospecha">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Match listas restringidas</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="match_listas">
                            <label class="form-check-label" for="match_listas">Aviso 24H</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Fracción (para umbrales PLD)</label>
                        <select class="form-select" id="id_fraccion">
                            <option value="">-- Seleccione --</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-save me-2"></i>Registrar Operación y Generar XML
            </button>
            <a href="operaciones_pld.php" class="btn btn-secondary btn-lg">Cancelar</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    cargarClientes();
    cargarFracciones();
    document.getElementById('id_cliente').addEventListener('change', cargarKYC);
    document.getElementById('es_sospechosa').addEventListener('change', function() {
        document.getElementById('fecha_sospecha_div').style.display = this.checked ? 'block' : 'none';
        if (this.checked && !document.getElementById('fecha_conocimiento_sospecha').value) {
            const n = new Date();
            n.setMinutes(n.getMinutes() - n.getTimezoneOffset());
            document.getElementById('fecha_conocimiento_sospecha').value = n.toISOString().slice(0, 16);
        }
    });
    document.getElementById('formDIN').addEventListener('submit', guardarOperacionDIN);
});

function cargarFracciones() {
    fetch('api/get_catalogos.php')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('id_fraccion');
            sel.innerHTML = '<option value="">-- Seleccione --</option>';
            const v = (data.data?.vulnerables || []).filter(f => (f.fraccion === 'V' || f.fraccion === 'V Bis'));
            v.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.id_vulnerable;
                opt.textContent = f.nombre + ' (' + f.fraccion + ')';
                sel.appendChild(opt);
            });
        })
        .catch(e => console.error('Error fracciones:', e));
}

function cargarClientes() {
    fetch('api/get_clients.php')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('id_cliente');
            sel.innerHTML = '<option value="">-- Seleccione Cliente --</option>';
            (Array.isArray(data) ? data : []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_cliente;
                opt.textContent = c.nombre_cliente || `Cliente #${c.id_cliente}`;
                sel.appendChild(opt);
            });
        })
        .catch(e => console.error('Error clientes:', e));
}

function cargarKYC() {
    const id = document.getElementById('id_cliente').value;
    const preview = document.getElementById('kyc-preview');
    if (!id) {
        preview.style.display = 'none';
        return;
    }
    fetch('api/get_cliente_kyc_pld.php?id=' + id)
        .then(r => r.json())
        .then(res => {
            if (res.status !== 'success') {
                preview.style.display = 'none';
                return;
            }
            const k = res.kyc;
            document.getElementById('kyc-rfc').textContent = k.rfc || '-';
            document.getElementById('kyc-curp').textContent = k.curp || '-';
            document.getElementById('kyc-tipo').textContent = k.tipo_persona || '-';
            document.getElementById('kyc-nombre').textContent = k.denominacion_razon || k.razon_social || '-';
            document.getElementById('kyc-fecha').textContent = k.fecha_nacimiento || k.fecha_constitucion || '-';
            document.getElementById('kyc-pais').textContent = k.pais_nacionalidad || '-';
            preview.style.display = 'block';
        })
        .catch(e => {
            console.error('Error KYC:', e);
            preview.style.display = 'none';
        });
}

function v(id) {
    const el = document.getElementById(id);
    return el ? String(el.value || '').trim() : '';
}

function guardarOperacionDIN(e) {
    e.preventDefault();
    const idCliente = v('id_cliente');
    if (!idCliente) {
        Swal.fire('Error', 'Seleccione un cliente', 'error');
        return;
    }

    const fechaAport = document.getElementById('fecha_aportacion').value;
    const fechaAportFmt = fechaAport ? fechaAport.replace(/-/g, '') : '';

    const payload = {
        id_cliente: parseInt(idCliente),
        id_fraccion: v('id_fraccion') ? parseInt(v('id_fraccion')) : null,
        es_sospechosa: document.getElementById('es_sospechosa').checked ? 1 : 0,
        fecha_conocimiento_sospecha: document.getElementById('es_sospechosa').checked ? v('fecha_conocimiento_sospecha') : null,
        match_listas_restringidas: document.getElementById('match_listas').checked ? 1 : 0,
        fecha_conocimiento_match: null,
        informe: [{
            mes_reportado: v('mes_reportado'),
            sujeto_obligado: {
                clave_sujeto_obligado: v('clave_sujeto_obligado'),
                clave_actividad: v('clave_actividad')
            },
            aviso: [{
                referencia_aviso: v('referencia_aviso'),
                prioridad: v('prioridad'),
                alerta: { tipo_alerta: v('tipo_alerta') },
                detalle_operaciones: [{
                    datos_operacion: [{
                        tipo_operacion: v('tipo_operacion'),
                        desarrollos_inmobiliarios: [{
                            datos_desarrollo: [{
                                objeto_aviso_anterior: v('objeto_aviso_anterior'),
                                modificacion: v('modificacion'),
                                entidad_federativa: v('entidad_federativa'),
                                registro_licencia: v('registro_licencia'),
                                caracteristicas_desarrollo: [{
                                    codigo_postal: v('codigo_postal'),
                                    colonia: v('colonia'),
                                    calle: v('calle'),
                                    tipo_desarrollo: v('tipo_desarrollo'),
                                    descripcion_desarrollo: v('descripcion_desarrollo') || undefined,
                                    monto_desarrollo: parseFloat(v('monto_desarrollo')) || 0,
                                    unidades_comercializadas: parseFloat(v('unidades_comercializadas')) || 1,
                                    costo_unidad: parseFloat(v('costo_unidad')) || 0,
                                    otras_empresas: v('otras_empresas')
                                }]
                            }]
                        }],
                        aportaciones: [{
                            fecha_aportacion: fechaAportFmt,
                            tipo_aportacion: [{
                                recursos_propios: [{
                                    datos_aportacion: [{
                                        aportacion_numerario: [{
                                            instrumento_monetario: v('instrumento_monetario') || '1',
                                            moneda: v('moneda') || '1',
                                            monto_aportacion: parseFloat(v('monto_aportacion')) || 0,
                                            aportacion_fideicomiso: v('aportacion_fideicomiso') || 'NO',
                                            nombre_institucion: v('nombre_institucion') || ''
                                        }]
                                    }]
                                }]
                            }]
                        }]
                    }]
                }]
            }]
        }]
    };

    fetch('api/registrar_operacion_din.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Operación registrada',
                html: (data.requiere_aviso ? '<p><strong>Requiere aviso.</strong> Deadline: ' + (data.fecha_deadline || '') + '</p>' : '<p>Operación registrada sin aviso.</p>') +
                      '<p>XML almacenado correctamente.</p>'
            }).then(() => {
                window.location.href = 'operaciones_pld.php';
            });
        } else {
            Swal.fire('Error', data.message || 'Error al registrar', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Error', 'Error de conexión', 'error');
    });
}
</script>

<?php include 'templates/footer.php'; ?>
