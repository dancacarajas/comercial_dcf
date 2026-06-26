<?php
$sponsor = $sponsor ?? [];
$isEdit = !empty($sponsor['id']);
$formAction = $isEdit ? app_url('/sponsors/' . (int) $sponsor['id'] . '/update') : app_url('/sponsors');
$old = $old ?? [];
$errors = $errors ?? [];
$sponsorshipTypes = $sponsorshipTypes ?? [];
$fundingMechanisms = $fundingMechanisms ?? [];
$statuses = $statuses ?? [];
$paymentStatuses = $paymentStatuses ?? [];
$companies = $companies ?? [];
$companyContacts = $companyContacts ?? [];
$opportunities = $opportunities ?? [];
$proposals = $proposals ?? [];
$quotas = $quotas ?? [];
$users = $users ?? [];
$documents = $documents ?? [];

$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
?>

<form method="post" action="<?= e($formAction) ?>" class="form-box sponsor-form" novalidate>
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
            <label for="proposal_id">Proposta</label>
            <select id="proposal_id" name="proposal_id">
                <option value="">— Opcional —</option>
                <?php foreach ($proposals as $pr): ?>
                    <option value="<?= (int) $pr['id'] ?>" <?= (int) $val('proposal_id') === (int) $pr['id'] ? 'selected' : '' ?>><?= e($pr['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('proposal_id') ?>
        </div>
        <div>
            <label for="quota_id">Cota</label>
            <select id="quota_id" name="quota_id">
                <option value="">— Opcional —</option>
                <?php foreach ($quotas as $q): ?>
                    <option value="<?= (int) $q['id'] ?>" <?= (int) $val('quota_id') === (int) $q['id'] ? 'selected' : '' ?>><?= e($q['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('quota_id') ?>
        </div>
        <?php if ($documents !== []): ?>
        <div>
            <label for="primary_document_id">Documento principal</label>
            <select id="primary_document_id" name="primary_document_id">
                <option value="">— Opcional —</option>
                <?php foreach ($documents as $doc): ?>
                    <option value="<?= (int) $doc['id'] ?>" <?= (int) $val('primary_document_id') === (int) $doc['id'] ? 'selected' : '' ?>><?= e($doc['label'] ?? $doc['title'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('primary_document_id') ?>
        </div>
        <?php endif; ?>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="badge-dollar-sign"></i> Identificação</h3>
    <div class="form-grid">
        <div class="form-grid-full">
            <label for="sponsor_display_name">Nome de exibição do patrocinador *</label>
            <input type="text" id="sponsor_display_name" name="sponsor_display_name" value="<?= e($val('sponsor_display_name')) ?>" required maxlength="180">
            <?= $err('sponsor_display_name') ?>
        </div>
        <div>
            <label for="project_year">Ano do projeto</label>
            <input type="number" id="project_year" name="project_year" min="2026" value="<?= e($val('project_year', '2026')) ?>">
            <?= $err('project_year') ?>
        </div>
        <div>
            <label for="festival_edition">Edição / Festival</label>
            <input type="text" id="festival_edition" name="festival_edition" value="<?= e($val('festival_edition', 'Dança Carajás Festival 2026')) ?>">
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="tags"></i> Classificação</h3>
    <div class="form-grid">
        <div>
            <label for="sponsorship_type">Tipo de patrocínio *</label>
            <select id="sponsorship_type" name="sponsorship_type" required>
                <?php foreach ($sponsorshipTypes as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('sponsorship_type', 'patrocinio_direto') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('sponsorship_type') ?>
        </div>
        <div>
            <label for="funding_mechanism">Mecanismo de fomento *</label>
            <select id="funding_mechanism" name="funding_mechanism" required>
                <?php foreach ($fundingMechanisms as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('funding_mechanism', 'lei_rouanet') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('funding_mechanism') ?>
        </div>
        <div>
            <label for="status">Status do fechamento *</label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'fechamento_registrado') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
        </div>
        <div>
            <label for="payment_status">Status de pagamento/aporte *</label>
            <select id="payment_status" name="payment_status" required>
                <?php foreach ($paymentStatuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('payment_status', 'pendente') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('payment_status') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="banknote"></i> Valores</h3>
    <div class="form-grid">
        <div>
            <label for="committed_amount">Valor comprometido</label>
            <input type="text" id="committed_amount" name="committed_amount" value="<?= e($val('committed_amount')) ?>" placeholder="0,00">
            <?= $err('committed_amount') ?>
        </div>
        <div>
            <label for="confirmed_amount">Valor confirmado</label>
            <input type="text" id="confirmed_amount" name="confirmed_amount" value="<?= e($val('confirmed_amount')) ?>" placeholder="0,00">
            <?= $err('confirmed_amount') ?>
        </div>
        <div class="form-grid-full">
            <label for="in_kind_description">Permuta / bens e serviços</label>
            <textarea id="in_kind_description" name="in_kind_description" rows="2"><?= e($val('in_kind_description')) ?></textarea>
        </div>
        <div>
            <label for="in_kind_estimated_value">Valor estimado da permuta</label>
            <input type="text" id="in_kind_estimated_value" name="in_kind_estimated_value" value="<?= e($val('in_kind_estimated_value')) ?>">
            <?= $err('in_kind_estimated_value') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="calendar-clock"></i> Datas</h3>
    <div class="form-grid">
        <div>
            <label for="closed_at">Data do fechamento</label>
            <input type="datetime-local" id="closed_at" name="closed_at" value="<?= e(str_replace(' ', 'T', substr($val('closed_at', date('Y-m-d H:i')), 0, 16))) ?>">
            <?= $err('closed_at') ?>
        </div>
        <div>
            <label for="confirmed_at">Data de confirmação</label>
            <input type="datetime-local" id="confirmed_at" name="confirmed_at" value="<?= e(str_replace(' ', 'T', substr($val('confirmed_at'), 0, 16))) ?>">
            <?= $err('confirmed_at') ?>
        </div>
        <div>
            <label for="expected_payment_date">Previsão de aporte</label>
            <input type="date" id="expected_payment_date" name="expected_payment_date" value="<?= e($val('expected_payment_date')) ?>">
            <?= $err('expected_payment_date') ?>
        </div>
        <div>
            <label for="received_at">Data de recebimento</label>
            <input type="date" id="received_at" name="received_at" value="<?= e($val('received_at')) ?>">
            <?= $err('received_at') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="landmark"></i> Lei de Incentivo</h3>
    <div class="form-grid">
        <div><label for="pronac_number">Número PRONAC</label><input type="text" id="pronac_number" name="pronac_number" value="<?= e($val('pronac_number')) ?>"><?= $err('pronac_number') ?></div>
        <div><label for="incentive_law">Lei / Mecanismo</label><input type="text" id="incentive_law" name="incentive_law" value="<?= e($val('incentive_law')) ?>"></div>
        <div class="form-grid-full"><label for="incentive_notes">Observações de incentivo</label><textarea id="incentive_notes" name="incentive_notes" rows="2"><?= e($val('incentive_notes')) ?></textarea></div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="settings"></i> Controle</h3>
    <div class="form-grid">
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
        <div class="form-grid-full">
            <label><input type="checkbox" name="public_announcement_allowed" value="1" <?= !empty($old['public_announcement_allowed']) ? 'checked' : '' ?>> Permissão de anúncio público</label>
        </div>
        <?php if (!$isEdit): ?>
        <div class="form-grid-full">
            <label><input type="checkbox" name="close_linked" value="1"> Atualizar oportunidade/proposta relacionada para fechada</label>
            <p class="text-muted-dcx" style="font-size:13px;margin-top:4px;">Opcional. Desmarcado por padrão — nenhum efeito colateral automático.</p>
        </div>
        <?php endif; ?>
        <div class="form-grid-full"><label for="notes">Observações</label><textarea id="notes" name="notes" rows="3"><?= e($val('notes')) ?></textarea></div>
        <div class="form-grid-full"><label for="internal_notes">Observações internas</label><textarea id="internal_notes" name="internal_notes" rows="3"><?= e($val('internal_notes')) ?></textarea></div>
    </div>

    <div class="actions-row">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= $isEdit ? 'Salvar alterações' : 'Registrar fechamento' ?></button>
        <a href="<?= e($isEdit ? app_url('/sponsors/' . (int) $sponsor['id']) : app_url('/sponsors')) ?>" class="btn btn-outline">Cancelar</a>
    </div>
</form>
