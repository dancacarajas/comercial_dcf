<?php
$proposal = $proposal ?? [];
$old      = $old ?? [];
$errors   = $errors ?? [];
$types    = $types ?? [];

$pid  = (int) ($proposal['id'] ?? 0);
$val  = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err  = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Nova versão</span>
                <h1 class="h2-section">Versão baseada em: <?= e($proposal['title'] ?? '') ?></h1>
                <p class="page-sub">
                    Versão atual: <span class="proposal-version">v<?= (int) ($proposal['version_number'] ?? 1) ?></span>
                    · Nova versão será <strong>v<?= (int) ($proposal['version_number'] ?? 1) + 1 ?></strong>
                </p>
            </div>
            <a href="<?= e(app_url('/proposals/' . $pid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="arrow-left"></i> Voltar</a>
        </div>

        <div class="notice" style="margin-bottom:18px;">
            <p class="mb-0"><i data-lucide="info"></i> Os vínculos (empresa, contato, oportunidade e cota) serão copiados da proposta base. Status inicial: <strong>Rascunho</strong>.</p>
        </div>

        <form method="post" action="<?= e(app_url('/proposals/' . $pid . '/version')) ?>" enctype="multipart/form-data" class="form-box proposal-form" novalidate>
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-grid-full">
                    <label for="title">Título *</label>
                    <input type="text" id="title" name="title" value="<?= e($val('title', (string) ($proposal['title'] ?? ''))) ?>" maxlength="180" required>
                    <?= $err('title') ?>
                </div>
                <div>
                    <label for="proposed_value">Valor proposto</label>
                    <input type="text" id="proposed_value" name="proposed_value" value="<?= e($val('proposed_value', (string) ($proposal['proposed_value'] ?? ''))) ?>" placeholder="0,00">
                    <?= $err('proposed_value') ?>
                </div>
                <div>
                    <label for="valid_until">Validade</label>
                    <input type="date" id="valid_until" name="valid_until" value="<?= e($val('valid_until', (string) ($proposal['valid_until'] ?? ''))) ?>">
                    <?= $err('valid_until') ?>
                </div>
                <div class="form-grid-full">
                    <label for="pdf_file">PDF da proposta (opcional)</label>
                    <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf,.pdf" class="proposal-upload-input">
                    <?= $err('pdf_file') ?>
                </div>
                <div class="form-grid-full">
                    <label for="revision_notes">Histórico / notas de revisão</label>
                    <textarea id="revision_notes" name="revision_notes" rows="4"><?= e($val('revision_notes')) ?></textarea>
                </div>
                <div class="form-grid-full">
                    <label for="notes">Observações</label>
                    <textarea id="notes" name="notes" rows="3"><?= e($val('notes', (string) ($proposal['notes'] ?? ''))) ?></textarea>
                </div>
            </div>

            <div class="actions-row" style="margin-top:18px;">
                <button type="submit" class="btn btn-yellow"><i data-lucide="git-branch"></i> Criar nova versão</button>
                <a href="<?= e(app_url('/proposals/' . $pid)) ?>" class="btn btn-outline">Cancelar</a>
            </div>
        </form>
    </div>
</section>
