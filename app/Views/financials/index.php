<?php
$items = $items ?? [];
$filters = $filters ?? [];
$entryTypes = $entryTypes ?? [];
$fundingMechanisms = $fundingMechanisms ?? [];
$paymentMethods = $paymentMethods ?? [];
$statuses = $statuses ?? [];
$fiscalStatuses = $fiscalStatuses ?? [];
$model = $model ?? null;
$sponsors = $sponsors ?? [];
$contracts = $contracts ?? [];
$companies = $companies ?? [];
$users = $users ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$hasFilters = !empty($hasFilters);
$canCreate = can('financials.create');

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$baseQuery = array_filter([
    'q'                      => $f('q'),
    'sponsor_id'             => (int) ($filters['sponsor_id'] ?? 0) ?: '',
    'contract_id'            => (int) ($filters['contract_id'] ?? 0) ?: '',
    'company_id'             => (int) ($filters['company_id'] ?? 0) ?: '',
    'contact_id'             => (int) ($filters['contact_id'] ?? 0) ?: '',
    'opportunity_id'         => (int) ($filters['opportunity_id'] ?? 0) ?: '',
    'proposal_id'            => (int) ($filters['proposal_id'] ?? 0) ?: '',
    'quota_id'               => (int) ($filters['quota_id'] ?? 0) ?: '',
    'entry_type'             => $f('entry_type'),
    'funding_mechanism'      => $f('funding_mechanism'),
    'payment_method'         => $f('payment_method'),
    'status'                 => $f('status'),
    'fiscal_document_status' => $f('fiscal_document_status'),
    'responsible_user_id'    => (int) ($filters['responsible_user_id'] ?? 0) ?: '',
    'due_from'               => $f('due_from'),
    'due_to'                 => $f('due_to'),
    'received_from'          => $f('received_from'),
    'received_to'            => $f('received_to'),
    'overdue'                => !empty($filters['overdue']) ? 1 : '',
    'received'               => !empty($filters['received']) ? 1 : '',
    'partial'                => !empty($filters['partial']) ? 1 : '',
    'reconciled'             => !empty($filters['reconciled']) ? 1 : '',
    'pending'                => !empty($filters['pending']) ? 1 : '',
    'show_archived'          => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static fn (int $p): string => app_url('/financials') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
$createUrl = app_url('/financials/create');
?>
<section class="section">
    <div class="container">
        <div class="page-head financial-index-head">
            <div>
                <span class="kicker kicker-dark">CRM · Financeiro</span>
                <h1 class="h2-section">Financeiro Detalhado / Parcelas</h1>
                <p class="page-sub"><?= $total ?> lançamento(s) encontrado(s).</p>
            </div>
            <?php if ($canCreate): ?>
                <div class="financial-actions actions-row">
                    <a href="<?= e($createUrl) ?>" class="btn btn-yellow"><i data-lucide="wallet"></i> Novo lançamento</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($hasFilters): ?>
            <div class="notice notice-warn financial-alert" style="margin-bottom:14px;">
                <p class="mb-0"><i data-lucide="filter"></i> Filtros ativos. <a href="<?= e(app_url('/financials')) ?>" class="link-strong">Limpar filtros</a></p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?= e(app_url('/financials')) ?>" class="filter-box filter-box--financial">
            <div class="filter-grid filter-grid--financial">
                <div class="filter-q"><label for="q">Busca</label><input type="text" id="q" name="q" class="input" value="<?= e($f('q')) ?>" placeholder="Título, número, patrocinador, contrato, referências, notas"></div>
                <div><label for="fsponsor">Patrocinador</label>
                    <select id="fsponsor" name="sponsor_id" class="input"><option value="">Todos</option>
                    <?php foreach ($sponsors as $sp): ?><option value="<?= (int) $sp['id'] ?>" <?= (int)($filters['sponsor_id']??0)===(int)$sp['id']?'selected':'' ?>><?= e($sp['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fcontract">Contrato</label>
                    <select id="fcontract" name="contract_id" class="input"><option value="">Todos</option>
                    <?php foreach ($contracts as $cn): ?><option value="<?= (int) $cn['id'] ?>" <?= (int)($filters['contract_id']??0)===(int)$cn['id']?'selected':'' ?>><?= e($cn['label'] ?? '') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fcompany">Empresa</label>
                    <select id="fcompany" name="company_id" class="input"><option value="">Todas</option>
                    <?php foreach ($companies as $co): ?><option value="<?= (int) $co['id'] ?>" <?= (int)($filters['company_id']??0)===(int)$co['id']?'selected':'' ?>><?= e($co['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="ftype">Tipo</label>
                    <select id="ftype" name="entry_type" class="input"><option value="">Todos</option>
                    <?php foreach ($entryTypes as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('entry_type')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fmech">Mecanismo</label>
                    <select id="fmech" name="funding_mechanism" class="input"><option value="">Todos</option>
                    <?php foreach ($fundingMechanisms as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('funding_mechanism')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fpay">Pagamento</label>
                    <select id="fpay" name="payment_method" class="input"><option value="">Todos</option>
                    <?php foreach ($paymentMethods as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('payment_method')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fstatus">Status</label>
                    <select id="fstatus" name="status" class="input"><option value="">Todos</option>
                    <?php foreach ($statuses as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('status')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="ffiscal">Status fiscal</label>
                    <select id="ffiscal" name="fiscal_document_status" class="input"><option value="">Todos</option>
                    <?php foreach ($fiscalStatuses as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('fiscal_document_status')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fresp">Responsável</label>
                    <select id="fresp" name="responsible_user_id" class="input"><option value="">Todos</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)($filters['responsible_user_id']??0)===(int)$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                    </select></div>
            </div>

            <div class="filter-subsection">
                <span class="filter-subsection__title">Períodos</span>
                <div class="filter-grid filter-grid--dates">
                    <div><label for="due_from">Vencimento de</label><input type="date" id="due_from" name="due_from" class="input" value="<?= e($f('due_from')) ?>"></div>
                    <div><label for="due_to">Vencimento até</label><input type="date" id="due_to" name="due_to" class="input" value="<?= e($f('due_to')) ?>"></div>
                    <div><label for="received_from">Recebimento de</label><input type="date" id="received_from" name="received_from" class="input" value="<?= e($f('received_from')) ?>"></div>
                    <div><label for="received_to">Recebimento até</label><input type="date" id="received_to" name="received_to" class="input" value="<?= e($f('received_to')) ?>"></div>
                </div>
            </div>

            <div class="filter-flags">
                <span class="filter-flags__title">Situação rápida</span>
                <div class="filter-checks">
                    <label class="check-inline"><input type="checkbox" name="overdue" value="1" <?= !empty($filters['overdue'])?'checked':'' ?>> Atrasados</label>
                    <label class="check-inline"><input type="checkbox" name="received" value="1" <?= !empty($filters['received'])?'checked':'' ?>> Recebidos</label>
                    <label class="check-inline"><input type="checkbox" name="partial" value="1" <?= !empty($filters['partial'])?'checked':'' ?>> Parciais</label>
                    <label class="check-inline"><input type="checkbox" name="reconciled" value="1" <?= !empty($filters['reconciled'])?'checked':'' ?>> Conciliados</label>
                    <label class="check-inline"><input type="checkbox" name="pending" value="1" <?= !empty($filters['pending'])?'checked':'' ?>> Pendentes</label>
                    <label class="check-inline"><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived'])?'checked':'' ?>> Arquivados</label>
                </div>
            </div>

            <div class="filter-actions-row">
                <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="filter"></i> Filtrar</button>
                <a href="<?= e(app_url('/financials')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
        </form>

        <?php if ($items === []): ?>
            <div class="empty-state financial-empty-state">
                <p>Nenhum lançamento financeiro encontrado.</p>
                <?php if ($canCreate): ?><a href="<?= e($createUrl) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Novo lançamento</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="financial-linked-list">
                    <thead>
                        <tr>
                            <th>Título</th><th>Número</th><th>Patrocinador</th><th>Contrato</th><th>Empresa</th>
                            <th>Tipo</th><th>Mecanismo</th><th>Pagamento</th><th>Previsto</th><th>Recebido</th><th>Saldo</th>
                            <th>Vencimento</th><th>Status</th><th>Fiscal</th><th>Responsável</th><th>Comprovante</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $fe): ?>
                            <?php
                            $fid = (int) ($fe['id'] ?? 0);
                            $st = (string) ($fe['status'] ?? '');
                            $fiscal = (string) ($fe['fiscal_document_status'] ?? '');
                            $overdue = $model && $model->isOverdue($fe);
                            ?>
                            <tr class="<?= $overdue ? 'financial-overdue' : '' ?>">
                                <td><strong><?= e($fe['title'] ?? '') ?></strong></td>
                                <td><?= e($fe['entry_number'] ?? '—') ?></td>
                                <td><?= e($fe['sponsor_name'] ?? '—') ?></td>
                                <td><?= e($fe['contract_title'] ?? ($fe['contract_number'] ?? '—')) ?></td>
                                <td><?= e($fe['company_name'] ?? '—') ?></td>
                                <td><span class="financial-type"><?= e($entryTypes[$fe['entry_type'] ?? ''] ?? '') ?></span></td>
                                <td><?= e($fundingMechanisms[$fe['funding_mechanism'] ?? ''] ?? '') ?></td>
                                <td><?= e($paymentMethods[$fe['payment_method'] ?? ''] ?? '') ?></td>
                                <td class="financial-value money-value"><?= isset($fe['planned_amount']) ? e(money_br($fe['planned_amount'])) : '—' ?></td>
                                <td class="financial-received money-value"><?= isset($fe['received_amount']) ? e(money_br($fe['received_amount'])) : '—' ?></td>
                                <td class="financial-balance money-value"><?= isset($fe['remaining_amount']) ? e(money_br($fe['remaining_amount'])) : '—' ?></td>
                                <td class="<?= $overdue ? 'overdue' : '' ?>"><?= e($fe['due_date'] ?? '—') ?></td>
                                <td><span class="financial-status badge-fin-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                                <td><span class="financial-fiscal-status badge-fiscal-<?= e($fiscal) ?>"><?= e($fiscalStatuses[$fiscal] ?? $fiscal) ?></span></td>
                                <td><?= e($fe['responsible_name'] ?? '—') ?></td>
                                <td><?php if (!empty($fe['proof_document_id'])): ?><i data-lucide="paperclip" title="<?= e($fe['proof_document_title'] ?? '') ?>"></i><?php else: ?>—<?php endif; ?></td>
                                <td style="text-align:right;"><a href="<?= e(app_url('/financials/' . $fid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
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
