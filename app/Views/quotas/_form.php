<?php
/**
 * Formulário compartilhado de Cota (cadastro e edição).
 *
 * Variáveis: $formAction, $submitLabel, $old, $errors, $statuses, $idealProfiles,
 * $pricingModes, $inventoryModes, $projects
 */
$old             = $old ?? [];
$errors          = $errors ?? [];
$statuses        = $statuses ?? [];
$idealProfiles   = $idealProfiles ?? [];
$pricingModes    = $pricingModes ?? [];
$inventoryModes  = $inventoryModes ?? [];
$projects        = $projects ?? [];

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
            <label for="incentive_project_id">Projeto incentivado *</label>
            <select id="incentive_project_id" name="incentive_project_id" required>
                <option value="">- Selecione -</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= (int) $project['id'] ?>" <?= (int) $val('incentive_project_id') === (int) $project['id'] ? 'selected' : '' ?>>
                        <?= e($project['label'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= $err('incentive_project_id') ?>
        </div>
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
            <small class="field-hint">Opcional em RANGE. Obrigatório em FIXED/FULL_PROJECT.</small>
            <?= $err('amount') ?>
        </div>
        <div>
            <label for="available_quantity">Quantidade disponível</label>
            <?php
            $availDisplay = array_key_exists('available_quantity', $old) && $old['available_quantity'] !== null && $old['available_quantity'] !== ''
                ? (string) $old['available_quantity']
                : ($val('inventory_mode') === 'UNSPECIFIED' ? '' : '0');
            ?>
            <input type="number" id="available_quantity" name="available_quantity" min="0" value="<?= e($availDisplay) ?>">
            <small class="field-hint">Pode ficar vazio se o inventário for UNSPECIFIED.</small>
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

    <h3 class="h3-card form-section-title"><i data-lucide="tags"></i> Catálogo V2.1 (opcional)</h3>
    <div class="form-grid">
        <div>
            <label for="catalog_ref_id">Ref. catálogo</label>
            <input type="text" id="catalog_ref_id" name="catalog_ref_id" value="<?= e($val('catalog_ref_id')) ?>" maxlength="40" placeholder="Ex.: INCENTIVA">
            <?= $err('catalog_ref_id') ?>
        </div>
        <div>
            <label for="catalog_version">Versão do catálogo</label>
            <input type="text" id="catalog_version" name="catalog_version" value="<?= e($val('catalog_version')) ?>" maxlength="20" placeholder="Ex.: 2026-V2.1">
            <?= $err('catalog_version') ?>
        </div>
        <div>
            <label for="pricing_mode">Modo de preço</label>
            <select id="pricing_mode" name="pricing_mode">
                <option value="">— Não informado —</option>
                <?php foreach ($pricingModes as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('pricing_mode') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('pricing_mode') ?>
        </div>
        <div>
            <label for="inventory_mode">Modo de inventário</label>
            <select id="inventory_mode" name="inventory_mode">
                <option value="">— Não informado —</option>
                <?php foreach ($inventoryModes as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $val('inventory_mode') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('inventory_mode') ?>
        </div>
        <div>
            <label for="min_amount">Valor mínimo (R$)</label>
            <input type="text" id="min_amount" name="min_amount" value="<?= e($val('min_amount')) ?>" placeholder="Ex.: 10000,00">
            <?= $err('min_amount') ?>
        </div>
        <div>
            <label for="max_amount">Valor máximo (R$)</label>
            <input type="text" id="max_amount" name="max_amount" value="<?= e($val('max_amount')) ?>" placeholder="Ex.: 50000,00">
            <?= $err('max_amount') ?>
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

    <div class="actions-row" style="margin-top:22px;gap:10px;">
        <button type="submit" class="btn btn-yellow"><i data-lucide="save"></i> <?= e($submitLabel) ?></button>
        <a href="<?= e(app_url('/quotas')) ?>" class="btn btn-outline">Cancelar</a>
    </div>
</form>
