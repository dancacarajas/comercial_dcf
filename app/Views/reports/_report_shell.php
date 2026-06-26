<?php
/**
 * Shell comum dos relatórios gerenciais (Etapa 17).
 *
 * Variáveis: title, description, reportKey, filters, filterErrors, metrics, tables, alerts, rankings, options, hasFilters, isEmpty
 */
$title = $title ?? 'Relatório';
$description = $description ?? '';
$reportKey = $reportKey ?? 'executive';
$filters = $filters ?? [];
$filterErrors = $filterErrors ?? [];
$metrics = $metrics ?? [];
$tables = $tables ?? [];
$alerts = $alerts ?? [];
$rankings = $rankings ?? [];
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
?>
<section class="section">
    <div class="container">
        <div class="page-head report-index-head">
            <div>
                <span class="kicker kicker-dark">CRM · Indicadores gerenciais</span>
                <h1 class="h2-section"><?= e($title) ?></h1>
                <p class="page-sub"><?= e($description) ?></p>
            </div>
            <div class="report-actions actions-row">
                <?php if ($canSnapshotsList): ?>
                    <a href="<?= e(app_url('/reports/snapshots')) ?>" class="btn btn-ghost"><i data-lucide="camera"></i> Snapshots</a>
                <?php endif; ?>
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
            </div>
        </div>

        <div class="notice notice-info report-alert" style="margin-bottom:16px;">
            <p class="mb-0"><i data-lucide="info"></i> Relatórios externos, BI, exportação Excel global, envio automático e integrações serão tratados em etapas futuras.</p>
        </div>

        <nav class="report-nav" aria-label="Relatórios">
            <?php foreach ($nav as $key => $item): ?>
                <a href="<?= e($item['url']) ?>" class="report-nav-link<?= $key === $reportKey ? ' is-active' : '' ?>">
                    <i data-lucide="<?= e($item['icon']) ?>"></i> <?= e($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($hasFilters): ?>
            <div class="notice notice-warn report-alert">
                <p class="mb-0"><i data-lucide="filter"></i> Filtros ativos aplicados ao relatório.</p>
            </div>
        <?php endif; ?>

        <?php require __DIR__ . '/_filters.php'; ?>

        <?php foreach ($alerts as $alert): ?>
            <?php $type = (string) ($alert['type'] ?? 'info'); ?>
            <div class="notice notice-<?= e($type === 'danger' ? 'error' : ($type === 'warning' ? 'warn' : 'info')) ?> report-alert">
                <p class="mb-0"><?= e((string) ($alert['message'] ?? '')) ?></p>
            </div>
        <?php endforeach; ?>

        <?php if ($canSnapshot): ?>
            <details class="report-snapshot" style="margin:16px 0;">
                <summary class="btn btn-yellow" style="display:inline-flex;cursor:pointer;"><i data-lucide="camera"></i> Gerar snapshot manual</summary>
                <form method="post" action="<?= e(app_url('/reports/snapshots')) ?>" class="card card-pad" style="margin-top:12px;">
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
                        <div class="span-2">
                            <label class="label-sm" for="snapshot_notes">Observações</label>
                            <textarea id="snapshot_notes" name="notes" class="input" rows="2"></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-yellow" style="margin-top:12px;"><i data-lucide="save"></i> Salvar snapshot</button>
                </form>
            </details>
        <?php endif; ?>

        <?php require __DIR__ . '/_metric_cards.php'; ?>
        <?php require __DIR__ . '/_tables.php'; ?>
    </div>
</section>
