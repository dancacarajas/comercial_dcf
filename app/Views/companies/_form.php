<?php
/**
 * Formulário compartilhado de Empresa (cadastro e edição).
 *
 * Variáveis esperadas:
 * - $formAction (string)  URL do POST
 * - $submitLabel (string) Texto do botão
 * - $old (array)          Valores atuais (posted ou registro)
 * - $errors (array)       Mapa campo => mensagem
 * - $segments, $priorities, $statuses, $taxRegimes, $sources, $states (listas)
 * - $owners (array)       Usuários ativos para responsável
 */
$old        = $old ?? [];
$errors     = $errors ?? [];
$segments   = $segments ?? [];
$priorities = $priorities ?? [];
$statuses   = $statuses ?? [];
$taxRegimes = $taxRegimes ?? [];
$sources    = $sources ?? [];
$states     = $states ?? [];
$owners     = $owners ?? [];

$val     = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$checked = static fn (string $k): string => !empty($old[$k]) ? 'checked' : '';
$err     = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
?>

<form method="post" action="<?= e($formAction) ?>" class="form-box" novalidate>
    <?= csrf_field() ?>

    <h3 class="h3-card form-section-title"><i data-lucide="building-2"></i> Dados principais</h3>
    <div class="form-grid">
        <div>
            <label for="name">Nome da empresa *</label>
            <input type="text" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="180" required>
            <?= $err('name') ?>
        </div>
        <div>
            <label for="trade_name">Nome fantasia</label>
            <input type="text" id="trade_name" name="trade_name" value="<?= e($val('trade_name')) ?>" maxlength="180">
            <?= $err('trade_name') ?>
        </div>
        <div>
            <label for="cnpj">CNPJ</label>
            <input type="text" id="cnpj" name="cnpj" value="<?= e($val('cnpj')) ?>" maxlength="20" placeholder="Somente números">
            <?= $err('cnpj') ?>
        </div>
        <div>
            <label for="segment">Segmento</label>
            <select id="segment" name="segment">
                <option value="">— Selecione —</option>
                <?php foreach ($segments as $seg): ?>
                    <option value="<?= e($seg) ?>" <?= $val('segment') === $seg ? 'selected' : '' ?>><?= e(ucfirst($seg)) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('segment') ?>
        </div>
        <div>
            <label for="city">Cidade</label>
            <input type="text" id="city" name="city" value="<?= e($val('city')) ?>" maxlength="120">
            <?= $err('city') ?>
        </div>
        <div>
            <label for="state">Estado (UF)</label>
            <select id="state" name="state">
                <option value="">—</option>
                <?php foreach ($states as $uf): ?>
                    <option value="<?= e($uf) ?>" <?= strtoupper($val('state')) === $uf ? 'selected' : '' ?>><?= e($uf) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('state') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="contact"></i> Contato geral</h3>
    <div class="form-grid">
        <div>
            <label for="website">Site</label>
            <input type="text" id="website" name="website" value="<?= e($val('website')) ?>" maxlength="255" placeholder="https://...">
            <?= $err('website') ?>
        </div>
        <div>
            <label for="linkedin">LinkedIn</label>
            <input type="text" id="linkedin" name="linkedin" value="<?= e($val('linkedin')) ?>" maxlength="255">
            <?= $err('linkedin') ?>
        </div>
        <div>
            <label for="general_email">E-mail geral</label>
            <input type="email" id="general_email" name="general_email" value="<?= e($val('general_email')) ?>" maxlength="180">
            <?= $err('general_email') ?>
        </div>
        <div>
            <label for="general_phone">Telefone geral</label>
            <input type="text" id="general_phone" name="general_phone" value="<?= e($val('general_phone')) ?>" maxlength="40">
            <?= $err('general_phone') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="map-pin"></i> Atuação territorial</h3>
    <div class="check-grid">
        <label class="check-item">
            <input type="checkbox" name="operates_para" value="1" <?= $checked('operates_para') ?>>
            <span class="check-label">Atua no Pará</span>
        </label>
        <label class="check-item">
            <input type="checkbox" name="operates_carajas" value="1" <?= $checked('operates_carajas') ?>>
            <span class="check-label">Atua em Carajás</span>
        </label>
        <label class="check-item">
            <input type="checkbox" name="operates_parauapebas" value="1" <?= $checked('operates_parauapebas') ?>>
            <span class="check-label">Atua em Parauapebas</span>
        </label>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="target"></i> Informações estratégicas</h3>
    <div class="form-grid">
        <div>
            <label for="tax_regime_guess">Regime tributário provável</label>
            <select id="tax_regime_guess" name="tax_regime_guess">
                <option value="">— Selecione —</option>
                <?php foreach ($taxRegimes as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $val('tax_regime_guess') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('tax_regime_guess') ?>
        </div>
        <div>
            <label for="priority">Prioridade</label>
            <select id="priority" name="priority">
                <?php foreach ($priorities as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= strtoupper($val('priority', 'C')) === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('priority') ?>
        </div>
        <div>
            <label for="status">Status geral</label>
            <select id="status" name="status">
                <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $val('status', 'prospect') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
        </div>
        <div>
            <label for="source">Origem da indicação</label>
            <select id="source" name="source">
                <option value="">— Selecione —</option>
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
    </div>

    <div class="check-grid" style="margin-top:14px;">
        <label class="check-item">
            <input type="checkbox" name="has_cultural_sponsorship_history" value="1" <?= $checked('has_cultural_sponsorship_history') ?>>
            <span class="check-label">Histórico de patrocínio cultural</span>
        </label>
        <label class="check-item">
            <input type="checkbox" name="has_rouanet_history" value="1" <?= $checked('has_rouanet_history') ?>>
            <span class="check-label">Histórico de Lei Rouanet</span>
        </label>
        <label class="check-item">
            <input type="checkbox" name="has_esg_alignment" value="1" <?= $checked('has_esg_alignment') ?>>
            <span class="check-label">Aderência ESG</span>
        </label>
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
