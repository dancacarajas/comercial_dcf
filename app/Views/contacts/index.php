<?php
/**
 * Listagem de contatos.
 *
 * Variáveis: $contacts, $filters, $companies, $departments, $decisionLevels,
 * $influenceLevels, $channels, $statuses, $owners, $page, $pages, $total
 */
$contacts        = $contacts ?? [];
$filters         = $filters ?? [];
$companies       = $companies ?? [];
$departments     = $departments ?? [];
$decisionLevels  = $decisionLevels ?? [];
$influenceLevels = $influenceLevels ?? [];
$channels        = $channels ?? [];
$statuses        = $statuses ?? [];
$owners          = $owners ?? [];
$page            = (int) ($page ?? 1);
$pages           = (int) ($pages ?? 1);
$total           = (int) ($total ?? 0);

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$decLabel = static fn (string $v): string => $decisionLevels[$v] ?? $v;
$infLabel = static fn (string $v): string => $influenceLevels[$v] ?? $v;
$stLabel  = static fn (string $v): string => $statuses[$v] ?? $v;
$now      = time();

$baseQuery = array_filter([
    'q'                 => $f('q'),
    'company_id'        => (int) ($filters['company_id'] ?? 0) > 0 ? (int) $filters['company_id'] : '',
    'department'        => $f('department'),
    'decision_level'    => $f('decision_level'),
    'influence_level'   => $f('influence_level'),
    'preferred_channel' => $f('preferred_channel'),
    'status'            => $f('status'),
    'owner'             => (int) ($filters['owner'] ?? 0) > 0 ? (int) $filters['owner'] : '',
    'overdue'           => !empty($filters['overdue']) ? 1 : '',
    'show_archived'     => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static function (int $p) use ($baseQuery): string {
    return app_url('/contacts') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
};
?>

<section class="section">
    <div class="container">
        <div class="page-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section">Contatos</h1>
                <p class="page-sub">Pessoas estratégicas vinculadas às empresas potenciais patrocinadoras.</p>
            </div>
            <?php if (can('contacts.create')): ?>
                <a href="<?= e(app_url('/contacts/create')) ?>" class="btn btn-yellow">
                    <i data-lucide="user-plus"></i> Novo contato
                </a>
            <?php endif; ?>
        </div>

        <form method="get" action="<?= e(app_url('/contacts')) ?>" class="filter-box">
            <div class="filter-grid">
                <div class="filter-q">
                    <label for="q">Busca</label>
                    <input type="text" id="q" name="q" value="<?= e($f('q')) ?>" placeholder="Nome, empresa, e-mail, WhatsApp ou cargo">
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
                    <label for="fdepartment">Área</label>
                    <select id="fdepartment" name="department">
                        <option value="">Todas</option>
                        <?php foreach ($departments as $dep): ?>
                            <option value="<?= e($dep) ?>" <?= $f('department') === $dep ? 'selected' : '' ?>><?= e(ucfirst($dep)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fdecision">Decisão</label>
                    <select id="fdecision" name="decision_level">
                        <option value="">Todos</option>
                        <?php foreach ($decisionLevels as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $f('decision_level') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="finfluence">Influência</label>
                    <select id="finfluence" name="influence_level">
                        <option value="">Todas</option>
                        <?php foreach ($influenceLevels as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $f('influence_level') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fchannel">Canal</label>
                    <select id="fchannel" name="preferred_channel">
                        <option value="">Todos</option>
                        <?php foreach ($channels as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $f('preferred_channel') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
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
                    <input type="checkbox" name="overdue" value="1" <?= !empty($filters['overdue']) ? 'checked' : '' ?>> Próximo contato vencido
                </label>
                <label class="check-inline">
                    <input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived']) ? 'checked' : '' ?>> Exibir arquivados
                </label>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="filter"></i> Filtrar</button>
                    <a href="<?= e(app_url('/contacts')) ?>" class="btn btn-sm btn-outline"><i data-lucide="x"></i> Limpar</a>
                </div>
            </div>
        </form>

        <p class="result-count"><?= $total ?> contato(s) encontrado(s).</p>

        <?php if ($contacts === []): ?>
            <div class="empty-state">
                <span class="card-icon"><i data-lucide="contact"></i></span>
                <h3 class="h3-card">Nenhum contato encontrado</h3>
                <p>Ajuste os filtros ou cadastre o primeiro contato vinculado a uma empresa.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Empresa</th>
                            <th>Cargo</th>
                            <th>Área</th>
                            <th>E-mail</th>
                            <th>WhatsApp</th>
                            <th>Decisão</th>
                            <th>Influência</th>
                            <th>Status</th>
                            <th>Próximo contato</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $c): ?>
                            <?php
                            $cid        = (int) $c['id'];
                            $isArchived = !empty($c['archived_at']);
                            $dec        = (string) ($c['decision_level'] ?? 'nao_informado');
                            $inf        = (string) ($c['influence_level'] ?? 'media');
                            $st         = (string) ($c['status'] ?? 'ativo');
                            $next       = (string) ($c['next_contact_at'] ?? '');
                            $overdue    = $next !== '' && strtotime($next) !== false && strtotime($next) < $now;
                            ?>
                            <tr<?= $isArchived ? ' class="row-archived"' : '' ?>>
                                <td>
                                    <strong><?= e($c['name']) ?></strong>
                                    <?php if ($isArchived): ?><span class="badge-status badge-status-arquivado">Arquivado</span><?php endif; ?>
                                    <?php if (!empty($c['company_archived_at'])): ?><span class="cell-sub"><i data-lucide="archive"></i> empresa arquivada</span><?php endif; ?>
                                </td>
                                <td><a href="<?= e(app_url('/companies/' . (int) $c['company_id'])) ?>"><?= e($c['company_name'] ?? '—') ?></a></td>
                                <td><?= e($c['position_title'] ?? '') ?: '—' ?></td>
                                <td><?= e($c['department'] ? ucfirst((string) $c['department']) : '—') ?></td>
                                <td><?= e($c['email'] ?? '') ?: '—' ?></td>
                                <td><?= e($c['whatsapp'] ?? '') ?: '—' ?></td>
                                <td><span class="badge-decision badge-decision-<?= e($dec) ?>"><?= e($decLabel($dec)) ?></span></td>
                                <td><span class="badge-influence badge-influence-<?= e($inf) ?>"><?= e($infLabel($inf)) ?></span></td>
                                <td><span class="badge-status badge-status-<?= e($st) ?>"><?= e($stLabel($st)) ?></span></td>
                                <td><?= $next !== '' ? '<span class="' . ($overdue ? 'overdue' : '') . '">' . e($next) . '</span>' : '—' ?></td>
                                <td>
                                    <div class="actions-row" style="justify-content:flex-end;">
                                        <a href="<?= e(app_url('/contacts/' . $cid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i> Ver</a>
                                        <?php if (can('contacts.edit') && !$isArchived): ?>
                                            <a href="<?= e(app_url('/contacts/' . $cid . '/edit')) ?>" class="btn btn-sm btn-light"><i data-lucide="pencil"></i> Editar</a>
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
