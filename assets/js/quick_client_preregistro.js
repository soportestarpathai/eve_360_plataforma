(function () {
    'use strict';

    function isOperacionFraccionPage() {
        var p = (window.location.pathname || '').toLowerCase();
        return /\/operacion_[a-z0-9_]+\.php$/.test(p);
    }

    function escHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function getSwal() {
        return (typeof window.Swal !== 'undefined') ? window.Swal : null;
    }

    function showError(message) {
        var Swal = getSwal();
        if (Swal) {
            Swal.fire('Error', message, 'error');
        } else {
            alert(message);
        }
    }

    function ensureModal() {
        if (document.getElementById('quickClientModal')) return;

        var html = [
            '<div class="modal fade" id="quickClientModal" tabindex="-1" aria-hidden="true">',
            '  <div class="modal-dialog modal-dialog-centered">',
            '    <div class="modal-content">',
            '      <div class="modal-header">',
            '        <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>Alta rápida de cliente</h5>',
            '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>',
            '      </div>',
            '      <div class="modal-body">',
            '        <p class="text-muted small mb-3">Se creará como <strong>pre-registro incompleto</strong> para terminar KYC después.</p>',
            '        <div class="mb-3">',
            '          <label class="form-label">Tipo de persona *</label>',
            '          <select class="form-select" id="quick_tipo_persona">',
            '            <option value="fisica" selected>Persona física</option>',
            '            <option value="moral">Persona moral</option>',
            '            <option value="fideicomiso">Fideicomiso</option>',
            '          </select>',
            '        </div>',
            '        <div class="mb-3 quick-field quick-field-fisica">',
            '          <label class="form-label">Nombre completo *</label>',
            '          <input type="text" class="form-control text-uppercase" id="quick_nombre_completo" placeholder="JUAN CARLOS PEREZ GOMEZ" maxlength="200" autocomplete="off">',
            '        </div>',
            '        <div class="mb-3 quick-field quick-field-fisica">',
            '          <label class="form-label">CURP *</label>',
            '          <input type="text" class="form-control text-uppercase" id="quick_curp" placeholder="PEPJ900101HDFRRS01" maxlength="18" autocomplete="off">',
            '        </div>',
            '        <div class="mb-3 quick-field quick-field-moral d-none">',
            '          <label class="form-label">Razón social *</label>',
            '          <input type="text" class="form-control text-uppercase" id="quick_razon_social" placeholder="COMERCIALIZADORA DEL CENTRO SA DE CV" maxlength="254" autocomplete="off">',
            '        </div>',
            '        <div class="mb-3 quick-field quick-field-moral d-none">',
            '          <label class="form-label">RFC / Tax ID *</label>',
            '          <input type="text" class="form-control text-uppercase" id="quick_rfc_tax_id_moral" placeholder="CDC120101AB1" maxlength="20" autocomplete="off">',
            '        </div>',
            '        <div class="mb-3 quick-field quick-field-fideicomiso d-none">',
            '          <label class="form-label">Número / identificador de fideicomiso *</label>',
            '          <input type="text" class="form-control text-uppercase" id="quick_numero_fideicomiso" placeholder="FID-2026-00123" maxlength="120" autocomplete="off">',
            '        </div>',
            '        <div class="mb-3 quick-field quick-field-fideicomiso d-none">',
            '          <label class="form-label">Institución fiduciaria</label>',
            '          <input type="text" class="form-control text-uppercase" id="quick_institucion_fiduciaria" placeholder="BANCO FIDUCIARIO MEXICANO SA" maxlength="180" autocomplete="off">',
            '        </div>',
            '        <div class="mb-3 quick-field quick-field-fideicomiso d-none">',
            '          <label class="form-label">RFC / Tax ID (opcional)</label>',
            '          <input type="text" class="form-control text-uppercase" id="quick_rfc_tax_id_fidei" placeholder="FID120101AB1" maxlength="20" autocomplete="off">',
            '        </div>',
            '        <div class="mb-1">',
            '          <label class="form-label" id="quick_folio_label">Folio de la INE *</label>',
            '          <input type="text" class="form-control text-uppercase" id="quick_folio_ine" placeholder="IDMEX1234567890" maxlength="40" autocomplete="off">',
            '        </div>',
            '      </div>',
            '      <div class="modal-footer">',
            '        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>',
            '        <button type="button" class="btn btn-primary" id="quick_save_client_btn">',
            '          <i class="fa-solid fa-floppy-disk me-1"></i> Crear pre-registro',
            '        </button>',
            '      </div>',
            '    </div>',
            '  </div>',
            '</div>'
        ].join('');

        document.body.insertAdjacentHTML('beforeend', html);
    }

    function normalizeUpper(value) {
        return (value || '').trim().replace(/\s+/g, ' ').toUpperCase();
    }

    function getQuickFormPayload() {
        var tipo = normalizeUpper(document.getElementById('quick_tipo_persona').value || '').toLowerCase();
        return {
            tipo_persona: tipo || 'fisica',
            nombre_completo: normalizeUpper(document.getElementById('quick_nombre_completo').value),
            curp: normalizeUpper(document.getElementById('quick_curp').value),
            razon_social: normalizeUpper(document.getElementById('quick_razon_social').value),
            rfc_tax_id_moral: normalizeUpper(document.getElementById('quick_rfc_tax_id_moral').value),
            numero_fideicomiso: normalizeUpper(document.getElementById('quick_numero_fideicomiso').value),
            institucion_fiduciaria: normalizeUpper(document.getElementById('quick_institucion_fiduciaria').value),
            rfc_tax_id_fidei: normalizeUpper(document.getElementById('quick_rfc_tax_id_fidei').value),
            folio_ine: normalizeUpper(document.getElementById('quick_folio_ine').value)
        };
    }

    function toggleQuickFieldsByTipo(tipoPersona) {
        var tipo = (tipoPersona || 'fisica').toLowerCase();
        var showFisica = (tipo === 'fisica');
        var showMoral = (tipo === 'moral');
        var showFidei = (tipo === 'fideicomiso');

        document.querySelectorAll('.quick-field-fisica').forEach(function (el) {
            el.classList.toggle('d-none', !showFisica);
        });
        document.querySelectorAll('.quick-field-moral').forEach(function (el) {
            el.classList.toggle('d-none', !showMoral);
        });
        document.querySelectorAll('.quick-field-fideicomiso').forEach(function (el) {
            el.classList.toggle('d-none', !showFidei);
        });

        var labelFolio = document.getElementById('quick_folio_label');
        if (!labelFolio) return;
        if (showFisica) {
            labelFolio.textContent = 'Folio de la INE *';
        } else if (showMoral) {
            labelFolio.textContent = 'Folio de identificación del representante *';
        } else {
            labelFolio.textContent = 'Folio de identificación del delegado *';
        }
    }

    function validateQuickForm(payload) {
        var curpRegex = /^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z][0-9]$/;
        var folioRegex = /^[A-Z0-9-]{6,40}$/;
        var rfcTaxRegex = /^[A-Z0-9Ñ&-]{9,20}$/;

        if (!folioRegex.test(payload.folio_ine)) {
            return 'Folio de INE inválido.';
        }

        if (payload.tipo_persona === 'fisica') {
            if (!payload.nombre_completo || payload.nombre_completo.length < 5) {
                return 'Capture nombre completo válido.';
            }
            if (!curpRegex.test(payload.curp)) {
                return 'CURP inválida.';
            }
        } else if (payload.tipo_persona === 'moral') {
            if (!payload.razon_social || payload.razon_social.length < 3) {
                return 'Capture razón social válida.';
            }
            if (!rfcTaxRegex.test(payload.rfc_tax_id_moral)) {
                return 'RFC / Tax ID inválido.';
            }
        } else if (payload.tipo_persona === 'fideicomiso') {
            if (!payload.numero_fideicomiso || payload.numero_fideicomiso.length < 3) {
                return 'Capture número de fideicomiso válido.';
            }
            if (payload.rfc_tax_id_fidei && !rfcTaxRegex.test(payload.rfc_tax_id_fidei)) {
                return 'RFC / Tax ID inválido.';
            }
        } else {
            return 'Tipo de persona inválido.';
        }

        return '';
    }

    function resetQuickForm() {
        var tipo = document.getElementById('quick_tipo_persona');
        if (tipo) tipo.value = 'fisica';
        var ids = [
            'quick_nombre_completo',
            'quick_curp',
            'quick_razon_social',
            'quick_rfc_tax_id_moral',
            'quick_numero_fideicomiso',
            'quick_institucion_fiduciaria',
            'quick_rfc_tax_id_fidei',
            'quick_folio_ine'
        ];
        ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        toggleQuickFieldsByTipo('fisica');
    }

    function appendClientOption(select, cliente) {
        if (!select || !cliente) return;
        var value = String(cliente.id_cliente || '');
        if (!value) return;

        var labelName = cliente.nombre_cliente || 'Cliente';
        var identificador = cliente.identificador || cliente.curp || cliente.rfc || 'SIN-DATO';
        var label = value + ' - ' + labelName + ' (' + identificador + ')';

        var existing = Array.prototype.find.call(select.options, function (opt) {
            return String(opt.value) === value;
        });

        if (existing) {
            existing.textContent = label;
            select.value = value;
        } else {
            var opt = new Option(label, value, true, true);
            select.add(opt);
            select.value = value;
        }

        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function bindQuickSave(select) {
        var saveBtn = document.getElementById('quick_save_client_btn');
        if (!saveBtn || saveBtn.dataset.boundQuickClient === '1') return;
        saveBtn.dataset.boundQuickClient = '1';

        saveBtn.addEventListener('click', function () {
            var form = getQuickFormPayload();
            var err = validateQuickForm(form);
            if (err) {
                showError(err);
                return;
            }

            saveBtn.disabled = true;
            var oldHtml = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Guardando...';

            fetch('api/create_client_quick_preregistro.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tipo_persona: form.tipo_persona,
                    nombre_completo: form.nombre_completo,
                    curp: form.curp,
                    razon_social: form.razon_social,
                    rfc_tax_id: form.tipo_persona === 'moral' ? form.rfc_tax_id_moral : form.rfc_tax_id_fidei,
                    numero_fideicomiso: form.numero_fideicomiso,
                    institucion_fiduciaria: form.institucion_fiduciaria,
                    folio_ine: form.folio_ine
                })
            })
                .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
                .then(function (result) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = oldHtml;

                    if (!result.ok || !result.json || result.json.status !== 'success') {
                        var msg = (result.json && result.json.message) ? result.json.message : 'No fue posible crear el pre-registro.';
                        throw new Error(msg);
                    }

                    var data = result.json;
                    var cliente = data.cliente || {};
                    appendClientOption(select, cliente);

                    var modalEl = document.getElementById('quickClientModal');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();

                    resetQuickForm();

                    if (typeof window.loadNotifs === 'function') {
                        window.loadNotifs();
                    }

                    var faltantes = Array.isArray(data.faltantes) ? data.faltantes : [];
                    var listHtml = faltantes.length
                        ? '<ul class="text-start mb-0">' + faltantes.slice(0, 5).map(function (f) { return '<li>' + escHtml(f) + '</li>'; }).join('') + '</ul>'
                        : '<span class="text-muted">Completar expediente KYC.</span>';

                    var Swal = getSwal();
                    if (Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Pre-registro creado',
                            html: '<p class="mb-2"><strong>Contrato:</strong> ' + escHtml(cliente.no_contrato || '-') + '</p>'
                                + '<p class="mb-1"><strong>Pendientes por completar:</strong></p>' + listHtml
                        });
                    }
                })
                .catch(function (error) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = oldHtml;
                    showError(error.message || 'Error de conexión');
                });
        });
    }

    function injectQuickButton(select) {
        if (!select || select.dataset.quickClientReady === '1') return;
        select.dataset.quickClientReady = '1';

        var host = select.parentElement;
        if (!host) return;

        var row = document.createElement('div');
        row.className = 'd-flex align-items-center justify-content-between gap-2 mt-2';
        row.innerHTML = ''
            + '<small class="text-muted">¿No existe el cliente? Puedes crear un pre-registro mínimo.</small>'
            + '<button type="button" class="btn btn-outline-primary btn-sm" id="btn_open_quick_client">'
            + '<i class="fa-solid fa-user-plus me-1"></i> Nuevo cliente rápido'
            + '</button>';
        host.appendChild(row);

        var openBtn = row.querySelector('#btn_open_quick_client');
        if (!openBtn) return;

        openBtn.addEventListener('click', function () {
            ensureModal();
            var tipoSel = document.getElementById('quick_tipo_persona');
            if (tipoSel && !tipoSel.dataset.boundQuickTipo) {
                tipoSel.dataset.boundQuickTipo = '1';
                tipoSel.addEventListener('change', function () {
                    toggleQuickFieldsByTipo(tipoSel.value);
                });
            }
            resetQuickForm();
            bindQuickSave(select);
            var modalEl = document.getElementById('quickClientModal');
            if (!modalEl) return;
            var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!isOperacionFraccionPage()) return;
        var select = document.getElementById('id_cliente');
        if (!select || select.tagName !== 'SELECT') return;
        injectQuickButton(select);
    });
})();
