<?php
/**
 * Listagem de cotas de patrocínio.
 *
 * Variáveis: $items, $filters, $statuses, $model (Quota), $page, $pages, $total
 */
$items    = $items ?? [];
$filters  = $filters ?? [];
$statuses = $statuses ?? [];
$model    = $model ?? null;
$page     = (int) ($page ?? 1);
$pages    = (int) ($pages ?? 1);
$total    = (int) ($total ?? 0);

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$baseQuery = array_filter([
    'q'             => $f('q'),
    'status'        => $f('status'),
    'amount_min'    => $f('amount_min'),
    'amount_max'    => $f('amount_max'),
    'show_archived' => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static fn (int $p): string =>
    app_url('/quotas') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">Cotas · Patrocínio</span>
                <h1 class="h2-section">Cotas de patrocínio</h1>
                <p class="page-sub">Cotas disponíveis, em negociação, reservadas e fechadas.</p>
            </div>
            <div class="actions-row">
                <?php if (can('quotas.create')): ?>
                    <a href="<?= e(app_url('/quotas/create')) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Nova cota</a>
                <?php endif; ?>
            </div>
        </div>

        <form method="get" action="<?= e(app_url('/quotas')) ?>" class="filter-box">
            <div class="filter-grid">
                <div class="filter-q">
                    <label for="q">Busca</label>
                    <input type="text" id="q" name="q" value="<?= e($f('q')) ?>" placeholder="Nome, nome comercial ou descrição">
                </div>
                <div>
                    <label for="fstatus">Status</label>
                    <select id="fstatus" name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statuses as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $f('status') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="famin">Valor mínimo (R$)</label>
                    <input type="number" id="famin" name="amount_min" min="0" step="0.01" value="<?= e($f('amount_min')) ?>">
                </div>
                <div>
                    <label for="famax">Valor máximo (R$)</label>
                    <input type="number" id="famax" name="amount_max" min="0" step="0.01" value="<?= e($f('amount_max')) ?>">
                </div>
            </div>

            <div class="filter-flags">
                <label class="check-inline"><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived']) ? 'checked' : '' ?>> Exibir arquivadas</label>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="filter"></i> Filtrar</button>
                    <a href="<?= e(app_url('/quotas')) ?>" class="btn btn-sm btn-outline"><i data-lucide="x"></i> Limpar</a>
                </div>
            </div>
        </form>

        <p class="result-count"><?= $total ?> cota(s) encontrada(s).</p>

        <?php if ($items === []): ?>
            <div class="empty-state">
                <span class="card-icon"><i data-lucide="ticket"></i></span>
                <h3 class="h3-card">Nenhuma cota encontrada</h3>
                <p>Ajuste os filtros ou cadastre a primeira cota de patrocínio.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Nome comercial</th>
                            <th>Valor</th>
                            <th>Disp.</th>
                            <th>Reserv.</th>
                            <th>Fech.</th>
                            <th>Saldo</th>
                            <th>Status</th>
                            <th>Ordem</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $q): ?>
                            <?php
                            $qid        = (int) $q['id'];
                            $isArchived = !empty($q['archived_at']);
                            $st         = (string) ($q['status'] ?? '');
                            $remaining  = $model !== null ? $model->remainingQuantity($q) : 0;
                            ?>
                            <tr<?= $isArchived ? ' class="row-archived"' : '' ?>>
                                <td>
                                    <strong><?= e($q['name']) ?></strong>
                                    <?php if ($isArchived): ?><span class="badge-status badge-status-arquivado">Arquivada</span><?php endif; ?>
                                </td>
                                <td><?= e($q['commercial_name'] ?? '') ?: '—' ?></td>
                                <td class="money-value"><?= $q['amount'] !== null ? e(money_br($q['amount'])) : '<span class="quota-flex">flexível</span>' ?></td>
                                <td><?= (int) ($q['available_quantity'] ?? 0) ?></td>
                                <td><?= (int) ($q['reserved_quantity'] ?? 0) ?></td>
                                <td><?= (int) ($q['closed_quantity'] ?? 0) ?></td>
                                <td><span class="remaining-quantity <?= $remaining < 0 ? 'remaining-negative' : '' ?>"><?= $remaining ?></span></td>
                                <td><span class="badge-quota badge-quota-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                                <td><?= (int) ($q['display_order'] ?? 0) ?></td>
                                <td>
                                    <div class="actions-row" style="justify-content:flex-end;">
                                        <a href="<?= e(app_url('/quotas/' . $qid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> Ver</a>
                                        <?php if (can('quotas.edit') && !$isArchived): ?>
                                            <a href="<?= e(app_url('/quotas/' . $qid . '/edit')) ?>" class="btn btn-sm btn-light"><i data-lucide="pencil"></i> Editar</a>
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
