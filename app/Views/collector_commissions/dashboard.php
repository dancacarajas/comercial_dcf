<?php
$filters = $filters ?? [];
$projects = $projects ?? [];
$collectors = $collectors ?? [];
$statusGroups = $statusGroups ?? [];
$summary = $summary ?? [];
$byProject = $byProject ?? [];
$byCollector = $byCollector ?? [];
$byFinancial = $byFinancial ?? [];
$byStatus = $byStatus ?? [];
$alerts = $alerts ?? [];
$cleanFilters = array_filter($filters, static fn ($v): bool => $v !== '' && $v !== 0 && $v !== null);
$query = http_build_query($cleanFilters);
$statusLabel = static function (string $approval, string $payment): string {
    if ($approval === 'bloqueada') {
        return 'Bloqueada';
    }
    if ($approval === 'pendente_aprovacao') {
        return 'Pendente';
    }
    return match ($payment) {
        'a_pagar' => 'A pagar',
        'parcialmente_pago' => 'Parcialmente paga',
        'pago' => 'Paga',
        default => $approval !== '' ? $approval : $payment,
    };
};
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Etapa 20C</span>
                <h1 class="h2-section">Dashboard de Comissoes</h1>
                <p class="page-sub">Visao interna por projeto, captador, financeiro, status e alertas operacionais.</p>
            </div>
            <div class="actions-row">
                <a href="<?= e(app_url('/commissions' . ($query !== '' ? '?' . $query : ''))) ?>" class="btn btn-sm btn-outline"><i data-lucide="list"></i> Lista</a>
                <a href="<?= e(app_url('/commissions/export' . ($query !== '' ? '?' . $query : ''))) ?>" class="btn btn-sm btn-outline"><i data-lucide="download"></i> CSV</a>
                <a href="<?= e(app_url('/commissions/pools')) ?>" class="btn btn-sm btn-outline"><i data-lucide="gauge"></i> Pools</a>
            </div>
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
                <label>Status</label>
                <select name="status_group">
                    <option value="">Todos</option>
                    <?php foreach ($statusGroups as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= ($filters['status_group'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
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
                <a href="<?= e(app_url('/commissions/dashboard')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
        </form>

        <div class="card-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:18px 0;">
            <div class="metric-card"><span class="metric-label">Registros</span><strong class="metric-value"><?= (int) ($summary['total_count'] ?? 0) ?></strong></div>
            <div class="metric-card"><span class="metric-label">Recebido</span><strong class="metric-value"><?= e(money_br($summary['received_total'] ?? 0)) ?></strong></div>
            <div class="metric-card"><span class="metric-label">Gerado</span><strong class="metric-value"><?= e(money_br($summary['generated_total'] ?? 0)) ?></strong></div>
            <div class="metric-card"><span class="metric-label">Aprovado</span><strong class="metric-value"><?= e(money_br($summary['approved_total'] ?? 0)) ?></strong></div>
            <div class="metric-card"><span class="metric-label">Pago</span><strong class="metric-value"><?= e(money_br($summary['paid_total'] ?? 0)) ?></strong></div>
            <div class="metric-card"><span class="metric-label">Saldo</span><strong class="metric-value"><?= e(money_br($summary['balance_total'] ?? 0)) ?></strong></div>
        </div>

        <div class="card" style="margin-bottom:18px;">
            <h2 class="h3-card">Alertas operacionais</h2>
            <div class="card-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <div class="metric-card"><span class="metric-label">Financeiro cancelado com comissao paga</span><strong class="metric-value"><?= (int) ($alerts['financial_cancelled_with_paid_commission'] ?? 0) ?></strong></div>
                <div class="metric-card"><span class="metric-label">Pagamento sem comprovante valido</span><strong class="metric-value"><?= (int) ($alerts['payment_without_valid_proof'] ?? 0) ?></strong></div>
                <div class="metric-card"><span class="metric-label">Rateios bloqueados por comissao</span><strong class="metric-value"><?= (int) ($alerts['locked_shared_deals'] ?? 0) ?></strong></div>
            </div>
        </div>

        <div class="detail-grid">
            <div class="card">
                <h2 class="h3-card">Por projeto</h2>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Projeto</th><th>Gerado</th><th>Pago</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($byProject as $row): ?>
                        <tr>
                            <td><?= e($row['group_label'] ?? '') ?><br><small><?= (int) ($row['total_count'] ?? 0) ?> comissao(oes)</small></td>
                            <td><?= e(money_br($row['generated_total'] ?? 0)) ?></td>
                            <td><?= e(money_br($row['paid_total'] ?? 0)) ?></td>
                            <td><a href="<?= e(app_url('/commissions?incentive_project_id=' . (int) ($row['group_id'] ?? 0))) ?>" class="btn btn-xs btn-outline">Abrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($byProject === []): ?><tr><td colspan="4" class="text-muted">Sem dados.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>

            <div class="card">
                <h2 class="h3-card">Por captador</h2>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Captador</th><th>Gerado</th><th>Pago</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($byCollector as $row): ?>
                        <tr>
                            <td><?= e($row['group_label'] ?? '') ?><br><small><?= (int) ($row['total_count'] ?? 0) ?> comissao(oes)</small></td>
                            <td><?= e(money_br($row['generated_total'] ?? 0)) ?></td>
                            <td><?= e(money_br($row['paid_total'] ?? 0)) ?></td>
                            <td><a href="<?= e(app_url('/commissions?collector_id=' . (int) ($row['group_id'] ?? 0))) ?>" class="btn btn-xs btn-outline">Abrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($byCollector === []): ?><tr><td colspan="4" class="text-muted">Sem dados.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>
        </div>

        <div class="detail-grid" style="margin-top:18px;">
            <div class="card">
                <h2 class="h3-card">Por financeiro</h2>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Financeiro</th><th>Gerado</th><th>Pago</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($byFinancial as $row): ?>
                        <tr>
                            <td><?= e($row['group_label'] ?? '') ?><br><small><?= e($row['financial_status'] ?? '') ?></small></td>
                            <td><?= e(money_br($row['generated_total'] ?? 0)) ?></td>
                            <td><?= e(money_br($row['paid_total'] ?? 0)) ?></td>
                            <td><a href="<?= e(app_url('/commissions?financial_entry_id=' . (int) ($row['group_id'] ?? 0))) ?>" class="btn btn-xs btn-outline">Abrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($byFinancial === []): ?><tr><td colspan="4" class="text-muted">Sem dados.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>

            <div class="card">
                <h2 class="h3-card">Por status</h2>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Status</th><th>Gerado</th><th>Pago</th><th>Saldo</th></tr></thead>
                    <tbody>
                    <?php foreach ($byStatus as $row): ?>
                        <tr>
                            <td><?= e($statusLabel((string) ($row['approval_status'] ?? ''), (string) ($row['payment_status'] ?? ''))) ?><br><small><?= (int) ($row['total_count'] ?? 0) ?> registro(s)</small></td>
                            <td><?= e(money_br($row['generated_total'] ?? 0)) ?></td>
                            <td><?= e(money_br($row['paid_total'] ?? 0)) ?></td>
                            <td><?= e(money_br($row['balance_total'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($byStatus === []): ?><tr><td colspan="4" class="text-muted">Sem dados.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
</section>
