<?php
/**
 * Shell comum dos relatórios gerenciais (Etapa 17 + visualizações analíticas).
 */
$title = $title ?? 'Relatório';
$description = $description ?? '';
$reportKey = $reportKey ?? 'executive';
$filters = $filters ?? [];
$filterErrors = $filterErrors ?? [];
$metrics = $metrics ?? [];
$tables = $tables ?? [];
$alerts = $alerts ?? [];
$visualizations = $visualizations ?? [];
$options = $options ?? [];
$reportKeys = $reportKeys ?? [];
$hasFilters = !empty($hasFilters);
$isEmpty = !empty($isEmpty);

$nav = [
    'executive'    => ['label' => 'Executivo', 'url' => app_url('/reports'), 'icon' => 'layout-dashboard'],
    'pipeline'     => ['label' => 'Funil', 'url' => app_url('/reports/pipeline'), 'icon' => 'git-branch'],
    'proposals'    => ['label' => 'Propostas', 'url' => app_url('/reports/proposals'), 'icon' => 'file-text'],
    'sponsors'     => ['label' => 'Patrocinadores', 'url' => app_url('/reports/sponsors'), 'icon' => 'handshake'],
    'financials'   => ['label' => 'Financeiro', 'url' => app_url('/reports/financials'), 'icon' => 'wallet'],
    'contracts'    => ['label' => 'Contratos', 'url' => app_url('/reports/contracts'), 'icon' => 'file-signature'],
    'counterparts' => ['label' => 'Contrapartidas', 'url' => app_url('/reports/counterparts'), 'icon' => 'package-check'],
    'dossiers'     => ['label' => 'Dossiês', 'url' => app_url('/reports/dossiers'), 'icon' => 'folder-check'],
    'tasks'        => ['label' => 'Tarefas', 'url' => app_url('/reports/tasks'), 'icon' => 'list-checks'],
    'leads'        => ['label' => 'Leads', 'url' => app_url('/reports/leads'), 'icon' => 'inbox'],
];

$canSnapshot = can('reports.snapshots');
$canPrint = can('reports.print');
$canSnapshotsList = can('reports.view');

$f = static fn (string $k): string => (string) ($filters[$k] ?? '');

