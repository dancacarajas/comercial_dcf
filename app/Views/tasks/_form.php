<?php
/**
 * Formulário compartilhado de Tarefa (cadastro e edição).
 *
 * Variáveis: $formAction, $submitLabel, $old, $errors, $types, $priorities,
 * $statuses, $companies, $companyContacts, $companyOpps, $owners
 */
$old             = $old ?? [];
$errors          = $errors ?? [];
$types           = $types ?? [];
$priorities      = $priorities ?? [];
$statuses        = $statuses ?? [];
$companies       = $companies ?? [];
$companyContacts = $companyContacts ?? [];
$companyOpps     = $companyOpps ?? [];
$owners          = $owners ?? [];

$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
?>

<form method="post" action="<?= e($formAction) ?>" class="form-box" novalidate>
    <?= csrf_field() ?>

    <h3 class="h3-card form-section-title"><i data-lucide="check-square"></i> Dados principais</h3>
    <div class="form-grid">
        <div class="col-span-2">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" value="<?= e($val('title')) ?>" maxlength="180" required>
            <?= $err('title') ?>
        </div>
        <div>
            <label for="type">Tipo</label>
            <select id="type" name="type">
                <?php foreach ($types as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('type', 'follow_up') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('type') ?>
        </div>
        <div class="col-span-2">
            <label for="description">Descrição</label>
            <textarea id="description" name="description" rows="3"><?= e($val('description')) ?></textarea>
            <?= $err('description') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="link"></i> Vínculos</h3>
    <div class="form-grid">
        <div>
            <label for="company_id">Empresa</label>
            <select id="company_id" name="company_id">
                <option value="">— Sem empresa —</option>
                <?php foreach ($companies as $co): ?>
                    <option value="<?= (int) $co['id'] ?>" <?= (int) $val('company_id') === (int) $co['id'] ? 'selected' : '' ?>>
                        <?= e($co['name']) ?><?= !empty($co['archived_at']) ? ' (arquivada)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="field-hint">Salve para atualizar contatos e oportunidades da empresa.</small>
            <?= $err('company_id') ?>
        </div>
        <div>
            <label for="contact_id">Contato</label>
            <select id="contact_id" name="contact_id">
                <option value="">— Sem contato —</option>
                <?php foreach ($companyContacts as $ct): ?>
                    <option value="<?= (int) $ct['id'] ?>" <?= (int) $val('contact_id') === (int) $ct['id'] ? 'selected' : '' ?>><?= e($ct['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('contact_id') ?>
        </div>
        <div>
            <label for="opportunity_id">Oportunidade</label>
            <select id="opportunity_id" name="opportunity_id">
                <option value="">— Sem oportunidade —</option>
                <?php foreach ($companyOpps as $op): ?>
                    <option value="<?= (int) $op['id'] ?>" <?= (int) $val('opportunity_id') === (int) $op['id'] ? 'selected' : '' ?>><?= e($op['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="field-hint">Ao vincular uma oportunidade, empresa e contato são preenchidos automaticamente.</small>
            <?= $err('opportunity_id') ?>
        </div>
        <div>
            <label for="assigned_user_id">Responsável interno</label>
            <select id="assigned_user_id" name="assigned_user_id">
                <option value="">— Sem responsável —</option>
                <?php foreach ($owners as $o): ?>
                    <option value="<?= (int) $o['id'] ?>" <?= (int) $val('assigned_user_id') === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('assigned_user_id') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="calendar-clock"></i> Prazo e classificação</h3>
    <div class="form-grid">
        <div>
            <label for="due_date">Data de vencimento</label>
            <input type="date" id="due_date" name="due_date" value="<?= e($val('due_date')) ?>">
            <?= $err('due_date') ?>
        </div>
        <div>
            <label for="due_time">Hora</label>
            <input type="time" id="due_time" name="due_time" value="<?= e(substr($val('due_time'), 0, 5)) ?>">
            <?= $err('due_time') ?>
        </div>
        <div>
            <label for="priority">Prioridade</label>
            <select id="priority" name="priority">
                <?php foreach ($priorities as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('priority', 'normal') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('priority') ?>
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'pendente') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <small class="field-hint">Ao marcar "Concluída", a data de conclusão é registrada automaticamente.</small>
            <?= $err('status') ?>
        </div>
    </div>

    <div style="margin-top:18px;">
        <label for="result">Resultado</label>
        <textarea id="result" name="result" rows="3" placeholder="Resultado da ação (opcional)"><?= e($val('result')) ?></textarea>
        <?= $err('result') ?>
    </div>

    <div class="actions-row" style="margin-top:22px;">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= e($submitLabel) ?></button>
    </div>
</form>
