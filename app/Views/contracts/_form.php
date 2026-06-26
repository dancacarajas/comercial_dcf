<?php
$contract = $contract ?? [];
$old = $old ?? $contract;
$isEdit = !empty($contract['id']);
$formAction = $isEdit ? app_url('/contracts/' . (int) $contract['id'] . '/update') : app_url('/contracts');
$errors = $errors ?? [];
$contractTypes = $contractTypes ?? [];
$fundingMechanisms = $fundingMechanisms ?? [];
$statuses = $statuses ?? [];
$reviewStatuses = $reviewStatuses ?? [];
$signatureStatuses = $signatureStatuses ?? [];
$sponsors = $sponsors ?? [];
$companies = $companies ?? [];
$contacts = $contacts ?? [];
$opportunities = $opportunities ?? [];
$proposals = $proposals ?? [];
$quotas = $quotas ?? [];
$users = $users ?? [];
$documents = $documents ?? [];

$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
$dtLocal = static fn (string $k): string => e(str_replace(' ', 'T', substr($val($k), 0, 16)));
?>

<form method="post" action="<?= e($formAction) ?>" class="form-box contract-form" novalidate>
    <?= csrf_field() ?>

    <h3 class="h3-card form-section-title"><i data-lucide="link"></i> Vínculos</h3>
    <div class="form-grid">
        <div class="form-grid-full">
            <label for="sponsor_id">Patrocinador / fechamento *</label>
            <select id="sponsor_id" name="sponsor_id" required>
                <option value="">— Selecione —</option>
                <?php foreach ($sponsors as $sp): ?>
                    <option value="<?= (int) $sp['id'] ?>" <?= (int) $val('sponsor_id') === (int) $sp['id'] ? 'selected' : '' ?>>
                        <?= e($sp['sponsor_display_name'] ?? $sp['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $err('sponsor_id') ?>
        </div>
        <div>
            <label for="company_id">Empresa</label>
            <select id="company_id" name="company_id">
                <option value="">— Opcional —</option>
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
                <?php foreach ($contacts as $ct): ?>
                    <option value="<?= (int) $ct['id'] ?>" <?= (int) $val('contact_id') === (int) $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="opportunity_id">Oportunidade</label>
            <select id="opportunity_id" name="opportunity_id">
                <option value="">— Opcional —</option>
                <?php foreach ($opportunities as $op): ?>
                    <option value="<?= (int) $op['id'] ?>" <?= (int) $val('opportunity_id') === (int) $op['id'] ? 'selected' : '' ?>><?= e($op['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="proposal_id">Proposta</label>
            <select id="proposal_id" name="proposal_id">
                <option value="">— Opcional —</option>
                <?php foreach ($proposals as $pr): ?>
                    <option value="<?= (int) $pr['id'] ?>" <?= (int) $val('proposal_id') === (int) $pr['id'] ? 'selected' : '' ?>><?= e($pr['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="quota_id">Cota</label>
            <select id="quota_id" name="quota_id">
                <option value="">— Opcional —</option>
                <?php foreach ($quotas as $q): ?>
                    <option value="<?= (int) $q['id'] ?>" <?= (int) $val('quota_id') === (int) $q['id'] ? 'selected' : '' ?>><?= e($q['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($documents !== []): ?>
        <div>
            <label for="draft_document_id">Documento minuta</label>
            <select id="draft_document_id" name="draft_document_id">
                <option value="">— Opcional —</option>
                <?php foreach ($documents as $doc): ?>
                    <option value="<?= (int) $doc['id'] ?>" <?= (int) $val('draft_document_id') === (int) $doc['id'] ? 'selected' : '' ?>><?= e($doc['label'] ?? $doc['title'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('draft_document_id') ?>
        </div>
        <div>
            <label for="final_document_id">Documento final</label>
            <select id="final_document_id" name="final_document_id">
                <option value="">— Opcional —</option>
                <?php foreach ($documents as $doc): ?>
                    <option value="<?= (int) $doc['id'] ?>" <?= (int) $val('final_document_id') === (int) $doc['id'] ? 'selected' : '' ?>><?= e($doc['label'] ?? $doc['title'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('final_document_id') ?>
        </div>
        <div>
            <label for="signed_document_id">Documento assinado</label>
            <select id="signed_document_id" name="signed_document_id">
                <option value="">— Opcional —</option>
                <?php foreach ($documents as $doc): ?>
                    <option value="<?= (int) $doc['id'] ?>" <?= (int) $val('signed_document_id') === (int) $doc['id'] ? 'selected' : '' ?>><?= e($doc['label'] ?? $doc['title'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('signed_document_id') ?>
        </div>
        <?php endif; ?>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="file-signature"></i> Dados do contrato</h3>
    <div class="form-grid">
        <div>
            <label for="contract_number">Número do contrato</label>
            <input type="text" id="contract_number" name="contract_number" value="<?= e($val('contract_number')) ?>" maxlength="80" placeholder="ex.: CT-2026-001">
            <?= $err('contract_number') ?>
        </div>
        <div class="form-grid-full">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="180" placeholder="Deixe em branco para gerar automaticamente">
            <?= $err('title') ?>
        </div>
        <div>
            <label for="contract_type">Tipo de instrumento *</label>
            <select id="contract_type" name="contract_type" required>
                <?php foreach ($contractTypes as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('contract_type', 'termo_patrocinio') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('contract_type') ?>
        </div>
        <div>
            <label for="formalized_value">Valor formalizado (R$)</label>
            <input type="text" id="formalized_value" name="formalized_value" class="contract-value" value="<?= e($val('formalized_value')) ?>" placeholder="0,00">
            <?= $err('formalized_value') ?>
        </div>
        <div>
            <label for="funding_mechanism">Mecanismo de formalização *</label>
            <select id="funding_mechanism" name="funding_mechanism" required>
                <?php foreach ($fundingMechanisms as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('funding_mechanism', 'nao_definido') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('funding_mechanism') ?>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'minuta') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
        </div>
        <div>
            <label for="review_status">Status de revisão *</label>
            <select id="review_status" name="review_status" required>
                <?php foreach ($reviewStatuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('review_status', 'nao_revisado') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('review_status') ?>
        </div>
        <div>
            <label for="signature_status">Status de assinatura *</label>
            <select id="signature_status" name="signature_status" required>
                <?php foreach ($signatureStatuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('signature_status', 'nao_enviado') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('signature_status') ?>
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

    <h3 class="h3-card form-section-title"><i data-lucide="calendar"></i> Vigência e marcos</h3>
    <div class="form-grid">
        <div>
            <label for="start_date">Data inicial</label>
            <input type="date" id="start_date" name="start_date" value="<?= e($val('start_date')) ?>">
            <?= $err('start_date') ?>
        </div>
        <div>
            <label for="end_date">Data final</label>
            <input type="date" id="end_date" name="end_date" value="<?= e($val('end_date')) ?>">
            <?= $err('end_date') ?>
        </div>
        <div>
            <label for="sent_for_signature_at">Enviado para assinatura</label>
            <input type="datetime-local" id="sent_for_signature_at" name="sent_for_signature_at" value="<?= $dtLocal('sent_for_signature_at') ?>">
            <?= $err('sent_for_signature_at') ?>
        </div>
        <div>
            <label for="signed_at">Assinado em</label>
            <input type="datetime-local" id="signed_at" name="signed_at" value="<?= $dtLocal('signed_at') ?>">
            <?= $err('signed_at') ?>
        </div>
        <div>
            <label for="effective_at">Início de vigência</label>
            <input type="date" id="effective_at" name="effective_at" value="<?= e($val('effective_at')) ?>">
            <?= $err('effective_at') ?>
        </div>
        <div>
            <label for="ended_at">Encerrado em</label>
            <input type="date" id="ended_at" name="ended_at" value="<?= e($val('ended_at')) ?>">
            <?= $err('ended_at') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="pen-line"></i> Signatários</h3>
    <div class="form-grid">
        <div>
            <label for="sponsor_signatory_name">Signatário do patrocinador</label>
            <input type="text" id="sponsor_signatory_name" name="sponsor_signatory_name" value="<?= e($val('sponsor_signatory_name')) ?>" maxlength="180">
        </div>
        <div>
            <label for="sponsor_signatory_email">E-mail do signatário (patrocinador)</label>
            <input type="email" id="sponsor_signatory_email" name="sponsor_signatory_email" value="<?= e($val('sponsor_signatory_email')) ?>" maxlength="180">
            <?= $err('sponsor_signatory_email') ?>
        </div>
        <div>
            <label for="sponsor_signatory_position">Cargo (patrocinador)</label>
            <input type="text" id="sponsor_signatory_position" name="sponsor_signatory_position" value="<?= e($val('sponsor_signatory_position')) ?>" maxlength="120">
        </div>
        <div>
            <label for="sponsor_signatory_document">Documento (CPF/CNPJ)</label>
            <input type="text" id="sponsor_signatory_document" name="sponsor_signatory_document" value="<?= e($val('sponsor_signatory_document')) ?>" maxlength="80">
        </div>
        <div>
            <label for="organization_signatory_name">Signatário da organização</label>
            <input type="text" id="organization_signatory_name" name="organization_signatory_name" value="<?= e($val('organization_signatory_name')) ?>" maxlength="180">
        </div>
        <div>
            <label for="organization_signatory_email">E-mail do signatário (organização)</label>
            <input type="email" id="organization_signatory_email" name="organization_signatory_email" value="<?= e($val('organization_signatory_email')) ?>" maxlength="180">
            <?= $err('organization_signatory_email') ?>
        </div>
        <div>
            <label for="organization_signatory_position">Cargo (organização)</label>
            <input type="text" id="organization_signatory_position" name="organization_signatory_position" value="<?= e($val('organization_signatory_position')) ?>" maxlength="120">
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="message-square"></i> Observações</h3>
    <div class="form-grid">
        <div>
            <label for="approval_notes">Notas de aprovação</label>
            <textarea id="approval_notes" name="approval_notes" rows="2"><?= e($val('approval_notes')) ?></textarea>
        </div>
        <div>
            <label for="signature_notes">Notas de assinatura</label>
            <textarea id="signature_notes" name="signature_notes" rows="2"><?= e($val('signature_notes')) ?></textarea>
        </div>
        <div>
            <label for="legal_notes">Notas jurídicas</label>
            <textarea id="legal_notes" name="legal_notes" rows="2"><?= e($val('legal_notes')) ?></textarea>
        </div>
        <div>
            <label for="notes">Observações</label>
            <textarea id="notes" name="notes" rows="3"><?= e($val('notes')) ?></textarea>
        </div>
        <?php if (can('contracts.edit')): ?>
        <div>
            <label for="internal_notes">Observações internas</label>
            <textarea id="internal_notes" name="internal_notes" rows="3"><?= e($val('internal_notes')) ?></textarea>
        </div>
        <?php endif; ?>
    </div>

    <div class="form-actions contract-actions">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= $isEdit ? 'Salvar alterações' : 'Registrar contrato' ?></button>
    </div>
</form>
