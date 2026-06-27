/**
 * DCX CRM Collectors — proxy REST WordPress (sem token no navegador).
 */
(function () {
  'use strict';

  if (!window.DCX_CRM_COLLECTORS || !window.DCX_CRM_COLLECTORS.proxyUrl) {
    return;
  }

  var cfg = window.DCX_CRM_COLLECTORS;

  var FORM_SELECTORS = [
    'form[data-dcx-collector-form]',
    'form.dcx-collector-form',
    'form.form-box[action*="captadores-de-recursos"]',
    'form.form-box:has([name="experiencia_rouanet"])',
    'form.form-box:has([name="documento"])'
  ];

  function isCollectorForm(form) {
    if (!form || !form.querySelector) return false;
    if (form.querySelector('[name="experiencia_rouanet"]')) return true;
    if (form.querySelector('[name="documento"]')) return true;
    if (form.querySelector('[name="segmentos"]')) return true;
    if (form.querySelector('[name="carteira"]')) return true;
    if (window.location.pathname.indexOf('captadores-de-recursos') !== -1) {
      return form.matches('form.form-box');
    }
    return FORM_SELECTORS.some(function (sel) {
      try { return form.matches(sel); } catch (e) { return false; }
    });
  }

  function ensureHoneypot(form) {
    if (form.querySelector('[name="website_url"]')) return;
    var hp = document.createElement('input');
    hp.type = 'hidden';
    hp.name = 'website_url';
    hp.value = '';
    hp.tabIndex = -1;
    hp.autocomplete = 'off';
    hp.setAttribute('aria-hidden', 'true');
    hp.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;opacity:0;';
    form.appendChild(hp);
  }

  function formToPayload(form) {
    var fd = new FormData(form);
    var payload = {};
    fd.forEach(function (value, key) {
      if (Object.prototype.hasOwnProperty.call(payload, key)) {
        if (!Array.isArray(payload[key])) payload[key] = [payload[key]];
        payload[key].push(value);
      } else {
        payload[key] = value;
      }
    });

    payload.source_page = payload.source_page || cfg.sourcePage || 'patrocinio/captadores-de-recursos';
    payload.source_url = payload.source_url || cfg.sourceUrl || window.location.href.split('#')[0];

    var consentEl = form.querySelector('[name="autorizacao_contato"], [name="consent_contact"]');
    if (consentEl && (consentEl.checked || consentEl.value === '1')) {
      payload.consent_contact = '1';
    }

    return payload;
  }

  function sendToProxy(payload) {
    return fetch(cfg.proxyUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
      mode: 'cors'
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (body) {
        return { ok: res.ok, status: res.status, body: body };
      });
    });
  }

  function showSuccess(form, message) {
    var text = message || 'Manifestação recebida com sucesso.';
    var existing = form.parentElement && form.parentElement.querySelector('.dcx-crm-collector-success');
    if (existing) {
      existing.textContent = text;
      existing.hidden = false;
    } else {
      var box = document.createElement('div');
      box.className = 'dcx-crm-collector-success dcx-crm-success-msg';
      box.setAttribute('role', 'status');
      box.style.cssText = 'margin:16px 0;padding:16px 18px;border-radius:8px;background:#e8f7ef;border:1px solid #0e7a56;color:#0e7a56;font-weight:800;';
      box.textContent = text;
      form.insertAdjacentElement('beforebegin', box);
    }

    var anchor = document.getElementById('mensagem-enviada') || form;
    if (anchor.scrollIntoView) anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });

    if (window.history && window.history.replaceState) {
      var clean = window.location.pathname + window.location.search.replace(/[?&]$/, '');
      window.history.replaceState(null, '', clean + '#mensagem-enviada');
    }

    form.reset();
  }

  function showError(form, message) {
    var text = message || 'Não foi possível enviar sua manifestação agora. Tente novamente em alguns instantes.';
    var existing = form.parentElement && form.parentElement.querySelector('.dcx-crm-collector-error');
    if (existing) {
      existing.textContent = text;
      existing.hidden = false;
    } else {
      var box = document.createElement('div');
      box.className = 'dcx-crm-collector-error dcx-crm-error-msg';
      box.setAttribute('role', 'alert');
      box.style.cssText = 'margin:16px 0;padding:16px 18px;border-radius:8px;background:#fdecea;border:1px solid #c62828;color:#c62828;font-weight:800;';
      box.textContent = text;
      form.insertAdjacentElement('beforebegin', box);
    }
  }

  function bindForms() {
    document.querySelectorAll('form.form-box, form[data-dcx-collector-form]').forEach(function (form) {
      if (!isCollectorForm(form)) return;
      ensureHoneypot(form);
      form.setAttribute('data-dcx-collector-form', '1');
      form.setAttribute('data-dcx-crm-skip-leads', '1');
      if (!form.getAttribute('method') || form.getAttribute('method').toLowerCase() === 'get') {
        form.setAttribute('method', 'post');
      }
      form.setAttribute('action', '#');
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindForms);
  } else {
    bindForms();
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!isCollectorForm(form)) return;

    event.preventDefault();
    event.stopImmediatePropagation();
    ensureHoneypot(form);

    var payload = formToPayload(form);
    if (payload.website_url && String(payload.website_url).trim() !== '') {
      showSuccess(form);
      return;
    }

    var submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    sendToProxy(payload)
      .then(function (result) {
        if (submitBtn) submitBtn.disabled = false;
        var body = result.body || {};
        if (body.success === true) {
          showSuccess(form, body.message);
        } else {
          showError(form, body.message);
        }
      })
      .catch(function () {
        if (submitBtn) submitBtn.disabled = false;
        showError(form);
      });
  }, true);
})();
