<?php
$dossier = $dossier ?? [];
$old = $old ?? $dossier;
$isEdit = !empty($dossier['id']);
$formAction = $isEdit
    ? app_url('/sponsor-dossiers/' . (int) $dossier['id'] . '/update')
    : app_url('/sponsor-dossiers');
$errors = $errors ?? [];
$dossierTypes = $dossierTypes ?? [];
$statuses = $statuses ?? [];
$deliveryStatuses = $deliveryStatuses ?? [];
$projects = $projects ?? [];
$sponsors = $sponsors ?? [];
$contracts = $contracts ?? [];
$companies = $companies ?? [];
$contacts = $contacts ?? [];
$opportunities = $opportunities ?? [];
$proposals = $proposals ?? [];
$quotas = $quotas ?? [];
$users = $users ?? [];
$documents = $documents ?? [];

$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$checked = static fn (string $k, bool $default = true): string => !empty($old[$k] ?? ($default ? 1 : 0)) ? 'checked' : '';
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
?>

<form method="post" action="<?= e($formAction) ?>" class="form-box dossier-form" novalidate>
    <?= csrf_field() ?>

    <h3 class="h3-card form-section-title"><i data-lucide="link"></i> Vínculos</h3>
    <div class="form-grid">
        <div class="form-grid-full">
            <label for="incentive_project_id">Projeto incentivado *</label>
            <select id="incentive_project_id" name="incentive_project_id" required>
                <option value="">— Selecione —</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= (int) $project['id'] ?>" <?= (int) $val('incentive_project_id') === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('incentive_project_id') ?>
        </div>
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
            <label for="main_contract_id">Contrato principal</label>
            <select id="main_contract_id" name="main_contract_id">
                <option value="">— Opcional —</option>
                <?php foreach ($contracts as $cn): ?>
                    <option value="<?= (int) $cn['id'] ?>" <?= (int) $val('main_contract_id') === (int) $cn['id'] ? 'selected' : '' ?>><?= e($cn['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('main_contract_id') ?>
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
                <label for="main_document_id">Documento principal</label>
                <select id="main_document_id" name="main_document_id">
                    <option value="">— Opcional —</option>
                    <?php foreach ($documents as $doc): ?>
                        <option value="<?= (int) $doc['id'] ?>" <?= (int) $val('main_document_id') === (int) $doc['id'] ? 'selected' : '' ?>><?= e($doc['label'] ?? $doc['title'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('main_document_id') ?>
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
                <label for="delivery_receipt_document_id">Comprovante de entrega</label>
                <select id="delivery_receipt_document_id" name="delivery_receipt_document_id">
                    <option value="">— Opcional —</option>
                    <?php foreach ($documents as $doc): ?>
                        <option value="<?= (int) $doc['id'] ?>" <?= (int) $val('delivery_receipt_document_id') === (int) $doc['id'] ? 'selected' : '' ?>><?= e($doc['label'] ?? $doc['title'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('delivery_receipt_document_id') ?>
            </div>
        <?php endif; ?>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="folder-open"></i> Identificação</h3>
    <div class="form-grid">
        <div>
            <label for="dossier_number">Número / referência</label>
            <input type="text" id="dossier_number" name="dossier_number" value="<?= e($val('dossier_number')) ?>" maxlength="80" placeholder="ex.: DOS-2026-001">
            <?= $err('dossier_number') ?>
        </div>
        <div class="form-grid-full">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="180" placeholder="Deixe em branco para gerar automaticamente">
            <?= $err('title') ?>
        </div>
        <div>
            <label for="dossier_type">Tipo de dossiê *</label>
            <select id="dossier_type" name="dossier_type" required>
                <?php foreach ($dossierTypes as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('dossier_type', 'prestacao_comercial') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('dossier_type') ?>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'rascunho') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
        </div>
        <div>
            <label for="delivery_status">Status de entrega *</label>
            <select id="delivery_status" name="delivery_status" required>
                <?php foreach ($deliveryStatuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('delivery_status', 'nao_entregue') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('delivery_status') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="calendar"></i> Período</h3>
    <div class="form-grid">
        <div>
            <label for="period_start">Início do período</label>
            <input type="date" id="period_start" name="period_start" value="<?= e($val('period_start')) ?>">
            <?= $err('period_start') ?>
        </div>
        <div>
            <label for="period_end">Fim do período</label>
            <input type="date" id="period_end" name="period_end" value="<?= e($val('period_end')) ?>">
            <?= $err('period_end') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="layers"></i> Inclusões na consolidação</h3>
    <div class="form-grid">
        <div class="form-grid-full filter-checks dossier-section">
            <label><input type="checkbox" name="include_contracts" value="1" <?= $checked('include_contracts') ?>> Contratos</label>
            <label><input type="checkbox" name="include_counterparts" value="1" <?= $checked('include_counterparts') ?>> Contrapartidas</label>
            <label><input type="checkbox" name="include_financials" value="1" <?= $checked('include_financials') ?>> Lançamentos financeiros</label>
            <label><input type="checkbox" name="include_documents" value="1" <?= $checked('include_documents') ?>> Documentos</label>
            <label><input type="checkbox" name="include_evidence" value="1" <?= $checked('include_evidence') ?>> Evidências</label>
            <label><input type="checkbox" name="include_clipping" value="1" <?= $checked('include_clipping') ?>> Clipping</label>
            <label><input type="checkbox" name="include_media" value="1" <?= $checked('include_media') ?>> Mídia (foto/vídeo)</label>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="file-text"></i> Textos e resumos</h3>
    <div class="form-grid">
        <div class="form-grid-full">
            <label for="executive_summary">Resumo executivo</label>
            <textarea id="executive_summary" name="executive_summary" rows="3"><?= e($val('executive_summary')) ?></textarea>
        </div>
        <div class="form-grid-full">
            <label for="commercial_summary">Resumo comercial</label>
            <textarea id="commercial_summary" name="commercial_summary" rows="3"><?= e($val('commercial_summary')) ?></textarea>
        </div>
        <div class="form-grid-full">
            <label for="counterparts_summary">Resumo de contrapartidas</label>
            <textarea id="counterparts_summary" name="counterparts_summary" rows="2"><?= e($val('counterparts_summary')) ?></textarea>
        </div>
        <div class="form-grid-full">
            <label for="financial_summary">Resumo financeiro</label>
            <textarea id="financial_summary" name="financial_summary" rows="2"><?= e($val('financial_summary')) ?></textarea>
        </div>
        <div class="form-grid-full">
            <label for="documents_summary">Resumo de documentos</label>
            <textarea id="documents_summary" name="documents_summary" rows="2"><?= e($val('documents_summary')) ?></textarea>
        </div>
        <div class="form-grid-full">
            <label for="pending_notes">Pendências</label>
            <textarea id="pending_notes" name="pending_notes" rows="2"><?= e($val('pending_notes')) ?></textarea>
        </div>
        <div>
            <label for="approval_notes">Observações de aprovação</label>
            <textarea id="approval_notes" name="approval_notes" rows="2"><?= e($val('approval_notes')) ?></textarea>
        </div>
        <div>
            <label for="delivery_notes">Observações de entrega</label>
            <textarea id="delivery_notes" name="delivery_notes" rows="2"><?= e($val('delivery_notes')) ?></textarea>
        </div>
        <div class="form-grid-full">
            <label for="notes">Observações gerais</label>
            <textarea id="notes" name="notes" rows="3"><?= e($val('notes')) ?></textarea>
        </div>
        <?php if (can('dossiers.edit')): ?>
            <div class="form-grid-full">
                <label for="internal_notes">Observações internas</label>
                <textarea id="internal_notes" name="internal_notes" rows="3"><?= e($val('internal_notes')) ?></textarea>
            </div>
        <?php endif; ?>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="user"></i> Responsável</h3>
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
    </div>

    <div class="form-actions dossier-actions">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= $isEdit ? 'Salvar alterações' : 'Registrar dossiê' ?></button>
    </div>
</form>
