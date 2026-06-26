<?php
$items = $items ?? [];
$filters = $filters ?? [];
$categories = $categories ?? [];
$deliveryTypes = $deliveryTypes ?? [];
$statuses = $statuses ?? [];
$priorities = $priorities ?? [];
$model = $model ?? null;
$sponsors = $sponsors ?? [];
$companies = $companies ?? [];
$users = $users ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$hasFilters = !empty($hasFilters);
$canCreate = can('counterparts.create');

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$baseQuery = array_filter([
    'q' => $f('q'), 'sponsor_id' => (int) ($filters['sponsor_id'] ?? 0) ?: '',
    'company_id' => (int) ($filters['company_id'] ?? 0) ?: '',
    'category' => $f('category'), 'delivery_type' => $f('delivery_type'),
    'priority' => $f('priority'), 'status' => $f('status'),
    'responsible_user_id' => (int) ($filters['responsible_user_id'] ?? 0) ?: '',
    'due_from' => $f('due_from'), 'due_to' => $f('due_to'),
    'overdue' => !empty($filters['overdue']) ? 1 : '',
    'delivered' => !empty($filters['delivered']) ? 1 : '',
    'pending' => !empty($filters['pending']) ? 1 : '',
    'show_archived' => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static fn (int $p): string => app_url('/counterparts') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
$createUrl = app_url('/counterparts/create');
?>
<section class="section">
    <div class="container">
        <div class="page-head counterpart-index-head">
            <div>
                <span class="kicker kicker-dark">CRM · Contrapartidas</span>
                <h1 class="h2-section">Contrapartidas dos Patrocinadores</h1>
                <p class="page-sub"><?= $total ?> contrapartida(s) encontrada(s).</p>
            </div>
            <?php if ($canCreate): ?>
                <div class="counterpart-actions actions-row">
                    <a href="<?= e($createUrl) ?>" class="btn btn-yellow"><i data-lucide="list-checks"></i> Nova contrapartida</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($hasFilters): ?>
            <div class="notice notice-warn" style="margin-bottom:14px;">
                <p class="mb-0"><i data-lucide="filter"></i> Filtros ativos. <a href="<?= e(app_url('/counterparts')) ?>" class="link-strong">Limpar filtros</a></p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?= e(app_url('/counterparts')) ?>" class="filter-box">
            <div class="filter-grid">
                <div class="filter-q"><label for="q">Busca</label><input type="text" id="q" name="q" value="<?= e($f('q')) ?>" placeholder="Título, patrocinador, empresa, evidência"></div>
                <div><label for="fsponsor">Patrocinador</label>
                    <select id="fsponsor" name="sponsor_id"><option value="">Todos</option>
                    <?php foreach ($sponsors as $sp): ?><option value="<?= (int) $sp['id'] ?>" <?= (int)($filters['sponsor_id']??0)===(int)$sp['id']?'selected':'' ?>><?= e($sp['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fcompany">Empresa</label>
                    <select id="fcompany" name="company_id"><option value="">Todas</option>
                    <?php foreach ($companies as $co): ?><option value="<?= (int) $co['id'] ?>" <?= (int)($filters['company_id']??0)===(int)$co['id']?'selected':'' ?>><?= e($co['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fcat">Categoria</label>
                    <select id="fcat" name="category"><option value="">Todas</option>
                    <?php foreach ($categories as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('category')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fdtype">Tipo entrega</label>
                    <select id="fdtype" name="delivery_type"><option value="">Todos</option>
                    <?php foreach ($deliveryTypes as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('delivery_type')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fpri">Prioridade</label>
                    <select id="fpri" name="priority"><option value="">Todas</option>
                    <?php foreach ($priorities as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('priority')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fstatus">Status</label>
                    <select id="fstatus" name="status"><option value="">Todos</option>
                    <?php foreach ($statuses as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('status')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fresp">Responsável</label>
                    <select id="fresp" name="responsible_user_id"><option value="">Todos</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)($filters['responsible_user_id']??0)===(int)$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="due_from">Prazo de</label><input type="date" id="due_from" name="due_from" value="<?= e($f('due_from')) ?>"></div>
                <div><label for="due_to">Prazo até</label><input type="date" id="due_to" name="due_to" value="<?= e($f('due_to')) ?>"></div>
                <div class="filter-checks">
                    <label><input type="checkbox" name="overdue" value="1" <?= !empty($filters['overdue'])?'checked':'' ?>> Atrasadas</label>
                    <label><input type="checkbox" name="delivered" value="1" <?= !empty($filters['delivered'])?'checked':'' ?>> Entregues</label>
                    <label><input type="checkbox" name="pending" value="1" <?= !empty($filters['pending'])?'checked':'' ?>> Pendentes</label>
                    <label><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived'])?'checked':'' ?>> Arquivadas</label>
                </div>
            </div>
            <div class="actions-row">
                <button type="submit" class="btn btn-sm btn-yellow">Filtrar</button>
                <a href="<?= e(app_url('/counterparts')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
        </form>

        <?php if ($items === []): ?>
            <div class="empty-state counterpart-empty-state">
                <p>Nenhuma contrapartida encontrada.</p>
                <?php if ($canCreate): ?><a href="<?= e($createUrl) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Nova contrapartida</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="counterpart-linked-list">
                    <thead>
                        <tr>
                            <th>Título</th><th>Patrocinador</th><th>Empresa</th><th>Categoria</th><th>Tipo</th>
                            <th>Prioridade</th><th>Status</th><th>Prazo</th><th>Prometida</th><th>Entregue</th><th>Responsável</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $cp): ?>
                            <?php
                            $cid = (int) ($cp['id'] ?? 0);
                            $st = (string) ($cp['status'] ?? '');
                            $pri = (string) ($cp['priority'] ?? '');
                            $overdue = $model && $model->isOverdue($cp);
                            ?>
                            <tr class="<?= $overdue ? 'counterpart-overdue-row' : '' ?>">
                                <td><strong><?= e($cp['title'] ?? '') ?></strong></td>
                                <td><?= e($cp['sponsor_name'] ?? '—') ?></td>
                                <td><?= e($cp['company_name'] ?? '—') ?></td>
                                <td><span class="counterpart-category"><?= e($categories[$cp['category'] ?? ''] ?? '') ?></span></td>
                                <td><?= e($deliveryTypes[$cp['delivery_type'] ?? ''] ?? '') ?></td>
                                <td><span class="counterpart-priority priority-<?= e($pri) ?>"><?= e($priorities[$pri] ?? $pri) ?></span></td>
                                <td><span class="counterpart-status badge-cp-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                                <td class="<?= $overdue ? 'overdue' : '' ?>"><?= e($cp['due_date'] ?? '—') ?></td>
                                <td><?= $cp['promised_quantity'] !== null ? e((string) $cp['promised_quantity']) : '—' ?></td>
                                <td><?= $cp['delivered_quantity'] !== null ? e((string) $cp['delivered_quantity']) : '—' ?></td>
                                <td><?= e($cp['responsible_name'] ?? '—') ?></td>
                                <td style="text-align:right;"><a href="<?= e(app_url('/counterparts/' . $cid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
                <nav class="pagination" aria-label="Paginação">
                    <?php if ($page > 1): ?><a href="<?= e($pageUrl($page - 1)) ?>" class="btn btn-sm btn-outline">Anterior</a><?php endif; ?>
                    <span class="pagination-info">Página <?= $page ?> de <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a href="<?= e($pageUrl($page + 1)) ?>" class="btn btn-sm btn-outline">Próxima</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
