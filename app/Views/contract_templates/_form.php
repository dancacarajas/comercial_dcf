<?php
/** @var array<string, mixed> $old @var array<string, string> $errors @var array<string, string> $placeholders */
$old = $old ?? [];
$errors = $errors ?? [];
$types = $types ?? [];
$statuses = $statuses ?? [];
$placeholders = $placeholders ?? [];
$formAction = $formAction ?? app_url('/contract-templates');
$submitLabel = $submitLabel ?? 'Salvar';
$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err = static fn (string $k): string => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<form method="post" action="<?= e($formAction) ?>" class="form-box" id="contract-template-form">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="col-span-2">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" value="<?= e($val('title')) ?>" required maxlength="180">
            <?= $err('title') ?>
        </div>
        <div>
            <label for="template_type">Tipo</label>
            <select id="template_type" name="template_type">
                <?php foreach ($types as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('template_type', 'autorizacao_captador') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'rascunho') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-span-2">
            <label for="description">Descrição</label>
            <textarea id="description" name="description" rows="2"><?= e($val('description')) ?></textarea>
        </div>
        <div>
            <label for="template_key">Chave (opcional)</label>
            <input type="text" id="template_key" name="template_key" value="<?= e($val('template_key')) ?>" maxlength="100">
        </div>
        <div>
            <label class="check-inline"><input type="checkbox" name="is_default" value="1" <?= $val('is_default') === '1' ? 'checked' : '' ?>> Modelo padrão do tipo</label>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="file-text"></i> Conteúdo do modelo</h3>
    <div class="contract-editor-wrap">
        <div class="contract-editor-toolbar" role="toolbar" aria-label="Formatação">
            <button type="button" class="btn btn-sm btn-outline" data-cmd="bold"><strong>B</strong></button>
            <button type="button" class="btn btn-sm btn-outline" data-cmd="italic"><em>I</em></button>
            <button type="button" class="btn btn-sm btn-outline" data-cmd="underline"><u>U</u></button>
            <button type="button" class="btn btn-sm btn-outline" data-cmd="formatBlock" data-value="h2">Título</button>
            <button type="button" class="btn btn-sm btn-outline" data-cmd="formatBlock" data-value="p">Parágrafo</button>
            <button type="button" class="btn btn-sm btn-outline" data-cmd="insertUnorderedList">Lista</button>
            <button type="button" class="btn btn-sm btn-outline" data-cmd="insertOrderedList">Lista nº</button>
            <button type="button" class="btn btn-sm btn-outline" data-cmd="justifyLeft">Esq.</button>
            <button type="button" class="btn btn-sm btn-outline" data-cmd="justifyCenter">Centro</button>
            <button type="button" class="btn btn-sm btn-outline" data-cmd="justifyRight">Dir.</button>
            <button type="button" class="btn btn-sm btn-outline" data-cmd="insertBlock">Bloco</button>
            <select id="placeholder-select" class="input input-sm">
                <option value="">Inserir campo dinâmico</option>
                <?php foreach ($placeholders as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?> ({{<?= e($key) ?>}})</option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-sm btn-outline" data-cmd="removeFormat">Limpar</button>
            <button type="button" class="btn btn-sm btn-outline" id="contract-preview-btn">Pré-visualizar</button>
        </div>
        <div id="contract-editor" class="contract-editor-area" contenteditable="true"><?= $val('content_html', '<p></p>') ?></div>
        <textarea name="content_html" id="content_html" hidden><?= e($val('content_html', '<p></p>')) ?></textarea>
        <?= $err('content_html') ?>
        <div id="contract-preview-panel" class="contract-preview-panel" hidden>
            <h4 class="h4-card">Pré-visualização</h4>
            <div id="contract-preview-content" class="contract-document-body"></div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-yellow"><?= e($submitLabel) ?></button>
    </div>
</form>
<script src="<?= e(app_url('/assets/js/contract-template-editor.js')) ?>" defer></script>
