<?php
$document  = $document ?? [];
$old       = $old ?? [];
$errors    = $errors ?? [];
$categories = $categories ?? [];
$statuses  = $statuses ?? [];
$did       = (int) ($document['id'] ?? 0);

$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Documentos · Versão</span>
                <h1 class="h2-section">Nova versão</h1>
                <p class="page-sub">Base: <?= e($document['title'] ?? '') ?> (v<?= (int) ($document['version_number'] ?? 1) ?>)</p>
            </div>
            <a href="<?= e(app_url('/documents/' . $did)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <form method="post" action="<?= e(app_url('/documents/' . $did . '/version')) ?>" enctype="multipart/form-data" class="form-box document-form" novalidate>
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-span-2">
                    <label for="title">Título *</label>
                    <input type="text" id="title" name="title" value="<?= e($val('title')) ?>" required maxlength="180">
                    <?= $err('title') ?>
                </div>
                <div class="form-grid-full">
                    <label for="description">Descrição</label>
                    <textarea id="description" name="description" rows="3"><?= e($val('description')) ?></textarea>
                </div>
                <div>
                    <label for="category">Categoria *</label>
                    <select id="category" name="category" required>
                        <?php foreach ($categories as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $val('category') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('category') ?>
                </div>
                <div>
                    <label for="status">Status inicial *</label>
                    <select id="status" name="status" required>
                        <option value="ativo" <?= $val('status', 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="em_revisao" <?= $val('status') === 'em_revisao' ? 'selected' : '' ?>>Em revisão</option>
                    </select>
                </div>
                <div>
                    <label for="valid_until">Validade</label>
                    <input type="date" id="valid_until" name="valid_until" value="<?= e($val('valid_until')) ?>">
                    <?= $err('valid_until') ?>
                </div>
                <div class="form-grid-full">
                    <label for="notes">Observações</label>
                    <textarea id="notes" name="notes" rows="3"><?= e($val('notes')) ?></textarea>
                </div>
            </div>

            <h3 class="h3-card form-section-title"><i data-lucide="upload"></i> Novo arquivo *</h3>
            <div class="document-upload">
                <input type="file" id="document_file" name="document_file" class="document-upload-input" required
                       accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.zip">
                <p class="page-sub">PDF, Office, imagens, CSV, TXT ou ZIP — máximo 25 MB.</p>
                <?= $err('document_file') ?>
            </div>

            <div class="form-grid-full" style="margin-top:14px;">
                <label><input type="checkbox" name="mark_previous_substituted" value="1" checked> Marcar versão anterior como substituída</label>
            </div>

            <div class="actions-row" style="margin-top:18px;">
                <button type="submit" class="btn btn-yellow"><i data-lucide="git-branch"></i> Criar nova versão</button>
                <a href="<?= e(app_url('/documents/' . $did)) ?>" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</section>
