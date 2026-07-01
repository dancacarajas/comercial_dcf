<?php
$filters = $filters ?? [];
$options = $options ?? [];
$filterErrors = $filterErrors ?? [];
$reportKey = $reportKey ?? 'executive';
$basePath = $basePath ?? app_url('/reports');

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$routeMap = [
    'executive'    => app_url('/reports'),
    'pipeline'     => app_url('/reports/pipeline'),
    'proposals'    => app_url('/reports/proposals'),
    'sponsors'     => app_url('/reports/sponsors'),
    'financials'   => app_url('/reports/financials'),
    'contracts'    => app_url('/reports/contracts'),
    'counterparts' => app_url('/reports/counterparts'),
    'dossiers'     => app_url('/reports/dossiers'),
    'tasks'        => app_url('/reports/tasks'),
    'leads'        => app_url('/reports/leads'),
];
$action = $routeMap[$reportKey] ?? app_url('/reports');
?>
<form method="get" action="<?= e($action) ?>" class="filter-box report-filter report-filter--compact">
    <details class="report-filter-details"<?= ($filters['period_start'] ?? null) || ($filters['period_end'] ?? null) || !empty($filters['responsible_user_id']) ? ' open' : '' ?>>
        <summary class="report-filter-summary">
            <i data-lucide="sliders-horizontal" aria-hidden="true"></i>
            <span>Filtros do relatório</span>
            <?php if ($hasFilters ?? false): ?>
                <span class="pill pill-warn">Ativos</span>
            <?php endif; ?>
        </summary>
        <div class="filter-grid report-filter-grid">
        <div>
            <label class="label-sm" for="period_start">Período início</label>
            <input type="date" id="period_start" name="period_start" class="input" value="<?= e($f('period_start')) ?>">
        </div>
        <div>
            <label class="label-sm" for="period_end">Período fim</label>
            <input type="date" id="period_end" name="period_end" class="input" value="<?= e($f('period_end')) ?>">
        </div>
        <div>
            <label class="label-sm" for="responsible_user_id">Responsável</label>
            <select id="responsible_user_id" name="responsible_user_id" class="input">
                <option value="">Todos</option>
                <?php foreach ($options['users'] ?? [] as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (int) ($filters['responsible_user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label-sm" for="incentive_project_id">Projeto</label>
            <select id="incentive_project_id" name="incentive_project_id" class="input">
                <option value="">Todos</option>
                <?php foreach ($options['projects'] ?? [] as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) ($filters['incentive_project_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['label'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label-sm" for="company_id">Empresa</label>
            <select id="company_id" name="company_id" class="input">
                <option value="">Todas</option>
                <?php foreach ($options['companies'] ?? [] as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($filters['company_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label-sm" for="sponsor_id">Patrocinador</label>
            <select id="sponsor_id" name="sponsor_id" class="input">
                <option value="">Todos</option>
                <?php foreach ($options['sponsors'] ?? [] as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($filters['sponsor_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label-sm" for="quota_id">Cota</label>
            <select id="quota_id" name="quota_id" class="input">
                <option value="">Todas</option>
                <?php foreach ($options['quotas'] ?? [] as $q): ?>
                    <option value="<?= (int) $q['id'] ?>" <?= (int) ($filters['quota_id'] ?? 0) === (int) $q['id'] ? 'selected' : '' ?>><?= e($q['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="label-sm" for="status">Status</label>
            <input type="text" id="status" name="status" class="input" value="<?= e($f('status')) ?>" placeholder="Opcional">
        </div>
        <div>
            <label class="label-sm" for="source">Origem</label>
            <input type="text" id="source" name="source" class="input" value="<?= e($f('source')) ?>" placeholder="Opcional">
        </div>
        <div class="filter-checks">
            <label class="check-inline">
                <input type="checkbox" name="only_pending" value="1" <?= !empty($filters['only_pending']) ? 'checked' : '' ?>>
                Somente pendentes
            </label>
            <label class="check-inline">
                <input type="checkbox" name="only_overdue" value="1" <?= !empty($filters['only_overdue']) ? 'checked' : '' ?>>
                Somente atrasados
            </label>
        </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-yellow btn-sm"><i data-lucide="filter"></i> Aplicar filtros</button>
            <a href="<?= e($action) ?>" class="btn btn-ghost btn-sm">Limpar filtros</a>
        </div>
    </details>
</form>
<?php if ($filterErrors !== []): ?>
    <div class="notice notice-warn report-alert">
        <?php foreach ($filterErrors as $msg): ?>
            <p class="mb-0"><?= e($msg) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
