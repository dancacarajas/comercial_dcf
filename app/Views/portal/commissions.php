<?php
$items = $items ?? [];
$filters = $filters ?? [];
$summary = $summary ?? [];
$projects = $projects ?? [];
$approvalStatuses = $approvalStatuses ?? [];
$paymentStatuses = $paymentStatuses ?? [];
$statusGroups = $statusGroups ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Portal do captador</span>
                <h1 class="h2-section">Minhas comissoes</h1>
                <p class="page-sub">Extrato somente leitura das suas comissoes por projeto, financeiro e patrocinador.</p>
            </div>
            <a class="btn btn-outline" href="<?= e(app_url('/portal')) ?>">
                <i data-lucide="arrow-left"></i> Carteira
            </a>
        </div>

        <div class="report-summary">
            <div class="report-metric report-metric-money">
                <span class="report-metric-label">Gerada</span>
                <strong class="report-metric-value"><?= e(money_br($summary['generated_total'] ?? 0)) ?></strong>
            </div>
            <div class="report-metric report-metric-money">
                <span class="report-metric-label">Aprovada</span>
                <strong class="report-metric-value"><?= e(money_br($summary['approved_total'] ?? 0)) ?></strong>
            </div>
            <div class="report-metric report-metric-money">
                <span class="report-metric-label">Pago</span>
                <strong class="report-metric-value"><?= e(money_br($summary['paid_total'] ?? 0)) ?></strong>
            </div>
            <div class="report-metric report-metric-money">
                <span class="report-metric-label">Saldo</span>
                <strong class="report-metric-value"><?= e(money_br($summary['balance_total'] ?? 0)) ?></strong>
            </div>
        </div>

        <form method="get" action="<?= e(app_url('/portal/commissions')) ?>" class="filter-box">
            <div class="filter-grid">
                <div class="filter-q">
                    <label for="q">Busca</label>
                    <input type="search" id="q" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Projeto, financeiro, patrocinador">
                </div>
                <div>
                    <label for="incentive_project_id">Projeto</label>
                    <select id="incentive_project_id" name="incentive_project_id">
                        <option value="">Todos</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) ($project['group_id'] ?? 0) ?>" <?= (int) ($filters['incentive_project_id'] ?? 0) === (int) ($project['group_id'] ?? 0) ? 'selected' : '' ?>><?= e($project['group_label'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status_group">Status</label>
                    <select id="status_group" name="status_group">
                        <option value="">Todos</option>
                        <?php foreach ($statusGroups as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= ($filters['status_group'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="payment_status">Pagamento</label>
                    <select id="payment_status" name="payment_status">
                        <option value="">Todos</option>
                        <?php foreach ($paymentStatuses as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= ($filters['payment_status'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="date_from">De</label>
                    <input type="date" id="date_from" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
                </div>
                <div>
                    <label for="date_to">Ate</label>
                    <input type="date" id="date_to" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
                </div>
            </div>

            <div class="filter-flags">
                <div class="filter-actions">
                    <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="filter"></i> Filtrar</button>
                    <a class="btn btn-sm btn-outline" href="<?= e(app_url('/portal/commissions')) ?>"><i data-lucide="x"></i> Limpar</a>
                </div>
            </div>
        </form>

        <p class="result-count"><?= $total ?> comissao(oes) encontrada(s).</p>

        <?php if ($items === []): ?>
            <div class="empty-state">
                <span class="card-icon"><i data-lucide="receipt"></i></span>
                <h3 class="h3-card">Nenhuma comissao encontrada</h3>
                <p>Ajuste os filtros ou aguarde novas comissoes calculadas para o seu cadastro.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Projeto</th>
                            <th>Financeiro / patrocinador</th>
                            <th>Valores</th>
                            <th>Status</th>
                            <th style="text-align:right;">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?= e($item['project_name'] ?? '-') ?></strong>
                                <span class="cell-sub"><?= e($item['attribution_type'] ?? '') ?></span>
                            </td>
                            <td>
                                <strong><?= e($item['financial_title'] ?? ('#' . (int) ($item['financial_entry_id'] ?? 0))) ?></strong>
                                <span class="cell-sub"><?= e($item['sponsor_name'] ?? ($item['company_name'] ?? '-')) ?></span>
                            </td>
                            <td>
                                <strong><?= e(money_br($item['capped_commission_amount'] ?? 0)) ?></strong>
                                <span class="cell-sub">Pago <?= e(money_br($item['payment_total_amount'] ?? 0)) ?> - saldo <?= e(money_br($item['payment_balance_amount'] ?? 0)) ?></span>
                            </td>
                            <td>
                                <span class="badge-status"><?= e($approvalStatuses[$item['approval_status'] ?? ''] ?? ($item['approval_status'] ?? '')) ?></span>
                                <span class="cell-sub"><?= e($paymentStatuses[$item['payment_status'] ?? ''] ?? ($item['payment_status'] ?? '')) ?></span>
                            </td>
                            <td>
                                <div class="actions-row" style="justify-content:flex-end;">
                                    <a class="btn btn-sm btn-outline" href="<?= e(app_url('/portal/commissions/' . (int) $item['id'])) ?>">
                                        <i data-lucide="eye"></i> Abrir
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="pagination" aria-label="Paginacao">
                    <span class="page-info">Pagina <?= $page ?> de <?= $pages ?></span>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
