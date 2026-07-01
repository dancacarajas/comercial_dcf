<?php
$items = $items ?? [];
$filters = $filters ?? [];
$projects = $projects ?? [];
$statuses = $statuses ?? [];
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Etapa 20A</span>
                <h1 class="h2-section">Pools de Comissao</h1>
                <p class="page-sub">Teto da rubrica de captacao, consumo e saldo por projeto incentivado.</p>
            </div>
            <a href="<?= e(app_url('/commissions')) ?>" class="btn btn-sm btn-outline"><i data-lucide="list"></i> Comissoes</a>
        </div>

        <form method="get" class="filters-bar">
            <div>
                <label>Projeto</label>
                <select name="incentive_project_id">
                    <option value="">Todos</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= (int) $project['id'] ?>" <?= (int) ($filters['incentive_project_id'] ?? 0) === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['label'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filters-actions">
                <button class="btn btn-sm btn-yellow" type="submit"><i data-lucide="search"></i> Filtrar</button>
                <a href="<?= e(app_url('/commissions/pools')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Projeto</th>
                        <th>Aprovado</th>
                        <th>Rubrica</th>
                        <th>Fator</th>
                        <th>Gerado</th>
                        <th>Saldo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['project_name'] ?? '') ?> <?php if (!empty($item['edition_year'])): ?><small>(<?= (int) $item['edition_year'] ?>)</small><?php endif; ?></td>
                            <td><?= e(money_br($item['approved_total_amount_snapshot'] ?? 0)) ?></td>
                            <td><?= e(money_br($item['capture_commission_budget_snapshot'] ?? 0)) ?></td>
                            <td><?= e(number_format(((float) ($item['commission_factor_snapshot'] ?? 0)) * 100, 6, ',', '.')) ?>%</td>
                            <td><?= e(money_br($item['commission_generated_total'] ?? 0)) ?></td>
                            <td><strong><?= e(money_br($item['commission_available_balance'] ?? 0)) ?></strong></td>
                            <td><span class="pill"><?= e($statuses[$item['status'] ?? ''] ?? ($item['status'] ?? '')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($items === []): ?>
                        <tr><td colspan="7" class="text-muted">Nenhum pool encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
