<?php
/**
 * Formulário compartilhado de Proposta (cadastro e edição).
 */
$old             = $old ?? [];
$errors          = $errors ?? [];
$companies       = $companies ?? [];
$companyContacts = $companyContacts ?? [];
$opportunities   = $opportunities ?? [];
$quotas          = $quotas ?? [];
$projects        = $projects ?? [];
$users           = $users ?? [];
$types           = $types ?? [];
$statuses        = $statuses ?? [];
$proposal        = $proposal ?? [];

$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
$dtLocal = static function (string $k) use ($old): string {
    $v = (string) ($old[$k] ?? '');
    if ($v === '') { return ''; }
    $ts = strtotime(str_replace('T', ' ', $v));
    return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
};
?>

<form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="form-box proposal-form" novalidate>
    <?= csrf_field() ?>

    <h3 class="h3-card form-section-title"><i data-lucide="link"></i> Vínculos</h3>
    <div class="form-grid">
        <div>
            <label for="company_id">Empresa *</label>
            <select id="company_id" name="company_id" required>
                <option value="">— Selecione —</option>
                <?php foreach ($companies as $co): ?>
                    <option value="<?= (int) $co['id'] ?>" <?= (int) $val('company_id') === (int) $co['id'] ? 'selected' : '' ?>><?= e($co['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('company_id') ?>
        </div>
        <div>
            <label for="contact_id">Contato</label>
            <select id="contact_id" name="contact_id">
                <option value="">— Opcional —</option>
                <?php foreach ($companyContacts as $ct): ?>
                    <option value="<?= (int) $ct['id'] ?>" <?= (int) $val('contact_id') === (int) $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('contact_id') ?>
        </div>
        <div>
            <label for="opportunity_id">Oportunidade</label>
            <select id="opportunity_id" name="opportunity_id">
                <option value="">— Opcional —</option>
                <?php foreach ($opportunities as $op): ?>
                    <option value="<?= (int) $op['id'] ?>" <?= (int) $val('opportunity_id') === (int) $op['id'] ? 'selected' : '' ?>><?= e($op['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('opportunity_id') ?>
        </div>
        <div>
            <label for="quota_id">Cota</label>
            <select id="quota_id" name="quota_id">
                <option value="">— Opcional —</option>
                <?php foreach ($quotas as $q): ?>
                    <option value="<?= (int) $q['id'] ?>" <?= (int) $val('quota_id') === (int) $q['id'] ? 'selected' : '' ?>><?= e($q['name'] ?? $q['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('quota_id') ?>
        </div>
        <div>
            <label for="incentive_project_id">Projeto incentivado *</label>
            <select id="incentive_project_id" name="incentive_project_id" required>
                <option value="">â€” Selecione â€”</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) $val('incentive_project_id') === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('incentive_project_id') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="file-text"></i> Dados da proposta</h3>
    <div class="form-grid">
        <div class="form-span-2">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" value="<?= e($val('title')) ?>" required maxlength="180">
            <?= $err('title') ?>
        </div>
        <div>
            <label for="type">Tipo</label>
            <select id="type" name="type">
                <?php foreach ($types as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('type', 'proposta_por_cota') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('type') ?>
        </div>
        <div>
            <label for="proposed_value">Valor proposto (R$)</label>
            <input type="text" id="proposed_value" name="proposed_value" value="<?= e($val('proposed_value')) ?>" placeholder="0,00">
            <?= $err('proposed_value') ?>
        </div>
        <div>
            <label for="version_number">Versão</label>
            <input type="number" id="version_number" name="version_number" min="1" value="<?= e($val('version_number', '1')) ?>">
            <?= $err('version_number') ?>
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'rascunho') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
        </div>
        <div>
            <label for="responsible_user_id">Responsável</label>
            <select id="responsible_user_id" name="responsible_user_id">
                <option value="">— Opcional —</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (int) $val('responsible_user_id') === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('responsible_user_id') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="calendar"></i> Datas</h3>
    <div class="form-grid">
        <div>
            <label for="created_on">Data de criação</label>
            <input type="date" id="created_on" name="created_on" value="<?= e($val('created_on')) ?>">
            <?= $err('created_on') ?>
        </div>
        <div>
            <label for="sent_at">Data de envio</label>
            <input type="datetime-local" id="sent_at" name="sent_at" value="<?= e($dtLocal('sent_at')) ?>">
            <?= $err('sent_at') ?>
        </div>
        <div>
            <label for="valid_until">Validade</label>
            <input type="date" id="valid_until" name="valid_until" value="<?= e($val('valid_until')) ?>">
            <?= $err('valid_until') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="paperclip"></i> Arquivo PDF</h3>
    <div class="proposal-upload">
        <label for="pdf_file">PDF da proposta (opcional, máx. 10 MB)</label>
        <input type="file" id="pdf_file" name="pdf_file" accept="application/pdf,.pdf">
        <?php if (!empty($proposal['pdf_original_name'])): ?>
            <p class="field-hint">Arquivo atual: <strong><?= e($proposal['pdf_original_name']) ?></strong>. Envie um novo arquivo para substituir.</p>
        <?php endif; ?>
        <?= $err('pdf_file') ?>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="align-left"></i> Textos</h3>
    <div class="form-grid">
        <div class="form-span-2">
            <label for="revision_notes">Histórico / notas de revisão</label>
            <textarea id="revision_notes" name="revision_notes" rows="4"><?= e($val('revision_notes')) ?></textarea>
        </div>
        <div class="form-span-2">
            <label for="notes">Observações</label>
            <textarea id="notes" name="notes" rows="4"><?= e($val('notes')) ?></textarea>
        </div>
    </div>

    <div class="actions-row" style="margin-top:20px;">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= e($submitLabel) ?></button>
        <a href="<?= e(app_url('/proposals')) ?>" class="btn btn-outline">Cancelar</a>
    </div>
</form>
