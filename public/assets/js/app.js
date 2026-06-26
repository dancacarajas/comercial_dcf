/* =====================================================================
   Dança Carajás Captação — JS leve (app.js)
   Sem dependências. Apenas interações simples: menu mobile e mensagens.
   ===================================================================== */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initIcons();
        initNavToggle();
        initDismissibleNotices();
        initAutoConfirm();
    });

    /** Renderiza os ícones Lucide (<i data-lucide="nome">). */
    function initIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    /** Alterna o menu de navegação no mobile. */
    function initNavToggle() {
        var toggle = document.querySelector('[data-nav-toggle]');
        var nav = document.querySelector('[data-nav]');

        if (!toggle || !nav) {
            return;
        }

        toggle.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    /** Permite fechar avisos com [data-dismiss]. */
    function initDismissibleNotices() {
        document.querySelectorAll('[data-dismiss]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.closest('[data-dismissible]');
                if (target) {
                    target.style.display = 'none';
                }
            });
        });
    }

    /** Confirmação simples para ações sensíveis (uso futuro). */
    function initAutoConfirm() {
        document.querySelectorAll('[data-confirm]').forEach(function (el) {
            el.addEventListener('click', function (event) {
                var message = el.getAttribute('data-confirm') || 'Tem certeza?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    }
})();
