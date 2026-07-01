<?php
/** @var array<string,mixed> $old */
/** @var array<string,string> $errors */
/** @var array<string,string> $statusLabels */
/** @var array<string,string> $bankAccountTypes */
$old = $old ?? [];
$errors = $errors ?? [];
$statusLabels = $statusLabels ?? [];
$bankAccountTypes = $bankAccountTypes ?? [];
$isEdit = !empty($project['id']);
$action = $isEdit ? app_url('/projects/' . (int) $project['id'] . '/update') : app_url('/projects');
$v = static fn (string $k, string $default = ''): string => e((string) ($old[$k] ?? $default));
$sel = static fn (string $k, string $val): string => (string) ($old[$k] ?? '') === $val ? 'selected' : '';
$err = static function (string $k) use ($errors): string {
    return !empty($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
};
?>
<form method="post" action="<?= e($action) ?>" class="form-card">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="form-grid-full"><label for="project_name">Nome do projeto *</label>
            <input type="text" id="project_name" name="project_name" class="input" value="<?= $v('project_name') ?>" required>
            <?= $err('project_name') ?>
        </div>
        <div><label for="edition_year">Ano da edição</label><input type="number" id="edition_year" name="edition_year" class="input" value="<?= $v('edition_year') ?>" placeholder="2026"><?= $err('edition_year') ?></div>
        <div><label for="project_status">Status *</label>
            <select id="project_status" name="project_status" class="input" required>
                <?php foreach ($statusLabels as $k => $label): ?><option value="<?= e($k) ?>" <?= $sel('project_status', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select><?= $err('project_status') ?>
        </div>
        <div><label for="pronac_number">PRONAC</label><input type="text" id="pronac_number" name="pronac_number" class="input" value="<?= $v('pronac_number') ?>"></div>
        <div><label for="salic_proposal_number">Proposta SALIC</label><input type="text" id="salic_proposal_number" name="salic_proposal_number" class="input" value="<?= $v('salic_proposal_number') ?>"></div>
        <div><label for="law_framework">Mecanismo / Lei</label><input type="text" id="law_framework" name="law_framework" class="input" value="<?= $v('law_framework') ?>" placeholder="Lei Rouanet"></div>
        <div><label for="proponent_name">Proponente</label><input type="text" id="proponent_name" name="proponent_name" class="input" value="<?= $v('proponent_name') ?>"></div>
        <div><label for="proponent_document">CNPJ/CPF do proponente</label><input type="text" id="proponent_document" name="proponent_document" class="input" value="<?= $v('proponent_document') ?>"></div>

        <div><label for="approved_total_amount">Total aprovado (R$)</label><input type="text" id="approved_total_amount" name="approved_total_amount" class="input" value="<?= $v('approved_total_amount') ?>" placeholder="470448,00"><?= $err('approved_total_amount') ?></div>
        <div><label for="authorized_capture_amount">Autorizado p/ captação (R$)</label><input type="text" id="authorized_capture_amount" name="authorized_capture_amount" class="input" value="<?= $v('authorized_capture_amount') ?>"><?= $err('authorized_capture_amount') ?></div>
        <div><label for="capture_commission_budget">Rubrica de captação (R$)</label><input type="text" id="capture_commission_budget" name="capture_commission_budget" class="input" value="<?= $v('capture_commission_budget') ?>" placeholder="42768,00"><?= $err('capture_commission_budget') ?></div>

        <div><label for="capture_start_date">Início da captação</label><input type="date" id="capture_start_date" name="capture_start_date" class="input" value="<?= $v('capture_start_date') ?>"><?= $err('capture_start_date') ?></div>
        <div><label for="capture_end_date">Fim da captação</label><input type="date" id="capture_end_date" name="capture_end_date" class="input" value="<?= $v('capture_end_date') ?>"><?= $err('capture_end_date') ?></div>

        <div><label for="bank_name">Banco</label><input type="text" id="bank_name" name="bank_name" class="input" value="<?= $v('bank_name') ?>"></div>
        <div><label for="bank_agency">Agência</label><input type="text" id="bank_agency" name="bank_agency" class="input" value="<?= $v('bank_agency') ?>"></div>
        <div><label for="bank_account">Conta</label><input type="text" id="bank_account" name="bank_account" class="input" value="<?= $v('bank_account') ?>"></div>
        <div><label for="bank_account_digit">Dígito</label><input type="text" id="bank_account_digit" name="bank_account_digit" class="input" value="<?= $v('bank_account_digit') ?>"></div>
        <div><label for="bank_account_type">Tipo de conta</label>
            <select id="bank_account_type" name="bank_account_type" class="input">
                <option value="">—</option>
                <?php foreach ($bankAccountTypes as $k => $label): ?><option value="<?= e($k) ?>" <?= $sel('bank_account_type', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>

        <div class="form-grid-full"><label for="notes">Observações</label><textarea id="notes" name="notes" class="input" rows="3"><?= $v('notes') ?></textarea></div>
    </div>

    <div class="alert alert-info" style="margin-top:14px;">
        O <strong>fator de comissão</strong> é calculado automaticamente: rubrica de captação ÷ total aprovado.
    </div>

    <div class="actions-row" style="margin-top:18px;">
        <button type="submit" class="btn btn-yellow"><?= $isEdit ? 'Salvar alterações' : 'Cadastrar projeto' ?></button>
        <a href="<?= e(app_url($isEdit ? '/projects/' . (int) $project['id'] : '/projects')) ?>" class="btn btn-outline">Cancelar</a>
    </div>
</form>
