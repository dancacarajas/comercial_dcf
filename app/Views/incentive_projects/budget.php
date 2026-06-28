<?php
$project = $project ?? [];
$budgetItems = $budgetItems ?? [];
$budgetTotal = (float) ($budgetTotal ?? 0);
$commissionBudget = (float) ($commissionBudget ?? 0);
$canEdit = !empty($canEdit);
$id = (int) ($project['id'] ?? 0);
?>
<section class="section"><div class="container">
<div class="page-head">
    <div>
        <span class="kicker kicker-dark">Orçamento do projeto</span>
        <h1 class="h2-section"><?= e($project['project_name'] ?? '') ?></h1>
        <p class="page-sub">Total das rubricas: <strong><?= money_br($budgetTotal) ?></strong> · Rubrica de captação: <strong><?= money_br($commissionBudget) ?></strong></p>
    </div>
    <a href="<?= e(app_url('/projects/' . $id)) ?>" class="btn btn-sm btn-outline">Voltar</a>
</div>

<div class="form-card" style="margin-bottom:18px;">
    <h2 class="h3-section">Rubricas cadastradas (<?= count($budgetItems) ?>)</h2>
    <?php if ($budgetItems === []): ?>
        <p class="page-sub">Nenhuma rubrica cadastrada ainda.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Item</th><th>Rubrica</th><th>Produto</th><th>Etapa</th><th>Valor</th><th>Comissão?</th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($budgetItems as $bi): ?>
                <tr>
                    <td><?= e($bi['item_number'] ?? '—') ?></td>
                    <td><strong><?= e($bi['budget_item_name'] ?? '') ?></strong></td>
                    <td><?= e($bi['product'] ?? '—') ?></td>
                    <td><?= e($bi['stage'] ?? '—') ?></td>
                    <td><?= money_br($bi['requested_amount'] ?? null) ?></td>
                    <td><?= !empty($bi['is_capture_commission_item']) ? 'Sim' : '—' ?></td>
                    <?php if ($canEdit): ?>
                    <td style="text-align:right;">
                        <form method="post" action="<?= e(app_url('/projects/' . $id . '/budget/' . (int) $bi['id'] . '/archive')) ?>" onsubmit="return confirm('Arquivar esta rubrica?');">
                            <?= csrf_field() ?><button type="submit" class="btn btn-sm btn-outline">Arquivar</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>

<?php if ($canEdit): ?>
<div class="form-card">
    <h2 class="h3-section">Nova rubrica</h2>
    <form method="post" action="<?= e(app_url('/projects/' . $id . '/budget')) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div><label for="item_number">Item nº</label><input type="number" id="item_number" name="item_number" class="input"></div>
            <div class="form-grid-full"><label for="budget_item_name">Nome da rubrica *</label><input type="text" id="budget_item_name" name="budget_item_name" class="input" required></div>
            <div><label for="product">Produto</label><input type="text" id="product" name="product" class="input"></div>
            <div><label for="stage">Etapa</label><input type="text" id="stage" name="stage" class="input"></div>
            <div><label for="unit">Unidade</label><input type="text" id="unit" name="unit" class="input"></div>
            <div><label for="requested_amount">Valor solicitado (R$)</label><input type="text" id="requested_amount" name="requested_amount" class="input" placeholder="42768,00"></div>
            <div><label class="checkbox-label"><input type="checkbox" name="is_capture_commission_item" value="1"> Rubrica de remuneração de captação</label></div>
            <div class="form-grid-full"><label for="notes">Observações</label><textarea id="notes" name="notes" class="input" rows="2"></textarea></div>
        </div>
        <div class="actions-row" style="margin-top:14px;"><button type="submit" class="btn btn-yellow">Adicionar rubrica</button></div>
    </form>
</div>
<?php endif; ?>
</div></section>
