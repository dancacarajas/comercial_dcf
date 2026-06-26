<?php
$items       = $items ?? [];
$filters     = $filters ?? [];
$types       = $types ?? [];
$statuses    = $statuses ?? [];
$model       = $model ?? null;
$companies   = $companies ?? [];
$contacts    = $contacts ?? [];
$opportunities = $opportunities ?? [];
$quotas      = $quotas ?? [];
$users       = $users ?? [];
$page        = (int) ($page ?? 1);
$pages       = (int) ($pages ?? 1);
$total       = (int) ($total ?? 0);
$hasFilters  = !empty($hasFilters);
$canCreate   = can('proposals.create');

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$baseQuery = array_filter([
    'q' => $f('q'), 'company_id' => (int) ($filters['company_id'] ?? 0) ?: '',
    'contact_id' => (int) ($filters['contact_id'] ?? 0) ?: '',
    'opportunity_id' => (int) ($filters['opportunity_id'] ?? 0) ?: '',
    'quota_id' => (int) ($filters['quota_id'] ?? 0) ?: '',
    'type' => $f('type'), 'status' => $f('status'),
    'responsible_user_id' => (int) ($filters['responsible_user_id'] ?? 0) ?: '',
    'sent' => !empty($filters['sent']) ? 1 : '',
    'not_sent' => !empty($filters['not_sent']) ? 1 : '',
    'expired' => !empty($filters['expired']) ? 1 : '',
    'valid_from' => $f('valid_from'), 'valid_to' => $f('valid_to'),
    'show_archived' => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl = static fn (int $p): string => app_url('/proposals') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
$createUrl = app_url('/proposals/create');
?>

<section class="section">
    <div class="container">
        <div class="page-head proposal-index-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section">Propostas comerciais</h1>
                <p class="page-sub"><?= $total ?> proposta(s) encontrada(s).</p>
            </div>
            <?php if ($canCreate): ?>
                <div class="proposal-index-actions actions-row">
                    <a href="<?= e($createUrl) ?>" class="btn btn-yellow proposal-create-btn"><i data-lucide="plus"></i> Nova proposta</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($hasFilters): ?>
            <div class="notice notice-warn proposal-filter-notice" style="margin-bottom:14px;">
                <p class="mb-0">
                    <i data-lucide="filter"></i> Filtros ativos.
                    <a href="<?= e(app_url('/proposals')) ?>" class="link-strong">Limpar filtros</a>
                </p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?= e(app_url('/proposals')) ?>" class="filter-box">
            <div class="filter-grid">
                <div class="filter-q"><label for="q">Busca</label><input type="text" id="q" name="q" value="<?= e($f('q')) ?>" placeholder="Título, empresa, contato, observações"></div>
                <div><label for="fcompany">Empresa</label>
                    <select id="fcompany" name="company_id"><option value="">Todas</option>
                    <?php foreach ($companies as $co): ?><option value="<?= (int) $co['id'] ?>" <?= (int)($filters['company_id']??0)===(int)$co['id']?'selected':'' ?>><?= e($co['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fcontact">Contato</label>
                    <select id="fcontact" name="contact_id"><option value="">Todos</option>
                    <?php foreach ($contacts as $ct): ?><option value="<?= (int) $ct['id'] ?>" <?= (int)($filters['contact_id']??0)===(int)$ct['id']?'selected':'' ?>><?= e($ct['label']??'') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="ftype">Tipo</label>
                    <select id="ftype" name="type"><option value="">Todos</option>
                    <?php foreach ($types as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('type')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fstatus">Status</label>
                    <select id="fstatus" name="status"><option value="">Todos</option>
                    <?php foreach ($statuses as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('status')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fresp">Responsável</label>
                    <select id="fresp" name="responsible_user_id"><option value="">Todos</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)($filters['responsible_user_id']??0)===(int)$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fopp">Oportunidade</label>
                    <select id="fopp" name="opportunity_id"><option value="">Todas</option>
                    <?php foreach ($opportunities as $op): ?><option value="<?= (int)$op['id'] ?>" <?= (int)($filters['opportunity_id']??0)===(int)$op['id']?'selected':'' ?>><?= e($op['label']??'') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fquota">Cota</label>
                    <select id="fquota" name="quota_id"><option value="">Todas</option>
                    <?php foreach ($quotas as $q): ?><option value="<?= (int)$q['id'] ?>" <?= (int)($filters['quota_id']??0)===(int)$q['id']?'selected':'' ?>><?= e($q['name']??'') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="vfrom">Validade de</label><input type="date" id="vfrom" name="valid_from" value="<?= e($f('valid_from')) ?>"></div>
                <div><label for="vto">Validade até</label><input type="date" id="vto" name="valid_to" value="<?= e($f('valid_to')) ?>"></div>
                <div class="filter-checks">
                    <label><input type="checkbox" name="sent" value="1" <?= !empty($filters['sent'])?'checked':'' ?>> Enviadas</label>
                    <label><input type="checkbox" name="not_sent" value="1" <?= !empty($filters['not_sent'])?'checked':'' ?>> Não enviadas</label>
                    <label><input type="checkbox" name="expired" value="1" <?= !empty($filters['expired'])?'checked':'' ?>> Vencidas</label>
                    <label><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived'])?'checked':'' ?>> Arquivadas</label>
                </div>
            </div>
            <div class="actions-row">
                <button type="submit" class="btn btn-sm btn-yellow">Filtrar</button>
                <a href="<?= e(app_url('/proposals')) ?>" class="btn btn-sm btn-outline">Limpar</a>
                <?php if ($canCreate): ?>
                    <a href="<?= e($createUrl) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Nova proposta</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($items === []): ?>
            <div class="empty-state proposal-empty-state">
                <p>Nenhuma proposta encontrada.</p>
                <?php if ($canCreate): ?>
                    <a href="<?= e($createUrl) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Criar primeira proposta</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Título</th><th>Empresa</th><th>Contato</th><th>Oportunidade</th><th>Cota</th>
                        <th>Tipo</th><th>Valor</th><th>Versão</th><th>Status</th><th>Responsável</th><th>Validade</th><th>Envio</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($items as $p): ?>
                        <?php
                        $pid = (int) $p['id'];
                        $st  = (string) ($p['status'] ?? '');
                        $expired = $model && $model->isExpired($p);
                        ?>
                        <tr class="<?= $expired ? 'proposal-expired-row' : '' ?>">
                            <td><strong><?= e($p['title']) ?></strong></td>
                            <td><?= e($p['company_name'] ?? '—') ?></td>
                            <td><?= e($p['contact_name'] ?? '—') ?></td>
                            <td><?= e($p['opportunity_title'] ?? '—') ?></td>
                            <td><?= e($p['quota_name'] ?? '—') ?></td>
                            <td><span class="badge-proposal badge-proposal-type"><?= e($types[$p['type']??''] ?? $p['type']??'') ?></span></td>
                            <td class="money-value"><?= $p['proposed_value'] !== null ? e(money_br($p['proposed_value'])) : '—' ?></td>
                            <td><span class="proposal-version">v<?= (int)($p['version_number']??1) ?></span></td>
                            <td><span class="badge-proposal proposal-status badge-proposal-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                            <td><?= e($p['responsible_name'] ?? '—') ?></td>
                            <td class="<?= $expired ? 'overdue' : '' ?>"><?= e($p['valid_until'] ?? '—') ?></td>
                            <td><?= !empty($p['sent_at']) ? e(substr((string)$p['sent_at'],0,16)) : '—' ?></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/proposals/'.$pid)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
                <nav class="pagination" aria-label="Paginação">
                    <?php if ($page > 1): ?><a href="<?= e($pageUrl($page-1)) ?>" class="page-link">Anterior</a><?php endif; ?>
                    <span class="page-info">Página <?= $page ?> de <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a href="<?= e($pageUrl($page+1)) ?>" class="page-link">Próxima</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
