<?php
/**
 * Listagem de empresas / prospects.
 *
 * Variáveis: $companies, $filters, $segments, $priorities, $statuses,
 * $states, $owners, $page, $pages, $total, $perPage
 */
$companies  = $companies ?? [];
$filters    = $filters ?? [];
$segments   = $segments ?? [];
$priorities = $priorities ?? [];
$statuses   = $statuses ?? [];
$states     = $states ?? [];
$owners     = $owners ?? [];
$page       = (int) ($page ?? 1);
$pages      = (int) ($pages ?? 1);
$total      = (int) ($total ?? 0);

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$priorityLabel = static fn (string $p): string => match ($p) {
    'A' => 'Alta', 'B' => 'Média', 'C' => 'Baixa', 'D' => 'Monitorar', default => $p,
};
$statusLabel = static function (string $s) use ($statuses): string {
    return $statuses[$s] ?? $s;
};

// Monta a query string base (filtros ativos) para a paginação.
$baseQuery = array_filter([
    'q'        => $f('q'),
    'segment'  => $f('segment'),
    'priority' => $f('priority'),
    'status'   => $f('status'),
    'state'    => $f('state'),
    'owner'    => (int) ($filters['owner'] ?? 0) > 0 ? (int) $filters['owner'] : '',
    'operates_para'        => !empty($filters['operates_para']) ? 1 : '',
    'operates_carajas'     => !empty($filters['operates_carajas']) ? 1 : '',
    'operates_parauapebas' => !empty($filters['operates_parauapebas']) ? 1 : '',
    'show_archived'        => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static function (int $p) use ($baseQuery): string {
    $q = array_merge($baseQuery, ['page' => $p]);
    return app_url('/companies') . '?' . http_build_query($q);
};
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section">Empresas</h1>
                <p class="page-sub">Empresas potenciais patrocinadoras do Dança Carajás Festival 2026.</p>
            </div>
            <?php if (can('companies.create')): ?>
                <a href="<?= e(app_url('/companies/create')) ?>" class="btn btn-yellow">
                    <i data-lucide="building-2"></i> Nova empresa
                </a>
            <?php endif; ?>
        </div>

        <form method="get" action="<?= e(app_url('/companies')) ?>" class="filter-box">
            <div class="filter-grid">
                <div class="filter-q">
                    <label for="q">Busca</label>
                    <input type="text" id="q" name="q" value="<?= e($f('q')) ?>" placeholder="Nome, fantasia ou CNPJ">
                </div>
                <div>
                    <label for="fsegment">Segmento</label>
                    <select id="fsegment" name="segment">
                        <option value="">Todos</option>
                        <?php foreach ($segments as $seg): ?>
                            <option value="<?= e($seg) ?>" <?= $f('segment') === $seg ? 'selected' : '' ?>><?= e(ucfirst($seg)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fpriority">Prioridade</label>
                    <select id="fpriority" name="priority">
                        <option value="">Todas</option>
                        <?php foreach ($priorities as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $f('priority') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fstatus">Status</label>
                    <select id="fstatus" name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statuses as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $f('status') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fstate">UF</label>
                    <select id="fstate" name="state">
                        <option value="">Todas</option>
                        <?php foreach ($states as $uf): ?>
                            <option value="<?= e($uf) ?>" <?= $f('state') === $uf ? 'selected' : '' ?>><?= e($uf) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fowner">Responsável</label>
                    <select id="fowner" name="owner">
                        <option value="">Todos</option>
                        <?php foreach ($owners as $o): ?>
                            <option value="<?= (int) $o['id'] ?>" <?= (int) ($filters['owner'] ?? 0) === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="filter-flags">
                <label class="check-inline">
                    <input type="checkbox" name="operates_para" value="1" <?= !empty($filters['operates_para']) ? 'checked' : '' ?>> Pará
                </label>
                <label class="check-inline">
                    <input type="checkbox" name="operates_carajas" value="1" <?= !empty($filters['operates_carajas']) ? 'checked' : '' ?>> Carajás
                </label>
                <label class="check-inline">
                    <input type="checkbox" name="operates_parauapebas" value="1" <?= !empty($filters['operates_parauapebas']) ? 'checked' : '' ?>> Parauapebas
                </label>
                <label class="check-inline">
                    <input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived']) ? 'checked' : '' ?>> Exibir arquivadas
                </label>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="filter"></i> Filtrar</button>
                    <a href="<?= e(app_url('/companies')) ?>" class="btn btn-sm btn-outline"><i data-lucide="x"></i> Limpar</a>
                </div>
            </div>
        </form>

        <p class="result-count"><?= $total ?> empresa(s) encontrada(s).</p>

        <?php if ($companies === []): ?>
            <div class="empty-state">
                <span class="card-icon"><i data-lucide="building-2"></i></span>
                <h3 class="h3-card">Nenhuma empresa encontrada</h3>
                <p>Ajuste os filtros ou cadastre a primeira empresa potencial patrocinadora.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>CNPJ</th>
                            <th>Segmento</th>
                            <th>Cidade/UF</th>
                            <th>Prioridade</th>
                            <th>Status</th>
                            <th>Responsável</th>
                            <th>Atualizado</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $c): ?>
                            <?php
                            $cid        = (int) $c['id'];
                            $isArchived = !empty($c['archived_at']);
                            $prio       = (string) ($c['priority'] ?? 'C');
                            $st         = (string) ($c['status'] ?? 'prospect');
                            $cityUf     = trim((string) ($c['city'] ?? '') . (($c['city'] ?? '') && ($c['state'] ?? '') ? '/' : '') . (string) ($c['state'] ?? ''));
                            ?>
                            <tr<?= $isArchived ? ' class="row-archived"' : '' ?>>
                                <td>
                                    <strong><?= e($c['name']) ?></strong>
                                    <?php if (!empty($c['trade_name'])): ?>
                                        <span class="cell-sub"><?= e($c['trade_name']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($isArchived): ?>
                                        <span class="badge-status badge-status-arquivado">Arquivada</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($c['cnpj'] ?? '') ?: '—' ?></td>
                                <td><?= e($c['segment'] ? ucfirst((string) $c['segment']) : '—') ?></td>
                                <td><?= e($cityUf !== '' ? $cityUf : '—') ?></td>
                                <td><span class="badge-priority badge-priority-<?= e($prio) ?>"><?= e($prio) ?> · <?= e($priorityLabel($prio)) ?></span></td>
                                <td><span class="badge-status badge-status-<?= e($st) ?>"><?= e($statusLabel($st)) ?></span></td>
                                <td><?= e($c['owner_name'] ?? '') ?: '—' ?></td>
                                <td><?= e($c['updated_at'] ?? $c['created_at'] ?? '') ?></td>
                                <td>
                                    <div class="actions-row" style="justify-content:flex-end;">
                                        <a href="<?= e(app_url('/companies/' . $cid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> Ver</a>
                                        <?php if (can('companies.edit') && !$isArchived): ?>
                                            <a href="<?= e(app_url('/companies/' . $cid . '/edit')) ?>" class="btn btn-sm btn-light"><i data-lucide="pencil"></i> Editar</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="pagination" aria-label="Paginação">
                    <?php if ($page > 1): ?>
                        <a href="<?= e($pageUrl($page - 1)) ?>" class="page-link"><i data-lucide="chevron-left"></i> Anterior</a>
                    <?php endif; ?>
                    <span class="page-info">Página <?= $page ?> de <?= $pages ?></span>
                    <?php if ($page < $pages): ?>
                        <a href="<?= e($pageUrl($page + 1)) ?>" class="page-link">Próxima <i data-lucide="chevron-right"></i></a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
