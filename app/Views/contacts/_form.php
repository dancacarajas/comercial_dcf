<?php
/**
 * Formulário compartilhado de Contato (cadastro e edição).
 *
 * Variáveis: $formAction, $submitLabel, $old, $errors,
 * $companies, $departments, $decisionLevels, $influenceLevels,
 * $channels, $statuses, $owners
 */
$old             = $old ?? [];
$errors          = $errors ?? [];
$companies       = $companies ?? [];
$departments     = $departments ?? [];
$decisionLevels  = $decisionLevels ?? [];
$influenceLevels = $influenceLevels ?? [];
$channels        = $channels ?? [];
$statuses        = $statuses ?? [];
$owners          = $owners ?? [];

$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
// datetime-local precisa de "YYYY-MM-DDTHH:MM"
$dtLocal = static function (string $k) use ($old): string {
    $v = (string) ($old[$k] ?? '');
    if ($v === '') { return ''; }
    $ts = strtotime(str_replace('T', ' ', $v));
    return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
};

// Verifica se a empresa selecionada está arquivada (para aviso).
$selectedCompanyId = (int) $val('company_id');
$selectedArchived  = false;
foreach ($companies as $co) {
    if ((int) $co['id'] === $selectedCompanyId && !empty($co['archived_at'])) {
        $selectedArchived = true;
        break;
    }
}
?>

<form method="post" action="<?= e($formAction) ?>" class="form-box" novalidate>
    <?= csrf_field() ?>

    <h3 class="h3-card form-section-title"><i data-lucide="building-2"></i> Empresa vinculada</h3>
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
    </div>
    <?php if ($selectedArchived): ?>
        <div class="notice notice-warn" style="margin-top:12px;">
            <p class="mb-0"><i data-lucide="alert-triangle"></i> Atenção: a empresa selecionada está <strong>arquivada</strong>. O contato será vinculado mesmo assim.</p>
        </div>
    <?php endif; ?>

    <h3 class="h3-card form-section-title"><i data-lucide="user"></i> Dados principais</h3>
    <div class="form-grid">
        <div>
            <label for="name">Nome *</label>
            <input type="text" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="180" required>
            <?= $err('name') ?>
        </div>
        <div>
            <label for="position_title">Cargo</label>
            <input type="text" id="position_title" name="position_title" value="<?= e($val('position_title')) ?>" maxlength="160">
            <?= $err('position_title') ?>
        </div>
        <div>
            <label for="department">Área</label>
            <select id="department" name="department">
                <option value="">— Selecione —</option>
                <?php foreach ($departments as $dep): ?>
                    <option value="<?= e($dep) ?>" <?= $val('department') === $dep ? 'selected' : '' ?>><?= e(ucfirst($dep)) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('department') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="contact"></i> Canais de contato</h3>
    <div class="form-grid">
        <div>
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= e($val('email')) ?>" maxlength="180">
            <?= $err('email') ?>
        </div>
        <div>
            <label for="whatsapp">WhatsApp</label>
            <input type="text" id="whatsapp" name="whatsapp" value="<?= e($val('whatsapp')) ?>" maxlength="40" placeholder="Somente números (com DDD)">
            <?= $err('whatsapp') ?>
        </div>
        <div>
            <label for="phone">Telefone</label>
            <input type="text" id="phone" name="phone" value="<?= e($val('phone')) ?>" maxlength="40">
            <?= $err('phone') ?>
        </div>
        <div>
            <label for="linkedin">LinkedIn</label>
            <input type="text" id="linkedin" name="linkedin" value="<?= e($val('linkedin')) ?>" maxlength="255" placeholder="linkedin.com/in/usuario">
            <?= $err('linkedin') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="sliders-horizontal"></i> Classificação</h3>
    <div class="form-grid">
        <div>
            <label for="decision_level">Nível de decisão</label>
            <select id="decision_level" name="decision_level">
                <?php foreach ($decisionLevels as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('decision_level', 'nao_informado') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('decision_level') ?>
        </div>
        <div>
            <label for="influence_level">Influência</label>
            <select id="influence_level" name="influence_level">
                <?php foreach ($influenceLevels as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('influence_level', 'media') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('influence_level') ?>
        </div>
        <div>
            <label for="preferred_channel">Canal preferencial</label>
            <select id="preferred_channel" name="preferred_channel">
                <?php foreach ($channels as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('preferred_channel', 'nao_informado') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('preferred_channel') ?>
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'ativo') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
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

    <h3 class="h3-card form-section-title"><i data-lucide="calendar-clock"></i> Agenda</h3>
    <div class="form-grid">
        <div>
            <label for="last_interaction_at">Última interação</label>
            <input type="datetime-local" id="last_interaction_at" name="last_interaction_at" value="<?= e($dtLocal('last_interaction_at')) ?>">
            <?= $err('last_interaction_at') ?>
        </div>
        <div>
            <label for="next_contact_at">Próximo contato</label>
            <input type="datetime-local" id="next_contact_at" name="next_contact_at" value="<?= e($dtLocal('next_contact_at')) ?>">
            <?= $err('next_contact_at') ?>
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
