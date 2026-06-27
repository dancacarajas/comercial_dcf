(function () {
    'use strict';

    var form = document.getElementById('contract-template-form');
    if (!form) return;

    var editor = document.getElementById('contract-editor');
    var hidden = document.getElementById('content_html');
    var previewBtn = document.getElementById('contract-preview-btn');
    var previewPanel = document.getElementById('contract-preview-panel');
    var previewContent = document.getElementById('contract-preview-content');
    var placeholderSelect = document.getElementById('placeholder-select');

    function syncHidden() {
        if (editor && hidden) {
            hidden.value = editor.innerHTML;
        }
    }

    form.addEventListener('submit', syncHidden);

    document.querySelectorAll('.contract-editor-toolbar [data-cmd]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var cmd = btn.getAttribute('data-cmd');
            var val = btn.getAttribute('data-value') || null;
            if (cmd === 'insertBlock') {
                document.execCommand('insertHTML', false, '<p>&nbsp;</p>');
            } else {
                document.execCommand(cmd, false, val);
            }
            editor.focus();
            syncHidden();
        });
    });

    if (placeholderSelect) {
        placeholderSelect.addEventListener('change', function () {
            var key = placeholderSelect.value;
            if (!key) return;
            document.execCommand('insertText', false, '{{' + key + '}}');
            placeholderSelect.value = '';
            editor.focus();
            syncHidden();
        });
    }

    if (previewBtn && previewPanel && previewContent) {
        previewBtn.addEventListener('click', function (e) {
            e.preventDefault();
            syncHidden();
            previewContent.innerHTML = hidden.value;
            previewPanel.hidden = !previewPanel.hidden;
        });
    }

    if (editor) {
        editor.addEventListener('input', syncHidden);
    }
})();
