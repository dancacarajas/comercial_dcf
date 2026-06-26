<?php
/**
 * Formulário compartilhado de Documento (cadastro e edição).
 */
$old             = $old ?? [];
$errors          = $errors ?? [];
$companies       = $companies ?? [];
$companyContacts = $companyContacts ?? [];
$opportunities   = $opportunities ?? [];
$quotas          = $quotas ?? [];
$proposals       = $proposals ?? [];
$leads           = $leads ?? [];
$sponsors        = $sponsors ?? [];
$users           = $users ?? [];
$categories      = $categories ?? [];
$statuses        = $statuses ?? [];
$accessLevels    = $accessLevels ?? [];
$document        = $document ?? [];
$isEdit          = !empty($document['id']);
$model           = $model ?? null;

$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
?>

<form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="form-box document-form" novalidate>
    <?= csrf_field() ?>

    <h3 class="h3-card form-section-title"><i data-lucide="link"></i> Vínculos</h3>
    <div class="form-grid">
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
            <label for="lead_id">Lead</label>
            <select id="lead_id" name="lead_id">
                <option value="">— Opcional —</option>
                <?php foreach ($leads as $ld): ?>
                    <option value="<?= (int) $ld['id'] ?>" <?= (int) $val('lead_id') === (int) $ld['id'] ? 'selected' : '' ?>><?= e($ld['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('lead_id') ?>
        </div>
        <div>
            <label for="sponsor_id">Patrocinador</label>
            <select id="sponsor_id" name="sponsor_id">
                <option value="">— Opcional —</option>
                <?php foreach ($sponsors as $sp): ?>
                    <option value="<?= (int) $sp['id'] ?>" <?= (int) $val('sponsor_id') === (int) $sp['id'] ? 'selected' : '' ?>><?= e($sp['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('sponsor_id') ?>
        </div>
        <div>
            <label for="counterpart_id">Contrapartida</label>
            <select id="counterpart_id" name="counterpart_id">
                <option value="">— Opcional —</option>
                <?php foreach (($counterparts ?? []) as $cp): ?>
                    <option value="<?= (int) $cp['id'] ?>" <?= (int) $val('counterpart_id') === (int) $cp['id'] ? 'selected' : '' ?>><?= e($cp['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('counterpart_id') ?>
        </div>
        <?php if (!empty($val('counterpart_id')) && can('counterparts.edit')): ?>
            <div class="form-grid-full">
                <label class="checkbox-inline">
                    <input type="checkbox" name="use_as_evidence" value="1" <?= !empty($val('use_as_evidence')) ? 'checked' : '' ?>>
                    Usar este documento como evidência da contrapartida
                </label>
            </div>
        <?php endif; ?>
        <div>
            <label for="contract_id">Contrato</label>
            <select id="contract_id" name="contract_id">
                <option value="">— Opcional —</option>
                <?php foreach (($contracts ?? []) as $ct): ?>
                    <option value="<?= (int) $ct['id'] ?>" <?= (int) $val('contract_id') === (int) $ct['id'] ? 'selected' : '' ?>><?= e($ct['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('contract_id') ?>
        </div>
        <?php if (!empty($val('contract_id')) && can('contracts.edit')): ?>
            <div class="form-grid-full contract-doc-links">
                <label class="checkbox-inline">
                    <input type="checkbox" name="use_as_draft" value="1" <?= !empty($val('use_as_draft')) ? 'checked' : '' ?>>
                    Usar como minuta do contrato
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="use_as_final" value="1" <?= !empty($val('use_as_final')) ? 'checked' : '' ?>>
                    Usar como versão final do contrato
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="use_as_signed" value="1" <?= !empty($val('use_as_signed')) ? 'checked' : '' ?>>
                    Usar como documento assinado do contrato
                </label>
            </div>
        <?php endif; ?>
        <div>
            <label for="financial_entry_id">Lançamento financeiro</label>
            <select id="financial_entry_id" name="financial_entry_id">
                <option value="">— Opcional —</option>
                <?php foreach (($financials ?? []) as $fe): ?>
                    <option value="<?= (int) $fe['id'] ?>" <?= (int) $val('financial_entry_id') === (int) $fe['id'] ? 'selected' : '' ?>><?= e($fe['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('financial_entry_id') ?>
        </div>
        <?php if (!empty($val('financial_entry_id')) && can('financials.edit')): ?>
            <div class="form-grid-full financial-doc-links">
                <label class="checkbox-inline">
                    <input type="checkbox" name="use_as_proof" value="1" <?= !empty($val('use_as_proof')) ? 'checked' : '' ?>>
                    Usar como comprovante de pagamento
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="use_as_receipt" value="1" <?= !empty($val('use_as_receipt')) ? 'checked' : '' ?>>
                    Usar como recibo
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="use_as_fiscal" value="1" <?= !empty($val('use_as_fiscal')) ? 'checked' : '' ?>>
                    Usar como documento fiscal
                </label>
            </div>
        <?php endif; ?>
        <?php if (can('dossiers.view')): ?>
        <div>
            <label for="sponsor_dossier_id">Dossiê do patrocinador</label>
            <select id="sponsor_dossier_id" name="sponsor_dossier_id">
                <option value="">— Opcional —</option>
                <?php foreach (($sponsorDossiers ?? []) as $sd): ?>
                    <option value="<?= (int) $sd['id'] ?>" <?= (int) $val('sponsor_dossier_id') === (int) $sd['id'] ? 'selected' : '' ?>><?= e($sd['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('sponsor_dossier_id') ?>
        </div>
        <?php if (!empty($val('sponsor_dossier_id')) && can('dossiers.edit')): ?>
            <div class="form-grid-full dossier-doc-links">
                <label class="checkbox-inline">
                    <input type="checkbox" name="use_as_dossier_main" value="1" <?= !empty($val('use_as_dossier_main')) ? 'checked' : '' ?>>
                    Usar como documento principal do dossiê
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="use_as_dossier_final" value="1" <?= !empty($val('use_as_dossier_final')) ? 'checked' : '' ?>>
                    Usar como documento final do dossiê
                </label>
                <label class="checkbox-inline">
                    <input type="checkbox" name="use_as_dossier_delivery_receipt" value="1" <?= !empty($val('use_as_dossier_delivery_receipt')) ? 'checked' : '' ?>>
                    Usar como comprovante de entrega do dossiê
                </label>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="file-stack"></i> Dados do documento</h3>
    <div class="form-grid">
        <div class="form-span-2">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" value="<?= e($val('title')) ?>" required maxlength="180">
            <?= $err('title') ?>
        </div>
        <div class="form-grid-full">
            <label for="description">Descrição</label>
            <textarea id="description" name="description" rows="3"><?= e($val('description')) ?></textarea>
            <?= $err('description') ?>
        </div>
        <div>
            <label for="category">Categoria *</label>
            <select id="category" name="category" required>
                <?php foreach ($categories as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('category', 'documento_comercial') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('category') ?>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'ativo') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
        </div>
        <div>
            <label for="access_level">Nível de acesso *</label>
            <select id="access_level" name="access_level" required>
                <?php foreach ($accessLevels as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('access_level', 'interno') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('access_level') ?>
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
        <div>
            <label for="document_date">Data do documento</label>
            <input type="date" id="document_date" name="document_date" value="<?= e($val('document_date')) ?>">
            <?= $err('document_date') ?>
        </div>
        <div>
            <label for="valid_until">Validade</label>
            <input type="date" id="valid_until" name="valid_until" value="<?= e($val('valid_until')) ?>">
            <?= $err('valid_until') ?>
        </div>
        <div class="form-grid-full">
            <label for="notes">Observações</label>
            <textarea id="notes" name="notes" rows="3"><?= e($val('notes')) ?></textarea>
            <?= $err('notes') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="upload"></i> Arquivo</h3>
    <div class="document-upload">
        <?php if ($isEdit && !empty($document['original_name'])): ?>
            <p class="document-file-meta">Arquivo atual: <strong><?= e($document['original_name']) ?></strong>
                (<?= ($model ?? null) ? e($model->formatSize($document['size_bytes'] ?? 0)) : '—' ?>)
            </p>
            <p class="page-sub">Envie um novo arquivo apenas se desejar substituir o atual.</p>
        <?php endif; ?>
        <label for="document_file">Arquivo <?= $isEdit ? '' : '*' ?></label>
        <input type="file" id="document_file" name="document_file" class="document-upload-input" <?= $isEdit ? '' : 'required' ?>
               accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.zip">
        <p class="page-sub">PDF, Office, imagens, CSV, TXT ou ZIP — máximo 25 MB.</p>
        <?= $err('document_file') ?>
    </div>

    <div class="actions-row" style="margin-top:18px;">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= e($submitLabel) ?></button>
        <a href="<?= e(app_url('/documents')) ?>" class="btn btn-outline">Cancelar</a>
    </div>
</form>
