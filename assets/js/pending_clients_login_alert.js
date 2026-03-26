(function () {
    'use strict';

    function hasTopBar() {
        return !!document.querySelector('.top-banner');
    }

    function getSwal() {
        return (typeof window.Swal !== 'undefined') ? window.Swal : null;
    }

    function escHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function buildHtmlList(pendientes, total) {
        var items = (pendientes || []).slice(0, 6).map(function (p) {
            var faltantes = Array.isArray(p.faltantes) ? p.faltantes : [];
            var faltanTxt = faltantes.length
                ? faltantes.slice(0, 3).map(escHtml).join(', ')
                : 'Completar expediente KYC';
            var nombre = escHtml(p.nombre_cliente || 'Sin nombre');
            var contrato = escHtml(p.no_contrato || '-');
            return '<li class="mb-2"><strong>' + contrato + '</strong> - ' + nombre
                + '<br><small class="text-muted">Falta: ' + faltanTxt + '</small></li>';
        }).join('');

        var extra = '';
        if (total > (pendientes || []).length) {
            extra = '<p class="text-muted small mt-2 mb-0">Se muestran los primeros ' + (pendientes || []).length + ' de ' + total + ' pendientes.</p>';
        }

        return '<div class="text-start">'
            + '<p class="mb-2">Tienes <strong>' + total + '</strong> cliente(s) pendiente(s) por completar.</p>'
            + '<ul class="mb-0 ps-3">' + items + '</ul>'
            + extra
            + '</div>';
    }

    function showPendingAlert(data) {
        var total = Number(data.total || 0);
        if (!total) return;
        var pendientes = Array.isArray(data.pendientes) ? data.pendientes : [];
        var html = buildHtmlList(pendientes, total);
        var Swal = getSwal();

        if (!Swal) {
            alert('Tienes ' + total + ' cliente(s) pendiente(s) por completar expediente.');
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Clientes pendientes',
            html: html,
            confirmButtonText: 'Ir a Clientes',
            cancelButtonText: 'Cerrar',
            showCancelButton: true
        }).then(function (result) {
            if (result.isConfirmed) {
                window.location.href = 'clientes.php?estatus=preregistro';
            }
        });
    }

    function shouldRun() {
        if (!hasTopBar()) return false;
        if (!window.EVE_PENDING_ALERT_SESSION_KEY) return false;
        var key = 'eve_pending_alert_seen_' + window.EVE_PENDING_ALERT_SESSION_KEY;
        if (localStorage.getItem(key) === '1') return false;
        localStorage.setItem(key, '1');
        return true;
    }

    function runPendingAlert() {
        if (!shouldRun()) return;

        fetch('api/get_pending_clients_alert.php?limit=6', {
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json || json.status !== 'success') return;
                showPendingAlert(json);
            })
            .catch(function () {
                // silencioso: no bloquear navegación por falla de red temporal
            });
    }

    document.addEventListener('DOMContentLoaded', runPendingAlert);
})();