$criticalAlerts = array_values(array_filter($alerts, static fn (array $a): bool => in_array($a['type'] ?? '', ['danger', 'warning'], true)));
$infoAlerts = array_values(array_filter($alerts, static fn (array $a): bool => ($a['type'] ?? 'info') === 'info'));
?>
<section class="section report-analytics-section">
    <div class="container">
        <div class="page-head report-index-head">
            <div>
                <span class="kicker kicker-dark">CRM · Indicadores gerenciais</span>
                <h1 class="h2-section"><?= e($title) ?></h1>
                <p class="page-sub"><?= e($description) ?></p>
            </div>
            <div class="report-actions actions-row">
                <?php if ($canPrint): ?>
                    <a href="<?= e(app_url('/reports/' . $reportKey . '/print?' . http_build_query(array_filter([
                        'period_start' => $f('period_start'),
                        'period_end' => $f('period_end'),
                        'responsible_user_id' => (int) ($filters['responsible_user_id'] ?? 0) ?: '',
                        'company_id' => (int) ($filters['company_id'] ?? 0) ?: '',
                        'sponsor_id' => (int) ($filters['sponsor_id'] ?? 0) ?: '',
                        'quota_id' => (int) ($filters['quota_id'] ?? 0) ?: '',
                        'status' => $f('status'),
                        'source' => $f('source'),
                        'only_pending' => !empty($filters['only_pending']) ? 1 : '',
                        'only_overdue' => !empty($filters['only_overdue']) ? 1 : '',
                    ])))) ?>" class="btn btn-ghost" target="_blank" rel="noopener"><i data-lucide="printer"></i> Imprimir</a>
                <?php endif; ?>
                <?php if ($canSnapshot): ?>
                    <details class="report-snapshot-inline">
                        <summary class="btn btn-yellow report-snapshot-trigger"><i data-lucide="camera"></i> Gerar snapshot</summary>
                        <form method="post" action="<?= e(app_url('/reports/snapshots')) ?>" class="card card-pad report-snapshot-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="report_key" value="<?= e($reportKey) ?>">
                            <input type="hidden" name="period_start" value="<?= e($f('period_start')) ?>">
                            <input type="hidden" name="period_end" value="<?= e($f('period_end')) ?>">
                            <input type="hidden" name="responsible_user_id" value="<?= (int) ($filters['responsible_user_id'] ?? 0) ?>">
                            <input type="hidden" name="company_id" value="<?= (int) ($filters['company_id'] ?? 0) ?>">
                            <input type="hidden" name="sponsor_id" value="<?= (int) ($filters['sponsor_id'] ?? 0) ?>">
                            <input type="hidden" name="quota_id" value="<?= (int) ($filters['quota_id'] ?? 0) ?>">
                            <input type="hidden" name="status" value="<?= e($f('status')) ?>">
                            <input type="hidden" name="source" value="<?= e($f('source')) ?>">
                            <input type="hidden" name="only_pending" value="<?= !empty($filters['only_pending']) ? '1' : '' ?>">
                            <input type="hidden" name="only_overdue" value="<?= !empty($filters['only_overdue']) ? '1' : '' ?>">
                            <div class="form-grid">
                                <div class="span-2">
                                    <label class="label-sm" for="snapshot_title">Título *</label>
                                    <input type="text" id="snapshot_title" name="title" class="input" required minlength="3" placeholder="Ex.: Executivo — <?= e(date('d/m/Y')) ?>">
                                </div>
                                <div class="span-2">
                                    <label class="label-sm" for="snapshot_description">Descrição</label>
                                    <input type="text" id="snapshot_description" name="description" class="input">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-yellow" style="margin-top:12px;"><i data-lucide="save"></i> Salvar snapshot</button>
                        </form>
                    </details>
                <?php endif; ?>
                <?php if ($canSnapshotsList): ?>
                    <a href="<?= e(app_url('/reports/snapshots')) ?>" class="btn btn-ghost"><i data-lucide="archive"></i> Snapshots</a>
                <?php endif; ?>
            </div>
        </div>

        <nav class="report-nav" aria-label="Relatórios">
            <?php foreach ($nav as $key => $item): ?>
                <a href="<?= e($item['url']) ?>" class="report-nav-link<?= $key === $reportKey ? ' is-active' : '' ?>">
                    <i data-lucide="<?= e($item['icon']) ?>"></i> <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php require __DIR__ . '/_filters.php'; ?>

        <?php foreach ($infoAlerts as $alert): ?>
            <div class="notice notice-info report-alert report-alert--compact">
                <p class="mb-0"><i data-lucide="info"></i> <?= e((string) ($alert['message'] ?? '')) ?></p>
            </div>
        <?php endforeach; ?>

        <?php require __DIR__ . '/_metric_cards.php'; ?>

        <?php require __DIR__ . '/_charts.php'; ?>

        <article class="dcx-chart-card dcx-chart-card--wide dcx-critical-card">
            <h3 class="dcx-chart-title"><i data-lucide="alert-triangle" aria-hidden="true"></i> Pendências críticas</h3>
            <?php if ($criticalAlerts === []): ?>
                <div class="dcx-empty-visual dcx-empty-visual--ok">
                    <i data-lucide="check-circle" aria-hidden="true"></i>
                    <p>Nenhuma pendência crítica no escopo atual.</p>
                </div>
            <?php else: ?>
                <ul class="dcx-critical-list">
                    <?php foreach ($criticalAlerts as $alert): ?>
                        <li class="dcx-critical-item dcx-critical-item--<?= e((string) ($alert['type'] ?? 'warning')) ?>">
                            <i data-lucide="alert-circle" aria-hidden="true"></i>
                            <span><?= e((string) ($alert['message'] ?? '')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <?php require __DIR__ . '/_tables.php'; ?>
    </div>
</section>
