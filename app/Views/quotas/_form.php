<?php
/**
 * Formulário compartilhado de Cota (cadastro e edição).
 *
 * Variáveis: $formAction, $submitLabel, $old, $errors, $statuses, $idealProfiles
 */
$old           = $old ?? [];
$errors        = $errors ?? [];
$statuses      = $statuses ?? [];
$idealProfiles = $idealProfiles ?? [];

$val = static fn (string $k, string $default = ''): string => (string) ($old[$k] ?? $default);
$err = static function (string $k) use ($errors): string {
    return isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
};
?>

<form method="post" action="<?= e($formAction) ?>" class="form-box" novalidate>
    <?= csrf_field() ?>

    <h3 class="h3-card form-section-title"><i data-lucide="badge-dollar-sign"></i> Dados principais</h3>
    <div class="form-grid">
        <div class="col-span-2">
            <label for="name">Nome da cota *</label>
            <input type="text" id="name" name="name" value="<?= e($val('name')) ?>" maxlength="120" required>
            <?= $err('name') ?>
        </div>
        <div>
            <label for="commercial_name">Nome comercial</label>
            <input type="text" id="commercial_name" name="commercial_name" value="<?= e($val('commercial_name')) ?>" maxlength="160">
            <?= $err('commercial_name') ?>
        </div>
        <div>
            <label for="amount">Valor (R$)</label>
            <input type="text" id="amount" name="amount" value="<?= e($val('amount')) ?>" placeholder="Ex.: 50000,00">
            <small class="field-hint">Opcional. Deixe vazio para valor flexível.</small>
            <?= $err('amount') ?>
        </div>
        <div>
            <label for="available_quantity">Quantidade disponível</label>
            <input type="number" id="available_quantity" name="available_quantity" min="0" value="<?= e($val('available_quantity', '0')) ?>">
            <?= $err('available_quantity') ?>
        </div>
        <div>
            <label for="reserved_quantity">Quantidade reservada</label>
            <input type="number" id="reserved_quantity" name="reserved_quantity" min="0" value="<?= e($val('reserved_quantity', '0')) ?>">
            <?= $err('reserved_quantity') ?>
        </div>
        <div>
            <label for="closed_quantity">Quantidade fechada</label>
            <input type="number" id="closed_quantity" name="closed_quantity" min="0" value="<?= e($val('closed_quantity', '0')) ?>">
            <?= $err('closed_quantity') ?>
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('status', 'disponivel') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('status') ?>
        </div>
        <div>
            <label for="display_order">Ordem de exibição</label>
            <input type="number" id="display_order" name="display_order" min="0" value="<?= e($val('display_order', '0')) ?>">
            <?= $err('display_order') ?>
        </div>
    </div>

    <h3 class="h3-card form-section-title"><i data-lucide="file-text"></i> Descrição</h3>
    <div class="form-grid">
        <div class="col-span-2">
            <label for="description">Descrição</label>
            <textarea id="description" name="description" rows="3"><?= e($val('description')) ?></textarea>
            <?= $err('description') ?>
        </div>
        <div>
            <label for="ideal_profile">Perfil indicado</label>
            <select id="ideal_profile" name="ideal_profile">
                <option value="">— Não informado —</option>
                <?php foreach ($idealProfiles as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('ideal_profile') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('ideal_profile') ?>
        </div>
    </div>

    <div style="margin-top:18px;">
        <label for="notes">Observações</label>
        <textarea id="notes" name="notes" rows="3"><?= e($val('notes')) ?></textarea>
        <?= $err('notes') ?>
    </div>

    <div class="actions-row" style="margin-top:22px;">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= e($submitLabel) ?></button>
    </div>
</form>
