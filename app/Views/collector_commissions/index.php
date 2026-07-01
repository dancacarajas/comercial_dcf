<?php
$items = $items ?? [];
$filters = $filters ?? [];
$projects = $projects ?? [];
$collectors = $collectors ?? [];
$calculationStatuses = $calculationStatuses ?? [];
$approvalStatuses = $approvalStatuses ?? [];
$paymentStatuses = $paymentStatuses ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Etapa 20A</span>
                <h1 class="h2-section">Comissoes dos Captadores</h1>
                <p class="page-sub">Motor de calculo proporcional por projeto. Pagamento e portal ficam para etapas futuras.</p>
            </div>
            <a href="<?= e(app_url('/commissions/pools')) ?>" class="btn btn-sm btn-outline"><i data-lucide="gauge"></i> Pools</a>
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
            <div class="filters-actions">
                <button class="btn btn-sm btn-yellow" type="submit"><i data-lucide="search"></i> Filtrar</button>
                <a href="<?= e(app_url('/commissions')) ?>" class="btn btn-sm btn-outline">Limpar</a>
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
