<?php
session_start();
require_once 'config/db.php';
require_once 'config/modules_helper.php';
require_once 'config/pld_middleware.php';
require_once 'config/pld_permisos.php';
require_once 'config/pld_fraccion_iv.php';
require_once 'config/mpc_catalogos.php';

requireModuleActive($pdo, 'pld');

if (!checkHabilitadoPLD($pdo)) {
    header('Location: index.php?error=pld_no_habilitado');
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if (!function_exists('userCanAccessMPC') || !userCanAccessMPC($pdo, $userId)) {
    header('Location: operaciones_pld.php?error=sin_permiso_mpc');
    exit;
}

$clave_sujeto_obligado = '';
try {
    $row = null;
    if ($userId > 0) {
        $stmtU = $pdo->prepare("SELECT folio_patron_pld FROM config_empresa_usuario WHERE id_usuario = ?");
        $stmtU->execute([$userId]);
        $row = $stmtU->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    if (empty($row['folio_patron_pld'])) {
        $row = $pdo->query("SELECT folio_patron_pld FROM config_empresa WHERE id_config = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $clave_sujeto_obligado = (string)($row['folio_patron_pld'] ?? '');
} catch (Exception $e) { /* no-op */ }

$idFraccionIV = getIdVulnerableFraccionIV($pdo);
$umbralAvisoUma = pldFraccionIVUmbralAviso();
$prioridadOptions = function_exists('mpcCatalogoOptions') ? mpcCatalogoOptions('prioridad', '1', null, false) : '';
$tipoAlertaOptions = function_exists('mpcCatalogoOptions') ? mpcCatalogoOptions('tipo_alerta', '100', null, false) : '';
$tipoOperacionOptions = function_exists('mpcCatalogoOptions') ? mpcCatalogoOptions('tipo_operacion', '', null, true) : '';
$tipoGarantiaOptions = function_exists('mpcCatalogoOptions') ? mpcCatalogoOptions('tipo_garantia', '', null, true) : '';
$tipoInmuebleOptions = function_exists('mpcCatalogoOptions') ? mpcCatalogoOptions('tipo_inmueble', '', null, true) : '';
$instrumentoMonetarioOptions = function_exists('mpcCatalogoOptions') ? mpcCatalogoOptions('instrumento_monetario', '', null, true) : '';
$monedaOptions = function_exists('mpcCatalogoOptions') ? mpcCatalogoOptions('moneda', '', null, true) : '';

$page_title = 'Aviso MPC - Fracción IV';
include 'templates/header.php';
?>
<title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($appConfig['nombre_empresa']) ?></title>
<link rel="stylesheet" href="assets/css/operaciones_pld.css">
</head>
<body>
<?php
$is_sub_page = true;
include 'templates/top_bar.php';
?>
<div class="content-wrapper">
    <div class="container-fluid" style="max-width: 1080px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="fw-bold text-primary mb-0"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Aviso MPC</h2>
                <p class="text-muted mb-0">Fracción IV - Mutuo, garantía, préstamos o créditos</p>
            </div>
            <a href="operaciones_pld.php" class="btn btn-outline-primary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        </div>

        <?php if (empty($idFraccionIV)): ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            No se encontró la Fracción IV en `cat_vulnerables`. Pida a administración cargar el catálogo base.
        </div>
        <?php endif; ?>

        <div class="alert alert-info mb-4">
            <i class="fa-solid fa-circle-info me-1"></i>
            <strong>Regla MPC:</strong> Identificación <strong>siempre</strong>. Aviso/acumulación a partir de
            <strong><?= number_format($umbralAvisoUma, 0) ?> UMA</strong>.
        </div>

        <form id="form-mpc" class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pt-3">
                <h5 class="mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Captura de operación MPC</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente *</label>
                        <select id="id_cliente" class="form-select" required>
                            <option value="">-- Seleccione cliente --</option>
                        </select>
                        <small id="kyc_info" class="text-muted"></small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mes reportado *</label>
                        <input id="mes_reportado" class="form-control" maxlength="6" pattern="\d{6}" value="<?= date('Ym') ?>" placeholder="Ej: 202604" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Clave actividad</label>
                        <input class="form-control" value="MPC" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Clave sujeto obligado *</label>
                        <input id="clave_sujeto_obligado" class="form-control text-uppercase" maxlength="13" value="<?= htmlspecialchars($clave_sujeto_obligado) ?>" placeholder="Ej: ABC010203AB1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Referencia aviso *</label>
                        <input id="referencia_aviso" class="form-control text-uppercase" maxlength="14" placeholder="Ej: REF20260001" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Exento</label>
                        <select id="exento" class="form-select">
                            <option value="0" selected>0 - No</option>
                            <option value="1">1 - Sí</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Clave entidad colegiada</label>
                        <input id="clave_entidad_colegiada" class="form-control text-uppercase" maxlength="12" placeholder="Ej: ABC900101A1B">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha operación *</label>
                        <input id="fecha_operacion" type="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Monto operación *</label>
                        <input id="monto" type="number" min="0.01" step="0.01" class="form-control" placeholder="Ej: 150000.00" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Código postal operación *</label>
                        <input id="codigo_postal_operacion" class="form-control" maxlength="5" inputmode="numeric" placeholder="Ej: 01020" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Instrumento monetario *</label>
                        <select id="instrumento_monetario_mpc" class="form-select" required>
                            <?= $instrumentoMonetarioOptions ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Moneda *</label>
                        <select id="moneda_mpc" class="form-select" required>
                            <?= $monedaOptions ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Prioridad *</label>
                        <select id="prioridad" class="form-select" required>
                            <?= $prioridadOptions ?>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Tipo de alerta *</label>
                        <select id="tipo_alerta" class="form-select" required>
                            <?= $tipoAlertaOptions ?>
                        </select>
                        <small class="text-muted">Regla: si prioridad = 2, tipo_alerta no puede ser 100.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tipo operación *</label>
                        <select id="tipo_operacion_mpc" class="form-select" required>
                            <?= $tipoOperacionOptions ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-none" id="wrap_tipo_garantia_mpc">
                        <label class="form-label">Tipo de garantía *</label>
                        <select id="tipo_garantia_mpc" class="form-select">
                            <?= $tipoGarantiaOptions ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-none" id="wrap_tipo_inmueble_mpc">
                        <label class="form-label">Tipo de inmueble *</label>
                        <select id="tipo_inmueble_mpc" class="form-select">
                            <?= $tipoInmuebleOptions ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-none" id="wrap_cp_garantia_mpc">
                        <label class="form-label">CP garantía inmueble *</label>
                        <input id="codigo_postal_garantia" class="form-control" maxlength="5" inputmode="numeric" placeholder="Ej: 01020">
                    </div>
                    <div class="col-md-3 d-none" id="wrap_folio_garantia_mpc">
                        <label class="form-label">Folio real garantía *</label>
                        <input id="folio_real_garantia" class="form-control text-uppercase" maxlength="200" placeholder="Ej: FOLIO-123456">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Observaciones</label>
                        <input id="observaciones" class="form-control text-uppercase" maxlength="250" placeholder="Ej: CREDITO SIMPLE A 12 MESES CON GARANTIA PRENDARIA">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Descripción alerta</label>
                        <input id="descripcion_alerta" class="form-control text-uppercase" maxlength="3000" placeholder="Ej: OPERACIÓN INUSUAL POR MONTO Y FRECUENCIA">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-outline-secondary">Limpiar</button>
                <button type="submit" class="btn btn-primary" <?= empty($idFraccionIV) ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-floppy-disk me-1"></i>Registrar operación
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const MPC_ID_FRACCION = <?= json_encode($idFraccionIV) ?>;
const MPC_UMBRAL_AVISO_UMA = <?= json_encode($umbralAvisoUma) ?>;
const MPC_CLIENT_CTX = { kyc: null, detail: null };

function normalizeUpper(v) {
  return (v || '').toString().trim().toUpperCase();
}

function onlyDigits(v) {
  return (v || '').toString().replace(/\D+/g, '');
}

function dateTo8(v) {
  return (v || '').toString().replace(/-/g, '');
}

function toMoney2(v) {
  const n = Number(v || 0);
  return n.toFixed(2);
}

function fallbackName(v, defVal = 'NO PROPORCIONADO') {
  const x = normalizeUpper(v);
  return x || defVal;
}

function firstAddress() {
  const dirs = MPC_CLIENT_CTX.detail?.direcciones;
  if (!Array.isArray(dirs) || dirs.length === 0) return null;
  return dirs[0] || null;
}

function firstCpFromAddress() {
  const d = firstAddress();
  if (!d) return '';
  const cp = onlyDigits(d.codigo_postal ?? d.cp ?? '').slice(0, 5);
  return cp.length === 5 ? cp : '';
}

function firstRepFromClient() {
  const aps = MPC_CLIENT_CTX.detail?.apoderados;
  if (!Array.isArray(aps) || aps.length === 0) return null;
  const pd = aps[0]?.persona_data || {};
  return {
    nombre: fallbackName(pd.nombre),
    apellido_paterno: fallbackName(pd.apellido_paterno),
    apellido_materno: fallbackName(pd.apellido_materno),
    fecha_nacimiento: dateTo8(pd.fecha_nacimiento || ''),
    rfc: normalizeUpper(pd.tax_id || pd.rfc || ''),
    curp: normalizeUpper(pd.CURP || pd.curp || '')
  };
}

function buildDomicilioFromClient() {
  const d = firstAddress();
  if (!d) return null;
  const cp = onlyDigits(d.codigo_postal ?? d.cp ?? '').slice(0, 5);
  if (cp.length !== 5) return null;
  return {
    nacional: {
      colonia: fallbackName(d.colonia, 'SIN COLONIA'),
      calle: fallbackName(d.calle, 'SIN CALLE'),
      numero_exterior: fallbackName(d.numero_exterior ?? d.no_exterior ?? d.num_exterior ?? d.no_ext, 'SN'),
      numero_interior: normalizeUpper(d.numero_interior ?? d.no_interior ?? d.num_interior ?? d.no_int ?? ''),
      codigo_postal: cp
    }
  };
}

function buildPersonaAvisoFromClient() {
  const k = MPC_CLIENT_CTX.kyc || {};
  const detailPersona = MPC_CLIENT_CTX.detail?.persona || {};
  const pais = normalizeUpper(k.pais_nacionalidad || 'MX');
  const tipo = (k.es_fisica === 1) ? 'fisica' : ((k.es_moral === 1) ? 'moral' : 'fideicomiso');
  const domicilio = buildDomicilioFromClient();
  const persona = { tipo_persona: {} };

  if (tipo === 'fisica') {
    persona.tipo_persona.persona_fisica = {
      nombre: fallbackName(detailPersona.nombre ?? k.nombre, 'CLIENTE'),
      apellido_paterno: fallbackName(detailPersona.apellido_paterno ?? k.apellido_paterno, 'SIN APELLIDO'),
      apellido_materno: fallbackName(detailPersona.apellido_materno ?? k.apellido_materno, 'SIN APELLIDO'),
      fecha_nacimiento: dateTo8(detailPersona.fecha_nacimiento ?? k.fecha_nacimiento ?? ''),
      rfc: normalizeUpper(detailPersona.tax_id ?? k.rfc ?? ''),
      curp: normalizeUpper(detailPersona.CURP ?? k.curp ?? ''),
      pais_nacionalidad: pais,
      actividad_economica: '1000000'
    };
  } else if (tipo === 'moral') {
    const rep = firstRepFromClient() || {
      nombre: 'REPRESENTANTE',
      apellido_paterno: 'NO PROPORCIONADO',
      apellido_materno: 'NO PROPORCIONADO',
      fecha_nacimiento: '',
      rfc: '',
      curp: ''
    };
    persona.tipo_persona.persona_moral = {
      denominacion_razon: fallbackName(detailPersona.razon_social ?? k.denominacion_razon, 'RAZON SOCIAL NO DISPONIBLE'),
      fecha_constitucion: dateTo8(detailPersona.fecha_constitucion ?? k.fecha_constitucion ?? ''),
      rfc: normalizeUpper(detailPersona.tax_id ?? k.rfc ?? ''),
      pais_nacionalidad: pais,
      giro_mercantil: '1000000',
      representante_apoderado: rep
    };
  } else {
    const rep = firstRepFromClient() || {
      nombre: 'APODERADO',
      apellido_paterno: 'NO PROPORCIONADO',
      apellido_materno: 'NO PROPORCIONADO',
      fecha_nacimiento: '',
      rfc: '',
      curp: ''
    };
    persona.tipo_persona.fideicomiso = {
      denominacion_razon: fallbackName(detailPersona.denominacion ?? k.denominacion_razon, 'FIDEICOMISO NO DISPONIBLE'),
      rfc: normalizeUpper(detailPersona.tax_id ?? k.rfc ?? ''),
      identificador_fideicomiso: normalizeUpper(detailPersona.identificador_fideicomiso ?? ''),
      apoderado_delegado: rep
    };
  }

  if (domicilio) persona.tipo_domicilio = domicilio;
  return persona;
}

function syncTipoInmuebleMpc() {
  const tipoOp = document.getElementById('tipo_operacion_mpc').value;
  const tipoGarantia = document.getElementById('tipo_garantia_mpc').value;
  const wrap = document.getElementById('wrap_tipo_inmueble_mpc');
  const wrapCp = document.getElementById('wrap_cp_garantia_mpc');
  const wrapFolio = document.getElementById('wrap_folio_garantia_mpc');
  const sel = document.getElementById('tipo_inmueble_mpc');
  const cp = document.getElementById('codigo_postal_garantia');
  const folio = document.getElementById('folio_real_garantia');
  const requiere = (tipoOp === '402' && tipoGarantia === '2');
  wrap.classList.toggle('d-none', !requiere);
  wrapCp.classList.toggle('d-none', !requiere);
  wrapFolio.classList.toggle('d-none', !requiere);
  sel.required = requiere;
  cp.required = requiere;
  folio.required = requiere;
  if (!requiere) sel.value = '';
  if (!requiere) cp.value = '';
  if (!requiere) folio.value = '';
}

function syncTipoGarantiaMpc() {
  const tipoOp = document.getElementById('tipo_operacion_mpc').value;
  const wrap = document.getElementById('wrap_tipo_garantia_mpc');
  const sel = document.getElementById('tipo_garantia_mpc');
  const requiere = tipoOp === '402';
  wrap.classList.toggle('d-none', !requiere);
  sel.required = requiere;
  if (!requiere) sel.value = '';
  syncTipoInmuebleMpc();
}

async function cargarClientes() {
  const sel = document.getElementById('id_cliente');
  try {
    const r = await fetch('api/get_clients.php');
    const data = await r.json();
    const list = Array.isArray(data) ? data : [];
    for (const c of list) {
      const op = document.createElement('option');
      op.value = c.id_cliente;
      op.textContent = `${c.id_cliente} - ${c.nombre_cliente || 'Sin nombre'} (${c.rfc || 'N/A'})`;
      sel.appendChild(op);
    }
  } catch (e) {
    console.error(e);
  }
}

async function loadClientContext() {
  const info = document.getElementById('kyc_info');
  const id = document.getElementById('id_cliente').value;
  MPC_CLIENT_CTX.kyc = null;
  MPC_CLIENT_CTX.detail = null;
  if (!id) {
    info.textContent = '';
    return;
  }
  try {
    const [rKyc, rDetail] = await Promise.all([
      fetch('api/get_cliente_kyc_pld.php?id=' + encodeURIComponent(id)),
      fetch('api/get_client_details.php?id=' + encodeURIComponent(id))
    ]);
    const dKyc = await rKyc.json();
    const dDetail = await rDetail.json();
    if (dKyc?.status === 'success') MPC_CLIENT_CTX.kyc = dKyc.kyc || null;
    if (dDetail?.status === 'success') MPC_CLIENT_CTX.detail = dDetail.data || null;

    const k = MPC_CLIENT_CTX.kyc || {};
    const nombre = [k.nombre, k.apellido_paterno, k.apellido_materno].filter(Boolean).join(' ').trim()
      || k.denominacion_razon || k.alias || 'Sin nombre';
    info.innerHTML = `<strong>KYC:</strong> ${normalizeUpper(k.tipo_persona || '')} | ${normalizeUpper(nombre)} | RFC: ${normalizeUpper(k.rfc || '-')}`;

    const cpAuto = firstCpFromAddress();
    const cpOp = document.getElementById('codigo_postal_operacion');
    if (!cpOp.value && cpAuto) cpOp.value = cpAuto;
  } catch (e) {
    info.textContent = '';
  }
}

document.getElementById('form-mpc').addEventListener('submit', async (ev) => {
  ev.preventDefault();
  if (!MPC_ID_FRACCION) {
    Swal.fire('Catálogo incompleto', 'No se encontró id de Fracción IV en cat_vulnerables.', 'warning');
    return;
  }

  const idCliente = Number(document.getElementById('id_cliente').value);
  const montoNum = Number(document.getElementById('monto').value);
  const fechaOperacion = document.getElementById('fecha_operacion').value;
  const mesReportado = normalizeUpper(document.getElementById('mes_reportado').value);
  const claveSO = normalizeUpper(document.getElementById('clave_sujeto_obligado').value);
  const tipoOp = document.getElementById('tipo_operacion_mpc').value;
  const instrumento = document.getElementById('instrumento_monetario_mpc').value;
  const moneda = document.getElementById('moneda_mpc').value;
  const prioridad = document.getElementById('prioridad').value;
  const tipoAlerta = document.getElementById('tipo_alerta').value;
  const referencia = normalizeUpper(document.getElementById('referencia_aviso').value);
  const cpOperacion = onlyDigits(document.getElementById('codigo_postal_operacion').value).slice(0, 5);

  if (!idCliente || !montoNum || !fechaOperacion || !mesReportado || !claveSO || !tipoOp || !instrumento || !moneda || !prioridad || !tipoAlerta || !referencia || cpOperacion.length !== 5) {
    Swal.fire('Datos incompletos', 'Complete todos los campos obligatorios.', 'warning');
    return;
  }

  if (!['401', '402'].includes(tipoOp)) {
    Swal.fire('Validación', 'Tipo de operación MPC inválido. Debe ser 401 o 402.', 'warning');
    return;
  }

  if (!['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15'].includes(instrumento)) {
    Swal.fire('Validación', 'Instrumento monetario MPC inválido.', 'warning');
    return;
  }

  const monedaNum = Number(moneda);
  const esMetalAmonedado = ['13', '14'].includes(instrumento);
  if (esMetalAmonedado && (monedaNum < 159 || monedaNum > 179)) {
    Swal.fire('Validación', 'Para instrumento 13/14 la moneda debe estar entre 159 y 179.', 'warning');
    return;
  }
  if (!esMetalAmonedado && monedaNum >= 159 && monedaNum <= 179) {
    Swal.fire('Validación', 'Para instrumentos distintos de 13/14 no se permite moneda entre 159 y 179.', 'warning');
    return;
  }

  const tipoGarantia = document.getElementById('tipo_garantia_mpc').value;
  const tipoInmueble = document.getElementById('tipo_inmueble_mpc').value;
  const cpGarantia = onlyDigits(document.getElementById('codigo_postal_garantia').value).slice(0, 5);
  const folioGarantia = normalizeUpper(document.getElementById('folio_real_garantia').value);

  if (tipoOp === '402' && !tipoGarantia) {
    Swal.fire('Validación', 'Para operación 402 debe seleccionar el tipo de garantía.', 'warning');
    return;
  }

  if (tipoOp === '402' && tipoGarantia === '2' && !tipoInmueble) {
    Swal.fire('Validación', 'Para garantía tipo Inmueble debe seleccionar el tipo de inmueble.', 'warning');
    return;
  }

  if (tipoOp === '402' && tipoGarantia === '2' && cpGarantia.length !== 5) {
    Swal.fire('Validación', 'Para garantía inmueble capture código postal de 5 dígitos.', 'warning');
    return;
  }

  if (tipoOp === '402' && tipoGarantia === '2' && !folioGarantia) {
    Swal.fire('Validación', 'Para garantía inmueble capture el folio real.', 'warning');
    return;
  }

  if (prioridad === '2' && tipoAlerta === '100') {
    Swal.fire('Validación', 'Si la prioridad es 2 (24 horas), el tipo de alerta no puede ser 100 (Sin alerta).', 'warning');
    return;
  }

  if (!MPC_CLIENT_CTX.kyc) {
    await loadClientContext();
  }
  if (!MPC_CLIENT_CTX.kyc) {
    Swal.fire('Datos incompletos', 'No fue posible cargar el contexto KYC del cliente seleccionado.', 'warning');
    return;
  }

  const personaAviso = buildPersonaAvisoFromClient();
  const operacion = {
    fecha_operacion: dateTo8(fechaOperacion),
    codigo_postal: cpOperacion,
    tipo_operacion: tipoOp,
    datos_liquidacion: [{
      fecha_disposicion: dateTo8(fechaOperacion),
      instrumento_monetario: instrumento,
      moneda: moneda,
      monto_operacion: toMoney2(montoNum)
    }]
  };

  if (tipoOp === '402') {
    const garantia = { tipo_garantia: tipoGarantia };
    if (tipoGarantia === '2') {
      garantia.datos_bien_mutuo = {
        datos_inmueble: {
          tipo_inmueble: tipoInmueble,
          valor_referencia: toMoney2(montoNum),
          codigo_postal: cpGarantia,
          folio_real: folioGarantia
        }
      };
    }
    operacion.datos_garantia = [garantia];
  }

  const sujetoObligado = {
    clave_sujeto_obligado: claveSO,
    clave_actividad: 'MPC'
  };
  const claveEntidad = normalizeUpper(document.getElementById('clave_entidad_colegiada').value);
  if (claveEntidad) sujetoObligado.clave_entidad_colegiada = claveEntidad;
  if (document.getElementById('exento').value === '1') sujetoObligado.exento = '1';

  const payload = {
    id_cliente: idCliente,
    informe: [{
      mes_reportado: mesReportado,
      sujeto_obligado: sujetoObligado,
      aviso: [{
        referencia_aviso: referencia,
        prioridad: prioridad,
        alerta: {
          tipo_alerta: tipoAlerta,
          descripcion_alerta: normalizeUpper(document.getElementById('descripcion_alerta').value)
        },
        persona_aviso: [personaAviso],
        detalle_operaciones: [{ datos_operacion: [operacion] }]
      }]
    }]
  };

  try {
    const r = await fetch('api/registrar_aviso_mpc.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const j = await r.json();
    if (!r.ok || j.status !== 'success') {
      throw new Error(j.message || 'No fue posible registrar');
    }

    const avisoTxt = j.requiere_aviso ? `Aviso requerido (${j.tipo_aviso || 'UMBRAL'})` : 'Operación registrada sin aviso inmediato';
    await Swal.fire('Registro exitoso', `${j.message || 'Operación registrada'}\n${avisoTxt}`, 'success');
    window.location.href = 'operaciones_pld.php';
  } catch (e) {
    Swal.fire('No fue posible registrar', e.message || 'Error inesperado', 'error');
  }
});

document.getElementById('tipo_operacion_mpc').addEventListener('change', syncTipoGarantiaMpc);
document.getElementById('tipo_garantia_mpc').addEventListener('change', syncTipoInmuebleMpc);
document.getElementById('id_cliente').addEventListener('change', loadClientContext);
syncTipoGarantiaMpc();
cargarClientes();
</script>

<?php include 'templates/footer.php'; ?>
