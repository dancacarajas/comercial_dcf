/**
 * JS opcional — envia formulário de captadores via proxy WordPress (sem token no browser).
 * Copiar para: wp-content/novamira-sandbox/dcx-collector-applications.js
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('form.dcx-collector-form, form[data-dcx-collector-form]');
        if (!form) return;

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var fd = new FormData(form);
            var payload = {};
            fd.forEach(function (value, key) {
                if (key === 'website_url') return;
                payload[key] = value;
            });

            fetch('/wp-json/dcx-crm/v1/collector-application', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var box = form.querySelector('.dcx-collector-feedback');
                    if (!box) {
                        box = document.createElement('div');
                        box.className = 'dcx-collector-feedback';
                        form.appendChild(box);
                    }
                    box.textContent = data.message || (data.success ? 'Enviado!' : 'Erro ao enviar.');
                    box.className = 'dcx-collector-feedback ' + (data.success ? 'is-success' : 'is-error');
                    if (data.success) form.reset();
                })
                .catch(function () {
                    alert('Não foi possível enviar sua manifestação. Tente novamente.');
                });
        });
    });
})();
