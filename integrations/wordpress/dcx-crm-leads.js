/**
 * DCX CRM Leads v1.3 — proxy REST WordPress (sem token no navegador).
 * Nunca redireciona com dados pessoais na query string.
 *
 * Copiar para: wp-content/novamira-sandbox/dcx-crm-leads.js
 */
(function () {
  'use strict';

  if (!window.DCX_CRM_LEADS || !window.DCX_CRM_LEADS.proxyUrl) {
    return;
  }

  var cfg = window.DCX_CRM_LEADS;
  var FORM_SELECTORS = [
    'form[data-dcx-crm-form]',
    'form.dcx-sponsor-form',
    'form.dcx-fale__form',
    'form.form-box[action*="fale-com-a-producao"]'
  ];

  var CONSENT_FIELD_NAMES = [
    'autorizacao', 'autorizacao_contato', 'aceite_contato', 'aceite_privacidade',
    'consentimento', 'consent', 'aceite', 'aceite_lgpd', 'lgpd', 'contact_consent', 'privacy'
  ];

  var CONSENT_YES = ['1', 'true', 'sim', 'yes', 'on', 'autorizado'];

  function isCollectorForm(form) {
    if (!form || !form.querySelector) return false;
    if (form.getAttribute && form.getAttribute('data-dcx-crm-skip-leads') === '1') return true;
    if (form.getAttribute && form.getAttribute('data-dcx-collector-form')) return true;
    if (form.querySelector('[name="experiencia_rouanet"]')) return true;
    if (form.querySelector('[name="documento"]') && form.querySelector('[name="segmentos"]')) return true;
    if (window.location.pathname.indexOf('captadores-de-recursos') !== -1 && form.matches && form.matches('form.form-box')) return true;
    return false;
  }

  function matchesForm(form) {
    if (!form || !form.matches) return false;
    if (form.matches('form.dcx-fale-producao-newsletter__form')) return false;
    if (isCollectorForm(form)) return false;
    return FORM_SELECTORS.some(function (sel) {
      try { return form.matches(sel); } catch (e) { return false; }
    });
  }

  function isConsentYes(value) {
    if (value === true) return true;
    var v = String(value == null ? '' : value).trim().toLowerCase();
    return CONSENT_YES.indexOf(v) !== -1;
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

  function readConsentFromForm(form, payload) {
    var granted = false;

    CONSENT_FIELD_NAMES.forEach(function (name) {
      var fields = form.querySelectorAll('[name="' + name + '"]');
      fields.forEach(function (el) {
        if (el.type === 'checkbox' || el.type === 'radio') {
          if (el.checked && (isConsentYes(el.value) || el.value === '' || el.value === 'on')) {
            granted = true;
          }
        } else if (isConsentYes(el.value)) {
          granted = true;
        }
      });
    });

    if (granted) {
      payload.autorizacao = 'sim';
    } else if (payload.autorizacao && isConsentYes(payload.autorizacao)) {
      payload.autorizacao = 'sim';
    } else if (payload.autorizacao_contato && isConsentYes(payload.autorizacao_contato)) {
      payload.autorizacao = 'sim';
    } else if (payload.aceite_contato && isConsentYes(payload.aceite_contato)) {
      payload.autorizacao = 'sim';
    }

    return payload;
  }

  function normalizeFieldAliases(payload) {
    if (!payload.nome && payload.responsavel) {
      payload.nome = payload.responsavel;
    }
    if (!payload.whatsapp && payload.telefone) {
      payload.whatsapp = payload.telefone;
    }
    if (!payload.cidade_uf && (payload.cidade || payload.estado)) {
      payload.cidade_uf = [payload.cidade, payload.estado].filter(Boolean).join('/');
    }
    return payload;
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

    payload = normalizeFieldAliases(payload);
    payload = readConsentFromForm(form, payload);

    payload.origin_page = payload.origin_page || cfg.originPage || window.location.pathname.replace(/^\/+/, '');
    payload.source_url = payload.source_url || cfg.sourceUrl || window.location.href.split('#')[0];
    payload.form_id = form.id || form.getAttribute('data-dcx-crm-form') || form.getAttribute('data-form-id') || form.className.split(/\s+/)[0] || 'patrocinio-form';
    payload.form_name = form.getAttribute('data-form-name') || payload.form_id;

    if (payload.website && String(payload.website).trim() !== '') {
      payload.website_url = payload.website;
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

  function findAnchor(form) {
    return document.getElementById('mensagem-enviada')
      || form.querySelector('[data-dcx-crm-success]')
      || form.closest('section')
      || form.parentElement;
  }

  function hideFeedback(form) {
    var parent = form.parentElement;
    if (!parent) return;
    ['.dcx-crm-success-msg', '.dcx-crm-error-msg'].forEach(function (sel) {
      var el = parent.querySelector(sel);
      if (el) el.hidden = true;
    });
  }

  function scrollToAnchor(form) {
    var anchor = findAnchor(form);
    if (anchor && anchor.scrollIntoView) {
      anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function showSuccess(form, message) {
    hideFeedback(form);
    var text = message || 'Mensagem enviada com sucesso.';
    var existing = form.parentElement && form.parentElement.querySelector('.dcx-crm-success-msg');
    if (existing) {
      existing.textContent = text;
      existing.hidden = false;
    } else {
      var box = document.createElement('div');
      box.className = 'dcx-crm-success-msg';
      box.setAttribute('role', 'status');
      box.setAttribute('data-dcx-crm-success', '1');
      box.style.cssText = 'margin:16px 0;padding:16px 18px;border-radius:8px;background:#e8f7ef;border:1px solid #0e7a56;color:#0e7a56;font-weight:800;';
      box.textContent = text;
      form.insertAdjacentElement('beforebegin', box);
    }

    scrollToAnchor(form);

    if (window.history && window.history.replaceState) {
      var clean = window.location.pathname + window.location.search
        .replace(/([?&])lead_enviado=1(&|$)/, '$1')
        .replace(/([?&])lead_erro=1(&|$)/, '$1')
        .replace(/[?&]$/, '');
      window.history.replaceState(null, '', clean + '#mensagem-enviada');
    }

    form.reset();
  }

  function showError(form, message) {
    hideFeedback(form);
    var text = message || 'Não foi possível enviar sua mensagem agora. Tente novamente em alguns instantes.';
    var existing = form.parentElement && form.parentElement.querySelector('.dcx-crm-error-msg');
    if (existing) {
      existing.textContent = text;
      existing.hidden = false;
    } else {
      var box = document.createElement('div');
      box.className = 'dcx-crm-error-msg';
      box.setAttribute('role', 'alert');
      box.setAttribute('data-dcx-crm-error', '1');
      box.style.cssText = 'margin:16px 0;padding:16px 18px;border-radius:8px;background:#fdecea;border:1px solid #c62828;color:#c62828;font-weight:800;';
      box.textContent = text;
      form.insertAdjacentElement('beforebegin', box);
    }

    scrollToAnchor(form);
  }

  function bindForms() {
    document.querySelectorAll(FORM_SELECTORS.join(',')).forEach(function (form) {
      if (form.matches('form.dcx-fale-producao-newsletter__form')) return;
      ensureHoneypot(form);
      if (form.getAttribute('method') && form.getAttribute('method').toLowerCase() === 'get') {
        form.setAttribute('method', 'post');
      }
      if (!form.getAttribute('action') || form.getAttribute('action').indexOf('fale-com-a-producao') !== -1) {
        form.setAttribute('action', '#');
      }
      if (!form.getAttribute('data-dcx-crm-form')) {
        form.setAttribute('data-dcx-crm-form', 'patrocinio');
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindForms);
  } else {
    bindForms();
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!matchesForm(form)) return;

    event.preventDefault();
    event.stopPropagation();
    ensureHoneypot(form);
    hideFeedback(form);

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

  if (window.location.search.indexOf('lead_enviado=1') !== -1) {
    var successForm = document.querySelector(FORM_SELECTORS.join(','));
    if (successForm && matchesForm(successForm)) {
      showSuccess(successForm);
    }
  }

  if (window.location.search.indexOf('lead_erro=1') !== -1) {
    var errorForm = document.querySelector(FORM_SELECTORS.join(','));
    if (errorForm && matchesForm(errorForm)) {
      showError(errorForm);
      if (window.history && window.history.replaceState) {
        var cleanErr = window.location.pathname + window.location.search
          .replace(/([?&])lead_erro=1(&|$)/, '$1')
          .replace(/[?&]$/, '');
        window.history.replaceState(null, '', cleanErr + '#mensagem-enviada');
      }
    }
  }
})();
