<?php
$entry = $entry ?? [];
$old = $old ?? $entry;
$isEdit = !empty($entry['id']);
$formAction = $isEdit ? app_url('/financials/' . (int) $entry['id'] . '/update') : app_url('/financials');
$errors = $errors ?? [];
$entryTypes = $entryTypes ?? [];
$fundingMechanisms = $fundingMechanisms ?? [];
$paymentMethods = $paymentMethods ?? [];
$statuses = $statuses ?? [];
$fiscalStatuses = $fiscalStatuses ?? [];
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
$remainingPreview = $remainingPreview ?? null;

$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
$dtLocal = static fn (string $k): string => e(str_replace(' ', 'T', substr($val($k), 0, 16)));

$planned = is_numeric($old['planned_amount'] ?? null) ? (float) $old['planned_amount'] : 0.0;
$received = is_numeric($old['received_amount'] ?? null) ? (float) $old['received_amount'] : 0.0;
$balance = $remainingPreview ?? max(0.0, round($planned - $received, 2));
?>

<form method="post" action="<?= e($formAction) ?>" class="form-box financial-form" novalidate>
    <?= csrf_field() ?>

    <h3 class="h3-card form-section-title"><i data-lucide="link"></i> Vínculos</h3>
    <div class="form-grid">
        <div class="form-grid-full">
            <label for="incentive_project_id">Projeto incentivado *</label>
            <select id="incentive_project_id" name="incentive_project_id" required>
                <option value="">— Selecione —</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= (int) $project['id'] ?>" <?= (int) $val('incentive_project_id') === (int) $project['id'] ? 'selected' : '' ?>>
                        <?= e($project['label'] ?? '') ?>
                    </option>
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
            <label for="contract_id">Contrato</label>
            <select id="contract_id" name="contract_id">
                <option value="">— Opcional —</option>
                <?php foreach ($contracts as $cn): ?>
                    <option value="<?= (int) $cn['id'] ?>" <?= (int) $val('contract_id') === (int) $cn['id'] ? 'selected' : '' ?>><?= e($cn['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('contract_id') ?>
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
            <label for="proof_document_id">Comprovante de pagamento</label>
            <select id="proof_document_id" name="proof_document_id">
                <option value="">— Opcional —</option>
                <?php foreach ($documents as $doc): ?>
                    <option value="<?= (int) $doc['id'] ?>" <?= (int) $val('proof_document_id') === (int) $doc['id'] ? 'selected' : '' ?>><?= e($doc['label'] ?? $doc['title'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('proof_document_id') ?>
        </div>
        <div>
            <label for="receipt_document_id">Recibo</label>
            <select id="receipt_document_id" name="receipt_document_id">
                <option value="">— Opcional —</option>
                <?php foreach ($documents as $doc): ?>
                    <option value="<?= (int) $doc['id'] ?>" <?= (int) $val('receipt_document_id') === (int) $doc['id'] ? 'selected' : '' ?>><?= e($doc['label'] ?? $doc['title'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('receipt_document_id') ?>
        </div>
        <div>
            <label for="fiscal_document_id">Documento fiscal</label>
            <select id="fiscal_document_id" name="fiscal_document_id">
                <option value="">— Opcional —</option>
                <?php foreach ($documents as $doc): ?>
                    <option value="<?= (int) $doc['id'] ?>" <?= (int) $val('fiscal_document_id') === (int) $doc['id'] ? 'selected' : '' ?>><?= e($doc['label'] ?? $doc['title'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('fiscal_document_id') ?>
        </div>
        <?php endif; ?>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="receipt"></i> Identificação</h3>
    <div class="form-grid">
        <div>
            <label for="entry_number">Número / referência</label>
            <input type="text" id="entry_number" name="entry_number" value="<?= e($val('entry_number')) ?>" maxlength="80" placeholder="ex.: FIN-2026-001">
            <?= $err('entry_number') ?>
        </div>
        <div class="form-grid-full">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="180" placeholder="Deixe em branco para gerar automaticamente">
            <?= $err('title') ?>
        </div>
        <div>
            <label for="entry_type">Tipo de registro *</label>
            <select id="entry_type" name="entry_type" required>
                <?php foreach ($entryTypes as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('entry_type', 'parcela_patrocinio') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('entry_type') ?>
        </div>
        <div>
            <label for="funding_mechanism">Mecanismo de fomento *</label>
            <select id="funding_mechanism" name="funding_mechanism" required>
                <?php foreach ($fundingMechanisms as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('funding_mechanism', 'nao_definido') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('funding_mechanism') ?>
        </div>
        <div>
            <label for="payment_method">Forma de pagamento *</label>
            <select id="payment_method" name="payment_method" required>
                <?php foreach ($paymentMethods as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('payment_method', 'nao_definido') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('payment_method') ?>
        </div>
        <div>
            <label for="status">Status financeiro *</label>
            <select id="status" name="status" required>
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'previsto') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
        </div>
        <div>
            <label for="fiscal_document_status">Status do documento fiscal *</label>
            <select id="fiscal_document_status" name="fiscal_document_status" required>
                <?php foreach ($fiscalStatuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('fiscal_document_status', 'nao_aplicavel') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('fiscal_document_status') ?>
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

    <h3 class="h3-card form-section-title"><i data-lucide="layers"></i> Parcelamento</h3>
    <div class="form-grid">
        <div>
            <label for="installment_number">Número da parcela</label>
            <input type="number" id="installment_number" name="installment_number" min="1" value="<?= e($val('installment_number')) ?>" placeholder="ex.: 1">
            <?= $err('installment_number') ?>
        </div>
        <div>
            <label for="installments_total">Total de parcelas</label>
            <input type="number" id="installments_total" name="installments_total" min="1" value="<?= e($val('installments_total')) ?>" placeholder="ex.: 2">
            <?= $err('installments_total') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="banknote"></i> Valores</h3>
    <div class="form-grid">
        <div>
            <label for="planned_amount">Valor previsto (R$) *</label>
            <input type="text" id="planned_amount" name="planned_amount" class="financial-value" value="<?= e($val('planned_amount')) ?>" placeholder="0,00">
            <?= $err('planned_amount') ?>
        </div>
        <div>
            <label for="received_amount">Valor recebido (R$)</label>
            <input type="text" id="received_amount" name="received_amount" class="financial-received" value="<?= e($val('received_amount', '0')) ?>" placeholder="0,00">
            <?= $err('received_amount') ?>
        </div>
        <div>
            <label>Saldo (calculado)</label>
            <p class="financial-balance money-value" style="margin:8px 0 0;font-weight:700;"><?= e(money_br($balance)) ?></p>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="calendar"></i> Datas</h3>
    <div class="form-grid">
        <div>
            <label for="due_date">Vencimento</label>
            <input type="date" id="due_date" name="due_date" value="<?= e($val('due_date')) ?>">
            <?= $err('due_date') ?>
        </div>
        <div>
            <label for="expected_payment_date">Previsão de recebimento</label>
            <input type="date" id="expected_payment_date" name="expected_payment_date" value="<?= e($val('expected_payment_date')) ?>">
            <?= $err('expected_payment_date') ?>
        </div>
        <div>
            <label for="received_at">Recebido em</label>
            <input type="datetime-local" id="received_at" name="received_at" value="<?= $dtLocal('received_at') ?>">
            <?= $err('received_at') ?>
        </div>
        <div>
            <label for="reconciled_at">Conciliado em</label>
            <input type="datetime-local" id="reconciled_at" name="reconciled_at" value="<?= $dtLocal('reconciled_at') ?>">
            <?= $err('reconciled_at') ?>
        </div>
        <div>
            <label for="cancelled_at">Cancelado em</label>
            <input type="datetime-local" id="cancelled_at" name="cancelled_at" value="<?= $dtLocal('cancelled_at') ?>">
            <?= $err('cancelled_at') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="user"></i> Pagador / Referências</h3>
    <div class="form-grid">
        <div>
            <label for="payer_name">Nome do pagador</label>
            <input type="text" id="payer_name" name="payer_name" value="<?= e($val('payer_name')) ?>" maxlength="180">
        </div>
        <div>
            <label for="payer_document">Documento do pagador</label>
            <input type="text" id="payer_document" name="payer_document" value="<?= e($val('payer_document')) ?>" maxlength="80">
        </div>
        <div>
            <label for="bank_reference">Referência bancária</label>
            <input type="text" id="bank_reference" name="bank_reference" value="<?= e($val('bank_reference')) ?>" maxlength="120">
        </div>
        <div>
            <label for="transaction_reference">Referência da transação</label>
            <input type="text" id="transaction_reference" name="transaction_reference" value="<?= e($val('transaction_reference')) ?>" maxlength="120">
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="message-square"></i> Observações</h3>
    <div class="form-grid">
        <div>
            <label for="proof_notes">Observações do comprovante</label>
            <textarea id="proof_notes" name="proof_notes" rows="2"><?= e($val('proof_notes')) ?></textarea>
        </div>
        <div>
            <label for="receipt_notes">Observações do recibo</label>
            <textarea id="receipt_notes" name="receipt_notes" rows="2"><?= e($val('receipt_notes')) ?></textarea>
        </div>
        <div>
            <label for="fiscal_notes">Observações fiscais</label>
            <textarea id="fiscal_notes" name="fiscal_notes" rows="2"><?= e($val('fiscal_notes')) ?></textarea>
        </div>
        <div>
            <label for="reconciliation_notes">Observações de conciliação</label>
            <textarea id="reconciliation_notes" name="reconciliation_notes" rows="2"><?= e($val('reconciliation_notes')) ?></textarea>
        </div>
        <div>
            <label for="notes">Observações gerais</label>
            <textarea id="notes" name="notes" rows="3"><?= e($val('notes')) ?></textarea>
        </div>
        <?php if (can('financials.edit')): ?>
        <div>
            <label for="internal_notes">Observações internas</label>
            <textarea id="internal_notes" name="internal_notes" rows="3"><?= e($val('internal_notes')) ?></textarea>
        </div>
        <?php endif; ?>
    </div>

    <div class="form-actions financial-actions">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= $isEdit ? 'Salvar alterações' : 'Registrar lançamento' ?></button>
    </div>
</form>
