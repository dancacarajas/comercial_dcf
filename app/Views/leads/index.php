<?php
$items = $items ?? []; $filters = $filters ?? []; $statuses = $statuses ?? [];
$origins = $origins ?? []; $owners = $owners ?? [];
$page = (int)($page ?? 1); $pages = (int)($pages ?? 1); $total = (int)($total ?? 0);
$f = static fn(string $k, string $d=''): string => (string)($filters[$k] ?? $d);
$baseQuery = array_filter([
    'q'=>$f('q'),'status'=>$f('status'),'origin_page'=>$f('origin_page'),'interest'=>$f('interest'),
    'assigned_user_id'=>(int)($filters['assigned_user_id']??0)>0?(int)$filters['assigned_user_id']:'',
    'contact_consent'=>$filters['contact_consent']??'','converted'=>!empty($filters['converted'])?1:'',
    'not_converted'=>!empty($filters['not_converted'])?1:'','show_archived'=>!empty($filters['show_archived'])?1:'',
    'date_from'=>$f('date_from'),'date_to'=>$f('date_to'),
], fn($v)=>$v!==''&&$v!==null);
$pageUrl = fn(int $p) => app_url('/leads').'?'.http_build_query(array_merge($baseQuery,['page'=>$p]));
?>
<section class="section"><div class="container">
<div class="page-head"><div><span class="kicker kicker-dark">CRM · Captação</span><h1 class="h2-section">Leads do site</h1>
<p class="page-sub">Entradas dos formulários públicos de patrocínio.</p></div>
<?php if (can('leads.create')): ?><a href="<?= e(app_url('/leads/create')) ?>" class="btn btn-yellow"><i data-lucide="plus"></i> Novo lead</a><?php endif; ?>
</div>
<form method="get" action="<?= e(app_url('/leads')) ?>" class="filter-box">
<div class="filter-grid">
<div class="filter-q"><label for="q">Busca</label><input id="q" name="q" value="<?= e($f('q')) ?>" placeholder="Nome, empresa, e-mail, WhatsApp..."></div>
<div><label for="fstatus">Status</label><select id="fstatus" name="status"><option value="">Todos</option>
<?php foreach ($statuses as $k=>$l): ?><option value="<?= e($k) ?>" <?= $f('status')===$k?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
<div><label for="forigin">Origem</label><select id="forigin" name="origin_page"><option value="">Todas</option>
<?php foreach ($origins as $o): ?><option value="<?= e($o) ?>" <?= $f('origin_page')===$o?'selected':'' ?>><?= e($o) ?></option><?php endforeach; ?></select></div>
<div><label for="fowner">Responsável</label><select id="fowner" name="assigned_user_id"><option value="">Todos</option>
<?php foreach ($owners as $o): ?><option value="<?= (int)$o['id'] ?>" <?= (int)($filters['assigned_user_id']??0)===(int)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
<div><label for="dff">De</label><input type="date" id="dff" name="date_from" value="<?= e($f('date_from')) ?>"></div>
<div><label for="dft">Até</label><input type="date" id="dft" name="date_to" value="<?= e($f('date_to')) ?>"></div>
</div>
<div class="filter-flags">
<label class="check-inline"><input type="checkbox" name="converted" value="1" <?= !empty($filters['converted'])?'checked':'' ?>> Convertidos</label>
<label class="check-inline"><input type="checkbox" name="not_converted" value="1" <?= !empty($filters['not_converted'])?'checked':'' ?>> Não convertidos</label>
<label class="check-inline"><input type="checkbox" name="contact_consent" value="1" <?= ($filters['contact_consent']??'')==='1'?'checked':'' ?>> Com consentimento</label>
<label class="check-inline"><input type="checkbox" name="show_archived" value="1" <?= !empty($filters['show_archived'])?'checked':'' ?>> Exibir arquivados</label>
<div class="filter-actions"><button type="submit" class="btn btn-sm btn-yellow">Filtrar</button>
<a href="<?= e(app_url('/leads')) ?>" class="btn btn-sm btn-outline">Limpar</a></div>
</div></form>
<p class="result-count"><?= $total ?> lead(s) encontrado(s).</p>
<?php if ($items===[]): ?><div class="empty-state"><span class="card-icon"><i data-lucide="inbox"></i></span><h3 class="h3-card">Nenhum lead encontrado</h3></div>
<?php else: ?>
<div class="table-wrap"><table><thead><tr>
<th>Nome</th><th>Empresa</th><th>E-mail</th><th>WhatsApp</th><th>Interesse</th><th>Origem</th><th>Status</th><th>Responsável</th><th>Entrada</th><th></th>
</tr></thead><tbody>
<?php foreach ($items as $it): $id=(int)$it['id']; $st=(string)($it['status']??''); ?>
<tr class="<?= !empty($it['archived_at'])?'row-archived':'' ?>">
<td><strong><?= e($it['name']) ?></strong></td>
<td><?= e($it['company_name']??'')?:'—' ?></td>
<td><?= e($it['email']??'')?:'—' ?></td>
<td><?= e($it['whatsapp']??'')?:'—' ?></td>
<td><?= e($it['interest']??'')?:'—' ?></td>
<td><span class="lead-source"><?= e($it['origin_page']??'')?:'—' ?></span></td>
<td><span class="badge-lead badge-lead-<?= e($st) ?>"><?= e($statuses[$st]??$st) ?></span></td>
<td><?= e($it['assigned_name']??'')?:'—' ?></td>
<td><?= e($it['created_at']??'') ?></td>
<td><a href="<?= e(app_url('/leads/'.$id)) ?>" class="btn btn-sm btn-outline">Ver</a></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($pages>1): ?><nav class="pagination">
<?php if ($page>1): ?><a href="<?= e($pageUrl($page-1)) ?>" class="page-link">Anterior</a><?php endif; ?>
<span class="page-info">Página <?= $page ?> de <?= $pages ?></span>
<?php if ($page<$pages): ?><a href="<?= e($pageUrl($page+1)) ?>" class="page-link">Próxima</a><?php endif; ?>
</nav><?php endif; endif; ?>
</div></section>
