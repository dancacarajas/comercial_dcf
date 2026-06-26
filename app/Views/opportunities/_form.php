<?php
/**
 * Formulário compartilhado de Oportunidade (cadastro e edição).
 *
 * Variáveis: $formAction, $submitLabel, $old, $errors, $companies,
 * $companyContacts, $statusLabels, $statusProbabilities, $quotaInterests,
 * $sources, $urgencyLevels, $lostReasons, $owners
 */
$old                 = $old ?? [];
$errors              = $errors ?? [];
$companies           = $companies ?? [];
$companyContacts     = $companyContacts ?? [];
$statusLabels        = $statusLabels ?? [];
$statusProbabilities = $statusProbabilities ?? [];
$quotaInterests      = $quotaInterests ?? [];
$quotas              = $quotas ?? [];
$sources             = $sources ?? [];
$urgencyLevels       = $urgencyLevels ?? [];
$lostReasons         = $lostReasons ?? [];
$owners              = $owners ?? [];

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

$selectedCompanyId = (int) $val('company_id');
$selectedArchived  = false;
foreach ($companies as $co) {
    if ((int) $co['id'] === $selectedCompanyId && !empty($co['archived_at'])) {
        $selectedArchived = true;
        break;
    }
}
// JSON do mapa status => probabilidade sugerida (automação leve no front).
$probJson = htmlspecialchars(json_encode($statusProbabilities, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>

<form method="post" action="<?= e($formAction) ?>" class="form-box" novalidate>
    <?= csrf_field() ?>

    <h3 class="h3-card form-section-title"><i data-lucide="link"></i> Vínculo</h3>
    <div class="form-grid">
        <div>
            <label for="company_id">Empresa *</label>
            <select id="company_id" name="company_id" required>
                <option value="">— Selecione a empresa —</option>
                <?php foreach ($companies as $co): ?>
                    <option value="<?= (int) $co['id'] ?>" <?= $selectedCompanyId === (int) $co['id'] ? 'selected' : '' ?>>
                        <?= e($co['name']) ?><?= !empty($co['archived_at']) ? ' (arquivada)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $err('company_id') ?>
        </div>
        <div>
            <label for="contact_id">Contato principal</label>
            <select id="contact_id" name="contact_id">
                <option value="">— Sem contato —</option>
                <?php foreach ($companyContacts as $ct): ?>
                    <option value="<?= (int) $ct['id'] ?>" <?= (int) $val('contact_id') === (int) $ct['id'] ? 'selected' : '' ?>>
                        <?= e($ct['name']) ?><?= !empty($ct['position_title']) ? ' — ' . e($ct['position_title']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="field-hint">Liste os contatos da empresa selecionada. Salve a empresa para atualizar a lista.</small>
            <?= $err('contact_id') ?>
        </div>
    </div>
    <?php if ($selectedArchived): ?>
        <div class="notice notice-warn" style="margin-top:12px;">
            <p class="mb-0"><i data-lucide="alert-triangle"></i> Atenção: a empresa selecionada está <strong>arquivada</strong>. A oportunidade será vinculada mesmo assim.</p>
        </div>
    <?php endif; ?>

    <h3 class="h3-card form-section-title"><i data-lucide="handshake"></i> Dados da oportunidade</h3>
    <div class="form-grid">
        <div class="col-span-2">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="180" required>
            <?= $err('title') ?>
        </div>
        <div>
            <label for="quota_id">Cota de patrocínio</label>
            <select id="quota_id" name="quota_id">
                <option value="">— Sem cota vinculada —</option>
                <?php foreach ($quotas as $qt): ?>
                    <option value="<?= (int) $qt['id'] ?>" data-amount="<?= $qt['amount'] !== null ? e((string) $qt['amount']) : '' ?>" <?= (int) $val('quota_id') === (int) $qt['id'] ? 'selected' : '' ?>>
                        <?= e($qt['name']) ?><?= $qt['amount'] !== null ? ' — ' . money_br($qt['amount']) : ' — valor flexível' ?><?= in_array((string) ($qt['status'] ?? ''), ['suspensa', 'fechada'], true) ? ' (' . e($qt['status']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="field-hint">Vínculo real com a cota. Preenche o valor estimado automaticamente se ele estiver vazio.</small>
            <?= $err('quota_id') ?>
        </div>
        <div>
            <label for="estimated_value">Valor estimado (R$)</label>
            <input type="text" id="estimated_value" name="estimated_value" value="<?= e($val('estimated_value')) ?>" placeholder="Ex.: 100000,00">
            <?= $err('estimated_value') ?>
        </div>
        <div class="col-span-2">
            <label for="quota_interest">Interesse de cota (legado / observação)</label>
            <select id="quota_interest" name="quota_interest">
                <option value="">— Não informado —</option>
                <?php foreach ($quotaInterests as $qi): ?>
                    <option value="<?= e($qi) ?>" <?= $val('quota_interest') === $qi ? 'selected' : '' ?>><?= e($qi) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="field-hint">Campo histórico/auxiliar. Prefira vincular a cota real acima.</small>
            <?= $err('quota_interest') ?>
        </div>
        <div>
            <label for="status">Status do funil</label>
            <select id="status" name="status" data-prob-map="<?= $probJson ?>">
                <?php foreach ($statusLabels as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'prospect_identificado') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
        </div>
        <div>
            <label for="probability">Probabilidade (%)</label>
            <input type="number" id="probability" name="probability" min="0" max="100" value="<?= e($val('probability')) ?>" placeholder="Sugerida pelo status">
            <?= $err('probability') ?>
        </div>
        <div>
            <label for="source">Origem</label>
            <select id="source" name="source">
                <option value="">— Não informada —</option>
                <?php foreach ($sources as $src): ?>
                    <option value="<?= e($src) ?>" <?= $val('source') === $src ? 'selected' : '' ?>><?= e(ucfirst($src)) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('source') ?>
        </div>
        <div>
            <label for="owner_user_id">Responsável interno</label>
            <select id="owner_user_id" name="owner_user_id">
                <option value="">— Sem responsável —</option>
                <?php foreach ($owners as $o): ?>
                    <option value="<?= (int) $o['id'] ?>" <?= (int) $val('owner_user_id') === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('owner_user_id') ?>
        </div>
        <div>
            <label for="urgency_level">Urgência</label>
            <select id="urgency_level" name="urgency_level">
                <?php foreach ($urgencyLevels as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('urgency_level', 'normal') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('urgency_level') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="calendar-clock"></i> Datas</h3>
    <div class="form-grid">
        <div>
            <label for="opened_at">Data de abertura</label>
            <input type="datetime-local" id="opened_at" name="opened_at" value="<?= e($dtLocal('opened_at')) ?>">
            <small class="field-hint">Se vazio, usa a data/hora atual.</small>
            <?= $err('opened_at') ?>
        </div>
        <div>
            <label for="last_interaction_at">Última movimentação</label>
            <input type="datetime-local" id="last_interaction_at" name="last_interaction_at" value="<?= e($dtLocal('last_interaction_at')) ?>">
            <?= $err('last_interaction_at') ?>
        </div>
        <div>
            <label for="next_action_at">Próxima ação</label>
            <input type="datetime-local" id="next_action_at" name="next_action_at" value="<?= e($dtLocal('next_action_at')) ?>">
            <?= $err('next_action_at') ?>
        </div>
        <div>
            <label for="quota_reserved_until">Reserva de cota válida até</label>
            <input type="datetime-local" id="quota_reserved_until" name="quota_reserved_until" value="<?= e($dtLocal('quota_reserved_until')) ?>">
            <small class="field-hint">Use quando o status for "Reserva de cota". Opcional.</small>
            <?= $err('quota_reserved_until') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="circle-slash"></i> Resultado / perda</h3>
    <div class="form-grid">
        <div>
            <label for="lost_reason">Motivo de perda</label>
            <select id="lost_reason" name="lost_reason">
                <option value="">— Não se aplica —</option>
                <?php foreach ($lostReasons as $lr): ?>
                    <option value="<?= e($lr) ?>" <?= $val('lost_reason') === $lr ? 'selected' : '' ?>><?= e(ucfirst($lr)) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="field-hint">Obrigatório quando o status for "Perdido".</small>
            <?= $err('lost_reason') ?>
        </div>
    </div>

    <div style="margin-top:18px;">
        <label for="notes">Observações</label>
        <textarea id="notes" name="notes" rows="4"><?= e($val('notes')) ?></textarea>
        <?= $err('notes') ?>
    </div>

    <div class="actions-row" style="margin-top:22px;">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= e($submitLabel) ?></button>
    </div>
</form>

<script>
(function () {
    var statusEl = document.getElementById('status');
    var probEl = document.getElementById('probability');
    if (!statusEl || !probEl) { return; }
    var map = {};
    try { map = JSON.parse(statusEl.getAttribute('data-prob-map') || '{}'); } catch (e) { map = {}; }
    statusEl.addEventListener('change', function () {
        var s = statusEl.value;
        if (s === 'fechado') { probEl.value = 100; return; }
        if (s === 'perdido') { probEl.value = 0; return; }
        if (probEl.value === '' && map[s] !== undefined) { probEl.value = map[s]; }
    });

    // Sugere valor estimado a partir da cota (apenas quando o campo está vazio).
    var quotaEl = document.getElementById('quota_id');
    var valueEl = document.getElementById('estimated_value');
    if (quotaEl && valueEl) {
        quotaEl.addEventListener('change', function () {
            var opt = quotaEl.options[quotaEl.selectedIndex];
            var amount = opt ? opt.getAttribute('data-amount') : '';
            if (valueEl.value.trim() === '' && amount) {
                valueEl.value = String(amount).replace('.', ',');
            }
        });
    }
})();
</script>
