<?php
$items        = $items ?? [];
$filters      = $filters ?? [];
$categories   = $categories ?? [];
$statuses     = $statuses ?? [];
$accessLevels = $accessLevels ?? [];
$model        = $model ?? null;
$companies    = $companies ?? [];
$contacts     = $contacts ?? [];
$opportunities = $opportunities ?? [];
$quotas       = $quotas ?? [];
$proposals    = $proposals ?? [];
$leads        = $leads ?? [];
$sponsors     = $sponsors ?? [];
$counterparts = $counterparts ?? [];
$contracts    = $contracts ?? [];
$users        = $users ?? [];
$page         = (int) ($page ?? 1);
$pages        = (int) ($pages ?? 1);
$total        = (int) ($total ?? 0);
$hasFilters   = !empty($hasFilters);
$canCreate    = can('documents.create');

$f = static fn (string $k, string $default = ''): string => (string) ($filters[$k] ?? $default);

$baseQuery = array_filter([
    'q' => $f('q'), 'company_id' => (int) ($filters['company_id'] ?? 0) ?: '',
    'contact_id' => (int) ($filters['contact_id'] ?? 0) ?: '',
    'opportunity_id' => (int) ($filters['opportunity_id'] ?? 0) ?: '',
    'quota_id' => (int) ($filters['quota_id'] ?? 0) ?: '',
    'proposal_id' => (int) ($filters['proposal_id'] ?? 0) ?: '',
    'lead_id' => (int) ($filters['lead_id'] ?? 0) ?: '',
    'sponsor_id' => (int) ($filters['sponsor_id'] ?? 0) ?: '',
    'counterpart_id' => (int) ($filters['counterpart_id'] ?? 0) ?: '',
    'contract_id' => (int) ($filters['contract_id'] ?? 0) ?: '',
    'category' => $f('category'), 'status' => $f('status'), 'access_level' => $f('access_level'),
    'responsible_user_id' => (int) ($filters['responsible_user_id'] ?? 0) ?: '',
    'expired' => !empty($filters['expired']) ? 1 : '',
    'valid_from' => $f('valid_from'), 'valid_to' => $f('valid_to'),
    'show_archived' => !empty($filters['show_archived']) ? 1 : '',
], static fn ($v): bool => $v !== '' && $v !== null);

$pageUrl   = static fn (int $p): string => app_url('/documents') . '?' . http_build_query(array_merge($baseQuery, ['page' => $p]));
$createUrl = app_url('/documents/create');
?>

