<?php
$items = $items ?? [];
$filters = $filters ?? [];
$reportKeys = $reportKeys ?? [];
$statuses = $statuses ?? [];
$users = $users ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$hasFilters = !empty($hasFilters);

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$baseQuery = array_filter([
    'q'             => $f('q'),
    'report_key'    => $f('report_key'),
    'status'        => $f('status'),
    'generated_by'  => (int) ($filters['generated_by'] ?? 0) ?: '',
    'period_from'   => $f('period_from'),
    'period_to'     => $f('period_to'),
    'show_archived' => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static fn (int $p): string => app_url('/reports/snapshots') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Relatórios</span>
                <h1 class="h2-section">Snapshots manuais</h1>
                <p class="page-sub"><?= $total ?> snapshot(s) encontrado(s).</p>
            </div>
            <a href="<?= e(app_url('/reports')) ?>" class="btn btn-ghost"><i data-lucide="arrow-left"></i> Voltar aos relatórios</a>
        </div>

        <form method="get" action="<?= e(app_url('/reports/snapshots')) ?>" class="filter-box report-filter">
            <div class="filter-grid">
                <div class="filter-q">
                    <label class="label-sm" for="q">Busca</label>
                    <input type="search" id="q" name="q" class="input" value="<?= e($f('q')) ?>" placeholder="Título, descrição…">
                </div>
                <div>
                    <label class="label-sm" for="report_key">Relatório</label>
                    <select id="report_key" name="report_key" class="input">
                        <option value="">Todos</option>
                        <?php foreach ($reportKeys as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $f('report_key') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label-sm" for="status">Status</label>
                    <select id="status" name="status" class="input">
                        <option value="">Todos</option>
                        <?php foreach ($statuses as $slug => $label): ?>
                            <option value="<?= e($slug) ?>" <?= $f('status') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label-sm" for="generated_by">Gerado por</label>
                    <select id="generated_by" name="generated_by" class="input">
                        <option value="">Todos</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (int) ($filters['generated_by'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="label-sm" for="period_from">Período de</label>
                    <input type="date" id="period_from" name="period_from" class="input" value="<?= e($f('period_from')) ?>">
                </div>
                <div>
                    <label class="label-sm" for="period_to">Período até</label>
                    <input type="date" id="period_to" name="period_to" class="input" value="<?= e($f('period_to')) ?>">
                </div>
                <div class="filter-checks">
                    <label class="check-inline">
                        <input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived']) ? 'checked' : '' ?>>
                        Incluir arquivados
                    </label>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-yellow">Filtrar</button>
                <a href="<?= e(app_url('/reports/snapshots')) ?>" class="btn btn-ghost">Limpar filtros</a>
            </div>
        </form>

        <?php if ($items === []): ?>
            <div class="report-empty"><p>Nenhum snapshot encontrado.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table-dcx report-table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Relatório</th>
                            <th>Período</th>
                            <th>Status</th>
                            <th>Gerado em</th>
                            <th>Por</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $row): ?>
                            <tr>
                                <td><?= e($row['title'] ?? '') ?></td>
                                <td><?= e($reportKeys[$row['report_key'] ?? ''] ?? ($row['report_key'] ?? '')) ?></td>
                                <td><?= e(trim(($row['period_start'] ?? '') . ' — ' . ($row['period_end'] ?? ''), ' —')) ?: '—' ?></td>
                                <td><span class="badge-dcx"><?= e($statuses[$row['status'] ?? ''] ?? ($row['status'] ?? '')) ?></span></td>
                                <td><?= e($row['generated_at'] ?? $row['created_at'] ?? '') ?></td>
                                <td><?= e($row['generated_by_name'] ?? '—') ?></td>
                                <td><a href="<?= e(app_url('/reports/snapshots/' . (int) ($row['id'] ?? 0))) ?>" class="link-strong">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
                <nav class="pagination" aria-label="Paginação">
                    <?php for ($p = 1; $p <= $pages; $p++): ?>
                        <a href="<?= e($pageUrl($p)) ?>" class="<?= $p === $page ? 'is-active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
