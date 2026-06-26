<?php
/**
 * Listagem de tarefas e follow-ups.
 *
 * Variáveis: $items, $filters, $types, $priorities, $statuses, $companies,
 * $owners, $model (Task), $page, $pages, $total
 */
$items      = $items ?? [];
$filters    = $filters ?? [];
$types      = $types ?? [];
$priorities = $priorities ?? [];
$statuses   = $statuses ?? [];
$companies  = $companies ?? [];
$owners     = $owners ?? [];
$model      = $model ?? null;
$page       = (int) ($page ?? 1);
$pages      = (int) ($pages ?? 1);
$total      = (int) ($total ?? 0);

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$baseQuery = array_filter([
    'q'                => $f('q'),
    'type'             => $f('type'),
    'company_id'       => (int) ($filters['company_id'] ?? 0) > 0 ? (int) $filters['company_id'] : '',
    'contact_id'       => (int) ($filters['contact_id'] ?? 0) > 0 ? (int) $filters['contact_id'] : '',
    'opportunity_id'   => (int) ($filters['opportunity_id'] ?? 0) > 0 ? (int) $filters['opportunity_id'] : '',
    'assigned_user_id' => (int) ($filters['assigned_user_id'] ?? 0) > 0 ? (int) $filters['assigned_user_id'] : '',
    'priority'         => $f('priority'),
    'status'           => $f('status'),
    'overdue'          => !empty($filters['overdue']) ? 1 : '',
    'today'            => !empty($filters['today']) ? 1 : '',
    'week'             => !empty($filters['week']) ? 1 : '',
    'mine'             => !empty($filters['mine']) ? 1 : '',
    'show_archived'    => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static fn (int $p): string =>
    app_url('/tasks') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section">Tarefas e follow-ups</h1>
                <p class="page-sub">Próximas ações, cobranças de retorno e pendências internas.</p>
            </div>
            <div class="actions-row">
                <?php if (can('tasks.create')): ?>
                    <a href="<?= e(app_url('/tasks/create')) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Nova tarefa</a>
                <?php endif; ?>
            </div>
        </div>

        <form method="get" action="<?= e(app_url('/tasks')) ?>" class="filter-box">
            <div class="filter-grid">
                <div class="filter-q">
                    <label for="q">Busca</label>
                    <input type="text" id="q" name="q" value="<?= e($f('q')) ?>" placeholder="Título, descrição, empresa, contato ou oportunidade">
                </div>
                <div>
                    <label for="ftype">Tipo</label>
                    <select id="ftype" name="type">
                        <option value="">Todos</option>
                        <?php foreach ($types as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $f('type') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                    <label for="fowner">Responsável</label>
                    <select id="fowner" name="assigned_user_id">
                        <option value="">Todos</option>
                        <?php foreach ($owners as $o): ?>
                            <option value="<?= (int) $o['id'] ?>" <?= (int) ($filters['assigned_user_id'] ?? 0) === (int) $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fpriority">Prioridade</label>
                    <select id="fpriority" name="priority">
                        <option value="">Todas</option>
                        <?php foreach ($priorities as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $f('priority') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
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
            </div>

            <div class="filter-flags">
                <label class="check-inline"><input type="checkbox" name="overdue" value="1" <?= !empty($filters['overdue']) ? 'checked' : '' ?>> Vencidas</label>
                <label class="check-inline"><input type="checkbox" name="today" value="1" <?= !empty($filters['today']) ? 'checked' : '' ?>> Hoje</label>
                <label class="check-inline"><input type="checkbox" name="week" value="1" <?= !empty($filters['week']) ? 'checked' : '' ?>> Esta semana</label>
                <label class="check-inline"><input type="checkbox" name="mine" value="1" <?= !empty($filters['mine']) ? 'checked' : '' ?>> Minhas tarefas</label>
                <label class="check-inline"><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived']) ? 'checked' : '' ?>> Exibir arquivadas</label>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="filter"></i> Filtrar</button>
                    <a href="<?= e(app_url('/tasks')) ?>" class="btn btn-sm btn-outline"><i data-lucide="x"></i> Limpar</a>
                </div>
            </div>
        </form>

        <p class="result-count"><?= $total ?> tarefa(s) encontrada(s).</p>

        <?php if ($items === []): ?>
            <div class="empty-state">
                <span class="card-icon"><i data-lucide="list-checks"></i></span>
                <h3 class="h3-card">Nenhuma tarefa encontrada</h3>
                <p>Ajuste os filtros ou cadastre a primeira tarefa.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Empresa</th>
                            <th>Contato</th>
                            <th>Oportunidade</th>
                            <th>Responsável</th>
                            <th>Vencimento</th>
                            <th>Prioridade</th>
                            <th>Status</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $t): ?>
                            <?php
                            $tid        = (int) $t['id'];
                            $isArchived = !empty($t['archived_at']);
                            $overdue    = $model !== null && $model->isOverdue($t);
                            $st         = (string) ($t['status'] ?? '');
                            $pr         = (string) ($t['priority'] ?? '');
                            $tp         = (string) ($t['type'] ?? '');
                            $due        = trim((string) ($t['due_date'] ?? '') . ' ' . substr((string) ($t['due_time'] ?? ''), 0, 5));
                            ?>
                            <tr class="<?= $isArchived ? 'row-archived' : '' ?> <?= $overdue ? 'task-overdue' : '' ?>">
                                <td>
                                    <strong><?= e($t['title']) ?></strong>
                                    <?php if ($overdue): ?><span class="badge-task badge-task-vencida">Vencida</span><?php endif; ?>
                                    <?php if ($isArchived): ?><span class="badge-status badge-status-arquivado">Arquivada</span><?php endif; ?>
                                </td>
                                <td><span class="badge-task-type"><?= e($types[$tp] ?? $tp) ?></span></td>
                                <td><?php if (!empty($t['company_id'])): ?><a href="<?= e(app_url('/companies/' . (int) $t['company_id'])) ?>"><?= e($t['company_name'] ?? '—') ?></a><?php else: ?>—<?php endif; ?></td>
                                <td><?php if (!empty($t['contact_id'])): ?><a href="<?= e(app_url('/contacts/' . (int) $t['contact_id'])) ?>"><?= e($t['contact_name'] ?? '—') ?></a><?php else: ?>—<?php endif; ?></td>
                                <td><?php if (!empty($t['opportunity_id'])): ?><a href="<?= e(app_url('/opportunities/' . (int) $t['opportunity_id'])) ?>"><?= e($t['opportunity_title'] ?? '—') ?></a><?php else: ?>—<?php endif; ?></td>
                                <td><?= e($t['assigned_name'] ?? '') ?: '—' ?></td>
                                <td><?= $due !== '' ? '<span class="' . ($overdue ? 'overdue' : '') . '">' . e($due) . '</span>' : '—' ?></td>
                                <td><span class="badge-priority badge-priority-<?= e($pr) ?>"><?= e($priorities[$pr] ?? $pr) ?></span></td>
                                <td><span class="badge-task badge-task-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                                <td>
                                    <div class="actions-row" style="justify-content:flex-end;">
                                        <a href="<?= e(app_url('/tasks/' . $tid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> Ver</a>
                                        <?php if (can('tasks.edit') && !$isArchived): ?>
                                            <a href="<?= e(app_url('/tasks/' . $tid . '/edit')) ?>" class="btn btn-sm btn-light"><i data-lucide="pencil"></i> Editar</a>
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
