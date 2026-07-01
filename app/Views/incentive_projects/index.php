<?php
$items = $items ?? [];
$filters = $filters ?? [];
$statusLabels = $statusLabels ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);
$baseQuery = array_filter([
    'q' => $f('q'), 'status' => $f('status'), 'year' => $f('year'),
    'show_archived' => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
$pageUrl = static fn (int $p): string => app_url('/projects') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
?>
<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Projetos · PRONACs</span>
                <h1 class="h2-section">Projetos Incentivados</h1>
                <p class="page-sub"><?= $total ?> projeto(s) cadastrado(s).</p>
            </div>
            <?php if (can('incentive_projects.create')): ?>
                <a href="<?= e(app_url('/projects/create')) ?>" class="btn btn-yellow">Novo projeto</a>
            <?php endif; ?>
        </div>

        <form method="get" class="filter-box">
            <div class="filter-grid">
                <div class="filter-q"><label for="q">Busca</label><input type="text" id="q" name="q" class="input" value="<?= e($f('q')) ?>" placeholder="Nome, PRONAC, proponente"></div>
                <div><label for="fstatus">Status</label><select id="fstatus" name="status" class="input"><option value="">Todos</option>
                    <?php foreach ($statusLabels as $k => $label): ?><option value="<?= e($k) ?>" <?= $f('status') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
                <div><label for="fyear">Ano</label><input type="number" id="fyear" name="year" class="input" value="<?= e($f('year') !== '0' ? $f('year') : '') ?>" placeholder="2026"></div>
            </div>
            <div class="filter-actions-row">
                <button type="submit" class="btn btn-sm btn-yellow">Filtrar</button>
                <a href="<?= e(app_url('/projects')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
        </form>

        <?php if ($items === []): ?>
            <div class="empty-state"><p>Nenhum projeto incentivado encontrado.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Projeto</th><th>Ano</th><th>PRONAC</th><th>Status</th><th>Total aprovado</th><th>Rubrica captação</th><th>Fator</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr class="<?= !empty($it['archived_at']) ? 'row-archived' : '' ?>">
                            <td><strong><?= e($it['project_name'] ?? '') ?></strong></td>
                            <td><?= e($it['edition_year'] ?? '—') ?></td>
                            <td><?= e($it['pronac_number'] ?? '—') ?></td>
                            <td><?= e($statusLabels[$it['project_status'] ?? ''] ?? $it['project_status'] ?? '') ?></td>
                            <td><?= money_br($it['approved_total_amount'] ?? null) ?></td>
                            <td><?= money_br($it['capture_commission_budget'] ?? null) ?></td>
                            <td><?= $it['commission_factor'] !== null ? e(number_format((float) $it['commission_factor'] * 100, 4, ',', '.')) . '%' : '—' ?></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/projects/' . (int) $it['id'])) ?>" class="btn btn-sm btn-outline">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
                <nav class="pagination">
                    <?php if ($page > 1): ?><a href="<?= e($pageUrl($page - 1)) ?>" class="page-link">Anterior</a><?php endif; ?>
                    <span class="page-info">Página <?= $page ?> de <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a href="<?= e($pageUrl($page + 1)) ?>" class="page-link">Próxima</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