<section class="section">
    <div class="container">
        <div class="page-head document-index-head">
            <div>
                <span class="kicker kicker-dark">CRM · Captação</span>
                <h1 class="h2-section">Documentos e arquivos</h1>
                <p class="page-sub"><?= $total ?> documento(s) encontrado(s).</p>
            </div>
            <?php if ($canCreate): ?>
                <div class="document-index-actions actions-row">
                    <a href="<?= e($createUrl) ?>" class="btn btn-yellow document-create-btn"><i data-lucide="plus"></i> Novo documento</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($hasFilters): ?>
            <div class="notice notice-warn document-filter-notice" style="margin-bottom:14px;">
                <p class="mb-0">
                    <i data-lucide="filter"></i> Filtros ativos.
                    <a href="<?= e(app_url('/documents')) ?>" class="link-strong">Limpar filtros</a>
                </p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?= e(app_url('/documents')) ?>" class="filter-box">
            <div class="filter-grid">
                <div class="filter-q"><label for="q">Busca</label><input type="text" id="q" name="q" value="<?= e($f('q')) ?>" placeholder="Título, descrição, arquivo, empresa, proposta"></div>
                <div><label for="fcompany">Empresa</label>
                    <select id="fcompany" name="company_id"><option value="">Todas</option>
                    <?php foreach ($companies as $co): ?><option value="<?= (int) $co['id'] ?>" <?= (int)($filters['company_id']??0)===(int)$co['id']?'selected':'' ?>><?= e($co['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fcontact">Contato</label>
                    <select id="fcontact" name="contact_id"><option value="">Todos</option>
                    <?php foreach ($contacts as $ct): ?><option value="<?= (int) $ct['id'] ?>" <?= (int)($filters['contact_id']??0)===(int)$ct['id']?'selected':'' ?>><?= e($ct['label']??'') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fopp">Oportunidade</label>
                    <select id="fopp" name="opportunity_id"><option value="">Todas</option>
                    <?php foreach ($opportunities as $op): ?><option value="<?= (int) $op['id'] ?>" <?= (int)($filters['opportunity_id']??0)===(int)$op['id']?'selected':'' ?>><?= e($op['label']??'') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fquota">Cota</label>
                    <select id="fquota" name="quota_id"><option value="">Todas</option>
                    <?php foreach ($quotas as $q): ?><option value="<?= (int) $q['id'] ?>" <?= (int)($filters['quota_id']??0)===(int)$q['id']?'selected':'' ?>><?= e($q['name']??'') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fproposal">Proposta</label>
                    <select id="fproposal" name="proposal_id"><option value="">Todas</option>
                    <?php foreach ($proposals as $pr): ?><option value="<?= (int) $pr['id'] ?>" <?= (int)($filters['proposal_id']??0)===(int)$pr['id']?'selected':'' ?>><?= e($pr['label']??'') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="flead">Lead</label>
                    <select id="flead" name="lead_id"><option value="">Todos</option>
                    <?php foreach ($leads as $ld): ?><option value="<?= (int) $ld['id'] ?>" <?= (int)($filters['lead_id']??0)===(int)$ld['id']?'selected':'' ?>><?= e($ld['label']??'') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fsponsor">Patrocinador</label>
                    <select id="fsponsor" name="sponsor_id"><option value="">Todos</option>
                    <?php foreach ($sponsors as $sp): ?><option value="<?= (int) $sp['id'] ?>" <?= (int)($filters['sponsor_id']??0)===(int)$sp['id']?'selected':'' ?>><?= e($sp['label']??'') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fcounterpart">Contrapartida</label>
                    <select id="fcounterpart" name="counterpart_id"><option value="">Todas</option>
                    <?php foreach ($counterparts as $cp): ?><option value="<?= (int) $cp['id'] ?>" <?= (int)($filters['counterpart_id']??0)===(int)$cp['id']?'selected':'' ?>><?= e($cp['label']??'') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fcontract">Contrato</label>
                    <select id="fcontract" name="contract_id"><option value="">Todos</option>
                    <?php foreach ($contracts as $ct): ?><option value="<?= (int) $ct['id'] ?>" <?= (int)($filters['contract_id']??0)===(int)$ct['id']?'selected':'' ?>><?= e($ct['label']??'') ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fcat">Categoria</label>
                    <select id="fcat" name="category"><option value="">Todas</option>
                    <?php foreach ($categories as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('category')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fstatus">Status</label>
                    <select id="fstatus" name="status"><option value="">Todos</option>
                    <?php foreach ($statuses as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('status')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="faccess">Nível de acesso</label>
                    <select id="faccess" name="access_level"><option value="">Todos</option>
                    <?php foreach ($accessLevels as $k=>$label): ?><option value="<?= e($k) ?>" <?= $f('access_level')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="fresp">Responsável</label>
                    <select id="fresp" name="responsible_user_id"><option value="">Todos</option>
                    <?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)($filters['responsible_user_id']??0)===(int)$u['id']?'selected':'' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label for="vfrom">Validade de</label><input type="date" id="vfrom" name="valid_from" value="<?= e($f('valid_from')) ?>"></div>
                <div><label for="vto">Validade até</label><input type="date" id="vto" name="valid_to" value="<?= e($f('valid_to')) ?>"></div>
                <div class="filter-checks">
                    <label><input type="checkbox" name="expired" value="1" <?= !empty($filters['expired'])?'checked':'' ?>> Vencidos</label>
                    <label><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived'])?'checked':'' ?>> Arquivados</label>
                </div>
            </div>
            <div class="actions-row">
                <button type="submit" class="btn btn-sm btn-yellow">Filtrar</button>
                <a href="<?= e(app_url('/documents')) ?>" class="btn btn-sm btn-outline">Limpar</a>
                <?php if ($canCreate): ?>
                    <a href="<?= e($createUrl) ?>" class="btn btn-sm btn-yellow"><i data-lucide="plus"></i> Novo documento</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($items === []): ?>
            <div class="empty-state document-empty-state">
                <p>Nenhum documento encontrado.</p>
                <?php if ($canCreate): ?>
                    <a href="<?= e($createUrl) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Cadastrar primeiro documento</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Título</th><th>Categoria</th><th>Status</th><th>Acesso</th><th>Empresa</th><th>Oportunidade</th><th>Proposta</th>
                        <th>Responsável</th><th>Validade</th><th>Tamanho</th><th>Versão</th><th>Criado</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($items as $doc): ?>
                        <?php
                        $did     = (int) $doc['id'];
                        $st      = (string) ($doc['status'] ?? '');
                        $cat     = (string) ($doc['category'] ?? '');
                        $access  = (string) ($doc['access_level'] ?? '');
                        $expired = $model && $model->isExpired($doc);
                        ?>
                        <tr class="<?= $expired ? 'document-expired-row' : '' ?>">
                            <td><strong><?= e($doc['title']) ?></strong></td>
                            <td><span class="badge-document document-category badge-document-<?= e($cat) ?>"><?= e($categories[$cat] ?? $cat) ?></span></td>
                            <td><span class="badge-document document-status badge-document-<?= e($st) ?>"><?= e($statuses[$st] ?? $st) ?></span></td>
                            <td><span class="badge-document document-access badge-access-<?= e($access) ?>"><?= e($accessLevels[$access] ?? $access) ?></span></td>
                            <td><?= e($doc['company_name'] ?? '—') ?></td>
                            <td><?= e($doc['opportunity_title'] ?? '—') ?></td>
                            <td><?= e($doc['proposal_title'] ?? '—') ?></td>
                            <td><?= e($doc['responsible_name'] ?? '—') ?></td>
                            <td class="<?= $expired ? 'overdue' : '' ?>"><?= e($doc['valid_until'] ?? '—') ?></td>
                            <td><?= $model ? e($model->formatSize($doc['size_bytes'] ?? 0)) : '—' ?></td>
                            <td>v<?= (int)($doc['version_number']??1) ?></td>
                            <td><?= e(substr((string)($doc['created_at']??''),0,16)) ?></td>
                            <td style="text-align:right;"><a href="<?= e(app_url('/documents/'.$did)) ?>" class="btn btn-sm btn-outline"><i data-lucide="eye"></i></a></td>
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
