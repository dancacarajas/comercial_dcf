<?php
$items = $items ?? [];
$filters = $filters ?? [];
$sponsorshipTypes = $sponsorshipTypes ?? [];
$fundingMechanisms = $fundingMechanisms ?? [];
$statuses = $statuses ?? [];
$paymentStatuses = $paymentStatuses ?? [];
$model = $model ?? null;
$companies = $companies ?? [];
$contacts = $contacts ?? [];
$opportunities = $opportunities ?? [];
$proposals = $proposals ?? [];
$quotas = $quotas ?? [];
$users = $users ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$hasFilters = !empty($hasFilters);
$canCreate = can('sponsors.create');

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$baseQuery = array_filter([
    'q' => $f('q'), 'company_id' => (int) ($filters['company_id'] ?? 0) ?: '',
    'contact_id' => (int) ($filters['contact_id'] ?? 0) ?: '',
    'opportunity_id' => (int) ($filters['opportunity_id'] ?? 0) ?: '',
    'proposal_id' => (int) ($filters['proposal_id'] ?? 0) ?: '',
    'quota_id' => (int) ($filters['quota_id'] ?? 0) ?: '',
    'sponsorship_type' => $f('sponsorship_type'), 'funding_mechanism' => $f('funding_mechanism'),
    'status' => $f('status'), 'payment_status' => $f('payment_status'),
    'responsible_user_id' => (int) ($filters['responsible_user_id'] ?? 0) ?: '',
    'project_year' => (int) ($filters['project_year'] ?? 0) ?: '',
    'awaiting_contribution' => !empty($filters['awaiting_contribution']) ? 1 : '',
    'overdue' => !empty($filters['overdue']) ? 1 : '',
    'closed_from' => $f('closed_from'), 'closed_to' => $f('closed_to'),
    'confirmed_from' => $f('confirmed_from'), 'confirmed_to' => $f('confirmed_to'),
    'show_archived' => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static fn (int $p): string => app_url('/sponsors') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
$createUrl = app_url('/sponsors/create');
?>

<section class="section">
    <div class="container">
        <div class="page-head sponsor-index-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section">Patrocinadores / Fechamentos</h1>
                <p class="page-sub"><?= $total ?> fechamento(s) encontrado(s).</p>
            </div>
            <?php if ($canCreate): ?>
                <div class="sponsor-actions actions-row">
                    <a href="<?= e($createUrl) ?>" class="btn btn-yellow sponsor-create-btn"><i data-lucide="badge-dollar-sign"></i> Novo fechamento</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($hasFilters): ?>
            <div class="notice notice-warn" style="margin-bottom:14px;">
                <p class="mb-0"><i data-lucide="filter"></i> Filtros ativos. <a href="<?= e(app_url('/sponsors')) ?>" class="link-strong">Limpar filtros</a></p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?= e(app_url('/sponsors')) ?>" class="filter-box filter-box--financial">
            <div class="filter-grid filter-grid--financial">
                <div class="filter-q"><label for="q">Busca</label><input type="text" id="q" name="q" class="input" value="<?= e($f('q')) ?>" placeholder="Nome, empresa, PRONAC, observações"></div>
                <div><label for="fcompany">Empresa</label>
                    <select id="fcompany" name="company_id" class="input"><option value="">Todas</option>
                    <?php foreach ($companies as $co): ?><option value="<?= (int) $co['id'] ?>" <?= (int)($filters['company_id']??0)===(int)$co['id']?'selected':'' ?>><?= e($co['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="ftype">Tipo</label>
                    <select id="ftype" name="sponsorship_type" class="input"><option value="">Todos</option>
                    <?php foreach ($sponsorshipTypes as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('sponsorship_type')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="ffund">Mecanismo</label>
                    <select id="ffund" name="funding_mechanism" class="input"><option value="">Todos</option>
                    <?php foreach ($fundingMechanisms as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('funding_mechanism')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fstatus">Status</label>
                    <select id="fstatus" name="status" class="input"><option value="">Todos</option>
                    <?php foreach ($statuses as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('status')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fpay">Pagamento</label>
                    <select id="fpay" name="payment_status" class="input"><option value="">Todos</option>
                    <?php foreach ($paymentStatuses as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('payment_status')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fyear">Ano</label><input type="number" id="fyear" name="project_year" class="input" min="2026" value="<?= (int)($filters['project_year']??0) ?: '' ?>" placeholder="2026"></div>
                <div><label for="fresp">Responsável</label>
                    <select id="fresp" name="responsible_user_id" class="input"><option value="">Todos</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)($filters['responsible_user_id']??0)===(int)$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                    </select></div>
            </div>

            <div class="filter-flags">
                <span class="filter-flags__title">Situação rápida</span>
                <div class="filter-checks">
                    <label class="check-inline"><input type="checkbox" name="awaiting_contribution" value="1" <?= !empty($filters['awaiting_contribution'])?'checked':'' ?>> Aguardando aporte</label>
                    <label class="check-inline"><input type="checkbox" name="overdue" value="1" <?= !empty($filters['overdue'])?'checked':'' ?>> Em atraso</label>
                    <label class="check-inline"><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived'])?'checked':'' ?>> Arquivados</label>
                </div>
            </div>

            <div class="filter-actions-row">
                <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="filter"></i> Filtrar</button>
                <a href="<?= e(app_url('/sponsors')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
        </form>

        <?php if ($items === []): ?>
            <div class="empty-state sponsor-empty-state">
                <p>Nenhum fechamento encontrado.</p>
                <?php if ($canCreate): ?><a href="<?= e($createUrl) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Registrar fechamento</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="sponsor-linked-list">
                    <thead>
                        <tr>
                            <th>Patrocinador</th><th>Empresa</th><th>Cota</th><th>Tipo</th><th>Mecanismo</th>
                            <th>Comprometido</th><th>Confirmado</th><th>Status</th><th>Pagamento</th><th>Responsável</th><th>Fechamento</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $s): ?>
                            <?php
                            $sid = (int) ($s['id'] ?? 0);
                            $st = (string) ($s['status'] ?? '');
                            $pay = (string) ($s['payment_status'] ?? '');
                            $overdue = $model && $model->isOverdue($s);
                            ?>
                            <tr class="<?= $overdue ? 'sponsor-overdue-row' : '' ?>">
                                <td><strong><?= e($s['sponsor_display_name'] ?? '') ?></strong></td>
                                <td><?= e($s['company_name'] ?? '—') ?></td>
                                <td><?= e($s['quota_snapshot_name'] ?? $s['quota_name'] ?? '—') ?></td>
                                <td><span class="sponsor-type"><?= e($sponsorshipTypes[$s['sponsorship_type'] ?? ''] ?? ($s['sponsorship_type'] ?? '')) ?></span></td>
                                <td><?= e($fundingMechanisms[$s['funding_mechanism'] ?? ''] ?? ($s['funding_mechanism'] ?? '')) ?></td>
                                <td class="sponsor-value money-value"><?= isset($s['committed_amount']) && $s['committed_amount'] !== null ? e(money_br($s['committed_amount'])) : '—' ?></td>
                                <td class="sponsor-value money-value"><?= isset($s['confirmed_amount']) && $s['confirmed_amount'] !== null ? e(money_br($s['confirmed_amount'])) : '—' ?></td>
                                <td><span class="sponsor-status badge-sponsor-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                                <td><span class="sponsor-payment-status badge-pay-<?= e($pay) ?>"><?= e($paymentStatuses[$pay] ?? $pay) ?></span></td>
                                <td><?= e($s['responsible_name'] ?? '—') ?></td>
                                <td><?= !empty($s['closed_at']) ? e(substr((string) $s['closed_at'], 0, 10)) : '—' ?></td>
                                <td style="text-align:right;"><a href="<?= e(app_url('/sponsors/' . $sid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
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
