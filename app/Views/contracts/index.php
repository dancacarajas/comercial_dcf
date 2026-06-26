<?php
$items = $items ?? [];
$filters = $filters ?? [];
$contractTypes = $contractTypes ?? [];
$fundingMechanisms = $fundingMechanisms ?? [];
$statuses = $statuses ?? [];
$reviewStatuses = $reviewStatuses ?? [];
$signatureStatuses = $signatureStatuses ?? [];
$model = $model ?? null;
$sponsors = $sponsors ?? [];
$companies = $companies ?? [];
$users = $users ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
$hasFilters = !empty($hasFilters);
$canCreate = can('contracts.create');

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$baseQuery = array_filter([
    'q'                   => $f('q'),
    'sponsor_id'          => (int) ($filters['sponsor_id'] ?? 0) ?: '',
    'company_id'          => (int) ($filters['company_id'] ?? 0) ?: '',
    'contract_type'       => $f('contract_type'),
    'funding_mechanism'   => $f('funding_mechanism'),
    'status'              => $f('status'),
    'review_status'       => $f('review_status'),
    'signature_status'    => $f('signature_status'),
    'responsible_user_id' => (int) ($filters['responsible_user_id'] ?? 0) ?: '',
    'start_from'          => $f('start_from'),
    'end_to'              => $f('end_to'),
    'expired'             => !empty($filters['expired']) ? 1 : '',
    'active_vigente'      => !empty($filters['active_vigente']) ? 1 : '',
    'awaiting_signature'  => !empty($filters['awaiting_signature']) ? 1 : '',
    'signed'              => !empty($filters['signed']) ? 1 : '',
    'show_archived'       => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static fn (int $p): string => app_url('/contracts') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
$createUrl = app_url('/contracts/create');
?>
<section class="section">
    <div class="container">
        <div class="page-head contract-index-head">
            <div>
                <span class="kicker kicker-dark">CRM · Contratos</span>
                <h1 class="h2-section">Contratos e Instrumentos de Formalização</h1>
                <p class="page-sub"><?= $total ?> contrato(s) encontrado(s).</p>
            </div>
            <?php if ($canCreate): ?>
                <div class="contract-actions actions-row">
                    <a href="<?= e($createUrl) ?>" class="btn btn-yellow"><i data-lucide="file-signature"></i> Novo contrato</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($hasFilters): ?>
            <div class="notice notice-warn contract-alert" style="margin-bottom:14px;">
                <p class="mb-0"><i data-lucide="filter"></i> Filtros ativos. <a href="<?= e(app_url('/contracts')) ?>" class="link-strong">Limpar filtros</a></p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?= e(app_url('/contracts')) ?>" class="filter-box filter-box--financial">
            <div class="filter-grid filter-grid--financial">
                <div class="filter-q"><label for="q">Busca</label><input type="text" id="q" name="q" class="input" value="<?= e($f('q')) ?>" placeholder="Título, número, patrocinador, empresa, notas"></div>
                <div><label for="fsponsor">Patrocinador</label>
                    <select id="fsponsor" name="sponsor_id" class="input"><option value="">Todos</option>
                    <?php foreach ($sponsors as $sp): ?><option value="<?= (int) $sp['id'] ?>" <?= (int)($filters['sponsor_id']??0)===(int)$sp['id']?'selected':'' ?>><?= e($sp['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fcompany">Empresa</label>
                    <select id="fcompany" name="company_id" class="input"><option value="">Todas</option>
                    <?php foreach ($companies as $co): ?><option value="<?= (int) $co['id'] ?>" <?= (int)($filters['company_id']??0)===(int)$co['id']?'selected':'' ?>><?= e($co['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="ftype">Tipo</label>
                    <select id="ftype" name="contract_type" class="input"><option value="">Todos</option>
                    <?php foreach ($contractTypes as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('contract_type')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fmech">Mecanismo</label>
                    <select id="fmech" name="funding_mechanism" class="input"><option value="">Todos</option>
                    <?php foreach ($fundingMechanisms as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('funding_mechanism')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fstatus">Status</label>
                    <select id="fstatus" name="status" class="input"><option value="">Todos</option>
                    <?php foreach ($statuses as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('status')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="freview">Revisão</label>
                    <select id="freview" name="review_status" class="input"><option value="">Todos</option>
                    <?php foreach ($reviewStatuses as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('review_status')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fsig">Assinatura</label>
                    <select id="fsig" name="signature_status" class="input"><option value="">Todos</option>
                    <?php foreach ($signatureStatuses as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('signature_status')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fresp">Responsável</label>
                    <select id="fresp" name="responsible_user_id" class="input"><option value="">Todos</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)($filters['responsible_user_id']??0)===(int)$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                    </select></div>
            </div>

            <div class="filter-subsection">
                <span class="filter-subsection__title">Vigência</span>
                <div class="filter-grid filter-grid--dates">
                    <div><label for="start_from">Início de</label><input type="date" id="start_from" name="start_from" class="input" value="<?= e($f('start_from')) ?>"></div>
                    <div><label for="end_to">Término até</label><input type="date" id="end_to" name="end_to" class="input" value="<?= e($f('end_to')) ?>"></div>
                </div>
            </div>

            <div class="filter-flags">
                <span class="filter-flags__title">Situação rápida</span>
                <div class="filter-checks">
                    <label class="check-inline"><input type="checkbox" name="expired" value="1" <?= !empty($filters['expired'])?'checked':'' ?>> Vencidos</label>
                    <label class="check-inline"><input type="checkbox" name="active_vigente" value="1" <?= !empty($filters['active_vigente'])?'checked':'' ?>> Vigentes</label>
                    <label class="check-inline"><input type="checkbox" name="awaiting_signature" value="1" <?= !empty($filters['awaiting_signature'])?'checked':'' ?>> Aguardando assinatura</label>
                    <label class="check-inline"><input type="checkbox" name="signed" value="1" <?= !empty($filters['signed'])?'checked':'' ?>> Assinados</label>
                    <label class="check-inline"><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived'])?'checked':'' ?>> Arquivados</label>
                </div>
            </div>

            <div class="filter-actions-row">
                <button type="submit" class="btn btn-sm btn-yellow"><i data-lucide="filter"></i> Filtrar</button>
                <a href="<?= e(app_url('/contracts')) ?>" class="btn btn-sm btn-outline">Limpar</a>
            </div>
        </form>

        <?php if ($items === []): ?>
            <div class="empty-state contract-empty-state">
                <p>Nenhum contrato encontrado.</p>
                <?php if ($canCreate): ?><a href="<?= e($createUrl) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Novo contrato</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="contract-linked-list">
                    <thead>
                        <tr>
                            <th>Título</th><th>Número</th><th>Patrocinador</th><th>Empresa</th><th>Tipo</th><th>Mecanismo</th>
                            <th>Status</th><th>Revisão</th><th>Assinatura</th><th>Vigência</th><th>Valor</th><th>Responsável</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $ct): ?>
                            <?php
                            $cid = (int) ($ct['id'] ?? 0);
                            $st = (string) ($ct['status'] ?? '');
                            $rev = (string) ($ct['review_status'] ?? '');
                            $sig = (string) ($ct['signature_status'] ?? '');
                            $expired = $model && $model->isExpired($ct);
                            ?>
                            <tr class="<?= $expired ? 'contract-expired-row' : '' ?>">
                                <td><strong><?= e($ct['title'] ?? '') ?></strong></td>
                                <td><?= e($ct['contract_number'] ?? '—') ?></td>
                                <td><?= e($ct['sponsor_name'] ?? '—') ?></td>
                                <td><?= e($ct['company_name'] ?? '—') ?></td>
                                <td><span class="contract-type"><?= e($contractTypes[$ct['contract_type'] ?? ''] ?? '') ?></span></td>
                                <td><?= e($fundingMechanisms[$ct['funding_mechanism'] ?? ''] ?? '') ?></td>
                                <td><span class="contract-status badge-ct-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                                <td><span class="contract-review-status badge-rev-<?= e($rev) ?>"><?= e($reviewStatuses[$rev] ?? $rev) ?></span></td>
                                <td><span class="contract-signature-status badge-sig-<?= e($sig) ?>"><?= e($signatureStatuses[$sig] ?? $sig) ?></span></td>
                                <td class="<?= $expired ? 'overdue' : '' ?>"><?= e($ct['start_date'] ?? '—') ?> — <?= e($ct['end_date'] ?? '—') ?></td>
                                <td class="contract-value money-value"><?= isset($ct['formalized_value']) && $ct['formalized_value'] !== null ? e(money_br($ct['formalized_value'])) : '—' ?></td>
                                <td><?= e($ct['responsible_name'] ?? '—') ?></td>
                                <td style="text-align:right;"><a href="<?= e(app_url('/contracts/' . $cid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
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
