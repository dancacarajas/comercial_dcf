<?php
/**
 * Listagem de oportunidades.
 *
 * Variáveis: $items, $filters, $companies, $statusLabels, $quotaInterests,
 * $sources, $urgencyLevels, $owners, $page, $pages, $total
 */
$items          = $items ?? [];
$filters        = $filters ?? [];
$companies      = $companies ?? [];
$statusLabels   = $statusLabels ?? [];
$quotaInterests = $quotaInterests ?? [];
$sources        = $sources ?? [];
$urgencyLevels  = $urgencyLevels ?? [];
$owners         = $owners ?? [];
$page           = (int) ($page ?? 1);
$pages          = (int) ($pages ?? 1);
$total          = (int) ($total ?? 0);

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$probClass = static function (int $p): string {
    if ($p >= 90) { return 'top'; }
    if ($p >= 60) { return 'high'; }
    if ($p >= 25) { return 'mid'; }
    return 'low';
};
$now = time();

$baseQuery = array_filter([
    'q'              => $f('q'),
    'company_id'     => (int) ($filters['company_id'] ?? 0) > 0 ? (int) $filters['company_id'] : '',
    'contact_id'     => (int) ($filters['contact_id'] ?? 0) > 0 ? (int) $filters['contact_id'] : '',
    'status'         => $f('status'),
    'prob_min'       => $f('prob_min'),
    'prob_max'       => $f('prob_max'),
    'quota_interest' => $f('quota_interest'),
    'source'         => $f('source'),
    'urgency_level'  => $f('urgency_level'),
    'owner'          => (int) ($filters['owner'] ?? 0) > 0 ? (int) $filters['owner'] : '',
    'overdue'        => !empty($filters['overdue']) ? 1 : '',
    'open'           => !empty($filters['open']) ? 1 : '',
    'closed'         => !empty($filters['closed']) ? 1 : '',
    'lost'           => !empty($filters['lost']) ? 1 : '',
    'show_archived'  => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static fn (int $p): string =>
    app_url('/opportunities') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section">Oportunidades</h1>
                <p class="page-sub">Funil de prospecção de patrocínio.</p>
            </div>
            <div class="actions-row">
                <a href="<?= e(app_url('/opportunities/pipeline')) ?>" class="btn btn-sm btn-outline"><i data-lucide="kanban-square"></i> Pipeline</a>
                <?php if (can('opportunities.create')): ?>
                    <a href="<?= e(app_url('/opportunities/create')) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Nova oportunidade</a>
                <?php endif; ?>
            </div>
        </div>

        <form method="get" action="<?= e(app_url('/opportunities')) ?>" class="filter-box">
            <div class="filter-grid">
                <div class="filter-q">
                    <label for="q">Busca</label>
                    <input type="text" id="q" name="q" value="<?= e($f('q')) ?>" placeholder="Título, empresa, contato ou observações">
                </div>
                <div>
                    <label for="fcompany">Empresa</label>
                    <select id="fcompany" name="company_id">
                        <option value="">Todas</option>
                        <?php foreach ($companies as $co): ?>
                            <option value="<?= (int) $co['id'] ?>" <?= (int) ($filters['company_id'] ?? 0) === (int) $co['id'] ? 'selected' : '' ?>><?= e($co['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fstatus">Status</label>
                    <select id="fstatus" name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statusLabels as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $f('status') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fquota">Interesse de cota</label>
                    <select id="fquota" name="quota_interest">
                        <option value="">Todos</option>
                        <?php foreach ($quotaInterests as $qi): ?>
                            <option value="<?= e($qi) ?>" <?= $f('quota_interest') === $qi ? 'selected' : '' ?>><?= e($qi) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fsource">Origem</label>
                    <select id="fsource" name="source">
                        <option value="">Todas</option>
                        <?php foreach ($sources as $src): ?>
                            <option value="<?= e($src) ?>" <?= $f('source') === $src ? 'selected' : '' ?>><?= e(ucfirst($src)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="furgency">Urgência</label>
                    <select id="furgency" name="urgency_level">
                        <option value="">Todas</option>
                        <?php foreach ($urgencyLevels as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $f('urgency_level') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
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
                <div>
                    <label for="fpmin">Prob. mínima (%)</label>
                    <input type="number" id="fpmin" name="prob_min" min="0" max="100" value="<?= e($f('prob_min')) ?>">
                </div>
                <div>
                    <label for="fpmax">Prob. máxima (%)</label>
                    <input type="number" id="fpmax" name="prob_max" min="0" max="100" value="<?= e($f('prob_max')) ?>">
                </div>
            </div>

            <div class="filter-flags">
                <label class="check-inline"><input type="checkbox" name="open" value="1" <?= !empty($filters['open']) ? 'checked' : '' ?>> Abertas</label>
                <label class="check-inline"><input type="checkbox" name="closed" value="1" <?= !empty($filters['closed']) ? 'checked' : '' ?>> Fechadas</label>
                <label class="check-inline"><input type="checkbox" name="lost" value="1" <?= !empty($filters['lost']) ? 'checked' : '' ?>> Perdidas</label>
                <label class="check-inline"><input type="checkbox" name="overdue" value="1" <?= !empty($filters['overdue']) ? 'checked' : '' ?>> Próxima ação vencida</label>
                <label class="check-inline"><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived']) ? 'checked' : '' ?>> Exibir arquivadas</label>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="filter"></i> Filtrar</button>
                    <a href="<?= e(app_url('/opportunities')) ?>" class="btn btn-sm btn-outline"><i data-lucide="x"></i> Limpar</a>
                </div>
            </div>
        </form>

        <p class="result-count"><?= $total ?> oportunidade(s) encontrada(s).</p>

        <?php if ($items === []): ?>
            <div class="empty-state">
                <span class="card-icon"><i data-lucide="handshake"></i></span>
                <h3 class="h3-card">Nenhuma oportunidade encontrada</h3>
                <p>Ajuste os filtros ou cadastre a primeira oportunidade do funil.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Empresa</th>
                            <th>Contato</th>
                            <th>Cota</th>
                            <th>Valor</th>
                            <th>Prob.</th>
                            <th>Status</th>
                            <th>Resp.</th>
                            <th>Próxima ação</th>
                            <th>Últ. mov.</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $o): ?>
                            <?php
                            $oid        = (int) $o['id'];
                            $isArchived = !empty($o['archived_at']);
                            $st         = (string) ($o['status'] ?? '');
                            $prob       = (int) ($o['probability'] ?? 0);
                            $next       = (string) ($o['next_action_at'] ?? '');
                            $overdue    = $next !== '' && strtotime($next) !== false && strtotime($next) < $now;
                            $quotaName  = trim((string) ($o['quota_name'] ?? ''));
                            $quotaLegacy= trim((string) ($o['quota_interest'] ?? ''));
                            ?>
                            <tr<?= $isArchived ? ' class="row-archived"' : '' ?>>
                                <td>
                                    <strong><?= e($o['title']) ?></strong>
                                    <?php if ($isArchived): ?><span class="badge-status badge-status-arquivado">Arquivada</span><?php endif; ?>
                                </td>
                                <td><a href="<?= e(app_url('/companies/' . (int) $o['company_id'])) ?>"><?= e($o['company_name'] ?? '—') ?></a></td>
                                <td>
                                    <?php if (!empty($o['contact_id'])): ?>
                                        <a href="<?= e(app_url('/contacts/' . (int) $o['contact_id'])) ?>"><?= e($o['contact_name'] ?? '—') ?></a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($quotaName !== ''): ?>
                                        <span class="badge-quota"><?= e($quotaName) ?></span>
                                    <?php elseif ($quotaLegacy !== ''): ?>
                                        <span class="quota-legacy"><?= e($quotaLegacy) ?></span>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="money-value"><?= e(money_br($o['estimated_value'] ?? null)) ?></td>
                                <td><span class="badge-probability badge-probability-<?= $probClass($prob) ?>"><?= $prob ?>%</span></td>
                                <td><span class="badge-status badge-status-<?= e($st) ?>"><?= e($statusLabels[$st] ?? $st) ?></span></td>
                                <td><?= e($o['owner_name'] ?? '') ?: '—' ?></td>
                                <td><?= $next !== '' ? '<span class="' . ($overdue ? 'overdue' : '') . '">' . e($next) . '</span>' : '—' ?></td>
                                <td><?= e($o['last_interaction_at'] ?? '') ?: '—' ?></td>
                                <td>
                                    <div class="actions-row" style="justify-content:flex-end;">
                                        <a href="<?= e(app_url('/opportunities/' . $oid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> Ver</a>
                                        <?php if (can('opportunities.edit') && !$isArchived): ?>
                                            <a href="<?= e(app_url('/opportunities/' . $oid . '/edit')) ?>" class="btn btn-sm btn-light"><i data-lucide="pencil"></i> Editar</a>
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
