<?php
$items = $items ?? [];
$filters = $filters ?? [];
$dossierTypes = $dossierTypes ?? [];
$statuses = $statuses ?? [];
$deliveryStatuses = $deliveryStatuses ?? [];
$model = $model ?? null;
$sponsors = $sponsors ?? [];
$contracts = $contracts ?? [];
$companies = $companies ?? [];
$users = $users ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$hasFilters = !empty($hasFilters);
$canCreate = can('dossiers.create');

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$contractFilter = (int) ($filters['main_contract_id'] ?? 0) ?: (int) ($filters['contract_id'] ?? 0);

$baseQuery = array_filter([
    'q'                    => $f('q'),
    'sponsor_id'           => (int) ($filters['sponsor_id'] ?? 0) ?: '',
    'company_id'           => (int) ($filters['company_id'] ?? 0) ?: '',
    'main_contract_id'     => $contractFilter ?: '',
    'dossier_type'         => $f('dossier_type'),
    'status'               => $f('status'),
    'delivery_status'      => $f('delivery_status'),
    'responsible_user_id'  => (int) ($filters['responsible_user_id'] ?? 0) ?: '',
    'period_from'          => $f('period_from'),
    'period_to'            => $f('period_to'),
    'approved'             => !empty($filters['approved']) ? 1 : '',
    'delivered'            => !empty($filters['delivered']) ? 1 : '',
    'pending'              => !empty($filters['pending']) ? 1 : '',
    'with_balance'         => !empty($filters['with_balance']) ? 1 : '',
    'pending_counterparts' => !empty($filters['pending_counterparts']) ? 1 : '',
    'overdue_counterparts' => !empty($filters['overdue_counterparts']) ? 1 : '',
    'show_archived'        => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static fn (int $p): string => app_url('/sponsor-dossiers') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
$createUrl = app_url('/sponsor-dossiers/create');

$periodLabel = static function (array $row): string {
    $start = (string) ($row['period_start'] ?? '');
    $end = (string) ($row['period_end'] ?? '');
    if ($start === '' && $end === '') {
        return '—';
    }
    if ($start !== '' && $end !== '') {
        return $start . ' — ' . $end;
    }
    return $start !== '' ? $start : $end;
};
?>
<section class="section">
    <div class="container">
        <div class="page-head dossier-index-head">
            <div>
                <span class="kicker kicker-dark">CRM · Prestação de contas</span>
                <h1 class="h2-section">Dossiês do Patrocinador</h1>
                <p class="page-sub"><?= $total ?> dossiê(s) encontrado(s).</p>
            </div>
            <?php if ($canCreate): ?>
                <div class="dossier-actions actions-row">
                    <a href="<?= e($createUrl) ?>" class="btn btn-yellow"><i data-lucide="folder-open"></i> Novo dossiê</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($hasFilters): ?>
            <div class="notice notice-warn dossier-alert" style="margin-bottom:14px;">
                <p class="mb-0"><i data-lucide="filter"></i> Filtros ativos. <a href="<?= e(app_url('/sponsor-dossiers')) ?>" class="link-strong">Limpar filtros</a></p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?= e(app_url('/sponsor-dossiers')) ?>" class="filter-box filter-box--financial">
            <div class="filter-grid filter-grid--financial">
                <div class="filter-q">
                    <label for="q">Busca</label>
                    <input type="text" id="q" name="q" class="input" value="<?= e($f('q')) ?>" placeholder="Título, número, patrocinador, contrato, resumos, notas">
                </div>
                <div>
                    <label for="fsponsor">Patrocinador</label>
                    <select id="fsponsor" name="sponsor_id" class="input">
                        <option value="">Todos</option>
                        <?php foreach ($sponsors as $sp): ?>
                            <option value="<?= (int) $sp['id'] ?>" <?= (int) ($filters['sponsor_id'] ?? 0) === (int) $sp['id'] ? 'selected' : '' ?>><?= e($sp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fcompany">Empresa</label>
                    <select id="fcompany" name="company_id" class="input">
                        <option value="">Todas</option>
                        <?php foreach ($companies as $co): ?>
                            <option value="<?= (int) $co['id'] ?>" <?= (int) ($filters['company_id'] ?? 0) === (int) $co['id'] ? 'selected' : '' ?>><?= e($co['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fcontract">Contrato</label>
                    <select id="fcontract" name="main_contract_id" class="input">
                        <option value="">Todos</option>
                        <?php foreach ($contracts as $cn): ?>
                            <option value="<?= (int) $cn['id'] ?>" <?= $contractFilter === (int) $cn['id'] ? 'selected' : '' ?>><?= e($cn['label'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ftype">Tipo</label>
                    <select id="ftype" name="dossier_type" class="input">
                        <option value="">Todos</option>
                        <?php foreach ($dossierTypes as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $f('dossier_type') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fstatus">Status</label>
                    <select id="fstatus" name="status" class="input">
                        <option value="">Todos</option>
                        <?php foreach ($statuses as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $f('status') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fdelivery">Entrega</label>
                    <select id="fdelivery" name="delivery_status" class="input">
                        <option value="">Todos</option>
                        <?php foreach ($deliveryStatuses as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $f('delivery_status') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fresp">Responsável</label>
                    <select id="fresp" name="responsible_user_id" class="input">
                        <option value="">Todos</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (int) ($filters['responsible_user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="filter-subsection">
                <span class="filter-subsection__title">Período</span>
                <div class="filter-grid filter-grid--dates">
                    <div>
                        <label for="period_from">De</label>
                        <input type="date" id="period_from" name="period_from" class="input" value="<?= e($f('period_from')) ?>">
                    </div>
                    <div>
                        <label for="period_to">Até</label>
                        <input type="date" id="period_to" name="period_to" class="input" value="<?= e($f('period_to')) ?>">
                    </div>
                </div>
            </div>

            <div class="filter-flags">
                <span class="filter-flags__title">Situação rápida</span>
                <div class="filter-checks">
                    <label class="check-inline"><input type="checkbox" name="approved" value="1" <?= !empty($filters['approved']) ? 'checked' : '' ?>> Aprovados</label>
                    <label class="check-inline"><input type="checkbox" name="delivered" value="1" <?= !empty($filters['delivered']) ? 'checked' : '' ?>> Entregues</label>
                    <label class="check-inline"><input type="checkbox" name="pending" value="1" <?= !empty($filters['pending']) ? 'checked' : '' ?>> Pendentes</label>
                    <label class="check-inline"><input type="checkbox" name="with_balance" value="1" <?= !empty($filters['with_balance']) ? 'checked' : '' ?>> Com saldo</label>
                    <label class="check-inline"><input type="checkbox" name="pending_counterparts" value="1" <?= !empty($filters['pending_counterparts']) ? 'checked' : '' ?>> Contrapartidas pendentes</label>
                    <label class="check-inline"><input type="checkbox" name="overdue_counterparts" value="1" <?= !empty($filters['overdue_counterparts']) ? 'checked' : '' ?>> Contrapartidas atrasadas</label>
                    <label class="check-inline"><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived']) ? 'checked' : '' ?>> Arquivados</label>
                </div>
            </div>

            <div class="filter-actions-row">
                <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="filter"></i> Filtrar</button>
                <a href="<?= e(app_url('/sponsor-dossiers')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
        </form>

        <?php if ($items === []): ?>
            <div class="empty-state dossier-empty-state">
                <p>Nenhum dossiê encontrado.</p>
                <?php if ($canCreate): ?>
                    <a href="<?= e($createUrl) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Novo dossiê</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="dossier-linked-list">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Número</th>
                            <th>Patrocinador</th>
                            <th>Contrato</th>
                            <th>Empresa</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Entrega</th>
                            <th>Período</th>
                            <th>Contrapartidas</th>
                            <th>Financeiro</th>
                            <th>Responsável</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $d): ?>
                            <?php
                            $did = (int) ($d['id'] ?? 0);
                            $st = (string) ($d['status'] ?? '');
                            $delivery = (string) ($d['delivery_status'] ?? '');
                            $pendingCp = (int) ($d['counterparts_pending_count'] ?? 0);
                            $overdueCp = (int) ($d['counterparts_overdue_count'] ?? 0);
                            $remaining = (float) ($d['financial_remaining_amount'] ?? 0);
                            ?>
                            <tr class="<?= $overdueCp > 0 ? 'dossier-overdue' : '' ?>">
                                <td><strong><?= e($d['title'] ?? '') ?></strong></td>
                                <td><?= e($d['dossier_number'] ?? '—') ?></td>
                                <td><?= e($d['sponsor_name'] ?? '—') ?></td>
                                <td><?= e($d['contract_title'] ?? ($d['contract_number'] ?? '—')) ?></td>
                                <td><?= e($d['company_name'] ?? '—') ?></td>
                                <td><span class="dossier-type"><?= e($dossierTypes[$d['dossier_type'] ?? ''] ?? '') ?></span></td>
                                <td><span class="dossier-status badge-dossier-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                                <td><span class="dossier-delivery-status badge-delivery-<?= e($delivery) ?>"><?= e($deliveryStatuses[$delivery] ?? $delivery) ?></span></td>
                                <td><?= e($periodLabel($d)) ?></td>
                                <td>
                                    <?php if ($pendingCp > 0 || $overdueCp > 0): ?>
                                        <span class="<?= $overdueCp > 0 ? 'dossier-alert' : '' ?>"><?= $pendingCp ?> pend.<?= $overdueCp > 0 ? ' / ' . $overdueCp . ' atras.' : '' ?></span>
                                    <?php else: ?>
                                        <?= (int) ($d['counterparts_delivered_count'] ?? 0) ?>/<?= (int) ($d['counterparts_count'] ?? 0) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="money-value">
                                    <?php if ($remaining > 0): ?>
                                        <span class="dossier-alert"><?= e(money_br($remaining)) ?></span>
                                    <?php else: ?>
                                        <?= e(money_br($d['financial_received_amount'] ?? 0)) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($d['responsible_name'] ?? '—') ?></td>
                                <td style="text-align:right;">
                                    <a href="<?= e(app_url('/sponsor-dossiers/' . $did)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a>
                                </td>
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
