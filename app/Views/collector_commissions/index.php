<?php
$items = $items ?? [];
$filters = $filters ?? [];
$projects = $projects ?? [];
$collectors = $collectors ?? [];
$calculationStatuses = $calculationStatuses ?? [];
$approvalStatuses = $approvalStatuses ?? [];
$paymentStatuses = $paymentStatuses ?? [];
$statusGroups = $statusGroups ?? [];
$attributionTypes = $attributionTypes ?? [];
$summary = $summary ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$cleanFilters = array_filter($filters, static fn ($v): bool => $v !== '' && $v !== 0 && $v !== null);
$query = http_build_query($cleanFilters);
?>
<style>
.commission-summary-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.commission-summary-grid .metric-card {
    min-height: 62px;
    padding: 10px 12px;
}
.commission-summary-grid .metric-label {
    font-size: 10px;
}
.commission-summary-grid .metric-value {
    font-size: 16px;
}
.commission-filter-panel {
    border: 1px solid #e6e1d8;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 14px;
    background: #fff;
}
.commission-filter-grid {
    display: grid;
    grid-template-columns: minmax(220px, 2fr) minmax(180px, 1.5fr) minmax(180px, 1.5fr) repeat(4, minmax(130px, 1fr)) 120px 120px auto;
    gap: 8px;
    align-items: end;
}
.commission-filter-grid label {
    display: block;
    margin-bottom: 3px;
    font-size: 11px;
    font-weight: 700;
}
.commission-filter-grid input,
.commission-filter-grid select {
    width: 100%;
    min-height: 34px;
    padding: 6px 8px;
    font-size: 12px;
}
.commission-filter-grid .filters-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    white-space: nowrap;
}
@media (max-width: 1180px) {
    .commission-filter-grid {
        grid-template-columns: repeat(4, minmax(150px, 1fr));
    }
    .commission-filter-grid .filters-actions {
        grid-column: span 4;
    }
}
@media (max-width: 720px) {
    .commission-summary-grid,
    .commission-filter-grid {
        grid-template-columns: 1fr 1fr;
    }
    .commission-filter-grid .filters-actions {
        grid-column: span 2;
    }
}
</style>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Etapa 20C</span>
                <h1 class="h2-section">Comissoes dos Captadores</h1>
                <p class="page-sub">Consolidacao interna de comissoes por projeto, captador, financeiro e status.</p>
            </div>
            <div class="actions-row">
                <a href="<?= e(app_url('/commissions/dashboard' . ($query !== '' ? '?' . $query : ''))) ?>" class="btn btn-sm btn-outline"><i data-lucide="layout-dashboard"></i> Dashboard</a>
                <a href="<?= e(app_url('/commissions/export' . ($query !== '' ? '?' . $query : ''))) ?>" class="btn btn-sm btn-outline"><i data-lucide="download"></i> CSV</a>
                <a href="<?= e(app_url('/commissions/pools')) ?>" class="btn btn-sm btn-outline"><i data-lucide="gauge"></i> Pools</a>
            </div>
        </div>

        <div class="commission-summary-grid">
            <div class="metric-card"><span class="metric-label">Gerada</span><strong class="metric-value"><?= e(money_br($summary['generated_total'] ?? 0)) ?></strong></div>
            <div class="metric-card"><span class="metric-label">Aprovada</span><strong class="metric-value"><?= e(money_br($summary['approved_total'] ?? 0)) ?></strong></div>
            <div class="metric-card"><span class="metric-label">A pagar</span><strong class="metric-value"><?= e(money_br($summary['payable_total'] ?? 0)) ?></strong></div>
            <div class="metric-card"><span class="metric-label">Pago</span><strong class="metric-value"><?= e(money_br($summary['paid_total'] ?? 0)) ?></strong></div>
            <div class="metric-card"><span class="metric-label">Saldo pendente</span><strong class="metric-value"><?= e(money_br($summary['balance_total'] ?? 0)) ?></strong></div>
        </div>

        <form method="get" class="commission-filter-panel">
            <div class="commission-filter-grid">
            <div>
                <label>Busca</label>
                <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Projeto, captador, financeiro">
            </div>
            <div>
                <label>Projeto</label>
                <select name="incentive_project_id">
                    <option value="">Todos</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= (int) $project['id'] ?>" <?= (int) ($filters['incentive_project_id'] ?? 0) === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['label'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Captador</label>
                <select name="collector_id">
                    <option value="">Todos</option>
                    <?php foreach ($collectors as $collector): ?>
                        <option value="<?= (int) $collector['id'] ?>" <?= (int) ($filters['collector_id'] ?? 0) === (int) $collector['id'] ? 'selected' : '' ?>><?= e($collector['label'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Status gerencial</label>
                <select name="status_group">
                    <option value="">Todos</option>
                    <?php foreach ($statusGroups as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= ($filters['status_group'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Calculo</label>
                <select name="calculation_status">
                    <option value="">Todos</option>
                    <?php foreach ($calculationStatuses as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= ($filters['calculation_status'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Aprovacao</label>
                <select name="approval_status">
                    <option value="">Todas</option>
                    <?php foreach ($approvalStatuses as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= ($filters['approval_status'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Pagamento</label>
                <select name="payment_status">
                    <option value="">Todos</option>
                    <?php foreach ($paymentStatuses as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= ($filters['payment_status'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Tipo</label>
                <select name="attribution_type">
                    <option value="">Todos</option>
                    <?php foreach ($attributionTypes as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= ($filters['attribution_type'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>De</label>
                <input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div>
                <label>Ate</label>
                <input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="filters-actions">
                <button class="btn btn-sm btn-yellow" type="submit"><i data-lucide="search"></i> Filtrar</button>
                <a href="<?= e(app_url('/commissions')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Projeto</th>
                        <th>Captador</th>
                        <th>Financeiro</th>
                        <th>Recebido</th>
                        <th>Comissao</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>#<?= (int) $item['id'] ?></td>
                            <td><?= e($item['project_name'] ?? '') ?></td>
                            <td><?= e($item['collector_name'] ?? '') ?><br><small><?= e($item['collector_code'] ?? '') ?></small></td>
                            <td><?php if (!empty($item['financial_entry_id'])): ?><a href="<?= e(app_url('/financials/' . (int) $item['financial_entry_id'])) ?>"><?= e($item['financial_title'] ?? ('#' . (int) $item['financial_entry_id'])) ?></a><?php endif; ?></td>
                            <td><?= e(money_br($item['financial_received_amount'] ?? 0)) ?></td>
                            <td><strong><?= e(money_br($item['capped_commission_amount'] ?? 0)) ?></strong><br><small>bruta <?= e(money_br($item['gross_commission_amount'] ?? 0)) ?></small></td>
                            <td>
                                <span class="pill"><?= e($calculationStatuses[$item['calculation_status'] ?? ''] ?? ($item['calculation_status'] ?? '')) ?></span>
                                <span class="pill"><?= e($approvalStatuses[$item['approval_status'] ?? ''] ?? ($item['approval_status'] ?? '')) ?></span>
                                <span class="pill"><?= e($paymentStatuses[$item['payment_status'] ?? ''] ?? ($item['payment_status'] ?? '')) ?></span>
                            </td>
                            <td><a href="<?= e(app_url('/commissions/' . (int) $item['id'])) ?>" class="btn btn-sm btn-outline">Abrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($items === []): ?>
                        <tr><td colspan="8" class="text-muted">Nenhuma comissao calculada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="page-sub">Total: <?= $total ?> registro(s). Pagina <?= $page ?> de <?= $pages ?>.</p>
    </div>
</section>
